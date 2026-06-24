<?php

declare(strict_types=1);

namespace App\Telegram;

use App\Models\Dealer;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Log\LogManager;
use Nutgram\Laravel\RunningMode\LaravelWebhook;
use SergiX44\Nutgram\Configuration;
use SergiX44\Nutgram\Nutgram;

class BotFactory
{
    public function __construct(
        private readonly Application $app,
        private readonly CacheRepository $cache,
        private readonly LogManager $log,
    ) {}

    public function make(string $token): Nutgram
    {
        return new Nutgram($token, $this->configuration());
    }

    public function forDealer(Dealer $dealer): Nutgram
    {
        $bot = $this->make($dealer->bot_token);
        $bot->setRunningMode(LaravelWebhook::class);

        // Dealer ni container ga bind qilamiz — handlerlar DI orqali oladi
        $this->app->instance(Dealer::class, $dealer);

        // ShopResolver ni ham bind qilamiz — dealer ga bog'liq
        $this->app->bind(ShopResolver::class, fn (): ShopResolver => new ShopResolver($dealer));

        $this->loadHandlers($bot);

        return $bot;
    }

    private function configuration(): Configuration
    {
        return new Configuration(
            apiUrl: (string) config('nutgram.api_url', Configuration::DEFAULT_API_URL),
            botId: null,
            botName: null,
            testEnv: false,
            isLocal: ! $this->app->isProduction(),
            clientTimeout: (int) config('nutgram.client_timeout', 12),
            clientOptions: $this->clientOptions(),
            container: $this->app,
            cache: $this->cache,
            logger: $this->log->channel(config('nutgram.log_channel', 'stack')),
            conversationTtl: Configuration::DEFAULT_CONVERSATION_TTL,
        );
    }

    /**
     * Cloudflare Worker proxy ishlatilganda X-Proxy-Secret header avtomatik qo'shiladi.
     *
     * `expect: false` — Guzzle "Expect: 100-continue" header'ini o'chiradi. Proxy
     * zanjirida (server -> worker -> Telegram) fayl yuklashda u deadlock'ga olib
     * keladi: client 100-Continue kutadi, worker body kutadi -> timeout. Bularsiz
     * sendDocument/sendPhoto ishlamaydi.
     *
     * `connect_timeout` — ulanish bosqichi uchun alohida (read timeout'dan ajratilgan).
     * Proxy javob bermasa to'liq client_timeout kutmay tez fail bo'ladi.
     *
     * `curl` keep-alive — bir request ichida ketma-ket Telegram chaqiruvlarida TCP/TLS
     * ulanishni qayta ishlatadi (har chaqiruvga yangi handshake qilmaydi), latency kamayadi.
     *
     * @return array<string, mixed>
     */
    private function clientOptions(): array
    {
        $options = [
            'expect' => false,
            'connect_timeout' => (int) config('nutgram.connect_timeout', 5),
            'curl' => [
                CURLOPT_TCP_KEEPALIVE => 1,
                CURLOPT_TCP_KEEPIDLE => 30,
                CURLOPT_TCP_KEEPINTVL => 15,
            ],
        ];

        $proxySecret = (string) config('nutgram.proxy_secret', '');

        if ($proxySecret !== '') {
            $options['headers'] = ['X-Proxy-Secret' => $proxySecret];
        }

        return $options;
    }

    private function loadHandlers(Nutgram $bot): void
    {
        (static function () use ($bot): void {
            require base_path('routes/telegram.php');
        })();
    }
}
