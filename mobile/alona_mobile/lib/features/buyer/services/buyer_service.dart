import 'package:alona_mobile/core/network/api_client.dart';
import 'package:alona_mobile/features/buyer/models/buyer_models.dart';

class BuyerService {
  // Home Data
  static Future<Map<String, dynamic>> getHome() async {
    final res = await ApiClient.get('api/marketplace/home');
    final data = res as Map<String, dynamic>;

    final categories = (data['categories'] as List? ?? [])
        .map((c) => CategoryModel.fromJson(Map<String, dynamic>.from(c)))
        .toList();

    final featured = (data['featured_products'] as List? ?? [])
        .map((p) => ProductModel.fromJson(Map<String, dynamic>.from(p)))
        .toList();

    final topSelling = (data['top_selling_products'] as List? ?? [])
        .map((p) => ProductModel.fromJson(Map<String, dynamic>.from(p)))
        .toList();

    final recommended = (data['recommended_products'] as List? ?? [])
        .map((p) => ProductModel.fromJson(Map<String, dynamic>.from(p)))
        .toList();

    final flashSales = (data['flash_sales'] as List? ?? [])
        .map((s) => FlashSaleModel.fromJson(Map<String, dynamic>.from(s)))
        .toList();

    final promotions = (data['promotions'] as List? ?? [])
        .map((v) => VoucherModel.fromJson(Map<String, dynamic>.from(v)))
        .toList();

    final trending = (data['trending_products'] as List? ?? [])
        .map((p) => ProductModel.fromJson(Map<String, dynamic>.from(p)))
        .toList();

    final heroBanners = (data['hero_banners'] as List? ?? [])
        .map((b) => HeroBannerModel.fromJson(Map<String, dynamic>.from(b)))
        .toList();

    final heroStats = data['hero_stats'] is Map
        ? HeroStatsModel.fromJson(Map<String, dynamic>.from(data['hero_stats']))
        : const HeroStatsModel();

    return {
      'categories': categories,
      'featured_products': featured,
      'top_selling_products': topSelling,
      'recommended_products': recommended,
      'trending_products': trending,
      'flash_sales': flashSales,
      'flash_sale_end_time': data['flash_sale_end_time'],
      'promotions': promotions,
      'hero_banners': heroBanners,
      'hero_stats': heroStats,
    };
  }

  // Products
  static Future<Map<String, dynamic>> getProducts({
    String? search,
    int? categoryId,
    String? categorySlug,
    double? minPrice,
    double? maxPrice,
    String? sort,
    int page = 1,
  }) async {
    final query = <String, dynamic>{
      'page': page,
      if (search != null && search.isNotEmpty) 'search': search,
      'category_id': ?categoryId,
      if (categorySlug != null && categorySlug.isNotEmpty) 'category_slug': categorySlug,
      'min_price': ?minPrice,
      'max_price': ?maxPrice,
      if (sort != null && sort.isNotEmpty) 'sort': sort,
    };

    final res = await ApiClient.get('api/marketplace/products', queryParameters: query);
    final data = res as Map<String, dynamic>;

    final items = (data['data'] as List? ?? [])
        .map((p) => ProductModel.fromJson(Map<String, dynamic>.from(p)))
        .toList();

    return {
      'products': items,
      'current_page': data['current_page'] ?? 1,
      'last_page': data['last_page'] ?? 1,
      'total': data['total'] ?? 0,
    };
  }

  static Future<Map<String, dynamic>> getProduct(int id) async {
    final res = await ApiClient.get('api/marketplace/products/$id');
    final data = res as Map<String, dynamic>;
    final product = ProductModel.fromJson(Map<String, dynamic>.from(data['product']));
    final related = (data['related_products'] as List? ?? [])
        .map((p) => ProductModel.fromJson(Map<String, dynamic>.from(p)))
        .toList();

    return {
      'product': product,
      'related': related,
    };
  }

  static Future<List<CategoryModel>> getCategories() async {
    final res = await ApiClient.get('api/marketplace/categories');
    final data = res as Map<String, dynamic>;
    return (data['categories'] as List? ?? [])
        .map((c) => CategoryModel.fromJson(Map<String, dynamic>.from(c)))
        .toList();
  }

