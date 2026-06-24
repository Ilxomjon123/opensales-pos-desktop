<?php

declare(strict_types=1);

namespace App\Telegram\Conversations;

use App\Exceptions\Domain\OutsideDeliveryZoneException;
use App\Models\Dealer;
use App\Repositories\FinanceRepository;
use App\Services\PublicShopRegistrationService;
use App\Telegram\BotLocale;
use App\Telegram\Keyboards\MainMenuKeyboard;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Keyboard\KeyboardButton;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardMarkup;
use SergiX44\Nutgram\Telegram\Types\Keyboard\ReplyKeyboardRemove;
use Throwable;

/**
 * Public bot uchun ro'yxatdan o'tish oqimi:
 * 1. Manzil so'raladi (matn yoki Joylashuv tugmasi orqali)
 * 2. Telefon ixtiyoriy (Kontakt yuborish yoki "O'tkazib yuborish")
 * 3. Shop + ShopMember atomic yaratiladi → MainMenu
 *
 * DI servislar har stepda containerdan qayta olinadi (serializatsiya
 * orqali tashilmasin) — Conversation cache'i faqat ma'lumot fieldlarini
 * saqlaydi.
 */
final class RegisterShopConversation extends Conversation
{
    private ?int $dealerId = null;

    private ?string $address = null;

    private ?float $latitude = null;

    private ?float $longitude = null;

    public function start(Nutgram $bot): void
    {
        $this->dealerId ??= $this->dealer()->id;
        BotLocale::apply($bot, $this->dealer());

        $bot->sendMessage(
            text: __('bot.register.welcome'),
            parse_mode: ParseMode::MARKDOWN_LEGACY,
            reply_markup: ReplyKeyboardMarkup::make(
                resize_keyboard: true,
                one_time_keyboard: true,
            )->addRow(
                KeyboardButton::make(__('bot.button.send_location'), request_location: true),
            ),
        );

        $this->next('handleAddress');
    }

    public function handleAddress(Nutgram $bot): void
    {
        BotLocale::apply($bot, $this->dealer());

        $message = $bot->message();

        if ($message?->location !== null) {
            $this->latitude = $message->location->latitude;
            $this->longitude = $message->location->longitude;
            $this->address = sprintf(
                'Joylashuv: %.6f, %.6f',
                $this->latitude,
                $this->longitude,
            );
        } else {
            $text = trim((string) ($message?->text ?? ''));

            if ($text === '' || mb_strlen($text) < 5) {
                $bot->sendMessage(__('bot.register.address_too_short'));
                $this->next('handleAddress');

                return;
            }

            $this->address = $text;
        }

        $bot->sendMessage(
            text: __('bot.register.address_saved'),
            reply_markup: ReplyKeyboardMarkup::make(
                resize_keyboard: true,
                one_time_keyboard: true,
            )
                ->addRow(KeyboardButton::make(__('bot.button.send_phone'), request_contact: true))
                ->addRow(KeyboardButton::make(__('bot.button.skip'))),
        );

        $this->next('handlePhone');
    }

    public function handlePhone(Nutgram $bot): void
    {
        BotLocale::apply($bot, $this->dealer());

        $message = $bot->message();
        $phone = null;

        if ($message?->contact !== null) {
            $phone = $message->contact->phone_number;
        } else {
            $text = trim((string) ($message?->text ?? ''));

            if ($text !== '' && $text !== __('bot.button.skip')) {
                $phone = $text;
            }
        }

        try {
            $this->finalize($bot, $phone);
        } catch (OutsideDeliveryZoneException $e) {
            $bot->sendMessage(
                text: "❌ {$e->getMessage()}",
                reply_markup: ReplyKeyboardRemove::make(remove_keyboard: true),
            );

            $this->end();

            return;
        } catch (Throwable $e) {
            Log::error('RegisterShopConversation finalize failed', [
                'dealer_id' => $this->dealerId,
                'telegram_id' => $bot->userId(),
                'address' => $this->address,
                'phone' => $phone,
                'exception' => $e,
            ]);

            $bot->sendMessage(
                text: __('bot.register.error'),
                reply_markup: ReplyKeyboardRemove::make(remove_keyboard: true),
            );
        }

        $this->end();
    }

