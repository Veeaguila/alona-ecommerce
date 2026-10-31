import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/shared/widgets/empty_state_view.dart';
import 'package:alona_mobile/shared/widgets/status_badge.dart';
import 'package:alona_mobile/features/logistics/models/logistics_models.dart';
import 'package:alona_mobile/features/logistics/services/logistics_service.dart';
import 'package:alona_mobile/features/logistics/presentation/operations/parcel_operations_screen.dart';

class DeliveryMonitoringScreen extends StatefulWidget {
  const DeliveryMonitoringScreen({super.key});

  @override
  State<DeliveryMonitoringScreen> createState() =>
      _DeliveryMonitoringScreenState();
}

class _DeliveryMonitoringScreenState extends State<DeliveryMonitoringScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;
  final List<String> _filters = [
    'ALL',
    'AT_SORTING_CENTER',
    'IN_HUB_TRANSIT',
    'OUT_FOR_DELIVERY',
    'DELIVERED',
    'FAILED_RETURNED',
    'LOST',
    'DAMAGED',
  ];
  final List<String> _filterLabels = [
    'All',
    'At Center',
    'Between hubs',
    'Out for delivery',
    'Delivered',
    'Failed',
    'Lost',
    'Damaged',
  ];

  bool _isLoading = true;
  String? _error;
  List<ParcelModel> _allParcels = [];

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: _filters.length, vsync: this);
    _tabController.addListener(() {
      if (!_tabController.indexIsChanging) {
        setState(() {});
      }
    });
    _loadParcels();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _loadParcels() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final parcels = await LogisticsService.getMonitoringParcels();
      if (!mounted) return;
      setState(() {
        _allParcels = parcels;
        _isLoading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _isLoading = false;
      });
    }
  }

  List<ParcelModel> get _filteredParcels {
    final current = _filters[_tabController.index];
    if (current == 'ALL') return _allParcels;
    return _allParcels.where((p) => p.status.toUpperCase() == current).toList();
  }

  @override
  Widget build(BuildContext context) {
    final parcels = _filteredParcels;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Delivery Monitoring'),
        bottom: TabBar(
          controller: _tabController,
          isScrollable: true,
          tabs: _filterLabels.map((l) => Tab(text: l)).toList(),
        ),
      ),
      body: _isLoading
          ? const Center(
              child: CircularProgressIndicator(color: AppColors.primary),
            )
          : _error != null
          ? Center(child: Text(_error!))
          : parcels.isEmpty
          ? const Padding(
              padding: EdgeInsets.all(24),
              child: EmptyStateView(
                icon: Icons.local_shipping_outlined,
                title: 'No parcels in this status',
                message: 'Parcels matching this operational status will be displayed here.',
              ),
            )
          : RefreshIndicator(
              onRefresh: _loadParcels,
              color: AppColors.primary,
              child: ListView.separated(
                padding: const EdgeInsets.all(16),
                itemCount: parcels.length,
                separatorBuilder: (_, _) => const SizedBox(height: 12),
                itemBuilder: (context, index) {
                  final p = parcels[index];
                  return Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: AppColors.border),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        TextButton(
                          onPressed: () => Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (_) =>
                                  ParcelOperationsScreen(parcelId: p.id),
                            ),
                          ),
                          child: const Text('Parcel operations and proofs'),
                        ),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              p.orderId,
                              style: GoogleFonts.figtree(
                                fontSize: 15,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            StatusBadge(
                              status: p.status,
                              customLabel: p.status == 'DELIVERED'
                                  ? 'Delivered'
                                  : null,
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Text(
                          'Delivery Address:',
                          style: GoogleFonts.figtree(
                            fontSize: 11,
                            color: AppColors.textMuted,
                          ),
                        ),
                        Text(
                          p.fullDeliveryAddress,
                          style: GoogleFonts.figtree(
                            fontSize: 13,
                            color: AppColors.textSecondary,
                          ),
                        ),
                        const Divider(height: 20),
                        Row(
                          children: [
                            const Icon(
                              Icons.person_pin_circle_outlined,
                              size: 16,
                              color: AppColors.primary,
                            ),
                            const SizedBox(width: 6),
                            Text(
                              p.assignedRiderName != null
                                  ? 'Rider: ${p.assignedRiderName}'
                                  : 'No rider assigned yet',
                              style: GoogleFonts.figtree(
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
                                color: p.assignedRiderName != null
                                    ? AppColors.textPrimary
                                    : AppColors.textMuted,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  );
                },
              ),
            ),
    );
  }
}
