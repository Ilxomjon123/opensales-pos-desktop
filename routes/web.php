<?php

declare(strict_types=1);

use App\Contracts\WebhookServiceInterface;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BackupDownloadController;
use App\Http\Controllers\Admin\BillingController;
use App\Http\Controllers\Admin\BotHealthController;
use App\Http\Controllers\Admin\BroadcastCampaignController as AdminBroadcastCampaignController;
use App\Http\Controllers\Admin\DealerController;
use App\Http\Controllers\Admin\DeliverymanSecurityController;
use App\Http\Controllers\Admin\DirectoryShopController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\LeadController as AdminLeadController;
use App\Http\Controllers\Admin\PlatformBroadcastController;
use App\Http\Controllers\Admin\PlatformPaymentController;
use App\Http\Controllers\Admin\Reports\DealerActivityReportController;
use App\Http\Controllers\Admin\Reports\DealerCommissionReportController;
use App\Http\Controllers\Admin\Reports\PlatformSalesReportController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\ShopController as AdminShopController;
use App\Http\Controllers\Admin\StatsController;
use App\Http\Controllers\Api\UsernameAvailabilityController;
use App\Http\Controllers\Auth\DealerRegistrationController;
use App\Http\Controllers\Blog\BlogController;
use App\Http\Controllers\Compare\CompareController;
use App\Http\Controllers\Dealer\BotController;
use App\Http\Controllers\Dealer\BotUserController;
use App\Http\Controllers\Dealer\BroadcastCampaignController as DealerBroadcastCampaignController;
use App\Http\Controllers\Dealer\BroadcastController;
use App\Http\Controllers\Dealer\CourierCashController;
use App\Http\Controllers\Dealer\DeliverymanCarryController;
use App\Http\Controllers\Dealer\DeliverymanController;
use App\Http\Controllers\Dealer\DeliveryZoneController;
use App\Http\Controllers\Dealer\EmployeeController;
use App\Http\Controllers\Dealer\FinanceController;
use App\Http\Controllers\Dealer\LoadingRouteController;
use App\Http\Controllers\Dealer\MarketplaceController;
use App\Http\Controllers\Dealer\MarketplaceFinanceController;
use App\Http\Controllers\Dealer\MarketplaceOrderController;
use App\Http\Controllers\Dealer\MarketplaceSaleController;
use App\Http\Controllers\Dealer\OnboardingController;
use App\Http\Controllers\Dealer\OrderController;
use App\Http\Controllers\Dealer\Pos\CustomerController as PosCustomerController;
use App\Http\Controllers\Dealer\Pos\ReportController as PosReportController;
use App\Http\Controllers\Dealer\Pos\SaleController as PosSaleController;
use App\Http\Controllers\Dealer\Pos\ShiftController as PosShiftController;
use App\Http\Controllers\Dealer\ProductBulkController;
use App\Http\Controllers\Dealer\ProductCategoryController;
use App\Http\Controllers\Dealer\ProductController;
use App\Http\Controllers\Dealer\PromotionController;
use App\Http\Controllers\Dealer\Reports\CustomersReportController;
use App\Http\Controllers\Dealer\Reports\DailyClosingController;
use App\Http\Controllers\Dealer\Reports\InventoryReportController;
use App\Http\Controllers\Dealer\Reports\ProfitReportController;
use App\Http\Controllers\Dealer\Reports\ReturnsReportController;
use App\Http\Controllers\Dealer\Reports\SalesReportController;
use App\Http\Controllers\Dealer\RouteController;
use App\Http\Controllers\Dealer\ShopBalanceController;
use App\Http\Controllers\Dealer\ShopController;
use App\Http\Controllers\Dealer\ShopReturnController;
use App\Http\Controllers\Dealer\ShopVisitController;
use App\Http\Controllers\Dealer\StatsController as DealerStatsController;
use App\Http\Controllers\Dealer\StockTransactionController;
use App\Http\Controllers\Dealer\SupplierController;
use App\Http\Controllers\Dealer\SupplierFinanceController;
use App\Http\Controllers\Dealer\SupplierReturnController;
use App\Http\Controllers\Dealer\WarehouseController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Pricing\PricingController;
use App\Http\Controllers\Seo\FeedController;
use App\Http\Controllers\Seo\HumansController;
use App\Http\Controllers\Seo\IndexNowController;
use App\Http\Controllers\Seo\LlmsController;
use App\Http\Controllers\Seo\OgImageController;
use App\Http\Controllers\Seo\SecurityTxtController;
use App\Http\Controllers\Seo\SitemapController;
use App\Http\Middleware\SetLandingLocale;
use App\Models\Dealer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Landing sahifalar. Default (uz) prefiksiz: `/`, `/blog`, ...
// Boshqa tillar `/<locale>` prefiksi bilan (nomi `loc.` bilan): `/ru`, `/ru/blog`.
// Faqat marketing sahifalari; admin/diller qismi tegmaydi.
$landingRoutes = function (): void {
    Route::get('/', fn () => Inertia::render('Welcome'))->name('home');

    Route::prefix('blog')->name('blog.')->group(function (): void {
        Route::get('/', [BlogController::class, 'index'])->name('index');
        Route::get('{post:slug}', [BlogController::class, 'show'])->name('show');
    });

    Route::prefix('taqqoslash')->name('compare.')->group(function (): void {
        Route::get('opensales-vs-1c', [CompareController::class, 'opensalesVs1c'])->name('opensales-vs-1c');
        Route::get('opensales-vs-sales-doctor', [CompareController::class, 'opensalesVsSalesDoctor'])->name('opensales-vs-sales-doctor');
    });

    Route::prefix('narxlar')->name('pricing.')->group(function (): void {
        Route::get('kalkulyator', [PricingController::class, 'calculator'])->name('calculator');
    });
};

