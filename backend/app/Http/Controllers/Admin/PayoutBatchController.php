<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\PayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayoutBatchController extends Controller
{
    /** POST /admin/payout-batches (MD §4): batch every pending payout request in a currency for manual payment. */
    public function store(Request $request, PayoutService $payouts): JsonResponse
    {
        $data = $request->validate(['currency_code' => ['required', 'string', 'size:3']]);
        $batch = $payouts->createBatch(strtoupper($data['currency_code']), $request->user());
        AuditLog::record('payout_batch.create', $batch, ['currency_code' => $batch->currency_code, 'total_amount' => $batch->total_amount]);

        return response()->json(['data' => [
            'id' => $batch->id, 'currency_code' => $batch->currency_code, 'total_amount' => $batch->total_amount,
            'status' => $batch->status, 'requests' => $batch->requests()->count(),
        ]], 201);
    }
}
