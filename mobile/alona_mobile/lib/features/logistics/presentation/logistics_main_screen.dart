import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/core/services/auth_state.dart';
import 'package:alona_mobile/features/buyer/presentation/buyer_main_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/assignment/rider_assignment_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/auth/logistics_login_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/dashboard/logistics_dashboard_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/incoming/incoming_parcels_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/monitoring/delivery_monitoring_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/pickups/pickup_requests_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/proofs/proof_review_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/reports/logistics_reports_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/riders/rider_management_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/sorting/parcel_sorting_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/hubs/hub_workspace_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/payroll/rider_payouts_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/outbound/outbound_parcels_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/logistics_access_gate.dart';
import 'package:alona_mobile/core/network/api_client.dart';
import 'package:alona_mobile/features/logistics/presentation/returns/returns_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/cod/cod_screen.dart';

class LogisticsMainScreen extends StatefulWidget {
  final AuthState authState;

  const LogisticsMainScreen({super.key, required this.authState});

  @override
  State<LogisticsMainScreen> createState() => _LogisticsMainScreenState();
}

class _LogisticsMainScreenState extends State<LogisticsMainScreen> {
  int _currentIndex = 0;

  @override
  Widget build(BuildContext context) {
    return LogisticsAccessGate(
      authState: widget.authState,
      owner: _buildOwner(context),
      staff: _buildStaff(context),
    );
  }

