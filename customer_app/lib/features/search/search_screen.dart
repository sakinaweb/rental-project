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
      appBar: AppBar(title: Text(context.t.searchTitle)),
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(resultsProvider.future),
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Wrap(
              spacing: 12,
              runSpacing: 12,
              children: [
                SizedBox(
                  width: 200,
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
                    width: 200,
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
                  label: context.t.searchPickUp,
                  value: f.from,
                  onPick: (d) => set(f.copyWith(from: d)),
                ),
                _DateField(
                  label: context.t.searchReturn,
                  value: f.to,
                  onPick: (d) => set(f.copyWith(to: d)),
                ),
                SizedBox(
                  width: 200,
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
            ),
            if (f.periodInvalid)
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Text(context.t.searchDatesInvalid, style: const TextStyle(color: Colors.red)),
              ),
            const SizedBox(height: 16),
            AsyncView(
              results,
              onRetry: () => ref.invalidate(resultsProvider),
              data: (page) => page.items.isEmpty
                  ? Empty(context.t.searchNoResults)
                  : Column(
                      children: [
                        for (final v in page.items) _VehicleCard(v, f.period),
                        if (page.hasMore) TextButton(onPressed: ref.read(resultsProvider.notifier).loadMore, child: const Icon(Icons.expand_more)),
                      ],
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DateField extends StatelessWidget {
  const _DateField({required this.label, required this.value, required this.onPick});
  final String label;
  final DateTime? value;
  final ValueChanged<DateTime?> onPick;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: 200,
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
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('${vehicle.category} · ${vehicle.type == 'bike' ? context.t.searchBike : context.t.searchCar}', style: small),
              Text(vehicle.title, style: Theme.of(context).textTheme.titleMedium),
              Text(specs),
              Text(vehicle.branch),
              const SizedBox(height: 8),
              if (price == null)
                Text(context.t.searchPickDatesForPrice, style: small)
              else
                price.when(
                  data: (q) => q == null
                      ? Text(context.t.vehiclePriceUnavailable, style: small)
                      : Text.rich(
                          TextSpan(
                            children: [
                              TextSpan(
                                text: money(ref, q.total, q.currency),
                                style: const TextStyle(fontWeight: FontWeight.bold),
                              ),
                              TextSpan(text: '  ${context.t.searchTotalForDates}', style: small),
                            ],
                          ),
                        ),
                  loading: () => const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)),
                  error: (_, _) => const Icon(Icons.error_outline),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
