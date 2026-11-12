import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/shared/widgets/empty_state_view.dart';
import 'package:alona_mobile/features/buyer/models/buyer_models.dart';
import 'package:alona_mobile/features/buyer/services/buyer_service.dart';
import 'package:alona_mobile/features/buyer/presentation/cart/cart_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/messages/buyer_conversations_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/home/widgets/product_card.dart';
import 'package:alona_mobile/features/buyer/presentation/products/product_list_screen.dart';

class WishlistScreen extends StatefulWidget {
  final VoidCallback? onBrowseProducts;

  const WishlistScreen({super.key, this.onBrowseProducts});

  @override
  State<WishlistScreen> createState() => _WishlistScreenState();
}

class _WishlistScreenState extends State<WishlistScreen> {
  static const Color _bgScaffold = AppColors.buyerBackground;
  static const Color _darkTeal = Color(0xFF087F8C);
  static const Color _headingColor = Color(0xFF132228);

  final TextEditingController _searchController = TextEditingController();
  String _searchQuery = '';
  bool _isLoading = true;
  String? _error;
  List<ProductModel> _items = [];
  List<ProductModel> _recommendedProducts = [];

  @override
  void initState() {
    super.initState();
    _loadWishlist();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  List<ProductModel> get _filteredItems {
    if (_searchQuery.trim().isEmpty) return _items;
    final q = _searchQuery.toLowerCase().trim();
    return _items
        .where((p) =>
            p.name.toLowerCase().contains(q) ||
            (p.categoryName?.toLowerCase().contains(q) ?? false) ||
            (p.sellerStoreName?.toLowerCase().contains(q) ?? false))
        .toList();
  }

  Future<void> _loadWishlist() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    List<ProductModel> wishlistItems = [];
    String? wishlistError;

    try {
      wishlistItems = await BuyerService.getWishlist();
    } catch (e) {
      wishlistError = e.toString();
    }

    List<ProductModel> recommendations = [];
    try {
      recommendations = await BuyerService.getYouMayAlsoLike(
        excludeIds: wishlistItems.map((e) => e.id).toSet(),
        limit: 8,
      );
    } catch (_) {}

    if (!mounted) return;
    setState(() {
      _items = wishlistItems;
      _recommendedProducts = recommendations;
      _error = wishlistError;
      _isLoading = false;
    });
  }

