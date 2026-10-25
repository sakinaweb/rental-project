<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Damage;
use App\Models\Inspection;
use App\Models\User;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Staff handover and return (MD §7.10). Owner decisions (Phase 10): staff see their own branch only; both checklists
 * need photos, odometer and fuel; damages found at return are reported for the admin to approve or waive.
 */
class StaffService
{
    public function __construct(private BookingService $bookings) {}

    /** @return Builder<Booking> bookings of vehicles at the staff member's branch */
    public function bookingsAtBranch(User $staff): Builder
    {
        if (! $staff->branch_id) {
            throw new DomainException(__('messages.staff_no_branch'));
        }

        return Booking::whereHas('vehicle', fn (Builder $q) => $q->where('branch_id', $staff->branch_id));
    }

    /**
     * Pickups: confirmed bookings starting by the end of the day (late customers included).
     * Returns: every rental that is out, earliest due first (an early return is still a return).
     *
     * @return array{pickups: Collection<int, Booking>, returns: Collection<int, Booking>}
     */
    public function day(User $staff, CarbonInterface $day): array
    {
        $with = ['user', 'vehicle.vehicleModel.brand'];

        return [
            'pickups' => $this->bookingsAtBranch($staff)->with($with)->where('status', BookingStatus::Confirmed)
                ->where('start_at', '<=', $day->copy()->endOfDay())->orderBy('start_at')->get(),
            'returns' => $this->bookingsAtBranch($staff)->with($with)->where('status', BookingStatus::PickedUp)->orderBy('end_at')->get(),
        ];
    }

    /**
     * Handover: the checklist and confirmed → picked_up in one transaction.
     *
     * @param  array{odometer: int, fuel_level: int, notes?: ?string}  $data
     * @param  list<UploadedFile>  $photos
     */
    public function handOver(User $staff, Booking $booking, array $data, array $photos): Booking
    {
        return $this->withPhotos($photos, "inspections/{$booking->id}", function (array $paths) use ($staff, $booking, $data) {
            $booking = $this->bookings->advance($booking, BookingStatus::PickedUp);
            $this->inspect($staff, $booking, Inspection::PICKUP, $data, $paths);

            return $booking;
        });
    }

    /**
     * Return: the checklist, any damages (reported, for the admin), picked_up → returned in one transaction.
     * The odometer may not go backwards from the handover reading.
     *
     * @param  array{odometer: int, fuel_level: int, notes?: ?string}  $data
     * @param  list<UploadedFile>  $photos
     * @param  list<array{description: string, cost: int, photos?: list<UploadedFile>}>  $damages
     */
    public function receive(User $staff, Booking $booking, array $data, array $photos, array $damages = []): Booking
    {
        $pickup = $booking->inspections()->where('stage', Inspection::PICKUP)->latest('id')->value('odometer');
        if ($pickup !== null && $data['odometer'] < $pickup) {
            throw new DomainException(__('messages.inspection_odometer_lower', ['km' => $pickup]));
        }

        $damagePhotos = array_merge(...array_map(fn (array $d) => $d['photos'] ?? [], $damages) ?: [[]]);

        return $this->withPhotos([...$photos, ...$damagePhotos], "inspections/{$booking->id}", function (array $paths) use ($staff, $booking, $data, $photos, $damages) {
            $booking = $this->bookings->advance($booking, BookingStatus::Returned);
            $this->inspect($staff, $booking, Inspection::RETURN, $data, array_slice($paths, 0, count($photos)));

            $offset = count($photos);
            foreach ($damages as $damage) {
                $n = count($damage['photos'] ?? []);
                $booking->damages()->create([
                    'description' => $damage['description'], 'cost' => $damage['cost'], 'status' => Damage::REPORTED,
                    'evidence_json' => ['photos' => array_slice($paths, $offset, $n), 'reported_by' => $staff->id],
                ]);
                $offset += $n;
            }

            return $booking;
        });
    }

    /** @param  list<string>  $paths */
    private function inspect(User $staff, Booking $booking, string $stage, array $data, array $paths): void
    {
        $booking->inspections()->create([
            'stage' => $stage, 'photos_json' => $paths, 'odometer' => $data['odometer'], 'fuel_level' => $data['fuel_level'],
            'notes' => $data['notes'] ?? null, 'inspected_by' => $staff->id,
        ]);
        $booking->vehicle()->update(['odometer' => $data['odometer']]);
    }

    /**
     * Stores the photos, then runs $work in a transaction; if anything fails the stored files are deleted again.
     *
     * @param  list<UploadedFile>  $photos
     * @param  callable(list<string>): Booking  $work
     */
    private function withPhotos(array $photos, string $dir, callable $work): Booking
    {
        $disk = Storage::disk(Inspection::disk());
        $paths = array_map(fn (UploadedFile $photo) => $photo->store($dir, Inspection::disk()), $photos);

        try {
            return DB::transaction(fn () => $work($paths));
        } catch (Throwable $e) {
            $disk->delete($paths);
            throw $e;
        }
    }
}
