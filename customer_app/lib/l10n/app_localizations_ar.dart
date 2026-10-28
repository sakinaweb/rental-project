// ignore: unused_import
import 'package:intl/intl.dart' as intl;

import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Arabic (`ar`).
class L10nAr extends L10n {
  L10nAr([String locale = 'ar']) : super(locale);

  @override
  String get navSearch => 'بحث';

  @override
  String get navMyBookings => 'حجوزاتي';

  @override
  String get navWallet => 'المحفظة';

  @override
  String get navProfile => 'الملف الشخصي';

  @override
  String get navLogin => 'تسجيل الدخول';

  @override
  String get navRegister => 'إنشاء حساب';

  @override
  String get navLogout => 'تسجيل الخروج';

  @override
  String get navLanguage => 'اللغة';

  @override
  String get commonLoading => 'جارٍ التحميل…';

  @override
  String get commonBack => 'رجوع';

  @override
  String get commonOptional => 'اختياري';

  @override
  String commonTimesNote(String tz) {
    return 'جميع الأوقات معروضة بتوقيت $tz.';
  }

  @override
  String get searchTitle => 'ابحث عن دراجة أو سيارة';

  @override
  String get searchCountry => 'الدولة';

  @override
  String get searchCity => 'المدينة';

  @override
  String get searchAny => 'الكل';

  @override
  String get searchPickUp => 'الاستلام';

  @override
  String get searchReturn => 'الإرجاع';

  @override
  String get searchCategory => 'الفئة';

  @override
  String get searchType => 'النوع';

  @override
  String get searchBike => 'دراجة';

  @override
  String get searchCar => 'سيارة';

  @override
  String get searchSeats => 'الحد الأدنى للمقاعد';

  @override
  String get searchButton => 'بحث';

  @override
  String get searchNoResults =>
      'لا توجد مركبات مطابقة لبحثك. جرّب تواريخ أو مرشحات أخرى.';

  @override
  String get searchDatesInvalid => 'يجب أن يكون الإرجاع بعد الاستلام.';

  @override
  String get searchTotalForDates => 'الإجمالي لتواريخك';

  @override
  String get searchPickDatesForPrice =>
      'اختر وقتي الاستلام والإرجاع لعرض السعر.';

  @override
  String vehicleSeats(int n) {
    return '$n مقاعد';
  }

  @override
  String get vehicleSeatsLabel => 'المقاعد';

  @override
  String get vehicleTransmission => 'ناقل الحركة';

  @override
  String get vehicleFuel => 'الوقود';

  @override
  String get vehicleBranch => 'الفرع';

  @override
  String get vehicleAvailable => 'متاحة في هذه التواريخ';

  @override
  String get vehicleUnavailable => 'غير متاحة في هذه التواريخ';

  @override
  String get vehicleAddons => 'الإضافات';

  @override
  String get vehicleAddonPerDay => 'لكل يوم';

  @override
  String get vehicleAddonPerBooking => 'لكل حجز';

  @override
  String get vehicleQty => 'الكمية';

  @override
  String get vehicleCoupon => 'رمز القسيمة';

  @override
  String get vehicleQuote => 'سعرك';

  @override
  String get vehicleSubtotal => 'المجموع الفرعي';

  @override
  String get vehicleDiscounts => 'الخصومات';

  @override
  String get vehicleTax => 'الضريبة';

  @override
  String get vehicleTotal => 'الإجمالي';

  @override
  String get vehicleDeposit => 'تأمين مسترد';

  @override
  String get vehicleDueNow => 'المبلغ المطلوب الآن';

  @override
  String get vehicleBookNow => 'احجز الآن';

  @override
  String get vehicleLoginToBook => 'سجّل الدخول للحجز';

  @override
  String get vehiclePriceUnavailable => 'لا يوجد سعر لهذه التواريخ';

  @override
  String get authSignIn => 'تسجيل الدخول';

  @override
  String get authForgotPassword => 'نسيت كلمة المرور؟';

  @override
  String get authWithPassword => 'كلمة المرور';

  @override
  String get authWithOtp => 'رمز الهاتف';

  @override
  String get authEmail => 'البريد الإلكتروني';

  @override
  String get authPassword => 'كلمة المرور';

  @override
  String get authPasswordConfirmation => 'تأكيد كلمة المرور';

  @override
  String get authName => 'الاسم الكامل';

  @override
  String get authPhone => 'الهاتف';

  @override
  String get authPhoneHint => 'بالصيغة الدولية، مثل +971500000000';

  @override
  String get authCode => 'الرمز المكوّن من 6 أرقام';

  @override
  String get authSendCode => 'إرسال الرمز';

