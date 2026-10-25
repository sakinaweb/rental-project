import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../l10n/app_localizations.dart';
import 'api.dart';

/// Brand indigo, shared with the website, the admin panel and the customer app.
const brand = Color(0xFF4F46E5);

final theme = ThemeData(
  colorScheme: ColorScheme.fromSeed(seedColor: brand, primary: brand, surface: const Color(0xFFFAFAFA)),
  useMaterial3: true,
  appBarTheme: const AppBarTheme(centerTitle: false, scrolledUnderElevation: 0.5),
  cardTheme: CardThemeData(
    elevation: 0,
    clipBehavior: Clip.antiAlias,
    color: Colors.white,
    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16), side: const BorderSide(color: Color(0xFFE4E4E7))),
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
    enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: Color(0xFFE4E4E7))),
  ),
);

extension Ctx on BuildContext {
  L10n get t => L10n.of(this);
}

String dateTime(DateTime d) => DateFormat('d MMM, HH:mm').format(d);

String errorText(BuildContext context, Object e) =>
    e is ApiException && !e.isNetwork && e.message.isNotEmpty ? e.message : context.t.errorNetwork;

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

void snack(BuildContext context, String text) => ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
