import 'package:alona_mobile/core/config/api_config.dart';

double parseDouble(dynamic value, [double defaultValue = 0.0]) {
  if (value == null) return defaultValue;
  if (value is num) return value.toDouble();
  if (value is String) {
    return double.tryParse(value) ?? defaultValue;
  }
  return defaultValue;
}

int parseInt(dynamic value, [int defaultValue = 0]) {
  if (value == null) return defaultValue;
  if (value is num) return value.toInt();
  if (value is String) {
    return int.tryParse(value) ?? defaultValue;
  }
  return defaultValue;
}

class CategoryModel {
  final int id;
  final String name;
  final String slug;
  final String? imagePath;
  final int productsCount;

  static final Map<String, String> fallbackImages = {
    'home-living': 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=900&q=85',
    'mens-apparel': 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?auto=format&fit=crop&w=900&q=85',
    'mobiles-gadgets': 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?auto=format&fit=crop&w=900&q=85',
    'mobiles-accessories': 'https://images.unsplash.com/photo-1584438784894-089d6a62b8fa?auto=format&fit=crop&w=900&q=85',
    'home-entertainment': 'https://images.unsplash.com/photo-1593784991095-a205069470b6?auto=format&fit=crop&w=900&q=85',
    'babies-kids': 'https://images.unsplash.com/photo-1516627145497-ae6968895b74?auto=format&fit=crop&w=900&q=85',
    'snacks': 'https://images.unsplash.com/photo-1566478989037-eec170784d0b?auto=format&fit=crop&w=900&q=85',
    'toys-games-collectibles': 'https://images.unsplash.com/photo-1566576912321-d58ddd7a6088?auto=format&fit=crop&w=900&q=85',
    'womens-bags': 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=900&q=85',
    'women-accessories': 'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?auto=format&fit=crop&w=900&q=85',
    'womens-apparel': 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?auto=format&fit=crop&w=900&q=85',
    'health-personal-care': 'https://images.unsplash.com/photo-1556229010-6c3f2c9ca5f8?auto=format&fit=crop&w=900&q=85',
    'makeup-fragrances': 'https://images.unsplash.com/photo-1596462502278-27bfdc403348?auto=format&fit=crop&w=900&q=85',
    'home-appliances': 'https://images.unsplash.com/photo-1556911220-bff31c812dba?auto=format&fit=crop&w=900&q=85',
    'laptops-computers': 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=900&q=85',
    'cameras': 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?auto=format&fit=crop&w=900&q=85',
    'sports-travel': 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?auto=format&fit=crop&w=900&q=85',
    'mens-bags-accessories': 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?auto=format&fit=crop&w=900&q=85',
    'mens-shoes': 'https://images.unsplash.com/photo-1542291026-7eec264c27ff?auto=format&fit=crop&w=900&q=85',
    'motors': 'https://images.unsplash.com/photo-1558981403-c5f9899a28bc?auto=format&fit=crop&w=900&q=85',
    'womens-shoes': 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?auto=format&fit=crop&w=900&q=85',
    'pet-care': 'https://images.unsplash.com/photo-1450778869180-41d0601e046e?auto=format&fit=crop&w=900&q=85',
    'audio': 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=900&q=85',
    'hobbies-stationery': 'https://images.unsplash.com/photo-1499951360447-b19be8fe80f5?auto=format&fit=crop&w=900&q=85',
    'gaming': 'https://images.unsplash.com/photo-1600080972464-8e5f35f63d08?auto=format&fit=crop&w=900&q=85',
  };

  CategoryModel({
    required this.id,
    required this.name,
    required this.slug,
    this.imagePath,
    this.productsCount = 0,
  });

  String get effectiveImageUrl {
    final raw = (imagePath != null && imagePath!.trim().isNotEmpty)
        ? imagePath!.trim()
        : (fallbackImages[slug] ??
              'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=900&q=85');
    return ApiConfig.imageUrl(raw);
  }

