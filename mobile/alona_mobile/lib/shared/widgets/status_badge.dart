import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class StatusBadge extends StatelessWidget {
  final String status;
  final String? customLabel;

  const StatusBadge({super.key, required this.status, this.customLabel});

  @override
  Widget build(BuildContext context) {
    final style = _resolveStatusStyle(status.toLowerCase());

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: style.bgColor,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: style.borderColor, width: 1),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 6,
            height: 6,
            decoration: BoxDecoration(
              color: style.dotColor,
              shape: BoxShape.circle,
            ),
          ),
          const SizedBox(width: 6),
          Text(
            customLabel ??
                const {
                  'approved': 'Approved',
                  'active': 'Active',
                  'inactive': 'Suspended',
                  'rejected': 'Rejected',
                  'paid': 'Paid',
                  'at_sorting_center': 'At sorting center',
                  'in_hub_transit': 'Between hubs',
                  'out_for_delivery': 'Out for delivery',
                  'failed_returned': 'Delivery failed',
                  'picked_up': 'Picked up',
                  'lost': 'Lost',
                  'damaged': 'Damaged',
                  'delivered': 'Delivered',
                  'processing': 'Processing',
                  'unpaid': 'Unpaid',
                }[status.toLowerCase()] ??
                style.label,
            style: GoogleFonts.figtree(
              color: style.textColor,
              fontSize: 12,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }

  _BadgeStyle _resolveStatusStyle(String s) {
    switch (s) {
      case 'completed':
      case 'delivered':
      case 'paid':
      case 'approved':
      case 'active':
        return _BadgeStyle(
          label: 'Completed',
          bgColor: const Color(0xFFF0FDF4),
          borderColor: const Color(0xFFBBF7D0),
          dotColor: const Color(0xFF16A34A),
          textColor: const Color(0xFF15803D),
        );
      case 'in_transit':
      case 'shipped':
      case 'out_for_delivery':
        return _BadgeStyle(
          label: 'In Transit',
          bgColor: const Color(0xFFEFF6FF),
          borderColor: const Color(0xFFBFDBFE),
          dotColor: const Color(0xFF2563EB),
          textColor: const Color(0xFF1D4ED8),
        );
      case 'pending':
      case 'unpaid':
      case 'processing':
      case 'at_sorting_center':
        return _BadgeStyle(
          label: 'Pending',
          bgColor: const Color(0xFFFFFBEB),
          borderColor: const Color(0xFFFDE68A),
          dotColor: const Color(0xFFD97706),
          textColor: const Color(0xFFB45309),
        );
      case 'cancelled':
      case 'failed':
      case 'failed_returned':
      case 'rejected':
      case 'inactive':
        return _BadgeStyle(
          label: 'Cancelled',
          bgColor: const Color(0xFFFEF2F2),
          borderColor: const Color(0xFFFECACA),
          dotColor: const Color(0xFFDC2626),
          textColor: const Color(0xFFB91C1C),
        );
      default:
        return _BadgeStyle(
          label: s.toUpperCase(),
          bgColor: const Color(0xFFF8FAFC),
          borderColor: const Color(0xFFE2E8F0),
          dotColor: const Color(0xFF64748B),
          textColor: const Color(0xFF475569),
        );
    }
  }
}

class _BadgeStyle {
  final String label;
  final Color bgColor;
  final Color borderColor;
  final Color dotColor;
  final Color textColor;

  _BadgeStyle({
    required this.label,
    required this.bgColor,
    required this.borderColor,
    required this.dotColor,
    required this.textColor,
  });
}
