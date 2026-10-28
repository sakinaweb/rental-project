import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api.dart';
import '../../core/ui.dart';
import 'search_providers.dart';

/// Search + results (MD §7.7: mirrors the website's first step).
class SearchScreen extends ConsumerWidget {
  const SearchScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final f = ref.watch(filtersProvider);
    final set = ref.read(filtersProvider.notifier).set;
    final results = ref.watch(resultsProvider);

    return Scaffold(
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(resultsProvider.future),
        child: ListView(
          padding: EdgeInsets.zero,
          children: [
            Stack(
              children: [
                HeroHeader(title: context.t.searchTitle, bottomSpace: 72),
                const Positioned(right: -24, top: 24, child: Icon(Icons.two_wheeler_rounded, size: 160, color: Color(0x1AFFFFFF))),
              ],
            ),
            // The search card floats over the bottom of the hero.
            Transform.translate(
              offset: const Offset(0, -48),
              child: Container(
                margin: const EdgeInsets.symmetric(horizontal: 16),
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(24), boxShadow: softShadow),
                child: _filters(context, ref, f, set),
              ),
            ),
            Transform.translate(
              offset: const Offset(0, -32),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (f.periodInvalid)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 8),
                        child: Text(context.t.searchDatesInvalid, style: const TextStyle(color: Colors.red)),
                      ),
                    AsyncView(
                      results,
                      onRetry: () => ref.invalidate(resultsProvider),
                      data: (page) => page.items.isEmpty
                          ? Empty(context.t.searchNoResults)
                          : Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch, // full-width cards
                              children: [
                                for (final v in page.items) _VehicleCard(v, f.period),
                                if (page.hasMore) TextButton(onPressed: ref.read(resultsProvider.notifier).loadMore, child: const Icon(Icons.expand_more)),
                              ],
                            ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _filters(BuildContext context, WidgetRef ref, Filters f, void Function(Filters) set) => LayoutBuilder(
    // Phones: one full-width field per row; wider screens: 200 px fields side by side.
    builder: (context, c) {
      final w = c.maxWidth < 412 ? c.maxWidth : 200.0;
      return Wrap(
        spacing: 12,
        runSpacing: 12,
        children: [
          SizedBox(
            width: w,
            child: ref
                .watch(countriesProvider)
                .maybeWhen(
                  data: (countries) => DropdownButtonFormField<int?>(
                    isExpanded: true,
                    key: const Key('country'),
                    initialValue: f.countryId,
                    decoration: InputDecoration(labelText: context.t.searchCountry),
                    items: [
                      DropdownMenuItem(value: null, child: Text(context.t.searchAny)),
                      for (final c in countries) DropdownMenuItem(value: c.id, child: Text(c.name)),
                    ],
                    onChanged: (id) => set(f.copyWith(countryId: id, cityId: null)),
                  ),
                  orElse: () => const LinearProgressIndicator(),
                ),
          ),
          if (f.countryId != null)
            SizedBox(
              width: w,
              child: ref
                  .watch(citiesProvider(f.countryId!))
                  .maybeWhen(
                    data: (cities) => DropdownButtonFormField<int?>(
                      isExpanded: true,
                      key: const Key('city'),
                      initialValue: f.cityId,
                      decoration: InputDecoration(labelText: context.t.searchCity),
                      items: [
                        DropdownMenuItem(value: null, child: Text(context.t.searchAny)),
                        for (final c in cities) DropdownMenuItem(value: c.id, child: Text(c.name)),
                      ],
                      onChanged: (id) => set(f.copyWith(cityId: id)),
                    ),
                    orElse: () => const LinearProgressIndicator(),
                  ),
            ),
          _DateField(
            width: w,
            label: context.t.searchPickUp,
            value: f.from,
            onPick: (d) => set(f.copyWith(from: d)),
          ),
          _DateField(
            width: w,
            label: context.t.searchReturn,
            value: f.to,
            onPick: (d) => set(f.copyWith(to: d)),
          ),
          SizedBox(
            width: w,
            child: ref
                .watch(categoriesProvider)
                .maybeWhen(
                  data: (cats) => DropdownButtonFormField<int?>(
                    isExpanded: true,
                    initialValue: f.categoryId,
                    decoration: InputDecoration(labelText: context.t.searchCategory),
                    items: [
                      DropdownMenuItem(value: null, child: Text(context.t.searchAny)),
                      for (final c in cats) DropdownMenuItem(value: c.id, child: Text(c.name)),
                    ],
                    onChanged: (id) => set(f.copyWith(categoryId: id)),
                  ),
                  orElse: () => const SizedBox.shrink(),
                ),
          ),
          SegmentedButton<String?>(
            segments: [
              ButtonSegment(value: null, label: Text(context.t.searchAny)),
              ButtonSegment(value: 'bike', label: Text(context.t.searchBike)),
              ButtonSegment(value: 'car', label: Text(context.t.searchCar)),
            ],
            selected: {f.type},
            onSelectionChanged: (s) => set(f.copyWith(type: s.first)),
          ),
        ],
      );
    },
  );
}

class _DateField extends StatelessWidget {
  const _DateField({required this.width, required this.label, required this.value, required this.onPick});
  final double width;
  final String label;
  final DateTime? value;
  final ValueChanged<DateTime?> onPick;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: width,
    child: Consumer(
      builder: (context, ref, _) => InputDecorator(
        decoration: InputDecoration(
          labelText: label,
          suffixIcon: value == null ? null : IconButton(icon: const Icon(Icons.clear), onPressed: () => onPick(null)),
        ),
        child: InkWell(
          onTap: () async {
            final picked = await pickDateTime(context, value);
            if (picked != null) onPick(picked);
          },
          child: Text(value == null ? context.t.selectDate : dateTime(ref, value!)),
        ),
      ),
    ),
  );
}

class _VehicleCard extends ConsumerWidget {
  const _VehicleCard(this.vehicle, this.period);
  final Vehicle vehicle;
  final Period? period;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final price = period == null ? null : ref.watch(cardQuoteProvider((vehicle.id, period!)));
    final specs = [if (vehicle.seats != null) context.t.vehicleSeats(vehicle.seats!), ?vehicle.transmission, ?vehicle.fuel].join(' · ');

