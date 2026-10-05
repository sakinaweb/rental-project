import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:open_filex/open_filex.dart';
import 'package:path_provider/path_provider.dart';

import '../../core/api.dart';
import '../../core/ui.dart';
import 'booking_providers.dart';
import 'booking_summary.dart';

/// Booking detail: cancel (refund to wallet), extend, pay the balance, invoice PDF — all through the API.
class BookingDetailScreen extends ConsumerStatefulWidget {
  const BookingDetailScreen(this.id, {super.key});
  final int id;

  @override
  ConsumerState<BookingDetailScreen> createState() => _BookingDetailScreenState();
}

class _BookingDetailScreenState extends ConsumerState<BookingDetailScreen> {
  bool busy = false;
  String? error;

  Future<void> _run(Future<void> Function(Api api) action, {String? notice}) async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await action(ref.read(apiProvider));
      ref.invalidate(bookingProvider(widget.id));
      ref.invalidate(bookingsProvider);
      if (notice != null && mounted) snack(context, notice);
    } on ApiException catch (e) {
      if (mounted) setState(() => error = e.errors.values.firstOrNull?.first ?? errorText(context, e));
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> _cancel() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (c) => AlertDialog(
        content: Text(context.t.bookingCancelConfirm),
        actions: [
          TextButton(onPressed: () => Navigator.pop(c, false), child: Text(context.t.commonBack)),
          FilledButton(key: const Key('confirm-cancel'), onPressed: () => Navigator.pop(c, true), child: Text(context.t.bookingCancel)),
        ],
      ),
    );
    if (ok == true && mounted) await _run((api) => api.cancel(widget.id), notice: context.t.bookingCancelledNotice);
  }

  Future<void> _extend(Booking b) async {
    final end = await pickDateTime(context, b.end.add(const Duration(days: 1)));
    if (end != null && mounted) await _run((api) => api.extend(widget.id, end), notice: context.t.bookingExtendedNotice);
  }

  Future<void> _invoice() => _run((api) async {
    final bytes = await api.invoice(widget.id);
    final file = File('${(await getApplicationDocumentsDirectory()).path}/invoice-booking-${widget.id}.pdf');
    await file.writeAsBytes(bytes);
    if (mounted) snack(context, context.t.invoiceSaved(file.path));
    await OpenFilex.open(file.path);
  });

  @override
  Widget build(BuildContext context) {
    final t = context.t;

    return Scaffold(
      appBar: AppBar(title: Text(t.bookingTitle('${widget.id}'))),
      body: AsyncView(
        ref.watch(bookingProvider(widget.id)),
        onRetry: () => ref.invalidate(bookingProvider(widget.id)),
        data: (b) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            BookingSummary(b),
            if (b.depositStatus != null) Text('${t.bookingDepositStatus}: ${b.depositStatus == 'held' ? t.depositHeld : t.depositReleased}'),
            if (error != null)
              Padding(
                padding: const EdgeInsets.only(top: 12),
                child: Text(error!, style: const TextStyle(color: Colors.red)),
              ),
            const Divider(height: 32),
            if (b.canPay) ...[
              Line(t.bookingAmountDue, money(ref, b.due, b.currency), bold: true),
              FilledButton(onPressed: busy ? null : () => context.go('/checkout/${b.id}'), child: Text(t.bookingPayBalance)),
              const SizedBox(height: 8),
            ],
            if (b.canExtend) OutlinedButton(key: const Key('extend'), onPressed: busy ? null : () => _extend(b), child: Text(t.bookingExtend)),
            if (b.canCancel)
              OutlinedButton(
                key: const Key('cancel'),
                onPressed: busy ? null : _cancel,
                style: OutlinedButton.styleFrom(foregroundColor: Colors.red),
                child: Text(t.bookingCancel),
              ),
            OutlinedButton(key: const Key('invoice'), onPressed: busy ? null : _invoice, child: Text(t.bookingInvoice)),
          ],
        ),
      ),
    );
  }
}
