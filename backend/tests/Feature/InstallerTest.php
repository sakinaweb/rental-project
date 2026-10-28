<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    use RefreshDatabase;

    // A refused connection drops the test transaction, so RefreshDatabase migrates again: seed then, as every other test expects.
    protected $seed = true;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/installer-test-'.uniqid();
        mkdir($this->dir);
        config(['app.installed_marker' => $this->dir.'/installed']);
        $this->app->useEnvironmentPath($this->dir); // the installer writes $dir/.env, never the real one
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/{,.}[!.]*', GLOB_BRACE));
        rmdir($this->dir);
        parent::tearDown();
    }

    private function form(array $overrides = []): array
    {
        $db = config('database.connections.mysql');

        return $overrides + [
            'app_name' => 'Ride "Easy" $hop', 'app_url' => 'https://rent.example.com/',
            'db_host' => $db['host'], 'db_port' => $db['port'], 'db_database' => $db['database'],
            'db_username' => $db['username'], 'db_password' => $db['password'],
            'admin_name' => 'Owner', 'admin_email' => 'owner@example.com',
            'admin_password' => 'secret-pass', 'admin_password_confirmation' => 'secret-pass',
        ];
    }

    public function test_site_and_admin_go_to_the_installer_until_installed(): void
    {
        $this->get('/')->assertRedirect('/install');
        $this->get('/admin')->assertRedirect('/install');
        $this->get('/install')->assertOk()->assertSee('Server requirements')->assertSee('PHP extension: pdo_mysql');
    }

    public function test_bad_database_details_are_reported_and_nothing_is_marked_installed(): void
    {
        $this->post('/install', $this->form(['db_password' => 'wrong']))
            ->assertOk()->assertSee('Installation failed');
        $this->assertFileDoesNotExist($this->dir.'/installed');

        $this->post('/install', $this->form(['admin_password_confirmation' => 'other']))
            ->assertOk()->assertSee('confirmation');
    }

    public function test_installs_writes_env_creates_the_admin_and_locks_itself(): void
    {
        $this->post('/install', $this->form())->assertRedirect('/admin');

        $admin = User::where('email', 'owner@example.com')->firstOrFail();
        $this->assertTrue($admin->hasRole(Role::ADMIN));
        $this->assertTrue(Hash::check('secret-pass', $admin->password));

        $env = file_get_contents($this->dir.'/.env');
        $this->assertStringContainsString('APP_NAME="Ride \"Easy\" $hop"', $env);
        $this->assertStringContainsString('APP_URL=https://rent.example.com'.PHP_EOL, $env);
        $this->assertStringContainsString('APP_DEBUG=false', $env);
        $this->assertStringContainsString('CACHE_STORE=file', $env);
        $this->assertStringContainsString('QUEUE_CONNECTION=database', $env);
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:\S{44}$/m', $env);

        $this->assertFileExists($this->dir.'/installed');
        $this->get('/install')->assertNotFound();
        $this->post('/install', $this->form())->assertNotFound();
    }
}