  static Future<List<ProductModel>> getYouMayAlsoLike({
    Set<int> excludeIds = const {},
    int limit = 8,
  }) async {
    final Map<int, ProductModel> combined = {};

    try {
      final home = await getHome();
      final recommended = home['recommended_products'] as List<ProductModel>? ?? [];
      final featured = home['featured_products'] as List<ProductModel>? ?? [];
      final topSelling = home['top_selling_products'] as List<ProductModel>? ?? [];

      for (final p in [...recommended, ...featured, ...topSelling]) {
        combined.putIfAbsent(p.id, () => p);
      }
    } catch (_) {}

    if (combined.length < limit) {
      try {
        final res = await getProducts(page: 1);
        final products = res['products'] as List<ProductModel>? ?? [];
        for (final p in products) {
          combined.putIfAbsent(p.id, () => p);
        }
      } catch (_) {}
    }

    final all = combined.values.toList();
    if (excludeIds.isEmpty) {
      return all.take(limit).toList();
    }

    final filtered = all.where((p) => !excludeIds.contains(p.id)).toList();
    if (filtered.isNotEmpty) {
      return filtered.take(limit).toList();
    }
    return all.take(limit).toList();
  }

  // Cart
  static Future<Map<String, dynamic>> getCart() async {
    final res = await ApiClient.get('api/buyer/cart');
    final data = res as Map<String, dynamic>;
    final items = (data['items'] as List? ?? [])
        .map((i) => CartItemModel.fromJson(Map<String, dynamic>.from(i)))
        .toList();

    return {
      'items': items,
      'subtotal': parseDouble(data['subtotal']),
      'discount': parseDouble(data['discount']),
      'total': parseDouble(data['total']),
      'item_count': parseInt(data['item_count']),
    };
  }

  static Future<Map<String, dynamic>> addToCart({
    required int productId,
    int? variantId,
    int quantity = 1,
  }) async {
    final res = await ApiClient.post('api/buyer/cart', body: {
      'product_id': productId,
      'product_variant_id': variantId,
      'quantity': quantity,
    });
    return res is Map<String, dynamic> ? res : {};
  }

  static Future<void> updateCartItem(int id, int quantity) async {
    await ApiClient.patch('api/buyer/cart/$id', body: {'quantity': quantity});
  }

  static Future<void> removeCartItem(int id) async {
    await ApiClient.delete('api/buyer/cart/$id');
  }

  // Wishlist
  static Future<List<ProductModel>> getWishlist() async {
    final res = await ApiClient.get('api/buyer/wishlist');
    final data = res as Map<String, dynamic>;
    return (data['wishlist'] as List? ?? [])
        .map((p) => ProductModel.fromJson(Map<String, dynamic>.from(p)))
        .toList();
  }

  static Future<bool> toggleWishlist(int productId) async {
    final res = await ApiClient.post('api/buyer/wishlist/$productId/toggle');
    final data = res as Map<String, dynamic>;
    return data['is_wishlisted'] == true;
  }

  // Store Follow
  static Future<Map<String, dynamic>> toggleFollowStore(int sellerId) async {
    final res = await ApiClient.post('api/buyer/store/$sellerId/toggle-follow');
    return res as Map<String, dynamic>;
  }

  // Vouchers
  static Future<Map<String, dynamic>> getVouchers() async {
    final res = await ApiClient.get('api/buyer/vouchers');
    final data = res as Map<String, dynamic>;

    final claimed = (data['claimed'] as List? ?? [])
        .map((v) => VoucherModel.fromJson(Map<String, dynamic>.from(v)))
        .toList();

    final available = (data['available'] as List? ?? [])
        .map((v) => VoucherModel.fromJson(Map<String, dynamic>.from(v)))
        .toList();

    return {
      'claimed': claimed,
      'available': available,
    };
  }

  static Future<void> claimVoucher(int id) async {
    await ApiClient.post('api/buyer/vouchers/$id/claim');
  }

  static Future<Map<String, dynamic>> validateVoucher({
    required String code,
    required List<int> cartItemIds,
    String shippingMethod = 'standard',
  }) async {
    final res = await ApiClient.post('api/buyer/vouchers/validate', body: {
      'code': code,
      'cart_item_ids': cartItemIds,
      'shipping_method': shippingMethod,
    });
    return res as Map<String, dynamic>;
  }

