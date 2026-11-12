import 'package:flutter/material.dart';

import '../services/rider_presence_session.dart';
import '../services/rider_session.dart';
import '../theme/rider_theme.dart';
import 'rider_pickup_location.dart';

class RiderPresenceControl extends StatefulWidget {
  final RiderSession session;
  const RiderPresenceControl({super.key, required this.session});
  static final _sessions = Expando<RiderPresenceSession>();
  @override
  State<RiderPresenceControl> createState() => _RiderPresenceControlState();
}

class _RiderPresenceControlState extends State<RiderPresenceControl> {
  late final RiderPresenceSession presence =
      RiderPresenceControl._sessions[widget.session] ??= RiderPresenceSession(
        widget.session,
      );
  @override
  void initState() {
    super.initState();
    presence.addListener(_changed);
    presence.attach();
  }

  void _changed() {
    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    presence.removeListener(_changed);
    presence.detach();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Card(
    margin: EdgeInsets.zero,
    child: Padding(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              IconButton(
                tooltip: 'Pickup location sharing',
                icon: const Icon(Icons.my_location),
                color: RiderColors.teal,
                constraints: const BoxConstraints(minWidth: 36, minHeight: 40),
                padding: EdgeInsets.zero,
                onPressed: () => showDialog<void>(
                  context: context,
                  builder: (context) => AlertDialog(
                    title: const Text('Pickup location sharing'),
                    content: SingleChildScrollView(
                      child: RiderPickupLocation(session: widget.session),
                    ),
                    actions: [
                      TextButton(
                        onPressed: () => Navigator.pop(context),
                        child: const Text('Close location sharing'),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Availability',
                      style: TextStyle(fontSize: 11, color: RiderColors.textSecondary),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      presence.presence,
                      style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 10),
              OutlinedButton(
                style: OutlinedButton.styleFrom(
                  minimumSize: const Size(0, 40),
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  textStyle: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                onPressed: presence.busy
                    ? null
                    : () => presence.setOnline(presence.presence != 'Online'),
                child: Text(
                  presence.presence == 'Online' ? 'Go Offline' : 'Go Online',
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          const Text(
            'Online updates while this app is active. Connection expires after 90 seconds.',
            style: TextStyle(fontSize: 11, height: 1.4, color: RiderColors.textSecondary),
          ),
          if (presence.error != null && !presence.error!.contains('no longer available'))
            Text(
              presence.error!,
              style: TextStyle(color: Theme.of(context).colorScheme.error),
            ),
        ],
      ),
    ),
  );
}
