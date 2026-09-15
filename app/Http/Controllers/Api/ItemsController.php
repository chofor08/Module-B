<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ItemsResource;
use App\Models\Items;
use App\Models\OrderItems;
use App\Models\Orders;
use Stripe\StripeClient;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class ItemsController extends Controller
{
    public function index()
    {
        return ItemsResource::Collection(Items::all());
    }


    public function checkout()
    {
        $stripe = new StripeClient(config('services.stripe.secret'));
        $lineItems = [];
        $orderItems = OrderItems::all(); 
        $total_price = 0;

        foreach($orderItems as $orderItem){
            $lineItems = [
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => $orderItem->name,
                    ],
                    'unit_price' => $orderItem->unit_price*100,
                ],
                'quantity' => $orderItem->quantity,
                'sub_total' => 'quantity'*'unit_price',
                $total_price += 'sub_total',
                'metadata' => [
                    // 'event_id' => $event->id,
                    'order_id' => $orderItem->order_id,
                ]
            ];
        }

        $session = $stripe->checkout->sessions->create([
        'line_items' => $lineItems,
        'mode' => 'payment',
        'success_url' => route('checkout.success', [], true),
        'cancel_url' => route('checkout.cancel', [], true). "?session_id={CHECKOUT_SESSION_ID}",
        'integration_identifier' => '{{INTEGRATION_ID}}',
        ]);

        $orders = Orders::where('id' ,$orderItems[0]->order_id);
        $orders->total_price = $total_price;
        $orders->session_id = $session->id;
        $orders->save();

        return response()->json(['checkout_url' => $session->url]);
    }

    public function success()
    {
        return response('Order paid');
    }
    
    public function cancel()
    {
        return response('Order cancelled');
    }
    
}
