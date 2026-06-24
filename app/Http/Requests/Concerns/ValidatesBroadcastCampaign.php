<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Enums\BroadcastScheduleType;
use Illuminate\Validation\Validator;

/**
 * BroadcastCampaign uchun schedule_type ga bog'liq qo'shimcha validatsiya.
 * Trait — dealer va admin form request lari uchun umumiy.
 */
trait ValidatesBroadcastCampaign
{
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $type = $this->input('schedule_type');

            if ($type === BroadcastScheduleType::ONCE->value) {
                $datetime = $this->input('schedule_config.datetime');

                if (! is_string($datetime) || $datetime === '') {
                    $v->errors()->add('schedule_config.datetime', 'Sana va vaqt kiritilishi shart');
                }

                return;
            }

            if (in_array($type, [BroadcastScheduleType::WEEKLY->value, BroadcastScheduleType::MONTHLY->value], true)) {
                $days = $this->input('schedule_config.days', []);

                if (! is_array($days) || count($days) === 0) {
                    $v->errors()->add('schedule_config.days', 'Kamida bitta kun tanlanishi kerak');
                }
            }
        });
    }
}
