import 'package:flutter/material.dart';

import '../services/rider_session.dart';
import '../services/rider_portal_api.dart';

class RiderHandovers extends StatefulWidget {
  final RiderSession session;
  const RiderHandovers({super.key, required this.session});
  @override
  State<RiderHandovers> createState() => _RiderHandoversState();
}

class _RiderHandoversState extends State<RiderHandovers> {
  List<Map<String, dynamic>> rows = [];
  int? riderId, savingId;
  bool loading = true;
  String? error, success;
  late final api = RiderPortalApi(widget.session);
  @override
  void initState() {
    super.initState();
    load();
  }

  Future<void> load() async {
    if (mounted) {
      setState(() {
        loading = true;
        error = null;
      });
    }
    try {
      final data = await api.request('rider/handovers', ready: false);
      if (!mounted) return;
      final meta = data['meta'];
      final owner = meta is Map ? meta['rider_id'] : null;
      if (owner is! int || owner <= 0 || data['data'] is! List) {
        throw const FormatException();
      }
      final records = List<Map<String, dynamic>>.from(data['data'] as List);
      for (final row in records) {
        final ids = row['parcel_ids'];
        if (row['id'] is! int ||
            (row['id'] as int) <= 0 ||
            row['from_rider_id'] is! int ||
            row['to_rider_id'] is! int ||
            (row['from_rider_id'] as int) <= 0 ||
            (row['to_rider_id'] as int) <= 0 ||
            (row['from_rider_id'] != owner && row['to_rider_id'] != owner) ||
            row['status'] is! String ||
            ids is! List ||
            ids.isEmpty ||
            ids.toSet().length != ids.length ||
            ids.any((id) => id is! int || id <= 0) ||
            [
              'parcel',
              'from_rider',
              'to_rider',
            ].any((key) => row[key] != null && row[key] is! Map)) {
          throw const FormatException();
        }
      }
      setState(() {
        rows = records;
        riderId = owner;
      });
    } catch (failure) {
      if (mounted) {
        setState(() {
          rows = [];
          riderId = null;
          success = null;
          error = failure is SessionFailure
              ? failure.message
              : 'Could not load supervised handovers. Refresh to retry.';
        });
      }
    } finally {
      if (mounted) {
        setState(() => loading = false);
      }
    }
  }

  Future<void> acknowledge(Map<String, dynamic> row) async {
    if (savingId != null || loading) return;
    final source = row['from_rider_id'] == riderId;
    final counterparty = source ? row['to_rider'] : row['from_rider'];
    final accepted = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Acknowledge physical handover'),
        content: Text(
          'Shipment ${row['parcel']?['order_id']} contains ${(row['parcel_ids'] as List).length} parcel(s). '
          '${source ? 'Confirm that you physically handed all listed parcels to' : 'Confirm that you physically received all listed parcels from'} '
          '${counterparty?['name']}. Logistics must still confirm the transfer.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('I confirm the physical handover'),
          ),
        ],
      ),
    );
    if (accepted != true || !mounted) return;
    setState(() {
      savingId = row['id'] as int;
      error = null;
      success = null;
    });
    try {
      final response = await api.request(
        'rider/handovers/${row['id']}/acknowledge',
        method: 'POST',
        ready: false,
        body: {'physical_handover': true},
      );
      final result = response['data'];
      final ack = source ? 'source_acknowledged_at' : 'target_acknowledged_at';
      final expectedParcels = (row['parcel_ids'] as List).toSet();
      final actualParcels = result is Map ? result['parcel_ids'] : null;
      if (result is! Map ||
          result['id'] != row['id'] ||
          result['from_rider_id'] != row['from_rider_id'] ||
          result['to_rider_id'] != row['to_rider_id'] ||
          actualParcels is! List ||
          actualParcels.length != expectedParcels.length ||
          actualParcels.toSet().length != expectedParcels.length ||
          actualParcels.toSet().difference(expectedParcels).isNotEmpty ||
          !['pending', 'confirmed'].contains(result['status']) ||
          result[ack] is! String ||
          DateTime.tryParse(result[ack] as String) == null) {
        throw const SessionFailure(
          'Acknowledgment response was not confirmed. Refresh handovers before another action.',
        );
      }
      if (mounted) {
        setState(
          () => success =
              'Physical handover acknowledged. Await Logistics confirmation.',
        );
      }
      await load();
    } catch (failure) {
      if (mounted) {
        setState(() {
          rows = [];
          riderId = null;
          success = null;
          error = failure is SessionFailure
              ? failure.message
              : 'Could not confirm acknowledgment. Refresh before retrying.';
        });
      }
    } finally {
      if (mounted) {
        setState(() => savingId = null);
      }
    }
  }

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Supervised handovers',
            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
          ),
          const Text(
            'Existing physical custody only. This does not enable new work on a suspended account.',
          ),
          TextButton(
            onPressed: loading || savingId != null ? null : load,
            child: const Text('Refresh handovers'),
          ),
          if (loading) const LinearProgressIndicator(),
          if (error != null) Text(error!),
          if (success != null) Text(success!),
          if (!loading && error == null && rows.isEmpty)
            const Text('No supervised handovers recorded.'),
          for (final row in rows)
            Padding(
              padding: const EdgeInsets.only(top: 12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Shipment ${row['parcel']?['order_id']} · ${row['status']}',
                  ),
                  Text(
                    '${row['from_rider']?['name']} → ${row['to_rider']?['name']}',
                  ),
                  Text('Parcels: ${(row['parcel_ids'] as List).join(', ')}'),
                  Text('Reason: ${row['reason']}'),
                  if (row['status'] == 'pending' &&
                      (row['from_rider_id'] == riderId
                              ? row['source_acknowledged_at']
                              : row['target_acknowledged_at']) ==
                          null)
                    FilledButton(
                      onPressed: savingId != null || loading
                          ? null
                          : () => acknowledge(row),
                      child: const Text('I acknowledge physical handover'),
                    )
                  else if (row['status'] == 'pending')
                    const Text(
                      'Acknowledged by you. Await the other Rider and Logistics.',
                    ),
                ],
              ),
            ),
        ],
      ),
    ),
  );
}
