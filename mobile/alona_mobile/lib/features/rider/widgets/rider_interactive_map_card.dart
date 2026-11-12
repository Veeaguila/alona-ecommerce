import 'dart:math' as math;

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:geolocator/geolocator.dart';

import '../services/rider_quick_actions.dart';

class RiderInteractiveMapCard extends StatefulWidget {
  final String destinationAddress;
  final String destinationName;
  final double? destinationLat;
  final double? destinationLng;
  final double height;
  final VoidCallback? onOpenExternalMap;
  final bool fullscreen;

  const RiderInteractiveMapCard({
    super.key,
    required this.destinationAddress,
    this.destinationName = 'Delivery Destination',
    this.destinationLat,
    this.destinationLng,
    this.height = 230,
    this.onOpenExternalMap,
    this.fullscreen = false,
  });

  @override
  State<RiderInteractiveMapCard> createState() => _RiderInteractiveMapCardState();
}

class _RiderInteractiveMapCardState extends State<RiderInteractiveMapCard> {
  // Current map center & zoom level
  late double _centerLat;
  late double _centerLng;
  int _zoom = 14; // Default city zoom

  // Rider's Live GPS Position
  Position? _riderPosition;
  bool _locatingRider = false;
  String? _distanceText;
  String? _etaText;

  final TransformationController _transformController = TransformationController();

  // Known Coordinates for Laguna Towns (30) and Metro Manila Cities (17)
  static const Map<String, List<double>> _townCoordinates = {
    // Laguna Towns
    'santa rosa': [14.3122, 121.1114],
    'calamba': [14.2117, 121.1656],
    'biñan': [14.3333, 121.0833],
    'binan': [14.3333, 121.0833],
    'san pedro': [14.3592, 121.0544],
    'cabuyao': [14.2786, 121.1250],
    'los baños': [14.1706, 121.2422],
    'los banos': [14.1706, 121.2422],
    'san pablo': [14.0683, 121.3256],
    'santa cruz': [14.2800, 121.4170],
    'sta cruz': [14.2800, 121.4170],
    'bay': [14.1819, 121.2847],
    'pagsanjan': [14.2750, 121.4550],
    'alaminos': [14.0667, 121.2500],
    'calauan': [14.1500, 121.3167],
    'victoria': [14.2250, 121.3278],
    'lumban': [14.3000, 121.4600],
    'paete': [14.3667, 121.4833],
    'siniloan': [14.4167, 121.4500],
    'mabitac': [14.4250, 121.4250],
    'pangil': [14.4000, 121.4800],
    'pakil': [14.3800, 121.4800],
    'kalayaan': [14.3500, 121.5700],
    'cavinti': [14.2500, 121.5100],
    'luisiana': [14.1800, 121.5200],
    'majayjay': [14.1400, 121.5100],
    'liliw': [14.1300, 121.4300],
    'nagcarlan': [14.1400, 121.4200],
    'rizal': [14.1100, 121.4000],
    'magdalena': [14.2000, 121.4300],
    'pila': [14.2300, 121.3600],
    'famy': [14.4300, 121.4500],
    'santa maria': [14.4700, 121.4200],

    // Metro Manila Cities
    'quezon city': [14.6760, 121.0437],
    'manila': [14.5995, 120.9842],
    'makati': [14.5547, 121.0244],
    'taguig': [14.5176, 121.0509],
    'pasig': [14.5764, 121.0851],
    'parañaque': [14.4793, 121.0198],
    'paranaque': [14.4793, 121.0198],
    'las piñas': [14.4445, 120.9939],
    'las pinas': [14.4445, 120.9939],
    'muntinlupa': [14.4081, 121.0415],
    'pasay': [14.5378, 120.9997],
    'mandaluyong': [14.5794, 121.0359],
    'marikina': [14.6507, 121.1029],
    'caloocan': [14.6416, 120.9762],
    'malabon': [14.6625, 120.9570],
    'navotas': [14.6667, 120.9417],
    'valenzuela': [14.7011, 120.9830],
    'san juan': [14.6019, 121.0355],
    'pateros': [14.5456, 121.0686],
  };

  @override
  void initState() {
    super.initState();
    _resolveDestinationCoordinates();
    _fetchRiderLocation();
  }

  @override
  void dispose() {
    _transformController.dispose();
    super.dispose();
  }

