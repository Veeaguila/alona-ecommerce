import 'dart:math';

import 'package:flutter/material.dart';
import 'package:alona_mobile/features/logistics/services/logistics_service.dart';
import 'package:alona_mobile/features/logistics/presentation/workflow_widgets.dart';

class HubWorkspaceScreen extends StatefulWidget {
  const HubWorkspaceScreen({super.key});
  @override
  State<HubWorkspaceScreen> createState() => _HubWorkspaceScreenState();
}

class _HubWorkspaceScreenState extends State<HubWorkspaceScreen> {
  final _reference = TextEditingController(),
      _transport = TextEditingController();
  List<Map<String, dynamic>> _hubs = [], _queue = [];
  int? _hub;
  int _generation = 0;
  bool _busy = true;
  String? _error, _requestKey;
  Map<String, dynamic>? _view;
  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _reference.dispose();
    _transport.dispose();
    super.dispose();
  }

  Map<String, dynamic> get _query => {if (_hub != null) 'hub_id': _hub};
  Future<void> _load() async {
    final generation = ++_generation;
    setState(() {
      _busy = true;
      _error = null;
      _view = null;
      _queue = [];
      _requestKey = null;
      _transport.clear();
    });
    try {
      final data = await LogisticsService.getData(
        'hub-workspace',
        query: _query,
      );
      if (!mounted || generation != _generation) return;
      final hub = data['operating_hub']?['id'] as int?;
      _hubs = LogisticsService.rows(data['hubs']);
      _hub = hub;
      if (hub != null) {
        final page = await LogisticsService.getData(
          'hub-workspace/queue',
          query: {'hub_id': hub},
        );
        if (!mounted || generation != _generation) return;
        _queue = LogisticsService.rows(page['data']);
      }
      setState(() => _busy = false);
    } catch (error) {
      if (mounted && generation == _generation) {
        setState(() {
          _busy = false;
          _error = error.toString();
        });
      }
    }
  }

  Future<void> _lookup(String reference) async {
    if (_busy || _hub == null || reference.trim().isEmpty) return;
    setState(() {
      _busy = true;
      _error = null;
      _view = null;
      _requestKey = null;
      _transport.clear();
    });
    try {
      final view = await LogisticsService.getData(
        'hub-workspace/lookup',
        query: {..._query, 'reference': reference.trim()},
      );
      if (mounted) {
        setState(() {
          _view = view;
          _busy = false;
        });
      }
    } catch (error) {
      if (mounted) {
        setState(() {
          _error = error.toString();
          _busy = false;
        });
      }
    }
  }

  String _uuid() {
    final bytes = List<int>.generate(16, (_) => Random.secure().nextInt(256));
    bytes[6] = (bytes[6] & 15) | 64;
    bytes[8] = (bytes[8] & 63) | 128;
    final s = bytes.map((v) => v.toRadixString(16).padLeft(2, '0')).join();
    return '${s.substring(0, 8)}-${s.substring(8, 12)}-${s.substring(12, 16)}-${s.substring(16, 20)}-${s.substring(20)}';
  }

  Future<void> _act() async {
    if (_busy || _view?['action'] == null) return;
    final view = _view!, hub = _hub!;
    final transport = _transport.text.trim();
    if (view['action'] == 'dispatch' && transport.length < 3) {
      setState(() => _error = 'Enter the actual transport reference.');
      return;
    }
    setState(() => _busy = true);
    final confirmed = await confirmMovement(
      context,
      '${view['action_label']} for ${view['parcel']['order_id']} at ${view['operating_hub']['name']}? Confirm only after checking the physical parcel.',
    );
    if (!mounted) return;
    if (!confirmed) {
      setState(() => _busy = false);
      return;
    }
    _requestKey ??= _uuid();
    try {
      await LogisticsService.act(
        'hub-workspace/actions',
        body: {
          'hub_id': hub,
          'reference': view['parcel']['order_id'],
          'action': view['action'],
          'physical_confirmation': true,
          if (view['action'] == 'dispatch') ...{
            'request_key': _requestKey,
            'transport_reference': transport,
          },
        },
      );
      if (mounted) await _load();
    } catch (error) {
      if (mounted) {
        setState(() {
          _error = error.toString();
          _busy = false;
          _view = null;
        });
      }
      // Never repeat a movement automatically after an uncertain response.
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Hub workspace'),
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
        DropdownButtonFormField<int>(
          key: ValueKey(_hub),
          initialValue: _hub,
          isExpanded: true,
          decoration: const InputDecoration(labelText: 'Operating hub'),
          items: _hubs
              .map(
                (h) => DropdownMenuItem<int>(
                  value: h['id'],
                  child: Text(h['name']),
                ),
              )
              .toList(),
          onChanged: _busy
              ? null
              : (id) {
                  _hub = id;
                  _load();
                },
        ),
        const SizedBox(height: 16),
        TextField(
          controller: _reference,
          enabled: !_busy,
          decoration: const InputDecoration(
            labelText: 'Parcel reference',
            hintText: 'ITEM-123',
          ),
          onSubmitted: _lookup,
        ),
        const SizedBox(height: 12),
        FilledButton(
          onPressed: _busy || _hub == null
              ? null
              : () => _lookup(_reference.text),
          child: const Text('Look up parcel'),
        ),
        if (_busy) const LinearProgressIndicator(),
        if (_error != null)
          Padding(padding: const EdgeInsets.all(12), child: Text(_error!)),
        if (_hubs.isEmpty && !_busy)
          const Text(
            'No active hub is available. Ask the company administrator to review hub setup.',
          ),
        if (_view != null) ...[
          const SizedBox(height: 16),
          Text(
            '${_view!['parcel']['order_id']} · ${_view!['parcel']['status']}',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          Text('Current location: ${_view!['location_label']}'),
          if (_view!['next_destination'] != null)
            Text('Next: ${_view!['next_destination']['name']}'),
          Text(_view!['instruction']),
          if (_view!['action'] == 'dispatch')
            TextField(
              controller: _transport,
              enabled: !_busy,
              decoration: const InputDecoration(
                labelText: 'Actual transport reference',
              ),
            ),
          if (_view!['action'] != null)
            FilledButton(
              onPressed: _busy ? null : _act,
              child: Text(_view!['action_label']),
            ),
        ],
        const Divider(height: 32),
        const Text('Active parcels at this hub'),
        for (final row in _queue)
          ListTile(
            title: Text(row['parcel']['order_id']),
            subtitle: Text(row['parcel']['status']),
            onTap: _busy ? null : () => _lookup(row['parcel']['order_id']),
          ),
        if (_queue.length == 20)
          const Text(
            'Showing the latest 20. Look up the exact reference to locate another parcel.',
          ),
      ],
    ),
  );
}
