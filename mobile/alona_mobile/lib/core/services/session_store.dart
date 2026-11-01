import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Native credentials use platform secure storage. Web keeps the existing
/// preference storage boundary and must run on a trusted HTTPS origin.
class SessionStore {
  const SessionStore();
  static const _storage = FlutterSecureStorage();
  Future<String?> read(String key) async {
    final prefs = await SharedPreferences.getInstance();
    if (kIsWeb) return prefs.getString(key);
    final token = await _storage.read(key: key);
    // Do not carry an upstream plaintext login across this integration upgrade.
    await prefs.remove(key);
    return token;
  }

  Future<void> write(String key, String value) async {
    if (kIsWeb) {
      await (await SharedPreferences.getInstance()).setString(key, value);
    } else {
      await _storage.write(key: key, value: value);
      await (await SharedPreferences.getInstance()).remove(key);
    }
  }

  Future<void> remove(String key) async {
    await (await SharedPreferences.getInstance()).remove(key);
    if (!kIsWeb) await _storage.delete(key: key);
  }
}
