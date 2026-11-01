import '../widgets/rider_presence_control.dart';

import 'dart:convert';

import 'package:flutter/material.dart';

import '../widgets/rider_content.dart';

import '../services/api_error.dart';

import '../services/api_config.dart';
import '../services/parcel_address.dart';
import '../services/rider_session.dart';
import '../services/rider_session_guard.dart';
import 'login_page.dart';
import 'rider_portal_page.dart';

import '../theme/rider_theme.dart';
import '../widgets/rider_design_widgets.dart';
import 'order_detail_pages.dart';

class PickupDashboardPage extends StatefulWidget {
  final RiderSession? session;
  final bool active;
  const PickupDashboardPage({super.key, this.session, this.active = true});

  @override
  State<PickupDashboardPage> createState() => _PickupDashboardPageState();
}

class _PickupDashboardPageState extends State<PickupDashboardPage>
    with WidgetsBindingObserver, RiderSessionGuard<PickupDashboardPage> {
  @override
  late final riderSession = widget.session ?? RiderSession();
  bool loggingOut = false;
  final scroll = ScrollController();
  int? actionParcelId;
  int refreshRevision = 0;
  List<Map<String, dynamic>> available = [];
  List<Map<String, dynamic>> mine = [];
  String pickupView = 'active';
  int historyPage = 1, historyLastPage = 1;
  bool loading = true;
  String? error;
  String? success;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    if (widget.active) refresh();
  }

  @override
  void didUpdateWidget(covariant PickupDashboardPage oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.active && !oldWidget.active) refresh();
  }

  @override
  void dispose() {
    scroll.dispose();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && !loggingOut) refresh();
  }

  Future<void> refresh() async {
    if (loggingOut) return;
    final revision = ++refreshRevision;
    final view = pickupView;
    final page = historyPage;
    setState(() {
      loading = true;
      available = [];
      mine = [];
      error = null;
    });
    try {
      final token = await readyToken();
      final headers = {
        'Accept': 'application/json',
        'Authorization': 'Bearer $token',
      };
      var availableRows = <Map<String, dynamic>>[];
      if (view == 'active') {
        final response = await riderSession.get(
          Uri.parse(pickupsAvailableUrl),
          headers: headers,
        );
        await checkAuthorization(response.statusCode);
        if (response.statusCode != 200) {
          throw SessionFailure(apiErrorMessage(response));
        }
        availableRows = List<Map<String, dynamic>>.from(
          jsonDecode(response.body)['data'] as List,
        );
      }
      final uri = Uri.parse(pickupsMineUrl);
      final mineResponse = await riderSession.get(
        view == 'active'
            ? uri
            : uri.replace(
                queryParameters: {
                  'view': view,
                  if (view == 'history') 'page': '$page',
                },
              ),
        headers: headers,
      );
      await checkAuthorization(mineResponse.statusCode);
      if (mineResponse.statusCode != 200) {
        throw SessionFailure(apiErrorMessage(mineResponse));
      }
      final payload = jsonDecode(mineResponse.body) as Map<String, dynamic>;
      if (!mounted || revision != refreshRevision) return;
      setState(() {
        available = availableRows;
        mine = List<Map<String, dynamic>>.from(payload['data'] as List);
        historyLastPage = (payload['meta']?['last_page'] as int?) ?? 1;
        loading = false;
      });
    } catch (exception) {
      if (!mounted || revision != refreshRevision) return;
      setState(() {
        loading = false;
        error = exception is SessionFailure
            ? exception.message
            : 'Could not load pickup jobs. Refresh and retry.';
      });
    }
  }

  Future<void> logout() async {
    if (loggingOut) return;
    setState(() => loggingOut = true);
    try {
      await riderSession.logout();
      if (!mounted) return;
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => const LoginPage()),
        (route) => false,
      );
    } catch (failure) {
      if (!mounted) return;
      setState(
        () => error = failure is SessionFailure
            ? failure.message
            : 'Could not clear your session. Please retry logout.',
      );
    } finally {
      if (mounted) setState(() => loggingOut = false);
    }
  }

  Future<void> accept(Map<String, dynamic> pickup) =>
      pickupAction(pickup, 'accept');

  Future<void> pickupAction(Map<String, dynamic> pickup, String action) async {
    if (loggingOut || actionParcelId != null) return;
    setState(() {
      actionParcelId = pickup['id'] as int;
      error = null;
      success = null;
    });
    try {
      final token = await readyToken();
      final response = await riderSession.post(
        Uri.parse('$pickupsUrl/${pickup['id']}/$action'),
        headers: {
          'Authorization': 'Bearer $token',
          'Accept': 'application/json',
        },
      );
      await checkAuthorization(response.statusCode);
      if (response.statusCode != 200) {
        throw SessionFailure(apiErrorMessage(response));
      }
      if (action == 'confirm' && mounted) {
        setState(() {
          pickupView = 'transit';
          historyPage = 1;
        });
      }
      await refresh();
      if (mounted) {
        setState(
          () => success = action == 'confirm'
              ? 'Pickup confirmed successfully.'
              : action == 'acknowledge'
              ? 'Pickup assignment acknowledged.'
              : action == 'decline'
              ? 'Nearest pickup offer declined.'
              : 'Pickup accepted successfully.',
        );
      }
    } catch (failure) {
      if (!mounted) return;
      final message = failure is SessionFailure
          ? failure.message
          : 'Could not confirm this pickup action. Refresh before retrying.';
      if (failure is! SessionFailure || !failure.accessDenied) await refresh();
      if (mounted) {
        if (scroll.hasClients) scroll.jumpTo(0);
        setState(() => error = message);
      }
    } finally {
      if (mounted) setState(() => actionParcelId = null);
    }
  }

  Future<void> confirmPickup(Map<String, dynamic> pickup) =>
      pickupAction(pickup, 'confirm');

  bool _canStart(Map<String, dynamic> pickup, bool isMine) =>
      !isMine || ['PLACED', 'PREPARING'].contains(pickup['status']);

  void openPickup(Map<String, dynamic> pickup, {required bool isMine}) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => PickupOrderPage(
          pickup: pickup,
          session: riderSession,
          isMine: isMine,
          statusLabel: pickupStatus(pickup['status']),
          primaryLabel: !isMine
              ? (pickup['nearest_offer_expires_at'] != null
                    ? 'Accept nearest pickup offer'
                    : 'Accept pickup')
              : (['PLACED', 'PREPARING'].contains(pickup['status'])
                    ? (pickup['pickup_assignment_state'] == 'assigned'
                          ? 'Accept assigned pickup'
                          : 'Confirm Pickup')
                    : null),
          onPrimary: !isMine
              ? () => accept(pickup)
              : (['PLACED', 'PREPARING'].contains(pickup['status'])
                    ? () => pickup['pickup_assignment_state'] == 'assigned'
                          ? pickupAction(pickup, 'acknowledge')
                          : confirmPickup(pickup)
                    : null),
        ),
      ),
    );
  }

  Widget pickupCard(Map<String, dynamic> pickup, {required bool isMine}) {
    final status = pickup['status'];
    final done = status == 'AT_SORTING_CENTER' || status == 'DELIVERED';
    final badgeBg = status == 'PICKED_UP'
        ? RiderColors.blueBadge
        : done
        ? RiderColors.mintBadge
        : (isMine ? RiderColors.mintBadge : RiderColors.amberBadge);
    final badgeFg = status == 'PICKED_UP'
        ? RiderColors.blueBadgeText
        : done || isMine
        ? RiderColors.mintBadgeText
        : RiderColors.amberBadgeText;
    final canStart = _canStart(pickup, isMine);
    final address = pickupAddress(pickup);
    final note = status == 'PICKED_UP'
        ? 'Bring this parcel to your Sorting Center. Only Logistics staff confirm physical arrival.'
        : pickupView == 'history'
        ? 'Pickup handoff completed. This is not a buyer-delivery assignment.'
        : !isMine
        ? (pickup['nearest_offer_expires_at'] != null
              ? 'Nearest pickup offer'
              : 'Open pickup job')
        : pickup['pickup_assignment_state'] == 'assigned'
        ? 'Assigned to you'
        : 'Accepted by you';
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.06),
            blurRadius: 14,
            offset: const Offset(0, 5),
          ),
        ],
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(20),
        onTap: actionParcelId != null || loggingOut
            ? null
            : () => openPickup(pickup, isMine: isMine),
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      '#${pickup['order_id']}',
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 18,
                        color: RiderColors.text,
                      ),
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 5,
                    ),
                    decoration: BoxDecoration(
                      color: badgeBg,
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      pickupStatus(status),
                      style: TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 13,
                        color: badgeFg,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              Text(
                orderContactName(pickup, pickup: true),
                style: const TextStyle(
                  fontWeight: FontWeight.w800,
                  fontSize: 16,
                  color: RiderColors.text,
                ),
              ),
              const SizedBox(height: 6),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(
                    Icons.location_on_outlined,
                    size: 18,
                    color: RiderColors.textSecondary,
                  ),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      address.isEmpty ? 'Address not provided' : address,
                      style: const TextStyle(
                        color: RiderColors.textSecondary,
                        fontSize: 14,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              Wrap(
                alignment: WrapAlignment.spaceBetween,
                crossAxisAlignment: WrapCrossAlignment.center,
                spacing: 8,
                runSpacing: 8,
                children: [
                  Text(
                    note,
                    style: const TextStyle(
                      color: RiderColors.textSecondary,
                      fontSize: 14,
                    ),
                  ),
                  Container(
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        colors: [Color(0xFF0F766E), Color(0xFF14A39A)],
                      ),
                      borderRadius: BorderRadius.circular(16),
                      boxShadow: [
                        BoxShadow(
                          color: RiderColors.teal.withValues(alpha: 0.3),
                          blurRadius: 10,
                          offset: const Offset(0, 4),
                        ),
                      ],
                    ),
                    child: FilledButton(
                      style: FilledButton.styleFrom(
                        backgroundColor: Colors.transparent,
                        shadowColor: Colors.transparent,
                        padding: const EdgeInsets.symmetric(
                          horizontal: 22,
                          vertical: 14,
                        ),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(16),
                        ),
                      ),
                      onPressed: actionParcelId != null
                          ? null
                          : () {
                              if (!isMine) {
                                accept(pickup);
                              } else if ([
                                'PLACED',
                                'PREPARING',
                              ].contains(pickup['status'])) {
                                pickup['pickup_assignment_state'] == 'assigned'
                                    ? pickupAction(pickup, 'acknowledge')
                                    : confirmPickup(pickup);
                              } else {
                                openPickup(pickup, isMine: isMine);
                              }
                            },
                      child: Text(
                        !isMine
                            ? (pickup['nearest_offer_expires_at'] != null
                                  ? 'Accept nearest pickup offer'
                                  : 'Accept pickup')
                            : canStart
                            ? (pickup['pickup_assignment_state'] == 'assigned'
                                  ? 'Accept assigned pickup'
                                  : 'Confirm Pickup')
                            : 'View',
                        style: const TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 15,
                        ),
                      ),
                    ),
                  ),
                  if (!isMine && pickup['nearest_offer_expires_at'] != null)
                    OutlinedButton(
                      onPressed: actionParcelId != null
                          ? null
                          : () => pickupAction(pickup, 'decline'),
                      child: const Text('Decline nearest offer'),
                    ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget jobSection(
    String title,
    List<Map<String, dynamic>> jobs, {
    required bool isMine,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                title,
                style: const TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
            if (!isMine || pickupView == 'active')
              const Text(
                'Pickup jobs',
                style: TextStyle(
                  color: RiderColors.textSecondary,
                  fontWeight: FontWeight.w600,
                ),
              ),
          ],
        ),
        const SizedBox(height: 12),
        if (jobs.isEmpty)
          Text(
            isMine
                ? pickupView == 'transit'
                      ? 'No parcels in transit to the center.'
                      : pickupView == 'history'
                      ? 'No completed pickup handoffs yet.'
                      : 'No accepted pickups yet.'
                : 'No pickup jobs are available right now.',
            style: TextStyle(color: Colors.grey.shade700),
          )
        else
          ...jobs.map((pickup) => pickupCard(pickup, isMine: isMine)),
      ],
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: RiderColors.background,
      body: Column(
        children: [
          _header(context),
          if (error != null) _statusMessage(error!, false),
          if (success != null) _statusMessage(success!, true),
          Expanded(
            child: RefreshIndicator(
              onRefresh: refresh,
              child: ListView(
                controller: scroll,
                padding: riderPagePadding(context),
                children: [
                  RiderPresenceControl(session: riderSession),
                  RiderNotificationBanner(
                    title: 'Seller & hub notifications',
                    subtitle: 'Tap to see pickup updates from sellers',
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) =>
                            RiderNotificationsPage(session: riderSession),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    child: Row(
                      children: [
                        for (final view in const {
                          'active': 'Pickup jobs',
                          'transit': 'In Transit to Center',
                          'history': 'Pickup History',
                        }.entries)
                          Padding(
                            padding: const EdgeInsets.only(right: 8),
                            child: ChoiceChip(
                              label: Text(view.value),
                              selected: pickupView == view.key,
                              onSelected: actionParcelId != null
                                  ? null
                                  : (_) {
                                      setState(() {
                                        pickupView = view.key;
                                        historyPage = 1;
                                      });
                                      refresh();
                                    },
                            ),
                          ),
                      ],
                    ),
                  ),
                  if (loading) ...[
                    const SizedBox(height: 40),
                    const Center(child: CircularProgressIndicator()),
                  ] else ...[
                    const SizedBox(height: 28),
                    if (pickupView == 'active') ...[
                      jobSection('Items for pickup', available, isMine: false),
                      const SizedBox(height: 28),
                    ],
                    jobSection(
                      pickupView == 'transit'
                          ? 'Collected parcels'
                          : pickupView == 'history'
                          ? 'Completed pickup handoffs'
                          : 'My accepted pickups',
                      mine,
                      isMine: true,
                    ),
                    if (pickupView == 'history')
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          TextButton(
                            onPressed: historyPage <= 1
                                ? null
                                : () {
                                    setState(() => historyPage--);
                                    refresh();
                                  },
                            child: const Text('Previous'),
                          ),
                          Text('Page $historyPage of $historyLastPage'),
                          TextButton(
                            onPressed: historyPage >= historyLastPage
                                ? null
                                : () {
                                    setState(() => historyPage++);
                                    refresh();
                                  },
                            child: const Text('Next'),
                          ),
                        ],
                      ),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _header(BuildContext context) {
    return Container(
      width: double.infinity,
      clipBehavior: Clip.antiAlias,
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFF0B7A83), Color(0xFF0F766E), Color(0xFF0D9488)],
        ),
        borderRadius: BorderRadius.only(
          bottomLeft: Radius.circular(32),
          bottomRight: Radius.circular(32),
        ),
      ),
      padding: EdgeInsets.fromLTRB(
        20,
        MediaQuery.of(context).padding.top + 14,
        20,
        18,
      ),
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Positioned(
            top: -100,
            right: -90,
            child: Container(
              width: 200,
              height: 200,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: RiderColors.amber.withValues(alpha: 0.28),
              ),
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          _greeting(),
                          style: TextStyle(
                            color: Colors.white.withValues(alpha: 0.9),
                            fontSize: 15,
                          ),
                        ),
                        const SizedBox(height: 2),
                        const Text(
                          'Pickups',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 28,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    onPressed: loading ? null : refresh,
                    icon: const Icon(Icons.refresh, color: Colors.white),
                    tooltip: 'Refresh',
                  ),
                  IconButton(
                    onPressed: loggingOut ? null : logout,
                    icon: const Icon(Icons.logout, color: Colors.white),
                    tooltip: 'Sign out',
                  ),
                ],
              ),
              const SizedBox(height: 14),
              Row(
                children: [
                  _summaryCount(
                    pickupView == 'active'
                        ? 'Available pickups'
                        : pickupView == 'transit'
                        ? 'In transit'
                        : 'Completed handoffs',
                    pickupView == 'active' ? available.length : mine.length,
                  ),
                  if (pickupView == 'active') ...[
                    const SizedBox(width: 12),
                    _summaryCount('Accepted by you', mine.length),
                  ],
                ],
              ),
            ],
          ),
        ],
      ),
    );
  }

  String pickupStatus(Object? status) => switch (status) {
    'PICKED_UP' => 'In Transit to Center',
    'AT_SORTING_CENTER' => 'Arrived at Sorting Center',
    'OUT_FOR_DELIVERY' => 'Out for Delivery',
    'DELIVERED' => 'Delivered to Buyer',
    'FAILED_RETURNED' => 'Failed / Returned',
    _ => 'Awaiting Seller pickup',
  };

  String _greeting() {
    final hour = DateTime.now().hour;
    if (hour < 12) return 'Good morning';
    if (hour < 17) return 'Good afternoon';
    return 'Good evening';
  }

  Widget _summaryCount(String label, int count) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: RiderColors.tealDark,
          borderRadius: BorderRadius.circular(18),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              count.toString(),
              style: const TextStyle(
                color: Colors.white,
                fontSize: 22,
                fontWeight: FontWeight.w800,
              ),
            ),
            Text(
              label,
              style: const TextStyle(color: Colors.white, fontSize: 12),
            ),
          ],
        ),
      ),
    );
  }

  Widget _statusMessage(String text, bool isSuccess) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: isSuccess ? const Color(0xffdff4e9) : const Color(0xffffe6e2),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(
        text,
        style: TextStyle(
          color: isSuccess ? const Color(0xff176b43) : const Color(0xff9b3125),
        ),
      ),
    );
  }
}