    private function finalize(Nutgram $bot, ?string $phone): void
    {
        $from = $bot->user();
        $name = trim(($from?->first_name ?? '').' '.($from?->last_name ?? ''))
            ?: ($from?->username ?? __('bot.register.fallback_name', ['id' => $bot->userId()]));

        if ($this->address === null || $this->address === '') {
            throw new \RuntimeException('Address is empty when finalizing public registration.');
        }

        $dealer = $this->dealer();

        $member = $this->registrationService()->register(
            dealer: $dealer,
            telegramId: (int) $bot->userId(),
            shopName: $name,
            address: $this->address,
            latitude: $this->latitude,
            longitude: $this->longitude,
            phone: $phone,
            username: $from?->username,
        );

        // Ro'yxatdan oldin tanlangan til mijoz yozuviga ko'chiriladi.
        BotLocale::persist($bot, app()->getLocale(), $dealer);

        $shop = $member->shop;

        $bot->sendMessage(
            text: __('bot.register.done'),
            reply_markup: ReplyKeyboardRemove::make(remove_keyboard: true),
        );

        $bot->sendMessage(
            text: implode("\n", array_filter([
                __('bot.register.new_customer_title', ['shop' => $shop->name]),
                '',
                __('bot.register.address_line', ['address' => $shop->address]),
                __('bot.menu.balance', ['balance' => $this->formatMoney((int) ($shop->balance ?? 0))]),
                $this->pendingLine($shop->id),
                '',
                __('bot.menu.order_cta'),
            ], static fn ($line) => $line !== null)),
            parse_mode: ParseMode::MARKDOWN_LEGACY,
            reply_markup: MainMenuKeyboard::make($dealer, (int) $bot->userId()),
        );
    }

    private function pendingLine(int $shopId): ?string
    {
        $pending = $this->finance()->shopPendingTotal($shopId);

        return $pending > 0
            ? __('bot.menu.pending', ['amount' => Number::format($pending)])
            : null;
    }

    private function formatMoney(int $amount): string
    {
        $formatted = Number::format(abs($amount));
        $currency = __('bot.currency');

        return $amount < 0 ? "-{$formatted} {$currency}" : "{$formatted} {$currency}";
    }

    private function dealer(): Dealer
    {
        return app(Dealer::class);
    }

    private function registrationService(): PublicShopRegistrationService
    {
        return app(PublicShopRegistrationService::class);
    }

    private function finance(): FinanceRepository
    {
        return app(FinanceRepository::class);
    }

    /**
     * Faqat ma'lumot fieldlarini cache ga saqlaymiz — DI servislari va
     * Eloquent modellari serializatsiya muammolarini keltirib chiqarmasin.
     *
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        return [
            'step' => $this->step,
            'userId' => $this->getUserId(),
            'chatId' => $this->getChatId(),
            'dealerId' => $this->dealerId,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function __unserialize(array $data): void
    {
        $this->step = $data['step'] ?? 'start';
        $this->dealerId = $data['dealerId'] ?? null;
        $this->address = $data['address'] ?? null;
        $this->latitude = $data['latitude'] ?? null;
        $this->longitude = $data['longitude'] ?? null;

        $reflect = new \ReflectionClass(Conversation::class);

        if ($reflect->hasProperty('userId')) {
            $userIdProp = $reflect->getProperty('userId');
            $userIdProp->setValue($this, $data['userId'] ?? null);
        }

        if ($reflect->hasProperty('chatId')) {
            $chatIdProp = $reflect->getProperty('chatId');
            $chatIdProp->setValue($this, $data['chatId'] ?? null);
        }
    }
}
