import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api.dart';
import '../../core/session.dart';
import '../../core/ui.dart';
import '../search/search_providers.dart';

/// Registration with POST /auth/register (field errors come from the API's RegisterRequest rules).
class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({super.key, this.from});
  final String? from;

  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  final fields = {
    for (final f in ['name', 'email', 'phone', 'password', 'password_confirmation']) f: TextEditingController(),
  };
  int? countryId;
  bool busy = false;
  ApiException? error;

  Future<void> _submit() async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final (token, user) = await ref.read(apiProvider).register({
        for (final e in fields.entries)
          if (e.value.text.trim().isNotEmpty) e.key: e.value.text.trim(),
        'country_id': ?countryId,
        'locale': ref.read(sessionProvider).locale,
      });
      await ref.read(sessionProvider.notifier).signIn(token, user);
      if (mounted) context.go(widget.from ?? '/');
    } on ApiException catch (e) {
      if (mounted) setState(() => error = e);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = context.t;
    final labels = {
      'name': t.authName,
      'email': t.authEmail,
      'phone': '${t.authPhone} (${t.commonOptional})',
      'password': t.authPassword,
      'password_confirmation': t.authPasswordConfirmation,
    };

    return AuthScaffold(
      title: t.authCreateAccount,
      children: [
        for (final e in fields.entries) ...[
          TextField(
            key: Key(e.key),
            controller: e.value,
            obscureText: e.key.startsWith('password'),
            keyboardType: e.key == 'email' ? TextInputType.emailAddress : (e.key == 'phone' ? TextInputType.phone : null),
            decoration: InputDecoration(labelText: labels[e.key], helperText: e.key == 'phone' ? t.authPhoneHint : null, errorText: error?.field(e.key)),
          ),
          const SizedBox(height: 12),
        ],
        ref
            .watch(countriesProvider)
            .maybeWhen(
              data: (countries) => DropdownButtonFormField<int?>(
                isExpanded: true,
                initialValue: countryId,
                decoration: InputDecoration(labelText: t.searchCountry, errorText: error?.field('country_id')),
                items: [
                  const DropdownMenuItem(value: null, child: Text('—')),
                  for (final c in countries) DropdownMenuItem(value: c.id, child: Text(c.name)),
                ],
                onChanged: (id) => countryId = id,
              ),
              orElse: () => const SizedBox.shrink(),
            ),
        if (error != null && error!.errors.isEmpty)
          Padding(
            padding: const EdgeInsets.only(top: 12),
            child: Text(errorText(context, error!), style: const TextStyle(color: Colors.red)),
          ),
        const SizedBox(height: 16),
        FilledButton(key: const Key('submit'), onPressed: busy ? null : _submit, child: Text(t.authCreateAccount)),
        TextButton(
          onPressed: () => context.go(Uri(path: '/login', queryParameters: {'from': ?widget.from}).toString()),
          child: Text('${t.authHaveAccount} ${t.authSignIn}'),
        ),
      ],
    );
  }
}