  @override
  void didUpdateWidget(covariant RiderInteractiveMapCard oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.destinationAddress != widget.destinationAddress) {
      _resolveDestinationCoordinates();
      _calculateDistanceAndEta();
    }
  }

  void _resolveDestinationCoordinates() {
    if (widget.destinationLat != null && widget.destinationLng != null) {
      _centerLat = widget.destinationLat!;
      _centerLng = widget.destinationLng!;
      return;
    }

    final lower = widget.destinationAddress.toLowerCase();
    for (final entry in _townCoordinates.entries) {
      if (lower.contains(entry.key)) {
        _centerLat = entry.value[0];
        _centerLng = entry.value[1];
        return;
      }
    }

    // Default to Laguna Hub (Santa Rosa)
    _centerLat = 14.3122;
    _centerLng = 121.1114;
  }

  Future<void> _fetchRiderLocation() async {
    if (_locatingRider) return;
    setState(() => _locatingRider = true);

    try {
      final serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) {
        if (mounted) setState(() => _locatingRider = false);
        return;
      }

      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      if (permission == LocationPermission.denied ||
          permission == LocationPermission.deniedForever) {
        if (mounted) setState(() => _locatingRider = false);
        return;
      }

      final pos = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.medium,
          timeLimit: Duration(seconds: 10),
        ),
      );

      if (mounted) {
        setState(() {
          _riderPosition = pos;
          _locatingRider = false;
        });
        _calculateDistanceAndEta();
      }
    } catch (_) {
      if (mounted) setState(() => _locatingRider = false);
    }
  }

  void _calculateDistanceAndEta() {
    if (_riderPosition == null) return;
    final distMeters = Geolocator.distanceBetween(
      _riderPosition!.latitude,
      _riderPosition!.longitude,
      _centerLat,
      _centerLng,
    );

    setState(() {
      if (distMeters >= 1000) {
        _distanceText = '${(distMeters / 1000).toStringAsFixed(1)} km';
      } else {
        _distanceText = '${distMeters.toStringAsFixed(0)} m';
      }

      // Motorcycle estimated travel time @ 25 km/h average
      final mins = ((distMeters / 1000) / 25 * 60).round().clamp(1, 120);
      _etaText = '~$mins mins';
    });
  }

  void _zoomIn() {
    HapticFeedback.selectionClick();
    if (_zoom < 17) {
      setState(() => _zoom++);
      _resetTransformation();
    }
  }

  void _zoomOut() {
    HapticFeedback.selectionClick();
    if (_zoom > 12) {
      setState(() => _zoom--);
      _resetTransformation();
    }
  }

  void _recenterOnRider() {
    HapticFeedback.mediumImpact();
    if (_riderPosition != null) {
      setState(() {
        _centerLat = _riderPosition!.latitude;
        _centerLng = _riderPosition!.longitude;
      });
      _resetTransformation();
    } else {
      _fetchRiderLocation();
    }
  }

  void _recenterOnDestination() {
    HapticFeedback.mediumImpact();
    _resolveDestinationCoordinates();
    setState(() {});
    _resetTransformation();
  }

  void _resetTransformation() {
    _transformController.value = Matrix4.identity();
  }

  // Web Mercator Slippy Map Tile Calculations
  static int _lonToTileX(double lon, int z) {
    return ((lon + 180.0) / 360.0 * (1 << z)).floor();
  }

  static int _latToTileY(double lat, int z) {
    final latRad = lat * math.pi / 180.0;
    return ((1.0 - math.log(math.tan(latRad) + 1.0 / math.cos(latRad)) / math.pi) /
            2.0 *
            (1 << z))
        .floor();
  }

  @override
  Widget build(BuildContext context) {
    final tileX = _lonToTileX(_centerLng, _zoom);
    final tileY = _latToTileY(_centerLat, _zoom);

    final routeFooter = Padding(
      padding: const EdgeInsets.all(14),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  _distanceText != null
                      ? '$_distanceText away · $_etaText'
                      : (_locatingRider
                            ? 'Finding your location…'
                            : 'Route destination'),
                  style: const TextStyle(
                    fontWeight: FontWeight.w700,
                    fontSize: 12,
                    color: Color(0xFF0F172A),
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  widget.destinationAddress,
                  style: const TextStyle(
                    fontSize: 11,
                    height: 1.35,
                    color: Color(0xFF64748B),
                  ),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          IconButton.filled(
            tooltip: 'Open Waze / Maps',
            style: IconButton.styleFrom(
              backgroundColor: const Color(0xFF0F766E),
              foregroundColor: Colors.white,
              minimumSize: const Size(44, 44),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
            icon: const Icon(Icons.navigation_rounded, size: 20),
            onPressed: () {
              if (widget.onOpenExternalMap != null) {
                widget.onOpenExternalMap!();
              } else {
                RiderQuickActions.openMapNavigation(
                  context,
                  widget.destinationAddress,
                );
              }
            },
          ),
        ],
      ),
    );
    final cardContent = Stack(
      children: [
        // -------------------------------------------------------------
        // Interactive Tile Canvas (3x3 OpenStreetMap Slippy Grid)
        // -------------------------------------------------------------
        ClipRRect(
          borderRadius: BorderRadius.circular(widget.fullscreen ? 0 : 20),
          child: InteractiveViewer(
            transformationController: _transformController,
            minScale: 0.8,
            maxScale: 2.5,
            boundaryMargin: const EdgeInsets.all(120),
            child: SizedBox(
              width: double.infinity,
              height: widget.fullscreen ? double.infinity : widget.height,
              child: Stack(
                alignment: Alignment.center,
                children: [
                  // 3x3 Tile Grid (768 x 768 px virtual canvas)
                  OverflowBox(
                    maxWidth: 768,
                    maxHeight: 768,
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        for (int dy = -1; dy <= 1; dy++)
                          Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              for (int dx = -1; dx <= 1; dx++)
                                _buildTile(tileX + dx, tileY + dy, _zoom),
                            ],
                          ),
                      ],
                    ),
                  ),

                  // Route Line connecting Rider and Destination
                  if (_riderPosition != null)
                    Positioned.fill(
                      child: CustomPaint(painter: _RouteLinePainter()),
                    ),

                  // Center Marker: Destination Drop-off Pin 📍
                  Padding(
                    padding: const EdgeInsets.only(left: 12, right: 60),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 8,
                            vertical: 4,
                          ),
                          decoration: BoxDecoration(
                            color: const Color(0xFFDC2626),
                            borderRadius: BorderRadius.circular(10),
                            boxShadow: const [
                              BoxShadow(
                                color: Colors.black26,
                                blurRadius: 4,
                                offset: Offset(0, 2),
                              ),
                            ],
                          ),
                          child: Text(
                            widget.destinationName,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 10,
                              fontWeight: FontWeight.w900,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                        const SizedBox(height: 2),
                        const Icon(
                          Icons.location_on_rounded,
                          color: Color(0xFFDC2626),
                          size: 34,
                        ),
                      ],
                    ),
                  ),

                  // Rider Live Location Pin 🛵 (Offset simulated if available)
                  if (_riderPosition != null)
                    Positioned(
                      left: 120,
                      top: 140,
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Container(
                            padding: const EdgeInsets.all(8),
                            decoration: BoxDecoration(
                              color: const Color(0xFF0F766E),
                              shape: BoxShape.circle,
                              border: Border.all(
                                color: Colors.white,
                                width: 2.5,
                              ),
                              boxShadow: const [
                                BoxShadow(
                                  color: Color(0xFF0F766E),
                                  blurRadius: 10,
                                  spreadRadius: 2,
                                ),
                              ],
                            ),
                            child: const Icon(
                              Icons.directions_bike_rounded,
                              color: Colors.white,
                              size: 18,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 6,
                              vertical: 2,
                            ),
                            decoration: BoxDecoration(
                              color: const Color(0xFF0F766E),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: const Text(
                              'Ikaw (Rider)',
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 9,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                ],
              ),
            ),
          ),
        ),

        // -------------------------------------------------------------
        // Floating Top Badges (Status & Fullscreen Toggle)
        // -------------------------------------------------------------
        Positioned(
          top: 12,
          left: 12,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(10),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(
                  Icons.map_rounded,
                  color: Color(0xFF14B8A6),
                  size: 14,
                ),
                const SizedBox(width: 6),
                const Text(
                  'OpenStreetMap',
                  style: TextStyle(
                    color: Color(0xFF0F766E),
                    fontSize: 10,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                if (_locatingRider) ...[
                  const SizedBox(width: 8),
                  const SizedBox(
                    width: 10,
                    height: 10,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.tealAccent,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ),

        // Fullscreen Toggle Button
        Positioned(
          top: 12,
          right: 12,
          child: Row(
            children: [
              _mapActionButton(
                icon: widget.fullscreen
                    ? Icons.fullscreen_exit_rounded
                    : Icons.fullscreen_rounded,
                tooltip: widget.fullscreen
                    ? 'Exit Fullscreen'
                    : 'Fullscreen Map',
                onTap: () {
                  HapticFeedback.mediumImpact();
                  if (widget.fullscreen) {
                    Navigator.pop(context);
                    return;
                  }
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => RiderInteractiveMapCard(
                        destinationAddress: widget.destinationAddress,
                        destinationName: widget.destinationName,
                        destinationLat: widget.destinationLat,
                        destinationLng: widget.destinationLng,
                        onOpenExternalMap: widget.onOpenExternalMap,
                        fullscreen: true,
                      ),
                    ),
                  );
                },
              ),
            ],
          ),
        ),

        // -------------------------------------------------------------
        // Floating Map Controls (Zoom +, Zoom -, Recenter Pins)
        // -------------------------------------------------------------
        Positioned(
          right: 12,
          top: 60,
          child: Column(
            children: [
              _mapActionButton(
                icon: Icons.add,
                tooltip: 'Zoom In',
                onTap: _zoomIn,
              ),
              const SizedBox(height: 6),
              _mapActionButton(
                icon: Icons.remove,
                tooltip: 'Zoom Out',
                onTap: _zoomOut,
              ),
            ],
          ),
        ),
        Positioned(
          left: 12,
          bottom: 12,
          child: Row(
            children: [
              _mapActionButton(
                icon: Icons.my_location_rounded,
                tooltip: 'Puntahan ang Lokasyon Ko',
                color: const Color(0xFF0F766E),
                onTap: _recenterOnRider,
              ),
              const SizedBox(width: 8),
              _mapActionButton(
                icon: Icons.pin_drop_rounded,
                tooltip: 'Puntahan ang Drop-off',
                color: const Color(0xFFDC2626),
                onTap: _recenterOnDestination,
              ),
            ],
          ),
        ),
      ],
    );

    if (widget.fullscreen) {
      return Scaffold(
        appBar: AppBar(
          title: const Text('Route Map Navigation'),
          backgroundColor: const Color(0xFF0F766E),
          foregroundColor: Colors.white,
          leading: IconButton(
            icon: const Icon(Icons.close),
            onPressed: () => Navigator.pop(context),
          ),
        ),
        body: SafeArea(
          top: false,
          child: Column(
            children: [
              Expanded(child: cardContent),
              routeFooter,
            ],
          ),
        ),
      );
    }

    return Container(
      width: double.infinity,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFCBD5E1)),
        boxShadow: const [
          BoxShadow(color: Colors.black12, blurRadius: 8, offset: Offset(0, 3)),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          SizedBox(height: widget.height, child: cardContent),
          routeFooter,
        ],
      ),
    );
  }

  Widget _buildTile(int x, int y, int z) {
    // OpenStreetMap standard tile URL
    final url = 'https://tile.openstreetmap.org/$z/$x/$y.png';
    return SizedBox(
      width: 256,
      height: 256,
      child: CachedNetworkImage(
        imageUrl: url,
        fit: BoxFit.cover,
        httpHeaders: const {
          'User-Agent': 'AlonaCourierRiderApp/1.0 (contact@shopalona.online)',
        },
        placeholder: (context, url) => Container(
          color: Colors.white,
          child: const Center(
            child: SizedBox(
              width: 18,
              height: 18,
              child: CircularProgressIndicator(strokeWidth: 2, color: Colors.teal),
            ),
          ),
        ),
        errorWidget: (context, url, error) => Container(
          color: const Color(0xFFF1F5F9),
          child: const Center(
            child: Icon(Icons.broken_image_rounded, color: Colors.grey, size: 24),
          ),
        ),
      ),
    );
  }

  Widget _mapActionButton({
    required IconData icon,
    required String tooltip,
    required VoidCallback onTap,
    Color? color,
  }) {
    return Tooltip(
      message: tooltip,
      child: Material(
        color: Colors.white,
        shape: const CircleBorder(),
        elevation: 1,
        child: InkWell(
          customBorder: const CircleBorder(),
          onTap: onTap,
          child: Container(
            width: 40,
            height: 40,
            alignment: Alignment.center,
            child: Icon(
              icon,
              size: 18,
              color: color ?? const Color(0xFF1E293B),
            ),
          ),
        ),
      ),
    );
  }


}

class _RouteLinePainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final start = Offset(size.width * 0.35, size.height * 0.65);
    final end = Offset(size.width * 0.5, size.height * 0.5);

    final linePaint = Paint()
      ..color = const Color(0xFF0F766E).withValues(alpha: 0.8)
      ..strokeWidth = 3.5
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round;

    final dashPaint = Paint()
      ..color = Colors.white
      ..strokeWidth = 1.5
      ..style = PaintingStyle.stroke;

    canvas.drawLine(start, end, linePaint);
    canvas.drawLine(start, end, dashPaint);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