  factory CategoryModel.fromJson(Map<String, dynamic> json) {
    return CategoryModel(
      id: json['id'] ?? 0,
      name: json['name'] ?? json['title'] ?? '',
      slug: json['slug'] ?? '',
      imagePath:
          json['image_path'] ??
          json['image_url'] ??
          json['image'] ??
          json['thumbnail'],
      productsCount:
          json['products_count'] ??
          json['product_count'] ??
          json['productsCount'] ??
          0,
    );
  }
}

class ProductVariantModel {
  final int id;
  final String? color;
  final String? size;
  final double price;
  final int stock;
  final String? sku;
  final String? imageUrl;

  ProductVariantModel({
    required this.id,
    this.color,
    this.size,
    required this.price,
    required this.stock,
    this.sku,
    this.imageUrl,
  });

  String get label {
    final parts = [
      color,
      size,
    ].where((s) => s != null && s.isNotEmpty).join(' • ');
    return parts.isEmpty ? 'Default Variant' : parts;
  }

  factory ProductVariantModel.fromJson(Map<String, dynamic> json) {
    return ProductVariantModel(
      id: parseInt(json['id']),
      color: json['color'],
      size: json['size'],
      price: parseDouble(json['price']),
      stock: parseInt(json['stock']),
      sku: json['sku'],
      imageUrl: json['image_url'],
    );
  }
}

class ProductStoreVoucherModel {
  final int id;
  final String code;
  final String claimLabel;
  final double value;
  final double minSpend;
  final bool isClaimed;

  ProductStoreVoucherModel({
    required this.id,
    required this.code,
    required this.claimLabel,
    required this.value,
    required this.minSpend,
    required this.isClaimed,
  });

  factory ProductStoreVoucherModel.fromJson(Map<String, dynamic> json) {
    return ProductStoreVoucherModel(
      id: parseInt(json['id']),
      code: json['code'] ?? '',
      claimLabel: json['claim_label'] ?? '',
      value: parseDouble(json['value']),
      minSpend: parseDouble(json['min_spend']),
      isClaimed: json['is_claimed'] == true || json['is_claimed'] == 1,
    );
  }
}

class ProductReviewModel {
  final int id;
  final int rating;
  final String? comment;
  final String? createdAt;
  final String userName;
  final String? avatarUrl;
  final String? productName;
  final String? sellerReply;
  final String? sellerRepliedAt;

  ProductReviewModel({
    required this.id,
    required this.rating,
    this.comment,
    this.createdAt,
    required this.userName,
    this.avatarUrl,
    this.productName,
    this.sellerReply,
    this.sellerRepliedAt,
  });

  factory ProductReviewModel.fromJson(Map<String, dynamic> json) {
    return ProductReviewModel(
      id: json['id'] ?? 0,
      rating: json['rating'] ?? 5,
      comment: json['comment'],
      createdAt: json['created_at'],
      userName: json['user_name'] ?? 'Verified Buyer',
      avatarUrl: json['avatar_url'] ?? json['user_avatar'],
      productName:
          json['product_name']?.toString() ??
          (json['product'] is Map ? json['product']['name']?.toString() : null),
      sellerReply: json['seller_reply'],
      sellerRepliedAt: json['seller_replied_at'],
    );
  }
}

class ProductModel {
  final int id;
  final String name;
  final String slug;
  final String? description;
  final double price;
  final double? oldPrice;
  final int stock;
  final double rating;
  final int reviewsCount;
  final String imageUrl;
  final List<String> images;
  final List<ProductVariantModel> variants;
  final String? categoryName;
  final String? sellerName;
  final int? sellerId;
  final String? sellerStoreName;
  final String? sellerDescription;
  final String? sellerLogoUrl;
  final double sellerRating;
  final int sellerProductsCount;
  final int sellerFollowersCount;
  final bool sellerIsOnVacation;
  final String? sellerVacationMessage;
  final bool isFeatured;
  final bool hasFlashSale;
  final double? flashPrice;
  final String? flashEndsAt;
  final int? flashRemaining;
  final int? flashCountdownSeconds;
  final String? brand;
  final String condition;
  final int? perBuyerLimit;
  final List<ProductStoreVoucherModel> vouchers;
  final List<int> claimedVoucherIds;
  final bool inWishlist;
  final bool isFollowingStore;
  final int userPurchasedCount;
  final int? userRemainingLimit;
  final int? customSoldCount;
  final List<ProductReviewModel> reviews;