  Widget _buildStaff(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Logistics hub'),
      actions: [
        IconButton(
          onPressed: () => widget.authState.switchModule('buyer'),
          icon: const Icon(Icons.shopping_bag_outlined),
        ),
        IconButton(onPressed: _logout, icon: const Icon(Icons.logout)),
      ],
    ),
    body: const HubWorkspaceScreen(),
  );

  Future<void> _logout() async {
    try {
      await ApiClient.post('api/logistics/logout');
    } catch (_) {}
    await widget.authState.clearLogisticsSession();
  }

  Widget _buildOwner(BuildContext context) {
    final sc = widget.authState.sortingCenter;
    final scName = sc?['business_name'] ?? 'Logistics Sorting Center';
    final user = widget.authState.logisticsUser;
    final userName = user?['name'] ?? 'Logistics Staff';

    final pages = [
      LogisticsDashboardScreen(
        onNavigateToSorting: () => setState(() => _currentIndex = 2),
        onNavigateToMonitoring: () => setState(() => _currentIndex = 3),
      ),
      const IncomingParcelsScreen(),
      const ParcelSortingScreen(),
      const DeliveryMonitoringScreen(),
      _buildHubGrid(context, userName, scName),
    ];

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        backgroundColor: AppColors.primaryDark,
        foregroundColor: Colors.white,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                const Icon(
                  Icons.local_shipping_rounded,
                  size: 20,
                  color: AppColors.accent,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Alona Logistics',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: GoogleFonts.figtree(
                      fontWeight: FontWeight.w800,
                      fontSize: 18,
                      color: Colors.white,
                    ),
                  ),
                ),
              ],
            ),
            Text(
              scName,
              style: GoogleFonts.figtree(
                fontSize: 11,
                color: Colors.white70,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ),
        actions: [
          TextButton.icon(
            onPressed: () async {
              await widget.authState.switchModule('buyer');
              if (!context.mounted) return;
              Navigator.pushAndRemoveUntil(
                context,
                MaterialPageRoute(
                  builder: (_) => BuyerMainScreen(authState: widget.authState),
                ),
                (route) => false,
              );
            },
            icon: const Icon(
              Icons.shopping_bag_outlined,
              color: Colors.white,
              size: 16,
            ),
            label: const Text(
              'Buyer App',
              style: TextStyle(
                color: Colors.white,
                fontSize: 12,
                fontWeight: FontWeight.bold,
              ),
            ),
            style: TextButton.styleFrom(
              backgroundColor: Colors.white.withValues(alpha: 0.18),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(20),
              ),
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            ),
          ),
          const SizedBox(width: 10),
        ],
      ),
      body: KeyedSubtree(
        key: ValueKey(_currentIndex),
        child: pages[_currentIndex],
      ),
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          border: const Border(
            top: BorderSide(color: AppColors.border, width: 1),
          ),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.04),
              blurRadius: 10,
              offset: const Offset(0, -3),
            ),
          ],
        ),
        child: BottomNavigationBar(
          currentIndex: _currentIndex,
          onTap: (index) => setState(() => _currentIndex = index),
          selectedItemColor: AppColors.primary,
          unselectedItemColor: AppColors.textMuted,
          showUnselectedLabels: true,
          type: BottomNavigationBarType.fixed,
          backgroundColor: Colors.white,
          elevation: 0,
          selectedLabelStyle: GoogleFonts.figtree(
            fontSize: 11,
            fontWeight: FontWeight.w700,
          ),
          unselectedLabelStyle: GoogleFonts.figtree(
            fontSize: 11,
            fontWeight: FontWeight.w500,
          ),
          items: const [
            BottomNavigationBarItem(
              icon: Icon(Icons.dashboard_outlined),
              activeIcon: Icon(Icons.dashboard_rounded),
              label: 'Dashboard',
            ),
            BottomNavigationBarItem(
              icon: Icon(Icons.qr_code_scanner_outlined),
              activeIcon: Icon(Icons.qr_code_scanner_rounded),
              label: 'Check-In',
            ),
            BottomNavigationBarItem(
              icon: Icon(Icons.category_outlined),
              activeIcon: Icon(Icons.category_rounded),
              label: 'Sorting',
            ),
            BottomNavigationBarItem(
              icon: Icon(Icons.track_changes_outlined),
              activeIcon: Icon(Icons.track_changes_rounded),
              label: 'Monitoring',
            ),
            BottomNavigationBarItem(
              icon: Icon(Icons.grid_view_outlined),
              activeIcon: Icon(Icons.grid_view_rounded),
              label: 'Operations',
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildHubGrid(BuildContext context, String userName, String scName) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // User & Hub Header
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [AppColors.primaryDark, AppColors.primary],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(16),
              boxShadow: [
                BoxShadow(
                  color: AppColors.primary.withValues(alpha: 0.2),
                  blurRadius: 10,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: Row(
              children: [
                CircleAvatar(
                  radius: 26,
                  backgroundColor: Colors.white.withValues(alpha: 0.2),
                  child: const Icon(
                    Icons.warehouse_rounded,
                    color: Colors.white,
                    size: 28,
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        userName,
                        style: GoogleFonts.figtree(
                          fontSize: 16,
                          fontWeight: FontWeight.w800,
                          color: Colors.white,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        scName,
                        style: GoogleFonts.figtree(
                          fontSize: 12,
                          color: Colors.white70,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 2,
                        ),
                        decoration: BoxDecoration(
                          color: AppColors.accent,
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: const Text(
                          'AUTHORIZED LOGISTICS',
                          style: TextStyle(
                            fontSize: 10,
                            fontWeight: FontWeight.bold,
                            color: AppColors.textPrimary,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 20),

          Text(
            'Operational Workflows',
            style: GoogleFonts.figtree(
              fontSize: 16,
              fontWeight: FontWeight.w800,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 12),

          GridView.count(
            crossAxisCount: 2,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            crossAxisSpacing: 12,
            mainAxisSpacing: 12,
            childAspectRatio: MediaQuery.sizeOf(context).width < 360
                ? 0.9
                : 1.15,
            children: [
              _buildOpCard(
                title: 'Hub workspace',
                subtitle: 'Receive, dispatch and receive transfers',
                icon: Icons.warehouse_outlined,
                color: Colors.teal,
                onTap: () => Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const HubWorkspaceScreen()),
                ),
              ),
              _buildOpCard(
                title: 'Return to Seller',
                subtitle: 'Actual custody and Seller receipt',
                icon: Icons.assignment_return_outlined,
                color: Colors.orange,
                onTap: () => Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const ReturnsScreen()),
                ),
              ),
              _buildOpCard(
                title: 'COD cash receipt',
                subtitle: 'Review actual Rider remittances',
                icon: Icons.receipt_long_outlined,
                color: Colors.green,
                onTap: () => Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const CodScreen()),
                ),
              ),
              _buildOpCard(
                title: 'Rider payouts',
                subtitle: 'Rates, grouped payments and Rider receipt',
                icon: Icons.payments_outlined,
                color: AppColors.primary,
                onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const RiderPayoutsScreen())),
              ),
              _buildOpCard(
                title: 'Outbound readiness',
                subtitle: 'Check the assigned delivery handoff',
                icon: Icons.output,
                color: Colors.blue,
                onTap: () => Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (_) => const OutboundParcelsScreen(),
                  ),
                ),
              ),
              _buildOpCard(
                title: 'Rider Assignment',
                subtitle: 'Assign parcels to riders',
                icon: Icons.person_add_alt_1_outlined,
                color: AppColors.primary,
                onTap: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => const RiderAssignmentScreen(),
                    ),
                  );
                },
              ),
              _buildOpCard(
                title: 'Rider Fleet',
                subtitle: 'Manage active delivery riders',
                icon: Icons.two_wheeler_outlined,
                color: Colors.indigo,
                onTap: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => const RiderManagementScreen(),
                    ),
                  );
                },
              ),
              _buildOpCard(
                title: 'Pickup Requests',
                subtitle: 'Seller warehouse pickups',
                icon: Icons.storefront_outlined,
                color: Colors.deepOrange,
                onTap: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => const PickupRequestsScreen(),
                    ),
                  );
                },
              ),
              _buildOpCard(
                title: 'Proof Verification',
                subtitle: 'Delivery photo audit',
                icon: Icons.verified_outlined,
                color: Colors.teal,
                onTap: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => const ProofReviewScreen(),
                    ),
                  );
                },
              ),
              _buildOpCard(
                title: 'Reports & Analytics',
                subtitle: 'Turnaround & status metrics',
                icon: Icons.analytics_outlined,
                color: Colors.purple,
                onTap: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => const LogisticsReportsScreen(),
                    ),
                  );
                },
              ),
              _buildOpCard(
                title: 'Switch to Buyer',
                subtitle: 'Browse marketplace products',
                icon: Icons.shopping_bag_outlined,
                color: AppColors.accent,
                onTap: () async {
                  await widget.authState.switchModule('buyer');
                  if (!context.mounted) return;
                  Navigator.pushAndRemoveUntil(
                    context,
                    MaterialPageRoute(
                      builder: (_) =>
                          BuyerMainScreen(authState: widget.authState),
                    ),
                    (route) => false,
                  );
                },
              ),
            ],
          ),

          const SizedBox(height: 24),

          // Logout Action
          Center(
            child: TextButton.icon(
              onPressed: () async {
                await _logout();
                if (!context.mounted) return;
                Navigator.pushAndRemoveUntil(
                  context,
                  MaterialPageRoute(
                    builder: (_) =>
                        LogisticsLoginScreen(authState: widget.authState),
                  ),
                  (route) => false,
                );
              },
              icon: const Icon(Icons.logout_rounded, color: Colors.red),
              label: const Text(
                'Logistics Sign Out',
                style: TextStyle(
                  color: Colors.red,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
          ),
          const SizedBox(height: 30),
        ],
      ),
    );
  }

  Widget _buildOpCard({
    required String title,
    required String subtitle,
    required IconData icon,
    required Color color,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: AppColors.border),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.02),
              blurRadius: 6,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(icon, color: color, size: 22),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: GoogleFonts.figtree(
                    fontWeight: FontWeight.w700,
                    fontSize: 13,
                    color: AppColors.textPrimary,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  style: GoogleFonts.figtree(
                    fontSize: 11,
                    color: AppColors.textMuted,
                  ),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
