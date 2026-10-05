import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api.dart';

class Filters {
  const Filters({this.countryId, this.cityId, this.from, this.to, this.categoryId, this.type});

  final int? countryId;
  final int? cityId;
  final DateTime? from;
  final DateTime? to;
  final int? categoryId;
  final String? type;

  Period? get period => from != null && to != null && to!.isAfter(from!) ? (from: from!, to: to!) : null;
  bool get periodInvalid => from != null && to != null && !to!.isAfter(from!);

  /// Same filters as GET /vehicles (and the website).
  Map<String, dynamic> get query => {
    if (cityId != null) 'city_id': cityId,
    if (categoryId != null) 'category': categoryId,
    if (type != null) 'type': type,
    if (period != null) ...{'from': period!.from.toUtc().toIso8601String(), 'to': period!.to.toUtc().toIso8601String()},
  };

  Filters copyWith({
    Object? countryId = _keep,
    Object? cityId = _keep,
    Object? from = _keep,
    Object? to = _keep,
    Object? categoryId = _keep,
    Object? type = _keep,
  }) => Filters(
    countryId: countryId == _keep ? this.countryId : countryId as int?,
    cityId: cityId == _keep ? this.cityId : cityId as int?,
    from: from == _keep ? this.from : from as DateTime?,
    to: to == _keep ? this.to : to as DateTime?,
    categoryId: categoryId == _keep ? this.categoryId : categoryId as int?,
    type: type == _keep ? this.type : type as String?,
  );
}

const _keep = Object();

final filtersProvider = NotifierProvider<FiltersNotifier, Filters>(FiltersNotifier.new);

class FiltersNotifier extends Notifier<Filters> {
  @override
  Filters build() => const Filters();

  void set(Filters filters) => state = filters;
}

final countriesProvider = FutureProvider((ref) => ref.watch(apiProvider).countries());
final citiesProvider = FutureProvider.family<List<Named>, int>((ref, countryId) => ref.watch(apiProvider).cities(countryId));
final categoriesProvider = FutureProvider((ref) => ref.watch(apiProvider).categories());
final addonsProvider = FutureProvider((ref) => ref.watch(apiProvider).addons());

final resultsProvider = AsyncNotifierProvider.autoDispose<ResultsNotifier, Page<Vehicle>>(ResultsNotifier.new);

class ResultsNotifier extends AsyncNotifier<Page<Vehicle>> {
  @override
  Future<Page<Vehicle>> build() {
    final filters = ref.watch(filtersProvider);
    if (filters.periodInvalid) return Future.value(Page([], 1, 1));
    return ref.watch(apiProvider).vehicles(filters.query);
  }

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.hasMore) return;
    final next = await ref.read(apiProvider).vehicles(ref.read(filtersProvider).query, page: current.currentPage + 1);
    state = AsyncData(Page([...current.items, ...next.items], next.currentPage, next.lastPage));
  }
}

/// A vehicle's real total for a period (POST /bookings/quote); null when it has no price for those dates.
final cardQuoteProvider = FutureProvider.autoDispose.family<Quote?, (int, Period)>((ref, args) async {
  try {
    return await ref.watch(apiProvider).quote(args.$1, args.$2);
  } on ApiException catch (e) {
    if (e.isNetwork) rethrow;
    return null;
  }
});
