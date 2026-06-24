<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Concerns\ReportsTelegramErrors;
use App\Enums\BroadcastMediaType;
use App\Models\BroadcastCampaign;
use App\Models\Dealer;
use App\Models\Shop;
use App\Models\ShopMember;
use App\Services\Broadcast\BroadcastRenderer;
use App\Services\Broadcast\InlineButtonsFactory;
use App\Telegram\BotFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Internal\InputFile;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use Throwable;

/**
 * Bir mijoz a'zosiga ommaviy (immediate) broadcast xabarini yuborish.
 * Rejalashtirilgan kampaniyalar bilan bir xil imkoniyatlar: shablon
 * placeholderlari, inline tugmalar va media (rasm/video/hujjat).
 * Rate-limit queue darajasida (worker serial ishlaydi).
 */
final class SendBroadcastMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ReportsTelegramErrors, SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    /**
     * @param  array<int,mixed>  $buttons  qatorlar × tugmalar (text + url)
     */
    public function __construct(
        public readonly int $dealerId,
        public readonly int $chatId,
        public readonly string $message,
        public readonly ?int $shopId = null,
        public readonly array $buttons = [],
        public readonly ?string $mediaPath = null,
        public readonly ?string $mediaType = null,
    ) {}

    public function handle(BotFactory $factory, BroadcastRenderer $renderer, InlineButtonsFactory $buttonsFactory): void
    {
        $dealer = Dealer::query()->find($this->dealerId);

        if ($dealer === null) {
            return;
        }

        $shop = $this->shopId !== null ? Shop::query()->find($this->shopId) : null;
        $member = $this->shopId !== null
            ? ShopMember::query()
                ->where('shop_id', $this->shopId)
                ->where('telegram_id', $this->chatId)
                ->first()
            : null;

        $draft = new BroadcastCampaign(['message_text' => $this->message]);
        $text = $renderer->render($draft, $shop, $dealer, $member);
        $markup = $buttonsFactory->fromRows($this->buttons);

        try {
            $bot = $factory->make($dealer->bot_token);

            if ($this->mediaPath !== null && $this->mediaType !== null) {
                $this->sendMedia($bot, $text, $markup);
            } else {
                $bot->sendMessage(
                    text: $text,
                    chat_id: $this->chatId,
                    parse_mode: ParseMode::MARKDOWN_LEGACY,
                    reply_markup: $markup,
                );
            }
        } catch (Throwable $e) {
            if ($this->isTransientTelegramError($e) && $this->attempts() < $this->tries) {
                throw $e;
            }

            $this->handleShopMemberError($e, $this->chatId, (int) $dealer->id);
        }
    }

    private function sendMedia(Nutgram $bot, string $text, ?InlineKeyboardMarkup $markup): void
    {
        $type = BroadcastMediaType::from((string) $this->mediaType);
        $media = $this->resolveMedia();
        $caption = $text !== '' ? $text : null;

        $result = match ($type) {
            BroadcastMediaType::PHOTO => $bot->sendPhoto(
                photo: $media,
                chat_id: $this->chatId,
                caption: $caption,
                parse_mode: ParseMode::MARKDOWN_LEGACY,
                reply_markup: $markup,
            ),
            BroadcastMediaType::VIDEO => $bot->sendVideo(
                video: $media,
                chat_id: $this->chatId,
                caption: $caption,
                parse_mode: ParseMode::MARKDOWN_LEGACY,
                reply_markup: $markup,
            ),
            BroadcastMediaType::DOCUMENT => $bot->sendDocument(
                document: $media,
                chat_id: $this->chatId,
                caption: $caption,
                parse_mode: ParseMode::MARKDOWN_LEGACY,
                reply_markup: $markup,
            ),
        };

        $this->cacheFileId($type, $result);
    }

    /**
     * Birinchi yuborishda faylni yuklaydi; Telegram qaytargan file_id keyingi
     * a'zolar uchun cache'lanadi (24 soat) — bir faylni qayta-qayta yuklamaslik.
     */
    private function resolveMedia(): InputFile|string
    {
        $cached = Cache::get($this->fileIdCacheKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $disk = Storage::disk('public');

        return new InputFile($disk->path((string) $this->mediaPath), basename((string) $this->mediaPath));
    }

    private function cacheFileId(BroadcastMediaType $type, mixed $result): void
    {
        if ($result === null || Cache::has($this->fileIdCacheKey())) {
            return;
        }

        $fileId = match ($type) {
            BroadcastMediaType::PHOTO => $this->largestPhotoFileId($result),
            BroadcastMediaType::VIDEO => $result->video?->file_id ?? null,
            BroadcastMediaType::DOCUMENT => $result->document?->file_id ?? null,
        };

        if (is_string($fileId) && $fileId !== '') {
            Cache::put($this->fileIdCacheKey(), $fileId, now()->addDay());
        }
    }

    private function largestPhotoFileId(mixed $message): ?string
    {
        $photos = $message->photo ?? null;

        if (! is_array($photos) || $photos === []) {
            return null;
        }

        $last = $photos[array_key_last($photos)];

        return $last->file_id ?? null;
    }

    private function fileIdCacheKey(): string
    {
        return 'broadcast:fileid:'.md5((string) $this->mediaPath);
    }
}
