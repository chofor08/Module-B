<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LegderEntriesResource;
use App\Http\Resources\OrderResource;
use App\Models\LedgerEntries;
use App\Models\Orders;
use App\Models\User;
use Illuminate\Http\Request;

class LedgerEntriesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function ledger()
    {
        try {
            $userIds = User::pluck('id');
            $ledger_entries = [];
            $total_amount = 0;

            foreach($userIds as $userId){
                $ledger_entries[] = LedgerEntries::where('user_id', $userId)->orderBy('id', 'desc')->first();

                $last_entry = collect($ledger_entries)->last();
                $total_amount += $last_entry['adjustment'];
            }


            return response()->json([
                'Ledger' => LegderEntriesResource::collection($ledger_entries),
                'Amount Collected' => $total_amount,
            ]);

        } catch (\ErrorException $e) {
            return response()->json([
                "status code" => "400",
                "message" => "ledger is empty."
            ]);
        }
    }

}
