import 'package:flutter/material.dart';

import '../theme/rider_theme.dart';

/// Mint banner with a teal bell badge, used on the dashboards (design only;
/// tapping runs whatever [onTap] the screen provides).
class RiderNotificationBanner extends StatelessWidget {
  final String title;
  final String subtitle;
  final VoidCallback? onTap;

  const RiderNotificationBanner({
    super.key,
    required this.title,
    required this.subtitle,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: RiderColors.mintBanner,
      borderRadius: BorderRadius.circular(20),
      child: InkWell(
        borderRadius: BorderRadius.circular(20),
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: const Color(0xFFB9E6E6)),
          ),
          child: Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: const BoxDecoration(
                  color: RiderColors.teal,
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.notifications_none_rounded,
                  color: Colors.white,
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 15,
                        color: RiderColors.text,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      subtitle,
                      style: const TextStyle(
                        fontSize: 14,
                        color: RiderColors.tealDark,
                      ),
                    ),
                  ],
                ),
              ),
              if (onTap != null)
                const Icon(Icons.chevron_right, color: RiderColors.tealDark),
            ],
          ),
        ),
      ),
    );
  }
}

/// Pill used in the teal header (selected = amber, otherwise dark teal).
class RiderHeaderPill extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback? onTap;

  const RiderHeaderPill({
    super.key,
    required this.label,
    this.selected = false,
    this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected ? RiderColors.amber : RiderColors.tealDark,
      borderRadius: BorderRadius.circular(20),
      child: InkWell(
        borderRadius: BorderRadius.circular(20),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
          child: Text(
            label,
            style: TextStyle(
              fontWeight: FontWeight.w700,
              fontSize: 14,
              color: selected ? RiderColors.text : Colors.white,
            ),
          ),
        ),
      ),
    );
  }
}

/// Stylised route illustration from the order mock-ups (decorative only).
class RiderMapIllustration extends StatelessWidget {
  final double height;

  const RiderMapIllustration({super.key, this.height = 140});

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(20),
      child: SizedBox(
        height: height,
        width: double.infinity,
        child: CustomPaint(painter: _MapPainter()),
      ),
    );
  }
}

class _MapPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    canvas.drawRect(
      Offset.zero & size,
      Paint()..color = const Color(0xFFDDEFEF),
    );
    final road = Paint()..color = Colors.white;
    final w = size.width;
    final h = size.height;
    canvas.drawRect(Rect.fromLTWH(w * 0.25, 0, 12, h), road);
    canvas.drawRect(Rect.fromLTWH(w * 0.72, 0, 12, h), road);
    canvas.drawRect(Rect.fromLTWH(0, h * 0.28, w, 12), road);
    canvas.drawRect(Rect.fromLTWH(0, h * 0.72, w, 12), road);

    final start = Offset(w * 0.1, h * 0.78);
    final corner = Offset(w * 0.25 + 6, h * 0.78);
    final up = Offset(w * 0.25 + 6, h * 0.34);
    final end = Offset(w * 0.72 + 6, h * 0.34);
    final dot = Paint()..color = RiderColors.teal;
    for (final seg in [
      [start, corner],
      [corner, up],
      [up, end],
    ]) {
      final a = seg[0];
      final b = seg[1];
      final len = (b - a).distance;
      final steps = (len / 12).floor();
      for (var i = 1; i < steps; i++) {
        canvas.drawCircle(Offset.lerp(a, b, i / steps)!, 3, dot);
      }
    }
    canvas.drawCircle(start, 11, Paint()..color = Colors.white);
    canvas.drawCircle(start, 8, Paint()..color = const Color(0xFF1F2937));
    canvas.drawCircle(end.translate(0, -8), 15, dot);
    canvas.drawCircle(end.translate(0, -8), 5, Paint()..color = Colors.white);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
