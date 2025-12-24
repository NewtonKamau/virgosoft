<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TestUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user1 = \App\Models\User::create([
            'name' => 'Alice Buyer',
            'email' => 'alice@example.com',
            'password' => bcrypt('password'),
            'balance' => 1000000,
        ]);

        $user2 = \App\Models\User::create([
            'name' => 'Bob Seller',
            'email' => 'bob@example.com',
            'password' => bcrypt('password'),
            'balance' => 5000,
        ]);

        \App\Models\Asset::create([
            'user_id' => $user2->id,
            'symbol' => 'BTC',
            'amount' => 10,
            'locked_amount' => 0,
        ]);

        \App\Models\Asset::create([
            'user_id' => $user2->id,
            'symbol' => 'ETH',
            'amount' => 100,
            'locked_amount' => 0,
        ]);
    }
}
