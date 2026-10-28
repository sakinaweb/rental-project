<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Web installer for buyers (Phase 13): writes .env, migrates, seeds, creates the first admin, then locks itself.
class Installer
{
    public const PHP_VERSION = '8.3.0';

    public const EXTENSIONS = ['pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'fileinfo', 'bcmath', 'intl', 'gd', 'zip'];

    public static function installed(): bool
    {
        return is_file(config('app.installed_marker'));
    }

    /** @return array<string, bool> requirement label → met */
    public static function requirements(): array
    {
        $checks = ['PHP '.self::PHP_VERSION.'+' => version_compare(PHP_VERSION, self::PHP_VERSION, '>=')];
        foreach (self::EXTENSIONS as $ext) {
            $checks["PHP extension: {$ext}"] = extension_loaded($ext);
        }
        $env = app()->environmentFilePath();
        $checks['Writable: .env'] = is_file($env) ? is_writable($env) : is_writable(dirname($env));
        foreach (['storage', 'bootstrap/cache'] as $dir) {
            $checks["Writable: {$dir}"] = is_writable(base_path($dir));
        }

        return $checks;
    }

    /**
     * @param  array{app_name: string, app_url: string, db_host: string, db_port: int|string, db_database: string, db_username: string, db_password: ?string, redis: bool, admin_name: string, admin_email: string, admin_password: string, demo: bool}  $data
     */
    public function install(array $data): void
    {
        $this->connect($data);

        $this->writeEnv([
            'APP_NAME' => $data['app_name'],
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => rtrim($data['app_url'], '/'),
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $data['db_host'],
            'DB_PORT' => (string) $data['db_port'],
            'DB_DATABASE' => $data['db_database'],
            'DB_USERNAME' => $data['db_username'],
            'DB_PASSWORD' => (string) ($data['db_password'] ?? ''),
            // Shared hosting usually has no Redis: fall back to the database queue and file cache (MD §9).
            'CACHE_STORE' => $data['redis'] ? 'redis' : 'file',
            'QUEUE_CONNECTION' => $data['redis'] ? 'redis' : 'database',
        ]);

        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('db:seed', ['--force' => true]);
        if ($data['demo']) {
            Artisan::call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);
        }
        if (! file_exists(public_path('storage'))) {
            Artisan::call('storage:link');
        }

        User::updateOrCreate(['email' => $data['admin_email']], [
            'name' => $data['admin_name'],
            'password' => $data['admin_password'],
            'role_id' => Role::idFor(Role::ADMIN),
        ]);

        file_put_contents(config('app.installed_marker'), now()->toIso8601String().PHP_EOL);
        Artisan::call('optimize:clear');
    }

    /** Points the running app at the buyer's database; throws with the driver's message when it can't connect. */
    private function connect(array $data): void
    {
        $current = config('database.connections.mysql');
        $wanted = array_merge($current, [
            'host' => $data['db_host'], 'port' => $data['db_port'], 'database' => $data['db_database'],
            'username' => $data['db_username'], 'password' => (string) ($data['db_password'] ?? ''),
        ]);
        config(['database.default' => 'mysql']);
        if ($wanted != $current) { // keep a live connection (and its transaction) when nothing changed
            config(['database.connections.mysql' => $wanted]);
            DB::purge('mysql');
        }

        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            config(['database.connections.mysql' => $current]);
            DB::purge('mysql');
            throw new RuntimeException($e->getMessage(), 0, $e);
        }
    }

    /** @param  array<string, string>  $values */
    public function writeEnv(array $values): void
    {
        $path = app()->environmentFilePath();
        if (! is_file($path)) {
            copy(base_path('.env.example'), $path);
        }
        $env = file_get_contents($path);

        if (! preg_match('/^APP_KEY=\S+/m', $env)) {
            $values['APP_KEY'] = 'base64:'.base64_encode(random_bytes(32));
        }

        foreach ($values as $key => $value) {
            $line = $key.'='.(preg_match('/^[\w.\/:@+=-]*$/', $value) ? $value : '"'.addcslashes($value, '"\\').'"');
            $env = preg_match("/^{$key}=.*$/m", $env)
                ? preg_replace("/^{$key}=.*$/m", str_replace(['\\', '$'], ['\\\\', '\\$'], $line), $env)
                : rtrim($env).PHP_EOL.$line.PHP_EOL;
        }

        file_put_contents($path, $env);
    }
}
