import 'package:flutter/material.dart';

import '../pages/login_page.dart';
import 'rider_session.dart';

mixin RiderSessionGuard<T extends StatefulWidget> on State<T> {
  RiderSession get riderSession;

  Future<void> checkAuthorization(int statusCode) async {
    if (statusCode == 401) {
      await riderSession.tokens.clear();
      await readyToken();
    } else if (statusCode == 403) {
      // A parcel ownership error must not sign out an otherwise eligible rider.
      await readyToken();
    }
  }

  Future<String> readyToken() => sessionAction(riderSession.requireReadyToken);

  Future<R> sessionAction<R>(Future<R> Function() action) async {
    try {
      return await action();
    } on SessionFailure catch (failure) {
      if (failure.accessDenied && mounted) {
        Navigator.of(context).pushAndRemoveUntil(
          MaterialPageRoute(
            builder: (_) => LoginPage(
              initialMessage: failure.message,
              session: riderSession,
            ),
          ),
          (route) => false,
        );
      }
      rethrow;
    }
  }
}
