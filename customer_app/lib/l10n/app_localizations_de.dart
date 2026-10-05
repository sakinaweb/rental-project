// ignore: unused_import
import 'package:intl/intl.dart' as intl;

import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for German (`de`).
class L10nDe extends L10n {
  L10nDe([String locale = 'de']) : super(locale);

  @override
  String get navSearch => 'Suche';

  @override
  String get navMyBookings => 'Meine Buchungen';

  @override
  String get navWallet => 'Wallet';

  @override
  String get navProfile => 'Profil';

  @override
  String get navLogin => 'Anmelden';

  @override
  String get navRegister => 'Konto erstellen';

  @override
  String get navLogout => 'Abmelden';

  @override
  String get navLanguage => 'Sprache';

  @override
  String get commonLoading => 'Wird geladen…';

  @override
  String get commonBack => 'Zurück';

  @override
  String get commonOptional => 'optional';

  @override
  String commonTimesNote(String tz) {
    return 'Alle Zeiten werden in $tz angezeigt.';
  }

  @override
  String get searchTitle => 'Motorrad oder Auto finden';

  @override
  String get searchCountry => 'Land';

  @override
  String get searchCity => 'Stadt';

  @override
  String get searchAny => 'Alle';

  @override
  String get searchPickUp => 'Abholung';

  @override
  String get searchReturn => 'Rückgabe';

  @override
  String get searchCategory => 'Kategorie';

  @override
  String get searchType => 'Typ';

  @override
  String get searchBike => 'Motorrad';

  @override
  String get searchCar => 'Auto';

  @override
  String get searchSeats => 'Min. Sitze';

  @override
  String get searchButton => 'Suchen';

  @override
  String get searchNoResults =>
      'Keine Fahrzeuge passen zu Ihrer Suche. Versuchen Sie andere Daten oder Filter.';

  @override
  String get searchDatesInvalid =>
      'Die Rückgabe muss nach der Abholung liegen.';

  @override
  String get searchTotalForDates => 'gesamt für Ihre Daten';

  @override
  String get searchPickDatesForPrice =>
      'Wählen Sie Abholung und Rückgabe, um den Preis zu sehen.';

