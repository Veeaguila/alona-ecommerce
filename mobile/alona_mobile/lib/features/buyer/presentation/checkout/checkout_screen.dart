import 'dart:convert';

import 'package:alona_mobile/core/utils/request_key.dart';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/core/network/api_client.dart';
import 'package:alona_mobile/core/services/auth_state.dart';
import 'package:alona_mobile/core/utils/currency_formatter.dart';
import 'package:alona_mobile/shared/widgets/alona_button.dart';
import 'package:alona_mobile/shared/widgets/network_image_box.dart';
import 'package:alona_mobile/features/buyer/models/buyer_models.dart';
import 'package:alona_mobile/features/buyer/services/buyer_service.dart';
import 'package:alona_mobile/features/buyer/presentation/auth/buyer_login_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/profile/address_management_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/checkout/order_confirmation_screen.dart';

class CheckoutScreen extends StatefulWidget {
  final List<int>? selectedCartItemIds;
  final DirectBuyModel? directBuy;

  const CheckoutScreen({super.key, this.selectedCartItemIds, this.directBuy});

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
  double _shippingFee = 0.0;
  double _discount = 0.0;
  double _total = 0.0;
  String? _appliedVoucherCode;

  @override
  void initState() {
    super.initState();
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
        _selectedAddress = _addresses.firstWhere(
          (a) => a.isDefault,
          orElse: () => _addresses.first,
        );
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
    if (!mounted || _isSubmitting) return false;
    final revision = ++_quoteRevision;
    setState(() {
      _quote = null;
      _isQuoting = true;
    });
    if (_selectedAddress == null) {
      setState(() => _isQuoting = false);
      return false;
    }
    try {
      final preview = widget.directBuy != null
          ? await BuyerService.previewCheckoutDirect(
              shippingAddress: _selectedAddress!.fullAddress,
              productId: widget.directBuy!.productId,
              variantId: widget.directBuy!.productVariantId,
              quantity: widget.directBuy!.quantity,
              shippingMethod: _shippingMethod,
              voucherCode: _appliedVoucherCode,
            )
          : await BuyerService.previewCheckout(
              shippingAddress: _selectedAddress!.fullAddress,
              cartItemIds: widget.selectedCartItemIds ?? [],
              shippingMethod: _shippingMethod,
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

  Future<void> _applyVoucher() async {
    if (_isSubmitting || _isQuoting) return;
    final code = _voucherController.text.trim();
    if (code.isEmpty) return;

    final previous = _appliedVoucherCode;
    setState(() => _appliedVoucherCode = code);
    final applied = await _recalculatePreview();
    if (!mounted) return;
    if (applied) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Voucher applied successfully!'),
          backgroundColor: AppColors.success,
        ),
      );
    } else {
      setState(() => _appliedVoucherCode = previous);
      await _recalculatePreview();
    }
  }

