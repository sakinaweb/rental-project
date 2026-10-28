import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api.dart';
import '../../core/money.dart';
import '../../core/ui.dart';

typedef InvestorView = ({Map<String, dynamic>? dashboard, Map<String, dynamic>? disclosure, List<Map<String, dynamic>> plans});

/// Only what the current step needs: no investor yet (404) → apply; approved without the current disclosure → disclosure;
/// fully onboarded → open plans.
final investorProvider = FutureProvider.autoDispose<InvestorView>((ref) async {
  final api = ref.watch(apiProvider);
  Map<String, dynamic>? dash;
  try {
    dash = await api.investorDashboard();
  } on ApiException catch (e) {
    if (e.status != 404) rethrow;
  }
  final approved = dash?['kyc_status'] == 'approved';
  final accepted = dash?['disclosure_accepted'] == true;
  return (
    dashboard: dash,
    disclosure: approved && !accepted ? await api.disclosure() : null,
    plans: approved && accepted ? await api.investmentPlans() : const <Map<String, dynamic>>[],
  );
});

/// Investor area (Phase 11, same steps as the website's /investor): apply with bank details → KYC by the admin →
/// accept the risk disclosure → invest in a plan (pending until the bank transfer arrives) → earnings and payouts.
class InvestorScreen extends ConsumerStatefulWidget {
  const InvestorScreen({super.key});

  @override
  ConsumerState<InvestorScreen> createState() => _InvestorScreenState();
}

class _InvestorScreenState extends ConsumerState<InvestorScreen> {
  final payout = {
    for (final f in ['account_holder', 'bank_name', 'account_number', 'routing_code']) f: TextEditingController(),
  };
  final amounts = <int, TextEditingController>{};
  final payoutAmount = TextEditingController();
  String? payoutCurrency;
  bool accepted = false;
  bool busy = false;
  ApiException? error;
  String? notice;

