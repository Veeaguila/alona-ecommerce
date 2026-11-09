import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../services/rider_finance_api.dart';
import '../services/rider_session.dart';
import '../services/rider_session_guard.dart';
import '../theme/rider_theme.dart';
import '../widgets/rider_bottom_nav.dart';

class CodAndEarningsTrackerPage extends StatefulWidget {
  final RiderSession? session;
  final bool active;
  final int initialTabIndex;

  const CodAndEarningsTrackerPage({
    super.key,
    this.session,
    this.active = true,
    this.initialTabIndex = 0,
  });

  @override
  State<CodAndEarningsTrackerPage> createState() =>
      _CodAndEarningsTrackerPageState();
}

class _CodAndEarningsTrackerPageState extends State<CodAndEarningsTrackerPage>
    with
        SingleTickerProviderStateMixin,
        WidgetsBindingObserver,
        RiderSessionGuard<CodAndEarningsTrackerPage> {
  @override
  late final riderSession = widget.session ?? RiderSession();
  late final api = RiderFinanceApi(riderSession);

  late TabController _tabController;
  bool loading = true;
  String? error;

  // COD Data
  Map<String, dynamic>? codData;
  double expectedCodBalance = 0.0;
  List<dynamic> codCollections = [];
  List<dynamic> codRemittances = [];

  // Earnings Data
  Map<String, dynamic>? earningsData;
  String totalEarned = '0.00';
  String paidEarnings = '0.00';
  String unpaidEarnings = '0.00';
  List<dynamic> earningsRows = [];

  // Denomination Counter State (Filipino Peso)
  final Map<int, int> _denominations = {
    1000: 0,
    500: 0,
    200: 0,
    100: 0,
    50: 0,
    20: 0,
    10: 0,
    5: 0,
    1: 0,
  };

  double get countedCashTotal {
    double sum = 0.0;
    _denominations.forEach((denom, count) {
      sum += denom * count;
    });
    return sum;
  }

  @override
  void initState() {
    super.initState();
    _tabController = TabController(
      length: 2,
      vsync: this,
      initialIndex: widget.initialTabIndex,
    );
    WidgetsBinding.instance.addObserver(this);
    if (widget.active) loadAllData();
  }

  @override
  void didUpdateWidget(covariant CodAndEarningsTrackerPage oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.active && !oldWidget.active && !loading) {
      loadAllData();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _tabController.dispose();
    super.dispose();
  }

  Future<void> loadAllData() async {
    setState(() {
      loading = true;
      error = null;
    });

    try {
      final codRes = await sessionAction(() => api.getCodLedger());
      final earnRes = await sessionAction(() => api.getEarnings());

      if (!mounted) return;

      // Parse COD
      double balanceSum = 0.0;
      final balances = codRes['balances'];
      if (balances is List) {
        for (final b in balances) {
          if (b is Map && b['balance'] != null) {
            balanceSum += double.tryParse('${b['balance']}') ?? 0.0;
          }
        }
      }

      final collections = codRes['collections'] is List ? (codRes['collections'] as List) : [];
      final remittances = codRes['remittances'] is List ? (codRes['remittances'] as List) : [];

      // Parse Earnings
      final summary = earnRes['summary'] as Map?;
      final rows = earnRes['data'] is List ? (earnRes['data'] as List) : [];

      setState(() {
        codData = codRes;
        expectedCodBalance = balanceSum;
        codCollections = collections;
        codRemittances = remittances;

        earningsData = earnRes;
        totalEarned = summary?['total']?.toString() ?? '0.00';
        paidEarnings = summary?['paid']?.toString() ?? '0.00';
        unpaidEarnings = summary?['outstanding']?.toString() ?? '0.00';
        earningsRows = rows;

        loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        loading = false;
        error = e is SessionFailure ? e.message : 'Hindi ma-load ang financial data.';
      });
    }
  }

  void _adjustDenomination(int denom, int delta) {
    HapticFeedback.selectionClick();
    setState(() {
      final current = _denominations[denom] ?? 0;
      final updated = current + delta;
      _denominations[denom] = updated < 0 ? 0 : updated;
    });
  }

  void _resetCashCounter() {
    HapticFeedback.mediumImpact();
    setState(() {
      _denominations.updateAll((key, value) => 0);
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: RiderColors.background,
      appBar: AppBar(
        title: const Text('COD Cash & Earnings'),
        bottom: TabBar(
          controller: _tabController,
          indicatorColor: RiderColors.teal,
          indicatorWeight: 3,
          labelColor: RiderColors.teal,
          unselectedLabelColor: RiderColors.textSecondary,
          labelStyle: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
          tabs: const [
            Tab(icon: Icon(Icons.calculate_outlined), text: 'COD Cash & Counter'),
            Tab(icon: Icon(Icons.account_balance_wallet_outlined), text: 'Kinita / Earnings'),
          ],
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            tooltip: 'Refresh details',
            onPressed: loading ? null : loadAllData,
          ),
        ],
      ),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : error != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(Icons.error_outline, size: 48, color: RiderColors.danger),
                        const SizedBox(height: 12),
                        Text(error!, textAlign: TextAlign.center),
                        const SizedBox(height: 16),
                        FilledButton(onPressed: loadAllData, child: const Text('Subukan Muli')),
                      ],
                    ),
                  ),
                )
              : TabBarView(
                  controller: _tabController,
                  children: [
                    _buildCodCashTab(),
                    _buildEarningsTab(),
                  ],
                ),
      bottomNavigationBar: widget.session != null
          ? RiderBottomNav(currentIndex: 2, session: widget.session!)
          : null,
    );
  }

  // -------------------------------------------------------------
  // TAB 1: COD Cash & Interactive Denomination Counter
  // -------------------------------------------------------------
  Widget _buildCodCashTab() {
    final counted = countedCashTotal;
    final diff = counted - expectedCodBalance;
    final isExact = (diff.abs() < 0.01) && (expectedCodBalance > 0 || counted > 0);
    final isShort = diff < -0.01;
    final isOver = diff > 0.01;

    return RefreshIndicator(
      onRefresh: loadAllData,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // Cash on Hand / Remittance Card
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [Color(0xFF0F766E), Color(0xFF14B8A6)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(20),
              boxShadow: const [
                BoxShadow(
                  color: Colors.black12,
                  blurRadius: 10,
                  offset: Offset(0, 4),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      'Kailangang I-remit sa Hub (COD)',
                      style: TextStyle(color: Colors.white70, fontSize: 13, fontWeight: FontWeight.w600),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: Colors.white24,
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Text(
                        expectedCodBalance > 0 ? 'Pending Remittance' : 'Cleared / No Balance',
                        style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Text(
                  'PHP ${expectedCodBalance.toStringAsFixed(2)}',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 32,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  'Total Collections: ${codCollections.length} parcel(s) na may COD',
                  style: const TextStyle(color: Colors.white70, fontSize: 12),
                ),
              ],
            ),
          ),

          const SizedBox(height: 18),

          // Interactive Denomination Counter Header
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: RiderColors.border),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Row(
                      children: [
                        Icon(Icons.point_of_sale_rounded, color: Color(0xFF0F766E), size: 22),
                        SizedBox(width: 8),
                        Text(
                          'Denomination Cash Counter',
                          style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
                        ),
                      ],
                    ),
                    TextButton.icon(
                      onPressed: counted > 0 ? _resetCashCounter : null,
                      icon: const Icon(Icons.restart_alt, size: 16),
                      label: const Text('Reset', style: TextStyle(fontSize: 12)),
                      style: TextButton.styleFrom(
                        foregroundColor: RiderColors.danger,
                        padding: EdgeInsets.zero,
                        minimumSize: Size.zero,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                const Text(
                  'I-count ang iyong barya at perang papel para makasigurong tugma bago mag-remit sa hub cashier.',
                  style: TextStyle(fontSize: 12, color: RiderColors.textSecondary),
                ),
                const SizedBox(height: 14),

                // Live Count Display vs Target
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                  decoration: BoxDecoration(
                    color: isExact
                        ? const Color(0xFFD1FAE5)
                        : (isShort
                            ? const Color(0xFFFEE2E2)
                            : (isOver ? const Color(0xFFFEF3C7) : const Color(0xFFF1F5F9))),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                      color: isExact
                          ? const Color(0xFF10B981)
                          : (isShort
                              ? const Color(0xFFEF4444)
                              : (isOver ? const Color(0xFFF59E0B) : const Color(0xFFCBD5E1))),
                      width: 1.5,
                    ),
                  ),
                  child: Column(
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text(
                            'Kabuuang Nabilang sa Bulsa:',
                            style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                          ),
                          Text(
                            'PHP ${counted.toStringAsFixed(2)}',
                            style: TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.w900,
                              color: isExact
                                  ? const Color(0xFF065F46)
                                  : (isShort ? const Color(0xFF991B1B) : const Color(0xFF92400E)),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Row(
                        children: [
                          Icon(
                            isExact
                                ? Icons.check_circle_rounded
                                : (isShort ? Icons.error_rounded : Icons.info_rounded),
                            size: 16,
                            color: isExact
                                ? const Color(0xFF059669)
                                : (isShort ? const Color(0xFFDC2626) : const Color(0xFFD97706)),
                          ),
                          const SizedBox(width: 6),
                          Expanded(
                            child: Text(
                              isExact
                                  ? 'TUGMA ANG PERA! Eksaktong PHP ${counted.toStringAsFixed(2)} (Handa nang i-remit)'
                                  : (isShort
                                      ? 'KULANG ng PHP ${(-diff).toStringAsFixed(2)} kumpara sa inaasahang remit'
                                      : (isOver
                                          ? 'SOBRA ng PHP ${diff.toStringAsFixed(2)} kumpara sa inaasahang remit'
                                          : 'Magsimulang magbilang gamit ang mga button sa ibaba')),
                              style: TextStyle(
                                fontSize: 12,
                                fontWeight: FontWeight.bold,
                                color: isExact
                                    ? const Color(0xFF065F46)
                                    : (isShort ? const Color(0xFF991B1B) : const Color(0xFF92400E)),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 14),

                // Bill Steppers
                ..._denominations.entries.map((e) => _buildDenominationRow(e.key, e.value)),
              ],
            ),
          ),

          const SizedBox(height: 18),

          // COD Collections List
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: RiderColors.border),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Row(
                  children: [
                    Icon(Icons.history, color: Color(0xFF0F766E), size: 20),
                    SizedBox(width: 8),
                    Text(
                      'Talaan ng mga Nakolektang COD',
                      style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                if (codCollections.isEmpty)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 12),
                    child: Text('Wala pang nakolektang COD para sa kasalukuyang shift.',
                        style: TextStyle(color: Colors.grey)),
                  )
                else
                  ...codCollections.take(15).map((col) {
                    final parcel = col['parcel'] as Map?;
                    final orderId = parcel?['order_id'] ?? '#${col['parcel_id']}';
                    final amount = col['amount'] ?? '0.00';
                    final recordedAt = col['recorded_at'] != null
                        ? DateTime.tryParse('${col['recorded_at']}')?.toLocal().toString().substring(0, 16)
                        : '';
                    return Padding(
                      padding: const EdgeInsets.symmetric(vertical: 6),
                      child: Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.all(8),
                            decoration: BoxDecoration(
                              color: const Color(0xFFFEF3C7),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: const Icon(Icons.payments_outlined, color: Color(0xFFD97706), size: 18),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  '$orderId',
                                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                                ),
                                if (recordedAt != null && recordedAt.isNotEmpty)
                                  Text(
                                    recordedAt,
                                    style: const TextStyle(fontSize: 11, color: RiderColors.textSecondary),
                                  ),
                              ],
                            ),
                          ),
                          Text(
                            '₱$amount',
                            style: const TextStyle(
                              fontWeight: FontWeight.w900,
                              fontSize: 15,
                              color: Color(0xFF065F46),
                            ),
                          ),
                        ],
                      ),
                    );
                  }),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildDenominationRow(int denom, int count) {
    final isCoin = denom <= 10;
    final rowTotal = denom * count;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        decoration: BoxDecoration(
          color: count > 0 ? const Color(0xFFF0FDFA) : const Color(0xFFF8FAFC),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(
            color: count > 0 ? const Color(0xFF99F6E4) : const Color(0xFFE2E8F0),
          ),
        ),
        child: Row(
          children: [
            // Denomination Badge
            Container(
              width: 65,
              padding: const EdgeInsets.symmetric(vertical: 4),
              decoration: BoxDecoration(
                color: isCoin ? const Color(0xFFE2E8F0) : const Color(0xFFCCFBF1),
                borderRadius: BorderRadius.circular(6),
              ),
              child: Text(
                '₱$denom',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontWeight: FontWeight.w900,
                  fontSize: 13,
                  color: isCoin ? const Color(0xFF475569) : const Color(0xFF0F766E),
                ),
              ),
            ),
            const SizedBox(width: 12),
            // Subtotal for this bill
            Expanded(
              child: Text(
                count > 0 ? 'x $count = ₱$rowTotal' : 'x 0',
                style: TextStyle(
                  fontWeight: count > 0 ? FontWeight.bold : FontWeight.normal,
                  fontSize: 13,
                  color: count > 0 ? RiderColors.text : RiderColors.textSecondary,
                ),
              ),
            ),
            // Stepper buttons
            IconButton(
              icon: const Icon(Icons.remove_circle_outline, size: 22),
              color: count > 0 ? RiderColors.danger : Colors.grey.shade400,
              onPressed: count > 0 ? () => _adjustDenomination(denom, -1) : null,
              visualDensity: VisualDensity.compact,
              padding: EdgeInsets.zero,
            ),
            SizedBox(
              width: 32,
              child: Text(
                '$count',
                textAlign: TextAlign.center,
                style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14),
              ),
            ),
            IconButton(
              icon: const Icon(Icons.add_circle, size: 22),
              color: const Color(0xFF0F766E),
              onPressed: () => _adjustDenomination(denom, 1),
              visualDensity: VisualDensity.compact,
              padding: EdgeInsets.zero,
            ),
            // Quick +5 button for high frequency
            InkWell(
              onTap: () => _adjustDenomination(denom, 5),
              borderRadius: BorderRadius.circular(6),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
                margin: const EdgeInsets.only(left: 4),
                decoration: BoxDecoration(
                  color: const Color(0xFFE0F2FE),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: const Text(
                  '+5',
                  style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF0284C7)),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  // -------------------------------------------------------------
  // TAB 2: Rider Earnings & Job Pay Tracker
  // -------------------------------------------------------------
  Widget _buildEarningsTab() {
    return RefreshIndicator(
      onRefresh: loadAllData,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // Total Earnings Overview Card
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: RiderColors.teal,
              borderRadius: BorderRadius.circular(20),
              boxShadow: const [
                BoxShadow(color: Colors.black12, blurRadius: 10, offset: Offset(0, 4)),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Kabuuang Kinita (Total Earnings)',
                    style: TextStyle(color: Colors.white70, fontSize: 13, fontWeight: FontWeight.w600)),
                const SizedBox(height: 6),
                Text(
                  'PHP $totalEarned',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 32,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: Container(
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.15),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text('Nabayaran Na',
                                style: TextStyle(color: Colors.white70, fontSize: 11)),
                            const SizedBox(height: 2),
                            Text('PHP $paidEarnings',
                                style: const TextStyle(
                                    color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15)),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Container(
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.15),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text('Papasok / Unpaid',
                                style: TextStyle(color: Colors.white70, fontSize: 11)),
                            const SizedBox(height: 2),
                            Text('PHP $unpaidEarnings',
                                style: const TextStyle(
                                    color: Colors.white, fontWeight: FontWeight.bold, fontSize: 15)),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),

          const SizedBox(height: 18),

          // Information Note
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: const Color(0xFFEFF6FF),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: const Color(0xFFBFDBFE)),
            ),
            child: const Row(
              children: [
                Icon(Icons.info_outline, color: Color(0xFF2563EB), size: 20),
                SizedBox(width: 10),
                Expanded(
                  child: Text(
                    'Ang bayad sa delivery at pickup ay awtomatikong kinikilala kada nakumpletong parcel. Hiwalay ang COD cash sa iyong kinita.',
                    style: TextStyle(fontSize: 12, color: Color(0xFF1E40AF)),
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 18),

          // Earnings Rows History
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: RiderColors.border),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Talaan ng mga Nakumpletong Deliveries',
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 12),
                if (earningsRows.isEmpty)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 16),
                    child: Center(
                      child: Text(
                        'Wala pang recorded delivery earnings.',
                        style: TextStyle(color: Colors.grey),
                      ),
                    ),
                  )
                else
                  ...earningsRows.map((row) {
                    final amount = row['amount'] ?? '0.00';
                    final status = row['payout_status'] ?? 'pending';
                    final earnedAt = row['earned_at'] != null
                        ? DateTime.tryParse('${row['earned_at']}')?.toLocal().toString().substring(0, 10)
                        : '';
                    final isPaid = status == 'paid';

                    return Padding(
                      padding: const EdgeInsets.symmetric(vertical: 8),
                      child: Row(
                        children: [
                          Container(
                            padding: const EdgeInsets.all(8),
                            decoration: BoxDecoration(
                              color: isPaid ? const Color(0xFFD1FAE5) : const Color(0xFFFEF3C7),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Icon(
                              isPaid ? Icons.check_circle_outline : Icons.schedule,
                              color: isPaid ? const Color(0xFF059669) : const Color(0xFFD97706),
                              size: 18,
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'Delivery Fee #${row['id']}',
                                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                                ),
                                if (earnedAt != null && earnedAt.isNotEmpty)
                                  Text(
                                    earnedAt,
                                    style: const TextStyle(fontSize: 12, color: RiderColors.textSecondary),
                                  ),
                              ],
                            ),
                          ),
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.end,
                            children: [
                              Text(
                                '+PHP $amount',
                                style: const TextStyle(
                                  fontWeight: FontWeight.w900,
                                  fontSize: 15,
                                  color: Color(0xFF0F766E),
                                ),
                              ),
                              Text(
                                isPaid ? 'Paid' : 'Unpaid',
                                style: TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w600,
                                  color: isPaid ? const Color(0xFF059669) : const Color(0xFFD97706),
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    );
                  }),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
