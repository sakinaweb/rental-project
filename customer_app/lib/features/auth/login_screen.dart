import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api.dart';
import '../../core/session.dart';
import '../../core/ui.dart';

/// Sign in with password or phone OTP (same endpoints and messages as the website).
class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key, this.from});
  final String? from;

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final email = TextEditingController();
  final password = TextEditingController();
  final phone = TextEditingController();
  final code = TextEditingController();
  bool otp = false;
  bool codeSent = false;
  bool busy = false;
  String? error;
  String? notice;

  Future<void> _run(Future<void> Function() action) async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await action();
    } on ApiException catch (e) {
      if (mounted) setState(() => error = e.errors.values.firstOrNull?.first ?? errorText(context, e));
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> _signIn((String, User) result) async {
    await ref.read(sessionProvider.notifier).signIn(result.$1, result.$2);
    if (mounted) context.go(widget.from ?? '/');
  }

  @override
  Widget build(BuildContext context) {
    final t = context.t;
    final api = ref.read(apiProvider);

    return Scaffold(
      appBar: AppBar(title: Text(t.authSignIn)),
      body: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          SegmentedButton<bool>(
            segments: [
              ButtonSegment(value: false, label: Text(t.authWithPassword)),
              ButtonSegment(value: true, label: Text(t.authWithOtp)),
            ],
            selected: {otp},
            onSelectionChanged: (s) => setState(() => otp = s.first),
          ),
          const SizedBox(height: 16),
          if (notice != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Text(notice!, style: const TextStyle(color: Colors.green)),
            ),
          if (!otp) ...[
            TextField(
              key: const Key('email'),
              controller: email,
              keyboardType: TextInputType.emailAddress,
              decoration: InputDecoration(labelText: t.authEmail),
            ),
            const SizedBox(height: 12),
            TextField(
              key: const Key('password'),
              controller: password,
              obscureText: true,
              decoration: InputDecoration(labelText: t.authPassword),
            ),
          ] else ...[
            TextField(
              key: const Key('phone'),
              controller: phone,
              keyboardType: TextInputType.phone,
              decoration: InputDecoration(labelText: t.authPhone, helperText: t.authPhoneHint),
            ),
            if (codeSent) ...[
              const SizedBox(height: 12),
              TextField(
                key: const Key('code'),
                controller: code,
                keyboardType: TextInputType.number,
                maxLength: 6,
                decoration: InputDecoration(labelText: t.authCode),
              ),
            ],
          ],
          if (error != null)
            Padding(
              padding: const EdgeInsets.only(top: 12),
              child: Text(error!, style: const TextStyle(color: Colors.red)),
            ),
          const SizedBox(height: 16),
          FilledButton(
            key: const Key('submit'),
            onPressed: busy
                ? null
                : () => _run(() async {
                    if (!otp) return _signIn(await api.login(email.text.trim(), password.text));
                    if (!codeSent) {
                      final message = await api.requestOtp(phone.text.trim()); // same answer whether or not registered
                      setState(() {
                        codeSent = true;
                        notice = message;
                      });
                      return;
                    }
                    return _signIn(await api.verifyOtp(phone.text.trim(), code.text.trim()));
                  }),
            child: Text(!otp ? t.authSignIn : (codeSent ? t.authVerify : t.authSendCode)),
          ),
          const SizedBox(height: 24),
          TextButton(
            onPressed: () => context.go(Uri(path: '/register', queryParameters: {'from': ?widget.from}).toString()),
            child: Text('${t.authNoAccount} ${t.authCreateAccount}'),
          ),
        ],
      ),
    );
  }
}
