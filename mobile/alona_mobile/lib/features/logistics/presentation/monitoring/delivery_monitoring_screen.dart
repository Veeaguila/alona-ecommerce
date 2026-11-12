import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/features/logistics/presentation/workflow_widgets.dart';
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
              child: FadeSlideIn(
                child: EmptyStateView(
                  icon: Icons.local_shipping_outlined,
                  title: 'No parcels in this status',
                  message: 'Parcels matching this operational status will be displayed here.',
                ),
              ),
            )
          : RefreshIndicator(
              onRefresh: _loadParcels,
              color: AppColors.primary,
              child: ListView.separated(
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
                itemCount: parcels.length,
                separatorBuilder: (_, _) => const SizedBox(height: 12),
                itemBuilder: (context, index) {
                  final p = parcels[index];
                  final delivered = p.status == 'DELIVERED';
                  final hasRider = p.assignedRiderName != null;
                  return FadeSlideIn(
                    index: index,
                    child: AccentCard(
                      accent: delivered ? AppColors.success : AppColors.primary,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              IconTile(
                                delivered
                                    ? Icons.task_alt_rounded
                                    : Icons.local_shipping_rounded,
                                color: delivered
                                    ? AppColors.success
                                    : AppColors.primary,
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Text(
                                  p.orderId,
                                  style: GoogleFonts.figtree(
                                    fontSize: 15,
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                              ),
                              StatusBadge(
                                status: p.status,
                                customLabel: delivered ? 'Delivered' : null,
                              ),
                            ],
                          ),
                          const SizedBox(height: 12),
                          Text(
                            'Delivery Address:',
                            style: GoogleFonts.figtree(
                              fontSize: 11,
                              fontWeight: FontWeight.w600,
                              color: AppColors.textMuted,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            p.fullDeliveryAddress,
                            style: GoogleFonts.figtree(
                              fontSize: 13,
                              height: 1.4,
                              color: AppColors.textSecondary,
                            ),
                          ),
                          const SizedBox(height: 12),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 10,
                              vertical: 6,
                            ),
                            decoration: BoxDecoration(
                              color: hasRider
                                  ? AppColors.lightTeal
                                  : AppColors.surfaceAlt,
                              borderRadius: BorderRadius.circular(20),
                            ),
                            child: Row(
                              mainAxisSize: MainAxisSize.min,
                              children: [
                                Icon(
                                  Icons.person_pin_circle_outlined,
                                  size: 16,
                                  color: hasRider
                                      ? AppColors.primary
                                      : AppColors.textMuted,
                                ),
                                const SizedBox(width: 6),
                                Flexible(
                                  child: Text(
                                    hasRider
                                        ? 'Rider: ${p.assignedRiderName}'
                                        : 'No rider assigned yet',
                                    overflow: TextOverflow.ellipsis,
                                    style: GoogleFonts.figtree(
                                      fontSize: 12,
                                      fontWeight: FontWeight.w700,
                                      color: hasRider
                                          ? AppColors.primaryDark
                                          : AppColors.textMuted,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                          const Divider(height: 24),
                          Align(
                            alignment: Alignment.centerRight,
                            child: TextButton.icon(
                              onPressed: () => Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (_) =>
                                      ParcelOperationsScreen(parcelId: p.id),
                                ),
                              ),
                              icon: const Icon(
                                Icons.open_in_new_rounded,
                                size: 16,
                              ),
                              label: const Text('Parcel operations and proofs'),
                            ),
                          ),
                        ],
                      ),
                    ),
                  );
                },
              ),
            ),
    );
  }
}
