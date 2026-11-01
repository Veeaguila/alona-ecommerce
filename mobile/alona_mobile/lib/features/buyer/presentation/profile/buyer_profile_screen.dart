import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/api_config.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/core/services/auth_state.dart';
import 'package:alona_mobile/shared/widgets/alona_button.dart';
import 'package:alona_mobile/shared/widgets/alona_text_field.dart';
import 'package:alona_mobile/features/logistics/presentation/logistics_main_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/auth/buyer_login_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/orders/order_list_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/vouchers/vouchers_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/wishlist/wishlist_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/messages/buyer_conversations_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/profile/address_management_screen.dart';

class BuyerProfileScreen extends StatefulWidget {
  final AuthState authState;

  const BuyerProfileScreen({super.key, required this.authState});

  @override
  State<BuyerProfileScreen> createState() => _BuyerProfileScreenState();
}

class _BuyerProfileScreenState extends State<BuyerProfileScreen> {
  static const Color _bgScaffold = Color(0xFFF2F6F6);
  static const Color _darkTeal = Color(0xFF087F8C);
  static const Color _lightTeal = Color(0xFFE3F3F1);
  static const Color _cardBorder = Color(0xFFE2EBE9);
  static const Color _headingColor = Color(0xFF132228);
  static const Color _subtitleColor = Color(0xFF6B7C7C);
  static const Color _dangerColor = Color(0xFFB91C1C);

