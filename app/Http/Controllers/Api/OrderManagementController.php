<?php

namespace App\Http\Controllers\APi;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderItemsResource;
use App\Http\Resources\OrderResource;
use App\Models\Items;
use App\Models\OrderItems;
use App\Models\Orders;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Stripe\Event;
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

        //Get all ids from Item to validate request
        $valid_ids = Items::pluck('id');

        //Validate the array, not single fields
        $validated = $request->validate([
            'order_items' => ['required', 'array', 'min:1'],
            'order_items.*.item_id' => ['required', 'integer', 'min:0', Rule::in($valid_ids)],
            'order_items.*.quantity' => ['required', 'integer', 'min:0'],
        ]);


        //Create the order
        $order = Auth::user();
        $order = Orders::create([
            'user_id' => $request->user()->id,
            'status' => 'pending',
            'total_price' => 0,

        ]);

        // Loop through the orderitems array to create all orderitems
        $total = 0;
        foreach ($validated['order_items'] as $order_item){
            $item = Items::find($order_item['item_id']);

            $sub_total = $order_item['quantity'] * $item->unit_price;
            $total += $sub_total;

            $data = OrderItems::create([
                'order_id' => $order->id,
                'item_id' => $item->id,
                'quantity' => $order_item['quantity'],
                'unit_price' => $item->unit_price,
                'sub_total' => $sub_total,
            ]);

        }

        //update total
        $order->update(['total_price' => $total]);


        // check if order exist in the databese
        $user = Request()->user();
        $order = Orders::where('user_id', $user->id)->where('status', 'pending')->first();
        if(!$order){
            return response()->json([
                'status code' => '400',
                'message' => 'No order available.',
            ], 400);
        }

        // get orderitems for an order
        $orderItems = OrderItems::where('order_id', $order->id)->get();
        $lineItems = [];

        // Loop through the orderitems array for the sreipe server
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
                'metadata' => [
                    'order_id' => $orderItem->order_id,
                    'item_id' => $orderItem->item_id,
                ]
            ];
        }

        // get the stripe secret key
        $stripe = new StripeClient(config('services.stripe.secret'));

        // create stripe session
        $session = $stripe->checkout->sessions->create([
        'line_items' => $lineItems,
        'mode' => 'payment',
        'success_url' => route('checkout.success'). "?session_id={CHECKOUT_SESSION_ID}",
        'cancel_url' => route('checkout.cancel'),
        'expires_at' => now()->addMinutes(30)->timestamp,
        // 'integration_identifier' => '{{INTEGRATION_ID}}',
        ]);

        // update the order record
        $order->update(['session_id' => $session->id]);
        $order->update(['status' => 'unpaid']);

        // return checkout url
        return response()->json(['checkout_url' => $session->url])->setEncodingOptions(JSON_UNESCAPED_SLASHES);
    }

    public function success(Request $request)
    {

        $stripe = new StripeClient(config('services.stripe.secret'));

        $session_id = $request->query('session_id');
        if(!$session_id){
            return response()->json([
                'status code' => '400',
                'message' => 'Missing session ID.',
            ], 400);
        }

        try {
            $session = $stripe->checkout->sessions->retrieve($session_id);
            $customerName = $session->customer_details->name ?? 'Customer';

            $order = Orders::where('session_id', $session_id)->first();
            if (!$order) {
            return response()->json([
                'status code' => '400',
                'message' => 'Invalid Order.',
            ], 400);
            }

            if($order->status === 'upaid'){
                $order->update(['status' => 'paid']);
                return response()->json("Thanks for your order, $customerName!", 200);
            }

            return response()->noContent();

        } catch (\Throwable $e) {
            // http_response_code(400);
            // echo json_encode(['error' => $e->getMessage()]);
            return response()->json([
                'status code' => '400',
                'message' => 'Bad Request',
            ], 400);
        }

    }

    public function cancel()
    {
        echo "<h1>Checkout was cancelled.</h1>";
    }

    public function webhook()
    {
        $endpoint_secret = config('services.stripe.webhook_secret');

        $payload = @file_get_contents('php://input');
        $event = null;

        try {
            $event = Event::constructFrom(
                json_decode($payload, true)
            );
        } catch(\UnexpectedValueException $e) {
            // Invalid payload
            return response('', 400);
        }

        if ($endpoint_secret) {
        // Only verify the event if you've defined an endpoint secret
        // Otherwise, use the basic decoded event
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
        try {
            $event = Webhook::constructEvent(
            $payload, $sig_header, $endpoint_secret
            );
        } catch(\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            echo '⚠️  Webhook error while validating signature.';
            return response('', 400);
        }
        }

        // Handle the event
        switch ($event->type) {
            case 'payment_intent.succeeded':
                $paymentIntent = $event->data->object;
                $sessionId = $paymentIntent->id;

            $order = Orders::where('session_id', $sessionId)->first();
            if($order && $order->status === 'upaid'){
                $order->update(['status' => 'paid']);
                //Send email to customer
            }

            // ... handle other event types
            default:
                echo 'Received unknown event type ' . $event->type;
        }

        http_response_code(200);
    }
    
}
