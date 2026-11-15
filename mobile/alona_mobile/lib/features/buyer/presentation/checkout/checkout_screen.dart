import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/core/network/api_client.dart';
import 'package:alona_mobile/core/services/auth_state.dart';
import 'package:alona_mobile/core/utils/currency_formatter.dart';
import 'package:alona_mobile/core/utils/request_key.dart';
import 'package:alona_mobile/shared/widgets/alona_button.dart';
import 'package:alona_mobile/shared/widgets/alona_curved_header.dart';
import 'package:alona_mobile/shared/widgets/network_image_box.dart';
import 'package:alona_mobile/features/buyer/models/buyer_models.dart';
import 'package:alona_mobile/features/buyer/services/buyer_service.dart';
import 'package:alona_mobile/features/buyer/presentation/auth/buyer_login_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/profile/address_management_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/checkout/order_confirmation_screen.dart';

class CheckoutScreen extends StatefulWidget {
  final List<int>? selectedCartItemIds;
  final DirectBuyModel? directBuy;

  const CheckoutScreen({
    super.key,
    this.selectedCartItemIds,
    this.directBuy,
  });

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  String? _error;
  List<AddressModel> _addresses = [];
  bool _isLoading = true;
  bool _isSubmitting = false;
  bool _isQuoting = false;
  int _quoteRevision = 0;
  Map<String, dynamic>? _quote;
  String? _requestKey, _submissionFingerprint;

  AddressModel? _selectedAddress;

  String _shippingMethod = 'standard';
  String _paymentMethod = 'cod';
  final TextEditingController _noteController = TextEditingController();
  final TextEditingController _voucherController = TextEditingController();

  double _subtotal = 0.0;
  double _shippingFee = 50.0;
  double _discount = 0.0;
  double _total = 0.0;
  String? _appliedVoucherCode;
  List<VoucherModel> _claimedVouchers = [];
  List<VoucherModel> _availableVouchers = [];
  bool _showManualVoucherInput = false;

  @override
  void initState() {
    super.initState();
    if (widget.directBuy != null) {
      _subtotal = widget.directBuy!.unitPrice * widget.directBuy!.quantity;
      _total = _subtotal + _shippingFee;
    }
    _loadInitialData();
  }

  @override
  void dispose() {
    _noteController.dispose();
    _voucherController.dispose();
    super.dispose();
  }

  Future<void> _loadInitialData() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final addresses = await BuyerService.getAddresses();
      if (!mounted) return;
      _addresses = addresses;
      if (_addresses.isNotEmpty) {
        _selectedAddress = _addresses.firstWhere((a) => a.isDefault, orElse: () => _addresses.first);
      }

      await _recalculatePreview();

