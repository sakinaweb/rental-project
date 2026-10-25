// Build-time settings (flutter run/build --dart-define=KEY=value).
class Config {
  /// Android emulator → the Docker backend on the host.
  static const apiBaseUrl = String.fromEnvironment('API_BASE_URL', defaultValue: 'http://10.0.2.2:8000/api/v1');
}

/// Photo URLs are built from the server's APP_URL; a dev server's "localhost" means this device to the app,
/// so loopback media URLs are pointed at the API host instead. Real hosts (and S3) are left alone.
String mediaUrl(String url) {
  final u = Uri.tryParse(url);
  if (u == null || !const {'localhost', '127.0.0.1'}.contains(u.host)) return url;
  final api = Uri.parse(Config.apiBaseUrl);
  return u.replace(scheme: api.scheme, host: api.host, port: api.port).toString();
}
