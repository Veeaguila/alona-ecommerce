import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/core/network/api_client.dart';
import 'package:alona_mobile/core/services/auth_state.dart';
import 'package:alona_mobile/core/utils/currency_formatter.dart';
import 'package:alona_mobile/shared/widgets/alona_button.dart';
import 'package:alona_mobile/shared/widgets/empty_state_view.dart';
import 'package:alona_mobile/shared/widgets/network_image_box.dart';
import 'package:alona_mobile/features/buyer/models/buyer_models.dart';
import 'package:alona_mobile/features/buyer/services/buyer_service.dart';
import 'package:alona_mobile/features/buyer/presentation/auth/buyer_login_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/checkout/checkout_screen.dart';

class CartScreen extends StatefulWidget {
  const CartScreen({super.key});

  @override
  State<CartScreen> createState() => _CartScreenState();
}

class _CartScreenState extends State<CartScreen> {
  bool _isLoading = true;
  bool _isMutating = false;
  int _loadRevision = 0;
  String? _error;
  List<CartItemModel> _items = [];
  final Set<int> _selectedIds = {};

  @override
  void initState() {
    super.initState();
    _loadCart();
  }

  Future<void> _loadCart({bool preserveSelection = false}) async {
    if (!mounted) return;
    final revision = ++_loadRevision;
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final res = await BuyerService.getCart();
      if (!mounted || revision != _loadRevision) return;
      final items = res['items'] as List<CartItemModel>;
      final selected = Set<int>.from(_selectedIds);
      setState(() {
        _items = items;
        // Select all available items by default
        _selectedIds.clear();
        for (var item in items) {
          if (item.isInStock &&
              (!preserveSelection || selected.contains(item.id))) {
            _selectedIds.add(item.id);
          }
        }
        _isLoading = false;
      });
    } catch (e) {
      if (!mounted || revision != _loadRevision) return;
      setState(() {
        _error = e.toString();
        _isLoading = false;
      });
    }
  }

  Future<void> _updateQuantity(CartItemModel item, int newQty) async {
    if (newQty < 1 || _isMutating) return;
    setState(() => _isMutating = true);
    try {
      await BuyerService.updateCartItem(item.id, newQty);
      if (!mounted) return;
      await _loadCart(preserveSelection: true);
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: AppColors.error),
      );
    } finally {
      if (mounted) setState(() => _isMutating = false);
    }
  }

  Future<void> _removeItem(int id) async {
    if (_isMutating) return;
    setState(() => _isMutating = true);
    try {
      await BuyerService.removeCartItem(id);
      if (!mounted) return;
      setState(() {
        _items.removeWhere((i) => i.id == id);
        _selectedIds.remove(id);
      });
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: AppColors.error),
      );
    } finally {
      if (mounted) setState(() => _isMutating = false);
    }
  }

  double get _selectedTotal {
    double total = 0.0;
    for (var item in _items) {
      if (_selectedIds.contains(item.id)) {
        total += item.unitPrice * item.quantity;
      }
    }
    return total;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Shopping Cart (${_items.length})'),
        actions: [
          if (_items.isNotEmpty)
            TextButton(
              onPressed: () {
                if (_selectedIds.length == _items.length) {
                  setState(() => _selectedIds.clear());
                } else {
                  setState(() {
                    _selectedIds.clear();
                    _selectedIds.addAll(
                      _items.where((i) => i.isInStock).map((i) => i.id),
                    );
                  });
                }
              },
              child: Text(
                _selectedIds.length == _items.length
                    ? 'Deselect All'
                    : 'Select All',
                style: GoogleFonts.figtree(
                  fontWeight: FontWeight.w700,
                  color: AppColors.primary,
                ),
              ),
            ),
        ],
      ),
      body: _isLoading
          ? const Center(
              child: CircularProgressIndicator(color: AppColors.primary),
            )
          : (!ApiClient.isAuthenticated ||
                (_error != null &&
                    _error!.toLowerCase().contains('unauthenticated')))
          ? Padding(
              padding: const EdgeInsets.all(24),
              child: EmptyStateView(
                icon: Icons.lock_outline_rounded,
                title: 'Sign In Required',
                message: 'Please sign in or create an account to view your shopping cart and place orders.',
                buttonText: 'Sign In / Register',
                onButtonPressed: () async {
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
                    _loadCart();
                  }
                },
              ),
            )
          : _error != null
          ? Center(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 24),
                    child: Text(
                      _error!,
                      textAlign: TextAlign.center,
                      style: GoogleFonts.figtree(
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  AlonaButton(text: 'Retry', width: 120, onPressed: _loadCart),
                ],
              ),
            )
          : _items.isEmpty
          ? Padding(
              padding: const EdgeInsets.all(24),
              child: EmptyStateView(
                icon: Icons.remove_shopping_cart_outlined,
                title: 'Your cart is empty',
                message:
                    'Looks like you have not added anything to your cart yet.',
                buttonText: 'Start Shopping',
                onButtonPressed: () => Navigator.pop(context),
              ),
            )
          : ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: _items.length,
              separatorBuilder: (_, _) => const SizedBox(height: 12),
              itemBuilder: (context, index) {
                final item = _items[index];
                final isSelected = _selectedIds.contains(item.id);

                return Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(
                      color: isSelected ? AppColors.primary : AppColors.border,
                      width: isSelected ? 1.5 : 1,
                    ),
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Checkbox(
                        value: isSelected,
                        activeColor: AppColors.primary,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(4),
                        ),
                        onChanged: item.isInStock
                            ? (val) {
                                setState(() {
                                  if (val == true) {
                                    _selectedIds.add(item.id);
                                  } else {
                                    _selectedIds.remove(item.id);
                                  }
                                });
                              }
                            : null,
                      ),
                      NetworkImageBox(
                        imageUrl: item.imageUrl,
                        width: 72,
                        height: 72,
                        borderRadius: BorderRadius.circular(10),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              item.productName,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: GoogleFonts.figtree(
                                fontSize: 14,
                                fontWeight: FontWeight.w600,
                                color: AppColors.textPrimary,
                              ),
                            ),
                            if (item.variantLabel != null) ...[
                              const SizedBox(height: 4),
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 6,
                                  vertical: 2,
                                ),
                                decoration: BoxDecoration(
                                  color: AppColors.surfaceAlt,
                                  borderRadius: BorderRadius.circular(4),
                                ),
                                child: Text(
                                  item.variantLabel!,
                                  style: GoogleFonts.figtree(
                                    fontSize: 11,
                                    color: AppColors.textMuted,
                                  ),
                                ),
                              ),
                            ],
                            const SizedBox(height: 8),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Text(
                                  CurrencyFormatter.format(item.unitPrice),
                                  style: GoogleFonts.figtree(
                                    fontSize: 15,
                                    fontWeight: FontWeight.w800,
                                    color: AppColors.primary,
                                  ),
                                ),
                                // Quantity Controls
                                Container(
                                  decoration: BoxDecoration(
                                    border: Border.all(color: AppColors.border),
                                    borderRadius: BorderRadius.circular(6),
                                  ),
                                  child: Row(
                                    children: [
                                      GestureDetector(
                                        onTap: item.quantity > 1
                                            ? () => _updateQuantity(
                                                item,
                                                item.quantity - 1,
                                              )
                                            : () => _removeItem(item.id),
                                        child: Padding(
                                          padding: const EdgeInsets.all(4),
                                          child: Icon(
                                            item.quantity > 1
                                                ? Icons.remove
                                                : Icons.delete_outline,
                                            size: 16,
                                            color: AppColors.textMuted,
                                          ),
                                        ),
                                      ),
                                      Padding(
                                        padding: const EdgeInsets.symmetric(
                                          horizontal: 8,
                                        ),
                                        child: Text(
                                          '${item.quantity}',
                                          style: GoogleFonts.figtree(
                                            fontSize: 13,
                                            fontWeight: FontWeight.w700,
                                          ),
                                        ),
                                      ),
                                      GestureDetector(
                                        onTap: item.quantity < item.stock
                                            ? () => _updateQuantity(
                                                item,
                                                item.quantity + 1,
                                              )
                                            : null,
                                        child: const Padding(
                                          padding: EdgeInsets.all(4),
                                          child: Icon(
                                            Icons.add,
                                            size: 16,
                                            color: AppColors.textMuted,
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                );
              },
            ),
      bottomNavigationBar: _items.isNotEmpty
          ? Container(
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
                          'Total (${_selectedIds.length} items)',
                          style: GoogleFonts.figtree(
                            fontSize: 12,
                            color: AppColors.textMuted,
                          ),
                        ),
                        Text(
                          CurrencyFormatter.format(_selectedTotal),
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
                      width: 160,
                      child: AlonaButton(
                        text: 'Checkout',
                        onPressed: _selectedIds.isNotEmpty && !_isMutating
                            ? () async {
                                await Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) => CheckoutScreen(
                                      selectedCartItemIds: _selectedIds
                                          .toList(),
                                    ),
                                  ),
                                );
                                if (mounted) await _loadCart();
                              }
                            : null,
                      ),
                    ),
                  ],
                ),
              ),
            )
          : null,
    );
  }
}
