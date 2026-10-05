// ignore: unused_import
import 'package:intl/intl.dart' as intl;

import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Hindi (`hi`).
class L10nHi extends L10n {
  L10nHi([String locale = 'hi']) : super(locale);

  @override
  String get navSearch => 'खोजें';

  @override
  String get navMyBookings => 'मेरी बुकिंग';

  @override
  String get navWallet => 'वॉलेट';

  @override
  String get navProfile => 'प्रोफ़ाइल';

  @override
  String get navLogin => 'साइन इन';

  @override
  String get navRegister => 'खाता बनाएँ';

  @override
  String get navLogout => 'साइन आउट';

  @override
  String get navLanguage => 'भाषा';

  @override
  String get commonLoading => 'लोड हो रहा है…';

  @override
  String get commonBack => 'वापस';

  @override
  String get commonOptional => 'वैकल्पिक';

  @override
  String commonTimesNote(String tz) {
    return 'सभी समय $tz में दिखाए गए हैं।';
  }

  @override
  String get searchTitle => 'बाइक या कार खोजें';

  @override
  String get searchCountry => 'देश';

  @override
  String get searchCity => 'शहर';

  @override
  String get searchAny => 'कोई भी';

  @override
  String get searchPickUp => 'पिक-अप';

  @override
  String get searchReturn => 'वापसी';

  @override
  String get searchCategory => 'श्रेणी';

  @override
  String get searchType => 'प्रकार';

  @override
  String get searchBike => 'बाइक';

  @override
  String get searchCar => 'कार';

  @override
  String get searchSeats => 'न्यूनतम सीटें';

  @override
  String get searchButton => 'खोजें';

  @override
  String get searchNoResults =>
      'आपकी खोज से कोई वाहन मेल नहीं खाता। अन्य तारीखें या फ़िल्टर आज़माएँ।';

  @override
  String get searchDatesInvalid => 'वापसी पिक-अप के बाद होनी चाहिए।';

  @override
  String get searchTotalForDates => 'आपकी तारीखों का कुल';

  @override
  String get searchPickDatesForPrice =>
      'कीमत देखने के लिए पिक-अप और वापसी चुनें।';

  @override
  String vehicleSeats(int n) {
    return '$n सीटें';
  }

  @override
  String get vehicleSeatsLabel => 'सीटें';

  @override
  String get vehicleTransmission => 'ट्रांसमिशन';

  @override
  String get vehicleFuel => 'ईंधन';

  @override
  String get vehicleBranch => 'शाखा';

  @override
  String get vehicleAvailable => 'इन तारीखों में उपलब्ध';

  @override
  String get vehicleUnavailable => 'इन तारीखों में उपलब्ध नहीं';

  @override
  String get vehicleAddons => 'ऐड-ऑन';

  @override
  String get vehicleAddonPerDay => 'प्रति दिन';

  @override
  String get vehicleAddonPerBooking => 'प्रति बुकिंग';

  @override
  String get vehicleQty => 'मात्रा';

  @override
  String get vehicleCoupon => 'कूपन कोड';

  @override
  String get vehicleQuote => 'आपकी कीमत';

  @override
  String get vehicleSubtotal => 'उप-योग';

  @override
  String get vehicleDiscounts => 'छूट';

  @override
  String get vehicleTax => 'कर';

  @override
  String get vehicleTotal => 'कुल';

  @override
  String get vehicleDeposit => 'वापसी योग्य जमा राशि';

  @override
  String get vehicleDueNow => 'अभी भुगतान करें';

  @override
  String get vehicleBookNow => 'अभी बुक करें';

  @override
  String get vehicleLoginToBook => 'बुक करने के लिए साइन इन करें';

  @override
  String get vehiclePriceUnavailable => 'इन तारीखों के लिए कोई कीमत नहीं';

  @override
  String get authSignIn => 'साइन इन';

  @override
  String get authWithPassword => 'पासवर्ड';

  @override
  String get authWithOtp => 'फ़ोन कोड';

  @override
  String get authEmail => 'ईमेल';

  @override
  String get authPassword => 'पासवर्ड';

  @override
  String get authPasswordConfirmation => 'पासवर्ड की पुष्टि करें';

  @override
  String get authName => 'पूरा नाम';

  @override
  String get authPhone => 'फ़ोन';

  @override
  String get authPhoneHint => 'अंतरराष्ट्रीय प्रारूप, जैसे +919900000000';

  @override
  String get authCode => '6 अंकों का कोड';

  @override
  String get authSendCode => 'कोड भेजें';

  @override
  String get authVerify => 'सत्यापित करें और साइन इन करें';

  @override
  String get authNoAccount => 'नए हैं?';

  @override
  String get authHaveAccount => 'पहले से खाता है?';

  @override
  String get authCreateAccount => 'खाता बनाएँ';

  @override
  String get authTooMany =>
      'बहुत अधिक प्रयास। कृपया एक मिनट रुककर फिर प्रयास करें।';

  @override
  String get checkoutTitle => 'चेकआउट';

  @override
  String get checkoutPay => 'भुगतान करें';

  @override
  String get checkoutPayWallet => 'वॉलेट से भुगतान करें';

  @override
  String get checkoutPayStripe => 'कार्ड से भुगतान (Stripe)';

