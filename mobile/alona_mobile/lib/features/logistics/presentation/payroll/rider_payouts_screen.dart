import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import 'package:alona_mobile/core/network/api_client.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/shared/widgets/alona_button.dart';
import 'package:alona_mobile/features/logistics/presentation/workflow_widgets.dart';

class RiderPayoutsScreen extends StatefulWidget {
  const RiderPayoutsScreen({super.key, this.pickPaymentProof});
  final Future<XFile?> Function()? pickPaymentProof;
  @override
  State<RiderPayoutsScreen> createState() => _RiderPayoutsScreenState();
}

class _RiderPayoutsScreenState extends State<RiderPayoutsScreen> {
  final pickup = TextEditingController(),
      delivery = TextEditingController(),
      transfer = TextEditingController();
  Map<String, dynamic>? data, review;
  int? riderId, destinationId;
  DateTimeRange? range;
  bool loading = true,
      busy = false,
      enabled = false,
      reviewed = false,
      fresh = false;
  String? error, notice;
  String reviewKey = newRequestKey();
  int page = 1, revision = 0;
  static const base = 'api/sorting-center/payroll';
  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void dispose() {
    pickup.dispose();
    delivery.dispose();
    transfer.dispose();
    super.dispose();
  }

  String money(dynamic value) {
    if (value is! String || !RegExp(r'^(0|[1-9]\d*)\.\d{2}$').hasMatch(value)) {
      throw const FormatException('Invalid amount');
    }
    return 'PHP $value';
  }

  Future<void> load() async {
    final current = ++revision;
    if (mounted) {
      setState(() {
        loading = true;
        fresh = false;
      });
    }
    try {
      final response = await ApiClient.get('$base?payouts_page=$page');
      final next = Map<String, dynamic>.from(response['data'] as Map);
      for (final key in ['unpaid', 'processing', 'paid']) {
        money(next['summary'][key]);
      }
      if (!mounted || current != revision) return;
      setState(() {
        data = next;
        fresh = true;
        if (!(next['riders'] as List).any((rider) => rider['id'] == riderId)) {
          riderId = null;
          review = null;
        }
        enabled = next['settings']['enabled'] == true;
        pickup.text = next['settings']['pickup'] as String? ?? '';
        delivery.text = next['settings']['delivery'] as String? ?? '';
        transfer.text = next['settings']['transfer'] as String? ?? '';
      });
    } catch (failure) {
      if (mounted && current == revision) {
        setState(() => error = failure.toString());
      }
    } finally {
      if (mounted && current == revision) setState(() => loading = false);
    }
  }

  Future<void> run(
    Future<void> Function() action,
    String success, {
    bool reload = true,
  }) async {
    if (busy || loading) return;
    setState(() {
      busy = true;
      error = null;
      notice = null;
    });
    try {
      await action();
      if (mounted) setState(() => notice = success.isEmpty ? null : success);
    } catch (failure) {
      if (mounted) {
        setState(() {
          error = failure.toString();
          fresh = false;
        });
      }
    } finally {
      if (mounted) setState(() => busy = false);
    }
    if (mounted && reload) await load();
  }