  @override
  String get authVerify => 'تحقق وسجّل الدخول';

  @override
  String get authNoAccount => 'جديد هنا؟';

  @override
  String get authHaveAccount => 'لديك حساب بالفعل؟';

  @override
  String get authCreateAccount => 'إنشاء حساب';

  @override
  String get authTooMany =>
      'محاولات كثيرة جدًا. يرجى الانتظار دقيقة ثم المحاولة مرة أخرى.';

  @override
  String get checkoutTitle => 'الدفع';

  @override
  String get checkoutPay => 'ادفع';

  @override
  String get checkoutPayWallet => 'الدفع من المحفظة';

  @override
  String get checkoutPayStripe => 'الدفع بالبطاقة (Stripe)';

  @override
  String get checkoutPayRazorpay => 'الدفع عبر Razorpay';

  @override
  String get checkoutNoCard => 'الدفع بالبطاقة غير متاح حاليًا.';

  @override
  String checkoutHoldNote(String time) {
    return 'نحجز هذه المركبة لك حتى $time.';
  }

  @override
  String get confirmationTitle => 'حالة الحجز';

  @override
  String get confirmationConfirmed => 'تم تأكيد حجزك.';

  @override
  String get confirmationWaiting =>
      'بانتظار تأكيد الدفع. تُحدَّث هذه الصفحة تلقائيًا.';

  @override
  String get confirmationFailed =>
      'تم إلغاء هذا الحجز. أُعيد أي مبلغ مستلم إلى محفظتك.';

  @override
  String get confirmationCardFailed =>
      'لم تتم عملية الدفع بالبطاقة. يمكنك المحاولة مرة أخرى.';

  @override
  String get confirmationViewBooking => 'عرض الحجز';

  @override
  String bookingTitle(String id) {
    return 'الحجز رقم $id';
  }

  @override
  String get bookingNone => 'لا توجد لديك حجوزات بعد.';

  @override
  String get bookingAmountDue => 'المبلغ المستحق';

  @override
  String get bookingAmountPaid => 'المدفوع';

  @override
  String get bookingPayBalance => 'ادفع الآن';

  @override
  String get bookingNewEnd => 'وقت الإرجاع الجديد';

  @override
  String get bookingExtend => 'تمديد الحجز';

  @override
  String get bookingCancel => 'إلغاء الحجز';

  @override
  String get bookingCancelConfirm =>
      'هل تريد إلغاء هذا الحجز؟ سيُعاد كل ما دفعته إلى محفظتك.';

  @override
  String get bookingInvoice => 'تنزيل الفاتورة (PDF)';

  @override
  String get bookingDepositStatus => 'التأمين';

  @override
  String get bookingCancelledNotice =>
      'تم إلغاء الحجز. المبلغ المسترد في محفظتك.';

  @override
  String get bookingExtendedNotice => 'تم تمديد الحجز.';

  @override
  String get depositHeld => 'محتجز';

  @override
  String get depositReleased => 'مُعاد';

  @override
  String get statusDraft => 'بانتظار الدفع';

  @override
  String get statusPendingPayment => 'الدفع قيد التنفيذ';

  @override
  String get statusConfirmed => 'مؤكد';

  @override
  String get statusPickedUp => 'قيد الإيجار';

  @override
  String get statusReturned => 'تم الإرجاع';

  @override
  String get statusCompleted => 'مكتمل';

  @override
  String get statusSettled => 'مغلق';

  @override
  String get statusCancelled => 'ملغى';

  @override
  String get statusNoShow => 'لم يحضر';

  @override
  String get walletBalance => 'الرصيد';

  @override
  String get walletEmpty => 'محفظتك فارغة.';

  @override
  String get walletNoTransactions => 'لا توجد معاملات بعد.';

  @override
  String get walletTopUp => 'شحن الرصيد';

  @override
  String get walletAmount => 'المبلغ';

  @override
  String get walletCurrency => 'العملة';

  @override
  String get walletDate => 'التاريخ';

  @override
  String get walletType => 'النوع';

  @override
  String get walletProcessing =>
      'أكمل الدفع في النافذة التي فُتحت. يُحدَّث رصيدك بعد تأكيد مزود الدفع.';

  @override
  String get walletAmountInvalid => 'أدخل مبلغًا صالحًا.';

  @override
  String get walletTypeTopup => 'شحن';

  @override
  String get walletTypePayment => 'دفع حجز';

  @override
  String get walletTypeRefund => 'استرداد';

  @override
  String get walletTypeDepositRelease => 'إعادة التأمين';

  @override
  String get profileSave => 'حفظ';

