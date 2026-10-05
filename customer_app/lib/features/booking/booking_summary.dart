import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api.dart';
import '../../core/ui.dart';

String statusLabel(BuildContext context, String status) => switch (status) {
  'draft' => context.t.statusDraft,
  'pending_payment' => context.t.statusPendingPayment,
  'confirmed' => context.t.statusConfirmed,
  'picked_up' => context.t.statusPickedUp,
  'returned' => context.t.statusReturned,
  'completed' => context.t.statusCompleted,
  'settled' => context.t.statusSettled,
  'cancelled' => context.t.statusCancelled,
  'no_show' => context.t.statusNoShow,
  _ => status,
};

/// Vehicle, period, add-ons and money of a booking (checkout, confirmation, detail).
class BookingSummary extends ConsumerWidget {
  const BookingSummary(this.b, {super.key});
  final Booking b;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final t = context.t;
    String m(int minor) => money(ref, minor, b.currency);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(child: Text(b.vehicle?.title ?? '', style: Theme.of(context).textTheme.titleMedium)),
            Chip(label: Text(statusLabel(context, b.status))),
          ],
        ),
        Text('#${b.id} · ${b.vehicle?.branch ?? ''}'),
        Text('${dateTime(ref, b.start)} → ${dateTime(ref, b.end)}'),
        for (final a in b.addons) Text('• ${a['name']} × ${a['qty']}'),
        const Divider(),
        Line(t.vehicleSubtotal, m(b.subtotal)),
        if (b.discount > 0) Line(t.vehicleDiscounts, '−${m(b.discount)}'),
        Line(t.vehicleTax, m(b.tax)),
        Line(t.vehicleTotal, m(b.total), bold: true),
        Line(t.vehicleDeposit, m(b.deposit)),
        Line(t.bookingAmountPaid, m(b.paid)),
      ],
    );
  }
}