  ProductModel({
    required this.id,
    required this.name,
    required this.slug,
    this.description,
    required this.price,
    this.oldPrice,
    required this.stock,
    this.rating = 0.0,
    this.reviewsCount = 0,
    required this.imageUrl,
    this.images = const [],
    this.variants = const [],
    this.categoryName,
    this.sellerName,
    this.sellerId,
    this.sellerStoreName,
    this.sellerDescription,
    this.sellerLogoUrl,
    this.sellerRating = 5.0,
    this.sellerProductsCount = 0,
    this.sellerFollowersCount = 0,
    this.sellerIsOnVacation = false,
    this.sellerVacationMessage,
    this.isFeatured = false,
    this.hasFlashSale = false,
    this.flashPrice,
    this.flashEndsAt,
    this.flashRemaining,
    this.flashCountdownSeconds,
    this.brand,
    this.condition = 'Brand New',
    this.perBuyerLimit,
    this.vouchers = const [],
    this.claimedVoucherIds = const [],
    this.inWishlist = false,
    this.isFollowingStore = false,
    this.userPurchasedCount = 0,
    this.userRemainingLimit,
    this.customSoldCount,
    this.reviews = const [],
  });

  double get effectivePrice =>
      hasFlashSale && flashPrice != null ? flashPrice! : price;

  int get discountPercentage {
    if (oldPrice != null && oldPrice! > price) {
      return (((oldPrice! - price) / oldPrice!) * 100).round();
    }
    return 0;
  }

  int get discountPercent => discountPercentage;

  int get soldCount => customSoldCount ?? 0;

  factory ProductModel.fromJson(Map<String, dynamic> json) {
    var rawImages = <String>[];
    if (json['images'] is List) {
      for (var img in json['images']) {
        if (img is Map && img['url'] != null) {
          rawImages.add(img['url'].toString());
        } else if (img is String) {
          rawImages.add(img);
        }
      }
    }

    var rawVariants = <ProductVariantModel>[];
    if (json['variants'] is List) {
      rawVariants = (json['variants'] as List)
          .map(
            (v) => ProductVariantModel.fromJson(Map<String, dynamic>.from(v)),
          )
          .toList();
    }

    var rawVouchers = <ProductStoreVoucherModel>[];
    if (json['vouchers'] is List) {
      rawVouchers = (json['vouchers'] as List)
          .map(
            (v) =>
                ProductStoreVoucherModel.fromJson(Map<String, dynamic>.from(v)),
          )
          .toList();
    }

    var rawReviews = <ProductReviewModel>[];
    if (json['reviews'] is List) {
      rawReviews = (json['reviews'] as List)
          .map((r) => ProductReviewModel.fromJson(Map<String, dynamic>.from(r)))
          .toList();
    }

    var rawClaimedVoucherIds = <int>[];
    if (json['claimed_voucher_ids'] is List) {
      rawClaimedVoucherIds = (json['claimed_voucher_ids'] as List)
          .map((id) => parseInt(id))
          .toList();
    }

    final sellerMap = json['seller'] is Map
        ? Map<String, dynamic>.from(json['seller'])
        : null;
    final hasFs =
        json['has_flash_sale'] == true || json['has_active_flash_sale'] == true;

    return ProductModel(
      id: parseInt(json['id']),
      name: json['name'] ?? '',
      slug: json['slug'] ?? '',
      description: json['description'],
      price: parseDouble(json['price']),
      oldPrice: (json['old_price'] != null)
          ? parseDouble(json['old_price'])
          : null,
      stock: parseInt(json['stock']),
      rating: parseDouble(json['rating']),
      reviewsCount: parseInt(json['reviews_count']),
      imageUrl: json['image_url'] ?? '',
      images: rawImages,
      variants: rawVariants,
      categoryName:
          json['category_name'] ??
          (json['category'] is Map ? json['category']['name'] : null),
      sellerName: json['seller_name'] ?? sellerMap?['name'],
      sellerId: json['seller_id'] != null
          ? parseInt(json['seller_id'])
          : (sellerMap?['id'] != null ? parseInt(sellerMap!['id']) : null),
      sellerStoreName: sellerMap?['store_name'],
      sellerDescription: sellerMap?['store_description'],
      sellerLogoUrl: sellerMap?['logo_url'],
      sellerRating: (sellerMap?['rating'] != null)
          ? parseDouble(sellerMap!['rating'])
          : 5.0,
      sellerProductsCount: sellerMap?['total_products'] != null
          ? parseInt(sellerMap!['total_products'])
          : 0,
      sellerFollowersCount: sellerMap?['followers_count'] != null
          ? parseInt(sellerMap!['followers_count'])
          : 0,
      sellerIsOnVacation: sellerMap?['is_on_vacation'] == true,
      sellerVacationMessage: sellerMap?['vacation_message'],
      isFeatured: json['is_featured'] == true,
      hasFlashSale: hasFs,
      flashPrice: (json['flash_price'] != null)
          ? parseDouble(json['flash_price'])
          : null,
      flashEndsAt: json['flash_ends_at'],
      flashRemaining: json['flash_remaining'],
      flashCountdownSeconds: json['flash_countdown_seconds'],
      brand: json['brand'],
      condition: json['condition'] ?? 'Brand New',
      perBuyerLimit: json['per_buyer_limit'],
      vouchers: rawVouchers,
      claimedVoucherIds: rawClaimedVoucherIds,
      inWishlist: json['in_wishlist'] == true,
      isFollowingStore: json['is_following_store'] == true,
      userPurchasedCount: parseInt(json['user_purchased_count']),
      userRemainingLimit: json['user_remaining_limit'],
      customSoldCount: json['sold_total'] != null
          ? parseInt(json['sold_total'])
          : (json['sold_count'] != null
                ? parseInt(json['sold_count'])
                : (json['orders_count'] != null
                      ? parseInt(json['orders_count'])
                      : null)),
      reviews: rawReviews,
    );
  }
}

