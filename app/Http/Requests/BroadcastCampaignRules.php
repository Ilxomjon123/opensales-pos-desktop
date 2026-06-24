<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\BroadcastAudienceType;
use App\Enums\BroadcastMediaType;
use App\Enums\BroadcastScheduleType;
use Illuminate\Validation\Rule;

/**
 * Broadcast campaign uchun umumiy validatsiya qoidalari.
 * Dealer va admin form request lari shu qoidalarni audience type bilan filtrlab oladi.
 */
final class BroadcastCampaignRules
{
    /**
     * @param  list<BroadcastAudienceType>  $allowedAudiences
     * @return array<string, mixed>
     */
    public static function rules(array $allowedAudiences): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'message_text' => ['required', 'string', 'min:1', 'max:4000'],
            'media' => ['nullable', 'file', 'max:20480'],
            'media_type' => ['nullable', Rule::in(array_map(fn (BroadcastMediaType $t): string => $t->value, BroadcastMediaType::cases()))],
            'remove_media' => ['nullable', 'boolean'],

            'buttons' => ['nullable', 'array', 'max:5'],
            'buttons.*' => ['array', 'min:1', 'max:3'],
            'buttons.*.*.text' => ['required_with:buttons.*', 'string', 'max:64'],
            'buttons.*.*.url' => ['required_with:buttons.*', 'string', 'url', 'max:256'],

            ...self::audienceRules($allowedAudiences),

            'schedule_type' => ['required', Rule::in(array_map(fn (BroadcastScheduleType $t): string => $t->value, BroadcastScheduleType::cases()))],
            'schedule_config' => ['required', 'array'],
            'schedule_config.datetime' => ['nullable', 'date'],
            'schedule_config.time' => ['nullable', 'string', 'regex:/^\d{1,2}:\d{2}$/'],
            'schedule_config.times' => ['nullable', 'array', 'max:24'],
            'schedule_config.times.*' => ['string', 'regex:/^\d{1,2}:\d{2}$/'],
            'schedule_config.days' => ['nullable', 'array'],
            'schedule_config.days.*' => ['integer', 'min:0', 'max:31'],

            'timezone' => ['nullable', 'string', 'max:64'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Faqat auditoriya qoidalari — ommaviy (immediate) broadcast ham shu qoidalardan foydalanadi.
     *
     * @param  list<BroadcastAudienceType>  $allowedAudiences
     * @return array<string, mixed>
     */
    public static function audienceRules(array $allowedAudiences): array
    {
        $audienceValues = array_map(fn (BroadcastAudienceType $a): string => $a->value, $allowedAudiences);

        return [
            'audience_type' => ['required', Rule::in($audienceValues)],
            'audience_config' => ['nullable', 'array'],
            'audience_config.shop_ids' => ['array'],
            'audience_config.shop_ids.*' => ['integer'],
            'audience_config.dealer_ids' => ['array'],
            'audience_config.dealer_ids.*' => ['integer'],
            'audience_config.balance_op' => ['nullable', Rule::in(['<', '<=', '=', '>=', '>'])],
            'audience_config.balance_value' => ['nullable', 'integer'],
            'audience_config.debtors_only' => ['nullable', 'boolean'],
            'audience_config.min_days_since_last_order' => ['nullable', 'integer', 'min:1', 'max:365'],
            'audience_config.region' => ['nullable', 'string', 'max:64'],
            'audience_config.category_ids' => ['nullable', 'array'],
            'audience_config.category_ids.*' => ['integer'],
        ];
    }
}
