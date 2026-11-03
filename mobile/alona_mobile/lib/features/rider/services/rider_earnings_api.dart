import 'rider_portal_api.dart';
import 'rider_session.dart';

class RiderEarning {
  final int shipmentId;
  final String amount, status, earnedAt;
  final String? paidAt;

  RiderEarning(Map<String, dynamic> row)
    : shipmentId = _positiveInt((row['charge'] as Map)['seller_shipment_id']),
      amount = _money(row['amount']),
      status = _status(row['payout_status']),
      earnedAt = _date(row['earned_at']),
      paidAt = row['paid_at'] == null ? null : _date(row['paid_at']) {
    if ((row['charge'] as Map)['currency'] != 'PHP') {
      throw const FormatException();
    }
  }
}

class RiderEarningsPage {
  final String total, pending, paid;
  final int page, lastPage;
  final List<RiderEarning> rows;

  RiderEarningsPage(Map<String, dynamic> payload)
    : total = _money((payload['summary'] as Map)['total']),
      pending = _money((payload['summary'] as Map)['outstanding']),
      paid = _money((payload['summary'] as Map)['paid']),
      page = _positiveInt((payload['meta'] as Map)['current_page']),
      lastPage = _positiveInt((payload['meta'] as Map)['last_page']),
      rows = (payload['data'] as List)
          .map((row) => RiderEarning(Map<String, dynamic>.from(row as Map)))
          .toList(growable: false) {
    if ((payload['summary'] as Map)['currency'] != 'PHP') {
      throw const FormatException();
    }
  }
}

class RiderEarningsApi {
  final RiderPortalApi portal;
  RiderEarningsApi(RiderSession session) : portal = RiderPortalApi(session);

  Future<RiderEarningsPage> load({int page = 1}) async {
    if (page < 1) throw ArgumentError.value(page, 'page');
    final payload = await portal.request('rider/earnings?page=$page');
    try {
      return RiderEarningsPage(payload);
    } catch (_) {
      throw const SessionFailure(
        'Earnings could not be read. Please refresh and retry.',
      );
    }
  }
}

// Amounts remain backend decimal strings. No rate, count, sum or payout arithmetic.
String _money(Object? value) {
  if (value is! String || !RegExp(r'^(0|[1-9]\d*)\.\d{2}$').hasMatch(value)) {
    throw const FormatException();
  }
  return value;
}

int _positiveInt(Object? value) {
  if (value is! int || value < 1) throw const FormatException();
  return value;
}

String _status(Object? value) {
  if (value != 'paid' && value != 'unpaid') throw const FormatException();
  return value as String;
}

String _date(Object? value) {
  if (value is! String || DateTime.tryParse(value) == null) {
    throw const FormatException();
  }
  return value;
}
