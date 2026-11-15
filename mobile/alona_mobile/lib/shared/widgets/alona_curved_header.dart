import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

/// Shared curved teal header background matching the Rider Pickups header design
/// (deep teal gradient, soft top-right decorative circle, and 24px rounded bottom corners
/// extending behind the top section of the page content).
class AlonaCurvedHeader {
  static const Color teal = Color(0xFF007A87);
  static const Color tealDark = Color(0xFF005C66);
  static const Color amberCircle = Color(0xFFFDB813);

  static const LinearGradient headerGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [tealDark, teal, tealDark],
  );

  static const SystemUiOverlayStyle lightStatusBar = SystemUiOverlayStyle(
    statusBarColor: Colors.transparent,
    statusBarIconBrightness: Brightness.light,
    statusBarBrightness: Brightness.dark,
    systemStatusBarContrastEnforced: false,
  );
}

/// Used inside `AppBar(flexibleSpace: const AlonaCurvedHeaderSurface(), ...)`
/// Renders the top portion of the unified teal gradient and top-right ambient circle.
class AlonaCurvedHeaderSurface extends StatelessWidget {
  final double overlapHeight;
  final double bottomBarHeight;

  const AlonaCurvedHeaderSurface({
    super.key,
    this.overlapHeight = 54.0,
    this.bottomBarHeight = 0.0,
  });

  @override
  Widget build(BuildContext context) {
    final topPadding = MediaQuery.viewPaddingOf(context).top;
    final appBarHeight = topPadding + kToolbarHeight + bottomBarHeight;
    final totalHeight = appBarHeight + overlapHeight;

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: AlonaCurvedHeader.lightStatusBar,
      child: ClipRect(
        child: OverflowBox(
          alignment: Alignment.topCenter,
          minHeight: totalHeight,
          maxHeight: totalHeight,
          child: Container(
            width: double.infinity,
            height: totalHeight,
            decoration: const BoxDecoration(
              gradient: AlonaCurvedHeader.headerGradient,
            ),
            child: Stack(
              clipBehavior: Clip.none,
              children: [
                Positioned(
                  top: topPadding - 90,
                  right: -70,
                  child: Container(
                    width: 200,
                    height: 200,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: AlonaCurvedHeader.amberCircle.withValues(alpha: 0.08),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Wraps a page's `body` so the curved teal header background (with 24px rounded bottom corners
/// and the continuation of the gradient/circle) extends down behind the top content of the page.
class AlonaCurvedHeaderBody extends StatelessWidget {
  final Widget child;
  final double overlapHeight;
  final bool includeTopSafeArea;
  final double bottomBarHeight;

  const AlonaCurvedHeaderBody({
    super.key,
    required this.child,
    this.overlapHeight = 54.0,
    this.includeTopSafeArea = false,
    this.bottomBarHeight = 0.0,
  });

  @override
  Widget build(BuildContext context) {
    final topPadding = MediaQuery.viewPaddingOf(context).top;
    final appBarOffset = includeTopSafeArea ? 0.0 : (topPadding + kToolbarHeight + bottomBarHeight);
    final visibleBackdropHeight = includeTopSafeArea
        ? (topPadding + overlapHeight)
        : overlapHeight;
    final totalHeight = appBarOffset + visibleBackdropHeight;

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: AlonaCurvedHeader.lightStatusBar,
      child: Stack(
        children: [
          Positioned(
            top: 0,
            left: 0,
            right: 0,
            height: visibleBackdropHeight,
            child: IgnorePointer(
              child: ClipRRect(
                borderRadius: const BorderRadius.only(
                  bottomLeft: Radius.circular(24),
                  bottomRight: Radius.circular(24),
                ),
                child: OverflowBox(
                  alignment: Alignment.bottomCenter,
                  minHeight: totalHeight,
                  maxHeight: totalHeight,
                  child: Container(
                    width: double.infinity,
                    height: totalHeight,
                    decoration: const BoxDecoration(
                      gradient: AlonaCurvedHeader.headerGradient,
                    ),
                    child: Stack(
                      clipBehavior: Clip.none,
                      children: [
                        Positioned(
                          top: topPadding - 90,
                          right: -70,
                          child: Container(
                            width: 200,
                            height: 200,
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              color: AlonaCurvedHeader.amberCircle.withValues(alpha: 0.08),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ),
          child,
        ],
      ),
    );
  }
}
