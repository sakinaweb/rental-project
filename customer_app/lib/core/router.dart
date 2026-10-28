import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../features/auth/login_screen.dart';
import '../features/auth/profile_screen.dart';
import '../features/auth/register_screen.dart';
import '../features/booking/booking_detail_screen.dart';
import '../features/booking/bookings_screen.dart';
import '../features/booking/checkout_screen.dart';
import '../features/booking/confirmation_screen.dart';
import '../features/search/search_screen.dart';
import '../features/search/vehicle_screen.dart';
import '../features/investor/investor_screen.dart';
import '../features/wallet/wallet_screen.dart';
import 'session.dart';
import 'ui.dart';

/// Screens that need a signed-in customer; guests are sent to sign-in and brought back afterwards.
const _protected = ['/bookings', '/wallet', '/profile', '/checkout', '/confirmation', '/investor'];

final routerProvider = Provider<GoRouter>((ref) {
  final refresh = ValueNotifier(0);
  ref.listen(sessionProvider, (_, _) => refresh.value++);
  ref.onDispose(refresh.dispose);

  return GoRouter(
    refreshListenable: refresh,
    redirect: (context, state) {
      final session = ref.read(sessionProvider);
      final path = state.uri.path;
      if (!session.signedIn && _protected.any(path.startsWith)) {
        return Uri(path: '/login', queryParameters: {'from': state.uri.toString()}).toString();
      }
      if (session.signedIn && (path == '/login' || path == '/register')) {
        return state.uri.queryParameters['from'] ?? '/';
      }
      return null;
    },
    routes: [
      StatefulShellRoute.indexedStack(
        builder: (context, state, shell) => _Tabs(shell),
        branches: [
          StatefulShellBranch(
            routes: [GoRoute(path: '/', builder: (_, _) => const SearchScreen())],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/bookings',
                builder: (_, _) => const BookingsScreen(),
                routes: [GoRoute(path: ':id', builder: (_, s) => BookingDetailScreen(int.parse(s.pathParameters['id']!)))],
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [GoRoute(path: '/wallet', builder: (_, _) => const WalletScreen())],
          ),
          StatefulShellBranch(
            routes: [GoRoute(path: '/profile', builder: (_, _) => const ProfileScreen())],
          ),
        ],
      ),
      GoRoute(path: '/vehicles/:id', builder: (_, s) => VehicleScreen(int.parse(s.pathParameters['id']!), s.uri.queryParameters)),
      GoRoute(
        path: '/login',
        builder: (_, s) => LoginScreen(from: s.uri.queryParameters['from']),
      ),
      GoRoute(
        path: '/register',
        builder: (_, s) => RegisterScreen(from: s.uri.queryParameters['from']),
      ),
      GoRoute(path: '/checkout/:id', builder: (_, s) => CheckoutScreen(int.parse(s.pathParameters['id']!))),
      GoRoute(path: '/confirmation/:id', builder: (_, s) => ConfirmationScreen(int.parse(s.pathParameters['id']!))),
      GoRoute(path: '/investor', builder: (_, _) => const InvestorScreen()),
    ],
  );
});

class _Tabs extends StatelessWidget {
  const _Tabs(this.shell);
  final StatefulNavigationShell shell;

  @override
  Widget build(BuildContext context) => Scaffold(
    body: shell,
    bottomNavigationBar: NavigationBar(
      selectedIndex: shell.currentIndex,
      onDestinationSelected: (i) => shell.goBranch(i, initialLocation: i == shell.currentIndex),
      destinations: [
        NavigationDestination(icon: const Icon(Icons.search), label: context.t.navSearch),
        NavigationDestination(icon: const Icon(Icons.event_note), label: context.t.navMyBookings),
        NavigationDestination(icon: const Icon(Icons.account_balance_wallet_outlined), label: context.t.navWallet),
        NavigationDestination(icon: const Icon(Icons.person_outline), label: context.t.navProfile),
      ],
    ),
  );
}
