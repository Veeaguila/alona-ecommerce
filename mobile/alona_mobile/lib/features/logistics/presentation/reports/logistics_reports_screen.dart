import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/features/logistics/services/logistics_service.dart';

class LogisticsReportsScreen extends StatefulWidget {
  const LogisticsReportsScreen({super.key});

  @override
  State<LogisticsReportsScreen> createState() => _LogisticsReportsScreenState();
}

class _LogisticsReportsScreenState extends State<LogisticsReportsScreen> {
  DateTime _fromDate = DateTime.now().subtract(const Duration(days: 7));
  DateTime _toDate = DateTime.now();
  String _selectedRange = 'Last 7 Days';
  bool _isLoading = false;
  Map<String, dynamic>? _reportData;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _loadReport();
  }

  void _selectPreset(String preset) {
    final now = DateTime.now();
    setState(() {
      _selectedRange = preset;
      if (preset == 'Today') {
        _fromDate = now;
        _toDate = now;
      } else if (preset == 'Last 7 Days') {
        _fromDate = now.subtract(const Duration(days: 7));
        _toDate = now;
      } else if (preset == 'Last 30 Days') {
        _fromDate = now.subtract(const Duration(days: 30));
        _toDate = now;
      }
    });
    _loadReport();
  }

  Future<void> _loadReport() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final fmt = DateFormat('yyyy-MM-dd');
    try {
      final res = await LogisticsService.getReports(
        fmt.format(_fromDate),
        fmt.format(_toDate),
      );
      if (mounted) {
        setState(() {
          _reportData = res;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _errorMessage = e.toString().replaceFirst('Exception: ', '');
          _isLoading = false;
        });
      }
    }
  }

  Future<void> _pickCustomRange() async {
    final picked = await showDateRangePicker(
      context: context,
      firstDate: DateTime(2020),
      lastDate: DateTime.now(),
      initialDateRange: DateTimeRange(start: _fromDate, end: _toDate),
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: const ColorScheme.light(
              primary: AppColors.primary,
              onPrimary: Colors.white,
              surface: Colors.white,
              onSurface: AppColors.textPrimary,
            ),
          ),
          child: child!,
        );
      },
    );

    if (picked != null) {
      setState(() {
        _selectedRange = 'Custom';
        _fromDate = picked.start;
        _toDate = picked.end;
      });
      _loadReport();
    }
  }

  @override
  Widget build(BuildContext context) {
    final total = _reportData?['total_parcels_processed'] as int? ?? 0;
    final breakdown =
        (_reportData?['status_breakdown'] as Map?)?.cast<String, dynamic>() ??
        {};
    final turnaround = _reportData?['average_turnaround'] as String? ?? 'N/A';

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Performance & Reports'),
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: _loadReport),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _loadReport,
        color: AppColors.primary,
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: Row(
                  children: [
                    for (final preset in [
                      'Today',
                      'Last 7 Days',
                      'Last 30 Days',
                    ]) ...[
                      ChoiceChip(
                        label: Text(preset),
                        selected: _selectedRange == preset,
                        onSelected: (sel) {
                          if (sel) _selectPreset(preset);
                        },
                        selectedColor: AppColors.primary,
                        labelStyle: TextStyle(
                          color: _selectedRange == preset
                              ? Colors.white
                              : AppColors.textPrimary,
                          fontWeight: FontWeight.w600,
                          fontSize: 13,
                        ),
                      ),
                      const SizedBox(width: 8),
                    ],
                    ActionChip(
                      avatar: const Icon(
                        Icons.date_range,
                        size: 16,
                        color: AppColors.primary,
                      ),
                      label: Text(
                        _selectedRange == 'Custom'
                            ? '${DateFormat('MM/dd').format(_fromDate)} - ${DateFormat('MM/dd').format(_toDate)}'
                            : 'Custom Range',
                      ),
                      onPressed: _pickCustomRange,
                      backgroundColor: Colors.white,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(20),
                        side: BorderSide(
                          color: _selectedRange == 'Custom'
                              ? AppColors.primary
                              : AppColors.border,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),

              if (_isLoading)
                const Center(
                  child: Padding(
                    padding: EdgeInsets.symmetric(vertical: 40),
                    child: CircularProgressIndicator(color: AppColors.primary),
                  ),
                )
              else if (_errorMessage != null)
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.red.shade50,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: Colors.red.shade200),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.error_outline, color: Colors.red),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          _errorMessage!,
                          style: const TextStyle(
                            color: Colors.red,
                            fontSize: 13,
                          ),
                        ),
                      ),
                      TextButton(
                        onPressed: _loadReport,
                        child: const Text('Retry'),
                      ),
                    ],
                  ),
                )
              else ...[
                Row(
                  children: [
                    Expanded(
                      child: Container(
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: AppColors.border),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.03),
                              blurRadius: 8,
                              offset: const Offset(0, 2),
                            ),
                          ],
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Container(
                              padding: const EdgeInsets.all(8),
                              decoration: BoxDecoration(
                                color: AppColors.primaryLight,
                                shape: BoxShape.circle,
                              ),
                              child: const Icon(
                                Icons.local_shipping_outlined,
                                color: AppColors.primary,
                                size: 20,
                              ),
                            ),
                            const SizedBox(height: 12),
                            Text(
                              '$total',
                              style: const TextStyle(
                                fontSize: 24,
                                fontWeight: FontWeight.bold,
                                color: AppColors.textPrimary,
                              ),
                            ),
                            const SizedBox(height: 4),
                            const Text(
                              'Total Processed',
                              style: TextStyle(
                                fontSize: 12,
                                color: AppColors.textMuted,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Container(
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(color: AppColors.border),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.03),
                              blurRadius: 8,
                              offset: const Offset(0, 2),
                            ),
                          ],
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Container(
                              padding: const EdgeInsets.all(8),
                              decoration: BoxDecoration(
                                color: AppColors.accent.withValues(alpha: 0.12),
                                shape: BoxShape.circle,
                              ),
                              child: const Icon(
                                Icons.timer_outlined,
                                color: AppColors.accent,
                                size: 20,
                              ),
                            ),
                            const SizedBox(height: 12),
                            Text(
                              turnaround,
                              style: const TextStyle(
                                fontSize: 20,
                                fontWeight: FontWeight.bold,
                                color: AppColors.textPrimary,
                              ),
                            ),
                            const SizedBox(height: 4),
                            const Text(
                              'Avg Turnaround',
                              style: TextStyle(
                                fontSize: 12,
                                color: AppColors.textMuted,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 20),

                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: AppColors.border),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Parcel Status Breakdown',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                          color: AppColors.textPrimary,
                        ),
                      ),
                      const SizedBox(height: 16),
                      _buildStatusRow(
                        title: 'Delivered',
                        count: breakdown['DELIVERED'] as int? ?? 0,
                        total: total,
                        color: Colors.green,
                        icon: Icons.check_circle_outline,
                      ),
                      const Divider(height: 24),
                      _buildStatusRow(
                        title: 'Out for Delivery',
                        count: breakdown['OUT_FOR_DELIVERY'] as int? ?? 0,
                        total: total,
                        color: Colors.blue,
                        icon: Icons.directions_bike,
                      ),
                      const Divider(height: 24),
                      _buildStatusRow(
                        title: 'At Sorting Center',
                        count: breakdown['AT_SORTING_CENTER'] as int? ?? 0,
                        total: total,
                        color: AppColors.accent,
                        icon: Icons.warehouse_outlined,
                      ),
                      const Divider(height: 24),
                      _buildStatusRow(
                        title: 'Failed / Returned',
                        count: breakdown['FAILED_RETURNED'] as int? ?? 0,
                        total: total,
                        color: Colors.red,
                        icon: Icons.replay,
                      ),
                    ],
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildStatusRow({
    required String title,
    required int count,
    required int total,
    required Color color,
    required IconData icon,
  }) {
    final pct = total > 0 ? (count / total) : 0.0;
    final pctStr = (pct * 100).toStringAsFixed(1);

    return Column(
      children: [
        Row(
          children: [
            Icon(icon, size: 18, color: color),
            const SizedBox(width: 8),
            Text(
              title,
              style: const TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w600,
                color: AppColors.textPrimary,
              ),
            ),
            const Spacer(),
            Text(
              '$count',
              style: TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.bold,
                color: color,
              ),
            ),
            const SizedBox(width: 8),
            Text(
              '($pctStr%)',
              style: const TextStyle(fontSize: 12, color: AppColors.textMuted),
            ),
          ],
        ),
        const SizedBox(height: 8),
        ClipRRect(
          borderRadius: BorderRadius.circular(4),
          child: LinearProgressIndicator(
            value: pct,
            backgroundColor: color.withValues(alpha: 0.1),
            valueColor: AlwaysStoppedAnimation<Color>(color),
            minHeight: 6,
          ),
        ),
      ],
    );
  }
}