  @override
  String vehicleSeats(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n Sitze',
      one: '$n Sitz',
    );
    return '$_temp0';
  }

  @override
  String get vehicleSeatsLabel => 'Sitze';

  @override
  String get vehicleTransmission => 'Getriebe';

  @override
  String get vehicleFuel => 'Kraftstoff';

  @override
  String get vehicleBranch => 'Filiale';

  @override
  String get vehicleAvailable => 'In diesem Zeitraum verfügbar';

  @override
  String get vehicleUnavailable => 'In diesem Zeitraum nicht verfügbar';

  @override
  String get vehicleAddons => 'Extras';

  @override
  String get vehicleAddonPerDay => 'pro Tag';

  @override
  String get vehicleAddonPerBooking => 'pro Buchung';

  @override
  String get vehicleQty => 'Menge';

  @override
  String get vehicleCoupon => 'Gutscheincode';

  @override
  String get vehicleQuote => 'Ihr Preis';

  @override
  String get vehicleSubtotal => 'Zwischensumme';

  @override
  String get vehicleDiscounts => 'Rabatte';

  @override
  String get vehicleTax => 'Steuer';

  @override
  String get vehicleTotal => 'Gesamt';

  @override
  String get vehicleDeposit => 'Erstattbare Kaution';

  @override
  String get vehicleDueNow => 'Jetzt zu zahlen';

  @override
  String get vehicleBookNow => 'Jetzt buchen';

  @override
  String get vehicleLoginToBook => 'Zum Buchen anmelden';

  @override
  String get vehiclePriceUnavailable => 'Kein Preis für diesen Zeitraum';

  @override
  String get authSignIn => 'Anmelden';

  @override
  String get authWithPassword => 'Passwort';

  @override
  String get authWithOtp => 'SMS-Code';

  @override
  String get authEmail => 'E-Mail';

  @override
  String get authPassword => 'Passwort';

  @override
  String get authPasswordConfirmation => 'Passwort bestätigen';

  @override
  String get authName => 'Vollständiger Name';

  @override
  String get authPhone => 'Telefon';

  @override
  String get authPhoneHint => 'Internationales Format, z. B. +4915100000000';

  @override
  String get authCode => '6-stelliger Code';

  @override
  String get authSendCode => 'Code senden';

  @override
  String get authVerify => 'Bestätigen und anmelden';

  @override
  String get authNoAccount => 'Neu hier?';

  @override
  String get authHaveAccount => 'Sie haben bereits ein Konto?';

  @override
  String get authCreateAccount => 'Konto erstellen';

  @override
  String get authTooMany =>
      'Zu viele Versuche. Bitte warten Sie eine Minute und versuchen Sie es erneut.';

  @override
  String get checkoutTitle => 'Bezahlung';

  @override
  String get checkoutPay => 'Bezahlen';

  @override
  String get checkoutPayWallet => 'Mit Wallet bezahlen';

  @override
  String get checkoutPayStripe => 'Mit Karte bezahlen (Stripe)';

  @override
  String get checkoutPayRazorpay => 'Mit Razorpay bezahlen';

  @override
  String get checkoutNoCard => 'Kartenzahlung ist derzeit nicht verfügbar.';

  @override
  String checkoutHoldNote(String time) {
    return 'Wir halten dieses Fahrzeug bis $time Uhr für Sie frei.';
  }

  @override
  String get confirmationTitle => 'Buchungsstatus';

  @override
  String get confirmationConfirmed => 'Ihre Buchung ist bestätigt.';

  @override
  String get confirmationWaiting =>
      'Warten auf die Zahlungsbestätigung. Diese Seite aktualisiert sich automatisch.';

  @override
  String get confirmationFailed =>
      'Diese Buchung wurde storniert. Erhaltenes Geld wurde Ihrem Wallet gutgeschrieben.';

  @override
  String get confirmationCardFailed =>
      'Die Kartenzahlung ist nicht durchgegangen. Sie können es erneut versuchen.';

  @override
  String get confirmationViewBooking => 'Buchung ansehen';

  @override
  String bookingTitle(String id) {
    return 'Buchung Nr. $id';
  }

  @override
  String get bookingNone => 'Sie haben noch keine Buchungen.';

  @override
  String get bookingAmountDue => 'Offener Betrag';

  @override
  String get bookingAmountPaid => 'Bezahlt';

  @override
  String get bookingPayBalance => 'Jetzt bezahlen';

  @override
  String get bookingNewEnd => 'Neue Rückgabezeit';

  @override
  String get bookingExtend => 'Buchung verlängern';

  @override
  String get bookingCancel => 'Buchung stornieren';

  @override
  String get bookingCancelConfirm =>
      'Diese Buchung stornieren? Alles, was Sie bezahlt haben, geht zurück in Ihr Wallet.';

  @override
  String get bookingInvoice => 'Rechnung herunterladen (PDF)';

  @override
  String get bookingDepositStatus => 'Kaution';

  @override
  String get bookingCancelledNotice =>
      'Buchung storniert. Die Erstattung ist in Ihrem Wallet.';

  @override
  String get bookingExtendedNotice => 'Buchung verlängert.';

  @override
  String get depositHeld => 'einbehalten';

  @override
  String get depositReleased => 'zurückgegeben';

  @override
  String get statusDraft => 'Zahlung ausstehend';

  @override
  String get statusPendingPayment => 'Zahlung läuft';

  @override
  String get statusConfirmed => 'Bestätigt';

  @override
  String get statusPickedUp => 'In Miete';

  @override
  String get statusReturned => 'Zurückgegeben';

  @override
  String get statusCompleted => 'Abgeschlossen';

  @override
  String get statusSettled => 'Abgerechnet';

  @override
  String get statusCancelled => 'Storniert';

  @override
  String get statusNoShow => 'Nicht erschienen';

  @override
  String get walletBalance => 'Guthaben';

  @override
  String get walletEmpty => 'Ihr Wallet ist leer.';

  @override
  String get walletNoTransactions => 'Noch keine Buchungen im Wallet.';

  @override
  String get walletTopUp => 'Aufladen';

  @override
  String get walletAmount => 'Betrag';

  @override
  String get walletCurrency => 'Währung';

  @override
  String get walletDate => 'Datum';

  @override
  String get walletType => 'Art';

  @override
  String get walletProcessing =>
      'Schließen Sie die Zahlung im geöffneten Fenster ab. Ihr Guthaben wird aktualisiert, sobald der Zahlungsanbieter sie bestätigt.';

  @override
  String get walletAmountInvalid => 'Bitte einen gültigen Betrag eingeben.';

  @override
  String get walletTypeTopup => 'Aufladung';

  @override
  String get walletTypePayment => 'Buchungszahlung';

  @override
  String get walletTypeRefund => 'Erstattung';

  @override
  String get walletTypeDepositRelease => 'Kaution zurückgegeben';

  @override
  String get profileSave => 'Speichern';

  @override
  String get profileSaved => 'Profil gespeichert.';

  @override
  String get draftExpired =>
      'Dieser Buchungsentwurf ist abgelaufen. Bitte starten Sie eine neue Buchung.';

  @override
  String get walletTooLow =>
      'Ihr Wallet-Guthaben reicht für diese Zahlung nicht aus.';

  @override
  String get errorNetwork =>
      'Server nicht erreichbar. Bitte Verbindung prüfen.';

  @override
  String get retry => 'Erneut versuchen';

  @override
  String get paymentNotCompleted => 'Die Zahlung wurde nicht abgeschlossen.';

  @override
  String invoiceSaved(String path) {
    return 'Rechnung gespeichert unter $path';
  }

  @override
  String get signInToContinue => 'Zum Fortfahren anmelden';

  @override
  String get selectDate => 'Datum und Uhrzeit wählen';
}
