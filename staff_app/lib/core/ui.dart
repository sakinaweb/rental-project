import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../l10n/app_localizations.dart';
import 'api.dart';

/// Brand indigo, shared with the website, the admin panel and the customer app.
const brand = Color(0xFF4F46E5);

/// Near-black ink for text and the dark end of the hero gradient; gold for highlights (prices, badges).
const ink = Color(0xFF0F172A);
const gold = Color(0xFFF59E0B);
const canvas = Color(0xFFF5F6FA);
const muted = Color(0xFF64748B);

/// Deep indigo → violet: headers, hero and primary highlights.
const brandGradient = LinearGradient(colors: [Color(0xFF1E1B4B), Color(0xFF4338CA), Color(0xFF7C3AED)], begin: Alignment.topLeft, end: Alignment.bottomRight);

/// Soft, wide shadow instead of grey outlines.
const softShadow = [BoxShadow(color: Color(0x140F172A), blurRadius: 24, offset: Offset(0, 8))];

final _radius = BorderRadius.circular(16);

final theme = ThemeData(
  useMaterial3: true,
  fontFamily: 'PlusJakartaSans',
  scaffoldBackgroundColor: canvas,
  colorScheme: ColorScheme.fromSeed(seedColor: brand, primary: brand, secondary: gold, surface: canvas, onSurface: ink),
  textTheme: const TextTheme(
    headlineLarge: TextStyle(fontWeight: FontWeight.w800, letterSpacing: -1, color: ink),
    headlineMedium: TextStyle(fontWeight: FontWeight.w800, letterSpacing: -.8, color: ink),
    headlineSmall: TextStyle(fontWeight: FontWeight.w700, letterSpacing: -.5, color: ink),
    titleLarge: TextStyle(fontWeight: FontWeight.w700, letterSpacing: -.3, color: ink),
    titleMedium: TextStyle(fontWeight: FontWeight.w700, color: ink),
    bodySmall: TextStyle(color: muted),
  ),
  appBarTheme: const AppBarTheme(
    centerTitle: false,
    backgroundColor: canvas,
    foregroundColor: ink,
    surfaceTintColor: Colors.transparent,
    scrolledUnderElevation: 0,
    titleTextStyle: TextStyle(fontFamily: 'PlusJakartaSans', fontSize: 24, fontWeight: FontWeight.w800, letterSpacing: -.6, color: ink),
  ),
  cardTheme: CardThemeData(
    elevation: 6,
    shadowColor: const Color(0x260F172A),
    surfaceTintColor: Colors.transparent,
    clipBehavior: Clip.antiAlias,
    margin: const EdgeInsets.only(bottom: 16),
    color: Colors.white,
    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
  ),
  filledButtonTheme: FilledButtonThemeData(
    style: FilledButton.styleFrom(
      minimumSize: const Size(0, 56),
      elevation: 0,
      shape: RoundedRectangleBorder(borderRadius: _radius),
      textStyle: const TextStyle(fontFamily: 'PlusJakartaSans', fontSize: 16, fontWeight: FontWeight.w700, letterSpacing: .2),
    ),
  ),
  outlinedButtonTheme: OutlinedButtonThemeData(
    style: OutlinedButton.styleFrom(
      minimumSize: const Size(0, 52),
      foregroundColor: ink,
      side: const BorderSide(color: Color(0xFFE2E8F0)),
      shape: RoundedRectangleBorder(borderRadius: _radius),
      textStyle: const TextStyle(fontFamily: 'PlusJakartaSans', fontSize: 15, fontWeight: FontWeight.w600),
    ),
  ),
  inputDecorationTheme: InputDecorationTheme(
    filled: true,
    fillColor: Colors.white,
    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
    labelStyle: const TextStyle(color: muted),
    floatingLabelStyle: const TextStyle(color: brand, fontWeight: FontWeight.w600),
    border: OutlineInputBorder(borderRadius: _radius, borderSide: BorderSide.none),
    enabledBorder: OutlineInputBorder(
      borderRadius: _radius,
      borderSide: const BorderSide(color: Color(0xFFE8EAF0)),
    ),
    focusedBorder: OutlineInputBorder(
      borderRadius: _radius,
      borderSide: const BorderSide(color: brand, width: 1.6),
    ),
    errorBorder: OutlineInputBorder(
      borderRadius: _radius,
      borderSide: const BorderSide(color: Color(0xFFDC2626)),
    ),
  ),
  segmentedButtonTheme: SegmentedButtonThemeData(
    style: SegmentedButton.styleFrom(
      backgroundColor: Colors.white,
      selectedBackgroundColor: ink,
      selectedForegroundColor: Colors.white,
      side: const BorderSide(color: Color(0xFFE8EAF0)),
      textStyle: const TextStyle(fontFamily: 'PlusJakartaSans', fontWeight: FontWeight.w600),
    ),
  ),
  navigationBarTheme: NavigationBarThemeData(
    height: 70,
    backgroundColor: Colors.white,
    surfaceTintColor: Colors.transparent,
    elevation: 12,
    shadowColor: const Color(0x330F172A),
    indicatorColor: brand.withValues(alpha: .12),
    iconTheme: WidgetStateProperty.resolveWith((s) => IconThemeData(color: s.contains(WidgetState.selected) ? brand : muted)),
    labelTextStyle: WidgetStateProperty.resolveWith(
      (s) => TextStyle(
        fontFamily: 'PlusJakartaSans',
        fontSize: 12,
        fontWeight: s.contains(WidgetState.selected) ? FontWeight.w700 : FontWeight.w500,
        color: s.contains(WidgetState.selected) ? brand : muted,
      ),
    ),
  ),
  listTileTheme: const ListTileThemeData(contentPadding: EdgeInsets.symmetric(horizontal: 16)),
  dividerTheme: const DividerThemeData(color: Color(0xFFE8EAF0)),
  snackBarTheme: SnackBarThemeData(
    behavior: SnackBarBehavior.floating,
    backgroundColor: ink,
    shape: RoundedRectangleBorder(borderRadius: _radius),
  ),
  chipTheme: ChipThemeData(shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20))),
);

