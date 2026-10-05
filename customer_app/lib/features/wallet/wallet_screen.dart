import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api.dart';
import '../../core/money.dart';
import '../../core/session.dart';
import '../../core/ui.dart';
import '../booking/booking_providers.dart';
import '../booking/card_checkout.dart';
import '../search/search_providers.dart';

/// Wallet (GET /wallet) and top-up (POST /wallet/topup). The balance changes only when the gateway webhook
/// reports the money arrived; pull to refresh afterwards.
class WalletScreen extends ConsumerStatefulWidget {
  const WalletScreen({super.key});

  @override
  ConsumerState<WalletScreen> createState() => _WalletScreenState();
}

class _WalletScreenState extends ConsumerState<WalletScreen> {
  final amount = TextEditingController();
  bool busy = false;
  String? error;
  String? notice;

  String _typeLabel(String type) => switch (type) {
    'topup' => context.t.walletTypeTopup,
    'payment' => context.t.walletTypePayment,
    'refund' => context.t.walletTypeRefund,
    'deposit_release' => context.t.walletTypeDepositRelease,
    _ => type,
  };

  Future<void> _topup(String currency) async {
    final minor = toMinor(amount.text, currency);
    if (minor == null) return setState(() => error = context.t.walletAmountInvalid);
    setState(() {
      busy = true;
      error = null;
      notice = null;
    });
    try {
      final intent = await ref.read(apiProvider).topup(minor, currency, 'razorpay');
      if (!mounted) return;
      final finished = await ref.read(cardCheckoutProvider)('razorpay', intent.client!, title: context.t.walletTopUp);
      if (mounted) setState(() => finished ? notice = context.t.walletProcessing : error = context.t.paymentNotCompleted);
    } on ApiException catch (e) {
      if (mounted) setState(() => error = errorText(context, e));
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = context.t;
    final countryId = ref.watch(sessionProvider).user?.countryId;
    final currency = ref.watch(countriesProvider).value?.where((c) => c.id == countryId).firstOrNull?.currency;
    final canTopUp = (ref.watch(gatewaysProvider).value ?? const []).contains('razorpay') && currency != null;

    return Scaffold(
      appBar: AppBar(title: Text(t.navWallet)),
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(walletProvider.future),
        child: AsyncView(
          ref.watch(walletProvider),
          onRetry: () => ref.invalidate(walletProvider),
          data: (w) => ListView(
            padding: const EdgeInsets.all(16),
            children: [
              if (w.balances.isEmpty) Empty(t.walletEmpty),
              for (final e in w.balances.entries)
                Card(
                  child: ListTile(
                    title: Text('${t.walletBalance} · ${e.key}'),
                    trailing: Text(money(ref, e.value, e.key), style: Theme.of(context).textTheme.titleLarge),
                  ),
                ),
              const SizedBox(height: 16),
              Text(t.walletTopUp, style: Theme.of(context).textTheme.titleMedium),
              if (!canTopUp)
                Text(t.checkoutNoCard, style: Theme.of(context).textTheme.bodySmall)
              else ...[
                TextField(
                  key: const Key('amount'),
                  controller: amount,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  decoration: InputDecoration(labelText: '${t.walletAmount} ($currency)'),
                ),
                const SizedBox(height: 8),
                FilledButton(key: const Key('topup'), onPressed: busy ? null : () => _topup(currency), child: Text(t.checkoutPayRazorpay)),
              ],
              if (notice != null)
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: Text(notice!, style: const TextStyle(color: Colors.orange)),
                ),
              if (error != null)
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: Text(error!, style: const TextStyle(color: Colors.red)),
                ),
              const Divider(height: 32),
              if (w.transactions.isEmpty) Empty(t.walletNoTransactions),
              for (final tx in w.transactions)
                ListTile(
                  title: Text(_typeLabel(tx.type)),
                  subtitle: Text(dateTime(ref, tx.at)),
                  trailing: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text(
                        money(ref, tx.amount, tx.currency),
                        style: TextStyle(color: tx.amount > 0 ? Colors.green.shade700 : null, fontWeight: FontWeight.w600),
                      ),
                      Text(money(ref, tx.balanceAfter, tx.currency), style: Theme.of(context).textTheme.bodySmall),
                    ],
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
