import 'package:flutter/material.dart';
import '../theme/rider_theme.dart';

import '../services/rider_session.dart';
import '../widgets/rider_bottom_nav.dart';
import 'delivery_dashboard_page.dart';
import 'pickup_dashboard_page.dart';
import 'cod_and_earnings_tracker_page.dart';
import 'rider_portal_page.dart';

class RiderShellPage extends StatefulWidget {
  final RiderSession session;
  final int initialTab;

  const RiderShellPage({super.key, required this.session, this.initialTab = 1});

  @override
  State<RiderShellPage> createState() => _RiderShellPageState();
}

class _RiderShellPageState extends State<RiderShellPage> {
  late int _currentTab;

  @override
  void initState() {
    super.initState();
    _currentTab = widget.initialTab;
  }

  @override
  Widget build(BuildContext context) {
    final pages = [
      PickupDashboardPage(session: widget.session, active: _currentTab == 0),
      DeliveryDashboardPage(session: widget.session, active: _currentTab == 1),
      CodAndEarningsTrackerPage(
        session: widget.session,
        active: _currentTab == 2,
        showBottomNavigation: false,
      ),
      RiderChatPage(session: widget.session),
      RiderAccountPage(session: widget.session),
    ];

    return RiderTheme.wrap(Scaffold(
      body: IndexedStack(
        index: _currentTab,
        children: [
          for (var index = 0; index < pages.length; index++)
            HeroMode(enabled: index == _currentTab, child: pages[index]),
        ],
      ),
      bottomNavigationBar: RiderBottomNav(
        currentIndex: _currentTab,
        session: widget.session,
        onTabSelected: (index) => setState(() => _currentTab = index),
      ),
    ));
  }
}