// Default (uz) — prefiksiz.
Route::middleware(SetLandingLocale::class)->group($landingRoutes);

// Lokalizatsiya qilingan — `/{locale}` prefiksi, `loc.` nom prefiksi bilan.
Route::prefix('{locale}')
    ->whereIn('locale', ['ru', 'en', 'uz-Cyrl'])
    ->middleware(SetLandingLocale::class)
    ->name('loc.')
    ->group($landingRoutes);

Route::get('sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('feed.xml', FeedController::class)->name('feed');
Route::get('llms.txt', LlmsController::class)->name('llms');
Route::get('humans.txt', HumansController::class)->name('humans');
Route::get('og-image.png', OgImageController::class)->name('og.image');
Route::get('.well-known/security.txt', SecurityTxtController::class)->name('security.txt');
Route::get('{key}.txt', IndexNowController::class)
    ->where('key', '[a-f0-9]{8,128}')
    ->name('indexnow.key');

Route::post('leads', [LeadController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('leads.store');

// Mobil ilova maxfiylik siyosati — App Store / Google Play talab qiladi.
Route::view('privacy', 'legal.privacy')->name('privacy');

// Akkauntni o'chirish sahifasi — Google Play Data Safety talab qiladi.
Route::view('delete-account', 'legal.delete-account')->name('delete-account');

// Diller ochiq registratsiyasi — mehmonlar uchun.
Route::middleware('guest')->group(function (): void {
    Route::get('register', [DealerRegistrationController::class, 'create'])->name('register');
    Route::post('register', [DealerRegistrationController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('register.store');

    // Registratsiya formasi uchun jonli foydalanuvchi nomi tekshiruvi (auth talab qilmaydi).
    Route::get('register/username-availability', [UsernameAvailabilityController::class, 'check'])
        ->middleware('throttle:30,1')
        ->name('register.username-availability');
});

Route::middleware(['auth'])->group(function (): void {
    Route::get('dashboard', function (Request $request) {
        return redirect()->route($request->user()->defaultRouteName());
    })->name('dashboard');

    Route::get('api/username-availability', [UsernameAvailabilityController::class, 'check'])
        ->name('api.username-availability');

    Route::middleware('super_admin')->prefix('admin')->name('admin.')->group(function (): void {
        Route::resource('dealers', DealerController::class)->except(['show']);
        Route::patch('dealers/{dealer}/toggle', [DealerController::class, 'toggleActive'])
            ->name('dealers.toggle');
        Route::post('dealers/{dealer}/webhook', [DealerController::class, 'setWebhook'])
            ->name('dealers.webhook.set');
        Route::delete('dealers/{dealer}/webhook', [DealerController::class, 'removeWebhook'])
            ->name('dealers.webhook.remove');
        Route::patch('dealers/{dealer}/commission', [DealerController::class, 'updateCommission'])
            ->name('dealers.commission.update');
        Route::get('dealers/{dealer}/directory-search', [DealerController::class, 'directorySearch'])
            ->name('dealers.directory-search');
        Route::post('dealers/{dealer}/assign-shops', [DealerController::class, 'assignShops'])
            ->name('dealers.assign-shops');
        Route::post('dealers/{dealer}/platform-payments', [DealerController::class, 'storePlatformPayment'])
            ->name('dealers.platform-payments.store');
        Route::delete('platform-payments/{payment}', [DealerController::class, 'destroyPlatformPayment'])
            ->name('platform-payments.destroy');
        Route::get('platform-payments', [PlatformPaymentController::class, 'index'])
            ->name('platform-payments.index');
        Route::get('shops', [AdminShopController::class, 'index'])->name('shops.index');

        Route::get('directory', [DirectoryShopController::class, 'index'])->name('directory.index');
        Route::get('directory/create', [DirectoryShopController::class, 'create'])->name('directory.create');
        Route::get('directory/inn-lookup/{inn}', [DirectoryShopController::class, 'lookupInn'])
            ->where('inn', '[0-9]+')
            ->name('directory.inn-lookup');
        Route::get('directory/phone-lookup', [DirectoryShopController::class, 'lookupPhone'])->name('directory.phone-lookup');
        Route::get('directory/reverse-geocode', [DirectoryShopController::class, 'reverseGeocode'])->name('directory.reverse-geocode');
        Route::get('directory/resolve-map-link', [DirectoryShopController::class, 'resolveMapLink'])->name('directory.resolve-map-link');
        Route::post('directory', [DirectoryShopController::class, 'store'])->name('directory.store');
        Route::get('directory/import-template', [DirectoryShopController::class, 'template'])->name('directory.template');
        Route::post('directory/import', [DirectoryShopController::class, 'import'])->name('directory.import');
        Route::get('directory/{directory_shop}/edit', [DirectoryShopController::class, 'edit'])->name('directory.edit');
        Route::patch('directory/{directory_shop}', [DirectoryShopController::class, 'update'])->name('directory.update');
        Route::delete('directory/{directory_shop}', [DirectoryShopController::class, 'destroy'])->name('directory.destroy');

        Route::get('stats', [StatsController::class, 'index'])->name('stats.index');

        // Platforma sozlamalari — davlat bo'yicha funksiya bayroqlari (Pennant).
        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::patch('settings/flags', [SettingsController::class, 'updateFlag'])->name('settings.flags.update');

        // Backup yuklab olish (Pulse card'dan). URL '/download' bilan tugaydi —
        // '.gz' bilan tugasa nginx statik fayl deb 404 beradi.
        Route::get('backups/{file}/download', BackupDownloadController::class)
            ->name('backups.download');

        Route::prefix('reports')->name('reports.')->group(function (): void {
            Route::get('sales', [PlatformSalesReportController::class, 'index'])->name('sales.index');
            Route::get('sales/export', [PlatformSalesReportController::class, 'export'])->name('sales.export');
            Route::get('commission', [DealerCommissionReportController::class, 'index'])->name('commission.index');
            Route::get('commission/export', [DealerCommissionReportController::class, 'export'])->name('commission.export');
            Route::get('dealer-activity', [DealerActivityReportController::class, 'index'])->name('dealer-activity.index');
            Route::get('dealer-activity/export', [DealerActivityReportController::class, 'export'])->name('dealer-activity.export');
        });

        Route::get('bot-health', [BotHealthController::class, 'index'])->name('bot-health.index');
        Route::post('bot-health/refresh', [BotHealthController::class, 'refreshAll'])->name('bot-health.refresh-all');
        Route::post('bot-health/{dealer}/refresh', [BotHealthController::class, 'refresh'])->name('bot-health.refresh');

        Route::get('audit-log', [AuditLogController::class, 'index'])->name('audit.index');

        Route::get('leads', [AdminLeadController::class, 'index'])->name('leads.index');
        Route::patch('leads/{lead}', [AdminLeadController::class, 'update'])->name('leads.update');
        Route::delete('leads/{lead}', [AdminLeadController::class, 'destroy'])->name('leads.destroy');

        Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
        Route::get('billing/{dealer}', [BillingController::class, 'show'])->name('billing.show');

        Route::post('dealers/{dealer}/impersonate', [ImpersonationController::class, 'start'])
            ->name('dealers.impersonate');

        Route::post('deliverymen/{deliveryman}/unlock', [DeliverymanSecurityController::class, 'unlock'])
            ->name('deliverymen.unlock');

        Route::get('broadcasts', [PlatformBroadcastController::class, 'index'])->name('broadcasts.index');
        Route::get('broadcasts/preview', [PlatformBroadcastController::class, 'preview'])->name('broadcasts.preview');
        Route::post('broadcasts', [PlatformBroadcastController::class, 'store'])->name('broadcasts.store');

        Route::get('broadcast-campaigns/preview', [AdminBroadcastCampaignController::class, 'preview'])
            ->name('broadcast-campaigns.preview');
        Route::post('broadcast-campaigns/{campaign}/toggle', [AdminBroadcastCampaignController::class, 'toggle'])
            ->name('broadcast-campaigns.toggle');
        Route::post('broadcast-campaigns/{campaign}/run-now', [AdminBroadcastCampaignController::class, 'runNow'])
            ->name('broadcast-campaigns.run-now');
        Route::resource('broadcast-campaigns', AdminBroadcastCampaignController::class)
            ->parameters(['broadcast-campaigns' => 'campaign']);

        Route::get('api/verify-token', function (Request $request) {
            $token = $request->string('token')->toString();
            $excludeDealerId = $request->integer('dealer_id') ?: null;

            if ($token === '') {
                return response()->json(['username' => null, 'taken_by' => null]);
            }

            // Avval bazadan qidiramiz — band token uchun Telegram API ga so'rov yubormaslik.
            $existing = Dealer::query()
                ->where('bot_token', $token)
                ->when($excludeDealerId, fn ($q) => $q->where('id', '!=', $excludeDealerId))
                ->first(['name', 'bot_username']);

            if ($existing !== null) {
                return response()->json([
                    'username' => $existing->bot_username,
                    'taken_by' => $existing->name,
                ]);
            }

            $username = app(WebhookServiceInterface::class)->verifyToken($token);

            return response()->json([
                'username' => $username,
                'taken_by' => null,
            ]);
        })->name('api.verify-token');
    });

    // Impersonation stop — impersonation davomida diller sifatida borasiz, shuning uchun auth middleware ichida
    Route::post('impersonate/stop', [ImpersonationController::class, 'stop'])->name('impersonate.stop');

    Route::middleware(['dealer', 'track.deliveryman', 'restrict.deliveryman', 'restrict.cashier'])->prefix('dealer')->name('dealer.')->group(function (): void {
        Route::get('orders/export', [OrderController::class, 'export'])->name('orders.export');
        Route::get('orders/loading-route', [LoadingRouteController::class, 'show'])
            ->name('orders.loading-route.show');
        Route::post('orders/loading-route', [LoadingRouteController::class, 'compute'])
            ->name('orders.loading-route.compute');
        Route::resource('orders', OrderController::class)->only(['index', 'create', 'store', 'show', 'update']);
        Route::get('orders/{order}/edit', [OrderController::class, 'editForm'])
            ->name('orders.edit');
        Route::put('orders/{order}/edit', [OrderController::class, 'edit'])
            ->name('orders.edit.update');
        Route::get('orders/{order}/invoice', [OrderController::class, 'invoice'])
            ->name('orders.invoice');
        Route::post('orders/{order}/assemble', [OrderController::class, 'assemble'])
            ->name('orders.assemble');
        Route::post('orders/{order}/picked', [OrderController::class, 'editPicked'])
            ->name('orders.picked.edit');
        Route::post('orders/{order}/dispatch', [OrderController::class, 'dispatchToDelivery'])
            ->name('orders.dispatch');
        Route::post('orders/{order}/deliver', [OrderController::class, 'deliver'])
            ->name('orders.deliver');
        Route::post('orders/{order}/return', [OrderController::class, 'acceptReturn'])
            ->name('orders.accept-return');
        Route::post('orders/{order}/shop-return', [ShopReturnController::class, 'store'])
            ->name('orders.shop-return.store');
        Route::post('shops/{shop}/return', [ShopReturnController::class, 'storeFreeform'])
            ->name('shops.return.store');
        Route::get('shops-api/{shop}/returnable-orders', [ShopReturnController::class, 'returnableOrders'])
            ->name('shops.returnable-orders');
        Route::post('orders/{order}/cancel', [OrderController::class, 'cancel'])
            ->name('orders.cancel');
        Route::patch('orders/{order}/deliveryman', [OrderController::class, 'assignDeliveryman'])
            ->name('orders.deliveryman.assign');
        Route::post('orders/{order}/self-assign', [OrderController::class, 'selfAssign'])
            ->name('orders.self-assign');
        Route::post('orders/{order}/release-self', [OrderController::class, 'releaseSelf'])
            ->name('orders.release-self');

        Route::post('orders/{order}/messages', [OrderController::class, 'storeMessage'])
            ->name('orders.messages.store');
        Route::put('orders/{order}/messages/{message}', [OrderController::class, 'updateMessage'])
            ->name('orders.messages.update');
        Route::delete('orders/{order}/messages/{message}', [OrderController::class, 'destroyMessage'])
            ->name('orders.messages.destroy');

        Route::get('carry', [DeliverymanCarryController::class, 'index'])->name('carry.index');

        Route::get('courier-cash', [CourierCashController::class, 'index'])
            ->name('courier-cash.index');
        Route::get('courier-cash/{deliveryman}', [CourierCashController::class, 'show'])
            ->name('courier-cash.show');
        Route::post('courier-cash/{deliveryman}/settle', [CourierCashController::class, 'store'])
            ->name('courier-cash.settle');

        Route::get('products/bulk', [ProductBulkController::class, 'edit'])->name('products.bulk');
        Route::post('products/bulk/adjust', [ProductBulkController::class, 'adjust'])->name('products.bulk.adjust');
        Route::post('products/bulk/import', [ProductBulkController::class, 'import'])->name('products.bulk.import');
        Route::get('products/reorder', [ProductController::class, 'reorder'])->name('products.reorder');
        Route::post('products/reorder', [ProductController::class, 'updateOrder'])->name('products.reorder.update');
        Route::patch('products/{product}/toggle', [ProductController::class, 'toggleActive'])
            ->name('products.toggle');
        Route::get('products/{product}/json', [ProductController::class, 'show'])
            ->name('products.show.json');
        Route::get('stock-transactions', [StockTransactionController::class, 'index'])
            ->name('stock-transactions.index');
        Route::post('stock-transactions', [StockTransactionController::class, 'store'])
            ->name('stock-transactions.store');
        Route::resource('products', ProductController::class)->except(['show']);

        Route::get('categories', [ProductCategoryController::class, 'index'])->name('categories.index');
        Route::post('categories', [ProductCategoryController::class, 'store'])->name('categories.store');
        Route::put('categories/{category}', [ProductCategoryController::class, 'update'])->name('categories.update');
        Route::delete('categories/{category}', [ProductCategoryController::class, 'destroy'])->name('categories.destroy');

        Route::resource('promotions', PromotionController::class)->except(['show']);

        // Birja (marketplace) — dillerlararo savdo
        Route::prefix('marketplace')->name('marketplace.')->group(function (): void {
            Route::get('/', [MarketplaceController::class, 'index'])->name('index');

            Route::get('orders', [MarketplaceOrderController::class, 'index'])->name('orders.index');
            Route::post('orders', [MarketplaceOrderController::class, 'store'])->name('orders.store');
            Route::get('orders/{order}', [MarketplaceOrderController::class, 'show'])->name('orders.show');
            Route::post('orders/{order}/receive', [MarketplaceOrderController::class, 'receive'])->name('orders.receive');
            Route::post('orders/{order}/cancel', [MarketplaceOrderController::class, 'cancel'])->name('orders.cancel');

            // Birja sotuv amallari — alohida sahifa yo'q, Buyurtmalar sahifasidan ishlatiladi.
            Route::post('sales/{order}/accept', [MarketplaceSaleController::class, 'accept'])->name('sales.accept');
            Route::post('sales/{order}/ship', [MarketplaceSaleController::class, 'ship'])->name('sales.ship');
            Route::post('sales/{order}/deliver', [MarketplaceSaleController::class, 'deliver'])->name('sales.deliver');
            Route::post('sales/{order}/cancel', [MarketplaceSaleController::class, 'cancel'])->name('sales.cancel');

            Route::get('finance', [MarketplaceFinanceController::class, 'index'])->name('finance.index');
            Route::post('finance/payment', [MarketplaceFinanceController::class, 'storePayment'])->name('finance.payment');
        });

        Route::get('broadcasts', [BroadcastController::class, 'index'])->name('broadcasts.index');
        Route::get('broadcasts/preview', [BroadcastController::class, 'preview'])->name('broadcasts.preview');
        Route::post('broadcasts', [BroadcastController::class, 'store'])->name('broadcasts.store');

        Route::get('broadcast-campaigns/preview', [DealerBroadcastCampaignController::class, 'preview'])
            ->name('broadcast-campaigns.preview');
        Route::post('broadcast-campaigns/{campaign}/toggle', [DealerBroadcastCampaignController::class, 'toggle'])
            ->name('broadcast-campaigns.toggle');
        Route::post('broadcast-campaigns/{campaign}/run-now', [DealerBroadcastCampaignController::class, 'runNow'])
            ->name('broadcast-campaigns.run-now');
        Route::get('broadcast-campaigns/{campaign}/render-preview', [DealerBroadcastCampaignController::class, 'renderPreview'])
            ->name('broadcast-campaigns.render-preview');
        Route::resource('broadcast-campaigns', DealerBroadcastCampaignController::class)
            ->parameters(['broadcast-campaigns' => 'campaign']);

        Route::resource('shops', ShopController::class);
        Route::post('shops/{shop}/photo', [ShopController::class, 'updatePhoto'])
            ->name('shops.photo.update');
        Route::delete('shops/{shop}/photo', [ShopController::class, 'destroyPhoto'])
            ->name('shops.photo.destroy');
        Route::post('shops/{shop}/invite', [ShopController::class, 'invite'])
            ->name('shops.invite');
        Route::get('shops/{shop}/visits', [ShopVisitController::class, 'index'])
            ->name('shops.visits.index');
        Route::post('shops/{shop}/visits', [ShopVisitController::class, 'store'])
            ->name('shops.visits.store');
        Route::put('shops/{shop}/visits/{visit}', [ShopVisitController::class, 'update'])
            ->name('shops.visits.update');
        Route::delete('shops/{shop}/visits/{visit}', [ShopVisitController::class, 'destroy'])
            ->name('shops.visits.destroy');
        Route::get('shops-api/inn-lookup/{inn}', [ShopController::class, 'lookupInn'])
            ->where('inn', '[0-9]{9}')
            ->name('shops.inn-lookup');
        Route::get('shops-api/phone-lookup', [ShopController::class, 'lookupPhone'])
            ->name('shops.phone-lookup');
        Route::get('shops-api/resolve-map-link', [ShopController::class, 'resolveMapLink'])
            ->name('shops.resolve-map-link');
        Route::get('shops-api/reverse-geocode', [ShopController::class, 'reverseGeocode'])
            ->name('shops.reverse-geocode');
        Route::get('shops-api/forward-geocode', [ShopController::class, 'forwardGeocode'])
            ->name('shops.forward-geocode');

        Route::resource('deliverymen', DeliverymanController::class)->except(['show'])
            ->parameter('deliverymen', 'deliveryman');

        Route::resource('employees', EmployeeController::class)->except(['show'])
            ->parameter('employees', 'employee');

        Route::get('shops-balance', [ShopBalanceController::class, 'index'])->name('shops-balance.index');

        Route::get('bot-users', [BotUserController::class, 'index'])->name('bot-users.index');
        Route::get('bot-users/{member}/json', [BotUserController::class, 'show'])->name('bot-users.show');

        Route::resource('suppliers', SupplierController::class);
        Route::post('suppliers/{supplier}/return', [SupplierReturnController::class, 'store'])
            ->name('suppliers.return.store');

        Route::get('suppliers-balance', [SupplierFinanceController::class, 'index'])
            ->name('suppliers-balance.index');
        Route::get('suppliers-balance/payments', [SupplierFinanceController::class, 'payments'])
            ->name('suppliers-balance.payments');
        Route::get('suppliers-balance/payments/export', [SupplierFinanceController::class, 'exportPayments'])
            ->name('suppliers-balance.payments.export');
        Route::post('suppliers-balance/payments', [SupplierFinanceController::class, 'storePayment'])
            ->name('suppliers-balance.payments.store');

        Route::get('finance', [FinanceController::class, 'index'])->name('finance.index');
        Route::get('finance/aging', [FinanceController::class, 'aging'])->name('finance.aging');
        Route::get('finance/export', [FinanceController::class, 'export'])->name('finance.export');
        Route::post('finance/payments', [FinanceController::class, 'storePayment'])
            ->name('finance.payments.store');

        Route::get('routes/today', [RouteController::class, 'today'])->name('routes.today');
        Route::post('routes/today/dispatch', [RouteController::class, 'startRoute'])
            ->name('routes.today.dispatch');

        Route::get('stats', [DealerStatsController::class, 'index'])->name('stats.index');

        Route::post('onboarding/complete', [OnboardingController::class, 'complete'])
            ->name('onboarding.complete');

        Route::prefix('reports')->name('reports.')->group(function (): void {
            Route::get('sales', [SalesReportController::class, 'index'])->name('sales.index');
            Route::get('sales/export', [SalesReportController::class, 'export'])->name('sales.export');
            Route::get('daily-closing', [DailyClosingController::class, 'index'])->name('daily-closing.index');
            Route::get('daily-closing/export', [DailyClosingController::class, 'export'])->name('daily-closing.export');
            Route::get('inventory', [InventoryReportController::class, 'index'])->name('inventory.index');
            Route::get('inventory/export', [InventoryReportController::class, 'export'])->name('inventory.export');
            Route::get('profit', [ProfitReportController::class, 'index'])->name('profit.index');
            Route::get('profit/export', [ProfitReportController::class, 'export'])->name('profit.export');
            Route::get('customers', [CustomersReportController::class, 'index'])->name('customers.index');
            Route::get('customers/export', [CustomersReportController::class, 'export'])->name('customers.export');
            Route::get('returns', [ReturnsReportController::class, 'index'])->name('returns.index');
            Route::get('returns/export', [ReturnsReportController::class, 'export'])->name('returns.export');
        });

        Route::get('settings/warehouse', [WarehouseController::class, 'show'])
            ->name('settings.warehouse.show');
        Route::put('settings/warehouse', [WarehouseController::class, 'update'])
            ->name('settings.warehouse.update');
        Route::get('settings/warehouse/resolve-map-link', [WarehouseController::class, 'resolveMapLink'])
            ->name('settings.warehouse.resolve-map-link');

        Route::get('settings/delivery-zones', [DeliveryZoneController::class, 'show'])
            ->name('settings.delivery-zones.show');
        Route::put('settings/delivery-zones', [DeliveryZoneController::class, 'update'])
            ->name('settings.delivery-zones.update');

        Route::get('settings/orders', [BotController::class, 'orderSettings'])
            ->name('settings.orders.show');

        Route::get('bot', [BotController::class, 'show'])->name('bot.show');
        Route::put('bot', [BotController::class, 'update'])->name('bot.update');
        Route::post('bot/webhook', [BotController::class, 'setWebhook'])
            ->name('bot.webhook.set');
        Route::delete('bot/webhook', [BotController::class, 'removeWebhook'])
            ->name('bot.webhook.remove');
        Route::get('api/verify-token', [BotController::class, 'verifyToken'])
            ->name('bot.verify-token');

        // ─── POS kassa moduli ───────────────────────────────────────────────
        Route::prefix('pos')->name('pos.')->group(function (): void {
            // Kassir terminal
            Route::get('/', [PosSaleController::class, 'index'])->name('index');
            Route::post('sales', [PosSaleController::class, 'store'])->name('sales.store');

            // Sotuvlar tarixi
            Route::get('sales', [PosSaleController::class, 'listing'])->name('sales.index');
            Route::get('sales/{sale}', [PosSaleController::class, 'show'])->name('sales.show');

            // Smenalar
            Route::get('shifts', [PosShiftController::class, 'index'])->name('shifts.index');
            Route::post('shifts/open', [PosShiftController::class, 'open'])->name('shifts.open');
            Route::get('shifts/{shift}', [PosShiftController::class, 'show'])->name('shifts.show');
            Route::post('shifts/{shift}/close', [PosShiftController::class, 'close'])->name('shifts.close');

            // Mijozlar
            Route::get('customers', [PosCustomerController::class, 'index'])->name('customers.index');
            Route::post('customers', [PosCustomerController::class, 'store'])->name('customers.store');
            Route::get('customers/lookup', [PosCustomerController::class, 'lookup'])->name('customers.lookup');
            Route::get('customers/{customer}', [PosCustomerController::class, 'show'])->name('customers.show');
            Route::put('customers/{customer}', [PosCustomerController::class, 'update'])->name('customers.update');
            Route::post('customers/{customer}/payments', [PosCustomerController::class, 'recordPayment'])
                ->name('customers.payments.store');

            // Hisobotlar — faqat owner
            Route::get('reports', [PosReportController::class, 'index'])->name('reports.index');
        });
    });
});

Route::post('locale/{code}', [LocaleController::class, 'switch'])->name('locale.switch');

// Telegram login tugmasi → ilovani ochish (custom scheme orqali).
Route::get('tg/open', function (Request $request) {
    $token = preg_replace('/[^a-f0-9]/', '', (string) $request->query('token', ''));
    $deep = 'opensales://login'.($token !== '' ? '?token='.$token : '');

    return response(
        '<!doctype html><html><head><meta charset="utf-8">'
        .'<meta http-equiv="refresh" content="0;url='.$deep.'">'
        .'<script>location.replace("'.$deep.'");</script></head>'
        .'<body style="font-family:sans-serif;text-align:center;padding:40px">'
        .'OpenSales ilovasi ochilmoqda…</body></html>'
    )->header('Content-Type', 'text/html');
})->name('tg.open');

// Invite link → mobil ilovani ochish (bot bo'lmasa ham ishlaydi).
// QR/share shu URL'ni ko'rsatadi; ilova `opensales://invite` orqali ochiladi.
Route::get('i/{token}', function (string $token) {
    $token = preg_replace('/[^a-zA-Z0-9_]/', '', $token);
    $deep = 'opensales://invite'.($token !== '' ? '?token='.$token : '');

    return response(
        '<!doctype html><html><head><meta charset="utf-8">'
        .'<meta name="viewport" content="width=device-width,initial-scale=1">'
        .'<meta http-equiv="refresh" content="0;url='.$deep.'">'
        .'<script>location.replace("'.$deep.'");</script></head>'
        .'<body style="font-family:sans-serif;text-align:center;padding:40px">'
        .'OpenSales ilovasi ochilmoqda… Agar ochilmasa, ilovada QR/kodni qo\'lda kiriting: '
        .'<br><br><code style="font-size:18px">'.e($token).'</code></body></html>'
    )->header('Content-Type', 'text/html');
})->name('invite.open');

require __DIR__.'/settings.php';
