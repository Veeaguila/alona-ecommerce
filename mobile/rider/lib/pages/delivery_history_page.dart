import 'dart:convert';

import 'package:flutter/material.dart';

import '../widgets/rider_bottom_nav.dart';
import '../widgets/rider_content.dart';
import '../services/api_config.dart';
import '../services/api_error.dart';
import '../services/parcel_address.dart';
import '../services/rider_session.dart';
import '../services/rider_session_guard.dart';
import 'profit_dashboard_page.dart';
import '../theme/rider_theme.dart';

class DeliveryHistoryPage extends StatefulWidget {
  final RiderSession? session;
  const DeliveryHistoryPage({super.key, this.session});
  @override
  State<DeliveryHistoryPage> createState() => _DeliveryHistoryPageState();
}

class _DeliveryHistoryPageState extends State<DeliveryHistoryPage>
    with WidgetsBindingObserver, RiderSessionGuard<DeliveryHistoryPage> {
  @override
  late final riderSession = widget.session ?? RiderSession();
  List<Map<String, dynamic>> rows = [];
  Map<String, dynamic> summary = {};
  int page = 1, lastPage = 1, revision = 0;
  bool loading = true;
  String? error;
  String filter = 'All';
  String searchQuery = '';
  final searchController = TextEditingController();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    load();
  }

  @override
  void dispose() {
    searchController.dispose();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      load();
    }
  }

  Future<void> load({int? target}) async {
    final current = ++revision;
    setState(() {
      loading = true;
      error = null;
      rows = [];
    });
    try {
      final token = await readyToken();
      final uri = Uri.parse(deliveriesUrl).replace(
        queryParameters: {'view': 'history', 'page': '${target ?? page}'},
      );
      final response = await riderSession.get(
        uri,
        headers: {'Authorization': 'Bearer $token'},
      );
      await checkAuthorization(response.statusCode);
      if (response.statusCode != 200) {
        throw SessionFailure(apiErrorMessage(response));
      }
      final payload = jsonDecode(response.body) as Map<String, dynamic>;
      final data = List<Map<String, dynamic>>.from(payload['data'] as List);
      if (!mounted || current != revision) return;
      setState(() {
        rows = data;
        summary = Map<String, dynamic>.from(payload['summary'] as Map);
        page = (payload['meta']['current_page'] as num).toInt();
        lastPage = (payload['meta']['last_page'] as num).toInt();
        loading = false;
      });
    } catch (failure) {
      if (mounted && current == revision) {
        setState(() {
          loading = false;
          error = failure is SessionFailure
              ? failure.message
              : 'Could not load delivery history. Refresh and retry.';
        });
      }
    }
  }

  List<Map<String, dynamic>> get filteredRows {
    return rows.where((row) {
      final status = (row['status'] ?? '').toString().toUpperCase();
      if (filter == 'Delivered' && !status.contains('DELIVERED')) {
        return false;
      }
      if (filter == 'Pickups' &&
          !(status.contains('PICK') ||
              status.contains('COLLECT') ||
              status.contains('TRANSIT'))) {
        return false;
      }
      if (filter == 'Failed' &&
          !(status.contains('FAIL') || status.contains('RETURN'))) {
        return false;
      }

      if (searchQuery.isNotEmpty) {
        final query = searchQuery.toLowerCase();
        final orderId = (row['order_id'] ?? '').toString().toLowerCase();
        final addr = deliveryAddress(row).toLowerCase();
        final reason = (row['failure_reason'] ?? '').toString().toLowerCase();
        if (!orderId.contains(query) &&
            !addr.contains(query) &&
            !reason.contains(query)) {
          return false;
        }
      }
      return true;
    }).toList();
  }

  Widget _buildFilterPill(String title) {
    final isSelected = filter == title;
    return GestureDetector(
      onTap: () => setState(() => filter = title),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        decoration: BoxDecoration(
          color: isSelected
              ? RiderColors.amber
              : Colors.white.withValues(alpha: 0.18),
          borderRadius: BorderRadius.circular(20),
        ),
        child: Text(
          title,
          style: TextStyle(
            color: isSelected ? const Color(0xFF78350F) : Colors.white,
            fontWeight: FontWeight.w700,
            fontSize: 13,
          ),
        ),
      ),
    );
  }

  Widget _buildStatusBadge(String rawStatus) {
    final status = rawStatus.toUpperCase();
    if (status.contains('DELIVERED')) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        decoration: BoxDecoration(
          color: RiderColors.mintBadge,
          borderRadius: BorderRadius.circular(12),
        ),
        child: const Text(
          'Delivered',
          style: TextStyle(
            color: RiderColors.mintBadgeText,
            fontWeight: FontWeight.w700,
            fontSize: 11,
          ),
        ),
      );
    }
    if (status.contains('FAIL') || status.contains('RETURN')) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        decoration: BoxDecoration(
          color: RiderColors.redBadge,
          borderRadius: BorderRadius.circular(12),
        ),
        child: const Text(
          'Failed',
          style: TextStyle(
            color: RiderColors.redBadgeText,
            fontWeight: FontWeight.w700,
            fontSize: 11,
          ),
        ),
      );
    }
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: RiderColors.blueBadge,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(
        status.contains('PICK') ? 'Picked up' : 'In transit',
        style: const TextStyle(
          color: RiderColors.blueBadgeText,
          fontWeight: FontWeight.w700,
          fontSize: 11,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final currentFiltered = filteredRows;

    return Scaffold(
      backgroundColor: RiderColors.background,
      body: Column(
        children: [
          // Screen 10 Header Banner (Teal)
          Container(
            color: RiderColors.teal,
            child: SafeArea(
              bottom: false,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Top row: Back to Earnings & Refresh
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        TextButton.icon(
                          onPressed: () {
                            if (Navigator.of(context).canPop()) {
                              Navigator.of(context).pop();
                            } else {
                              Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (_) => ProfitDashboardPage(
                                    session: riderSession,
                                  ),
                                ),
                              );
                            }
                          },
                          icon: const Icon(
                            Icons.chevron_left,
                            color: Colors.white,
                            size: 20,
                          ),
                          label: const Text(
                            'View earnings',
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 15,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          style: TextButton.styleFrom(padding: EdgeInsets.zero),
                        ),
                        IconButton(
                          onPressed: loading ? null : () => load(),
                          icon: const Icon(Icons.refresh, color: Colors.white),
                          tooltip: 'Refresh',
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'Delivery history',
                      style: TextStyle(
                        fontSize: 26,
                        fontWeight: FontWeight.w800,
                        color: Colors.white,
                      ),
                    ),
                    const SizedBox(height: 8),
                    // Summary counts
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 6,
                      ),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.15),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Text(
                        'Delivered: ${summary['DELIVERED'] ?? 0}   Failed: ${summary['FAILED_RETURNED'] ?? 0}   Active: ${summary['active'] ?? 0}',
                        style: const TextStyle(
                          fontWeight: FontWeight.w600,
                          fontSize: 12,
                          color: Colors.white,
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
                    // Filter pills row
                    SingleChildScrollView(
                      scrollDirection: Axis.horizontal,
                      child: Row(
                        children: [
                          _buildFilterPill('All'),
                          const SizedBox(width: 8),
                          _buildFilterPill('Delivered'),
                          const SizedBox(width: 8),
                          _buildFilterPill('Pickups'),
                          const SizedBox(width: 8),
                          _buildFilterPill('Failed'),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
          // Search Bar & Delivery Rows List
          Expanded(
            child: RefreshIndicator(
              onRefresh: () => load(),
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: riderPagePadding(context),
                children: [
                  const SizedBox(height: 12),
                  // Search Field matching Screen 10
                  TextField(
                    controller: searchController,
                    onChanged: (val) => setState(() => searchQuery = val.trim()),
                    decoration: InputDecoration(
                      hintText: 'Search order no. or name',
                      prefixIcon: const Icon(
                        Icons.search,
                        color: RiderColors.textSecondary,
                      ),
                      suffixIcon: searchQuery.isNotEmpty
                          ? IconButton(
                              icon: const Icon(Icons.clear, size: 18),
                              onPressed: () {
                                searchController.clear();
                                setState(() => searchQuery = '');
                              },
                            )
                          : null,
                      filled: true,
                      fillColor: Colors.white,
                      contentPadding: const EdgeInsets.symmetric(
                        horizontal: 16,
                        vertical: 14,
                      ),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(16),
                        borderSide: const BorderSide(color: RiderColors.border),
                      ),
                      enabledBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(16),
                        borderSide: const BorderSide(color: RiderColors.border),
                      ),
                      focusedBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(16),
                        borderSide: const BorderSide(
                          color: RiderColors.teal,
                          width: 1.5,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  if (loading)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 32),
                      child: Center(child: CircularProgressIndicator()),
                    ),
                  if (error != null)
                    Container(
                      padding: const EdgeInsets.all(16),
                      margin: const EdgeInsets.only(bottom: 16),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFEE2E2),
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: const Color(0xFFFCA5A5)),
                      ),
                      child: Text(
                        error!,
                        style: const TextStyle(
                          color: RiderColors.danger,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  if (!loading && error == null && currentFiltered.isEmpty)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 24),
                      child: Center(
                        child: Text(
                          'No completed deliveries yet.',
                          style: TextStyle(
                            color: RiderColors.textSecondary,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ),
                    ),
                  if (currentFiltered.isNotEmpty)
                    Container(
                      margin: const EdgeInsets.only(bottom: 16),
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
                          itemCount: currentFiltered.length,
                          separatorBuilder: (context, index) => const Divider(
                            height: 1,
                            thickness: 1,
                            color: RiderColors.border,
                          ),
                          itemBuilder: (context, index) {
                            final row = currentFiltered[index];
                            final statusStr = (row['status'] ?? '')
                                .toString()
                                .replaceAll('_', ' ');
                            return Padding(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 16,
                                vertical: 14,
                              ),
                              child: Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          'Order ${row['order_id']}',
                                          style: const TextStyle(
                                            fontWeight: FontWeight.w700,
                                            fontSize: 15,
                                            color: RiderColors.text,
                                          ),
                                        ),
                                        const SizedBox(height: 4),
                                        Text(
                                          '$statusStr\n${deliveryAddress(row)}${row['failure_reason'] == null ? '' : '\n${row['failure_reason']}'}${row['delivered_at'] == null ? '' : '\nCompleted: ${row['delivered_at']}'}',
                                          style: const TextStyle(
                                            fontSize: 13,
                                            color: RiderColors.textSecondary,
                                            height: 1.3,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                  const SizedBox(width: 12),
                                  _buildStatusBadge(row['status'] ?? ''),
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
                          onPressed: loading || page <= 1
                              ? null
                              : () => load(target: page - 1),
                          style: OutlinedButton.styleFrom(
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(16),
                            ),
                            side: const BorderSide(color: RiderColors.border),
                          ),
                          child: const Text('Previous'),
                        ),
                        Text(
                          'Page $page of $lastPage',
                          style: const TextStyle(
                            fontWeight: FontWeight.w600,
                            color: RiderColors.textSecondary,
                          ),
                        ),
                        OutlinedButton(
                          onPressed: loading || page >= lastPage
                              ? null
                              : () => load(target: page + 1),
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
              ),
            ),
          ),
        ],
      ),
      bottomNavigationBar: RiderBottomNav(
        currentIndex: 1,
        session: riderSession,
      ),
    );
  }
}
