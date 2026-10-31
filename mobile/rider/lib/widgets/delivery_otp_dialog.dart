import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

Future<String?> showDeliveryOtpDialog(BuildContext context, String reference) =>
    showDialog<String>(
      context: context,
      builder: (_) => DeliveryOtpDialog(reference: reference),
    );

class DeliveryOtpDialog extends StatefulWidget {
  final String reference;
  const DeliveryOtpDialog({super.key, required this.reference});
  @override
  State<DeliveryOtpDialog> createState() => _DeliveryOtpDialogState();
}

class _DeliveryOtpDialogState extends State<DeliveryOtpDialog> {
  final code = TextEditingController();
  final form = GlobalKey<FormState>();
  @override
  void dispose() {
    code.dispose();
    super.dispose();
  }

  void confirm() {
    if (form.currentState!.validate()) Navigator.pop(context, code.text);
  }

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: const Text('Confirm successful delivery'),
    content: SingleChildScrollView(
      child: Form(
        key: form,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Confirm receipt of ${widget.reference}. Ask the Buyer for the delivery OTP shown in their ALONA order. Your photo proof is also required.',
            ),
            const SizedBox(height: 16),
            TextFormField(
              controller: code,
              autofocus: true,
              autocorrect: false,
              enableSuggestions: false,
              keyboardType: TextInputType.number,
              inputFormatters: [
                FilteringTextInputFormatter.digitsOnly,
                LengthLimitingTextInputFormatter(6),
              ],
              decoration: const InputDecoration(
                labelText: 'Buyer delivery OTP',
                hintText: '6-digit code',
              ),
              validator: (value) => RegExp(r'^[0-9]{6}$').hasMatch(value ?? '')
                  ? null
                  : 'Enter the Buyer’s 6-digit delivery OTP.',
              onFieldSubmitted: (_) => confirm(),
            ),
          ],
        ),
      ),
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: const Text('Cancel'),
      ),
      FilledButton(onPressed: confirm, child: const Text('Confirm delivery')),
    ],
  );
}
