<?php

namespace App\Http\Controllers\APi;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderItemsResource;
use App\Http\Resources\OrderResource;
use App\Mail\PaymentConfirmed;
use App\Mail\RefundConfirmed;
use App\Models\IdempotencyKey;
use App\Models\Items;
use App\Models\LedgerEntries;
use App\Models\OrderItems;
use App\Models\Orders;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

class OrderManagementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Request()->user();
        $orders = Orders::where('user_id', $user->id)->get();

        // $orderitems = collect();
        // foreach ($orders as $order){
        //     $data = OrderItems::where('order_id', $order->id)->get();
        //     $orderitems = $orderitems->merge($data);
        // }

        $orderIds = $orders->pluck('id');
        $orderitems = OrderItems::whereIn('order_id', $orderIds)->get();

        return response()->json([
            'Order Details' => OrderItemsResource::collection($orderitems),
        ]);

    }

    public function reciept()
    {
        $orders = Orders::where('user_id', Auth::user()->id)->get();
        return response()->json([
            'Orders' => OrderResource::collection($orders),
        ]);
    }

    public function checkout(Request $request)
    {

        // Keep track of the database
        DB::beginTransaction();

        // -----------------------creaate and pay for an order-----------------------
        //Get all ids from Item to validate request
        $valid_ids = Items::pluck('id');

        //Validate the array, not single fields
        $validated = $request->validate([
            'order_items' => ['required', 'array', 'min:1'],
            'order_items.*.item_id' => ['required', 'integer', 'min:0', Rule::in($valid_ids)],
            'order_items.*.quantity' => ['required', 'integer', 'min:0'],
        ]);


        // Loop through the validated orderitems array to create orderitems
        $total = 0;
        $orderItemIds = [];
        foreach ($validated['order_items'] as $order_item){
            $item = Items::find($order_item['item_id']);

            // check if there's enough of the item available
            if($order_item['quantity'] > $item->quantity){
                return response()->json([
                    "Error" => "Insufficient quantity available",
                    "Message" => "The amount of $item->name purchaseable: $item->quantity"
                    ], 400);
            }

            // update the item instance with new quantity available in stock
            $new_quantity = $item->quantity - $order_item['quantity'];
            $item->update(['quantity' => $new_quantity]);

            $sub_total = $order_item['quantity'] * $item->unit_price;
            $total += $sub_total;

            $data = OrderItems::create([
                'item_id' => $item->id,
                'quantity' => $order_item['quantity'],
                'unit_price' => $item->unit_price,
                'sub_total' => $sub_total,
            ]);
            $orderItemIds[] = $data->id;
        }

        // -----------------get the orderitems just created------------------
        $orderItems = OrderItems::whereIn('id', $orderItemIds)->get();
        $lineItems = [];

        //-----------------------Create the order--------------------
        $order = Orders::create([
            'user_id' => $request->user()->id,
            'status' => 'unpaid',
            'total_price' => $total,
        ]);

        // update orderitems' user_id
        foreach($orderItems as $orderItem){
            $orderItem->update(['order_id' => $order->id]);
        }

        // Loop through the orderitems array for the stripe server
        foreach($orderItems as $orderItem){
            $item = Items::find($orderItem->item_id);
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => $item->name,
                    ],
                    'unit_amount' => $orderItem->unit_price*100,
                ],
                'quantity' => $orderItem->quantity,
            ];
        }

        // get the stripe secret key
        $stripe = new StripeClient(config('services.stripe.secret'));

        try {
            // create stripe session
            $session = $stripe->checkout->sessions->create([
            'line_items' => $lineItems,
            'metadata' => [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
            ],
            'mode' => 'payment',
            'success_url' => route('checkout.success'). "?session_id={CHECKOUT_SESSION_ID}",
            'cancel_url' => route('checkout.cancel'),
            'expires_at' => now()->addMinutes(30)->timestamp,
            ]);

            DB::commit();
            // return checkout url
            return response()->json(['checkout_url' => $session->url])->setEncodingOptions(JSON_UNESCAPED_SLASHES);

        } catch (\Stripe\Exception\ApiConnectionException $e) {
            DB::rollBack();
            throw $e;
            }

    }

    public function success(Request $request)
    {

        $stripe = new StripeClient(config('services.stripe.secret'));

        $session_id = $request->query('session_id');
        if(!$session_id){
            return response()->json([
                'status code' => 400,
                'message' => 'Missing session ID.',
            ], 400);
        }

        try {
            $session = $stripe->checkout->sessions->retrieve($session_id);
            $customerName = $session->customer_details->name ?? 'Customer';

            $order = Orders::find($session->metadata->order_id);
            if (!$order) {
                return response()->json([
                    'status code' => 400,
                    'message' => 'Not Found.',
                ], 400);
            }

            if(!($order->session_id) && !($order->payment_intent_id)){
                OrderItems::where('order_id', $session->metadata->order_id)->delete();
                Orders::where('id', $session->metadata->order_id)->delete();
                IdempotencyKey::where('user_id', $session->metadata->user_id)->orderBy('id', 'desc')->first()->delete();
                return response()->json([
                    'Error' => 'Set up local listener and try again. '
                ]);
            }

            if($order->status === 'unpaid'){
                return response()->json("Thanks for your orders, $customerName!", 200);
            }

            return response()->json("Thanks for your order, $customerName!", 200);

        } catch (\Throwable $e) {
            return response()->json([
                'status code' => 400,
                'error' => $e->getMessage(),
            ], 400)->setEncodingOptions(JSON_UNESCAPED_SLASHES);
        }

    }

    public function cancel()
    {
        echo "<h1>Checkout was cancelled.</h1>";
    }

    public function webhook(Request $request)
{
    $endpointSecret = config('services.stripe.webhook_secret');
    $payload = $request->getContent();
    $sigHeader = $request->header('Stripe-Signature');

    try {
        $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
    } catch (\UnexpectedValueException | SignatureVerificationException $e) {
        return response('', 400);
    }

    switch ($event->type) {
        case 'checkout.session.completed':
            $session = $event->data->object;
            $order = Orders::where('id', $session->metadata->order_id)
                ->lockForUpdate()
                ->first();
            if (!$order || $order->status === 'paid') {
                return response('', 400); // no matching order yet, or already processed — idempotent no-op
            }

            $order->update([
                'status' => 'paid',
                'session_id' => $session->id,
                'payment_intent_id' => $session->payment_intent
                ]);

            // -----------------------update ledger------------------------
            $userId = $order->user_id;

            $last_entry = LedgerEntries::where('user_id', $userId)->orderBy('id', 'desc')->first();
            $payment = $last_entry->payment ?? 0;
            $adjustment = $last_entry->adjustment ?? 0;

            if($order->status === 'paid'){
                $payment += $order->total_price;
                $adjustment += $order->total_price;
            }

            $ledger_entry = new LedgerEntries();
            $ledger_entry->user_id = $userId;
            $ledger_entry->payment = $payment;
            $ledger_entry->refund = $last_entry->refund ?? 0;
            $ledger_entry->adjustment = $adjustment;
            $ledger_entry->save();

            //send email confirming payment
            $currency = $event->data->object->currency;
            $paymentMethodLabel = 'visa';
            Mail::to($order->user)->queue(
                new PaymentConfirmed($order, $currency, $paymentMethodLabel)
            );

            break;

        case 'charge.refunded':
            $order = Orders::where('payment_intent_id', $event->data->object->payment_intent)->first();

            // ---------------------update order to refunded status--------------------
            $order->update([
                'status' => 'refunded',
                'refund_id' => $event->data->object->id,
                ]);

            // ---------------------Reverse ledger entry -----------------------

            $last_entry = LedgerEntries::where('user_id', $order->user_id)->orderBy('id', 'desc')->first();
            $refund = $last_entry->refund ?? 0;
            $adjustment = $last_entry->adjustment ?? 0;

            if($order->status === 'refunded'){
                $refund += $order->total_price;
                $adjustment -= $order->total_price;
            }

            $ledger_entry = new LedgerEntries();
            $ledger_entry->user_id = $order->user_id;
            $ledger_entry->payment = $last_entry->payment ?? 0;
            $ledger_entry->refund = $refund;
            $ledger_entry->adjustment = $adjustment;
            $ledger_entry->save();

            // -------------------- Re-stock items with the refunded order---------------------
            $order_items = OrderItems::where('order_id', $order->id)->get();
            foreach($order_items as $order_item){

                $item = Items::find($order_item->item_id);

                // update the item instance with new quantity in stock
                $new_quantity = $item->quantity + $order_item->quantity;
                $item->update(['quantity' => $new_quantity]);
            }

            // send email
            $charge = $event->data->object;
            $currency = $charge->currency;
            $refundAmount = $charge->amount_refunded / 100; // Stripe amounts are in cents

            Mail::to($order->user)->queue(
                new RefundConfirmed($order, $currency, $refundAmount)
            );

            break;

        case 'charge.succeeded':
            $order = Orders::where('payment_intent_id', $event->data->object->payment_intent)->first();
            $payment_method = $event->data->object->payment_method_details->card->brand;
            // $order->update(['payment_method' => $payment_method]);
            break;

        default:
            echo 'Received unknown event type ' . $event->type;
            break;
    }

    return response('', 200);
    }

    public function refund(Request $request)
    {
        // Validate incoming data
        $valid_ids = Orders::pluck('id');
        $validated = $request->validate([
            'id' => ['required', 'integer', 'min:1', Rule::in($valid_ids)],
        ]);

        // Order for refund payment
        $order = Orders::find($validated['id']);

        if($order->user_id !== Auth::id()){
            return response()->json([
                'error' => 'access denied',
            ], 403);
        }

        try {
        // refund the order
        $stripe = new StripeClient(config('services.stripe.secret'));
        $refund = $stripe->refunds->create(['payment_intent' => $order->payment_intent_id]);
        $order->save();

        return response()->json([
            'Info' => "Refund of $order->total_price was successfully completed.",
            'Message' => "Refund Id: $refund->id"
        ]);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ],500)->setEncodingOptions(JSON_UNESCAPED_SLASHES);
        }
    }
}
