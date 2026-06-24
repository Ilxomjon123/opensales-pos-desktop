# Diller-Do'kon Savdo Tizimi

---

## Joriy holat

Laravel 13 loyihasi yaratilgan. Paketlar o'rnatilmagan. Quyidagi
vazifalar ketma-ket bajarilishi kerak.

---

## Keyingi vazifalar (shu tartibda bajaring)

### 1. Paketlarni o'rnatish
```bash
composer require nutgram/laravel
composer require inertiajs/inertia-laravel
composer require laravel/sanctum
npm install @inertiajs/vue3 vue @vitejs/plugin-vue
npm install -D tailwindcss @tailwindcss/vite
```

### 2. Paketlarni sozlash
```bash
php artisan nutgram:install
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

### 3. .env ni sozlash
`.env` faylida quyidagi qiymatlarni to'g'ri sozla:
```env
APP_NAME="Dealer Bot"
APP_URL=https://yourdomain.com

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=dealer_bot
DB_USERNAME=postgres
DB_PASSWORD=secret

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=database

SUPER_ADMIN_EMAIL=admin@yourdomain.com
```

### 4. Barcha migratsiyalarni yozish
Quyidagi jadvallar uchun migration fayllari yaratiladi va yoziladi:

```
dealers            — id, name, bot_token (unique), bot_username (unique),
                     telegram_chat_id (nullable), is_active (bool, default true),
                     webhook_set_at (nullable timestamp), timestamps

shops              — id, dealer_id (FK), name, phone, address (nullable),
                     telegram_id (bigint, unique), balance (int, default 0),
                     is_active (bool, default true), timestamps

product_categories — id, dealer_id (FK), name, sort_order (int, default 0),
                     is_active (bool, default true), timestamps

