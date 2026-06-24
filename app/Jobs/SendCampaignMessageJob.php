<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Concerns\ReportsTelegramErrors;
use App\Enums\BroadcastMediaType;
use App\Enums\BroadcastMessageStatus;
use App\Enums\BroadcastRunStatus;
use App\Models\BroadcastCampaign;
use App\Models\BroadcastMessage;
use App\Models\BroadcastRun;
use App\Models\Dealer;
use App\Models\ShopMember;
use App\Services\Broadcast\BroadcastRenderer;
use App\Services\Broadcast\InlineButtonsFactory;
use App\Telegram\BotFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimitedWithRedis;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use SergiX44\Nutgram\Telegram\Types\Internal\InputFile;
use SergiX44\Nutgram\Telegram\Types\Keyboard\InlineKeyboardMarkup;
use Throwable;

/**
 * Campaign'dan bitta xabar yuborish (media + buttons + template variables).
 * Rate limit: Redis throttle 25 msg/sek per bot.
 */
final class SendCampaignMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, ReportsTelegramErrors, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int $messageId,
        public readonly int $dealerId = 0,
    ) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new RateLimitedWithRedis('telegram-bot')];
    }

    /**
     * AppServiceProvider da ro'yxatga olingan rate limiter callback bu metodni chaqiradi.
     */
    public function rateLimiterDealerId(): int
    {
        return $this->dealerId;
    }

    public function handle(BotFactory $factory, BroadcastRenderer $renderer, InlineButtonsFactory $buttonsFactory): void
    {
        $message = BroadcastMessage::query()->with(['run.campaign', 'shop', 'dealer'])->find($this->messageId);

        if ($message === null) {
            return;
        }

        $run = $message->run;
        $campaign = $run?->campaign;
        $dealer = $message->dealer ?? ($campaign?->dealer) ?? Dealer::query()->find($message->dealer_id);

        if ($campaign === null || $dealer === null) {
            $this->markFailed($message, 'Campaign yoki dealer topilmadi');

            return;
        }

        $member = $message->shop_id !== null
            ? ShopMember::query()
                ->where('shop_id', $message->shop_id)
                ->where('telegram_id', $message->chat_id)
                ->first()
            : null;

        $text = $renderer->render($campaign, $message->shop, $dealer, $member);
        $buttons = $buttonsFactory->fromRows((array) ($campaign->buttons ?? []));

        try {
            $bot = $factory->make($dealer->bot_token);
            $telegramMessageId = $this->send($bot, $message->chat_id, $campaign, $text, $buttons);

            $message->forceFill([
                'status' => BroadcastMessageStatus::SENT,
                'telegram_message_id' => $telegramMessageId,
                'sent_at' => Carbon::now(),
                'error' => null,
            ])->save();

            if ($run !== null) {
                $this->bumpAndMaybeComplete($run->id, 'success_count');
            }
        } catch (Throwable $e) {
            // Transient (timeout/429/5xx) — oxirgi urinish bo'lmasa qayta tashlaymiz,
            // queue retry qiladi (markFailed/counter o'zgartirmasdan).
            if ($this->isTransientTelegramError($e) && $this->attempts() < $this->tries) {
                throw $e;
            }

            $this->markFailed($message, $e->getMessage());

            if ($run !== null) {
                $this->bumpAndMaybeComplete($run->id, 'failed_count');
            }

            $this->handleShopMemberError($e, (int) $message->chat_id, (int) $dealer->id);
        }
    }

    private function send(Nutgram $bot, int $chatId, BroadcastCampaign $campaign, string $text, ?InlineKeyboardMarkup $markup): ?int
    {
        $parseMode = ParseMode::MARKDOWN_LEGACY;

        if ($campaign->media_path !== null && $campaign->media_type !== null) {
            // file_id cache: birinchi muvaffaqiyatli send qaytargan ID'ni
            // campaign'da saqlaymiz, keyingi yuborishlar uchun fayl o'rniga string ishlatamiz.
            $media = $campaign->telegram_file_id ?? $this->prepareMediaUpload($campaign);

            $result = match ($campaign->media_type) {
                BroadcastMediaType::PHOTO => $bot->sendPhoto(
                    photo: $media,
                    chat_id: $chatId,
                    caption: $text !== '' ? $text : null,
                    parse_mode: $parseMode,
                    reply_markup: $markup,
                ),
                BroadcastMediaType::VIDEO => $bot->sendVideo(
                    video: $media,
                    chat_id: $chatId,
                    caption: $text !== '' ? $text : null,
                    parse_mode: $parseMode,
                    reply_markup: $markup,
                ),
                BroadcastMediaType::DOCUMENT => $bot->sendDocument(
                    document: $media,
                    chat_id: $chatId,
                    caption: $text !== '' ? $text : null,
                    parse_mode: $parseMode,
                    reply_markup: $markup,
                ),
            };

            if ($campaign->telegram_file_id === null) {
                $this->captureFileId($campaign, $result);
            }

            return $result?->message_id !== null ? (int) $result->message_id : null;
        }

        $result = $bot->sendMessage(
            text: $text,
            chat_id: $chatId,
            parse_mode: $parseMode,
            reply_markup: $markup,
        );

        return $result?->message_id !== null ? (int) $result->message_id : null;
    }

    private function prepareMediaUpload(BroadcastCampaign $campaign): InputFile
    {
        $disk = Storage::disk('public');
        $absolute = $disk->path((string) $campaign->media_path);
        $fileName = basename((string) $campaign->media_path);

        return new InputFile($absolute, $fileName);
    }

    /**
     * Telegram javobidan tegishli file_id ni olib campaign'ga yozadi.
     * Race-safe: bir nechta parallel worker bir vaqtda yozsa ham xato bermaydi
     * (oxirgi yozish g'olib, qiymat funksional ekvivalent).
     */
    private function captureFileId(BroadcastCampaign $campaign, mixed $result): void
    {
        if ($result === null) {
            return;
        }

        $fileId = match ($campaign->media_type) {
            BroadcastMediaType::PHOTO => $this->largestPhotoFileId($result),
            BroadcastMediaType::VIDEO => $result->video?->file_id ?? null,
            BroadcastMediaType::DOCUMENT => $result->document?->file_id ?? null,
            default => null,
        };

        if (! is_string($fileId) || $fileId === '') {
            return;
        }

        // Faqat NULL bo'lsa yangilaymiz — eski cache'ni qayta yozmaslik
        BroadcastCampaign::query()
            ->whereKey($campaign->id)
            ->whereNull('telegram_file_id')
            ->update(['telegram_file_id' => $fileId]);

        $campaign->telegram_file_id = $fileId;
    }

    private function largestPhotoFileId(mixed $message): ?string
    {
        $photos = $message->photo ?? null;

        if (! is_array($photos) || $photos === []) {
            return null;
        }

        // Telegram PhotoSize[] eng kichigidan eng kattagacha qaytaradi
        $largest = end($photos);

        return is_object($largest) ? ($largest->file_id ?? null) : null;
    }

    private function markFailed(BroadcastMessage $message, string $error): void
    {
        $message->forceFill([
            'status' => BroadcastMessageStatus::FAILED,
            'error' => mb_substr($error, 0, 2000),
        ])->save();
    }

    /**
     * Atomik usulda counter ni oshirish va shu jarayonda runni yakunlash.
     * Faqat oxirgi worker (processed + 1 == total) statusni yangilaydi —
     * `whereColumn('success_count + failed_count + 1', '=', 'total_recipients')` guard
     * yordamida bir nechta parallel worker ham bir martagina trigger qiladi.
     */
    private function bumpAndMaybeComplete(int $runId, string $column): void
    {
        DB::transaction(function () use ($runId, $column): void {
            // Lock the row to make read-modify-write race-free
            $run = BroadcastRun::query()
                ->whereKey($runId)
                ->lockForUpdate()
                ->first();

            if ($run === null) {
                return;
            }

            $run->{$column} = (int) $run->{$column} + 1;

            $processed = (int) $run->success_count + (int) $run->failed_count;

            if ($processed >= (int) $run->total_recipients && $run->status === BroadcastRunStatus::RUNNING) {
                $run->status = $run->failed_count === $run->total_recipients
                    ? BroadcastRunStatus::FAILED
                    : BroadcastRunStatus::COMPLETED;
                $run->completed_at = Carbon::now();
            }

            $run->save();
        });
    }
}