class FlashSaleModel {
  final int id;
  final int productId;
  final String name;
  final String slug;
  final String imageUrl;
  final double flashPrice;
  final double originalPrice;
  final int discountPercent;
  final int soldQuantity;
  final int flashQuantity;
  final int progressPercent;
  final int remaining;
  final String? endsAt;

  FlashSaleModel({
    required this.id,
    required this.productId,
    required this.name,
    required this.slug,
    required this.imageUrl,
    required this.flashPrice,
    required this.originalPrice,
    required this.discountPercent,
    required this.soldQuantity,
    required this.flashQuantity,
    required this.progressPercent,
    required this.remaining,
    this.endsAt,
  });

  factory FlashSaleModel.fromJson(Map<String, dynamic> json) {
    return FlashSaleModel(
      id: parseInt(json['id']),
      productId: parseInt(json['product_id']),
      name: json['name'] ?? '',
      slug: json['slug'] ?? '',
      imageUrl: json['image_url'] ?? '',
      flashPrice: parseDouble(json['flash_price']),
      originalPrice: parseDouble(json['original_price']),
      discountPercent: parseInt(json['discount_percent']),
      soldQuantity: parseInt(json['sold_quantity']),
      flashQuantity: parseInt(json['flash_quantity']),
      progressPercent: parseInt(json['progress_percent']),
      remaining: parseInt(json['remaining']),
      endsAt: json['ends_at'],
    );
  }
}

class CartItemModel {
  final int id;
  final int productId;
  final String productName;
  final String productSlug;
  final String imageUrl;
  final int? productVariantId;
  final String? variantLabel;
  int quantity;
  final double unitPrice;
  final double totalPrice;
  final int stock;
  final bool isInStock;
  final int? sellerId;
  final String? sellerName;

  CartItemModel({
    required this.id,
    required this.productId,
    required this.productName,
    required this.productSlug,
    required this.imageUrl,
    this.productVariantId,
    this.variantLabel,
    required this.quantity,
    required this.unitPrice,
    required this.totalPrice,
    required this.stock,
    required this.isInStock,
    this.sellerId,
    this.sellerName,
  });

