import 'package:flutter/material.dart';

import '../services/rider_session.dart';
import '../services/rider_portal_api.dart';

class RiderReturnsPage extends StatefulWidget {
  final RiderSession session;
  const RiderReturnsPage({super.key, required this.session});
  @override
  State<RiderReturnsPage> createState() => _RiderReturnsPageState();
}

class _RiderReturnsPageState extends State<RiderReturnsPage> {
  late final api = RiderPortalApi(widget.session);
  List<Map<String, dynamic>> rows = [];
  bool loading = true, saving = false, history = false;
  int page = 1, lastPage = 1;
  String? error, success;
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final result = await api.request(
        'rider/returns?view=${history ? 'history' : 'active'}&page=$page',
      );
      if (!mounted) return;
      final data = result['data'] as Map;
      setState(() {
        rows = List<Map<String, dynamic>>.from(data['data'] as List);
        lastPage = data['last_page'] as int;
      });
    } catch (failure) {
      if (mounted) {
        setState(
          () => error = failure is SessionFailure
              ? failure.message
              : 'Could not load return jobs. Refresh to retry.',
        );
      }
    } finally {
      if (mounted) {
        setState(() => loading = false);
      }
    }
  }

  Future<void> action(Map<String, dynamic> row) async {
    if (saving || loading) return;
    final handover = row['status'] == 'in_transit';
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(
          handover
              ? 'Confirm physical handover to Seller'
              : 'Confirm return parcel pickup',
        ),
        content: Text(
          handover
              ? 'Confirm you physically handed ${row['parcel']?['order_id']} to ${row['parcel']?['seller']?['name']}. Inventory stays unchanged until the Seller confirms receipt.'
              : 'Confirm you hold ${row['parcel']?['order_id']} and are beginning its return to the Seller. This does not confirm arrival at a Sorting Center.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('I confirm the physical action'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() {
      saving = true;
      error = null;
    });
    try {
      final result = await api.request(
        'rider/returns/${row['id']}/${handover ? 'handover' : 'pickup'}',
        method: 'POST',
        body: {'physical_confirmation': true},
      );
      if (!mounted) return;
      setState(() {
        success = result['message']?.toString();
        if (handover) {
          history = true;
          page = 1;
        }
      });
      await load();
    } catch (failure) {
      if (mounted) {
        setState(
          () => error = failure is SessionFailure ? failure.message : 'Could not confirm this return action. Refresh before retrying.',
        );
      }
    } finally {
      if (mounted) {
        setState(() => saving = false);
      }
    }
  }

  String label(String status) => switch (status) {
    'initiated' => 'Return initiated',
    'in_transit' => 'In Transit to Seller',
    'handed_over' => 'Returned to Seller — awaiting receipt confirmation',
    'received' => 'Returned to Seller — Seller confirmed',
    _ => status,
  };
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Return to Seller'),
      actions: [
        IconButton(
          tooltip: 'Refresh returns',
          onPressed: loading || saving ? null : load,
          icon: const Icon(Icons.refresh),
        ),
      ],
    ),
    body: ListView(
      padding: const EdgeInsets.all(16),
      children: [
        const Text(
          'These are physical returns assigned by Logistics. Seller receipt confirmation is required before restocking.',
        ),
        Wrap(
          spacing: 8,
          children: [
            for (final entry in {
              false: 'Active returns',
              true: 'Return history',
            }.entries)
              ChoiceChip(
                label: Text(entry.value),
                selected: history == entry.key,
                onSelected: loading || saving
                    ? null
                    : (_) {
                        setState(() {
                          history = entry.key;
                          page = 1;
                        });
                        load();
                      },
              ),
          ],
        ),
        if (loading || saving) const LinearProgressIndicator(),
        if (error != null) Text(error!),
        if (success != null) Text(success!),
        if (!loading && rows.isEmpty)
          const Text('No return parcels in this view.'),
        for (final row in rows)
          Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '${row['parcel']?['order_id']} · ${label(row['status'] as String)}',
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                  Text('Seller: ${row['parcel']?['seller']?['name']}'),
                  Text(
                    'Collection address: ${row['parcel']?['collection_address'] ?? 'Confirm with Logistics'}',
                  ),
                  Text('Return reason: ${row['reason']}'),
                  if (row['picked_up_at'] != null)
                    Text('Return pickup: ${row['picked_up_at']}'),
                  if (row['handed_over_at'] != null)
                    Text('Rider handover: ${row['handed_over_at']}'),
                  if (row['seller_received_at'] != null)
                    Text('Seller receipt: ${row['seller_received_at']}'),
                  if (!history)
                    FilledButton(
                      onPressed: loading || saving ? null : () => action(row),
                      child: Text(
                        row['status'] == 'in_transit'
                            ? 'Confirm handover to Seller'
                            : 'Confirm return pickup',
                      ),
                    ),
                ],
              ),
            ),
          ),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            TextButton(
              onPressed: loading || saving || page <= 1
                  ? null
                  : () {
                      setState(() => page--);
                      load();
                    },
              child: const Text('Previous returns'),
            ),
            Text('Page $page of $lastPage'),
            TextButton(
              onPressed: loading || saving || page >= lastPage
                  ? null
                  : () {
                      setState(() => page++);
                      load();
                    },
              child: const Text('Next returns'),
            ),
          ],
        ),
      ],
    ),
  );
}
