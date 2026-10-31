import 'dart:async';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/core/utils/currency_formatter.dart';
import 'package:alona_mobile/shared/widgets/empty_state_view.dart';
import 'package:alona_mobile/shared/widgets/network_image_box.dart';
import 'package:alona_mobile/features/buyer/models/buyer_models.dart';
import 'package:alona_mobile/features/buyer/presentation/products/product_detail_screen.dart';

class FlashSalesWidget extends StatefulWidget {
  final List<FlashSaleModel> flashSales;
  final String? endTime;
  final VoidCallback onBrowseProducts;

  const FlashSalesWidget({
    super.key,
    required this.flashSales,
    this.endTime,
    required this.onBrowseProducts,
  });

  @override
  State<FlashSalesWidget> createState() => _FlashSalesWidgetState();
}

class _FlashSalesWidgetState extends State<FlashSalesWidget> {
  Timer? _timer;
  Duration _remaining = Duration.zero;

  @override
  void initState() {
    super.initState();
    _initTimer();
  }

  @override
  void didUpdateWidget(FlashSalesWidget oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.endTime != widget.endTime) {
      _initTimer();
    }
  }

  void _initTimer() {
    _timer?.cancel();
    if (widget.endTime != null) {
      try {
        final end = DateTime.parse(widget.endTime!);
        _updateRemaining(end);
        _timer = Timer.periodic(const Duration(seconds: 1), (_) => _updateRemaining(end));
      } catch (_) {
        _remaining = Duration.zero;
      }
    } else {
      _remaining = Duration.zero;
    }
  }

  void _updateRemaining(DateTime end) {
    final diff = end.difference(DateTime.now());
    if (diff.isNegative) {
      setState(() {
        _remaining = Duration.zero;
      });
      _timer?.cancel();
    } else {
      setState(() {
        _remaining = diff;
      });
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  String _twoDigits(int n) => n.toString().padLeft(2, '0');

  @override
  Widget build(BuildContext context) {
    final hours = _twoDigits(_remaining.inHours);
    final minutes = _twoDigits(_remaining.inMinutes.remainder(60));
    final seconds = _twoDigits(_remaining.inSeconds.remainder(60));

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Section Header matching web exactly
        Row(
          children: [
            Container(
              width: 8,
              height: 8,
              decoration: const BoxDecoration(
                color: AppColors.accentGold,
                shape: BoxShape.circle,
              ),
            ),
            const SizedBox(width: 8),
            Text(
              'LIMITED TIME',
              style: GoogleFonts.figtree(
                color: AppColors.primary,
                fontSize: 11,
                fontWeight: FontWeight.w800,
                letterSpacing: 1.2,
              ),
            ),
          ],
        ),
        const SizedBox(height: 6),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Row(
              children: [
                Text(
                  'Flash Deals',
                  style: GoogleFonts.figtree(
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                    color: const Color(0xFF132228),
                  ),
                ),
                const SizedBox(width: 6),
                const Text('⚡', style: TextStyle(fontSize: 18)),
              ],
            ),
            if (widget.flashSales.isNotEmpty && _remaining > Duration.zero)
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                decoration: BoxDecoration(
                  color: AppColors.textPrimary,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      'Ends in  ',
                      style: GoogleFonts.figtree(
                        color: Colors.white70,
                        fontSize: 11,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    _buildTimeBadge(hours),
                    const Text(' : ', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 11)),
                    _buildTimeBadge(minutes),
                    const Text(' : ', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 11)),
                    _buildTimeBadge(seconds),
                  ],
                ),
              ),
          ],
        ),
        const SizedBox(height: 14),

        // Cards list or empty state matching user uploaded screenshot
        if (widget.flashSales.isEmpty)
          EmptyStateView(
            icon: Icons.local_offer_outlined,
            title: 'No active promotions right now',
            message: 'Check back soon for vouchers and special deals from Alona sellers.',
            buttonText: 'Browse products',
            onButtonPressed: widget.onBrowseProducts,
          )
        else
          SizedBox(
            height: 240,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: widget.flashSales.length,
              separatorBuilder: (_, _) => const SizedBox(width: 12),
              itemBuilder: (context, index) {
                final sale = widget.flashSales[index];
                return _buildFlashCard(context, sale);
              },
            ),
          ),
      ],
    );
  }

  Widget _buildTimeBadge(String text) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(4),
      ),
      child: Text(
        text,
        style: GoogleFonts.figtree(
          color: Colors.white,
          fontSize: 12,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }

  Widget _buildFlashCard(BuildContext context, FlashSaleModel sale) {
    return GestureDetector(
      onTap: () {
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => ProductDetailScreen(productId: sale.productId),
          ),
        );
      },
      child: Container(
        width: 160,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: AppColors.border, width: 1),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.03),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Stack(
              children: [
                NetworkImageBox(
                  imageUrl: sale.imageUrl,
                  height: 120,
                  width: 160,
                  borderRadius: const BorderRadius.vertical(top: Radius.circular(15)),
                ),
                Positioned(
                  top: 8,
                  left: 8,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                    decoration: BoxDecoration(
                      color: AppColors.error,
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      '${sale.discountPercent}% OFF',
                      style: GoogleFonts.figtree(
                        color: Colors.white,
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ),
              ],
            ),
            Padding(
              padding: const EdgeInsets.all(10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    sale.name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: GoogleFonts.figtree(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: AppColors.textPrimary,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      Text(
                        CurrencyFormatter.format(sale.flashPrice),
                        style: GoogleFonts.figtree(
                          fontSize: 14,
                          fontWeight: FontWeight.w800,
                          color: AppColors.primary,
                        ),
                      ),
                      const SizedBox(width: 4),
                      Text(
                        CurrencyFormatter.format(sale.originalPrice),
                        style: GoogleFonts.figtree(
                          fontSize: 11,
                          decoration: TextDecoration.lineThrough,
                          color: AppColors.textLight,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(4),
                    child: LinearProgressIndicator(
                      value: sale.progressPercent / 100,
                      backgroundColor: AppColors.surfaceAlt,
                      valueColor: const AlwaysStoppedAnimation<Color>(AppColors.accentGold),
                      minHeight: 5,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    '${sale.remaining} left',
                    style: GoogleFonts.figtree(
                      fontSize: 10,
                      fontWeight: FontWeight.w500,
                      color: AppColors.textMuted,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
