import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:alona_mobile/core/config/api_config.dart';
import 'package:alona_mobile/core/config/app_theme.dart';

class NetworkImageBox extends StatelessWidget {
  final String? imageUrl;
  final double? width;
  final double? height;
  final BoxFit fit;
  final BorderRadius? borderRadius;

  const NetworkImageBox({
    super.key,
    required this.imageUrl,
    this.width,
    this.height,
    this.fit = BoxFit.cover,
    this.borderRadius,
  });

  @override
  Widget build(BuildContext context) {
    final effectiveRadius = borderRadius ?? BorderRadius.circular(12);
    final fullUrl = ApiConfig.imageUrl(imageUrl);

    if (fullUrl.isEmpty) {
      return _buildFallback(effectiveRadius);
    }

    return ClipRRect(
      borderRadius: effectiveRadius,
      child: CachedNetworkImage(
        imageUrl: fullUrl,
        width: width,
        height: height,
        fit: fit,
        placeholder: (context, url) => Container(
          width: width,
          height: height,
          color: AppColors.surfaceAlt,
          child: const Center(
            child: SizedBox(
              width: 20,
              height: 20,
              child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.primary),
            ),
          ),
        ),
        errorWidget: (context, url, error) => _buildFallback(effectiveRadius),
      ),
    );
  }

  Widget _buildFallback(BorderRadius radius) {
    return Container(
      width: width,
      height: height,
      decoration: BoxDecoration(
        color: AppColors.surfaceAlt,
        borderRadius: radius,
      ),
      child: Center(
        child: Image.asset(
          'assets/images/alona-icon.png',
          width: 32,
          height: 32,
          fit: BoxFit.contain,
          errorBuilder: (_, _, _) => const Icon(
            Icons.shopping_bag_outlined,
            color: AppColors.textMuted,
            size: 24,
          ),
        ),
      ),
    );
  }
}
