// ignore: unused_import
import 'package:intl/intl.dart' as intl;

import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Arabic (`ar`).
class L10nAr extends L10n {
  L10nAr([String locale = 'ar']) : super(locale);

  @override
  String get appTitle => 'الموظفون';

  @override
  String get signIn => 'تسجيل الدخول';

  @override
  String get email => 'البريد الإلكتروني';

  @override
  String get password => 'كلمة المرور';

  @override
  String get signOut => 'تسجيل الخروج';

  @override
  String get notStaff =>
      'هذا التطبيق لحسابات الموظفين. سجّل الدخول بحساب الموظف الخاص بك.';

  @override
  String get errorNetwork => 'تعذّر الوصول إلى الخادم. تحقّق من اتصالك.';

  @override
  String get retry => 'أعد المحاولة';

  @override
  String get today => 'اليوم';

  @override
  String get pickups => 'الاستلامات';

  @override
  String get returns => 'الإرجاعات';

  @override
  String get noPickups => 'لا توجد استلامات مستحقة.';

  @override
  String get noReturns => 'لا توجد مركبات خارج الفرع.';

  @override
  String get late => 'متأخر';

  @override
  String due(String time) {
    return 'الموعد $time';
  }

  @override
  String booking(String id) {
    return 'الحجز رقم $id';
  }

  @override
  String get customer => 'العميل';

  @override
  String get vehicle => 'المركبة';

  @override
  String get period => 'المدة';

  @override
  String currentOdometer(String km) {
    return 'عدّاد المسافة الآن: $km';
  }

  @override
  String get handOver => 'تسليم';

  @override
  String get receive => 'استلام الإرجاع';

  @override
  String get handoverTitle => 'قائمة فحص التسليم';

  @override
  String get returnTitle => 'قائمة فحص الإرجاع';

  @override
  String get photosHint => '4 صور على الأقل: الأمام والخلف واليسار واليمين.';

  @override
  String get addPhoto => 'التقط صورة';

  @override
  String photoCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n صورة',
      few: '$n صور',
      two: 'صورتان',
      one: 'صورة واحدة',
    );
    return '$_temp0';
  }

  @override
  String get odometer => 'عدّاد المسافة';

  @override
  String fuel(String pct) {
    return 'مستوى الوقود: $pct %';
  }

  @override
  String get notes => 'ملاحظات (اختياري)';

  @override
  String get damages => 'أضرار تم العثور عليها';

  @override
  String get addDamage => 'إضافة ضرر';

  @override
  String get damageDescription => 'ما الذي تضرّر';

  @override
  String damageCost(String currency) {
    return 'التكلفة التقديرية ($currency)';
  }

  @override
  String get damageNote => 'يراجع المسؤول الأضرار قبل احتساب أي مبلغ.';

  @override
  String get remove => 'إزالة';

  @override
  String get confirmHandover => 'تأكيد التسليم';

  @override
  String get confirmReturn => 'تأكيد الإرجاع';

  @override
  String get needPhotos => 'التقط 4 صور على الأقل.';

  @override
  String get invalidNumber => 'أدخل عددًا صحيحًا.';

  @override
  String get done => 'تم الحفظ.';

  @override
  String get checklists => 'قوائم الفحص';

  @override
  String get stagePickup => 'التسليم';

  @override
  String get stageReturn => 'الإرجاع';

  @override
  String by(String name) {
    return 'بواسطة $name';
  }

  @override
  String status(String status) {
    return 'الحالة: $status';
  }
}
