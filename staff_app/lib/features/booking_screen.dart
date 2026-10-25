import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../core/config.dart';
import '../core/ui.dart';
import 'providers.dart';

/// Customer, vehicle, the checklists done so far and any damages; the next step's button.
class BookingScreen extends ConsumerWidget {
  const BookingScreen(this.id, {super.key});
  final int id;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final t = context.t;
    return Scaffold(
      appBar: AppBar(title: Text(t.booking('$id'))),
      body: AsyncView(
        ref.watch(bookingProvider(id)),
        onRetry: () => ref.invalidate(bookingProvider(id)),
        data: (b) => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(t.status(b.status), style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(b.customer),
              subtitle: Text(b.phone ?? '—'),
              leading: const Icon(Icons.person),
            ),
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(b.vehicle),
              subtitle: Text(t.currentOdometer('${b.odometer ?? '—'}')),
              leading: const Icon(Icons.two_wheeler),
            ),
            ListTile(
              contentPadding: EdgeInsets.zero,
              title: Text('${dateTime(b.start)} → ${dateTime(b.end)}'),
              leading: const Icon(Icons.schedule),
            ),
            if (b.canHandOver)
              FilledButton.icon(
                key: const Key('hand-over'),
                onPressed: () => context.push('/bookings/$id/pickup'),
                icon: const Icon(Icons.key),
                label: Text(t.handOver),
              ),
            if (b.canReceive)
              FilledButton.icon(
                key: const Key('receive'),
                onPressed: () => context.push('/bookings/$id/return'),
                icon: const Icon(Icons.assignment_return),
                label: Text(t.receive),
              ),
            if (b.inspections.isNotEmpty) ...[
              const Divider(height: 32),
              Text(t.checklists, style: Theme.of(context).textTheme.titleMedium),
              for (final i in b.inspections)
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('${i['stage'] == 'pickup' ? t.stagePickup : t.stageReturn} · ${t.by(i['inspected_by'] ?? '—')}'),
                        Text('${t.odometer}: ${i['odometer']} · ${t.fuel('${i['fuel_level']}')}'),
                        if ((i['notes'] ?? '').isNotEmpty) Text(i['notes']),
                        const SizedBox(height: 8),
                        Wrap(
                          spacing: 4,
                          runSpacing: 4,
                          children: [
                            for (final url in List<String>.from(i['photos']))
                              Image.network(
                                mediaUrl(url),
                                width: 72,
                                height: 72,
                                fit: BoxFit.cover,
                                errorBuilder: (_, _, _) => const Icon(Icons.broken_image),
                              ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
            ],
            if (b.damages.isNotEmpty) ...[
              const Divider(height: 32),
              Text(t.damages, style: Theme.of(context).textTheme.titleMedium),
              for (final d in b.damages)
                ListTile(contentPadding: EdgeInsets.zero, title: Text(d['description']), subtitle: Text(d['status'])),
            ],
          ],
        ),
      ),
    );
  }
}
