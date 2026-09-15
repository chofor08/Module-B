<?php

namespace App\Http\Controllers\APi;

use App\Http\Controllers\Controller;
use App\Models\Items;
use App\Models\OrderItems;
use App\Models\Orders;
use Gate;
use Illuminate\Auth\Events\Validated;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class OrderManagementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Response $response)
    {
        // Gate::authorize('access', $response);
        return OrderItems::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
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
        $order = Orders::create([
            'user_id' => $request->user()->id,
            'status' => 'unpaid',
            'total_price' => 0,

        ]);

        // Loop through the orderitems array
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

        return $order;

    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
