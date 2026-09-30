<?php

namespace App\Exports;

use App\Services\RevenueReport;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Laravel Excel export of the revenue report (MD §1 "CSV/report export"). Amounts in minor units, like the API.
 *
 * @phpstan-import-type RevenueRow from RevenueReport
 */
class RevenueExport implements FromCollection, WithHeadings
{
    /** @param  Collection<int, RevenueRow>  $rows */
    public function __construct(private Collection $rows) {}

    public function collection(): Collection
    {
        return $this->rows;
    }

    /** @return list<string> */
    public function headings(): array
    {
        return RevenueReport::COLUMNS;
    }
}
