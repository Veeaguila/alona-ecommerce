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
import 'package:alona_mobile/features/logistics/presentation/workflow_widgets.dart';

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
    final d = _dashboard;
    final inFlight =
        (d?.parcelsAwaitingSorting ?? 0) +
        (d?.parcelsAwaitingAssignment ?? 0) +
        (d?.parcelsOutForDelivery ?? 0);

    return Scaffold(
      backgroundColor: AppColors.background,
      body: _isLoading
          ? const Center(
              child: CircularProgressIndicator(color: AppColors.primary),
            )
          : _error != null
          ? WorkflowError(_error!, _loadDashboard)
          : RefreshIndicator(
              onRefresh: _loadDashboard,
              color: AppColors.primary,
              child: SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Hub Banner
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.fromLTRB(18, 16, 10, 18),
                      decoration: BoxDecoration(
                        gradient: AppColors.buyerBrandGradient,
                        borderRadius: BorderRadius.circular(22),
                        boxShadow: [
                          BoxShadow(
                            color: AppColors.primaryDark.withValues(
                              alpha: 0.25,
                            ),
                            blurRadius: 18,
                            offset: const Offset(0, 8),
                          ),
                        ],
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 10,
                                  vertical: 4,
                                ),
                                decoration: BoxDecoration(
                                  color: Colors.white.withValues(alpha: 0.14),
                                  borderRadius: BorderRadius.circular(20),
                                ),
                                child: Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    Container(
                                      width: 8,
                                      height: 8,
                                      decoration: const BoxDecoration(
                                        color: Color(0xFF34D399),
                                        shape: BoxShape.circle,
                                      ),
                                    ),
                                    const SizedBox(width: 6),
                                    Text(
                                      'SORTING CENTER ACTIVE',
                                      style: GoogleFonts.figtree(
                                        color: Colors.white,
                                        fontSize: 10.5,
                                        fontWeight: FontWeight.w800,
                                        letterSpacing: 0.8,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              const Spacer(),
                              IconButton(
                                tooltip: 'Refresh',
                                onPressed: _loadDashboard,
                                icon: const Icon(
                                  Icons.refresh_rounded,
                                  color: Colors.white,
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 6),
                          Padding(
                            padding: const EdgeInsets.only(right: 8),
                            child: Text(
                              'Fulfillment & Dispatch Workflow',
                              style: GoogleFonts.figtree(
                                fontSize: 20,
                                fontWeight: FontWeight.w800,
                                height: 1.2,
                                color: Colors.white,
                              ),
                            ),
                          ),
                          const SizedBox(height: 6),
                          Padding(
                            padding: const EdgeInsets.only(right: 8),
                            child: Text(
                              'Manage arriving order parcels, assign regional zones, and track real-time deliveries.',
                              style: GoogleFonts.figtree(
                                fontSize: 12.5,
                                height: 1.4,
                                color: Colors.white.withValues(alpha: 0.8),
                              ),
                            ),
                          ),
                          const SizedBox(height: 16),
                          Container(
                            margin: const EdgeInsets.only(right: 8),
                            padding: const EdgeInsets.symmetric(
                              horizontal: 14,
                              vertical: 12,
                            ),
                            decoration: BoxDecoration(
                              color: Colors.black.withValues(alpha: 0.16),
                              borderRadius: BorderRadius.circular(16),
                            ),
                            child: Row(
                              children: [
                                const Icon(
                                  Icons.inventory_2_rounded,
                                  color: AppColors.accent,
                                  size: 20,
                                ),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: Text(
                                    'Parcels in the pipeline',
                                    style: GoogleFonts.figtree(
                                      fontSize: 12.5,
                                      fontWeight: FontWeight.w600,
                                      color: Colors.white.withValues(
                                        alpha: 0.85,
                                      ),
                                    ),
                                  ),
                                ),
                                CountUpText(
                                  inFlight,
                                  style: GoogleFonts.figtree(
                                    fontSize: 22,
                                    fontWeight: FontWeight.w900,
                                    color: Colors.white,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),

                    const SizedBox(height: 24),

                    _sectionHeader(
                      'Operations Overview',
                      'Tap a card to open its workflow',
                    ),
                    const SizedBox(height: 12),

                    // Metric Cards Grid
                    GridView.count(
                      physics: const NeverScrollableScrollPhysics(),
                      shrinkWrap: true,
                      crossAxisCount: 2,
                      crossAxisSpacing: 12,
                      mainAxisSpacing: 12,
                      childAspectRatio: MediaQuery.sizeOf(context).width < 360
                          ? 1.0
                          : 1.2,
                      children: [
                        _kpiCard(
                          index: 0,
                          title: 'Awaiting Sorting',
                          count: _dashboard?.parcelsAwaitingSorting ?? 0,
                          icon: Icons.sort_rounded,
                          color: const Color(0xFFD97706),
                          bgColor: const Color(0xFFFFFBEB),
                          onTap: widget.onNavigateToSorting,
                        ),
                        _kpiCard(
                          index: 1,
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
                          index: 2,
                          title: 'Out for Delivery',
                          count: _dashboard?.parcelsOutForDelivery ?? 0,
                          icon: Icons.delivery_dining_outlined,
                          color: const Color(0xFF087F8C),
                          bgColor: const Color(0xFFE8F7F6),
                          onTap: widget.onNavigateToMonitoring,
                        ),
                        _kpiCard(
                          index: 3,
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
                          index: 4,
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
                          index: 5,
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

                    // PAYMENT DISTRIBUTION & COMMISSION MODEL (Web Model Sync)
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(18),
                        border: Border.all(color: AppColors.border),
                        boxShadow: const [
                          BoxShadow(
                            color: Color(0x0A000000),
                            blurRadius: 8,
                            offset: Offset(0, 2),
                          ),
                        ],
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Container(
                                padding: const EdgeInsets.all(6),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFE8F7F6),
                                  borderRadius: BorderRadius.circular(8),
                                ),
                                child: const Icon(
                                  Icons.account_balance_wallet_outlined,
                                  color: Color(0xFF087F8C),
                                  size: 18,
                                ),
                              ),
                              const SizedBox(width: 10),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      'Revenue & Commission Distribution',
                                      style: GoogleFonts.figtree(
                                        fontSize: 14,
                                        fontWeight: FontWeight.w800,
                                        color: AppColors.textPrimary,
                                      ),
                                    ),
                                    Text(
                                      'Synchronized with Alona Web Marketplace model',
                                      style: GoogleFonts.figtree(
                                        fontSize: 11,
                                        color: AppColors.mutedText,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ),

                          const SizedBox(height: 14),
                          const Divider(height: 1, color: Color(0xFFE2E8F0)),
                          const SizedBox(height: 14),

                          // Shipping Fee Distribution (70 / 20 / 10)
                          Wrap(
                            alignment: WrapAlignment.spaceBetween,
                            spacing: 8,
                            runSpacing: 4,
                            children: [
                              Text(
                                'Shipping Fee Distribution',
                                style: GoogleFonts.figtree(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w700,
                                  color: const Color(0xFF334155),
                                ),
                              ),
                              Text(
                                '100% of Delivery Fee',
                                style: GoogleFonts.figtree(
                                  fontSize: 11,
                                  color: const Color(0xFF64748B),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 8),

                          // Segmented bar
                          ClipRRect(
                            borderRadius: BorderRadius.circular(6),
                            child: SizedBox(
                              height: 10,
                              child: Row(
                                children: [
                                  Expanded(
                                    flex: 70,
                                    child: Container(
                                      color: const Color(0xFF087F8C),
                                    ),
                                  ),
                                  Expanded(
                                    flex: 20,
                                    child: Container(
                                      color: const Color(0xFF38BDF8),
                                    ),
                                  ),
                                  Expanded(
                                    flex: 10,
                                    child: Container(
                                      color: const Color(0xFF94A3B8),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),

                          const SizedBox(height: 10),

                          Wrap(
                            spacing: 14,
                            runSpacing: 6,
                            children: [
                              _shareChip(
                                'Rider',
                                '70%',
                                const Color(0xFF087F8C),
                              ),
                              _shareChip(
                                'Logistics Hub',
                                '20%',
                                const Color(0xFF0284C7),
                              ),
                              _shareChip(
                                'Platform',
                                '10%',
                                const Color(0xFF64748B),
                              ),
                            ],
                          ),

                          const SizedBox(height: 14),
                          const Divider(height: 1, color: Color(0xFFE2E8F0)),
                          const SizedBox(height: 14),

                          // Product Sales Distribution (90 / 10)
                          Wrap(
                            alignment: WrapAlignment.spaceBetween,
                            spacing: 8,
                            runSpacing: 4,
                            children: [
                              Text(
                                'Product Items Payout',
                                style: GoogleFonts.figtree(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w700,
                                  color: const Color(0xFF334155),
                                ),
                              ),
                              Text(
                                '100% of Product Value',
                                style: GoogleFonts.figtree(
                                  fontSize: 11,
                                  color: const Color(0xFF64748B),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 8),

                          ClipRRect(
                            borderRadius: BorderRadius.circular(6),
                            child: SizedBox(
                              height: 10,
                              child: Row(
                                children: [
                                  Expanded(
                                    flex: 90,
                                    child: Container(
                                      color: const Color(0xFF10B981),
                                    ),
                                  ),
                                  Expanded(
                                    flex: 10,
                                    child: Container(
                                      color: const Color(0xFF94A3B8),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),

                          const SizedBox(height: 10),

                          Wrap(
                            spacing: 14,
                            runSpacing: 6,
                            children: [
                              _shareChip(
                                'Seller Earnings',
                                '90%',
                                const Color(0xFF10B981),
                              ),
                              _shareChip(
                                'Platform Fee',
                                '10%',
                                const Color(0xFF64748B),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),

                    const SizedBox(height: 24),

                    _sectionHeader('Quick Actions', 'Common hub tasks'),
                    const SizedBox(height: 12),

                    Container(
                      padding: const EdgeInsets.symmetric(vertical: 6),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(18),
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
                          const Divider(height: 1, indent: 16, endIndent: 16),
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
                          const Divider(height: 1, indent: 16, endIndent: 16),
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
                    const SizedBox(height: 16),
                  ],
                ),
              ),
            ),
    );
  }

  Widget _sectionHeader(String title, String subtitle) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: GoogleFonts.figtree(
              fontSize: 16,
              fontWeight: FontWeight.w800,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            subtitle,
            style: GoogleFonts.figtree(
              fontSize: 12,
              color: AppColors.textMuted,
            ),
          ),
        ],
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
    int index = 0,
  }) {
    return FadeSlideIn(
      index: index,
      child: Material(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(18),
          child: Ink(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: AppColors.border),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.03),
                  blurRadius: 10,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Row(
                  children: [
                    Container(
                      width: 38,
                      height: 38,
                      decoration: BoxDecoration(
                        color: bgColor,
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Icon(icon, color: color, size: 20),
                    ),
                    const Spacer(),
                    Icon(
                      Icons.arrow_outward_rounded,
                      size: 16,
                      color: color.withValues(alpha: 0.6),
                    ),
                  ],
                ),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    CountUpText(
                      count,
                      style: GoogleFonts.figtree(
                        fontSize: 26,
                        height: 1.1,
                        fontWeight: FontWeight.w900,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: GoogleFonts.figtree(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
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

  Widget _shareChip(String label, String pct, Color color) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(
          width: 8,
          height: 8,
          decoration: BoxDecoration(color: color, shape: BoxShape.circle),
        ),
        const SizedBox(width: 5),
        Text(
          '$label: ',
          style: GoogleFonts.figtree(
            fontSize: 11,
            color: const Color(0xFF64748B),
          ),
        ),
        Text(
          pct,
          style: GoogleFonts.figtree(
            fontSize: 11,
            fontWeight: FontWeight.w800,
            color: color,
          ),
        ),
      ],
    );
  }
}