  // Checkout & Orders
  static Future<Map<String, dynamic>> previewCheckout({
    required List<int> cartItemIds,
    String shippingMethod = 'standard',
    String? shippingAddress,
    String? voucherCode,
  }) async {
    final res = await ApiClient.post('api/buyer/checkout/preview', body: {
      'cart_item_ids': cartItemIds,
      'shipping_method': shippingMethod,
      if (shippingAddress != null && shippingAddress.isNotEmpty) 'shipping_address': shippingAddress,
      if (voucherCode != null && voucherCode.isNotEmpty) 'voucher_code': voucherCode,
    });
    return res as Map<String, dynamic>;
  }

  static Future<Map<String, dynamic>> previewCheckoutDirect({
    required int productId,
    int? variantId,
    required int quantity,
    String shippingMethod = 'standard',
    String? shippingAddress,
    String? voucherCode,
  }) async {
    final res = await ApiClient.post('api/buyer/checkout/preview', body: {
      'buy_now': true,
      'product_id': productId,
      'product_variant_id': ?variantId,
      'quantity': quantity,
      'shipping_method': shippingMethod,
      if (shippingAddress != null && shippingAddress.isNotEmpty) 'shipping_address': shippingAddress,
      if (voucherCode != null && voucherCode.isNotEmpty) 'voucher_code': voucherCode,
    });
    return res as Map<String, dynamic>;
  }

  static Future<Map<String, dynamic>> placeOrder({
    required List<int> cartItemIds,
    required String shippingAddress,
    required String shippingMethod,
    required String paymentMethod,
    String? buyerNote,
    String? voucherCode,
    Map<String, dynamic>? quote,
    String? requestKey,
  }) async {
    final body = <String, dynamic>{
      'cart_item_ids': cartItemIds,
      'shipping_address': shippingAddress,
      'shipping_method': shippingMethod,
      'payment_method': paymentMethod,
      if (buyerNote != null && buyerNote.isNotEmpty) 'buyer_note': buyerNote,
      if (voucherCode != null && voucherCode.isNotEmpty) 'voucher_code': voucherCode,
      if (requestKey != null && requestKey.isNotEmpty) 'request_key': requestKey,
    };
    if (quote != null) {
      if (quote['total'] != null) body['expected_total'] = quote['total'];
      if (quote['shipping_fee_before_discount'] != null) {
        body['expected_shipping_fee'] = quote['shipping_fee_before_discount'];
      } else if (quote['shipping_fee'] != null) {
        body['expected_shipping_fee'] = quote['shipping_fee'];
      }
      if (quote['expected_prices'] != null) body['expected_prices'] = quote['expected_prices'];
    }
    final res = await ApiClient.post('api/buyer/orders', body: body);
    return res as Map<String, dynamic>;
  }

  static Future<Map<String, dynamic>> placeOrderDirect({
    required int productId,
    int? variantId,
    required int quantity,
    required String shippingAddress,
    required String shippingMethod,
    required String paymentMethod,
    String? buyerNote,
    String? voucherCode,
    Map<String, dynamic>? quote,
    String? requestKey,
  }) async {
    final body = <String, dynamic>{
      'buy_now': true,
      'product_id': productId,
      'product_variant_id': ?variantId,
      'quantity': quantity,
      'shipping_address': shippingAddress,
      'shipping_method': shippingMethod,
      'payment_method': paymentMethod,
      if (buyerNote != null && buyerNote.isNotEmpty) 'buyer_note': buyerNote,
      if (voucherCode != null && voucherCode.isNotEmpty) 'voucher_code': voucherCode,
      if (requestKey != null && requestKey.isNotEmpty) 'request_key': requestKey,
    };
    if (quote != null) {
      if (quote['total'] != null) body['expected_total'] = quote['total'];
      if (quote['shipping_fee_before_discount'] != null) {
        body['expected_shipping_fee'] = quote['shipping_fee_before_discount'];
      } else if (quote['shipping_fee'] != null) {
        body['expected_shipping_fee'] = quote['shipping_fee'];
      }
      if (quote['expected_prices'] != null) body['expected_prices'] = quote['expected_prices'];
    }
    final res = await ApiClient.post('api/buyer/orders', body: body);
    return res as Map<String, dynamic>;
  }

