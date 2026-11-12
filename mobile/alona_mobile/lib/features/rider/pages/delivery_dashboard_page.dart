import '../widgets/rider_presence_control.dart';

import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../widgets/rider_content.dart';

import 'package:image_picker/image_picker.dart';

import '../services/api_error.dart';

import '../services/api_config.dart';
import '../services/parcel_address.dart';
import '../services/rider_quick_actions.dart';
import '../services/rider_session.dart';
import '../services/rider_session_guard.dart';
import '../widgets/failure_reason_dialog.dart';
import 'login_page.dart';
import 'rider_portal_page.dart';
import 'proof_capture_page.dart';
import 'rider_returns_page.dart';
import 'parcel_scanner_page.dart';
import 'delivery_success_page.dart';
import 'cod_and_earnings_tracker_page.dart';
import '../theme/rider_theme.dart';
import '../widgets/rider_interactive_map_card.dart';
import 'order_detail_pages.dart';
import 'pickup_dashboard_page.dart';
import 'rider_notifications_page.dart';

class DeliveryDashboardPage extends StatefulWidget {
  final RiderSession? session;
  final int? parcelId;
  final Future<XFile?> Function()? capturePhoto;
  final bool active;
  const DeliveryDashboardPage({
    super.key,
    this.session,
    this.parcelId,
    this.capturePhoto,
    this.active = true,
  });

  @override
  State<DeliveryDashboardPage> createState() => _DeliveryDashboardPageState();
}