  void _handleBrowse() {
    if (widget.onBrowseProducts != null) {
      widget.onBrowseProducts!();
    } else {
      Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => const ProductListScreen()),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final canPop = Navigator.canPop(context);
    final filtered = _filteredItems;

    return Scaffold(
      backgroundColor: _bgScaffold,
      appBar: AppBar(
        titleSpacing: canPop ? 0 : 16,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        backgroundColor: AppColors.primary,
        flexibleSpace: const DecoratedBox(
          decoration: BoxDecoration(gradient: AppColors.buyerBrandGradient),
        ),
        automaticallyImplyLeading: false,
        leading: canPop
            ? IconButton(
                icon: const Icon(Icons.arrow_back_rounded, color: Colors.white),
                onPressed: () => Navigator.pop(context),
              )
            : null,
        title: Row(
          children: [
            // Left side: Search Bar matching Home layout
            Expanded(
              child: Container(
                height: 40,
                padding: const EdgeInsets.symmetric(horizontal: 14),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(24),
                  border: Border.all(color: AppColors.buyerBorder, width: 1),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.search_rounded, color: AppColors.primary, size: 19),
                    const SizedBox(width: 8),
                    Expanded(
                      child: TextField(
                        controller: _searchController,
                        onChanged: (val) => setState(() => _searchQuery = val),
                        style: GoogleFonts.figtree(
                          color: AppColors.textPrimary,
                          fontSize: 13,
                          fontWeight: FontWeight.w500,
                        ),
                        decoration: InputDecoration(
                          hintText: 'Search products, brands...',
                          hintStyle: GoogleFonts.figtree(
                            color: AppColors.textMuted,
                            fontSize: 13,
                            fontWeight: FontWeight.w500,
                          ),
                          border: InputBorder.none,
                          enabledBorder: InputBorder.none,
                          focusedBorder: InputBorder.none,
                          isDense: true,
                          contentPadding: const EdgeInsets.symmetric(vertical: 10),
                        ),
                      ),
                    ),
                    if (_searchQuery.isNotEmpty)
                      GestureDetector(
                        onTap: () {
                          _searchController.clear();
                          setState(() => _searchQuery = '');
                        },
                        child: const Icon(Icons.close_rounded, size: 18, color: AppColors.mutedText),
                      ),
                  ],
                ),
              ),
            ),
            const SizedBox(width: 4),

            // Right side: Message and Cart icons
            IconButton(
              icon: const Icon(Icons.chat_bubble_outline_rounded, color: Colors.white, size: 22),
              tooltip: 'Messages',
              onPressed: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const BuyerConversationsScreen()),
                );
              },
            ),
            IconButton(
              icon: const Icon(Icons.shopping_bag_outlined, color: Colors.white, size: 24),
              tooltip: 'Cart',
              onPressed: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const CartScreen()),
                );
              },
            ),
          ],
        ),
      ),
      body: SafeArea(
        child: _isLoading
            ? const Center(child: CircularProgressIndicator(color: _darkTeal))
            : RefreshIndicator(
                onRefresh: _loadWishlist,
                color: _darkTeal,
                child: SingleChildScrollView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 96),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Header
                      Padding(
                        padding: const EdgeInsets.only(left: 4, right: 4, bottom: 12),
                        child: Row(
                          children: [
                            Text(
                              'My Wishlist',
                              style: GoogleFonts.figtree(
                                fontSize: 22,
                                fontWeight: FontWeight.w800,
                                color: _headingColor,
                                letterSpacing: -0.5,
                              ),
                            ),
                            if (_items.isNotEmpty) ...[
                              const SizedBox(width: 8),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFE3F3F1),
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: Text(
                                  '${filtered.length}',
                                  style: GoogleFonts.figtree(
                                    fontSize: 12,
                                    fontWeight: FontWeight.w700,
                                    color: _darkTeal,
                                  ),
                                ),
                              ),
                            ],
                          ],
                        ),
                      ),

                      // Wishlist Content (or Empty State)
                      if (_error != null && _items.isEmpty)
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.all(24),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(24),
                          ),
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(_error!, textAlign: TextAlign.center),
                              const SizedBox(height: 12),
                              ElevatedButton(
                                onPressed: _loadWishlist,
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: _darkTeal,
                                  foregroundColor: Colors.white,
                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                                  elevation: 0,
                                ),
                                child: const Text('Try Again'),
                              ),
                            ],
                          ),
                        )
                      else if (filtered.isEmpty)
                        EmptyStateView(
                          icon: Icons.favorite_border_rounded,
                          title: _searchQuery.isNotEmpty ? 'No matching items' : 'Your wishlist is empty',
                          message: _searchQuery.isNotEmpty
                              ? 'No saved products match "$_searchQuery".'
                              : 'Save items you love by tapping the heart icon on any product.',
                          buttonText: _searchQuery.isNotEmpty ? 'Clear Search' : 'Discover Products',
                          onButtonPressed: _searchQuery.isNotEmpty
                              ? () {
                                  _searchController.clear();
                                  setState(() => _searchQuery = '');
                                }
                              : _handleBrowse,
                        )
                      else
                        GridView.builder(
                          shrinkWrap: true,
                          physics: const NeverScrollableScrollPhysics(),
                          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                            crossAxisCount: 2,
                            crossAxisSpacing: 12,
                            mainAxisSpacing: 12,
                            childAspectRatio: 0.65,
                          ),
                          itemCount: filtered.length,
                          itemBuilder: (context, index) {
                            return ProductCard(
                              product: filtered[index],
                              onReturn: _loadWishlist,
                            );
                          },
                        ),

                      // YOU MAY ALSO LIKE SECTION (Always visible)
                      const SizedBox(height: 28),
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 4),
                        child: Row(
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
                                  'You May Also Like',
                                  style: GoogleFonts.figtree(
                                    fontSize: 18,
                                    fontWeight: FontWeight.w800,
                                    color: _headingColor,
                                  ),
                                ),
                              ],
                            ),
                            TextButton(
                              onPressed: () {
                                Navigator.push(
                                  context,
                                  MaterialPageRoute(builder: (_) => const ProductListScreen()),
                                );
                              },
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Text(
                                    'See all',
                                    style: GoogleFonts.figtree(
                                      fontWeight: FontWeight.w700,
                                      color: _darkTeal,
                                      fontSize: 13,
                                    ),
                                  ),
                                  const SizedBox(width: 4),
                                  const Icon(Icons.arrow_forward_rounded, size: 14, color: _darkTeal),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 8),
                      if (_recommendedProducts.isNotEmpty)
                        GridView.builder(
                          shrinkWrap: true,
                          physics: const NeverScrollableScrollPhysics(),
                          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                            crossAxisCount: 2,
                            crossAxisSpacing: 12,
                            mainAxisSpacing: 12,
                            childAspectRatio: 0.65,
                          ),
                          itemCount: _recommendedProducts.length,
                          itemBuilder: (context, index) {
                            return ProductCard(
                              product: _recommendedProducts[index],
                              onReturn: _loadWishlist,
                            );
                          },
                        )
                      else
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 28),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(20),
                            border: Border.all(color: const Color(0xFFE2EBE9)),
                          ),
                          child: Column(
                            children: [
                              const Icon(Icons.storefront_outlined, size: 32, color: _darkTeal),
                              const SizedBox(height: 8),
                              Text(
                                'Discover more products',
                                style: GoogleFonts.figtree(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w800,
                                  color: _headingColor,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                'Browse the catalog to find items tailored for you.',
                                textAlign: TextAlign.center,
                                style: GoogleFonts.figtree(
                                  fontSize: 12,
                                  color: const Color(0xFF6B7C7C),
                                ),
                              ),
                            ],
                          ),
                        ),
                    ],
                  ),
                ),
              ),
      ),
    );
  }
}
