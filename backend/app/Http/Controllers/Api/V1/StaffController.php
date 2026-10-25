<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Damage;
use App\Models\Inspection;
use App\Services\StaffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

// Staff app API (MD §7.10, Phase 10): the branch's pickups and returns, and the handover/return checklists.
class StaffController extends Controller
{
    public function __construct(private StaffService $staff) {}

    /** GET /staff/bookings?date=Y-m-d: pickups due by that day and rentals out, at the staff member's branch. */
    public function index(Request $request): JsonResponse
    {
        $date = $request->validate(['date' => ['sometimes', 'date_format:Y-m-d']])['date'] ?? null;
        $day = $this->staff->day($request->user(), $date ? Carbon::parse($date) : now());

        return response()->json(['data' => [
            'branch' => $request->user()->branch?->name,
            'pickups' => $day['pickups']->map(fn (Booking $b) => $this->summary($b))->values(),
            'returns' => $day['returns']->map(fn (Booking $b) => $this->summary($b))->values(),
        ]]);
    }

    /** GET /staff/bookings/{id}: customer, vehicle, checklists done so far and damages. */
    public function show(Request $request, int $booking): JsonResponse
    {
        return response()->json(['data' => $this->detail($this->find($request, $booking))]);
    }

    /** POST /staff/bookings/{id}/pickup (multipart): checklist + handover (confirmed → picked_up). */
    public function pickup(Request $request, int $booking): JsonResponse
    {
        $b = $this->find($request, $booking);
        $data = $request->validate($this->checklistRules());

        return response()->json(['data' => $this->detail($this->staff->handOver($request->user(), $b, $data, $data['photos']))]);
    }

    /** POST /staff/bookings/{id}/return (multipart): checklist + damages found (reported for the admin) + return. */
    public function return(Request $request, int $booking): JsonResponse
    {
        $b = $this->find($request, $booking);
        $data = $request->validate($this->checklistRules() + [
            'damages' => ['sometimes', 'array', 'max:10'],
            'damages.*.description' => ['required', 'string', 'max:500'],
            'damages.*.cost' => ['required', 'integer', 'min:0', 'max:100000000'], // estimate, minor units
            'damages.*.photos' => ['sometimes', 'array', 'max:6'],
            'damages.*.photos.*' => ['image', 'max:8192'],
        ]);

        return response()->json(['data' => $this->detail($this->staff->receive($request->user(), $b, $data, $data['photos'], $data['damages'] ?? []))]);
    }

    /** Owner decision (Phase 10): at least 4 photos (front, back, left, right), odometer and fuel at both stages. */
    private function checklistRules(): array
    {
        return [
            'photos' => ['required', 'array', 'min:4', 'max:12'],
            'photos.*' => ['image', 'max:8192'],
            'odometer' => ['required', 'integer', 'min:0', 'max:2000000'],
            'fuel_level' => ['required', 'integer', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** Another branch's booking is a 404 for staff. */
    private function find(Request $request, int $id): Booking
    {
        return $this->staff->bookingsAtBranch($request->user())->findOrFail($id);
    }

    private function summary(Booking $b): array
    {
        return [
            'id' => $b->id, 'status' => $b->status->value,
            'start_at' => $b->start_at->toIso8601String(), 'end_at' => $b->end_at->toIso8601String(),
            'customer' => $b->user->name,
            'vehicle' => ['id' => $b->vehicle->id, 'plate_no' => $b->vehicle->plate_no, 'title' => $b->vehicle->vehicleModel->brand->name.' '.$b->vehicle->vehicleModel->name],
        ];
    }

    private function detail(Booking $b): array
    {
        $b->load(['user', 'vehicle.vehicleModel.brand', 'inspections.inspector', 'damages']);
        $url = fn (string $path) => Storage::disk(Inspection::disk())->url($path);

        return $this->summary($b) + [
            'customer_phone' => $b->user->phone,
            'customer_email' => $b->user->email,
            'odometer' => $b->vehicle->odometer,
            'inspections' => $b->inspections->map(fn (Inspection $i) => [
                'stage' => $i->stage, 'odometer' => $i->odometer, 'fuel_level' => $i->fuel_level, 'notes' => $i->notes,
                'photos' => $i->photoUrls(), 'inspected_by' => $i->inspector?->name, 'inspected_at' => $i->created_at->toIso8601String(),
            ])->values(),
            'damages' => $b->damages->map(fn (Damage $d) => [
                'description' => $d->description, 'cost' => $d->cost, 'status' => $d->status,
                'photos' => array_map($url, $d->evidence_json['photos'] ?? []),
            ])->values(),
            'currency_code' => $b->currency_code,
        ];
    }
}
