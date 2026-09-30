<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Feature flags and operational settings (MD §9: editable from admin, not just .env).
 * Keys are the ones the services already read; changes are audited (D6).
 *
 * @property-read Schema $form
 */
class Settings extends Page
{
    /** setting key => form field name (dots aren't allowed in Livewire state paths) */
    public const FIELDS = [
        'features.partner' => 'features_partner',
        'features.investor' => 'features_investor',
        'availability.buffer_minutes' => 'availability_buffer_minutes',
        'booking.draft_ttl_minutes' => 'booking_draft_ttl_minutes',
        'booking.damage_hold_hours' => 'booking_damage_hold_hours',
        'payment.timeout_minutes' => 'payment_timeout_minutes',
    ];

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 100;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(collect(self::FIELDS)->mapWithKeys(fn (string $field, string $key) => [$field => Setting::valueOf($key)])->all());
    }

    public function form(Schema $schema): Schema
    {
        $minutes = fn (string $name, string $label, string $help) => TextInput::make($name)->label($label)->helperText($help)
            ->required()->integer()->minValue(0)->maxValue(100000);

        return $schema->statePath('data')->components([
            Section::make('Feature flags')
                ->description('A feature is on for a country only if it is enabled here and in that country pack.')
                ->schema([
                    Toggle::make('features_partner')->label('Partner module'),
                    Toggle::make('features_investor')->label('Investor module'),
                ]),
            Section::make('Bookings and payments')->columns(2)->schema([
                $minutes('availability_buffer_minutes', 'Turnaround buffer (minutes)', 'Kept free before and after every booking.'),
                $minutes('booking_draft_ttl_minutes', 'Draft expiry (minutes)', 'Unpaid drafts stop holding the vehicle after this.'),
                $minutes('payment_timeout_minutes', 'Payment timeout (minutes)', 'Unpaid bookings awaiting payment are cancelled after this.'),
                $minutes('booking_damage_hold_hours', 'Damage hold window (hours)', 'Completed bookings settle after this.'),
            ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([Actions::make([Action::make('save')->label('Save')->submit('save')])]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $changes = [];

        foreach (self::FIELDS as $key => $field) {
            $value = str_starts_with($key, 'features.') ? (bool) $data[$field] : (int) $data[$field];
            $old = Setting::valueOf($key);
            if ($old !== $value) {
                Setting::updateOrCreate(['key' => $key], ['value_json' => $value]);
                $changes[$key] = ['from' => $old, 'to' => $value];
            }
        }

        if ($changes) {
            AuditLog::record('settings.update', null, $changes);
        }
        Notification::make()->success()->title('Settings saved')->send();
    }
}
