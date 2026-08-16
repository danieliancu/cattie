# Kattie.uk

Current architecture, implemented functionality, operational status and known gaps are maintained in [APPLICATION_STATUS.md](APPLICATION_STATUS.md). Update that living report whenever the application changes.

UK delivery methods are managed in **Admin → Operations → Shipping Methods**. Checkout uses active methods for the common fulfilment provider of every basket item, and the accepted Order freezes the price, estimate, provider and provider service code for Stripe and future fulfilment.

## Local End-to-End Development

The local journey uses fake AI and fake payment providers. No OpenAI, Stripe or fulfilment credentials are required.

Set these values in `.env`:

```env
AI_IMAGE_PROVIDER=fake
AI_IMAGE_FAKE_FAILURE=false
QUEUE_CONNECTION=database
PAYMENT_PROVIDER=fake
FAKE_PAYMENTS_ENABLED=true
CHECKOUT_TAX_STRATEGY=zero_uk
```

Prepare the application once. The `migrate:fresh` command deletes existing local database records:

```powershell
php artisan config:clear
php artisan migrate:fresh --seed
php artisan storage:link
```

Run the web server, frontend and queue worker in three separate terminals:

```powershell
php artisan serve
```

```powershell
npm run dev
```

```powershell
php artisan queue:work --queue=default --tries=2
```

The worker is required with the database queue. After changing `.env`, run `php artisan config:clear` and restart the worker.

Open `http://127.0.0.1:8000`, choose a product, variant and style, enter its personalisation, upload a JPEG/PNG/WebP photo, wait for the fake preview, optionally regenerate, approve, add it to the basket, complete UK checkout, then choose **Complete test payment**. The final order should be `Paid`.

For failure recovery, set `AI_IMAGE_FAKE_FAILURE=true`, clear config and restart the worker. After the safe failure screen appears, restore `false`, clear config, restart the worker and select **Try again**.

## Stripe Checkout (test mode)

Stripe Checkout uses Kattie's order snapshots and creates dynamic line items. It does not require Stripe Products or Prices.

```env
PAYMENT_PROVIDER=stripe
STRIPE_SECRET_KEY=sk_test_...
STRIPE_PUBLISHABLE_KEY=pk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

Clear cached configuration after changing these values:

```powershell
php artisan config:clear
```

For local webhook forwarding, install and authenticate the Stripe CLI, then run:

```powershell
stripe listen --forward-to http://127.0.0.1:8000/api/webhooks/stripe
```

Copy the `whsec_...` value printed by the CLI into `STRIPE_WEBHOOK_SECRET`. Complete a sandbox purchase with Stripe's standard successful test card `4242 4242 4242 4242`, any future expiry date and any CVC. The browser return and signed webhook both use the same idempotent reconciliation path; an order is confirmed only after Stripe reports it paid.

The Stripe payment form is embedded directly in the Kattie payment page. Dynamic Payment Methods are controlled in the Stripe Dashboard; methods that require bank authorization can temporarily redirect the customer and return through Kattie. Do not commit Stripe secrets. The `pk_...` key is intentionally browser-visible, while `sk_...` and `whsec_...` must remain server-side. Kattie remains authoritative for products, prices, shipping, tax and order totals; non-zero discounts are intentionally rejected until a Stripe discount strategy is implemented.

---

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logo.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