    final small = Theme.of(context).textTheme.bodySmall;

    // Price on its own line (a narrow trailing slot can't hold the hint text on a phone).
    return Card(
      child: InkWell(
        onTap: () => context.push(
          Uri(
            path: '/vehicles/${vehicle.id}',
            queryParameters: {if (period != null) 'from': period!.from.toIso8601String(), if (period != null) 'to': period!.to.toIso8601String()},
          ).toString(),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Stack(
              children: [
                VehicleImage(vehicle, height: 200),
                // Darkens the bottom of the photo so the chip reads on any image.
                const Positioned.fill(
                  child: DecoratedBox(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(begin: Alignment.center, end: Alignment.bottomCenter, colors: [Colors.transparent, Color(0x660F172A)]),
                    ),
                  ),
                ),
                PositionedDirectional(
                  start: 12,
                  bottom: 12,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                    decoration: BoxDecoration(color: const Color(0xE6FFFFFF), borderRadius: BorderRadius.circular(20)),
                    child: Text(
                      '${vehicle.category} · ${vehicle.type == 'bike' ? context.t.searchBike : context.t.searchCar}'.toUpperCase(),
                      style: small?.copyWith(color: brand, fontWeight: FontWeight.w700, letterSpacing: .6, fontSize: 11),
                    ),
                  ),
                ),
              ],
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(18, 16, 18, 18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(vehicle.title, style: Theme.of(context).textTheme.titleLarge),
                  const SizedBox(height: 4),
                  Text(specs, style: small),
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      const Icon(Icons.place_rounded, size: 16, color: brand),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Text(vehicle.branch, style: small, overflow: TextOverflow.ellipsis),
                      ),
                    ],
                  ),
                  const Padding(padding: EdgeInsets.symmetric(vertical: 12), child: Divider(height: 1)),
                  Row(
                    children: [
                      Expanded(
                        child: price == null
                            ? Text(context.t.searchPickDatesForPrice, style: small)
                            : price.when(
                                data: (q) => q == null
                                    ? Text(context.t.vehiclePriceUnavailable, style: small)
                                    : Text.rich(
                                        TextSpan(
                                          children: [
                                            TextSpan(
                                              text: money(ref, q.total, q.currency),
                                              style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 22, color: ink, letterSpacing: -.5),
                                            ),
                                            TextSpan(text: '  ${context.t.searchTotalForDates}', style: small),
                                          ],
                                        ),
                                      ),
                                loading: () => const Align(
                                  alignment: AlignmentDirectional.centerStart,
                                  child: SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)),
                                ),
                                error: (_, _) => const Icon(Icons.error_outline),
                              ),
                      ),
                      Container(
                        width: 40,
                        height: 40,
                        decoration: const BoxDecoration(gradient: brandGradient, shape: BoxShape.circle),
                        child: const Icon(Icons.arrow_forward_rounded, color: Colors.white, size: 20),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