  void _showServerSettingsDialog() {
    final controller = TextEditingController(text: ApiConfig.baseUrl);
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text('Server API Settings', style: GoogleFonts.figtree(fontWeight: FontWeight.w700)),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Set the Laravel backend base URL for API connections.',
              style: GoogleFonts.figtree(fontSize: 13, color: AppColors.textMuted),
            ),
            const SizedBox(height: 12),
            AlonaTextField(
              label: 'Base URL',
              controller: controller,
              hint: 'http://10.0.2.2:8000',
            ),
            const SizedBox(height: 8),
            Text(
              '• Android Emulator: http://10.0.2.2:8000\n• Physical Device: http://<your-pc-ip>:8000\n• Live Production: https://shopalona.online',
              style: GoogleFonts.figtree(fontSize: 11, color: AppColors.textLight, height: 1.4),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () async {
              try { await ApiConfig.setBaseUrl(controller.text); }
              on FormatException catch (error) {
                if (ctx.mounted) ScaffoldMessenger.of(ctx).showSnackBar(SnackBar(content:Text(error.message)));
                return;
              }
              if (ctx.mounted) {
                Navigator.pop(ctx);
              }
              if (!mounted) return;
              setState(() {});
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(content: Text('API URL updated to: ${ApiConfig.baseUrl}')),
              );
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: _darkTeal,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              elevation: 0,
            ),
            child: const Text('Save URL'),
          ),
        ],
      ),
    );
  }

  void _showEditProfileDialog(Map<String, dynamic>? user) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text('Account Details', style: GoogleFonts.figtree(fontWeight: FontWeight.w700)),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Username: ${user?['name'] ?? user?['username'] ?? 'Buyer'}', style: GoogleFonts.figtree(fontSize: 14)),
            const SizedBox(height: 6),
            Text('Email: ${user?['email'] ?? 'Not set'}', style: GoogleFonts.figtree(fontSize: 14)),
            const SizedBox(height: 6),
            Text('Role: Buyer', style: GoogleFonts.figtree(fontSize: 14, color: _darkTeal, fontWeight: FontWeight.w700)),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Close'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final isAuth = widget.authState.isBuyerAuthenticated;
    final user = widget.authState.buyerUser;

    return Scaffold(
      backgroundColor: _bgScaffold,
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header Title
              Padding(
                padding: const EdgeInsets.only(left: 4, bottom: 16),
                child: Text(
                  'My Account',
                  style: GoogleFonts.figtree(
                    fontSize: 26,
                    fontWeight: FontWeight.w800,
                    color: _headingColor,
                    letterSpacing: -0.5,
                  ),
                ),
              ),

              // Card 1: User Profile Header Card
              Container(
                padding: const EdgeInsets.all(18),
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
                child: isAuth
                    ? Row(
                        children: [
                          Container(
                            width: 58,
                            height: 58,
                            decoration: const BoxDecoration(
                              color: _darkTeal,
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
                                color: Colors.white,
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
                                    color: _headingColor,
                                  ),
                                ),
                                const SizedBox(height: 2),
                                Text(
                                  user?['email'] ?? '',
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: GoogleFonts.figtree(
                                    fontSize: 13,
                                    color: _subtitleColor,
                                  ),
                                ),
                                const SizedBox(height: 6),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                                  decoration: BoxDecoration(
                                    color: _lightTeal,
                                    borderRadius: BorderRadius.circular(10),
                                  ),
                                  child: Text(
                                    'Buyer',
                                    style: GoogleFonts.figtree(
                                      fontSize: 11,
                                      fontWeight: FontWeight.w700,
                                      color: _darkTeal,
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
                              icon: const Icon(Icons.edit_outlined, size: 20, color: _darkTeal),
                              padding: EdgeInsets.zero,
                              onPressed: () => _showEditProfileDialog(user),
                            ),
                          ),
                        ],
                      )
                    : Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Welcome to Alona',
                            style: GoogleFonts.figtree(fontSize: 17, fontWeight: FontWeight.w800, color: _headingColor),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            'Log in or register to place orders, track shipments, and claim coupons.',
                            style: GoogleFonts.figtree(fontSize: 13, color: _subtitleColor),
                          ),
                          const SizedBox(height: 16),
                          AlonaButton(
                            text: 'Log In / Register',
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

              // Card 3: Menu List Card
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
                    const Divider(height: 1, thickness: 1, color: Color(0xFFF2F6F6)),
                    _menuTile(
                      icon: Icons.chat_bubble_outline_rounded,
                      title: 'Messages & Support',
                      subtitle: 'Chat with sellers & track inquiries',
                      onTap: () {
                        Navigator.push(context, MaterialPageRoute(builder: (_) => const BuyerConversationsScreen()));
                      },
                    ),
                    const Divider(height: 1, thickness: 1, color: Color(0xFFF2F6F6)),
                    _menuTile(
                      icon: Icons.dns_outlined,
                      title: 'Server API Configuration',
                      subtitle: ApiConfig.baseUrl,
                      onTap: _showServerSettingsDialog,
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 16),

              // Card 4: Logistics Portal Switcher Card
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
                decoration: BoxDecoration(
                  color: _darkTeal,
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Row(
                  children: [
                    Container(
                      width: 46,
                      height: 46,
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.15),
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: const Center(
                        child: Icon(Icons.swap_horiz_rounded, color: Colors.white, size: 24),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Logistics Portal',
                            style: GoogleFonts.figtree(
                              fontSize: 15,
                              fontWeight: FontWeight.w800,
                              color: Colors.white,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            'Sorting center, parcels & riders',
                            style: GoogleFonts.figtree(
                              fontSize: 12,
                              color: Colors.white.withValues(alpha: 0.8),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 10),
                    Material(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(16),
                      child: InkWell(
                        borderRadius: BorderRadius.circular(16),
                        onTap: () async {
                          await widget.authState.switchModule('logistics');
                          if (!context.mounted) return;
                          Navigator.pushAndRemoveUntil(
                            context,
                            MaterialPageRoute(builder: (_) => LogisticsMainScreen(authState: widget.authState)),
                            (route) => false,
                          );
                        },
                        child: Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 9),
                          child: Text(
                            'Switch',
                            style: GoogleFonts.figtree(
                              color: _darkTeal,
                              fontWeight: FontWeight.w800,
                              fontSize: 13,
                            ),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              // Sign Out Section
              if (isAuth) ...[
                const SizedBox(height: 24),
                Center(
                  child: InkWell(
                    borderRadius: BorderRadius.circular(8),
                    onTap: () async {
                      await widget.authState.clearBuyerSession();
                      if (!context.mounted) return;
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Successfully logged out.')),
                      );
                    },
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(Icons.logout_rounded, color: _dangerColor, size: 20),
                          const SizedBox(width: 8),
                          Text(
                            'Sign Out',
                            style: GoogleFonts.figtree(
                              fontSize: 15,
                              fontWeight: FontWeight.w700,
                              color: _dangerColor,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ],
              const SizedBox(height: 24),
            ],
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
