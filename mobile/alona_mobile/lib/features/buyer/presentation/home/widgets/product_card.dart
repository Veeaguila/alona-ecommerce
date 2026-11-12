import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/core/utils/currency_formatter.dart';
import 'package:alona_mobile/shared/widgets/network_image_box.dart';
import 'package:alona_mobile/features/buyer/models/buyer_models.dart';
import 'package:alona_mobile/features/buyer/presentation/products/product_detail_screen.dart';

class ProductCard extends StatelessWidget {
  final ProductModel product;
  final bool isMall;
  final VoidCallback? onReturn;

  const ProductCard({
    super.key,
    required this.product,
    this.isMall = false,
    this.onReturn,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      behavior: HitTestBehavior.opaque,
      onTap: () async {
        await Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => ProductDetailScreen(productId: product.id),
          ),
        );
        onReturn?.call();
      },
      child: Container(
        decoration: BoxDecoration(
          gradient: AppColors.buyerCardGradient,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: AppColors.buyerBorder, width: 1),
          boxShadow: [
            BoxShadow(
              color: AppColors.primaryDark.withValues(alpha: 0.07),
              blurRadius: 6,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Image with discount badge
            Expanded(
              child: Stack(
                fit: StackFit.expand,
                children: [
                  NetworkImageBox(
                    imageUrl: product.imageUrl,
                    height: double.infinity,
                    width: double.infinity,
                    borderRadius: const BorderRadius.vertical(top: Radius.circular(15)),
                  ),
                  if (product.discountPercentage > 0)
                    Positioned(
                      top: 8,
                      left: 8,
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2.5),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFFF1F1),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: AppColors.sale.withValues(alpha: 0.3), width: 0.8),
                        ),
                        child: Text(
                          '${product.discountPercentage}% OFF',
                          style: GoogleFonts.figtree(
                            color: AppColors.sale,
                            fontSize: 9.5,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (product.categoryName != null)
                        Text(
                          product.categoryName!.toUpperCase(),
                          style: GoogleFonts.figtree(
                            fontSize: 9,
                            fontWeight: FontWeight.w700,
                            color: AppColors.mutedText,
                            letterSpacing: 0.5,
                          ),
                        ),
                      const SizedBox(height: 2),
                      RichText(
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        text: TextSpan(
                          children: [
                            if (isMall || (product.sellerStoreName != null && product.sellerStoreName!.isNotEmpty))
                              WidgetSpan(
                                alignment: PlaceholderAlignment.middle,
                                child: Container(
                                  margin: const EdgeInsets.only(right: 4),
                                  padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
                                  decoration: BoxDecoration(
                                    color: AppColors.sale,
                                    borderRadius: BorderRadius.circular(3),
                                  ),
                                  child: Text(
                                    'Mall',
                                    style: GoogleFonts.figtree(
                                      fontSize: 8.5,
                                      fontWeight: FontWeight.w800,
                                      color: Colors.white,
                                      height: 1.1,
                                    ),
                                  ),
                                ),
                              ),
                            TextSpan(
                              text: product.name,
                              style: GoogleFonts.figtree(
                                fontSize: 13,
                                fontWeight: FontWeight.w600,
                                color: AppColors.textPrimary,
                                height: 1.25,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 6),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          if (product.rating > 0) ...[
                            const Icon(Icons.star_rounded, size: 14, color: AppColors.goldAccent),
                            const SizedBox(width: 2),
                            Text(
                              product.rating.toStringAsFixed(1),
                              style: GoogleFonts.figtree(
                                fontSize: 11,
                                fontWeight: FontWeight.w700,
                                color: AppColors.textPrimary,
                              ),
                            ),
                          ],
                          if (product.soldCount > 0) ...[
                            if (product.rating > 0) const SizedBox(width: 4),
                            Text(
                              '${product.rating > 0 ? '• ' : ''}${product.soldCount} sold',
                              style: GoogleFonts.figtree(
                                fontSize: 10,
                                color: AppColors.mutedText,
                              ),
                            ),
                          ] else if (product.reviewsCount > 0) ...[
                            if (product.rating > 0) const SizedBox(width: 4),
                            Text(
                              '(${product.reviewsCount})',
                              style: GoogleFonts.figtree(
                                fontSize: 10,
                                color: AppColors.mutedText,
                              ),
                            ),
                          ],
                        ],
                      ),
                      const SizedBox(height: 4),
                      Wrap(
                        crossAxisAlignment: WrapCrossAlignment.center,
                        spacing: 4,
                        runSpacing: 2,
                        children: [
                          Text(
                            CurrencyFormatter.format(product.effectivePrice),
                            style: GoogleFonts.figtree(
                              fontSize: 15,
                              fontWeight: FontWeight.w800,
                              color: AppColors.alonaTeal,
                            ),
                          ),
                          if (product.discountPercentage > 0) ...[
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 1),
                              decoration: BoxDecoration(
                                color: AppColors.sale.withValues(alpha: 0.12),
                                borderRadius: BorderRadius.circular(3),
                              ),
                              child: Text(
                                '-${product.discountPercentage}%',
                                style: GoogleFonts.figtree(
                                  fontSize: 9.5,
                                  fontWeight: FontWeight.w800,
                                  color: AppColors.sale,
                                ),
                              ),
                            ),
                          ] else if (product.oldPrice != null && product.oldPrice! > product.effectivePrice) ...[
                            Text(
                              CurrencyFormatter.format(product.oldPrice),
                              style: GoogleFonts.figtree(
                                fontSize: 11,
                                decoration: TextDecoration.lineThrough,
                                color: AppColors.mutedText,
                              ),
                            ),
                          ],
                        ],
                      ),
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
}
