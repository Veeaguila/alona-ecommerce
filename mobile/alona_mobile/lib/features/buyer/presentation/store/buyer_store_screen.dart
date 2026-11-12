import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/core/network/api_client.dart';
import 'package:alona_mobile/core/services/auth_state.dart';
import 'package:alona_mobile/shared/widgets/network_image_box.dart';
import 'package:alona_mobile/shared/widgets/empty_state_view.dart';
import 'package:alona_mobile/features/buyer/models/buyer_models.dart';
import 'package:alona_mobile/features/buyer/services/buyer_service.dart';
import 'package:alona_mobile/features/buyer/presentation/auth/buyer_login_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/cart/cart_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/home/widgets/product_card.dart';
import 'package:alona_mobile/features/buyer/presentation/messages/buyer_chat_detail_screen.dart';

class BuyerStoreScreen extends StatefulWidget {
  final int sellerId;
  final String? storeName;

  const BuyerStoreScreen({
    super.key,
    required this.sellerId,
    this.storeName,
  });

  @override
  State<BuyerStoreScreen> createState() => _BuyerStoreScreenState();
}

class _BuyerStoreScreenState extends State<BuyerStoreScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  final TextEditingController _searchController = TextEditingController();

  bool _isLoading = true;
  String? _error;
  bool _isFollowing = false;
  bool _isTogglingFollow = false;

  Map<String, dynamic>? _seller;
  List<ProductModel> _products = [];
  List<ProductModel> _recommendedProducts = [];
  List<ProductReviewModel> _reviews = [];
  List<dynamic> _categories = [];
  List<dynamic> _vouchers = [];
  List<dynamic> _bundleDeals = [];
  Map<String, dynamic> _stats = {};

  String _currentSort = 'popular';
  String? _selectedCategory;
  String? _selectedSubcategory;
  String _searchQuery = '';

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 4, vsync: this, initialIndex: 1); // Shop, Products, Reviews, Categories
    _loadStoreData();
  }

  @override
  void dispose() {
    _tabController.dispose();
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _loadStoreData() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final res = await BuyerService.getStoreDetails(
        widget.sellerId,
        sort: _currentSort,
        search: _searchQuery,
        category: _selectedSubcategory ?? _selectedCategory,
      );

      final Map<String, dynamic> sellerData =
          res['seller'] is Map ? Map<String, dynamic>.from(res['seller'] as Map) : <String, dynamic>{};
      final Map<String, dynamic> statsData =
          res['stats'] is Map ? Map<String, dynamic>.from(res['stats'] as Map) : <String, dynamic>{};

      // Parse products
      List<ProductModel> productList = [];
      if (res['products'] is Map && res['products']['data'] is List) {
        productList = (res['products']['data'] as List)
            .map((item) => ProductModel.fromJson(Map<String, dynamic>.from(item)))
            .toList();
      } else if (res['products'] is List) {
        productList = (res['products'] as List)
            .map((item) => ProductModel.fromJson(Map<String, dynamic>.from(item)))
            .toList();
      }

      // Parse recommended products
      List<ProductModel> recList = [];
      if (res['recommended_products'] is List) {
        recList = (res['recommended_products'] as List)
            .map((item) => ProductModel.fromJson(Map<String, dynamic>.from(item)))
            .toList();
      }

      // Parse real reviews from backend
      List<ProductReviewModel> reviewList = [];
      if (res['reviews'] is List) {
        reviewList = (res['reviews'] as List)
            .map((item) => ProductReviewModel.fromJson(Map<String, dynamic>.from(item)))
            .toList();
      }

      if (!mounted) return;
      setState(() {
        _seller = sellerData;
        _stats = statsData;
        _products = productList;
        _recommendedProducts = recList;
        _reviews = reviewList;
        _categories = (res['categories'] as List?) ?? [];
        _vouchers = (res['vouchers'] as List?) ?? [];
        _bundleDeals = (res['bundle_deals'] as List?) ?? [];
        _isFollowing = res['is_following'] == true;
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

  Future<void> _toggleFollow() async {
    if (!ApiClient.isAuthenticated) {
      final nav = Navigator.of(context);
      final auth = AuthState();
      await auth.init();
      if (!mounted) return;
      final loggedIn = await nav.push<bool>(
        MaterialPageRoute(builder: (_) => BuyerLoginScreen(authState: auth)),
      );
      if (loggedIn != true || !mounted) return;
    }

    setState(() => _isTogglingFollow = true);
    try {
      final res = await BuyerService.toggleFollowStore(widget.sellerId);
      final bool following = res['is_following'] == true;
      if (!mounted) return;
      setState(() {
        _isFollowing = following;
        _isTogglingFollow = false;
        if (res['followers_count'] != null) {
          _stats['followers_count'] = res['followers_count'];
        } else {
          final currentFollowers = (_stats['followers_count'] as num?)?.toInt() ?? 0;
          _stats['followers_count'] = following ? currentFollowers + 1 : (currentFollowers > 0 ? currentFollowers - 1 : 0);
        }
      });
    } catch (e) {
      if (!mounted) return;
      setState(() => _isTogglingFollow = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed to update follow: $e'), backgroundColor: AppColors.sale),
      );
    }
  }

  void _openChatWithSeller() async {
    if (!ApiClient.isAuthenticated) {
      final nav = Navigator.of(context);
      final auth = AuthState();
      await auth.init();
      if (!mounted) return;
      final loggedIn = await nav.push<bool>(
        MaterialPageRoute(builder: (_) => BuyerLoginScreen(authState: auth)),
      );
      if (loggedIn != true || !mounted) return;
    }

    try {
      final convId = await BuyerService.startConversationWithSeller(widget.sellerId);
      if (!mounted) return;
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => BuyerChatDetailScreen(
            conversationId: convId,
            title: _storeName,
            sellerId: widget.sellerId,
            sellerAvatarUrl: _storeLogo,
            sellerBadge: _badgeLabel,
          ),
        ),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Could not start chat: $e'), backgroundColor: AppColors.sale),
      );
    }
  }

  String get _storeName =>
      _seller?['store_name']?.toString() ??
      _seller?['name']?.toString() ??
      widget.storeName ??
      'Store';

  String? get _storeLogo => _seller?['store_logo_url']?.toString();
  String? get _storeBanner => _seller?['store_banner_url']?.toString();
  String? get _badgeLabel => _seller?['badge_label']?.toString();
  bool get _isMall => _seller?['is_mall'] == true;

  String _formatFollowerCount(int count) {
    if (count >= 1000000) {
      return '${(count / 1000000).toStringAsFixed(1)}M Followers';
    } else if (count >= 1000) {
      return '${(count / 1000).toStringAsFixed(1)}k Followers';
    }
    return '$count Followers';
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.buyerBackground,
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppColors.alonaTeal))
          : _error != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(_error!, textAlign: TextAlign.center, style: const TextStyle(color: AppColors.sale)),
                        const SizedBox(height: 12),
                        ElevatedButton(
                          onPressed: _loadStoreData,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.primary,
                            foregroundColor: AppColors.white,
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                            elevation: 0,
                          ),
                          child: const Text('Retry'),
                        ),
                      ],
                    ),
                  ),
                )
              : NestedScrollView(
                  headerSliverBuilder: (context, innerBoxIsScrolled) {
                    return [
                      // Header Section matching brand palette and clean UI
                      SliverToBoxAdapter(
                        child: _buildStoreHeader(),
                      ),

                      // Tabs Bar styled identically to the rest of the app
                      SliverPersistentHeader(
                        pinned: true,
                        delegate: _SliverAppBarDelegate(
                          Container(
                            decoration: const BoxDecoration(
                              color: AppColors.white,
                              border: Border(bottom: BorderSide(color: AppColors.border, width: 0.8)),
                            ),
                            child: TabBar(
                              controller: _tabController,
                              indicatorColor: AppColors.alonaTeal,
                              indicatorWeight: 3,
                              labelColor: AppColors.alonaTeal,
                              unselectedLabelColor: AppColors.mutedText,
                              labelStyle: GoogleFonts.figtree(fontWeight: FontWeight.w700, fontSize: 13.5),
                              unselectedLabelStyle: GoogleFonts.figtree(fontWeight: FontWeight.w500, fontSize: 13.5),
                              tabs: [
                                const Tab(text: 'Shop'),
                                Tab(
                                  child: Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      const Text('Products'),
                                      const SizedBox(width: 4),
                                      Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 0.5),
                                        decoration: BoxDecoration(
                                          color: AppColors.sale,
                                          borderRadius: BorderRadius.circular(4),
                                        ),
                                        child: Text(
                                          'New',
                                          style: GoogleFonts.figtree(
                                            fontSize: 8.5,
                                            fontWeight: FontWeight.w800,
                                            color: Colors.white,
                                            height: 1.1,
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                const Tab(text: 'Reviews'),
                                const Tab(text: 'Categories'),
                              ],
                            ),
                          ),
                        ),
                      ),
                    ];
                  },
                  body: TabBarView(
                    controller: _tabController,
                    children: [
                      _buildShopTab(),
                      _buildProductsTab(),
                      _buildReviewsTab(),
                      _buildCategoriesTab(),
                    ],
                  ),
                ),
    );
  }

  Widget _buildStoreHeader() {
    final double rating = (_stats['avg_rating'] as num?)?.toDouble() ?? 0.0;
    final int followers = (_stats['followers_count'] as num?)?.toInt() ?? 0;
    final String? tagline = _seller?['store_tagline']?.toString();
    final bool isVerified = _seller?['has_business_permit'] == true ||
        _seller?['status'] == 'approved' ||
        _isMall;

    return Stack(
      children: [
        // Background banner matching web gradient or custom banner
        Container(
          height: tagline != null && tagline.isNotEmpty ? 220 : 200,
          width: double.infinity,
          decoration: BoxDecoration(
            color: const Color(0xFF065A63),
            image: _storeBanner != null && _storeBanner!.isNotEmpty
                ? DecorationImage(
                    image: NetworkImage(_storeBanner!),
                    fit: BoxFit.cover,
                  )
                : null,
          ),
          child: Container(
            decoration: BoxDecoration(
              gradient: _storeBanner != null && _storeBanner!.isNotEmpty
                  ? LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: [
                        Colors.black.withValues(alpha: 0.65),
                        Colors.black.withValues(alpha: 0.75),
                      ],
                    )
                  : const LinearGradient(
                      begin: Alignment.centerLeft,
                      end: Alignment.centerRight,
                      colors: [
                        Color(0xFF087F8C),
                        Color(0xFF0A96A6),
                        Color(0xFF065A63),
                      ],
                    ),
            ),
          ),
        ),

        // Header content
        SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Top App Bar row: Back + Search In Shop + Overflow Menu
                Row(
                  children: [
                    IconButton(
                      icon: const Icon(Icons.arrow_back, color: AppColors.white),
                      onPressed: () => Navigator.pop(context),
                      padding: EdgeInsets.zero,
                      constraints: const BoxConstraints(),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Container(
                        height: 38,
                        decoration: BoxDecoration(
                          color: AppColors.white,
                          borderRadius: BorderRadius.circular(24),
                          border: Border.all(color: AppColors.buyerBorder, width: 1),
                        ),
                        child: TextField(
                          controller: _searchController,
                          cursorColor: AppColors.primary,
                          style: GoogleFonts.figtree(color: AppColors.textPrimary, fontSize: 13),
                          decoration: InputDecoration(
                            filled: false,
                            hintText: 'Search in shop',
                            hintStyle: GoogleFonts.figtree(
                              color: AppColors.textMuted,
                              fontSize: 13,
                            ),
                            prefixIcon: const Icon(Icons.search_rounded, color: AppColors.primary, size: 19),
                            suffixIcon: _searchQuery.isNotEmpty
                                ? IconButton(
                                    icon: const Icon(Icons.clear, color: AppColors.textMuted, size: 16),
                                    onPressed: () {
                                      _searchController.clear();
                                      setState(() => _searchQuery = '');
                                      _loadStoreData();
                                    },
                                  )
                                : null,
                            border: InputBorder.none,
                            enabledBorder: InputBorder.none,
                            focusedBorder: InputBorder.none,
                            contentPadding: const EdgeInsets.symmetric(vertical: 8),
                            isDense: true,
                          ),
                          onSubmitted: (val) {
                            setState(() {
                              _searchQuery = val.trim();
                              _tabController.index = 1;
                            });
                            _loadStoreData();
                          },
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    IconButton(
                      icon: const Icon(Icons.chat_bubble_outline_rounded, color: AppColors.white, size: 20),
                      tooltip: 'Message Seller',
                      onPressed: _openChatWithSeller,
                      padding: const EdgeInsets.all(6),
                      constraints: const BoxConstraints(),
                    ),
                    IconButton(
                      icon: const Icon(Icons.shopping_cart_outlined, color: AppColors.white, size: 22),
                      tooltip: 'Cart',
                      onPressed: () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(builder: (_) => const CartScreen()),
                        );
                      },
                      padding: const EdgeInsets.all(6),
                      constraints: const BoxConstraints(),
                    ),
                    IconButton(
                      icon: const Icon(Icons.more_vert, color: AppColors.white),
                      onPressed: _showStoreInfoSheet,
                      padding: const EdgeInsets.all(4),
                      constraints: const BoxConstraints(),
                    ),
                  ],
                ),

                const SizedBox(height: 12),

                // Store info row: Avatar + Name/Stats + Follow/Chat Buttons
                Row(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    // Avatar (rounded-2xl matching web)
                    SizedBox(
                      width: 58,
                      height: 58,
                      child: Stack(
                        clipBehavior: Clip.none,
                        alignment: Alignment.center,
                        children: [
                          Container(
                            width: 56,
                            height: 56,
                            decoration: BoxDecoration(
                              color: AppColors.white,
                              borderRadius: BorderRadius.circular(16),
                              border: Border.all(color: AppColors.white, width: 2),
                              boxShadow: [
                                BoxShadow(
                                  color: Colors.black.withValues(alpha: 0.12),
                                  blurRadius: 6,
                                  offset: const Offset(0, 2),
                                ),
                              ],
                            ),
                            child: ClipRRect(
                              borderRadius: BorderRadius.circular(14),
                              child: _storeLogo != null && _storeLogo!.isNotEmpty
                                  ? NetworkImageBox(imageUrl: _storeLogo!, width: 56, height: 56, fit: BoxFit.cover)
                                  : Center(
                                      child: Text(
                                        _storeName.isNotEmpty ? _storeName.substring(0, _storeName.length >= 2 ? 2 : 1).toUpperCase() : 'ST',
                                        style: GoogleFonts.figtree(
                                          fontSize: 18,
                                          fontWeight: FontWeight.w800,
                                          color: AppColors.alonaTeal,
                                        ),
                                      ),
                                    ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 12),

                    // Name + Verified Badge + Tagline + Rating
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          InkWell(
                            onTap: _showStoreInfoSheet,
                            child: Row(
                              children: [
                                Flexible(
                                  child: Text(
                                    _storeName,
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: GoogleFonts.figtree(
                                      fontSize: 16,
                                      fontWeight: FontWeight.w800,
                                      color: AppColors.white,
                                    ),
                                  ),
                                ),
                                const SizedBox(width: 4),
                                const Icon(Icons.chevron_right, color: AppColors.white, size: 18),
                              ],
                            ),
                          ),
                          if (isVerified) ...[
                            const SizedBox(height: 3),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1.5),
                              decoration: BoxDecoration(
                                color: const Color(0xFFEAF8F1),
                                borderRadius: BorderRadius.circular(10),
                              ),
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  const Icon(Icons.verified, size: 11, color: Color(0xFF16A34A)),
                                  const SizedBox(width: 3),
                                  Text(
                                    'Verified Merchant',
                                    style: GoogleFonts.figtree(
                                      fontSize: 9.5,
                                      fontWeight: FontWeight.w700,
                                      color: const Color(0xFF16A34A),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                          if (tagline != null && tagline.isNotEmpty) ...[
                            const SizedBox(height: 2),
                            Text(
                              tagline,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: GoogleFonts.figtree(
                                fontSize: 11,
                                fontWeight: FontWeight.w600,
                                color: const Color(0xFFD1FAE5),
                              ),
                            ),
                          ],
                          const SizedBox(height: 3),
                          Row(
                            children: [
                              const Icon(Icons.star_rounded, color: AppColors.goldAccent, size: 15),
                              const SizedBox(width: 3),
                              Text(
                                rating > 0 ? rating.toStringAsFixed(1) : 'New Seller',
                                style: GoogleFonts.figtree(
                                  fontSize: 11.5,
                                  fontWeight: FontWeight.w700,
                                  color: AppColors.white,
                                ),
                              ),
                              const SizedBox(width: 8),
                              Text(
                                '•',
                                style: TextStyle(color: AppColors.white.withValues(alpha: 0.6)),
                              ),
                              const SizedBox(width: 8),
                              Text(
                                _formatFollowerCount(followers),
                                style: GoogleFonts.figtree(
                                  fontSize: 11.5,
                                  fontWeight: FontWeight.w500,
                                  color: AppColors.white,
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),

                    // Action buttons: Follow + Chat
                    Column(
                      children: [
                        // Chat button matching Web: White pill button with #087F8C text
                        InkWell(
                          onTap: _openChatWithSeller,
                          borderRadius: BorderRadius.circular(10),
                          child: Container(
                            width: 84,
                            padding: const EdgeInsets.symmetric(vertical: 6.5),
                            decoration: BoxDecoration(
                              color: AppColors.white,
                              borderRadius: BorderRadius.circular(10),
                              boxShadow: [
                                BoxShadow(
                                  color: Colors.black.withValues(alpha: 0.08),
                                  blurRadius: 4,
                                  offset: const Offset(0, 1),
                                ),
                              ],
                            ),
                            alignment: Alignment.center,
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                const Icon(Icons.chat_bubble_outline_rounded, color: AppColors.alonaTeal, size: 13),
                                const SizedBox(width: 4),
                                Text(
                                  'Chat',
                                  style: GoogleFonts.figtree(
                                    fontSize: 11.5,
                                    fontWeight: FontWeight.w800,
                                    color: AppColors.alonaTeal,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                        const SizedBox(height: 6),

                        // Follow button matching Web: Outlined with white border
                        InkWell(
                          onTap: _isTogglingFollow ? null : _toggleFollow,
                          borderRadius: BorderRadius.circular(10),
                          child: Container(
                            width: 84,
                            padding: const EdgeInsets.symmetric(vertical: 6),
                            decoration: BoxDecoration(
                              color: _isFollowing ? AppColors.white.withValues(alpha: 0.15) : Colors.transparent,
                              borderRadius: BorderRadius.circular(10),
                              border: Border.all(color: AppColors.white.withValues(alpha: 0.6), width: 1),
                            ),
                            alignment: Alignment.center,
                            child: _isTogglingFollow
                                ? const SizedBox(
                                    width: 12,
                                    height: 12,
                                    child: CircularProgressIndicator(color: AppColors.white, strokeWidth: 1.5),
                                  )
                                : Text(
                                    _isFollowing ? '✓ Following' : '+ Follow',
                                    style: GoogleFonts.figtree(
                                      fontSize: 11.5,
                                      fontWeight: FontWeight.w700,
                                      color: _isFollowing ? const Color(0xFF6EE7B7) : AppColors.white,
                                    ),
                                  ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  // 1. Shop Tab Widget
  Widget _buildShopTab() {
    return ListView(
      padding: const EdgeInsets.symmetric(vertical: 12),
      children: [
        // Store Vouchers Carousel
        if (_vouchers.isNotEmpty) ...[
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 14),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Store Vouchers',
                  style: GoogleFonts.figtree(fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.textPrimary),
                ),
                Text(
                  '${_vouchers.length} available',
                  style: GoogleFonts.figtree(fontSize: 11.5, color: AppColors.mutedText),
                ),
              ],
            ),
          ),
          const SizedBox(height: 8),
          SizedBox(
            height: 78,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 14),
              itemCount: _vouchers.length,
              separatorBuilder: (_, _) => const SizedBox(width: 10),
              itemBuilder: (context, index) {
                final v = _vouchers[index];
                return _buildVoucherCard(v);
              },
            ),
          ),
          const SizedBox(height: 16),
        ],

        // Bundle Deals Carousel
        if (_bundleDeals.isNotEmpty) ...[
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 14),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(5),
                      decoration: BoxDecoration(
                        color: AppColors.alonaTeal,
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: const Icon(Icons.card_giftcard_rounded, color: Colors.white, size: 14),
                    ),
                    const SizedBox(width: 8),
                    Text(
                      'Store Bundle Deals',
                      style: GoogleFonts.figtree(fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.textPrimary),
                    ),
                  ],
                ),
                Text(
                  '${_bundleDeals.length} active',
                  style: GoogleFonts.figtree(fontSize: 11.5, color: AppColors.mutedText),
                ),
              ],
            ),
          ),
          const SizedBox(height: 8),
          SizedBox(
            height: 112,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 14),
              itemCount: _bundleDeals.length,
              separatorBuilder: (_, _) => const SizedBox(width: 10),
              itemBuilder: (context, index) {
                final bundle = _bundleDeals[index];
                final name = bundle['name']?.toString() ?? 'Bundle Deal';
                final minQty = bundle['min_quantity'] ?? 2;
                final discountVal = bundle['discount_value'] ?? 0;
                final discountType = bundle['discount_type']?.toString() ?? 'percentage';
                final discountLabel = discountType == 'percentage'
                    ? '${(discountVal is num ? discountVal : double.tryParse(discountVal.toString()) ?? 0).toInt()}% OFF'
                    : '₱$discountVal OFF';

                return Container(
                  width: 250,
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                      colors: [Color(0xFFFFFDF9), Colors.white, Color(0xFFF0FAF9)],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: const Color(0xCCFDE68A)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(
                              color: AppColors.alonaTeal,
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text(
                              'BUNDLE',
                              style: GoogleFonts.figtree(fontSize: 9.5, fontWeight: FontWeight.w900, color: Colors.white),
                            ),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                            decoration: BoxDecoration(
                              color: const Color(0xFF043E44),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text(
                              discountLabel,
                              style: GoogleFonts.figtree(fontSize: 10.5, fontWeight: FontWeight.w900, color: Colors.white),
                            ),
                          ),
                        ],
                      ),
                      Text(
                        name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: GoogleFonts.figtree(fontSize: 12.5, fontWeight: FontWeight.w800, color: const Color(0xFF1F2937)),
                      ),
                      Text(
                        'Buy any $minQty participating items from this store',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: GoogleFonts.figtree(fontSize: 10.5, color: const Color(0xFF64748B), fontWeight: FontWeight.w500),
                      ),
                    ],
                  ),
                );
              },
            ),
          ),
          const SizedBox(height: 16),
        ],

        // Recommended Products Header
        if (_recommendedProducts.isNotEmpty) ...[
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 14),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Recommended For You',
                  style: GoogleFonts.figtree(fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.textPrimary),
                ),
                InkWell(
                  onTap: () => _tabController.animateTo(1),
                  child: Text(
                    'See All >',
                    style: GoogleFonts.figtree(fontSize: 12.5, fontWeight: FontWeight.w600, color: AppColors.alonaTeal),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 10),

          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12),
            child: GridView.builder(
              physics: const NeverScrollableScrollPhysics(),
              shrinkWrap: true,
              itemCount: _recommendedProducts.length > 4 ? 4 : _recommendedProducts.length,
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                childAspectRatio: 0.68,
                crossAxisSpacing: 12,
                mainAxisSpacing: 12,
              ),
              itemBuilder: (context, index) {
                return ProductCard(
                  product: _recommendedProducts[index],
                  isMall: _isMall,
                );
              },
            ),
          ),
          const SizedBox(height: 20),
        ],

        // About Store summary card
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14),
          child: Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: AppColors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: AppColors.border),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'About $_storeName',
                  style: GoogleFonts.figtree(fontSize: 14.5, fontWeight: FontWeight.w700, color: AppColors.textPrimary),
                ),
                const SizedBox(height: 6),
                Text(
                  _seller?['store_description']?.toString() ??
                      _seller?['store_tagline']?.toString() ??
                      'Official seller on Alona Marketplace.',
                  style: GoogleFonts.figtree(fontSize: 12.5, color: AppColors.mutedText, height: 1.4),
                ),
                const Divider(height: 24, color: AppColors.border),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceAround,
                  children: [
                    _buildStoreStatItem('Products', '${_stats['total_products'] ?? _products.length}'),
                    _buildStoreStatItem(
                      'Rating',
                      (_stats['avg_rating'] != null)
                          ? '${((_stats['avg_rating'] as num).toDouble()).toStringAsFixed(1)} / 5.0'
                          : 'No rating',
                    ),
                    _buildStoreStatItem('Followers', _formatFollowerCount((_stats['followers_count'] as num?)?.toInt() ?? 0)),
                  ],
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildStoreStatItem(String label, String value) {
    return Column(
      children: [
        Text(
          value,
          style: GoogleFonts.figtree(fontSize: 13, fontWeight: FontWeight.w700, color: AppColors.alonaTeal),
        ),
        const SizedBox(height: 2),
        Text(
          label,
          style: GoogleFonts.figtree(fontSize: 11, color: AppColors.mutedText),
        ),
      ],
    );
  }

  Widget _buildVoucherCard(dynamic v) {
    final code = v['code']?.toString() ?? '';
    final desc = v['description']?.toString() ?? (v['name']?.toString() ?? 'Store Voucher');

    return Container(
      width: 170,
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: AppColors.lightTeal,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: AppColors.oceanTeal.withValues(alpha: 0.35)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Row(
            children: [
              const Icon(Icons.confirmation_number_outlined, size: 14, color: AppColors.alonaTeal),
              const SizedBox(width: 4),
              Flexible(
                child: Text(
                  code,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: GoogleFonts.figtree(fontSize: 12, fontWeight: FontWeight.w800, color: AppColors.alonaTeal),
                ),
              ),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            desc,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: GoogleFonts.figtree(fontSize: 11, color: AppColors.textPrimary),
          ),
        ],
      ),
    );
  }

  // 2. Products Tab Widget matching other screens (using unified ProductCard)
  Widget _buildProductsTab() {
    return Column(
      children: [
        // Sub-filter Sort Bar: Popular | Latest | Top Sales | Price ⇅
        Container(
          color: AppColors.white,
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 2),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,
            children: [
              _buildSortItem('Popular', 'popular'),
              _buildSortItem('Latest', 'latest', showDot: true),
              _buildSortItem('Top Sales', 'top_sales'),
              _buildSortItem(
                'Price ⇅',
                _currentSort == 'price-low' ? 'price-high' : 'price-low',
                isActive: _currentSort == 'price-low' || _currentSort == 'price-high',
              ),
            ],
          ),
        ),
        const Divider(height: 1, thickness: 0.8, color: AppColors.border),

        // Selected Category Pill (if filtered)
        if (_selectedCategory != null)
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
            color: AppColors.background,
            child: Row(
              children: [
                Text(
                  'Category: $_selectedCategory',
                  style: GoogleFonts.figtree(fontSize: 12, fontWeight: FontWeight.w600, color: AppColors.alonaTeal),
                ),
                const SizedBox(width: 6),
                InkWell(
                  onTap: () {
                    setState(() => _selectedCategory = null);
                    _loadStoreData();
                  },
                  child: const Icon(Icons.close, size: 16, color: AppColors.mutedText),
                ),
              ],
            ),
          ),

        // Product Grid using identical ProductCard component
        Expanded(
          child: _products.isEmpty
              ? Padding(
                  padding: const EdgeInsets.all(24),
                  child: EmptyStateView(
                    icon: Icons.inventory_2_outlined,
                    title: 'No products found',
                    message: 'Try searching for another keyword or filter',
                  ),
                )
              : GridView.builder(
                  padding: const EdgeInsets.all(12),
                  itemCount: _products.length,
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2,
                    childAspectRatio: 0.68,
                    crossAxisSpacing: 12,
                    mainAxisSpacing: 12,
                  ),
                  itemBuilder: (context, index) {
                    return ProductCard(
                      product: _products[index],
                      isMall: _isMall,
                    );
                  },
                ),
        ),
      ],
    );
  }

  Widget _buildSortItem(String label, String sortKey, {bool? isActive, bool showDot = false}) {
    final selected = isActive ?? (_currentSort == sortKey);

    return InkWell(
      onTap: () {
        setState(() => _currentSort = sortKey);
        _loadStoreData();
      },
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 10),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              label,
              style: GoogleFonts.figtree(
                fontSize: 13,
                fontWeight: selected ? FontWeight.w700 : FontWeight.w500,
                color: selected ? AppColors.alonaTeal : AppColors.textPrimary,
              ),
            ),
            if (showDot) ...[
              const SizedBox(width: 3),
              Container(
                width: 5,
                height: 5,
                decoration: const BoxDecoration(
                  color: AppColors.sale,
                  shape: BoxShape.circle,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  // 3. Reviews Tab Widget
  Widget _buildReviewsTab() {
    final double rating = (_stats['avg_rating'] as num?)?.toDouble() ?? 0.0;
    final int reviewsCount = (_stats['total_reviews'] as num?)?.toInt() ?? _reviews.length;
    final List<dynamic> breakdown = (_stats['rating_breakdown'] as List?) ?? [];

    return ListView(
      padding: const EdgeInsets.all(14),
      children: [
        // Overall Rating Score Card
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: AppColors.white,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: AppColors.border),
          ),
          child: Row(
            children: [
              Column(
                children: [
                  Text(
                    rating > 0 ? rating.toStringAsFixed(1) : '0.0',
                    style: GoogleFonts.figtree(fontSize: 32, fontWeight: FontWeight.w800, color: AppColors.textPrimary),
                  ),
                  Row(
                    children: List.generate(
                      5,
                      (i) => Icon(
                        Icons.star_rounded,
                        color: i < rating.round() ? AppColors.goldAccent : AppColors.border,
                        size: 16,
                      ),
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    '$reviewsCount Reviews',
                    style: GoogleFonts.figtree(fontSize: 11.5, color: AppColors.mutedText),
                  ),
                ],
              ),
              const SizedBox(width: 20),
              Expanded(
                child: Column(
                  children: breakdown.isNotEmpty
                      ? breakdown.map((item) {
                          final stars = item['stars'] ?? 5;
                          final pct = (item['percentage'] as num?)?.toDouble() ?? 0.0;
                          return _buildRatingBar('$stars Stars', pct);
                        }).toList()
                      : [
                          _buildRatingBar('5 Stars', 0.0),
                          _buildRatingBar('4 Stars', 0.0),
                          _buildRatingBar('3 Stars', 0.0),
                          _buildRatingBar('2 Stars', 0.0),
                          _buildRatingBar('1 Star', 0.0),
                        ],
                ),
              ),
            ],
          ),
        ),

        const SizedBox(height: 14),

        Text(
          'Customer Feedback',
          style: GoogleFonts.figtree(fontSize: 15, fontWeight: FontWeight.w700, color: AppColors.textPrimary),
        ),
        const SizedBox(height: 10),

        if (_reviews.isEmpty)
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 20),
            child: EmptyStateView(
              icon: Icons.rate_review_outlined,
              title: 'No reviews yet',
              message: 'Buyer reviews will appear here once submitted.',
            ),
          )
        else
          ..._reviews.map((r) => _buildReviewItem(
                name: r.userName,
                avatarUrl: r.avatarUrl,
                rating: r.rating,
                date: r.createdAt ?? 'Recently',
                comment: r.comment ?? '',
                product: r.productName,
                sellerReply: r.sellerReply,
              )),
      ],
    );
  }

  Widget _buildRatingBar(String label, double percent) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Row(
        children: [
          SizedBox(
            width: 46,
            child: Text(label, style: GoogleFonts.figtree(fontSize: 10.5, color: AppColors.mutedText)),
          ),
          Expanded(
            child: ClipRRect(
              borderRadius: BorderRadius.circular(4),
              child: LinearProgressIndicator(
                value: percent.clamp(0.0, 1.0),
                backgroundColor: AppColors.border,
                valueColor: const AlwaysStoppedAnimation<Color>(AppColors.goldAccent),
                minHeight: 6,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildReviewItem({
    required String name,
    String? avatarUrl,
    required int rating,
    required String date,
    required String comment,
    String? product,
    String? sellerReply,
  }) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Row(
                children: [
                  Container(
                    width: 28,
                    height: 28,
                    decoration: const BoxDecoration(
                      color: AppColors.lightTeal,
                      shape: BoxShape.circle,
                    ),
                    child: ClipOval(
                      child: avatarUrl != null && avatarUrl.isNotEmpty
                          ? NetworkImageBox(imageUrl: avatarUrl, width: 28, height: 28, fit: BoxFit.cover)
                          : Center(
                              child: Text(
                                name.isNotEmpty ? name[0].toUpperCase() : 'B',
                                style: GoogleFonts.figtree(fontSize: 12, fontWeight: FontWeight.w700, color: AppColors.alonaTeal),
                              ),
                            ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Text(name, style: GoogleFonts.figtree(fontSize: 13, fontWeight: FontWeight.w700, color: AppColors.textPrimary)),
                ],
              ),
              Text(date, style: GoogleFonts.figtree(fontSize: 11, color: AppColors.mutedText)),
            ],
          ),
          const SizedBox(height: 6),
          Row(
            children: List.generate(
              rating,
              (i) => const Icon(Icons.star_rounded, color: AppColors.goldAccent, size: 14),
            ),
          ),
          if (comment.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(comment, style: GoogleFonts.figtree(fontSize: 12.5, color: AppColors.textPrimary, height: 1.35)),
          ],
          if (product != null && product.isNotEmpty) ...[
            const SizedBox(height: 6),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
              decoration: BoxDecoration(
                color: AppColors.background,
                borderRadius: BorderRadius.circular(4),
              ),
              child: Text(
                'Product: $product',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: GoogleFonts.figtree(fontSize: 10.5, color: AppColors.mutedText),
              ),
            ),
          ],
          if (sellerReply != null && sellerReply.isNotEmpty) ...[
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: AppColors.background,
                borderRadius: BorderRadius.circular(6),
                border: Border.all(color: AppColors.border),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Seller Response:',
                    style: GoogleFonts.figtree(fontSize: 11, fontWeight: FontWeight.w700, color: AppColors.alonaTeal),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    sellerReply,
                    style: GoogleFonts.figtree(fontSize: 11.5, color: AppColors.textPrimary),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }

  // 4. Categories Tab Widget: Categories + Subcategories + Sort + Products Grid
  Widget _buildCategoriesTab() {
    // Build dynamic categories from backend _categories + fallback from _products
    final List<Map<String, dynamic>> categoryItems = [];
    for (final rawCat in _categories) {
      if (rawCat is Map) {
        final name = rawCat['name']?.toString() ?? '';
        final slug = rawCat['slug']?.toString() ?? name;
        final subsRaw = rawCat['subcategories'] is List ? (rawCat['subcategories'] as List) : const [];
        final subs = <Map<String, String>>[];
        for (final s in subsRaw) {
          if (s is Map && s['name'] != null) {
            subs.add({
              'name': s['name'].toString(),
              'slug': (s['slug'] ?? s['name']).toString(),
            });
          }
        }
        if (name.isNotEmpty) {
          categoryItems.add({
            'name': name,
            'slug': slug,
            'subcategories': subs,
          });
        }
      }
    }

    // Also supplement from loaded products if backend categories list was empty
    if (categoryItems.isEmpty && _products.isNotEmpty) {
      final Map<String, Map<String, dynamic>> derived = {};
      for (final p in _products) {
        final parentName = p.parentCategoryName ?? p.categoryName;
        final parentSlug = p.parentCategorySlug ?? p.categorySlug ?? parentName;
        if (parentName != null && parentName.isNotEmpty && parentSlug != null) {
          derived.putIfAbsent(parentSlug, () => {
                'name': parentName,
                'slug': parentSlug,
                'subcategories': <Map<String, String>>[],
              });
          if (p.parentCategoryName != null &&
              p.categoryName != null &&
              p.categoryName != p.parentCategoryName) {
            final subsList = derived[parentSlug]!['subcategories'] as List<Map<String, String>>;
            final subSlug = p.categorySlug ?? p.categoryName!;
            if (!subsList.any((s) => s['slug'] == subSlug)) {
              subsList.add({
                'name': p.categoryName!,
                'slug': subSlug,
              });
            }
          }
        }
      }
      categoryItems.addAll(derived.values);
    }

    // Collect subcategories for the currently selected category (or all subcategories if 'all')
    final List<Map<String, String>> activeSubcategories = [];
    for (final cat in categoryItems) {
      final isSelectedCat = _selectedCategory == null ||
          _selectedCategory == cat['slug'] ||
          _selectedCategory == cat['name'];
      if (isSelectedCat && cat['subcategories'] is List) {
        for (final sub in (cat['subcategories'] as List)) {
          if (sub is Map<String, String> &&
              !activeSubcategories.any((s) => s['slug'] == sub['slug'])) {
            activeSubcategories.add(sub);
          }
        }
      }
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // 1. Main Categories Filter Bar
        if (categoryItems.isNotEmpty)
          Container(
            width: double.infinity,
            color: AppColors.white,
            padding: const EdgeInsets.fromLTRB(12, 10, 12, 8),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'CATEGORIES',
                  style: GoogleFonts.figtree(
                    fontSize: 10.5,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 1.1,
                    color: AppColors.mutedText,
                  ),
                ),
                const SizedBox(height: 6),
                SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: [
                      _buildCategoryChip(
                        label: 'All Categories',
                        selected: _selectedCategory == null,
                        onTap: () {
                          setState(() {
                            _selectedCategory = null;
                            _selectedSubcategory = null;
                          });
                          _loadStoreData();
                        },
                      ),
                      ...categoryItems.map((cat) {
                        final slug = cat['slug']?.toString() ?? '';
                        final name = cat['name']?.toString() ?? 'Category';
                        final selected = _selectedCategory == slug || _selectedCategory == name;
                        return _buildCategoryChip(
                          label: name,
                          selected: selected,
                          onTap: () {
                            setState(() {
                              _selectedCategory = selected ? null : slug;
                              _selectedSubcategory = null;
                            });
                            _loadStoreData();
                          },
                        );
                      }),
                    ],
                  ),
                ),

                // 2. Subcategories Filter Row
                if (activeSubcategories.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      const Icon(Icons.subdirectory_arrow_right_rounded, size: 15, color: AppColors.alonaTeal),
                      const SizedBox(width: 4),
                      Text(
                        'Subcategory:',
                        style: GoogleFonts.figtree(
                          fontSize: 11.5,
                          fontWeight: FontWeight.w700,
                          color: AppColors.alonaTeal,
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: SingleChildScrollView(
                          scrollDirection: Axis.horizontal,
                          child: Row(
                            children: [
                              _buildSubcategoryChip(
                                label: 'All',
                                selected: _selectedSubcategory == null,
                                onTap: () {
                                  setState(() => _selectedSubcategory = null);
                                  _loadStoreData();
                                },
                              ),
                              ...activeSubcategories.map((sub) {
                                final subSlug = sub['slug'] ?? '';
                                final subName = sub['name'] ?? '';
                                final selected = _selectedSubcategory == subSlug || _selectedSubcategory == subName;
                                return _buildSubcategoryChip(
                                  label: subName,
                                  selected: selected,
                                  onTap: () {
                                    setState(() {
                                      _selectedSubcategory = selected ? null : subSlug;
                                    });
                                    _loadStoreData();
                                  },
                                );
                              }),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ],
            ),
          ),

        // 3. Sort Bar: Popular | Latest | Top Sales | Price ⇅
        Container(
          color: AppColors.white,
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 2),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,
            children: [
              _buildSortItem('Popular', 'popular'),
              _buildSortItem('Latest', 'latest', showDot: true),
              _buildSortItem('Top Sales', 'top_sales'),
              _buildSortItem(
                'Price ⇅',
                _currentSort == 'price-low' ? 'price-high' : 'price-low',
                isActive: _currentSort == 'price-low' || _currentSort == 'price-high',
              ),
            ],
          ),
        ),
        const Divider(height: 1, thickness: 0.8, color: AppColors.border),

        // 4. Products Grid inside Categories Tab
        Expanded(
          child: _products.isEmpty
              ? Padding(
                  padding: const EdgeInsets.all(24),
                  child: EmptyStateView(
                    icon: Icons.inventory_2_outlined,
                    title: 'No products in this category',
                    message: 'Try selecting another category or subcategory filter above.',
                  ),
                )
              : GridView.builder(
                  padding: const EdgeInsets.all(12),
                  itemCount: _products.length,
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2,
                    childAspectRatio: 0.68,
                    crossAxisSpacing: 12,
                    mainAxisSpacing: 12,
                  ),
                  itemBuilder: (context, index) {
                    return ProductCard(
                      product: _products[index],
                      isMall: _isMall,
                    );
                  },
                ),
        ),
      ],
    );
  }

  Widget _buildCategoryChip({
    required String label,
    required bool selected,
    required VoidCallback onTap,
  }) {
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(20),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
          decoration: BoxDecoration(
            color: selected ? AppColors.alonaTeal : AppColors.background,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(
              color: selected ? AppColors.alonaTeal : AppColors.border,
            ),
          ),
          child: Text(
            label,
            style: GoogleFonts.figtree(
              fontSize: 12.5,
              fontWeight: FontWeight.w700,
              color: selected ? Colors.white : AppColors.textPrimary,
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildSubcategoryChip({
    required String label,
    required bool selected,
    required VoidCallback onTap,
  }) {
    return Padding(
      padding: const EdgeInsets.only(right: 6),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4.5),
          decoration: BoxDecoration(
            color: selected ? AppColors.lightTeal : AppColors.white,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: selected ? AppColors.alonaTeal : AppColors.border,
            ),
          ),
          child: Text(
            label,
            style: GoogleFonts.figtree(
              fontSize: 11.5,
              fontWeight: FontWeight.w700,
              color: selected ? AppColors.alonaTeal : AppColors.mutedText,
            ),
          ),
        ),
      ),
    );
  }

  void _showStoreInfoSheet() {
    showModalBottomSheet(
      context: context,
      backgroundColor: AppColors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (ctx) {
        final returnDays = _seller?['return_policy_days'] ?? 7;
        final responseTime = _seller?['response_time_label']?.toString() ?? 'Within a day';

        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Center(
                  child: Container(
                    width: 36,
                    height: 4,
                    decoration: BoxDecoration(
                      color: AppColors.border,
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                Text(
                  _storeName,
                  style: GoogleFonts.figtree(fontSize: 18, fontWeight: FontWeight.w800, color: AppColors.textPrimary),
                ),
                if (_seller?['store_tagline'] != null && _seller!['store_tagline'].toString().isNotEmpty) ...[
                  const SizedBox(height: 6),
                  Text(
                    _seller!['store_tagline'].toString(),
                    style: GoogleFonts.figtree(fontSize: 13, color: AppColors.mutedText),
                  ),
                ],
                const Divider(height: 24, color: AppColors.border),
                _buildInfoRow(Icons.access_time_rounded, 'Response Time', responseTime),
                const SizedBox(height: 10),
                _buildInfoRow(Icons.assignment_return_outlined, 'Return Policy', '$returnDays Days Return Policy'),
                if (_seller?['shipping_policy'] != null && _seller!['shipping_policy'].toString().isNotEmpty) ...[
                  const SizedBox(height: 10),
                  _buildInfoRow(Icons.local_shipping_outlined, 'Shipping', _seller!['shipping_policy'].toString()),
                ],
                if (_seller?['warranty_policy'] != null && _seller!['warranty_policy'].toString().isNotEmpty) ...[
                  const SizedBox(height: 10),
                  _buildInfoRow(Icons.verified_user_outlined, 'Warranty', _seller!['warranty_policy'].toString()),
                ],
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _buildInfoRow(IconData icon, String label, String value) {
    return Row(
      children: [
        Icon(icon, size: 18, color: AppColors.alonaTeal),
        const SizedBox(width: 10),
        Text(
          label,
          style: GoogleFonts.figtree(fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.textPrimary),
        ),
        const Spacer(),
        Flexible(
          child: Text(
            value,
            textAlign: TextAlign.right,
            style: GoogleFonts.figtree(fontSize: 12.5, color: AppColors.mutedText),
          ),
        ),
      ],
    );
  }
}

class _SliverAppBarDelegate extends SliverPersistentHeaderDelegate {
  final Widget _widget;

  _SliverAppBarDelegate(this._widget);

  @override
  double get minExtent => 44.0;
  @override
  double get maxExtent => 44.0;

  @override
  Widget build(BuildContext context, double shrinkOffset, bool overlapsContent) {
    return _widget;
  }

  @override
  bool shouldRebuild(_SliverAppBarDelegate oldDelegate) {
    return false;
  }
}
