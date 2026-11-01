import 'package:flutter/material.dart';

class DeliverySuccessPage extends StatelessWidget {
  final String reference;
  final bool returnToScanner;
  const DeliverySuccessPage({
    super.key,
    required this.reference,
    this.returnToScanner = false,
  });
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Delivery recorded'),
      automaticallyImplyLeading: false,
    ),
    body: Center(
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 440),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(
                Icons.check_circle,
                color: Color(0xff008b8b),
                size: 80,
              ),
              const SizedBox(height: 24),
              Text(
                'Delivery confirmed',
                style: Theme.of(context).textTheme.headlineMedium
                    ?.copyWith(fontWeight: FontWeight.w700),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 12),
              SelectableText(
                reference,
                style: Theme.of(context).textTheme.titleMedium,
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 12),
              const Text(
                'Your photo proof and delivery status were saved by ALONA.',
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 28),
              SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: () => Navigator.pop(context),
                  child: Text(
                    returnToScanner ? 'Back to scanner' : 'Back to deliveries',
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    ),
  );
}
