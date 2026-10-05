import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../l10n/app_localizations.dart';
import 'api.dart';
import 'money.dart';
import 'session.dart';

final theme = ThemeData(
  colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF18181B)),
  useMaterial3: true,
  inputDecorationTheme: const InputDecorationTheme(border: OutlineInputBorder(), isDense: true),
);

extension Ctx on BuildContext {
  L10n get t => L10n.of(this);
}

/// Money in the session language.
String money(WidgetRef ref, int minor, String currency) => formatMoney(minor, currency, ref.watch(sessionProvider).locale);

String dateTime(WidgetRef ref, DateTime t) => DateFormat.yMMMd(ref.watch(sessionProvider).locale).add_Hm().format(t);

/// Message for an error from the API (or "no connection").
String errorText(BuildContext context, Object error) =>
    error is ApiException && !error.isNetwork && error.message.isNotEmpty ? error.message : context.t.errorNetwork;

/// Loading / error-with-retry / data for an AsyncValue (the app's standard states).
class AsyncView<T> extends StatelessWidget {
  const AsyncView(this.value, {super.key, required this.data, this.onRetry});

  final AsyncValue<T> value;
  final Widget Function(T data) data;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) => value.when(
    data: data,
    loading: () => const Center(
      child: Padding(padding: EdgeInsets.all(24), child: CircularProgressIndicator()),
    ),
    error: (e, _) => Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(errorText(context, e), textAlign: TextAlign.center),
            if (onRetry != null) TextButton(onPressed: onRetry, child: Text(context.t.retry)),
          ],
        ),
      ),
    ),
  );
}

class Empty extends StatelessWidget {
  const Empty(this.text, {super.key});
  final String text;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(32),
      child: Text(text, textAlign: TextAlign.center),
    ),
  );
}

/// "Label …… value" line of a price breakdown.
class Line extends StatelessWidget {
  const Line(this.label, this.value, {super.key, this.bold = false});
  final String label;
  final String value;
  final bool bold;

  @override
  Widget build(BuildContext context) {
    final style = bold ? const TextStyle(fontWeight: FontWeight.bold) : null;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        children: [
          Expanded(child: Text(label, style: style)),
          Text(value, style: style),
        ],
      ),
    );
  }
}

void snack(BuildContext context, String text) => ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));

/// Date + time picker (local time).
Future<DateTime?> pickDateTime(BuildContext context, DateTime? initial) async {
  final now = DateTime.now();
  final date = await showDatePicker(context: context, firstDate: now, lastDate: now.add(const Duration(days: 730)), initialDate: initial ?? now);
  if (date == null || !context.mounted) return null;
  final time = await showTimePicker(context: context, initialTime: TimeOfDay.fromDateTime(initial ?? now.add(const Duration(hours: 1))));
  if (time == null) return null;
  return DateTime(date.year, date.month, date.day, time.hour, time.minute);
}