  Future<void> saveRates() => run(() async {
    await ApiClient.put(
      '$base/settings',
      body: {
        'enabled': enabled,
        'revision': data!['settings']['revision'],
        'pickup': pickup.text.trim(),
        'delivery': delivery.text.trim(),
        'transfer': transfer.text.trim().isEmpty ? null : transfer.text.trim(),
      },
    );
  }, 'Approved rates saved for future assignments.');
  Future<void> loadReview() => run(
    () async {
      final dates = range == null
          ? ''
          : '?from=${DateFormat('yyyy-MM-dd').format(range!.start)}&to=${DateFormat('yyyy-MM-dd').format(range!.end)}';
      final result = await ApiClient.get('$base/riders/$riderId/review$dates');
      if (mounted) {
        setState(() {
          review = Map<String, dynamic>.from(result['data'] as Map);
          destinationId = null;
          reviewed = false;
          reviewKey = newRequestKey();
        });
      }
    },
    '',
    reload: false,
  );
  Future<void> prepare() => run(
    () async {
      await ApiClient.post(
        '$base/payouts',
        body: {
          'rider_id': riderId,
          'destination_id': destinationId,
          'earning_ids': (review!['jobs'] as List).map((j) => j['id']).toList(),
          'expected_amount': review!['amount'],
          'request_key': reviewKey,
          'reviewed': reviewed,
        },
      );
      if (mounted) setState(() => review = null);
    },
    'Payout prepared. Review the receiving instructions before actual payment.',
  );
  Future<void> openPayment(Map<String, dynamic> batch) async {
    if (busy || loading || !fresh) return;
    setState(() => busy = true);
    try {
      final response = await ApiClient.get('$base/payouts/${batch['id']}');
      if (!mounted) return;
      await showDialog<bool>(
        context: context,
        barrierDismissible: false,
        builder: (_) => _PaymentDialog(
          batch: Map<String, dynamic>.from(response['data'] as Map),
          pickProof: widget.pickPaymentProof,
        ),
      );
    } catch (failure) {
      if (mounted) setState(() => error = failure.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
    if (mounted) await load();
  }

  Future<void> cancel(Map<String, dynamic> batch) async {
    if (busy || loading || !fresh) return;
    final reason = await requestText(
      context,
      title: 'Reason to cancel this unsent payout',
      minLength: 3,
      maxLength: 500,
    );
    if (!mounted || reason == null) return;
    final yes = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Was this payout never sent?'),
        content: const Text(
          'Cancel only if no money was sent. An actual payment must remain in the payment history.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Keep payout'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Not sent; cancel'),
          ),
        ],
      ),
    );
    if (yes == true && mounted) {
      await run(() async {
        await ApiClient.post(
          '$base/payouts/${batch['id']}/cancel',
          body: {'reason': reason, 'not_sent': true},
        );
      }, 'Unsent payout cancelled; its earned jobs are available again.');
    }
  }

  Widget card(List<Widget> children) => Card(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: children,
      ),
    ),
  );
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Rider payouts'),
      actions: [
        IconButton(
          tooltip: 'Refresh payouts',
          onPressed: busy ? null : load,
          icon: const Icon(Icons.refresh),
        ),
      ],
    ),
    body: loading
        ? const Center(child: CircularProgressIndicator())
        : ListView(
            padding: const EdgeInsets.all(16),
            children: [
              if (error != null)
                Padding(
                  padding: const EdgeInsets.all(12),
                  child: Text(
                    error!,
                    style: const TextStyle(color: AppColors.error),
                  ),
                ),
              if (notice != null)
                Padding(
                  padding: const EdgeInsets.all(12),
                  child: Text(notice!),
                ),
              if (data != null) ...[
                card([
                  const Text(
                    'Job earnings',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                  ),
                  for (final item in [
                    ['unpaid', 'Earned, unpaid'],
                    ['processing', 'Processing'],
                    ['paid', 'Recorded payments'],
                  ])
                    Padding(
                      padding: const EdgeInsets.only(top: 8),
                      child: Text(
                        '${item[1]}: ${money(data!['summary'][item[0]])}',
                      ),
                    ),
                  const SizedBox(height: 8),
                  const Text(
                    'COD cash and historical Seller-funded earnings stay separate. These totals are not company profit or a wallet balance.',
                  ),
                ]),
                Card(
                  child: ExpansionTile(
                    title: const Text('Approved job rates'),
                    initiallyExpanded: data!['settings']['enabled'] != true,
                    childrenPadding: const EdgeInsets.all(16),
                    children: [
                      const Text(
                        'One pickup and delivery rate per Seller shipment, even with several parcels. Rates apply to future assignments. Your company must fund the approved rates.',
                      ),
                      SwitchListTile(
                        title: const Text('Enable future job earnings'),
                        value: enabled,
                        onChanged: busy
                            ? null
                            : (value) => setState(() => enabled = value),
                      ),
                      TextField(
                        controller: pickup,
                        enabled: !busy,
                        keyboardType: const TextInputType.numberWithOptions(
                          decimal: true,
                        ),
                        decoration: const InputDecoration(
                          labelText: 'Pickup per shipment (PHP)',
                        ),
                      ),
                      TextField(
                        controller: delivery,
                        enabled: !busy,
                        keyboardType: const TextInputType.numberWithOptions(
                          decimal: true,
                        ),
                        decoration: const InputDecoration(
                          labelText: 'Delivery per shipment (PHP)',
                        ),
                      ),
                      TextField(
                        controller: transfer,
                        enabled: !busy,
                        keyboardType: const TextInputType.numberWithOptions(
                          decimal: true,
                        ),
                        decoration: const InputDecoration(
                          labelText:
                              'Transfer per complete trip (optional PHP)',
                        ),
                      ),
                      const SizedBox(height: 12),
                      AlonaButton(
                        text: 'Save approved rates',
                        onPressed: busy || !fresh ? null : saveRates,
                      ),
                    ],
                  ),
                ),
                card([
                  const Text(
                    'Review unpaid jobs',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                  ),
                  DropdownButtonFormField<int>(
                    key: ValueKey('rider-$riderId'),
                    initialValue: riderId,
                    isExpanded: true,
                    decoration: const InputDecoration(labelText: 'Rider'),
                    items: [
                      for (final rider in data!['riders'] as List)
                        DropdownMenuItem(
                          value: rider['id'] as int,
                          child: Text(rider['name'] as String),
                        ),
                    ],
                    onChanged: busy
                        ? null
                        : (value) => setState(() {
                            riderId = value;
                            review = null;
                          }),
                  ),
                  TextButton(
                    onPressed: busy
                        ? null
                        : () async {
                            final result = await showDateRangePicker(
                              context: context,
                              firstDate: DateTime(2020),
                              lastDate: DateTime.now(),
                              initialDateRange: range,
                            );
                            if (mounted && result != null) {
                              setState(() {
                                range = result;
                                review = null;
                              });
                            }
                          },
                    child: Text(
                      range == null
                          ? 'Optional date range (Manila)'
                          : '${DateFormat.yMd().format(range!.start)} – ${DateFormat.yMd().format(range!.end)}',
                    ),
                  ),
                  if (range != null)
                    TextButton(
                      onPressed: busy
                          ? null
                          : () => setState(() {
                              range = null;
                              review = null;
                            }),
                      child: const Text('All dates'),
                    ),
                  AlonaButton(
                    text: 'Review unpaid jobs',
                    onPressed: busy || !fresh || riderId == null
                        ? null
                        : loadReview,
                  ),
                  if (review != null) ...[
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      child: Text(
                        '${(review!['jobs'] as List).length} jobs · ${money(review!['amount'])}',
                        style: const TextStyle(fontWeight: FontWeight.bold),
                      ),
                    ),
                    for (final job in review!['jobs'] as List)
                      ListTile(
                        contentPadding: EdgeInsets.zero,
                        title: Text('${job['reference']} · ${job['leg']}'),
                        subtitle: Text(money(job['amount'])),
                      ),
                    if (review!['has_more'] == true)
                      const Text(
                        'This batch contains the oldest 500 matching jobs. The rest can be prepared afterward.',
                      ),
                    if ((review!['jobs'] as List).isEmpty)
                      const Text('No completed unpaid jobs match this review.')
                    else if ((review!['destinations'] as List).isEmpty)
                      const Text(
                        'Ask the Rider to save receiving instructions in My Earnings.',
                      )
                    else ...[
                      DropdownButtonFormField<int>(
                        key: ValueKey('destination-$reviewKey'),
                        initialValue: destinationId,
                        isExpanded: true,
                        decoration: const InputDecoration(
                          labelText: 'Rider-provided receiving method',
                        ),
                        items: [
                          for (final destination
                              in review!['destinations'] as List)
                            DropdownMenuItem(
                              value: destination['id'] as int,
                              child: Text(destination['label'] as String),
                            ),
                        ],
                        onChanged: busy
                            ? null
                            : (value) => setState(() => destinationId = value),
                      ),
                      CheckboxListTile(
                        contentPadding: EdgeInsets.zero,
                        value: reviewed,
                        title: const Text(
                          'I reviewed the jobs, total and receiving method.',
                        ),
                        onChanged: busy
                            ? null
                            : (value) =>
                                  setState(() => reviewed = value == true),
                      ),
                      AlonaButton(
                        text: 'Prepare grouped payout',
                        onPressed:
                            busy || !fresh || !reviewed || destinationId == null
                            ? null
                            : prepare,
                      ),
                    ],
                  ],
                ]),
                const Padding(
                  padding: EdgeInsets.all(12),
                  child: Text(
                    'Payout history',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                  ),
                ),
                for (final raw in data!['payouts']['data'] as List)
                  card([
                    Text(
                      '${money(raw['amount'])} · ${raw['items_count']} jobs',
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    Text(
                      '${raw['destination']['label']}\n${raw['status'] == 'prepared' ? 'Processing; not yet paid' : raw['status']}',
                    ),
                    if (raw['reference'] != null)
                      Text('Reference: ${raw['reference']}'),
                    if (raw['paid_at'] != null)
                      Text(
                        'Recorded ${DateTime.parse(raw['paid_at'] as String).toLocal()} · ${raw['acknowledged_at'] == null ? 'Awaiting Rider receipt confirmation' : 'Rider confirmed receipt'}',
                      ),
                    if (raw['status'] == 'prepared') ...[
                      AlonaButton(
                        text: 'Review & record actual payment',
                        onPressed: busy || !fresh
                            ? null
                            : () => openPayment(Map<String, dynamic>.from(raw)),
                      ),
                      TextButton(
                        onPressed: busy || !fresh
                            ? null
                            : () => cancel(Map<String, dynamic>.from(raw)),
                        child: const Text('Cancel only if never sent'),
                      ),
                    ],
                  ]),
                if ((data!['payouts']['data'] as List).isEmpty)
                  const Padding(
                    padding: EdgeInsets.all(12),
                    child: Text('No grouped payouts yet.'),
                  ),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    TextButton(
                      onPressed: busy || page <= 1
                          ? null
                          : () {
                              page--;
                              load();
                            },
                      child: const Text('Previous'),
                    ),
                    Text('Page $page of ${data!['payouts']['last_page']}'),
                    TextButton(
                      onPressed: busy || page >= data!['payouts']['last_page']
                          ? null
                          : () {
                              page++;
                              load();
                            },
                      child: const Text('Next'),
                    ),
                  ],
                ),
                const Padding(
                  padding: EdgeInsets.all(12),
                  child: Text(
                    'Create compensated transfer-trip manifests from Rider payouts in the web portal. Destination receipt uses the existing hub workspace and earns once after the full manifest arrives.',
                  ),
                ),
              ],
            ],
          ),
  );
}

