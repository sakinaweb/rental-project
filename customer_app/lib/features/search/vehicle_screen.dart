import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';

import '../../core/api.dart';
import '../../core/config.dart';
import '../../core/session.dart';
import '../../core/ui.dart';
import 'search_providers.dart';

final vehicleProvider = FutureProvider.autoDispose.family<Vehicle, int>((ref, id) => ref.watch(apiProvider).vehicle(id));

/// Vehicle details → availability → quote → book (MD §7.7). All numbers come from the API.
class VehicleScreen extends ConsumerStatefulWidget {
  const VehicleScreen(this.id, this.query, {super.key});
  final int id;

  /// from, to (ISO), addons ("id:qty,…"), coupon — kept in the link so sign-in can bring the customer back.
  final Map<String, String> query;

  @override
  ConsumerState<VehicleScreen> createState() => _VehicleScreenState();
}

class _VehicleScreenState extends ConsumerState<VehicleScreen> {
  DateTime? from;
  DateTime? to;
  final qty = <int, int>{};
  final coupon = TextEditingController();
  Timer? _debounce;
  bool? available;
  Quote? quote;
  String? quoteError;
  String? bookError;
  bool busy = false;

  @override
  void initState() {
    super.initState();
    from = DateTime.tryParse(widget.query['from'] ?? '');
    to = DateTime.tryParse(widget.query['to'] ?? '');
    for (final pair in (widget.query['addons'] ?? '').split(',').where((p) => p.contains(':'))) {
      final [id, n] = pair.split(':');
      qty[int.parse(id)] = int.parse(n);
    }
    coupon.text = widget.query['coupon'] ?? '';
    _refresh();
  }

  @override
  void dispose() {
    _debounce?.cancel();
    coupon.dispose();
    super.dispose();
  }

  Period? get period => from != null && to != null && to!.isAfter(from!) ? (from: from!, to: to!) : null;

  /// Availability + quote for the current choices (debounced for typing in the coupon field).
  void _refresh({bool debounce = false}) {
    _debounce?.cancel();
    _debounce = Timer(Duration(milliseconds: debounce ? 500 : 0), () async {
      final p = period;
      setState(() {
        available = null;
        quote = null;
        quoteError = null;
      });
      if (p == null) return;
      final api = ref.read(apiProvider);
      try {
        final results = await Future.wait([api.available(widget.id, p), api.quote(widget.id, p, addons: qty, coupon: coupon.text)]);
        if (!mounted) return;
        setState(() {
          available = results[0] as bool;
          quote = results[1] as Quote;
        });
      } on ApiException catch (e) {
        if (mounted) setState(() => quoteError = errorText(context, e));
      }
    });
  }

  String get _link => Uri(
    path: '/vehicles/${widget.id}',
    queryParameters: {
      if (from != null) 'from': from!.toIso8601String(),
      if (to != null) 'to': to!.toIso8601String(),
      if (qty.values.any((n) => n > 0)) 'addons': qty.entries.where((e) => e.value > 0).map((e) => '${e.key}:${e.value}').join(','),
      if (coupon.text.isNotEmpty) 'coupon': coupon.text,
    },
  ).toString();