  static Future<Map<String, dynamic>> getDeliveryOtp(int orderId, int parcelId, {bool renew = false}) async {
    final endpoint = renew
        ? 'api/buyer/orders/$orderId/parcels/$parcelId/delivery-otp/renew'
        : 'api/buyer/orders/$orderId/parcels/$parcelId/delivery-otp';
    final res = renew
        ? await ApiClient.post(endpoint)
        : await ApiClient.get(endpoint);
    return res as Map<String, dynamic>;
  }

  static Future<Map<String, dynamic>> getOrders({String status = 'all', int page = 1}) async {
    final res = await ApiClient.get('api/buyer/orders', queryParameters: {
      'status': status,
      'page': page,
    });
    final data = res as Map<String, dynamic>;

    final rawOrders = (data['orders'] is Map && data['orders']['data'] is List)
        ? data['orders']['data'] as List
        : (data['orders'] is List ? data['orders'] as List : []);

    final orders = rawOrders
        .map((o) => OrderModel.fromJson(Map<String, dynamic>.from(o)))
        .toList();

    return {
      'orders': orders,
      'counts': data['counts'] ?? {},
      'current_page': data['orders'] is Map ? data['orders']['current_page'] ?? page : page,
      'last_page': data['orders'] is Map ? data['orders']['last_page'] ?? 1 : 1,
    };
  }

  static Future<Map<String, dynamic>> getOrderDetails(int id) async {
    final res = await ApiClient.get('api/buyer/orders/$id');
    final data = res as Map<String, dynamic>;
    final rawOrder = data['order'] is Map
        ? Map<String, dynamic>.from(data['order'] as Map)
        : <String, dynamic>{};
    final order = OrderModel.fromJson(rawOrder);

    final deliveryParcels = <Map<String, dynamic>>[];
    if (data['delivery_parcels'] is List) {
      for (final p in (data['delivery_parcels'] as List)) {
        if (p is Map) {
          deliveryParcels.add(Map<String, dynamic>.from(p));
        }
      }
    } else if (rawOrder['items'] is List) {
      for (final item in (rawOrder['items'] as List)) {
        if (item is Map && item['delivery_parcel_id'] != null) {
          final parcelStatus = (item['delivery_parcel_status'] ?? '').toString().toUpperCase();
          if (parcelStatus == 'OUT_FOR_DELIVERY' ||
              parcelStatus == 'IN_TRANSIT' ||
              parcelStatus == 'ARRIVED_AT_DESTINATION_HUB') {
            deliveryParcels.add({
              'delivery_parcel_id': parseInt(item['delivery_parcel_id']),
              'product_name': (item['product_name'] ?? 'Item').toString(),
              'status': parcelStatus,
            });
          }
        }
      }
    }

    return {
      'order': order,
      'can_cancel': (data['can_cancel'] ?? rawOrder['can_cancel']) == true,
      'can_mark_received': (data['can_mark_received'] ?? rawOrder['can_mark_received']) == true,
      'status_histories': data['status_histories'] ?? rawOrder['status_histories'] ?? [],
      'delivery_parcels': deliveryParcels,
    };
  }

  static Future<void> cancelOrder(int id, String reason) async {
    await ApiClient.post('api/buyer/orders/$id/cancel', body: {'reason': reason});
  }

  static Future<void> receiveOrder(int id) async {
    await ApiClient.post('api/buyer/orders/$id/receive');
  }

  static Future<void> reorder(int id) async {
    await ApiClient.post('api/buyer/orders/$id/reorder');
  }

  // Addresses
  static Future<List<AddressModel>> getAddresses() async {
    final res = await ApiClient.get('api/buyer/addresses');
    final data = res as Map<String, dynamic>;
    return (data['addresses'] as List? ?? [])
        .map((a) => AddressModel.fromJson(Map<String, dynamic>.from(a)))
        .toList();
  }

  static Future<void> addAddress(Map<String, dynamic> data) async {
    await ApiClient.post('api/buyer/addresses', body: data);
  }

  static Future<void> updateAddress(int id, Map<String, dynamic> data) async {
    await ApiClient.patch('api/buyer/addresses/$id', body: data);
  }

