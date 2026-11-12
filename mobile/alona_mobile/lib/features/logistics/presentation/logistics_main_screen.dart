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
import 'package:alona_mobile/features/logistics/presentation/workflow_widgets.dart';
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
    if (!widget.authState.isLogisticsAuthenticated) {
      return LogisticsLoginScreen(authState: widget.authState);
    }
    return LogisticsAccessGate(
      authState: widget.authState,
      owner: _buildOwner(context),
      staff: _buildStaff(context),
    );
  }

  Widget _buildStaff(BuildContext context) => Scaffold(
    appBar: AppBar(
      backgroundColor: AppColors.primaryDark,
      foregroundColor: Colors.white,
      iconTheme: const IconThemeData(color: Colors.white),
      actionsIconTheme: const IconThemeData(color: Colors.white),
      shape: const Border(),
      titleSpacing: 16,
      title: _brandTitle(
        'Logistics hub',
        widget.authState.sortingCenter?['business_name']?.toString(),
      ),
      actions: [
        IconButton(
          tooltip: 'Buyer App',
          onPressed: () => widget.authState.switchModule('buyer'),
          icon: const Icon(Icons.shopping_bag_outlined),
        ),
        IconButton(
          tooltip: 'Sign out',
          onPressed: _logout,
          icon: const Icon(Icons.logout),
        ),
        const SizedBox(width: 4),
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

  Widget _brandTitle(String title, String? subtitle) {
    return Row(
      children: [
        Container(
          width: 36,
          height: 36,
          padding: const EdgeInsets.all(5),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(11),
            boxShadow: const [
              BoxShadow(
                color: Color(0x14000000),
                blurRadius: 4,
                offset: Offset(0, 1),
              ),
            ],
          ),
          child: Image.asset(
            'assets/images/alona-icon.png',
            fit: BoxFit.contain,
            errorBuilder: (_, _, _) => const Icon(
              Icons.view_in_ar_outlined,
              size: 20,
              color: AppColors.primary,
            ),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                title,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: GoogleFonts.figtree(
                  fontWeight: FontWeight.w800,
                  fontSize: 17,
                  color: Colors.white,
                ),
              ),
              if (subtitle != null && subtitle.isNotEmpty)
                Text(
                  subtitle,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: GoogleFonts.figtree(
                    fontSize: 11.5,
                    color: Colors.white70,
                    fontWeight: FontWeight.w500,
                  ),
                ),
            ],
          ),
        ),
      ],
    );
  }

  Future<void> _openBuyer(BuildContext context) async {
    await widget.authState.switchModule('buyer');
    if (!context.mounted) return;
    Navigator.pushAndRemoveUntil(
      context,
      MaterialPageRoute(
        builder: (_) => BuyerMainScreen(authState: widget.authState),
      ),
      (route) => false,
    );
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
        iconTheme: const IconThemeData(color: Colors.white),
        actionsIconTheme: const IconThemeData(color: Colors.white),
        shape: const Border(),
        titleSpacing: 16,
        title: _brandTitle('Alona Logistics', scName),
        actions: [
          TextButton.icon(
            onPressed: () => _openBuyer(context),
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
              backgroundColor: Colors.white.withValues(alpha: 0.14),
              shape: const StadiumBorder(),
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              minimumSize: Size.zero,
              tapTargetSize: MaterialTapTargetSize.shrinkWrap,
            ),
          ),
          const SizedBox(width: 12),
        ],
      ),
      body: KeyedSubtree(
        key: ValueKey(_currentIndex),
        child: pages[_currentIndex],
      ),
      bottomNavigationBar: SafeArea(
        minimum: const EdgeInsets.fromLTRB(12, 0, 12, 12),
        child: DecoratedBox(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(22),
            border: Border.all(color: AppColors.border),
            boxShadow: [
              BoxShadow(
                color: AppColors.primaryDark.withValues(alpha: 0.12),
                blurRadius: 18,
                offset: const Offset(0, 6),
              ),
            ],
          ),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(22),
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
                fontWeight: FontWeight.w800,
              ),
              unselectedLabelStyle: GoogleFonts.figtree(
                fontSize: 11,
                fontWeight: FontWeight.w500,
              ),
              items: [
                _navItem(
                  Icons.dashboard_outlined,
                  Icons.dashboard_rounded,
                  'Dashboard',
                ),
                _navItem(
                  Icons.view_in_ar_outlined,
                  Icons.view_in_ar_rounded,
                  'Check-In',
                ),
                _navItem(
                  Icons.view_week_outlined,
                  Icons.view_week_rounded,
                  'Sorting',
                ),
                _navItem(
                  Icons.map_outlined,
                  Icons.map_rounded,
                  'Monitoring',
                ),
                _navItem(
                  Icons.grid_view_outlined,
                  Icons.grid_view_rounded,
                  'Operations',
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  BottomNavigationBarItem _navItem(
    IconData icon,
    IconData active,
    String label,
  ) {
    return BottomNavigationBarItem(
      icon: Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Icon(icon),
      ),
      activeIcon: Container(
        margin: const EdgeInsets.only(bottom: 2),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 3),
        decoration: BoxDecoration(
          color: AppColors.lightTeal,
          borderRadius: BorderRadius.circular(20),
        ),
        child: Icon(active),
      ),
      label: label,
    );
  }

  Widget _buildHubGrid(BuildContext context, String userName, String scName) {
    final narrow = MediaQuery.sizeOf(context).width < 360;
    void open(Widget page) =>
        Navigator.push(context, MaterialPageRoute(builder: (_) => page));

    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // User & Hub Header
          Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [AppColors.primaryDark, AppColors.primary],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(22),
              boxShadow: [
                BoxShadow(
                  color: AppColors.primary.withValues(alpha: 0.25),
                  blurRadius: 18,
                  offset: const Offset(0, 8),
                ),
              ],
            ),
            child: Row(
              children: [
                Container(
                  width: 54,
                  height: 54,
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.16),
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(
                      color: Colors.white.withValues(alpha: 0.25),
                    ),
                  ),
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
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: GoogleFonts.figtree(
                          fontSize: 17,
                          fontWeight: FontWeight.w800,
                          color: Colors.white,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        scName,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: GoogleFonts.figtree(
                          fontSize: 12.5,
                          color: Colors.white70,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 10,
                          vertical: 3,
                        ),
                        decoration: BoxDecoration(
                          color: AppColors.accent,
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const Icon(
                              Icons.verified_rounded,
                              size: 12,
                              color: AppColors.textPrimary,
                            ),
                            const SizedBox(width: 4),
                            Text(
                              'AUTHORIZED LOGISTICS',
                              style: GoogleFonts.figtree(
                                fontSize: 10,
                                fontWeight: FontWeight.w800,
                                letterSpacing: 0.4,
                                color: AppColors.textPrimary,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),

          _sectionTitle(
            'Hub & parcel flow',
            'Receive, dispatch and return parcels',
          ),
          _opGrid(narrow, [
            _buildOpCard(
              title: 'Hub workspace',
              subtitle: 'Receive, dispatch and receive transfers',
              icon: Icons.view_in_ar_outlined,
              color: Colors.teal,
              onTap: () => open(const HubWorkspaceScreen()),
            ),
            _buildOpCard(
              title: 'Outbound readiness',
              subtitle: 'Check the assigned delivery handoff',
              icon: Icons.local_shipping_outlined,
              color: Colors.blue,
              onTap: () => open(const OutboundParcelsScreen()),
            ),
            _buildOpCard(
              title: 'Pickup Requests',
              subtitle: 'Seller warehouse pickups',
              icon: Icons.calendar_today_outlined,
              color: Colors.deepOrange,
              onTap: () => open(const PickupRequestsScreen()),
            ),
            _buildOpCard(
              title: 'Return to Seller',
              subtitle: 'Actual custody and Seller receipt',
              icon: Icons.assignment_return_outlined,
              color: Colors.orange,
              onTap: () => open(const ReturnsScreen()),
            ),
          ]),

          _sectionTitle('Riders', 'Assignments, fleet and delivery proof'),
          _opGrid(narrow, [
            _buildOpCard(
              title: 'Rider Assignment',
              subtitle: 'Assign parcels to riders',
              icon: Icons.groups_outlined,
              color: AppColors.primary,
              onTap: () => open(const RiderAssignmentScreen()),
            ),
            _buildOpCard(
              title: 'Rider Fleet',
              subtitle: 'Manage active delivery riders',
              icon: Icons.groups_outlined,
              color: Colors.indigo,
              onTap: () => open(const RiderManagementScreen()),
            ),
            _buildOpCard(
              title: 'Proof Verification',
              subtitle: 'Delivery photo audit',
              icon: Icons.verified_outlined,
              color: Colors.teal,
              onTap: () => open(const ProofReviewScreen()),
            ),
          ]),

          _sectionTitle('Finance & insights', 'Cash, payouts and performance'),
          _opGrid(narrow, [
            _buildOpCard(
              title: 'COD cash receipt',
              subtitle: 'Review actual Rider remittances',
              icon: Icons.receipt_long_outlined,
              color: Colors.green,
              onTap: () => open(const CodScreen()),
            ),
            _buildOpCard(
              title: 'Rider payouts',
              subtitle: 'Rates, grouped payments and Rider receipt',
              icon: Icons.payments_outlined,
              color: AppColors.primary,
              onTap: () => open(const RiderPayoutsScreen()),
            ),
            _buildOpCard(
              title: 'Reports & Analytics',
              subtitle: 'Turnaround & status metrics',
              icon: Icons.bar_chart_outlined,
              color: Colors.purple,
              onTap: () => open(const LogisticsReportsScreen()),
            ),
          ]),

          const SizedBox(height: 24),

          // Account actions
          Material(
            color: Colors.white,
            clipBehavior: Clip.antiAlias,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(18),
              side: const BorderSide(color: AppColors.border),
            ),
            child: Column(
              children: [
                ListTile(
                  leading: _tileIcon(
                    Icons.shopping_bag_outlined,
                    AppColors.accent,
                  ),
                  title: const Text('Switch to Buyer'),
                  subtitle: const Text('Browse marketplace products'),
                  trailing: const Icon(
                    Icons.chevron_right_rounded,
                    color: AppColors.textLight,
                  ),
                  onTap: () => _openBuyer(context),
                ),
                const Divider(indent: 16, endIndent: 16),
                ListTile(
                  leading: _tileIcon(Icons.logout_rounded, AppColors.error),
                  title: Text(
                    'Logistics Sign Out',
                    style: GoogleFonts.figtree(
                      fontWeight: FontWeight.w700,
                      color: AppColors.error,
                    ),
                  ),
                  onTap: () async {
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
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _sectionTitle(String title, String subtitle) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(4, 24, 4, 12),
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

  Widget _opGrid(bool narrow, List<Widget> cards) {
    return GridView.count(
      crossAxisCount: 2,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      crossAxisSpacing: 12,
      mainAxisSpacing: 12,
      childAspectRatio: narrow ? 0.9 : 1.12,
      children: [
        for (var i = 0; i < cards.length; i++)
          FadeSlideIn(index: i, child: cards[i]),
      ],
    );
  }

  Widget _tileIcon(IconData icon, Color color) {
    return Container(
      width: 40,
      height: 40,
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Icon(icon, color: color, size: 20),
    );
  }

  Widget _buildOpCard({
    required String title,
    required String subtitle,
    required IconData icon,
    required Color color,
    required VoidCallback onTap,
  }) {
    return Material(
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
                    width: 42,
                    height: 42,
                    decoration: BoxDecoration(
                      color: color.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(13),
                    ),
                    child: Icon(icon, color: color, size: 22),
                  ),
                  const Spacer(),
                  const Icon(
                    Icons.arrow_outward_rounded,
                    size: 16,
                    color: AppColors.textLight,
                  ),
                ],
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: GoogleFonts.figtree(
                      fontWeight: FontWeight.w800,
                      fontSize: 13.5,
                      color: AppColors.textPrimary,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 3),
                  Text(
                    subtitle,
                    style: GoogleFonts.figtree(
                      fontSize: 11.5,
                      height: 1.3,
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
      ),
    );
  }
}
