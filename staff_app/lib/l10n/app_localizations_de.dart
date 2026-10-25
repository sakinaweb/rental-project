// ignore: unused_import
import 'package:intl/intl.dart' as intl;

import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for German (`de`).
class L10nDe extends L10n {
  L10nDe([String locale = 'de']) : super(locale);

  @override
  String get appTitle => 'Mitarbeiter';

  @override
  String get signIn => 'Anmelden';

  @override
  String get email => 'E-Mail';

  @override
  String get password => 'Passwort';

  @override
  String get signOut => 'Abmelden';

  @override
  String get notStaff =>
      'Diese App ist für Mitarbeiterkonten. Melde dich mit deinem Mitarbeiterkonto an.';

  @override
  String get errorNetwork => 'Server nicht erreichbar. Prüfe deine Verbindung.';

  @override
  String get retry => 'Erneut versuchen';

  @override
  String get today => 'Heute';

  @override
  String get pickups => 'Übergaben';

  @override
  String get returns => 'Rückgaben';

  @override
  String get noPickups => 'Keine Übergaben fällig.';

  @override
  String get noReturns => 'Keine Fahrzeuge unterwegs.';

  @override
  String get late => 'Verspätet';

  @override
  String due(String time) {
    return 'Fällig $time';
  }

  @override
  String booking(String id) {
    return 'Buchung #$id';
  }

  @override
  String get customer => 'Kunde';

  @override
  String get vehicle => 'Fahrzeug';

  @override
  String get period => 'Zeitraum';

  @override
  String currentOdometer(String km) {
    return 'Kilometerstand jetzt: $km';
  }

  @override
  String get handOver => 'Übergeben';

  @override
  String get receive => 'Rückgabe annehmen';

  @override
  String get handoverTitle => 'Übergabe-Checkliste';

  @override
  String get returnTitle => 'Rückgabe-Checkliste';

  @override
  String get photosHint =>
      'Mindestens 4 Fotos: vorne, hinten, links und rechts.';

  @override
  String get addPhoto => 'Foto aufnehmen';

  @override
  String photoCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n Fotos',
      one: '1 Foto',
    );
    return '$_temp0';
  }

  @override
  String get odometer => 'Kilometerstand';

  @override
  String fuel(String pct) {
    return 'Tankfüllung: $pct %';
  }

  @override
  String get notes => 'Notizen (optional)';

  @override
  String get damages => 'Gefundene Schäden';

  @override
  String get addDamage => 'Schaden hinzufügen';

  @override
  String get damageDescription => 'Was ist beschädigt';

  @override
  String damageCost(String currency) {
    return 'Geschätzte Kosten ($currency)';
  }

  @override
  String get damageNote =>
      'Der Admin prüft Schäden, bevor etwas berechnet wird.';

  @override
  String get remove => 'Entfernen';

  @override
  String get confirmHandover => 'Übergabe bestätigen';

  @override
  String get confirmReturn => 'Rückgabe bestätigen';

  @override
  String get needPhotos => 'Nimm mindestens 4 Fotos auf.';

  @override
  String get invalidNumber => 'Gib eine ganze Zahl ein.';

  @override
  String get done => 'Gespeichert.';

  @override
  String get checklists => 'Checklisten';

  @override
  String get stagePickup => 'Übergabe';

  @override
  String get stageReturn => 'Rückgabe';

  @override
  String by(String name) {
    return 'von $name';
  }

  @override
  String status(String status) {
    return 'Status: $status';
  }
}
