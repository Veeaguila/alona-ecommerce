import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/core/utils/date_formatter.dart';
import 'package:alona_mobile/shared/widgets/alona_text_field.dart';
import 'package:alona_mobile/shared/widgets/empty_state_view.dart';
import 'package:alona_mobile/features/logistics/presentation/proofs/protected_proof_image.dart';
import 'package:alona_mobile/features/logistics/models/logistics_models.dart';
import 'package:alona_mobile/features/logistics/services/logistics_service.dart';

class ProofReviewScreen extends StatefulWidget {
  const ProofReviewScreen({super.key, this.parcelId});
  final int? parcelId;

  @override
  State<ProofReviewScreen> createState() => _ProofReviewScreenState();
}

class _ProofReviewScreenState extends State<ProofReviewScreen> {
  final _parcelIdController = TextEditingController();
  bool _isLoading = false;
  String? _error;
  List<DeliveryProofModel> _proofs = [];

  @override
  void initState() {
    super.initState();
    if (widget.parcelId != null) {
      _parcelIdController.text = '${widget.parcelId}';
      _fetchProofs();
    }
  }

  @override
  void dispose() {
    _parcelIdController.dispose();
    super.dispose();
  }

  Future<void> _fetchProofs() async {
    final parcelIdStr = _parcelIdController.text.trim();
    final parcelId = int.tryParse(parcelIdStr);
    if (parcelId == null) {
      setState(() => _error = 'Please enter a valid numeric parcel ID.');
      return;
    }

    setState(() {
      _isLoading = true;
      _error = null;
      _proofs = [];
    });

    try {
      final list = await LogisticsService.getProofs(parcelId);
      if (!mounted) return;
      setState(() {
        _proofs = list;
        _isLoading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Proof of Delivery Review')),
      body: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: AlonaTextField(
                    label: 'Parcel Database ID',
                    hint: 'e.g. 1, 2, 10...',
                    controller: _parcelIdController,
                    keyboardType: TextInputType.number,
                  ),
                ),
                const SizedBox(width: 10),
                Padding(
                  padding: const EdgeInsets.only(top: 24),
                  child: SizedBox(
                    height: 48,
                    child: ElevatedButton(
                      onPressed: _isLoading ? null : _fetchProofs,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.primary,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      child: const Text('Search'),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 20),

            Expanded(
              child: _isLoading
                  ? const Center(
                      child: CircularProgressIndicator(
                        color: AppColors.primary,
                      ),
                    )
                  : _error != null
                  ? Center(child: Text(_error!))
                  : _proofs.isEmpty
                  ? const EmptyStateView(
                      icon: Icons.image_search_outlined,
                      title: 'No delivery proofs found',
                      message: 'Enter a valid delivered parcel ID to inspect proof photos and signatures.',
                    )
                  : ListView.separated(
                      itemCount: _proofs.length,
                      separatorBuilder: (_, _) => const SizedBox(height: 12),
                      itemBuilder: (context, index) {
                        final proof = _proofs[index];
                        return Container(
                          padding: const EdgeInsets.all(16),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(color: AppColors.border),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                mainAxisAlignment:
                                    MainAxisAlignment.spaceBetween,
                                children: [
                                  Container(
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 8,
                                      vertical: 4,
                                    ),
                                    decoration: BoxDecoration(
                                      color: AppColors.primaryLight,
                                      borderRadius: BorderRadius.circular(6),
                                    ),
                                    child: Text(
                                      proof.type,
                                      style: GoogleFonts.figtree(
                                        fontSize: 11,
                                        fontWeight: FontWeight.w700,
                                        color: AppColors.primary,
                                      ),
                                    ),
                                  ),
                                  if (proof.capturedAt != null)
                                    Text(
                                      DateFormatter.formatWithTime(
                                        DateTime.tryParse(proof.capturedAt!),
                                      ),
                                      style: GoogleFonts.figtree(
                                        fontSize: 11,
                                        color: AppColors.textMuted,
                                      ),
                                    ),
                                ],
                              ),
                              const SizedBox(height: 8),
                              Text(
                                'Rider: ${proof.riderName}',
                                style: GoogleFonts.figtree(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                              if (proof.notes != null) ...[
                                const SizedBox(height: 4),
                                Text(
                                  'Notes: ${proof.notes}',
                                  style: GoogleFonts.figtree(
                                    fontSize: 12,
                                    color: AppColors.textSecondary,
                                  ),
                                ),
                              ],
                              if (proof.filePath != null) ...[
                                const SizedBox(height: 12),
                                ProtectedProofImage(
                                  key: ValueKey(proof.id),
                                  proofId: proof.id,
                                ),
                              ],
                            ],
                          ),
                        );
                      },
                    ),
            ),
          ],
        ),
      ),
    );
  }
}
