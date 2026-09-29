<?php

namespace Tests\Feature;

use App\Models\Translation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_every_locale_has_the_same_message_keys_as_english(): void
    {
        $en = array_keys(require lang_path('en/messages.php'));

        foreach (config('app.supported_locales') as $locale) {
            $this->assertSame($en, array_keys(require lang_path("$locale/messages.php")), $locale);
        }
    }

    public function test_accept_language_picks_the_locale(): void
    {
        $this->postJson('/api/v1/auth/otp/request', ['phone' => '+10000000000'], ['Accept-Language' => 'ar'])
            ->assertJsonPath('message', 'إذا كان هذا الرقم مسجلاً، فقد تم إرسال رمز التحقق.');
    }

    public function test_user_locale_beats_accept_language(): void
    {
        $user = User::factory()->create(['locale' => 'es', 'email' => 'es@example.com', 'password' => 'right-pass']);
        $token = $user->createToken('t')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/auth/otp/request', ['phone' => '+10000000000'], ['Accept-Language' => 'de'])
            ->assertJsonPath('message', 'Si este número está registrado, se ha enviado un código de verificación.');
    }

    public function test_translations_table_overrides_files(): void
    {
        Translation::create(['locale' => 'en', 'group' => 'messages', 'key' => 'otp_sent', 'value' => 'Code on its way.']);

        $this->postJson('/api/v1/auth/otp/request', ['phone' => '+10000000000'])
            ->assertJsonPath('message', 'Code on its way.');
    }
}
