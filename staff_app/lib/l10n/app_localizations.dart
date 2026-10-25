import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_ar.dart';
import 'app_localizations_de.dart';
import 'app_localizations_en.dart';
import 'app_localizations_es.dart';
import 'app_localizations_hi.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of L10n
/// returned by `L10n.of(context)`.
///
/// Applications need to include `L10n.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'l10n/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: L10n.localizationsDelegates,
///   supportedLocales: L10n.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the L10n.supportedLocales
/// property.
abstract class L10n {
  L10n(String locale)
    : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static L10n of(BuildContext context) {
    return Localizations.of<L10n>(context, L10n)!;
  }

  static const LocalizationsDelegate<L10n> delegate = _L10nDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates =
      <LocalizationsDelegate<dynamic>>[
        delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
      ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[
    Locale('ar'),
    Locale('de'),
    Locale('en'),
    Locale('es'),
    Locale('hi'),
  ];

  /// No description provided for @appTitle.
  ///
  /// In en, this message translates to:
  /// **'Staff'**
  String get appTitle;

  /// No description provided for @signIn.
  ///
  /// In en, this message translates to:
  /// **'Sign in'**
  String get signIn;

  /// No description provided for @email.
  ///
  /// In en, this message translates to:
  /// **'Email'**
  String get email;

  /// No description provided for @password.
  ///
  /// In en, this message translates to:
  /// **'Password'**
  String get password;

  /// No description provided for @signOut.
  ///
  /// In en, this message translates to:
  /// **'Sign out'**
  String get signOut;

  /// No description provided for @notStaff.
  ///
  /// In en, this message translates to:
  /// **'This app is for staff accounts. Sign in with your staff account.'**
  String get notStaff;

  /// No description provided for @errorNetwork.
  ///
  /// In en, this message translates to:
  /// **'Can\'t reach the server. Check your connection.'**
  String get errorNetwork;

  /// No description provided for @retry.
  ///
  /// In en, this message translates to:
  /// **'Try again'**
  String get retry;

  /// No description provided for @today.
  ///
  /// In en, this message translates to:
  /// **'Today'**
  String get today;

  /// No description provided for @pickups.
  ///
  /// In en, this message translates to:
  /// **'Pickups'**
  String get pickups;

  /// No description provided for @returns.
  ///
  /// In en, this message translates to:
  /// **'Returns'**
  String get returns;

  /// No description provided for @noPickups.
  ///
  /// In en, this message translates to:
  /// **'No pickups due.'**
  String get noPickups;

  /// No description provided for @noReturns.
  ///
  /// In en, this message translates to:
  /// **'No vehicles out.'**
  String get noReturns;

  /// No description provided for @late.
  ///
  /// In en, this message translates to:
  /// **'Late'**
  String get late;

  /// No description provided for @due.
  ///
  /// In en, this message translates to:
  /// **'Due {time}'**
  String due(String time);

  /// No description provided for @booking.
  ///
  /// In en, this message translates to:
  /// **'Booking #{id}'**
  String booking(String id);

  /// No description provided for @customer.
  ///
  /// In en, this message translates to:
  /// **'Customer'**
  String get customer;

  /// No description provided for @vehicle.
  ///
  /// In en, this message translates to:
  /// **'Vehicle'**
  String get vehicle;

  /// No description provided for @period.
  ///
  /// In en, this message translates to:
  /// **'Period'**
  String get period;

  /// No description provided for @currentOdometer.
  ///
  /// In en, this message translates to:
  /// **'Odometer now: {km}'**
  String currentOdometer(String km);

  /// No description provided for @handOver.
  ///
  /// In en, this message translates to:
  /// **'Hand over'**
  String get handOver;

  /// No description provided for @receive.
  ///
  /// In en, this message translates to:
  /// **'Receive return'**
  String get receive;

  /// No description provided for @handoverTitle.
  ///
  /// In en, this message translates to:
  /// **'Handover checklist'**
  String get handoverTitle;

  /// No description provided for @returnTitle.
  ///
  /// In en, this message translates to:
  /// **'Return checklist'**
  String get returnTitle;

  /// No description provided for @photosHint.
  ///
  /// In en, this message translates to:
  /// **'At least 4 photos: front, back, left and right.'**
  String get photosHint;

  /// No description provided for @addPhoto.
  ///
  /// In en, this message translates to:
  /// **'Take photo'**
  String get addPhoto;

  /// No description provided for @photoCount.
  ///
  /// In en, this message translates to:
  /// **'{n, plural, =1{1 photo} other{{n} photos}}'**
  String photoCount(int n);

  /// No description provided for @odometer.
  ///
  /// In en, this message translates to:
  /// **'Odometer'**
  String get odometer;

  /// No description provided for @fuel.
  ///
  /// In en, this message translates to:
  /// **'Fuel level: {pct} %'**
  String fuel(String pct);

  /// No description provided for @notes.
  ///
  /// In en, this message translates to:
  /// **'Notes (optional)'**
  String get notes;

  /// No description provided for @damages.
  ///
  /// In en, this message translates to:
  /// **'Damage found'**
  String get damages;

  /// No description provided for @addDamage.
  ///
  /// In en, this message translates to:
  /// **'Add damage'**
  String get addDamage;

  /// No description provided for @damageDescription.
  ///
  /// In en, this message translates to:
  /// **'What is damaged'**
  String get damageDescription;

  /// No description provided for @damageCost.
  ///
  /// In en, this message translates to:
  /// **'Estimated cost ({currency})'**
  String damageCost(String currency);

  /// No description provided for @damageNote.
  ///
  /// In en, this message translates to:
  /// **'The admin reviews damages before anything is charged.'**
  String get damageNote;

  /// No description provided for @remove.
  ///
  /// In en, this message translates to:
  /// **'Remove'**
  String get remove;

  /// No description provided for @confirmHandover.
  ///
  /// In en, this message translates to:
  /// **'Confirm handover'**
  String get confirmHandover;

  /// No description provided for @confirmReturn.
  ///
  /// In en, this message translates to:
  /// **'Confirm return'**
  String get confirmReturn;

  /// No description provided for @needPhotos.
  ///
  /// In en, this message translates to:
  /// **'Take at least 4 photos.'**
  String get needPhotos;

  /// No description provided for @invalidNumber.
  ///
  /// In en, this message translates to:
  /// **'Enter a whole number.'**
  String get invalidNumber;

  /// No description provided for @done.
  ///
  /// In en, this message translates to:
  /// **'Saved.'**
  String get done;

  /// No description provided for @checklists.
  ///
  /// In en, this message translates to:
  /// **'Checklists'**
  String get checklists;

  /// No description provided for @stagePickup.
  ///
  /// In en, this message translates to:
  /// **'Handover'**
  String get stagePickup;

  /// No description provided for @stageReturn.
  ///
  /// In en, this message translates to:
  /// **'Return'**
  String get stageReturn;

  /// No description provided for @by.
  ///
  /// In en, this message translates to:
  /// **'by {name}'**
  String by(String name);

  /// No description provided for @status.
  ///
  /// In en, this message translates to:
  /// **'Status: {status}'**
  String status(String status);
}

class _L10nDelegate extends LocalizationsDelegate<L10n> {
  const _L10nDelegate();

  @override
  Future<L10n> load(Locale locale) {
    return SynchronousFuture<L10n>(lookupL10n(locale));
  }

  @override
  bool isSupported(Locale locale) =>
      <String>['ar', 'de', 'en', 'es', 'hi'].contains(locale.languageCode);

  @override
  bool shouldReload(_L10nDelegate old) => false;
}

L10n lookupL10n(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'ar':
      return L10nAr();
    case 'de':
      return L10nDe();
    case 'en':
      return L10nEn();
    case 'es':
      return L10nEs();
    case 'hi':
      return L10nHi();
  }

  throw FlutterError(
    'L10n.delegate failed to load unsupported locale "$locale". This is likely '
    'an issue with the localizations generation tool. Please file an issue '
    'on GitHub with a reproducible sample app and the gen-l10n configuration '
    'that was used.',
  );
}
