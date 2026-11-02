import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/services/auth_state.dart';
import 'package:alona_mobile/features/buyer/presentation/auth/buyer_login_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/auth/buyer_register_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/buyer_main_screen.dart';

class BuyerSplashWelcomeScreen extends StatefulWidget {
  final AuthState authState;

  const BuyerSplashWelcomeScreen({
    super.key,
    required this.authState,
  });

  @override
  State<BuyerSplashWelcomeScreen> createState() =>
      _BuyerSplashWelcomeScreenState();
}

class _BuyerSplashWelcomeScreenState extends State<BuyerSplashWelcomeScreen>
    with TickerProviderStateMixin {
  static const Color _tealCardColor = Color(0xFF117E8B);
  static const Color _goldButtonColor = Color(0xFFF3B84B);
  static const Color _goldTextColor = Color(0xFFF3B340);
  static const Color _darkTextColor = Color(0xFF132228);

  // Smooth Apple/iOS-style deceleration curves for silky 120fps swipe-up
  static const Cubic _smoothOutCubic = Cubic(0.22, 1.0, 0.36, 1.0);
  static const Cubic _cardSpringCubic = Cubic(0.16, 1.0, 0.30, 1.0);

  late final AnimationController _splashController;
  late final AnimationController _welcomeController;

  // Stage 1 (0.00 - 0.28): 'a' shopping bag icon fades & scales in smoothly at center
  late final Animation<double> _iconOpacity;
  late final Animation<double> _iconScale;

  // Stage 2 (0.32 - 0.70): 'a' glides left while 'lona' wipes in smoothly
  late final Animation<double> _wordmarkReveal;

  // Stage 3 (0.68 - 0.96): '— Shop More • Live Better —' fades in beneath 'alona'
  late final Animation<double> _taglineOpacity;

  // Stage 4: GPU-accelerated upward glide of logo + Welcome card swipe-up
  late final Animation<double> _logoUpProgress;
  late final Animation<double> _cardUpProgress;
  late final Animation<double> _cardContentOpacity;
  late final Animation<double> _cardContentFloat;

  bool _imagesPrecached = false;
  bool _welcomeInteractive = false;
  bool _enterMainApp = false;
  late final Widget _cachedWelcomeCard;

  @override
  void initState() {
    super.initState();

    _splashController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 3500),
    );

    _welcomeController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1150),
    );

    _iconOpacity = CurvedAnimation(
      parent: _splashController,
      curve: const Interval(0.0, 0.28, curve: Curves.easeInOutCubic),
    );

    _iconScale = Tween<double>(begin: 0.90, end: 1.0).animate(
      CurvedAnimation(
        parent: _splashController,
        curve: const Interval(0.0, 0.30, curve: _smoothOutCubic),
      ),
    );

    _wordmarkReveal = CurvedAnimation(
      parent: _splashController,
      curve: const Interval(0.32, 0.70, curve: Curves.easeInOutCubic),
    );

    _taglineOpacity = CurvedAnimation(
      parent: _splashController,
      curve: const Interval(0.68, 0.96, curve: Curves.easeInOut),
    );

    // Logo starts rising smoothly right at 0.0 of welcomeController
    _logoUpProgress = CurvedAnimation(
      parent: _welcomeController,
      curve: const Interval(0.0, 0.88, curve: _smoothOutCubic),
    );

    // Teal card follows smoothly right behind the rising logo
    _cardUpProgress = CurvedAnimation(
      parent: _welcomeController,
      curve: const Interval(0.04, 1.0, curve: _cardSpringCubic),
    );

    _cardContentOpacity = CurvedAnimation(
      parent: _welcomeController,
      curve: const Interval(0.18, 0.92, curve: Curves.easeOut),
    );

    _cardContentFloat = Tween<double>(begin: 20.0, end: 0.0).animate(
      CurvedAnimation(
        parent: _welcomeController,
        curve: const Interval(0.16, 1.0, curve: _smoothOutCubic),
      ),
    );

    // Pre-build the welcome card once so it never triggers layout rebuilds during swipe-up
    _cachedWelcomeCard = RepaintBoundary(
      child: _buildWelcomeCardContent(),
    );
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_imagesPrecached) {
      _imagesPrecached = true;
      _precacheAndStart();
    }
  }

  Future<void> _precacheAndStart() async {
    try {
      await Future.wait([
        precacheImage(const AssetImage('assets/images/alona.png'), context),
        precacheImage(const AssetImage('assets/images/Alogo.png'), context),
      ]);
    } catch (_) {}

    if (!mounted) return;

    // If session changed after initial app launch (e.g., user just logged in or signed out),
    // skip replaying the cold-start splash animation.
    if (widget.authState.sessionRevision > 0) {
      _splashController.value = 1.0;
      _welcomeController.value = 1.0;
      setState(() {
        if (widget.authState.isBuyerAuthenticated) {
          _enterMainApp = true;
        } else {
          _welcomeInteractive = true;
        }
      });
      return;
    }

    await _splashController.forward();
    if (!mounted) return;

    if (widget.authState.isBuyerAuthenticated) {
      await Future.delayed(const Duration(milliseconds: 200));
      if (!mounted) return;
      setState(() {
        _enterMainApp = true;
      });
    } else {
      setState(() {
        _welcomeInteractive = true;
      });
      await _welcomeController.forward();
    }
  }

  @override
  void dispose() {
    _splashController.dispose();
    _welcomeController.dispose();
    super.dispose();
  }

  Future<void> _openCreateAccount() async {
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => BuyerRegisterScreen(authState: widget.authState),
      ),
    );
    if (mounted && widget.authState.isBuyerAuthenticated) {
      setState(() {
        _enterMainApp = true;
      });
    }
  }

  Future<void> _openLogin() async {
    final result = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => BuyerLoginScreen(authState: widget.authState),
      ),
    );
    if (mounted && (result == true || widget.authState.isBuyerAuthenticated)) {
      setState(() {
        _enterMainApp = true;
      });
    }
  }

  void _enterAsGuest() {
    setState(() {
      _enterMainApp = true;
    });
  }

  @override
  Widget build(BuildContext context) {
    if (_enterMainApp) {
      return BuyerMainScreen(authState: widget.authState);
    }

    final size = MediaQuery.of(context).size;

    // Fixed base dimensions for logo so image never re-layouts during swipe-up;
    // size transitions are handled 100% by GPU Transform.scale.
    final baseLogoWidth = (size.width * 0.68).clamp(230.0, 290.0);
    final baseLogoHeight = baseLogoWidth / 3.0;
    final targetLogoScale = 0.82; // Scales down cleanly to header size

    // Vertical positions
    final centerTop = (size.height * 0.43) - (baseLogoHeight / 2);
    final headerTop = (size.height * 0.042).clamp(18.0, 44.0);
    final cardTop = headerTop + (baseLogoHeight * targetLogoScale) + 22;
    final offscreenCardDistance = (size.height - cardTop) + 48.0;

    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        bottom: false,
        child: AnimatedBuilder(
          animation: Listenable.merge([_splashController, _welcomeController]),
          builder: (context, _) {
            final logoUp = _logoUpProgress.value;
            final cardUp = _cardUpProgress.value;

            final currentLogoTop = lerpDouble(centerTop, headerTop, logoUp)!;
            final currentLogoScale =
                _iconScale.value * lerpDouble(1.0, targetLogoScale, logoUp)!;

            // Progressive reveal math for alona.png (2172 x 724):
            final r = _wordmarkReveal.value;
            final leftClipFraction = 0.080 * (1.0 - r);
            final rightClipFraction = 0.328 + (1.0 - 0.328) * r;
            final horizontalShift = (1.0 - r) * 0.296 * baseLogoWidth;

            final cardTranslateY = (1.0 - cardUp) * offscreenCardDistance;

            return Stack(
              clipBehavior: Clip.none,
              children: [
                // GPU-accelerated Alona Logo + Tagline
                Positioned(
                  top: 0,
                  left: 0,
                  right: 0,
                  child: Transform.translate(
                    offset: Offset(0, currentLogoTop),
                    child: Center(
                      child: Transform.scale(
                        scale: currentLogoScale,
                        alignment: Alignment.topCenter,
                        child: Opacity(
                          opacity: _iconOpacity.value,
                          child: RepaintBoundary(
                            child: SizedBox(
                              width: baseLogoWidth,
                              height: baseLogoHeight,
                              child: Stack(
                                children: [
                                  // Layer 1: 'alona' wordmark (top 86% of alona.png)
                                  Transform.translate(
                                    offset: Offset(horizontalShift, 0),
                                    child: ClipRect(
                                      clipper: _SubRectClipper(
                                        leftFraction: leftClipFraction,
                                        topFraction: 0.0,
                                        rightFraction: rightClipFraction,
                                        bottomFraction: 0.86,
                                      ),
                                      child: Image.asset(
                                        'assets/images/alona.png',
                                        width: baseLogoWidth,
                                        height: baseLogoHeight,
                                        fit: BoxFit.fill,
                                        filterQuality: FilterQuality.high,
                                      ),
                                    ),
                                  ),

                                  // Layer 2: '— Shop More • Live Better —' tagline (bottom 14% of alona.png)
                                  Opacity(
                                    opacity: _taglineOpacity.value,
                                    child: ClipRect(
                                      clipper: const _SubRectClipper(
                                        leftFraction: 0.0,
                                        topFraction: 0.86,
                                        rightFraction: 1.0,
                                        bottomFraction: 1.0,
                                      ),
                                      child: Image.asset(
                                        'assets/images/alona.png',
                                        width: baseLogoWidth,
                                        height: baseLogoHeight,
                                        fit: BoxFit.fill,
                                        filterQuality: FilterQuality.high,
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
                  ),
                ),

                // Pre-mounted GPU-accelerated Welcome to Alona Bottom Card
                Positioned(
                  top: cardTop,
                  left: 8,
                  right: 8,
                  bottom: 8,
                  child: IgnorePointer(
                    ignoring: !_welcomeInteractive,
                    child: Transform.translate(
                      offset: Offset(0, cardTranslateY),
                      child: _cachedWelcomeCard,
                    ),
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }

  Widget _buildWelcomeCardContent() {
    return Container(
      decoration: BoxDecoration(
        color: _tealCardColor,
        borderRadius: BorderRadius.circular(28),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.10),
            blurRadius: 24,
            offset: const Offset(0, -4),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Stack(
        children: [
          // Top-right decorative soft circle matching video
          Positioned(
            top: -12,
            right: -38,
            child: Container(
              width: 190,
              height: 190,
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.08),
                shape: BoxShape.circle,
              ),
            ),
          ),

          // Bottom-left decorative soft circle matching video
          Positioned(
            bottom: 48,
            left: -72,
            child: Container(
              width: 205,
              height: 205,
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.08),
                shape: BoxShape.circle,
              ),
            ),
          ),

          // Main card content with subtle upward settle
          LayoutBuilder(
            builder: (context, constraints) {
              return SingleChildScrollView(
                physics: const BouncingScrollPhysics(),
                child: ConstrainedBox(
                  constraints: BoxConstraints(minHeight: constraints.maxHeight),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 24,
                      vertical: 28,
                    ),
                    child: AnimatedBuilder(
                      animation: _welcomeController,
                      builder: (context, child) {
                        return Transform.translate(
                          offset: Offset(0, _cardContentFloat.value),
                          child: Opacity(
                            opacity: _cardContentOpacity.value,
                            child: child,
                          ),
                        );
                      },
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          // White circle badge with 'a' shopping bag icon
                          Container(
                            width: 124,
                            height: 124,
                            decoration: const BoxDecoration(
                              color: Colors.white,
                              shape: BoxShape.circle,
                            ),
                            padding: const EdgeInsets.all(23),
                            child: Image.asset(
                              'assets/images/Alogo.png',
                              fit: BoxFit.contain,
                              filterQuality: FilterQuality.high,
                            ),
                          ),

                          const SizedBox(height: 26),

                          // "Welcome to Alona."
                          RichText(
                            textAlign: TextAlign.center,
                            text: TextSpan(
                              style: GoogleFonts.figtree(
                                fontSize: 27,
                                fontWeight: FontWeight.w800,
                                letterSpacing: -0.3,
                              ),
                              children: const [
                                TextSpan(
                                  text: 'Welcome to ',
                                  style: TextStyle(color: Colors.white),
                                ),
                                TextSpan(
                                  text: 'Alona.',
                                  style: TextStyle(color: _goldTextColor),
                                ),
                              ],
                            ),
                          ),

                          const SizedBox(height: 12),

                          // Subtitle
                          Text(
                            'Everything you love, all in one place.\nQuality products, trusted sellers, and\ngreat deals made for you.',
                            textAlign: TextAlign.center,
                            style: GoogleFonts.figtree(
                              fontSize: 14,
                              fontWeight: FontWeight.w500,
                              color: Colors.white.withValues(alpha: 0.92),
                              height: 1.42,
                            ),
                          ),

                          const SizedBox(height: 30),

                          // "Create account" gold button
                          SizedBox(
                            width: double.infinity,
                            height: 50,
                            child: ElevatedButton(
                              onPressed: _openCreateAccount,
                              style: ElevatedButton.styleFrom(
                                backgroundColor: _goldButtonColor,
                                foregroundColor: _darkTextColor,
                                elevation: 0,
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(12),
                                ),
                              ),
                              child: Text(
                                'Create account',
                                style: GoogleFonts.figtree(
                                  fontSize: 15,
                                  fontWeight: FontWeight.w700,
                                  color: _darkTextColor,
                                ),
                              ),
                            ),
                          ),

                          const SizedBox(height: 14),

                          // "Log in" outlined white button
                          SizedBox(
                            width: double.infinity,
                            height: 50,
                            child: OutlinedButton(
                              onPressed: _openLogin,
                              style: OutlinedButton.styleFrom(
                                foregroundColor: Colors.white,
                                side: const BorderSide(
                                  color: Colors.white,
                                  width: 1.3,
                                ),
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(12),
                                ),
                              ),
                              child: Text(
                                'Log in',
                                style: GoogleFonts.figtree(
                                  fontSize: 15,
                                  fontWeight: FontWeight.w700,
                                  color: Colors.white,
                                ),
                              ),
                            ),
                          ),

                          const SizedBox(height: 22),

                          // "Continue as guest" underlined button
                          GestureDetector(
                            onTap: _enterAsGuest,
                            behavior: HitTestBehavior.opaque,
                            child: Padding(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 12,
                                vertical: 6,
                              ),
                              child: Text(
                                'Continue as guest',
                                style: GoogleFonts.figtree(
                                  fontSize: 13.5,
                                  fontWeight: FontWeight.w600,
                                  color: Colors.white,
                                  decoration: TextDecoration.underline,
                                  decorationColor: Colors.white,
                                  decorationThickness: 1.3,
                                ),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              );
            },
          ),
        ],
      ),
    );
  }
}

class _SubRectClipper extends CustomClipper<Rect> {
  final double leftFraction;
  final double topFraction;
  final double rightFraction;
  final double bottomFraction;

  const _SubRectClipper({
    required this.leftFraction,
    required this.topFraction,
    required this.rightFraction,
    required this.bottomFraction,
  });

  @override
  Rect getClip(Size size) {
    return Rect.fromLTRB(
      size.width * leftFraction,
      size.height * topFraction,
      size.width * rightFraction,
      size.height * bottomFraction,
    );
  }

  @override
  bool shouldReclip(covariant _SubRectClipper oldClipper) {
    return oldClipper.leftFraction != leftFraction ||
        oldClipper.topFraction != topFraction ||
        oldClipper.rightFraction != rightFraction ||
        oldClipper.bottomFraction != bottomFraction;
  }
}
