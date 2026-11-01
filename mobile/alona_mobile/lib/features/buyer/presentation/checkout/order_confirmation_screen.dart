import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/core/utils/currency_formatter.dart';
import 'package:alona_mobile/shared/widgets/alona_button.dart';
import 'package:alona_mobile/features/buyer/models/buyer_models.dart';
import 'package:alona_mobile/features/buyer/presentation/orders/order_detail_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/orders/order_list_screen.dart';

class OrderConfirmationScreen extends StatelessWidget {
  final OrderModel order;
  final List<OrderModel> orders;

  const OrderConfirmationScreen({
    super.key,
    required this.order,
    this.orders = const [],
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Spacer(),
              Container(
                width: 80,
                height: 80,
                decoration: const BoxDecoration(
                  color: AppColors.primaryLight,
                  shape: BoxShape.circle,
                ),
                child: const Center(
                  child: Icon(
                    Icons.check_circle_rounded,
                    color: AppColors.primary,
                    size: 48,
                  ),
                ),
              ),
              const SizedBox(height: 24),
              Text(
                'Order Placed Successfully!',
                textAlign: TextAlign.center,
                style: GoogleFonts.figtree(
                  fontSize: 22,
                  fontWeight: FontWeight.w800,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                orders.length > 1
                    ? 'Your checkout was split into ${orders.length} store orders. Each seller will prepare their own items.'
                    : 'Thank you for your purchase. We have received your order and the seller is preparing your items.',
                textAlign: TextAlign.center,
                style: GoogleFonts.figtree(
                  fontSize: 14,
                  color: AppColors.textMuted,
                  height: 1.4,
                ),
              ),
              const SizedBox(height: 28),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: AppColors.border),
                ),
                child: Column(
                  children: [
                    _infoRow('Order Number', order.orderNumber),
                    const Divider(height: 16),
                    _infoRow(
                      'Total Amount',
                      CurrencyFormatter.format(
                        orders.isEmpty
                            ? order.total
                            : orders.fold<double>(
                                0,
                                (sum, item) => sum + item.total,
                              ),
                      ),
                      isBold: true,
                    ),
                    const Divider(height: 16),
                    _infoRow(
                      'Payment Method',
                      order.paymentMethod.toUpperCase(),
                    ),
                    const Divider(height: 16),
                    _infoRow(
                      'Shipping Method',
                      order.shippingMethod.toUpperCase(),
                    ),
                  ],
                ),
              ),
              const Spacer(),
              if (order.paymentMethod != 'cod' &&
                  order.paymentStatus == 'unpaid')
                const Padding(
                  padding: EdgeInsets.only(bottom: 12),
                  child: Text(
                    'Payment is pending. Open this order on the ALONA website to complete online payment.',
                    textAlign: TextAlign.center,
                  ),
                ),
              if (orders.length > 1)
                TextButton(
                  onPressed: () => Navigator.push(
                    context,
                    MaterialPageRoute(builder: (_) => const OrderListScreen()),
                  ),
                  child: const Text('View all store orders'),
                ),
              AlonaButton(
                text: 'View Order Details',
                onPressed: () {
                  Navigator.pushReplacement(
                    context,
                    MaterialPageRoute(
                      builder: (_) => OrderDetailScreen(orderId: order.id),
                    ),
                  );
                },
              ),
              const SizedBox(height: 12),
              AlonaButton(
                text: 'Back to Home',
                variant: ButtonVariant.outline,
                onPressed: () {
                  Navigator.popUntil(context, (route) => route.isFirst);
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _infoRow(String label, String value, {bool isBold = false}) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: GoogleFonts.figtree(fontSize: 13, color: AppColors.textMuted),
        ),
        Text(
          value,
          style: GoogleFonts.figtree(
            fontSize: 13,
            fontWeight: isBold ? FontWeight.w800 : FontWeight.w600,
            color: isBold ? AppColors.primary : AppColors.textPrimary,
          ),
        ),
      ],
    );
  }
}
