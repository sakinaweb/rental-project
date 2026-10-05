import 'dart:ui';

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/push.dart';
import 'core/router.dart';
import 'core/session.dart';
import 'core/ui.dart';
import 'l10n/app_localizations.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Push.init();
  runApp(ProviderScope(retry: (_, _) => null, child: const RentalApp()));
}

class RentalApp extends ConsumerStatefulWidget {
  const RentalApp({super.key});

  @override
  ConsumerState<RentalApp> createState() => _RentalAppState();
}

class _RentalAppState extends ConsumerState<RentalApp> {
  @override
  void initState() {
    super.initState();
    ref.read(sessionProvider.notifier).restore(PlatformDispatcher.instance.locale.languageCode);
    // A tapped booking notification opens that booking.
    Push.openedBookings().listen((id) => ref.read(routerProvider).push('/bookings/$id'));
  }

  @override
  Widget build(BuildContext context) {
    final session = ref.watch(sessionProvider);

    return MaterialApp.router(
      onGenerateTitle: (context) => context.t.searchTitle,
      theme: theme,
      locale: Locale(session.locale),
      supportedLocales: L10n.supportedLocales,
      localizationsDelegates: const [
        L10n.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      routerConfig: ref.watch(routerProvider),
      builder: (context, child) => session.ready ? child! : const Scaffold(body: Center(child: CircularProgressIndicator())),
    );
  }
}
