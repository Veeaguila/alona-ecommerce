import 'dart:math';

import 'package:flutter/material.dart';
import 'package:alona_mobile/features/logistics/services/logistics_service.dart';
import 'package:alona_mobile/features/logistics/presentation/workflow_widgets.dart';
import 'hub_scanner_modal.dart';

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

  // Multi-select & Batch Operations
  final Set<int> _selectedIds = {};
  String _queueFilter = 'all'; // 'all', 'at_hub', 'in_transit'

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

  String get _currentHubName {
    final match = _hubs.where((h) => h['id'] == _hub);
    return match.isNotEmpty ? match.first['name'] : 'Operating Hub';
  }

  List<Map<String, dynamic>> get _filteredQueue {
    if (_queueFilter == 'at_hub') {
      return _queue
          .where((r) => r['parcel']?['status'] == 'AT_SORTING_CENTER')
          .toList();
    }
    if (_queueFilter == 'in_transit') {
      return _queue
          .where((r) => r['parcel']?['status'] == 'IN_HUB_TRANSIT')
          .toList();
    }
    return _queue;
  }

  Future<void> _load() async {
    final generation = ++_generation;
    setState(() {
      _busy = true;
      _error = null;
      _view = null;
      _queue = [];
      _selectedIds.clear();
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

  Future<void> _openScanner({bool batchMode = false}) async {
    final result = await HubScannerModal.show(
      context,
      initialBatchMode: batchMode,
    );
    if (!mounted || result == null) return;

    if (result.isBatch) {
      final scannedCodes =
          result.batchCodes.map((c) => c.toLowerCase()).toSet();
      final matchedIds = <int>{};
      for (final row in _queue) {
        final orderId =
            (row['parcel']?['order_id'] as String?)?.toLowerCase();
        final pid = row['parcel']?['id'] as int?;
        if (orderId != null && scannedCodes.contains(orderId) && pid != null) {
          matchedIds.add(pid);
        }
      }

      setState(() {
        _selectedIds.addAll(matchedIds);
      });

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            matchedIds.isNotEmpty
                ? 'Selected ${matchedIds.length} parcel(s) from scanner.'
                : 'Scanned ${result.batchCodes.length} barcode(s), but none matched active parcels in this hub queue.',
          ),
          backgroundColor: matchedIds.isNotEmpty
              ? const Color(0xFF0F766E)
              : const Color(0xFFD97706),
          behavior: SnackBarBehavior.floating,
        ),
      );
    } else if (result.singleCode != null) {
      _reference.text = result.singleCode!;
      await _lookup(result.singleCode!);
    }
  }

  void _toggleSelectAll() {
    final filtered = _filteredQueue;
    final allSelected = filtered.isNotEmpty &&
        filtered.every((r) => _selectedIds.contains(r['parcel']?['id']));

    setState(() {
      if (allSelected) {
        for (final r in filtered) {
          final id = r['parcel']?['id'] as int?;
          if (id != null) _selectedIds.remove(id);
        }
      } else {
        for (final r in filtered) {
          final id = r['parcel']?['id'] as int?;
          if (id != null) _selectedIds.add(id);
        }
      }
    });
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
    }
  }

  // --- BATCH DISPATCH MODAL ---
  Future<void> _openBatchDispatchDialog() async {
    if (_selectedIds.isEmpty || _hub == null) return;
    final otherHubs = _hubs.where((h) => h['id'] != _hub).toList();
    if (otherHubs.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('No other destination hub is available for transit dispatch.'),
          backgroundColor: Colors.redAccent,
        ),
      );
      return;
    }

    int? destinationHubId = otherHubs.first['id'] as int?;
    final transportController = TextEditingController();
    bool confirmedLoaded = false;
    String? dialogError;

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) {
        return StatefulBuilder(
          builder: (modalCtx, setModalState) {
            return Padding(
              padding: EdgeInsets.only(
                left: 20,
                right: 20,
                top: 20,
                bottom: MediaQuery.of(modalCtx).viewInsets.bottom + 20,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      const Icon(Icons.local_shipping_outlined, color: Color(0xFF0F766E), size: 28),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          'Batch Dispatch (${_selectedIds.length} Parcels)',
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 17),
                        ),
                      ),
                      IconButton(
                        icon: const Icon(Icons.close),
                        onPressed: () => Navigator.pop(ctx),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Text(
                    'Dispatches selected parcels from $_currentHubName to another operating hub via transport linehaul.',
                    style: const TextStyle(color: Colors.black54, fontSize: 13),
                  ),
                  const SizedBox(height: 16),
                  DropdownButtonFormField<int>(
                    initialValue: destinationHubId,
                    decoration: const InputDecoration(
                      labelText: 'Destination Hub',
                      border: OutlineInputBorder(),
                    ),
                    items: otherHubs.map((h) {
                      return DropdownMenuItem<int>(
                        value: h['id'] as int,
                        child: Text(h['name']),
                      );
                    }).toList(),
                    onChanged: (id) => setModalState(() => destinationHubId = id),
                  ),
                  const SizedBox(height: 14),
                  TextField(
                    controller: transportController,
                    decoration: const InputDecoration(
                      labelText: 'Transport Reference / Truck Plate No.',
                      hintText: 'e.g. TRUCK-MNL-LAG-01',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 12),
                  CheckboxListTile(
                    contentPadding: EdgeInsets.zero,
                    value: confirmedLoaded,
                    title: const Text(
                      'I confirm that all selected parcels are physically loaded into the vehicle.',
                      style: TextStyle(fontSize: 12.5),
                    ),
                    onChanged: (val) => setModalState(() => confirmedLoaded = val ?? false),
                  ),
                  if (dialogError != null)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: Text(dialogError!, style: const TextStyle(color: Colors.red, fontSize: 12)),
                    ),
                  const SizedBox(height: 12),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton.icon(
                      style: FilledButton.styleFrom(
                        backgroundColor: const Color(0xFF0F766E),
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                      icon: const Icon(Icons.check_circle_outline),
                      label: Text(
                        'Confirm Dispatch (${_selectedIds.length} Parcels)',
                        style: const TextStyle(fontWeight: FontWeight.bold),
                      ),
                      onPressed: () async {
                        final transport = transportController.text.trim();
                        if (destinationHubId == null) {
                          setModalState(() => dialogError = 'Please select a destination hub.');
                          return;
                        }
                        if (transport.length < 3) {
                          setModalState(() => dialogError = 'Enter a valid transport reference (at least 3 characters).');
                          return;
                        }
                        if (!confirmedLoaded) {
                          setModalState(() => dialogError = 'Please confirm physical loading before dispatch.');
                          return;
                        }

                        Navigator.pop(ctx);
                        setState(() => _busy = true);
                        try {
                          final res = await LogisticsService.act(
                            'hub-workspace/batch-dispatch',
                            body: {
                              'hub_id': _hub,
                              'destination_hub_id': destinationHubId,
                              'transport_reference': transport,
                              'parcel_ids': _selectedIds.toList(),
                            },
                          );
                          final msg = res['message'] ?? 'Parcels dispatched successfully.';
                          if (mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(
                                content: Text('$msg'),
                                backgroundColor: const Color(0xFF0F766E),
                                behavior: SnackBarBehavior.floating,
                              ),
                            );
                            await _load();
                          }
                        } catch (e) {
                          if (mounted) {
                            setState(() {
                              _error = e.toString();
                              _busy = false;
                            });
                          }
                        }
                      },
                    ),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  // --- BATCH RECEIVE MODAL ---
  Future<void> _openBatchReceiveDialog() async {
    if (_selectedIds.isEmpty || _hub == null) return;
    final transportController = TextEditingController();
    bool confirmedReceived = false;
    String? dialogError;

    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) {
        return StatefulBuilder(
          builder: (modalCtx, setModalState) {
            return Padding(
              padding: EdgeInsets.only(
                left: 20,
                right: 20,
                top: 20,
                bottom: MediaQuery.of(modalCtx).viewInsets.bottom + 20,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      const Icon(Icons.inventory_2_outlined, color: Color(0xFF4338CA), size: 28),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          'Batch Receive (${_selectedIds.length} Parcels)',
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 17),
                        ),
                      ),
                      IconButton(
                        icon: const Icon(Icons.close),
                        onPressed: () => Navigator.pop(ctx),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Text(
                    'Marks incoming transit parcels as physically arrived at $_currentHubName.',
                    style: const TextStyle(color: Colors.black54, fontSize: 13),
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: transportController,
                    decoration: const InputDecoration(
                      labelText: 'Transport / Manifest Reference (Optional)',
                      hintText: 'e.g. TRUCK-MNL-LAG-01',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 12),
                  CheckboxListTile(
                    contentPadding: EdgeInsets.zero,
                    value: confirmedReceived,
                    title: const Text(
                      'I confirm that all selected parcels have physically arrived and are inspected at this hub.',
                      style: TextStyle(fontSize: 12.5),
                    ),
                    onChanged: (val) => setModalState(() => confirmedReceived = val ?? false),
                  ),
                  if (dialogError != null)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: Text(dialogError!, style: const TextStyle(color: Colors.red, fontSize: 12)),
                    ),
                  const SizedBox(height: 12),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton.icon(
                      style: FilledButton.styleFrom(
                        backgroundColor: const Color(0xFF4338CA),
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                      icon: const Icon(Icons.check_circle_outline),
                      label: Text(
                        'Confirm Receive (${_selectedIds.length} Parcels)',
                        style: const TextStyle(fontWeight: FontWeight.bold),
                      ),
                      onPressed: () async {
                        if (!confirmedReceived) {
                          setModalState(() => dialogError = 'Please confirm physical arrival before receiving.');
                          return;
                        }

                        Navigator.pop(ctx);
                        setState(() => _busy = true);
                        try {
                          final transport = transportController.text.trim();
                          final res = await LogisticsService.act(
                            'hub-workspace/batch-receive',
                            body: {
                              'hub_id': _hub,
                              if (transport.isNotEmpty) 'transport_reference': transport,
                              'parcel_ids': _selectedIds.toList(),
                            },
                          );
                          final msg = res['message'] ?? 'Parcels received into hub.';
                          if (mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(
                                content: Text('$msg'),
                                backgroundColor: const Color(0xFF4338CA),
                                behavior: SnackBarBehavior.floating,
                              ),
                            );
                            await _load();
                          }
                        } catch (e) {
                          if (mounted) {
                            setState(() {
                              _error = e.toString();
                              _busy = false;
                            });
                          }
                        }
                      },
                    ),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    final filtered = _filteredQueue;
    final allInFilteredSelected = filtered.isNotEmpty &&
        filtered.every((r) => _selectedIds.contains(r['parcel']?['id']));

    return Scaffold(
      appBar: AppBar(
        title: const Text('Hub workspace'),
        actions: [
          IconButton(
            tooltip: 'Open Barcode Scanner',
            onPressed: _busy ? null : () => _openScanner(batchMode: false),
            icon: const Icon(Icons.qr_code_scanner, color: Colors.blueAccent),
          ),
          IconButton(
            tooltip: 'Refresh',
            onPressed: _busy ? null : _load,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      bottomNavigationBar: _selectedIds.isNotEmpty
          ? Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              decoration: BoxDecoration(
                color: Colors.white,
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.12),
                    blurRadius: 10,
                    offset: const Offset(0, -3),
                  ),
                ],
              ),
              child: SafeArea(
                top: false,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Row(
                          children: [
                            const Icon(Icons.check_box, color: Color(0xFF0F766E), size: 20),
                            const SizedBox(width: 8),
                            Text(
                              '${_selectedIds.length} parcel(s) selected',
                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                            ),
                          ],
                        ),
                        TextButton(
                          onPressed: () => setState(() => _selectedIds.clear()),
                          child: const Text('Clear selection'),
                        ),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Row(
                      children: [
                        Expanded(
                          child: FilledButton.icon(
                            style: FilledButton.styleFrom(
                              backgroundColor: const Color(0xFF0F766E),
                              padding: const EdgeInsets.symmetric(vertical: 12),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            ),
                            icon: const Icon(Icons.local_shipping, size: 18),
                            label: const Text('Batch Dispatch'),
                            onPressed: _openBatchDispatchDialog,
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: FilledButton.icon(
                            style: FilledButton.styleFrom(
                              backgroundColor: const Color(0xFF4338CA),
                              padding: const EdgeInsets.symmetric(vertical: 12),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            ),
                            icon: const Icon(Icons.archive, size: 18),
                            label: const Text('Batch Receive'),
                            onPressed: _openBatchReceiveDialog,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            )
          : null,
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // Operating Hub Dropdown
          DropdownButtonFormField<int>(
            key: ValueKey(_hub),
            initialValue: _hub,
            isExpanded: true,
            decoration: const InputDecoration(
              labelText: 'Operating hub',
              border: OutlineInputBorder(),
              contentPadding: EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            ),
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
          const SizedBox(height: 14),

          // Search / Single Scan Input
          TextField(
            controller: _reference,
            enabled: !_busy,
            decoration: InputDecoration(
              labelText: 'Parcel reference',
              hintText: 'e.g. ITEM-1234',
              border: const OutlineInputBorder(),
              contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              suffixIcon: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  IconButton(
                    icon: const Icon(Icons.qr_code_scanner, color: Colors.blueAccent),
                    tooltip: 'Scan Barcode',
                    onPressed: _busy ? null : () => _openScanner(batchMode: false),
                  ),
                  IconButton(
                    icon: const Icon(Icons.search),
                    tooltip: 'Look up',
                    onPressed: _busy || _hub == null
                        ? null
                        : () => _lookup(_reference.text),
                  ),
                ],
              ),
            ),
            onSubmitted: _lookup,
          ),
          const SizedBox(height: 8),

          // Fast Batch Scan Button
          OutlinedButton.icon(
            style: OutlinedButton.styleFrom(
              padding: const EdgeInsets.symmetric(vertical: 12),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              side: const BorderSide(color: Color(0xFF0F766E)),
            ),
            icon: const Icon(Icons.checklist_rtl, color: Color(0xFF0F766E)),
            label: const Text(
              'Continuous Batch Barcode Scan (Multi-Scan)',
              style: TextStyle(color: Color(0xFF0F766E), fontWeight: FontWeight.bold),
            ),
            onPressed: _busy || _hub == null
                ? null
                : () => _openScanner(batchMode: true),
          ),

          if (_busy) const Padding(
            padding: EdgeInsets.symmetric(vertical: 12),
            child: LinearProgressIndicator(),
          ),
          if (_error != null)
            Container(
              margin: const EdgeInsets.only(top: 12),
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.red.shade50,
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: Colors.red.shade200),
              ),
              child: Text(_error!, style: TextStyle(color: Colors.red.shade800, fontSize: 13)),
            ),
          if (_hubs.isEmpty && !_busy)
            const Padding(
              padding: EdgeInsets.all(16),
              child: Text(
                'No active hub is available. Ask the company administrator to review hub setup.',
                style: TextStyle(color: Colors.black54),
              ),
            ),

          // Single Parcel Details View Card
          if (_view != null) ...[
            const SizedBox(height: 16),
            Card(
              elevation: 2,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          _view!['parcel']['order_id'],
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                          decoration: BoxDecoration(
                            color: Colors.blue.shade50,
                            borderRadius: BorderRadius.circular(6),
                            border: Border.all(color: Colors.blue.shade200),
                          ),
                          child: Text(
                            _view!['parcel']['status'],
                            style: TextStyle(color: Colors.blue.shade900, fontWeight: FontWeight.w600, fontSize: 12),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text('Location: ${_view!['location_label']}', style: const TextStyle(fontSize: 13.5)),
                    if (_view!['next_destination'] != null)
                      Text('Next Destination: ${_view!['next_destination']['name']}', style: const TextStyle(fontSize: 13.5, fontWeight: FontWeight.w500)),
                    const SizedBox(height: 6),
                    Text(
                      _view!['instruction'] ?? '',
                      style: const TextStyle(fontSize: 13, color: Colors.black87),
                    ),
                    if (_view!['action'] == 'dispatch') ...[
                      const SizedBox(height: 10),
                      TextField(
                        controller: _transport,
                        enabled: !_busy,
                        decoration: const InputDecoration(
                          labelText: 'Actual transport reference',
                          border: OutlineInputBorder(),
                          isDense: true,
                        ),
                      ),
                    ],
                    if (_view!['action'] != null) ...[
                      const SizedBox(height: 12),
                      SizedBox(
                        width: double.infinity,
                        child: FilledButton(
                          style: FilledButton.styleFrom(
                            padding: const EdgeInsets.symmetric(vertical: 12),
                          ),
                          onPressed: _busy ? null : _act,
                          child: Text(_view!['action_label']),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ],

          const Divider(height: 36),

          // Active Parcels Section Header with Select All
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              const Text(
                'Active parcels at this hub',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
              ),
              if (_queue.isNotEmpty)
                Text(
                  '${_queue.length} items',
                  style: const TextStyle(color: Colors.black54, fontSize: 13),
                ),
            ],
          ),
          const SizedBox(height: 8),

          // Filter chips
          Wrap(
            spacing: 8,
            children: [
              ChoiceChip(
                label: Text('All (${_queue.length})'),
                selected: _queueFilter == 'all',
                onSelected: (sel) {
                  if (sel) setState(() => _queueFilter = 'all');
                },
              ),
              ChoiceChip(
                label: Text('At Hub (${_queue.where((r) => r['parcel']?['status'] == 'AT_SORTING_CENTER').length})'),
                selected: _queueFilter == 'at_hub',
                onSelected: (sel) {
                  if (sel) setState(() => _queueFilter = 'at_hub');
                },
              ),
              ChoiceChip(
                label: Text('In Transit (${_queue.where((r) => r['parcel']?['status'] == 'IN_HUB_TRANSIT').length})'),
                selected: _queueFilter == 'in_transit',
                onSelected: (sel) {
                  if (sel) setState(() => _queueFilter = 'in_transit');
                },
              ),
            ],
          ),
          const SizedBox(height: 10),

          // Select All bar
          if (filtered.isNotEmpty)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(
                color: Colors.grey.shade100,
                borderRadius: BorderRadius.circular(8),
              ),
              child: Row(
                children: [
                  Checkbox(
                    value: allInFilteredSelected,
                    onChanged: (val) => _toggleSelectAll(),
                  ),
                  InkWell(
                    onTap: _toggleSelectAll,
                    child: Text(
                      allInFilteredSelected
                          ? 'Deselect All (${_selectedIds.length} selected)'
                          : 'Select All (${_selectedIds.length}/${filtered.length} selected)',
                      style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
                    ),
                  ),
                ],
              ),
            ),

          if (filtered.isEmpty && !_busy)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 24),
              child: Center(
                child: Text('No parcels found for this filter.', style: TextStyle(color: Colors.black45)),
              ),
            ),

          // Parcels List
          for (final row in filtered) ...[
            Builder(
              builder: (ctx) {
                final parcel = row['parcel'] as Map<String, dynamic>? ?? {};
                final pid = parcel['id'] as int?;
                final orderId = parcel['order_id']?.toString() ?? 'N/A';
                final status = parcel['status']?.toString() ?? 'N/A';
                final destHub = row['destinationHub'] ?? row['destination_hub'];
                final destName = destHub != null ? destHub['name']?.toString() : null;
                final isSelected = pid != null && _selectedIds.contains(pid);

                Color badgeColor = Colors.grey.shade200;
                Color badgeTextColor = Colors.grey.shade800;
                if (status == 'AT_SORTING_CENTER') {
                  badgeColor = const Color(0xFFCCFBF1);
                  badgeTextColor = const Color(0xFF0F766E);
                } else if (status == 'IN_HUB_TRANSIT') {
                  badgeColor = const Color(0xFFE0E7FF);
                  badgeTextColor = const Color(0xFF4338CA);
                }

                return Card(
                  margin: const EdgeInsets.symmetric(vertical: 4),
                  elevation: isSelected ? 2 : 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                    side: BorderSide(
                      color: isSelected ? const Color(0xFF0F766E) : Colors.grey.shade300,
                      width: isSelected ? 1.8 : 1,
                    ),
                  ),
                  child: ListTile(
                    contentPadding: const EdgeInsets.symmetric(horizontal: 10, vertical: 2),
                    leading: Checkbox(
                      value: isSelected,
                      activeColor: const Color(0xFF0F766E),
                      onChanged: pid == null
                          ? null
                          : (val) {
                              setState(() {
                                if (val == true) {
                                  _selectedIds.add(pid);
                                } else {
                                  _selectedIds.remove(pid);
                                }
                              });
                            },
                    ),
                    title: Row(
                      children: [
                        Text(orderId, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15)),
                        const SizedBox(width: 8),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(
                            color: badgeColor,
                            borderRadius: BorderRadius.circular(4),
                          ),
                          child: Text(
                            status,
                            style: TextStyle(color: badgeTextColor, fontWeight: FontWeight.bold, fontSize: 10),
                          ),
                        ),
                      ],
                    ),
                    subtitle: destName != null
                        ? Text('Destination: $destName', style: const TextStyle(fontSize: 12, color: Colors.black54))
                        : null,
                    trailing: IconButton(
                      icon: const Icon(Icons.info_outline, size: 20, color: Colors.blueGrey),
                      tooltip: 'Inspect single parcel',
                      onPressed: () => _lookup(orderId),
                    ),
                    onTap: pid == null
                        ? null
                        : () {
                            setState(() {
                              if (isSelected) {
                                _selectedIds.remove(pid);
                              } else {
                                _selectedIds.add(pid);
                              }
                            });
                          },
                  ),
                );
              },
            ),
          ],

          if (_queue.length == 20)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 12),
              child: Text(
                'Showing the latest 20. Look up the exact reference or scan barcode to locate another parcel.',
                style: TextStyle(color: Colors.black45, fontSize: 12),
              ),
            ),
          const SizedBox(height: 60),
        ],
      ),
    );
  }
}
