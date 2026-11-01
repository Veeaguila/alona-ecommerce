import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/core/utils/currency_formatter.dart';
import 'package:alona_mobile/shared/widgets/network_image_box.dart';
import 'package:alona_mobile/shared/widgets/status_badge.dart';
import 'package:alona_mobile/features/buyer/models/buyer_models.dart';
import 'package:alona_mobile/features/buyer/services/buyer_service.dart';
import 'package:alona_mobile/features/buyer/presentation/orders/order_detail_screen.dart';

class OrderListScreen extends StatefulWidget {
  final String initialTab;
  final VoidCallback? onBrowseProducts;

  const OrderListScreen({
    super.key,
    this.initialTab = 'all',
    this.onBrowseProducts,
  });

  @override
  State<OrderListScreen> createState() => _OrderListScreenState();
}

class _OrderListScreenState extends State<OrderListScreen> {
  final List<String> _tabs = [
    'all',
    'to_pay',
    'to_ship',
    'to_receive',
    'completed',
    'cancelled',
  ];
  final List<String> _tabLabels = [
    'All',
    'To Pay',
    'To Ship',
    'To Receive',
    'Completed',
    'Cancelled',
  ];

  late int _selectedTabIndex;
  bool _isLoading = true;
  int _loadRevision = 0;
  final Set<int> _pendingOrders = {};
  String? _error;
  List<OrderModel> _orders = [];
  Map<String, dynamic> _counts = {};
  int _page = 1, _lastPage = 1;

  // Custom colors matching the design mockups
  static const Color _bgScaffold = Color(0xFFF2F6F6);
  static const Color _darkTeal = Color(0xFF087F8C);
  static const Color _lightTeal = Color(0xFFDFF3F0);
  static const Color _cardBorder = Color(0xFFE2EBE9);
  static const Color _headingColor = Color(0xFF132228);
  static const Color _subtitleColor = Color(0xFF5D7377);

  @override
  void initState() {
    super.initState();
    int initialIndex = _tabs.indexOf(widget.initialTab);
    if (initialIndex < 0) initialIndex = 0;
    _selectedTabIndex = initialIndex;
    _fetchOrders(_tabs[_selectedTabIndex]);
  }

