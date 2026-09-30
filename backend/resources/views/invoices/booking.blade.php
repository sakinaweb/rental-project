@php($m = fn (int $minor) => \App\Support\Money::format($minor, $booking->currency_code))
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Invoice — booking {{ $booking->id }}</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
    h1 { font-size: 20px; margin: 0 0 4px; }
    .muted { color: #666; }
    table { width: 100%; border-collapse: collapse; margin-top: 16px; }
    th, td { padding: 6px 8px; border-bottom: 1px solid #ddd; text-align: left; }
    td.amount, th.amount { text-align: right; }
    tr.total td { font-weight: bold; border-top: 2px solid #222; }
</style>
</head>
<body>
    <h1>{{ config('app.name') }} — Invoice</h1>
    <div class="muted">Booking #{{ $booking->id }} · Issued {{ now()->toDateString() }} · Status: {{ $booking->status->value }}</div>

    <table>
        <tr><th>Customer</th><td>{{ $booking->user->name }} ({{ $booking->user->email }})</td></tr>
        <tr><th>Vehicle</th><td>{{ $booking->vehicle->vehicleModel->brand->name }} {{ $booking->vehicle->vehicleModel->name }} · {{ $booking->vehicle->plate_no }}</td></tr>
        <tr><th>Branch</th><td>{{ $booking->vehicle->branch->name }}, {{ $booking->vehicle->branch->location->city->name }}</td></tr>
        <tr><th>Period</th><td>{{ $booking->start_at->toDayDateTimeString() }} → {{ $booking->end_at->toDayDateTimeString() }} (UTC)</td></tr>
    </table>

    <table>
        <tr><th>Item</th><th class="amount">Qty</th><th class="amount">Unit price</th></tr>
        @foreach ($booking->addons as $line)
            <tr><td>{{ $line->addon->name }}</td><td class="amount">{{ $line->qty }}</td><td class="amount">{{ $m($line->price) }}</td></tr>
        @endforeach
        <tr><td>Subtotal (rental + add-ons)</td><td></td><td class="amount">{{ $m($booking->subtotal) }}</td></tr>
        <tr><td>Discounts{{ $booking->coupon ? ' (coupon '.$booking->coupon->code.')' : '' }}</td><td></td><td class="amount">−{{ $m($booking->discount_total) }}</td></tr>
        <tr><td>Tax</td><td></td><td class="amount">{{ $m($booking->tax_total) }}</td></tr>
        <tr class="total"><td>Total</td><td></td><td class="amount">{{ $m($booking->grand_total) }}</td></tr>
        <tr><td>Refundable deposit</td><td></td><td class="amount">{{ $m($booking->deposit_amount) }}</td></tr>
        <tr><td>Paid (net of refunds)</td><td></td><td class="amount">{{ $m($booking->amountPaid()) }}</td></tr>
    </table>
</body>
</html>
