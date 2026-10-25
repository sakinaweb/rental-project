// ignore: unused_import
import 'package:intl/intl.dart' as intl;

import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Hindi (`hi`).
class L10nHi extends L10n {
  L10nHi([String locale = 'hi']) : super(locale);

  @override
  String get appTitle => 'स्टाफ़';

  @override
  String get signIn => 'साइन इन करें';

  @override
  String get email => 'ईमेल';

  @override
  String get password => 'पासवर्ड';

  @override
  String get signOut => 'साइन आउट करें';

  @override
  String get notStaff =>
      'यह ऐप स्टाफ़ खातों के लिए है। अपने स्टाफ़ खाते से साइन इन करें।';

  @override
  String get errorNetwork => 'सर्वर तक नहीं पहुँच सके। अपना कनेक्शन जाँचें।';

  @override
  String get retry => 'फिर से कोशिश करें';

  @override
  String get today => 'आज';

  @override
  String get pickups => 'पिक-अप';

  @override
  String get returns => 'वापसी';

  @override
  String get noPickups => 'कोई पिक-अप बाकी नहीं है।';

  @override
  String get noReturns => 'कोई वाहन बाहर नहीं है।';

  @override
  String get late => 'देर';

  @override
  String due(String time) {
    return 'समय $time';
  }

  @override
  String booking(String id) {
    return 'बुकिंग #$id';
  }

  @override
  String get customer => 'ग्राहक';

  @override
  String get vehicle => 'वाहन';

  @override
  String get period => 'अवधि';

  @override
  String currentOdometer(String km) {
    return 'अभी ओडोमीटर: $km';
  }

  @override
  String get handOver => 'सौंपें';

  @override
  String get receive => 'वापसी लें';

  @override
  String get handoverTitle => 'हैंडओवर चेकलिस्ट';

  @override
  String get returnTitle => 'वापसी चेकलिस्ट';

  @override
  String get photosHint => 'कम से कम 4 फ़ोटो: आगे, पीछे, बाएँ और दाएँ।';

  @override
  String get addPhoto => 'फ़ोटो लें';

  @override
  String photoCount(int n) {
    String _temp0 = intl.Intl.pluralLogic(
      n,
      locale: localeName,
      other: '$n फ़ोटो',
      one: '1 फ़ोटो',
    );
    return '$_temp0';
  }

  @override
  String get odometer => 'ओडोमीटर';

  @override
  String fuel(String pct) {
    return 'ईंधन स्तर: $pct %';
  }

  @override
  String get notes => 'टिप्पणी (वैकल्पिक)';

  @override
  String get damages => 'मिली क्षति';

  @override
  String get addDamage => 'क्षति जोड़ें';

  @override
  String get damageDescription => 'क्या क्षतिग्रस्त है';

  @override
  String damageCost(String currency) {
    return 'अनुमानित लागत ($currency)';
  }

  @override
  String get damageNote =>
      'कुछ भी वसूलने से पहले एडमिन क्षति की समीक्षा करता है।';

  @override
  String get remove => 'हटाएँ';

  @override
  String get confirmHandover => 'हैंडओवर की पुष्टि करें';

  @override
  String get confirmReturn => 'वापसी की पुष्टि करें';

  @override
  String get needPhotos => 'कम से कम 4 फ़ोटो लें।';

  @override
  String get invalidNumber => 'एक पूर्ण संख्या दर्ज करें।';

  @override
  String get done => 'सहेजा गया।';

  @override
  String get checklists => 'चेकलिस्ट';

  @override
  String get stagePickup => 'हैंडओवर';

  @override
  String get stageReturn => 'वापसी';

  @override
  String by(String name) {
    return '$name द्वारा';
  }

  @override
  String status(String status) {
    return 'स्थिति: $status';
  }
}
