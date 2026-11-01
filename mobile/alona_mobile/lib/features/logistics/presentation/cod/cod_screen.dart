import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import 'package:alona_mobile/core/network/api_client.dart';
import 'package:alona_mobile/features/logistics/presentation/workflow_widgets.dart';
import 'package:alona_mobile/features/logistics/services/logistics_service.dart';

class CodScreen extends StatefulWidget {
  const CodScreen({super.key});
  @override
  State<CodScreen> createState() => _CodScreenState();
}

class _CodScreenState extends State<CodScreen> {
  Map<String, dynamic>? _data;
  bool _busy = true;
  int _page = 1;
  DateTimeRange _range = DateTimeRange(
    start: DateTime.now().subtract(const Duration(days: 30)),
    end: DateTime.now(),
  );
  String? _message;
  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _busy = true;
      _data = null;
    });
    try {
      final data = await LogisticsService.getData(
        'cod',
        query: {
          'from': DateFormat('yyyy-MM-dd').format(_range.start),
          'to': DateFormat('yyyy-MM-dd').format(_range.end),
          'page': _page,
        },
      );
      if (mounted) setState(() => _data = data);
    } catch (error) {
      if (mounted) setState(() => _message = error.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _rangePicker() async {
    final range = await showDateRangePicker(
      context: context,
      firstDate: DateTime(2020),
      lastDate: DateTime.now(),
      initialDateRange: _range,
    );
    if (!mounted || range == null) return;
    _range = range;
    _page = 1;
    await _load();
  }

  Future<void> _receive(Map<String, dynamic> remittance) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      final amount = await requestText(
        context,
        title: 'Actual cash received (PHP)',
        minLength: 1,
        maxLength: 13,
      );
      if (amount == null) return;
      if (!RegExp(r'^[0-9]{1,10}(\.[0-9]{1,2})?$').hasMatch(amount)) {
        throw ApiException(
          'Enter the actual cash amount with up to two decimal places.',
        );
      }
      if (!mounted) return;
      final shortfall = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('Cash discrepancy'),
          content: const Text(
            'Is the actual receipt short of the submitted amount? Declare the shortfall explicitly for Admin review.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: const Text('Cancel'),
            ),
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('No shortfall'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Declare shortfall'),
            ),
          ],
        ),
      );
      if (shortfall == null || !mounted) return;
      String? reason;
      if (shortfall) {
        reason = await requestText(
          context,
          title: 'Actual shortfall reason',
          maxLength: 2000,
        );
        if (reason == null) return;
      }
      final photo = await ImagePicker().pickImage(
        source: ImageSource.gallery,
        maxWidth: 6000,
        maxHeight: 6000,
      );
      if (photo == null || !mounted) return;
      final bytes = await photo.readAsBytes();
      if (bytes.length > 20 * 1024 * 1024) {
        throw ApiException('Cash evidence must be at most 20 MB.');
      }
      if (!mounted) return;
      if (!await confirmMovement(
        context,
        'Confirm actual receipt of PHP $amount from ${remittance['rider']?['name'] ?? 'this Rider'}? This records cash receipt; it does not issue a payout.',
      )) {
        return;
      }
      final res = await ApiClient.multipart(
        'api/sorting-center/cod/remittances/${remittance['id']}/confirm',
        method: 'POST',
        fields: {
          'amount': amount,
          'declare_shortfall': shortfall ? '1' : '0',
          'reason': ?reason,
        },
        files: [
          http.MultipartFile.fromBytes('file', bytes, filename: photo.name),
        ],
      );
      if (mounted) {
        setState(() => _message = res['message']);
        await _load();
      }
    } catch (error) {
      if (mounted) setState(() => _message = error.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('COD cash receipt'),
      actions: [
        IconButton(
          onPressed: _busy ? null : _rangePicker,
          icon: const Icon(Icons.date_range),
        ),
        IconButton(
          onPressed: _busy ? null : _load,
          icon: const Icon(Icons.refresh),
        ),
      ],
    ),
    body: ListView(
      padding: const EdgeInsets.all(16),
      children: [
        const Text(
          'Delivery, cash collection, Logistics receipt, discrepancies and payouts remain separate records.',
        ),
        Text(
          '${DateFormat('yyyy-MM-dd').format(_range.start)} to ${DateFormat('yyyy-MM-dd').format(_range.end)}',
        ),
        if (_busy) const LinearProgressIndicator(),
        if (_message != null) Text(_message!),
        if (_data != null) ...[
          const Divider(),
          Text('Balances', style: Theme.of(context).textTheme.titleMedium),
          for (final row in _data!['balances'])
            ListTile(
              title: Text(row['name'] ?? 'Recorded Rider'),
              subtitle: Text(
                row.entries
                    .where((e) => !['name', 'rider_id'].contains(e.key))
                    .map((e) => '${e.key.replaceAll('_', ' ')}: ${e.value}')
                    .join('\n'),
              ),
            ),
          const Divider(),
          Text(
            'Pending remittances',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          for (final row in _data!['pending_remittances'])
            Card(
              child: ListTile(
                title: Text(
                  '${row['rider']?['name']} · PHP ${row['submitted_amount']}',
                ),
                subtitle: const Text(
                  'Verify the actual cash and attach receipt evidence.',
                ),
                trailing: IconButton(
                  onPressed: _busy
                      ? null
                      : () => _receive(Map<String, dynamic>.from(row)),
                  icon: const Icon(Icons.receipt_long),
                ),
              ),
            ),
          const Divider(),
          Text('Cash records', style: Theme.of(context).textTheme.titleMedium),
          for (final row in _data!['records'])
            Card(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Text(
                  row.entries
                      .map(
                        (e) =>
                            '${e.key.replaceAll('_', ' ')}: ${e.value ?? 'Not recorded'}',
                      )
                      .join('\n'),
                ),
              ),
            ),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              IconButton(
                onPressed: _busy || _page <= 1
                    ? null
                    : () {
                        _page--;
                        _load();
                      },
                icon: const Icon(Icons.chevron_left),
              ),
              Text('Page $_page of ${_data!['pagination']['last_page']}'),
              IconButton(
                onPressed: _busy || _page >= _data!['pagination']['last_page']
                    ? null
                    : () {
                        _page++;
                        _load();
                      },
                icon: const Icon(Icons.chevron_right),
              ),
            ],
          ),
        ],
      ],
    ),
  );
}
