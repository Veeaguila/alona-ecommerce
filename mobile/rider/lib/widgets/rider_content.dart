import 'package:flutter/material.dart';

/// Centers scrollable content while preserving its existing viewport/controller.
EdgeInsets riderPagePadding(BuildContext context, {double maxWidth = 840}) {
  final size = MediaQuery.sizeOf(context);
  final safe = MediaQuery.paddingOf(context);
  final availableWidth = size.width - safe.left - safe.right;
  final gutter = ((availableWidth - maxWidth) / 2).clamp(20.0, double.infinity);
  return EdgeInsets.fromLTRB(
    gutter + safe.left,
    24,
    gutter + safe.right,
    32 + safe.bottom,
  );
}