products           — id, dealer_id (FK), category_id (FK nullable),
                     name, description (nullable),
                     price (decimal(14,6) — per-unit so'm),
                     pack_price (decimal(14,6) nullable — per-blok so'm, user-typed),
                     stock (int, default 0), unit (string, default 'dona'),
                     image (nullable), is_active (bool, default true), timestamps

orders             — id, shop_id (FK), dealer_id (FK), status (string),
                     total (int — yaxlitlangan so'm), note (nullable text), timestamps

order_items        — id, order_id (FK), product_id (FK), product_name (string),
                     price (decimal(14,6) — per-unit snapshot),
                     pack_price (decimal(14,6) nullable — per-blok snapshot),
                     qty (int), timestamps

payments           — id, shop_id (FK), dealer_id (FK), amount (int),
                     type (string: credit|debit), note (nullable), timestamps

users              — (Laravel default + role column: super_admin|dealer)
```

Migratsiyalar yozilgandan so'ng:
```bash
php artisan migrate
```

### 5. Enum sinflari yaratish
`app/Enums/` papkasida:
- `OrderStatus.php` — pending, confirmed, delivered, cancelled (+ label(), canTransitionTo())
- `PaymentType.php` — credit, debit
- `UserRole.php` — super_admin, dealer

### 6. Model sinflari yozish
Har bir model uchun: fillable, casts, relations, scopes.
Model factory lar ham yoziladi (`database/factories/`).

### 7. Service Provider va route sozlash
- `routes/telegram.php` — `POST /webhook/{dealer}` route
- `routes/web.php` — Inertia sahifalar
- `bootstrap/app.php` — exception handler larni ro'yxatga olish

### 8. Asosiy sinf skeletlari yaratish
Quyidagilarning bo'sh skeletlarini yaratish (ichini keyinroq to'ldiramiz):
- `app/Services/` — WebhookService, CartService, OrderService, FinanceService
- `app/Actions/` — RegisterDealerAction, CreateOrderFromCartAction
- `app/Repositories/` — OrderRepository, FinanceRepository
- `app/Http/Controllers/BotController.php`
- `app/Http/Controllers/Admin/DealerController.php`
- `app/Http/Controllers/Dealer/OrderController.php`
- `app/Telegram/Handlers/` — StartHandler, CatalogHandler, CartHandler
- `app/Telegram/Conversations/CartConversation.php`

### 9. Seeder yozish
`database/seeders/DatabaseSeeder.php` — super admin user yaratadi:
```php
User::create([
    'name'     => 'Super Admin',
    'email'    => env('SUPER_ADMIN_EMAIL'),
    'password' => Hash::make('changeme'),
    'role'     => UserRole::SUPER_ADMIN,
]);
```
So'ng:
```bash
php artisan db:seed
```

### 10. Tekshirish
```bash
php artisan route:list   # routelar to'g'ri ekanini tekshirish
php artisan test         # barcha testlar yashil bo'lishi kerak
```

---

## Muhim eslatma
Har bir vazifa tugagandan keyin keyingisiga o'tish.
Agar biror qadam xato bersa — xatoni to'liq ko'rsat va tuzat, keyin davom et.

---

## Loyiha maqsadi
Savdo agentlari o'rnini bosuvchi tizim. Do'konchilar har bir diller uchun
alohida Telegram bot orqali zakas beradi. Dillerlar web dashboard orqali
zakaslarni boshqaradi. Super admin barcha dillerlarni boshqaradi.

---

## Texnologik stack
- **Framework:** Laravel 13 (PHP 8.3+)
- **Bot:** Nutgram `nutgram/laravel` — FSM Conversation, multi-bot webhook
- **Frontend:** Inertia.js v2 + Vue 3 + Tailwind CSS v4
- **DB:** PostgreSQL 15+
- **Kesh / Bot sessiyasi:** Redis 7+
- **Queue:** Laravel Queue (database driver, keyinchalik Redis)
- **Auth:** Laravel Sanctum — diller va super admin uchun
- **Test:** PestPHP v3

---

## Arxitektura: Multi-bot tizimi

Har bir diller uchun **alohida Telegram bot** bo'ladi.
- Webhook URL pattern: `POST /webhook/{dealer_id}`
- Diller qo'shilganda `WebhookService::register()` avtomatik `setWebhook` chaqiradi
- Barcha botlar **bitta kod bazasi** bilan ishlaydi — faqat token farq qiladi
- Nutgram har so'rovda DB dan tokenni olib, dinamik ishga tushiriladi

---

## Rollar
| Rol        | Kirish     | Imkoniyatlar                                      |
|------------|------------|---------------------------------------------------|
| Super Admin| Web panel  | Diller qo'shish/o'chirish, barcha statistika      |
| Diller     | Web panel  | O'z zakaslarini ko'rish, katalog boshqarish, moliya|
| Do'konchi  | Telegram   | Katalog ko'rish, savat, zakas berish, hisob ko'rish|

---

## Ma'lumotlar bazasi (asosiy jadvallar)

```
dealers            — diller profili + bot_token + bot_username
shops              — do'konchilar (har biri bir dillerga bog'liq)
products           — mahsulotlar (diller kataloqi)
product_categories — kategoriyalar
orders             — zakaslar
order_items        — zakas tarkibi (narx snapshot saqlanadi)
payments           — to'lovlar
cart_sessions      — Redis da saqlanadi (DB ga yozilmaydi)
```

---

## Pul hisoblash qoidalari
- Barcha narxlar **so'mda**
- **Per-unit narx (`price`, `unit_cost`)** — `decimal(14,6)`, kasrli ruxsat
- **Per-blok narx (`pack_price`, `pack_unit_cost`)** — `decimal(14,6)` nullable.
  User-typed summa lossless saqlanadi: 85000 / 3.5 kg uchun
  `price = 24285.714286`, `pack_price = 85000` — har ikkalasi ham DB ga yoziladi
- **Source-of-truth:** frontend usePackPrice composable ikki yo'nalishli sync qiladi —
  user qaysi inputga yozsa, o'sha asosiy. Submit'da ikkalasi ham yuboriladi
- **Yig'indilar (orders.total, payments.amount, shops.balance,
  min_order_amount, fixed_commission_amount)** — `int`, butun so'm
- **Line total** (`OrderItem::subtotal`):
  - `pack_qty > 0 && pack_price`: `pack_qty × pack_price + loose_qty × price` (lossless)
  - aks holda: `qty × price`, yaxlitlash
- `order_items.price` va `order_items.pack_price` — zakasdagi snapshot,
  hech qachon o'zgartirilmaydi
- `payments` jadvalida har to'lov yoziladi
- `shops.balance` — joriy saldo (manfiy = qarzdor, musbat = ortiqcha to'lagan)

---

## Bot holatlari (FSM Conversations)

```
Do'konchi oqimi:
IDLE → mahsulot tanladi → WAIT_QUANTITY → miqdor kiritti →
CART_REVIEW → tasdiqladi → ORDER_PLACED → IDLE

Savat ishlash tartibi:
- Bir zakasda N ta mahsulot bo'lishi mumkin
- "Yana qo'shish" → IDLE ga qaytadi, savat Redis da saqlanadi
- "Tasdiqlash" → order + order_items yaratiladi, savat o'chiriladi
- Savat TTL: 24 soat (Redis da)
```

---

## Fayl tuzilmasi

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── BotController.php              # webhook qabul qilish
│   │   ├── Admin/
│   │   │   ├── DealerController.php       # super admin: diller CRUD
│   │   │   └── StatsController.php
│   │   └── Dealer/
│   │       ├── OrderController.php
│   │       ├── ProductController.php
│   │       └── FinanceController.php
│   ├── Requests/                          # FormRequest (validation)
│   ├── Resources/                         # API Resource (response format)
│   └── Middleware/
│       └── EnsureDealer.php
│
├── Telegram/
│   ├── Conversations/
│   │   ├── CartConversation.php           # savat FSM
│   │   └── RegisterConversation.php
│   ├── Handlers/
│   │   ├── StartHandler.php
│   │   ├── CatalogHandler.php
│   │   ├── CartHandler.php
│   │   └── OrderHandler.php
│   └── Keyboards/                         # inline keyboard builder lar
│       ├── CatalogKeyboard.php
│       └── CartKeyboard.php
│
├── Models/
│   ├── Dealer.php
│   ├── Shop.php
│   ├── Product.php
│   ├── ProductCategory.php
│   ├── Order.php
│   ├── OrderItem.php
│   └── Payment.php
│
├── Enums/
│   ├── OrderStatus.php
│   └── PaymentType.php
│
├── Services/
│   ├── WebhookService.php
│   ├── CartService.php
│   ├── OrderService.php
│   └── FinanceService.php
│
├── Actions/                               # murakkab bir martalik operatsiyalar
│   ├── RegisterDealerAction.php
│   └── CreateOrderFromCartAction.php
│
├── Repositories/                          # murakkab DB query lar
│   ├── OrderRepository.php
│   └── FinanceRepository.php
│
├── Events/
│   ├── OrderCreated.php
│   └── DealerRegistered.php
│
├── Listeners/
│   ├── SendOrderNotificationToDealer.php
│   └── SendOrderConfirmationToShop.php
│
└── Exceptions/
    └── Domain/
        ├── DomainException.php
        ├── InsufficientStockException.php
        └── InvalidOrderTransitionException.php

routes/
├── web.php          # Inertia sahifalari
├── api.php          # REST endpointlar
└── telegram.php     # /webhook/{dealer} route

resources/js/
├── Pages/
│   ├── Admin/
│   └── Dealer/
└── Components/

database/migrations/
database/seeders/
tests/
├── Feature/
└── Unit/
```

---

## Muhit o'zgaruvchilari (.env)

```env
APP_NAME="Dealer Bot"
APP_URL=https://yourdomain.com

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=dealer_bot
DB_USERNAME=postgres
DB_PASSWORD=secret

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=database

SUPER_ADMIN_EMAIL=admin@yourdomain.com
```

---

## Talablar
- PHP 8.3+
- Composer 2+
- Node.js 20+
- PostgreSQL 15+
- Redis 7+

---

## Yangi loyiha boshlash (noldan)

```bash
# 1. Laravel 13 o'rnatish
composer create-project laravel/laravel:^13.0 dealer-bot
cd dealer-bot

# 2. Kerakli paketlar
composer require nutgram/laravel
composer require inertiajs/inertia-laravel

# 3. Nutgram sozlash
php artisan nutgram:install

# 4. Inertia + Vue 3 + Tailwind
npm install @inertiajs/vue3 vue @vitejs/plugin-vue
npm install -D tailwindcss @tailwindcss/vite

# 5. .env sozlash
cp .env.example .env
php artisan key:generate

# 6. DB va migratsiyalar
php artisan migrate

# 7. Super admin seed
php artisan db:seed

# 8. Frontend build
npm run build
```

---

## Ishga tushirish (mavjud loyiha)

```bash
composer install
npm install && npm run build
php artisan migrate
php artisan queue:work &
php artisan serve
```

---

## Kod sifati va arxitektura standartlari

Bu bo'lim eng muhim. Har bir yozilgan kod shu standartlarga mos bo'lishi SHART.

---

### Qatlam mas'uliyatlari (Separation of Concerns)

**Controller** — faqat HTTP so'rovini qabul qiladi, javob qaytaradi.
Hech qanday business logika bo'lmaydi. Maksimal 15 qator.

```php
// TO'G'RI
final class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orderService) {}

    public function store(CreateOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->createFromCart(
            shop: $request->user()->shop,
            cartItems: $request->validated('items'),
        );

        return OrderResource::make($order)
            ->response()
            ->setStatusCode(201);
    }
}

// NOTO'G'RI — controller da business logika
final class OrderController extends Controller
{
    public function store(Request $request)
    {
        $total = 0;
        foreach ($request->items as $item) {
            $product = Product::find($item['id']);
            $total += $product->price * $item['qty']; // bu yerda bo'lmasligi kerak
        }
    }
}
```

**Service** — barcha business logika shu yerda. Yagona mas'uliyat prinsipi:
har bir service bir domenga tegishli.

```php
final class OrderService
{
    public function __construct(
        private readonly CartService     $cartService,
        private readonly FinanceService  $financeService,
    ) {}

    public function createFromCart(Shop $shop, array $cartItems): Order
    {
        return DB::transaction(function () use ($shop, $cartItems) {
            $order = Order::create([
                'shop_id'   => $shop->id,
                'dealer_id' => $shop->dealer_id,
                'status'    => OrderStatus::PENDING,
                'total'     => $this->calculateTotal($cartItems),
            ]);

            $this->attachItems($order, $cartItems);
            $this->financeService->debit($shop, $order->total);
            $this->cartService->clear($shop->id);

            event(new OrderCreated($order));

            return $order->load('items.product');
        });
    }

    private function calculateTotal(array $items): int
    {
        return collect($items)->sum(fn($i) => $i['price'] * $i['qty']);
    }
}
```

**Model** — faqat ma'lumotlar tuzilmasi: relation, cast, scope.
Hech qanday business logika bo'lmaydi.

```php
final class Order extends Model
{
    protected $fillable = ['shop_id', 'dealer_id', 'status', 'total', 'note'];

    protected $casts = [
        'status' => OrderStatus::class,
        'total'  => 'integer',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', OrderStatus::PENDING);
    }

    public function scopeForDealer(Builder $query, int $dealerId): void
    {
        $query->where('dealer_id', $dealerId);
    }
}
```

---

### Enum — magic string va raqamlardan voz kechish

Barcha holat va tip qiymatlari Enum bo'ladi. Hech qachon `status = 'pending'`
kabi raw string ishlatilmaydi.

```php
enum OrderStatus: string
{
    case PENDING   = 'pending';
    case CONFIRMED = 'confirmed';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::PENDING   => 'Kutilmoqda',
            self::CONFIRMED => 'Tasdiqlandi',
            self::DELIVERED => 'Yetkazildi',
            self::CANCELLED => 'Bekor qilindi',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return match($this) {
            self::PENDING   => in_array($next, [self::CONFIRMED, self::CANCELLED]),
            self::CONFIRMED => $next === self::DELIVERED,
            default         => false,
        };
    }
}
```

---

### Form Request — validation Controller dan tashqarida

```php
final class CreateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isShop();
    }

    public function rules(): array
    {
        return [
            'items'          => ['required', 'array', 'min:1'],
            'items.*.id'     => ['required', 'integer',
                Rule::exists('products', 'id')
                    ->where('dealer_id', $this->user()->shop->dealer_id)
                    ->where('is_active', true),
            ],
            'items.*.qty'    => ['required', 'integer', 'min:1', 'max:9999'],
        ];
    }
}
```

---

### Action — murakkab bir martalik operatsiyalar uchun

Service da bitta metod juda kattalashib ketsa, Action ga ajratiladi.

```php
final class RegisterDealerAction
{
    public function __construct(
        private readonly WebhookService $webhookService,
    ) {}

    public function execute(array $data): Dealer
    {
        return DB::transaction(function () use ($data) {
            $dealer = Dealer::create($data);
            $this->webhookService->register($dealer);
            event(new DealerRegistered($dealer));
            return $dealer;
        });
    }
}
```

---

### Event va Listener — side effect larni ajratish

Asosiy jarayon tugagandan keyin yuz beradigan narsalar (bildirishnoma, log,
statistika) Event orqali bajariladi. Service da to'g'ridan-to'g'ri chaqirilmaydi.

```php
// Listener — queue orqali (asosiy jarayon sekinlashmaydi)
final class SendOrderNotificationToDealer implements ShouldQueue
{
    public function handle(OrderCreated $event): void
    {
        $order  = $event->order->load('shop', 'items.product');
        $dealer = $order->dealer;

        $bot = new Nutgram($dealer->bot_token);
        $bot->sendMessage(
            text: $this->buildMessage($order),
            chat_id: $dealer->telegram_chat_id,
        );
    }
}
```

---

### Xatoliklarni boshqarish

Domenga xos Exception sinflari. Hech qachon generic `Exception` ishlatilmaydi.

```php
final class InsufficientStockException extends DomainException
{
    public static function forProduct(Product $product, int $requested): self
    {
        return new self(
            "'{$product->name}': so'raldi {$requested}, mavjud {$product->stock}"
        );
    }
}

// bootstrap/app.php da
$exceptions->renderable(function (InsufficientStockException $e) {
    return response()->json(['message' => $e->getMessage()], 422);
});
```

---

### Test yozish majburiy

Har bir Service va Action uchun unit test. Controller uchun feature test.

```php
it('creates order and debits shop balance', function () {
    $dealer  = Dealer::factory()->create();
    $shop    = Shop::factory()->for($dealer)->create(['balance' => 0]);
    $product = Product::factory()->for($dealer)->create(['price' => 50_000]);

    $order = app(OrderService::class)->createFromCart($shop, [
        ['product_id' => $product->id, 'qty' => 2, 'price' => $product->price],
    ]);

    expect($order->total)->toBe(100_000)
        ->and($order->status)->toBe(OrderStatus::PENDING)
        ->and($order->items)->toHaveCount(1)
        ->and($shop->fresh()->balance)->toBe(-100_000);
});
```

---

### Telegram Handler — yupqa, Service ga topshiradi

```php
final class CartHandler
{
    public function __construct(private readonly CartService $cartService) {}

    public function addItem(Nutgram $bot): void
    {
        $shop = Shop::where('telegram_id', $bot->userId())->firstOrFail();
        [$productId, $qty] = explode(':', $bot->callbackQuery()->data);

        $cart = $this->cartService->addItem(
            shopId:    $shop->id,
            productId: (int) $productId,
            qty:       (int) $qty,
        );

        $bot->editMessageText(
            text:         $this->cartService->formatSummary($cart),
            reply_markup: CartKeyboard::make($cart),
        );
    }
}
```

---

### Dependency Injection — doim constructor injection

```php
// TO'G'RI
final class FinanceService
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly PaymentRepository $payments,
    ) {}
}

// NOTO'G'RI
class FinanceService
{
    public function debit(Shop $shop, int $amount): void
    {
        $repo = app(PaymentRepository::class); // hech qachon bunday
    }
}
```

---

## Qat'iy taqiqlangan narsalar

```
# Hech qachon qilinmaydi:
- Controller da DB query yoki business logika yozish
- Service da response() yoki redirect() qaytarish
- Raw string status ishlatish: 'pending', 'active' — faqat Enum
- Business logika Model ichida (faqat scope, relation, cast ruxsat)
- app() yoki resolve() helper — faqat constructor injection
- try/catch bilan xatolikni log qilmasdan yutib yuborish
- N+1 query — with() va whereHas() majburiy
- Migration da down() ni bo'sh qoldirish
- .env ni git ga commit qilish
- Hardcode qiymatlar: 86400 o'rniga Carbon::SECONDS_PER_DAY
```

---

## PHP uslubi

- PHP 8.3+ syntax: `readonly`, `match`, `enum`, named arguments
- Barcha metodlar return type bilan: `function create(): Order`
- `final class` — meros olish kerak bo'lmagan sinflar uchun (aksariyat)
- `array` o'rniga `Collection` yoki aniq DTO ishlatish
- Magic number yo'q — konstantalar yoki Enum ishlatish
- PHPDoc izoh emas — to'g'ri type hint yetarli
- Murakkab logika uchun qisqa inline izoh (o'zbek yoki ingliz, izchil)

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v3
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/pennant (PENNANT) - v1
- laravel/prompts (PROMPTS) - v0
- laravel/pulse (PULSE) - v1
- laravel/sanctum (SANCTUM) - v4
- laravel/wayfinder (WAYFINDER) - v0
- livewire/livewire (LIVEWIRE) - v4
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/vue3 (INERTIA_VUE) - v3
- tailwindcss (TAILWINDCSS) - v4
- vue (VUE) - v3
- @laravel/vite-plugin-wayfinder (WAYFINDER_VITE) - v0
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `fortify-development` — ACTIVATE when the user works on authentication in Laravel. This includes login, registration, password reset, email verification, two-factor authentication (2FA/TOTP/QR codes/recovery codes), profile updates, password confirmation, or any auth-related routes and controllers. Activate when the user mentions Fortify, auth, authentication, login, register, signup, forgot password, verify email, 2FA, or references app/Actions/Fortify/, CreateNewUser, UpdateUserProfileInformation, FortifyServiceProvider, config/fortify.php, or auth guards. Fortify is the frontend-agnostic authentication backend for Laravel that registers all auth routes and controllers. Also activate when building SPA or headless authentication, customizing login redirects, overriding response contracts like LoginResponse, or configuring login throttling. Do NOT activate for Laravel Passport (OAuth2 API tokens), Socialite (OAuth social login), or non-auth Laravel features.
- `laravel-best-practices` — Apply this skill whenever writing, reviewing, or refactoring Laravel PHP code. This includes creating or modifying controllers, models, migrations, form requests, policies, jobs, scheduled commands, service classes, and Eloquent queries. Triggers for N+1 and query performance issues, caching strategies, authorization and security patterns, validation, error handling, queue and job configuration, route definitions, and architectural decisions. Also use for Laravel code reviews and refactoring existing Laravel code to follow best practices. Covers any task involving Laravel backend PHP code patterns.
- `pennant-development` — Use when working with Laravel Pennant the official Laravel feature flag package. Trigger whenever the query mentions Pennant by name or involves feature flags or feature toggles in a Laravel project. Tasks include defining feature flags checking whether features are active creating class based features in `app/Features` using Blade `@feature` directives scoping flags to users or teams building custom Pennant storage drivers protecting routes with feature flags testing feature flags with Pest or PHPUnit and implementing A B testing or gradual rollouts with feature flags. Do not trigger for generic Laravel configuration authorization policies authentication or non Pennant feature management systems.
- `pulse-development` — Handles Laravel Pulse setup, configuration, and custom card development. Activates when installing Pulse; configuring the dashboard or authorization gate; setting up recorders and filtering; building custom Livewire cards; optimizing with Redis ingest or sampling; or when the user mentions /pulse, pulse:check, pulse:work, Pulse::record(), or application monitoring.
- `wayfinder-development` — Use this skill for Laravel Wayfinder which auto-generates typed functions for Laravel controllers and routes. ALWAYS use this skill when frontend code needs to call backend routes or controller actions. Trigger when: connecting any React/Vue/Svelte/Inertia frontend to Laravel controllers, routes, building end-to-end features with both frontend and backend, wiring up forms or links to backend endpoints, fixing route-related TypeScript errors, importing from @/actions or @/routes, or running wayfinder:generate. Use Wayfinder route functions instead of hardcoded URLs. Covers: wayfinder() vite plugin, .url()/.get()/.post()/.form(), query params, route model binding, tree-shaking. Do not use for backend-only task
- `inertia-vue-development` — Develops Inertia.js v3 Vue client-side applications. Activates when creating Vue pages, forms, or navigation; using <Link>, <Form>, useForm, useHttp, setLayoutProps, or router; working with deferred props, prefetching, optimistic updates, instant visits, or polling; or when user mentions Vue with Inertia, Vue pages, Vue forms, or Vue navigation.
- `tailwindcss-development` — Always invoke when the user's message includes 'tailwind' in any form. Also invoke for: building responsive grid layouts (multi-column card grids, product grids), flex/grid page structures (dashboards with sidebars, fixed topbars, mobile-toggle navs), styling UI components (cards, tables, navbars, pricing sections, forms, inputs, badges), adding dark mode variants, fixing spacing or typography, and Tailwind v3/v4 work. The core use case: writing or fixing Tailwind utility classes in HTML templates (Blade, JSX, Vue). Skip for backend PHP logic, database queries, API routes, JavaScript with no HTML/CSS component, CSS file audits, build tool configuration, and vanilla CSS.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always declare `declare(strict_types=1);` at the top of every `.php` file.
- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Follow existing application Enum naming conventions.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

</laravel-boost-guidelines>
