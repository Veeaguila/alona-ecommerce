import 'package:flutter/material.dart';

import 'dart:math';

String newRequestKey() {
  final bytes = List<int>.generate(16, (_) => Random.secure().nextInt(256));
  bytes[6] = (bytes[6] & 15) | 64;
  bytes[8] = (bytes[8] & 63) | 128;
  final value = bytes.map((v) => v.toRadixString(16).padLeft(2, '0')).join();
  return '${value.substring(0, 8)}-${value.substring(8, 12)}-${value.substring(12, 16)}-${value.substring(16, 20)}-${value.substring(20)}';
}

Future<bool> confirmMovement(BuildContext context, String message) async =>
    await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Confirm physical movement'),
        content: Text(message),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('I confirm'),
          ),
        ],
      ),
    ) ??
    false;

Future<String?> requestText(
  BuildContext context, {
  required String title,
  int minLength = 10,
  int maxLength = 300,
}) async {
  return showDialog<String>(
    context: context,
    builder: (ctx) => _TextRequestDialog(
      title: title,
      minLength: minLength,
      maxLength: maxLength,
    ),
  );
}

class _TextRequestDialog extends StatefulWidget {
  const _TextRequestDialog({
    required this.title,
    required this.minLength,
    required this.maxLength,
  });
  final String title;
  final int minLength, maxLength;
  @override
  State<_TextRequestDialog> createState() => _TextRequestDialogState();
}

class _TextRequestDialogState extends State<_TextRequestDialog> {
  final controller = TextEditingController();
  @override
  void dispose() {
    controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text(widget.title),
    content: TextField(
      controller: controller,
      maxLength: widget.maxLength,
      minLines: 2,
      maxLines: 4,
      onChanged: (_) => setState(() {}),
      decoration: InputDecoration(
        helperText: 'At least ${widget.minLength} characters',
      ),
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: const Text('Cancel'),
      ),
      FilledButton(
        onPressed: controller.text.trim().length < widget.minLength
            ? null
            : () => Navigator.pop(context, controller.text.trim()),
        child: const Text('Continue'),
      ),
    ],
  );
}

class WorkflowError extends StatelessWidget {
  const WorkflowError(this.message, this.retry, {super.key});
  final String message;
  final VoidCallback retry;
  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(message),
          const SizedBox(height: 12),
          FilledButton(onPressed: retry, child: const Text('Refresh')),
        ],
      ),
    ),
  );
}
