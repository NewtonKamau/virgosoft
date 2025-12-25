<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Asset;
use App\Models\Trade;
use App\Events\OrderMatched;
use App\Events\OrderBookUpdated;
use Illuminate\Support\Facades\DB;

class MatchingEngine
{
    public function match(Order $newOrder)
    {
        return DB::transaction(function () use ($newOrder) {
            $matchingOrder = null;

            if ($newOrder->side === 'buy') {
                $matchingOrder = Order::where('symbol', $newOrder->symbol)
                    ->where('side', 'sell')
                    ->where('status', 1) // open
                    ->where('price', '<=', $newOrder->price)
                    ->where('amount', $newOrder->amount) // Full Match Only
                    ->orderBy('created_at', 'asc')
                    ->lockForUpdate()
                    ->first();
            } else {
                $matchingOrder = Order::where('symbol', $newOrder->symbol)
                    ->where('side', 'buy')
                    ->where('status', 1) // open
                    ->where('price', '>=', $newOrder->price)
                    ->where('amount', $newOrder->amount) // Full Match Only
                    ->orderBy('created_at', 'asc')
                    ->lockForUpdate()
                    ->first();
            }

            if ($matchingOrder) {
                return $this->executeMatch($newOrder, $matchingOrder);
            }

            return null;
        });
    }

    /**
     * Execute the match between two orders.
     *
     * @param Order $newOrder The new order to match
     * @param Order $matchingOrder The existing order to match against
     * @return Trade|null The trade record if the match was successful, null otherwise
     */
    protected function executeMatch(Order $newOrder, Order $matchingOrder)
    {
        $buyOrder = $newOrder->side === 'buy' ? $newOrder : $matchingOrder;
        $sellOrder = $newOrder->side === 'sell' ? $newOrder : $matchingOrder;

        $price = $matchingOrder->price; // Match at the price of the existing order
        $amount = $newOrder->amount; // Assuming full match only as per instructions
        $volume = $price * $amount;

        // Fee calculation: 1.5%
        // Buyer pays fee in Asset: receives (Amount - Fee)
        // Seller pays fee in USD: receives (Volume - Fee)
        $rate = 0.015;
        $commissionAsset = $amount * $rate;
        $commissionUSD = $volume * $rate;

        // Update statuses
        $buyOrder->update(['status' => 2]); // filled
        $sellOrder->update(['status' => 2]); // filled

        // Lock Users to prevent race conditions on balance updates
        // Note: The newOrder initiator was already locked in Controller, but we lock again to be safe and consistent for both.
        // It's safe to re-lock the same row in the same transaction (reentrant).
        $buyer = User::lockForUpdate()->find($buyOrder->user_id);
        $seller = User::lockForUpdate()->find($sellOrder->user_id);

        // Refund buyer if match price < limit price
        $refund = ($buyOrder->price - $price) * $amount;
        if ($refund > 0) {
            $buyer->increment('balance', $refund);
        }

        // Seller receives volume - commissionUSD
        $seller->increment('balance', $volume - $commissionUSD);

        // Buyer receives assets (Amount - CommissionAsset)
        $buyerAsset = Asset::where('user_id', $buyer->id)
            ->where('symbol', $buyOrder->symbol)
            ->lockForUpdate()
            ->first();

        if (!$buyerAsset) {
             // Create if not exists (using create then lock or start with 0)
             // specific lock is hard if it doesn't exist, but we locked the User so it's relatively safe.
            $buyerAsset = Asset::create([
                'user_id' => $buyer->id,
                'symbol' => $buyOrder->symbol,
                'amount' => 0,
                'locked_amount' => 0
            ]);
        }

        $buyerAsset->increment('amount', $amount - $commissionAsset);

        // Seller's locked assets were already deducted/moved to locked at creation.
        // We need to decrease locked_amount.
        $sellerAsset = Asset::where('user_id', $seller->id)
            ->where('symbol', $sellOrder->symbol)
            ->lockForUpdate()
            ->first();

        $sellerAsset->decrement('locked_amount', $amount);

        // Create Trade record
        $trade = Trade::create([
            'buy_order_id' => $buyOrder->id,
            'sell_order_id' => $sellOrder->id,
            'symbol' => $buyOrder->symbol,
            'price' => $price,
            'amount' => $amount,
            'commission' => $commissionUSD, // Storing USD value of commission for reference
        ]);

        // Broadcast events
        broadcast(new OrderMatched($buyOrder));
        broadcast(new OrderMatched($sellOrder));
        broadcast(new OrderBookUpdated($buyOrder->symbol));

        return $trade;
    }
}
