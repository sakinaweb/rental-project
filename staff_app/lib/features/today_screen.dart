import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/api.dart';
import '../core/session.dart';
import '../core/ui.dart';
import 'providers.dart';

/// The branch's day: pickups due (late ones first) and every vehicle that is out.
class TodayScreen extends ConsumerWidget {
  const TodayScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final t = context.t;
    final day = ref.watch(dayProvider);

    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: Text(day.value?.branch ?? t.today),
          actions: [
            IconButton(tooltip: t.signOut, icon: const Icon(Icons.logout), onPressed: () => ref.read(sessionProvider.notifier).signOut()),
          ],
          bottom: TabBar(
            tabs: [
              Tab(text: '${t.pickups} (${day.value?.pickups.length ?? '…'})'),
              Tab(text: '${t.returns} (${day.value?.returns.length ?? '…'})'),
            ],
          ),
        ),
        body: AsyncView(
          day,
          onRetry: () => ref.invalidate(dayProvider),
          data: (d) => TabBarView(
            children: [
              _List(d.pickups, empty: t.noPickups, due: (b) => b.start),
              _List(d.returns, empty: t.noReturns, due: (b) => b.end),
            ],
          ),
        ),
      ),
    );
  }
}

class _List extends ConsumerWidget {
  const _List(this.items, {required this.empty, required this.due});
  final List<StaffBooking> items;
  final String empty;
  final DateTime Function(StaffBooking) due;

  @override
  Widget build(BuildContext context, WidgetRef ref) => RefreshIndicator(
    onRefresh: () => ref.refresh(dayProvider.future),
    child: items.isEmpty
        ? ListView(
            children: [
              Padding(
                padding: const EdgeInsets.all(32),
                child: Text(empty, textAlign: TextAlign.center),
              ),
            ],
          )
        : ListView(
            padding: const EdgeInsets.all(12),
            children: [
              for (final b in items)
                Card(
                  margin: const EdgeInsets.only(bottom: 10),
                  // Late ones get an orange edge so they stand out while scrolling.
                  shape: due(b).isBefore(DateTime.now())
                      ? RoundedRectangleBorder(borderRadius: BorderRadius.circular(16), side: BorderSide(color: Colors.orange.shade300, width: 1.5))
                      : null,
                  child: ListTile(
                    key: Key('booking-${b.id}'),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                    leading: CircleAvatar(
                      backgroundColor: brand.withValues(alpha: .1),
                      foregroundColor: brand,
                      child: const Icon(Icons.directions_car_filled_rounded),
                    ),
                    title: Text('${b.customer} · #${b.id}', style: const TextStyle(fontWeight: FontWeight.w600)),
                    subtitle: Text('${b.vehicle}\n${context.t.due(dateTime(due(b)))}'),
                    isThreeLine: true,
                    trailing: due(b).isBefore(DateTime.now())
                        ? Chip(label: Text(context.t.late), backgroundColor: Colors.orange.shade100)
                        : const Icon(Icons.chevron_right),
                    onTap: () => context.push('/bookings/${b.id}'),
                  ),
                ),
            ],
          ),
  );
}
