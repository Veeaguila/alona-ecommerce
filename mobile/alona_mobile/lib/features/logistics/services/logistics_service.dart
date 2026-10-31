import 'package:alona_mobile/core/network/api_client.dart';
import 'package:alona_mobile/features/logistics/models/logistics_models.dart';

class LogisticsService {
  static List<Map<String, dynamic>> rows(dynamic value) =>
      (value as List).map((row) => Map<String, dynamic>.from(row)).toList();
  static Future<Map<String, dynamic>> getData(
    String path, {
    Map<String, dynamic>? query,
  }) async {
    final response = await ApiClient.get(
      'api/sorting-center/$path',
      queryParameters: query,
    );
    return Map<String, dynamic>.from(response['data']);
  }

  static Future<Map<String, dynamic>> act(
    String path, {
    Map<String, dynamic>? body,
  }) async => Map<String, dynamic>.from(
    await ApiClient.post('api/sorting-center/$path', body: body),
  );
  static Future<List<ParcelModel>> getAssignableParcels() async {
    final res = await ApiClient.get(
      'api/sorting-center/parcels',
      queryParameters: {'status': 'sorted-unassigned'},
    );
    return rows(res['data']).map(ParcelModel.fromJson).toList();
  }

  // Authentication
  static Future<Map<String, dynamic>> login(
    String email,
    String password,
  ) async {
    final res = await ApiClient.post(
      'api/sorting-center/login',
      body: {'email': email, 'password': password},
    );
    return res as Map<String, dynamic>;
  }

  // Dashboard
  static Future<LogisticsDashboardModel> getDashboard() async {
    final res = await ApiClient.get('api/sorting-center/dashboard');
    final data = res as Map<String, dynamic>;
    return LogisticsDashboardModel.fromJson(
      Map<String, dynamic>.from(data['data'] ?? {}),
    );
  }

  // Parcel Sorting
  static Future<List<ParcelModel>> getUnsortedParcels() async {
    final res = await ApiClient.get('api/sorting-center/parcels');
    final data = res as Map<String, dynamic>;
    final list = data['data'] as List? ?? [];
    return list
        .map((p) => ParcelModel.fromJson(Map<String, dynamic>.from(p)))
        .toList();
  }

  static Future<List<AreaModel>> getAreas() async {
    final res = await ApiClient.get('api/sorting-center/areas');
    final data = res as Map<String, dynamic>;
    final list = data['data'] as List? ?? [];
    return list
        .map((a) => AreaModel.fromJson(Map<String, dynamic>.from(a)))
        .toList();
  }

  static Future<void> createArea({required String name}) async {
    await ApiClient.post('api/sorting-center/areas', body: {'name': name});
  }

  static Future<void> sortParcel(int parcelId, int areaId) async {
    await ApiClient.post(
      'api/sorting-center/parcels/$parcelId/sort',
      body: {'area_id': areaId},
    );
  }

  // Rider Assignment
  static Future<List<RiderModel>> getEligibleRiders(int parcelId) async {
    final res = await ApiClient.get(
      'api/sorting-center/parcels/$parcelId/eligible-riders',
    );
    final data = res as Map<String, dynamic>;
    final list = data['data'] as List? ?? [];
    return list
        .map((r) => RiderModel.fromJson(Map<String, dynamic>.from(r)))
        .toList();
  }

  static Future<void> assignRider(int parcelId, int riderId) async {
    await ApiClient.post(
      'api/sorting-center/parcels/$parcelId/assign',
      body: {'rider_id': riderId},
    );
  }

  // Monitoring
  static Future<List<ParcelModel>> getMonitoringParcels() async {
    final result = <ParcelModel>[];
    var page = 1;
    while (true) {
      final data = await getData('parcels/monitoring', query: {'page': page});
      result.addAll(rows(data['data']).map(ParcelModel.fromJson));
      if (page >= (data['last_page'] as int)) return result;
      page++;
    }
  }

  // Incoming Scan / Arrive
  static Future<ParcelModel> lookupIncoming(String orderId) async {
    final res = await ApiClient.get(
      'api/sorting-center/parcels/lookup/${Uri.encodeComponent(orderId)}',
    );
    final data = res as Map<String, dynamic>;
    return ParcelModel.fromJson(Map<String, dynamic>.from(data['data'] ?? {}));
  }

  static Future<void> markArrived(String orderId, {int? hubId}) async {
    await ApiClient.post(
      'api/sorting-center/parcels/mark-arrived',
      body: {'order_id': orderId, 'hub_id': ?hubId},
    );
  }

  // Riders
  static Future<List<RiderModel>> getRiders({String? status}) async {
    final query = <String, dynamic>{
      if (status != null && status.isNotEmpty) 'status': status,
    };
    final res = await ApiClient.get(
      'api/sorting-center/riders',
      queryParameters: query,
    );
    final data = res as Map<String, dynamic>;
    final list = data['data'] as List? ?? [];
    return list
        .map((r) => RiderModel.fromJson(Map<String, dynamic>.from(r)))
        .toList();
  }

  static Future<void> approveRider(int id) async {
    await ApiClient.post('api/sorting-center/riders/$id/approve');
  }

  static Future<void> rejectRider(int id, String? reason) async {
    await ApiClient.post(
      'api/sorting-center/riders/$id/reject',
      body: {'reason': ?reason},
    );
  }

  static Future<void> activateRider(int id) async {
    await ApiClient.post('api/sorting-center/riders/$id/activate');
  }

  static Future<void> deactivateRider(int id, String reason) async {
    await ApiClient.post(
      'api/sorting-center/riders/$id/deactivate',
      body: {'reason': reason},
    );
  }

  // Pickup Requests
  static Future<List<PickupRequestModel>> getPickupRequests({
    String status = 'pending',
  }) async {
    final result = <PickupRequestModel>[];
    var page = 1;
    while (true) {
      final data = await getData(
        'pickup-requests',
        query: {'status': status, 'page': page},
      );
      result.addAll(rows(data['data']).map(PickupRequestModel.fromJson));
      if (page >= (data['last_page'] as int)) return result;
      page++;
    }
  }

  static Future<void> confirmPickup(int id) async {
    await ApiClient.post('api/sorting-center/pickup-requests/$id/confirm');
  }

  // Delivery Proofs
  static Future<List<DeliveryProofModel>> getProofs(int parcelId) async {
    final res = await ApiClient.get(
      'api/sorting-center/parcels/$parcelId/proofs',
    );
    final data = res as Map<String, dynamic>;
    final proofs = data['data']?['proofs'] as List? ?? [];
    return proofs
        .map((p) => DeliveryProofModel.fromJson(Map<String, dynamic>.from(p)))
        .toList();
  }

  // Reports
  static Future<Map<String, dynamic>> getReports(String from, String to) async {
    final res = await ApiClient.get(
      'api/sorting-center/reports',
      queryParameters: {'from': from, 'to': to},
    );
    final data = res as Map<String, dynamic>;
    return data['data'] as Map<String, dynamic>? ?? {};
  }
}
