import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/shared/widgets/alona_button.dart';
import 'package:alona_mobile/shared/widgets/alona_text_field.dart';
import 'package:alona_mobile/shared/widgets/status_badge.dart';
import 'package:alona_mobile/features/logistics/models/logistics_models.dart';
import 'package:alona_mobile/features/logistics/services/logistics_service.dart';
import 'package:alona_mobile/features/logistics/presentation/workflow_widgets.dart';
import 'package:alona_mobile/features/logistics/presentation/hubs/hub_workspace_screen.dart';

class IncomingParcelsScreen extends StatefulWidget {
  const IncomingParcelsScreen({super.key});

  @override
  State<IncomingParcelsScreen> createState() => _IncomingParcelsScreenState();
}

class _IncomingParcelsScreenState extends State<IncomingParcelsScreen> {
  final _orderIdController = TextEditingController();
  bool _isLoading = false;
  bool _isMarking = false;
  String? _errorMessage;
  String? _successMessage;
  ParcelModel? _foundParcel;

  @override
  void dispose() {
    _orderIdController.dispose();
    super.dispose();
  }

  Future<void> _lookup() async {
    if (_isLoading || _isMarking) return;
    final orderId = _orderIdController.text.trim();
    if (orderId.isEmpty) return;

    setState(() {
      _isLoading = true;
      _errorMessage = null;
      _successMessage = null;
      _foundParcel = null;
    });

    try {
      final parcel = await LogisticsService.lookupIncoming(orderId);
      if (!mounted) return;
      setState(() {
        _foundParcel = parcel;
        _isLoading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _errorMessage = e.toString();
        _isLoading = false;
      });
    }
  }

  Future<void> _markArrived() async {
    if (_isMarking || _isLoading || _foundParcel?.status != 'PICKED_UP') return;
    final orderId = _foundParcel!.orderId;

    setState(() {
      _isMarking = true;
      _errorMessage = null;
    });
    final confirmed = await confirmMovement(
      context,
      'Confirm $orderId is physically at this sorting center?',
    );
    if (!mounted) return;
    if (!confirmed) {
      setState(() => _isMarking = false);
      return;
    }

    try {
      await LogisticsService.markArrived(orderId);
      if (!mounted) return;
      setState(() {
        _isMarking = false;
        _successMessage =
            'Parcel #$orderId has arrived at sorting center and is ready for area sorting!';
        _foundParcel = null;
        _orderIdController.clear();
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isMarking = false;
        _errorMessage = e.toString();
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Incoming Parcel Check-In')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: AppColors.border),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Scan or Enter Order Reference',
                    style: GoogleFonts.figtree(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Check in parcels received from seller pickup requests to register them into the sorting workflow.',
                    style: GoogleFonts.figtree(
                      fontSize: 12,
                      color: AppColors.textMuted,
                    ),
                  ),
                  const SizedBox(height: 14),
                  Row(
                    children: [
                      Expanded(
                        child: AlonaTextField(
                          label: 'Order ID / Tracking Number',
                          hint: 'ITEM-123',
                          controller: _orderIdController,
                          prefixIcon: const Icon(
                            Icons.qr_code_scanner_rounded,
                            color: AppColors.primary,
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Padding(
                        padding: const EdgeInsets.only(top: 24),
                        child: SizedBox(
                          height: 48,
                          child: ElevatedButton(
                            onPressed: _isLoading ? null : _lookup,
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppColors.primary,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12),
                              ),
                            ),
                            child: const Text('Lookup'),
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),

            const SizedBox(height: 16),

            if (_errorMessage != null)
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: const Color(0xFFFEE2E2),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: const Color(0xFFFCA5A5)),
                ),
                child: Row(
                  children: [
                    const Icon(
                      Icons.error_outline,
                      color: AppColors.error,
                      size: 20,
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        _errorMessage!,
                        style: GoogleFonts.figtree(
                          color: AppColors.error,
                          fontSize: 13,
                        ),
                      ),
                    ),
                  ],
                ),
              ),

            if (_successMessage != null)
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: const Color(0xFFF0FDF4),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: const Color(0xFFBBF7D0)),
                ),
                child: Row(
                  children: [
                    const Icon(
                      Icons.check_circle_rounded,
                      color: AppColors.success,
                      size: 20,
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        _successMessage!,
                        style: GoogleFonts.figtree(
                          color: AppColors.success,
                          fontSize: 13,
                        ),
                      ),
                    ),
                  ],
                ),
              ),

            if (_foundParcel != null) ...[
              const SizedBox(height: 16),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: AppColors.primary, width: 1.5),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          'Parcel Found',
                          style: GoogleFonts.figtree(
                            fontSize: 16,
                            fontWeight: FontWeight.w800,
                            color: AppColors.primary,
                          ),
                        ),
                        StatusBadge(status: _foundParcel!.status),
                      ],
                    ),
                    const Divider(height: 20),
                    _infoRow('Order ID', _foundParcel!.orderId),
                    const SizedBox(height: 8),
                    _infoRow('Destination', _foundParcel!.fullDeliveryAddress),
                    if (_foundParcel!.collectionAddress != null) ...[
                      const SizedBox(height: 8),
                      _infoRow(
                        'Origin / Pickup',
                        _foundParcel!.collectionAddress!,
                      ),
                    ],
                    const SizedBox(height: 20),
                    if (_foundParcel!.hubRoute != null)
                      AlonaButton(
                        text: 'Receive at the recorded hub',
                        onPressed: _isMarking
                            ? null
                            : () => Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (_) => const HubWorkspaceScreen(),
                                ),
                              ),
                      )
                    else
                      AlonaButton(
                        text: 'Confirm Parcel Arrived at Center',
                        icon: Icons.check_circle_outline,
                        isLoading: _isMarking,
                        onPressed:
                            _isLoading ||
                                _isMarking ||
                                _foundParcel!.status != 'PICKED_UP'
                            ? null
                            : _markArrived,
                      ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _infoRow(String label, String value) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: GoogleFonts.figtree(fontSize: 11, color: AppColors.textMuted),
        ),
        const SizedBox(height: 2),
        Text(
          value,
          style: GoogleFonts.figtree(
            fontSize: 13,
            fontWeight: FontWeight.w600,
            color: AppColors.textPrimary,
          ),
        ),
      ],
    );
  }
}