class _DeliveryDashboardPageState extends State<DeliveryDashboardPage>
    with WidgetsBindingObserver, RiderSessionGuard<DeliveryDashboardPage> {
  @override
  late final riderSession = widget.session ?? RiderSession();
  bool loggingOut = false;
  List<Map<String, dynamic>> deliveries = [];
  bool loading = true;
  String? error;
  String? success;
  int? actionParcelId;
  bool proofFlow = false;
  int refreshRevision = 0;
  final scroll = ScrollController();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    if (widget.active) refresh();
  }

  @override
  void didUpdateWidget(covariant DeliveryDashboardPage oldWidget) {
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
    if (state == AppLifecycleState.resumed && !loggingOut && !proofFlow) {
      refresh();
    }
  }

  Future<void> refresh() async {
    if (loggingOut) return;
    final revision = ++refreshRevision;
    setState(() {
      loading = true;
      deliveries = [];
      error = null;
      success = null;
    });
    try {
      final token = await readyToken();
      final response = await riderSession.get(
        Uri.parse(deliveriesUrl),
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        },
      );
      await checkAuthorization(response.statusCode);
      if (response.statusCode != 200) {
        throw SessionFailure(apiErrorMessage(response));
      }
      if (!mounted || revision != refreshRevision) return;
      setState(() {
        deliveries =
            List<Map<String, dynamic>>.from(
                  jsonDecode(response.body)['data'] as List,
                )
                .where(
                  (parcel) =>
                      widget.parcelId == null ||
                      parcel['id'] == widget.parcelId,
                )
                .toList();
        loading = false;
      });
    } catch (exception) {
      if (!mounted || revision != refreshRevision) return;
      setState(() {
        loading = false;
        error = exception is SessionFailure ? exception.message : 'Could not confirm this delivery request. Refresh before retrying.';
      });
    }
  }

  Future<void> logout() async {
    if (loggingOut || proofFlow || actionParcelId != null) return;
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

  String address(Map<String, dynamic> delivery) => deliveryAddress(delivery);

  Future<void> markDelivered(Map<String, dynamic> delivery) async {
    if (loggingOut || proofFlow || actionParcelId != null) return;
    setState(() => proofFlow = true);
    try {
      var current = delivery;
      if (current['has_valid_photo_proof'] != true) {
        final uploaded = await openProof(current);
        if (!mounted || uploaded != true) return;
        await refresh();
        if (!mounted || error != null) return;
        final updated = deliveries
            .where((row) => row['id'] == delivery['id'])
            .firstOrNull;
        if (updated == null || updated['has_valid_photo_proof'] != true) {
          setState(
            () => error = 'Your photo could not be verified for this active delivery. Refresh and retry.',
          );
          return;
        }
        current = updated;
      }
      if (!mounted) return;
      if (await updateDelivery(current, 'deliver') && mounted) {
        await Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => DeliverySuccessPage(
              reference: current['order_id'].toString(),
              returnToScanner: widget.parcelId != null,
            ),
          ),
        );
        if (mounted && widget.parcelId != null) Navigator.pop(context, true);
      }
    } finally {
      if (mounted) setState(() => proofFlow = false);
    }
  }

  Future<bool?> openProof(Map<String, dynamic> delivery) =>
      Navigator.push<bool>(
        context,
        MaterialPageRoute(
          builder: (_) => ProofCapturePage(
            openCameraOnStart: true,
            capturePhoto: widget.capturePhoto,
            parcelId: delivery['id'],
            session: riderSession,
            deliveryAddress: address(delivery),
          ),
        ),
      );

  Future<void> startDelivery(Map<String, dynamic> delivery) async {
    if (loggingOut || actionParcelId != null || proofFlow) return;
    setState(() {
      actionParcelId = delivery['id'] as int;
      error = null;
      success = null;
    });
    try {
      final token = await readyToken();
      final response = await riderSession.post(
        Uri.parse('$deliveriesUrl/${delivery['id']}/start'),
        headers: {
          'Accept': 'application/json',
          'Authorization': 'Bearer $token',
        },
      );
      await checkAuthorization(response.statusCode);
      if (response.statusCode != 200) {
        throw SessionFailure(apiErrorMessage(response));
      }
      final payload = jsonDecode(response.body) as Map<String, dynamic>;
      if (response.statusCode != 200) {
        throw Exception(
          payload['message']?.toString() ?? 'Could not start this delivery.',
        );
      }
      if (!mounted) return;
      setState(() {
        delivery['status'] = 'OUT_FOR_DELIVERY';
        success = payload['message']?.toString() ?? 'Delivery started.';
        actionParcelId = null;
      });
    } catch (exception) {
      if (!mounted) return;
      setState(() {
        actionParcelId = null;
        error = exception is SessionFailure ? exception.message : 'Could not confirm this delivery request. Refresh before retrying.';
      });
    }
  }

  Future<void> markFailed(Map<String, dynamic> delivery) async {
    if (loggingOut || proofFlow || actionParcelId != null) return;
    final reason = await showFailureReasonDialog();
    if (reason == null || reason.trim().isEmpty) {
      return;
    }
    await updateDelivery(delivery, 'fail', body: {'reason': reason.trim()});
  }

  Future<bool> updateDelivery(
    Map<String, dynamic> delivery,
    String action, {
    Map<String, String>? body,
  }) async {
    if (loggingOut || actionParcelId != null) return false;
    setState(() {
      actionParcelId = delivery['id'] as int;
      error = null;
      success = null;
    });
    try {
      final token = await readyToken();
      final response = await riderSession.post(
        Uri.parse('$deliveriesUrl/${delivery['id']}/$action'),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'Authorization': 'Bearer $token',
        },
        body: body != null ? jsonEncode(body) : null,
      );
      await checkAuthorization(response.statusCode);
      if (response.statusCode != 200) {
        if (response.statusCode == 422) {
          try {
            if (jsonDecode(response.body)['code'] == 'valid_photo_required') {
              delivery['has_valid_photo_proof'] = false;
            }
          } catch (_) {
            /* Only trust the known backend code. */
          }
        }
        throw SessionFailure(apiErrorMessage(response));
      }
      final payload = jsonDecode(response.body) as Map<String, dynamic>;
      if (response.statusCode != 200) {
        throw Exception(
          payload['message']?.toString() ?? 'Could not update this delivery.',
        );
      }
      if (action != 'deliver') await refresh();
      if (!mounted) return false;
      setState(() {
        if (action == 'deliver') {
          deliveries.removeWhere((item) => item['id'] == delivery['id']);
        }
        success =
            payload['message']?.toString() ?? 'Delivery updated successfully.';
        actionParcelId = null;
      });
      Future.delayed(const Duration(seconds: 3), () {
        if (mounted && success != null) {
          setState(() => success = null);
        }
      });
      return true;
    } catch (exception) {
      if (!mounted) return false;
      if (scroll.hasClients) scroll.jumpTo(0);
      setState(() {
        actionParcelId = null;
        error = exception is SessionFailure ? exception.message : 'Could not confirm this delivery request. Refresh before retrying.';
      });
      return false;
    }
  }

  Future<String?> showFailureReasonDialog() async {
    return showDialog<String>(
      context: context,
      builder: (context) => const FailureReasonDialog(),
    );
  }

  Widget deliveryCard(Map<String, dynamic> delivery) {
    final status = delivery['status'];
    final isOutForDelivery = status == 'OUT_FOR_DELIVERY';
    final isAssigned = status == 'AT_SORTING_CENTER';

    final canAttempt =
        isOutForDelivery &&
        delivery['awaiting_retry'] != true &&
        delivery['return_status'] == null &&
        delivery['rts_eligible'] != true;
    final canStart =
        isAssigned &&
        delivery['return_status'] == null &&
        delivery['rts_eligible'] != true;
    final method = delivery['payment_method'];
    final isCod = delivery['is_cod'] == true || method == 'cod';
    final amount = delivery['cod_amount'];
    final metaText = isCod
        ? (amount == null ? 'Cash on delivery' : 'Cash on delivery · ₱$amount')
        : 'Payment details: confirm with Logistics';

    final recipient =
        delivery['customer_name']?.toString().trim().isNotEmpty == true
        ? delivery['customer_name'].toString()
        : delivery['recipient_name']?.toString().trim().isNotEmpty == true
        ? delivery['recipient_name'].toString()
        : orderContactName(delivery, pickup: false);
    final custName = recipient.isEmpty ? 'Buyer' : recipient;

    final addr = address(delivery);

    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      decoration: BoxDecoration(
        gradient: RiderColors.cardGradient,
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 14,
            offset: const Offset(0, 5),
          ),
        ],
      ),
      child: InkWell(
        borderRadius: BorderRadius.circular(20),
        onTap: actionParcelId != null || proofFlow || loggingOut
            ? null
            : () => Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => DeliveryOrderPage(
                    delivery: delivery,
                    session: riderSession,
                    statusLabel: isAssigned ? 'ASSIGNED' : 'OUT FOR DELIVERY',
                    onStart: canStart ? () => startDelivery(delivery) : null,
                    onCapture: canAttempt ? () => openProof(delivery) : null,
                    onDeliver: canAttempt
                        ? () => markDelivered(delivery)
                        : null,
                    onFail: canAttempt
                        ? (reason) async {
                            await updateDelivery(
                              delivery,
                              'fail',
                              body: {'reason': reason},
                            );
                          }
                        : null,
                  ),
                ),
              ),
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      '#${delivery['order_id']}',
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
                      color: isOutForDelivery
                          ? const Color(0xFFD1FAE5)
                          : const Color(0xFFFEF3C7),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      isOutForDelivery
                          ? 'Out for delivery'
                          : (isAssigned ? 'Next stop' : 'Assigned'),
                      style: TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 12,
                        color: isOutForDelivery
                            ? const Color(0xFF065F46)
                            : const Color(0xFF92400E),
                      ),
                    ),
                  ),
                ],
              ),
              Builder(
                builder: (_) {
                  final isCod = delivery['is_cod'] == true;
                  final codAmount = delivery['cod_amount'] != null
                      ? double.tryParse(delivery['cod_amount'].toString()) ?? 0.0
                      : 0.0;
                  return Container(
                    width: double.infinity,
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                    margin: const EdgeInsets.only(top: 8),
                    decoration: BoxDecoration(
                      color: isCod ? const Color(0xFFFEF3C7) : const Color(0xFFD1FAE5),
                      borderRadius: BorderRadius.circular(8),
                      border: Border.all(
                        color: isCod ? const Color(0xFFF59E0B) : const Color(0xFF10B981),
                      ),
                    ),
                    child: Text(
                      isCod
                          ? 'COD: Collect PHP ${codAmount.toStringAsFixed(2)}'
                          : 'PAID ONLINE - Do not collect',
                      style: TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 12,
                        color: isCod ? const Color(0xFF92400E) : const Color(0xFF065F46),
                      ),
                    ),
                  );
                },
              ),
              if (delivery['item_name'] != null) ...[
                const SizedBox(height: 6),
                Text(
                  'Item: ${delivery['item_quantity'] ?? 1}x ${delivery['item_name']}',
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: RiderColors.textSecondary,
                  ),
                ),
              ],
              const SizedBox(height: 12),
              Text(
                custName,
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
                      addr.isEmpty ? 'Address not provided' : addr,
                      style: const TextStyle(
                        color: RiderColors.textSecondary,
                        fontSize: 14,
                      ),
                    ),
                  ),
                ],
              ),
              RiderCardQuickActions(
                address: addr,
                phone: delivery['address_details'] is Map
                    ? (delivery['address_details']['contact_no']?.toString() ?? '')
                    : (delivery['contact_no']?.toString() ?? ''),
                recipientName: custName,
                orderId: '${delivery['order_id'] ?? ''}',
                isCod: isCod,
                codAmount: double.tryParse('$amount') ?? 0.0,
                isPickup: false,
              ),
              const SizedBox(height: 10),
              Wrap(
                alignment: WrapAlignment.spaceBetween,
                crossAxisAlignment: WrapCrossAlignment.center,
                spacing: 8,
                runSpacing: 8,
                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        metaText,
                        style: const TextStyle(
                          color: RiderColors.textSecondary,
                          fontSize: 14,
                        ),
                      ),
                      if (isOutForDelivery &&
                          delivery['awaiting_retry'] != true &&
                          delivery['return_status'] == null &&
                          delivery['rts_eligible'] != true)
                        TextButton(
                          onPressed: actionParcelId != null || loggingOut
                              ? null
                              : () => markFailed(delivery),
                          style: TextButton.styleFrom(
                            padding: EdgeInsets.zero,
                            minimumSize: Size.zero,
                            tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                            foregroundColor: RiderColors.danger,
                          ),
                          child: const Text(
                            'Mark Failed',
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                    ],
                  ),
                  if (delivery['return_status'] != null ||
                      delivery['rts_eligible'] == true)
                    OutlinedButton(
                      onPressed: () => Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) =>
                              RiderReturnsPage(session: riderSession),
                        ),
                      ),
                      child: const Text('Return to Seller jobs'),
                    )
                  else if (delivery['awaiting_retry'] == true)
                    FilledButton(
                      onPressed: actionParcelId != null
                          ? null
                          : () => updateDelivery(delivery, 'retry'),
                      child: const Text('Start next delivery attempt'),
                    )
                  else if (isAssigned)
                    Container(
                      decoration: BoxDecoration(
                        gradient: RiderColors.headerGradient,
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
                            : () => startDelivery(delivery),
                        child: const Text(
                          'Start Delivery',
                          style: TextStyle(
                            fontWeight: FontWeight.w800,
                            fontSize: 15,
                          ),
                        ),
                      ),
                    )
                  else if (isOutForDelivery)
                    Container(
                      decoration: BoxDecoration(
                        gradient: RiderColors.headerGradient,
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
                            horizontal: 24,
                            vertical: 14,
                          ),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(16),
                          ),
                        ),
                        onPressed:
                            actionParcelId != null || proofFlow || loggingOut
                            ? null
                            : () => markDelivered(delivery),
                        child: const Text(
                          'Mark Delivered',
                          style: TextStyle(
                            fontWeight: FontWeight.w800,
                            fontSize: 15,
                          ),
                        ),
                      ),
                    )
                  else
                    OutlinedButton(
                      style: OutlinedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 24,
                          vertical: 14,
                        ),
                        side: const BorderSide(color: RiderColors.teal),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(16),
                        ),
                      ),
                      onPressed: () => Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => DeliveryOrderPage(
                            delivery: delivery,
                            session: riderSession,
                            statusLabel: isAssigned
                                ? 'ASSIGNED'
                                : 'OUT FOR DELIVERY',
                            onStart: canStart
                                ? () => startDelivery(delivery)
                                : null,
                            onCapture: canAttempt
                                ? () => openProof(delivery)
                                : null,
                            onDeliver: canAttempt
                                ? () => markDelivered(delivery)
                                : null,
                            onFail: canAttempt
                                ? (reason) async {
                                    await updateDelivery(
                                      delivery,
                                      'fail',
                                      body: {'reason': reason},
                                    );
                                  }
                                : null,
                          ),
                        ),
                      ),
                      child: const Text(
                        'View',
                        style: TextStyle(
                          color: RiderColors.teal,
                          fontWeight: FontWeight.w800,
                          fontSize: 15,
                        ),
                      ),
                    ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return RiderTheme.wrap(Scaffold(
      backgroundColor: RiderColors.portalBackground,
      body: Column(
        children: [
          _header(context),
          Expanded(
            child: RefreshIndicator(
              onRefresh: refresh,
              child: ListView(
                controller: scroll,
                padding: riderPagePadding(context).copyWith(top: 16),
                children: [
                  RiderPresenceControl(session: riderSession),
                  if (error != null) ...[
                    const SizedBox(height: 16),
                    _statusMessage(error!),
                  ],
                  if (success != null) ...[
                    const SizedBox(height: 16),
                    _successMessage(success!),
                  ],
                  if (widget.parcelId != null) ...[
                    const SizedBox(height: 12),
                    Builder(
                      builder: (ctx) {
                        final filtered = deliveries.firstWhere(
                          (d) => d['id'] == widget.parcelId,
                          orElse: () => deliveries.isNotEmpty ? deliveries.first : {},
                        );
                        final pAddr = address(filtered);
                        return RiderInteractiveMapCard(
                          destinationAddress: pAddr.isNotEmpty ? pAddr : 'Santa Rosa, Laguna',
                          destinationName: orderContactName(filtered, pickup: false),
                          height: 230,
                          onOpenExternalMap: () => RiderQuickActions.openMapNavigation(context, pAddr),
                        );
                      },
                    ),
                  ],
                  if (loading) ...[
                    const SizedBox(height: 40),
                    const Center(child: CircularProgressIndicator()),
                  ] else ...[
                    if (deliveries.isEmpty) ...[
                      const SizedBox(height: 14),
                      // Standby Hub & Coverage Map
                      RiderInteractiveMapCard(
                        destinationAddress: 'Santa Rosa, Laguna',
                        destinationName: 'Sorting Center Hub (Standby)',
                        height: 220,
                        onOpenExternalMap: () => RiderQuickActions.openMapNavigation(
                          context,
                          'Laguna Sorting Center',
                        ),
                      ),
                      if (widget.parcelId == null) ...[
                        const SizedBox(height: 14),
                        _scanHeroCard(context),
                        const SizedBox(height: 10),
                        _codCounterHeroCard(context),
                      ],
                      const SizedBox(height: 24),
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(24),
                        decoration: BoxDecoration(
                          gradient: RiderColors.cardGradient,
                          borderRadius: BorderRadius.circular(20),
                          border: Border.all(color: const Color(0xFFE2E8F0)),
                        ),
                        child: Column(
                          children: [
                            Container(
                              padding: const EdgeInsets.all(16),
                              decoration: const BoxDecoration(
                                color: Color(0xFFD1FAE5),
                                shape: BoxShape.circle,
                              ),
                              child: const Icon(
                                Icons.check_circle_rounded,
                                size: 36,
                                color: Color(0xFF059669),
                              ),
                            ),
                            const SizedBox(height: 14),
                            Text(
                              widget.parcelId == null
                                  ? 'All caught up! No active deliveries.'
                                  : 'This parcel is no longer in your active assignments. Refresh or check Delivery History.',
                              textAlign: TextAlign.center,
                              style: const TextStyle(
                                fontWeight: FontWeight.w800,
                                fontSize: 16,
                                color: RiderColors.text,
                              ),
                            ),
                            const SizedBox(height: 6),
                            const Text(
                              'Pumunta sa Sorting Hub upang mag-scan ng susunod na batch ng parcels.',
                              textAlign: TextAlign.center,
                              style: TextStyle(
                                fontSize: 13,
                                color: RiderColors.textSecondary,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ] else ...[
                      Builder(
                        builder: (_) {
                          final nextStop = deliveries.firstWhere(
                            (d) => d['status'] == 'OUT_FOR_DELIVERY',
                            orElse: () => deliveries.first,
                          );
                          final remaining = deliveries
                              .where((d) => d['id'] != nextStop['id'])
                              .toList();

                          return Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              _nextStopHeroCard(context, nextStop),
                              if (widget.parcelId == null) ...[
                                Row(
                                  children: [
                                    Expanded(
                                      child: _miniActionCard(
                                        icon: Icons.qr_code_scanner,
                                        title: 'Scan Parcels',
                                        color: const Color(0xFF0F766E),
                                        onTap: () => Navigator.push(
                                          context,
                                          MaterialPageRoute(
                                            builder: (_) => ParcelScannerPage(
                                              session: riderSession,
                                            ),
                                          ),
                                        ).then((_) {
                                          if (mounted) refresh();
                                        }),
                                      ),
                                    ),
                                    const SizedBox(width: 10),
                                    Expanded(
                                      child: _miniActionCard(
                                        icon: Icons.calculate_outlined,
                                        title: 'Cash Counter',
                                        color: const Color(0xFFD97706),
                                        onTap: () => Navigator.push(
                                          context,
                                          MaterialPageRoute(
                                            builder: (_) =>
                                                CodAndEarningsTrackerPage(
                                              session: riderSession,
                                              initialTabIndex: 0,
                                            ),
                                          ),
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 20),
                              ],
                              if (remaining.isNotEmpty) ...[
                                Row(
                                  children: [
                                    const Text(
                                      'Upcoming Stops in Queue',
                                      style: TextStyle(
                                        fontSize: 18,
                                        fontWeight: FontWeight.w800,
                                        color: RiderColors.text,
                                      ),
                                    ),
                                    const SizedBox(width: 8),
                                    Container(
                                      padding: const EdgeInsets.symmetric(
                                        horizontal: 10,
                                        vertical: 3,
                                      ),
                                      decoration: BoxDecoration(
                                        color: const Color(0xFFE2E8F0),
                                        borderRadius: BorderRadius.circular(12),
                                      ),
                                      child: Text(
                                        '${remaining.length}',
                                        style: const TextStyle(
                                          fontWeight: FontWeight.w800,
                                          fontSize: 12,
                                          color: Color(0xFF334155),
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 12),
                                ...remaining.map(deliveryCard),
                              ] else ...[
                                Container(
                                  width: double.infinity,
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 16,
                                    vertical: 12,
                                  ),
                                  decoration: BoxDecoration(
                                    color: const Color(0xFFECFDF5),
                                    borderRadius: BorderRadius.circular(14),
                                    border: Border.all(
                                      color: const Color(0xFFA7F3D0),
                                    ),
                                  ),
                                  child: Row(
                                    children: const [
                                      Icon(
                                        Icons.check_circle_outline_rounded,
                                        color: Color(0xFF059669),
                                        size: 20,
                                      ),
                                      SizedBox(width: 10),
                                      Expanded(
                                        child: Text(
                                          'Ito na ang huling delivery para sa batch na ito!',
                                          style: TextStyle(
                                            fontSize: 13,
                                            fontWeight: FontWeight.w700,
                                            color: Color(0xFF065F46),
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ],
                          );
                        },
                      ),
                    ],
                    if (widget.parcelId == null) ...[
                      const SizedBox(height: 24),
                      _availablePickupsSection(),
                    ],
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    ));
  }

  Widget _miniActionCard({
    required IconData icon,
    required String title,
    required Color color,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: color.withValues(alpha: 0.25)),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.03),
              blurRadius: 6,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 20, color: color),
            const SizedBox(width: 8),
            Text(
              title,
              style: TextStyle(
                fontWeight: FontWeight.w800,
                fontSize: 13,
                color: color,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _nextStopHeroCard(BuildContext context, Map<String, dynamic> delivery) {
    final status = delivery['status']?.toString().toUpperCase();
    final isOutForDelivery = status == 'OUT_FOR_DELIVERY';
    final isAssigned = status == 'ASSIGNED';
    final isCod = delivery['is_cod'] == true || delivery['payment_method'] == 'cod';
    final codAmount = delivery['cod_amount'] != null
        ? double.tryParse(delivery['cod_amount'].toString()) ?? 0.0
        : 0.0;

    final recipient = delivery['customer_name']?.toString().trim().isNotEmpty == true
        ? delivery['customer_name'].toString()
        : delivery['recipient_name']?.toString().trim().isNotEmpty == true
            ? delivery['recipient_name'].toString()
            : orderContactName(delivery, pickup: false);
    final custName = recipient.isEmpty ? 'Customer' : recipient;

    final addr = address(delivery);
    final phone = delivery['address_details'] is Map
        ? (delivery['address_details']['contact_no']?.toString() ?? '')
        : (delivery['contact_no']?.toString() ?? '');

    final itemName = delivery['item_name']?.toString();
    final itemQty = delivery['item_quantity'] ?? 1;

    final canStart = isAssigned;
    final canAttempt = isOutForDelivery &&
        delivery['awaiting_retry'] != true &&
        delivery['return_status'] == null &&
        delivery['rts_eligible'] != true;

    return Container(
      margin: const EdgeInsets.only(top: 8, bottom: 18),
      decoration: BoxDecoration(
        gradient: RiderColors.cardGradient,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(
          color: isOutForDelivery ? const Color(0xFF0F766E) : const Color(0xFFF59E0B),
          width: 2,
        ),
        boxShadow: [
          BoxShadow(
            color: (isOutForDelivery ? const Color(0xFF0F766E) : const Color(0xFFF59E0B))
                .withValues(alpha: 0.16),
            blurRadius: 18,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header Banner
            InkWell(
              borderRadius: const BorderRadius.vertical(top: Radius.circular(22)),
              onTap: actionParcelId != null || proofFlow || loggingOut
                  ? null
                  : () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => DeliveryOrderPage(
                          delivery: delivery,
                          session: riderSession,
                          statusLabel: isAssigned ? 'ASSIGNED' : 'OUT FOR DELIVERY',
                          onStart: canStart ? () => startDelivery(delivery) : null,
                          onCapture: canAttempt ? () => openProof(delivery) : null,
                          onDeliver: canAttempt ? () => markDelivered(delivery) : null,
                          onFail: canAttempt
                              ? (reason) async {
                                  await updateDelivery(
                                    delivery,
                                    'fail',
                                    body: {'reason': reason},
                                  );
                                }
                              : null,
                        ),
                      ),
                    ),
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 12),
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: isOutForDelivery
                        ? [const Color(0xFF0F766E), const Color(0xFF0D9488)]
                        : [const Color(0xFFD97706), const Color(0xFFF59E0B)],
                  ),
                  borderRadius: const BorderRadius.vertical(top: Radius.circular(22)),
                ),
                child: Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(6),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.25),
                        shape: BoxShape.circle,
                      ),
                      child: Icon(
                        isOutForDelivery ? Icons.directions_bike_rounded : Icons.schedule_rounded,
                        color: Colors.white,
                        size: 18,
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Text(
                                isOutForDelivery ? '📍 CURRENT NEXT STOP' : '⏳ NEXT DISPATCH STOP',
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 12,
                                  fontWeight: FontWeight.w900,
                                  letterSpacing: 1.1,
                                ),
                              ),
                            ],
                          ),
                          Text(
                            'Order #${delivery['order_id'] ?? delivery['id']}',
                            style: const TextStyle(
                              color: Colors.white70,
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ),
                    ),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                      decoration: BoxDecoration(
                        color: Colors.black.withValues(alpha: 0.22),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Text(
                        isOutForDelivery ? 'IN TRANSIT' : 'ASSIGNED',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 11,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),

            // Card Body
            Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Customer Name
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          custName,
                          style: const TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.w900,
                            color: Color(0xFF0F172A),
                            letterSpacing: -0.3,
                          ),
                        ),
                      ),
                      if (phone.isNotEmpty)
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF1F5F9),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            phone,
                            style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                              color: Color(0xFF475569),
                            ),
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(height: 8),

                  // Destination Address with Pin
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Padding(
                        padding: EdgeInsets.only(top: 2),
                        child: Icon(
                          Icons.location_on_rounded,
                          color: Color(0xFFDC2626),
                          size: 20,
                        ),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          addr.isNotEmpty ? addr : 'No address provided',
                          style: const TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w600,
                            color: Color(0xFF334155),
                            height: 1.35,
                          ),
                        ),
                      ),
                    ],
                  ),

                  // Item details if present
                  if (itemName != null && itemName.isNotEmpty) ...[
                    const SizedBox(height: 10),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF8FAFC),
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: const Color(0xFFE2E8F0)),
                      ),
                      child: Row(
                        children: [
                          const Icon(Icons.inventory_2_outlined, size: 16, color: Color(0xFF64748B)),
                          const SizedBox(width: 6),
                          Expanded(
                            child: Text(
                              '$itemQty x $itemName',
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w600,
                                color: Color(0xFF475569),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],

                  const SizedBox(height: 14),

                  // Live Interactive OpenStreetMap Route Card
                  RiderInteractiveMapCard(
                    destinationAddress: addr,
                    destinationName: custName,
                    height: 210,
                    onOpenExternalMap: () => RiderQuickActions.openMapNavigation(context, addr),
                  ),

                  const SizedBox(height: 14),

                  // Payment / COD Callout Pill
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: isCod ? const Color(0xFFFFFBEB) : const Color(0xFFECFDF5),
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(
                        color: isCod ? const Color(0xFFFBBF24) : const Color(0xFF34D399),
                        width: 1.5,
                      ),
                    ),
                    child: Row(
                      children: [
                        Icon(
                          isCod ? Icons.payments_rounded : Icons.check_circle_rounded,
                          color: isCod ? const Color(0xFFD97706) : const Color(0xFF059669),
                          size: 24,
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                isCod
                                    ? 'COLLECT CASH: PHP ${codAmount.toStringAsFixed(2)}'
                                    : 'PAID ONLINE (No Cash Collection)',
                                style: TextStyle(
                                  fontSize: 15,
                                  fontWeight: FontWeight.w900,
                                  color: isCod ? const Color(0xFF92400E) : const Color(0xFF065F46),
                                ),
                              ),
                              Text(
                                isCod
                                    ? 'Pakihanda ang panukli bago lumapit kay buyer.'
                                    : 'Ipa-verify ang OTP o kumuha ng photo proof.',
                                style: TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w500,
                                  color: isCod ? const Color(0xFFB45309) : const Color(0xFF047857),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 16),

                  // 1-Tap Thumb Action Bar: [ 🚗 Waze/Maps ] [ 📞 Call ] [ 💬 SMS ]
                  Row(
                    children: [
                      // Navigation Button
                      Expanded(
                        flex: 4,
                        child: ElevatedButton.icon(
                          icon: const Icon(Icons.navigation_rounded, size: 18),
                          label: const Text(
                            'Maps / Waze',
                            style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13),
                          ),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFF0284C7),
                            foregroundColor: Colors.white,
                            elevation: 0,
                            padding: const EdgeInsets.symmetric(vertical: 13),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(14),
                            ),
                          ),
                          onPressed: () => RiderQuickActions.openMapNavigation(context, addr),
                        ),
                      ),
                      const SizedBox(width: 8),

                      // Call Button
                      Expanded(
                        flex: 3,
                        child: ElevatedButton.icon(
                          icon: const Icon(Icons.phone_in_talk_rounded, size: 18),
                          label: const Text(
                            'Call',
                            style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13),
                          ),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFF10B981),
                            foregroundColor: Colors.white,
                            elevation: 0,
                            padding: const EdgeInsets.symmetric(vertical: 13),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(14),
                            ),
                          ),
                          onPressed: phone.isNotEmpty
                              ? () => RiderQuickActions.callContact(context, phone)
                              : null,
                        ),
                      ),
                      const SizedBox(width: 8),

                      // SMS Button
                      IconButton.filled(
                        icon: const Icon(Icons.sms_rounded, size: 19),
                        style: IconButton.styleFrom(
                          backgroundColor: const Color(0xFFF1F5F9),
                          foregroundColor: const Color(0xFF334155),
                          padding: const EdgeInsets.all(12),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(14),
                            side: const BorderSide(color: Color(0xFFCBD5E1)),
                          ),
                        ),
                        onPressed: phone.isNotEmpty
                            ? () => RiderQuickActions.sendDeliverySms(
                                  context,
                                  rawPhone: phone,
                                  recipientName: custName,
                                  orderId: '${delivery['order_id'] ?? ''}',
                                  isCod: isCod,
                                  codAmount: codAmount,
                                )
                            : null,
                      ),
                    ],
                  ),

                  const SizedBox(height: 12),

                  // Giant Touch Target Primary Action: [ Complete Delivery (Photo & OTP) ] or [ Start Delivery ]
                  if (isOutForDelivery) ...[
                    SizedBox(
                      width: double.infinity,
                      height: 52,
                      child: ElevatedButton.icon(
                        icon: const Icon(Icons.camera_alt_rounded, size: 22),
                        label: const Text(
                          'COMPLETE DELIVERY (Photo & OTP)',
                          style: TextStyle(
                            fontWeight: FontWeight.w900,
                            fontSize: 15,
                            letterSpacing: 0.5,
                          ),
                        ),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFF0F766E),
                          foregroundColor: Colors.white,
                          elevation: 3,
                          shadowColor: const Color(0xFF0F766E).withValues(alpha: 0.35),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(16),
                          ),
                        ),
                        onPressed: canAttempt ? () => markDelivered(delivery) : null,
                      ),
                    ),
                  ] else if (isAssigned) ...[
                    SizedBox(
                      width: double.infinity,
                      height: 52,
                      child: ElevatedButton.icon(
                        icon: const Icon(Icons.play_arrow_rounded, size: 24),
                        label: const Text(
                          'START THIS TRIP',
                          style: TextStyle(
                            fontWeight: FontWeight.w900,
                            fontSize: 15,
                            letterSpacing: 0.5,
                          ),
                        ),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFFD97706),
                          foregroundColor: Colors.white,
                          elevation: 3,
                          shadowColor: const Color(0xFFD97706).withValues(alpha: 0.35),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(16),
                          ),
                        ),
                        onPressed: actionParcelId != null ? null : () => startDelivery(delivery),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _scanHeroCard(BuildContext context) {
    return Material(
      color: Colors.transparent,
      borderRadius: BorderRadius.circular(16),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => ParcelScannerPage(session: riderSession),
          ),
        ).then((_) {
          if (mounted) refresh();
        }),
        child: Ink(
          padding: const EdgeInsets.all(16),
          decoration: const BoxDecoration(gradient: RiderColors.headerGradient),
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: RiderColors.amber,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(
                  Icons.qr_code_scanner,
                  size: 22,
                  color: RiderColors.tealDark,
                ),
              ),
              const SizedBox(width: 12),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Scan waybill',
                      style: TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w700),
                    ),
                    SizedBox(height: 4),
                    Text(
                      'For pickups and deliveries',
                      style: TextStyle(color: Colors.white70, fontSize: 12, height: 1.35),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              const Icon(Icons.arrow_forward_rounded, color: Colors.white, size: 20),
            ],
          ),
        ),
      ),
    );
  }
  Widget _codCounterHeroCard(BuildContext context) {
    return Material(
      color: Colors.transparent,
      borderRadius: BorderRadius.circular(16),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => CodAndEarningsTrackerPage(
              session: riderSession,
              initialTabIndex: 0,
            ),
          ),
        ),
        child: Ink(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            gradient: RiderColors.cardGradient,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: RiderColors.portalBorder),
          ),
          child: const Row(
            children: [
              Icon(Icons.calculate_outlined, size: 24, color: RiderColors.teal),
              SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'COD cash counter',
                      style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
                    ),
                    SizedBox(height: 4),
                    Text(
                      'Count cash before remitting',
                      style: TextStyle(fontSize: 12, color: RiderColors.textSecondary),
                    ),
                  ],
                ),
              ),
              SizedBox(width: 12),
              Icon(Icons.chevron_right_rounded, color: RiderColors.teal, size: 20),
            ],
          ),
        ),
      ),
    );
  }
  Widget _header(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: Container(
        width: double.infinity,
        clipBehavior: Clip.antiAlias,
        decoration: const BoxDecoration(
          gradient: RiderColors.headerGradient,
          borderRadius: BorderRadius.only(
            bottomLeft: Radius.circular(24),
            bottomRight: Radius.circular(24),
          ),
        ),
        padding: EdgeInsets.fromLTRB(
          20,
          MediaQuery.of(context).padding.top + 10,
          20,
          20,
        ),
        child: Stack(
          clipBehavior: Clip.none,
          children: [
            Positioned(
              top: -100,
              right: -80,
              child: Container(
                width: 200,
                height: 200,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: RiderColors.amber.withValues(alpha: 0.08),
                ),
              ),
            ),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    if (widget.parcelId != null) ...[
                      const BackButton(color: Colors.white),
                      const SizedBox(width: 4),
                    ],
                    Expanded(
                      child: Text(
                        widget.parcelId == null
                            ? 'Deliveries'
                            : 'Delivery details',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 24,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                    if (widget.parcelId == null) ...[
                      const SizedBox(width: 12),
                      InkWell(
                        onTap: () => Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) =>
                                RiderNotificationsPage(session: riderSession),
                          ),
                        ),
                        borderRadius: BorderRadius.circular(14),
                        child: Container(
                          padding: const EdgeInsets.all(8),
                          decoration: BoxDecoration(
                            color: Colors.white.withValues(alpha: 0.16),
                            borderRadius: BorderRadius.circular(14),
                            border: Border.all(
                              color: Colors.white.withValues(alpha: 0.25),
                            ),
                          ),
                          child: const Icon(
                            Icons.notifications_outlined,
                            color: Colors.white,
                            size: 20,
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
                if (widget.parcelId == null) ...[
                  const SizedBox(height: 16),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 16,
                          vertical: 8,
                        ),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFBBF24),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: Text(
                          'My deliveries · ${deliveries.length}',
                          style: const TextStyle(
                            color: Color(0xFF78350F),
                            fontWeight: FontWeight.w800,
                            fontSize: 13,
                          ),
                        ),
                      ),
                      InkWell(
                        borderRadius: BorderRadius.circular(20),
                        onTap: () => Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) =>
                                PickupDashboardPage(session: riderSession),
                          ),
                        ),
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 16,
                            vertical: 8,
                          ),
                          decoration: BoxDecoration(
                            color: const Color(0xFF0F5A56),
                            borderRadius: BorderRadius.circular(20),
                          ),
                          child: const Text(
                            'Available pickups',
                            style: TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.w700,
                              fontSize: 13,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _availablePickupsSection() => Card(
    child: Padding(
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Pickup requests',
            style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 8),
          const Text('View current offers and your assigned pickups.'),
          const SizedBox(height: 12),
          FilledButton(
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => PickupDashboardPage(session: riderSession),
              ),
            ),
            child: const Text('View pickup jobs'),
          ),
        ],
      ),
    ),
  );

  Widget _statusMessage(String text) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xffffe6e2),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(text, style: const TextStyle(color: Color(0xff9b3125))),
    );
  }

  Widget _successMessage(String text) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xffdff4e9),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(text, style: const TextStyle(color: Color(0xff176b43))),
    );
  }
}
