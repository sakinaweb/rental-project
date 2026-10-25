// ignore: unused_import
import 'package:intl/intl.dart' as intl;

import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class L10nEn extends L10n {
  L10nEn([String locale = 'en']) : super(locale);

  @override
  String get appTitle => 'Staff';

  @override
  String get signIn => 'Sign in';

  @override
  String get email => 'Email';

  @override
  String get password => 'Password';

  @override
  String get signOut => 'Sign out';

  @override
  String get notStaff =>
      'This app is for staff accounts. Sign in with your staff account.';

  @override
  String get errorNetwork => 'Can\'t reach the server. Check your connection.';

  @override
  String get retry => 'Try again';

  @override
  String get today => 'Today';

  @override
  String get pickups => 'Pickups';

  @override
  String get returns => 'Returns';

  @override
  String get noPickups => 'No pickups due.';

  @override
  String get noReturns => 'No vehicles out.';

  @override
  String get late => 'Late';

  @override
  String due(String time) {
    return 'Due $time';
  }

  @override
  String booking(String id) {
    return 'Booking #$id';
  }

  @override
  String get customer => 'Customer';

  @override
  String get vehicle => 'Vehicle';

  @override
  String get period => 'Period';

  @override
  String currentOdometer(String km) {
    return 'Odometer now: $km';
  }

  @override
  String get handOver => 'Hand over';

  @override
  String get receive => 'Receive return';

  @override
  String get handoverTitle => 'Handover checklist';

  @override
  String get returnTitle => 'Return checklist';

  @override
  String get photosHint => 'At least 4 photos: front, back, left and right.';

  @override
  String get addPhoto => 'Take photo';

  @override
  String photoCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n photos',
      one: '1 photo',
    );
    return '$_temp0';
  }

  @override
  String get odometer => 'Odometer';

  @override
  String fuel(String pct) {
    return 'Fuel level: $pct %';
  }

  @override
  String get notes => 'Notes (optional)';

  @override
  String get damages => 'Damage found';

  @override
  String get addDamage => 'Add damage';

  @override
  String get damageDescription => 'What is damaged';

  @override
  String damageCost(String currency) {
    return 'Estimated cost ($currency)';
  }

  @override
  String get damageNote =>
      'The admin reviews damages before anything is charged.';

  @override
  String get remove => 'Remove';

  @override
  String get confirmHandover => 'Confirm handover';

  @override
  String get confirmReturn => 'Confirm return';

  @override
  String get needPhotos => 'Take at least 4 photos.';

  @override
  String get invalidNumber => 'Enter a whole number.';

  @override
  String get done => 'Saved.';

  @override
  String get checklists => 'Checklists';

  @override
  String get stagePickup => 'Handover';

  @override
  String get stageReturn => 'Return';

  @override
  String by(String name) {
    return 'by $name';
  }

  @override
  String status(String status) {
    return 'Status: $status';
  }
}
