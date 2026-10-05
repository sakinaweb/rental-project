import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/ui.dart';
import 'booking_providers.dart';
import 'booking_summary.dart';

/// My bookings (GET /bookings: only the signed-in customer's own).
class BookingsScreen extends ConsumerWidget {
  const BookingsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final t = context.t;

    return Scaffold(
      appBar: AppBar(title: Text(t.navMyBookings)),
      body: RefreshIndicator(
        onRefresh: () => ref.refresh(bookingsProvider.future),
        child: AsyncView(
          ref.watch(bookingsProvider),
          onRetry: () => ref.invalidate(bookingsProvider),
          data: (page) => page.items.isEmpty
              ? ListView(children: [Empty(t.bookingNone)])
              : ListView(
                  padding: const EdgeInsets.all(12),
                  children: [
                    for (final b in page.items)
                      Card(
                        child: ListTile(
                          onTap: () => context.go('/bookings/${b.id}'),
                          title: Text('${b.vehicle?.title ?? ''}  #${b.id}'),
                          subtitle: Text('${dateTime(ref, b.start)} → ${dateTime(ref, b.end)}\n${statusLabel(context, b.status)}'),
                          isThreeLine: true,
                          trailing: Text(money(ref, b.total, b.currency), style: const TextStyle(fontWeight: FontWeight.w600)),
                        ),
                      ),
                    if (page.hasMore) TextButton(onPressed: ref.read(bookingsProvider.notifier).loadMore, child: const Icon(Icons.expand_more)),
                  ],
                ),
        ),
      ),
    );
  }
}
