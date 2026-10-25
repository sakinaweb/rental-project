import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../core/api.dart';
import '../core/session.dart';
import '../core/ui.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});

  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final email = TextEditingController();
  final password = TextEditingController();
  bool busy = false;
  String? error;

  Future<void> _signIn() async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final (token, user) = await ref.read(apiProvider).login(email.text.trim(), password.text);
      final ok = await ref.read(sessionProvider.notifier).signIn(token, user);
      if (!ok && mounted) setState(() => error = context.t.notStaff);
    } on ApiException catch (e) {
      if (mounted) setState(() => error = errorText(context, e));
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = context.t;
    return Scaffold(
      appBar: AppBar(title: Text(t.appTitle)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          TextField(
            key: const Key('email'),
            controller: email,
            keyboardType: TextInputType.emailAddress,
            decoration: InputDecoration(labelText: t.email),
          ),
          const SizedBox(height: 12),
          TextField(
            key: const Key('password'),
            controller: password,
            obscureText: true,
            decoration: InputDecoration(labelText: t.password),
          ),
          const SizedBox(height: 16),
          FilledButton(key: const Key('sign-in'), onPressed: busy ? null : _signIn, child: Text(t.signIn)),
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
