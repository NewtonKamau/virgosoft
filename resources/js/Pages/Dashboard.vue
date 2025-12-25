<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, onMounted, reactive, computed, watch, onUnmounted } from 'vue';
import axios from 'axios';

interface Asset {
    id: number;
    symbol: string;
    amount: string;
    locked_amount: string;
}

interface Order {
    id: number;
    side: string;
    symbol: string;
    price: string;
    amount: string;
    status: number;
    created_at: string;
}

const user = ref<any>(usePage().props.auth.user);
const assets = ref<Asset[]>([]);
const orderBook = ref<{ buy: Order[], sell: Order[] }>({ buy: [], sell: [] });
const myOrders = ref<Order[]>([]);
const symbol = ref('BTC');

const filters = reactive({
    status: '',
    side: '',
});

const form = reactive({
    side: 'buy', // buy or sell
    price: 0,
    amount: 0,
});

const total = computed(() => {
    return form.price * form.amount;
});

const fee = computed(() => {
    return total.value * 0.015;
});

const notifications = ref<{id: number, message: string}[]>([]);

const addNotification = (message: string) => {
    const id = Date.now();
    notifications.value.push({ id, message });
    setTimeout(() => {
        notifications.value = notifications.value.filter(n => n.id !== id);
    }, 5000);
};

const fetchProfile = async () => {
    await axios.get('/api/profile').then(res => {
        user.value.balance = res.data.balance;
        assets.value = res.data.assets;
    });
};

const fetchOrders = async () => {
    const params: any = { symbol: symbol.value };
    if (filters.status) params.status = filters.status;
    if (filters.side) params.side = filters.side;

    await axios.get(`/api/orders`, { params }).then(res => {
        orderBook.value = res.data.orderbook;
        myOrders.value = res.data.user_orders;
    });
};

const submitOrder = async () => {
    try {
        await axios.get('/sanctum/csrf-cookie');
        await axios.post('/api/orders', {
            symbol: symbol.value,
            side: form.side,
            price: form.price,
            amount: form.amount
        });
        addNotification('Order Placed Successfully');
        form.amount = 0;
        // Fetch handled by events
    } catch (error: any) {
        addNotification('Error: ' + (error.response?.data?.error || error.message));
    }
};

const cancelOrder = async (id: number) => {
    try {
        await axios.get('/sanctum/csrf-cookie');
        await axios.post(`/api/orders/${id}/cancel`);
        addNotification('Order Cancelled');
        // Fetch handled by events
    } catch (error: any) {
        addNotification('Error cancelling order');
    }
};

let marketChannel: any = null;

const subscribeMarket = () => {
    if (window.Echo) {
        if (marketChannel) {
            window.Echo.leave(`market.${symbol.value}`);
        }
        marketChannel = window.Echo.channel(`market.${symbol.value}`)
            .listen('OrderBookUpdated', (e: any) => {
                // Update OrderBook and possibly my orders if I had one involved
                fetchOrders();
            });
    }
};

onMounted(() => {
    axios.get('/sanctum/csrf-cookie').then(() => {
        fetchProfile();
        fetchOrders();
    });

    if (window.Echo) {
         window.Echo.private(`user.${user.value.id}`)
            .listen('OrderMatched', (e: any) => {
                addNotification(`Order Matched: ${e.order.side.toUpperCase()} ${e.order.amount} ${e.order.symbol} @ ${e.order.price}`);
                fetchProfile();
            });

        subscribeMarket();
    }
});