  factory CartItemModel.fromJson(Map<String, dynamic> json) {
    return CartItemModel(
      id: parseInt(json['id']),
      productId: parseInt(json['product_id']),
      productName: json['product_name'] ?? 'Product',
      productSlug: json['product_slug'] ?? '',
      imageUrl: json['image_url'] ?? '',
      productVariantId: json['product_variant_id'] != null
          ? parseInt(json['product_variant_id'])
          : null,
      variantLabel: json['variant_label'],
      quantity: parseInt(json['quantity'], 1),
      unitPrice: parseDouble(json['unit_price'] ?? json['price']),
      totalPrice: parseDouble(json['total_price']),
      stock: parseInt(json['stock']),
      isInStock: json['is_in_stock'] == true || json['is_in_stock'] == 1,
      sellerId: json['seller_id'] != null ? parseInt(json['seller_id']) : null,
      sellerName: json['seller_name'],
    );
  }
}

class AddressModel {
  final int id;
  final String label;
  final String recipientName;
  final String phone;
  final String? region;
  final String? province;
  final String city;
  final String barangay;
  final String street;
  final String? postalCode;
  final bool isDefault;
  final String? recordedAddress;

  AddressModel({
    required this.id,
    required this.label,
    required this.recipientName,
    required this.phone,
    this.region,
    this.province,
    required this.city,
    required this.barangay,
    required this.street,
    this.postalCode,
    required this.isDefault,
    this.recordedAddress,
  });

  String get fullAddress => recordedAddress?.isNotEmpty == true
      ? recordedAddress!
      : [
          street,
          barangay,
          city,
          province,
          region,
          postalCode,
        ].where((s) => s != null && s.isNotEmpty).join(', ');

  factory AddressModel.fromJson(Map<String, dynamic> json) {
    return AddressModel(
      id: json['id'] ?? 0,
      label: json['label'] ?? 'Home',
      recipientName: json['recipient_name'] ?? json['name'] ?? '',
      phone: json['phone'] ?? json['recipient_phone'] ?? '',
      region: json['region'],
      province: json['province'],
      city: json['municipality'] ?? json['city'] ?? '',
      barangay: json['barangay'] ?? '',
      street: json['street_address'] ?? json['street'] ?? json['address'] ?? '',
      recordedAddress: json['address'],
      postalCode: json['postal_code']?.toString(),
      isDefault: json['is_default'] == true || json['is_default'] == 1,
    );
  }
}

class VoucherModel {
  final int id;
  final String code;
  final String type; // 'percentage', 'fixed', 'free_shipping'
  final double value;
  final double minSpend;
  final double? maxDiscount;
  final String? startsAt;
  final String? expiresAt;
  final bool isClaimed;
  final bool isPlatform;
  final String? sellerName;

  VoucherModel({
    required this.id,
    required this.code,
    required this.type,
    required this.value,
    this.minSpend = 0.0,
    this.maxDiscount,
    this.startsAt,
    this.expiresAt,
    this.isClaimed = false,
    this.isPlatform = true,
    this.sellerName,
  });

  int? get daysRemaining {
    if (expiresAt == null) return null;
    final exp = DateTime.tryParse(expiresAt!);
    if (exp == null) return null;
    return exp.difference(DateTime.now()).inDays;
  }

  String get discountDescription {
    if (type == 'percentage') {
      return '${value.toInt()}% OFF';
    } else if (type == 'free_shipping') {
      return 'Free Shipping';
    } else {
      return '₱${value.toInt()} OFF';
    }
  }

  factory VoucherModel.fromJson(Map<String, dynamic> json) {
    return VoucherModel(
      id: parseInt(json['id']),
      code: json['code'] ?? '',
      type: json['type'] ?? 'fixed',
      value: parseDouble(json['value']),
      minSpend: parseDouble(json['min_spend']),
      maxDiscount: (json['max_discount'] != null)
          ? parseDouble(json['max_discount'])
          : null,
      startsAt: json['starts_at'],
      expiresAt: json['expires_at'],
      isClaimed: json['is_claimed'] == true,
      isPlatform: json['is_platform'] == true,
      sellerName: json['seller_name'],
    );
  }
}

class OrderItemModel {
  final int id;
  final int productId;
  final String productName;
  final String? variantLabel;
  final double unitPrice;
  final int quantity;
  final double totalPrice;
  final String status;
  final String? imageUrl;