class _PaymentDialog extends StatefulWidget {
  const _PaymentDialog({required this.batch, this.pickProof});
  final Map<String, dynamic> batch;
  final Future<XFile?> Function()? pickProof;
  @override
  State<_PaymentDialog> createState() => _PaymentDialogState();
}

class _PaymentDialogState extends State<_PaymentDialog> {
  final reference = TextEditingController();
  final paymentKey = newRequestKey();
  XFile? file;
  bool confirmed = false, busy = false, picking = false;
  String? error;
  @override
  void dispose() {
    reference.dispose();
    super.dispose();
  }

  Future<void> pay() async {
    if (busy ||
        picking ||
        !confirmed ||
        file == null ||
        reference.text.trim().isEmpty) {
      return;
    }
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final bytes = await file!.readAsBytes();
      if (!mounted) return;
      if (bytes.isEmpty || bytes.length > 5 * 1024 * 1024) {
        throw ApiException('Choose an image of at most 5 MB.');
      }
      await ApiClient.multipart(
        'api/sorting-center/payroll/payouts/${widget.batch['id']}/pay',
        method: 'POST',
        fields: {
          'expected_amount': widget.batch['amount'],
          'payment_key': paymentKey,
          'reference': reference.text.trim(),
          'actually_paid': '1',
          'paid_from_payroll': '1',
        },
        files: [
          http.MultipartFile.fromBytes('proof', bytes, filename: file!.name),
        ],
      );
      if (mounted) Navigator.pop(context, true);
    } catch (failure) {
      if (mounted) setState(() => error = failure.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> chooseProof() async {
    if (busy || picking) {
      return;
    }
    setState(() {
      picking = true;
      error = null;
    });
    try {
      final picked =
          await (widget.pickProof?.call() ??
              ImagePicker().pickImage(source: ImageSource.gallery));
      if (mounted && picked != null) {
        setState(() => file = picked);
      }
    } catch (_) {
      if (mounted) {
        setState(
          () => error = 'Could not open your photo library. Check permissions and try again.',
        );
      }
    } finally {
      if (mounted) {
        setState(() => picking = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) => PopScope(
    canPop: !busy && !picking,
    child: AlertDialog(
      title: Text('Actual payment: PHP ${widget.batch['amount']}'),
      content: SizedBox(
        width: 440,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '${widget.batch['destination']['method']} · ${widget.batch['instructions']['account_name']}\n${widget.batch['instructions']['institution'] ?? ''} ${widget.batch['instructions']['account_number'] ?? ''}',
              ),
              if (error != null)
                Text(error!, style: const TextStyle(color: AppColors.error)),
              TextField(
                controller: reference,
                enabled: !busy && !picking,
                maxLength: 120,
                decoration: const InputDecoration(
                  labelText: 'Actual payment / cash receipt reference',
                ),
              ),
              TextButton.icon(
                onPressed: busy || picking ? null : chooseProof,
                icon: const Icon(Icons.attach_file),
                label: Text(file?.name ?? 'Choose payment proof (image)'),
              ),
              CheckboxListTile(
                contentPadding: EdgeInsets.zero,
                value: confirmed,
                title: const Text(
                  'I actually paid this amount from company payroll funds without deducting COD cash.',
                ),
                onChanged: busy || picking
                    ? null
                    : (value) => setState(() => confirmed = value == true),
              ),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: busy || picking ? null : () => Navigator.pop(context),
          child: const Text('Close'),
        ),
        AlonaButton(
          text: 'Record actual payment',
          width: 220,
          isLoading: busy,
          onPressed: busy || picking || !confirmed || file == null ? null : pay,
        ),
      ],
    ),
  );
}
