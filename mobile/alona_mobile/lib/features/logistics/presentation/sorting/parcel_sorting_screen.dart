import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/shared/widgets/alona_button.dart';
import 'package:alona_mobile/shared/widgets/empty_state_view.dart';
import 'package:alona_mobile/shared/widgets/status_badge.dart';
import 'package:alona_mobile/features/logistics/models/logistics_models.dart';
import 'package:alona_mobile/features/logistics/services/logistics_service.dart';
import 'package:alona_mobile/features/logistics/presentation/workflow_widgets.dart';

class ParcelSortingScreen extends StatefulWidget {
  const ParcelSortingScreen({super.key});

  @override
  State<ParcelSortingScreen> createState() => _ParcelSortingScreenState();
}

class _ParcelSortingScreenState extends State<ParcelSortingScreen> {
  bool _isLoading = true;
  bool _mutating = false;
  String? _error;
  List<ParcelModel> _parcels = [];
  List<AreaModel> _areas = [];

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final parcels = await LogisticsService.getUnsortedParcels();
      final areas = await LogisticsService.getAreas();
      if (!mounted) return;
      setState(() {
        _parcels = parcels;
        _areas = areas;
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

  Future<void> _sortParcel(ParcelModel parcel, int areaId) async {
    if (_mutating) return;
    setState(() => _mutating = true);
    try {
      await LogisticsService.sortParcel(parcel.id, areaId);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Parcel sorted to area successfully!'),
          backgroundColor: AppColors.success,
        ),
      );
      _loadData();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: AppColors.error),
      );
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }

  void _showSortDialog(ParcelModel parcel) {
    if (_mutating) return;
    int? selectedAreaId;

    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setSheetState) => Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Sort Parcel to Area',
                style: GoogleFonts.figtree(
                  fontSize: 18,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                'Parcel #${parcel.orderId}\nDestination: ${parcel.fullDeliveryAddress}',
                style: GoogleFonts.figtree(
                  fontSize: 12,
                  color: AppColors.textMuted,
                ),
              ),
              const SizedBox(height: 16),
              if (_areas.isEmpty)
                Text(
                  'No sorting areas created yet. Please add an area first.',
                  style: GoogleFonts.figtree(color: AppColors.error),
                )
              else
                DropdownButtonFormField<int>(
                  initialValue: selectedAreaId,
                  decoration: const InputDecoration(
                    labelText: 'Select Dispatch Area / Zone',
                  ),
                  items: _areas.map((a) {
                    final label =
                        '${a.municipality ?? a.name}${a.province == null ? '' : ', ${a.province}'}';
                    return DropdownMenuItem(value: a.id, child: Text(label));
                  }).toList(),
                  onChanged: (val) => setSheetState(() => selectedAreaId = val),
                ),
              const SizedBox(height: 20),
              AlonaButton(
                text: 'Confirm Sorting',
                onPressed: selectedAreaId != null
                    ? () {
                        Navigator.pop(ctx);
                        _sortParcel(parcel, selectedAreaId!);
                      }
                    : null,
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _showCreateAreaDialog() async {
    if (_mutating) return;
    setState(() => _mutating = true);
    try {
      final name = await requestText(
        context,
        title: 'Destination city service area name',
        minLength: 1,
        maxLength: 255,
      );
      if (name == null) return;
      await LogisticsService.createArea(name: name);
      if (mounted) await _loadData();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.toString())));
      }
    } finally {
      if (mounted) setState(() => _mutating = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Parcel Sorting (${_parcels.length})'),
        actions: [
          IconButton(
            icon: const Icon(Icons.add_location_alt_outlined),
            tooltip: 'Add Sorting Area',
            onPressed: _showCreateAreaDialog,
          ),
        ],
      ),
      body: _isLoading
          ? const Center(
              child: CircularProgressIndicator(color: AppColors.primary),
            )
          : _error != null
          ? Center(child: Text(_error!))
          : _parcels.isEmpty
          ? const Padding(
              padding: EdgeInsets.all(24),
              child: EmptyStateView(
                icon: Icons.check_circle_outline,
                title: 'All arrived parcels sorted',
                message: 'No pending unsorted parcels at the center right now.',
              ),
            )
          : ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: _parcels.length,
              separatorBuilder: (_, _) => const SizedBox(height: 12),
              itemBuilder: (context, index) {
                final p = _parcels[index];
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
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            p.orderId,
                            style: GoogleFonts.figtree(
                              fontSize: 15,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const StatusBadge(
                            status: 'AT_SORTING_CENTER',
                            customLabel: 'Unsorted',
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text(
                        'Destination: ${p.fullDeliveryAddress}',
                        style: GoogleFonts.figtree(
                          fontSize: 13,
                          color: AppColors.textSecondary,
                        ),
                      ),
                      const Divider(height: 20),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.end,
                        children: [
                          ElevatedButton.icon(
                            onPressed: _mutating
                                ? null
                                : () => _showSortDialog(p),
                            icon: const Icon(Icons.sort_rounded, size: 16),
                            label: const Text('Sort to Area'),
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppColors.primary,
                              padding: const EdgeInsets.symmetric(
                                horizontal: 14,
                                vertical: 8,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                );
              },
            ),
    );
  }
}
