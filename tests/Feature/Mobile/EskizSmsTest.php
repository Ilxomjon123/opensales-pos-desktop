<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class EskizSmsTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_request_sends_via_eskiz_when_configured(): void
    {
        config()->set('services.sms.driver', 'eskiz');
        config()->set('services.eskiz.email', 'a@b.uz');
        config()->set('services.eskiz.password', 'secret');

        Http::fake([
            '*/api/auth/login' => Http::response(['data' => ['token' => 'tok123']], 200),
            '*/api/message/sms/send' => Http::response(['status' => 'success'], 200),
        ]);

        $ok = app(OtpService::class)->request('+998901112233');

        $this->assertTrue($ok);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/api/auth/login'));
        Http::assertSent(fn ($r) => str_contains($r->url(), '/api/message/sms/send'));
    }

    public function test_log_driver_does_not_call_http(): void
    {
        config()->set('services.sms.driver', 'log');
        Http::fake();

        app(OtpService::class)->request('+998901112244');

        Http::assertNothingSent();
    }
}
