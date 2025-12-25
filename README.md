# VirgoSoft Trading Platform

A modern cryptocurrency trading platform built with Laravel 11, Inertia.js, and Vue 3. Features real-time order matching, live order books, and WebSocket-based notifications.

## 🚀 Features

- **User Authentication** - Secure registration and login with Laravel Breeze
- **Real-time Order Book** - Live buy/sell order matching
- **Wallet Management** - Track USD balance and cryptocurrency assets
- **Order Management** - Place, view, and cancel orders
- **Live Notifications** - Real-time updates via WebSocket (Pusher)
- **Trading Engine** - Automated order matching system
- **Asset Locking** - Secure asset management during trades

## 🛠️ Tech Stack

**Backend:**
- Laravel 11
- PHP 8.2+
- PostgreSQL Database
- Laravel Sanctum (SPA Authentication)
- Pusher (WebSocket Broadcasting)

**Frontend:**
- Vue 3 with TypeScript
- Inertia.js
- Vite
- Axios
- Tailwind CSS

## 📋 Prerequisites

Before you begin, ensure you have the following installed:

- **PHP** >= 8.2
- **Composer** >= 2.0
- **Node.js** >= 18.x
- **NPM** >= 9.x
- **PostgreSQL** >= 13.x

## 🔧 Installation

### 1. Clone the Repository

```bash
git clone https://github.com/NewtonKamau/virgosoft.git
cd virgosoft
```

### 2. Install PHP Dependencies

```bash
composer install
```

### 3. Install Node Dependencies

```bash
npm install
```

### 4. Environment Configuration

Copy the example environment file:

```bash
cp .env.example .env
```

### 5. Generate Application Key

```bash
php artisan key:generate
```

### 6. Configure Environment Variables

Edit the `.env` file and update the following:

#### Database (PostgreSQL - Default)
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=postgres
```

#### Session Configuration
```env
SESSION_DRIVER=database
SESSION_LIFETIME=120
```

#### Sanctum Configuration (Important for API Authentication)
```env
SANCTUM_STATEFUL_DOMAINS=virgosoft.test,localhost,127.0.0.1,::1
```

#### Broadcasting (Optional - for real-time features)
```env
BROADCAST_DRIVER=pusher
PUSHER_APP_ID=your_app_id
PUSHER_APP_KEY=your_app_key
PUSHER_APP_SECRET=your_app_secret
PUSHER_APP_CLUSTER=mt1

VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
```

> **Note:** If you don't configure Pusher, the app will still work but without real-time notifications.

### 7. Create Database

Ensure PostgreSQL is installed and running. Create a database:

```bash
# Using psql command line
psql -U postgres
CREATE DATABASE postgres;
\q

# Or use pgAdmin or any PostgreSQL GUI tool to create the database
```

> **Note:** Make sure the database name, username, and password in your `.env` file match your PostgreSQL setup.

### 8. Run Database Migrations

```bash
php artisan migrate
```

### 9. Seed Database (Optional)

If you have seeders configured:
```bash
php artisan db:seed
```

### 10. Build Frontend Assets

For development:
```bash
npm run dev
```

For production:
```bash
npm run build
```

## 🚀 Running the Application

### Development Mode

You need to run **two** separate commands in different terminal windows:

#### Terminal 1 - Laravel Development Server
```bash
php artisan serve
```

Or if using Laravel Herd/Valet:
```bash
# The app will be available at http://virgosoft.test
```

#### Terminal 2 - Vite Development Server
```bash
npm run dev
```

### Access the Application

- **Local Development Server:** http://localhost:8000
- **Laravel Valet/Herd:** http://virgosoft.test

## 🔐 First Time Setup

1. **Register a new account** at `/register`
2. **Login** at `/login`
3. **Access the dashboard** at `/dashboard`
4. Your initial balance is set during user creation (check `UserFactory` or database seeder)

## 📱 API Endpoints

All API endpoints require authentication via Laravel Sanctum (session-based for SPA):

### Profile
- `GET /api/profile` - Get user balance and assets

### Orders
- `GET /api/orders?symbol={symbol}` - Get order book and user orders
- `POST /api/orders` - Place a new order
- `POST /api/orders/{id}/cancel` - Cancel an order

### User
- `GET /api/user` - Get authenticated user details

## 🧪 Running Tests

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test --filter=OrderTest

# Run with coverage
php artisan test --coverage
```

