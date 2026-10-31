import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';
import 'package:alona_mobile/core/network/api_client.dart';
import 'package:alona_mobile/features/logistics/presentation/proofs/proof_review_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/workflow_widgets.dart';
import 'package:alona_mobile/features/logistics/services/logistics_service.dart';

class ParcelOperationsScreen extends StatefulWidget {
  const ParcelOperationsScreen({super.key, required this.parcelId});
  final int parcelId;
  @override
  State<ParcelOperationsScreen> createState() => _ParcelOperationsScreenState();
}

class _ParcelOperationsScreenState extends State<ParcelOperationsScreen> {
  bool _busy = true;
  String? _message;
  Map<String, dynamic>? _data;
  List<Map<String, dynamic>> _handovers = [];
  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _busy = true;
      _data = null;
      _handovers = [];
    });
    try {
      final data = await LogisticsService.getData(
        'parcels/${widget.parcelId}/operations',
      );
      final handovers = await ApiClient.get(
        'api/sorting-center/parcels/${widget.parcelId}/handovers',
      );
      if (mounted) {
        setState(() {
          _data = data;
          _handovers = LogisticsService.rows(handovers['data']);
        });
      }
    } catch (error) {
      if (mounted) setState(() => _message = error.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _operation(String operation) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      final reason = await requestText(
        context,
        title: operation == 'delivered'
            ? 'Reason for privileged Delivered override'
            : 'Actual $operation incident reason',
      );
      if (reason == null || !mounted) return;
      XFile? photo;
      if (operation == 'damaged') {
        photo = await ImagePicker().pickImage(
          source: ImageSource.gallery,
          maxWidth: 6000,
          maxHeight: 6000,
        );
        if (photo == null) return;
      }
      if (!mounted) return;
      if (!await confirmMovement(
        context,
        operation == 'delivered'
            ? 'Record a privileged delivery confirmation without Buyer OTP/photo? A permanent audit is recorded. This does not confirm cash or issue payouts.'
            : 'Record this actual $operation incident? Refunds remain subject to authorized review.',
      )) {
        return;
      }
      dynamic res;
      if (operation == 'delivered') {
        res = await LogisticsService.act(
          'parcels/${widget.parcelId}/override-delivered',
          body: {'reason': reason},
        );
      } else {
        final files = <http.MultipartFile>[];
        if (photo != null) {
          final bytes = await photo.readAsBytes();
          if (bytes.length > 20 * 1024 * 1024) {
            throw ApiException('Incident photo must be at most 20 MB.');
          }
          files.add(
            http.MultipartFile.fromBytes('file', bytes, filename: photo.name),
          );
        }
        res = await ApiClient.multipart(
          'api/sorting-center/parcels/${widget.parcelId}/incidents',
          method: 'POST',
          fields: {
            'type': operation,
            'reason': reason,
            'request_key': newRequestKey(),
          },
          files: files,
        );
      }
      if (mounted) {
        setState(() => _message = res['message']);
        await _load();
      }
    } catch (error) {
      if (mounted) {
        setState(() {
          _message = error.toString();
          _data = null;
        });
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _createHandover() async {
    if (_busy || _data == null) return;
    final parcel = Map<String, dynamic>.from(_data!['parcel']);
    setState(() => _busy = true);
    try {
      final response = await ApiClient.get(
        'api/sorting-center/pickup-dispatch/riders',
      );
      final riders = LogisticsService.rows(response['data']);
      if (!mounted) return;
      final id = await showDialog<int>(
        context: context,
        builder: (ctx) => SimpleDialog(
          title: const Text('Receiving Rider'),
          children: [
            if (riders.isEmpty)
              const Padding(
                padding: EdgeInsets.all(16),
                child: Text('No approved Online Rider has capacity.'),
              ),
            for (final rider in riders)
              SimpleDialogOption(
                onPressed: () => Navigator.pop(ctx, rider['id']),
                child: Text(rider['name']),
              ),
          ],
        ),
      );
      if (!mounted || id == null) return;
      final reason = await requestText(
        context,
        title: 'Supervised physical handover reason',
        maxLength: 2000,
      );
      if (reason == null) return;
      await LogisticsService.act(
        'parcels/${widget.parcelId}/handovers',
        body: {
          'request_key': newRequestKey(),
          'rider_id': id,
          'leg': parcel['status'] == 'PICKED_UP' ? 'pickup' : 'delivery',
          'reason': reason,
        },
      );
      if (mounted) await _load();
    } catch (error) {
      if (mounted) {
        setState(() {
          _message = error.toString();
          _data = null;
        });
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _handoverAction(Map<String, dynamic> row, bool cancel) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      String? reason;
      if (cancel) {
        reason = await requestText(
          context,
          title: 'Cancellation reason',
          maxLength: 2000,
        );
        if (reason == null) return;
      } else if (!await confirmMovement(
        context,
        'Confirm this whole-shipment physical handover after both Riders acknowledged?',
      )) {
        return;
      }
      final result = await LogisticsService.act(
        'handovers/${row['id']}/${cancel ? 'cancel' : 'confirm'}',
        body: {'reason': ?reason},
      );
      if (mounted) {
        setState(() => _message = result['message']);
        await _load();
      }
    } catch (error) {
      if (mounted) {
        setState(() {
          _message = error.toString();
          _data = null;
        });
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final parcel = _data?['parcel'];
    return Scaffold(
      appBar: AppBar(
        title: const Text('Parcel operations'),
        actions: [
          IconButton(
            onPressed: _busy ? null : _load,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (_busy) const LinearProgressIndicator(),
          if (_message != null) Text(_message!),
          if (parcel != null) ...[
            Text(
              '${parcel['order_id']} · ${parcel['status']}',
              style: Theme.of(context).textTheme.titleLarge,
            ),
            Text(
              'Seller collection: ${parcel['collection_address'] ?? 'Not recorded'}',
            ),
            Text(
              'Buyer destination: ${parcel['delivery_address'] ?? 'Not recorded'}',
            ),
            OutlinedButton(
              onPressed: _busy
                  ? null
                  : () => Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) =>
                            ProofReviewScreen(parcelId: widget.parcelId),
                      ),
                    ),
              child: const Text('View private delivery proofs'),
            ),
            if ([
              'PICKED_UP',
              'AT_SORTING_CENTER',
              'IN_HUB_TRANSIT',
              'OUT_FOR_DELIVERY',
            ].contains(parcel['status'])) ...[
              OutlinedButton(
                onPressed: _busy ? null : () => _operation('lost'),
                child: const Text('Report Lost'),
              ),
              OutlinedButton(
                onPressed: _busy ? null : () => _operation('damaged'),
                child: const Text('Report Damaged with photo'),
              ),
            ],
            if (parcel['status'] == 'OUT_FOR_DELIVERY')
              OutlinedButton(
                onPressed: _busy ? null : () => _operation('delivered'),
                child: const Text('Privileged Delivered override'),
              ),
            const Divider(),
            const Text('Supervised Rider handover'),
            const Text(
              'Both Riders must acknowledge the actual physical transfer before Logistics can confirm custody.',
            ),
            if ([
                  'PICKED_UP',
                  'OUT_FOR_DELIVERY',
                  'FAILED_RETURNED',
                ].contains(parcel['status']) &&
                !_handovers.any((row) => row['status'] == 'pending'))
              FilledButton(
                onPressed: _busy ? null : _createHandover,
                child: const Text('Request supervised handover'),
              ),
            for (final row in _handovers)
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${row['from_rider']?['name']} → ${row['to_rider']?['name']} · ${row['status']}',
                      ),
                      Text(
                        'Source acknowledgment: ${row['source_acknowledged_at'] ?? 'Pending'}',
                      ),
                      Text(
                        'Receiving acknowledgment: ${row['target_acknowledged_at'] ?? 'Pending'}',
                      ),
                      if (row['status'] == 'pending') ...[
                        FilledButton(
                          onPressed:
                              _busy ||
                                  row['source_acknowledged_at'] == null ||
                                  row['target_acknowledged_at'] == null
                              ? null
                              : () => _handoverAction(row, false),
                          child: const Text('Confirm supervised handover'),
                        ),
                        TextButton(
                          onPressed: _busy
                              ? null
                              : () => _handoverAction(row, true),
                          child: const Text('Cancel handover request'),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
            const Divider(),
            const Text('Immutable audit history'),
            for (final event in _data!['events'])
              ListTile(
                title: Text(event['action'].toString().replaceAll('_', ' ')),
                subtitle: Text(
                  '${event['actor']?['name'] ?? 'Recorded actor unavailable'} · ${event['created_at']}\n${event['data']?['reason'] ?? ''}',
                ),
              ),
            for (final incident in _data!['incidents'])
              Text('${incident['type']}: ${incident['reason']}'),
            if (_data!['review'] != null)
              const Text(
                'Refund or claim review remains in the existing authorized workflow. No refund is issued by this screen.',
              ),
          ],
        ],
      ),
    );
  }
}
