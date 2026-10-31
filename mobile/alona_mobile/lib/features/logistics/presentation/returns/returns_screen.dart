import 'package:flutter/material.dart';
import 'package:alona_mobile/features/logistics/presentation/workflow_widgets.dart';
import 'package:alona_mobile/features/logistics/services/logistics_service.dart';

class ReturnsScreen extends StatefulWidget {
  const ReturnsScreen({super.key});
  @override
  State<ReturnsScreen> createState() => _ReturnsScreenState();
}

class _ReturnsScreenState extends State<ReturnsScreen> {
  bool _busy = true;
  int _page = 1, _lastPage = 1;
  String? _message;
  List<Map<String, dynamic>> _rows = [];
  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _busy = true;
      _rows = [];
    });
    try {
      final data = await LogisticsService.getData(
        'returns',
        query: {'page': _page},
      );
      if (mounted) {
        setState(() {
          _rows = LogisticsService.rows(data['data']);
          _lastPage = data['last_page'];
        });
      }
    } catch (error) {
      if (mounted) setState(() => _message = error.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _initiate(Map<String, dynamic> row) async {
    if (_busy || row['parcel']?['assigned_rider_id'] == null) return;
    setState(() => _busy = true);
    try {
      final reason = await requestText(
        context,
        title: 'Actual reason for returning this parcel',
        maxLength: 2000,
      );
      if (reason == null) return;
      final result = await LogisticsService.act(
        'returns/${row['id']}/initiate',
        body: {
          'rider_id': row['parcel']['assigned_rider_id'],
          'reason': reason,
        },
      );
      if (mounted) {
        setState(() => _message = result['message']);
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
      title: const Text('Return to Seller'),
      actions: [
        IconButton(
          onPressed: _busy ? null : _load,
          icon: const Icon(Icons.refresh),
        ),
      ],
    ),
    body: Column(
      children: [
        const Padding(
          padding: EdgeInsets.all(16),
          child: Text(
            'Use the current verified custodian. A different Rider requires supervised handover. Only Seller receipt confirmation restocks inventory.',
          ),
        ),
        if (_busy) const LinearProgressIndicator(),
        if (_message != null)
          Padding(padding: const EdgeInsets.all(12), child: Text(_message!)),
        Expanded(
          child: ListView(
            padding: const EdgeInsets.all(12),
            children: [
              if (_rows.isEmpty && !_busy)
                const Text('No return records loaded.'),
              for (final row in _rows)
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          '${row['parcel']?['order_id']} · ${row['status']}',
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                        Text(
                          'Current custodian: ${row['parcel']?['assigned_rider']?['name'] ?? 'Needs Logistics review'}',
                        ),
                        for (final attempt
                            in row['parcel']?['delivery_attempts'] ?? [])
                          Text(
                            'Attempt ${attempt['attempt_number']}: ${attempt['reason']}',
                          ),
                        if (row['reason'] != null) Text(row['reason']),
                        if (row['status'] == 'eligible')
                          FilledButton(
                            onPressed:
                                _busy ||
                                    row['parcel']?['assigned_rider_id'] == null
                                ? null
                                : () => _initiate(row),
                            child: const Text('Initiate physical return'),
                          ),
                        for (final field in [
                          'picked_up_at',
                          'handed_over_at',
                          'seller_received_at',
                          'restocked_at',
                        ])
                          if (row[field] != null)
                            Text(
                              '${field.replaceAll('_', ' ')}: ${row[field]}',
                            ),
                      ],
                    ),
                  ),
                ),
            ],
          ),
        ),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            IconButton(
              onPressed: _busy || _page == 1
                  ? null
                  : () {
                      _page--;
                      _load();
                    },
              icon: const Icon(Icons.chevron_left),
            ),
            Text('Page $_page of $_lastPage'),
            IconButton(
              onPressed: _busy || _page >= _lastPage
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
    ),
  );
}
