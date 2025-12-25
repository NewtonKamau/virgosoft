<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_place_buy_order()
    {
        // Create user 'Alice'
        $user = User::factory()->create([
            'email' => 'alice@example.com',
            'balance' => 100000,
        ]);

        $this->actingAs($user);

        $this->withoutExceptionHandling();

        $response = $this->postJson('/api/orders', [
            'symbol' => 'BTC',
            'side' => 'buy',
            'price' => 1000,
            'amount' => 0.1,
        ]);

        $response->assertStatus(200);
    }
}
