import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/core/services/auth_state.dart';
import 'package:alona_mobile/shared/widgets/alona_button.dart';
import 'package:alona_mobile/features/buyer/models/buyer_models.dart';
import 'package:alona_mobile/features/buyer/services/buyer_service.dart';
import 'package:alona_mobile/features/buyer/presentation/auth/buyer_login_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/orders/order_list_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/vouchers/vouchers_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/wishlist/wishlist_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/profile/address_management_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/profile/buyer_settings_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/home/widgets/product_card.dart';
import 'package:alona_mobile/features/buyer/presentation/products/product_list_screen.dart';

class BuyerProfileScreen extends StatefulWidget {
  final AuthState authState;

  const BuyerProfileScreen({super.key, required this.authState});

  @override
  State<BuyerProfileScreen> createState() => _BuyerProfileScreenState();
}

class _BuyerProfileScreenState extends State<BuyerProfileScreen> {
  static const Color _bgScaffold = AppColors.buyerBackground;
  static const Color _darkTeal = AppColors.primary;
  static const Color _lightTeal = AppColors.lightTeal;
  static const Color _cardBorder = AppColors.border;
  static const Color _headingColor = AppColors.textPrimary;
  static const Color _subtitleColor = AppColors.textMuted;

  List<ProductModel> _recommendedProducts = [];
  bool _isLoadingRecommended = true;

  @override
  void initState() {
    super.initState();
    _loadRecommendations();
  }

