import 'package:flutter/material.dart';
import 'package:alona_mobile/core/network/api_client.dart';
import 'package:alona_mobile/features/logistics/services/logistics_service.dart';
import 'package:alona_mobile/features/logistics/presentation/workflow_widgets.dart';
import 'package:alona_mobile/features/logistics/presentation/operations/parcel_operations_screen.dart';

class PickupRequestsScreen extends StatefulWidget {
  const PickupRequestsScreen({super.key});
  @override
  State<PickupRequestsScreen> createState() => _PickupRequestsScreenState();
}

class _PickupRequestsScreenState extends State<PickupRequestsScreen> {
  static const stages = {
    'all': 'All',
    'pending': 'Awaiting acceptance',
    'accepted': 'Awaiting Rider',
    'assigned': 'Pickup assigned',
    'PICKED_UP': 'Collected / inbound',
    'arrived': 'Arrived / later',
  };
  String _stage = 'pending';
  int _page = 1, _lastPage = 1;
  bool _busy = true;
  String? _error;
  List<Map<String, dynamic>> _rows = [];
  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _busy = true;
      _error = null;
      _rows = [];
    });
    try {
      final data = await LogisticsService.getData(
        'pickup-requests',
        query: {'status': 'all', 'stage': _stage, 'page': _page},
      );
      if (mounted) {
        setState(() {
          _rows = LogisticsService.rows(data['data']);
          _lastPage = data['last_page'];
        });
      }
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _run(Future<dynamic> Function() action) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      final res = await action();
      if (!mounted) return;
      if (res is Map && res['message'] != null) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(res['message'])));
      }
      await _load();
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _accept(Map<String, dynamic> row) async {
    if (_busy) return;
    setState(() => _busy = true);
    final confirmed = await confirmMovement(
      context,
      'Accept pickup ${row['parcel']['order_id']} from ${row['seller']?['name'] ?? 'the Seller'} into your Logistics company? This does not confirm collection or arrival.',
    );
    if (!mounted) return;
    setState(() => _busy = false);
    if (confirmed) {
      await _run(
        () => LogisticsService.act('pickup-requests/${row['id']}/confirm'),
      );
    }
  }

  Future<void> _assign(Map<String, dynamic> parcel) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      final response = await ApiClient.get(
        'api/sorting-center/pickup-dispatch/riders',
      );
      final riders = LogisticsService.rows(response['data']);
      if (!mounted) return;
      if (riders.isEmpty) {
        setState(
          () => _error =
              response['meta']?['empty_reason'] ??
              'No eligible Online Rider has capacity.',
        );
        return;
      }
      final id = await showDialog<int>(
        context: context,
        builder: (ctx) => SimpleDialog(
          title: const Text('Select pickup Rider'),
          children: [
            for (final rider in riders)
              SimpleDialogOption(
                onPressed: () => Navigator.pop(ctx, rider['id']),
                child: Text(
                  '${rider['name']} · ${rider['active_jobs_count']} active jobs',
                ),
              ),
          ],
        ),
      );
      if (!mounted || id == null) return;
      final confirmed = await confirmMovement(
        context,
        'Assign pickup ${parcel['order_id']} to the selected Rider? The Rider must acknowledge before collection.',
      );
      if (!mounted || !confirmed) return;
      await LogisticsService.act(
        'pickup-dispatch/${parcel['id']}/assign',
        body: {'rider_id': id},
      );
      if (mounted) await _load();
    } catch (error) {
      if (mounted) setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Seller pickup requests'),
      actions: [
        IconButton(
          onPressed: _busy ? null : _load,
          icon: const Icon(Icons.refresh),
        ),
      ],
    ),
    body: Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(16),
          child: DropdownButtonFormField<String>(
            initialValue: _stage,
            isExpanded: true,
            items: stages.entries
                .map(
                  (e) => DropdownMenuItem(value: e.key, child: Text(e.value)),
                )
                .toList(),
            onChanged: _busy
                ? null
                : (v) {
                    _stage = v!;
                    _page = 1;
                    _load();
                  },
          ),
        ),
        if (_busy) const LinearProgressIndicator(),
        if (_error != null)
          Padding(padding: const EdgeInsets.all(12), child: Text(_error!)),
        Expanded(
          child: _rows.isEmpty && !_busy
              ? Center(
                  child: Text(
                    _error == null
                        ? 'No pickup requests in this stage.'
                        : 'Refresh to reload this queue.',
                  ),
                )
              : ListView.builder(
                  padding: const EdgeInsets.all(12),
                  itemCount: _rows.length,
                  itemBuilder: (context, index) {
                    final row = _rows[index],
                        p = Map<String, dynamic>.from(row['parcel']);
                    final canDispatch =
                        row['status'] == 'confirmed' &&
                        ['PLACED', 'PREPARING'].contains(p['status']) &&
                        p['pickup_rider_id'] == null;
                    return Card(
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              p['order_id'],
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                            Text(
                              '${row['seller']?['name'] ?? 'Seller'} · ${row['status']} · ${p['status']}',
                            ),
                            Text(
                              'Seller collection: ${p['collection_address'] ?? 'Not recorded'}',
                            ),
                            Text(
                              'Buyer destination: ${p['delivery_address'] ?? 'Not recorded'}',
                            ),
                            if (p['pickup_rider'] != null)
                              Text(
                                'Pickup Rider: ${p['pickup_rider']['name']}',
                              ),
                            if (row['status'] == 'confirmed')
                              TextButton(
                                onPressed: _busy
                                    ? null
                                    : () => Navigator.push(
                                        context,
                                        MaterialPageRoute(
                                          builder: (_) =>
                                              ParcelOperationsScreen(
                                                parcelId: p['id'],
                                              ),
                                        ),
                                      ),
                                child: const Text(
                                  'Parcel operations / handover',
                                ),
                              ),
                            if (row['status'] == 'pending')
                              FilledButton(
                                onPressed: _busy ? null : () => _accept(row),
                                child: const Text('Accept pickup request'),
                              ),
                            if (canDispatch)
                              Wrap(
                                spacing: 8,
                                children: [
                                  FilledButton(
                                    onPressed: _busy ? null : () => _assign(p),
                                    child: const Text('Assign pickup Rider'),
                                  ),
                                  OutlinedButton(
                                    onPressed: _busy
                                        ? null
                                        : () => _run(
                                            () => LogisticsService.act(
                                              'pickup-dispatch/${p['id']}/nearest',
                                            ),
                                          ),
                                    child: const Text('Offer nearest Rider'),
                                  ),
                                ],
                              ),
                            if (row['timeline'] is List)
                              ExpansionTile(
                                title: const Text('Tracking history'),
                                children: [
                                  for (final event in row['timeline'])
                                    ListTile(
                                      title: Text(
                                        '${event['label'] ?? event['note'] ?? event['status'] ?? ''}',
                                      ),
                                      subtitle: Text(
                                        '${event['occurred_at'] ?? 'Not recorded'}',
                                      ),
                                    ),
                                ],
                              ),
                          ],
                        ),
                      ),
                    );
                  },
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
