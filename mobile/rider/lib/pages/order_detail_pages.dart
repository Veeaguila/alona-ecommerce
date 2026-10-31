import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../services/parcel_address.dart';
import '../services/rider_session.dart';
import '../theme/rider_theme.dart';
import '../widgets/address_details.dart';
import '../widgets/rider_bottom_nav.dart';
import '../widgets/rider_design_widgets.dart';
import 'rider_portal_page.dart';

/// Presentation-only order screens (mock-ups 7 and 8). They never call the API
/// themselves: every action is a closure supplied by the dashboard that already
/// owns the real workflow, so the behaviour is identical to the list cards.

String _text(Object? v) => v is String ? v.trim() : '';

String _contactName(Map<String, dynamic> parcel, {required bool pickup}) {
  final details = parcel['address_details'];
  if (details is Map && _text(details['name']).isNotEmpty) {
    return _text(details['name']);
  }
  if (pickup && parcel['pickup_requests'] is List) {
    for (final request in parcel['pickup_requests'] as List) {
      if (request is Map && request['seller'] is Map) {
        final name = _text((request['seller'] as Map)['name']);
        if (name.isNotEmpty) return name;
      }
    }
  }
  return pickup ? 'Seller' : 'Buyer';
}

String orderContactName(Map<String, dynamic> parcel, {required bool pickup}) =>
    _contactName(parcel, pickup: pickup);

String _contactPhone(Map<String, dynamic> parcel) {
  final details = parcel['address_details'];
  return details is Map ? _text(details['contact_no']) : '';
}

class _OrderHeader extends StatelessWidget {
  final String backLabel;
  final String title;
  final String badge;
  final int step;
  final String stepLabel;

  const _OrderHeader({
    required this.backLabel,
    required this.title,
    required this.badge,
    required this.step,
    required this.stepLabel,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      clipBehavior: Clip.antiAlias,
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFF0B7A83), Color(0xFF0F766E), Color(0xFF0D9488)],
        ),
        borderRadius: BorderRadius.only(
          bottomLeft: Radius.circular(32),
          bottomRight: Radius.circular(32),
        ),
      ),
      padding: EdgeInsets.fromLTRB(
        20,
        MediaQuery.of(context).padding.top + 12,
        20,
        20,
      ),
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Positioned(
            top: -90,
            right: -90,
            child: Container(
              width: 190,
              height: 190,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: RiderColors.amber.withValues(alpha: 0.28),
              ),
            ),
          ),
          Positioned(
            top: 40,
            left: -70,
            child: Container(
              width: 150,
              height: 150,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.white.withValues(alpha: 0.06),
              ),
            ),
          ),
          _content(context),
        ],
      ),
    );
  }

  Widget _content(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        InkWell(
          onTap: () => Navigator.of(context).maybePop(),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.chevron_left, color: Colors.white),
              Text(
                backLabel,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w700,
                  fontSize: 16,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        Wrap(
          spacing: 12,
          runSpacing: 8,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
            Text(
              title,
              style: const TextStyle(
                color: Colors.white,
                fontSize: 26,
                fontWeight: FontWeight.w800,
              ),
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
              decoration: BoxDecoration(
                color: RiderColors.mintBanner,
                borderRadius: BorderRadius.circular(20),
              ),
              child: Text(
                badge,
                style: const TextStyle(
                  color: RiderColors.tealDark,
                  fontWeight: FontWeight.w700,
                  fontSize: 13,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 14),
        Row(
          children: [
            for (var i = 1; i <= 3; i++) ...[
              Expanded(
                child: Container(
                  height: 6,
                  decoration: BoxDecoration(
                    color: i <= step
                        ? RiderColors.amber
                        : Colors.white.withValues(alpha: 0.35),
                    borderRadius: BorderRadius.circular(3),
                  ),
                ),
              ),
              if (i < 3) const SizedBox(width: 8),
            ],
          ],
        ),
        const SizedBox(height: 10),
        Text(
          'Step $step of 3 · $stepLabel',
          style: const TextStyle(color: Colors.white, fontSize: 14),
        ),
      ],
    );
  }
}

class _ContactCard extends StatelessWidget {
  final String name;
  final String address;
  final String phone;
  final RiderSession? session;

  const _ContactCard({
    required this.name,
    required this.address,
    required this.phone,
    required this.session,
  });

  Widget _square(IconData icon, String tooltip, VoidCallback? onTap) {
    return Tooltip(
      message: tooltip,
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: onTap,
        child: Container(
          width: 46,
          height: 46,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: const Color(0xFF64748B)),
          ),
          child: Icon(
            icon,
            color: onTap == null ? RiderColors.textSecondary : RiderColors.text,
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: RiderColors.border),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  name,
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    fontSize: 17,
                    color: RiderColors.text,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  address.isEmpty ? 'Address not provided' : address,
                  style: const TextStyle(color: RiderColors.textSecondary),
                ),
              ],
            ),
          ),
          const SizedBox(width: 10),
          _square(
            Icons.chat_bubble_outline,
            'Message',
            () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => RiderChatPage(session: session),
              ),
            ),
          ),
          const SizedBox(width: 8),
          _square(
            Icons.phone_outlined,
            'Call',
            phone.isEmpty
                ? null
                : () async {
                    try {
                      await launchUrl(Uri(scheme: 'tel', path: phone));
                    } catch (_) {}
                  },
          ),
        ],
      ),
    );
  }
}