  Future<void> _submitOrder() async {
    if (_isSubmitting || _isQuoting || _quote == null) return;
    if (_selectedAddress == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Please add a delivery address to complete your order.',
          ),
          backgroundColor: AppColors.error,
        ),
      );
      await Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => const AddressManagementScreen()),
      );
      if (mounted) _loadInitialData();
      return;
    }

    setState(() {
      _isSubmitting = true;
    });

    final fingerprint = jsonEncode([
      _quote,
      _selectedAddress!.fullAddress,
      _shippingMethod,
      _paymentMethod,
      _appliedVoucherCode,
      _noteController.text.trim(),
    ]);
    if (_submissionFingerprint != fingerprint) {
      _submissionFingerprint = fingerprint;
      _requestKey = newRequestKey();
    }
    try {
      final res = widget.directBuy != null
          ? await BuyerService.placeOrderDirect(
              quote: _quote!,
              requestKey: _requestKey!,
              productId: widget.directBuy!.productId,
              variantId: widget.directBuy!.productVariantId,
              quantity: widget.directBuy!.quantity,
              shippingAddress: _selectedAddress!.fullAddress,
              shippingMethod: _shippingMethod,
              paymentMethod: _paymentMethod,
              buyerNote: _noteController.text.trim(),
              voucherCode: _appliedVoucherCode,
            )
          : await BuyerService.placeOrder(
              quote: _quote!,
              requestKey: _requestKey!,
              cartItemIds: widget.selectedCartItemIds ?? [],
              shippingAddress: _selectedAddress!.fullAddress,
              shippingMethod: _shippingMethod,
              paymentMethod: _paymentMethod,
              buyerNote: _noteController.text.trim(),
              voucherCode: _appliedVoucherCode,
            );

      if (!mounted) return;
      setState(() {
        _isSubmitting = false;
      });

      final orderData = res['order'] as Map<String, dynamic>;
      final order = OrderModel.fromJson(orderData);

      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => OrderConfirmationScreen(
            order: order,
            orders: (res['orders'] as List? ?? [orderData])
                .map((e) => OrderModel.fromJson(Map<String, dynamic>.from(e)))
                .toList(),
          ),
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
                    style: GoogleFonts.figtree(
                      fontSize: 16,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  TextButton(
                    onPressed: () async {
                      Navigator.pop(ctx);
                      await Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => const AddressManagementScreen(),
                        ),
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
                      title: Text(
                        addr.recipientName,
                        style: GoogleFonts.figtree(fontWeight: FontWeight.w600),
                      ),
                      subtitle: Text(addr.fullAddress),
                      trailing: isSelected
                          ? const Icon(Icons.check, color: AppColors.primary)
                          : null,
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
        body: Center(
          child: CircularProgressIndicator(color: AppColors.primary),
        ),
      );
    }

    if (!ApiClient.isAuthenticated ||
        (_error != null && _error!.toLowerCase().contains('unauthenticated'))) {
      return Scaffold(
        appBar: AppBar(title: const Text('Checkout')),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(
                  Icons.lock_outline_rounded,
                  size: 52,
                  color: AppColors.primary,
                ),
                const SizedBox(height: 16),
                Text(
                  'Sign In Required',
                  style: GoogleFonts.figtree(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  'Please sign in to proceed with your checkout.',
                  textAlign: TextAlign.center,
                  style: GoogleFonts.figtree(
                    fontSize: 13,
                    color: AppColors.textSecondary,
                  ),
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
                      MaterialPageRoute(
                        builder: (_) => BuyerLoginScreen(authState: auth),
                      ),
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
        appBar: AppBar(title: const Text('Checkout')),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                const Icon(
                  Icons.error_outline,
                  size: 48,
                  color: AppColors.error,
                ),
                const SizedBox(height: 16),
                Text(
                  _error!,
                  textAlign: TextAlign.center,
                  style: GoogleFonts.figtree(
                    fontSize: 14,
                    color: AppColors.textSecondary,
                  ),
                ),
                const SizedBox(height: 20),
                AlonaButton(text: 'Retry', onPressed: _loadInitialData),
              ],
            ),
          ),
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(
        title: const Text('Checkout'),
        actions: [
          IconButton(
            tooltip: 'Refresh checkout',
            onPressed: _isSubmitting || _isQuoting ? null : _recalculatePreview,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: SingleChildScrollView(
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
                            style: GoogleFonts.figtree(
                              fontSize: 14,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          if (widget.directBuy!.variantLabel != null &&
                              widget.directBuy!.variantLabel!.isNotEmpty) ...[
                            const SizedBox(height: 3),
                            Text(
                              'Variation: ${widget.directBuy!.variantLabel}',
                              style: GoogleFonts.figtree(
                                fontSize: 12,
                                color: AppColors.textMuted,
                              ),
                            ),
                          ],
                          const SizedBox(height: 4),
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text(
                                CurrencyFormatter.format(
                                  widget.directBuy!.unitPrice,
                                ),
                                style: GoogleFonts.figtree(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w800,
                                  color: AppColors.primary,
                                ),
                              ),
                              Text(
                                'x${widget.directBuy!.quantity}',
                                style: GoogleFonts.figtree(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
                                  color: AppColors.textSecondary,
                                ),
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
                      const Icon(
                        Icons.location_on_outlined,
                        color: AppColors.primary,
                        size: 20,
                      ),
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
                          if (_isSubmitting) return;
                          if (_addresses.isNotEmpty) {
                            _showAddressSelector();
                          } else {
                            await Navigator.push(
                              context,
                              MaterialPageRoute(
                                builder: (_) => const AddressManagementScreen(),
                              ),
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
                    if (_selectedAddress!.recipientName.isNotEmpty ||
                        _selectedAddress!.phone.isNotEmpty)
                      Row(
                        children: [
                          if (_selectedAddress!.recipientName.isNotEmpty)
                            Flexible(
                              child: Text(
                                _selectedAddress!.recipientName,
                                style: GoogleFonts.figtree(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                          const SizedBox(width: 8),
                          if (_selectedAddress!.phone.isNotEmpty)
                            Text(
                              '(${_selectedAddress!.phone})',
                              style: GoogleFonts.figtree(
                                fontSize: 13,
                                color: AppColors.textMuted,
                              ),
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
                      style: GoogleFonts.figtree(
                        color: AppColors.error,
                        fontSize: 13,
                      ),
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
                    style: GoogleFonts.figtree(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 12),
                  _buildShippingOption(
                    title: 'Standard Delivery',
                    subtitle: 'Arrives in 3-7 days',
                    fee: _shippingMethod == 'standard' && _quote != null
                        ? CurrencyFormatter.format(_shippingFee)
                        : 'Calculated',
                    value: 'standard',
                  ),
                  _buildShippingOption(
                    title: 'Express Delivery',
                    subtitle: 'Arrives in 1-3 days',
                    fee: _shippingMethod == 'express' && _quote != null
                        ? CurrencyFormatter.format(_shippingFee)
                        : 'Calculated',
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

            // Voucher Input Card
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
                      const Icon(
                        Icons.confirmation_number_outlined,
                        color: AppColors.accentGold,
                        size: 20,
                      ),
                      const SizedBox(width: 8),
                      Text(
                        'Platform or Seller Voucher',
                        style: GoogleFonts.figtree(
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: TextField(
                          controller: _voucherController,
                          enabled: !_isSubmitting,
                          textCapitalization: TextCapitalization.characters,
                          decoration: const InputDecoration(
                            hintText: 'Enter Voucher Code',
                            contentPadding: EdgeInsets.symmetric(
                              horizontal: 14,
                              vertical: 12,
                            ),
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
                            side: const BorderSide(
                              color: Color(0xFF334155),
                              width: 1,
                            ),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(8),
                            ),
                          ),
                          child: const Text('Apply'),
                        ),
                      ),
                    ],
                  ),
                  if (_appliedVoucherCode != null) ...[
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        const Icon(
                          Icons.check_circle,
                          color: AppColors.success,
                          size: 16,
                        ),
                        const SizedBox(width: 6),
                        Text(
                          'Applied voucher: $_appliedVoucherCode (-${CurrencyFormatter.format(_discount)})',
                          style: GoogleFonts.figtree(
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                            color: AppColors.success,
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
                    style: GoogleFonts.figtree(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 12),
                  _buildPaymentOption(
                    'Cash on Delivery (COD)',
                    'cod',
                    Icons.payments_outlined,
                  ),
                  _buildPaymentOption(
                    'GCash e-Wallet',
                    'gcash',
                    Icons.account_balance_wallet_outlined,
                  ),
                  _buildPaymentOption(
                    'Maya',
                    'maya',
                    Icons.credit_card_outlined,
                  ),
                  _buildPaymentOption(
                    'Credit / Debit Card',
                    'card',
                    Icons.credit_card_rounded,
                  ),
                ],
              ),
            ),

            const SizedBox(height: 16),

            // Buyer Note
            TextField(
              controller: _noteController,
              enabled: !_isSubmitting,
              decoration: const InputDecoration(
                hintText: 'Order note / instructions for seller (optional)',
                prefixIcon: Icon(
                  Icons.note_alt_outlined,
                  color: AppColors.textMuted,
                ),
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
                    style: GoogleFonts.figtree(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 12),
                  _summaryRow(
                    'Merchandise Subtotal',
                    CurrencyFormatter.format(_subtotal),
                  ),
                  const SizedBox(height: 8),
                  _summaryRow(
                    'Shipping Fee',
                    CurrencyFormatter.format(_shippingFee),
                  ),
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
                        style: GoogleFonts.figtree(
                          fontSize: 16,
                          fontWeight: FontWeight.w800,
                        ),
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
                  Text(
                    'Total Amount',
                    style: GoogleFonts.figtree(
                      fontSize: 12,
                      color: AppColors.textMuted,
                    ),
                  ),
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
                  text: _isQuoting ? 'Updating…' : 'Place Order',
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
        if (_isSubmitting) return;
        setState(() => _shippingMethod = value);
        _recalculatePreview();
      },
      child: Container(
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: isSelected
              ? AppColors.primaryLight.withValues(alpha: 0.4)
              : AppColors.surfaceAlt,
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
                  Text(
                    title,
                    style: GoogleFonts.figtree(
                      fontWeight: FontWeight.w700,
                      fontSize: 13,
                    ),
                  ),
                  Text(
                    subtitle,
                    style: GoogleFonts.figtree(
                      fontSize: 11,
                      color: AppColors.textMuted,
                    ),
                  ),
                ],
              ),
            ),
            Text(
              fee,
              style: GoogleFonts.figtree(
                fontWeight: FontWeight.w700,
                fontSize: 13,
                color: fee == 'Free'
                    ? AppColors.success
                    : AppColors.textPrimary,
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
      onTap: () {
        if (!_isSubmitting) setState(() => _paymentMethod = value);
      },
      child: Container(
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: isSelected
              ? AppColors.primaryLight.withValues(alpha: 0.4)
              : AppColors.surfaceAlt,
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
            Text(
              title,
              style: GoogleFonts.figtree(
                fontWeight: FontWeight.w600,
                fontSize: 13,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _summaryRow(String label, String value, {Color? valueColor}) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: GoogleFonts.figtree(
            fontSize: 13,
            color: AppColors.textSecondary,
          ),
        ),
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
