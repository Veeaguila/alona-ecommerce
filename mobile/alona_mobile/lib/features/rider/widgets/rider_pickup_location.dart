import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';

import '../services/rider_portal_api.dart';
import '../services/rider_session.dart';

class RiderPickupLocation extends StatefulWidget {
  final RiderSession session;
  const RiderPickupLocation({super.key, required this.session});
  @override
  State<RiderPickupLocation> createState() => _RiderPickupLocationState();
}

class _RiderPickupLocationState extends State<RiderPickupLocation> {
  bool busy = false;
  String? message;
  Future<void> share() async {
    if (busy) return;
    final consent = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Share current pickup location?'),
        content: const Text(
          'ALONA will use one device location for nearest pickup offers for 90 seconds. Go Online first. No background location is collected. You can keep using self-claim without sharing.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Share my location'),
          ),
        ],
      ),
    );
    if (consent != true || !mounted) return;
    setState(() {
      busy = true;
      message = null;
    });
    try {
      if (!await Geolocator.isLocationServiceEnabled()) {
        throw Exception('Enable device location services and try again.');
      }
      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      if (permission != LocationPermission.whileInUse &&
          permission != LocationPermission.always) {
        throw Exception(
          'Location permission was not granted. You can still use existing pickup options.',
        );
      }
      final point = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 20),
        ),
      );
      if (point.isMocked) {
        throw Exception(
          'A real device location is required for nearest pickup offers.',
        );
      }
      final result = await RiderPortalApi(widget.session).request(
        'rider/pickup-location',
        method: 'POST',
        body: {
          'latitude': point.latitude,
          'longitude': point.longitude,
          'accuracy': point.accuracy,
          'captured_at': point.timestamp.toUtc().millisecondsSinceEpoch ~/ 1000,
          'permission_confirmed': true,
        },
      );
      if (mounted) setState(() => message = result['message'] as String?);
    } catch (error) {
      if (mounted) {
        setState(
          () => message = error.toString().replaceFirst('Exception: ', ''),
        );
      }
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  Future<void> stop() async {
    if (busy) return;
    setState(() => busy = true);
    try {
      final result = await RiderPortalApi(widget.session)
          .request('rider/pickup-location', method: 'DELETE');
      if (mounted) setState(() => message = result['message'] as String?);
    } catch (error) {
      if (mounted) setState(() => message = error.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Wrap(
        spacing: 8,
        children: [
          OutlinedButton(
            onPressed: busy ? null : share,
            child: const Text('Share location for pickup offers'),
          ),
          TextButton(
            onPressed: busy ? null : stop,
            child: const Text('Stop sharing pickup location'),
          ),
        ],
      ),
      if (message != null) Text(message!),
    ],
  );
}
