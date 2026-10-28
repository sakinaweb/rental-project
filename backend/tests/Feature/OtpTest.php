<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Sms\AndroidSmsGateway;
use App\Services\Sms\SmsDriver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class OtpTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private object $sms;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(SmsDriver::class, $this->sms = new class implements SmsDriver
        {
            /** @var list<array{to: string, message: string}> */
            public array $sent = [];

            public function send(string $to, string $message): void
            {
                $this->sent[] = ['to' => $to, 'message' => $message];
            }
        });
    }

    private function lastCode(): string
    {
        preg_match('/\d{6}/', end($this->sms->sent)['message'], $m);

        return $m[0];
    }

    public function test_request_and_verify_issues_token(): void
    {
        User::factory()->create(['phone' => '+919900000002']);

        $this->postJson('/api/v1/auth/otp/request', ['phone' => '+919900000002'])->assertOk();
        $this->assertCount(1, $this->sms->sent);
        $this->assertSame('+919900000002', $this->sms->sent[0]['to']);

        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '+919900000002', 'code' => $this->lastCode()])
            ->assertOk()->assertJsonStructure(['token', 'user']);
    }

    public function test_code_is_single_use(): void
    {
        User::factory()->create(['phone' => '+919900000003']);
        $this->postJson('/api/v1/auth/otp/request', ['phone' => '+919900000003']);
        $payload = ['phone' => '+919900000003', 'code' => $this->lastCode()];

        $this->postJson('/api/v1/auth/otp/verify', $payload)->assertOk();
        $this->postJson('/api/v1/auth/otp/verify', $payload)->assertUnprocessable();
    }

    public function test_unknown_phone_gets_same_answer_and_no_sms(): void
    {
        $this->postJson('/api/v1/auth/otp/request', ['phone' => '+919900000999'])
            ->assertOk()->assertJsonPath('message', __('messages.otp_sent'));
        $this->assertCount(0, $this->sms->sent);
    }

    public function test_locks_after_five_wrong_codes(): void
    {
        User::factory()->create(['phone' => '+919900000004']);
        $this->postJson('/api/v1/auth/otp/request', ['phone' => '+919900000004']);
        $right = $this->lastCode();
        $wrong = $right === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/otp/verify', ['phone' => '+919900000004', 'code' => $wrong])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '+919900000004', 'code' => $right])->assertUnprocessable();
    }

    public function test_sends_are_rate_limited_per_phone(): void
    {
        User::factory()->create(['phone' => '+919900000005']);

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/auth/otp/request', ['phone' => '+919900000005'])->assertOk();
        }

        $this->assertCount(3, $this->sms->sent);
    }

    public function test_twilio_verify_sends_and_checks_the_code_behind_our_limits(): void
    {
        config(['services.twilio' => ['account_sid' => 'ACtest', 'auth_token' => 'secret', 'verify_sid' => 'VAtest']]);
        Http::fake([
            'verify.twilio.com/v2/Services/VAtest/Verifications' => Http::response(['status' => 'pending']),
            'verify.twilio.com/v2/Services/VAtest/VerificationCheck' => fn ($request) => Http::response(['status' => $request['Code'] === '123456' ? 'approved' : 'pending']),
        ]);
        User::factory()->create(['phone' => '+919900000007', 'locale' => 'hi']);

        $this->postJson('/api/v1/auth/otp/request', ['phone' => '+919900000007'])->assertOk();
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/Verifications') && $r['To'] === '+919900000007' && $r['Channel'] === 'sms' && $r['Locale'] === 'hi');
        $this->assertCount(0, $this->sms->sent); // nothing through the generic SMS driver

        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '+919900000007', 'code' => '000000'])->assertUnprocessable();
        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '+919900000007', 'code' => '123456'])->assertOk()->assertJsonStructure(['token']);
        // single use: our record is gone, Twilio is not asked again
        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '+919900000007', 'code' => '123456'])->assertUnprocessable();
        Http::assertSentCount(3);
    }

    public function test_twilio_refusal_is_logged_and_answer_stays_the_same(): void
    {
        config(['services.twilio' => ['account_sid' => 'ACtest', 'auth_token' => 'secret', 'verify_sid' => 'VAtest']]);
        Http::fake(['verify.twilio.com/*' => Http::response(['code' => 21608, 'message' => 'unverified number'], 400)]);
        Log::spy();
        User::factory()->create(['phone' => '+919900000008']);

        $this->postJson('/api/v1/auth/otp/request', ['phone' => '+919900000008'])
            ->assertOk()->assertJsonPath('message', __('messages.otp_sent'));
        Log::shouldHaveReceived('warning')->withArgs(fn ($msg, $ctx) => $msg === 'Twilio Verify send failed' && $ctx['code'] === 21608);
        $this->assertCount(0, $this->sms->sent); // no phone gateway configured: nothing else is sent
    }

    public function test_phone_gateway_takes_over_when_twilio_refuses(): void
    {
        config(['services.twilio' => ['account_sid' => 'ACtest', 'auth_token' => 'secret', 'verify_sid' => 'VAtest']]);
        config(['services.sms_gateway.url' => 'http://192.168.1.50:8080/message']);
        Http::fake(['verify.twilio.com/*' => Http::response(['code' => 21608, 'message' => 'unverified number'], 400)]);
        User::factory()->create(['phone' => '+919900000009']);

        $this->postJson('/api/v1/auth/otp/request', ['phone' => '+919900000009'])->assertOk();
        $this->assertCount(1, $this->sms->sent);

        // Our own code is checked locally; Twilio is not asked.
        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '+919900000009', 'code' => $this->lastCode()])->assertOk()->assertJsonStructure(['token']);
        Http::assertSentCount(1);
    }

    public function test_sms_is_in_the_users_locale(): void
    {
        User::factory()->create(['phone' => '+919900000006', 'locale' => 'de']);

        $this->postJson('/api/v1/auth/otp/request', ['phone' => '+919900000006']);

        $this->assertStringStartsWith('Ihr Bestätigungscode lautet', $this->sms->sent[0]['message']);
    }

    public function test_android_phone_gateway_sends_the_code_and_survives_an_unreachable_phone(): void
    {
        config(['services.sms_gateway' => ['url' => 'http://192.168.1.50:8080/message', 'username' => 'sms', 'password' => 'gate-pass']]);
        $this->app->forgetInstance(SmsDriver::class); // back to the real binding instead of setUp()'s recorder
        $this->assertInstanceOf(AndroidSmsGateway::class, app(SmsDriver::class));

        Http::fake(['192.168.1.50:8080/*' => Http::response(['id' => 'msg1'], 202)]);
        User::factory()->create(['phone' => '+919900000007']);
        $this->postJson('/api/v1/auth/otp/request', ['phone' => '+919900000007'])->assertOk();

        Http::assertSent(fn ($request) => $request->url() === 'http://192.168.1.50:8080/message'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('sms:gate-pass'))
            && $request['phoneNumbers'] === ['+919900000007']
            && preg_match('/\d{6}/', $request['textMessage']['text']) === 1);

        // "SMS Gateway Free" app: an API key selects its header and body.
        config(['services.sms_gateway.url' => 'http://192.168.1.50:8080/api/send', 'services.sms_gateway.api_key' => 'key-123']);
        User::factory()->create(['phone' => '+919900000010']);
        $this->postJson('/api/v1/auth/otp/request', ['phone' => '+919900000010'])->assertOk();
        Http::assertSent(fn ($request) => $request->url() === 'http://192.168.1.50:8080/api/send'
            && $request->hasHeader('X-API-Key', 'key-123')
            && $request['phone_number'] === '+919900000010'
            && preg_match('/\d{6}/', $request['message']) === 1);

        // Phone off or out of the network: logged, and the user still gets the usual answer.
        Http::fake(fn () => throw new ConnectionException('timeout'));
        Log::spy();
        User::factory()->create(['phone' => '+919900000008']);
        $this->postJson('/api/v1/auth/otp/request', ['phone' => '+919900000008'])->assertOk();
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => $message === 'SMS gateway unreachable');
    }
}
