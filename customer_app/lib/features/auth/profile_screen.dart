import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/api.dart';
import '../../core/config.dart';
import '../../core/session.dart';
import '../../core/ui.dart';
import '../search/search_providers.dart';

/// Profile (PATCH /me: name, phone, country, language) and sign-out.
class ProfileScreen extends ConsumerStatefulWidget {
  const ProfileScreen({super.key});

  @override
  ConsumerState<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends ConsumerState<ProfileScreen> {
  late final user = ref.read(sessionProvider).user!;
  late final name = TextEditingController(text: user.name);
  late final phone = TextEditingController(text: user.phone ?? '');
  late int? countryId = user.countryId;
  late String locale = user.locale;
  bool busy = false;
  ApiException? error;

  Future<void> _save() async {
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final updated = await ref.read(apiProvider).updateMe({
        'name': name.text.trim(),
        'phone': phone.text.trim().isEmpty ? null : phone.text.trim(),
        'country_id': countryId,
        'locale': locale,
      });
      ref.read(sessionProvider.notifier).updateUser(updated);
      if (mounted) snack(context, context.t.profileSaved);
    } on ApiException catch (e) {
      if (mounted) setState(() => error = e);
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = context.t;

    return Scaffold(
      appBar: AppBar(title: Text(t.navProfile)),
      body: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          Text(user.email, style: Theme.of(context).textTheme.bodyLarge),
          const SizedBox(height: 16),
          TextField(
            key: const Key('name'),
            controller: name,
            decoration: InputDecoration(labelText: t.authName, errorText: error?.field('name')),
          ),
          const SizedBox(height: 12),
          TextField(
            key: const Key('phone'),
            controller: phone,
            keyboardType: TextInputType.phone,
            decoration: InputDecoration(labelText: t.authPhone, helperText: t.authPhoneHint, errorText: error?.field('phone')),
          ),
          const SizedBox(height: 12),
          ref
              .watch(countriesProvider)
              .maybeWhen(
                data: (countries) => DropdownButtonFormField<int?>(
                  isExpanded: true,
                  initialValue: countryId,
                  decoration: InputDecoration(labelText: t.searchCountry),
                  items: [
                    const DropdownMenuItem(value: null, child: Text('—')),
                    for (final c in countries) DropdownMenuItem(value: c.id, child: Text(c.name)),
                  ],
                  onChanged: (id) => countryId = id,
                ),
                orElse: () => const SizedBox.shrink(),
              ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            isExpanded: true,
            key: const Key('locale'),
            initialValue: locale,
            decoration: InputDecoration(labelText: t.navLanguage),
            items: [for (final e in Config.localeNames.entries) DropdownMenuItem(value: e.key, child: Text(e.value))],
            onChanged: (l) => locale = l ?? locale,
          ),
          if (error != null && error!.errors.isEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 12),
              child: Text(errorText(context, error!), style: const TextStyle(color: Colors.red)),
            ),
          const SizedBox(height: 16),
          FilledButton(key: const Key('save'), onPressed: busy ? null : _save, child: Text(t.profileSave)),
          const SizedBox(height: 32),
          OutlinedButton(
            key: const Key('logout'),
            onPressed: () async {
              await ref.read(sessionProvider.notifier).signOut();
              if (context.mounted) context.go('/');
            },
            child: Text(t.navLogout),
          ),
        ],
      ),
    );
  }
}
