import 'package:flutter/material.dart';

import '../services/rider_job_pay_api.dart';
import '../services/rider_session.dart';
import '../services/rider_session_guard.dart';
import '../theme/rider_theme.dart';
import 'profit_dashboard_page.dart';

class JobEarningsPage extends StatefulWidget {
  const JobEarningsPage({super.key, this.session, this.active = true});
  final RiderSession? session;
  final bool active;
  @override
  State<JobEarningsPage> createState() => _JobEarningsPageState();
}

class _JobEarningsPageState extends State<JobEarningsPage>
    with WidgetsBindingObserver, RiderSessionGuard<JobEarningsPage> {
  @override
  late final riderSession = widget.session ?? RiderSession();
  late final api = RiderJobPayApi(riderSession);
  JobPayPage? data;
  bool loading = true, busy = false, dialogOpen = false;
  String? error, notice;
  int revision = 0;
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    if (widget.active) load();
  }

  @override
  void didUpdateWidget(covariant JobEarningsPage oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.active && !oldWidget.active && !busy) load();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && widget.active && !busy) load();
  }

  Future<void> load({int jobs = 1, int payouts = 1}) async {
    if (busy) return;
    final current = ++revision;
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final result = await sessionAction(
        () => api.load(earningsPage: jobs, payoutsPage: payouts),
      );
      if (mounted && current == revision) setState(() => data = result);
    } catch (failure) {
      if (mounted && current == revision) {
        setState(() {
          data = null;
          error = failure is SessionFailure
              ? failure.message
              : 'Could not load earnings. Refresh to retry.';
        });
      }
    } finally {
      if (mounted && current == revision) setState(() => loading = false);
    }
  }

  Future<void> run(Future<void> Function() action, String success) async {
    if (busy || loading) return;
    setState(() {
      busy = true;
      error = null;
      notice = null;
    });
    String? failed;
    try {
      await sessionAction(action);
      if (mounted) setState(() => notice = success);
    } catch (failure) {
      failed = failure is SessionFailure ? failure.message : failure.toString();
    } finally {
      if (mounted) setState(() => busy = false);
    }
    if (mounted) await load();
    if (mounted && failed != null) setState(() => error = failed);
  }

  Future<void> receiving() async {
    if (busy || loading || dialogOpen) return;
    dialogOpen = true;
    final result = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => const _ReceivingDialog(),
    );
    dialogOpen = false;
    if (result != null && mounted) {
      await run(() => api.destination(result), 'Receiving instructions saved.');
    }
  }

  Future<void> acknowledge(Map row) async {
    if (busy || loading || dialogOpen) return;
    dialogOpen = true;
    final yes = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Confirm actual receipt'),
        content: Text(
          'Did you receive PHP ${row['amount']} for payout #${row['id']}? Confirm only after checking the actual payment.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Not yet'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('I received it'),
          ),
        ],
      ),
    );
    dialogOpen = false;
    if (yes == true && mounted) {
      await run(
        () => api.acknowledge(row['id'] as int),
        'Payment receipt confirmed.',
      );
    }
  }

  Widget pager(Map<String, dynamic> page, bool jobs) => Row(
    mainAxisAlignment: MainAxisAlignment.spaceBetween,
    children: [
      TextButton(
        onPressed: busy || loading || page['current_page'] <= 1
            ? null
            : () => load(
                jobs: jobs
                    ? page['current_page'] - 1
                    : data!.earnings['current_page'],
                payouts: jobs
                    ? data!.payouts['current_page']
                    : page['current_page'] - 1,
              ),
        child: const Text('Previous'),
      ),
      Text('${page['current_page']} / ${page['last_page']}'),
      TextButton(
        onPressed: busy || loading || page['current_page'] >= page['last_page']
            ? null
            : () => load(
                jobs: jobs
                    ? page['current_page'] + 1
                    : data!.earnings['current_page'],
                payouts: jobs
                    ? data!.payouts['current_page']
                    : page['current_page'] + 1,
              ),
        child: const Text('Next'),
      ),
    ],
  );
  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: RiderColors.background,
    appBar: AppBar(
      title: const Text('My Earnings'),
      actions: [
        IconButton(
          tooltip: 'Refresh earnings',
          onPressed: busy ? null : () => load(),
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
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Text(
                      error!,
                      style: const TextStyle(color: RiderColors.danger),
                    ),
                  ),
                ),
              if (notice != null)
                Padding(
                  padding: const EdgeInsets.all(12),
                  child: Text(notice!),
                ),
              if (data != null) ...[
                Container(
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    color: RiderColors.teal,
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Earned, unpaid',
                        style: TextStyle(color: Colors.white),
                      ),
                      Text(
                        'PHP ${data!.summary['unpaid']}',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 30,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 12),
                      Text(
                        'Processing: PHP ${data!.summary['processing']}\nPaid: PHP ${data!.summary['paid']}',
                        style: const TextStyle(color: Colors.white),
                      ),
                    ],
                  ),
                ),
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 12),
                  child: Text(
                    'One pickup and delivery rate per Seller shipment. Transfer pay covers a whole received manifest. COD cash is separate from your earnings.',
                  ),
                ),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Receiving instructions',
                          style: TextStyle(fontWeight: FontWeight.bold),
                        ),
                        for (final destination in data!.destinations)
                          Padding(
                            padding: const EdgeInsets.only(top: 6),
                            child: Text(destination['label'] as String),
                          ),
                        TextButton(
                          onPressed: busy ? null : receiving,
                          child: const Text('Add receiving method'),
                        ),
                      ],
                    ),
                  ),
                ),
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 12),
                  child: Text(
                    'Payments',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                  ),
                ),
                if ((data!.payouts['data'] as List).isEmpty)
                  const Text('No grouped payments recorded yet.'),
                for (final row in data!.payouts['data'] as List)
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'PHP ${row['amount']} · ${row['items_count']} jobs',
                            style: const TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          Text(
                            '${row['destination']['label']}\n${row['status'] == 'prepared' ? 'Processing; not yet recorded as paid' : row['status']}',
                          ),
                          if (row['reference'] != null)
                            Text('Reference: ${row['reference']}'),
                          if (row['paid_at'] != null)
                            Text(
                              'Paid: ${DateTime.parse(row['paid_at'] as String).toLocal()}',
                            ),
                          if (row['status'] == 'paid')
                            row['acknowledged_at'] == null
                                ? FilledButton(
                                    onPressed: busy
                                        ? null
                                        : () => acknowledge(
                                            Map<String, dynamic>.from(row),
                                          ),
                                    child: const Text('Confirm received'),
                                  )
                                : const Text('You confirmed receipt'),
                        ],
                      ),
                    ),
                  ),
                pager(data!.payouts, false),
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 12),
                  child: Text(
                    'Job history',
                    style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
                  ),
                ),
                if ((data!.earnings['data'] as List).isEmpty)
                  const Text(
                    'No job snapshots yet. Earlier work is not assigned a guessed rate.',
                  ),
                for (final row in data!.earnings['data'] as List)
                  Card(
                    child: ListTile(
                      title: Text('${row['reference']} · ${row['leg']}'),
                      subtitle: Text(
                        '${row['status']}${row['batch_id'] == null ? '' : ' · payout #${row['batch_id']}'}',
                      ),
                      trailing: Text('PHP ${row['amount']}'),
                    ),
                  ),
                pager(data!.earnings, true),
              ],
              TextButton(
                onPressed: busy
                    ? null
                    : () => Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) =>
                              ProfitDashboardPage(session: riderSession),
                        ),
                      ),
                child: const Text('Historical Seller-funded earnings'),
              ),
            ],
          ),
  );
}

