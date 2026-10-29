import 'package:flutter/material.dart';

class FailureReasonDialog extends StatefulWidget {
  const FailureReasonDialog({super.key});
  @override
  State<FailureReasonDialog> createState() => _FailureReasonDialogState();
}

class _FailureReasonDialogState extends State<FailureReasonDialog> {
  String? reason;
  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text(
      'Mark Delivery as Failed',
      style: TextStyle(fontWeight: FontWeight.bold),
    ),
    content: SingleChildScrollView(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Choose the delivery outcome:'),
          const SizedBox(height: 16),
          DropdownButtonFormField<String>(
            isExpanded: true,
            decoration: const InputDecoration(
              labelText: 'Reason',
              border: OutlineInputBorder(),
            ),
            items: const ['Buyer not home', 'Wrong address', 'Refused']
                .map(
                  (value) => DropdownMenuItem(value: value, child: Text(value)),
                )
                .toList(),
            onChanged: (value) => setState(() => reason = value),
          ),
          const SizedBox(height: 12),
          Text(
            reason == 'Refused'
                ? 'Refused delivery goes directly to Return to Seller. Logistics must arrange the physical return.'
                : 'The third failed attempt goes to Return to Seller. Recording a failure does not confirm a physical return.',
          ),
        ],
      ),
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.of(context).pop(),
        child: const Text('Cancel'),
      ),
      ElevatedButton(
        onPressed: reason == null
            ? null
            : () => Navigator.of(context).pop(reason),
        style: ElevatedButton.styleFrom(
          backgroundColor: const Color(0xffdc3545),
          foregroundColor: Colors.white,
        ),
        child: const Text('Confirm Failed'),
      ),
    ],
  );
}
