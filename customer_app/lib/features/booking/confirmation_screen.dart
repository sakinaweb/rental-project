import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/ui.dart';
import 'booking_providers.dart';
import 'booking_summary.dart';

/// Shows only the stored booking status (wallet payment or gateway webhook set it) and re-checks every
/// 3 seconds while payment is pending. Never assumes success.
class ConfirmationScreen extends ConsumerStatefulWidget {
  const ConfirmationScreen(this.id, {super.key, this.pollEvery = const Duration(seconds: 3)});
  final int id;
  final Duration pollEvery;

  @override
  ConsumerState<ConfirmationScreen> createState() => _ConfirmationScreenState();
}

class _ConfirmationScreenState extends ConsumerState<ConfirmationScreen> {
  Timer? _poll;

  @override
  void initState() {
    super.initState();
    _poll = Timer.periodic(widget.pollEvery, (_) {
      final s = ref.read(bookingProvider(widget.id));
      // Restarting an unfinished request would discard it, so a server slower than the poll never answers.
      if (!s.isLoading && (s.value?.waitingForPayment ?? true)) ref.invalidate(bookingProvider(widget.id));
    });
  }

  @override
  void dispose() {
    _poll?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = context.t;

    // Opened with go(), so there is nothing to pop: Back leads to the booking instead of closing the app.
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) => didPop ? null : context.go('/bookings/${widget.id}'),
      child: Scaffold(
        appBar: AppBar(title: Text(t.confirmationTitle), automaticallyImplyLeading: false),
        body: AsyncView(
          ref.watch(bookingProvider(widget.id)),
          onRetry: () => ref.invalidate(bookingProvider(widget.id)),
          data: (b) {
            final (text, color) = switch (b.status) {
              'cancelled' || 'no_show' => (t.confirmationFailed, Colors.red),
              'draft' || 'pending_payment' => (t.confirmationWaiting, Colors.orange),
              _ => (t.confirmationConfirmed, Colors.green),
            };

            return ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Text(
                  text,
                  key: const Key('status-text'),
                  style: TextStyle(color: color, fontWeight: FontWeight.bold),
                ),
                if (b.waitingForPayment) const Padding(padding: EdgeInsets.symmetric(vertical: 8), child: LinearProgressIndicator()),
                const SizedBox(height: 12),
                BookingSummary(b),
                const SizedBox(height: 16),
                FilledButton(onPressed: () => context.go('/bookings/${b.id}'), child: Text(t.confirmationViewBooking)),
                if (b.waitingForPayment) TextButton(onPressed: () => context.go('/checkout/${b.id}'), child: Text(t.checkoutTitle)),
              ],
            );
          },
        ),
      ),
    );
  }
}
