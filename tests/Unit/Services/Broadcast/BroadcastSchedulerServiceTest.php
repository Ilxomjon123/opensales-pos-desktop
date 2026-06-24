<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Broadcast;

use App\Enums\BroadcastScheduleType;
use App\Models\BroadcastCampaign;
use App\Services\Broadcast\BroadcastSchedulerService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

final class BroadcastSchedulerServiceTest extends TestCase
{
    private BroadcastSchedulerService $scheduler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scheduler = new BroadcastSchedulerService;
    }

    public function test_daily_today_in_future(): void
    {
        $campaign = new BroadcastCampaign;
        $campaign->schedule_type = BroadcastScheduleType::DAILY;
        $campaign->schedule_config = ['time' => '15:00'];
        $campaign->timezone = 'Asia/Tashkent';

        $from = CarbonImmutable::parse('2026-05-14 10:00:00', 'Asia/Tashkent');
        $next = $this->scheduler->nextRunAt($campaign, $from);

        $this->assertNotNull($next);
        $this->assertSame('2026-05-14 15:00', $next->setTimezone('Asia/Tashkent')->format('Y-m-d H:i'));
    }

    public function test_daily_today_in_past_rolls_to_tomorrow(): void
    {
        $campaign = new BroadcastCampaign;
        $campaign->schedule_type = BroadcastScheduleType::DAILY;
        $campaign->schedule_config = ['time' => '09:00'];
        $campaign->timezone = 'Asia/Tashkent';

        $from = CarbonImmutable::parse('2026-05-14 10:00:00', 'Asia/Tashkent');
        $next = $this->scheduler->nextRunAt($campaign, $from);

        $this->assertSame('2026-05-15 09:00', $next->setTimezone('Asia/Tashkent')->format('Y-m-d H:i'));
    }

    public function test_daily_multiple_times_picks_next_today(): void
    {
        $campaign = new BroadcastCampaign;
        $campaign->schedule_type = BroadcastScheduleType::DAILY;
        $campaign->schedule_config = ['times' => ['09:00', '14:00', '18:00']];
        $campaign->timezone = 'Asia/Tashkent';

        $from = CarbonImmutable::parse('2026-05-14 10:00:00', 'Asia/Tashkent');
        $next = $this->scheduler->nextRunAt($campaign, $from);

        // 09:00 o'tib ketgan, keyingi bugungi slot = 14:00
        $this->assertSame('2026-05-14 14:00', $next->setTimezone('Asia/Tashkent')->format('Y-m-d H:i'));
    }

    public function test_daily_multiple_times_rolls_to_first_tomorrow(): void
    {
        $campaign = new BroadcastCampaign;
        $campaign->schedule_type = BroadcastScheduleType::DAILY;
        $campaign->schedule_config = ['times' => ['18:00', '09:00']];
        $campaign->timezone = 'Asia/Tashkent';

        $from = CarbonImmutable::parse('2026-05-14 20:00:00', 'Asia/Tashkent');
        $next = $this->scheduler->nextRunAt($campaign, $from);

        // bugungi barcha slotlar o'tgan, ertangi eng erta = 09:00
        $this->assertSame('2026-05-15 09:00', $next->setTimezone('Asia/Tashkent')->format('Y-m-d H:i'));
    }

    public function test_weekly_multiple_times_same_day(): void
    {
        $campaign = new BroadcastCampaign;
        $campaign->schedule_type = BroadcastScheduleType::WEEKLY;
        $campaign->schedule_config = ['times' => ['08:00', '16:00'], 'days' => [4]];
        $campaign->timezone = 'Asia/Tashkent';

        // 2026-05-14 Payshanba (dayOfWeek 4), 08:00 o'tgan => 16:00
        $from = CarbonImmutable::parse('2026-05-14 10:00:00', 'Asia/Tashkent');
        $next = $this->scheduler->nextRunAt($campaign, $from);

        $this->assertSame('2026-05-14 16:00', $next->setTimezone('Asia/Tashkent')->format('Y-m-d H:i'));
    }

    public function test_weekly_next_monday(): void
    {
        $campaign = new BroadcastCampaign;
        $campaign->schedule_type = BroadcastScheduleType::WEEKLY;
        $campaign->schedule_config = ['time' => '09:00', 'days' => [1]];
        $campaign->timezone = 'Asia/Tashkent';

        // 2026-05-14 is Thursday (dayOfWeek 4). Next Monday = 2026-05-18.
        $from = CarbonImmutable::parse('2026-05-14 10:00:00', 'Asia/Tashkent');
        $next = $this->scheduler->nextRunAt($campaign, $from);

        $this->assertSame('2026-05-18 09:00', $next->setTimezone('Asia/Tashkent')->format('Y-m-d H:i'));
    }

    public function test_monthly_next_day_of_month(): void
    {
        $campaign = new BroadcastCampaign;
        $campaign->schedule_type = BroadcastScheduleType::MONTHLY;
        $campaign->schedule_config = ['time' => '10:00', 'days' => [20]];
        $campaign->timezone = 'Asia/Tashkent';

        $from = CarbonImmutable::parse('2026-05-14 10:00:00', 'Asia/Tashkent');
        $next = $this->scheduler->nextRunAt($campaign, $from);

        $this->assertSame('2026-05-20 10:00', $next->setTimezone('Asia/Tashkent')->format('Y-m-d H:i'));
    }

    public function test_once_returns_configured_datetime(): void
    {
        $campaign = new BroadcastCampaign;
        $campaign->schedule_type = BroadcastScheduleType::ONCE;
        $campaign->schedule_config = ['datetime' => '2026-06-01 11:30'];
        $campaign->timezone = 'Asia/Tashkent';

        $next = $this->scheduler->nextRunAt($campaign, CarbonImmutable::parse('2026-05-14 10:00:00', 'Asia/Tashkent'));

        $this->assertSame('2026-06-01 11:30', $next->setTimezone('Asia/Tashkent')->format('Y-m-d H:i'));
    }

    public function test_once_returns_null_when_datetime_already_passed(): void
    {
        $campaign = new BroadcastCampaign;
        $campaign->schedule_type = BroadcastScheduleType::ONCE;
        $campaign->schedule_config = ['datetime' => '2026-06-01 11:30'];
        $campaign->timezone = 'Asia/Tashkent';

        // Yuborilgandan keyin (from > datetime) qayta hisoblansa => null,
        // shunda next_run_at tozalanadi va har daqiqa qayta yuborilmaydi.
        $next = $this->scheduler->nextRunAt($campaign, CarbonImmutable::parse('2026-06-01 11:31:00', 'Asia/Tashkent'));

        $this->assertNull($next);
    }

    public function test_ends_at_blocks_future_runs(): void
    {
        $campaign = new BroadcastCampaign;
        $campaign->schedule_type = BroadcastScheduleType::DAILY;
        $campaign->schedule_config = ['time' => '09:00'];
        $campaign->timezone = 'Asia/Tashkent';
        $campaign->ends_at = CarbonImmutable::parse('2026-05-14 23:59:00', 'Asia/Tashkent');

        $from = CarbonImmutable::parse('2026-05-14 10:00:00', 'Asia/Tashkent');
        // next would be 2026-05-15 09:00, after ends_at => null
        $this->assertNull($this->scheduler->nextRunAt($campaign, $from));
    }
}
