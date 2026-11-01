import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/shared/widgets/empty_state_view.dart';
import 'package:alona_mobile/features/buyer/models/buyer_models.dart';
import 'package:alona_mobile/features/buyer/services/buyer_service.dart';
import 'package:alona_mobile/features/buyer/presentation/home/widgets/product_card.dart';

class ProductListScreen extends StatefulWidget {
  final String? initialSearch;
  final int? categoryId;
  final String? categoryName;

  const ProductListScreen({
    super.key,
    this.initialSearch,
    this.categoryId,
    this.categoryName,
  });

  @override
  State<ProductListScreen> createState() => _ProductListScreenState();
}

class _ProductListScreenState extends State<ProductListScreen> {
  late TextEditingController _searchController;
  bool _isLoading = true;
  String? _error;
  List<ProductModel> _products = [];
  String _selectedSort = 'latest';

  @override
  void initState() {
    super.initState();
    _searchController = TextEditingController(text: widget.initialSearch ?? '');
    _fetchProducts();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _fetchProducts() async {
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final res = await BuyerService.getProducts(
        search: _searchController.text.trim(),
        categoryId: widget.categoryId,
        sort: _selectedSort,
      );
      setState(() {
        _products = res['products'] as List<ProductModel>;
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _error = e.toString();
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final title = widget.categoryName ?? (widget.initialSearch != null ? 'Search Results' : 'All Products');

    return Scaffold(
      appBar: AppBar(
        title: Text(title),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(60),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            child: TextField(
              controller: _searchController,
              onSubmitted: (_) => _fetchProducts(),
              decoration: InputDecoration(
                hintText: 'Search products, brands...',
                prefixIcon: const Icon(Icons.search, size: 20, color: AppColors.textMuted),
                suffixIcon: _searchController.text.isNotEmpty
                    ? IconButton(
                        icon: const Icon(Icons.clear, size: 18),
                        onPressed: () {
                          _searchController.clear();
                          _fetchProducts();
                        },
                      )
                    : null,
                contentPadding: const EdgeInsets.symmetric(vertical: 10),
              ),
            ),
          ),
        ),
      ),
      body: Column(
        children: [
          // Filter / Sort Bar
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            color: Colors.white,
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  _sortChip('Latest', 'latest'),
                  const SizedBox(width: 8),
                  _sortChip('Price: Low to High', 'price_low'),
                  const SizedBox(width: 8),
                  _sortChip('Price: High to Low', 'price_high'),
                  const SizedBox(width: 8),
                  _sortChip('Top Rated', 'rating'),
                ],
              ),
            ),
          ),
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator(color: AppColors.primary))
                : _error != null
                    ? Center(child: Text(_error!))
                    : _products.isEmpty
                        ? Padding(
                            padding: const EdgeInsets.all(24),
                            child: EmptyStateView(
                              icon: Icons.search_off_rounded,
                              title: 'No products found',
                              message: 'Try searching with different keywords or removing filters.',
                              buttonText: 'Reset Search',
                              onButtonPressed: () {
                                _searchController.clear();
                                _fetchProducts();
                              },
                            ),
                          )
                        : GridView.builder(
                            padding: const EdgeInsets.all(16),
                            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                              crossAxisCount: 2,
                              crossAxisSpacing: 12,
                              mainAxisSpacing: 12,
                              childAspectRatio: 0.65,
                            ),
                            itemCount: _products.length,
                            itemBuilder: (context, index) {
                              return ProductCard(product: _products[index]);
                            },
                          ),
          ),
        ],
      ),
    );
  }

  Widget _sortChip(String label, String value) {
    final isSelected = _selectedSort == value;
    return ChoiceChip(
      label: Text(
        label,
        style: GoogleFonts.figtree(
          fontSize: 13,
          fontWeight: isSelected ? FontWeight.w700 : FontWeight.w600,
          color: isSelected ? Colors.white : const Color(0xFF334155),
        ),
      ),
      selected: isSelected,
      selectedColor: AppColors.primary,
      backgroundColor: AppColors.surfaceAlt,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
      side: BorderSide(
        color: isSelected ? AppColors.primary : const Color(0xFF334155),
        width: 1.0,
      ),
      checkmarkColor: Colors.white,
      onSelected: (selected) {
        if (selected) {
          setState(() => _selectedSort = value);
          _fetchProducts();
        }
      },
    );
  }
}
