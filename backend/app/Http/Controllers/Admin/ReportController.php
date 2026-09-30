<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RevenueReport;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    /** GET /admin/reports/revenue (MD §4). Paginated rows; money in minor units + currency_code. */
    public function revenue(Request $request, RevenueReport $report): LengthAwarePaginator
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'group_by' => ['nullable', 'in:day,month'],
            'country_id' => ['nullable', 'integer'],
            'branch_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        $rows = $report->rows(
            Carbon::parse($data['from'])->startOfDay(), Carbon::parse($data['to'])->endOfDay(),
            $data['group_by'] ?? 'day', $data['country_id'] ?? null, $data['branch_id'] ?? null,
        );
        $perPage = (int) ($data['per_page'] ?? 31);
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page, ['path' => $request->url(), 'query' => $request->query()]);
    }
}
