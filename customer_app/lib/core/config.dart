// Build-time settings (flutter run/build --dart-define=KEY=value). Nothing secret lives in the app.
class Config {
  /// Android emulator → the Docker backend on the host.
  static const apiBaseUrl = String.fromEnvironment('API_BASE_URL', defaultValue: 'http://10.0.2.2:8000/api/v1');

  /// Push (firebase_messaging) is on only when these Firebase app values are given.
  static const firebaseApiKey = String.fromEnvironment('FIREBASE_API_KEY');
  static const firebaseAppId = String.fromEnvironment('FIREBASE_APP_ID');
  static const firebaseSenderId = String.fromEnvironment('FIREBASE_SENDER_ID');
  static const firebaseProjectId = String.fromEnvironment('FIREBASE_PROJECT_ID');
  static bool get pushEnabled => firebaseApiKey.isNotEmpty && firebaseAppId.isNotEmpty;

  /// The branch map shows only with a Google Maps key (also written into the Android manifest by Gradle).
  static const mapsApiKey = String.fromEnvironment('MAPS_API_KEY');
  static bool get mapsEnabled => mapsApiKey.isNotEmpty;

  static const supportedLocales = ['en', 'ar', 'hi', 'es', 'de'];
  static const localeNames = {'en': 'English', 'ar': 'العربية', 'hi': 'हिन्दी', 'es': 'Español', 'de': 'Deutsch'};
}

/// Upload URLs are built from the server's APP_URL; a dev server's "localhost" means this device to the app,
/// so loopback media URLs are pointed at the API host instead. Real hosts (and S3) are left alone.
String? mediaUrl(String? url) {
  final u = url == null ? null : Uri.tryParse(url);
  if (u == null || !const {'localhost', '127.0.0.1'}.contains(u.host)) return url;
  final api = Uri.parse(Config.apiBaseUrl);
  return u.replace(scheme: api.scheme, host: api.host, port: api.port).toString();
}
