class LogisticsDashboardModel {
  final int ridersPendingApproval;
  final int pickupRequestsPending;
  final int parcelsAwaitingSorting;
  final int parcelsAwaitingAssignment;
  final int parcelsOutForDelivery;
  final int activeApprovedRiders;

  LogisticsDashboardModel({
    required this.ridersPendingApproval,
    required this.pickupRequestsPending,
    required this.parcelsAwaitingSorting,
    required this.parcelsAwaitingAssignment,
    required this.parcelsOutForDelivery,
    required this.activeApprovedRiders,
  });

  factory LogisticsDashboardModel.fromJson(Map<String, dynamic> json) {
    return LogisticsDashboardModel(
      ridersPendingApproval: json['riders_pending_approval'] ?? 0,
      pickupRequestsPending: json['pickup_requests_pending'] ?? 0,
      parcelsAwaitingSorting: json['parcels_awaiting_sorting'] ?? 0,
      parcelsAwaitingAssignment: json['parcels_awaiting_rider_assignment'] ?? 0,
      parcelsOutForDelivery: json['parcels_out_for_delivery'] ?? 0,
      activeApprovedRiders: json['active_approved_riders'] ?? 0,
    );
  }
}

class ParcelModel {
  final int id;
  final String orderId;
  final int? orderItemId;
  final String status;
  final String deliveryAddress;
  final String? collectionAddress;
  final String? deliveryProvince;
  final String? deliveryMunicipality;
  final String? deliveryBarangay;
  final String? deliveryStreet;
  final String? deliveryHouseNumber;
  final String? sortedAt;
  final int? sortedAreaId;
  final String? assignedRiderName;
  final String? shippingMethod;
  final Map<String, dynamic>? hubRoute;

  ParcelModel({
    required this.id,
    required this.orderId,
    this.orderItemId,
    required this.status,
    required this.deliveryAddress,
    this.collectionAddress,
    this.deliveryProvince,
    this.deliveryMunicipality,
    this.deliveryBarangay,
    this.deliveryStreet,
    this.deliveryHouseNumber,
    this.sortedAt,
    this.sortedAreaId,
    this.assignedRiderName,
    this.shippingMethod,
    this.hubRoute,
  });

  String get fullDeliveryAddress {
    if (deliveryAddress.trim().isNotEmpty) {
      return deliveryAddress.trim();
    }
    final parts = [
      deliveryHouseNumber,
      deliveryStreet,
      deliveryBarangay,
      deliveryMunicipality,
      deliveryProvince,
    ].where((s) => s != null && s.isNotEmpty).join(', ');
    return parts.isEmpty ? deliveryAddress : parts;
  }

  factory ParcelModel.fromJson(Map<String, dynamic> json) {
    String? method;
    if (json['order_item'] is Map && json['order_item']['order'] is Map) {
      method = json['order_item']['order']['shipping_method'];
    }

    return ParcelModel(
      id: json['id'] ?? 0,
      orderId: json['order_id'] ?? '',
      orderItemId: json['order_item_id'],
      status: json['status'] ?? 'UNKNOWN',
      deliveryAddress: json['delivery_address'] ?? '',
      collectionAddress: json['collection_address'],
      deliveryProvince: json['delivery_province'],
      deliveryMunicipality: json['delivery_municipality'],
      deliveryBarangay: json['delivery_barangay'],
      deliveryStreet: json['delivery_street'],
      deliveryHouseNumber: json['delivery_house_number'],
      sortedAt: json['sorted_at'],
      sortedAreaId: json['sorted_area_id'],
      assignedRiderName: json['assigned_rider'] is Map
          ? json['assigned_rider']['name']
          : null,
      shippingMethod: json['shipping_method'] ?? method,
      hubRoute: json['hub_route'] is Map
          ? Map<String, dynamic>.from(json['hub_route'])
          : null,
    );
  }
}

class AreaModel {
  final int id;
  final String name;
  final String? code;
  final String? province;
  final String? municipality;
  final int? parcelsCount;

  AreaModel({
    required this.id,
    required this.name,
    this.code,
    this.province,
    this.municipality,
    this.parcelsCount,
  });

  factory AreaModel.fromJson(Map<String, dynamic> json) {
    return AreaModel(
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
      code: json['city_code'],
      province: json['province_name'],
      municipality: json['city_name'],
      parcelsCount: json['parcels_count'],
    );
  }
}

class RiderModel {
  final int id;
  final int? userId;
  final String name;
  final String? email;
  final String? phone;
  final String? vehicleType;
  final String? plateNumber;
  final String approvalStatus; // 'pending', 'approved', 'rejected'
  final bool isActive;

  RiderModel({
    required this.id,
    this.userId,
    required this.name,
    this.email,
    this.phone,
    this.vehicleType,
    this.plateNumber,
    required this.approvalStatus,
    required this.isActive,
  });

  factory RiderModel.fromJson(Map<String, dynamic> json) {
    String? email = json['email'];
    if (json['user'] is Map) {
      email = json['user']['email'] ?? email;
    }

    return RiderModel(
      id: json['id'] ?? 0,
      userId: json['user_id'],
      name:
          json['name'] ??
          (json['user'] is Map ? json['user']['name'] : 'Rider'),
      email: email,
      phone: json['contact'] ?? json['phone'] ?? json['contact_no'],
      vehicleType: json['vehicle_type'],
      plateNumber: json['plate_number'] ?? json['plate_no'],
      approvalStatus: json['approval_status'] ?? 'pending',
      isActive: json['is_active'] == true || json['is_active'] == 1,
    );
  }
}

class PickupRequestModel {
  final int id;
  final int? sellerId;
  final String sellerName;
  final String status;
  final String requestedAt;
  final String? orderId;
  final String? collectionAddress;

  PickupRequestModel({
    required this.id,
    this.sellerId,
    required this.sellerName,
    required this.status,
    required this.requestedAt,
    this.orderId,
    this.collectionAddress,
  });

  factory PickupRequestModel.fromJson(Map<String, dynamic> json) {
    String sellerName = 'Seller';
    if (json['seller'] is Map && json['seller']['name'] != null) {
      sellerName = json['seller']['name'];
    }

    String? orderId;
    String? collAddr;
    if (json['parcel'] is Map) {
      orderId = json['parcel']['order_id'];
      collAddr = json['parcel']['collection_address'];
    }

    return PickupRequestModel(
      id: json['id'] ?? 0,
      sellerId: json['seller_id'],
      sellerName: sellerName,
      status: json['status'] ?? 'pending',
      requestedAt: json['requested_at'] ?? '',
      orderId: orderId,
      collectionAddress: collAddr,
    );
  }
}

class DeliveryProofModel {
  final int id;
  final int parcelId;
  final String riderName;
  final String type; // 'PHOTO', 'SIGNATURE', 'OTP'
  final String? filePath;
  final String? notes;
  final String? capturedAt;

  DeliveryProofModel({
    required this.id,
    required this.parcelId,
    required this.riderName,
    required this.type,
    this.filePath,
    this.notes,
    this.capturedAt,
  });

  factory DeliveryProofModel.fromJson(Map<String, dynamic> json) {
    String rName = 'Rider';
    if (json['rider'] is Map && json['rider']['name'] != null) {
      rName = json['rider']['name'];
    }

    return DeliveryProofModel(
      id: json['id'] ?? 0,
      parcelId: json['parcel_id'] ?? 0,
      riderName: rName,
      type: json['type'] ?? 'PHOTO',
      filePath: json['file_url'],
      notes: json['notes'],
      capturedAt: json['captured_at'],
    );
  }
}
