import 'package:intl/intl.dart';

class CurrencyFormatter {
  static final NumberFormat _formatter = NumberFormat.currency(
    locale: 'en_PH',
    symbol: '₱',
    decimalDigits: 2,
  );

  static String format(num? amount) {
    if (amount == null) return '₱0.00';
    return _formatter.format(amount);
  }

  static String formatCompact(num? amount) {
    if (amount == null) return '₱0';
    if (amount >= 1000000) {
      return '₱${(amount / 1000000).toStringAsFixed(1)}M';
    }
    if (amount >= 1000) {
      return '₱${(amount / 1000).toStringAsFixed(1)}k';
    }
    return '₱${amount.toStringAsFixed(0)}';
  }
}