class _ReceivingDialog extends StatefulWidget {
  const _ReceivingDialog();
  @override
  State<_ReceivingDialog> createState() => _ReceivingDialogState();
}

class _ReceivingDialogState extends State<_ReceivingDialog> {
  final name = TextEditingController(),
      number = TextEditingController(),
      bank = TextEditingController();
  String method = 'gcash';
  String? error;
  @override
  void dispose() {
    name.dispose();
    number.dispose();
    bank.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text('Receiving method'),
    content: SingleChildScrollView(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (error != null)
            Text(error!, style: const TextStyle(color: RiderColors.danger)),
          DropdownButtonFormField<String>(
            initialValue: method,
            decoration: const InputDecoration(labelText: 'Method'),
            items: const [
              DropdownMenuItem(value: 'gcash', child: Text('GCash')),
              DropdownMenuItem(value: 'bank', child: Text('Bank transfer')),
              DropdownMenuItem(value: 'cash', child: Text('Cash')),
            ],
            onChanged: (value) => setState(() => method = value!),
          ),
          TextField(
            controller: name,
            maxLength: 60,
            decoration: const InputDecoration(
              labelText: 'Receiving account / recipient name',
            ),
          ),
          if (method != 'cash')
            TextField(
              controller: number,
              maxLength: 40,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(
                labelText: 'Receiving account number',
              ),
            ),
          if (method == 'bank')
            TextField(
              controller: bank,
              maxLength: 80,
              decoration: const InputDecoration(labelText: 'Bank name'),
            ),
        ],
      ),
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: const Text('Cancel'),
      ),
      FilledButton(
        onPressed: () {
          if (name.text.trim().isEmpty ||
              (method != 'cash' && number.text.trim().isEmpty) ||
              (method == 'bank' && bank.text.trim().isEmpty)) {
            setState(
              () => error = 'Complete the receiving name and account details.',
            );
            return;
          }
          if ((method == 'gcash' &&
                  !RegExp(r'^09[0-9]{9}$').hasMatch(number.text.trim())) ||
              (method == 'bank' &&
                  !RegExp(r'^[A-Za-z0-9 -]{6,40}$')
                      .hasMatch(number.text.trim()))) {
            setState(
              () => error = 'Check the receiving account number. GCash uses 11 digits starting with 09.',
            );
            return;
          }
          Navigator.pop(context, {
            'method': method,
            'account_name': name.text.trim(),
            'account_number': number.text.trim(),
            'institution': bank.text.trim(),
          });
        },
        child: const Text('Save instructions'),
      ),
    ],
  );
}
