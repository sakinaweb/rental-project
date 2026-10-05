import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api.dart';
import '../../core/ui.dart';
import 'booking_providers.dart';
import 'booking_summary.dart';
import 'card_checkout.dart';

/// Checkout (POST /payments/intent): wallet pays at once; a configured gateway opens its checkout and the
/// webhook confirms. Either way the customer lands on the confirmation screen, which shows the real status.
class CheckoutScreen extends ConsumerStatefulWidget {
  const CheckoutScreen(this.id, {super.key});
  final int id;

  @override
  ConsumerState<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends ConsumerState<CheckoutScreen> {
  bool busy = false;
  String? error;
  Timer? _expiry;
  late final AppLifecycleListener _lifecycle;

  @override
  void initState() {
    super.initState();
    // Stay current: the wallet or booking can change elsewhere (top-up webhook, admin, another device).
    _lifecycle = AppLifecycleListener(onResume: _refresh);
    // Redraw when the hold runs out, so the screen never offers to pay an expired draft.
    ref.listenManual(bookingProvider(widget.id), fireImmediately: true, (_, next) {
      final at = next.value?.expiresAt;
      _expiry?.cancel();
      if (at != null && at.isAfter(DateTime.now())) {
        _expiry = Timer(at.difference(DateTime.now()), () => mounted ? setState(() {}) : null);
      }
    });
  }

  @override
  void dispose() {
    _expiry?.cancel();
    _lifecycle.dispose();
    super.dispose();
  }

  Future<void> _refresh() async {
    ref.invalidate(walletProvider);
    ref.invalidate(bookingProvider(widget.id));
  }

  // Checkout is opened with go(), so there is nothing to pop: Back leads to the booking instead of closing the app.
  void _back() => context.go('/bookings/${widget.id}');

  Future<void> _pay(String gateway) async {
    final title = context.t.checkoutTitle;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final intent = await ref.read(apiProvider).payBooking(widget.id, gateway);
      if (intent.client != null) {
        final finished = await ref.read(cardCheckoutProvider)(gateway, intent.client!, title: title);
        if (!finished) {
          if (mounted) setState(() => error = context.t.paymentNotCompleted);
          return;
        }
      }
      ref.invalidate(bookingProvider(widget.id)); // show the status after payment, not the cached one
      if (mounted) context.go('/confirmation/${widget.id}');
    } on ApiException catch (e) {
      if (mounted) setState(() => error = errorText(context, e));
    } finally {
      if (mounted) setState(() => busy = false);
      ref.invalidate(walletProvider);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = context.t;
    final booking = ref.watch(bookingProvider(widget.id));

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) => didPop ? null : _back(),
      child: Scaffold(
        appBar: AppBar(
          title: Text(t.checkoutTitle),
          leading: BackButton(onPressed: _back),
        ),
        body: AsyncView(
          booking,
          onRetry: () => ref.invalidate(bookingProvider(widget.id)),
          data: (b) {
            final balance = ref.watch(walletProvider).value?.balances[b.currency] ?? 0;
            final gateways = cardGateways.where((ref.watch(gatewaysProvider(b.currency)).value ?? const []).contains).toList();

            return RefreshIndicator(
              onRefresh: _refresh,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  BookingSummary(b),
                  const Divider(height: 32),
                  Line(t.bookingAmountDue, money(ref, b.due < 0 ? 0 : b.due, b.currency), bold: true),
                  if (b.status == 'draft' && b.expiresAt != null && !b.isExpiredDraft)
                    Text(t.checkoutHoldNote(TimeOfDay.fromDateTime(b.expiresAt!).format(context))),
                  const SizedBox(height: 16),
                  if (b.isExpiredDraft) ...[
                    Text(t.draftExpired, style: const TextStyle(color: Colors.orange)),
                    if (b.vehicle != null)
                      Padding(
                        padding: const EdgeInsets.only(top: 12),
                        child: FilledButton(
                          key: const Key('book-again'),
                          onPressed: () => context.go('/vehicles/${b.vehicle!.id}'),
                          child: Text(t.bookAgain),
                        ),
                      ),
                  ] else if (!b.canPay)
                    FilledButton(onPressed: () => context.go('/confirmation/${b.id}'), child: Text(t.confirmationTitle))
                  else ...[
                    FilledButton(
                      key: const Key('pay-wallet'),
                      onPressed: busy || balance < b.due ? null : () => _pay('wallet'),
                      child: Text('${t.checkoutPayWallet} (${money(ref, balance, b.currency)})'),
                    ),
                    if (balance < b.due)
                      Padding(
                        padding: const EdgeInsets.only(top: 4),
                        child: Text(t.walletTooLow, style: Theme.of(context).textTheme.bodySmall),
                      ),
                    const SizedBox(height: 8),
                    for (final g in gateways)
                      OutlinedButton(key: Key('pay-$g'), onPressed: busy ? null : () => _pay(g), child: Text(payWith(t, g))),
                    if (gateways.isEmpty) Text(t.checkoutNoCard, style: Theme.of(context).textTheme.bodySmall),
                  ],
                  if (error != null && !b.isExpiredDraft) // an expired draft already says so above
                    Padding(
                      padding: const EdgeInsets.only(top: 12),
                      child: Text(error!, style: const TextStyle(color: Colors.red)),
                    ),
                ],
              ),
            );
          },
        ),
      ),
    );
  }
}
