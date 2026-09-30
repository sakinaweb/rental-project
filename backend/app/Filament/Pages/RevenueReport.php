<?php

namespace App\Filament\Pages;

use App\Exports\RevenueExport;
use App\Models\Branch;
use App\Models\Country;
use App\Services\RevenueReport as Report;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Earned revenue report (decision D3) with CSV export (Laravel Excel).
 *
 * @phpstan-import-type RevenueRow from Report
 *
 * @property-read Schema $form
 */
class RevenueReport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Revenue report';

    protected static ?int $navigationSort = 90;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString(), 'group_by' => 'day']);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->live()->columns(5)->components([
            DatePicker::make('from')->required(),
            DatePicker::make('to')->required()->afterOrEqual('from'),
            Select::make('group_by')->options(['day' => 'Day', 'month' => 'Month'])->required()->selectablePlaceholder(false),
            Select::make('country_id')->label('Country')->options(fn () => Country::pluck('name', 'id')),
            Select::make('branch_id')->label('Branch')->options(fn () => Branch::pluck('name', 'id'))->searchable(),
        ]);
    }

    /** @return Collection<int, RevenueRow> */
    public function rows(): Collection
    {
        $d = $this->data;
        if (empty($d['from']) || empty($d['to']) || $d['to'] < $d['from']) {
            return collect();
        }

        return app(Report::class)->rows(
            Carbon::parse($d['from'])->startOfDay(), Carbon::parse($d['to'])->endOfDay(),
            $d['group_by'] ?? 'day', ($d['country_id'] ?? null) ?: null, ($d['branch_id'] ?? null) ?: null,
        );
    }

    public function content(Schema $schema): Schema
    {
        $rows = $this->rows();
        $cell = fn (array $r, string $k) => Money::format($r[$k], $r['currency_code']);
        $table = $rows->isEmpty()
            ? '<p>No completed bookings in this period.</p>'
            : '<table class="w-full text-sm"><thead><tr>'.collect(['Period', 'Currency', 'Bookings', 'Subtotal', 'Discounts', 'Tax', 'Total', 'Net revenue'])
                ->map(fn ($h) => "<th class=\"text-start p-2\">{$h}</th>")->join('').'</tr></thead><tbody>'
                .$rows->map(fn ($r) => '<tr class="border-t"><td class="p-2">'.e($r['period']).'</td><td class="p-2">'.e($r['currency_code']).'</td><td class="p-2">'.$r['bookings'].'</td>'
                    .collect(['subtotal', 'discount_total', 'tax_total', 'grand_total', 'net_revenue'])->map(fn ($k) => '<td class="p-2">'.e($cell($r, $k)).'</td>')->join('').'</tr>')->join('')
                .'</tbody></table>';

        return $schema->components([
            EmbeddedSchema::make('form'),
            Section::make('Earned revenue')->description('Completed and settled bookings by completion date. Net revenue excludes tax; deposits are not revenue.')
                ->schema([Text::make(new HtmlString($table))]),
        ]);
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')->label('Export CSV')->icon('heroicon-o-arrow-down-tray')
                ->action(fn (): BinaryFileResponse => Excel::download(new RevenueExport($this->rows()), 'revenue-'.now()->format('Ymd-His').'.csv', ExcelFormat::CSV)),
        ];
    }
}
