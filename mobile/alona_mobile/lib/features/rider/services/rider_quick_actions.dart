import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

class RiderQuickActions {
  /// Opens Navigation in Google Maps or Waze, giving the rider a quick modal picker or direct navigation
  static Future<void> openMapNavigation(BuildContext context, String address) async {
    final clean = address.trim();
    if (clean.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Walang available na address para sa navigation.')),
      );
      return;
    }

    final encoded = Uri.encodeComponent(clean);

    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  const Icon(Icons.navigation_rounded, color: Color(0xFF0F766E), size: 22),
                  const SizedBox(width: 8),
                  const Text(
                    'Pumili ng Navigation App',
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                      color: Color(0xFF0F766E),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              Text(
                clean,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontSize: 12, color: Colors.grey),
              ),
              const SizedBox(height: 16),
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: Colors.red.shade50,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(Icons.map_rounded, color: Colors.red, size: 24),
                ),
                title: const Text('Google Maps', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                subtitle: const Text('Turn-by-turn route navigation', style: TextStyle(fontSize: 12)),
                trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 16, color: Colors.grey),
                onTap: () async {
                  Navigator.pop(ctx);
                  final googleNavUri = Uri.parse('google.navigation:q=$encoded');
                  final googleWebUri = Uri.parse('https://www.google.com/maps/search/?api=1&query=$encoded');
                  try {
                    if (await canLaunchUrl(googleNavUri)) {
                      await launchUrl(googleNavUri, mode: LaunchMode.externalApplication);
                    } else {
                      await launchUrl(googleWebUri, mode: LaunchMode.externalApplication);
                    }
                  } catch (_) {
                    await launchUrl(googleWebUri, mode: LaunchMode.externalApplication);
                  }
                },
              ),
              const Divider(height: 16),
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: Colors.blue.shade50,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(Icons.directions_car_rounded, color: Colors.blue, size: 24),
                ),
                title: const Text('Waze', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
                subtitle: const Text('Real-time traffic & crowd alerts', style: TextStyle(fontSize: 12)),
                trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 16, color: Colors.grey),
                onTap: () async {
                  Navigator.pop(ctx);
                  final wazeAppUri = Uri.parse('waze://?q=$encoded&navigate=yes');
                  final wazeWebUri = Uri.parse('https://waze.com/ul?q=$encoded&navigate=yes');
                  try {
                    if (await canLaunchUrl(wazeAppUri)) {
                      await launchUrl(wazeAppUri, mode: LaunchMode.externalApplication);
                    } else {
                      await launchUrl(wazeWebUri, mode: LaunchMode.externalApplication);
                    }
                  } catch (_) {
                    await launchUrl(wazeWebUri, mode: LaunchMode.externalApplication);
                  }
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  /// One-Tap Call to Contact
  static Future<void> callContact(BuildContext context, String rawPhone) async {
    final clean = rawPhone.replaceAll(RegExp(r'[^0-9+]'), '').trim();
    if (clean.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Walang nakatalang contact number.')),
      );
      return;
    }
    final uri = Uri(scheme: 'tel', path: clean);
    try {
      await launchUrl(uri);
    } catch (_) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Hindi mabuksan ang phone dialer.')),
        );
      }
    }
  }

  /// One-Tap SMS with Pre-Filled Polite Delivery / Pickup Template
  static Future<void> sendDeliverySms(
    BuildContext context, {
    required String rawPhone,
    required String recipientName,
    required String orderId,
    bool isCod = false,
    double codAmount = 0.0,
    bool isPickup = false,
  }) async {
    final clean = rawPhone.replaceAll(RegExp(r'[^0-9+]'), '').trim();
    if (clean.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Walang nakatalang contact number.')),
      );
      return;
    }

    final String message;
    if (isPickup) {
      message = 'Magandang araw po ${recipientName.isNotEmpty ? recipientName : "Seller"}! '
          'Rider po ako mula sa Alona Express. Papunta na po ako para sa pickup ng parcel (Order #$orderId). '
          'Pakihanda po ang item. Maraming salamat po!';
    } else {
      final paymentInfo = isCod
          ? 'COD Amount: PHP ${codAmount.toStringAsFixed(2)}. Pakihanda po ang eksaktong bayad.'
          : 'Status: PAID ONLINE na po.';
      message = 'Magandang araw po ${recipientName.isNotEmpty ? recipientName : "Buyer"}! '
          'Rider po ako mula sa Alona Express. May parcel po kayo for delivery today (Order #$orderId). '
          '$paymentInfo Pakihanda po ang tatanggap. Maraming salamat po!';
    }

    final uri = Uri(
      scheme: 'sms',
      path: clean,
      queryParameters: {'body': message},
    );

    try {
      await launchUrl(uri);
    } catch (_) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Hindi mabuksan ang messaging app.')),
        );
      }
    }
  }
}