/// Sign-in / create-account layout: gradient header with the logo mark, form on a white sheet.
class AuthScaffold extends StatelessWidget {
  const AuthScaffold({super.key, required this.title, required this.children});
  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: const Color(0xFF1E1B4B),
    body: DecoratedBox(
      decoration: const BoxDecoration(gradient: brandGradient),
      child: ListView(
        padding: EdgeInsets.zero,
        children: [
          Padding(
            padding: EdgeInsets.fromLTRB(16, MediaQuery.paddingOf(context).top + 8, 24, 32),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (Navigator.of(context).canPop()) const BackButton(color: Colors.white) else const SizedBox(height: 48),
                Padding(
                  padding: const EdgeInsetsDirectional.only(start: 8, top: 8),
                  child: Row(
                    children: [
                      Container(
                        width: 52,
                        height: 52,
                        decoration: BoxDecoration(color: const Color(0x26FFFFFF), borderRadius: BorderRadius.circular(16)),
                        child: const Icon(Icons.assignment_turned_in_rounded, color: Colors.white, size: 30),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: Text(title, style: Theme.of(context).textTheme.headlineMedium?.copyWith(color: Colors.white)),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          Container(
            constraints: BoxConstraints(minHeight: MediaQuery.sizeOf(context).height * .7),
            decoration: const BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.vertical(top: Radius.circular(32)),
            ),
            padding: const EdgeInsets.fromLTRB(24, 32, 24, 32),
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: children),
          ),
        ],
      ),
    ),
  );
}

extension Ctx on BuildContext {
  L10n get t => L10n.of(this);
}

String dateTime(DateTime d) => DateFormat('d MMM, HH:mm').format(d);

String errorText(BuildContext context, Object e) => e is ApiException && !e.isNetwork && e.message.isNotEmpty ? e.message : context.t.errorNetwork;

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
