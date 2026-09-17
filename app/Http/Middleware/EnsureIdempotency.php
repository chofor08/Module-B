<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotency
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('Idempotency-Key');

        if (!$key) {
            return response()->json(['message' => 'Idempotency-Key header is required.'], 400);
        }

        // Try to atomically "claim" the key
        $record = null;

        try {
            $record = DB::transaction(function () use ($key, $request) {
                return IdempotencyKey::create([
                    'key' => $key,
                    'user_id' => $request->user()?->id,
                    'status' => 'processing',
                ]);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // Unique constraint violation = key already exists
            $existing = IdempotencyKey::where('key', $key)->first();

            if ($existing && $existing->status === 'completed') {
                // Return the original response, don't reprocess
                return response()->json(
                    $existing->response_body,
                    $existing->response_status
                );
            }

            if ($existing && $existing->status === 'processing') {
                // Original request still in flight (race condition / rapid double-click)
                return response()->json(
                    ['message' => 'A request with this Idempotency-Key is already being processed.'],
                    409
                );
            }

            // status === 'failed' -> allow retry, fall through
            $record = $existing;
        }

        // Let the actual controller run
        $response = $next($request);

        // Save the response against the key so retries get the same result
        $record->update([
            'status' => $response->isSuccessful() ? 'completed' : 'failed',
            'response_status' => $response->getStatusCode(),
            'response_body' => json_decode($response->getContent(), true),
        ]);

        return response()->json([
            'Order' => $response,
            'Message' => "Order already exist",
        ]);
    }
}