  Future<void> _loadRecommendations() async {
    setState(() => _isLoadingRecommended = true);
    try {
      final items = await BuyerService.getYouMayAlsoLike(limit: 8);
      if (!mounted) return;
      setState(() {
        _recommendedProducts = items;
        _isLoadingRecommended = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _isLoadingRecommended = false);
    }
  }

  Future<void> _openSettings({String? initialPage}) async {
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => BuyerSettingsScreen(
          authState: widget.authState,
          initialPage: initialPage,
        ),
      ),
    );
    if (mounted) {
      setState(() {});
    }
  }

  @override
  Widget build(BuildContext context) {
    final isAuth = widget.authState.isBuyerAuthenticated;
    final user = widget.authState.buyerUser;

    return Scaffold(
      backgroundColor: _bgScaffold,
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: _loadRecommendations,
          color: _darkTeal,
          child: SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
              // Header Title + Settings Icon
              Padding(
                padding: const EdgeInsets.only(left: 4, top: 8, bottom: 16),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      'My Account',
                      style: GoogleFonts.figtree(
                        fontSize: 26,
                        fontWeight: FontWeight.w800,
                        color: _headingColor,
                        letterSpacing: -0.5,
                      ),
                    ),
                    Container(
                      width: 44,
                      height: 44,
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: _cardBorder),
                      ),
                      child: IconButton(
                        icon: const Icon(Icons.settings_outlined, size: 22, color: _headingColor),
                        tooltip: 'Account Settings',
                        padding: EdgeInsets.zero,
                        onPressed: () => _openSettings(),
                      ),
                    ),
                  ],
                ),
              ),

              // Card 1: User Profile Header Card
              Container(
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  gradient: AppColors.buyerBrandGradient,
                  borderRadius: BorderRadius.circular(24),
                  boxShadow: [
                    BoxShadow(
                      color: AppColors.buyerDeepTeal.withValues(alpha: 0.20),
                      blurRadius: 10,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                child: isAuth
                    ? Row(
                        children: [
                          Container(
                            width: 58,
                            height: 58,
                            decoration: const BoxDecoration(
                              color: AppColors.accentGold,
                              shape: BoxShape.circle,
                            ),
                            alignment: Alignment.center,
                            child: Text(
                              user?['name'] != null && user!['name'].toString().isNotEmpty
                                  ? user['name'][0].toUpperCase()
                                  : (user?['username'] != null && user!['username'].toString().isNotEmpty
                                      ? user['username'][0].toUpperCase()
                                      : 'A'),
                              style: GoogleFonts.figtree(
                                fontSize: 24,
                                fontWeight: FontWeight.w800,
                                color: AppColors.buyerDeepTeal,
                              ),
                            ),
                          ),
                          const SizedBox(width: 14),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  user?['name'] ?? user?['username'] ?? 'User',
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: GoogleFonts.figtree(
                                    fontSize: 17,
                                    fontWeight: FontWeight.w800,
                                    color: Colors.white,
                                  ),
                                ),
                                const SizedBox(height: 2),
                                Text(
                                  user?['email'] ?? '',
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: GoogleFonts.figtree(
                                    fontSize: 13,
                                    color: Colors.white,
                                  ),
                                ),
                                const SizedBox(height: 6),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                                  decoration: BoxDecoration(
                                    color: AppColors.accentGold,
                                    borderRadius: BorderRadius.circular(10),
                                  ),
                                  child: Text(
                                    'Buyer',
                                    style: GoogleFonts.figtree(
                                      fontSize: 11,
                                      fontWeight: FontWeight.w700,
                                      color: AppColors.buyerDeepTeal,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                          Container(
                            width: 44,
                            height: 44,
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(color: _cardBorder),
                            ),
                            child: IconButton(
                              icon: const Icon(Icons.edit_outlined, size: 20, color: AppColors.buyerDeepTeal),
                              tooltip: 'Edit Profile',
                              padding: EdgeInsets.zero,
                              onPressed: () => _openSettings(initialPage: 'profile'),
                            ),
                          ),
                        ],
                      )
                    : Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Welcome to Alona',
                            style: GoogleFonts.figtree(fontSize: 17, fontWeight: FontWeight.w800, color: Colors.white),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            'Log in or register to place orders, track shipments, and claim coupons.',
                            style: GoogleFonts.figtree(fontSize: 13, color: Colors.white),
                          ),
                          const SizedBox(height: 16),
                          AlonaButton(
                            text: 'Log In / Register',
                            variant: ButtonVariant.secondary,
                            onPressed: () {
                              Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (_) => BuyerLoginScreen(authState: widget.authState),
                                ),
                              );
                            },
                          ),
                        ],
                      ),
              ),

              const SizedBox(height: 16),

              // Card 2: My Purchases Card
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(24),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.02),
                      blurRadius: 10,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                child: Column(
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          'My Purchases',
                          style: GoogleFonts.figtree(
                            fontSize: 16,
                            fontWeight: FontWeight.w800,
                            color: _headingColor,
                          ),
                        ),
                        GestureDetector(
                          onTap: () {
                            Navigator.push(
                              context,
                              MaterialPageRoute(builder: (_) => const OrderListScreen(initialTab: 'all')),
                            );
                          },
                          child: Text(
                            'View all orders',
                            style: GoogleFonts.figtree(
                              fontSize: 13,
                              fontWeight: FontWeight.w700,
                              color: _darkTeal,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 18),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        _orderStatusItem('To Pay', Icons.credit_card_outlined, () {
                          Navigator.push(context, MaterialPageRoute(builder: (_) => const OrderListScreen(initialTab: 'to_pay')));
                        }),
                        _orderStatusItem('To Ship', Icons.inventory_2_outlined, () {
                          Navigator.push(context, MaterialPageRoute(builder: (_) => const OrderListScreen(initialTab: 'to_ship')));
                        }),
                        _orderStatusItem('To Receive', Icons.local_shipping_outlined, () {
                          Navigator.push(context, MaterialPageRoute(builder: (_) => const OrderListScreen(initialTab: 'to_receive')));
                        }),
                        _orderStatusItem('Completed', Icons.check_circle_outlined, () {
                          Navigator.push(context, MaterialPageRoute(builder: (_) => const OrderListScreen(initialTab: 'completed')));
                        }),
                      ],
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 16),

              // Card 3: Menu List Card (Wishlist & Vouchers)
              Container(
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(24),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.02),
                      blurRadius: 10,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                clipBehavior: Clip.antiAlias,
                child: Column(
                  children: [
                    _menuTile(
                      icon: Icons.location_on_outlined,
                      title: 'Delivery Addresses',
                      onTap: () {
                        Navigator.push(context, MaterialPageRoute(builder: (_) => const AddressManagementScreen()));
                      },
                    ),
                    const Divider(height: 1, thickness: 1, color: Color(0xFFF2F6F6)),
                    _menuTile(
                      icon: Icons.favorite_border_rounded,
                      title: 'My Wishlist',
                      onTap: () {
                        Navigator.push(context, MaterialPageRoute(builder: (_) => const WishlistScreen()));
                      },
                    ),
                    const Divider(height: 1, thickness: 1, color: Color(0xFFF2F6F6)),
                    _menuTile(
                      icon: Icons.local_offer_outlined,
                      title: 'Vouchers & Coupons',
                      onTap: () {
                        Navigator.push(context, MaterialPageRoute(builder: (_) => const VouchersScreen()));
                      },
                    ),
                  ],
                ),
              ),

              // YOU MAY ALSO LIKE SECTION (Always visible)
              const SizedBox(height: 24),
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
              if (_isLoadingRecommended)
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 32),
                  child: Center(
                    child: CircularProgressIndicator(color: _darkTeal),
                  ),
                )
              else if (_recommendedProducts.isNotEmpty)
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
                      onReturn: _loadRecommendations,
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
                    border: Border.all(color: _cardBorder),
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
                          color: _subtitleColor,
                        ),
                      ),
                    ],
                  ),
                ),

              const SizedBox(height: 96),
            ],
          ),
        ),
      ),
      ),
    );
  }

  Widget _orderStatusItem(String label, IconData icon, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: Column(
        children: [
          Container(
            width: 58,
            height: 58,
            decoration: BoxDecoration(
              color: _lightTeal,
              borderRadius: BorderRadius.circular(18),
            ),
            child: Center(
              child: Icon(icon, color: _darkTeal, size: 24),
            ),
          ),
          const SizedBox(height: 8),
          Text(
            label,
            style: GoogleFonts.figtree(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: _headingColor,
            ),
          ),
        ],
      ),
    );
  }

  Widget _menuTile({
    required IconData icon,
    required String title,
    String? subtitle,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        child: Row(
          children: [
            Container(
              width: 44,
              height: 44,
              decoration: BoxDecoration(
                color: _lightTeal,
                borderRadius: BorderRadius.circular(14),
              ),
              child: Center(
                child: Icon(icon, color: _darkTeal, size: 22),
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: GoogleFonts.figtree(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                      color: _headingColor,
                    ),
                  ),
                  if (subtitle != null) ...[
                    const SizedBox(height: 2),
                    Text(
                      subtitle,
                      style: GoogleFonts.figtree(
                        fontSize: 12,
                        color: _subtitleColor,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const Icon(
              Icons.chevron_right_rounded,
              color: Color(0xFF94A3B8),
              size: 20,
            ),
          ],
        ),
      ),
    );
  }
}