  /// Runs an API action, shows its refusal or [done]'s notice, then reloads the screen.
  Future<void> _act(Future<String?> Function() action) async {
    setState(() {
      busy = true;
      error = null;
      notice = null;
    });
    try {
      final message = await action();
      if (mounted) setState(() => notice = message);
      ref.invalidate(investorProvider);
    } on ApiException catch (e) {
      if (mounted) setState(() => error = e);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  String _status(String s) => switch (s) {
    'pending' => context.t.investorStatusPending,
    'active' => context.t.investorStatusActive,
    'closed' => context.t.investorStatusClosed,
    'cancelled' => context.t.investorStatusCancelled,
    _ => s,
  };

  String _payoutStatus(String s) => switch (s) {
    'pending' => context.t.payoutStatusPending,
    'batched' => context.t.payoutStatusBatched,
    'paid' => context.t.payoutStatusPaid,
    'rejected' => context.t.payoutStatusRejected,
    _ => s,
  };

  void _badAmount() => setState(
    () => error = ApiException(
      '',
      status: 422,
      errors: {
        'amount': [context.t.walletAmountInvalid],
      },
    ),
  );

  static String _pct(num p) => p == p.roundToDouble() ? '${p.round()}' : p.toStringAsFixed(2).replaceFirst(RegExp(r'0+$'), '');

  @override
  Widget build(BuildContext context) {
    final t = context.t;
    return Scaffold(
      appBar: AppBar(title: Text(t.investorTitle)),
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(investorProvider.future),
        child: AsyncView(
          ref.watch(investorProvider),
          onRetry: () => ref.invalidate(investorProvider),
          data: (v) => ListView(
            physics: const AlwaysScrollableScrollPhysics(), // pull to refresh works on the short "KYC pending" page too
            padding: const EdgeInsets.all(16),
            children: [
              if (notice != null) _Banner(notice!, Colors.green.shade50, Colors.green.shade900),
              if (error != null && error!.errors.isEmpty) _Banner(errorText(context, error!), Colors.red.shade50, Colors.red.shade900),
              ..._step(v),
            ],
          ),
        ),
      ),
    );
  }

  List<Widget> _step(InvestorView v) {
    final t = context.t;
    final dash = v.dashboard;
    final kyc = dash?['kyc_status'];

    if (dash == null || kyc == 'rejected') {
      final labels = {
        'account_holder': t.payoutAccountHolder,
        'bank_name': t.payoutBankName,
        'account_number': t.payoutAccountNumber,
        'routing_code': t.payoutRoutingCode,
      };
      return [
        Text(t.investorApplyTitle, style: Theme.of(context).textTheme.titleLarge),
        if (kyc == 'rejected') _Banner(t.investorKycRejected, Colors.amber.shade50, Colors.amber.shade900),
        const SizedBox(height: 8),
        Text(t.investorApplyIntro),
        for (final e in payout.entries)
          TextField(
            key: Key('payout-${e.key}'),
            controller: e.value,
            decoration: InputDecoration(labelText: labels[e.key], errorText: error?.field('payout_details.${e.key}')),
          ),
        const SizedBox(height: 16),
        FilledButton(
          key: const Key('apply'),
          onPressed: busy
              ? null
              : () => _act(() async {
                  await ref.read(apiProvider).investorApply({
                    for (final e in payout.entries)
                      if (e.value.text.trim().isNotEmpty) e.key: e.value.text.trim(),
                  });
                  return null;
                }),
          child: Text(t.investorApplyTitle),
        ),
      ];
    }

    if (kyc != 'approved') {
      return [
        Card(
          child: Padding(padding: const EdgeInsets.all(16), child: Text(t.investorKycPending)),
        ),
      ];
    }

    final disclosure = v.disclosure;
    if (disclosure != null) {
      return [
        Text(t.investorDisclosureTitle, style: Theme.of(context).textTheme.titleLarge),
        const SizedBox(height: 8),
        _Banner(disclosure['text'] as String, Colors.amber.shade50, Colors.brown.shade900),
        CheckboxListTile(
          key: const Key('accept'),
          contentPadding: EdgeInsets.zero,
          controlAffinity: ListTileControlAffinity.leading,
          value: accepted,
          onChanged: (a) => setState(() => accepted = a ?? false),
          title: Text(t.investorAcceptLabel),
        ),
        FilledButton(
          key: const Key('accept-disclosure'),
          onPressed: busy || !accepted
              ? null
              : () => _act(() async {
                  await ref.read(apiProvider).acceptDisclosure(disclosure['version'] as String);
                  return null;
                }),
          child: Text(t.investorAccept),
        ),
      ];
    }

    final investments = List<Map<String, dynamic>>.from(dash['investments'] ?? const []);
    final balances = List<Map<String, dynamic>>.from(dash['balances'] ?? const []);
    final payouts = List<Map<String, dynamic>>.from(dash['payout_requests'] ?? const []);
    final currency = payoutCurrency ?? (balances.isEmpty ? null : balances.first['currency_code'] as String);

    return [
      for (final b in balances)
        Card(
          color: brand,
          child: ListTile(
            textColor: Colors.white,
            title: Text('${t.payoutBalance} · ${b['currency_code']}'),
            subtitle: Text('${t.payoutAvailable}: ${money(ref, b['available'], b['currency_code'])}', style: const TextStyle(color: Colors.white70)),
            trailing: Text(money(ref, b['balance'], b['currency_code']), style: Theme.of(context).textTheme.titleLarge?.copyWith(color: Colors.white)),
          ),
        ),
      const SizedBox(height: 8),
      Text(t.investorPlans, style: Theme.of(context).textTheme.titleMedium),
      if (v.plans.isEmpty) Empty(t.investorNoPlans),
      for (final p in v.plans) _plan(p),
      const SizedBox(height: 16),
      Text(t.investorInvestments, style: Theme.of(context).textTheme.titleMedium),
      if (investments.isEmpty) Empty(t.investorNoInvestments),
      for (final i in investments)
        Card(
          child: ListTile(
            title: Text('INV-${i['id']} · ${i['plan']['name']}'),
            subtitle: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(_status(i['status'])),
                if (i['started_at'] != null)
                  Text('${dateTime(ref, DateTime.parse(i['started_at']).toLocal())} – ${dateTime(ref, DateTime.parse(i['ends_at']).toLocal())}'),
                for (final veh in i['vehicles'] as List) Text('${veh['title']} · ${veh['plate_no']}'),
              ],
            ),
            trailing: Text(money(ref, i['amount'], i['currency_code']), style: const TextStyle(fontWeight: FontWeight.bold)),
          ),
        ),
      const Divider(height: 32),
      Text(t.payoutRequest, style: Theme.of(context).textTheme.titleMedium),
      if (currency != null) ...[
        Row(
          children: [
            Expanded(
              flex: 2,
              child: TextField(
                key: const Key('payout-amount'),
                controller: payoutAmount,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                decoration: InputDecoration(labelText: t.payoutAmount, errorText: error?.field('amount')),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: DropdownButtonFormField<String>(
                initialValue: currency,
                decoration: InputDecoration(labelText: t.payoutCurrency),
                items: [for (final b in balances) DropdownMenuItem(value: b['currency_code'] as String, child: Text(b['currency_code']))],
                onChanged: (c) => setState(() => payoutCurrency = c),
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        FilledButton(
          key: const Key('request-payout'),
          onPressed: busy
              ? null
              : () {
                  final minor = toMinor(payoutAmount.text, currency);
                  if (minor == null) return _badAmount();
                  _act(() async {
                    await ref.read(apiProvider).requestInvestorPayout(currency, minor);
                    payoutAmount.clear();
                    return t.payoutRequested;
                  });
                },
          child: Text(t.payoutRequest),
        ),
      ],
      const SizedBox(height: 16),
      Text(t.payoutHistory, style: Theme.of(context).textTheme.titleMedium),
      if (payouts.isEmpty) Empty(t.payoutNone),
      for (final p in payouts)
        ListTile(
          title: Text(_payoutStatus(p['status'])),
          subtitle: Text(dateTime(ref, DateTime.parse(p['requested_at']).toLocal())),
          trailing: Text(money(ref, p['amount'], p['currency_code'])),
        ),
    ];
  }

  Widget _plan(Map<String, dynamic> p) {
    final t = context.t;
    final id = p['id'] as int;
    final currency = p['currency_code'] as String;
    final amount = amounts.putIfAbsent(id, TextEditingController.new);
    final pct = _pct(num.parse('${p['revenue_share_pct']}'));

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              (p['category'] as String?) ?? t.investorAllVehicles,
              style: const TextStyle(color: brand, fontWeight: FontWeight.w600),
            ),
            Text(p['name'], style: Theme.of(context).textTheme.titleMedium),
            Text('$pct%', style: Theme.of(context).textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.bold)),
            Text(t.investorShare(pct)),
            const SizedBox(height: 4),
            Text('${t.investorTerm(p['term_months'])} · ${t.investorMinimum(money(ref, p['min_amount'], currency))}'),
            TextField(
              key: Key('invest-amount-$id'),
              controller: amount,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              decoration: InputDecoration(labelText: t.investorAmount(currency), errorText: error?.field('amount')),
            ),
            const SizedBox(height: 8),
            FilledButton(
              key: Key('invest-$id'),
              onPressed: busy
                  ? null
                  : () {
                      final minor = toMinor(amount.text, currency);
                      if (minor == null) return _badAmount();
                      _act(() async {
                        final i = await ref.read(apiProvider).invest(id, minor);
                        amount.clear();
                        return t.investorInvested(money(ref, i['amount'], currency), '${i['id']}');
                      });
                    },
              child: Text(t.investorInvest),
            ),
          ],
        ),
      ),
    );
  }
}

class _Banner extends StatelessWidget {
  const _Banner(this.text, this.background, this.foreground);
  final String text;
  final Color background;
  final Color foreground;

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.symmetric(vertical: 8),
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(color: background, borderRadius: BorderRadius.circular(8)),
    child: Text(text, style: TextStyle(color: foreground)),
  );
}
