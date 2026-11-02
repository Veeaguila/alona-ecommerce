import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/core/network/api_client.dart';
import 'package:alona_mobile/core/utils/date_formatter.dart';
import 'package:alona_mobile/shared/widgets/empty_state_view.dart';
import 'package:alona_mobile/features/buyer/services/buyer_service.dart';
import 'package:alona_mobile/features/buyer/presentation/cart/cart_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/messages/buyer_conversations_screen.dart';

class NotificationsScreen extends StatefulWidget {
  final ValueChanged<int>? onUnreadCountChanged;

  const NotificationsScreen({super.key, this.onUnreadCountChanged});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  final TextEditingController _searchController = TextEditingController();
  String _searchQuery = '';
  bool _isLoading = true;
  String? _error;
  List<dynamic> _notifications = [];
  int _unreadCount = 0;

  @override
  void initState() {
    super.initState();
    _loadNotifications();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  List<dynamic> get _filteredNotifications {
    if (_searchQuery.trim().isEmpty) return _notifications;
    final q = _searchQuery.toLowerCase().trim();
    return _notifications.where((n) {
      final title = (n['title'] ?? '').toString().toLowerCase();
      final message = (n['message'] ?? '').toString().toLowerCase();
      return title.contains(q) || message.contains(q);
    }).toList();
  }

  Future<void> _loadNotifications() async {
    if (!ApiClient.isAuthenticated) {
      if (!mounted) return;
      setState(() {
        _notifications = [];
        _unreadCount = 0;
        _isLoading = false;
        _error = null;
      });
      widget.onUnreadCountChanged?.call(0);
      return;
    }

    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final res = await BuyerService.getNotifications();
      if (!mounted) return;
      final list = res['notifications'] as List<dynamic>? ?? [];
      final unread = (res['unread_count'] as num?)?.toInt() ??
          list.where((n) => n['read_at'] == null).length;
      setState(() {
        _notifications = list;
        _unreadCount = unread;
        _isLoading = false;
      });
      widget.onUnreadCountChanged?.call(unread);
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _isLoading = false;
      });
    }
  }

  Future<void> _markAllRead() async {
    try {
      await BuyerService.markAllNotificationsRead();
      await _loadNotifications();
    } catch (_) {}
  }

  Future<void> _markSingleRead(dynamic notification) async {
    if (notification['read_at'] != null) return;
    final id = notification['id'];
    if (id is int) {
      try {
        await BuyerService.markNotificationRead(id);
        await _loadNotifications();
      } catch (_) {}
    }
  }

  @override
  Widget build(BuildContext context) {
    final canPop = Navigator.canPop(context);
    final filtered = _filteredNotifications;

    return Scaffold(
      backgroundColor: AppColors.background,
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
                          hintText: 'Search notifications...',
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
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 16, 20, 8),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Row(
                  children: [
                    Text(
                      'Notifications',
                      style: GoogleFonts.figtree(
                        fontSize: 22,
                        fontWeight: FontWeight.w800,
                        color: AppColors.textPrimary,
                        letterSpacing: -0.5,
                      ),
                    ),
                    if (_unreadCount > 0) ...[
                      const SizedBox(width: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                        decoration: BoxDecoration(
                          color: const Color(0xFFE3F3F1),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Text(
                          '$_unreadCount new',
                          style: GoogleFonts.figtree(
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                            color: AppColors.primary,
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
                if (_unreadCount > 0)
                  TextButton(
                    onPressed: _markAllRead,
                    child: Text(
                      'Mark all read',
                      style: GoogleFonts.figtree(
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        color: AppColors.primary,
                      ),
                    ),
                  ),
              ],
            ),
          ),
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
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
                                onPressed: _loadNotifications,
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: AppColors.primary,
                                  foregroundColor: Colors.white,
                                ),
                                child: const Text('Retry'),
                              ),
                            ],
                          ),
                        ),
                      )
                    : filtered.isEmpty
                        ? Padding(
                            padding: const EdgeInsets.fromLTRB(24, 16, 24, 96),
                            child: EmptyStateView(
                              icon: Icons.notifications_none_outlined,
                              title: _searchQuery.isNotEmpty
                                  ? 'No matching notifications'
                                  : 'No notifications',
                              message: _searchQuery.isNotEmpty
                                  ? 'No notifications match "$_searchQuery".'
                                  : 'You are all caught up! Order updates and news will appear here.',
                            ),
                          )
                        : RefreshIndicator(
                            onRefresh: _loadNotifications,
                            color: AppColors.primary,
                            child: ListView.separated(
                              padding: const EdgeInsets.fromLTRB(16, 8, 16, 96),
                              itemCount: filtered.length,
                              separatorBuilder: (_, _) => const SizedBox(height: 10),
                              itemBuilder: (context, index) {
                                final n = filtered[index];
                                final isUnread = n['read_at'] == null;
                                final title = n['title'] ?? 'Notification';
                                final message = n['message'] ?? '';
                                final time = n['created_at'] != null
                                    ? DateFormatter.timeAgo(DateTime.tryParse(n['created_at']))
                                    : '';

                                return GestureDetector(
                                  onTap: () => _markSingleRead(n),
                                  child: Container(
                                    padding: const EdgeInsets.all(14),
                                    decoration: BoxDecoration(
                                      color: isUnread ? const Color(0xFFF0FDF4) : Colors.white,
                                      borderRadius: BorderRadius.circular(14),
                                      border: Border.all(
                                        color: isUnread ? const Color(0xFFBBF7D0) : AppColors.border,
                                      ),
                                    ),
                                    child: Row(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Container(
                                          width: 40,
                                          height: 40,
                                          decoration: BoxDecoration(
                                            color: isUnread ? AppColors.primaryLight : AppColors.surfaceAlt,
                                            shape: BoxShape.circle,
                                          ),
                                          child: const Center(
                                            child: Icon(
                                              Icons.notifications_active_outlined,
                                              color: AppColors.primary,
                                              size: 20,
                                            ),
                                          ),
                                        ),
                                        const SizedBox(width: 12),
                                        Expanded(
                                          child: Column(
                                            crossAxisAlignment: CrossAxisAlignment.start,
                                            children: [
                                              Row(
                                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                                children: [
                                                  Expanded(
                                                    child: Text(
                                                      title,
                                                      style: GoogleFonts.figtree(
                                                        fontSize: 14,
                                                        fontWeight: isUnread ? FontWeight.w800 : FontWeight.w600,
                                                        color: AppColors.textPrimary,
                                                      ),
                                                    ),
                                                  ),
                                                  Text(
                                                    time,
                                                    style: GoogleFonts.figtree(
                                                      fontSize: 11,
                                                      color: AppColors.textLight,
                                                    ),
                                                  ),
                                                ],
                                              ),
                                              const SizedBox(height: 4),
                                              Text(
                                                message,
                                                style: GoogleFonts.figtree(
                                                  fontSize: 13,
                                                  color: AppColors.textSecondary,
                                                  height: 1.3,
                                                ),
                                              ),
                                            ],
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                );
                              },
                            ),
                          ),
          ),
        ],
      ),
    );
  }
}
