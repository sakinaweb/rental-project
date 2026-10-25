import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/router.dart';
import 'core/session.dart';
import 'core/ui.dart';
import 'l10n/app_localizations.dart';

// Staff app (MD §7.10): the branch's pickups and returns, handover and return checklists with photos.
// No automatic retries: a failed request shows its error and a "Try again" button.
void main() => runApp(ProviderScope(retry: (_, _) => null, child: const StaffApp()));

class StaffApp extends ConsumerStatefulWidget {
  const StaffApp({super.key});

  @override
  ConsumerState<StaffApp> createState() => _StaffAppState();
}

class _StaffAppState extends ConsumerState<StaffApp> {
  @override
  void initState() {
    super.initState();
    ref.read(sessionProvider.notifier).restore();
  }

  @override
  Widget build(BuildContext context) {
    if (!ref.watch(sessionProvider.select((s) => s.ready))) {
      return MaterialApp(
        theme: theme,
        home: const Scaffold(body: Center(child: CircularProgressIndicator())),
      );
    }
    return MaterialApp.router(
      onGenerateTitle: (c) => c.t.appTitle,
      theme: theme,
      routerConfig: ref.watch(routerProvider),
      localizationsDelegates: const [
        L10n.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      supportedLocales: L10n.supportedLocales,
      // The account's language (set by the admin) once signed in, like the server's messages; the phone's before.
      locale: switch (ref.watch(sessionProvider.select((s) => s.user?.locale))) {
        final String code when L10n.supportedLocales.any((l) => l.languageCode == code) => Locale(code),
        _ => null,
      },
    );
  }
}