class _DashedAction extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback? onTap;

  const _DashedAction({required this.icon, required this.label, this.onTap});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(16),
      onTap: onTap,
      child: CustomPaint(
        painter: _DashedBorder(),
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 20),
          child: Row(
            children: [
              Icon(icon, color: RiderColors.text),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  label,
                  style: const TextStyle(
                    fontWeight: FontWeight.w700,
                    fontSize: 16,
                    color: RiderColors.text,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _DashedBorder extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = const Color(0xFF64748B)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1;
    final path = Path()
      ..addRRect(
        RRect.fromRectAndRadius(Offset.zero & size, const Radius.circular(16)),
      );
    for (final metric in path.computeMetrics()) {
      var d = 0.0;
      while (d < metric.length) {
        canvas.drawPath(metric.extractPath(d, d + 5), paint);
        d += 9;
      }
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

Widget _card({required Widget child}) => Container(
  width: double.infinity,
  padding: const EdgeInsets.all(18),
  decoration: BoxDecoration(
    color: Colors.white,
    borderRadius: BorderRadius.circular(20),
    border: Border.all(color: RiderColors.border),
  ),
  child: child,
);

class PickupOrderPage extends StatefulWidget {
  final Map<String, dynamic> pickup;
  final RiderSession? session;
  final bool isMine;

  /// Label + action for the primary button; null when no action is allowed.
  final String? primaryLabel;
  final Future<void> Function()? onPrimary;
  final String statusLabel;

  const PickupOrderPage({
    super.key,
    required this.pickup,
    required this.session,
    required this.isMine,
    required this.statusLabel,
    this.primaryLabel,
    this.onPrimary,
  });

  @override
  State<PickupOrderPage> createState() => _PickupOrderPageState();
}

class _PickupOrderPageState extends State<PickupOrderPage> {
  bool busy = false;
  bool sealed = false;
  bool handedOver = false;
  final Set<int> sealedExtra = {};

  int get boxes {
    for (final key in ['package_count', 'box_count']) {
      final v = int.tryParse('${widget.pickup[key] ?? ''}');
      if (v != null && v > 0) return v > 10 ? 10 : v;
    }
    return 1;
  }

  int get step {
    if (!widget.isMine) return 1;
    final status = widget.pickup['status'];
    return status == 'PLACED' || status == 'PREPARING' ? 2 : 3;
  }

  String get stepLabel => switch (step) {
    1 => 'Review the pickup',
    2 => 'Verify the parcel',
    _ => 'Bring to your Sorting Center',
  };

  @override
  Widget build(BuildContext context) {
    final pickup = widget.pickup;
    return Scaffold(
      backgroundColor: RiderColors.background,
      body: Column(
        children: [
          _OrderHeader(
            backLabel: 'Pickups',
            title: 'Pickup ${pickup['order_id']}',
            badge: widget.statusLabel,
            step: step,
            stepLabel: stepLabel,
          ),
          Expanded(
            child: ListView(
              padding: const EdgeInsets.all(20),
              children: [
                const RiderMapIllustration(),
                const SizedBox(height: 14),
                _ContactCard(
                  name: _contactName(pickup, pickup: true),
                  address: pickupAddress(pickup),
                  phone: _contactPhone(pickup),
                  session: widget.session,
                ),
                const SizedBox(height: 14),
                _card(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Verify parcel',
                        style: TextStyle(
                          fontSize: 20,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 8),
                      for (var i = 1; i <= boxes; i++)
                        Material(
                          color: Colors.transparent,
                          child: CheckboxListTile(
                            contentPadding: EdgeInsets.zero,
                            controlAffinity: ListTileControlAffinity.leading,
                            activeColor: RiderColors.teal,
                            value: i == 1 ? sealed : sealedExtra.contains(i),
                            onChanged: (v) => setState(() {
                              if (i == 1) {
                                sealed = v ?? false;
                              } else if (v == true) {
                                sealedExtra.add(i);
                              } else {
                                sealedExtra.remove(i);
                              }
                            }),
                            title: Text(
                              boxes > 1
                                  ? 'Box $i of $boxes · sealed, label matches'
                                  : 'Parcel sealed, label matches',
                            ),
                          ),
                        ),
                      Material(
                        color: Colors.transparent,
                        child: CheckboxListTile(
                          contentPadding: EdgeInsets.zero,
                          controlAffinity: ListTileControlAffinity.leading,
                          activeColor: RiderColors.teal,
                          value: handedOver,
                          onChanged: (v) =>
                              setState(() => handedOver = v ?? false),
                          title: const Text('Seller handed over the parcel'),
                        ),
                      ),
                      const SizedBox(height: 8),
                      AddressDetails(parcel: pickup, pickup: true),
                    ],
                  ),
                ),
                const SizedBox(height: 20),
                if (widget.primaryLabel != null && widget.onPrimary != null)
                  Container(
                    width: double.infinity,
                    height: 56,
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        colors: [Color(0xFF0F766E), Color(0xFF14A39A)],
                      ),
                      borderRadius: BorderRadius.circular(18),
                      boxShadow: [
                        BoxShadow(
                          color: RiderColors.teal.withValues(alpha: 0.3),
                          blurRadius: 14,
                          offset: const Offset(0, 6),
                        ),
                      ],
                    ),
                    child: FilledButton(
                      style: FilledButton.styleFrom(
                        backgroundColor: Colors.transparent,
                        shadowColor: Colors.transparent,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(18),
                        ),
                      ),
                      onPressed: busy
                          ? null
                          : () async {
                              final nav = Navigator.of(context);
                              setState(() => busy = true);
                              try {
                                await widget.onPrimary!();
                              } finally {
                                if (mounted) {
                                  setState(() => busy = false);
                                  nav.maybePop();
                                }
                              }
                            },
                      child: Text(
                        widget.primaryLabel!,
                        style: const TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                  ),
                Center(
                  child: TextButton(
                    onPressed: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => RiderChatPage(session: widget.session),
                      ),
                    ),
                    child: const Text(
                      'Report a problem with this pickup',
                      style: TextStyle(
                        color: RiderColors.tealDark,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
      bottomNavigationBar: widget.session != null
          ? RiderBottomNav(currentIndex: 0, session: widget.session!)
          : null,
    );
  }
}

class DeliveryOrderPage extends StatefulWidget {
  final Map<String, dynamic> delivery;
  final RiderSession? session;
  final String statusLabel;

  /// Real workflow closures owned by the delivery dashboard.
  final Future<void> Function()? onStart;
  final Future<void> Function()? onCapture;
  final Future<void> Function()? onDeliver;
  final Future<void> Function(String reason)? onFail;

  const DeliveryOrderPage({
    super.key,
    required this.delivery,
    required this.session,
    required this.statusLabel,
    this.onStart,
    this.onCapture,
    this.onDeliver,
    this.onFail,
  });

  @override
  State<DeliveryOrderPage> createState() => _DeliveryOrderPageState();
}

class _DeliveryOrderPageState extends State<DeliveryOrderPage> {
  bool busy = false;
  String result = 'Delivered';

  Future<void> _run(Future<void> Function()? action, {bool pop = true}) async {
    if (action == null || busy) return;
    final nav = Navigator.of(context);
    setState(() => busy = true);
    try {
      await action();
    } finally {
      if (mounted) {
        setState(() => busy = false);
        if (pop) nav.maybePop();
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final delivery = widget.delivery;
    final canResolve = widget.onDeliver != null || widget.onFail != null;
    final step = canResolve ? 3 : 2;
    return Scaffold(
      backgroundColor: RiderColors.background,
      body: Column(
        children: [
          _OrderHeader(
            backLabel: 'Deliveries',
            title: 'Deliver ${delivery['order_id']}',
            badge: widget.statusLabel,
            step: step,
            stepLabel: canResolve
                ? 'Hand over to the recipient'
                : 'Start the delivery',
          ),
          Expanded(
            child: ListView(
              padding: const EdgeInsets.all(20),
              children: [
                _ContactCard(
                  name: _contactName(delivery, pickup: false),
                  address: deliveryAddress(delivery),
                  phone: _contactPhone(delivery),
                  session: widget.session,
                ),
                const SizedBox(height: 14),
                const RiderMapIllustration(),
                const SizedBox(height: 14),
                if (canResolve)
                  _card(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Delivery result',
                          style: TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 12),
                        Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: [
                            for (final option in const [
                              'Delivered',
                              'Buyer not home',
                              'Wrong address',
                              'Refused',
                            ])
                              ChoiceChip(
                                label: Text(option),
                                selected: result == option,
                                onSelected: busy
                                    ? null
                                    : (_) => setState(() => result = option),
                              ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        const Text(
                          'Refused goes directly to Return to Seller. Other failures need a new attempt; the third failure also goes to Return to Seller.',
                        ),
                        if (widget.onCapture != null) ...[
                          const SizedBox(height: 12),
                          _DashedAction(
                            icon: Icons.photo_camera_outlined,
                            label: 'Photo proof of delivery',
                            onTap: busy
                                ? null
                                : () => _run(widget.onCapture, pop: false),
                          ),
                        ],
                      ],
                    ),
                  ),
                if (!canResolve) AddressDetails(parcel: delivery),
                const SizedBox(height: 20),
                if (widget.onStart != null)
                  SizedBox(
                    width: double.infinity,
                    height: 56,
                    child: FilledButton(
                      onPressed: busy ? null : () => _run(widget.onStart),
                      child: const Text('Start Delivery'),
                    ),
                  ),
                if (canResolve)
                  SizedBox(
                    width: double.infinity,
                    height: 56,
                    child: FilledButton(
                      onPressed: busy
                          ? null
                          : () => _run(
                              result == 'Delivered'
                                  ? widget.onDeliver
                                  : widget.onFail == null
                                  ? null
                                  : () => widget.onFail!(result),
                            ),
                      child: Text(
                        result == 'Delivered'
                            ? 'Mark as delivered'
                            : 'Mark as failed',
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
      bottomNavigationBar: widget.session != null
          ? RiderBottomNav(currentIndex: 1, session: widget.session!)
          : null,
    );
  }
}
