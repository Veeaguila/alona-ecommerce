import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/core/utils/currency_formatter.dart';
import 'package:alona_mobile/core/utils/date_formatter.dart';
import 'package:alona_mobile/shared/widgets/alona_button.dart';
import 'package:alona_mobile/shared/widgets/network_image_box.dart';
import 'package:alona_mobile/shared/widgets/status_badge.dart';
import 'package:alona_mobile/features/buyer/models/buyer_models.dart';
import 'package:alona_mobile/features/buyer/services/buyer_service.dart';
import 'package:alona_mobile/features/buyer/presentation/orders/delivery_otp_dialog.dart';

class OrderDetailScreen extends StatefulWidget {
  final int orderId;

  const OrderDetailScreen({super.key, required this.orderId});

  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen>
    with WidgetsBindingObserver {
  bool _isLoading = true;
  bool _isActing = false;
  int _loadRevision = 0;
  String? _error;
  OrderModel? _order;
  bool _canCancel = false;
  bool _canMarkReceived = false;
  List<dynamic> _histories = [];
  List<Map<String, dynamic>> _otpParcels = [];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _loadOrder();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && !_isLoading) _loadOrder();
  }

  Future<void> _loadOrder() async {
    if (!mounted) return;
    final revision = ++_loadRevision;
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final res = await BuyerService.getOrderDetails(widget.orderId);
      if (!mounted || revision != _loadRevision) return;
      setState(() {
        _order = res['order'] as OrderModel;
        _canCancel = res['can_cancel'] == true;
        _canMarkReceived = res['can_mark_received'] == true;
        _histories = res['status_histories'] as List<dynamic>? ?? [];
        _otpParcels = (res['delivery_parcels'] as List)
            .cast<Map<String, dynamic>>();
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

  Future<void> _showCancelDialog() async {
    if (_isActing) return;
    setState(() => _isActing = true);
    try {
      final reason = await showDialog<String>(
        context: context,
        builder: (_) => const _CancelOrderDialog(),
      );
      if (!mounted) return;
      if (reason != null && reason.isNotEmpty) {
        try {
          await BuyerService.cancelOrder(widget.orderId, reason);
          if (!mounted) return;
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Order cancelled successfully.')),
          );
          _loadOrder();
        } catch (e) {
          if (!mounted) return;
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(e.toString()),
              backgroundColor: AppColors.error,
            ),
          );
        }
      }
    } finally {
      if (mounted) setState(() => _isActing = false);
    }
  }

  Future<void> _confirmReceived() async {
    if (_isActing) return;
    setState(() => _isActing = true);
    try {
      await BuyerService.receiveOrder(widget.orderId);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Order marked as completed!'),
          backgroundColor: AppColors.success,
        ),
      );
      _loadOrder();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: AppColors.error),
      );
    } finally {
      if (mounted) setState(() => _isActing = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Scaffold(
        body: Center(
          child: CircularProgressIndicator(color: AppColors.primary),
        ),
      );
    }

    if (_error != null || _order == null) {
      return Scaffold(
        appBar: AppBar(),
        body: Center(child: Text(_error ?? 'Order not found')),
      );
    }

    final order = _order!;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Order Details'),
        actions: [
          IconButton(
            tooltip: 'Refresh order',
            onPressed: _loadOrder,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Status Header
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: AppColors.border),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Order Number',
                            style: GoogleFonts.figtree(
                              fontSize: 12,
                              color: AppColors.textMuted,
                            ),
                          ),
                          Text(
                            order.orderNumber,
                            style: GoogleFonts.figtree(
                              fontSize: 16,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ],
                      ),
                      StatusBadge(status: order.status),
                    ],
                  ),
                  if (order.createdAt != null) ...[
                    const SizedBox(height: 8),
                    Text(
                      'Placed on ${DateFormatter.formatWithTime(DateTime.tryParse(order.createdAt!))}',
                      style: GoogleFonts.figtree(
                        fontSize: 12,
                        color: AppColors.textMuted,
                      ),
                    ),
                  ],
                ],
              ),
            ),

            const SizedBox(height: 16),

            // Order Tracking History Timeline
            for (final parcel in _otpParcels)
              Padding(
                padding: const EdgeInsets.only(bottom: 16),
                child: AlonaButton(
                  text: _otpParcels.length == 1
                      ? 'Show delivery OTP'
                      : 'Delivery OTP · ${parcel['product_name']}',
                  icon: Icons.lock_outline,
                  onPressed: () => showDialog(
                    context: context,
                    builder: (_) => DeliveryOtpDialog(
                      orderId: order.id,
                      parcelId: parcel['delivery_parcel_id'],
                    ),
                  ),
                ),
              ),
            if (_histories.isNotEmpty)
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: AppColors.border),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Delivery Tracking History',
                      style: GoogleFonts.figtree(
                        fontSize: 15,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 14),
                    ..._histories.map((h) {
                      final status = h['status']?.toString() ?? '';
                      final notes = (h['note'] ?? h['notes'])?.toString() ?? '';
                      final time = h['created_at'] != null
                          ? DateFormatter.formatWithTime(
                              DateTime.tryParse(h['created_at']),
                            )
                          : '';

                      return Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Container(
                              width: 10,
                              height: 10,
                              margin: const EdgeInsets.only(top: 4),
                              decoration: const BoxDecoration(
                                color: AppColors.primary,
                                shape: BoxShape.circle,
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    status.toUpperCase(),
                                    style: GoogleFonts.figtree(
                                      fontWeight: FontWeight.w700,
                                      fontSize: 13,
                                    ),
                                  ),
                                  if (notes.isNotEmpty) ...[
                                    const SizedBox(height: 2),
                                    Text(
                                      notes,
                                      style: GoogleFonts.figtree(
                                        fontSize: 12,
                                        color: AppColors.textSecondary,
                                      ),
                                    ),
                                  ],
                                  if (time.isNotEmpty) ...[
                                    const SizedBox(height: 2),
                                    Text(
                                      time,
                                      style: GoogleFonts.figtree(
                                        fontSize: 11,
                                        color: AppColors.textLight,
                                      ),
                                    ),
                                  ],
                                ],
                              ),
                            ),
                          ],
                        ),
                      );
                    }),
                  ],
                ),
              ),

            const SizedBox(height: 16),

            // Ordered Items List
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: AppColors.border),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Ordered Items (${order.items.length})',
                    style: GoogleFonts.figtree(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 12),
                  ...order.items.map((item) {
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: Row(
                        children: [
                          NetworkImageBox(
                            imageUrl: item.imageUrl,
                            width: 56,
                            height: 56,
                            borderRadius: BorderRadius.circular(8),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  item.productName,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: GoogleFonts.figtree(
                                    fontWeight: FontWeight.w600,
                                    fontSize: 13,
                                  ),
                                ),
                                if (item.variantLabel != null)
                                  Text(
                                    item.variantLabel!,
                                    style: GoogleFonts.figtree(
                                      fontSize: 11,
                                      color: AppColors.textMuted,
                                    ),
                                  ),
                                Text(
                                  'x${item.quantity} • ${CurrencyFormatter.format(item.unitPrice)}',
                                  style: GoogleFonts.figtree(
                                    fontSize: 12,
                                    color: AppColors.textMuted,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          Text(
                            CurrencyFormatter.format(item.totalPrice),
                            style: GoogleFonts.figtree(
                              fontWeight: FontWeight.w700,
                              fontSize: 13,
                            ),
                          ),
                        ],
                      ),
                    );
                  }),
                ],
              ),
            ),

            const SizedBox(height: 16),

            // Shipping Address & Details
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: AppColors.border),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Shipping & Payment',
                    style: GoogleFonts.figtree(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 12),
                  _row('Delivery Address', order.shippingAddress),
                  const SizedBox(height: 8),
                  _row('Shipping Method', order.shippingMethod.toUpperCase()),
                  const SizedBox(height: 8),
                  _row(
                    'Payment Method',
                    '${order.paymentMethod.toUpperCase()} (${order.paymentStatus.toUpperCase()})',
                  ),
                  if (order.buyerNote != null &&
                      order.buyerNote!.isNotEmpty) ...[
                    const SizedBox(height: 8),
                    _row('Note to Seller', order.buyerNote!),
                  ],
                ],
              ),
            ),

            const SizedBox(height: 16),

            // Financial Summary
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: AppColors.border),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Price Breakdown',
                    style: GoogleFonts.figtree(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 12),
                  _row(
                    'Merchandise Subtotal',
                    CurrencyFormatter.format(order.subtotal),
                  ),
                  const SizedBox(height: 8),
                  _row(
                    'Shipping Fee',
                    CurrencyFormatter.format(order.shippingFee),
                  ),
                  if (order.discount > 0) ...[
                    const SizedBox(height: 8),
                    _row(
                      'Voucher Discount',
                      '-${CurrencyFormatter.format(order.discount)}',
                    ),
                  ],
                  const Divider(height: 20),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'Total Payment',
                        style: GoogleFonts.figtree(
                          fontSize: 16,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      Text(
                        CurrencyFormatter.format(order.total),
                        style: GoogleFonts.figtree(
                          fontSize: 18,
                          fontWeight: FontWeight.w800,
                          color: AppColors.primary,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),

            const SizedBox(height: 24),

            if (_canMarkReceived) ...[
              AlonaButton(
                text: 'Confirm Delivery Received',
                icon: Icons.check_circle_outline,
                onPressed: _isActing ? null : _confirmReceived,
              ),
              const SizedBox(height: 12),
            ],

            if (_canCancel) ...[
              AlonaButton(
                text: 'Cancel Order',
                variant: ButtonVariant.danger,
                onPressed: _isActing ? null : _showCancelDialog,
              ),
            ],
            const SizedBox(height: 40),
          ],
        ),
      ),
    );
  }

  Widget _row(String label, String value) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        SizedBox(
          width: 120,
          child: Text(
            label,
            style: GoogleFonts.figtree(
              fontSize: 13,
              color: AppColors.textMuted,
            ),
          ),
        ),
        Expanded(
          child: Text(
            value,
            textAlign: TextAlign.right,
            style: GoogleFonts.figtree(
              fontSize: 13,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ],
    );
  }
}

class _CancelOrderDialog extends StatefulWidget {
  const _CancelOrderDialog();
  @override
  State<_CancelOrderDialog> createState() => _CancelOrderDialogState();
}

class _CancelOrderDialogState extends State<_CancelOrderDialog> {
  final controller = TextEditingController();
  @override
  void dispose() {
    controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text(
      'Cancel Order',
      style: GoogleFonts.figtree(fontWeight: FontWeight.w700),
    ),
    content: Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Are you sure you want to cancel this order?',
          style: GoogleFonts.figtree(fontSize: 14),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: controller,
          decoration: const InputDecoration(
            hintText: 'Reason for cancellation...',
            border: OutlineInputBorder(),
          ),
        ),
      ],
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: const Text('Keep Order'),
      ),
      ElevatedButton(
        onPressed: () {
          if (controller.text.trim().isNotEmpty) {
            Navigator.pop(context, controller.text.trim());
          }
        },
        style: ElevatedButton.styleFrom(backgroundColor: AppColors.error),
        child: const Text('Confirm Cancel'),
      ),
    ],
  );
}
