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

  /// No description provided for @navSearch.
  ///
  /// In en, this message translates to:
  /// **'Search'**
  String get navSearch;

  /// No description provided for @navMyBookings.
  ///
  /// In en, this message translates to:
  /// **'My bookings'**
  String get navMyBookings;

  /// No description provided for @navWallet.
  ///
  /// In en, this message translates to:
  /// **'Wallet'**
  String get navWallet;

  /// No description provided for @navProfile.
  ///
  /// In en, this message translates to:
  /// **'Profile'**
  String get navProfile;

  /// No description provided for @navLogin.
  ///
  /// In en, this message translates to:
  /// **'Sign in'**
  String get navLogin;

  /// No description provided for @navRegister.
  ///
  /// In en, this message translates to:
  /// **'Create account'**
  String get navRegister;

  /// No description provided for @navLogout.
  ///
  /// In en, this message translates to:
  /// **'Sign out'**
  String get navLogout;

  /// No description provided for @navLanguage.
  ///
  /// In en, this message translates to:
  /// **'Language'**
  String get navLanguage;

  /// No description provided for @commonLoading.
  ///
  /// In en, this message translates to:
  /// **'Loading…'**
  String get commonLoading;

  /// No description provided for @commonBack.
  ///
  /// In en, this message translates to:
  /// **'Back'**
  String get commonBack;

  /// No description provided for @commonOptional.
  ///
  /// In en, this message translates to:
  /// **'optional'**
  String get commonOptional;

  /// No description provided for @commonTimesNote.
  ///
  /// In en, this message translates to:
  /// **'All times are shown in {tz}.'**
  String commonTimesNote(String tz);

  /// No description provided for @searchTitle.
  ///
  /// In en, this message translates to:
  /// **'Find a bike or car'**
  String get searchTitle;

  /// No description provided for @searchCountry.
  ///
  /// In en, this message translates to:
  /// **'Country'**
  String get searchCountry;

  /// No description provided for @searchCity.
  ///
  /// In en, this message translates to:
  /// **'City'**
  String get searchCity;

  /// No description provided for @searchAny.
  ///
  /// In en, this message translates to:
  /// **'Any'**
  String get searchAny;

  /// No description provided for @searchPickUp.
  ///
  /// In en, this message translates to:
  /// **'Pick-up'**
  String get searchPickUp;

  /// No description provided for @searchReturn.
  ///
  /// In en, this message translates to:
  /// **'Return'**
  String get searchReturn;

  /// No description provided for @searchCategory.
  ///
  /// In en, this message translates to:
  /// **'Category'**
  String get searchCategory;

  /// No description provided for @searchType.
  ///
  /// In en, this message translates to:
  /// **'Type'**
  String get searchType;

  /// No description provided for @searchBike.
  ///
  /// In en, this message translates to:
  /// **'Bike'**
  String get searchBike;

  /// No description provided for @searchCar.
  ///
  /// In en, this message translates to:
  /// **'Car'**
  String get searchCar;

  /// No description provided for @searchSeats.
  ///
  /// In en, this message translates to:
  /// **'Min. seats'**
  String get searchSeats;

  /// No description provided for @searchButton.
  ///
  /// In en, this message translates to:
  /// **'Search'**
  String get searchButton;

  /// No description provided for @searchNoResults.
  ///
  /// In en, this message translates to:
  /// **'No vehicles match your search. Try other dates or filters.'**
  String get searchNoResults;

  /// No description provided for @searchDatesInvalid.
  ///
  /// In en, this message translates to:
  /// **'Return must be after pick-up.'**
  String get searchDatesInvalid;

  /// No description provided for @searchTotalForDates.
  ///
  /// In en, this message translates to:
  /// **'total for your dates'**
  String get searchTotalForDates;

  /// No description provided for @searchPickDatesForPrice.
  ///
  /// In en, this message translates to:
  /// **'Choose pick-up and return to see the price.'**
  String get searchPickDatesForPrice;

  /// No description provided for @vehicleSeats.
  ///
  /// In en, this message translates to:
  /// **'{n, plural, =1{{n} seat} other{{n} seats}}'**
  String vehicleSeats(int n);

  /// No description provided for @vehicleSeatsLabel.
  ///
  /// In en, this message translates to:
  /// **'Seats'**
  String get vehicleSeatsLabel;

  /// No description provided for @vehicleTransmission.
  ///
  /// In en, this message translates to:
  /// **'Transmission'**
  String get vehicleTransmission;

  /// No description provided for @vehicleFuel.
  ///
  /// In en, this message translates to:
  /// **'Fuel'**
  String get vehicleFuel;

  /// No description provided for @vehicleBranch.
  ///
  /// In en, this message translates to:
  /// **'Branch'**
  String get vehicleBranch;

  /// No description provided for @vehicleAvailable.
  ///
  /// In en, this message translates to:
  /// **'Available for these dates'**
  String get vehicleAvailable;

  /// No description provided for @vehicleUnavailable.
  ///
  /// In en, this message translates to:
  /// **'Not available for these dates'**
  String get vehicleUnavailable;

  /// No description provided for @vehicleAddons.
  ///
  /// In en, this message translates to:
  /// **'Add-ons'**
  String get vehicleAddons;

  /// No description provided for @vehicleAddonPerDay.
  ///
  /// In en, this message translates to:
  /// **'per day'**
  String get vehicleAddonPerDay;

  /// No description provided for @vehicleAddonPerBooking.
  ///
  /// In en, this message translates to:
  /// **'per booking'**
  String get vehicleAddonPerBooking;

  /// No description provided for @vehicleQty.
  ///
  /// In en, this message translates to:
  /// **'Quantity'**
  String get vehicleQty;

  /// No description provided for @vehicleCoupon.
  ///
  /// In en, this message translates to:
  /// **'Coupon code'**
  String get vehicleCoupon;

  /// No description provided for @vehicleQuote.
  ///
  /// In en, this message translates to:
  /// **'Your price'**
  String get vehicleQuote;

  /// No description provided for @vehicleSubtotal.
  ///
  /// In en, this message translates to:
  /// **'Subtotal'**
  String get vehicleSubtotal;

  /// No description provided for @vehicleDiscounts.
  ///
  /// In en, this message translates to:
  /// **'Discounts'**
  String get vehicleDiscounts;

  /// No description provided for @vehicleTax.
  ///
  /// In en, this message translates to:
  /// **'Tax'**
  String get vehicleTax;

  /// No description provided for @vehicleTotal.
  ///
  /// In en, this message translates to:
  /// **'Total'**
  String get vehicleTotal;

  /// No description provided for @vehicleDeposit.
  ///
  /// In en, this message translates to:
  /// **'Refundable deposit'**
  String get vehicleDeposit;

  /// No description provided for @vehicleDueNow.
  ///
  /// In en, this message translates to:
  /// **'To pay now'**
  String get vehicleDueNow;

  /// No description provided for @vehicleBookNow.
  ///
  /// In en, this message translates to:
  /// **'Book now'**
  String get vehicleBookNow;

  /// No description provided for @vehicleLoginToBook.
  ///
  /// In en, this message translates to:
  /// **'Sign in to book'**
  String get vehicleLoginToBook;

  /// No description provided for @vehiclePriceUnavailable.
  ///
  /// In en, this message translates to:
  /// **'No price for these dates'**
  String get vehiclePriceUnavailable;

  /// No description provided for @authSignIn.
  ///
  /// In en, this message translates to:
  /// **'Sign in'**
  String get authSignIn;

  /// No description provided for @authWithPassword.
  ///
  /// In en, this message translates to:
  /// **'Password'**
  String get authWithPassword;

  /// No description provided for @authWithOtp.
  ///
  /// In en, this message translates to:
  /// **'Phone code'**
  String get authWithOtp;

  /// No description provided for @authEmail.
  ///
  /// In en, this message translates to:
  /// **'Email'**
  String get authEmail;

  /// No description provided for @authPassword.
  ///
  /// In en, this message translates to:
  /// **'Password'**
  String get authPassword;

  /// No description provided for @authPasswordConfirmation.
  ///
  /// In en, this message translates to:
  /// **'Confirm password'**
  String get authPasswordConfirmation;

  /// No description provided for @authName.
  ///
  /// In en, this message translates to:
  /// **'Full name'**
  String get authName;

  /// No description provided for @authPhone.
  ///
  /// In en, this message translates to:
  /// **'Phone'**
  String get authPhone;

  /// No description provided for @authPhoneHint.
  ///
  /// In en, this message translates to:
  /// **'International format, e.g. +919900000000'**
  String get authPhoneHint;

  /// No description provided for @authCode.
  ///
  /// In en, this message translates to:
  /// **'6-digit code'**
  String get authCode;

  /// No description provided for @authSendCode.
  ///
  /// In en, this message translates to:
  /// **'Send code'**
  String get authSendCode;

  /// No description provided for @authVerify.
  ///
  /// In en, this message translates to:
  /// **'Verify and sign in'**
  String get authVerify;

  /// No description provided for @authNoAccount.
  ///
  /// In en, this message translates to:
  /// **'New here?'**
  String get authNoAccount;

  /// No description provided for @authHaveAccount.
  ///
  /// In en, this message translates to:
  /// **'Already have an account?'**
  String get authHaveAccount;

  /// No description provided for @authCreateAccount.
  ///
  /// In en, this message translates to:
  /// **'Create account'**
  String get authCreateAccount;

  /// No description provided for @authTooMany.
  ///
  /// In en, this message translates to:
  /// **'Too many attempts. Please wait a minute and try again.'**
  String get authTooMany;

  /// No description provided for @checkoutTitle.
  ///
  /// In en, this message translates to:
  /// **'Checkout'**
  String get checkoutTitle;

  /// No description provided for @checkoutPay.
  ///
  /// In en, this message translates to:
  /// **'Pay'**
  String get checkoutPay;

  /// No description provided for @checkoutPayWallet.
  ///
  /// In en, this message translates to:
  /// **'Pay with wallet'**
  String get checkoutPayWallet;

  /// No description provided for @checkoutPayStripe.
  ///
  /// In en, this message translates to:
  /// **'Pay by card (Stripe)'**
  String get checkoutPayStripe;

  /// No description provided for @checkoutPayRazorpay.
  ///
  /// In en, this message translates to:
  /// **'Pay with Razorpay'**
  String get checkoutPayRazorpay;

  /// No description provided for @checkoutNoCard.
  ///
  /// In en, this message translates to:
  /// **'Card payments are not available right now.'**
  String get checkoutNoCard;

  /// No description provided for @checkoutHoldNote.
  ///
  /// In en, this message translates to:
  /// **'We hold this vehicle for you until {time}.'**
  String checkoutHoldNote(String time);

  /// No description provided for @confirmationTitle.
  ///
  /// In en, this message translates to:
  /// **'Booking status'**
  String get confirmationTitle;

  /// No description provided for @confirmationConfirmed.
  ///
  /// In en, this message translates to:
  /// **'Your booking is confirmed.'**
  String get confirmationConfirmed;

  /// No description provided for @confirmationWaiting.
  ///
  /// In en, this message translates to:
  /// **'Waiting for payment confirmation. This page updates automatically.'**
  String get confirmationWaiting;

  /// No description provided for @confirmationFailed.
  ///
  /// In en, this message translates to:
  /// **'This booking was cancelled. Any money received has been returned to your wallet.'**
  String get confirmationFailed;

  /// No description provided for @confirmationCardFailed.
  ///
  /// In en, this message translates to:
  /// **'The card payment did not go through. You can try again.'**
  String get confirmationCardFailed;

  /// No description provided for @confirmationViewBooking.
  ///
  /// In en, this message translates to:
  /// **'View booking'**
  String get confirmationViewBooking;

  /// No description provided for @bookingTitle.
  ///
  /// In en, this message translates to:
  /// **'Booking #{id}'**
  String bookingTitle(String id);

  /// No description provided for @bookingNone.
  ///
  /// In en, this message translates to:
  /// **'You have no bookings yet.'**
  String get bookingNone;

  /// No description provided for @bookingAmountDue.
  ///
  /// In en, this message translates to:
  /// **'Amount due'**
  String get bookingAmountDue;

  /// No description provided for @bookingAmountPaid.
  ///
  /// In en, this message translates to:
  /// **'Paid'**
  String get bookingAmountPaid;

  /// No description provided for @bookingPayBalance.
  ///
  /// In en, this message translates to:
  /// **'Pay now'**
  String get bookingPayBalance;

  /// No description provided for @bookingNewEnd.
  ///
  /// In en, this message translates to:
  /// **'New return time'**
  String get bookingNewEnd;

  /// No description provided for @bookingExtend.
  ///
  /// In en, this message translates to:
  /// **'Extend booking'**
  String get bookingExtend;

  /// No description provided for @bookingCancel.
  ///
  /// In en, this message translates to:
  /// **'Cancel booking'**
  String get bookingCancel;

  /// No description provided for @bookingCancelConfirm.
  ///
  /// In en, this message translates to:
  /// **'Cancel this booking? Everything you paid goes back to your wallet.'**
  String get bookingCancelConfirm;

  /// No description provided for @bookingInvoice.
  ///
  /// In en, this message translates to:
  /// **'Download invoice (PDF)'**
  String get bookingInvoice;

  /// No description provided for @bookingDepositStatus.
  ///
  /// In en, this message translates to:
  /// **'Deposit'**
  String get bookingDepositStatus;

  /// No description provided for @bookingCancelledNotice.
  ///
  /// In en, this message translates to:
  /// **'Booking cancelled. The refund is in your wallet.'**
  String get bookingCancelledNotice;

  /// No description provided for @bookingExtendedNotice.
  ///
  /// In en, this message translates to:
  /// **'Booking extended.'**
  String get bookingExtendedNotice;

  /// No description provided for @depositHeld.
  ///
  /// In en, this message translates to:
  /// **'held'**
  String get depositHeld;

  /// No description provided for @depositReleased.
  ///
  /// In en, this message translates to:
  /// **'released'**
  String get depositReleased;

  /// No description provided for @statusDraft.
  ///
  /// In en, this message translates to:
  /// **'Awaiting payment'**
  String get statusDraft;

  /// No description provided for @statusPendingPayment.
  ///
  /// In en, this message translates to:
  /// **'Payment in progress'**
  String get statusPendingPayment;

  /// No description provided for @statusConfirmed.
  ///
  /// In en, this message translates to:
  /// **'Confirmed'**
  String get statusConfirmed;

  /// No description provided for @statusPickedUp.
  ///
  /// In en, this message translates to:
  /// **'On rent'**
  String get statusPickedUp;

  /// No description provided for @statusReturned.
  ///
  /// In en, this message translates to:
  /// **'Returned'**
  String get statusReturned;

  /// No description provided for @statusCompleted.
  ///
  /// In en, this message translates to:
  /// **'Completed'**
  String get statusCompleted;

  /// No description provided for @statusSettled.
  ///
  /// In en, this message translates to:
  /// **'Closed'**
  String get statusSettled;

  /// No description provided for @statusCancelled.
  ///
  /// In en, this message translates to:
  /// **'Cancelled'**
  String get statusCancelled;

  /// No description provided for @statusNoShow.
  ///
  /// In en, this message translates to:
  /// **'No-show'**
  String get statusNoShow;

  /// No description provided for @walletBalance.
  ///
  /// In en, this message translates to:
  /// **'Balance'**
  String get walletBalance;

  /// No description provided for @walletEmpty.
  ///
  /// In en, this message translates to:
  /// **'Your wallet is empty.'**
  String get walletEmpty;

  /// No description provided for @walletNoTransactions.
  ///
  /// In en, this message translates to:
  /// **'No transactions yet.'**
  String get walletNoTransactions;

  /// No description provided for @walletTopUp.
  ///
  /// In en, this message translates to:
  /// **'Top up'**
  String get walletTopUp;

  /// No description provided for @walletAmount.
  ///
  /// In en, this message translates to:
  /// **'Amount'**
  String get walletAmount;

  /// No description provided for @walletCurrency.
  ///
  /// In en, this message translates to:
  /// **'Currency'**
  String get walletCurrency;

  /// No description provided for @walletDate.
  ///
  /// In en, this message translates to:
  /// **'Date'**
  String get walletDate;

  /// No description provided for @walletType.
  ///
  /// In en, this message translates to:
  /// **'Type'**
  String get walletType;

  /// No description provided for @walletProcessing.
  ///
  /// In en, this message translates to:
  /// **'Complete the payment in the window that opened. Your balance updates once the payment provider confirms it.'**
  String get walletProcessing;

  /// No description provided for @walletAmountInvalid.
  ///
  /// In en, this message translates to:
  /// **'Enter a valid amount.'**
  String get walletAmountInvalid;

  /// No description provided for @walletTypeTopup.
  ///
  /// In en, this message translates to:
  /// **'Top-up'**
  String get walletTypeTopup;

  /// No description provided for @walletTypePayment.
  ///
  /// In en, this message translates to:
  /// **'Booking payment'**
  String get walletTypePayment;

  /// No description provided for @walletTypeRefund.
  ///
  /// In en, this message translates to:
  /// **'Refund'**
  String get walletTypeRefund;

  /// No description provided for @walletTypeDepositRelease.
  ///
  /// In en, this message translates to:
  /// **'Deposit returned'**
  String get walletTypeDepositRelease;

  /// No description provided for @profileSave.
  ///
  /// In en, this message translates to:
  /// **'Save'**
  String get profileSave;

  /// No description provided for @profileSaved.
  ///
  /// In en, this message translates to:
  /// **'Profile saved.'**
  String get profileSaved;

  /// No description provided for @draftExpired.
  ///
  /// In en, this message translates to:
  /// **'This booking draft has expired. Please start a new booking.'**
  String get draftExpired;

  /// No description provided for @walletTooLow.
  ///
  /// In en, this message translates to:
  /// **'Your wallet balance is too low for this payment.'**
  String get walletTooLow;

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

  /// No description provided for @paymentNotCompleted.
  ///
  /// In en, this message translates to:
  /// **'The payment was not completed.'**
  String get paymentNotCompleted;

  /// No description provided for @invoiceSaved.
  ///
  /// In en, this message translates to:
  /// **'Invoice saved to {path}'**
  String invoiceSaved(String path);

  /// No description provided for @signInToContinue.
  ///
  /// In en, this message translates to:
  /// **'Sign in to continue'**
  String get signInToContinue;

  /// No description provided for @selectDate.
  ///
  /// In en, this message translates to:
  /// **'Select date and time'**
  String get selectDate;
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
