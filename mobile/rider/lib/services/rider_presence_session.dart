import 'dart:async';

import 'package:flutter/widgets.dart';

import 'rider_portal_api.dart';
import 'rider_session.dart';

class RiderPresenceSession extends ChangeNotifier with WidgetsBindingObserver {
  final RiderSession session;
  final Duration interval;
  RiderPresenceSession(
    this.session, {
    this.interval = const Duration(seconds: 30),
  });
  String presence = 'Unknown';
  String? error;
  bool busy = false;
  bool _wantedOnline = false;
  int _listeners = 0;
  int _revision = 0;
  Timer? _timer;

  void attach() {
    if (_listeners++ == 0) {
      WidgetsBinding.instance.addObserver(this);
      unawaited(load());
    }
  }

  void detach() {
    if (--_listeners > 0) return;
    _listeners = 0;
    _revision++;
    _timer?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    final wasOnline = _wantedOnline;
    _wantedOnline = false;
    if (wasOnline) {
      unawaited(
        RiderPortalApi(session)
            .request(
              'rider/presence',
              method: 'POST',
              body: {'action': 'go_offline'},
              ready: false,
            )
            .catchError((_) => <String, dynamic>{}),
      );
    }
  }

  Future<void> load() async {
    if (busy) return;
    final revision = _revision;
    busy = true;
    try {
      final result = await RiderPortalApi(session).request('rider/presence');
      if (revision != _revision) return;
      final data = result['data'] as Map;
      presence = _parse(data);
      _wantedOnline =
          presence == 'Online' && data['is_current_session'] == true;
      error = null;
      _schedule();
    } catch (failure) {
      if (revision == _revision) {
        presence = 'Unknown';
        error = failure.toString();
      }
    } finally {
      busy = false;
      if (_listeners > 0) notifyListeners();
    }
  }

  Future<void> setOnline(bool online) async {
    if (busy) return;
    final revision = _revision;
    busy = true;
    error = null;
    notifyListeners();
    try {
      final result = await RiderPortalApi(session).request(
        'rider/presence',
        method: 'POST',
        body: {'action': online ? 'go_online' : 'go_offline'},
      );
      if (revision != _revision) return;
      presence = _parse(result['data'] as Map);
      _wantedOnline = online && presence == 'Online';
      _schedule();
    } catch (failure) {
      if (revision == _revision) {
        error = failure.toString();
        presence = 'Unknown';
        _wantedOnline = false;
        _timer?.cancel();
      }
    } finally {
      busy = false;
      if (_listeners > 0) notifyListeners();
    }
  }

  void _schedule() {
    _timer?.cancel();
    if (_wantedOnline && _listeners > 0) {
      _timer = Timer.periodic(interval, (_) => _heartbeat());
    }
  }

  Future<void> _heartbeat() async {
    if (busy || !_wantedOnline || _listeners == 0) return;
    final lifecycle = WidgetsBinding.instance.lifecycleState;
    if (lifecycle != null && lifecycle != AppLifecycleState.resumed) return;
    final revision = _revision;
    busy = true;
    try {
      final result = await RiderPortalApi(session).request(
        'rider/presence',
        method: 'POST',
        body: {'action': 'heartbeat'},
      );
      if (revision != _revision) return;
      presence = _parse(result['data'] as Map);
      error = null;
    } catch (failure) {
      if (revision == _revision) {
        presence = 'Unknown';
        error = failure.toString();
        _wantedOnline = false;
        _timer?.cancel();
      }
    } finally {
      busy = false;
      if (_listeners > 0) notifyListeners();
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (_listeners == 0) return;
    if (state == AppLifecycleState.resumed) {
      if (_wantedOnline) {
        unawaited(setOnline(true));
      } else {
        unawaited(load());
      }
    } else if (state == AppLifecycleState.paused ||
        state == AppLifecycleState.detached) {
      _timer?.cancel();
      // Expiry also handles app termination/network loss when this best-effort message cannot arrive.
      if (_wantedOnline) {
        unawaited(
          RiderPortalApi(session)
              .request(
                'rider/presence',
                method: 'POST',
                body: {'action': 'go_offline'},
                ready: false,
              )
              .catchError((_) => <String, dynamic>{}),
        );
      }
    }
  }

  String _parse(Map data) {
    final value = data['presence'];
    if (!['Online', 'Offline', 'Unknown'].contains(value)) {
      throw const FormatException('Invalid presence response.');
    }
    return value as String;
  }
}
