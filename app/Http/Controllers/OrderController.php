<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Asset;
use App\Services\MatchingEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;

use App\Events\OrderBookUpdated;

// ... (existing imports)

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $symbol = $request->query('symbol', 'BTC');

        $buyOrders = Order::where('symbol', $symbol)
            ->where('side', 'buy')
            ->where('status', 1)
            ->orderBy('price', 'desc')
            ->get();

        $sellOrders = Order::where('symbol', $symbol)
            ->where('side', 'sell')
            ->where('status', 1)
            ->orderBy('price', 'asc')
            ->get();

        $query = $request->user()->orders();

        if ($request->filled('symbol')) {
            $query->where('symbol', $request->input('symbol'));
        }
        if ($request->filled('side')) {
            $query->where('side', $request->input('side'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $userOrders = $query->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'orderbook' => [
                'buy' => $buyOrders,
                'sell' => $sellOrders,
            ],
            'user_orders' => $userOrders,
        ]);
    }

    public function store(Request $request, MatchingEngine $engine)
    {
        $validated = $request->validate([
            'symbol' => 'required|string',
            'side' => 'required|in:buy,sell',
            'price' => 'required|numeric|min:0.00000001',
            'amount' => 'required|numeric|min:0.00000001',
        ]);

        $user = $request->user();
        $total = $validated['price'] * $validated['amount'];

        return DB::transaction(function () use ($user, $validated, $total, $engine) {
            $user = User::lockForUpdate()->find($user->id);

            if ($validated['side'] === 'buy') {
                if ($user->balance < $total) {
                    return response()->json(['error' => 'Insufficient balance'], 400);
                }
                $user->decrement('balance', $total);
            } else {
                $asset = Asset::where('user_id', $user->id)
                    ->where('symbol', $validated['symbol'])
                    ->lockForUpdate()
                    ->first();

                if (!$asset || $asset->amount < $validated['amount']) {
                    return response()->json(['error' => 'Insufficient asset balance'], 400);
                }

                $asset->decrement('amount', $validated['amount']);
                $asset->increment('locked_amount', $validated['amount']);
            }

            $order = Order::create([
                'user_id' => $user->id,
                'symbol' => $validated['symbol'],
                'side' => $validated['side'],
                'price' => $validated['price'],
                'amount' => $validated['amount'],
                'status' => 1, // open
            ]);

            $engine->match($order);

            // Broadcast update to public channel
            broadcast(new OrderBookUpdated($order->symbol));

            return response()->json($order);
        });
    }

    public function cancel($id)
    {
        return DB::transaction(function () use ($id) {
            $order = Order::where('id', $id)
                ->where('user_id', auth()->id())
                ->where('status', 1)
                ->lockForUpdate()
                ->firstOrFail();

            $order->update(['status' => 3]); // cancelled

            $user = $order->user;
            if ($order->side === 'buy') {
                $user->increment('balance', $order->price * $order->amount);
            } else {
                $asset = Asset::where('user_id', $user->id)
                    ->where('symbol', $order->symbol)
                    ->first();
                $asset->increment('amount', $order->amount);
                $asset->decrement('locked_amount', $order->amount);
            }

            broadcast(new OrderBookUpdated($order->symbol));

            return response()->json(['message' => 'Order cancelled']);
        });
    }
}
