import 'dart:async';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/core/network/api_client.dart';
import 'package:alona_mobile/core/services/auth_state.dart';
import 'package:alona_mobile/core/utils/currency_formatter.dart';
import 'package:alona_mobile/shared/widgets/network_image_box.dart';
import 'package:alona_mobile/features/buyer/models/buyer_models.dart';
import 'package:alona_mobile/features/buyer/services/buyer_service.dart';
import 'package:alona_mobile/features/buyer/presentation/auth/buyer_login_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/cart/cart_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/checkout/checkout_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/messages/buyer_chat_detail_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/products/product_list_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/store/buyer_store_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/home/widgets/product_card.dart';

class ProductDetailScreen extends StatefulWidget {
  final int productId;

  const ProductDetailScreen({super.key, required this.productId});

  @override
  State<ProductDetailScreen> createState() => _ProductDetailScreenState();
}

class _ProductDetailScreenState extends State<ProductDetailScreen> {
  ProductModel? _product;
  List<ProductModel> _related = [];
  bool _isLoading = true;
  String? _error;

  int _selectedImageIndex = 0;
  String? _selectedColor;
  String? _selectedSize;
  ProductVariantModel? _selectedVariant;
  int _quantity = 1;

  bool _isWishlisted = false;
  bool _isWishlistProcessing = false;
  bool _isFollowingStore = false;
  bool _isFollowingLoading = false;
  int _followersCount = 0;
  final Set<int> _claimedVoucherIds = {};
  bool _isAddingToCart = false;

  Timer? _countdownTimer;
  Duration _remainingTime = Duration.zero;

  @override
  void initState() {
    super.initState();
    _loadProduct();
  }

  @override
  void dispose() {
    _countdownTimer?.cancel();
    super.dispose();
  }

