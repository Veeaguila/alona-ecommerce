import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/core/utils/currency_formatter.dart';
import 'package:alona_mobile/shared/widgets/empty_state_view.dart';
import 'package:alona_mobile/features/buyer/models/buyer_models.dart';
import 'package:alona_mobile/features/buyer/services/buyer_service.dart';

class VouchersScreen extends StatefulWidget {
  const VouchersScreen({super.key});

  @override
  State<VouchersScreen> createState() => _VouchersScreenState();
}

class _VouchersScreenState extends State<VouchersScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;
  bool _isLoading = true;
  String? _error;
  List<VoucherModel> _claimed = [];
  List<VoucherModel> _available = [];

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    _loadVouchers();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _loadVouchers() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final res = await BuyerService.getVouchers();
      if (!mounted) return;
      setState(() {
        _claimed = res['claimed'] as List<VoucherModel>;
        _available = res['available'] as List<VoucherModel>;
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

  Future<void> _claimVoucher(VoucherModel v) async {
    try {
      await BuyerService.claimVoucher(v.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Voucher claimed successfully!'), backgroundColor: AppColors.success),
      );
      _loadVouchers();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: AppColors.error),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Discounts & Vouchers'),
        bottom: TabBar(
          controller: _tabController,
          tabs: [
            Tab(text: 'My Vouchers (${_claimed.length})'),
            Tab(text: 'Discover (${_available.length})'),
          ],
        ),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
          : _error != null
              ? Center(child: Text(_error!))
              : TabBarView(
                  controller: _tabController,
                  children: [
                    _buildVoucherList(_claimed, isClaimedTab: true),
                    _buildVoucherList(_available, isClaimedTab: false),
                  ],
                ),
    );
  }

  Widget _buildVoucherList(List<VoucherModel> list, {required bool isClaimedTab}) {
    if (list.isEmpty) {
      return Padding(
        padding: const EdgeInsets.all(24),
        child: EmptyStateView(
          icon: Icons.confirmation_number_outlined,
          title: isClaimedTab ? 'No claimed vouchers' : 'No available vouchers right now',
          message: isClaimedTab ? 'Discover and claim active discount vouchers to save on your orders.' : 'Check back later for new promotional discounts.',
        ),
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: list.length,
      separatorBuilder: (_, _) => const SizedBox(height: 12),
      itemBuilder: (context, index) {
        final v = list[index];
        return Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: AppColors.border),
          ),
          child: Row(
            children: [
              Container(
                width: 60,
                height: 60,
                decoration: BoxDecoration(
                  color: AppColors.primaryLight,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Center(
                  child: Icon(Icons.local_activity_outlined, color: AppColors.primary, size: 28),
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      v.discountDescription,
                      style: GoogleFonts.figtree(
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                        color: AppColors.primary,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      'Min. Spend ${CurrencyFormatter.format(v.minSpend)}',
                      style: GoogleFonts.figtree(fontSize: 12, color: AppColors.textSecondary),
                    ),
                    if (v.expiresAt != null) ...[
                      const SizedBox(height: 2),
                      Text(
                        'Expires: ${v.expiresAt}',
                        style: GoogleFonts.figtree(fontSize: 11, color: AppColors.textLight),
                      ),
                    ],
                  ],
                ),
              ),
              if (!isClaimedTab)
                ElevatedButton(
                  onPressed: () => _claimVoucher(v),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.primary,
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  ),
                  child: const Text('Claim'),
                )
              else
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: AppColors.surfaceAlt,
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: Text(
                    v.code,
                    style: GoogleFonts.figtree(
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                      color: AppColors.textPrimary,
                    ),
                  ),
                ),
            ],
          ),
        );
      },
    );
  }
}
