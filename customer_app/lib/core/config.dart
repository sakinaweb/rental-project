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
