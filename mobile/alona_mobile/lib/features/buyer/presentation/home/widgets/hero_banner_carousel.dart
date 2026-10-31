import 'dart:async';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/features/buyer/models/buyer_models.dart';
import 'package:alona_mobile/shared/widgets/network_image_box.dart';

class HeroBannerCarousel extends StatefulWidget {
  final List<HeroBannerModel> banners;
  final HeroStatsModel? stats;
  final VoidCallback? onShopNow;
  final VoidCallback? onExploreCategories;

  const HeroBannerCarousel({
    super.key,
    required this.banners,
    this.stats,
    this.onShopNow,
    this.onExploreCategories,
  });

  @override
  State<HeroBannerCarousel> createState() => _HeroBannerCarouselState();
}

class _HeroBannerCarouselState extends State<HeroBannerCarousel> {
  late final PageController _pageController;
  int _currentIndex = 0;
  Timer? _autoScrollTimer;

  @override
  void initState() {
    super.initState();
    _pageController = PageController();
    _startTimer();
  }

  @override
  void didUpdateWidget(covariant HeroBannerCarousel oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.banners.length != widget.banners.length) {
      _startTimer();
    }
  }

  void _startTimer() {
    _autoScrollTimer?.cancel();
    if (widget.banners.length > 1) {
      _autoScrollTimer = Timer.periodic(const Duration(seconds: 5), (_) {
        if (!mounted || !_pageController.hasClients) return;
        final next = (_currentIndex + 1) % widget.banners.length;
        _pageController.animateToPage(
          next,
          duration: const Duration(milliseconds: 400),
          curve: Curves.easeInOutCubic,
        );
      });
    }
  }

  @override
  void dispose() {
    _autoScrollTimer?.cancel();
    _pageController.dispose();
    super.dispose();
  }

  List<Color> _getThemeGradient(String theme) {
    switch (theme.toLowerCase()) {
      case 'amber':
        return const [Color(0xFF78350F), Color(0xFFD97706), Color(0xFFF59E0B)];
      case 'indigo':
        return const [Color(0xFF1E1B4B), Color(0xFF3730A3), Color(0xFF4F46E5)];
      case 'rose':
        return const [Color(0xFF881337), Color(0xFFBE123C), Color(0xFFE11D48)];
      case 'emerald':
        return const [Color(0xFF064E3B), Color(0xFF047857), Color(0xFF10B981)];
      case 'dark':
        return const [Color(0xFF0F172A), Color(0xFF1E293B), Color(0xFF334155)];
      case 'teal':
      default:
        return const [Color(0xFF044D55), Color(0xFF087F8C), Color(0xFF139691)];
    }
  }

  String _resolveBadgeText(HeroBannerModel banner) {
    if (banner.badgeText.trim().isNotEmpty) {
      return banner.badgeText;
    }
    final pCount = widget.stats?.productsCount ?? 5;
    final cCount = widget.stats?.categoriesCount ?? 12;
    return '$pCount PRODUCTS • $cCount CATEGORIES';
  }

  @override
  Widget build(BuildContext context) {
    final activeBanners = widget.banners.where((b) => b.isActive).toList();
    if (activeBanners.isEmpty) {
      return const SizedBox.shrink();
    }

    return Column(
      children: [
        SizedBox(
          height: 250,
          child: PageView.builder(
            controller: _pageController,
            itemCount: activeBanners.length,
            onPageChanged: (idx) {
              setState(() => _currentIndex = idx);
            },
            itemBuilder: (context, index) {
              final banner = activeBanners[index];
              return _buildBannerSlide(banner);
            },
          ),
        ),

        // Indicator dots & optional arrow controls
        if (activeBanners.length > 1) ...[
          const SizedBox(height: 10),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: List.generate(activeBanners.length, (idx) {
              final isSelected = _currentIndex == idx;
              return AnimatedContainer(
                duration: const Duration(milliseconds: 250),
                margin: const EdgeInsets.symmetric(horizontal: 3),
                height: 5,
                width: isSelected ? 20 : 6,
                decoration: BoxDecoration(
                  color: isSelected ? AppColors.primary : AppColors.border,
                  borderRadius: BorderRadius.circular(3),
                ),
              );
            }),
          ),
        ],
      ],
    );
  }

  Widget _buildBannerSlide(HeroBannerModel banner) {
    final colors = _getThemeGradient(banner.theme);

    if (banner.layoutType == 'full' && (banner.imageUrl != null || banner.imagePath.isNotEmpty)) {
      return _buildFullBanner(banner, colors);
    }

    return _buildSplitBanner(banner, colors);
  }

  Widget _buildFullBanner(HeroBannerModel banner, List<Color> colors) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 2),
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.08),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Stack(
        fit: StackFit.expand,
        children: [
          // Banner Image
          NetworkImageBox(
            imageUrl: banner.imageUrl ?? banner.imagePath,
            fit: BoxFit.cover,
            borderRadius: BorderRadius.circular(20),
          ),

          // Optional Text Overlay
          if (banner.overlayText)
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [
                    Colors.black.withValues(alpha: 0.75),
                    Colors.black.withValues(alpha: 0.35),
                    Colors.transparent,
                  ],
                  begin: Alignment.bottomCenter,
                  end: Alignment.topCenter,
                ),
              ),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.end,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (banner.badgeText.isNotEmpty)
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      margin: const EdgeInsets.only(bottom: 6),
                      decoration: BoxDecoration(
                        color: AppColors.accent,
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Text(
                        banner.badgeText,
                        style: GoogleFonts.figtree(
                          color: Colors.white,
                          fontSize: 9,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                  Text(
                    '${banner.title} ${banner.titleHighlight}',
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: GoogleFonts.figtree(
                      fontSize: 18,
                      fontWeight: FontWeight.w900,
                      color: Colors.white,
                    ),
                  ),
                  if (banner.description.isNotEmpty) ...[
                    const SizedBox(height: 3),
                    Text(
                      banner.description,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: GoogleFonts.figtree(
                        fontSize: 11,
                        color: Colors.white.withValues(alpha: 0.9),
                      ),
                    ),
                  ],
                  if (banner.showPrimaryButton && banner.primaryButtonText.isNotEmpty) ...[
                    const SizedBox(height: 10),
                    GestureDetector(
                      onTap: widget.onShopNow,
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Text(
                          banner.primaryButtonText,
                          style: GoogleFonts.figtree(
                            color: AppColors.primary,
                            fontSize: 12,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildSplitBanner(HeroBannerModel banner, List<Color> colors) {
    final badge = _resolveBadgeText(banner);

    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 2),
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: colors,
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(22),
        boxShadow: [
          BoxShadow(
            color: colors.first.withValues(alpha: 0.25),
            blurRadius: 14,
            offset: const Offset(0, 5),
          ),
        ],
      ),
      child: Stack(
        children: [
          // Background decorative rings
          Positioned(
            right: -25,
            top: -20,
            child: Container(
              width: 170,
              height: 170,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.white.withValues(alpha: 0.06),
              ),
            ),
          ),
          Positioned(
            right: 15,
            bottom: -35,
            child: Container(
              width: 130,
              height: 130,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(color: Colors.white.withValues(alpha: 0.08), width: 1.5),
              ),
            ),
          ),

          // Right side mascot illustration & floating cards
          if (banner.showImage)
            Positioned(
              right: 8,
              top: 18,
              bottom: 18,
              width: 130,
              child: Stack(
                alignment: Alignment.center,
                clipBehavior: Clip.none,
                children: [
                  // Mascot image
                  Center(
                    child: NetworkImageBox(
                      imageUrl: banner.imageUrl ?? banner.imagePath,
                      width: 105,
                      height: 105,
                      fit: BoxFit.contain,
                    ),
                  ),

                  // Floating Card 1 (Products)
                  if (banner.showFloatingCards)
                    Positioned(
                      top: 4,
                      right: 0,
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.95),
                          borderRadius: BorderRadius.circular(8),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.1),
                              blurRadius: 6,
                              offset: const Offset(0, 2),
                            ),
                          ],
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Container(
                              padding: const EdgeInsets.all(2.5),
                              decoration: BoxDecoration(
                                color: const Color(0xFFE8F7F6),
                                borderRadius: BorderRadius.circular(4),
                              ),
                              child: const Icon(Icons.inventory_2_outlined, size: 11, color: Color(0xFF087F8C)),
                            ),
                            const SizedBox(width: 4),
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Text(
                                  banner.card1Title.toUpperCase(),
                                  style: GoogleFonts.figtree(fontSize: 7, fontWeight: FontWeight.w700, color: Colors.grey.shade600),
                                ),
                                Text(
                                  '${widget.stats?.productsCount ?? 5} ${banner.card1Subtitle}',
                                  style: GoogleFonts.figtree(fontSize: 8, fontWeight: FontWeight.w800, color: Colors.black87),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ),

                  // Floating Card 2 (Categories)
                  if (banner.showFloatingCards)
                    Positioned(
                      bottom: 4,
                      left: 0,
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.95),
                          borderRadius: BorderRadius.circular(8),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.1),
                              blurRadius: 6,
                              offset: const Offset(0, 2),
                            ),
                          ],
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Container(
                              padding: const EdgeInsets.all(2.5),
                              decoration: BoxDecoration(
                                color: const Color(0xFFF4B942),
                                borderRadius: BorderRadius.circular(4),
                              ),
                              child: const Icon(Icons.grid_view_rounded, size: 11, color: Colors.white),
                            ),
                            const SizedBox(width: 4),
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Text(
                                  banner.card2Title.toUpperCase(),
                                  style: GoogleFonts.figtree(fontSize: 7, fontWeight: FontWeight.w700, color: Colors.grey.shade600),
                                ),
                                Text(
                                  '${widget.stats?.categoriesCount ?? 12} ${banner.card2Subtitle}',
                                  style: GoogleFonts.figtree(fontSize: 8, fontWeight: FontWeight.w800, color: Colors.black87),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ),
                ],
              ),
            ),

          // Left side Content (Text, Badge, Buttons)
          Padding(
            padding: EdgeInsets.only(
              left: 18,
              top: 16,
              bottom: 16,
              right: banner.showImage ? 135 : 18,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    // Badge
                    Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Container(
                          width: 20,
                          height: 20,
                          decoration: const BoxDecoration(
                            shape: BoxShape.circle,
                            color: Color(0xFFF4B942),
                          ),
                          child: const Icon(Icons.storefront_rounded, size: 12, color: Color(0xFF087F8C)),
                        ),
                        const SizedBox(width: 6),
                        Flexible(
                          child: Text(
                            badge,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: GoogleFonts.figtree(
                              fontSize: 9,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 0.8,
                              color: Colors.white.withValues(alpha: 0.95),
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),

                    // Title with Highlight
                    RichText(
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      text: TextSpan(
                        style: GoogleFonts.figtree(
                          fontSize: 20,
                          fontWeight: FontWeight.w900,
                          color: Colors.white,
                          height: 1.1,
                          letterSpacing: -0.4,
                        ),
                        children: [
                          TextSpan(text: '${banner.title} '),
                          TextSpan(
                            text: banner.titleHighlight,
                            style: const TextStyle(color: Color(0xFFF4B942)),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 6),

                    // Description
                    Text(
                      banner.description,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: GoogleFonts.figtree(
                        fontSize: 11,
                        color: Colors.white.withValues(alpha: 0.88),
                        height: 1.3,
                      ),
                    ),
                  ],
                ),

                // Buttons row
                if ((banner.showPrimaryButton && banner.primaryButtonText.isNotEmpty) ||
                    (banner.showSecondaryButton && banner.secondaryButtonText.isNotEmpty))
                  Padding(
                    padding: const EdgeInsets.only(top: 8),
                    child: Wrap(
                      spacing: 8,
                      runSpacing: 6,
                      children: [
                        // Primary Button
                        if (banner.showPrimaryButton && banner.primaryButtonText.isNotEmpty)
                          Material(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(12),
                            elevation: 3,
                            shadowColor: Colors.black26,
                            child: InkWell(
                              onTap: widget.onShopNow,
                              borderRadius: BorderRadius.circular(12),
                              child: Padding(
                                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                                child: Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    Text(
                                      banner.primaryButtonText,
                                      style: GoogleFonts.figtree(
                                        color: const Color(0xFF087F8C),
                                        fontSize: 12,
                                        fontWeight: FontWeight.w800,
                                      ),
                                    ),
                                    if (banner.showPrimaryButtonArrow) ...[
                                      const SizedBox(width: 5),
                                      Container(
                                        width: 16,
                                        height: 16,
                                        decoration: const BoxDecoration(
                                          shape: BoxShape.circle,
                                          color: Color(0xFFE8F7F6),
                                        ),
                                        child: const Icon(Icons.arrow_forward_rounded, size: 10, color: Color(0xFF087F8C)),
                                      ),
                                    ],
                                  ],
                                ),
                              ),
                            ),
                          ),

                        // Secondary Button
                        if (banner.showSecondaryButton && banner.secondaryButtonText.isNotEmpty)
                          Material(
                            color: Colors.transparent,
                            child: InkWell(
                              onTap: widget.onExploreCategories,
                              borderRadius: BorderRadius.circular(12),
                              child: Container(
                                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                                decoration: BoxDecoration(
                                  border: Border.all(color: Colors.white.withValues(alpha: 0.6), width: 1.2),
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: Text(
                                  banner.secondaryButtonText,
                                  style: GoogleFonts.figtree(
                                    color: Colors.white,
                                    fontSize: 12,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