  Future<void> _fetchOrders(String status, {int page = 1}) async {
    if (!mounted) return;
    final revision = ++_loadRevision;
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final res = await BuyerService.getOrders(status: status, page: page);
      if (!mounted || revision != _loadRevision) return;
      if ((res['orders'] as List).isEmpty && page > 1) {
        await _fetchOrders(status);
        return;
      }
      setState(() {
        _orders = res['orders'] as List<OrderModel>;
        _counts = res['counts'] as Map<String, dynamic>;
        _page = parseInt(res['current_page']);
        _lastPage = parseInt(res['last_page']);
        _isLoading = false;
      });
    } catch (e) {
      if (!mounted || revision != _loadRevision) return;
      setState(() {
        _error = e.toString();
        _isLoading = false;
      });
    }
  }

  void _onTabSelected(int index) {
    if (_selectedTabIndex == index && !_isLoading) return;
    setState(() {
      _selectedTabIndex = index;
    });
    _fetchOrders(_tabs[index]);
  }

  Future<void> _confirmReceived(int orderId) async {
    if (_pendingOrders.contains(orderId)) return;
    setState(() => _pendingOrders.add(orderId));
    try {
      await BuyerService.receiveOrder(orderId);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Order marked as received!'),
          backgroundColor: AppColors.success,
        ),
      );
      _fetchOrders(_tabs[_selectedTabIndex]);
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: AppColors.error),
      );
    } finally {
      if (mounted) setState(() => _pendingOrders.remove(orderId));
    }
  }

  Future<void> _reorder(int orderId) async {
    if (_pendingOrders.contains(orderId)) return;
    setState(() => _pendingOrders.add(orderId));
    try {
      await BuyerService.reorder(orderId);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Items added back to your cart!'),
          backgroundColor: AppColors.success,
        ),
      );
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: AppColors.error),
      );
    } finally {
      if (mounted) setState(() => _pendingOrders.remove(orderId));
    }
  }

  void _handleBrowseProducts() {
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
            // Header: Title with optional back button
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 14),
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
                          border: Border.all(color: _cardBorder),
                        ),
                        child: const Icon(
                          Icons.arrow_back_ios_new_rounded,
                          size: 16,
                          color: _headingColor,
                        ),
                      ),
                    ),
                  ],
                  Text(
                    'My Orders',
                    style: GoogleFonts.figtree(
                      fontSize: 26,
                      fontWeight: FontWeight.w800,
                      color: _headingColor,
                      letterSpacing: -0.5,
                    ),
                  ),
                ],
              ),
            ),

            // Horizontal Filter Chips
            _buildFilterChips(),

            const SizedBox(height: 12),

            // Main Content Area
            Expanded(
              child: _isLoading
                  ? const Center(
                      child: CircularProgressIndicator(color: _darkTeal),
                    )
                  : _error != null
                  ? Center(
                      child: Padding(
                        padding: const EdgeInsets.all(24),
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              _error!,
                              textAlign: TextAlign.center,
                              style: GoogleFonts.figtree(color: _subtitleColor),
                            ),
                            const SizedBox(height: 12),
                            ElevatedButton(
                              onPressed: () =>
                                  _fetchOrders(_tabs[_selectedTabIndex]),
                              style: ElevatedButton.styleFrom(
                                backgroundColor: _darkTeal,
                                foregroundColor: Colors.white,
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(8),
                                ),
                                elevation: 0,
                              ),
                              child: const Text('Try Again'),
                            ),
                          ],
                        ),
                      ),
                    )
                  : _orders.isEmpty
                  ? _buildEmptyState()
                  : RefreshIndicator(
                      onRefresh: () => _fetchOrders(_tabs[_selectedTabIndex]),
                      color: _darkTeal,
                      child: ListView.separated(
                        padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
                        itemCount: _orders.length + (_lastPage > 1 ? 1 : 0),
                        separatorBuilder: (_, _) => const SizedBox(height: 12),
                        itemBuilder: (context, index) {
                          if (index == _orders.length) {
                            return Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                TextButton(
                                  onPressed: _page > 1
                                      ? () => _fetchOrders(
                                          _tabs[_selectedTabIndex],
                                          page: _page - 1,
                                        )
                                      : null,
                                  child: const Text('Previous'),
                                ),
                                Text('$_page / $_lastPage'),
                                TextButton(
                                  onPressed: _page < _lastPage
                                      ? () => _fetchOrders(
                                          _tabs[_selectedTabIndex],
                                          page: _page + 1,
                                        )
                                      : null,
                                  child: const Text('Next'),
                                ),
                              ],
                            );
                          }
                          final order = _orders[index];
                          return _buildOrderCard(order);
                        },
                      ),
                    ),
            ),
          ],
        ),
      ),
    );
  }

  // Chip colors exact match from design image
  static const Color _chipActive = Color(0xFF087F8C);
  static const Color _chipInactiveBg = Color(0xFFF1F4F9);
  static const Color _chipInactiveBorder = Color(0xFF334155);
  static const Color _chipInactiveText = Color(0xFF334155);

  Widget _buildFilterChips() {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      physics: const BouncingScrollPhysics(),
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Row(
        children: List.generate(_tabs.length, (i) {
          final isSelected = _selectedTabIndex == i;
          final label = _tabLabels[i];
          final count = _counts[_tabs[i]];
          final displayText = (count != null && count is num && count > 0)
              ? '$label ($count)'
              : label;

          return Padding(
            padding: const EdgeInsets.only(right: 8),
            child: Material(
              color: isSelected ? _chipActive : _chipInactiveBg,
              borderRadius: BorderRadius.circular(8),
              child: InkWell(
                borderRadius: BorderRadius.circular(8),
                onTap: () => _onTabSelected(i),
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 8,
                  ),
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(
                      color: isSelected ? _chipActive : _chipInactiveBorder,
                      width: 1.0,
                    ),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      if (isSelected) ...[
                        const Icon(
                          Icons.check_rounded,
                          size: 15,
                          color: Colors.white,
                        ),
                        const SizedBox(width: 5),
                      ],
                      Text(
                        displayText,
                        style: GoogleFonts.figtree(
                          fontSize: 13,
                          fontWeight: isSelected
                              ? FontWeight.w700
                              : FontWeight.w600,
                          color: isSelected ? Colors.white : _chipInactiveText,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          );
        }),
      ),
    );
  }

  Widget _buildEmptyState() {
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(16, 6, 16, 24),
      physics: const AlwaysScrollableScrollPhysics(),
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 48),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(28),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.02),
              blurRadius: 10,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Circular badge with Receipt & Plus
            Container(
              width: 100,
              height: 100,
              decoration: const BoxDecoration(
                color: _lightTeal,
                shape: BoxShape.circle,
              ),
              child: Stack(
                alignment: Alignment.center,
                children: [
                  const Icon(
                    Icons.receipt_long_outlined,
                    size: 48,
                    color: _darkTeal,
                  ),
                  Positioned(
                    right: 22,
                    bottom: 22,
                    child: Container(
                      width: 24,
                      height: 24,
                      decoration: const BoxDecoration(
                        color: _darkTeal,
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(
                        Icons.add,
                        size: 16,
                        color: Colors.white,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),
            Text(
              'No orders yet',
              style: GoogleFonts.figtree(
                fontSize: 20,
                fontWeight: FontWeight.w800,
                color: _headingColor,
              ),
            ),
            const SizedBox(height: 10),
            Text(
              'When you place an order, you can\ntrack it here from payment to delivery.',
              textAlign: TextAlign.center,
              style: GoogleFonts.figtree(
                fontSize: 14,
                color: _subtitleColor,
                height: 1.45,
              ),
            ),
            const SizedBox(height: 28),
            Material(
              color: _darkTeal,
              borderRadius: BorderRadius.circular(8),
              child: InkWell(
                borderRadius: BorderRadius.circular(8),
                onTap: _handleBrowseProducts,
                child: Padding(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 28,
                    vertical: 13,
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        'Browse Products',
                        style: GoogleFonts.figtree(
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                          color: Colors.white,
                        ),
                      ),
                      const SizedBox(width: 6),
                      const Icon(
                        Icons.chevron_right_rounded,
                        size: 20,
                        color: Colors.white,
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildOrderCard(OrderModel order) {
    final firstItem = order.items.isNotEmpty ? order.items.first : null;

    return GestureDetector(
      onTap: () async {
        await Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => OrderDetailScreen(orderId: order.id),
          ),
        );
        if (mounted) {
          _fetchOrders(_tabs[_selectedTabIndex]);
        }
      },
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: _cardBorder),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.02),
              blurRadius: 6,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Order number and Status Badge
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  order.orderNumber,
                  style: GoogleFonts.figtree(
                    fontWeight: FontWeight.w800,
                    fontSize: 14,
                    color: _headingColor,
                  ),
                ),
                StatusBadge(status: order.status),
              ],
            ),
            const Divider(height: 20, color: Color(0xFFF0F4F4)),

            // Item preview
            if (firstItem != null)
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  NetworkImageBox(
                    imageUrl: firstItem.imageUrl,
                    width: 60,
                    height: 60,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          firstItem.productName,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: GoogleFonts.figtree(
                            fontWeight: FontWeight.w700,
                            fontSize: 13,
                            color: _headingColor,
                          ),
                        ),
                        if (firstItem.variantLabel != null) ...[
                          const SizedBox(height: 2),
                          Text(
                            firstItem.variantLabel!,
                            style: GoogleFonts.figtree(
                              fontSize: 11,
                              color: _subtitleColor,
                            ),
                          ),
                        ],
                        const SizedBox(height: 4),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              'x${firstItem.quantity}',
                              style: GoogleFonts.figtree(
                                fontSize: 12,
                                color: _subtitleColor,
                              ),
                            ),
                            Text(
                              CurrencyFormatter.format(firstItem.totalPrice),
                              style: GoogleFonts.figtree(
                                fontSize: 13,
                                fontWeight: FontWeight.w700,
                                color: _headingColor,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),

            if (order.items.length > 1) ...[
              const SizedBox(height: 8),
              Text(
                '+ ${order.items.length - 1} more items',
                style: GoogleFonts.figtree(fontSize: 11, color: _subtitleColor),
              ),
            ],

            const Divider(height: 20, color: Color(0xFFF0F4F4)),

            // Total and Actions
            Row(
              children: [
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Order Total',
                      style: GoogleFonts.figtree(
                        fontSize: 11,
                        color: _subtitleColor,
                      ),
                    ),
                    Text(
                      CurrencyFormatter.format(order.total),
                      style: GoogleFonts.figtree(
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                        color: _darkTeal,
                      ),
                    ),
                  ],
                ),
                const Spacer(),
                if (order.status.toLowerCase() == 'delivered')
                  ElevatedButton(
                    onPressed: _pendingOrders.contains(order.id)
                        ? null
                        : () => _confirmReceived(order.id),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: _darkTeal,
                      foregroundColor: Colors.white,
                      elevation: 0,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(8),
                      ),
                      padding: const EdgeInsets.symmetric(
                        horizontal: 16,
                        vertical: 8,
                      ),
                      textStyle: GoogleFonts.figtree(
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    child: const Text('Received'),
                  )
                else if (order.status.toLowerCase() == 'completed')
                  OutlinedButton(
                    onPressed: _pendingOrders.contains(order.id)
                        ? null
                        : () => _reorder(order.id),
                    style: OutlinedButton.styleFrom(
                      backgroundColor: _chipInactiveBg,
                      foregroundColor: _chipInactiveText,
                      side: const BorderSide(
                        color: _chipInactiveBorder,
                        width: 1.0,
                      ),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(8),
                      ),
                      padding: const EdgeInsets.symmetric(
                        horizontal: 16,
                        vertical: 8,
                      ),
                      textStyle: GoogleFonts.figtree(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    child: const Text('Buy Again'),
                  )
                else
                  OutlinedButton(
                    onPressed: () async {
                      await Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => OrderDetailScreen(orderId: order.id),
                        ),
                      );
                      if (mounted) {
                        _fetchOrders(_tabs[_selectedTabIndex]);
                      }
                    },
                    style: OutlinedButton.styleFrom(
                      backgroundColor: _chipInactiveBg,
                      foregroundColor: _chipInactiveText,
                      side: const BorderSide(
                        color: _chipInactiveBorder,
                        width: 1.0,
                      ),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(8),
                      ),
                      padding: const EdgeInsets.symmetric(
                        horizontal: 16,
                        vertical: 8,
                      ),
                      textStyle: GoogleFonts.figtree(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    child: const Text('Details'),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