  void _initCountdown(String? endsAt, int? seconds) {
    _countdownTimer?.cancel();
    if (seconds != null && seconds > 0) {
      _remainingTime = Duration(seconds: seconds);
    } else if (endsAt != null) {
      final end = DateTime.tryParse(endsAt);
      if (end != null) {
        final diff = end.difference(DateTime.now());
        _remainingTime = diff.isNegative ? Duration.zero : diff;
      }
    }

    if (_remainingTime > Duration.zero) {
      _countdownTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
        if (!mounted) {
          timer.cancel();
          return;
        }
        if (_remainingTime.inSeconds <= 1) {
          timer.cancel();
          setState(() {
            _remainingTime = Duration.zero;
          });
        } else {
          setState(() {
            _remainingTime -= const Duration(seconds: 1);
          });
        }
      });
    }
  }

  Future<void> _loadProduct() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final res = await BuyerService.getProduct(widget.productId);
      final product = res['product'] as ProductModel;
      final related = res['related'] as List<ProductModel>;

      if (!mounted) return;
      setState(() {
        _product = product;
        _related = related;
        _isLoading = false;
        _isWishlisted = product.inWishlist;
        _isFollowingStore = product.isFollowingStore;
        _followersCount = product.sellerFollowersCount;
        _claimedVoucherIds.addAll(product.claimedVoucherIds);

        if (product.variants.isNotEmpty) {
          _selectedVariant = product.variants.first;
          _selectedColor = _selectedVariant?.color;
          _selectedSize = _selectedVariant?.size;
        }

        if (product.hasFlashSale) {
          _initCountdown(product.flashEndsAt, product.flashCountdownSeconds);
        }
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _isLoading = false;
      });
    }
  }

  List<String> get _galleryImages {
    if (_product == null) return [];
    final list = <String>[];
    if (_product!.imageUrl.isNotEmpty) {
      list.add(_product!.imageUrl);
    }
    for (var img in _product!.images) {
      if (img.isNotEmpty && !list.contains(img)) {
        list.add(img);
      }
    }
    for (var v in _product!.variants) {
      if (v.imageUrl != null && v.imageUrl!.isNotEmpty && !list.contains(v.imageUrl)) {
        list.add(v.imageUrl!);
      }
    }
    return list;
  }

  List<String> get _colors {
    if (_product == null || _product!.variants.isEmpty) return [];
    return _product!.variants
        .map((v) => v.color)
        .where((c) => c != null && c.isNotEmpty)
        .cast<String>()
        .toSet()
        .toList();
  }

  List<String> _sizesForColor(String? color) {
    if (_product == null || _product!.variants.isEmpty) return [];
    return _product!.variants
        .where((v) => color == null || v.color == color)
        .map((v) => v.size)
        .where((s) => s != null && s.isNotEmpty)
        .cast<String>()
        .toSet()
        .toList();
  }

  bool _isSizeOutOfStock(String size) {
    if (_product == null || _product!.variants.isEmpty) return false;
    final match = _product!.variants.firstWhere(
      (v) => (_selectedColor == null || v.color == _selectedColor) && v.size == size,
      orElse: () => ProductVariantModel(id: 0, price: 0, stock: 0),
    );
    return match.stock <= 0;
  }

  void _selectColor(String color) {
    setState(() {
      _selectedColor = color;
      final availableSizes = _sizesForColor(color);
      if (_selectedSize != null && !availableSizes.contains(_selectedSize)) {
        _selectedSize = availableSizes.isNotEmpty ? availableSizes.first : null;
      }
      _updateVariant();
    });
  }

  void _selectSize(String size) {
    setState(() {
      _selectedSize = size;
      _updateVariant();
    });
  }

  void _updateVariant() {
    if (_product == null || _product!.variants.isEmpty) return;
    final match = _product!.variants.firstWhere(
      (v) =>
          (_selectedColor == null || v.color == _selectedColor) &&
          (_selectedSize == null || v.size == _selectedSize),
      orElse: () => _product!.variants.first,
    );
    setState(() {
      _selectedVariant = match;
      if (_quantity > currentStock && currentStock > 0) {
        _quantity = currentStock;
      }
    });
  }

  int get currentStock {
    if (_selectedVariant != null) {
      return _selectedVariant!.stock;
    }
    return _product?.stock ?? 0;
  }

  double get unitPrice {
    if (_product == null) return 0.0;
    if (_product!.hasFlashSale && _product!.flashPrice != null) {
      return _product!.flashPrice!;
    }
    if (_selectedVariant != null && _selectedVariant!.price > 0) {
      return _selectedVariant!.price;
    }
    return _product!.price;
  }

  double get totalPrice => unitPrice * _quantity;

  int get effectivePurchaseLimit {
    if (_product == null) return 0;
    if (_product!.userRemainingLimit != null) {
      return _product!.userRemainingLimit!;
    }
    return _product!.perBuyerLimit ?? 0;
  }

  bool get isPurchaseLimitReached =>
      effectivePurchaseLimit > 0 && (_product?.userRemainingLimit != null && _product!.userRemainingLimit! <= 0);

  int get maxAllowedQuantity {
    int maxStock = currentStock > 0 ? currentStock : 1;
    if (effectivePurchaseLimit > 0) {
      return maxStock < effectivePurchaseLimit ? maxStock : effectivePurchaseLimit;
    }
    return maxStock;
  }

  Future<void> _toggleWishlist() async {
    if (_product == null || _isWishlistProcessing) return;
    setState(() => _isWishlistProcessing = true);
    try {
      final state = await BuyerService.toggleWishlist(_product!.id);
      if (!mounted) return;
      setState(() {
        _isWishlisted = state;
        _isWishlistProcessing = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(state ? 'Added to wishlist!' : 'Removed from wishlist.'),
          duration: const Duration(seconds: 1),
          backgroundColor: AppColors.primary,
        ),
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _isWishlistProcessing = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: AppColors.error),
      );
    }
  }

  Future<void> _toggleFollowStore() async {
    if (_product?.sellerId == null || _isFollowingLoading) return;
    setState(() => _isFollowingLoading = true);
    try {
      final res = await BuyerService.toggleFollowStore(_product!.sellerId!);
      if (!mounted) return;
      setState(() {
        _isFollowingStore = res['is_following'] == true;
        _followersCount = res['followers_count'] ?? _followersCount;
        _isFollowingLoading = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(_isFollowingStore ? 'Following store!' : 'Unfollowed store.'),
          duration: const Duration(seconds: 1),
          backgroundColor: AppColors.primary,
        ),
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _isFollowingLoading = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: AppColors.error),
      );
    }
  }

  Future<void> _claimVoucher(ProductStoreVoucherModel voucher) async {
    try {
      await BuyerService.claimVoucher(voucher.id);
      if (!mounted) return;
      setState(() {
        _claimedVoucherIds.add(voucher.id);
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Claimed voucher ${voucher.code}!'),
          backgroundColor: AppColors.primary,
        ),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: AppColors.error),
      );
    }
  }

  Future<void> _addToCart({bool buyNow = false}) async {
    if (_product == null || currentStock <= 0 || isPurchaseLimitReached) return;

    if (!ApiClient.isAuthenticated) {
      final loggedIn = await _showLoginPrompt();
      if (loggedIn != true) return;
    }

    if (buyNow) {
      if (!mounted) return;
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => CheckoutScreen(
            directBuy: DirectBuyModel(
              productId: _product!.id,
              productVariantId: _selectedVariant?.id,
              quantity: _quantity,
              productName: _product!.name,
              unitPrice: unitPrice,
              imageUrl: _product!.imageUrl,
              variantLabel: _selectedVariant != null
                  ? '${_selectedVariant!.color ?? ''} ${_selectedVariant!.size ?? ''}'.trim()
                  : null,
            ),
          ),
        ),
      );
      return;
    }

    setState(() => _isAddingToCart = true);

    try {
      await BuyerService.addToCart(
        productId: _product!.id,
        variantId: _selectedVariant?.id,
        quantity: _quantity,
      );

      if (!mounted) return;
      setState(() => _isAddingToCart = false);

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Row(
            children: [
              const Icon(Icons.check_circle_rounded, color: Colors.white, size: 20),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  'Added to cart successfully!',
                  style: GoogleFonts.figtree(fontWeight: FontWeight.w700),
                ),
              ),
            ],
          ),
          backgroundColor: AppColors.primary,
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          action: SnackBarAction(
            label: 'View Cart',
            textColor: const Color(0xFFF4B942),
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const CartScreen()),
              );
            },
          ),
        ),
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _isAddingToCart = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(e.toString()),
          backgroundColor: AppColors.error,
        ),
      );
    }
  }

  void _openVariantBottomSheet({required bool buyNow}) {
    if (_product == null || currentStock <= 0 || isPurchaseLimitReached) return;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setModalState) {
            final availableSizes = _sizesForColor(_selectedColor);
            final variantLabel = _selectedVariant != null
                ? '${_selectedVariant!.color ?? ''} ${_selectedVariant!.size ?? ''}'.trim()
                : '';

            return Container(
              decoration: const BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
              ),
              padding: EdgeInsets.only(
                left: 20,
                right: 20,
                top: 12,
                bottom: MediaQuery.of(context).viewInsets.bottom + 20,
              ),
              child: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Drag Handle
                    Center(
                      child: Container(
                        width: 38,
                        height: 4,
                        margin: const EdgeInsets.only(bottom: 14),
                        decoration: BoxDecoration(
                          color: Colors.grey.shade300,
                          borderRadius: BorderRadius.circular(2),
                        ),
                      ),
                    ),

                    // Header row: Image, Price, Stock, Variant label, Close button
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        NetworkImageBox(
                          imageUrl: _product!.imageUrl,
                          width: 80,
                          height: 80,
                          borderRadius: BorderRadius.circular(12),
                          fit: BoxFit.cover,
                        ),
                        const SizedBox(width: 14),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                CurrencyFormatter.format(unitPrice),
                                style: GoogleFonts.figtree(
                                  fontSize: 20,
                                  fontWeight: FontWeight.w900,
                                  color: AppColors.primary,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                'Stock: $currentStock',
                                style: GoogleFonts.figtree(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w600,
                                  color: currentStock > 0 ? const Color(0xFF64748B) : Colors.red,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                variantLabel.isNotEmpty
                                    ? 'Selected: $variantLabel'
                                    : (_product!.variants.isNotEmpty
                                        ? 'Please select a variation'
                                        : _product!.name),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: GoogleFonts.figtree(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w700,
                                  color: const Color(0xFF1F2937),
                                ),
                              ),
                            ],
                          ),
                        ),
                        IconButton(
                          onPressed: () => Navigator.pop(context),
                          icon: const Icon(Icons.close_rounded, color: Color(0xFF64748B)),
                          padding: EdgeInsets.zero,
                          constraints: const BoxConstraints(),
                        ),
                      ],
                    ),

                    const SizedBox(height: 16),
                    const Divider(height: 1),

                    // Color options
                    if (_colors.isNotEmpty) ...[
                      const SizedBox(height: 16),
                      Text(
                        'Color',
                        style: GoogleFonts.figtree(
                          fontSize: 13,
                          fontWeight: FontWeight.w800,
                          color: const Color(0xFF1F2937),
                        ),
                      ),
                      const SizedBox(height: 8),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: _colors.map((c) {
                          final isSelected = _selectedColor == c;
                          return GestureDetector(
                            onTap: () {
                              _selectColor(c);
                              setModalState(() {});
                            },
                            child: Container(
                              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                              decoration: BoxDecoration(
                                color: isSelected ? const Color(0xFFE8F7F6) : Colors.white,
                                borderRadius: BorderRadius.circular(10),
                                border: Border.all(
                                  color: isSelected ? AppColors.primary : const Color(0xFFE5E7EB),
                                  width: isSelected ? 1.5 : 1,
                                ),
                              ),
                              child: Text(
                                c,
                                style: GoogleFonts.figtree(
                                  fontSize: 12,
                                  fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
                                  color: isSelected ? AppColors.primary : const Color(0xFF64748B),
                                ),
                              ),
                            ),
                          );
                        }).toList(),
                      ),
                    ],

                    // Size options
                    if (availableSizes.isNotEmpty) ...[
                      const SizedBox(height: 16),
                      Text(
                        'Size',
                        style: GoogleFonts.figtree(
                          fontSize: 13,
                          fontWeight: FontWeight.w800,
                          color: const Color(0xFF1F2937),
                        ),
                      ),
                      const SizedBox(height: 8),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: availableSizes.map((s) {
                          final isSelected = _selectedSize == s;
                          final isOutOfStock = _isSizeOutOfStock(s);
                          return GestureDetector(
                            onTap: isOutOfStock
                                ? null
                                : () {
                                    _selectSize(s);
                                    setModalState(() {});
                                  },
                            child: Container(
                              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                              decoration: BoxDecoration(
                                color: isOutOfStock
                                    ? Colors.grey.shade100
                                    : (isSelected ? const Color(0xFFE8F7F6) : Colors.white),
                                borderRadius: BorderRadius.circular(10),
                                border: Border.all(
                                  color: isSelected ? AppColors.primary : const Color(0xFFE5E7EB),
                                  width: isSelected ? 1.5 : 1,
                                ),
                              ),
                              child: Text(
                                s,
                                style: GoogleFonts.figtree(
                                  fontSize: 12,
                                  fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
                                  color: isOutOfStock
                                      ? Colors.grey.shade400
                                      : (isSelected ? AppColors.primary : const Color(0xFF64748B)),
                                  decoration: isOutOfStock ? TextDecoration.lineThrough : null,
                                ),
                              ),
                            ),
                          );
                        }).toList(),
                      ),
                    ],

                    // Quantity selector
                    const SizedBox(height: 18),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          'Quantity',
                          style: GoogleFonts.figtree(
                            fontSize: 13,
                            fontWeight: FontWeight.w800,
                            color: const Color(0xFF1F2937),
                          ),
                        ),
                        Container(
                          decoration: BoxDecoration(
                            border: Border.all(color: const Color(0xFFE5E7EB)),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              InkWell(
                                onTap: _quantity > 1
                                    ? () {
                                        setState(() => _quantity--);
                                        setModalState(() {});
                                      }
                                    : null,
                                borderRadius: const BorderRadius.horizontal(left: Radius.circular(8)),
                                child: Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                                  child: Icon(
                                    Icons.remove,
                                    size: 16,
                                    color: _quantity > 1 ? const Color(0xFF1F2937) : Colors.grey.shade300,
                                  ),
                                ),
                              ),
                              Container(
                                constraints: const BoxConstraints(minWidth: 32),
                                alignment: Alignment.center,
                                child: Text(
                                  '$_quantity',
                                  style: GoogleFonts.figtree(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ),
                              InkWell(
                                onTap: _quantity < currentStock
                                    ? () {
                                        setState(() => _quantity++);
                                        setModalState(() {});
                                      }
                                    : null,
                                borderRadius: const BorderRadius.horizontal(right: Radius.circular(8)),
                                child: Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                                  child: Icon(
                                    Icons.add,
                                    size: 16,
                                    color: _quantity < currentStock ? const Color(0xFF1F2937) : Colors.grey.shade300,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),

                    const SizedBox(height: 22),

                    // Confirmation Action Button
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton(
                        onPressed: currentStock > 0 && !isPurchaseLimitReached && !_product!.sellerIsOnVacation
                            ? () {
                                Navigator.pop(context);
                                _addToCart(buyNow: buyNow);
                              }
                            : null,
                        style: ElevatedButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 14),
                          backgroundColor: AppColors.primary,
                          foregroundColor: Colors.white,
                          elevation: 0,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                        ),
                        child: Text(
                          buyNow ? 'Buy Now' : 'Add to Cart',
                          style: GoogleFonts.figtree(
                            fontSize: 15,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }

  Future<bool?> _showLoginPrompt() async {
    return showModalBottomSheet<bool>(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (ctx) => Container(
        padding: const EdgeInsets.all(24),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        child: SafeArea(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 56,
                height: 56,
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.1),
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.lock_outline_rounded, color: AppColors.primary, size: 28),
              ),
              const SizedBox(height: 16),
              Text(
                'Sign In to Continue',
                style: GoogleFonts.figtree(
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                'You need to be signed in to add items to your cart and complete purchases.',
                textAlign: TextAlign.center,
                style: GoogleFonts.figtree(
                  fontSize: 13,
                  color: AppColors.textSecondary,
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 24),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: () async {
                    Navigator.pop(ctx, false);
                    final authState = AuthState();
                    await authState.init();
                    if (!mounted) return;
                    final loggedIn = await Navigator.push<bool>(
                      context,
                      MaterialPageRoute(
                        builder: (_) => BuyerLoginScreen(authState: authState),
                      ),
                    );
                    if (loggedIn == true && mounted) {
                      _addToCart();
                    }
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.primary,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                    padding: const EdgeInsets.symmetric(vertical: 14),
                  ),
                  child: Text(
                    'Sign In / Register',
                    style: GoogleFonts.figtree(fontSize: 14, fontWeight: FontWeight.w700),
                  ),
                ),
              ),
              const SizedBox(height: 10),
              TextButton(
                onPressed: () => Navigator.pop(ctx, false),
                child: Text(
                  'Cancel',
                  style: GoogleFonts.figtree(color: AppColors.textMuted, fontWeight: FontWeight.w600),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _showReportDialog() {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: Row(
          children: [
            const Icon(Icons.flag_outlined, color: Color(0xFFDC2626), size: 22),
            const SizedBox(width: 8),
            Text(
              'Report Product',
              style: GoogleFonts.figtree(fontWeight: FontWeight.bold, fontSize: 16),
            ),
          ],
        ),
        content: Text(
          'Do you want to report "${_product?.name}" for policy violation or inappropriate content?',
          style: GoogleFonts.figtree(fontSize: 13, color: const Color(0xFF64748B)),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: Text('Cancel', style: GoogleFonts.figtree(color: const Color(0xFF64748B))),
          ),
          ElevatedButton(
            onPressed: () {
              Navigator.pop(ctx);
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(
                  content: Text('Report submitted. Our team will review this listing.'),
                  backgroundColor: AppColors.primary,
                ),
              );
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: const Color(0xFFDC2626),
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
            ),
            child: const Text('Submit Report'),
          ),
        ],
      ),
    );
  }

  Future<void> _openChatWithSeller() async {
    if (_product == null) return;
    if (!ApiClient.isAuthenticated) {
      final loggedIn = await _showLoginPrompt();
      if (loggedIn != true) return;
    }

    if (!mounted) return;

    try {
      showDialog(
        context: context,
        barrierDismissible: false,
        builder: (_) => const Center(
          child: CircularProgressIndicator(color: AppColors.primary),
        ),
      );

      final convId = await BuyerService.startConversationWithProduct(_product!.id);

      if (!mounted) return;
      Navigator.pop(context); // pop loading dialog

      if (!mounted) return;
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => BuyerChatDetailScreen(
            conversationId: convId,
            title: _product!.sellerStoreName ?? _product!.sellerName ?? 'Seller',
            sellerId: _product!.sellerId,
            productName: _product!.name,
            productImage: _product!.imageUrl,
            productPrice: unitPrice,
          ),
        ),
      );
    } catch (e) {
      if (!mounted) return;
      Navigator.pop(context); // pop loading dialog
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Could not open chat: $e'), backgroundColor: AppColors.error),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return Scaffold(
        backgroundColor: const Color(0xFFF8FAF9),
        appBar: AppBar(
          backgroundColor: Colors.white,
          elevation: 0,
          leading: IconButton(
            icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF1F2937)),
            onPressed: () => Navigator.pop(context),
          ),
        ),
        body: const Center(
          child: CircularProgressIndicator(color: AppColors.primary),
        ),
      );
    }

    if (_error != null || _product == null) {
      return Scaffold(
        backgroundColor: const Color(0xFFF8FAF9),
        appBar: AppBar(
          backgroundColor: Colors.white,
          elevation: 0,
          leading: IconButton(
            icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF1F2937)),
            onPressed: () => Navigator.pop(context),
          ),
        ),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(Icons.error_outline_rounded, size: 48, color: AppColors.error),
                const SizedBox(height: 16),
                Text(
                  _error ?? 'Product not found.',
                  textAlign: TextAlign.center,
                  style: GoogleFonts.figtree(fontSize: 14, color: const Color(0xFF64748B)),
                ),
                const SizedBox(height: 16),
                ElevatedButton(
                  onPressed: _loadProduct,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.primary,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                  ),
                  child: const Text('Retry'),
                ),
              ],
            ),
          ),
        ),
      );
    }

    final p = _product!;
    final gallery = _galleryImages;
    final activeImg = gallery.isNotEmpty
        ? gallery[_selectedImageIndex < gallery.length ? _selectedImageIndex : 0]
        : '';

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAF9),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF1F2937)),
          onPressed: () => Navigator.pop(context),
        ),
        title: Text(
          p.name,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: GoogleFonts.figtree(
            fontSize: 16,
            fontWeight: FontWeight.bold,
            color: const Color(0xFF1F2937),
          ),
        ),
        actions: [
          IconButton(
            icon: Icon(
              _isWishlisted ? Icons.favorite_rounded : Icons.favorite_border_rounded,
              color: _isWishlisted ? const Color(0xFFE11D48) : const Color(0xFF64748B),
            ),
            onPressed: _toggleWishlist,
          ),
          IconButton(
            icon: const Icon(Icons.shopping_bag_outlined, color: Color(0xFF64748B)),
            onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const CartScreen())),
          ),
        ],
      ),
      body: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // BREADCRUMBS (Home > Category > Product)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
              child: Row(
                children: [
                  GestureDetector(
                    onTap: () => Navigator.popUntil(context, (r) => r.isFirst),
                    child: Text(
                      'Home',
                      style: GoogleFonts.figtree(fontSize: 11, color: const Color(0xFF64748B), fontWeight: FontWeight.w600),
                    ),
                  ),
                  const SizedBox(width: 4),
                  const Icon(Icons.chevron_right_rounded, size: 14, color: Color(0xFFCBD5E1)),
                  const SizedBox(width: 4),
                  GestureDetector(
                    onTap: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => ProductListScreen(categoryName: p.categoryName),
                        ),
                      );
                    },
                    child: Text(
                      p.categoryName ?? 'Marketplace',
                      style: GoogleFonts.figtree(fontSize: 11, color: AppColors.primary, fontWeight: FontWeight.w700),
                    ),
                  ),
                  const SizedBox(width: 4),
                  const Icon(Icons.chevron_right_rounded, size: 14, color: Color(0xFFCBD5E1)),
                  const SizedBox(width: 4),
                  Expanded(
                    child: Text(
                      p.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: GoogleFonts.figtree(fontSize: 11, color: const Color(0xFF1F2937), fontWeight: FontWeight.w700),
                    ),
                  ),
                ],
              ),
            ),

            // MAIN PRODUCT CONTAINER CARD (rounded-2xl border border-[#E5E7EB] bg-white)
            Container(
              margin: const EdgeInsets.symmetric(horizontal: 16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(24),
                border: Border.all(color: const Color(0xFFE5E7EB)),
                boxShadow: const [
                  BoxShadow(color: Color(0x0A000000), blurRadius: 10, offset: Offset(0, 4)),
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // ========================================================
                  // IMAGE GALLERY (Container #E8F7F6 with rounded top)
                  // ========================================================
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(20),
                    decoration: const BoxDecoration(
                      color: Color(0xFFE8F7F6),
                      borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
                    ),
                    child: Column(
                      children: [
                        // Floating Heart + Large White Center Card
                        Stack(
                          alignment: Alignment.center,
                          children: [
                            Container(
                              height: 250,
                              width: 250,
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(22),
                                boxShadow: const [
                                  BoxShadow(
                                    color: Color(0x14000000),
                                    blurRadius: 14,
                                    offset: Offset(0, 4),
                                  ),
                                ],
                              ),
                              child: ClipRRect(
                                borderRadius: BorderRadius.circular(22),
                                child: NetworkImageBox(
                                  imageUrl: activeImg,
                                  width: 250,
                                  height: 250,
                                  fit: BoxFit.cover,
                                ),
                              ),
                            ),

                            // Floating Wishlist Button on Top Right
                            Positioned(
                              top: 0,
                              right: 0,
                              child: GestureDetector(
                                onTap: _toggleWishlist,
                                child: Container(
                                  width: 40,
                                  height: 40,
                                  decoration: const BoxDecoration(
                                    color: Color(0xE6FFFFFF),
                                    shape: BoxShape.circle,
                                    boxShadow: [
                                      BoxShadow(color: Color(0x14000000), blurRadius: 6, offset: Offset(0, 2)),
                                    ],
                                  ),
                                  child: Center(
                                    child: Icon(
                                      _isWishlisted ? Icons.favorite_rounded : Icons.favorite_border_rounded,
                                      size: 20,
                                      color: _isWishlisted ? const Color(0xFFE11D48) : const Color(0xFF64748B),
                                    ),
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ),

                        // Thumbnails Strip
                        if (gallery.length > 1) ...[
                          const SizedBox(height: 16),
                          SizedBox(
                            height: 56,
                            child: ListView.separated(
                              scrollDirection: Axis.horizontal,
                              shrinkWrap: true,
                              itemCount: gallery.length,
                              separatorBuilder: (_, _) => const SizedBox(width: 10),
                              itemBuilder: (context, index) {
                                final isSelected = _selectedImageIndex == index;
                                return GestureDetector(
                                  onTap: () => setState(() => _selectedImageIndex = index),
                                  child: Container(
                                    width: 56,
                                    height: 56,
                                    decoration: BoxDecoration(
                                      color: Colors.white,
                                      borderRadius: BorderRadius.circular(10),
                                      border: Border.all(
                                        color: isSelected ? AppColors.primary : const Color(0xFFE5E7EB),
                                        width: isSelected ? 2.5 : 1,
                                      ),
                                    ),
                                    child: ClipRRect(
                                      borderRadius: BorderRadius.circular(8),
                                      child: NetworkImageBox(
                                        imageUrl: gallery[index],
                                        fit: BoxFit.cover,
                                      ),
                                    ),
                                  ),
                                );
                              },
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),

                  // ========================================================
                  // PRODUCT INFORMATION
                  // ========================================================
                  Padding(
                    padding: const EdgeInsets.all(20),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Category Label
                        Text(
                          (p.categoryName ?? 'PRODUCT').toUpperCase(),
                          style: GoogleFonts.figtree(
                            fontSize: 11,
                            fontWeight: FontWeight.w800,
                            color: AppColors.primary,
                            letterSpacing: 1.0,
                          ),
                        ),
                        const SizedBox(height: 6),

                        // Product Title
                        Text(
                          p.name,
                          style: GoogleFonts.figtree(
                            fontSize: 20,
                            fontWeight: FontWeight.w800,
                            color: const Color(0xFF1F2937),
                            height: 1.3,
                          ),
                        ),

                        // STORE CARD (#F8FAF9 rounded-2xl)
                        if (p.sellerId != null || p.sellerName != null) ...[
                          const SizedBox(height: 16),
                          Container(
                            padding: const EdgeInsets.all(14),
                            decoration: BoxDecoration(
                              color: const Color(0xFFF8FAF9),
                              borderRadius: BorderRadius.circular(18),
                              border: Border.all(color: const Color(0xFFE5E7EB)),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                InkWell(
                                  onTap: () {
                                    if (p.sellerId != null && p.sellerId! > 0) {
                                      Navigator.push(
                                        context,
                                        MaterialPageRoute(
                                          builder: (_) => BuyerStoreScreen(
                                            sellerId: p.sellerId!,
                                            storeName: p.sellerStoreName ?? p.sellerName,
                                          ),
                                        ),
                                      );
                                    }
                                  },
                                  child: Row(
                                    children: [
                                      // Store Logo or Initial
                                      Container(
                                        width: 46,
                                        height: 46,
                                        decoration: BoxDecoration(
                                          color: const Color(0xFFE8F7F6),
                                          borderRadius: BorderRadius.circular(12),
                                          border: Border.all(color: const Color(0xFFE5E7EB)),
                                        ),
                                        child: ClipRRect(
                                          borderRadius: BorderRadius.circular(12),
                                          child: p.sellerLogoUrl != null && p.sellerLogoUrl!.isNotEmpty
                                              ? NetworkImageBox(imageUrl: p.sellerLogoUrl!, fit: BoxFit.cover)
                                              : Center(
                                                  child: Text(
                                                    (p.sellerStoreName ?? p.sellerName ?? 'A').substring(0, 1).toUpperCase(),
                                                    style: GoogleFonts.figtree(
                                                      fontSize: 18,
                                                      fontWeight: FontWeight.w900,
                                                      color: AppColors.primary,
                                                    ),
                                                  ),
                                                ),
                                        ),
                                      ),
                                      const SizedBox(width: 12),

                                      // Store Info
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment: CrossAxisAlignment.start,
                                          children: [
                                            Text(
                                              'STORE',
                                              style: GoogleFonts.figtree(
                                                fontSize: 9.5,
                                                fontWeight: FontWeight.w800,
                                                color: AppColors.primary,
                                                letterSpacing: 1.1,
                                              ),
                                            ),
                                            const SizedBox(height: 2),
                                            Text(
                                              p.sellerStoreName ?? p.sellerName ?? 'Alona Merchant',
                                              maxLines: 1,
                                              overflow: TextOverflow.ellipsis,
                                              style: GoogleFonts.figtree(
                                                fontSize: 14,
                                                fontWeight: FontWeight.w800,
                                                color: const Color(0xFF1F2937),
                                              ),
                                            ),
                                            const SizedBox(height: 3),
                                            Row(
                                              children: [
                                                const Icon(Icons.star_rounded, size: 14, color: Color(0xFFF4B942)),
                                                const SizedBox(width: 2),
                                                Text(
                                                  p.sellerRating.toStringAsFixed(1),
                                                  style: GoogleFonts.figtree(
                                                    fontSize: 11,
                                                    fontWeight: FontWeight.w700,
                                                    color: const Color(0xFF1F2937),
                                                  ),
                                                ),
                                                const SizedBox(width: 8),
                                                Container(width: 3, height: 3, decoration: const BoxDecoration(color: Color(0xFFCBD5E1), shape: BoxShape.circle)),
                                                const SizedBox(width: 8),
                                                Text(
                                                  '${p.sellerProductsCount} products',
                                                  style: GoogleFonts.figtree(fontSize: 11, color: const Color(0xFF64748B)),
                                                ),
                                                const SizedBox(width: 8),
                                                Container(width: 3, height: 3, decoration: const BoxDecoration(color: Color(0xFFCBD5E1), shape: BoxShape.circle)),
                                                const SizedBox(width: 8),
                                                Text(
                                                  '$_followersCount followers',
                                                  style: GoogleFonts.figtree(fontSize: 11, color: const Color(0xFF64748B)),
                                                ),
                                              ],
                                            ),
                                          ],
                                        ),
                                      ),
                                      const Icon(Icons.chevron_right, color: Color(0xFF94A3B8), size: 20),
                                    ],
                                  ),
                                ),

                                // Store Buttons (Follow + Visit Store)
                                const SizedBox(height: 12),
                                Row(
                                  children: [
                                    Expanded(
                                      child: OutlinedButton(
                                        onPressed: _isFollowingLoading ? null : _toggleFollowStore,
                                        style: OutlinedButton.styleFrom(
                                          padding: const EdgeInsets.symmetric(vertical: 8),
                                          side: BorderSide(
                                            color: _isFollowingStore ? const Color(0xFF16A34A) : AppColors.primary,
                                          ),
                                          backgroundColor: _isFollowingStore ? const Color(0xFFDCFCE7) : Colors.white,
                                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                        ),
                                        child: Text(
                                          _isFollowingStore ? '✓ Following' : '+ Follow',
                                          style: GoogleFonts.figtree(
                                            fontSize: 11,
                                            fontWeight: FontWeight.w800,
                                            color: _isFollowingStore ? const Color(0xFF16A34A) : AppColors.primary,
                                          ),
                                        ),
                                      ),
                                    ),
                                    const SizedBox(width: 8),
                                    Expanded(
                                      child: ElevatedButton(
                                        onPressed: () {
                                          if (p.sellerId != null && p.sellerId! > 0) {
                                            Navigator.push(
                                              context,
                                              MaterialPageRoute(
                                                builder: (_) => BuyerStoreScreen(
                                                  sellerId: p.sellerId!,
                                                  storeName: p.sellerStoreName ?? p.sellerName,
                                                ),
                                              ),
                                            );
                                          } else {
                                            Navigator.push(
                                              context,
                                              MaterialPageRoute(
                                                builder: (_) => ProductListScreen(initialSearch: p.sellerStoreName ?? p.sellerName ?? ''),
                                              ),
                                            );
                                          }
                                        },
                                        style: ElevatedButton.styleFrom(
                                          padding: const EdgeInsets.symmetric(vertical: 8),
                                          backgroundColor: Colors.white,
                                          foregroundColor: AppColors.primary,
                                          elevation: 0,
                                          side: const BorderSide(color: AppColors.primary),
                                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                                        ),
                                        child: Row(
                                          mainAxisAlignment: MainAxisAlignment.center,
                                          children: [
                                            Text(
                                              'Visit Store',
                                              style: GoogleFonts.figtree(fontSize: 11, fontWeight: FontWeight.w800),
                                            ),
                                            const SizedBox(width: 4),
                                            const Icon(Icons.arrow_forward_rounded, size: 12),
                                          ],
                                        ),
                                      ),
                                    ),
                                  ],
                                ),

                                // Vacation Mode Alert Banner
                                if (p.sellerIsOnVacation) ...[
                                  const SizedBox(height: 10),
                                  Container(
                                    padding: const EdgeInsets.all(10),
                                    decoration: BoxDecoration(
                                      color: const Color(0xFFFFFBEB),
                                      borderRadius: BorderRadius.circular(12),
                                      border: Border.all(color: const Color(0xFFFDE68A)),
                                    ),
                                    child: Row(
                                      children: [
                                        const Icon(Icons.warning_amber_rounded, size: 16, color: Color(0xFFD97706)),
                                        const SizedBox(width: 8),
                                        Expanded(
                                          child: Text(
                                            'Store on Vacation: ${p.sellerVacationMessage ?? "Ordering from this store is temporarily paused."}',
                                            style: GoogleFonts.figtree(
                                              fontSize: 11,
                                              color: const Color(0xFF92400E),
                                              fontWeight: FontWeight.w600,
                                            ),
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ],
                              ],
                            ),
                          ),
                        ],

                        // PRICING ROW
                        const SizedBox(height: 18),
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.center,
                          children: [
                            Text(
                              CurrencyFormatter.format(unitPrice),
                              style: GoogleFonts.figtree(
                                fontSize: 24,
                                fontWeight: FontWeight.w900,
                                color: const Color(0xFF1F2937),
                              ),
                            ),
                            if (p.oldPrice != null && p.oldPrice! > unitPrice) ...[
                              const SizedBox(width: 8),
                              Text(
                                CurrencyFormatter.format(p.oldPrice),
                                style: GoogleFonts.figtree(
                                  fontSize: 14,
                                  decoration: TextDecoration.lineThrough,
                                  color: const Color(0xFF64748B),
                                  fontWeight: FontWeight.w500,
                                ),
                              ),
                              const SizedBox(width: 8),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFFFF1F1),
                                  borderRadius: BorderRadius.circular(6),
                                ),
                                child: Text(
                                  '−${p.discountPercentage}%',
                                  style: GoogleFonts.figtree(
                                    fontSize: 11,
                                    fontWeight: FontWeight.w800,
                                    color: const Color(0xFFE85D5D),
                                  ),
                                ),
                              ),
                            ],
                          ],
                        ),

                        // ACTIVE FLASH SALE BANNER (with ticking countdown)
                        if (p.hasFlashSale) ...[
                          const SizedBox(height: 14),
                          Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: const Color(0xFFFFFBEB),
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(color: const Color(0xFFFCD34D)),
                            ),
                            child: Row(
                              children: [
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                  decoration: BoxDecoration(
                                    color: const Color(0xFFF59E0B),
                                    borderRadius: BorderRadius.circular(6),
                                  ),
                                  child: Row(
                                    children: [
                                      const Icon(Icons.bolt_rounded, size: 14, color: Colors.white),
                                      const SizedBox(width: 2),
                                      Text(
                                        'Flash Sale',
                                        style: GoogleFonts.figtree(
                                          fontSize: 10.5,
                                          fontWeight: FontWeight.w900,
                                          color: Colors.white,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                const SizedBox(width: 10),
                                if (p.flashRemaining != null)
                                  Text(
                                    '${p.flashRemaining} left',
                                    style: GoogleFonts.figtree(
                                      fontSize: 11,
                                      fontWeight: FontWeight.w700,
                                      color: const Color(0xFFB45309),
                                    ),
                                  ),
                                const Spacer(),
                                Row(
                                  children: [
                                    Text(
                                      'Ends in ',
                                      style: GoogleFonts.figtree(
                                        fontSize: 11,
                                        fontWeight: FontWeight.w600,
                                        color: const Color(0xFFB45309),
                                      ),
                                    ),
                                    _buildTimerBadge(_remainingTime.inHours.toString().padLeft(2, '0')),
                                    const Text(' : ', style: TextStyle(fontWeight: FontWeight.bold, color: Color(0xFFB45309))),
                                    _buildTimerBadge((_remainingTime.inMinutes % 60).toString().padLeft(2, '0')),
                                    const Text(' : ', style: TextStyle(fontWeight: FontWeight.bold, color: Color(0xFFB45309))),
                                    _buildTimerBadge((_remainingTime.inSeconds % 60).toString().padLeft(2, '0')),
                                  ],
                                ),
                              ],
                            ),
                          ),
                        ],

                        // CLAIMABLE STORE VOUCHERS
                        if (p.vouchers.isNotEmpty) ...[
                          const SizedBox(height: 16),
                          Container(
                            padding: const EdgeInsets.all(12),
                            decoration: BoxDecoration(
                              color: const Color(0xFFE8F7F6).withValues(alpha: 0.6),
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(color: const Color(0xFFB2DFDB)),
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'STORE VOUCHERS AVAILABLE',
                                  style: GoogleFonts.figtree(
                                    fontSize: 10,
                                    fontWeight: FontWeight.w800,
                                    color: AppColors.primary,
                                    letterSpacing: 0.8,
                                  ),
                                ),
                                const SizedBox(height: 8),
                                Wrap(
                                  spacing: 8,
                                  runSpacing: 8,
                                  children: p.vouchers.map((v) {
                                    final isClaimed = _claimedVoucherIds.contains(v.id) || v.isClaimed;
                                    return Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                                      decoration: BoxDecoration(
                                        color: Colors.white,
                                        borderRadius: BorderRadius.circular(8),
                                        border: Border.all(color: AppColors.primary.withValues(alpha: 0.3)),
                                      ),
                                      child: Row(
                                        mainAxisSize: MainAxisSize.min,
                                        children: [
                                          Text(
                                            v.claimLabel,
                                            style: GoogleFonts.figtree(
                                              fontSize: 11,
                                              fontWeight: FontWeight.w800,
                                              color: const Color(0xFF1F2937),
                                            ),
                                          ),
                                          const SizedBox(width: 8),
                                          GestureDetector(
                                            onTap: isClaimed ? null : () => _claimVoucher(v),
                                            child: Container(
                                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                              decoration: BoxDecoration(
                                                color: isClaimed ? const Color(0xFFE2E8F0) : AppColors.primary,
                                                borderRadius: BorderRadius.circular(6),
                                              ),
                                              child: Text(
                                                isClaimed ? 'Claimed' : 'Claim',
                                                style: GoogleFonts.figtree(
                                                  fontSize: 10,
                                                  fontWeight: FontWeight.w800,
                                                  color: isClaimed ? const Color(0xFF64748B) : Colors.white,
                                                ),
                                              ),
                                            ),
                                          ),
                                        ],
                                      ),
                                    );
                                  }).toList(),
                                ),
                              ],
                            ),
                          ),
                        ],

                        // DESCRIPTION
                        const SizedBox(height: 16),
                        Text(
                          p.description ?? 'No product description provided.',
                          style: GoogleFonts.figtree(
                            fontSize: 13,
                            color: const Color(0xFF64748B),
                            height: 1.5,
                          ),
                        ),

                        const SizedBox(height: 18),
                        const Divider(color: Color(0xFFE5E7EB)),
                        const SizedBox(height: 14),

                        // AVAILABLE STOCK & VARIATIONS (2 Grid Boxes)
                        Row(
                          children: [
                            Expanded(
                              child: Container(
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFF8FAF9),
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      'AVAILABLE STOCK',
                                      style: GoogleFonts.figtree(
                                        fontSize: 9.5,
                                        fontWeight: FontWeight.w800,
                                        color: const Color(0xFF64748B),
                                        letterSpacing: 0.5,
                                      ),
                                    ),
                                    const SizedBox(height: 4),
                                    Text(
                                      currentStock <= 0
                                          ? 'Sold out'
                                          : '$currentStock items available',
                                      style: GoogleFonts.figtree(
                                        fontSize: 12.5,
                                        fontWeight: FontWeight.w800,
                                        color: currentStock <= 5 ? const Color(0xFFE85D5D) : const Color(0xFF1F2937),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Container(
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFF8FAF9),
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      'AVAILABLE VARIATIONS',
                                      style: GoogleFonts.figtree(
                                        fontSize: 9.5,
                                        fontWeight: FontWeight.w800,
                                        color: const Color(0xFF64748B),
                                        letterSpacing: 0.5,
                                      ),
                                    ),
                                    const SizedBox(height: 4),
                                    Text(
                                      _colors.isNotEmpty
                                          ? '${_colors.length} color ${_colors.length == 1 ? "option" : "options"}'
                                          : 'Single option',
                                      style: GoogleFonts.figtree(
                                        fontSize: 12.5,
                                        fontWeight: FontWeight.w800,
                                        color: const Color(0xFF1F2937),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ],
                        ),

                        // COLOR SELECTION
                        if (_colors.isNotEmpty) ...[
                          const SizedBox(height: 18),
                          Text(
                            'Color',
                            style: GoogleFonts.figtree(
                              fontSize: 13,
                              fontWeight: FontWeight.w800,
                              color: const Color(0xFF1F2937),
                            ),
                          ),
                          const SizedBox(height: 8),
                          Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: _colors.map((c) {
                              final isSelected = _selectedColor == c;
                              return GestureDetector(
                                onTap: () => _selectColor(c),
                                child: Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                                  decoration: BoxDecoration(
                                    color: isSelected ? const Color(0xFFE8F7F6) : Colors.white,
                                    borderRadius: BorderRadius.circular(10),
                                    border: Border.all(
                                      color: isSelected ? AppColors.primary : const Color(0xFFE5E7EB),
                                      width: isSelected ? 1.5 : 1,
                                    ),
                                  ),
                                  child: Text(
                                    c,
                                    style: GoogleFonts.figtree(
                                      fontSize: 12,
                                      fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
                                      color: isSelected ? AppColors.primary : const Color(0xFF64748B),
                                    ),
                                  ),
                                ),
                              );
                            }).toList(),
                          ),
                        ],

                        // SIZE SELECTION
                        if (_sizesForColor(_selectedColor).isNotEmpty) ...[
                          const SizedBox(height: 16),
                          Text(
                            'Size',
                            style: GoogleFonts.figtree(
                              fontSize: 13,
                              fontWeight: FontWeight.w800,
                              color: const Color(0xFF1F2937),
                            ),
                          ),
                          const SizedBox(height: 8),
                          Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: _sizesForColor(_selectedColor).map((s) {
                              final isSelected = _selectedSize == s;
                              final isOutOfStock = _isSizeOutOfStock(s);
                              return GestureDetector(
                                onTap: isOutOfStock ? null : () => _selectSize(s),
                                child: Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                                  decoration: BoxDecoration(
                                    color: isOutOfStock
                                        ? const Color(0xFFF1F5F9)
                                        : isSelected
                                            ? const Color(0xFFE8F7F6)
                                            : Colors.white,
                                    borderRadius: BorderRadius.circular(10),
                                    border: Border.all(
                                      color: isOutOfStock
                                          ? const Color(0xFFE2E8F0)
                                          : isSelected
                                              ? AppColors.primary
                                              : const Color(0xFFE5E7EB),
                                      width: isSelected ? 1.5 : 1,
                                    ),
                                  ),
                                  child: Text(
                                    s,
                                    style: GoogleFonts.figtree(
                                      fontSize: 12,
                                      decoration: isOutOfStock ? TextDecoration.lineThrough : null,
                                      fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
                                      color: isOutOfStock
                                          ? const Color(0xFF94A3B8)
                                          : isSelected
                                              ? AppColors.primary
                                              : const Color(0xFF64748B),
                                    ),
                                  ),
                                ),
                              );
                            }).toList(),
                          ),
                        ],

                        // QUANTITY CONTROLS
                        const SizedBox(height: 20),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              'Quantity',
                              style: GoogleFonts.figtree(
                                fontSize: 13,
                                fontWeight: FontWeight.w800,
                                color: const Color(0xFF1F2937),
                              ),
                            ),
                            Text(
                              currentStock <= 0 ? 'Sold out' : '$currentStock items available',
                              style: GoogleFonts.figtree(
                                fontSize: 11,
                                color: const Color(0xFF64748B),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),

                        Row(
                          children: [
                            Container(
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(10),
                                border: Border.all(color: const Color(0xFFE5E7EB)),
                              ),
                              child: Row(
                                children: [
                                  IconButton(
                                    icon: const Icon(Icons.remove, size: 16),
                                    color: const Color(0xFF64748B),
                                    onPressed: _quantity > 1 ? () => setState(() => _quantity--) : null,
                                    constraints: const BoxConstraints(minWidth: 36, minHeight: 36),
                                    padding: EdgeInsets.zero,
                                  ),
                                  Container(
                                    width: 44,
                                    height: 36,
                                    decoration: const BoxDecoration(
                                      border: Border.symmetric(vertical: BorderSide(color: Color(0xFFE5E7EB))),
                                    ),
                                    alignment: Alignment.center,
                                    child: Text(
                                      '$_quantity',
                                      style: GoogleFonts.figtree(
                                        fontSize: 13,
                                        fontWeight: FontWeight.w800,
                                        color: const Color(0xFF1F2937),
                                      ),
                                    ),
                                  ),
                                  IconButton(
                                    icon: const Icon(Icons.add, size: 16),
                                    color: const Color(0xFF64748B),
                                    onPressed: _quantity < maxAllowedQuantity
                                        ? () => setState(() => _quantity++)
                                        : null,
                                    constraints: const BoxConstraints(minWidth: 36, minHeight: 36),
                                    padding: EdgeInsets.zero,
                                  ),
                                ],
                              ),
                            ),
                            if (effectivePurchaseLimit > 0) ...[
                              const SizedBox(width: 12),
                              Text(
                                '(Max $effectivePurchaseLimit per buyer)',
                                style: GoogleFonts.figtree(
                                  fontSize: 11,
                                  color: const Color(0xFF64748B),
                                ),
                              ),
                            ],
                          ],
                        ),

                        // TOTAL ROW
                        const SizedBox(height: 18),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF8FAF9),
                            borderRadius: BorderRadius.circular(14),
                          ),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text(
                                'Total',
                                style: GoogleFonts.figtree(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
                                  color: const Color(0xFF64748B),
                                ),
                              ),
                              Text(
                                CurrencyFormatter.format(totalPrice),
                                style: GoogleFonts.figtree(
                                  fontSize: 17,
                                  fontWeight: FontWeight.w900,
                                  color: const Color(0xFF1F2937),
                                ),
                              ),
                            ],
                          ),
                        ),

                        // SECONDARY ACTION BUTTONS (Wishlist, Message Seller, Report)
                        const SizedBox(height: 12),
                        Row(
                          children: [
                            OutlinedButton.icon(
                              onPressed: _toggleWishlist,
                              icon: Icon(
                                _isWishlisted ? Icons.favorite_rounded : Icons.favorite_border_rounded,
                                size: 16,
                                color: _isWishlisted ? const Color(0xFFE11D48) : const Color(0xFF64748B),
                              ),
                              label: Text(
                                _isWishlisted ? 'Wishlisted' : 'Wishlist',
                                style: GoogleFonts.figtree(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w700,
                                  color: _isWishlisted ? const Color(0xFFE11D48) : const Color(0xFF1F2937),
                                ),
                              ),
                              style: OutlinedButton.styleFrom(
                                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                                side: BorderSide(
                                  color: _isWishlisted ? const Color(0xFFFDA4AF) : const Color(0xFFE5E7EB),
                                ),
                                backgroundColor: _isWishlisted ? const Color(0xFFFFF1F2) : Colors.white,
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: OutlinedButton(
                                onPressed: _openChatWithSeller,
                                style: OutlinedButton.styleFrom(
                                  padding: const EdgeInsets.symmetric(vertical: 10),
                                  side: const BorderSide(color: Color(0xFFE5E7EB)),
                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                                ),
                                child: Text(
                                  'Message Seller',
                                  style: GoogleFonts.figtree(
                                    fontSize: 11,
                                    fontWeight: FontWeight.w700,
                                    color: const Color(0xFF1F2937),
                                  ),
                                ),
                              ),
                            ),
                            const SizedBox(width: 8),
                            OutlinedButton(
                              onPressed: _showReportDialog,
                              style: OutlinedButton.styleFrom(
                                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
                                side: const BorderSide(color: Color(0xFFFECACA)),
                                backgroundColor: const Color(0xFFFEF2F2),
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                              ),
                              child: const Icon(Icons.flag_outlined, size: 16, color: Color(0xFFDC2626)),
                            ),
                          ],
                        ),

                        // TRUST BADGES (Fast Delivery, Secure Payment, Easy Returns)
                        const SizedBox(height: 24),
                        const Divider(color: Color(0xFFE5E7EB)),
                        const SizedBox(height: 16),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceAround,
                          children: [
                            _buildTrustItem(Icons.local_shipping_outlined, 'Fast Delivery'),
                            _buildTrustItem(Icons.verified_user_outlined, 'Secure Payment'),
                            _buildTrustItem(Icons.replay_rounded, 'Easy Returns'),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),

            // ========================================================
            // REVIEWS & RATINGS SECTION
            // ========================================================
            Container(
              margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 18),
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(24),
                border: Border.all(color: const Color(0xFFE5E7EB)),
                boxShadow: const [
                  BoxShadow(color: Color(0x0A000000), blurRadius: 10, offset: Offset(0, 4)),
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                        width: 8,
                        height: 8,
                        decoration: const BoxDecoration(
                          color: Color(0xFFF4B942),
                          shape: BoxShape.circle,
                        ),
                      ),
                      const SizedBox(width: 8),
                      Text(
                        'CUSTOMER FEEDBACK',
                        style: GoogleFonts.figtree(
                          fontSize: 10,
                          fontWeight: FontWeight.w800,
                          color: AppColors.primary,
                          letterSpacing: 1.0,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Ratings & Reviews',
                    style: GoogleFonts.figtree(
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                      color: const Color(0xFF1F2937),
                    ),
                  ),
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      Row(
                        children: List.generate(
                          5,
                          (i) => Icon(
                            Icons.star_rounded,
                            size: 18,
                            color: i < p.rating.round() ? const Color(0xFFF4B942) : const Color(0xFFE5E7EB),
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      Text(
                        p.rating > 0 ? p.rating.toStringAsFixed(1) : '5.0',
                        style: GoogleFonts.figtree(
                          fontSize: 13,
                          fontWeight: FontWeight.w800,
                          color: const Color(0xFF1F2937),
                        ),
                      ),
                      const SizedBox(width: 4),
                      Text(
                        '• ${p.reviewsCount > 0 ? p.reviewsCount : p.reviews.length} reviews',
                        style: GoogleFonts.figtree(
                          fontSize: 12,
                          color: const Color(0xFF64748B),
                        ),
                      ),
                    ],
                  ),

                  const SizedBox(height: 16),
                  const Divider(color: Color(0xFFE5E7EB)),

                  // REVIEW LIST OR EMPTY STATE
                  if (p.reviews.isNotEmpty) ...[
                    ListView.separated(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      itemCount: p.reviews.length,
                      separatorBuilder: (_, _) => const Divider(color: Color(0xFFE5E7EB), height: 28),
                      itemBuilder: (context, index) {
                        final rev = p.reviews[index];
                        return Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Container(
                                  width: 36,
                                  height: 36,
                                  decoration: const BoxDecoration(
                                    color: Color(0xFFE8F7F6),
                                    shape: BoxShape.circle,
                                  ),
                                  child: Center(
                                    child: Text(
                                      rev.userName.substring(0, 1).toUpperCase(),
                                      style: GoogleFonts.figtree(
                                        fontSize: 13,
                                        fontWeight: FontWeight.w800,
                                        color: AppColors.primary,
                                      ),
                                    ),
                                  ),
                                ),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        rev.userName,
                                        style: GoogleFonts.figtree(
                                          fontSize: 12.5,
                                          fontWeight: FontWeight.w800,
                                          color: const Color(0xFF1F2937),
                                        ),
                                      ),
                                      Text(
                                        rev.createdAt ?? 'Verified Buyer',
                                        style: GoogleFonts.figtree(
                                          fontSize: 10,
                                          color: const Color(0xFF64748B),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                Row(
                                  children: List.generate(
                                    5,
                                    (i) => Icon(
                                      Icons.star_rounded,
                                      size: 14,
                                      color: i < rev.rating ? const Color(0xFFF4B942) : const Color(0xFFE5E7EB),
                                    ),
                                  ),
                                ),
                              ],
                            ),
                            if (rev.comment != null && rev.comment!.isNotEmpty) ...[
                              const SizedBox(height: 10),
                              Container(
                                width: double.infinity,
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFF8FAF9),
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: Text(
                                  rev.comment!,
                                  style: GoogleFonts.figtree(
                                    fontSize: 12,
                                    color: const Color(0xFF475569),
                                    height: 1.4,
                                  ),
                                ),
                              ),
                            ],
                            if (rev.sellerReply != null && rev.sellerReply!.isNotEmpty) ...[
                              const SizedBox(height: 8),
                              Container(
                                margin: const EdgeInsets.only(left: 14),
                                padding: const EdgeInsets.all(10),
                                decoration: const BoxDecoration(
                                  color: Color(0xFFE8F7F6),
                                  borderRadius: BorderRadius.horizontal(right: Radius.circular(10)),
                                  border: Border(left: BorderSide(color: Color(0xFF16A6A0), width: 3)),
                                ),
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      'Seller response',
                                      style: GoogleFonts.figtree(
                                        fontSize: 9.5,
                                        fontWeight: FontWeight.w800,
                                        color: AppColors.primary,
                                      ),
                                    ),
                                    const SizedBox(height: 2),
                                    Text(
                                      rev.sellerReply!,
                                      style: GoogleFonts.figtree(
                                        fontSize: 11,
                                        color: const Color(0xFF475569),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ],
                        );
                      },
                    ),
                  ] else ...[
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 20),
                      child: Center(
                        child: Column(
                          children: [
                            Container(
                              width: 48,
                              height: 48,
                              decoration: const BoxDecoration(
                                color: Color(0xFFE8F7F6),
                                shape: BoxShape.circle,
                              ),
                              child: const Icon(Icons.star_rounded, size: 28, color: Color(0xFFF4B942)),
                            ),
                            const SizedBox(height: 10),
                            Text(
                              'No reviews yet',
                              style: GoogleFonts.figtree(
                                fontSize: 14,
                                fontWeight: FontWeight.w800,
                                color: const Color(0xFF1F2937),
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              'Be the first customer to share your experience with this product.',
                              textAlign: TextAlign.center,
                              style: GoogleFonts.figtree(
                                fontSize: 11.5,
                                color: const Color(0xFF64748B),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),

            // ========================================================
            // RELATED PRODUCTS SECTION ("You might also like")
            // ========================================================
            if (_related.isNotEmpty) ...[
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'You might also like',
                      style: GoogleFonts.figtree(
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                        color: const Color(0xFF1F2937),
                      ),
                    ),
                    const SizedBox(height: 12),
                    SizedBox(
                      height: 245,
                      child: ListView.separated(
                        scrollDirection: Axis.horizontal,
                        itemCount: _related.length,
                        separatorBuilder: (_, _) => const SizedBox(width: 12),
                        itemBuilder: (context, index) {
                          return SizedBox(
                            width: 155,
                            child: ProductCard(product: _related[index]),
                          );
                        },
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 30),
            ],
            const SizedBox(height: 60),
          ],
        ),
      ),
      bottomNavigationBar: _buildBottomActionBar(p),
    );
  }

  Widget _buildBottomActionBar(ProductModel p) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      decoration: BoxDecoration(
        color: Colors.white,
        border: const Border(top: BorderSide(color: Color(0xFFE2E8F0), width: 1)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 10,
            offset: const Offset(0, -3),
          ),
        ],
      ),
      child: SafeArea(
        child: Row(
          children: [
            InkWell(
              onTap: () {
                Navigator.push(context, MaterialPageRoute(builder: (_) => const CartScreen()));
              },
              borderRadius: BorderRadius.circular(12),
              child: Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: const Color(0xFFF1F5F9),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(Icons.shopping_bag_outlined, color: AppColors.primary, size: 22),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: OutlinedButton(
                onPressed: currentStock > 0 && !isPurchaseLimitReached && !p.sellerIsOnVacation
                    ? () => _openVariantBottomSheet(buyNow: false)
                    : null,
                style: OutlinedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  backgroundColor: AppColors.surfaceAlt,
                  foregroundColor: const Color(0xFF334155),
                  side: const BorderSide(color: Color(0xFF334155), width: 1.0),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                ),
                child: Text(
                  _isAddingToCart ? 'Adding...' : 'Add to Cart',
                  style: GoogleFonts.figtree(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: const Color(0xFF334155),
                  ),
                ),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: ElevatedButton(
                onPressed: currentStock > 0 && !isPurchaseLimitReached && !p.sellerIsOnVacation
                    ? () => _openVariantBottomSheet(buyNow: true)
                    : null,
                style: ElevatedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  backgroundColor: AppColors.primary,
                  foregroundColor: Colors.white,
                  elevation: 0,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                ),
                child: Text(
                  isPurchaseLimitReached
                      ? 'Limit Reached'
                      : p.sellerIsOnVacation
                          ? 'On Vacation'
                          : currentStock <= 0
                              ? 'Out of Stock'
                              : 'Buy Now',
                  style: GoogleFonts.figtree(
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildTimerBadge(String value) {
    return Container(
      width: 22,
      height: 22,
      decoration: BoxDecoration(
        color: const Color(0xFFB45309),
        borderRadius: BorderRadius.circular(4),
      ),
      alignment: Alignment.center,
      child: Text(
        value,
        style: GoogleFonts.figtree(
          fontSize: 10,
          fontWeight: FontWeight.w900,
          color: Colors.white,
        ),
      ),
    );
  }

  Widget _buildTrustItem(IconData icon, String text) {
    return Column(
      children: [
        Container(
          width: 36,
          height: 36,
          decoration: const BoxDecoration(
            color: Color(0xFFE8F7F6),
            shape: BoxShape.circle,
          ),
          child: Icon(icon, size: 18, color: AppColors.primary),
        ),
        const SizedBox(height: 6),
        Text(
          text,
          style: GoogleFonts.figtree(
            fontSize: 10.5,
            fontWeight: FontWeight.w700,
            color: const Color(0xFF1F2937),
          ),
        ),
      ],
    );
  }
}
