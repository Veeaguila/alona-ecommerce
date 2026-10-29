import 'dart:async';

import 'package:flutter/material.dart';

import '../services/rider_portal_api.dart';
import '../services/rider_session.dart';
import '../services/rider_session_guard.dart';
import '../theme/rider_theme.dart';
import '../widgets/rider_content.dart';
import 'delivery_dashboard_page.dart';
import 'delivery_history_page.dart';
import 'pickup_dashboard_page.dart';
import 'profit_dashboard_page.dart';
import 'rider_portal_page.dart'
    show RiderAccountPage, RiderChatPage, portalError;

class RiderNotificationsPage extends StatefulWidget {
  final RiderSession? session;
  const RiderNotificationsPage({super.key, this.session});
  @override
  State<RiderNotificationsPage> createState() => _RiderNotificationsPageState();
}

class _RiderNotificationsPageState extends State<RiderNotificationsPage>
    with WidgetsBindingObserver, RiderSessionGuard<RiderNotificationsPage> {
  @override
  late final riderSession = widget.session ?? RiderSession();
  late final api = RiderPortalApi(riderSession);
  Timer? poll;
  final rows = <Map<String, dynamic>>[];
  bool loading = false, opening = false;
  int? unread;
  int page = 1, lastPage = 1;
  String? error;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    load();
    startPolling();
  }

  void startPolling() {
    poll?.cancel();
    poll = Timer.periodic(const Duration(seconds: 30), (_) {
      if (mounted && !opening && ModalRoute.of(context)?.isCurrent == true) {
        load(quiet: true);
      }
    });
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      startPolling();
      load(quiet: true);
    } else {
      poll?.cancel();
    }
  }

  @override
  void dispose() {
    poll?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  Future<void> load({bool more = false, bool quiet = false}) async {
    if (loading || opening) return;
    setState(() {
      loading = true;
      if (!quiet) error = null;
    });
    try {
      final target = more ? page + 1 : 1;
      final payload = await sessionAction(
        () => api.request('rider/notifications?page=$target', ready: false),
      );
      if (!mounted) return;
      setState(() {
        if (!more) rows.clear();
        final ids = rows.map((row) => row['id']).toSet();
        for (final row in List<Map<String, dynamic>>.from(
          payload['data'] as List,
        )) {
          if (ids.add(row['id'])) rows.add(row);
        }
        unread = (payload['unread_count'] as num).toInt();
        page = (payload['current_page'] as num).toInt();
        lastPage = (payload['last_page'] as num).toInt();
        error = null;
      });
    } catch (failure) {
      if (mounted) setState(() => error = portalError(failure));
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> open(Map<String, dynamic> row) async {
    if (opening || loading) return;
    setState(() {
      opening = true;
      error = null;
    });
    try {
      await sessionAction(
        () => api.request(
          'rider/notifications/${row['id']}/read',
          method: 'POST',
          ready: false,
        ),
      );
      if (!mounted) return;
      final data = row['data'] as Map? ?? {};
      final screen = data['screen'];
      if (screen != 'account') await readyToken();
      if (!mounted) return;
      final Widget destination = switch (screen) {
        'pickups' => PickupDashboardPage(session: riderSession),
        'deliveries' => DeliveryDashboardPage(session: riderSession),
        'history' => DeliveryHistoryPage(session: riderSession),
        'earnings' => ProfitDashboardPage(session: riderSession),
        'chat' => RiderChatPage(
          session: riderSession,
          conversationId: (data['conversation_id'] as num?)?.toInt(),
        ),
        _ => RiderAccountPage(session: riderSession),
      };
      await Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => destination),
      );
    } catch (failure) {
      if (mounted) setState(() => error = portalError(failure));
    } finally {
      if (mounted) {
        setState(() => opening = false);
        await load();
      }
    }
  }

  Future<void> markAllAsRead() async {
    for (final row in rows) {
      if (row['read_at'] == null) {
        try {
          await api.request(
            'rider/notifications/${row['id']}/read',
            method: 'POST',
            ready: false,
          );
        } catch (_) {}
      }
    }
    if (mounted) await load();
  }

  String timestamp(dynamic value) {
    final date = DateTime.tryParse(value?.toString() ?? '')?.toLocal();
    if (date == null) return 'Time unavailable';
    final timeStr = TimeOfDay.fromDateTime(date).format(context);
    return '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')} · $timeStr';
  }

  IconData _iconForTitle(String title) {
    final lower = title.toLowerCase();
    if (lower.contains('pickup')) return Icons.check;
    if (lower.contains('delivery') || lower.contains('route')) {
      return Icons.local_shipping_outlined;
    }
    if (lower.contains('payout') || lower.contains('earn')) {
      return Icons.attach_money;
    }
    return Icons.notifications_none;
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: RiderColors.background,
    appBar: AppBar(
      title: const Text('Notifications'),
      actions: [
        IconButton(
          tooltip: 'Refresh',
          onPressed: loading || opening ? null : () => load(),
          icon: const Icon(Icons.refresh),
        ),
      ],
    ),
    body: RefreshIndicator(
      onRefresh: () => load(),
      child: ListView(
        padding: riderPagePadding(context, maxWidth: 720),
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          const SizedBox(height: 8),
          // Header Row matching Screen 2
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                unread == null
                    ? 'Unread count unavailable'
                    : '$unread unread notifications',
                style: const TextStyle(
                  fontWeight: FontWeight.w800,
                  fontSize: 16,
                  color: RiderColors.text,
                ),
              ),
              GestureDetector(
                onTap: loading || opening ? null : markAllAsRead,
                child: const Text(
                  'Mark all as read',
                  style: TextStyle(
                    color: RiderColors.teal,
                    fontWeight: FontWeight.w700,
                    fontSize: 14,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          if (loading || opening) const LinearProgressIndicator(),
          if (error != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Text(
                error!,
                style: const TextStyle(color: RiderColors.danger),
              ),
            ),
          if (!loading && rows.isEmpty && error == null)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 32),
              child: Center(
                child: Text(
                  'You are up to date.',
                  style: TextStyle(
                    color: RiderColors.textSecondary,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ),
            ),
          for (final row in rows) ...[
            Builder(
              builder: (context) {
                final isUnread = row['read_at'] == null;
                final title = row['title'].toString();
                final body = row['body'].toString();
                final timeLabel = timestamp(row['created_at']);
                final icon = _iconForTitle(title);

                return Container(
                  margin: const EdgeInsets.only(bottom: 12),
                  decoration: BoxDecoration(
                    color: isUnread ? const Color(0xFFE6F7F7) : Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(
                      color: isUnread
                          ? const Color(0xFFA7F3D0)
                          : RiderColors.border,
                      width: 1,
                    ),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.02),
                        blurRadius: 8,
                        offset: const Offset(0, 2),
                      ),
                    ],
                  ),
                  child: Material(
                    color: Colors.transparent,
                    child: InkWell(
                      borderRadius: BorderRadius.circular(16),
                      onTap: loading || opening ? null : () => open(row),
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            // Circular icon
                            Container(
                              width: 44,
                              height: 44,
                              decoration: BoxDecoration(
                                color: isUnread
                                    ? RiderColors.teal
                                    : const Color(0xFFE0F2FE),
                                shape: BoxShape.circle,
                              ),
                              child: Icon(
                                icon,
                                color: isUnread
                                    ? Colors.white
                                    : RiderColors.teal,
                                size: 22,
                              ),
                            ),
                            const SizedBox(width: 14),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      Expanded(
                                        child: Text(
                                          title,
                                          style: const TextStyle(
                                            fontWeight: FontWeight.w700,
                                            fontSize: 16,
                                            color: RiderColors.text,
                                          ),
                                        ),
                                      ),
                                      if (isUnread)
                                        Container(
                                          width: 9,
                                          height: 9,
                                          margin: const EdgeInsets.only(
                                            left: 8,
                                          ),
                                          decoration: const BoxDecoration(
                                            color: RiderColors.amber,
                                            shape: BoxShape.circle,
                                          ),
                                        ),
                                    ],
                                  ),
                                  const SizedBox(height: 4),
                                  Text(
                                    body,
                                    style: TextStyle(
                                      fontSize: 14,
                                      color: isUnread
                                          ? RiderColors.text
                                          : RiderColors.textSecondary,
                                      height: 1.3,
                                    ),
                                  ),
                                  const SizedBox(height: 6),
                                  Text(
                                    '$timeLabel${isUnread ? ' · Unread' : ''}',
                                    style: const TextStyle(
                                      fontSize: 12,
                                      color: RiderColors.textSecondary,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                );
              },
            ),
          ],
          if (page < lastPage)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 8),
              child: Center(
                child: TextButton(
                  onPressed: loading || opening ? null : () => load(more: true),
                  child: const Text('Load older notifications'),
                ),
              ),
            ),
          const SizedBox(height: 16),
          Row(
            children: [
              Container(
                width: 8,
                height: 8,
                decoration: const BoxDecoration(
                  color: RiderColors.success,
                  shape: BoxShape.circle,
                ),
              ),
              const SizedBox(width: 8),
              const Text(
                'Updates while this screen is open.',
                style: TextStyle(
                  color: RiderColors.textSecondary,
                  fontSize: 13,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ],
          ),
          const SizedBox(height: 24),
        ],
      ),
    ),
  );
}
