import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../services/rider_finance_api.dart';
import '../services/rider_quick_actions.dart';
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

  // Standard Rider Maximum Cash Holding Limit before Remittance is required
  static const double kRiderCodRemittanceLimit = 10000.0;

  // COD Data
  Map<String, dynamic>? codData;
  double expectedCodBalance = 0.0;
  String hubName = '';
  List<dynamic> codCollections = [];
  List<dynamic> codRemittances = [];
  String _historyFilter = 'all'; // 'all', 'collections', 'remittances'

  // Earnings Data
  Map<String, dynamic>? earningsData;
  String totalEarned = '0.00';
  String paidEarnings = '0.00';
  String unpaidEarnings = '0.00';
  List<dynamic> earningsRows = [];
  String _earningsFilter = 'all'; // 'all', 'paid', 'unpaid'

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

  double get billsTotal {
    double sum = 0.0;
    for (final denom in [1000, 500, 200, 100, 50, 20]) {
      sum += denom * (_denominations[denom] ?? 0);
    }
    return sum;
  }

  double get coinsTotal {
    double sum = 0.0;
    for (final denom in [10, 5, 1]) {
      sum += denom * (_denominations[denom] ?? 0);
    }
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
      String foundHub = '';
      final balances = codRes['balances'];
      if (balances is List) {
        for (final b in balances) {
          if (b is Map && b['balance'] != null) {
            balanceSum += double.tryParse('${b['balance']}') ?? 0.0;
            if (foundHub.isEmpty && b['name'] != null && b['name'].toString().trim().isNotEmpty) {
              foundHub = b['name'].toString().trim();
            }
          }
        }
      }

      final collections =
          codRes['collections'] is List ? (codRes['collections'] as List) : [];
      final remittances =
          codRes['remittances'] is List ? (codRes['remittances'] as List) : [];

      // Parse Earnings
      final summary = earnRes['summary'] as Map?;
      final rows = earnRes['data'] is List ? (earnRes['data'] as List) : [];

      setState(() {
        codData = codRes;
        expectedCodBalance = balanceSum;
        hubName = foundHub.isNotEmpty ? foundHub : 'Sorting Center Hub';
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

  void _autoMatchExact() {
    HapticFeedback.mediumImpact();
    int remainingCents = (expectedCodBalance * 100).round();
    final newDenoms = <int, int>{};
    for (final denom in [1000, 500, 200, 100, 50, 20, 10, 5, 1]) {
      final denomCents = denom * 100;
      final count = remainingCents ~/ denomCents;
      newDenoms[denom] = count;
      remainingCents %= denomCents;
    }
    setState(() {
      _denominations.addAll(newDenoms);
    });
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('⚡ Na-match na ang eksaktong bilang ng bills at barya!'),
        duration: Duration(seconds: 2),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: RiderColors.background,
      appBar: AppBar(
        title: const Text('COD Cash & Earnings'),
        bottom: TabBar(
          controller: _tabController,
          indicatorColor: Colors.white,
          indicatorWeight: 3.5,
          labelColor: Colors.white,
          unselectedLabelColor: Colors.white70,
          labelStyle: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13),
          tabs: const [
            Tab(icon: Icon(Icons.point_of_sale_rounded), text: 'COD Cash & Remittance'),
            Tab(icon: Icon(Icons.account_balance_wallet_rounded), text: 'Kinita / Earnings'),
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
  // TAB 1: COD Cash, Remittance Limit, & Interactive Denomination Counter
  // -------------------------------------------------------------
  Widget _buildCodCashTab() {
    final counted = countedCashTotal;
    final diff = counted - expectedCodBalance;
    final isExact = (diff.abs() < 0.01) && (expectedCodBalance > 0 || counted > 0);
    final isShort = diff < -0.01;
    final isOver = diff > 0.01;

    // Remittance limit ratio calculation
    final limitRatio = (expectedCodBalance / kRiderCodRemittanceLimit).clamp(0.0, 1.0);
    final isNearLimit = expectedCodBalance >= (kRiderCodRemittanceLimit * 0.8);
    final isOverLimit = expectedCodBalance >= kRiderCodRemittanceLimit;

    final filteredHistory = _getFilteredHistory();

    return RefreshIndicator(
      onRefresh: loadAllData,
      child: ListView(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        children: [
          // -----------------------------------------------------------
          // HERO SCORECARD: Cash on Hand, Remittance & Hub Status
          // -----------------------------------------------------------
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: isNearLimit
                    ? const [Color(0xFF991B1B), Color(0xFFDC2626)]
                    : const [Color(0xFF0F766E), Color(0xFF134E4A)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(20),
              boxShadow: [
                BoxShadow(
                  color: isNearLimit
                      ? Colors.red.withValues(alpha: 0.3)
                      : const Color(0xFF0F766E).withValues(alpha: 0.3),
                  blurRadius: 14,
                  offset: const Offset(0, 5),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Row(
                      children: [
                        Icon(Icons.lock_clock_rounded, color: Colors.white70, size: 16),
                        SizedBox(width: 6),
                        Text(
                          'Kailangang I-remit sa Hub (COD)',
                          style: TextStyle(
                            color: Colors.white70,
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: Colors.white24,
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Text(
                        expectedCodBalance > 0
                            ? (isNearLimit ? '⚠️ Limit Alert' : 'Pending Remittance')
                            : 'Cleared / Zero Balance',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 11,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                Text(
                  'PHP ${expectedCodBalance.toStringAsFixed(2)}',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 34,
                    fontWeight: FontWeight.w900,
                    letterSpacing: -0.5,
                  ),
                ),
                const SizedBox(height: 6),
                Row(
                  children: [
                    const Icon(Icons.warehouse_rounded, color: Colors.white70, size: 14),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        'Designated Hub: $hubName',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),

                // Remittance Progress Bar vs Max Limit
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.black.withValues(alpha: 0.25),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text(
                            'Remittance Safe Limit:',
                            style: TextStyle(color: Colors.white70, fontSize: 11),
                          ),
                          Text(
                            '₱${expectedCodBalance.toStringAsFixed(0)} / ₱${kRiderCodRemittanceLimit.toStringAsFixed(0)} (${(limitRatio * 100).toStringAsFixed(0)}%)',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 12,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      ClipRRect(
                        borderRadius: BorderRadius.circular(6),
                        child: LinearProgressIndicator(
                          value: limitRatio,
                          minHeight: 8,
                          backgroundColor: Colors.white24,
                          valueColor: AlwaysStoppedAnimation<Color>(
                            isOverLimit
                                ? const Color(0xFFEF4444)
                                : (isNearLimit
                                    ? const Color(0xFFFBBF24)
                                    : const Color(0xFF34D399)),
                          ),
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        isOverLimit
                            ? '🚨 LUMAMPAS SA LIMIT! Mag-remit agad sa Hub bago tumanggap ng susunod na delivery batch.'
                            : (isNearLimit
                                ? '⚠️ Malapit na sa ₱10,000 threshold. Magplano na mag-remit sa Sorting Center.'
                                : (expectedCodBalance > 0
                                    ? '✅ Ligtas ang hawak na cash. Tiyaking i-remit bago mag-out sa shift.'
                                    : '🎉 Walang pending remittance. Ligtas at handa sa biyahe!')),
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w600,
                          color: isNearLimit ? Colors.amberAccent : Colors.white70,
                        ),
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 14),

                // 1-Tap Shortcut: Navigate to Hub Cashier
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton.icon(
                    onPressed: () {
                      RiderQuickActions.openMapNavigation(
                        context,
                        hubName.isNotEmpty ? hubName : 'Alona Logistics Hub',
                      );
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.white,
                      foregroundColor: isNearLimit
                          ? const Color(0xFF991B1B)
                          : const Color(0xFF0F766E),
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                      elevation: 0,
                    ),
                    icon: const Icon(Icons.navigation_rounded, size: 18),
                    label: const Text(
                      'I-NAVIGATE SA HUB CASHIER (Waze / Maps)',
                      style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13),
                    ),
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 16),

          // -----------------------------------------------------------
          // INTERACTIVE DENOMINATION CASH COUNTER (Ergonomic HUD)
          // -----------------------------------------------------------
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: RiderColors.border),
              boxShadow: const [
                BoxShadow(color: Colors.black12, blurRadius: 4, offset: Offset(0, 2)),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Row(
                      children: [
                        Icon(Icons.calculate_rounded, color: Color(0xFF0F766E), size: 22),
                        SizedBox(width: 8),
                        Text(
                          'Denomination Cash Counter',
                          style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
                        ),
                      ],
                    ),
                    Row(
                      children: [
                        if (expectedCodBalance > 0)
                          TextButton.icon(
                            onPressed: _autoMatchExact,
                            icon: const Icon(Icons.bolt_rounded, size: 16),
                            label: const Text('Auto-Match', style: TextStyle(fontSize: 11)),
                            style: TextButton.styleFrom(
                              foregroundColor: const Color(0xFF0F766E),
                              padding: const EdgeInsets.symmetric(horizontal: 8),
                              minimumSize: Size.zero,
                            ),
                          ),
                        TextButton.icon(
                          onPressed: counted > 0 ? _resetCashCounter : null,
                          icon: const Icon(Icons.restart_alt, size: 16),
                          label: const Text('Reset', style: TextStyle(fontSize: 11)),
                          style: TextButton.styleFrom(
                            foregroundColor: RiderColors.danger,
                            padding: const EdgeInsets.symmetric(horizontal: 8),
                            minimumSize: Size.zero,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
                const SizedBox(height: 4),
                const Text(
                  'I-bilang ang iyong perang papel at barya para makasigurong eksakto bago ibigay sa hub cashier.',
                  style: TextStyle(fontSize: 12, color: RiderColors.textSecondary),
                ),
                const SizedBox(height: 14),

                // Live Count Display vs Target Disparity HUD
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                  decoration: BoxDecoration(
                    color: isExact
                        ? const Color(0xFFD1FAE5)
                        : (isShort
                            ? const Color(0xFFFEE2E2)
                            : (isOver ? const Color(0xFFFEF3C7) : const Color(0xFFF1F5F9))),
                    borderRadius: BorderRadius.circular(14),
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
                            style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700),
                          ),
                          Text(
                            'PHP ${counted.toStringAsFixed(2)}',
                            style: TextStyle(
                              fontSize: 20,
                              fontWeight: FontWeight.w900,
                              color: isExact
                                  ? const Color(0xFF065F46)
                                  : (isShort
                                      ? const Color(0xFF991B1B)
                                      : (isOver
                                          ? const Color(0xFF92400E)
                                          : const Color(0xFF334155))),
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
                                : (isShort
                                    ? Icons.error_rounded
                                    : (isOver ? Icons.warning_rounded : Icons.info_rounded)),
                            size: 18,
                            color: isExact
                                ? const Color(0xFF059669)
                                : (isShort ? const Color(0xFFDC2626) : const Color(0xFFD97706)),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              isExact
                                  ? 'TUGMA ANG PERA! Eksaktong PHP ${counted.toStringAsFixed(2)} (Handa nang i-remit)'
                                  : (isShort
                                      ? 'KULANG ng PHP ${(-diff).toStringAsFixed(2)} kumpara sa kailangang remit'
                                      : (isOver
                                          ? 'SOBRA ng PHP ${diff.toStringAsFixed(2)} kumpara sa kailangang remit'
                                          : 'Pindutin ang + o +5 sa bawat denominasyon para magbilang')),
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

                const SizedBox(height: 12),

                // Cash Summary Chips: Bills vs Coins
                Row(
                  children: [
                    Expanded(
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF0FDFA),
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(color: const Color(0xFF99F6E4)),
                        ),
                        child: Row(
                          children: [
                            const Text('💵', style: TextStyle(fontSize: 16)),
                            const SizedBox(width: 6),
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text('Papel (Bills)',
                                    style: TextStyle(
                                        fontSize: 10,
                                        fontWeight: FontWeight.w600,
                                        color: Color(0xFF0F766E))),
                                Text('₱${billsTotal.toStringAsFixed(2)}',
                                    style: const TextStyle(
                                        fontSize: 13,
                                        fontWeight: FontWeight.w900,
                                        color: Color(0xFF0F766E))),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                        decoration: BoxDecoration(
                          color: const Color(0xFFF8FAFC),
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(color: const Color(0xFFCBD5E1)),
                        ),
                        child: Row(
                          children: [
                            const Text('🪙', style: TextStyle(fontSize: 16)),
                            const SizedBox(width: 6),
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text('Barya (Coins)',
                                    style: TextStyle(
                                        fontSize: 10,
                                        fontWeight: FontWeight.w600,
                                        color: Color(0xFF475569))),
                                Text('₱${coinsTotal.toStringAsFixed(2)}',
                                    style: const TextStyle(
                                        fontSize: 13,
                                        fontWeight: FontWeight.w900,
                                        color: Color(0xFF334155))),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),

                const SizedBox(height: 16),

                // Section 1: Perang Papel (Banknotes)
                const Row(
                  children: [
                    Text(
                      '💵 MGA PERANG PAPEL (BANKNOTES)',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF0F766E),
                        letterSpacing: 0.5,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 6),
                ...[1000, 500, 200, 100, 50, 20].map(
                  (denom) => _buildDenominationRow(denom, _denominations[denom] ?? 0),
                ),

                const SizedBox(height: 14),

                // Section 2: Barya (Coins)
                const Row(
                  children: [
                    Text(
                      '🪙 MGA BARYA (COINS)',
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF475569),
                        letterSpacing: 0.5,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 6),
                ...[10, 5, 1].map(
                  (denom) => _buildDenominationRow(denom, _denominations[denom] ?? 0),
                ),
              ],
            ),
          ),

          const SizedBox(height: 18),

          // -----------------------------------------------------------
          // COD COLLECTIONS & REMITTANCES HISTORY SECTION
          // -----------------------------------------------------------
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: RiderColors.border),
              boxShadow: const [
                BoxShadow(color: Colors.black12, blurRadius: 4, offset: Offset(0, 2)),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Row(
                      children: [
                        Icon(Icons.history_rounded, color: Color(0xFF0F766E), size: 20),
                        SizedBox(width: 8),
                        Text(
                          'Talaan ng Transaksyon',
                          style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
                        ),
                      ],
                    ),
                    Text(
                      '${codCollections.length} nakolekta',
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.bold,
                        color: RiderColors.textSecondary,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),

                // Segmented Filter Bar
                Row(
                  children: [
                    _buildFilterChip('all', 'Lahat (${codCollections.length + codRemittances.length})'),
                    const SizedBox(width: 6),
                    _buildFilterChip('collections', 'Koleksyon (${codCollections.length})'),
                    const SizedBox(width: 6),
                    _buildFilterChip('remittances', 'Remit (${codRemittances.length})'),
                  ],
                ),

                const SizedBox(height: 14),

                if (filteredHistory.isEmpty)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 24),
                    child: Center(
                      child: Column(
                        children: [
                          Icon(Icons.receipt_long_outlined, size: 36, color: Colors.grey),
                          SizedBox(height: 8),
                          Text(
                            'Walang record sa napiling filter.',
                            style: TextStyle(color: Colors.grey, fontSize: 13),
                          ),
                        ],
                      ),
                    ),
                  )
                else
                  ...filteredHistory.map((item) => _buildHistoryItem(item)),
              ],
            ),
          ),
          const SizedBox(height: 24),
        ],
      ),
    );
  }

  Widget _buildFilterChip(String key, String label) {
    final isSelected = _historyFilter == key;
    return InkWell(
      onTap: () {
        HapticFeedback.selectionClick();
        setState(() => _historyFilter = key);
      },
      borderRadius: BorderRadius.circular(20),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        decoration: BoxDecoration(
          color: isSelected ? const Color(0xFF0F766E) : const Color(0xFFF1F5F9),
          borderRadius: BorderRadius.circular(20),
          border: Border.all(
            color: isSelected ? const Color(0xFF0F766E) : const Color(0xFFE2E8F0),
          ),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 11,
            fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
            color: isSelected ? Colors.white : RiderColors.textSecondary,
          ),
        ),
      ),
    );
  }

  List<Map<String, dynamic>> _getFilteredHistory() {
    final List<Map<String, dynamic>> list = [];

    if (_historyFilter == 'all' || _historyFilter == 'collections') {
      for (final c in codCollections) {
        if (c is Map) {
          list.add({'type': 'collection', ...Map<String, dynamic>.from(c)});
        }
      }
    }

    if (_historyFilter == 'all' || _historyFilter == 'remittances') {
      for (final r in codRemittances) {
        if (r is Map) {
          list.add({'type': 'remittance', ...Map<String, dynamic>.from(r)});
        }
      }
    }

    // Sort by id descending
    list.sort((a, b) {
      final aId = int.tryParse('${a['id']}') ?? 0;
      final bId = int.tryParse('${b['id']}') ?? 0;
      return bId.compareTo(aId);
    });

    return list.take(20).toList();
  }

  Widget _buildHistoryItem(Map<String, dynamic> item) {
    final isCollection = item['type'] == 'collection';
    final amount = item['amount']?.toString() ?? '0.00';
    final dateStr = (item['recorded_at'] ?? item['created_at'])?.toString();
    final formattedDate = dateStr != null && dateStr.isNotEmpty
        ? (DateTime.tryParse(dateStr)?.toLocal().toString().substring(0, 16) ?? dateStr)
        : '';

    final parcel = item['parcel'] as Map?;
    final title = isCollection
        ? (parcel?['order_id'] != null
            ? 'Order #${parcel!['order_id']}'
            : 'COD Parcel #${item['parcel_id'] ?? item['id']}')
        : 'Remittance #${item['id']} to Hub';

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: isCollection ? const Color(0xFFF0FDFA) : const Color(0xFFEFF6FF),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: isCollection ? const Color(0xFFCCFBF1) : const Color(0xFFDBEAFE),
          ),
        ),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: isCollection ? const Color(0xFFD1FAE5) : const Color(0xFFBFDBFE),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(
                isCollection ? Icons.payments_rounded : Icons.account_balance_rounded,
                color: isCollection ? const Color(0xFF059669) : const Color(0xFF1D4ED8),
                size: 20,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                  ),
                  if (formattedDate.isNotEmpty)
                    Text(
                      formattedDate,
                      style: const TextStyle(fontSize: 11, color: RiderColors.textSecondary),
                    ),
                ],
              ),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(
                  '${isCollection ? "+" : "-"}₱$amount',
                  style: TextStyle(
                    fontWeight: FontWeight.w900,
                    fontSize: 15,
                    color: isCollection ? const Color(0xFF065F46) : const Color(0xFF1E40AF),
                  ),
                ),
                Text(
                  isCollection ? 'Nakolekta' : 'I-remit sa Hub',
                  style: TextStyle(
                    fontSize: 10,
                    fontWeight: FontWeight.bold,
                    color: isCollection ? const Color(0xFF059669) : const Color(0xFF2563EB),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildDenominationRow(int denom, int count) {
    final isCoin = denom <= 10;
    final rowTotal = denom * count;

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
        decoration: BoxDecoration(
          color: count > 0 ? const Color(0xFFF0FDFA) : const Color(0xFFF8FAFC),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: count > 0 ? const Color(0xFF99F6E4) : const Color(0xFFE2E8F0),
            width: count > 0 ? 1.5 : 1.0,
          ),
        ),
        child: Row(
          children: [
            // Denomination Badge
            Container(
              width: 68,
              padding: const EdgeInsets.symmetric(vertical: 6),
              decoration: BoxDecoration(
                color: isCoin ? const Color(0xFFE2E8F0) : const Color(0xFFCCFBF1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Text(
                '₱$denom',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontWeight: FontWeight.w900,
                  fontSize: 14,
                  color: isCoin ? const Color(0xFF475569) : const Color(0xFF0F766E),
                ),
              ),
            ),
            const SizedBox(width: 10),
            // Subtotal for this bill
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    count > 0 ? 'x $count piraso' : 'x 0',
                    style: TextStyle(
                      fontWeight: count > 0 ? FontWeight.bold : FontWeight.normal,
                      fontSize: 12,
                      color: count > 0 ? RiderColors.text : RiderColors.textSecondary,
                    ),
                  ),
                  Text(
                    '₱${rowTotal.toStringAsFixed(2)}',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w900,
                      color: count > 0 ? const Color(0xFF0F766E) : Colors.grey.shade400,
                    ),
                  ),
                ],
              ),
            ),
            // Stepper controls: -1, count, +1
            IconButton(
              icon: const Icon(Icons.remove_circle_outline, size: 22),
              color: count > 0 ? RiderColors.danger : Colors.grey.shade300,
              onPressed: count > 0 ? () => _adjustDenomination(denom, -1) : null,
              visualDensity: VisualDensity.compact,
              padding: EdgeInsets.zero,
            ),
            SizedBox(
              width: 30,
              child: Text(
                '$count',
                textAlign: TextAlign.center,
                style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 15),
              ),
            ),
            IconButton(
              icon: const Icon(Icons.add_circle, size: 22),
              color: const Color(0xFF0F766E),
              onPressed: () => _adjustDenomination(denom, 1),
              visualDensity: VisualDensity.compact,
              padding: EdgeInsets.zero,
            ),
            // Quick +5 button
            InkWell(
              onTap: () => _adjustDenomination(denom, 5),
              borderRadius: BorderRadius.circular(6),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 5),
                margin: const EdgeInsets.only(left: 2),
                decoration: BoxDecoration(
                  color: const Color(0xFFE0F2FE),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: const Text(
                  '+5',
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.bold,
                    color: Color(0xFF0284C7),
                  ),
                ),
              ),
            ),
            // Quick +10 button for rapid count
            InkWell(
              onTap: () => _adjustDenomination(denom, 10),
              borderRadius: BorderRadius.circular(6),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 5),
                margin: const EdgeInsets.only(left: 4),
                decoration: BoxDecoration(
                  color: const Color(0xFFEDE9FE),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: const Text(
                  '+10',
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.bold,
                    color: Color(0xFF6D28D9),
                  ),
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
    final filteredRows = _getFilteredEarnings();

    return RefreshIndicator(
      onRefresh: loadAllData,
      child: ListView(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        children: [
          // Total Earnings Hero Overview Card
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [Color(0xFF0F766E), Color(0xFF0D9488)],
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
                      'Kabuuang Kinita (Total Earnings)',
                      style: TextStyle(
                        color: Colors.white70,
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                      decoration: BoxDecoration(
                        color: Colors.white24,
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: const Row(
                        children: [
                          Icon(Icons.verified_rounded, size: 14, color: Colors.white),
                          SizedBox(width: 4),
                          Text(
                            'Alona Verified',
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 11,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Text(
                  'PHP $totalEarned',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 34,
                    fontWeight: FontWeight.w900,
                    letterSpacing: -0.5,
                  ),
                ),
                const SizedBox(height: 16),
                Row(
                  children: [
                    Expanded(
                      child: Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.15),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Row(
                              children: [
                                Icon(Icons.check_circle_outline, color: Colors.white70, size: 14),
                                SizedBox(width: 4),
                                Text(
                                  'Nabayaran Na',
                                  style: TextStyle(color: Colors.white70, fontSize: 11),
                                ),
                              ],
                            ),
                            const SizedBox(height: 4),
                            Text(
                              'PHP $paidEarnings',
                              style: const TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.w900,
                                fontSize: 16,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.15),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Row(
                              children: [
                                Icon(Icons.schedule, color: Colors.white70, size: 14),
                                SizedBox(width: 4),
                                Text(
                                  'Papasok / Unpaid',
                                  style: TextStyle(color: Colors.white70, fontSize: 11),
                                ),
                              ],
                            ),
                            const SizedBox(height: 4),
                            Text(
                              'PHP $unpaidEarnings',
                              style: const TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.w900,
                                fontSize: 16,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),

          const SizedBox(height: 14),

          // Payout Schedule Information Advisory
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: const Color(0xFFEFF6FF),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: const Color(0xFFBFDBFE)),
            ),
            child: const Row(
              children: [
                Icon(Icons.event_available_rounded, color: Color(0xFF2563EB), size: 24),
                SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Payout Cycle: Tuwing Miyerkules at Biyernes',
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.bold,
                          color: Color(0xFF1E40AF),
                        ),
                      ),
                      SizedBox(height: 2),
                      Text(
                        'Awtomatikong nire-release ang delivery commission sa iyong bangko o e-wallet pagkatapos ma-audit ng Hub Cashier ang nai-remit na COD.',
                        style: TextStyle(fontSize: 11, color: Color(0xFF1E3A8A)),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 18),

          // Earnings History Section
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: RiderColors.border),
              boxShadow: const [
                BoxShadow(color: Colors.black12, blurRadius: 4, offset: Offset(0, 2)),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Row(
                      children: [
                        Icon(Icons.receipt_long_rounded, color: Color(0xFF0F766E), size: 20),
                        SizedBox(width: 8),
                        Text(
                          'Talaan ng Delivery Fee',
                          style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
                        ),
                      ],
                    ),
                    Text(
                      '${earningsRows.length} delivery(s)',
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.bold,
                        color: RiderColors.textSecondary,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),

                // Filter Chips
                Row(
                  children: [
                    _buildEarningsFilterChip('all', 'Lahat (${earningsRows.length})'),
                    const SizedBox(width: 6),
                    _buildEarningsFilterChip('paid', 'Nabayaran Na'),
                    const SizedBox(width: 6),
                    _buildEarningsFilterChip('unpaid', 'Papasok Pa'),
                  ],
                ),

                const SizedBox(height: 14),

                if (filteredRows.isEmpty)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 24),
                    child: Center(
                      child: Column(
                        children: [
                          Icon(Icons.inventory_2_outlined, size: 36, color: Colors.grey),
                          SizedBox(height: 8),
                          Text(
                            'Walang recorded delivery earnings sa filter na ito.',
                            style: TextStyle(color: Colors.grey, fontSize: 13),
                          ),
                        ],
                      ),
                    ),
                  )
                else
                  ...filteredRows.map((row) => _buildEarningsRowItem(row)),
              ],
            ),
          ),
          const SizedBox(height: 24),
        ],
      ),
    );
  }

  Widget _buildEarningsFilterChip(String key, String label) {
    final isSelected = _earningsFilter == key;
    return InkWell(
      onTap: () {
        HapticFeedback.selectionClick();
        setState(() => _earningsFilter = key);
      },
      borderRadius: BorderRadius.circular(20),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        decoration: BoxDecoration(
          color: isSelected ? const Color(0xFF0F766E) : const Color(0xFFF1F5F9),
          borderRadius: BorderRadius.circular(20),
          border: Border.all(
            color: isSelected ? const Color(0xFF0F766E) : const Color(0xFFE2E8F0),
          ),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 11,
            fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
            color: isSelected ? Colors.white : RiderColors.textSecondary,
          ),
        ),
      ),
    );
  }

  List<dynamic> _getFilteredEarnings() {
    if (_earningsFilter == 'all') return earningsRows;
    return earningsRows.where((row) {
      final status = (row['payout_status'] ?? '').toString().toLowerCase();
      if (_earningsFilter == 'paid') return status == 'paid';
      if (_earningsFilter == 'unpaid') return status != 'paid';
      return true;
    }).toList();
  }

  Widget _buildEarningsRowItem(dynamic row) {
    final amount = row['amount'] ?? '0.00';
    final status = (row['payout_status'] ?? 'pending').toString().toLowerCase();
    final earnedAt = row['earned_at'] != null
        ? DateTime.tryParse('${row['earned_at']}')?.toLocal().toString().substring(0, 16)
        : '';
    final isPaid = status == 'paid';

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: isPaid ? const Color(0xFFF0FDF4) : const Color(0xFFFFFBEB),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: isPaid ? const Color(0xFFBBF7D0) : const Color(0xFFFDE68A),
          ),
        ),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: isPaid ? const Color(0xFFDCFCE7) : const Color(0xFFFEF3C7),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(
                isPaid ? Icons.check_circle_rounded : Icons.pending_actions_rounded,
                color: isPaid ? const Color(0xFF16A34A) : const Color(0xFFD97706),
                size: 20,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Delivery Commission #${row['id']}',
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                  ),
                  if (earnedAt != null && earnedAt.isNotEmpty)
                    Text(
                      earnedAt,
                      style: const TextStyle(fontSize: 11, color: RiderColors.textSecondary),
                    ),
                ],
              ),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                Text(
                  '+PHP $amount',
                  style: TextStyle(
                    fontWeight: FontWeight.w900,
                    fontSize: 15,
                    color: isPaid ? const Color(0xFF15803D) : const Color(0xFFB45309),
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                  decoration: BoxDecoration(
                    color: isPaid ? const Color(0xFFDCFCE7) : const Color(0xFFFEF3C7),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    isPaid ? 'Nabayaran' : 'Pending Payout',
                    style: TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.w800,
                      color: isPaid ? const Color(0xFF166534) : const Color(0xFF92400E),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
