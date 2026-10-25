import 'dart:io';
import 'dart:math';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../core/api.dart';
import '../core/ui.dart';
import 'providers.dart';

/// Handover or return checklist (owner decision: ≥ 4 photos, odometer, fuel; damages at return go to the admin).
class ChecklistScreen extends ConsumerStatefulWidget {
  const ChecklistScreen(this.id, {super.key, required this.returning});
  final int id;
  final bool returning;

  @override
  ConsumerState<ChecklistScreen> createState() => _ChecklistScreenState();
}

class _ChecklistScreenState extends ConsumerState<ChecklistScreen> {
  final photos = <String>[];
  final odometer = TextEditingController();
  final notes = TextEditingController();
  double fuel = 100;
  final damages = <DamageReport>[];
  bool busy = false;
  String? error;

  Future<void> _photo() async {
    final path = await ref.read(takePhotoProvider)();
    if (path != null) setState(() => photos.add(path));
  }

  Future<void> _addDamage(String currency) async {
    final damage = await showDialog<DamageReport>(context: context, builder: (_) => _DamageDialog(currency));
    if (damage != null) setState(() => damages.add(damage));
  }

  Future<void> _submit() async {
    final t = context.t;
    final km = int.tryParse(odometer.text.trim());
    if (photos.length < 4) return setState(() => error = t.needPhotos);
    if (km == null || km < 0) return setState(() => error = t.invalidNumber);

    setState(() {
      busy = true;
      error = null;
    });
    final checklist = Checklist(photos: photos, odometer: km, fuel: fuel.round(), notes: notes.text.trim());
    try {
      final api = ref.read(apiProvider);
      widget.returning ? await api.receive(widget.id, checklist, damages) : await api.handOver(widget.id, checklist);
      ref.invalidate(dayProvider);
      ref.invalidate(bookingProvider(widget.id));
      if (!mounted) return;
      snack(context, t.done);
      context.pop();
    } on ApiException catch (e) {
      if (mounted) setState(() => error = errorText(context, e));
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = context.t;
    final currency = ref.watch(bookingProvider(widget.id)).value?.currency ?? '';

    return Scaffold(
      appBar: AppBar(title: Text(widget.returning ? t.returnTitle : t.handoverTitle)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(t.photosHint),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final (i, p) in photos.indexed)
                Stack(
                  children: [
                    _Thumb(p),
                    Positioned(
                      right: 0,
                      top: 0,
                      child: IconButton.filledTonal(
                        tooltip: t.remove,
                        iconSize: 16,
                        icon: const Icon(Icons.close),
                        onPressed: () => setState(() => photos.removeAt(i)),
                      ),
                    ),
                  ],
                ),
              if (photos.length < 12)
                OutlinedButton.icon(
                  key: const Key('take-photo'),
                  onPressed: _photo,
                  icon: const Icon(Icons.photo_camera),
                  label: Text(t.addPhoto),
                ),
            ],
          ),
          Text(t.photoCount(photos.length), style: Theme.of(context).textTheme.bodySmall),
          const SizedBox(height: 16),
          TextField(
            key: const Key('odometer'),
            controller: odometer,
            keyboardType: TextInputType.number,
            decoration: InputDecoration(labelText: t.odometer),
          ),
          const SizedBox(height: 12),
          Text(t.fuel('${fuel.round()}')),
          Slider(
            key: const Key('fuel'),
            value: fuel,
            max: 100,
            divisions: 20,
            label: '${fuel.round()} %',
            onChanged: (v) => setState(() => fuel = v),
          ),
          TextField(
            controller: notes,
            maxLines: 2,
            decoration: InputDecoration(labelText: t.notes),
          ),
          if (widget.returning) ...[
            const Divider(height: 32),
            Text(t.damages, style: Theme.of(context).textTheme.titleMedium),
            for (final (i, d) in damages.indexed)
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(d.description),
                subtitle: Text('${_major(d.cost, currency)} $currency'),
                trailing: IconButton(
                  tooltip: t.remove,
                  icon: const Icon(Icons.delete),
                  onPressed: () => setState(() => damages.removeAt(i)),
                ),
              ),
            OutlinedButton.icon(
              key: const Key('add-damage'),
              onPressed: () => _addDamage(currency),
              icon: const Icon(Icons.add),
              label: Text(t.addDamage),
            ),
            Text(t.damageNote, style: Theme.of(context).textTheme.bodySmall),
          ],
          const SizedBox(height: 16),
          FilledButton(
            key: const Key('submit'),
            onPressed: busy ? null : _submit,
            child: Text(widget.returning ? t.confirmReturn : t.confirmHandover),
          ),
          if (error != null)
            Padding(
              padding: const EdgeInsets.only(top: 12),
              child: Text(error!, style: const TextStyle(color: Colors.red)),
            ),
        ],
      ),
    );
  }
}

int _digits(String currency) => currency.isEmpty ? 2 : NumberFormat.simpleCurrency(name: currency).decimalDigits ?? 2;
String _major(int minor, String currency) => (minor / pow(10, _digits(currency))).toStringAsFixed(_digits(currency));

class _Thumb extends StatelessWidget {
  const _Thumb(this.path);
  final String path;

  @override
  Widget build(BuildContext context) => kIsWeb || !File(path).existsSync()
      ? Container(width: 72, height: 72, color: Colors.black12, child: const Icon(Icons.image))
      : Image.file(File(path), width: 72, height: 72, fit: BoxFit.cover);
}

class _DamageDialog extends ConsumerStatefulWidget {
  const _DamageDialog(this.currency);
  final String currency;

  @override
  ConsumerState<_DamageDialog> createState() => _DamageDialogState();
}

class _DamageDialogState extends ConsumerState<_DamageDialog> {
  final description = TextEditingController();
  final cost = TextEditingController();
  final photos = <String>[];

  @override
  Widget build(BuildContext context) {
    final t = context.t;
    return AlertDialog(
      title: Text(t.addDamage),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          TextField(
            key: const Key('damage-description'),
            controller: description,
            decoration: InputDecoration(labelText: t.damageDescription),
          ),
          const SizedBox(height: 8),
          TextField(
            key: const Key('damage-cost'),
            controller: cost,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: InputDecoration(labelText: t.damageCost(widget.currency)),
          ),
          const SizedBox(height: 8),
          OutlinedButton.icon(
            onPressed: () async {
              final p = await ref.read(takePhotoProvider)();
              if (p != null) setState(() => photos.add(p));
            },
            icon: const Icon(Icons.photo_camera),
            label: Text('${t.addPhoto} (${photos.length})'),
          ),
        ],
      ),
      actions: [
        TextButton(
          key: const Key('damage-save'),
          onPressed: () {
            final major = double.tryParse(cost.text.trim().replaceAll(',', '.'));
            if (description.text.trim().isEmpty || major == null || major < 0) return;
            final minor = (major * pow(10, _digits(widget.currency))).round();
            Navigator.pop(context, DamageReport(description.text.trim(), minor, List.of(photos)));
          },
          child: Text(t.addDamage),
        ),
      ],
    );
  }
}
