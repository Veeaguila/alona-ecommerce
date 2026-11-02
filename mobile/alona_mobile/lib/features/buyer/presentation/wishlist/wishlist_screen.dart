import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/shared/widgets/empty_state_view.dart';
import 'package:alona_mobile/features/buyer/models/buyer_models.dart';
import 'package:alona_mobile/features/buyer/services/buyer_service.dart';
import 'package:alona_mobile/features/buyer/presentation/cart/cart_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/messages/buyer_conversations_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/home/widgets/product_card.dart';

class WishlistScreen extends StatefulWidget {
  final VoidCallback? onBrowseProducts;

  const WishlistScreen({super.key, this.onBrowseProducts});

  @override
  State<WishlistScreen> createState() => _WishlistScreenState();
}

class _WishlistScreenState extends State<WishlistScreen> {
  static const Color _bgScaffold = Color(0xFFF2F6F6);
  static const Color _darkTeal = Color(0xFF087F8C);
  static const Color _headingColor = Color(0xFF132228);

  final TextEditingController _searchController = TextEditingController();
  String _searchQuery = '';
  bool _isLoading = true;
  String? _error;
  List<ProductModel> _items = [];

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

    try {
      final items = await BuyerService.getWishlist();
      if (!mounted) return;
      setState(() {
        _items = items;
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

  void _handleBrowse() {
    if (widget.onBrowseProducts != null) {
      widget.onBrowseProducts!();
    } else if (Navigator.canPop(context)) {
      Navigator.pop(context);
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
        backgroundColor: Colors.white,
        automaticallyImplyLeading: false,
        leading: canPop
            ? IconButton(
                icon: const Icon(Icons.arrow_back_rounded, color: AppColors.textPrimary),
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
                  color: AppColors.background,
                  borderRadius: BorderRadius.circular(24),
                  border: Border.all(color: AppColors.border, width: 1),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.search_rounded, color: AppColors.mutedText, size: 19),
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
                            color: AppColors.textLight,
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
              icon: const Icon(Icons.chat_bubble_outline_rounded, color: AppColors.textPrimary, size: 22),
              tooltip: 'Messages',
              onPressed: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const BuyerConversationsScreen()),
                );
              },
            ),
            IconButton(
              icon: const Icon(Icons.shopping_bag_outlined, color: AppColors.textPrimary, size: 24),
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
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 12),
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

            // Content
            Expanded(
              child: _isLoading
                  ? const Center(child: CircularProgressIndicator(color: _darkTeal))
                  : _error != null
                      ? Center(
                          child: Padding(
                            padding: const EdgeInsets.all(24),
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
                          ),
                        )
                      : filtered.isEmpty
                          ? Padding(
                              padding: const EdgeInsets.fromLTRB(16, 8, 16, 96),
                              child: EmptyStateView(
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
                              ),
                            )
                          : RefreshIndicator(
                              onRefresh: _loadWishlist,
                              color: _darkTeal,
                              child: GridView.builder(
                                padding: const EdgeInsets.fromLTRB(16, 8, 16, 96),
                                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                                  crossAxisCount: 2,
                                  crossAxisSpacing: 12,
                                  mainAxisSpacing: 12,
                                  childAspectRatio: 0.65,
                                ),
                                itemCount: filtered.length,
                                itemBuilder: (context, index) {
                                  return ProductCard(product: filtered[index]);
                                },
                              ),
                            ),
            ),
          ],
        ),
      ),
    );
  }
}