  OrderItemModel({
    required this.id,
    required this.productId,
    required this.productName,
    this.variantLabel,
    required this.unitPrice,
    required this.quantity,
    required this.totalPrice,
    required this.status,
    this.imageUrl,
  });

  factory OrderItemModel.fromJson(Map<String, dynamic> json) {
    var img = '';
    if (json['image_url'] != null && json['image_url'].toString().isNotEmpty) {
      img = json['image_url'].toString();
    } else if (json['product'] is Map &&
        json['product']['images'] is List &&
        (json['product']['images'] as List).isNotEmpty) {
      img = json['product']['images'][0]['image_path'] ?? '';
    } else if (json['product'] is Map &&
        json['product']['image_path'] != null) {
      img = json['product']['image_path'] ?? '';
    }

    final price = parseDouble(json['unit_price'] ?? json['price']);
    final qty = parseInt(json['quantity'], 1);
    final total = parseDouble(json['total_price'] ?? (price * qty));

    return OrderItemModel(
      id: parseInt(json['id']),
      productId: parseInt(json['product_id']),
      productName:
          json['product_name'] ??
          (json['product'] is Map ? json['product']['name'] : 'Item'),
      variantLabel: json['variant_label'],
      unitPrice: price,
      quantity: qty,
      totalPrice: total,
      status: json['status'] ?? 'pending',
      imageUrl: img,
    );
  }
}

class OrderModel {
  final int id;
  final String orderNumber;
  final double subtotal;
  final double shippingFee;
  final double discount;
  final double total;
  final String shippingAddress;
  final String shippingMethod;
  final String paymentMethod;
  final String paymentStatus;
  final String status;
  final String? buyerNote;
  final String? createdAt;
  final List<OrderItemModel> items;

  OrderModel({
    required this.id,
    required this.orderNumber,
    required this.subtotal,
    required this.shippingFee,
    required this.discount,
    required this.total,
    required this.shippingAddress,
    required this.shippingMethod,
    required this.paymentMethod,
    required this.paymentStatus,
    required this.status,
    this.buyerNote,
    this.createdAt,
    this.items = const [],
  });

  factory OrderModel.fromJson(Map<String, dynamic> json) {
    var rawItems = <OrderItemModel>[];
    if (json['items'] is List) {
      rawItems = (json['items'] as List)
          .map((i) => OrderItemModel.fromJson(Map<String, dynamic>.from(i)))
          .toList();
    }

    return OrderModel(
      id: parseInt(json['id']),
      orderNumber: json['order_number'] ?? '',
      subtotal: parseDouble(json['subtotal']),
      shippingFee: parseDouble(json['shipping_fee']),
      discount: parseDouble(json['discount']),
      total: parseDouble(json['total']),
      shippingAddress: json['shipping_address'] ?? '',
      shippingMethod: json['shipping_method'] ?? 'standard',
      paymentMethod: json['payment_method'] ?? 'cod',
      paymentStatus: json['payment_status'] ?? 'unpaid',
      status: json['status'] ?? 'pending',
      buyerNote: json['buyer_note'],
      createdAt: json['created_at'],
      items: rawItems,
    );
  }
}

class HeroBannerModel {
  final String id;
  final bool isActive;
  final String layoutType; // 'split' or 'full'
  final bool overlayText;
  final String theme; // 'teal', 'amber', 'indigo', 'rose', 'emerald', 'dark'
  final String badgeText;
  final String title;
  final String titleHighlight;
  final String description;
  final bool showPrimaryButton;
  final String primaryButtonText;
  final String primaryButtonUrl;
  final bool showPrimaryButtonArrow;
  final bool showSecondaryButton;
  final String secondaryButtonText;
  final String secondaryButtonUrl;
  final bool showImage;
  final String imagePath;
  final String? imageUrl;
  final bool showFloatingCards;
  final String card1Title;
  final String card1Subtitle;
  final String card2Title;
  final String card2Subtitle;