  @override
  String get profileSaved => 'تم حفظ الملف الشخصي.';

  @override
  String get draftExpired => 'انتهت صلاحية مسودة الحجز هذه. يرجى بدء حجز جديد.';

  @override
  String get walletTooLow => 'رصيد محفظتك غير كافٍ لهذه الدفعة.';

  @override
  String get errorNetwork => 'تعذر الوصول إلى الخادم. تحقق من اتصالك.';

  @override
  String get retry => 'حاول مرة أخرى';

  @override
  String get paymentNotCompleted => 'لم تكتمل عملية الدفع.';

  @override
  String invoiceSaved(String path) {
    return 'تم حفظ الفاتورة في $path';
  }

  @override
  String get signInToContinue => 'سجّل الدخول للمتابعة';

  @override
  String get selectDate => 'اختر التاريخ والوقت';

  @override
  String get statusExpired => 'منتهي';

  @override
  String get bookAgain => 'احجز مرة أخرى';

  @override
  String get investorTitle => 'لوحة المستثمر';

  @override
  String get investorApplyTitle => 'كن مستثمرًا';

  @override
  String get investorApplyIntro =>
      'موّل دراجات وسيارات الشركة واحصل على حصة مما تحققه. أدخل الحساب المصرفي لأرباحك، ثم نتحقق من هويتك (KYC) قبل أن تتمكن من الاستثمار.';

  @override
  String get investorKycPending =>
      'شكرًا! نحن نتحقق من هويتك (KYC). يمكنك الاستثمار بعد الموافقة.';

  @override
  String get investorKycRejected =>
      'لم تتم الموافقة على التحقق من هويتك. يمكنك التقدم مرة أخرى ببيانات محدّثة.';

  @override
  String get investorDisclosureTitle => 'إفصاح المخاطر';

  @override
  String get investorAcceptLabel => 'لقد قرأت إفصاح المخاطر هذا وفهمته.';

  @override
  String get investorAccept => 'قبول ومتابعة';

  @override
  String get investorPlans => 'خطط الاستثمار';

  @override
  String get investorNoPlans => 'لا توجد خطط مفتوحة في بلدك حاليًا.';

  @override
  String investorMinimum(String amount) {
    return 'الحد الأدنى $amount';
  }

  @override
  String investorTerm(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: 'مدة $n أشهر',
      one: 'مدة شهر واحد',
    );
    return '$_temp0';
  }

  @override
  String investorShare(String pct) {
    return '$pct% من كل تأجير (بعد الضريبة) لمركباتك';
  }

  @override
  String get investorAllVehicles => 'أي نوع مركبة';

  @override
  String investorAmount(String currency) {
    return 'المبلغ ($currency)';
  }

  @override
  String get investorInvest => 'استثمر';

  @override
  String investorInvested(String amount, String id) {
    return 'تم طلب الاستثمار. حوّل $amount إلى حسابنا المصرفي بالمرجع INV-$id؛ نبدأ مدتك عند وصوله.';
  }

  @override
  String get investorInvestments => 'استثماراتي';

  @override
  String get investorNoInvestments => 'ليست لديك استثمارات بعد.';

  @override
  String get investorStatusPending => 'بانتظار تحويلك';

  @override
  String get investorStatusActive => 'نشط';

  @override
  String get investorStatusClosed => 'مغلق';

  @override
  String get investorStatusCancelled => 'ملغى';

  @override
  String get investorVehicles => 'المركبات المموّلة';

  @override
  String get payoutAccountHolder => 'صاحب الحساب';

  @override
  String get payoutBankName => 'اسم البنك';

  @override
  String get payoutAccountNumber => 'رقم الحساب';

  @override
  String get payoutRoutingCode => 'IFSC / IBAN / SWIFT (اختياري)';

  @override
  String get payoutBalance => 'الرصيد';

  @override
  String get payoutAvailable => 'المتاح للصرف';

  @override
  String get payoutAmount => 'المبلغ';

  @override
  String get payoutCurrency => 'العملة';

  @override
  String get payoutRequest => 'طلب صرف';

  @override
  String get payoutRequested => 'تم طلب الصرف. سنحوّله مع دفعة الصرف القادمة.';

  @override
  String get payoutHistory => 'طلبات الصرف';

  @override
  String get payoutNone => 'لا توجد طلبات صرف بعد.';

  @override
  String get payoutStatusPending => 'معلّق';

  @override
  String get payoutStatusBatched => 'قيد المعالجة';

  @override
  String get payoutStatusPaid => 'مدفوع';

  @override
  String get payoutStatusRejected => 'مرفوض';

  @override
  String get navInvestor => 'استثمر';
}
