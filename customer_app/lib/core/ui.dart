import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../l10n/app_localizations.dart';
import 'api.dart';
import 'money.dart';
import 'session.dart';

/// Brand indigo, shared with the website, the admin panel and the staff app.
const brand = Color(0xFF4F46E5);

final theme = ThemeData(
  colorScheme: ColorScheme.fromSeed(seedColor: brand, primary: brand, surface: const Color(0xFFFAFAFA)),
  useMaterial3: true,
  appBarTheme: const AppBarTheme(centerTitle: false, scrolledUnderElevation: 0.5),
  cardTheme: CardThemeData(
    elevation: 0,
    clipBehavior: Clip.antiAlias,
    margin: const EdgeInsets.only(bottom: 16),
    color: Colors.white,
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(20),
      side: const BorderSide(color: Color(0xFFE4E4E7)),
    ),
  ),
  filledButtonTheme: FilledButtonThemeData(
    style: FilledButton.styleFrom(
      minimumSize: const Size(0, 52),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      textStyle: const TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
    ),
  ),
  inputDecorationTheme: InputDecorationTheme(
    isDense: true,
    filled: true,
    fillColor: Colors.white,
    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
    enabledBorder: OutlineInputBorder(
      borderRadius: BorderRadius.circular(12),
      borderSide: const BorderSide(color: Color(0xFFE4E4E7)),
    ),
  ),
  chipTheme: ChipThemeData(shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20))),
);

/// The model's photo, or a bike/car icon on the brand gradient when there is none (or it fails to load).
class VehicleImage extends StatelessWidget {
  const VehicleImage(this.vehicle, {super.key, this.height = 170});
  final Vehicle vehicle;
  final double height;

  @override
  Widget build(BuildContext context) {
    final fallback = DecoratedBox(
      decoration: const BoxDecoration(
        gradient: LinearGradient(colors: [Color(0xFFEEF2FF), Colors.white, Color(0xFFFFFBEB)], begin: Alignment.topLeft, end: Alignment.bottomRight),
      ),
      child: Center(
        child: Icon(vehicle.type == 'bike' ? Icons.two_wheeler_rounded : Icons.directions_car_filled_rounded, size: height * 0.5, color: brand),
      ),
    );
    final url = vehicle.imageUrl;
    return SizedBox(
      height: height,
      width: double.infinity,
      child: url == null ? fallback : Image.network(url, fit: BoxFit.cover, errorBuilder: (_, _, _) => fallback),
    );
  }
}

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