  Future<void> _book() async {
    if (!ref.read(sessionProvider).signedIn) {
      context.go(Uri(path: '/login', queryParameters: {'from': _link}).toString());
      return;
    }
    setState(() {
      busy = true;
      bookError = null;
    });
    try {
      final booking = await ref.read(apiProvider).createBooking(widget.id, period!, addons: qty, coupon: coupon.text);
      if (mounted) context.go('/checkout/${booking.id}');
    } on ApiException catch (e) {
      if (mounted) setState(() => bookError = errorText(context, e));
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = context.t;
    final vehicle = ref.watch(vehicleProvider(widget.id));

    return Scaffold(
      appBar: AppBar(title: Text(vehicle.value?.title ?? '')),
      body: AsyncView(
        vehicle,
        onRetry: () => ref.invalidate(vehicleProvider(widget.id)),
        data: (v) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text('${v.category} · ${v.type == 'bike' ? t.searchBike : t.searchCar}', style: Theme.of(context).textTheme.labelMedium),
            Text(v.title, style: Theme.of(context).textTheme.headlineSmall),
            const SizedBox(height: 8),
            Wrap(
              spacing: 16,
              children: [
                Text('${t.vehicleSeatsLabel}: ${v.seats ?? '—'}'),
                Text('${t.vehicleTransmission}: ${v.transmission ?? '—'}'),
                Text('${t.vehicleFuel}: ${v.fuel ?? '—'}'),
              ],
            ),
            const SizedBox(height: 8),
            Text('${t.vehicleBranch}: ${v.branch} · ${v.locationName}'),
            if (Config.mapsEnabled && v.lat != null && v.lng != null) ...[
              const SizedBox(height: 8),
              SizedBox(
                height: 180,
                child: GoogleMap(
                  initialCameraPosition: CameraPosition(target: LatLng(v.lat!, v.lng!), zoom: 14),
                  markers: {
                    Marker(
                      markerId: const MarkerId('branch'),
                      position: LatLng(v.lat!, v.lng!),
                      infoWindow: InfoWindow(title: v.branch),
                    ),
                  },
                  liteModeEnabled: true,
                ),
              ),
            ],
            const Divider(height: 32),
            Row(
              children: [
                Expanded(child: _pick(t.searchPickUp, from, (d) => from = d)),
                const SizedBox(width: 12),
                Expanded(child: _pick(t.searchReturn, to, (d) => to = d)),
              ],
            ),
            if (from != null && to != null && period == null) Text(t.searchDatesInvalid, style: const TextStyle(color: Colors.red)),
            const SizedBox(height: 8),
            if (available == true) Chip(label: Text(t.vehicleAvailable), backgroundColor: Colors.green.shade50),
            if (available == false) Chip(label: Text(t.vehicleUnavailable), backgroundColor: Colors.red.shade50),
            ...ref
                .watch(addonsProvider)
                .maybeWhen(
                  data: (addons) => [
                    if (addons.isNotEmpty)
                      Padding(
                        padding: const EdgeInsets.only(top: 16),
                        child: Text(t.vehicleAddons, style: Theme.of(context).textTheme.titleMedium),
                      ),
                    for (final a in addons)
                      ListTile(
                        contentPadding: EdgeInsets.zero,
                        title: Text(a.name),
                        subtitle: Text(a.perDay ? t.vehicleAddonPerDay : t.vehicleAddonPerBooking),
                        trailing: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            IconButton(
                              onPressed: (qty[a.id] ?? 0) > 0
                                  ? () {
                                      setState(() => qty[a.id] = qty[a.id]! - 1);
                                      _refresh();
                                    }
                                  : null,
                              icon: const Icon(Icons.remove),
                            ),
                            Text('${qty[a.id] ?? 0}', key: Key('qty-${a.id}')),
                            IconButton(
                              onPressed: () {
                                setState(() => qty[a.id] = (qty[a.id] ?? 0) + 1);
                                _refresh();
                              },
                              icon: const Icon(Icons.add),
                            ),
                          ],
                        ),
                      ),
                  ],
                  orElse: () => const <Widget>[],
                ),
            const SizedBox(height: 8),
            TextField(
              controller: coupon,
              decoration: InputDecoration(labelText: t.vehicleCoupon),
              onChanged: (_) => _refresh(debounce: true),
            ),
            const Divider(height: 32),
            Text(t.vehicleQuote, style: Theme.of(context).textTheme.titleMedium),
            if (quoteError != null) Text(quoteError!, style: const TextStyle(color: Colors.red)),
            if (quote == null && quoteError == null) Text(period == null ? t.searchPickDatesForPrice : '…'),
            if (quote != null) ...[
              Line(t.vehicleSubtotal, money(ref, quote!.subtotal, quote!.currency)),
              if (quote!.discount > 0) Line(t.vehicleDiscounts, '−${money(ref, quote!.discount, quote!.currency)}'),
              Line(t.vehicleTax, money(ref, quote!.tax, quote!.currency)),
              Line(t.vehicleTotal, money(ref, quote!.total, quote!.currency), bold: true),
              Line(t.vehicleDeposit, money(ref, quote!.deposit, quote!.currency)),
              Line(t.vehicleDueNow, money(ref, quote!.dueNow, quote!.currency), bold: true),
            ],
            if (bookError != null)
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Text(bookError!, style: const TextStyle(color: Colors.red)),
              ),
            const SizedBox(height: 16),
            FilledButton(
              key: const Key('book'),
              onPressed: quote != null && available != false && !busy ? _book : null,
              child: Text(ref.watch(sessionProvider).signedIn ? t.vehicleBookNow : t.vehicleLoginToBook),
            ),
          ],
        ),
      ),
    );
  }

  Widget _pick(String label, DateTime? value, void Function(DateTime) assign) => InputDecorator(
    decoration: InputDecoration(labelText: label),
    child: InkWell(
      onTap: () async {
        final picked = await pickDateTime(context, value);
        if (picked == null) return;
        setState(() => assign(picked));
        _refresh();
      },
      child: Text(value == null ? context.t.selectDate : dateTime(ref, value)),
    ),
  );
}
