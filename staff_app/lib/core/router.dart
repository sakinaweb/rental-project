import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../features/booking_screen.dart';
import '../features/checklist_screen.dart';
import '../features/login_screen.dart';
import '../features/today_screen.dart';
import 'session.dart';

/// Today → booking → checklist are pushed, so Back always returns one step; everything needs a staff session.
GoRouter createRouter(Ref ref, {String initial = '/'}) {
  final refresh = ValueNotifier(0);
  ref.listen(sessionProvider, (_, _) => refresh.value++);

  return GoRouter(
    initialLocation: initial,
    refreshListenable: refresh,
    redirect: (_, state) {
      final signedIn = ref.read(sessionProvider).user != null;
      if (!signedIn && state.matchedLocation != '/login') return '/login';
      if (signedIn && state.matchedLocation == '/login') return '/';
      return null;
    },
    routes: [
      GoRoute(path: '/login', builder: (_, _) => const LoginScreen()),
      GoRoute(
        path: '/',
        builder: (_, _) => const TodayScreen(),
        routes: [
          GoRoute(
            path: 'bookings/:id',
            builder: (_, s) => BookingScreen(int.parse(s.pathParameters['id']!)),
            routes: [
              GoRoute(path: 'pickup', builder: (_, s) => ChecklistScreen(int.parse(s.pathParameters['id']!), returning: false)),
              GoRoute(path: 'return', builder: (_, s) => ChecklistScreen(int.parse(s.pathParameters['id']!), returning: true)),
            ],
          ),
        ],
      ),
    ],
  );
}

final routerProvider = Provider<GoRouter>((ref) => createRouter(ref));