onUnmounted(() => {
    if (window.Echo && marketChannel) {
        window.Echo.leave(`market.${symbol.value}`);
    }
});
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Exchange Dashboard</h2>
        </template>

        <!-- Toast Notifications -->
        <div class="fixed top-20 right-4 z-50 space-y-2">
            <div v-for="notif in notifications" :key="notif.id"
                class="bg-indigo-600 text-white px-6 py-3 rounded-lg shadow-xl animate-bounce-slow flex items-center space-x-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <span>{{ notif.message }}</span>
            </div>
        </div>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                    <!-- Wallet Section -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-bold mb-4">Wallet</h3>
                        <div class="mb-4">
                            <p class="text-sm text-gray-500">USD Balance</p>
                            <p class="text-2xl font-mono">${{ parseFloat(user.balance).toLocaleString() }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 mb-2">Assets</p>
                            <div v-if="assets.length === 0" class="text-gray-400">No assets</div>
                            <ul v-else class="space-y-2">
                                <li v-for="asset in assets" :key="asset.id" class="flex justify-between">
                                    <span class="font-bold">{{ asset.symbol }}</span>
                                    <span class="font-mono">{{ parseFloat(asset.amount) }} (Locked: {{ parseFloat(asset.locked_amount) }})</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Order Form -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-bold mb-4">Place Order ({{ symbol }})</h3>
                        <div class="space-y-4">
                            <div class="flex space-x-2">
                                <button @click="form.side = 'buy'" :class="{'bg-green-500 text-white': form.side === 'buy', 'bg-gray-200': form.side !== 'buy'}" class="flex-1 py-2 rounded">Buy</button>
                                <button @click="form.side = 'sell'" :class="{'bg-red-500 text-white': form.side === 'sell', 'bg-gray-200': form.side !== 'sell'}" class="flex-1 py-2 rounded">Sell</button>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Price</label>
                                <input type="number" step="0.00000001" v-model="form.price" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Amount</label>
                                <input type="number" step="0.00000001" v-model="form.amount" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>

                            <div class="text-sm text-gray-500">
                                <div>Total: {{ total.toFixed(2) }} USD</div>
                                <div>Fee (1.5%): {{ fee.toFixed(2) }} {{ form.side === 'buy' ? 'Asset (approx)' : 'USD' }}</div>
                            </div>

                            <button @click="submitOrder" class="w-full bg-indigo-600 text-white py-2 rounded hover:bg-indigo-700 transition">Place {{ form.side.toUpperCase() }} Order</button>
                        </div>
                    </div>

                    <!-- Order Book -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-bold mb-4">Order Book</h3>
                        <div class="space-y-4">
                            <div>
                                <h4 class="text-red-500 font-semibold text-center border-b pb-2">Asks (Sell)</h4>
                                <div class="max-h-40 overflow-y-auto">
                                    <table class="w-full text-xs text-right">
                                        <tr v-for="order in orderBook.sell" :key="order.id" class="hover:bg-gray-50">
                                            <td class="text-red-600">{{ parseFloat(order.price).toFixed(2) }}</td>
                                            <td>{{ parseFloat(order.amount).toFixed(8) }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                            <div>
                                <h4 class="text-green-500 font-semibold text-center border-b pb-2">Bids (Buy)</h4>
                                <div class="max-h-40 overflow-y-auto">
                                    <table class="w-full text-xs text-right">
                                        <tr v-for="order in orderBook.buy" :key="order.id" class="hover:bg-gray-50">
                                            <td class="text-green-600">{{ parseFloat(order.price).toFixed(2) }}</td>
                                            <td>{{ parseFloat(order.amount).toFixed(8) }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- My Orders -->
                <div class="mt-6 bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-bold">My Orders</h3>
                        <div class="flex space-x-2">
                             <select v-model="filters.side" @change="fetchOrders" class="text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <option value="">All Sides</option>
                                <option value="buy">Buy</option>
                                <option value="sell">Sell</option>
                             </select>
                             <select v-model="filters.status" @change="fetchOrders" class="text-sm border-gray-300 rounded-md shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                <option value="">All Statuses</option>
                                <option value="1">Open</option>
                                <option value="2">Filled</option>
                                <option value="3">Cancelled</option>
                             </select>
                        </div>
                    </div>

                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Side</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Symbol</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr v-for="order in myOrders" :key="order.id">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ new Date(order.created_at).toLocaleTimeString() }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-bold" :class="order.side === 'buy' ? 'text-green-600' : 'text-red-600'">{{ order.side.toUpperCase() }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ order.symbol }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ parseFloat(order.price).toFixed(2) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ parseFloat(order.amount).toFixed(8) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <span v-if="order.status === 1" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Open</span>
                                    <span v-if="order.status === 2" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Filled</span>
                                    <span v-if="order.status === 3" class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Cancelled</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button v-if="order.status === 1" @click="cancelOrder(order.id)" class="text-red-600 hover:text-red-900">Cancel</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
