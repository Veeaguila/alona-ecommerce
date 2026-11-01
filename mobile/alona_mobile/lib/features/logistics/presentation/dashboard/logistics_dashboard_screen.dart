import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/features/logistics/models/logistics_models.dart';
import 'package:alona_mobile/features/logistics/services/logistics_service.dart';
import 'package:alona_mobile/features/logistics/presentation/assignment/rider_assignment_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/incoming/incoming_parcels_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/pickups/pickup_requests_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/proofs/proof_review_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/reports/logistics_reports_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/riders/rider_management_screen.dart';

class LogisticsDashboardScreen extends StatefulWidget {
  final VoidCallback onNavigateToSorting;
  final VoidCallback onNavigateToMonitoring;

  const LogisticsDashboardScreen({
    super.key,
    required this.onNavigateToSorting,
    required this.onNavigateToMonitoring,
  });

  @override
  State<LogisticsDashboardScreen> createState() =>
      _LogisticsDashboardScreenState();
}

class _LogisticsDashboardScreenState extends State<LogisticsDashboardScreen> {
  bool _isLoading = true;
  String? _error;
  LogisticsDashboardModel? _dashboard;

  @override
  void initState() {
    super.initState();
    _loadDashboard();
  }

  Future<void> _loadDashboard() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final data = await LogisticsService.getDashboard();
      if (!mounted) return;
      setState(() {
        _dashboard = data;
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Logistics Operations Hub'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: _loadDashboard,
          ),
        ],
      ),
      body: _isLoading
          ? const Center(
              child: CircularProgressIndicator(color: AppColors.primary),
            )
          : _error != null
          ? Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(_error!),
                  const SizedBox(height: 12),
                  ElevatedButton(
                    onPressed: _loadDashboard,
                    child: const Text('Retry'),
                  ),
                ],
              ),
            )
          : RefreshIndicator(
              onRefresh: _loadDashboard,
              color: AppColors.primary,
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Hub Banner
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: const Color(0xFF0B3C49),
                        borderRadius: BorderRadius.circular(16),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Container(
                                width: 8,
                                height: 8,
                                decoration: const BoxDecoration(
                                  color: Color(0xFF10B981),
                                  shape: BoxShape.circle,
                                ),
                              ),
                              const SizedBox(width: 8),
                              Text(
                                'SORTING CENTER ACTIVE',
                                style: GoogleFonts.figtree(
                                  color: const Color(0xFF10B981),
                                  fontSize: 11,
                                  fontWeight: FontWeight.w800,
                                  letterSpacing: 1,
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 8),
                          Text(
                            'Fulfillment & Dispatch Workflow',
                            style: GoogleFonts.figtree(
                              fontSize: 18,
                              fontWeight: FontWeight.w800,
                              color: Colors.white,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            'Manage arriving order parcels, assign regional zones, and track real-time deliveries.',
                            style: GoogleFonts.figtree(
                              fontSize: 12,
                              color: Colors.white70,
                            ),
                          ),
                        ],
                      ),
                    ),

                    const SizedBox(height: 20),

                    Text(
                      'Operations Overview',
                      style: GoogleFonts.figtree(
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    const SizedBox(height: 12),

                    // Metric Cards Grid
                    GridView.count(
                      physics: const NeverScrollableScrollPhysics(),
                      shrinkWrap: true,
                      crossAxisCount: 2,
                      crossAxisSpacing: 12,
                      mainAxisSpacing: 12,
                      childAspectRatio: 1.1,
                      children: [
                        _kpiCard(
                          title: 'Awaiting Sorting',
                          count: _dashboard?.parcelsAwaitingSorting ?? 0,
                          icon: Icons.sort_rounded,
                          color: const Color(0xFFD97706),
                          bgColor: const Color(0xFFFFFBEB),
                          onTap: widget.onNavigateToSorting,
                        ),
                        _kpiCard(
                          title: 'Awaiting Assignment',
                          count: _dashboard?.parcelsAwaitingAssignment ?? 0,
                          icon: Icons.person_add_alt_1_outlined,
                          color: const Color(0xFF2563EB),
                          bgColor: const Color(0xFFEFF6FF),
                          onTap: () {
                            Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => const RiderAssignmentScreen(),
                              ),
                            );
                          },
                        ),
                        _kpiCard(
                          title: 'Out for Delivery',
                          count: _dashboard?.parcelsOutForDelivery ?? 0,
                          icon: Icons.delivery_dining_outlined,
                          color: const Color(0xFF087F8C),
                          bgColor: const Color(0xFFE8F7F6),
                          onTap: widget.onNavigateToMonitoring,
                        ),
                        _kpiCard(
                          title: 'Pending Pickups',
                          count: _dashboard?.pickupRequestsPending ?? 0,
                          icon: Icons.hail_rounded,
                          color: const Color(0xFF8B5CF6),
                          bgColor: const Color(0xFFF5F3FF),
                          onTap: () {
                            Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => const PickupRequestsScreen(),
                              ),
                            );
                          },
                        ),
                        _kpiCard(
                          title: 'Active Riders',
                          count: _dashboard?.activeApprovedRiders ?? 0,
                          icon: Icons.two_wheeler_outlined,
                          color: const Color(0xFF16A34A),
                          bgColor: const Color(0xFFF0FDF4),
                          onTap: () {
                            Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => const RiderManagementScreen(),
                              ),
                            );
                          },
                        ),
                        _kpiCard(
                          title: 'Pending Riders',
                          count: _dashboard?.ridersPendingApproval ?? 0,
                          icon: Icons.pending_actions_rounded,
                          color: const Color(0xFFDC2626),
                          bgColor: const Color(0xFFFEF2F2),
                          onTap: () {
                            Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => const RiderManagementScreen(
                                  initialFilter: 'pending',
                                ),
                              ),
                            );
                          },
                        ),
                      ],
                    ),

                    const SizedBox(height: 24),

                    Text(
                      'Quick Actions',
                      style: GoogleFonts.figtree(
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    const SizedBox(height: 12),

                    Container(
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: AppColors.border),
                      ),
                      child: Column(
                        children: [
                          _actionItem(
                            icon: Icons.qr_code_scanner_rounded,
                            title: 'Scan / Mark Arrived Parcel',
                            subtitle: 'Check in incoming packages from seller pickups',
                            onTap: () {
                              Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (_) => const IncomingParcelsScreen(),
                                ),
                              );
                            },
                          ),
                          const Divider(height: 1),
                          _actionItem(
                            icon: Icons.checklist_rtl_rounded,
                            title: 'Proof of Delivery Review',
                            subtitle: 'Inspect rider recipient signatures and delivery photos',
                            onTap: () {
                              Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (_) => const ProofReviewScreen(),
                                ),
                              );
                            },
                          ),
                          const Divider(height: 1),
                          _actionItem(
                            icon: Icons.insights_rounded,
                            title: 'Fulfillment & Performance Reports',
                            subtitle: 'Delivery rates, turnaround seconds, and volume',
                            onTap: () {
                              Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (_) =>
                                      const LogisticsReportsScreen(),
                                ),
                              );
                            },
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 40),
                  ],
                ),
              ),
            ),
    );
  }

  Widget _kpiCard({
    required String title,
    required int count,
    required IconData icon,
    required Color color,
    required Color bgColor,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: AppColors.border),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Container(
                  padding: const EdgeInsets.all(6),
                  decoration: BoxDecoration(
                    color: bgColor,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Icon(icon, color: color, size: 20),
                ),
                Text(
                  '$count',
                  style: GoogleFonts.figtree(
                    fontSize: 22,
                    fontWeight: FontWeight.w800,
                    color: AppColors.textPrimary,
                  ),
                ),
              ],
            ),
            Text(
              title,
              style: GoogleFonts.figtree(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: AppColors.textSecondary,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _actionItem({
    required IconData icon,
    required String title,
    required String subtitle,
    required VoidCallback onTap,
  }) {
    return Material(
      color: Colors.transparent,
      child: ListTile(
        leading: Container(
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(
            color: AppColors.primaryLight,
            borderRadius: BorderRadius.circular(10),
          ),
          child: Icon(icon, color: AppColors.primary, size: 20),
        ),
        title: Text(
          title,
          style: GoogleFonts.figtree(fontWeight: FontWeight.w700, fontSize: 14),
        ),
        subtitle: Text(
          subtitle,
          style: GoogleFonts.figtree(fontSize: 12, color: AppColors.textMuted),
        ),
        trailing: const Icon(Icons.chevron_right, color: AppColors.textMuted),
        onTap: onTap,
      ),
    );
  }
}
