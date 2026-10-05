<?php

namespace App\Livewire\Partner;

use App\Models\Branch;
use App\Models\Partner;
use App\Models\Vehicle;
use App\Models\VehicleModel;
use App\Services\PartnerService;
use App\Services\VehicleSearch;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

// Partner vehicles (Phase 9): add a vehicle (listed after admin approval) and block dates. Same rules as the API.
#[Layout('layouts.site')]
class Vehicles extends Component
{
    public string $modelId = '';

    public string $branchId = '';

    public string $plateNo = '';

    /** @var array<int, string> per vehicle id */
    public array $from = [];

    /** @var array<int, string> */
    public array $to = [];

    /** @var array<int, string> */
    public array $reason = [];

    public ?string $notice = null;

    public ?string $actionError = null;

    private function partner(): Partner
    {
        return Partner::where('user_id', auth()->id())->firstOrFail();
    }

    private function branchIds(): array
    {
        return Branch::whereHas('location.city', fn ($q) => $q->where('country_id', auth()->user()->country_id))->pluck('id')->all();
    }

    public function addVehicle(PartnerService $partners): void
    {
        $this->reset('notice', 'actionError');
        $data = $this->validate([
            'modelId' => ['required', 'integer', Rule::exists('vehicle_models', 'id')->whereNull('deleted_at')],
            'branchId' => ['required', 'integer', Rule::in($this->branchIds())],
            'plateNo' => ['required', 'string', 'max:20', Rule::unique('vehicles', 'plate_no')],
        ]);

        try {
            $partners->addVehicle($this->partner(), ['model_id' => (int) $data['modelId'], 'branch_id' => (int) $data['branchId'], 'plate_no' => $data['plateNo']]);
            $this->notice = __('site.partner.vehicle_added');
            $this->reset('modelId', 'branchId', 'plateNo');
        } catch (DomainException $e) {
            $this->actionError = $e->getMessage();
        }
    }

    public function addBlockout(PartnerService $partners, int $vehicleId): void
    {
        $this->reset('notice', 'actionError');
        $this->validate([
            "from.$vehicleId" => ['required', 'date', 'after:now'],
            "to.$vehicleId" => ['required', 'date', "after:from.$vehicleId"],
            "reason.$vehicleId" => ['nullable', 'string', 'max:200'],
        ]);

        try {
            $partners->addBlockout($this->partner(), $this->ownVehicle($vehicleId), VehicleSearch::toAppTime($this->from[$vehicleId]),
                VehicleSearch::toAppTime($this->to[$vehicleId]), ($this->reason[$vehicleId] ?? '') ?: null);
            $this->notice = __('site.partner.blockout_added');
            unset($this->from[$vehicleId], $this->to[$vehicleId], $this->reason[$vehicleId]);
        } catch (DomainException $e) {
            $this->actionError = $e->getMessage();
        }
    }

    public function removeBlockout(int $vehicleId, int $blockoutId): void
    {
        $this->ownVehicle($vehicleId)->blockouts()->findOrFail($blockoutId)->delete();
    }

    private function ownVehicle(int $id): Vehicle
    {
        return Vehicle::where('owner_type', 'partner')->where('owner_id', $this->partner()->id)->findOrFail($id);
    }

    public function render(): View
    {
        $partner = $this->partner();

        return view('livewire.partner.vehicles', [
            'partner' => $partner,
            'vehicles' => $partner->partnerVehicles()->with(['vehicle.vehicleModel.brand', 'vehicle.branch', 'vehicle.blockouts' => fn ($q) => $q->where('end_at', '>', now())->orderBy('start_at')])->latest('id')->get(),
            'models' => VehicleModel::with('brand')->orderBy('name')->get(),
            'branches' => Branch::whereKey($this->branchIds())->orderBy('name')->get(),
        ])->title(__('site.partner.vehicles'));
    }
}
