import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/core/services/auth_state.dart';
import 'package:alona_mobile/features/buyer/models/buyer_models.dart';
import 'package:alona_mobile/features/buyer/services/buyer_service.dart';
import 'package:alona_mobile/features/buyer/presentation/cart/cart_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/messages/buyer_conversations_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/products/product_list_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/vouchers/vouchers_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/categories/buyer_categories_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/home/widgets/flash_sales_widget.dart';
import 'package:alona_mobile/features/buyer/presentation/home/widgets/hero_banner_carousel.dart';
import 'package:alona_mobile/features/buyer/presentation/home/widgets/product_card.dart';

class BuyerHomeScreen extends StatefulWidget {
  final AuthState authState;

  const BuyerHomeScreen({super.key, required this.authState});

  @override
  State<BuyerHomeScreen> createState() => _BuyerHomeScreenState();
}

class _BuyerHomeScreenState extends State<BuyerHomeScreen> {
  String? _error;
  List<ProductModel> _recommended = [];
  List<ProductModel> _trending = [];
  bool _isLoading = true;

  List<CategoryModel> _categories = [];
  List<ProductModel> _featured = [];
  List<ProductModel> _topSelling = [];
  List<FlashSaleModel> _flashSales = [];
  String? _flashSaleEndTime;
  List<VoucherModel> _promotions = [];
  List<HeroBannerModel> _heroBanners = [];
  HeroStatsModel? _heroStats;

  @override
  void initState() {
    super.initState();
    _loadHomeData();
  }

