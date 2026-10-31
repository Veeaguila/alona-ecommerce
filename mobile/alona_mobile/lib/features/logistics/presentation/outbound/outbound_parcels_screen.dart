import 'package:flutter/material.dart';
import 'package:alona_mobile/features/logistics/services/logistics_service.dart';
import 'package:alona_mobile/features/logistics/presentation/workflow_widgets.dart';

class OutboundParcelsScreen extends StatefulWidget {
  const OutboundParcelsScreen({super.key});
  @override
  State<OutboundParcelsScreen> createState() => _OutboundParcelsScreenState();
}

class _OutboundParcelsScreenState extends State<OutboundParcelsScreen> {
  final _reference = TextEditingController();
  Map<String, dynamic>? _parcel;
  bool _busy = false;
  String? _message;
  @override
  void dispose() {
    _reference.dispose();
    super.dispose();
  }

  Future<void> _lookup() async {
    if (_busy || _reference.text.trim().isEmpty) return;
    setState(() {
      _busy = true;
      _parcel = null;
      _message = null;
    });
    try {
      final p = await LogisticsService.getData(
        'outbound/lookup/${Uri.encodeComponent(_reference.text.trim())}',
      );
      if (mounted) setState(() => _parcel = p);
    } catch (error) {
      if (mounted) setState(() => _message = error.toString());
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _confirm() async {
    if (_busy || _parcel == null) return;
    final reference = _parcel!['order_id'];
    setState(() => _busy = true);
    final confirmed = await confirmMovement(
      context,
      'Confirm $reference is physically ready for the assigned delivery Rider?',
    );
    if (!mounted) return;
    if (!confirmed) {
      setState(() => _busy = false);
      return;
    }
    try {
      final res = await LogisticsService.act(
        'outbound/confirm',
        body: {'order_id': reference},
      );
      if (mounted) {
        setState(() {
          _message = res['message'];
          _parcel = null;
        });
      }
    } catch (error) {
      if (mounted) {
        setState(() {
          _message = error.toString();
          _parcel = null;
        });
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Outbound readiness')),
    body: ListView(
      padding: const EdgeInsets.all(16),
      children: [
        TextField(
          controller: _reference,
          enabled: !_busy,
          onChanged: (_) => setState(() => _parcel = null),
          decoration: const InputDecoration(
            labelText: 'Parcel reference',
            hintText: 'ITEM-123',
          ),
        ),
        FilledButton(
          onPressed: _busy ? null : _lookup,
          child: const Text('Look up parcel'),
        ),
        if (_busy) const LinearProgressIndicator(),
        if (_message != null) Text(_message!),
        if (_parcel != null) ...[
          Text('${_parcel!['order_id']} · ${_parcel!['status']}'),
          Text('Area: ${_parcel!['sorted_area']?['name'] ?? 'Not sorted'}'),
          Text(
            'Delivery Rider: ${_parcel!['assigned_rider']?['name'] ?? 'Not assigned'}',
          ),
          FilledButton(
            onPressed:
                _busy ||
                    _parcel!['sorted_area_id'] == null ||
                    _parcel!['assigned_rider_id'] == null
                ? null
                : _confirm,
            child: const Text('Confirm outbound readiness'),
          ),
        ],
      ],
    ),
  );
}
