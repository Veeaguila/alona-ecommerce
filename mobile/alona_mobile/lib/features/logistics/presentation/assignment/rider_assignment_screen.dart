import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/shared/widgets/empty_state_view.dart';
import 'package:alona_mobile/shared/widgets/status_badge.dart';
import 'package:alona_mobile/features/logistics/models/logistics_models.dart';
import 'package:alona_mobile/features/logistics/services/logistics_service.dart';

class RiderAssignmentScreen extends StatefulWidget {
  const RiderAssignmentScreen({super.key});

  @override
  State<RiderAssignmentScreen> createState() => _RiderAssignmentScreenState();
}

class _RiderAssignmentScreenState extends State<RiderAssignmentScreen> {
  bool _isLoading = true;
  String? _error;
  bool _assigning = false;
  List<ParcelModel> _parcels = [];

  @override
  void initState() {
    super.initState();
    _loadParcels();
  }

  Future<void> _loadParcels() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final parcels = await LogisticsService.getAssignableParcels();

      if (!mounted) return;
      setState(() {
        _parcels = parcels;
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

  Future<void> _showAssignDialog(ParcelModel parcel) async {
    if (_assigning) return;
    setState(() => _assigning = true);
    try {
      final riders = await LogisticsService.getEligibleRiders(parcel.id);
      if (!mounted) return;
      if (riders.isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'No eligible Rider covers this parcel. Check delivery areas, Online presence, capacity and destination hub.',
            ),
          ),
        );
        return;
      }
      final id = await showModalBottomSheet<int>(
        context: context,
        isScrollControlled: true,
        builder: (ctx) => SafeArea(
          child: ListView(
            shrinkWrap: true,
            children: [
              const ListTile(title: Text('Select an eligible delivery Rider')),
              for (final rider in riders)
                ListTile(
                  title: Text(rider.name),
                  subtitle: Text(rider.vehicleType ?? 'Vehicle not recorded'),
                  onTap: () => Navigator.pop(ctx, rider.id),
                ),
            ],
          ),
        ),
      );
      if (id == null || !mounted) return;
      await LogisticsService.assignRider(parcel.id, id);
      if (mounted) await _loadParcels();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.toString())));
      }
    } finally {
      if (mounted) setState(() => _assigning = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Rider Assignment (${_parcels.length})')),
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
                icon: Icons.assignment_turned_in_outlined,
                title: 'No parcels awaiting assignment',
                message:
                    'All sorted parcels have been assigned to delivery riders.',
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
                          StatusBadge(status: p.status),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text(
                        'Address: ${p.fullDeliveryAddress}',
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
                            onPressed: _assigning
                                ? null
                                : () => _showAssignDialog(p),
                            icon: const Icon(
                              Icons.person_add_alt_1_outlined,
                              size: 16,
                            ),
                            label: const Text('Assign Rider'),
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