  const HeroBannerModel({
    required this.id,
    this.isActive = true,
    this.layoutType = 'split',
    this.overlayText = false,
    this.theme = 'teal',
    this.badgeText = '',
    this.title = 'Welcome to',
    this.titleHighlight = 'Alona.',
    this.description = 'Everything you love, all in one place. Discover quality products, trusted sellers, and great deals made for you.',
    this.showPrimaryButton = true,
    this.primaryButtonText = 'Shop Now',
    this.primaryButtonUrl = '/products',
    this.showPrimaryButtonArrow = true,
    this.showSecondaryButton = true,
    this.secondaryButtonText = 'Explore Categories',
    this.secondaryButtonUrl = '#categories',
    this.showImage = true,
    this.imagePath = '/images/Alogo.png',
    this.imageUrl,
    this.showFloatingCards = true,
    this.card1Title = 'Products',
    this.card1Subtitle = 'available',
    this.card2Title = 'CATEGORIES',
    this.card2Subtitle = 'to explore',
  });

  factory HeroBannerModel.fromJson(Map<String, dynamic> json) {
    return HeroBannerModel(
      id: (json['id'] ?? 'banner_1').toString(),
      isActive: json['is_active'] != false,
      layoutType: json['layout_type'] ?? 'split',
      overlayText: json['overlay_text'] == true,
      theme: json['theme'] ?? 'teal',
      badgeText: json['badge_text'] ?? '',
      title: json['title'] ?? 'Welcome to',
      titleHighlight: json['title_highlight'] ?? 'Alona.',
      description: json['description'] ?? 'Everything you love, all in one place. Discover quality products, trusted sellers, and great deals made for you.',
      showPrimaryButton:
          json['show_primary_button'] != false &&
          ((json['primary_button_text'] ?? '').toString().trim().isNotEmpty),
      primaryButtonText: (json['primary_button_text'] ?? 'Shop Now').toString(),
      primaryButtonUrl: (json['primary_button_url'] ?? '/products').toString(),
      showPrimaryButtonArrow: json['show_primary_button_arrow'] != false,
      showSecondaryButton:
          json['show_secondary_button'] != false &&
          ((json['secondary_button_text'] ?? '').toString().trim().isNotEmpty),
      secondaryButtonText:
          (json['secondary_button_text'] ?? 'Explore Categories').toString(),
      secondaryButtonUrl: (json['secondary_button_url'] ?? '#categories')
          .toString(),
      showImage: json['show_image'] != false,
      imagePath: (json['image_path'] ?? '/images/Alogo.png').toString(),
      imageUrl: json['image_url']?.toString(),
      showFloatingCards: json['show_floating_cards'] != false,
      card1Title: (json['card_1_title'] ?? 'Products').toString(),
      card1Subtitle: (json['card_1_subtitle'] ?? 'available').toString(),
      card2Title: (json['card_2_title'] ?? 'CATEGORIES').toString(),
      card2Subtitle: (json['card_2_subtitle'] ?? 'to explore').toString(),
    );
  }
}

class HeroStatsModel {
  final int productsCount;
  final int categoriesCount;

  const HeroStatsModel({this.productsCount = 0, this.categoriesCount = 0});

  factory HeroStatsModel.fromJson(Map<String, dynamic> json) {
    return HeroStatsModel(
      productsCount: parseInt(json['products_count']),
      categoriesCount: parseInt(json['categories_count']),
    );
  }
}

class DirectBuyModel {
  final int productId;
  final int? productVariantId;
  final int quantity;
  final String productName;
  final double unitPrice;
  final String? imageUrl;
  final String? variantLabel;

  const DirectBuyModel({
    required this.productId,
    this.productVariantId,
    required this.quantity,
    required this.productName,
    required this.unitPrice,
    this.imageUrl,
    this.variantLabel,
  });
}

class ConversationModel {
  final int id;
  final int buyerId;
  final int sellerId;
  final String sellerName;
  final String? sellerStoreName;
  final String? sellerAvatarUrl;
  final int? productId;
  final String? productName;
  final String? productImagePath;
  final double? productPrice;
  final int? orderId;
  final String? orderNumber;
  final int unreadCount;
  final String? lastMessage;
  final String? lastMessageAt;