## 🐛 Troubleshooting

### Issue: 401 Unauthorized on API Requests

**Solution:** Ensure `EnsureFrontendRequestsAreStateful` middleware is configured in `bootstrap/app.php`:

```php
$middleware->api(prepend: [
    \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
]);
```

### Issue: 419 CSRF Token Mismatch

**Solutions:**
1. Clear config cache: `php artisan config:clear`
2. Ensure `SANCTUM_STATEFUL_DOMAINS` includes your domain in `.env`
3. Hard refresh browser (Ctrl+Shift+R / Cmd+Shift+R)
4. Clear browser cookies for the domain

### Issue: 500 Internal Server Error

**Solutions:**
1. Check Laravel logs: `storage/logs/laravel.log`
2. Ensure database is migrated: `php artisan migrate:fresh`
3. Clear all caches:
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   ```

### Issue: Blank Screen / Assets Not Loading

**Solutions:**
1. Ensure Vite dev server is running: `npm run dev`
2. Clear browser cache
3. Check browser console for errors
4. Rebuild assets: `npm run build`

### Issue: Database Connection Error

**For PostgreSQL:**
1. Ensure PostgreSQL service is running:
   ```bash
   # Windows (if using PostgreSQL service)
   Get-Service postgresql*

   # Linux
   sudo systemctl status postgresql

   # Mac
   brew services list | grep postgresql
   ```
2. Verify database credentials in `.env` match your PostgreSQL setup
3. Check if database exists: `psql -U postgres -l`
4. Test connection: `psql -U postgres -d postgres -h 127.0.0.1`
5. Ensure `DB_CONNECTION=pgsql` in `.env`

### Issue: Real-time Updates Not Working

**Solutions:**
1. Verify Pusher credentials in `.env`
2. Check browser console for WebSocket errors
3. Ensure Echo is properly configured in `resources/js/echo.ts`

## 📁 Project Structure

```
virgosoft/
├── app/
│   ├── Http/Controllers/
│   │   ├── OrderController.php
│   │   └── ProfileController.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Order.php
│   │   └── Asset.php
│   └── Services/
│       └── MatchingEngine.php
├── resources/
│   ├── js/
│   │   ├── Pages/
│   │   │   └── Dashboard.vue
│   │   ├── Layouts/
│   │   ├── app.js
│   │   ├── bootstrap.ts
│   │   └── echo.ts
│   └── views/
│       └── app.blade.php
├── routes/
│   ├── web.php
│   ├── api.php
│   └── channels.php
├── database/
│   └── migrations/
├── tests/
│   └── Feature/
│       ├── OrderTest.php
│       └── ProfileTest.php
└── public/
```

## 🔑 Key Configuration Files

- **`.env`** - Environment configuration
- **`bootstrap/app.php`** - Application middleware and routing
- **`config/sanctum.php`** - Sanctum authentication settings
- **`vite.config.ts`** - Vite build configuration
- **`resources/js/bootstrap.ts`** - Axios and CSRF configuration

## 📚 Additional Commands

### Clear All Caches
```bash
php artisan optimize:clear
```

### Generate IDE Helper (Optional)
```bash
composer require --dev barryvdh/laravel-ide-helper
php artisan ide-helper:generate
```

### Check Routes
```bash
php artisan route:list
```

### Check Current Config
```bash
php artisan config:show sanctum
```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📝 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## 💡 Support

For issues and questions:
- Check the [Troubleshooting](#-troubleshooting) section
- Review [Laravel Documentation](https://laravel.com/docs)
- Review [Inertia.js Documentation](https://inertiajs.com)
- Review [Vue 3 Documentation](https://vuejs.org)

## 🎯 Roadmap

- [ ] Add more trading pairs
- [ ] Implement market orders
- [ ] Add trading charts
- [ ] Implement deposit/withdrawal
- [ ] Add admin dashboard
- [ ] Implement KYC verification
- [ ] Add transaction history
- [ ] Mobile responsive improvements

---

**Happy Trading! 🚀📈**
