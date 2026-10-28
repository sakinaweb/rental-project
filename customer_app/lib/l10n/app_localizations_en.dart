// ignore: unused_import
import 'package:intl/intl.dart' as intl;

import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class L10nEn extends L10n {
  L10nEn([String locale = 'en']) : super(locale);

  @override
  String get navSearch => 'Search';

  @override
  String get navMyBookings => 'My bookings';

  @override
  String get navWallet => 'Wallet';

  @override
  String get navProfile => 'Profile';

  @override
  String get navLogin => 'Sign in';

  @override
  String get navRegister => 'Create account';

  @override
  String get navLogout => 'Sign out';

  @override
  String get navLanguage => 'Language';

  @override
  String get commonLoading => 'Loading…';

  @override
  String get commonBack => 'Back';

  @override
  String get commonOptional => 'optional';

  @override
  String commonTimesNote(String tz) {
    return 'All times are shown in $tz.';
  }

  @override
  String get searchTitle => 'Find a bike or car';

  @override
  String get searchCountry => 'Country';

  @override
  String get searchCity => 'City';

  @override
  String get searchAny => 'Any';

  @override
  String get searchPickUp => 'Pick-up';

  @override
  String get searchReturn => 'Return';

  @override
  String get searchCategory => 'Category';

  @override
  String get searchType => 'Type';

  @override
  String get searchBike => 'Bike';

  @override
  String get searchCar => 'Car';

  @override
  String get searchSeats => 'Min. seats';

  @override
  String get searchButton => 'Search';

  @override
  String get searchNoResults =>
      'No vehicles match your search. Try other dates or filters.';

  @override
  String get searchDatesInvalid => 'Return must be after pick-up.';

  @override
  String get searchTotalForDates => 'total for your dates';

  @override
  String get searchPickDatesForPrice =>
      'Choose pick-up and return to see the price.';

  @override
  String vehicleSeats(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n seats',
      one: '$n seat',
    );
    return '$_temp0';
  }

  @override
  String get vehicleSeatsLabel => 'Seats';

  @override
  String get vehicleTransmission => 'Transmission';

  @override
  String get vehicleFuel => 'Fuel';

  @override
  String get vehicleBranch => 'Branch';

  @override
  String get vehicleAvailable => 'Available for these dates';

  @override
  String get vehicleUnavailable => 'Not available for these dates';

  @override
  String get vehicleAddons => 'Add-ons';

  @override
  String get vehicleAddonPerDay => 'per day';

  @override
  String get vehicleAddonPerBooking => 'per booking';

  @override
  String get vehicleQty => 'Quantity';

  @override
  String get vehicleCoupon => 'Coupon code';

  @override
  String get vehicleQuote => 'Your price';

  @override
  String get vehicleSubtotal => 'Subtotal';

  @override
  String get vehicleDiscounts => 'Discounts';

  @override
  String get vehicleTax => 'Tax';

  @override
  String get vehicleTotal => 'Total';

  @override
  String get vehicleDeposit => 'Refundable deposit';

  @override
  String get vehicleDueNow => 'To pay now';

  @override
  String get vehicleBookNow => 'Book now';

  @override
  String get vehicleLoginToBook => 'Sign in to book';

  @override
  String get vehiclePriceUnavailable => 'No price for these dates';

  @override
  String get authSignIn => 'Sign in';

  @override
  String get authForgotPassword => 'Forgot password?';

  @override
  String get authWithPassword => 'Password';

  @override
  String get authWithOtp => 'Phone code';

  @override
  String get authEmail => 'Email';

  @override
  String get authPassword => 'Password';

  @override
  String get authPasswordConfirmation => 'Confirm password';

  @override
  String get authName => 'Full name';

  @override
  String get authPhone => 'Phone';

  @override
  String get authPhoneHint => 'International format, e.g. +919900000000';

  @override
  String get authCode => '6-digit code';

  @override
  String get authSendCode => 'Send code';

  @override
  String get authVerify => 'Verify and sign in';

  @override
  String get authNoAccount => 'New here?';

  @override
  String get authHaveAccount => 'Already have an account?';

  @override
  String get authCreateAccount => 'Create account';

  @override
  String get authTooMany =>
      'Too many attempts. Please wait a minute and try again.';

  @override
  String get checkoutTitle => 'Checkout';

  @override
  String get checkoutPay => 'Pay';

  @override
  String get checkoutPayWallet => 'Pay with wallet';

  @override
  String get checkoutPayStripe => 'Pay by card (Stripe)';

  @override
  String get checkoutPayRazorpay => 'Pay with Razorpay';

  @override
  String get checkoutNoCard => 'Card payments are not available right now.';

  @override
  String checkoutHoldNote(String time) {
    return 'We hold this vehicle for you until $time.';
  }

  @override
  String get confirmationTitle => 'Booking status';

  @override
  String get confirmationConfirmed => 'Your booking is confirmed.';

  @override
  String get confirmationWaiting =>
      'Waiting for payment confirmation. This page updates automatically.';

  @override
  String get confirmationFailed =>
      'This booking was cancelled. Any money received has been returned to your wallet.';

  @override
  String get confirmationCardFailed =>
      'The card payment did not go through. You can try again.';

  @override
  String get confirmationViewBooking => 'View booking';

  @override
  String bookingTitle(String id) {
    return 'Booking #$id';
  }

  @override
  String get bookingNone => 'You have no bookings yet.';

  @override
  String get bookingAmountDue => 'Amount due';

  @override
  String get bookingAmountPaid => 'Paid';

  @override
  String get bookingPayBalance => 'Pay now';

  @override
  String get bookingNewEnd => 'New return time';

  @override
  String get bookingExtend => 'Extend booking';

  @override
  String get bookingCancel => 'Cancel booking';

  @override
  String get bookingCancelConfirm =>
      'Cancel this booking? Everything you paid goes back to your wallet.';

  @override
  String get bookingInvoice => 'Download invoice (PDF)';

  @override
  String get bookingDepositStatus => 'Deposit';

  @override
  String get bookingCancelledNotice =>
      'Booking cancelled. The refund is in your wallet.';

  @override
  String get bookingExtendedNotice => 'Booking extended.';

  @override
  String get depositHeld => 'held';

  @override
  String get depositReleased => 'released';

  @override
  String get statusDraft => 'Awaiting payment';

  @override
  String get statusPendingPayment => 'Payment in progress';

  @override
  String get statusConfirmed => 'Confirmed';

  @override
  String get statusPickedUp => 'On rent';

  @override
  String get statusReturned => 'Returned';

  @override
  String get statusCompleted => 'Completed';

  @override
  String get statusSettled => 'Closed';

  @override
  String get statusCancelled => 'Cancelled';

  @override
  String get statusNoShow => 'No-show';

  @override
  String get walletBalance => 'Balance';

  @override
  String get walletEmpty => 'Your wallet is empty.';

  @override
  String get walletNoTransactions => 'No transactions yet.';

  @override
  String get walletTopUp => 'Top up';

  @override
  String get walletAmount => 'Amount';

  @override
  String get walletCurrency => 'Currency';

  @override
  String get walletDate => 'Date';

  @override
  String get walletType => 'Type';

  @override
  String get walletProcessing =>
      'Complete the payment in the window that opened. Your balance updates once the payment provider confirms it.';

  @override
  String get walletAmountInvalid => 'Enter a valid amount.';

  @override
  String get walletTypeTopup => 'Top-up';

  @override
  String get walletTypePayment => 'Booking payment';

  @override
  String get walletTypeRefund => 'Refund';

  @override
  String get walletTypeDepositRelease => 'Deposit returned';

  @override
  String get profileSave => 'Save';

  @override
  String get profileSaved => 'Profile saved.';

  @override
  String get draftExpired =>
      'This booking draft has expired. Please start a new booking.';

  @override
  String get walletTooLow => 'Your wallet balance is too low for this payment.';

  @override
  String get errorNetwork => 'Can\'t reach the server. Check your connection.';

  @override
  String get retry => 'Try again';

  @override
  String get paymentNotCompleted => 'The payment was not completed.';

  @override
  String invoiceSaved(String path) {
    return 'Invoice saved to $path';
  }

  @override
  String get signInToContinue => 'Sign in to continue';

  @override
  String get selectDate => 'Select date and time';

  @override
  String get statusExpired => 'Expired';

  @override
  String get bookAgain => 'Book again';

  @override
  String get investorTitle => 'Investor dashboard';

  @override
  String get investorApplyTitle => 'Become an investor';

  @override
  String get investorApplyIntro =>
      'Fund company bikes and cars and earn a share of what they make. Give the bank account for your earnings; we then check your identity (KYC) before you can invest.';

  @override
  String get investorKycPending =>
      'Thanks! We are checking your identity (KYC). You can invest once it is approved.';

  @override
  String get investorKycRejected =>
      'Your identity check was not approved. You can apply again with updated details.';

  @override
  String get investorDisclosureTitle => 'Risk disclosure';

  @override
  String get investorAcceptLabel =>
      'I have read and understood this risk disclosure.';

  @override
  String get investorAccept => 'Accept and continue';

  @override
  String get investorPlans => 'Investment plans';

  @override
  String get investorNoPlans => 'No plans are open in your country right now.';

  @override
  String investorMinimum(String amount) {
    return 'Minimum $amount';
  }

  @override
  String investorTerm(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n months term',
      one: '$n month term',
    );
    return '$_temp0';
  }

  @override
  String investorShare(String pct) {
    return '$pct% of each rental (after tax) of your vehicles';
  }

  @override
  String get investorAllVehicles => 'Any vehicle type';

  @override
  String investorAmount(String currency) {
    return 'Amount ($currency)';
  }

  @override
  String get investorInvest => 'Invest';

  @override
  String investorInvested(String amount, String id) {
    return 'Investment requested. Transfer $amount to our bank account with reference INV-$id; we start your term when it arrives.';
  }

  @override
  String get investorInvestments => 'My investments';

  @override
  String get investorNoInvestments => 'You have no investments yet.';

  @override
  String get investorStatusPending => 'Awaiting your transfer';

  @override
  String get investorStatusActive => 'Active';

  @override
  String get investorStatusClosed => 'Closed';

  @override
  String get investorStatusCancelled => 'Cancelled';

  @override
  String get investorVehicles => 'Funded vehicles';

  @override
  String get payoutAccountHolder => 'Account holder';

  @override
  String get payoutBankName => 'Bank name';

  @override
  String get payoutAccountNumber => 'Account number';

  @override
  String get payoutRoutingCode => 'IFSC / IBAN / SWIFT (optional)';

  @override
  String get payoutBalance => 'Balance';

  @override
  String get payoutAvailable => 'Available to pay out';

  @override
  String get payoutAmount => 'Amount';

  @override
  String get payoutCurrency => 'Currency';

  @override
  String get payoutRequest => 'Request payout';

  @override
  String get payoutRequested =>
      'Payout requested. We will transfer it with the next payout batch.';

  @override
  String get payoutHistory => 'Payout requests';

  @override
  String get payoutNone => 'No payout requests yet.';

  @override
  String get payoutStatusPending => 'Pending';

  @override
  String get payoutStatusBatched => 'Being processed';

  @override
  String get payoutStatusPaid => 'Paid';

  @override
  String get payoutStatusRejected => 'Rejected';

  @override
  String get navInvestor => 'Invest';
}
