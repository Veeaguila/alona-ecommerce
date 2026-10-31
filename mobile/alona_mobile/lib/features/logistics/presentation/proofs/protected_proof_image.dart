import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:alona_mobile/core/network/api_client.dart';

/// Fetch private proof bytes through the scoped API. Never use a public storage
/// URL or put the bearer token into an image URL/browser cache.
class ProtectedProofImage extends StatefulWidget {
  const ProtectedProofImage({super.key, required this.proofId});
  final int proofId;
  @override
  State<ProtectedProofImage> createState() => _ProtectedProofImageState();
}

class _ProtectedProofImageState extends State<ProtectedProofImage> {
  late Future<Uint8List> _bytes;
  @override
  void initState() {
    super.initState();
    _bytes = ApiClient.bytes(
      'api/sorting-center/proofs/${widget.proofId}/file',
    );
  }

  @override
  Widget build(BuildContext context) => FutureBuilder<Uint8List>(
    future: _bytes,
    builder: (context, snapshot) {
      if (snapshot.hasError) {
        return const Text(
          'Proof could not be loaded. Refresh and check access.',
        );
      }
      if (!snapshot.hasData) {
        return const Center(child: CircularProgressIndicator());
      }
      return Image.memory(
        snapshot.data!,
        height: 180,
        width: double.infinity,
        fit: BoxFit.contain,
        errorBuilder: (_, _, _) =>
            const Text('This proof is not a displayable image.'),
      );
    },
  );
}
