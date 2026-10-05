import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../core/api.dart';

final bookingProvider = FutureProvider.autoDispose.family<Booking, int>((ref, id) => ref.watch(apiProvider).booking(id));
final gatewaysProvider = FutureProvider.autoDispose.family<List<String>, String>((ref, currency) => ref.watch(apiProvider).gateways(currency));
final walletProvider = FutureProvider.autoDispose((ref) => ref.watch(apiProvider).wallet());

final bookingsProvider = AsyncNotifierProvider.autoDispose<BookingsNotifier, Page<Booking>>(BookingsNotifier.new);

class BookingsNotifier extends AsyncNotifier<Page<Booking>> {
  @override
  Future<Page<Booking>> build() => ref.watch(apiProvider).bookings();

  Future<void> loadMore() async {
    final current = state.value;
    if (current == null || !current.hasMore) return;
    final next = await ref.read(apiProvider).bookings(page: current.currentPage + 1);
    state = AsyncData(Page([...current.items, ...next.items], next.currentPage, next.lastPage));
  }
}