  const ConversationModel({
    required this.id,
    this.buyerId = 0,
    required this.sellerId,
    required this.sellerName,
    this.sellerStoreName,
    this.sellerAvatarUrl,
    this.productId,
    this.productName,
    this.productImagePath,
    this.productPrice,
    this.orderId,
    this.orderNumber,
    this.unreadCount = 0,
    this.lastMessage,
    this.lastMessageAt,
  });

  String get displayName =>
      sellerStoreName?.isNotEmpty == true ? sellerStoreName! : sellerName;

  String get badgeLabel {
    if (sellerStoreName != null && sellerStoreName!.isNotEmpty) {
      return 'Mall';
    }
    return 'Official';
  }

  factory ConversationModel.fromJson(Map<String, dynamic> json) {
    final seller = json['seller'] is Map
        ? Map<String, dynamic>.from(json['seller'])
        : null;
    final product = json['product'] is Map
        ? Map<String, dynamic>.from(json['product'])
        : null;
    final order = json['order'] is Map
        ? Map<String, dynamic>.from(json['order'])
        : null;

    String? latestMsg;
    if (json['messages'] is List && (json['messages'] as List).isNotEmpty) {
      final m = (json['messages'] as List).first;
      if (m is Map) latestMsg = m['body']?.toString();
    } else if (json['latest_message'] is Map) {
      latestMsg = json['latest_message']['body']?.toString();
    }

    return ConversationModel(
      id: parseInt(json['id']),
      buyerId: parseInt(json['buyer_id']),
      sellerId: parseInt(json['seller_id'] ?? seller?['id']),
      sellerName: seller?['name']?.toString() ?? 'Seller',
      sellerStoreName: seller?['store_name']?.toString(),
      sellerAvatarUrl:
          seller?['avatar']?.toString() ?? seller?['avatar_url']?.toString(),
      productId: product != null ? parseInt(product['id']) : null,
      productName: product?['name']?.toString(),
      productImagePath:
          product?['image_path']?.toString() ??
          product?['image_url']?.toString(),
      productPrice: product?['price'] != null
          ? parseDouble(product!['price'])
          : null,
      orderId: order != null ? parseInt(order['id']) : null,
      orderNumber: order?['order_number']?.toString(),
      unreadCount: parseInt(json['unread_count']),
      lastMessage: latestMsg,
      lastMessageAt: json['last_message_at']?.toString(),
    );
  }
}

class ConversationMessageModel {
  final int id;
  final int conversationId;
  final int senderId;
  final String? senderName;
  final String? senderAvatar;
  final String body;
  final String? attachmentUrl;
  final int? orderId;
  final String? orderNumber;
  final String? orderStatus;
  final double? orderTotal;
  final String? readAt;
  final String? createdAt;

  const ConversationMessageModel({
    required this.id,
    required this.conversationId,
    required this.senderId,
    this.senderName,
    this.senderAvatar,
    required this.body,
    this.attachmentUrl,
    this.orderId,
    this.orderNumber,
    this.orderStatus,
    this.orderTotal,
    this.readAt,
    this.createdAt,
  });

  bool isMe(int currentUserId) => senderId == currentUserId;

  factory ConversationMessageModel.fromJson(Map<String, dynamic> json) {
    final sender = json['sender'] is Map
        ? Map<String, dynamic>.from(json['sender'])
        : null;
    final order = json['order'] is Map
        ? Map<String, dynamic>.from(json['order'])
        : null;

    return ConversationMessageModel(
      id: parseInt(json['id']),
      conversationId: parseInt(json['conversation_id']),
      senderId: parseInt(json['sender_id']),
      senderName: sender?['name']?.toString(),
      senderAvatar: sender?['avatar']?.toString(),
      body: (json['body'] ?? '').toString(),
      attachmentUrl: json['attachment_url']?.toString(),
      orderId: order != null
          ? parseInt(order['id'])
          : (json['order_id'] != null ? parseInt(json['order_id']) : null),
      orderNumber: order?['order_number']?.toString(),
      orderStatus: order?['status']?.toString(),
      orderTotal: order?['total'] != null ? parseDouble(order!['total']) : null,
      readAt: json['read_at']?.toString(),
      createdAt: json['created_at']?.toString(),
    );
  }
}
