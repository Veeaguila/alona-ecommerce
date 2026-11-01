import 'dart:async';

import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/core/utils/currency_formatter.dart';
import 'package:alona_mobile/shared/widgets/network_image_box.dart';
import 'package:alona_mobile/features/buyer/models/buyer_models.dart';
import 'package:alona_mobile/features/buyer/services/buyer_service.dart';
import 'package:alona_mobile/features/buyer/presentation/store/buyer_store_screen.dart';

class BuyerChatDetailScreen extends StatefulWidget {
  final int conversationId;
  final String title;
  final int? sellerId;
  final String? sellerAvatarUrl;
  final String? sellerBadge;
  final String? productName;
  final String? productImage;
  final double? productPrice;

  const BuyerChatDetailScreen({
    super.key,
    required this.conversationId,
    required this.title,
    this.sellerId,
    this.sellerAvatarUrl,
    this.sellerBadge,
    this.productName,
    this.productImage,
    this.productPrice,
  });

  @override
  State<BuyerChatDetailScreen> createState() => _BuyerChatDetailScreenState();
}

class _BuyerChatDetailScreenState extends State<BuyerChatDetailScreen>
    with WidgetsBindingObserver {
  final TextEditingController _textController = TextEditingController();
  final ScrollController _scrollController = ScrollController();

  List<ConversationMessageModel> _messages = [];
  bool _isLoading = true;
  bool _isConnecting = false;
  bool _isSending = false;
  String? _error;
  Timer? _pollTimer;
  bool _loadingMessages = false, _paused = false;
  int _messageRevision = 0;

  int _buyerId = 0;
  int _sellerId = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _textController.addListener(_onDraftChanged);
    if (widget.sellerId != null && widget.sellerId! > 0) {
      _sellerId = widget.sellerId!;
    }
    _loadMessages(initial: true);
    _startPolling();
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    _textController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _onDraftChanged() {
    if (mounted) setState(() {});
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _paused = state != AppLifecycleState.resumed;
    if (_paused) {
      _pollTimer?.cancel();
      _messageRevision++;
    } else {
      _loadMessages();
      _startPolling();
    }
  }

  void _startPolling() {
    _pollTimer?.cancel();
    _pollTimer = Timer.periodic(const Duration(seconds: 4), (_) {
      if (mounted && !_paused && (ModalRoute.of(context)?.isCurrent ?? true)) {
        _loadMessages(initial: false);
      }
    });
  }

  Future<void> _loadMessages({bool initial = false}) async {
    if (!mounted || _loadingMessages || _isSending || _paused) return;
    _loadingMessages = true;
    final revision = ++_messageRevision;
    if (initial) {
      setState(() {
        _isLoading = true;
        _isConnecting = true;
        _error = null;
      });
    }

    try {
      final res = await BuyerService.getConversation(widget.conversationId);
      final newMessages = res['messages'] as List<ConversationMessageModel>;
      final conv = res['conversation'] as ConversationModel?;

      if (!mounted || revision != _messageRevision) return;
      final hadFewerMessages = newMessages.length > _messages.length;

      setState(() {
        _messages = newMessages;
        if (conv != null) {
          _buyerId = conv.buyerId;
          _sellerId = conv.sellerId;
        }
        _isLoading = false;
        _error = null;
        _isConnecting = false;
      });

      if (initial || hadFewerMessages) {
        _scrollToBottom();
      }
    } catch (e) {
      if (!mounted || revision != _messageRevision) return;
      if (initial || _isLoading) {
        setState(() {
          _error = e.toString();
          _isLoading = false;
          _isConnecting = false;
        });
      }
    } finally {
      _loadingMessages = false;
    }
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted && _scrollController.hasClients) {
        _scrollController.animateTo(
          _scrollController.position.maxScrollExtent,
          duration: const Duration(milliseconds: 250),
          curve: Curves.easeOut,
        );
      }
    });
  }

  Future<void> _handleSend({String? explicitText, int? orderId}) async {
    final text = explicitText ?? _textController.text.trim();
    if ((text.isEmpty && orderId == null) ||
        _isSending ||
        _isLoading ||
        _error != null) {
      return;
    }
    _messageRevision++;

    setState(() => _isSending = true);
    if (explicitText == null) {
      _textController.clear();
    }

    try {
      final sent = await BuyerService.sendMessage(
        conversationId: widget.conversationId,
        body: text,
        orderId: orderId,
      );

      if (!mounted) return;
      setState(() {
        if (!_messages.any((message) => message.id == sent.id)) {
          _messages.add(sent);
        }
        _isSending = false;
      });
      _scrollToBottom();
    } catch (e) {
      if (!mounted) return;
      if (explicitText == null && _textController.text.isEmpty) {
        _textController.text = text;
      }
      setState(() => _isSending = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Failed to send: $e'),
          backgroundColor: AppColors.sale,
        ),
      );
    }
  }

  void _sendProductInquiry() {
    if (widget.productName != null) {
      final msg =
          'Hello, I have an inquiry about "${widget.productName}". Is this still available?';
      _handleSend(explicitText: msg);
    }
  }

  void _showQuickActionsSheet() {
    showModalBottomSheet(
      context: context,
      backgroundColor: AppColors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (ctx) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 16),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 36,
                  height: 4,
                  decoration: BoxDecoration(
                    color: AppColors.border,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
                const SizedBox(height: 14),
                Text(
                  'Quick Actions',
                  style: GoogleFonts.figtree(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                ),
                const SizedBox(height: 12),
                if (widget.productName != null)
                  ListTile(
                    leading: const Icon(
                      Icons.inventory_2_outlined,
                      color: AppColors.alonaTeal,
                    ),
                    title: Text(
                      'Inquire About Product',
                      style: GoogleFonts.figtree(fontSize: 14),
                    ),
                    subtitle: Text(
                      widget.productName!,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    onTap: () {
                      Navigator.pop(ctx);
                      _sendProductInquiry();
                    },
                  ),
                ListTile(
                  leading: const Icon(
                    Icons.local_shipping_outlined,
                    color: AppColors.alonaTeal,
                  ),
                  title: Text(
                    'Ask Shipping Status',
                    style: GoogleFonts.figtree(fontSize: 14),
                  ),
                  onTap: () {
                    Navigator.pop(ctx);
                    _handleSend(
                      explicitText: 'Hi, when will my parcel be shipped?',
                    );
                  },
                ),
                ListTile(
                  leading: const Icon(
                    Icons.verified_outlined,
                    color: AppColors.alonaTeal,
                  ),
                  title: Text(
                    'Ask about Warranty / Return',
                    style: GoogleFonts.figtree(fontSize: 14),
                  ),
                  onTap: () {
                    Navigator.pop(ctx);
                    _handleSend(
                      explicitText:
                          'Hi, what is the warranty policy for this item?',
                    );
                  },
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  void _showEmojiSheet() {
    final emojis = ['👋', '👍', '❤️', '😊', '🙏', '📦', '🚚', '✨', '🔥', '💯'];
    showModalBottomSheet(
      context: context,
      backgroundColor: AppColors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (ctx) {
        return SafeArea(
          child: Container(
            padding: const EdgeInsets.all(16),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Quick Reactions',
                  style: GoogleFonts.figtree(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                ),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 12,
                  runSpacing: 10,
                  children: emojis.map((e) {
                    return InkWell(
                      onTap: () {
                        Navigator.pop(ctx);
                        _textController.text = '${_textController.text}$e';
                      },
                      borderRadius: BorderRadius.circular(20),
                      child: Container(
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          color: AppColors.background,
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: AppColors.border),
                        ),
                        child: Text(e, style: const TextStyle(fontSize: 22)),
                      ),
                    );
                  }).toList(),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        elevation: 0.5,
        backgroundColor: AppColors.white,
        foregroundColor: AppColors.textPrimary,
        titleSpacing: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: AppColors.textPrimary),
          onPressed: () => Navigator.of(context).pop(),
        ),
        title: Row(
          children: [
            // Avatar Stack matching reference screenshot with tag badge
            SizedBox(
              width: 38,
              height: 38,
              child: Stack(
                clipBehavior: Clip.none,
                alignment: Alignment.center,
                children: [
                  Container(
                    width: 36,
                    height: 36,
                    decoration: BoxDecoration(
                      color: AppColors.lightTeal,
                      shape: BoxShape.circle,
                      border: Border.all(color: AppColors.border, width: 0.8),
                    ),
                    child: ClipOval(
                      child:
                          widget.sellerAvatarUrl != null &&
                              widget.sellerAvatarUrl!.isNotEmpty
                          ? NetworkImageBox(
                              imageUrl: widget.sellerAvatarUrl,
                              width: 36,
                              height: 36,
                              fit: BoxFit.cover,
                            )
                          : Center(
                              child: Text(
                                widget.title.isNotEmpty
                                    ? widget.title[0].toUpperCase()
                                    : 'S',
                                style: GoogleFonts.figtree(
                                  fontSize: 15,
                                  fontWeight: FontWeight.w700,
                                  color: AppColors.alonaTeal,
                                ),
                              ),
                            ),
                    ),
                  ),
                  if (widget.sellerBadge != null &&
                      widget.sellerBadge!.isNotEmpty)
                    Positioned(
                      bottom: -3,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 4,
                          vertical: 0.5,
                        ),
                        decoration: BoxDecoration(
                          color: AppColors.alonaTeal,
                          borderRadius: BorderRadius.circular(3),
                          border: Border.all(
                            color: AppColors.white,
                            width: 0.8,
                          ),
                        ),
                        child: Text(
                          widget.sellerBadge!,
                          style: GoogleFonts.figtree(
                            fontSize: 7.5,
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
            const SizedBox(width: 10),

            // Store Name & Online / Connecting status
            Expanded(
              child: InkWell(
                onTap: () {
                  if (_sellerId > 0) {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => BuyerStoreScreen(
                          sellerId: _sellerId,
                          storeName: widget.title,
                        ),
                      ),
                    );
                  }
                },
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      _isConnecting ? 'Connecting...' : widget.title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: GoogleFonts.figtree(
                        fontSize: 15,
                        fontWeight: FontWeight.w700,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    Row(
                      children: [
                        Container(
                          width: 6,
                          height: 6,
                          decoration: const BoxDecoration(
                            color: AppColors.success,
                            shape: BoxShape.circle,
                          ),
                        ),
                        const SizedBox(width: 4),
                        Text(
                          'Online',
                          style: GoogleFonts.figtree(
                            fontSize: 11,
                            fontWeight: FontWeight.w500,
                            color: AppColors.mutedText,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(
              Icons.storefront_outlined,
              color: AppColors.textPrimary,
            ),
            tooltip: 'Visit Store',
            onPressed: () {
              if (_sellerId > 0) {
                Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => BuyerStoreScreen(
                      sellerId: _sellerId,
                      storeName: widget.title,
                    ),
                  ),
                );
              } else {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text('Loading store profile...'),
                    duration: Duration(seconds: 1),
                  ),
                );
              }
            },
          ),
          IconButton(
            icon: const Icon(Icons.more_vert, color: AppColors.textPrimary),
            onPressed: _showQuickActionsSheet,
          ),
        ],
        bottom: const PreferredSize(
          preferredSize: Size.fromHeight(1),
          child: Divider(height: 1, thickness: 0.8, color: AppColors.border),
        ),
      ),
      body: Column(
        children: [
          // Sticky Product Header Banner (if referenced)
          if (widget.productName != null && widget.productName!.isNotEmpty)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
              decoration: const BoxDecoration(
                color: AppColors.white,
                border: Border(
                  bottom: BorderSide(color: AppColors.border, width: 0.8),
                ),
              ),
              child: Row(
                children: [
                  if (widget.productImage != null &&
                      widget.productImage!.isNotEmpty)
                    NetworkImageBox(
                      imageUrl: widget.productImage,
                      width: 44,
                      height: 44,
                      borderRadius: BorderRadius.circular(6),
                      fit: BoxFit.cover,
                    ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          widget.productName!,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: GoogleFonts.figtree(
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                            color: AppColors.textPrimary,
                          ),
                        ),
                        const SizedBox(height: 2),
                        if (widget.productPrice != null)
                          Text(
                            CurrencyFormatter.format(widget.productPrice!),
                            style: GoogleFonts.figtree(
                              fontSize: 12.5,
                              fontWeight: FontWeight.w700,
                              color: AppColors.alonaTeal,
                            ),
                          ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  OutlinedButton(
                    onPressed: _sendProductInquiry,
                    style: OutlinedButton.styleFrom(
                      foregroundColor: AppColors.alonaTeal,
                      side: const BorderSide(
                        color: AppColors.alonaTeal,
                        width: 1,
                      ),
                      padding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 6,
                      ),
                      minimumSize: Size.zero,
                      tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(6),
                      ),
                    ),
                    child: Text(
                      'Send Link',
                      style: GoogleFonts.figtree(
                        fontSize: 11.5,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ),
            ),

          // Messages List with Date Separators
          Expanded(
            child: _isLoading
                ? const Center(
                    child: CircularProgressIndicator(
                      color: AppColors.alonaTeal,
                    ),
                  )
                : _error != null
                ? Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(
                          _error!,
                          style: const TextStyle(color: AppColors.sale),
                        ),
                        const SizedBox(height: 10),
                        ElevatedButton(
                          onPressed: () => _loadMessages(initial: true),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.alonaTeal,
                            foregroundColor: AppColors.white,
                          ),
                          child: const Text('Retry'),
                        ),
                      ],
                    ),
                  )
                : _messages.isEmpty
                ? Center(
                    child: Padding(
                      padding: const EdgeInsets.all(28),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Container(
                            width: 58,
                            height: 58,
                            decoration: const BoxDecoration(
                              color: AppColors.lightTeal,
                              shape: BoxShape.circle,
                            ),
                            child: const Icon(
                              Icons.chat_bubble_outline_rounded,
                              color: AppColors.alonaTeal,
                              size: 28,
                            ),
                          ),
                          const SizedBox(height: 14),
                          Text(
                            'Start the conversation',
                            style: GoogleFonts.figtree(
                              fontSize: 15,
                              fontWeight: FontWeight.w700,
                              color: AppColors.textPrimary,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            'Ask about stock, variation, or shipping details.',
                            textAlign: TextAlign.center,
                            style: GoogleFonts.figtree(
                              fontSize: 12,
                              color: AppColors.mutedText,
                            ),
                          ),
                        ],
                      ),
                    ),
                  )
                : ListView.builder(
                    controller: _scrollController,
                    padding: const EdgeInsets.symmetric(
                      horizontal: 14,
                      vertical: 12,
                    ),
                    itemCount: _messages.length,
                    itemBuilder: (context, index) {
                      final msg = _messages[index];

                      // Accurate sender detection
                      final bool isMe =
                          (_buyerId > 0 && msg.senderId == _buyerId) ||
                          (_sellerId > 0 && msg.senderId != _sellerId);

                      // Date separator pill check
                      final showDatePill = _shouldShowDatePill(index);

                      return Column(
                        children: [
                          if (showDatePill) _buildDateSeparator(msg.createdAt),
                          _buildMessageBubble(msg, isMe),
                        ],
                      );
                    },
                  ),
          ),

          // Bottom Bar matching reference screenshot layout
          Container(
            padding: EdgeInsets.only(
              left: 6,
              right: 6,
              top: 6,
              bottom: MediaQuery.of(context).viewInsets.bottom + 8,
            ),
            decoration: const BoxDecoration(
              color: AppColors.white,
              border: Border(
                top: BorderSide(color: AppColors.border, width: 0.8),
              ),
            ),
            child: SafeArea(
              top: false,
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  // (+) Add attachment icon button
                  IconButton(
                    icon: const Icon(
                      Icons.add_circle_outline_rounded,
                      color: AppColors.mutedText,
                      size: 24,
                    ),
                    padding: const EdgeInsets.all(8),
                    constraints: const BoxConstraints(),
                    onPressed: _showQuickActionsSheet,
                  ),

                  // Shopping bag icon button
                  IconButton(
                    icon: const Icon(
                      Icons.shopping_bag_outlined,
                      color: AppColors.mutedText,
                      size: 24,
                    ),
                    padding: const EdgeInsets.all(8),
                    constraints: const BoxConstraints(),
                    onPressed: () {
                      if (widget.productName != null) {
                        _sendProductInquiry();
                      } else {
                        _showQuickActionsSheet();
                      }
                    },
                  ),

                  const SizedBox(width: 4),

                  // Rounded Message Input
                  Expanded(
                    child: Container(
                      height: 40,
                      decoration: BoxDecoration(
                        color: AppColors.background,
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(color: AppColors.border, width: 0.8),
                      ),
                      child: TextField(
                        controller: _textController,
                        maxLines: 1,
                        textCapitalization: TextCapitalization.sentences,
                        style: GoogleFonts.figtree(
                          fontSize: 13.5,
                          color: AppColors.textPrimary,
                        ),
                        decoration: InputDecoration(
                          hintText: 'Type a message...',
                          hintStyle: GoogleFonts.figtree(
                            fontSize: 13,
                            color: AppColors.mutedText,
                          ),
                          contentPadding: const EdgeInsets.symmetric(
                            horizontal: 14,
                            vertical: 8,
                          ),
                          border: InputBorder.none,
                          isDense: true,
                        ),
                        onSubmitted: (_) => _handleSend(),
                      ),
                    ),
                  ),

                  const SizedBox(width: 4),

                  // Emoji button or Send button
                  _textController.text.trim().isEmpty && !_isSending
                      ? IconButton(
                          icon: const Icon(
                            Icons.sentiment_satisfied_outlined,
                            color: AppColors.mutedText,
                            size: 24,
                          ),
                          padding: const EdgeInsets.all(8),
                          constraints: const BoxConstraints(),
                          onPressed: _showEmojiSheet,
                        )
                      : IconButton(
                          icon: _isSending
                              ? const SizedBox(
                                  width: 18,
                                  height: 18,
                                  child: CircularProgressIndicator(
                                    color: AppColors.alonaTeal,
                                    strokeWidth: 2,
                                  ),
                                )
                              : const Icon(
                                  Icons.send_rounded,
                                  color: AppColors.alonaTeal,
                                  size: 22,
                                ),
                          padding: const EdgeInsets.all(8),
                          constraints: const BoxConstraints(),
                          onPressed: _isSending ? null : () => _handleSend(),
                        ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  bool _shouldShowDatePill(int index) {
    if (index == 0) return true;
    final prevDate = _messages[index - 1].createdAt;
    final currDate = _messages[index].createdAt;
    if (prevDate == null || currDate == null) return false;

    try {
      final prev = DateTime.parse(prevDate).toLocal();
      final curr = DateTime.parse(currDate).toLocal();
      return prev.day != curr.day ||
          prev.month != curr.month ||
          prev.year != curr.year;
    } catch (_) {
      return false;
    }
  }

  Widget _buildDateSeparator(String? rawDate) {
    String label = 'Today';
    if (rawDate != null) {
      try {
        final dt = DateTime.parse(rawDate).toLocal();
        final now = DateTime.now();
        final diffDays = now.difference(dt).inDays;

        if (diffDays == 0 && now.day == dt.day) {
          label = 'Today';
        } else if (diffDays == 1 ||
            (now.day - dt.day == 1 && now.month == dt.month)) {
          label = 'Yesterday';
        } else {
          final months = [
            '',
            'Jan',
            'Feb',
            'Mar',
            'Apr',
            'May',
            'Jun',
            'Jul',
            'Aug',
            'Sep',
            'Oct',
            'Nov',
            'Dec',
          ];
          final dayStr = dt.day.toString().padLeft(2, '0');
          final monthStr = months[dt.month];
          label = '$dayStr $monthStr';
        }
      } catch (_) {
        label = 'Recent';
      }
    }

    return Center(
      child: Container(
        margin: const EdgeInsets.symmetric(vertical: 14),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 3.5),
        decoration: BoxDecoration(
          color: AppColors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: AppColors.border, width: 0.8),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.02),
              blurRadius: 3,
              offset: const Offset(0, 1),
            ),
          ],
        ),
        child: Text(
          label,
          style: GoogleFonts.figtree(
            fontSize: 11,
            fontWeight: FontWeight.w500,
            color: AppColors.mutedText,
          ),
        ),
      ),
    );
  }

  Widget _buildMessageBubble(ConversationMessageModel msg, bool isMe) {
    return Align(
      alignment: isMe ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.symmetric(vertical: 4),
        constraints: BoxConstraints(
          maxWidth: MediaQuery.of(context).size.width * 0.78,
        ),
        padding: const EdgeInsets.fromLTRB(14, 10, 14, 8),
        decoration: BoxDecoration(
          color: isMe ? AppColors.alonaTeal : AppColors.white,
          borderRadius: BorderRadius.only(
            topLeft: const Radius.circular(16),
            topRight: const Radius.circular(16),
            bottomLeft: Radius.circular(isMe ? 16 : 4),
            bottomRight: Radius.circular(isMe ? 4 : 16),
          ),
          border: isMe ? null : Border.all(color: AppColors.border, width: 1),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.04),
              blurRadius: 4,
              offset: const Offset(0, 1),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: isMe
              ? CrossAxisAlignment.end
              : CrossAxisAlignment.start,
          children: [
            // Image Attachment if present
            if (msg.attachmentUrl != null && msg.attachmentUrl!.isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(bottom: 6),
                child: NetworkImageBox(
                  imageUrl: msg.attachmentUrl,
                  height: 140,
                  width: double.infinity,
                  borderRadius: BorderRadius.circular(8),
                  fit: BoxFit.cover,
                ),
              ),

            // Order Reference Card if present
            if (msg.orderNumber != null)
              Container(
                margin: const EdgeInsets.only(bottom: 6),
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 6,
                ),
                decoration: BoxDecoration(
                  color: isMe
                      ? Colors.white.withValues(alpha: 0.16)
                      : AppColors.background,
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(
                    color: isMe
                        ? Colors.white.withValues(alpha: 0.28)
                        : AppColors.border,
                  ),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(
                      Icons.receipt_long_outlined,
                      size: 16,
                      color: isMe ? Colors.white : AppColors.alonaTeal,
                    ),
                    const SizedBox(width: 6),
                    Text(
                      'Order #${msg.orderNumber}',
                      style: GoogleFonts.figtree(
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        color: isMe ? Colors.white : AppColors.textPrimary,
                      ),
                    ),
                  ],
                ),
              ),

            // Body text
            if (msg.body.isNotEmpty)
              Text(
                msg.body,
                style: GoogleFonts.figtree(
                  fontSize: 13.5,
                  height: 1.45,
                  fontWeight: FontWeight.w400,
                  color: isMe ? Colors.white : AppColors.textPrimary,
                ),
              ),

            const SizedBox(height: 4),

            // Timestamp inside bubble at bottom right
            Row(
              mainAxisSize: MainAxisSize.min,
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                if (msg.createdAt != null)
                  Text(
                    _formatTime(msg.createdAt!),
                    style: GoogleFonts.figtree(
                      fontSize: 10,
                      fontWeight: FontWeight.w500,
                      color: isMe
                          ? Colors.white.withValues(alpha: 0.75)
                          : AppColors.mutedText,
                    ),
                  ),
                if (isMe) ...[
                  const SizedBox(width: 4),
                  Icon(
                    msg.readAt != null
                        ? Icons.done_all_rounded
                        : Icons.done_rounded,
                    size: 14,
                    color: Colors.white,
                  ),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }

  String _formatTime(String rawDate) {
    try {
      final dt = DateTime.parse(rawDate).toLocal();
      final hour = dt.hour.toString().padLeft(2, '0');
      final minute = dt.minute.toString().padLeft(2, '0');
      return '$hour:$minute';
    } catch (_) {
      return '';
    }
  }
}
