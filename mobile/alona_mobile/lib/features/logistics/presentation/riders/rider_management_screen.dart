import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/shared/widgets/empty_state_view.dart';
import 'package:alona_mobile/shared/widgets/status_badge.dart';
import 'package:alona_mobile/features/logistics/presentation/workflow_widgets.dart';
import 'package:alona_mobile/core/network/api_client.dart';
import 'package:alona_mobile/features/logistics/models/logistics_models.dart';
import 'package:alona_mobile/features/logistics/services/logistics_service.dart';

class RiderManagementScreen extends StatefulWidget {
  final String? initialFilter;

  const RiderManagementScreen({super.key, this.initialFilter});

  @override
  State<RiderManagementScreen> createState() => _RiderManagementScreenState();
}

class _RiderManagementScreenState extends State<RiderManagementScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;
  final List<String> _filters = ['', 'pending', 'approved'];
  final List<String> _filterLabels = [
    'All Riders',
    'Pending Approval',
    'Approved',
  ];

  bool _isLoading = true;
  String? _error;
  List<RiderModel> _riders = [];

  @override
  void initState() {
    super.initState();
    int initIdx = widget.initialFilter == 'pending' ? 1 : 0;
    _tabController = TabController(
      length: _filters.length,
      vsync: this,
      initialIndex: initIdx,
    );
    _tabController.addListener(() {
      if (!_tabController.indexIsChanging) {
        _loadRiders(_filters[_tabController.index]);
      }
    });

    _loadRiders(_filters[initIdx]);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _loadRiders(String status) async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final list = await LogisticsService.getRiders(
        status: status.isEmpty ? null : status,
      );
      if (!mounted) return;
      setState(() {
        _riders = list;
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

  Future<void> _approveRider(int id) async {
    if (_isLoading) return;
    setState(() => _isLoading = true);
    try {
      if (!await confirmMovement(
        context,
        'Approve this Rider application after reviewing the submitted documents in the web portal?',
      )) {
        return;
      }
      await LogisticsService.approveRider(id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Rider approved successfully!'),
          backgroundColor: AppColors.success,
        ),
      );
      await _loadRiders(_filters[_tabController.index]);
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: AppColors.error),
      );
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _rejectRider(int id) async {
    if (_isLoading) return;
    setState(() => _isLoading = true);
    try {
      final reason = await requestText(
        context,
        title: 'Reason for rejecting this Rider',
      );
      if (reason == null) return;
      await LogisticsService.rejectRider(id, reason);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Rider application rejected.')),
      );
      await _loadRiders(_filters[_tabController.index]);
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: AppColors.error),
      );
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _toggleActive(RiderModel rider) async {
    if (_isLoading) return;
    setState(() => _isLoading = true);
    try {
      if (rider.isActive) {
        final reason = await requestText(
          context,
          title: 'Reason for suspending ${rider.name}',
        );
        if (reason == null) return;
        await LogisticsService.deactivateRider(rider.id, reason);
      } else {
        await LogisticsService.activateRider(rider.id);
      }
      await _loadRiders(_filters[_tabController.index]);
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: AppColors.error),
      );
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Rider Fleet Management'),
        bottom: TabBar(
          controller: _tabController,
          tabs: _filterLabels.map((l) => Tab(text: l)).toList(),
        ),
      ),
      body: _isLoading
          ? const Center(
              child: CircularProgressIndicator(color: AppColors.primary),
            )
          : _error != null
          ? Center(child: Text(_error!))
          : _riders.isEmpty
          ? const Padding(
              padding: EdgeInsets.all(24),
              child: EmptyStateView(
                icon: Icons.two_wheeler_outlined,
                title: 'No riders found',
                message:
                    'Riders in this approval category will be listed here.',
              ),
            )
          : ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: _riders.length,
              separatorBuilder: (_, _) => const SizedBox(height: 12),
              itemBuilder: (context, index) {
                final r = _riders[index];
                final isPending = r.approvalStatus.toLowerCase() == 'pending';

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
                      Row(
                        children: [
                          CircleAvatar(
                            backgroundColor: AppColors.primaryLight,
                            child: const Icon(
                              Icons.two_wheeler_rounded,
                              color: AppColors.primary,
                              size: 20,
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  r.name,
                                  style: GoogleFonts.figtree(
                                    fontSize: 15,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                                if (r.phone != null)
                                  Text(
                                    r.phone!,
                                    style: GoogleFonts.figtree(
                                      fontSize: 12,
                                      color: AppColors.textMuted,
                                    ),
                                  ),
                              ],
                            ),
                          ),
                          StatusBadge(status: r.approvalStatus),
                        ],
                      ),
                      const SizedBox(height: 12),
                      Wrap(
                        spacing: 12,
                        runSpacing: 8,
                        children: [
                          _metaItem('Vehicle', r.vehicleType ?? 'Not recorded'),
                          const SizedBox(width: 20),
                          _metaItem('Plate Number', r.plateNumber ?? 'N/A'),
                          const SizedBox(width: 20),
                          _metaItem(
                            'Fleet Status',
                            r.isActive ? 'Active' : 'Inactive',
                          ),
                        ],
                      ),
                      const Divider(height: 20),
                      if (r.approvalStatus == 'approved' && r.isActive)
                        TextButton(
                          onPressed: _isLoading
                              ? null
                              : () => _manageCoverage(r),
                          child: const Text('Manage delivery areas'),
                        ),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.end,
                        children: [
                          if (isPending) ...[
                            OutlinedButton(
                              onPressed: () => _rejectRider(r.id),
                              style: OutlinedButton.styleFrom(
                                foregroundColor: AppColors.error,
                              ),
                              child: const Text('Reject'),
                            ),
                            const SizedBox(width: 8),
                            ElevatedButton(
                              onPressed: () => _approveRider(r.id),
                              style: ElevatedButton.styleFrom(
                                backgroundColor: AppColors.success,
                              ),
                              child: const Text('Approve'),
                            ),
                          ] else if (r.approvalStatus == 'approved') ...[
                            OutlinedButton(
                              onPressed: () => _toggleActive(r),
                              child: Text(
                                r.isActive ? 'Deactivate' : 'Activate',
                              ),
                            ),
                          ],
                        ],
                      ),
                    ],
                  ),
                );
              },
            ),
    );
  }

  Future<void> _manageCoverage(RiderModel rider) async {
    if (_isLoading) return;
    setState(() => _isLoading = true);
    try {
      final data = await LogisticsService.getData(
        'riders/${rider.id}/delivery-areas',
      );
      if (!mounted) return;
      if (data['can_manage'] != true) {
        throw ApiException(data['reason'] ?? 'Coverage cannot be changed.');
      }
      final selected = Set<int>.from(data['area_ids']);
      final areas = LogisticsService.rows(data['areas']);
      final save = await showDialog<bool>(
        context: context,
        builder: (ctx) => StatefulBuilder(
          builder: (ctx, update) => AlertDialog(
            title: Text('Delivery areas: ${rider.name}'),
            content: SizedBox(
              width: double.maxFinite,
              child: ListView(
                shrinkWrap: true,
                children: [
                  if (areas.isEmpty)
                    const Text(
                      'Create the actual destination city service areas first.',
                    ),
                  for (final area in areas)
                    CheckboxListTile(
                      value: selected.contains(area['id']),
                      title: Text(area['name']),
                      onChanged: (value) => update(() {
                        if (value == true) {
                          selected.add(area['id']);
                        } else {
                          selected.remove(area['id']);
                        }
                      }),
                    ),
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(ctx, false),
                child: const Text('Cancel'),
              ),
              FilledButton(
                onPressed: () => Navigator.pop(ctx, true),
                child: const Text('Save coverage'),
              ),
            ],
          ),
        ),
      );
      if (save != true) return;
      await ApiClient.put(
        'api/sorting-center/riders/${rider.id}/delivery-areas',
        body: {'area_ids': selected.toList()},
      );
      if (mounted) await _loadRiders(_filters[_tabController.index]);
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.toString())));
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Widget _metaItem(String label, String value) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: GoogleFonts.figtree(fontSize: 10, color: AppColors.textLight),
        ),
        Text(
          value,
          style: GoogleFonts.figtree(fontSize: 12, fontWeight: FontWeight.w600),
        ),
      ],
    );
  }
}
