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
      'category_slug': ?categorySlug,
      'min_price': ?minPrice,
      'max_price': ?maxPrice,
      'sort': ?sort,
    };

    final res = await ApiClient.get(
      'api/marketplace/products',
      queryParameters: query,
    );
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
    final product = ProductModel.fromJson(
      Map<String, dynamic>.from(data['product']),
    );
    final related = (data['related_products'] as List? ?? [])
        .map((p) => ProductModel.fromJson(Map<String, dynamic>.from(p)))
        .toList();

    return {'product': product, 'related': related};
  }

  static Future<List<CategoryModel>> getCategories() async {
    final res = await ApiClient.get('api/marketplace/categories');
    final data = res as Map<String, dynamic>;
    return (data['categories'] as List? ?? [])
        .map((c) => CategoryModel.fromJson(Map<String, dynamic>.from(c)))
        .toList();
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
    final res = await ApiClient.post(
      'api/buyer/cart',
      body: {
        'product_id': productId,
        'product_variant_id': variantId,
        'quantity': quantity,
      },
    );
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

    return {'claimed': claimed, 'available': available};
  }

  static Future<void> claimVoucher(int id) async {
    await ApiClient.post('api/buyer/vouchers/$id/claim');
  }

  static Future<Map<String, dynamic>> validateVoucher({
    required String code,
    required String shippingAddress,
    required List<int> cartItemIds,
    String shippingMethod = 'standard',
  }) async {
    final res = await ApiClient.post(
      'api/buyer/vouchers/validate',
      body: {
        'code': code,
        'shipping_address': shippingAddress,
        'cart_item_ids': cartItemIds,
        'shipping_method': shippingMethod,
      },
    );
    return res as Map<String, dynamic>;
  }

  // Checkout & Orders
  static Future<Map<String, dynamic>> previewCheckout({
    required List<int> cartItemIds,
    required String shippingAddress,
    String shippingMethod = 'standard',
    String? voucherCode,
  }) async {
    final res = await ApiClient.post(
      'api/buyer/checkout/preview',
      body: {
        'cart_item_ids': cartItemIds,
        'shipping_address': shippingAddress,
        'shipping_method': shippingMethod,
        'voucher_code': voucherCode,
      },
    );
    return res as Map<String, dynamic>;
  }

  static Future<Map<String, dynamic>> previewCheckoutDirect({
    required int productId,
    int? variantId,
    required int quantity,
    required String shippingAddress,
    String shippingMethod = 'standard',
    String? voucherCode,
  }) async {
    final res = await ApiClient.post(
      'api/buyer/checkout/preview',
      body: {
        'buy_now': true,
        'product_id': productId,
        'product_variant_id': ?variantId,
        'quantity': quantity,
        'shipping_address': shippingAddress,
        'shipping_method': shippingMethod,
        'voucher_code': ?voucherCode,
      },
    );
    return res as Map<String, dynamic>;
  }

  static Future<Map<String, dynamic>> placeOrder({
    required List<int> cartItemIds,
    required String shippingAddress,
    required String shippingMethod,
    required String paymentMethod,
    required Map<String, dynamic> quote,
    required String requestKey,
    String? buyerNote,
    String? voucherCode,
  }) async {
    final res = await ApiClient.post(
      'api/buyer/orders',
      body: {
        'cart_item_ids': cartItemIds,
        'shipping_address': shippingAddress,
        'shipping_method': shippingMethod,
        'payment_method': paymentMethod,
        'expected_shipping_fee': quote['shipping_fee_before_discount'],
        'expected_total': quote['total'],
        'expected_prices': quote['expected_prices'],
        'request_key': requestKey,
        'buyer_note': ?buyerNote,
        'voucher_code': ?voucherCode,
      },
    );
    return res as Map<String, dynamic>;
  }

  static Future<Map<String, dynamic>> placeOrderDirect({
    required int productId,
    int? variantId,
    required int quantity,
    required String shippingAddress,
    required String shippingMethod,
    required String paymentMethod,
    required Map<String, dynamic> quote,
    required String requestKey,
    String? buyerNote,
    String? voucherCode,
  }) async {
    final res = await ApiClient.post(
      'api/buyer/orders',
      body: {
        'buy_now': true,
        'product_id': productId,
        'product_variant_id': ?variantId,
        'quantity': quantity,
        'shipping_address': shippingAddress,
        'shipping_method': shippingMethod,
        'payment_method': paymentMethod,
        'expected_shipping_fee': quote['shipping_fee_before_discount'],
        'expected_total': quote['total'],
        'expected_prices': quote['expected_prices'],
        'request_key': requestKey,
        'buyer_note': ?buyerNote,
        'voucher_code': ?voucherCode,
      },
    );
    return res as Map<String, dynamic>;
  }

  static Future<Map<String, dynamic>> getDeliveryOtp(
    int orderId,
    int parcelId, {
    bool renew = false,
  }) async {
    final path = 'api/buyer/orders/$orderId/parcels/$parcelId/delivery-otp';
    final res = renew
        ? await ApiClient.post('$path/renew')
        : await ApiClient.get(path);
    return Map<String, dynamic>.from(res['data']);
  }

  static Future<Map<String, dynamic>> getOrders({
    String status = 'all',
    int page = 1,
  }) async {
    final res = await ApiClient.get(
      'api/buyer/orders',
      queryParameters: {'status': status, 'page': page},
    );
    final data = res as Map<String, dynamic>;

    final rawOrders = (data['orders'] is Map && data['orders']['data'] is List)
        ? data['orders']['data'] as List
        : [];

    final orders = rawOrders
        .map((o) => OrderModel.fromJson(Map<String, dynamic>.from(o)))
        .toList();

    return {
      'orders': orders,
      'counts': data['counts'] ?? {},
      'current_page': data['orders']?['current_page'] ?? 1,
      'last_page': data['orders']?['last_page'] ?? 1,
    };
  }

  static Future<Map<String, dynamic>> getOrderDetails(int id) async {
    final res = await ApiClient.get('api/buyer/orders/$id');
    final data = res as Map<String, dynamic>;
    final order = OrderModel.fromJson(Map<String, dynamic>.from(data['order']));

    return {
      'order': order,
      'can_cancel': data['can_cancel'] == true,
      'can_mark_received': data['can_mark_received'] == true,
      'status_histories': data['status_histories'] ?? [],
      'delivery_parcels': (data['order']['items'] as List? ?? [])
          .where(
            (item) =>
                item['delivery_parcel_id'] != null &&
                item['delivery_parcel_status'] == 'OUT_FOR_DELIVERY' &&
                item['return_to_seller'] == null,
          )
          .map((item) => Map<String, dynamic>.from(item))
          .toList(),
    };
  }

  static Future<void> cancelOrder(int id, String reason) async {
    await ApiClient.post(
      'api/buyer/orders/$id/cancel',
      body: {'reason': reason},
    );
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
    final res = await ApiClient.post(
      'api/buyer/google-login',
      body: {
        'email': email,
        'google_id': googleId,
        'first_name': ?firstName,
        'last_name': ?lastName,
        'avatar': ?avatar,
        'id_token': ?idToken,
      },
    );
    return res as Map<String, dynamic>;
  }

  // Forgot Password
  static Future<Map<String, dynamic>> forgotPassword(String email) async {
    final res = await ApiClient.post(
      'api/buyer/forgot-password',
      body: {'email': email},
    );
    return res as Map<String, dynamic>;
  }

  // Reviews
  static Future<void> submitReview({
    required int productId,
    required int rating,
    String? comment,
    int? orderId,
  }) async {
    await ApiClient.post(
      'api/buyer/products/$productId/reviews',
      body: {'rating': rating, 'comment': comment, 'order_id': orderId},
    );
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

  static Future<Map<String, dynamic>> getConversation(
    int conversationId,
  ) async {
    final res = await ApiClient.get('api/buyer/conversations/$conversationId');
    final data = res as Map<String, dynamic>;
    final conv = ConversationModel.fromJson(
      Map<String, dynamic>.from(data['conversation'] ?? {}),
    );
    final rawMessages = data['messages'] as List? ?? [];
    final messages = rawMessages
        .map(
          (m) =>
              ConversationMessageModel.fromJson(Map<String, dynamic>.from(m)),
        )
        .toList();
    return {'conversation': conv, 'messages': messages};
  }

  static Future<ConversationMessageModel> sendMessage({
    required int conversationId,
    required String body,
    int? orderId,
  }) async {
    final Map<String, dynamic> payload = {'body': body};
    if (orderId != null) {
      payload['order_id'] = orderId;
    }
    final res = await ApiClient.post(
      'api/buyer/conversations/$conversationId',
      body: payload,
    );
    final data = res as Map<String, dynamic>;
    return ConversationMessageModel.fromJson(
      Map<String, dynamic>.from(data['message'] ?? {}),
    );
  }

  static Future<int> startConversationWithProduct(int productId) async {
    final res = await ApiClient.post(
      'api/buyer/conversations/start/$productId',
    );
    final data = res as Map<String, dynamic>;
    return parseInt(data['conversation_id']);
  }

  static Future<int> startConversationWithSeller(int sellerId) async {
    final res = await ApiClient.post(
      'api/buyer/conversations/start-seller/$sellerId',
    );
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

    final qStr = query.isNotEmpty
        ? '?${Uri(queryParameters: query).query}'
        : '';
    final res = await ApiClient.get('api/marketplace/store/$sellerId$qStr');
    return res as Map<String, dynamic>;
  }
}
