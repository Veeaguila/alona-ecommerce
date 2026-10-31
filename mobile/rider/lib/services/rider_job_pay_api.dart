import 'rider_portal_api.dart';
import 'rider_session.dart';

String jobMoney(Object? value) {
  if (value is! String || !RegExp(r'^(0|[1-9]\d*)\.\d{2}$').hasMatch(value)) {
    throw const FormatException('Invalid recorded amount');
  }
  return value;
}

class JobPayPage {
  final Map<String, dynamic> summary;
  final Map<String, dynamic> earnings, payouts;
  final List<Map<String, dynamic>> destinations;
  JobPayPage(Map<String, dynamic> json)
    : summary = Map<String, dynamic>.from(json['summary'] as Map),
      earnings = Map<String, dynamic>.from(json['earnings'] as Map),
      payouts = Map<String, dynamic>.from(json['payouts'] as Map),
      destinations = (json['destinations'] as List)
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList() {
    if (summary['currency'] != 'PHP') throw const FormatException();
    for (final name in ['earned', 'unpaid', 'processing', 'paid']) {
      jobMoney(summary[name]);
    }
    for (final collection in [earnings, payouts]) {
      if (collection['current_page'] is! int ||
          collection['last_page'] is! int ||
          collection['data'] is! List) {
        throw const FormatException();
      }
      for (final row in collection['data'] as List) {
        jobMoney(row['amount']);
      }
    }
  }
}

class RiderJobPayApi {
  RiderJobPayApi(RiderSession session) : portal = RiderPortalApi(session);
  final RiderPortalApi portal;
  Future<JobPayPage> load({int earningsPage = 1, int payoutsPage = 1}) async {
    final result = await portal.request(
      'rider/job-pay?earnings_page=$earningsPage&payouts_page=$payoutsPage',
    );
    try {
      return JobPayPage(Map<String, dynamic>.from(result['data'] as Map));
    } catch (_) {
      throw const SessionFailure(
        'Earnings could not be read. Refresh to retry.',
      );
    }
  }

  Future<void> destination(Map<String, dynamic> body) async {
    await portal.request(
      'rider/job-pay/destinations',
      method: 'POST',
      body: body,
    );
  }

  Future<void> acknowledge(int id) async {
    await portal.request(
      'rider/job-pay/payouts/$id/acknowledge',
      method: 'POST',
      body: {'received': true},
    );
  }
}
