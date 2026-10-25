import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:staff_app/core/config.dart';

import 'helpers.dart';

void main() {
  test('checklist photos: a dev server\'s localhost URL goes to the API host, real hosts are kept', () {
    final api = Uri.parse(Config.apiBaseUrl);
    expect(mediaUrl('http://localhost:8000/storage/inspections/6/a.jpg'), '${api.scheme}://${api.host}:${api.port}/storage/inspections/6/a.jpg');
    expect(mediaUrl('https://bucket.s3.amazonaws.com/a.jpg'), 'https://bucket.s3.amazonaws.com/a.jpg');
  });

  testWidgets('only staff accounts can sign in; staff land on their branch day', (tester) async {
    final backend = branchBackend()..on('POST /auth/login', (_) => {'token': 'customer-token', 'user': userJson(role: 'customer')});
    await pumpApp(tester, backend);

    await tester.enterText(find.byKey(const Key('email')), 'asha@example.com');
    await tester.enterText(find.byKey(const Key('password')), 'secret123');
    await tester.tap(find.byKey(const Key('sign-in')));
    await settle(tester);
    expect(find.text('This app is for staff accounts. Sign in with your staff account.'), findsOneWidget);

    backend.on('POST /auth/login', (_) => {'token': 'staff-token', 'user': userJson()});
    await tester.tap(find.byKey(const Key('sign-in')));
    await settle(tester);
    expect(find.text('Andheri'), findsOneWidget);
    expect(find.text('Pickups (1)'), findsOneWidget);
    expect(find.text('Returns (1)'), findsOneWidget);
    expect(find.text('Asha Rao · #42'), findsOneWidget);
    expect(find.text('Late'), findsOneWidget); // the pickup started an hour ago
  });

  testWidgets('handover needs 4 photos and an odometer, then uploads the checklist', (tester) async {
    var status = 'confirmed';
    final backend = branchBackend()
      ..on('GET /staff/bookings/42', (_) => {'data': bookingJson(status: status)})
      ..on('POST /staff/bookings/42/pickup', (_) {
        status = 'picked_up';
        return {'data': bookingJson(status: status)};
      });
    await pumpApp(tester, backend, signedIn: true);

    await tester.tap(find.byKey(const Key('booking-42')));
    await settle(tester);
    expect(find.text('Status: confirmed'), findsOneWidget);
    await tester.tap(find.byKey(const Key('hand-over')));
    await settle(tester);

    for (var i = 0; i < 3; i++) {
      await tester.tap(find.byKey(const Key('take-photo')));
      await settle(tester, 5);
    }
    await tester.enterText(find.byKey(const Key('odometer')), '12010');
    await tester.tap(find.byKey(const Key('submit')));
    await settle(tester);
    expect(find.text('Take at least 4 photos.'), findsOneWidget);
    expect(backend.sent('POST /staff/bookings/42/pickup'), isEmpty);

    await tester.tap(find.byKey(const Key('take-photo')));
    await settle(tester, 5);
    await tester.tap(find.byKey(const Key('submit')));
    await settle(tester);

    final (fields, files) = form(backend.sent('POST /staff/bookings/42/pickup').single);
    expect(fields, {'odometer': '12010', 'fuel_level': '100'});
    expect(files, [for (var i = 1; i <= 4; i++) ('photos[]', 'photo-$i.jpg')]);
    expect(find.text('Status: picked_up'), findsOneWidget); // back on the booking, reloaded
  });

  testWidgets('return sends damages in minor units with their photos, and shows a refusal', (tester) async {
    var calls = 0;
    var status = 'picked_up';
    final backend = branchBackend()
      ..on('GET /staff/bookings/43', (_) => {'data': bookingJson(id: 43, status: status, currency: 'AED')})
      ..on('POST /staff/bookings/43/return', (_) {
        if (++calls == 1) return (422, {'message': "The return odometer can't be lower than at pickup (12010)."});
        status = 'returned';
        return {'data': bookingJson(id: 43, status: status, currency: 'AED')};
      });
    await pumpApp(tester, backend, signedIn: true);

    await tester.tap(find.text('Returns (1)'));
    await settle(tester);
    await tester.tap(find.byKey(const Key('booking-43')));
    await settle(tester);
    await tester.tap(find.byKey(const Key('receive')));
    await settle(tester);

    for (var i = 0; i < 4; i++) {
      await tester.tap(find.byKey(const Key('take-photo')));
      await settle(tester, 5);
    }
    await tester.enterText(find.byKey(const Key('odometer')), '12005');
    await tester.drag(find.byKey(const Key('fuel')), const Offset(-200, 0)); // lower the fuel level
    await tester.ensureVisible(find.byKey(const Key('add-damage')));
    await tester.tap(find.byKey(const Key('add-damage')));
    await settle(tester);
    await tester.enterText(find.byKey(const Key('damage-description')), 'Scratch on rear door');
    await tester.enterText(find.byKey(const Key('damage-cost')), '150.50');
    await tester.tap(find.text('Take photo (0)'));
    await settle(tester, 5);
    await tester.tap(find.byKey(const Key('damage-save')));
    await settle(tester);
    expect(find.text('Scratch on rear door'), findsOneWidget);

    await tester.ensureVisible(find.byKey(const Key('submit')));
    await tester.tap(find.byKey(const Key('submit')));
    await settle(tester);
    expect(find.text("The return odometer can't be lower than at pickup (12010)."), findsOneWidget);

    await tester.enterText(find.byKey(const Key('odometer')), '12250');
    await tester.ensureVisible(find.byKey(const Key('submit')));
    await tester.tap(find.byKey(const Key('submit')));
    await settle(tester);

    final (fields, files) = form(backend.sent('POST /staff/bookings/43/return').last);
    expect(fields['odometer'], '12250');
    expect(int.parse(fields['fuel_level']!), lessThan(100));
    expect(fields['damages[0][description]'], 'Scratch on rear door');
    expect(fields['damages[0][cost]'], '15050'); // AED has 2 decimals
    expect(files.last, ('damages[0][photos][]', 'photo-5.jpg'));
    expect(files.where((f) => f.$1 == 'photos[]'), hasLength(4));
    expect(find.text('Status: returned'), findsOneWidget);
  });

  testWidgets('a stored token of a non-staff account is not kept', (tester) async {
    final backend = branchBackend()..on('GET /me', (_) => {'data': userJson(role: 'customer')});
    await pumpApp(tester, backend, signedIn: true);

    expect(find.byKey(const Key('sign-in')), findsOneWidget);
    expect(backend.sent('GET /staff/bookings'), isEmpty);
  });

  testWidgets("the app speaks the staff account's language: Arabic is translated and right-to-left", (tester) async {
    final backend = branchBackend()..on('POST /auth/login', (_) => {'token': 'staff-token', 'user': userJson(locale: 'ar')});
    await pumpApp(tester, backend);

    await tester.enterText(find.byKey(const Key('email')), 'ravi@example.com');
    await tester.enterText(find.byKey(const Key('password')), 'secret123');
    await tester.tap(find.byKey(const Key('sign-in')));
    await settle(tester);

    expect(find.text('الاستلامات (1)'), findsOneWidget);
    expect(find.text('الإرجاعات (1)'), findsOneWidget);
    expect(Directionality.of(tester.element(find.text('الاستلامات (1)'))), TextDirection.rtl);
  });
}
