<?php

declare(strict_types=1);

namespace App\Telegram\Handlers;

use App\Exceptions\Domain\InvalidInviteException;
use App\Models\Dealer;
use App\Repositories\FinanceRepository;
use App\Services\ShopInviteService;
use App\Telegram\BotLocale;
use App\Telegram\Conversations\RegisterShopConversation;
use App\Telegram\Keyboards\MainMenuKeyboard;
use App\Telegram\ShopResolver;
use Illuminate\Support\Number;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardRemove;

final class StartHandler
{
    public function __construct(
        private readonly ShopResolver $resolver,
        private readonly ShopInviteService $inviteService,
        private readonly FinanceRepository $finance,
        private readonly Dealer $dealer,
    ) {}

    public function handle(Nutgram $bot, ?string $token = null): void
    {
        BotLocale::apply($bot, $this->dealer);

        // Nutgram `start {token}` route'idan token'ni arg sifatida uzatadi,
        // fallback sifatida — matndan ham ajratib olamiz
        $param = $token !== null && $token !== ''
            ? $token
            : $this->extractStartParam($bot);

        if ($param !== null) {
            // `own_...` — diller o'z botiga bildirishnoma chatini ulayapti.
            if (str_starts_with($param, 'own_')) {
                $this->handleOwnerLink($bot, $param);

                return;
            }

            $this->handleInvite($bot, $param);

            return;
        }

        $this->showMainMenuOrPrompt($bot);
    }

    /**
     * Diller onboarding'da bergan bir martalik kod orqali bildirishnoma chatini ulaydi.
     */
    private function handleOwnerLink(Nutgram $bot, string $token): void
    {
        if ($this->dealer->owner_link_token !== $token) {
            $bot->sendMessage(
                text: __('bot.owner.invalid'),
                reply_markup: ReplyKeyboardRemove::make(remove_keyboard: true),
            );

            return;
        }

        $this->dealer->forceFill([
            'telegram_chat_id' => (int) $bot->chatId(),
            'owner_link_token' => null,
        ])->save();

        $bot->sendMessage(
            text: __('bot.owner.connected'),
            parse_mode: ParseMode::MARKDOWN_LEGACY,
            reply_markup: ReplyKeyboardRemove::make(remove_keyboard: true),
        );
    }

    private function handleInvite(Nutgram $bot, string $token): void
    {
        $invite = $this->inviteService->findValid($token);

        if ($invite === null || $invite->shop->dealer_id !== $this->dealer->id) {
            $bot->sendMessage(
                text: __('bot.invite.invalid'),
                reply_markup: ReplyKeyboardRemove::make(remove_keyboard: true),
            );

            return;
        }

        $from = $bot->user();
        $name = trim(($from?->first_name ?? '').' '.($from?->last_name ?? '')) ?: null;

        try {
            $member = $this->inviteService->redeem(
                token: $token,
                telegramId: (int) $bot->userId(),
                name: $name,
                username: $from?->username,
            );
        } catch (InvalidInviteException $e) {
            $bot->sendMessage('❌ '.$e->getMessage());

            return;
        }

        $shop = $member->shop->fresh();

        $bot->sendMessage(
            text: implode("\n", array_filter([
                __('bot.invite.success_title'),
                '',
                __('bot.invite.success_body', ['shop' => $shop->name]),
                '',
                __('bot.menu.balance', ['balance' => $this->formatMoney((int) ($shop->balance ?? 0))]),
                $this->pendingLine($shop->id),
                '',
                __('bot.menu.order_cta'),
            ], static fn ($line) => $line !== null)),
            parse_mode: ParseMode::MARKDOWN_LEGACY,
            reply_markup: MainMenuKeyboard::make($this->dealer, (int) $bot->userId()),
        );
    }

    private function showMainMenuOrPrompt(Nutgram $bot): void
    {
        $shop = $this->resolver->resolve($bot);

        if ($shop === null) {
            if ($this->dealer->isPublic()) {
                RegisterShopConversation::begin(
                    bot: $bot,
                    userId: (int) $bot->userId(),
                    chatId: (int) $bot->chatId(),
                );

                return;
            }

            $bot->sendMessage(
                text: __('bot.start.need_invite'),
                parse_mode: ParseMode::MARKDOWN_LEGACY,
                reply_markup: ReplyKeyboardRemove::make(remove_keyboard: true),
            );

            return;
        }

        $bot->sendMessage(
            text: implode("\n", array_filter([
                __('bot.menu.greeting', ['shop' => $shop->name]),
                '',
                __('bot.menu.balance', ['balance' => $this->formatMoney((int) ($shop->balance ?? 0))]),
                $this->pendingLine($shop->id),
                '',
                __('bot.menu.order_prompt'),
            ], static fn ($line) => $line !== null)),
            parse_mode: ParseMode::MARKDOWN_LEGACY,
            reply_markup: MainMenuKeyboard::make($this->dealer, (int) $bot->userId()),
        );
    }

    private function pendingLine(int $shopId): ?string
    {
        $pending = $this->finance->shopPendingTotal($shopId);

        return $pending > 0
            ? __('bot.menu.pending', ['amount' => Number::format($pending)])
            : null;
    }

    public function fallback(Nutgram $bot): void
    {
        BotLocale::apply($bot, $this->dealer);

        $shop = $this->resolver->resolve($bot);

        if ($shop === null) {
            if ($this->dealer->isPublic()) {
                RegisterShopConversation::begin(
                    bot: $bot,
                    userId: (int) $bot->userId(),
                    chatId: (int) $bot->chatId(),
                );

                return;
            }

            $bot->sendMessage(__('bot.fallback.need_start'));

            return;
        }

        $bot->sendMessage(
            text: __('bot.fallback.order_prompt'),
            reply_markup: MainMenuKeyboard::make($this->dealer, (int) $bot->userId()),
        );
    }

    private function extractStartParam(Nutgram $bot): ?string
    {
        $text = trim((string) ($bot->message()?->text ?? ''));

        if ($text === '') {
            return null;
        }

        // "/start TOKEN" yoki "/start@BotName TOKEN"
        $parts = preg_split('/\s+/', $text, 2);

        if ($parts === false || count($parts) < 2) {
            return null;
        }

        $param = trim($parts[1]);

        return $param !== '' ? $param : null;
    }

    private function formatMoney(int $amount): string
    {
        $formatted = Number::format(abs($amount));
        $currency = __('bot.currency');

        return $amount < 0 ? "-{$formatted} {$currency}" : "{$formatted} {$currency}";
    }
}
