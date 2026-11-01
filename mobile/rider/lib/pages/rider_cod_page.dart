import 'dart:math';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';

import '../services/api_config.dart';
import '../services/api_error.dart';
import '../services/rider_session.dart';
import '../services/rider_portal_api.dart';

class RiderCodPage extends StatefulWidget {
  final RiderSession session;
  final Future<XFile?> Function()? chooseEvidence;
  const RiderCodPage({super.key, required this.session, this.chooseEvidence});
  @override
  State<RiderCodPage> createState() => _RiderCodPageState();
}

class _RiderCodPageState extends State<RiderCodPage> {
  late final api = RiderPortalApi(widget.session);
  final amount = TextEditingController();
  bool loading = true,
      saving = false,
      remittance = false,
      physicallyConfirmed = false;
  List<Map<String, dynamic>> balances = [],
      candidates = [],
      collections = [],
      remittances = [];
  int? selectedId;
  Uint8List? evidence;
  String? evidenceName, error, success;
  String requestKey = newKey();
  static String newKey() {
    final random = Random.secure();
    final bytes = List.generate(16, (_) => random.nextInt(256));
    bytes[6] = (bytes[6] & 15) | 64;
    bytes[8] = (bytes[8] & 63) | 128;
    final value = bytes.map((n) => n.toRadixString(16).padLeft(2, '0')).join();
    return '${value.substring(0, 8)}-${value.substring(8, 12)}-${value.substring(12, 16)}-${value.substring(16, 20)}-${value.substring(20)}';
  }

  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void dispose() {
    amount.dispose();
    super.dispose();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final result = await api.request('rider/cod', ready: false);
      if (!mounted) return;
      final data = result['data'] as Map;
      setState(() {
        balances = List<Map<String, dynamic>>.from(data['balances'] as List);
        candidates = List<Map<String, dynamic>>.from(
          data['delivery_candidates'] as List,
        );
        collections = List<Map<String, dynamic>>.from(
          data['collections'] as List,
        );
        remittances = List<Map<String, dynamic>>.from(
          data['remittances'] as List,
        );
        if (!(remittance
            ? balances.any((b) => b['sorting_center_id'] == selectedId)
            : candidates.any((p) => p['id'] == selectedId))) {
          selectedId = null;
        }
      });
    } catch (failure) {
      if (mounted) {
        setState(
          () => error = failure is SessionFailure
              ? failure.message
              : 'Could not load COD cash records. Refresh to retry.',
        );
      }
    } finally {
      if (mounted) {
        setState(() => loading = false);
      }
    }
  }

  void mode(bool value) {
    if (saving) return;
    setState(() {
      remittance = value;
      selectedId = null;
      amount.clear();
      evidence = null;
      evidenceName = null;
      physicallyConfirmed = false;
      error = null;
      success = null;
      requestKey = newKey();
    });
  }

  Future<void> choose() async {
    if (saving) return;
    setState(() => saving = true);
    try {
      final file =
          await (widget.chooseEvidence?.call() ??
              ImagePicker().pickImage(source: ImageSource.gallery));
      if (file == null) return;
      if (await file.length() > 20 * 1024 * 1024) {
        throw const SessionFailure('Evidence must be at most 20 MB.');
      }
      final bytes = await file.readAsBytes();
      if (bytes.isEmpty) {
        throw const SessionFailure('Choose a real evidence image.');
      }
      if (mounted) {
        setState(() {
          evidence = bytes;
          evidenceName = file.name;
        });
      }
    } catch (failure) {
      if (mounted) {
        setState(
          () => error = failure is SessionFailure
              ? failure.message
              : 'Could not read evidence. Check photo permissions and retry.',
        );
      }
    } finally {
      if (mounted) {
        setState(() => saving = false);
      }
    }
  }

  Future<void> submit() async {
    if (saving || loading || selectedId == null || !physicallyConfirmed) return;
    if (!RegExp(r'^[0-9]{1,10}(\.[0-9]{1,2})?$').hasMatch(amount.text.trim()) ||
        double.tryParse(amount.text.trim()) == null ||
        double.parse(amount.text.trim()) <= 0 ||
        evidence == null) {
      setState(
        () => error =
            'Enter the actual positive cash amount and choose evidence.',
      );
      return;
    }
    setState(() {
      saving = true;
      error = null;
      success = null;
    });
    try {
      final token = await widget.session.tokens.read();
      if (token == null) throw const SessionFailure('Please sign in.');
      final path = remittance
          ? 'rider/cod/remittances'
          : 'rider/cod/collections/$selectedId';
      final request =
          http.MultipartRequest('POST', Uri.parse('$apiBaseUrl/$path'))
            ..headers['Authorization'] = 'Bearer $token'
            ..headers['Accept'] = 'application/json'
            ..fields['request_key'] = requestKey
            ..fields['amount'] = amount.text.trim()
            ..files.add(
              http.MultipartFile.fromBytes(
                'file',
                evidence!,
                filename: evidenceName ?? 'cash-evidence.jpg',
              ),
            );
      if (remittance) request.fields['sorting_center_id'] = '$selectedId';
      final response = await widget.session.sendMultipart(request);
      if (response.statusCode == 401) {
        await widget.session.tokens.clear();
        throw const SessionFailure('Your session expired. Please sign in.');
      }
      if (response.statusCode != 201) {
        throw SessionFailure(apiErrorMessage(response));
      }
      if (!mounted) return;
      setState(() {
        success = remittance
            ? 'Cash submitted. Logistics must confirm the actual amount received.'
            : 'Actual Buyer cash recorded. Remittance and delivery require separate confirmations.';
        amount.clear();
        selectedId = null;
        evidence = null;
        evidenceName = null;
        physicallyConfirmed = false;
        requestKey = newKey();
      });
      await load();
    } catch (failure) {
      if (mounted) {
        setState(
          () => error = failure is SessionFailure ? failure.message : 'Cash upload could not be confirmed. Refresh cash records before retrying.',
        );
      }
    } finally {
      if (mounted) {
        setState(() => saving = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('COD cash'),
      actions: [
        IconButton(
          tooltip: 'Refresh COD cash',
          onPressed: saving || loading ? null : load,
          icon: const Icon(Icons.refresh),
        ),
      ],
    ),
    body: ListView(
      padding: const EdgeInsets.all(16),
      children: [
        const Text(
          'Actual Buyer cash only. Separate from Seller shipment fees, earnings and payouts.',
        ),
        if (loading || saving) const LinearProgressIndicator(),
        if (error != null) Text(error!),
        if (success != null) Text(success!),
        for (final balance in balances)
          Card(
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Text(
                '${balance['name']}\nOutstanding cash: PHP ${balance['outstanding']}\nAwaiting confirmation: PHP ${balance['pending']}',
              ),
            ),
          ),
        Wrap(
          spacing: 8,
          children: [
            ChoiceChip(
              label: const Text('Buyer collection'),
              selected: !remittance,
              onSelected: saving ? null : (_) => mode(false),
            ),
            ChoiceChip(
              label: const Text('Cash remittance'),
              selected: remittance,
              onSelected: saving ? null : (_) => mode(true),
            ),
          ],
        ),
        const SizedBox(height: 12),
        DropdownButtonFormField<int>(
          key: ValueKey('$remittance:$selectedId'),
          initialValue: selectedId,
          isExpanded: true,
          decoration: InputDecoration(
            labelText: remittance
                ? 'Receiving Logistics'
                : 'Delivered / active COD parcel',
          ),
          items: remittance
              ? balances
                    .map(
                      (b) => DropdownMenuItem(
                        value: b['sorting_center_id'] as int,
                        child: Text('${b['name']}'),
                      ),
                    )
                    .toList()
              : candidates
                    .map(
                      (p) => DropdownMenuItem(
                        value: p['id'] as int,
                        child: Text('${p['order_id']}'),
                      ),
                    )
                    .toList(),
          onChanged: saving
              ? null
              : (value) => setState(() {
                  selectedId = value;
                  physicallyConfirmed = false;
                }),
        ),
        TextField(
          controller: amount,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          enabled: !saving,
          decoration: const InputDecoration(labelText: 'Actual cash amount'),
        ),
        const SizedBox(height: 12),
        OutlinedButton(
          onPressed: saving ? null : choose,
          child: const Text('Choose cash evidence'),
        ),
        if (evidenceName != null) Text('Evidence selected: $evidenceName'),
        CheckboxListTile(
          contentPadding: EdgeInsets.zero,
          value: physicallyConfirmed,
          onChanged: saving
              ? null
              : (value) => setState(() => physicallyConfirmed = value == true),
          title: Text(
            remittance
                ? 'I physically submitted this cash to Logistics.'
                : 'I actually collected this cash from the Buyer.',
          ),
        ),
        FilledButton(
          onPressed:
              saving || loading || !physicallyConfirmed || selectedId == null
              ? null
              : submit,
          child: Text(
            remittance ? 'Submit cash for verification' : 'Record Buyer cash',
          ),
        ),
        const SizedBox(height: 20),
        const Text(
          'Collection history',
          style: TextStyle(fontWeight: FontWeight.bold),
        ),
        for (final row in collections)
          ListTile(
            title: Text('${row['parcel']?['order_id']} · PHP ${row['amount']}'),
            subtitle: Text('Collected ${row['collected_at']}'),
          ),
        const Text(
          'Remittance history',
          style: TextStyle(fontWeight: FontWeight.bold),
        ),
        for (final row in remittances)
          ListTile(
            title: Text('Remittance #${row['id']} · ${row['status']}'),
            subtitle: Text(
              'Submitted PHP ${row['submitted_amount']} · Logistics received ${row['received_amount'] == null ? 'Not confirmed' : 'PHP ${row['received_amount']}'}',
            ),
          ),
      ],
    ),
  );
}