  Future<void> _loadHomeData() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final res = await BuyerService.getHome();
      if (!mounted) return;
      setState(() {
        _categories = res['categories'] as List<CategoryModel>;
        _featured = res['featured_products'] as List<ProductModel>;
        _topSelling = res['top_selling_products'] as List<ProductModel>;
        _recommended = res['recommended_products'] as List<ProductModel>;
        _trending = res['trending_products'] as List<ProductModel>? ?? [];
        _flashSales = res['flash_sales'] as List<FlashSaleModel>;
        _flashSaleEndTime = res['flash_sale_end_time'] as String?;
        _promotions = res['promotions'] as List<VoucherModel>;
        _heroBanners = res['hero_banners'] as List<HeroBannerModel>? ?? [];
        _heroStats = res['hero_stats'] as HeroStatsModel?;
        _isLoading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        titleSpacing: 8,
        elevation: 0,
        backgroundColor: Colors.white,
        automaticallyImplyLeading: false,
        title: Row(
          children: [
            // Left side: Cart and Message icons
            IconButton(
              icon: const Icon(Icons.shopping_bag_outlined, color: AppColors.textPrimary, size: 24),
              tooltip: 'Cart',
              onPressed: () {
                Navigator.push(context, MaterialPageRoute(builder: (_) => const CartScreen()));
              },
            ),
            IconButton(
              icon: const Icon(Icons.chat_bubble_outline_rounded, color: AppColors.textPrimary, size: 22),
              tooltip: 'Messages',
              onPressed: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const BuyerConversationsScreen()),
                );
              },
            ),
            const SizedBox(width: 4),

            // Top Search Bar (matching Web search bar)
            Expanded(
              child: GestureDetector(
                onTap: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => const ProductListScreen()),
                  );
                },
                child: Container(
                  height: 40,
                  padding: const EdgeInsets.symmetric(horizontal: 14),
                  decoration: BoxDecoration(
                    color: AppColors.background,
                    borderRadius: BorderRadius.circular(24),
                    border: Border.all(color: AppColors.border, width: 1),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.search_rounded, color: AppColors.mutedText, size: 19),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          'Search products, brands...',
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: GoogleFonts.figtree(
                            color: AppColors.textLight,
                            fontSize: 13,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
            const SizedBox(width: 4),
          ],
        ),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
          : _error != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Icon(Icons.error_outline, size: 48, color: AppColors.error),
                        const SizedBox(height: 16),
                        Text(
                          _error!,
                          textAlign: TextAlign.center,
                          style: GoogleFonts.figtree(fontSize: 14, color: AppColors.textSecondary),
                        ),
                        const SizedBox(height: 16),
                        ElevatedButton(
                          onPressed: _loadHomeData,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.primary,
                            foregroundColor: Colors.white,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                            elevation: 0,
                          ),
                          child: const Text('Retry'),
                        ),
                      ],
                    ),
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _loadHomeData,
                  color: AppColors.primary,
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 96),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Dynamic Hero Banners Carousel (synced with Admin Settings & web)
                        HeroBannerCarousel(
                          banners: _heroBanners.isNotEmpty
                              ? _heroBanners
                              : const [
                                  HeroBannerModel(
                                    id: 'banner_default',
                                    title: 'Welcome to',
                                    titleHighlight: 'Alona.',
                                    description: 'Everything you love, all in one place. Discover quality products, trusted sellers, and great deals made for you.',
                                    primaryButtonText: 'Shop Now',
                                    secondaryButtonText: 'Explore Categories',
                                  ),
                                ],
                          stats: _heroStats,
                          onShopNow: () {
                            Navigator.push(
                              context,
                              MaterialPageRoute(builder: (_) => const ProductListScreen()),
                            );
                          },
                          onExploreCategories: () {
                            Navigator.push(
                              context,
                              MaterialPageRoute(builder: (_) => const BuyerCategoriesScreen()),
                            );
                          },
                        ),

                        const SizedBox(height: 24),

                        // FLASH SALES SECTION (With Live Countdown)
                        FlashSalesWidget(
                          flashSales: _flashSales,
                          endTime: _flashSaleEndTime,
                          onBrowseProducts: () {
                            Navigator.push(
                              context,
                              MaterialPageRoute(builder: (_) => const ProductListScreen()),
                            );
                          },
                        ),

                        const SizedBox(height: 28),

                        // CATEGORIES SECTION MATCHING WEBSITE DASHBOARD.VUE
                        if (_categories.isNotEmpty) ...[
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Row(
                                children: [
                                  Container(
                                    width: 8,
                                    height: 8,
                                    decoration: const BoxDecoration(
                                      color: Color(0xFFF5A711),
                                      shape: BoxShape.circle,
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  Text(
                                    'Categories',
                                    style: GoogleFonts.figtree(
                                      fontSize: 18,
                                      fontWeight: FontWeight.w800,
                                      color: AppColors.textPrimary,
                                    ),
                                  ),
                                ],
                              ),
                              TextButton(
                                onPressed: () {
                                  Navigator.push(
                                    context,
                                    MaterialPageRoute(
                                      builder: (_) => BuyerCategoriesScreen(initialCategories: _categories),
                                    ),
                                  );
                                },
                                child: Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    Text(
                                      'View all',
                                      style: GoogleFonts.figtree(
                                        fontWeight: FontWeight.w700,
                                        color: AppColors.primary,
                                        fontSize: 13,
                                      ),
                                    ),
                                    const SizedBox(width: 4),
                                    const Icon(Icons.arrow_forward_rounded, size: 14, color: AppColors.primary),
                                  ],
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 12),
                          SizedBox(
                            height: 164,
                            child: ListView.separated(
                              scrollDirection: Axis.horizontal,
                              itemCount: _categories.length,
                              separatorBuilder: (_, _) => const SizedBox(width: 12),
                              itemBuilder: (context, index) {
                                final cat = _categories[index];
                                return GestureDetector(
                                  onTap: () {
                                    Navigator.push(
                                      context,
                                      MaterialPageRoute(
                                        builder: (_) => ProductListScreen(
                                          categoryId: cat.id,
                                          categoryName: cat.name,
                                        ),
                                      ),
                                    );
                                  },
                                  child: Container(
                                    width: 138,
                                    decoration: BoxDecoration(
                                      borderRadius: BorderRadius.circular(20),
                                      boxShadow: const [
                                        BoxShadow(
                                          color: Color(0x14000000),
                                          blurRadius: 8,
                                          offset: Offset(0, 3),
                                        ),
                                      ],
                                    ),
                                    child: ClipRRect(
                                      borderRadius: BorderRadius.circular(20),
                                      child: Stack(
                                        fit: StackFit.expand,
                                        children: [
                                          // Category Picture from Database / Unsplash
                                          Image.network(
                                            cat.effectiveImageUrl,
                                            fit: BoxFit.cover,
                                            errorBuilder: (_, _, _) => Container(
                                              color: const Color(0xFFE8F7F6),
                                              child: const Center(
                                                child: Icon(Icons.category_outlined, color: Color(0xFF087F8C), size: 32),
                                              ),
                                            ),
                                            loadingBuilder: (context, child, progress) {
                                              if (progress == null) return child;
                                              return Container(
                                                color: const Color(0xFFE8F7F6),
                                                child: const Center(
                                                  child: SizedBox(
                                                    width: 24,
                                                    height: 24,
                                                    child: CircularProgressIndicator(
                                                      color: Color(0xFF087F8C),
                                                      strokeWidth: 2,
                                                    ),
                                                  ),
                                                ),
                                              );
                                            },
                                          ),

                                          // Gradient overlay matching website (black/85 to transparent)
                                          Container(
                                            decoration: const BoxDecoration(
                                              gradient: LinearGradient(
                                                colors: [
                                                  Colors.transparent,
                                                  Color(0x33000000),
                                                  Color(0xDF000000),
                                                ],
                                                begin: Alignment.topCenter,
                                                end: Alignment.bottomCenter,
                                                stops: [0.0, 0.45, 1.0],
                                              ),
                                            ),
                                          ),

                                          // Top right circular white arrow badge
                                          Positioned(
                                            top: 10,
                                            right: 10,
                                            child: Container(
                                              width: 26,
                                              height: 26,
                                              decoration: const BoxDecoration(
                                                color: Color(0xE6FFFFFF),
                                                shape: BoxShape.circle,
                                                boxShadow: [
                                                  BoxShadow(
                                                    color: Color(0x1F000000),
                                                    blurRadius: 4,
                                                  ),
                                                ],
                                              ),
                                              child: const Center(
                                                child: Icon(
                                                  Icons.arrow_forward_rounded,
                                                  size: 14,
                                                  color: Color(0xFF087F8C),
                                                ),
                                              ),
                                            ),
                                          ),

                                          // Bottom Title and Item Count
                                          Positioned(
                                            left: 12,
                                            right: 12,
                                            bottom: 12,
                                            child: Column(
                                              crossAxisAlignment: CrossAxisAlignment.start,
                                              mainAxisSize: MainAxisSize.min,
                                              children: [
                                                Text(
                                                  cat.name,
                                                  maxLines: 2,
                                                  overflow: TextOverflow.ellipsis,
                                                  style: GoogleFonts.figtree(
                                                    fontSize: 13,
                                                    fontWeight: FontWeight.w800,
                                                    color: Colors.white,
                                                    height: 1.25,
                                                    shadows: const [
                                                      Shadow(color: Color(0xCC000000), blurRadius: 4, offset: Offset(0, 1)),
                                                    ],
                                                  ),
                                                ),
                                                const SizedBox(height: 3),
                                                Text(
                                                  cat.productsCount > 0
                                                      ? '${cat.productsCount} ${cat.productsCount == 1 ? 'item' : 'items'}'
                                                      : 'Explore products',
                                                  style: GoogleFonts.figtree(
                                                    fontSize: 10.5,
                                                    fontWeight: FontWeight.w600,
                                                    color: const Color(0xD9FFFFFF),
                                                  ),
                                                ),
                                              ],
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ),
                                );
                              },
                            ),
                          ),
                          if (_categories.length > 3) ...[
                            const SizedBox(height: 10),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Container(
                                  width: 4,
                                  height: 4,
                                  decoration: const BoxDecoration(
                                    color: Color(0xFFF5A711),
                                    shape: BoxShape.circle,
                                  ),
                                ),
                                const SizedBox(width: 6),
                                Text(
                                  'SWIPE TO EXPLORE ALL ${_categories.length} CATEGORIES',
                                  style: GoogleFonts.figtree(
                                    fontSize: 9.5,
                                    fontWeight: FontWeight.w800,
                                    letterSpacing: 1.2,
                                    color: const Color(0xFF64748B),
                                  ),
                                ),
                                const SizedBox(width: 6),
                                Container(
                                  width: 4,
                                  height: 4,
                                  decoration: const BoxDecoration(
                                    color: Color(0xFFF5A711),
                                    shape: BoxShape.circle,
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ],

                        const SizedBox(height: 28),

                        // ========================================================
                        // TRENDING NOW / PEOPLE ARE LOOKING (Matches Dashboard.vue)
                        // ========================================================
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    Container(
                                      width: 8,
                                      height: 8,
                                      decoration: const BoxDecoration(
                                        color: Color(0xFFF5A711),
                                        shape: BoxShape.circle,
                                      ),
                                    ),
                                    const SizedBox(width: 8),
                                    Text(
                                      'TRENDING NOW',
                                      style: GoogleFonts.figtree(
                                        fontSize: 10.5,
                                        fontWeight: FontWeight.w800,
                                        letterSpacing: 1.2,
                                        color: const Color(0xFF087F8C),
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  'People are looking',
                                  style: GoogleFonts.figtree(
                                    fontSize: 20,
                                    fontWeight: FontWeight.w900,
                                    color: const Color(0xFF1F2937),
                                  ),
                                ),
                              ],
                            ),
                            TextButton(
                              onPressed: () {
                                Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) => const ProductListScreen(),
                                  ),
                                );
                              },
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Text(
                                    'See everything',
                                    style: GoogleFonts.figtree(
                                      fontWeight: FontWeight.w700,
                                      color: const Color(0xFF087F8C),
                                      fontSize: 12.5,
                                    ),
                                  ),
                                  const SizedBox(width: 4),
                                  const Icon(Icons.arrow_forward_rounded, size: 14, color: Color(0xFF087F8C)),
                                ],
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),

                        if (_trending.isNotEmpty) ...[
                          GridView.builder(
                            physics: const NeverScrollableScrollPhysics(),
                            shrinkWrap: true,
                            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                              crossAxisCount: 2,
                              crossAxisSpacing: 12,
                              mainAxisSpacing: 12,
                              childAspectRatio: 0.65,
                            ),
                            itemCount: _trending.take(6).length,
                            itemBuilder: (context, index) {
                              return ProductCard(product: _trending[index]);
                            },
                          ),
                        ] else ...[
                          Container(
                            width: double.infinity,
                            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 36),
                            decoration: BoxDecoration(
                              color: const Color(0xFFF8FAF9),
                              borderRadius: BorderRadius.circular(24),
                              border: Border.all(
                                color: const Color(0xFFE5E7EB),
                                style: BorderStyle.solid,
                              ),
                            ),
                            child: Column(
                              children: [
                                Container(
                                  width: 52,
                                  height: 52,
                                  decoration: const BoxDecoration(
                                    color: Color(0xFFE8F7F6),
                                    shape: BoxShape.circle,
                                  ),
                                  child: const Icon(
                                    Icons.shopping_bag_outlined,
                                    size: 28,
                                    color: Color(0xFF087F8C),
                                  ),
                                ),
                                const SizedBox(height: 12),
                                Text(
                                  'Products are coming soon',
                                  style: GoogleFonts.figtree(
                                    fontSize: 14,
                                    fontWeight: FontWeight.w800,
                                    color: const Color(0xFF1F2937),
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  'Check back soon for new products from Alona sellers.',
                                  textAlign: TextAlign.center,
                                  style: GoogleFonts.figtree(
                                    fontSize: 12,
                                    color: const Color(0xFF64748B),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],

                        const SizedBox(height: 32),

                        // ========================================================
                        // DEALS & VOUCHERS / ACTIVE PROMOTIONS (Matches Dashboard.vue)
                        // ========================================================
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      Container(
                                        width: 8,
                                        height: 8,
                                        decoration: const BoxDecoration(
                                          color: Color(0xFFF5A711),
                                          shape: BoxShape.circle,
                                        ),
                                      ),
                                      const SizedBox(width: 8),
                                      Text(
                                        'DEALS & VOUCHERS',
                                        style: GoogleFonts.figtree(
                                          fontSize: 10.5,
                                          fontWeight: FontWeight.w800,
                                          letterSpacing: 1.2,
                                          color: const Color(0xFF087F8C),
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 4),
                                  Text(
                                    'Active Promotions',
                                    style: GoogleFonts.figtree(
                                      fontSize: 20,
                                      fontWeight: FontWeight.w900,
                                      color: const Color(0xFF1F2937),
                                    ),
                                  ),
                                  const SizedBox(height: 3),
                                  Text(
                                    'Claim vouchers to your account and apply extra discounts at checkout.',
                                    style: GoogleFonts.figtree(
                                      fontSize: 11.5,
                                      color: const Color(0xFF64748B),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            TextButton(
                              onPressed: () {
                                Navigator.push(
                                  context,
                                  MaterialPageRoute(builder: (_) => const VouchersScreen()),
                                );
                              },
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Text(
                                    'View all vouchers',
                                    style: GoogleFonts.figtree(
                                      fontWeight: FontWeight.w700,
                                      color: const Color(0xFF087F8C),
                                      fontSize: 12,
                                    ),
                                  ),
                                  const SizedBox(width: 4),
                                  const Icon(Icons.arrow_forward_rounded, size: 13, color: Color(0xFF087F8C)),
                                ],
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 14),

                        if (_promotions.isNotEmpty) ...[
                          SizedBox(
                            height: 136,
                            child: ListView.separated(
                              scrollDirection: Axis.horizontal,
                              itemCount: _promotions.length,
                              separatorBuilder: (_, _) => const SizedBox(width: 12),
                              itemBuilder: (context, index) {
                                final v = _promotions[index];
                                return Container(
                                  width: 240,
                                  padding: const EdgeInsets.all(14),
                                  decoration: BoxDecoration(
                                    color: Colors.white,
                                    borderRadius: BorderRadius.circular(18),
                                    border: Border.all(color: const Color(0xFFE5E7EB)),
                                    boxShadow: const [
                                      BoxShadow(
                                        color: Color(0x08000000),
                                        blurRadius: 8,
                                        offset: Offset(0, 3),
                                      ),
                                    ],
                                  ),
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                    children: [
                                      Row(
                                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                        children: [
                                          Container(
                                            padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                                            decoration: BoxDecoration(
                                              color: const Color(0xFFE8F7F6),
                                              borderRadius: BorderRadius.circular(6),
                                            ),
                                            child: Text(
                                              v.isPlatform ? 'PLATFORM VOUCHER' : 'STORE VOUCHER',
                                              style: GoogleFonts.figtree(
                                                fontSize: 9,
                                                fontWeight: FontWeight.w800,
                                                color: const Color(0xFF087F8C),
                                                letterSpacing: 0.5,
                                              ),
                                            ),
                                          ),
                                          if (v.daysRemaining != null && v.daysRemaining! <= 3)
                                            Container(
                                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                              decoration: BoxDecoration(
                                                color: const Color(0xFFFEE2E2),
                                                borderRadius: BorderRadius.circular(4),
                                              ),
                                              child: Text(
                                                'Expiring Soon',
                                                style: GoogleFonts.figtree(
                                                  fontSize: 9,
                                                  fontWeight: FontWeight.w800,
                                                  color: const Color(0xFFDC2626),
                                                ),
                                              ),
                                            ),
                                        ],
                                      ),
                                      Row(
                                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                        crossAxisAlignment: CrossAxisAlignment.center,
                                        children: [
                                          Text(
                                            v.discountDescription,
                                            style: GoogleFonts.figtree(
                                              fontSize: 18,
                                              fontWeight: FontWeight.w900,
                                              color: const Color(0xFF087F8C),
                                            ),
                                          ),
                                          Text(
                                            'Min. ₱${v.minSpend.toInt()}',
                                            style: GoogleFonts.figtree(
                                              fontSize: 11,
                                              color: const Color(0xFF64748B),
                                              fontWeight: FontWeight.w600,
                                            ),
                                          ),
                                        ],
                                      ),
                                      Row(
                                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                        children: [
                                          Container(
                                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                            decoration: BoxDecoration(
                                              color: const Color(0xFFF8FAF9),
                                              borderRadius: BorderRadius.circular(6),
                                              border: Border.all(color: const Color(0xFFCBD5E1)),
                                            ),
                                            child: Text(
                                              v.code,
                                              style: GoogleFonts.courierPrime(
                                                fontSize: 11,
                                                fontWeight: FontWeight.w700,
                                                color: const Color(0xFF1F2937),
                                              ),
                                            ),
                                          ),
                                          ElevatedButton(
                                            onPressed: () {
                                              Navigator.push(
                                                context,
                                                MaterialPageRoute(builder: (_) => const VouchersScreen()),
                                              );
                                            },
                                            style: ElevatedButton.styleFrom(
                                              backgroundColor: const Color(0xFF087F8C),
                                              foregroundColor: Colors.white,
                                              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                                              minimumSize: Size.zero,
                                              tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(6)),
                                            ),
                                            child: Text(
                                              'Claim',
                                              style: GoogleFonts.figtree(fontSize: 11, fontWeight: FontWeight.w800),
                                            ),
                                          ),
                                        ],
                                      ),
                                    ],
                                  ),
                                );
                              },
                            ),
                          ),
                        ] else ...[
                          Container(
                            width: double.infinity,
                            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 36),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(24),
                              border: Border.all(
                                color: const Color(0xFFE5E7EB),
                                style: BorderStyle.solid,
                              ),
                            ),
                            child: Column(
                              children: [
                                Container(
                                  width: 52,
                                  height: 52,
                                  decoration: const BoxDecoration(
                                    color: Color(0xFFFFF6DF),
                                    shape: BoxShape.circle,
                                  ),
                                  child: const Icon(
                                    Icons.confirmation_number_outlined,
                                    size: 28,
                                    color: Color(0xFFF59E0B),
                                  ),
                                ),
                                const SizedBox(height: 12),
                                Text(
                                  'No active promotions right now',
                                  style: GoogleFonts.figtree(
                                    fontSize: 14,
                                    fontWeight: FontWeight.w800,
                                    color: const Color(0xFF1F2937),
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  'Check back soon for vouchers and special deals from Alona sellers.',
                                  textAlign: TextAlign.center,
                                  style: GoogleFonts.figtree(
                                    fontSize: 12,
                                    color: const Color(0xFF64748B),
                                  ),
                                ),
                                const SizedBox(height: 14),
                                ElevatedButton(
                                  onPressed: () {
                                    Navigator.push(
                                      context,
                                      MaterialPageRoute(builder: (_) => const ProductListScreen()),
                                    );
                                  },
                                  style: ElevatedButton.styleFrom(
                                    backgroundColor: AppColors.primary,
                                    foregroundColor: Colors.white,
                                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                                    elevation: 0,
                                  ),
                                  child: Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      Text(
                                        'Browse products',
                                        style: GoogleFonts.figtree(fontSize: 12, fontWeight: FontWeight.w800),
                                      ),
                                      const SizedBox(width: 6),
                                      const Icon(Icons.arrow_forward_rounded, size: 14),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],

                        // TOP SELLING PRODUCTS
                        if (_topSelling.isNotEmpty) ...[
                          const SizedBox(height: 28),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Row(
                                children: [
                                  Text(
                                    'Top Selling',
                                    style: GoogleFonts.figtree(
                                      fontSize: 18,
                                      fontWeight: FontWeight.w800,
                                      color: AppColors.textPrimary,
                                    ),
                                  ),
                                  const SizedBox(width: 6),
                                  const Text('🔥', style: TextStyle(fontSize: 16)),
                                ],
                              ),
                              TextButton(
                                onPressed: () {
                                  Navigator.push(
                                    context,
                                    MaterialPageRoute(
                                      builder: (_) => const ProductListScreen(initialSearch: ''),
                                    ),
                                  );
                                },
                                child: Text(
                                  'See all',
                                  style: GoogleFonts.figtree(fontWeight: FontWeight.w700, color: AppColors.primary),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 12),
                          GridView.builder(
                            physics: const NeverScrollableScrollPhysics(),
                            shrinkWrap: true,
                            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                              crossAxisCount: 2,
                              crossAxisSpacing: 12,
                              mainAxisSpacing: 12,
                              childAspectRatio: 0.65,
                            ),
                            itemCount: _topSelling.take(4).length,
                            itemBuilder: (context, index) {
                              return ProductCard(product: _topSelling[index]);
                            },
                          ),
                        ],

                        // FEATURED PRODUCTS
                        if (_featured.isNotEmpty) ...[
                          const SizedBox(height: 28),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text(
                                'Featured Products',
                                style: GoogleFonts.figtree(
                                  fontSize: 18,
                                  fontWeight: FontWeight.w800,
                                  color: AppColors.textPrimary,
                                ),
                              ),
                              TextButton(
                                onPressed: () {
                                  Navigator.push(
                                    context,
                                    MaterialPageRoute(
                                      builder: (_) => const ProductListScreen(),
                                    ),
                                  );
                                },
                                child: Text(
                                  'See all',
                                  style: GoogleFonts.figtree(fontWeight: FontWeight.w700, color: AppColors.primary),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 12),
                          GridView.builder(
                            physics: const NeverScrollableScrollPhysics(),
                            shrinkWrap: true,
                            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                              crossAxisCount: 2,
                              crossAxisSpacing: 12,
                              mainAxisSpacing: 12,
                              childAspectRatio: 0.65,
                            ),
                            itemCount: _featured.take(4).length,
                            itemBuilder: (context, index) {
                              return ProductCard(product: _featured[index]);
                            },
                          ),
                        ],

                        // RECOMMENDED PRODUCTS
                        if (_recommended.isNotEmpty) ...[
                          const SizedBox(height: 28),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text(
                                'Recommended For You',
                                style: GoogleFonts.figtree(
                                  fontSize: 18,
                                  fontWeight: FontWeight.w800,
                                  color: AppColors.textPrimary,
                                ),
                              ),
                              TextButton(
                                onPressed: () {
                                  Navigator.push(
                                    context,
                                    MaterialPageRoute(
                                      builder: (_) => const ProductListScreen(),
                                    ),
                                  );
                                },
                                child: Text(
                                  'See all',
                                  style: GoogleFonts.figtree(fontWeight: FontWeight.w700, color: AppColors.primary),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 12),
                          GridView.builder(
                            physics: const NeverScrollableScrollPhysics(),
                            shrinkWrap: true,
                            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                              crossAxisCount: 2,
                              crossAxisSpacing: 12,
                              mainAxisSpacing: 12,
                              childAspectRatio: 0.65,
                            ),
                            itemCount: _recommended.take(6).length,
                            itemBuilder: (context, index) {
                              return ProductCard(product: _recommended[index]);
                            },
                          ),
                        ],

                        const SizedBox(height: 40),
                      ],
                    ),
                  ),
                ),
    );
  }
}