      if (!mounted) return;
      setState(() {
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

  Future<bool> _recalculatePreview() async {
    final revision = ++_quoteRevision;
    setState(() {
      _quote = null;
      _isQuoting = true;
    });
    try {
      final preview = widget.directBuy != null
          ? await BuyerService.previewCheckoutDirect(
              productId: widget.directBuy!.productId,
              variantId: widget.directBuy!.productVariantId,
              quantity: widget.directBuy!.quantity,
              shippingMethod: _shippingMethod,
              shippingAddress: _selectedAddress?.fullAddress,
              voucherCode: _appliedVoucherCode,
            )
          : await BuyerService.previewCheckout(
              cartItemIds: widget.selectedCartItemIds ?? [],
              shippingMethod: _shippingMethod,
              shippingAddress: _selectedAddress?.fullAddress,
              voucherCode: _appliedVoucherCode,
            );

      if (!mounted || revision != _quoteRevision) return false;
      setState(() {
        _quote = preview;
        _isQuoting = false;
        _subtotal = parseDouble(preview['subtotal']);
        _shippingFee = parseDouble(preview['shipping_fee']);
        _discount = parseDouble(preview['discount']);
        _total = parseDouble(preview['total']);
      });
      return true;
    } catch (e) {
      if (!mounted || revision != _quoteRevision) return false;
      setState(() => _isQuoting = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: AppColors.error),
      );
      return false;
    }
  }

  Future<void> _selectAndApplyVoucher(VoucherModel voucher, {bool claimFirst = false}) async {
    if (_appliedVoucherCode == voucher.code) {
      setState(() {
        _appliedVoucherCode = null;
        _voucherController.clear();
      });
      await _recalculatePreview();
      return;
    }

    try {
      if (claimFirst && !voucher.isClaimed) {
        try {
          await BuyerService.claimVoucher(voucher.id);
        } catch (e) {
          final msg = e.toString().toLowerCase();
          if (!msg.contains('already claimed')) {
            rethrow;
          }
        }
        if (mounted) {
          setState(() {
            _availableVouchers.removeWhere((v) => v.id == voucher.id);
            if (!_claimedVouchers.any((v) => v.id == voucher.id)) {
              _claimedVouchers.insert(
                0,
                VoucherModel(
                  id: voucher.id,
                  code: voucher.code,
                  type: voucher.type,
                  value: voucher.value,
                  minSpend: voucher.minSpend,
                  maxDiscount: voucher.maxDiscount,
                  startsAt: voucher.startsAt,
                  expiresAt: voucher.expiresAt,
                  isClaimed: true,
                  isPlatform: voucher.isPlatform,
                  sellerName: voucher.sellerName,
                ),
              );
            }
          });
        }
      }

      _voucherController.text = voucher.code;
      await _applyVoucher();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: AppColors.error),
      );
    }
  }

  Future<void> _showVoucherPickerSheet() async {
    if (_claimedVouchers.isEmpty && _availableVouchers.isEmpty) {
      try {
        final vRes = await BuyerService.getVouchers();
        if (mounted) {
          setState(() {
            _claimedVouchers = vRes['claimed'] as List<VoucherModel>? ?? [];
            _availableVouchers = vRes['available'] as List<VoucherModel>? ?? [];
          });
        }
      } catch (_) {}
    }
    if (!mounted) return;
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
      ),
      builder: (ctx) {
        final allVouchers = [
          ..._claimedVouchers,
          ..._availableVouchers.where((av) => !_claimedVouchers.any((cv) => cv.id == av.id)),
        ];
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 20),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Center(
                  child: Container(
                    width: 40,
                    height: 4,
                    decoration: BoxDecoration(
                      color: AppColors.border,
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                ),
                const SizedBox(height: 14),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      'Select a Voucher',
                      style: GoogleFonts.figtree(
                        fontSize: 17,
                        fontWeight: FontWeight.w800,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    if (_appliedVoucherCode != null)
                      TextButton(
                        onPressed: () async {
                          Navigator.pop(ctx);
                          setState(() {
                            _appliedVoucherCode = null;
                            _voucherController.clear();
                          });
                          await _recalculatePreview();
                        },
                        child: Text(
                          'Remove Voucher',
                          style: GoogleFonts.figtree(
                            fontSize: 12.5,
                            fontWeight: FontWeight.w700,
                            color: AppColors.error,
                          ),
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 4),
                Text(
                  'Tap a voucher below to apply it directly to your order.',
                  style: GoogleFonts.figtree(fontSize: 12.5, color: AppColors.textMuted),
                ),
                const SizedBox(height: 12),
                if (allVouchers.isEmpty)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 28),
                    child: Center(
                      child: Text(
                        'No active vouchers available right now.',
                        style: GoogleFonts.figtree(fontSize: 13, color: AppColors.textMuted),
                      ),
                    ),
                  )
                else
                  ConstrainedBox(
                    constraints: BoxConstraints(
                      maxHeight: MediaQuery.of(ctx).size.height * 0.55,
                    ),
                    child: ListView.separated(
                      shrinkWrap: true,
                      itemCount: allVouchers.length,
                      separatorBuilder: (_, _) => const SizedBox(height: 10),
                      itemBuilder: (_, index) {
                        final v = allVouchers[index];
                        final isClaimed = v.isClaimed || _claimedVouchers.any((cv) => cv.id == v.id);
                        final isSelected = _appliedVoucherCode == v.code;
                        final meetsMinSpend = _subtotal >= v.minSpend;
                        return InkWell(
                          borderRadius: BorderRadius.circular(14),
                          onTap: () async {
                            Navigator.pop(ctx);
                            await _selectAndApplyVoucher(v, claimFirst: !isClaimed);
                          },
                          child: Container(
                            padding: const EdgeInsets.all(14),
                            decoration: BoxDecoration(
                              color: isSelected
                                  ? const Color(0xFFE8F7F6)
                                  : Colors.white,
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(
                                color: isSelected
                                    ? AppColors.primary
                                    : AppColors.border,
                                width: isSelected ? 1.5 : 1,
                              ),
                            ),
                            child: Row(
                              children: [
                                Container(
                                  width: 48,
                                  height: 48,
                                  decoration: BoxDecoration(
                                    color: AppColors.primaryLight,
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                  child: const Icon(
                                    Icons.confirmation_number_outlined,
                                    color: AppColors.primary,
                                    size: 24,
                                  ),
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Row(
                                        children: [
                                          Text(
                                            v.discountDescription,
                                            style: GoogleFonts.figtree(
                                              fontSize: 15,
                                              fontWeight: FontWeight.w800,
                                              color: AppColors.primary,
                                            ),
                                          ),
                                          const SizedBox(width: 8),
                                          Container(
                                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                            decoration: BoxDecoration(
                                              color: isClaimed
                                                  ? const Color(0xFFE8F7F6)
                                                  : const Color(0xFFFFF6DF),
                                              borderRadius: BorderRadius.circular(5),
                                            ),
                                            child: Text(
                                              isClaimed ? 'Claimed' : 'Available',
                                              style: GoogleFonts.figtree(
                                                fontSize: 10,
                                                fontWeight: FontWeight.w800,
                                                color: isClaimed
                                                    ? AppColors.primary
                                                    : const Color(0xFFB45309),
                                              ),
                                            ),
                                          ),
                                        ],
                                      ),
                                      const SizedBox(height: 2),
                                      Text(
                                        'Code: ${v.code} • Min. Spend ${CurrencyFormatter.format(v.minSpend)}',
                                        style: GoogleFonts.figtree(
                                          fontSize: 12,
                                          color: meetsMinSpend
                                              ? AppColors.textSecondary
                                              : AppColors.error,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                                  decoration: BoxDecoration(
                                    color: isSelected
                                        ? AppColors.primary
                                        : const Color(0xFFE8F7F6),
                                    borderRadius: BorderRadius.circular(8),
                                  ),
                                  child: Text(
                                    isSelected
                                        ? 'Selected'
                                        : (isClaimed ? 'Use' : 'Claim & Use'),
                                    style: GoogleFonts.figtree(
                                      fontSize: 12,
                                      fontWeight: FontWeight.w800,
                                      color: isSelected ? Colors.white : AppColors.primary,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        );
                      },
                    ),
                  ),
              ],
            ),
          ),
        );
      },
    );
  }

  Future<void> _applyVoucher() async {
    final code = _voucherController.text.trim();
    if (code.isEmpty) return;

    final previousCode = _appliedVoucherCode;
    try {
      if (widget.directBuy != null) {
        setState(() {
          _appliedVoucherCode = code;
        });
        if (!await _recalculatePreview()) {
          if (mounted) setState(() => _appliedVoucherCode = previousCode);
          return;
        }
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Voucher applied successfully!'),
            backgroundColor: AppColors.success,
          ),
        );
        return;
      }

      final res = await BuyerService.validateVoucher(
        code: code,
        cartItemIds: widget.selectedCartItemIds ?? [],
        shippingMethod: _shippingMethod,
      );

      if (res['valid'] == true) {
        if (!mounted) return;
        setState(() {
          _appliedVoucherCode = code;
        });
        if (!await _recalculatePreview()) {
          if (mounted) setState(() => _appliedVoucherCode = previousCode);
          return;
        }
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Voucher applied successfully!'),
            backgroundColor: AppColors.success,
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: AppColors.error),
      );
    }
  }

  Future<void> _submitOrder() async {
    if (_isSubmitting || _isQuoting || _quote == null) return;
    if (_selectedAddress == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please add a delivery address to complete your order.'), backgroundColor: AppColors.error),
      );
      await Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => const AddressManagementScreen()),
      );
      if (mounted) _loadInitialData();
      return;
    }

    final fingerprint = jsonEncode([
      widget.directBuy?.productId,
      widget.directBuy?.productVariantId,
      widget.directBuy?.quantity,
      widget.selectedCartItemIds,
      _selectedAddress!.fullAddress,
      _shippingMethod,
      _paymentMethod,
      _noteController.text.trim(),
      _appliedVoucherCode,
      _quote!['total'],
      _quote!['shipping_fee'],
      _quote!['expected_prices'],
    ]);
    if (_submissionFingerprint != fingerprint || _requestKey == null) {
      _submissionFingerprint = fingerprint;
      _requestKey = newRequestKey();
    }

    setState(() {
      _isSubmitting = true;
    });

    try {
      final res = widget.directBuy != null
          ? await BuyerService.placeOrderDirect(
              productId: widget.directBuy!.productId,
              variantId: widget.directBuy!.productVariantId,
              quantity: widget.directBuy!.quantity,
              shippingAddress: _selectedAddress!.fullAddress,
              shippingMethod: _shippingMethod,
              paymentMethod: _paymentMethod,
              buyerNote: _noteController.text.trim(),
              voucherCode: _appliedVoucherCode,
              quote: _quote!,
              requestKey: _requestKey!,
            )
          : await BuyerService.placeOrder(
              cartItemIds: widget.selectedCartItemIds ?? [],
              shippingAddress: _selectedAddress!.fullAddress,
              shippingMethod: _shippingMethod,
              paymentMethod: _paymentMethod,
              buyerNote: _noteController.text.trim(),
              voucherCode: _appliedVoucherCode,
              quote: _quote!,
              requestKey: _requestKey!,
            );

      if (!mounted) return;
      setState(() {
        _isSubmitting = false;
      });

      final orderData = res['order'] as Map<String, dynamic>;
      final order = OrderModel.fromJson(orderData);
      final orders = (res['orders'] as List? ?? [orderData])
          .map((e) => OrderModel.fromJson(Map<String, dynamic>.from(e)))
          .toList();

      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => OrderConfirmationScreen(order: order, orders: orders),
        ),
      );
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _isSubmitting = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: AppColors.error),
      );
    }
  }

  void _showAddressSelector() {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    'Select Address',
                    style: GoogleFonts.figtree(fontSize: 16, fontWeight: FontWeight.bold),
                  ),
                  TextButton(
                    onPressed: () async {
                      Navigator.pop(ctx);
                      await Navigator.push(
                        context,
                        MaterialPageRoute(builder: (_) => const AddressManagementScreen()),
                      );
                      if (mounted) _loadInitialData();
                    },
                    child: const Text('Manage'),
                  ),
                ],
              ),
              const Divider(),
              Flexible(
                child: ListView.builder(
                  shrinkWrap: true,
                  itemCount: _addresses.length,
                  itemBuilder: (context, index) {
                    final addr = _addresses[index];
                    final isSelected = _selectedAddress?.id == addr.id;
                    return ListTile(
                      title: Text(addr.recipientName, style: GoogleFonts.figtree(fontWeight: FontWeight.w600)),
                      subtitle: Text(addr.fullAddress),
                      trailing: isSelected ? const Icon(Icons.check, color: AppColors.primary) : null,
                      onTap: () {
                        setState(() => _selectedAddress = addr);
                        Navigator.pop(ctx);
                        _recalculatePreview();
                      },
                    );
                  },
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator(color: AppColors.primary)),
      );
    }

    if (!ApiClient.isAuthenticated || (_error != null && _error!.toLowerCase().contains('unauthenticated'))) {
      return Scaffold(
        appBar: AppBar(
          automaticallyImplyLeading: false,
          title: const Text('Checkout'),
        ),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(Icons.lock_outline_rounded, size: 52, color: AppColors.primary),
                const SizedBox(height: 16),
                Text(
                  'Sign In Required',
                  style: GoogleFonts.figtree(fontSize: 18, fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 8),
                Text(
                  'Please sign in to proceed with your checkout.',
                  textAlign: TextAlign.center,
                  style: GoogleFonts.figtree(fontSize: 13, color: AppColors.textSecondary),
                ),
                const SizedBox(height: 20),
                AlonaButton(
                  text: 'Sign In / Register',
                  onPressed: () async {
                    final auth = AuthState();
                    await auth.init();
                    if (!context.mounted) return;
                    final loggedIn = await Navigator.push<bool>(
                      context,
                      MaterialPageRoute(builder: (_) => BuyerLoginScreen(authState: auth)),
                    );
                    if (loggedIn == true && mounted) {
                      _loadInitialData();
                    }
                  },
                ),
              ],
            ),
          ),
        ),
      );
    }

    if (_error != null) {
      return Scaffold(
        appBar: AppBar(
          automaticallyImplyLeading: false,
          title: const Text('Checkout'),
        ),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(Icons.error_outline, size: 48, color: AppColors.error),
                const SizedBox(height: 16),
                Text(
                  _error!,
                  textAlign: TextAlign.center,
                  style: GoogleFonts.figtree(fontSize: 14, color: AppColors.textSecondary),
                ),
                const SizedBox(height: 20),
                AlonaButton(
                  text: 'Retry',
                  onPressed: _loadInitialData,
                ),
              ],
            ),
          ),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        automaticallyImplyLeading: false,
        backgroundColor: AlonaCurvedHeader.tealDark,
        flexibleSpace: const AlonaCurvedHeaderSurface(overlapHeight: 48),
        title: const Text('Checkout'),
      ),
      body: AlonaCurvedHeaderBody(
        overlapHeight: 48,
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
            if (widget.directBuy != null) ...[
              Container(
                padding: const EdgeInsets.all(14),
                margin: const EdgeInsets.only(bottom: 16),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: AppColors.border),
                ),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    NetworkImageBox(
                      imageUrl: widget.directBuy!.imageUrl,
                      width: 64,
                      height: 64,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            widget.directBuy!.productName,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: GoogleFonts.figtree(fontSize: 14, fontWeight: FontWeight.w700),
                          ),
                          if (widget.directBuy!.variantLabel != null && widget.directBuy!.variantLabel!.isNotEmpty) ...[
                            const SizedBox(height: 3),
                            Text(
                              'Variation: ${widget.directBuy!.variantLabel}',
                              style: GoogleFonts.figtree(fontSize: 12, color: AppColors.textMuted),
                            ),
                          ],
                          const SizedBox(height: 4),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text(
                                CurrencyFormatter.format(widget.directBuy!.unitPrice),
                                style: GoogleFonts.figtree(fontSize: 14, fontWeight: FontWeight.w800, color: AppColors.primary),
                              ),
                              Text(
                                'x${widget.directBuy!.quantity}',
                                style: GoogleFonts.figtree(fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.textSecondary),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ],

            // Delivery Address Card
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
                  Row(
                    children: [
                      const Icon(Icons.location_on_outlined, color: AppColors.primary, size: 20),
                      const SizedBox(width: 8),
                      Text(
                        'Delivery Address',
                        style: GoogleFonts.figtree(
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                          color: AppColors.textPrimary,
                        ),
                      ),
                      const Spacer(),
                      TextButton(
                        onPressed: () async {
                          if (_addresses.isNotEmpty) {
                            _showAddressSelector();
                          } else {
                            await Navigator.push(
                              context,
                              MaterialPageRoute(builder: (_) => const AddressManagementScreen()),
                            );
                            if (mounted) _loadInitialData();
                          }
                        },
                        child: Text(
                          _selectedAddress == null ? 'Add Address' : 'Change',
                          style: GoogleFonts.figtree(
                            fontSize: 13,
                            fontWeight: FontWeight.w700,
                            color: AppColors.primary,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const Divider(height: 16),
                  if (_selectedAddress != null) ...[
                    Row(
                      children: [
                        Text(
                          _selectedAddress!.recipientName,
                          style: GoogleFonts.figtree(fontSize: 14, fontWeight: FontWeight.w700),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          '(${_selectedAddress!.phone})',
                          style: GoogleFonts.figtree(fontSize: 13, color: AppColors.textMuted),
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      _selectedAddress!.fullAddress,
                      style: GoogleFonts.figtree(
                        fontSize: 13,
                        color: AppColors.textSecondary,
                        height: 1.3,
                      ),
                    ),
                  ] else ...[
                    Text(
                      'No address selected. Please add or select your shipping address.',
                      style: GoogleFonts.figtree(color: AppColors.error, fontSize: 13),
                    ),
                  ],
                ],
              ),
            ),

            const SizedBox(height: 16),

            // Shipping Method Card
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
                    'Shipping Option',
                    style: GoogleFonts.figtree(fontSize: 15, fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 12),
                  _buildShippingOption(
                    title: 'Standard Delivery',
                    subtitle: 'Arrives in 3-7 days',
                    fee: '₱50.00 / store',
                    value: 'standard',
                  ),
                  _buildShippingOption(
                    title: 'Express Delivery',
                    subtitle: 'Arrives in 1-3 days',
                    fee: '₱120.00 / store',
                    value: 'express',
                  ),
                  _buildShippingOption(
                    title: 'Store Pickup',
                    subtitle: 'Pick up directly at the store',
                    fee: 'Free',
                    value: 'pickup',
                  ),
                ],
              ),
            ),

            const SizedBox(height: 16),

            // Voucher Selection Card
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
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Row(
                        children: [
                          const Icon(Icons.confirmation_number_outlined, color: AppColors.accentGold, size: 20),
                          const SizedBox(width: 8),
                          Text(
                            'Platform or Seller Voucher',
                            style: GoogleFonts.figtree(fontSize: 15, fontWeight: FontWeight.w700),
                          ),
                        ],
                      ),
                      TextButton.icon(
                        onPressed: _showVoucherPickerSheet,
                        style: TextButton.styleFrom(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                          minimumSize: Size.zero,
                          tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                        ),
                        icon: const Icon(Icons.local_offer_outlined, size: 15, color: AppColors.primary),
                        label: Text(
                          _appliedVoucherCode != null ? 'Change' : 'Select Voucher',
                          style: GoogleFonts.figtree(
                            fontSize: 12.5,
                            fontWeight: FontWeight.w800,
                            color: AppColors.primary,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  // Quick-select voucher cards (claimed + available)
                  Builder(
                    builder: (context) {
                      final quickVouchers = [
                        ..._claimedVouchers,
                        ..._availableVouchers.where((av) => !_claimedVouchers.any((cv) => cv.id == av.id)),
                      ];
                      if (quickVouchers.isEmpty) {
                        return InkWell(
                          onTap: () => setState(() => _showManualVoucherInput = !_showManualVoucherInput),
                          borderRadius: BorderRadius.circular(12),
                          child: Container(
                            width: double.infinity,
                            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                            decoration: BoxDecoration(
                              color: const Color(0xFFF8FAF9),
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(color: AppColors.border),
                            ),
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text(
                                  _appliedVoucherCode != null
                                      ? 'Applied: $_appliedVoucherCode'
                                      : 'Tap to enter a promo code',
                                  style: GoogleFonts.figtree(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w600,
                                    color: AppColors.textSecondary,
                                  ),
                                ),
                                const Icon(Icons.chevron_right_rounded, color: AppColors.textMuted, size: 20),
                              ],
                            ),
                          ),
                        );
                      }
                      return SizedBox(
                        height: 76,
                        child: ListView.separated(
                          scrollDirection: Axis.horizontal,
                          itemCount: quickVouchers.length,
                          separatorBuilder: (_, _) => const SizedBox(width: 10),
                          itemBuilder: (context, index) {
                            final v = quickVouchers[index];
                            final isClaimed = v.isClaimed || _claimedVouchers.any((cv) => cv.id == v.id);
                            final isSelected = _appliedVoucherCode == v.code;
                            return InkWell(
                              borderRadius: BorderRadius.circular(12),
                              onTap: () => _selectAndApplyVoucher(v, claimFirst: !isClaimed),
                              child: Container(
                                width: 205,
                                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                                decoration: BoxDecoration(
                                  color: isSelected ? const Color(0xFFE8F7F6) : const Color(0xFFFDFBF7),
                                  borderRadius: BorderRadius.circular(12),
                                  border: Border.all(
                                    color: isSelected
                                        ? AppColors.primary
                                        : AppColors.accentGold.withValues(alpha: 0.45),
                                    width: isSelected ? 1.5 : 1,
                                  ),
                                ),
                                child: Row(
                                  children: [
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment: CrossAxisAlignment.start,
                                        mainAxisAlignment: MainAxisAlignment.center,
                                        children: [
                                          Row(
                                            children: [
                                              Flexible(
                                                child: Text(
                                                  v.discountDescription,
                                                  maxLines: 1,
                                                  overflow: TextOverflow.ellipsis,
                                                  style: GoogleFonts.figtree(
                                                    fontSize: 13.5,
                                                    fontWeight: FontWeight.w800,
                                                    color: AppColors.primary,
                                                  ),
                                                ),
                                              ),
                                              const SizedBox(width: 4),
                                              if (isClaimed)
                                                const Icon(
                                                  Icons.check_circle_rounded,
                                                  size: 13,
                                                  color: AppColors.primary,
                                                ),
                                            ],
                                          ),
                                          const SizedBox(height: 2),
                                          Text(
                                            '${v.code} • Min. ₱${v.minSpend.toInt()}',
                                            maxLines: 1,
                                            overflow: TextOverflow.ellipsis,
                                            style: GoogleFonts.figtree(
                                              fontSize: 11,
                                              color: AppColors.textSecondary,
                                              fontWeight: FontWeight.w600,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                    const SizedBox(width: 6),
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                      decoration: BoxDecoration(
                                        color: isSelected ? AppColors.primary : Colors.white,
                                        borderRadius: BorderRadius.circular(8),
                                        border: Border.all(color: AppColors.primary),
                                      ),
                                      child: Text(
                                        isSelected ? 'Used' : 'Use',
                                        style: GoogleFonts.figtree(
                                          fontSize: 11,
                                          fontWeight: FontWeight.w800,
                                          color: isSelected ? Colors.white : AppColors.primary,
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            );
                          },
                        ),
                      );
                    },
                  ),
                  const SizedBox(height: 8),
                  Align(
                    alignment: Alignment.centerLeft,
                    child: InkWell(
                      onTap: () => setState(() => _showManualVoucherInput = !_showManualVoucherInput),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(vertical: 2),
                        child: Text(
                          _showManualVoucherInput ? 'Hide manual code input' : 'Have another promo code? Enter manually',
                          style: GoogleFonts.figtree(
                            fontSize: 11.5,
                            fontWeight: FontWeight.w700,
                            color: AppColors.textMuted,
                          ),
                        ),
                      ),
                    ),
                  ),
                  if (_showManualVoucherInput) ...[
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Expanded(
                          child: TextField(
                            controller: _voucherController,
                            textCapitalization: TextCapitalization.characters,
                            decoration: const InputDecoration(
                              hintText: 'Enter Voucher Code',
                              contentPadding: EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                            ),
                          ),
                        ),
                        const SizedBox(width: 10),
                        SizedBox(
                          height: 48,
                          child: ElevatedButton(
                            onPressed: _applyVoucher,
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppColors.surfaceAlt,
                              foregroundColor: const Color(0xFF334155),
                              elevation: 0,
                              side: const BorderSide(color: Color(0xFF334155), width: 1),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                            ),
                            child: const Text('Apply'),
                          ),
                        ),
                      ],
                    ),
                  ],
                  if (_appliedVoucherCode != null) ...[
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        const Icon(Icons.check_circle, color: AppColors.success, size: 16),
                        const SizedBox(width: 6),
                        Expanded(
                          child: Text(
                            'Applied voucher: $_appliedVoucherCode (-${CurrencyFormatter.format(_discount)})',
                            style: GoogleFonts.figtree(
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                              color: AppColors.success,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ],
              ),
            ),

            const SizedBox(height: 16),

            // Payment Method Selector
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
                    'Payment Method',
                    style: GoogleFonts.figtree(fontSize: 15, fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 12),
                  _buildPaymentOption('Cash on Delivery (COD)', 'cod', Icons.payments_outlined),
                  _buildPaymentOption('GCash e-Wallet', 'gcash', Icons.account_balance_wallet_outlined),
                  _buildPaymentOption('Maya', 'maya', Icons.credit_card_outlined),
                  _buildPaymentOption('Credit / Debit Card', 'card', Icons.credit_card_rounded),
                  _buildPaymentOption('Bank Transfer', 'bank_transfer', Icons.account_balance_outlined),
                ],
              ),
            ),

            const SizedBox(height: 16),

            // Buyer Note
            TextField(
              controller: _noteController,
              decoration: const InputDecoration(
                hintText: 'Order note / instructions for seller (optional)',
                prefixIcon: Icon(Icons.note_alt_outlined, color: AppColors.textMuted),
              ),
            ),

            const SizedBox(height: 20),

            // Payment Summary
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
                    'Payment Details',
                    style: GoogleFonts.figtree(fontSize: 15, fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 12),
                  _summaryRow('Merchandise Subtotal', CurrencyFormatter.format(_subtotal)),
                  const SizedBox(height: 8),
                  _summaryRow('Shipping Fee', CurrencyFormatter.format(_shippingFee)),
                  if (_discount > 0) ...[
                    const SizedBox(height: 8),
                    _summaryRow(
                      'Voucher Discount',
                      '-${CurrencyFormatter.format(_discount)}',
                      valueColor: AppColors.success,
                    ),
                  ],
                  const Divider(height: 20),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'Total Payment',
                        style: GoogleFonts.figtree(fontSize: 16, fontWeight: FontWeight.w800),
                      ),
                      Text(
                        CurrencyFormatter.format(_total),
                        style: GoogleFonts.figtree(
                          fontSize: 20,
                          fontWeight: FontWeight.w800,
                          color: AppColors.primary,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 100),
          ],
        ),
        ),
      ),
      bottomNavigationBar: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        decoration: BoxDecoration(
          color: Colors.white,
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.06),
              blurRadius: 10,
              offset: const Offset(0, -4),
            ),
          ],
        ),
        child: SafeArea(
          child: Row(
            children: [
              Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Total Amount', style: GoogleFonts.figtree(fontSize: 12, color: AppColors.textMuted)),
                  Text(
                    CurrencyFormatter.format(_total),
                    style: GoogleFonts.figtree(
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                      color: AppColors.primary,
                    ),
                  ),
                ],
              ),
              const Spacer(),
              SizedBox(
                width: 170,
                child: AlonaButton(
                  text: 'Place Order',
                  isLoading: _isSubmitting,
                  onPressed: _quote == null || _isQuoting ? null : _submitOrder,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildShippingOption({
    required String title,
    required String subtitle,
    required String fee,
    required String value,
  }) {
    final isSelected = _shippingMethod == value;
    return GestureDetector(
      onTap: () {
        setState(() => _shippingMethod = value);
        _recalculatePreview();
      },
      child: Container(
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: isSelected ? AppColors.primaryLight.withValues(alpha: 0.4) : AppColors.surfaceAlt,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(
            color: isSelected ? AppColors.primary : AppColors.border,
            width: isSelected ? 1.5 : 1,
          ),
        ),
        child: Row(
          children: [
            Icon(
              isSelected ? Icons.radio_button_checked : Icons.radio_button_off,
              color: isSelected ? AppColors.primary : AppColors.textMuted,
              size: 20,
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: GoogleFonts.figtree(fontWeight: FontWeight.w700, fontSize: 13)),
                  Text(subtitle, style: GoogleFonts.figtree(fontSize: 11, color: AppColors.textMuted)),
                ],
              ),
            ),
            Text(
              fee,
              style: GoogleFonts.figtree(
                fontWeight: FontWeight.w700,
                fontSize: 13,
                color: fee == 'Free' ? AppColors.success : AppColors.textPrimary,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPaymentOption(String title, String value, IconData icon) {
    final isSelected = _paymentMethod == value;
    return GestureDetector(
      onTap: () => setState(() => _paymentMethod = value),
      child: Container(
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: isSelected ? AppColors.primaryLight.withValues(alpha: 0.4) : AppColors.surfaceAlt,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(
            color: isSelected ? AppColors.primary : AppColors.border,
            width: isSelected ? 1.5 : 1,
          ),
        ),
        child: Row(
          children: [
            Icon(
              isSelected ? Icons.radio_button_checked : Icons.radio_button_off,
              color: isSelected ? AppColors.primary : AppColors.textMuted,
              size: 20,
            ),
            const SizedBox(width: 10),
            Icon(icon, size: 20, color: AppColors.textPrimary),
            const SizedBox(width: 10),
            Text(title, style: GoogleFonts.figtree(fontWeight: FontWeight.w600, fontSize: 13)),
          ],
        ),
      ),
    );
  }

  Widget _summaryRow(String label, String value, {Color? valueColor}) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: GoogleFonts.figtree(fontSize: 13, color: AppColors.textSecondary)),
        Text(
          value,
          style: GoogleFonts.figtree(
            fontSize: 14,
            fontWeight: FontWeight.w600,
            color: valueColor ?? AppColors.textPrimary,
          ),
        ),
      ],
    );
  }
}
