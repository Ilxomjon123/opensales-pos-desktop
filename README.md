# Dealer Bot

Savdo agentlari o'rnini bosuvchi tizim. Do'konchilar har bir diller uchun alohida Telegram bot orqali zakas beradi. Dillerlar web dashboard orqali zakaslarni boshqaradi. Super admin barcha dillerlarni boshqaradi.

## Texnologik stack

- **Backend:** Laravel 13 (PHP 8.3+)
- **Bot:** Nutgram — FSM Conversation, multi-bot webhook
- **Frontend:** Inertia.js v3 + Vue 3 + Tailwind CSS v4 + shadcn-vue
- **DB:** PostgreSQL 15+
- **Cache / Bot sessiya:** Redis 7+
- **Queue:** Laravel Queue (database driver)
- **Auth:** Laravel Fortify + Sanctum

## Arxitektura

```
Do'konchi (Telegram) ──► Bot (per-dealer) ──► Orders
                                                │
Diller (Web panel)   ──► Dashboard      ◄──────┘
                                                │
Super Admin (Web)    ──► Admin panel    ◄──────┘
```

Har bir diller uchun alohida Telegram bot. Webhook URL: `POST /webhook/{dealer_id}`.
Bitta kod bazasi, faqat token farq qiladi.

## Talablar

- PHP 8.3+
- Composer 2+
- Node.js 20+
- PostgreSQL 15+
- Redis 7+

## O'rnatish

```bash
# 1. Repo klonlash
git clone <repo-url> dealer-bot
cd dealer-bot

# 2. PHP dependencies
composer install

# 3. Node dependencies
npm install

# 4. Environment
cp .env.example .env
php artisan key:generate

# 5. .env ni tahrirlash
# DB_*, REDIS_*, SUPER_ADMIN_EMAIL ni to'g'rilang

# 6. Database yaratish
createdb dealer_bot

# 7. Migratsiyalar
php artisan migrate

# 8. Super admin yaratish
php artisan db:seed

# 9. Frontend build
npm run build
```

## Ishga tushirish (development)

```bash
composer dev
```

Bu buyruq parallel ishga tushiradi:
- `php artisan serve` (server)
- `php artisan queue:listen` (queue)
- `php artisan pail` (log viewer)
- `npm run dev` (Vite)

## Webhook sozlash

Yangi diller qo'shilganda webhook avtomatik o'rnatiladi. Server ko'chganda barcha webhook larni qayta o'rnatish:

```bash
# Barcha faol dillerlar uchun
php artisan webhook:setup

# Bitta diller uchun
php artisan webhook:setup --dealer=1

# Webhook larni o'chirish
php artisan webhook:setup --remove
```

## Production deploy

```bash
# Birinchi marta
cp .env.example .env
# .env ni production qiymatlari bilan to'ldiring:
#   APP_ENV=production
#   APP_DEBUG=false
#   APP_URL=https://yourdomain.com
#   LOG_STACK=daily
#   QUEUE_CONNECTION=database

# Keyingi deploy lar
bash deploy/deploy.sh
```

Nginx konfiguratsiyasi: `deploy/nginx.conf`

## Testlar

```bash
# Barcha testlar
php artisan test

# Coverage bilan
php artisan test --coverage

# Faqat bot testlari
php artisan test --filter=Telegram

# Faqat admin testlari
php artisan test --filter=Admin
```

## Loyiha tuzilmasi

```
app/
├── Console/Commands/     # Artisan buyruqlar (webhook:setup)
├── Contracts/            # Interface lar (WebhookServiceInterface)
├── Enums/                # OrderStatus, PaymentType, UserRole
├── Exceptions/Domain/    # Domen xatoliklari
├── Http/
│   ├── Controllers/
│   │   ├── Admin/        # Super admin panel
│   │   ├── Dealer/       # Diller dashboard
│   │   └── BotController # Webhook endpoint
│   ├── Middleware/        # EnsureDealer, EnsureSuperAdmin, VerifyTelegramWebhook
│   ├── Requests/          # FormRequest lar
│   └── Resources/         # API Resource lar
├── Models/                # Eloquent model lar
├── Policies/              # Authorization policy lar
├── Services/              # Business logika (Cart, Order, Finance, Webhook)
├── Support/Dto/           # Cart, CartItem value object lar
├── Telegram/
│   ├── BotFactory.php     # Per-dealer Nutgram yaratish
│   ├── ShopResolver.php   # telegram_id → Shop
│   ├── Handlers/          # Bot handler lar
│   ├── Conversations/     # FSM (Register, Cart)
│   └── Keyboards/         # Inline/Reply keyboard builder lar
└── Actions/               # Murakkab operatsiyalar

resources/js/pages/
├── Admin/                 # Super admin Vue sahifalari
├── Dealer/                # Diller Vue sahifalari
└── auth/                  # Login/Register
```

## Rollar

| Rol | Kirish | Imkoniyatlar |
|-----|--------|-------------|
| Super Admin | Web panel | Diller CRUD, statistika |
| Diller | Web panel | Zakaslar, katalog, moliya |
| Do'konchi | Telegram | Katalog, savat, zakas |
