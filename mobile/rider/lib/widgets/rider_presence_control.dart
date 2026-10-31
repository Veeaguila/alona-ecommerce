import 'package:flutter/material.dart';

import '../services/rider_presence_session.dart';
import '../services/rider_session.dart';
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
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Wrap(
            spacing: 12,
            runSpacing: 8,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              IconButton(
                tooltip: 'Pickup location sharing',
                icon: const Icon(Icons.my_location),
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
              Text(
                'Availability: ${presence.presence}',
                style: const TextStyle(fontWeight: FontWeight.w600),
              ),
              OutlinedButton(
                onPressed: presence.busy
                    ? null
                    : () => presence.setOnline(presence.presence != 'Online'),
                child: Text(
                  presence.presence == 'Online' ? 'Go Offline' : 'Go Online',
                ),
              ),
            ],
          ),
          const Text(
            'Online is refreshed while this app is active. A lost connection expires after 90 seconds.',
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
