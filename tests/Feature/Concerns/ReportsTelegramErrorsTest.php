<?php

declare(strict_types=1);

namespace Tests\Feature\Concerns;

use App\Concerns\ReportsTelegramErrors;
use App\Models\Dealer;
use App\Models\Shop;
use App\Models\ShopMember;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use RuntimeException;
use SergiX44\Nutgram\Telegram\Exceptions\TelegramException;
use Tests\TestCase;

final class ReportsTelegramErrorsTest extends TestCase
{
    use RefreshDatabase;

    private object $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new class
        {
            use ReportsTelegramErrors;

            public function callHandleShopMember(\Throwable $e, int $telegramId, int $dealerId): void
            {
                $this->handleShopMemberError($e, $telegramId, $dealerId);
            }

            public function callReportUnlessBenign(\Throwable $e): void
            {
                $this->reportUnlessBenign($e);
            }
        };
    }

    public function test_blocked_error_flags_member_in_dealer_scope_only(): void
    {
        Exceptions::fake();

        $dealerA = Dealer::factory()->create();
        $dealerB = Dealer::factory()->create();
        $shopA = Shop::factory()->for($dealerA)->create();
        $shopB = Shop::factory()->for($dealerB)->create();

        // Bir xil telegram_id ikki dillerda — faqat A flag bo'lishi kerak.
        $memberA = ShopMember::factory()->for($shopA)->create(['telegram_id' => 777, 'blocked_at' => null]);
        $memberB = ShopMember::factory()->for($shopB)->create(['telegram_id' => 777, 'blocked_at' => null]);

        $this->subject->callHandleShopMember(
            new TelegramException('Forbidden: bot was blocked by the user', 403),
            777,
            $dealerA->id,
        );

        $this->assertNotNull($memberA->fresh()->blocked_at);
        $this->assertNull($memberB->fresh()->blocked_at);
        Exceptions::assertNothingReported();
    }

    public function test_non_telegram_error_is_reported(): void
    {
        Exceptions::fake();

        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();
        $member = ShopMember::factory()->for($shop)->create(['telegram_id' => 888, 'blocked_at' => null]);

        $this->subject->callHandleShopMember(new RuntimeException('boom'), 888, $dealer->id);

        $this->assertNull($member->fresh()->blocked_at);
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_report_unless_benign_skips_benign(): void
    {
        Exceptions::fake();

        $this->subject->callReportUnlessBenign(new TelegramException('chat not found', 400));

        Exceptions::assertNothingReported();
    }

    public function test_transient_timeout_is_not_reported_and_not_flagged(): void
    {
        Exceptions::fake();

        $dealer = Dealer::factory()->create();
        $shop = Shop::factory()->for($dealer)->create();
        $member = ShopMember::factory()->for($shop)->create(['telegram_id' => 999, 'blocked_at' => null]);

        $timeout = new ConnectException(
            'cURL error 28: Operation timed out after 30002 milliseconds',
            new Request('POST', '/sendMessage'),
        );

        $this->subject->callHandleShopMember($timeout, 999, $dealer->id);
        $this->subject->callReportUnlessBenign($timeout);

        $this->assertNull($member->fresh()->blocked_at);
        Exceptions::assertNothingReported();
    }

    public function test_telegram_5xx_is_transient_and_not_reported(): void
    {
        Exceptions::fake();

        $this->subject->callReportUnlessBenign(new TelegramException('Bad Gateway', 502));
        $this->subject->callReportUnlessBenign(new TelegramException('Too Many Requests', 429));

        Exceptions::assertNothingReported();
    }
}