  static Future<void> setDefaultAddress(int id) async {
    await ApiClient.patch('api/buyer/addresses/$id/default');
  }

  static Future<void> deleteAddress(int id) async {
    await ApiClient.delete('api/buyer/addresses/$id');
  }

  // Notifications
  static Future<Map<String, dynamic>> getNotifications() async {
    final res = await ApiClient.get('api/buyer/notifications');
    final data = res as Map<String, dynamic>;
    return {
      'notifications': data['notifications'] ?? [],
      'unread_count': data['unread_count'] ?? 0,
    };
  }

  static Future<void> markNotificationRead(int id) async {
    await ApiClient.patch('api/buyer/notifications/$id/read');
  }

  static Future<void> markAllNotificationsRead() async {
    await ApiClient.post('api/buyer/notifications/read-all');
  }

  // Google Authentication
  static Future<Map<String, dynamic>> googleLogin({
    required String email,
    required String googleId,
    String? firstName,
    String? lastName,
    String? avatar,
    String? idToken,
  }) async {
    final res = await ApiClient.post('api/buyer/google-login', body: {
      'email': email,
      'google_id': googleId,
      if (firstName != null && firstName.isNotEmpty) 'first_name': firstName,
      if (lastName != null && lastName.isNotEmpty) 'last_name': lastName,
      if (avatar != null && avatar.isNotEmpty) 'avatar': avatar,
      if (idToken != null && idToken.isNotEmpty) 'id_token': idToken,
    });
    return res as Map<String, dynamic>;
  }

  // Forgot Password
  static Future<Map<String, dynamic>> forgotPassword(String email) async {
    final res = await ApiClient.post('api/buyer/forgot-password', body: {
      'email': email,
    });
    return res as Map<String, dynamic>;
  }

  // Reviews
  static Future<void> submitReview({
    required int productId,
    required int rating,
    String? comment,
    int? orderId,
  }) async {
    await ApiClient.post('api/buyer/products/$productId/reviews', body: {
      'rating': rating,
      'comment': comment,
      'order_id': orderId,
    });
  }

  // Conversations & Messaging
  static Future<List<ConversationModel>> getConversations() async {
    final res = await ApiClient.get('api/buyer/conversations');
    final data = res as Map<String, dynamic>;
    final rawList = data['conversations'] as List? ?? [];
    return rawList
        .map((c) => ConversationModel.fromJson(Map<String, dynamic>.from(c)))
        .toList();
  }

  static Future<Map<String, dynamic>> getConversation(int conversationId) async {
    final res = await ApiClient.get('api/buyer/conversations/$conversationId');
    final data = res as Map<String, dynamic>;
    final conv = ConversationModel.fromJson(Map<String, dynamic>.from(data['conversation'] ?? {}));
    final rawMessages = data['messages'] as List? ?? [];
    final messages = rawMessages
        .map((m) => ConversationMessageModel.fromJson(Map<String, dynamic>.from(m)))
        .toList();
    return {
      'conversation': conv,
      'messages': messages,
    };
  }

  static Future<ConversationMessageModel> sendMessage({
    required int conversationId,
    required String body,
    int? orderId,
  }) async {
    final Map<String, dynamic> payload = {
      'body': body,
    };
    if (orderId != null) {
      payload['order_id'] = orderId;
    }
    final res = await ApiClient.post('api/buyer/conversations/$conversationId', body: payload);
    final data = res as Map<String, dynamic>;
    return ConversationMessageModel.fromJson(Map<String, dynamic>.from(data['message'] ?? {}));
  }

  static Future<int> startConversationWithProduct(int productId) async {
    final res = await ApiClient.post('api/buyer/conversations/start/$productId');
    final data = res as Map<String, dynamic>;
    return parseInt(data['conversation_id']);
  }

  static Future<int> startConversationWithSeller(int sellerId) async {
    final res = await ApiClient.post('api/buyer/conversations/start-seller/$sellerId');
    final data = res as Map<String, dynamic>;
    return parseInt(data['conversation_id']);
  }

