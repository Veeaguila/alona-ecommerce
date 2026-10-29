import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

class TokenStore {
  static const key = 'rider_sanctum_token';
  static const secureStorage = FlutterSecureStorage();

  Future<void> save(String token) async {
    if (kIsWeb) {
      final preferences = await SharedPreferences.getInstance();
      await preferences.setString(key, token);
    } else {
      await secureStorage.write(key: key, value: token);
    }
  }

  Future<String?> read() async {
    if (kIsWeb) {
      final preferences = await SharedPreferences.getInstance();
      return preferences.getString(key);
    }
    return secureStorage.read(key: key);
  }

  Future<void> clear() async {
    if (kIsWeb) {
      final preferences = await SharedPreferences.getInstance();
      await preferences.remove(key);
      return;
    }
    await secureStorage.delete(key: key);
  }
}
