import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:alona_mobile/core/network/api_client.dart';
import 'package:alona_mobile/core/config/api_config.dart';
import 'package:alona_mobile/core/services/session_store.dart';

class AuthState extends ChangeNotifier {
  AuthState({this.sessionStore = const SessionStore()});
  final SessionStore sessionStore;
  int _sessionRevision = 0;
  int get sessionRevision => _sessionRevision;
  String _activeModule = 'buyer'; // 'buyer' or 'logistics'

  // Buyer
  String? _buyerToken;
  Map<String, dynamic>? _buyerUser;

  // Logistics
  String? _logisticsToken;
  Map<String, dynamic>? _logisticsUser;
  Map<String, dynamic>? _sortingCenter;
  Map<String, dynamic>? _operator;
  String? _accountStatus;
  Map<String, dynamic>? get logisticsOperator => _operator;
  bool get isHubStaff => _operator?['role'] == 'hub_staff';
  bool get canOperateLogistics =>
      _accountStatus == 'approved' &&
      _sortingCenter?['approval_status'] == 'approved';

  String get activeModule => _activeModule;
  bool get isBuyerModule => _activeModule == 'buyer';
  bool get isLogisticsModule => _activeModule == 'logistics';

  // Buyer getters
  String? get buyerToken => _buyerToken;
  Map<String, dynamic>? get buyerUser => _buyerUser;
  bool get isBuyerAuthenticated =>
      _buyerToken != null && _buyerToken!.isNotEmpty;

  // Logistics getters
  String? get logisticsToken => _logisticsToken;
  Map<String, dynamic>? get logisticsUser => _logisticsUser;
  Map<String, dynamic>? get sortingCenter => _sortingCenter;
  bool get isLogisticsAuthenticated =>
      _logisticsToken != null && _logisticsToken!.isNotEmpty;

  Future<void> init() async {
    final prefs = await SharedPreferences.getInstance();
    if (prefs.getString('session_server') != ApiConfig.baseUrl) {
      await sessionStore.remove('buyer_token');
      await sessionStore.remove('logistics_token');
      for (final key in [
        'buyer_user',
        'logistics_user',
        'sorting_center',
        'logistics_operator',
        'logistics_account_status',
      ]) {
        await prefs.remove(key);
      }
      await prefs.setString('session_server', ApiConfig.baseUrl);
    }
    _activeModule = prefs.getString('active_module') ?? 'buyer';

    _buyerToken = await sessionStore.read('buyer_token');
    final buyerUserStr = prefs.getString('buyer_user');
    if (buyerUserStr != null) {
      try {
        _buyerUser = jsonDecode(buyerUserStr);
      } catch (_) {}
    }

    _logisticsToken = await sessionStore.read('logistics_token');
    _accountStatus = prefs.getString('logistics_account_status');
    final operator = prefs.getString('logistics_operator');
    if (operator != null) {
      try {
        _operator = Map<String, dynamic>.from(jsonDecode(operator));
      } catch (_) {}
    }
    final logisticsUserStr = prefs.getString('logistics_user');
    if (logisticsUserStr != null) {
      try {
        _logisticsUser = jsonDecode(logisticsUserStr);
      } catch (_) {}
    }
    final scStr = prefs.getString('sorting_center');
    if (scStr != null) {
      try {
        _sortingCenter = jsonDecode(scStr);
      } catch (_) {}
    }

    ApiClient.setBuyerToken(_buyerToken);
    ApiClient.setLogisticsToken(_logisticsToken);
    ApiClient.setCurrentRole(_activeModule);
    ApiClient.onUnauthorized = (role, token) async {
      if (role == 'logistics' && token == _logisticsToken) {
        await clearLogisticsSession();
      }
      if (role == 'buyer' && token == _buyerToken) await clearBuyerSession();
    };
    ApiConfig.onServerChanged = () async {
      await clearBuyerSession();
      await clearLogisticsSession();
    };

    notifyListeners();
  }

  Future<void> switchModule(String module) async {
    if (module != 'buyer' && module != 'logistics') return;
    _activeModule = module;
    ApiClient.setCurrentRole(_activeModule);

    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('active_module', _activeModule);
    notifyListeners();
  }

  Future<void> setBuyerSession(String token, Map<String, dynamic> user) async {
    _sessionRevision++;
    _buyerToken = token;
    _buyerUser = user;
    ApiClient.setBuyerToken(token);

    final prefs = await SharedPreferences.getInstance();
    await sessionStore.write('buyer_token', token);
    await prefs.setString('session_server', ApiConfig.baseUrl);
    await prefs.setString('buyer_user', jsonEncode(user));
    notifyListeners();
  }

  Future<void> updateBuyerUser(Map<String, dynamic> user) async {
    _buyerUser = user;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('buyer_user', jsonEncode(user));
    notifyListeners();
  }

  Future<void> clearBuyerSession() async {
    _sessionRevision++;
    _buyerToken = null;
    _buyerUser = null;
    ApiClient.setBuyerToken(null);

    final prefs = await SharedPreferences.getInstance();
    await sessionStore.remove('buyer_token');
    await prefs.remove('buyer_user');
    notifyListeners();
  }

  Future<void> setLogisticsSession(
    String token,
    Map<String, dynamic> user,
    Map<String, dynamic>? sortingCenter, {
    Map<String, dynamic>? operator,
    String? accountStatus,
  }) async {
    _sessionRevision++;
    _logisticsToken = token;
    _logisticsUser = user;
    _sortingCenter = sortingCenter;
    _operator = operator;
    _accountStatus = accountStatus;
    ApiClient.setLogisticsToken(token);

    final prefs = await SharedPreferences.getInstance();
    await sessionStore.write('logistics_token', token);
    await prefs.setString('session_server', ApiConfig.baseUrl);
    if (operator != null) {
      await prefs.setString('logistics_operator', jsonEncode(operator));
    } else {
      await prefs.remove('logistics_operator');
    }
    if (accountStatus != null) {
      await prefs.setString('logistics_account_status', accountStatus);
    } else {
      await prefs.remove('logistics_account_status');
    }
    await prefs.setString('logistics_user', jsonEncode(user));
    if (sortingCenter != null) {
      await prefs.setString('sorting_center', jsonEncode(sortingCenter));
    } else {
      await prefs.remove('sorting_center');
    }
    notifyListeners();
  }

  Future<void> clearLogisticsSession() async {
    _sessionRevision++;
    _logisticsToken = null;
    _logisticsUser = null;
    _sortingCenter = null;
    _operator = null;
    _accountStatus = null;
    ApiClient.setLogisticsToken(null);

    final prefs = await SharedPreferences.getInstance();
    await sessionStore.remove('logistics_token');
    await prefs.remove('logistics_operator');
    await prefs.remove('logistics_account_status');
    await prefs.remove('logistics_user');
    await prefs.remove('sorting_center');
    notifyListeners();
  }
}
