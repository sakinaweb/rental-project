<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Coupon;
use App\Models\PricingRule;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\BookingService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

// MD §7.12 / §8 #1: real concurrent booking attempts, not a mock. The setup is committed so separate processes, each
// with its own MySQL connection, can race through BookingService at the same instant. Committing makes Laravel
// rebuild the test database for the next test, so nothing leaks.
class BookingRaceTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private const RACERS = 8;

    protected function setUp(): void
    {
        parent::setUp();
        if (! function_exists('pcntl_fork') || ! function_exists('posix_kill')) {
            $this->markTestSkipped('needs the pcntl and posix extensions (the Docker image has them)');
        }
        Carbon::setTestNow('2026-10-01 00:00:00');
        PricingRule::create(['scope_type' => 'global', 'unit' => 'day', 'base_rate' => 1000]);
    }

    /**
     * Runs $attempt in RACERS forked processes released together; returns each one's outcome:
     * 'ok', the DomainException message, or 'error: …' for anything else (a deadlock, a lost lock wait…).
     *
     * @param  callable(int): void  $attempt
     * @return list<string>
     */
    private function race(callable $attempt): array
    {
        DB::commit(); // publish the setup to the other connections
        DB::disconnect(); // children must not share the parent's socket

        $dir = sys_get_temp_dir().'/race-'.getmypid().'-'.uniqid();
        mkdir($dir);
        $start = microtime(true) + 1.0;
        $pids = [];
        for ($i = 0; $i < self::RACERS; $i++) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                DB::reconnect();
                time_sleep_until($start);
                try {
                    $attempt($i);
                    $result = 'ok';
                } catch (DomainException $e) {
                    $result = $e->getMessage();
                } catch (Throwable $e) {
                    $result = 'error: '.$e->getMessage();
                }
                file_put_contents("$dir/$i", $result);
                DB::disconnect();
                posix_kill(getmypid(), SIGKILL); // skip PHPUnit's shutdown handlers in the child
            }
            $pids[] = $pid;
        }
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }
        DB::reconnect();

        $results = array_map(fn ($i) => (string) @file_get_contents("$dir/$i"), range(0, self::RACERS - 1));
        array_map('unlink', glob("$dir/*"));
        rmdir($dir);

        return $results;
    }

    public function test_concurrent_bookings_of_one_vehicle_give_exactly_one_booking(): void
    {
        $vehicle = Vehicle::factory()->create();
        $users = User::factory()->count(self::RACERS)->create();

        $results = $this->race(fn (int $i) => app(BookingService::class)->create(
            $users[$i], $vehicle, Carbon::parse('2026-10-05 10:00'), Carbon::parse('2026-10-08 10:00'),
        ));

        $this->assertSame(1, count(array_keys($results, 'ok')), json_encode($results));
        $this->assertSame(self::RACERS - 1, count(array_keys($results, __('messages.vehicle_unavailable'))), json_encode($results));
        $this->assertSame(1, Booking::where('vehicle_id', $vehicle->id)->count());
    }

    public function test_overlapping_periods_including_the_buffer_never_both_win(): void
    {
        $vehicle = Vehicle::factory()->create();
        $users = User::factory()->count(self::RACERS)->create();

        // Each racer asks for a different window, each overlapping the others or within the 60-minute buffer.
        $results = $this->race(fn (int $i) => app(BookingService::class)->create(
            $users[$i], $vehicle, Carbon::parse('2026-10-05 10:00')->addMinutes(30 * $i), Carbon::parse('2026-10-05 14:00')->addMinutes(30 * $i),
        ));

        $won = Booking::where('vehicle_id', $vehicle->id)->orderBy('start_at')->get();
        $this->assertNotEmpty($won, json_encode($results));
        $this->assertEmpty(array_filter($results, fn ($r) => str_starts_with($r, 'error')), json_encode($results));
        foreach ($won->sliding(2) as [$a, $b]) { // whatever won is apart by at least the buffer
            $this->assertGreaterThanOrEqual(60, $a->end_at->diffInMinutes($b->start_at, false));
        }
    }

    public function test_concurrent_bookings_of_different_vehicles_all_succeed(): void
    {
        $vehicles = Vehicle::factory()->count(self::RACERS)->create();
        $users = User::factory()->count(self::RACERS)->create();

        $results = $this->race(fn (int $i) => app(BookingService::class)->create(
            $users[$i], $vehicles[$i], Carbon::parse('2026-10-05 10:00'), Carbon::parse('2026-10-08 10:00'),
        ));

        $this->assertSame(array_fill(0, self::RACERS, 'ok'), $results);
    }

    public function test_concurrent_bookings_cannot_take_a_coupon_past_its_usage_limit(): void
    {
        $vehicles = Vehicle::factory()->count(self::RACERS)->create(); // different vehicles: only the coupon is shared
        $users = User::factory()->count(self::RACERS)->create();
        $coupon = Coupon::create(['code' => 'ONCE', 'type' => Coupon::FIXED, 'value' => 100, 'usage_limit' => 1]);

        $results = $this->race(fn (int $i) => app(BookingService::class)->create(
            $users[$i], $vehicles[$i], Carbon::parse('2026-10-05 10:00'), Carbon::parse('2026-10-08 10:00'), [], $coupon,
        ));

        $this->assertSame(1, Booking::where('coupon_id', $coupon->id)->count(), json_encode($results));
        $this->assertSame(self::RACERS - 1, count(array_keys($results, __('messages.coupon_used_up'))), json_encode($results));
    }
}
