import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/core/network/api_client.dart';
import 'package:alona_mobile/core/services/auth_state.dart';
import 'package:alona_mobile/features/buyer/services/buyer_service.dart';
import 'package:alona_mobile/features/buyer/presentation/home/buyer_home_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/notifications/notifications_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/products/product_list_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/profile/buyer_profile_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/wishlist/wishlist_screen.dart';

class BuyerMainScreen extends StatefulWidget {
  final AuthState authState;

  const BuyerMainScreen({super.key, required this.authState});

  @override
  State<BuyerMainScreen> createState() => _BuyerMainScreenState();
}

class _BuyerMainScreenState extends State<BuyerMainScreen> {
  int _currentIndex = 0;
  int _notificationsRevision = 0;
  int _unreadNotificationsCount = 0;

  @override
  void initState() {
    super.initState();
    widget.authState.addListener(_onAuthChanged);
    _refreshUnreadNotificationsCount();
  }

  @override
  void dispose() {
    widget.authState.removeListener(_onAuthChanged);
    super.dispose();
  }

  void _onAuthChanged() {
    _refreshUnreadNotificationsCount();
  }

  Future<void> _refreshUnreadNotificationsCount() async {
    if (!ApiClient.isAuthenticated) {
      if (mounted && _unreadNotificationsCount != 0) {
        setState(() => _unreadNotificationsCount = 0);
      }
      return;
    }

    try {
      final res = await BuyerService.getNotifications();
      if (!mounted) return;
      final list = res['notifications'] as List<dynamic>? ?? [];
      final count = (res['unread_count'] as num?)?.toInt() ??
          list.where((n) => n['read_at'] == null).length;
      if (_unreadNotificationsCount != count) {
        setState(() => _unreadNotificationsCount = count);
      }
    } catch (_) {}
  }

  Widget _buildNotificationTabIcon(IconData iconData) {
    return Stack(
      clipBehavior: Clip.none,
      children: [
        Icon(iconData),
        if (_unreadNotificationsCount > 0)
          Positioned(
            right: -6,
            top: -4,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
              constraints: const BoxConstraints(minWidth: 16, minHeight: 16),
              decoration: BoxDecoration(
                color: const Color(0xFFDC2626),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: Colors.white, width: 1.5),
              ),
              alignment: Alignment.center,
              child: Text(
                _unreadNotificationsCount > 99
                    ? '99+'
                    : '$_unreadNotificationsCount',
                style: GoogleFonts.figtree(
                  color: Colors.white,
                  fontSize: 9,
                  fontWeight: FontWeight.w800,
                  height: 1.0,
                ),
              ),
            ),
          ),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    final screens = [
      BuyerHomeScreen(authState: widget.authState),
      const ProductListScreen(),
      WishlistScreen(onBrowseProducts: () => setState(() => _currentIndex = 1)),
      NotificationsScreen(
        key: ValueKey(_notificationsRevision),
        onUnreadCountChanged: (count) {
          if (mounted && _unreadNotificationsCount != count) {
            setState(() => _unreadNotificationsCount = count);
          }
        },
      ),
      BuyerProfileScreen(authState: widget.authState),
    ];

    return Scaffold(
      extendBody: true,
      body: IndexedStack(index: _currentIndex, children: screens),
      bottomNavigationBar: SafeArea(
        child: Container(
          margin: const EdgeInsets.fromLTRB(16, 0, 16, 12),
          decoration: BoxDecoration(
            color: AppColors.white,
            borderRadius: BorderRadius.circular(32),
            border: Border.all(color: AppColors.border, width: 1.2),
            boxShadow: [
              BoxShadow(
                color: AppColors.alonaTeal.withValues(alpha: 0.10),
                blurRadius: 20,
                offset: const Offset(0, 6),
              ),
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.04),
                blurRadius: 8,
                offset: const Offset(0, 2),
              ),
            ],
          ),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(32),
            child: BottomNavigationBar(
              currentIndex: _currentIndex,
              onTap: (index) {
                setState(() {
                  _currentIndex = index;
                  if (index == 3) _notificationsRevision++;
                });
                _refreshUnreadNotificationsCount();
              },
              type: BottomNavigationBarType.fixed,
              backgroundColor: AppColors.white,
              selectedItemColor: AppColors.alonaTeal,
              unselectedItemColor: AppColors.mutedText,
              selectedLabelStyle: GoogleFonts.figtree(
                fontWeight: FontWeight.w700,
                fontSize: 11,
              ),
              unselectedLabelStyle: GoogleFonts.figtree(
                fontWeight: FontWeight.w500,
                fontSize: 11,
              ),
              elevation: 0,
              items: [
                const BottomNavigationBarItem(
                  icon: Icon(Icons.home_outlined),
                  activeIcon: Icon(Icons.home_rounded),
                  label: 'Home',
                ),
                const BottomNavigationBarItem(
                  icon: Icon(Icons.grid_view_outlined),
                  activeIcon: Icon(Icons.grid_view_rounded),
                  label: 'Shop',
                ),
                const BottomNavigationBarItem(
                  icon: Icon(Icons.favorite_border_rounded),
                  activeIcon: Icon(Icons.favorite_rounded),
                  label: 'Wishlist',
                ),
                BottomNavigationBarItem(
                  icon: _buildNotificationTabIcon(Icons.notifications_none_rounded),
                  activeIcon: _buildNotificationTabIcon(Icons.notifications_rounded),
                  label: 'Notifications',
                ),
                const BottomNavigationBarItem(
                  icon: Icon(Icons.person_outline_rounded),
                  activeIcon: Icon(Icons.person_rounded),
                  label: 'Account',
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
