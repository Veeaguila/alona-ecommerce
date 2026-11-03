import '../widgets/delivery_otp_dialog.dart';
import '../widgets/rider_presence_control.dart';

import 'dart:convert';

import 'package:flutter/material.dart';

import '../widgets/rider_content.dart';

import 'package:image_picker/image_picker.dart';

import '../services/api_error.dart';

import '../services/api_config.dart';
import '../services/parcel_address.dart';
import '../services/rider_session.dart';
import '../services/rider_session_guard.dart';
import '../widgets/failure_reason_dialog.dart';
import 'login_page.dart';
import 'rider_portal_page.dart';
import 'proof_capture_page.dart';
import 'rider_returns_page.dart';
import 'parcel_scanner_page.dart';
import 'delivery_success_page.dart';
import '../theme/rider_theme.dart';
import '../widgets/rider_design_widgets.dart';
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
      final otp = await showDeliveryOtpDialog(
        context,
        current['order_id'].toString(),
      );
      if (otp == null || !mounted) return;
      if (await updateDelivery(current, 'deliver', body: {'otp': otp}) &&
          mounted) {
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
        color: Colors.white,
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
              const SizedBox(height: 16),
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
    return Scaffold(
      backgroundColor: RiderColors.background,
      body: Column(
        children: [
          _header(context),
          Expanded(
            child: RefreshIndicator(
              onRefresh: refresh,
              child: ListView(
                controller: scroll,
                padding: riderPagePadding(context),
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
                    const RiderMapIllustration(),
                  ],
                  if (widget.parcelId == null) ...[
                    const SizedBox(height: 16),
                    RiderNotificationBanner(
                      title: 'Delivery notifications',
                      subtitle: 'Open your saved delivery updates',
                      onTap: () => Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) =>
                              RiderNotificationsPage(session: riderSession),
                        ),
                      ),
                    ),
                  ],
                  if (loading) ...[
                    const SizedBox(height: 40),
                    const Center(child: CircularProgressIndicator()),
                  ] else ...[
                    const SizedBox(height: 24),
                    if (deliveries.isEmpty)
                      Text(
                        widget.parcelId == null
                            ? 'No deliveries assigned right now.'
                            : 'This parcel is no longer in your active assignments. Refresh or check Delivery History.',
                        style: TextStyle(color: Colors.grey.shade700),
                      )
                    else ...[
                      const Text(
                        'To deliver',
                        style: TextStyle(
                          fontSize: 20,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 14),
                      ...deliveries.map(deliveryCard),
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
                color: RiderColors.amber.withValues(alpha: 0.28),
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
                          ? 'Items for delivery'
                          : 'Delivery details',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 26,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  IconButton(
                    tooltip: 'Refresh',
                    color: Colors.white,
                    onPressed:
                        loading ||
                            actionParcelId != null ||
                            proofFlow ||
                            loggingOut
                        ? null
                        : refresh,
                    icon: const Icon(Icons.refresh),
                  ),
                  if (widget.parcelId == null)
                    InkWell(
                      borderRadius: BorderRadius.circular(16),
                      onTap: () =>
                          Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (_) =>
                                  ParcelScannerPage(session: riderSession),
                            ),
                          ).then((_) {
                            if (mounted) refresh();
                          }),
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 16,
                          vertical: 10,
                        ),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFBBF24),
                          borderRadius: BorderRadius.circular(16),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: const [
                            Icon(
                              Icons.crop_free,
                              size: 20,
                              color: Color(0xFF1E293B),
                            ),
                            SizedBox(width: 6),
                            Text(
                              'Scan',
                              style: TextStyle(
                                fontWeight: FontWeight.w800,
                                fontSize: 15,
                                color: Color(0xFF1E293B),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  IconButton(
                    iconSize: 20,
                    onPressed: loggingOut ? null : logout,
                    icon: Icon(
                      Icons.logout,
                      color: Colors.white.withValues(alpha: 0.15),
                    ),
                    tooltip: 'Sign out',
                  ),
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
