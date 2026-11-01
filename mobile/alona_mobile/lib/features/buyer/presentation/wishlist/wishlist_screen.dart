import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/shared/widgets/empty_state_view.dart';
import 'package:alona_mobile/features/buyer/models/buyer_models.dart';
import 'package:alona_mobile/features/buyer/services/buyer_service.dart';
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

  bool _isLoading = true;
  String? _error;
  List<ProductModel> _items = [];

  @override
  void initState() {
    super.initState();
    _loadWishlist();
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
    return Scaffold(
      backgroundColor: _bgScaffold,
      body: SafeArea(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 12),
              child: Row(
                children: [
                  if (Navigator.canPop(context)) ...[
                    GestureDetector(
                      onTap: () => Navigator.pop(context),
                      child: Container(
                        padding: const EdgeInsets.all(8),
                        margin: const EdgeInsets.only(right: 12),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: const Color(0xFFE2EBE9)),
                        ),
                        child: const Icon(Icons.arrow_back_ios_new_rounded, size: 16, color: _headingColor),
                      ),
                    ),
                  ],
                  Text(
                    'My Wishlist',
                    style: GoogleFonts.figtree(
                      fontSize: 26,
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
                        '${_items.length}',
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
                      : _items.isEmpty
                          ? Padding(
                              padding: const EdgeInsets.fromLTRB(16, 8, 16, 96),
                              child: EmptyStateView(
                                icon: Icons.favorite_border_rounded,
                                title: 'Your wishlist is empty',
                                message: 'Save items you love by tapping the heart icon on any product.',
                                buttonText: 'Discover Products',
                                onButtonPressed: _handleBrowse,
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
                                itemCount: _items.length,
                                itemBuilder: (context, index) {
                                  return ProductCard(product: _items[index]);
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