/// Compact 3-action bar for Delivery & Pickup list cards:
/// [ Maps / Waze ] [ SMS ] [ Call ]
class RiderCardQuickActions extends StatelessWidget {
  final String address;
  final String phone;
  final String recipientName;
  final String orderId;
  final bool isCod;
  final double codAmount;
  final bool isPickup;

  const RiderCardQuickActions({
    super.key,
    required this.address,
    required this.phone,
    required this.recipientName,
    required this.orderId,
    this.isCod = false,
    this.codAmount = 0.0,
    this.isPickup = false,
  });

  @override
  Widget build(BuildContext context) {
    final cleanPhone = phone.trim();
    final cleanAddr = address.trim();

    return Padding(
      padding: const EdgeInsets.only(top: 10, bottom: 4),
      child: Row(
        children: [
          // Maps / Waze Button
          Expanded(
            child: OutlinedButton.icon(
              onPressed: cleanAddr.isEmpty
                  ? null
                  : () => RiderQuickActions.openMapNavigation(context, cleanAddr),
              icon: const Icon(Icons.navigation_outlined, size: 15),
              label: const Text('Maps', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
              style: OutlinedButton.styleFrom(
                foregroundColor: const Color(0xFF0F766E),
                side: const BorderSide(color: Color(0xFF0F766E)),
                padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 4),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                minimumSize: Size.zero,
                tapTargetSize: MaterialTapTargetSize.shrinkWrap,
              ),
            ),
          ),
          const SizedBox(width: 8),
          // SMS Button
          Expanded(
            child: OutlinedButton.icon(
              onPressed: cleanPhone.isEmpty
                  ? null
                  : () => RiderQuickActions.sendDeliverySms(
                        context,
                        rawPhone: cleanPhone,
                        recipientName: recipientName,
                        orderId: orderId,
                        isCod: isCod,
                        codAmount: codAmount,
                        isPickup: isPickup,
                      ),
              icon: const Icon(Icons.sms_outlined, size: 15),
              label: const Text('SMS', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
              style: OutlinedButton.styleFrom(
                foregroundColor: const Color(0xFF2563EB),
                side: const BorderSide(color: Color(0xFF2563EB)),
                padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 4),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                minimumSize: Size.zero,
                tapTargetSize: MaterialTapTargetSize.shrinkWrap,
              ),
            ),
          ),
          const SizedBox(width: 8),
          // Call Button
          Expanded(
            child: OutlinedButton.icon(
              onPressed: cleanPhone.isEmpty
                  ? null
                  : () => RiderQuickActions.callContact(context, cleanPhone),
              icon: const Icon(Icons.phone_outlined, size: 15),
              label: const Text('Call', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
              style: OutlinedButton.styleFrom(
                foregroundColor: const Color(0xFF059669),
                side: const BorderSide(color: Color(0xFF059669)),
                padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 4),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                minimumSize: Size.zero,
                tapTargetSize: MaterialTapTargetSize.shrinkWrap,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
