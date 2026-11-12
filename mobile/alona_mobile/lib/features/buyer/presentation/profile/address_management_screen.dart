import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/shared/widgets/alona_button.dart';
import 'package:alona_mobile/shared/widgets/alona_text_field.dart';
import 'package:alona_mobile/shared/widgets/empty_state_view.dart';
import 'package:alona_mobile/features/buyer/models/buyer_models.dart';
import 'package:alona_mobile/features/buyer/services/buyer_service.dart';

class AddressManagementScreen extends StatefulWidget {
  const AddressManagementScreen({super.key});

  @override
  State<AddressManagementScreen> createState() => _AddressManagementScreenState();
}

class _AddressManagementScreenState extends State<AddressManagementScreen> {
  bool _isLoading = true;
  String? _error;
  List<AddressModel> _addresses = [];

  @override
  void initState() {
    super.initState();
    _loadAddresses();
  }

  Future<void> _loadAddresses() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final addresses = await BuyerService.getAddresses();
      if (!mounted) return;
      setState(() {
        _addresses = addresses;
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

  Future<void> _setDefault(int id) async {
    try {
      await BuyerService.setDefaultAddress(id);
      _loadAddresses();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString()), backgroundColor: AppColors.error),
      );
    }
  }

  Future<void> _deleteAddress(int id) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete Address'),
        content: const Text('Are you sure you want to delete this delivery address?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () => Navigator.pop(ctx, true),
            style: ElevatedButton.styleFrom(backgroundColor: AppColors.error),
            child: const Text('Delete'),
          ),
        ],
      ),
    );

    if (confirm == true) {
      try {
        await BuyerService.deleteAddress(id);
        _loadAddresses();
      } catch (e) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString()), backgroundColor: AppColors.error),
        );
      }
    }
  }

  void _showAddressDialog([AddressModel? existing]) {
    final nameCtrl = TextEditingController(text: existing?.recipientName ?? '');
    final phoneCtrl = TextEditingController(text: existing?.phone ?? '');
    final labelCtrl = TextEditingController(text: existing?.label ?? 'Home');
    final streetCtrl = TextEditingController(text: existing?.street ?? '');
    final brgyCtrl = TextEditingController(text: existing?.barangay ?? '');
    final cityCtrl = TextEditingController(text: existing?.city ?? '');
    final provCtrl = TextEditingController(text: existing?.province ?? '');
    bool isDefault = existing?.isDefault ?? false;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setSheetState) => Padding(
          padding: EdgeInsets.only(
            bottom: MediaQuery.of(ctx).viewInsets.bottom + 16,
            left: 20,
            right: 20,
            top: 20,
          ),
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      existing == null ? 'Add Delivery Address' : 'Edit Address',
                      style: GoogleFonts.figtree(fontSize: 18, fontWeight: FontWeight.w700),
                    ),
                    IconButton(icon: const Icon(Icons.close), onPressed: () => Navigator.pop(ctx)),
                  ],
                ),
                const SizedBox(height: 16),
                AlonaTextField(label: 'Full Name / Recipient', controller: nameCtrl),
                const SizedBox(height: 12),
                AlonaTextField(label: 'Contact Number (09XXXXXXXXX)', controller: phoneCtrl, keyboardType: TextInputType.phone),
                const SizedBox(height: 12),
                AlonaTextField(label: 'Address Label (e.g. Home, Office)', controller: labelCtrl),
                const SizedBox(height: 12),
                AlonaTextField(label: 'Street / Building / Unit', controller: streetCtrl),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(child: AlonaTextField(label: 'Barangay', controller: brgyCtrl)),
                    const SizedBox(width: 12),
                    Expanded(child: AlonaTextField(label: 'City / Municipality', controller: cityCtrl)),
                  ],
                ),
                const SizedBox(height: 12),
                AlonaTextField(label: 'Province', controller: provCtrl),
                const SizedBox(height: 12),
                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text('Set as default delivery address', style: GoogleFonts.figtree(fontSize: 13)),
                  value: isDefault,
                  activeColor: AppColors.primary,
                  onChanged: (val) => setSheetState(() => isDefault = val ?? false),
                ),
                const SizedBox(height: 16),
                AlonaButton(
                  text: existing == null ? 'Save Address' : 'Update Address',
                  onPressed: () async {
                    if (nameCtrl.text.isEmpty || phoneCtrl.text.isEmpty || cityCtrl.text.isEmpty || brgyCtrl.text.isEmpty || streetCtrl.text.isEmpty) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(content: Text('Please fill all required fields.'), backgroundColor: AppColors.error),
                      );
                      return;
                    }

                    final data = {
                      'label': labelCtrl.text.trim(),
                      'recipient_name': nameCtrl.text.trim(),
                      'phone': phoneCtrl.text.trim(),
                      'street': streetCtrl.text.trim(),
                      'barangay': brgyCtrl.text.trim(),
                      'city': cityCtrl.text.trim(),
                      'province': provCtrl.text.trim(),
                      'is_default': isDefault,
                    };

                    try {
                      if (existing == null) {
                        await BuyerService.addAddress(data);
                      } else {
                        await BuyerService.updateAddress(existing.id, data);
                      }
                      if (!ctx.mounted) return;
                      Navigator.pop(ctx);
                      _loadAddresses();
                    } catch (e) {
                      if (!ctx.mounted) return;
                      ScaffoldMessenger.of(ctx).showSnackBar(
                        SnackBar(content: Text(e.toString()), backgroundColor: AppColors.error),
                      );
                    }
                  },
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('My Addresses (${_addresses.length}/10)'),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
          : _error != null
              ? Center(child: Text(_error!))
              : _addresses.isEmpty
                  ? Padding(
                      padding: const EdgeInsets.all(24),
                      child: EmptyStateView(
                        icon: Icons.location_off_outlined,
                        title: 'No addresses saved',
                        message: 'Save a delivery address to checkout faster.',
                        buttonText: 'Add Address',
                        onButtonPressed: () => _showAddressDialog(),
                      ),
                    )
                  : ListView.separated(
                      padding: const EdgeInsets.all(16),
                      itemCount: _addresses.length,
                      separatorBuilder: (_, _) => const SizedBox(height: 12),
                      itemBuilder: (context, index) {
                        final addr = _addresses[index];
                        return Container(
                          padding: const EdgeInsets.all(16),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(
                              color: addr.isDefault ? AppColors.primary : AppColors.border,
                              width: addr.isDefault ? 1.5 : 1,
                            ),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                children: [
                                  Text(
                                    addr.recipientName,
                                    style: GoogleFonts.figtree(fontSize: 15, fontWeight: FontWeight.w700),
                                  ),
                                  const SizedBox(width: 8),
                                  Text(
                                    '(${addr.phone})',
                                    style: GoogleFonts.figtree(fontSize: 13, color: AppColors.textMuted),
                                  ),
                                  const Spacer(),
                                  if (addr.isDefault)
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                                      decoration: BoxDecoration(
                                        color: AppColors.primaryLight,
                                        borderRadius: BorderRadius.circular(6),
                                      ),
                                      child: Text(
                                        'Default',
                                        style: GoogleFonts.figtree(
                                          fontSize: 11,
                                          fontWeight: FontWeight.w700,
                                          color: AppColors.primary,
                                        ),
                                      ),
                                    ),
                                ],
                              ),
                              const SizedBox(height: 6),
                              Text(
                                addr.fullAddress,
                                style: GoogleFonts.figtree(fontSize: 13, color: AppColors.textSecondary, height: 1.3),
                              ),
                              const Divider(height: 20),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.end,
                                children: [
                                  if (!addr.isDefault)
                                    TextButton(
                                      onPressed: () => _setDefault(addr.id),
                                      child: const Text('Set as Default'),
                                    ),
                                  TextButton(
                                    onPressed: () => _showAddressDialog(addr),
                                    child: const Text('Edit'),
                                  ),
                                  IconButton(
                                    icon: const Icon(Icons.delete_outline, size: 20, color: AppColors.error),
                                    onPressed: () => _deleteAddress(addr.id),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        );
                      },
                    ),
      floatingActionButton: _addresses.length < 10
          ? FloatingActionButton.extended(
              onPressed: () => _showAddressDialog(),
              backgroundColor: AppColors.primary,
              foregroundColor: Colors.white,
              icon: const Icon(Icons.add),
              label: const Text('Add Address'),
            )
          : null,
    );
  }
}
