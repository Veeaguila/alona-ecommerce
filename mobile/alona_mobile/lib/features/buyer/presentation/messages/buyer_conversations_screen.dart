import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/core/network/api_client.dart';
import 'package:alona_mobile/core/services/auth_state.dart';
import 'package:alona_mobile/shared/widgets/network_image_box.dart';
import 'package:alona_mobile/features/buyer/models/buyer_models.dart';
import 'package:alona_mobile/features/buyer/services/buyer_service.dart';
import 'package:alona_mobile/features/buyer/presentation/auth/buyer_login_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/messages/buyer_chat_detail_screen.dart';

class BuyerConversationsScreen extends StatefulWidget {
  const BuyerConversationsScreen({super.key});

  @override
  State<BuyerConversationsScreen> createState() => _BuyerConversationsScreenState();
}

class _BuyerConversationsScreenState extends State<BuyerConversationsScreen> {
  List<ConversationModel> _conversations = [];
  bool _isLoading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadConversations();
  }

  Future<void> _loadConversations() async {
    if (!ApiClient.isAuthenticated) {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
      return;
    }

    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final list = await BuyerService.getConversations();
      if (!mounted) return;
      setState(() {
        _conversations = list;
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
    if (!ApiClient.isAuthenticated) {
      return Scaffold(
        backgroundColor: AppColors.background,
        appBar: AppBar(
          leading: IconButton(
            icon: const Icon(Icons.arrow_back, color: AppColors.textPrimary),
            onPressed: () => Navigator.of(context).pop(),
          ),
          title: Text(
            'Chats',
            style: GoogleFonts.figtree(
              fontSize: 20,
              fontWeight: FontWeight.w700,
              color: AppColors.textPrimary,
            ),
          ),
          backgroundColor: AppColors.white,
          elevation: 0,
          bottom: const PreferredSize(
            preferredSize: Size.fromHeight(1),
            child: Divider(height: 1, thickness: 1, color: AppColors.border),
          ),
        ),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(28),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                  width: 76,
                  height: 76,
                  decoration: const BoxDecoration(
                    color: AppColors.lightTeal,
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    Icons.chat_bubble_outline_rounded,
                    color: AppColors.alonaTeal,
                    size: 38,
                  ),
                ),
                const SizedBox(height: 20),
                Text(
                  'Sign In to Chat',
                  style: GoogleFonts.figtree(
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  'Log in to communicate with sellers, ask product questions, and track orders.',
                  textAlign: TextAlign.center,
                  style: GoogleFonts.figtree(
                    fontSize: 13,
                    color: AppColors.mutedText,
                  ),
                ),
                const SizedBox(height: 24),
                ElevatedButton(
                  onPressed: () async {
                    final nav = Navigator.of(context);
                    final auth = AuthState();
                    await auth.init();
                    if (!mounted) return;
                    final loggedIn = await nav.push<bool>(
                      MaterialPageRoute(builder: (_) => BuyerLoginScreen(authState: auth)),
                    );
                    if (loggedIn == true && mounted) _loadConversations();
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.alonaTeal,
                    foregroundColor: AppColors.white,
                    padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 12),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
                    elevation: 0,
                  ),
                  child: Text(
                    'Sign In Now',
                    style: GoogleFonts.figtree(fontWeight: FontWeight.w600, fontSize: 14),
                  ),
                ),
              ],
            ),
          ),
        ),
      );
    }

    return Scaffold(
      backgroundColor: AppColors.white,
      appBar: AppBar(
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppColors.textPrimary),
          onPressed: () => Navigator.of(context).pop(),
        ),
        title: Text(
          'Chats',
          style: GoogleFonts.figtree(
            fontSize: 20,
            fontWeight: FontWeight.w700,
            color: AppColors.textPrimary,
          ),
        ),
        backgroundColor: AppColors.white,
        elevation: 0,
        bottom: const PreferredSize(
          preferredSize: Size.fromHeight(1),
          child: Divider(height: 1, thickness: 0.8, color: AppColors.border),
        ),
      ),
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
                          onPressed: _loadConversations,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.alonaTeal,
                            foregroundColor: AppColors.white,
                          ),
                          child: const Text('Retry'),
                        ),
                      ],
                    ),
                  ),
                )
              : _conversations.isEmpty
                  ? RefreshIndicator(
                      onRefresh: _loadConversations,
                      color: AppColors.alonaTeal,
                      child: ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        children: [
                          SizedBox(height: MediaQuery.of(context).size.height * 0.25),
                          Center(
                            child: Column(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Container(
                                  width: 72,
                                  height: 72,
                                  decoration: const BoxDecoration(
                                    color: AppColors.lightTeal,
                                    shape: BoxShape.circle,
                                  ),
                                  child: const Icon(
                                    Icons.chat_bubble_outline_rounded,
                                    color: AppColors.alonaTeal,
                                    size: 36,
                                  ),
                                ),
                                const SizedBox(height: 18),
                                Text(
                                  'No conversations yet',
                                  style: GoogleFonts.figtree(
                                    fontSize: 17,
                                    fontWeight: FontWeight.w700,
                                    color: AppColors.textPrimary,
                                  ),
                                ),
                                const SizedBox(height: 6),
                                Padding(
                                  padding: const EdgeInsets.symmetric(horizontal: 40),
                                  child: Text(
                                    'When you message sellers about products or orders, your chats will appear here.',
                                    textAlign: TextAlign.center,
                                    style: GoogleFonts.figtree(fontSize: 13, color: AppColors.mutedText),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    )
                  : RefreshIndicator(
                      onRefresh: _loadConversations,
                      color: AppColors.alonaTeal,
                      child: ListView.separated(
                        itemCount: _conversations.length,
                        separatorBuilder: (_, index) => const Divider(
                          height: 1,
                          thickness: 0.6,
                          indent: 76,
                          color: AppColors.border,
                        ),
                        itemBuilder: (context, index) {
                          final conv = _conversations[index];
                          return _buildConversationTile(conv, index);
                        },
                      ),
                    ),
    );
  }

  Widget _buildConversationTile(ConversationModel conv, int index) {
    final hasUnread = conv.unreadCount > 0;

    final isMall = conv.sellerStoreName != null && conv.sellerStoreName!.isNotEmpty;
    final String? badgeText = isMall ? 'Official' : null;
    const Color badgeBg = AppColors.alonaTeal;

    return InkWell(
      onTap: () async {
        await Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => BuyerChatDetailScreen(
              conversationId: conv.id,
              title: conv.displayName,
              sellerId: conv.sellerId,
              sellerAvatarUrl: conv.sellerAvatarUrl,
              sellerBadge: badgeText,
              productName: conv.productName,
              productImage: conv.productImagePath,
              productPrice: conv.productPrice,
            ),
          ),
        );
        _loadConversations();
      },
      child: Container(
        color: AppColors.white,
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            // Avatar Stack with pill badge at bottom
            SizedBox(
              width: 52,
              height: 52,
              child: Stack(
                clipBehavior: Clip.none,
                alignment: Alignment.center,
                children: [
                  Container(
                    width: 50,
                    height: 50,
                    decoration: BoxDecoration(
                      color: AppColors.lightTeal,
                      shape: BoxShape.circle,
                      border: Border.all(color: AppColors.border, width: 0.8),
                    ),
                    child: ClipOval(
                      child: conv.sellerAvatarUrl != null && conv.sellerAvatarUrl!.isNotEmpty
                          ? NetworkImageBox(imageUrl: conv.sellerAvatarUrl, width: 50, height: 50, fit: BoxFit.cover)
                          : Center(
                              child: Text(
                                conv.displayName.isNotEmpty ? conv.displayName[0].toUpperCase() : 'S',
                                style: GoogleFonts.figtree(
                                  fontSize: 19,
                                  fontWeight: FontWeight.w700,
                                  color: AppColors.alonaTeal,
                                ),
                              ),
                            ),
                    ),
                  ),
                  if (badgeText != null)
                    Positioned(
                      bottom: -2,
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
                        decoration: BoxDecoration(
                          color: badgeBg,
                          borderRadius: BorderRadius.circular(4),
                          border: Border.all(color: AppColors.white, width: 1),
                        ),
                        child: Text(
                          badgeText,
                          style: GoogleFonts.figtree(
                            fontSize: 8.5,
                            fontWeight: FontWeight.w800,
                            color: AppColors.white,
                            height: 1.1,
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ),
            const SizedBox(width: 14),

            // Store Name & Message Preview
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    crossAxisAlignment: CrossAxisAlignment.baseline,
                    textBaseline: TextBaseline.alphabetic,
                    children: [
                      Flexible(
                        child: Text(
                          conv.displayName,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: GoogleFonts.figtree(
                            fontSize: 15,
                            fontWeight: FontWeight.w700,
                            color: AppColors.textPrimary,
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      if (conv.lastMessageAt != null)
                        Text(
                          _formatDate(conv.lastMessageAt!),
                          style: GoogleFonts.figtree(
                            fontSize: 11,
                            fontWeight: FontWeight.w500,
                            color: AppColors.mutedText,
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(height: 4),

                  // Message Preview & Unread Badge Counter
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          conv.lastMessage != null && conv.lastMessage!.isNotEmpty
                              ? conv.lastMessage!
                              : (conv.productName != null
                                  ? 'Inquired about ${conv.productName}'
                                  : 'No messages yet'),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: GoogleFonts.figtree(
                            fontSize: 13,
                            fontWeight: FontWeight.w400,
                            color: AppColors.mutedText,
                          ),
                        ),
                      ),
                      if (hasUnread) ...[
                        const SizedBox(width: 8),
                        Container(
                          width: 19,
                          height: 19,
                          decoration: const BoxDecoration(
                            color: AppColors.sale,
                            shape: BoxShape.circle,
                          ),
                          alignment: Alignment.center,
                          child: Text(
                            conv.unreadCount > 99 ? '99+' : '${conv.unreadCount}',
                            style: GoogleFonts.figtree(
                              fontSize: 10.5,
                              fontWeight: FontWeight.w800,
                              color: AppColors.white,
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  String _formatDate(String rawDate) {
    try {
      final dt = DateTime.parse(rawDate).toLocal();
      final day = dt.day.toString().padLeft(2, '0');
      final month = dt.month.toString().padLeft(2, '0');
      return '$day/$month';
    } catch (_) {
      return '';
    }
  }
}
