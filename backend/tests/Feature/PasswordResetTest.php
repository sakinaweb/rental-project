<?php

namespace Tests\Feature;

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\ResetPassword as ResetPasswordPage;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function requestToken(User $user): string
    {
        $token = null;
        $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email])->assertOk();
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use (&$token) {
            $token = $n->token;

            return true;
        });

        return $token;
    }

    public function test_api_reset_changes_the_password_and_revokes_tokens(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.com', 'password' => 'old-password']);
        $user->createToken('phone');

        $token = $this->requestToken($user);

        $this->postJson('/api/v1/auth/password/reset', ['email' => $user->email, 'token' => $token, 'password' => 'new-password', 'password_confirmation' => 'new-password'])
            ->assertOk()->assertJsonPath('message', __('messages.password_reset_done'));

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'new-password'])->assertOk();

        // A token works once.
        $this->postJson('/api/v1/auth/password/reset', ['email' => $user->email, 'token' => $token, 'password' => 'other-password', 'password_confirmation' => 'other-password'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_forgot_answers_the_same_for_unknown_emails(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/password/forgot', ['email' => 'nobody@example.com'])
            ->assertOk()->assertJsonPath('message', __('messages.password_reset_sent'));
        Notification::assertNothingSent();
    }

    public function test_wrong_token_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com', 'password' => 'old-password']);

        $this->postJson('/api/v1/auth/password/reset', ['email' => $user->email, 'token' => 'nope', 'password' => 'new-password', 'password_confirmation' => 'new-password'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_email_is_in_the_users_language_and_links_to_the_website(): void
    {
        $user = User::factory()->create(['email' => 'es@example.com', 'locale' => 'es']);

        // Really sent (array mailer), with an English request: the account's language wins.
        $this->postJson('/api/v1/auth/password/forgot', ['email' => $user->email], ['Accept-Language' => 'en'])->assertOk();

        $sent = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);
        $email = $sent[0]->getOriginalMessage();
        $this->assertSame('Restablece tu contraseña', $email->getSubject());
        $this->assertStringContainsString(url('/reset-password/'), $email->getHtmlBody());
    }

    public function test_website_forgot_and_reset_pages(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'web@example.com', 'password' => 'old-password']);

        $this->get('/login')->assertOk()->assertSee(route('password.request'));
        $this->get('/forgot-password')->assertOk();

        Livewire::test(ForgotPassword::class)->set('email', $user->email)->call('send')
            ->assertHasNoErrors()->assertSee(__('messages.password_reset_sent'));

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))->assertOk()->assertSee($user->email);

        Livewire::withQueryParams(['email' => $user->email])->test(ResetPasswordPage::class, ['token' => $token])
            ->set('password', 'new-password')->set('password_confirmation', 'new-password')
            ->call('save')->assertHasNoErrors()->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }
}