  @override
  String get checkoutPayRazorpay => 'Razorpay से भुगतान करें';

  @override
  String get checkoutNoCard => 'कार्ड भुगतान अभी उपलब्ध नहीं है।';

  @override
  String checkoutHoldNote(String time) {
    return 'हम यह वाहन आपके लिए $time तक रोककर रखते हैं।';
  }

  @override
  String get confirmationTitle => 'बुकिंग की स्थिति';

  @override
  String get confirmationConfirmed => 'आपकी बुकिंग की पुष्टि हो गई है।';

  @override
  String get confirmationWaiting =>
      'भुगतान की पुष्टि की प्रतीक्षा है। यह पेज अपने आप अपडेट होता है।';

  @override
  String get confirmationFailed =>
      'यह बुकिंग रद्द कर दी गई। प्राप्त कोई भी राशि आपके वॉलेट में लौटा दी गई है।';

  @override
  String get confirmationCardFailed =>
      'कार्ड भुगतान पूरा नहीं हुआ। आप फिर से प्रयास कर सकते हैं।';

  @override
  String get confirmationViewBooking => 'बुकिंग देखें';

  @override
  String bookingTitle(String id) {
    return 'बुकिंग #$id';
  }

  @override
  String get bookingNone => 'अभी तक आपकी कोई बुकिंग नहीं है।';

  @override
  String get bookingAmountDue => 'देय राशि';

  @override
  String get bookingAmountPaid => 'भुगतान किया';

  @override
  String get bookingPayBalance => 'अभी भुगतान करें';

  @override
  String get bookingNewEnd => 'नया वापसी समय';

  @override
  String get bookingExtend => 'बुकिंग बढ़ाएँ';

  @override
  String get bookingCancel => 'बुकिंग रद्द करें';

  @override
  String get bookingCancelConfirm =>
      'यह बुकिंग रद्द करें? आपका पूरा भुगतान आपके वॉलेट में लौट जाएगा।';

  @override
  String get bookingInvoice => 'इनवॉइस डाउनलोड करें (PDF)';

  @override
  String get bookingDepositStatus => 'जमा राशि';

  @override
  String get bookingCancelledNotice =>
      'बुकिंग रद्द हो गई। रिफ़ंड आपके वॉलेट में है।';

  @override
  String get bookingExtendedNotice => 'बुकिंग बढ़ा दी गई।';

  @override
  String get depositHeld => 'रोकी गई';

  @override
  String get depositReleased => 'लौटाई गई';

  @override
  String get statusDraft => 'भुगतान की प्रतीक्षा';

  @override
  String get statusPendingPayment => 'भुगतान जारी है';

  @override
  String get statusConfirmed => 'पुष्ट';

  @override
  String get statusPickedUp => 'किराये पर';

  @override
  String get statusReturned => 'लौटाया गया';

  @override
  String get statusCompleted => 'पूर्ण';

  @override
  String get statusSettled => 'बंद';

  @override
  String get statusCancelled => 'रद्द';

  @override
  String get statusNoShow => 'नहीं आए';

  @override
  String get walletBalance => 'शेष राशि';

  @override
  String get walletEmpty => 'आपका वॉलेट खाली है।';

  @override
  String get walletNoTransactions => 'अभी तक कोई लेन-देन नहीं।';

  @override
  String get walletTopUp => 'टॉप अप';

  @override
  String get walletAmount => 'राशि';

  @override
  String get walletCurrency => 'मुद्रा';

  @override
  String get walletDate => 'तारीख';

  @override
  String get walletType => 'प्रकार';

  @override
  String get walletProcessing =>
      'खुली हुई विंडो में भुगतान पूरा करें। भुगतान प्रदाता की पुष्टि के बाद आपकी शेष राशि अपडेट होगी।';

  @override
  String get walletAmountInvalid => 'मान्य राशि दर्ज करें।';

  @override
  String get walletTypeTopup => 'टॉप-अप';

  @override
  String get walletTypePayment => 'बुकिंग भुगतान';

  @override
  String get walletTypeRefund => 'रिफ़ंड';

  @override
  String get walletTypeDepositRelease => 'जमा राशि लौटाई गई';

  @override
  String get profileSave => 'सहेजें';

  @override
  String get profileSaved => 'प्रोफ़ाइल सहेजी गई।';

  @override
  String get draftExpired =>
      'इस बुकिंग ड्राफ़्ट की समय-सीमा समाप्त हो गई है। कृपया नई बुकिंग शुरू करें।';

  @override
  String get walletTooLow =>
      'इस भुगतान के लिए आपके वॉलेट में पर्याप्त शेष राशि नहीं है।';

  @override
  String get errorNetwork => 'सर्वर तक नहीं पहुँच सके। अपना कनेक्शन जाँचें।';

  @override
  String get retry => 'फिर से प्रयास करें';

  @override
  String get paymentNotCompleted => 'भुगतान पूरा नहीं हुआ।';

  @override
  String invoiceSaved(String path) {
    return 'इनवॉइस $path में सहेजा गया';
  }

  @override
  String get signInToContinue => 'जारी रखने के लिए साइन इन करें';

  @override
  String get selectDate => 'तारीख और समय चुनें';

  @override
  String get statusExpired => 'समाप्त';

  @override
  String get bookAgain => 'फिर से बुक करें';
}
