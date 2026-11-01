import 'package:flutter/material.dart';

import '../widgets/rider_content.dart';

import '../services/registration_api.dart';
import '../services/rider_portal_api.dart';
import '../services/rider_session.dart';
import '../services/rider_session_guard.dart';

class RiderAddressEditPage extends StatefulWidget {
  final RiderSession session;
  final Map<String, dynamic> initial;
  const RiderAddressEditPage({
    super.key,
    required this.session,
    required this.initial,
  });
  @override
  State<RiderAddressEditPage> createState() => _RiderAddressEditPageState();
}

class _RiderAddressEditPageState extends State<RiderAddressEditPage>
    with RiderSessionGuard<RiderAddressEditPage> {
  @override
  RiderSession get riderSession => widget.session;
  late final directory = RegistrationApi(client: riderSession.client);
  late final api = RiderPortalApi(riderSession);
  final form = GlobalKey<FormState>();
  final street = TextEditingController(),
      house = TextEditingController(),
      subdivision = TextEditingController(),
      postal = TextEditingController();
  List<Map<String, dynamic>> regions = [],
      cities = [],
      provinces = [],
      barangays = [];
  String? region, city, province, barangay, error;
  bool loading = true, saving = false;
  int revision = 0;
  @override
  void initState() {
    super.initState();
    street.text = widget.initial['street']?.toString() ?? '';
    house.text = widget.initial['house_number']?.toString() ?? '';
    subdivision.text = widget.initial['subdivision']?.toString() ?? '';
    postal.text = widget.initial['postal_code']?.toString() ?? '';
    initialLoad();
  }

  @override
  void dispose() {
    street.dispose();
    house.dispose();
    subdivision.dispose();
    postal.dispose();
    super.dispose();
  }

  Future<void> initialLoad() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final list = await directory.directory('addresses/regions');
      if (!mounted) return;
      setState(() {
        regions = list;
        loading = false;
      });
      final existing = widget.initial['region_code']?.toString();
      if (list.any((r) => r['code'] == existing)) {
        await chooseRegion(existing, restore: true);
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          loading = false;
          error = 'Address directory unavailable. Retry to continue.';
        });
      }
    }
  }

  Future<void> chooseRegion(String? code, {bool restore = false}) async {
    final version = ++revision;
    setState(() {
      region = code;
      city = null;
      province = null;
      barangay = null;
      cities = [];
      provinces = [];
      barangays = [];
      loading = true;
      error = null;
    });
    try {
      final lists = await Future.wait([
        directory.directory('addresses/cities', query: {'parent': code!}),
        directory.directory('addresses/provinces', query: {'parent': code}),
      ]);
      if (!mounted || version != revision) return;
      setState(() {
        cities = lists[0];
        provinces = lists[1];
        loading = false;
      });
      final existing = widget.initial['city_code']?.toString();
      if (restore && cities.any((c) => c['code'] == existing)) {
        await chooseCity(existing, restore: true);
      }
    } catch (_) {
      if (mounted && version == revision) {
        setState(() {
          loading = false;
          error = 'Could not load cities. Select the region again to retry.';
        });
      }
    }
  }

  Future<void> chooseCity(String? code, {bool restore = false}) async {
    final version = ++revision;
    final selected = cities.where((c) => c['code'] == code).firstOrNull;
    setState(() {
      city = code;
      province = selected?['province_code']?.toString() ?? '';
      barangay = null;
      barangays = [];
      loading = true;
      error = null;
    });
    try {
      final list = await directory.directory(
        'addresses/barangays',
        query: {'parent': code!},
      );
      if (!mounted || version != revision) return;
      setState(() {
        barangays = list;
        loading = false;
        final existing = widget.initial['barangay_code']?.toString();
        if (restore && list.any((b) => b['code'] == existing)) {
          barangay = existing;
        }
      });
    } catch (_) {
      if (mounted && version == revision) {
        setState(() {
          loading = false;
          error = 'Could not load barangays. Select the city again to retry.';
        });
      }
    }
  }

  Future<void> save() async {
    if (saving || loading || !form.currentState!.validate()) return;
    setState(() {
      saving = true;
      error = null;
    });
    try {
      await sessionAction(
        () => api.request(
          'rider/profile',
          method: 'PATCH',
          ready: false,
          body: {
            'address': {
              'region_code': region,
              'province_code': province ?? '',
              'city_code': city,
              'barangay_code': barangay,
              'street': street.text.trim(),
              'house_number': house.text.trim(),
              'subdivision': subdivision.text.trim(),
              'postal_code': postal.text.trim(),
            },
          },
        ),
      );
      if (mounted) Navigator.pop(context, true);
    } catch (failure) {
      if (mounted) {
        setState(
          () => error = failure is SessionFailure ? failure.message : 'Address update could not be confirmed. Refresh your account before retrying.',
        );
      }
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  Widget select(
    String label,
    String? selected,
    List<Map<String, dynamic>> rows,
    ValueChanged<String?> onChanged,
  ) => DropdownButtonFormField<String>(
    key: ValueKey('$label-$selected-${rows.length}'),
    initialValue: selected,
    isExpanded: true,
    decoration: InputDecoration(labelText: label),
    items: rows.map((r) {
      var name = r['name'].toString();
      if (label == 'City / municipality') {
        final p = provinces
            .where((p) => p['code'] == r['province_code'])
            .firstOrNull;
        if (p != null) name = '$name, ${p['name']}';
      }
      return DropdownMenuItem<String>(
        value: r['code'].toString(),
        child: Text(name, maxLines: 2, overflow: TextOverflow.ellipsis),
      );
    }).toList(),
    onChanged: saving || loading ? null : onChanged,
    validator: (v) => v == null ? 'Choose $label.' : null,
  );
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Update address')),
    body: Form(
      key: form,
      child: ListView(
        padding: riderPagePadding(context, maxWidth: 640),
        children: [
          const Text(
            'Saving a changed address requires Logistics reapproval and pauses pickup and delivery access. Your vehicle and service coverage remain subject to their existing approval.',
          ),
          const SizedBox(height: 16),
          if (loading || saving) const LinearProgressIndicator(),
          select('Region', region, regions, (v) => chooseRegion(v)),
          const SizedBox(height: 12),
          select('City / municipality', city, cities, (v) => chooseCity(v)),
          const SizedBox(height: 12),
          select(
            'Barangay',
            barangay,
            barangays,
            (v) => setState(() => barangay = v),
          ),
          TextFormField(
            controller: house,
            enabled: !saving,
            decoration: const InputDecoration(labelText: 'House number'),
            maxLength: 100,
          ),
          TextFormField(
            controller: street,
            enabled: !saving,
            decoration: const InputDecoration(labelText: 'Street'),
            maxLength: 255,
            validator: (v) =>
                v == null || v.trim().isEmpty ? 'Enter a street.' : null,
          ),
          TextFormField(
            controller: subdivision,
            enabled: !saving,
            decoration: const InputDecoration(
              labelText: 'Subdivision (optional)',
            ),
            maxLength: 255,
          ),
          TextFormField(
            controller: postal,
            enabled: !saving,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(
              labelText: 'Postal code (optional)',
            ),
            maxLength: 4,
            validator: (v) =>
                v != null && v.isNotEmpty && !RegExp(r'^\d{4}$').hasMatch(v)
                ? 'Use a four-digit postal code.'
                : null,
          ),
          if (error != null)
            Text(error!, style: const TextStyle(color: Color(0xff9b3125))),
          if (regions.isEmpty && !loading)
            TextButton(
              onPressed: initialLoad,
              child: const Text('Retry address directory'),
            ),
          FilledButton(
            onPressed: loading || saving ? null : save,
            child: Text(saving ? 'Saving...' : 'Save address for review'),
          ),
        ],
      ),
    ),
  );
}