  static Future<Map<String, dynamic>> getStoreDetails(
    int sellerId, {
    String? sort,
    String? search,
    String? category,
  }) async {
    final query = <String, String>{};
    if (sort != null && sort.isNotEmpty) query['sort'] = sort;
    if (search != null && search.isNotEmpty) query['search'] = search;
    if (category != null && category.isNotEmpty) query['category'] = category;

    final qStr = query.isNotEmpty ? '?${Uri(queryParameters: query).query}' : '';
    final res = await ApiClient.get('api/marketplace/store/$sellerId$qStr');
    return res as Map<String, dynamic>;
  }

  // Support Info, FAQs, Policies & Complaints
  static Future<Map<String, dynamic>> getSupportInfo() async {
    final res = await ApiClient.get('api/marketplace/support-info');
    return res is Map<String, dynamic> ? res : {};
  }

  static Future<Map<String, dynamic>> getComplaints({String status = 'all'}) async {
    final res = await ApiClient.get(
      'api/buyer/complaints',
      queryParameters: {'status': status},
    );
    return res is Map<String, dynamic> ? res : {};
  }

  static Future<Map<String, dynamic>> submitComplaint({
    required String subject,
    required String description,
    String type = 'complaint',
    int? orderId,
    String? contactEmail,
  }) async {
    final res = await ApiClient.post('api/buyer/complaints', body: {
      'subject': subject,
      'description': description,
      'type': type,
      if (orderId != null) 'order_id': orderId,
      if (contactEmail != null && contactEmail.isNotEmpty) 'contact_email': contactEmail,
    });
    return res is Map<String, dynamic> ? res : {};
  }

  static Future<Map<String, dynamic>> submitSupportContact({
    required String category,
    required String subject,
    required String message,
  }) async {
    final res = await ApiClient.post('api/buyer/support/contact', body: {
      'category': category,
      'subject': subject,
      'message': message,
    });
    return res is Map<String, dynamic> ? res : {};
  }

  // My Account: Profile, Password, Banks & Cards, Privacy Settings
  static Future<Map<String, dynamic>> getMe() async {
    final res = await ApiClient.get('api/buyer/me');
    if (res is Map<String, dynamic> && res['user'] is Map) {
      return Map<String, dynamic>.from(res['user'] as Map);
    }
    return res is Map<String, dynamic> ? res : {};
  }

  static Future<Map<String, dynamic>> updateProfile({
    required String firstName,
    String? middleInitial,
    required String lastName,
    String? sex,
    String? email,
    String? contactNo,
    String? birthday,
  }) async {
    final res = await ApiClient.post('api/buyer/profile', body: {
      'first_name': firstName,
      if (middleInitial != null) 'middle_initial': middleInitial,
      'last_name': lastName,
      if (sex != null && sex.isNotEmpty) 'sex': sex,
      if (email != null && email.isNotEmpty) 'email': email,
      if (contactNo != null && contactNo.isNotEmpty) 'contact_no': contactNo,
      if (birthday != null && birthday.isNotEmpty) 'birthday': birthday,
    });
    return res is Map<String, dynamic> ? res : {};
  }

  static Future<Map<String, dynamic>> changePassword({
    required String currentPassword,
    required String password,
    required String passwordConfirmation,
  }) async {
    final res = await ApiClient.post('api/buyer/change-password', body: {
      'current_password': currentPassword,
      'password': password,
      'password_confirmation': passwordConfirmation,
    });
    return res is Map<String, dynamic> ? res : {};
  }

  static Future<List<Map<String, dynamic>>> getPaymentMethods() async {
    final res = await ApiClient.get('api/buyer/payment-methods');
    if (res is Map<String, dynamic> && res['payment_methods'] is List) {
      return (res['payment_methods'] as List)
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList();
    }
    return [];
  }

  static Future<Map<String, dynamic>> addPaymentMethod(Map<String, dynamic> body) async {
    final res = await ApiClient.post('api/buyer/payment-methods', body: body);
    return res is Map<String, dynamic> ? res : {};
  }

  static Future<void> deletePaymentMethod(int id) async {
    await ApiClient.delete('api/buyer/payment-methods/$id');
  }

  static Future<Map<String, dynamic>> deleteAccount(String password) async {
    final res = await ApiClient.post('api/buyer/delete-account', body: {
      'password': password,
    });
    return res is Map<String, dynamic> ? res : {};
  }
}
