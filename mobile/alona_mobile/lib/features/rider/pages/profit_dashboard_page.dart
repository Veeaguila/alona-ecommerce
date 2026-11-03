import 'package:flutter/material.dart';

import '../widgets/rider_content.dart';
import '../services/rider_earnings_api.dart';
import '../services/rider_session.dart';
import '../services/rider_session_guard.dart';
import '../theme/rider_theme.dart';

class ProfitDashboardPage extends StatefulWidget {
  final RiderSession? session;
  const ProfitDashboardPage({super.key, this.session});

  @override
  State<ProfitDashboardPage> createState() => _ProfitDashboardPageState();
}

class _ProfitDashboardPageState extends State<ProfitDashboardPage>
    with WidgetsBindingObserver, RiderSessionGuard<ProfitDashboardPage> {
  @override
  late final riderSession = widget.session ?? RiderSession();
  late final api = RiderEarningsApi(riderSession);
  RiderEarningsPage? data;
  bool loading = true;
  String? error;
  int revision = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    load();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) load();
  }

  Future<void> load({int page = 1}) async {
    final current = ++revision;
    setState(() {
      loading = true;
      error = null;
      data = null;
    });
    try {
      final result = await sessionAction(() => api.load(page: page));
      if (!mounted || current != revision) return;
      setState(() {
        data = result;
        loading = false;
      });
    } catch (failure) {
      if (!mounted || current != revision) return;
      setState(() {
        loading = false;
        error = failure is SessionFailure
            ? failure.message
            : 'Could not load earnings. Please refresh and retry.';
      });
    }
  }

  String dateLabel(String value) {
    final date = DateTime.parse(value).toLocal();
    return '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: RiderColors.background,
    appBar: AppBar(
      title: const Text('Delivery earnings'),
      actions: [
        IconButton(
          tooltip: 'Refresh earnings',
          onPressed: loading ? null : () => load(),
          icon: const Icon(Icons.refresh),
        ),
      ],
    ),
    body: RefreshIndicator(
      onRefresh: () => load(),
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: riderPagePadding(context),
        children: [
          if (loading)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 32),
              child: Center(child: CircularProgressIndicator()),
            ),
          if (error != null) ...[
            Container(
              padding: const EdgeInsets.all(16),
              margin: const EdgeInsets.only(bottom: 16),
              decoration: BoxDecoration(
                color: const Color(0xFFFEE2E2),
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: const Color(0xFFFCA5A5)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    error!,
                    style: const TextStyle(
                      color: Color(0xFF991B1B),
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 8),
                  TextButton(
                    onPressed: () => load(),
                    style: TextButton.styleFrom(
                      padding: EdgeInsets.zero,
                      foregroundColor: const Color(0xFF991B1B),
                    ),
                    child: const Text(
                      'Retry',
                      style: TextStyle(fontWeight: FontWeight.w700),
                    ),
                  ),
                ],
              ),
            ),
          ],
          if (data case final result?) ...[
            // Top Earnings Summary Card matching Screen 9
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: RiderColors.border),
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
                children: [
                  const Text(
                    'Total earned',
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w500,
                      color: RiderColors.textSecondary,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    '₱${result.total}',
                    style: const TextStyle(
                      fontSize: 32,
                      fontWeight: FontWeight.w800,
                      color: RiderColors.text,
                    ),
                  ),
                  const SizedBox(height: 16),
                  LayoutBuilder(
                    builder: (context, constraints) {
                      final isNarrow = constraints.maxWidth < 340;
                      final cardWidth = isNarrow
                          ? constraints.maxWidth
                          : (constraints.maxWidth - 12) / 2;

                      final pendingCard = Container(
                        width: cardWidth,
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFEF3C7),
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(color: const Color(0xFFFDE68A)),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'Pending payout',
                              style: TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
                                color: Color(0xFF92400E),
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              '₱${result.pending}',
                              style: const TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.w800,
                                color: Color(0xFF92400E),
                              ),
                            ),
                          ],
                        ),
                      );

                      final paidCard = Container(
                        width: cardWidth,
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: const Color(0xFFE6F7F7),
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(color: const Color(0xFFA7F3D0)),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'Paid earnings',
                              style: TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
                                color: RiderColors.mintBadgeText,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              '₱${result.paid}',
                              style: const TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.w800,
                                color: RiderColors.mintBadgeText,
                              ),
                            ),
                          ],
                        ),
                      );

                      if (isNarrow) {
                        return Column(
                          children: [
                            pendingCard,
                            const SizedBox(height: 10),
                            paidCard,
                          ],
                        );
                      }

                      return Row(
                        children: [
                          Expanded(child: pendingCard),
                          const SizedBox(width: 12),
                          Expanded(child: paidCard),
                        ],
                      );
                    },
                  ),
                ],
              ),
            ),
            const Padding(
              padding: EdgeInsets.only(top: 24, bottom: 12),
              child: Text(
                'Earnings history',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w700,
                  color: RiderColors.text,
                ),
              ),
            ),
            if (result.rows.isEmpty)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 20),
                child: Center(
                  child: Text(
                    'No delivery earnings yet.',
                    style: TextStyle(
                      color: RiderColors.textSecondary,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ),
              ),
            if (result.rows.isNotEmpty)
              Container(
                margin: const EdgeInsets.only(bottom: 20),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: RiderColors.border),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.02),
                      blurRadius: 10,
                      offset: const Offset(0, 4),
                    ),
                  ],
                ),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(20),
                  child: ListView.separated(
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    itemCount: result.rows.length,
                    separatorBuilder: (context, index) => const Divider(
                      height: 1,
                      thickness: 1,
                      color: RiderColors.border,
                    ),
                    itemBuilder: (context, index) {
                      final row = result.rows[index];
                      final isPaid = row.status == 'paid';
                      return Padding(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 16,
                          vertical: 14,
                        ),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.center,
                          children: [
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Row(
                                    children: [
                                      Text(
                                        'Shipment ${row.shipmentId}',
                                        style: const TextStyle(
                                          fontWeight: FontWeight.w700,
                                          fontSize: 15,
                                          color: RiderColors.text,
                                        ),
                                      ),
                                      const Text(
                                        ' · Delivered',
                                        style: TextStyle(
                                          fontWeight: FontWeight.w500,
                                          fontSize: 13,
                                          color: RiderColors.textSecondary,
                                        ),
                                      ),
                                    ],
                                  ),
                                  const SizedBox(height: 4),
                                  Text(
                                    '${isPaid ? 'Paid · ' : 'Pending payout · '}${dateLabel(row.earnedAt)}${row.paidAt != null ? ' · Paid ${dateLabel(row.paidAt!) }' : ''}',
                                    style: const TextStyle(
                                      fontSize: 12,
                                      color: RiderColors.textSecondary,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(width: 12),
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.end,
                              children: [
                                Container(
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 10,
                                    vertical: 3,
                                  ),
                                  decoration: BoxDecoration(
                                    color: isPaid
                                        ? RiderColors.mintBadge
                                        : RiderColors.amberBadge,
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                  child: Text(
                                    isPaid ? 'Paid' : 'Pending payout',
                                    style: TextStyle(
                                      fontSize: 11,
                                      fontWeight: FontWeight.w700,
                                      color: isPaid
                                          ? RiderColors.mintBadgeText
                                          : RiderColors.amberBadgeText,
                                    ),
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  '₱${row.amount}',
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w800,
                                    fontSize: 16,
                                    color: RiderColors.text,
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                      );
                    },
                  ),
                ),
              ),
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 8),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  OutlinedButton(
                    onPressed: result.page <= 1
                        ? null
                        : () => load(page: result.page - 1),
                    style: OutlinedButton.styleFrom(
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(16),
                      ),
                      side: const BorderSide(color: RiderColors.border),
                    ),
                    child: const Text('Previous'),
                  ),
                  Text(
                    'Page ${result.page} of ${result.lastPage}',
                    style: const TextStyle(
                      fontWeight: FontWeight.w600,
                      color: RiderColors.textSecondary,
                    ),
                  ),
                  OutlinedButton(
                    onPressed: result.page >= result.lastPage
                        ? null
                        : () => load(page: result.page + 1),
                    style: OutlinedButton.styleFrom(
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(16),
                      ),
                      side: const BorderSide(color: RiderColors.border),
                    ),
                    child: const Text('Next'),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),
          ],
        ],
      ),
    ),
  );
}
