import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/features/buyer/models/buyer_models.dart';
import 'package:alona_mobile/features/buyer/services/buyer_service.dart';
import 'package:alona_mobile/features/buyer/presentation/cart/cart_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/messages/buyer_conversations_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/products/product_list_screen.dart';

class BuyerCategoriesScreen extends StatefulWidget {
  final List<CategoryModel>? initialCategories;

  const BuyerCategoriesScreen({super.key, this.initialCategories});

  @override
  State<BuyerCategoriesScreen> createState() => _BuyerCategoriesScreenState();
}

class _BuyerCategoriesScreenState extends State<BuyerCategoriesScreen> {
  List<CategoryModel> _categories = [];
  bool _isLoading = false;
  String _searchQuery = '';
  final _searchController = TextEditingController();
  int _selectedDepartmentIndex = 0;

  @override
  void initState() {
    super.initState();
    if (widget.initialCategories != null && widget.initialCategories!.isNotEmpty) {
      _categories = widget.initialCategories!;
    } else {
      _loadCategories();
    }
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _loadCategories() async {
    setState(() => _isLoading = true);
    try {
      final list = await BuyerService.getCategories();
      if (!mounted) return;
      setState(() {
        _categories = list;
        _isLoading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _isLoading = false);
    }
  }

  List<CategoryModel> get _departments {
    final roots = _categories.where((c) => c.parentId == null).toList();
    return roots.isNotEmpty ? roots : _categories;
  }

  List<CategoryModel> get _searchResults {
    if (_searchQuery.trim().isEmpty) return [];
    final q = _searchQuery.toLowerCase().trim();
    final results = <CategoryModel>[];
    for (final c in _categories) {
      if (c.name.toLowerCase().contains(q) || c.slug.toLowerCase().contains(q)) {
        results.add(c);
      }
      for (final sub in c.subcategories) {
        if (sub.name.toLowerCase().contains(q) || sub.slug.toLowerCase().contains(q)) {
          if (!results.any((r) => r.id == sub.id)) {
            results.add(sub);
          }
        }
      }
    }
    return results;
  }

  @override
  Widget build(BuildContext context) {
    final canPop = Navigator.canPop(context);
    final departments = _departments;
    final isSearching = _searchQuery.trim().isNotEmpty;
    final searchResults = _searchResults;

    return Scaffold(
      backgroundColor: AppColors.buyerBackground,
      appBar: AppBar(
        titleSpacing: canPop ? 0 : 16,
        backgroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        automaticallyImplyLeading: false,
        leading: canPop
            ? IconButton(
                icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF1F2937)),
                onPressed: () => Navigator.pop(context),
              )
            : null,
        title: Row(
          children: [
            // Left side: Search Bar matching Home layout
            Expanded(
              child: Container(
                height: 40,
                padding: const EdgeInsets.symmetric(horizontal: 14),
                decoration: BoxDecoration(
                  color: AppColors.background,
                  borderRadius: BorderRadius.circular(24),
                  border: Border.all(color: AppColors.border, width: 1),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.search_rounded, color: AppColors.mutedText, size: 19),
                    const SizedBox(width: 8),
                    Expanded(
                      child: TextField(
                        controller: _searchController,
                        onChanged: (val) => setState(() => _searchQuery = val),
                        style: GoogleFonts.figtree(
                          color: AppColors.textPrimary,
                          fontSize: 13,
                          fontWeight: FontWeight.w500,
                        ),
                        decoration: InputDecoration(
                          hintText: 'Search categories...',
                          hintStyle: GoogleFonts.figtree(
                            color: AppColors.textLight,
                            fontSize: 13,
                            fontWeight: FontWeight.w500,
                          ),
                          border: InputBorder.none,
                          enabledBorder: InputBorder.none,
                          focusedBorder: InputBorder.none,
                          isDense: true,
                          contentPadding: const EdgeInsets.symmetric(vertical: 10),
                        ),
                      ),
                    ),
                    if (_searchQuery.isNotEmpty)
                      GestureDetector(
                        onTap: () {
                          _searchController.clear();
                          setState(() => _searchQuery = '');
                        },
                        child: const Icon(Icons.close_rounded, color: AppColors.mutedText, size: 18),
                      ),
                  ],
                ),
              ),
            ),
            const SizedBox(width: 4),

            // Right side: Message and Cart icons
            IconButton(
              icon: const Icon(Icons.chat_bubble_outline_rounded, color: AppColors.textPrimary, size: 22),
              tooltip: 'Messages',
              onPressed: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const BuyerConversationsScreen()),
                );
              },
            ),
            IconButton(
              icon: const Icon(Icons.shopping_bag_outlined, color: AppColors.textPrimary, size: 24),
              tooltip: 'Cart',
              onPressed: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(builder: (_) => const CartScreen()),
                );
              },
            ),
          ],
        ),
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator(color: Color(0xFF087F8C)))
          : isSearching
              ? _buildSearchResultsView(searchResults)
              : _buildTwoPaneDepartmentView(departments),
    );
  }

  /// Search Results Grid View
  Widget _buildSearchResultsView(List<CategoryModel> results) {
    if (results.isEmpty) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.search_off_rounded, size: 52, color: Color(0xFF94A3B8)),
              const SizedBox(height: 12),
              Text(
                'No categories found matching "$_searchQuery"',
                style: GoogleFonts.figtree(fontSize: 14, color: const Color(0xFF64748B)),
              ),
            ],
          ),
        ),
      );
    }

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Found ${results.length} categories matching "$_searchQuery"',
            style: GoogleFonts.figtree(fontSize: 13, fontWeight: FontWeight.w700, color: const Color(0xFF475569)),
          ),
          const SizedBox(height: 12),
          GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2,
              crossAxisSpacing: 12,
              mainAxisSpacing: 12,
              childAspectRatio: 0.95,
            ),
            itemCount: results.length,
            itemBuilder: (context, index) => _buildCategoryCard(context, results[index]),
          ),
        ],
      ),
    );
  }

  /// Native Android Two-Pane Department Side Rail & Subcategory Grid
  Widget _buildTwoPaneDepartmentView(List<CategoryModel> departments) {
    if (departments.isEmpty) {
      return RefreshIndicator(
        onRefresh: _loadCategories,
        color: const Color(0xFF087F8C),
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(40),
          child: Center(
            child: Text(
              'No categories available.',
              style: GoogleFonts.figtree(color: const Color(0xFF64748B)),
            ),
          ),
        ),
      );
    }

    final safeIdx = _selectedDepartmentIndex.clamp(0, departments.length - 1);
    final activeDept = departments[safeIdx];
    final subcategories = activeDept.subcategories;

    return Row(
      children: [
        // Left Side-Rail: Vertical Departments Navigation
        Container(
          width: 98,
          decoration: const BoxDecoration(
            color: Color(0xFFF1F5F9),
            border: Border(right: BorderSide(color: Color(0xFFE2E8F0))),
          ),
          child: ListView.builder(
            itemCount: departments.length,
            padding: const EdgeInsets.symmetric(vertical: 8),
            itemBuilder: (context, index) {
              final dept = departments[index];
              final isSelected = index == safeIdx;

              return InkWell(
                onTap: () => setState(() => _selectedDepartmentIndex = index),
                child: Container(
                  padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
                  decoration: BoxDecoration(
                    color: isSelected ? Colors.white : Colors.transparent,
                    border: Border(
                      left: BorderSide(
                        color: isSelected ? const Color(0xFF087F8C) : Colors.transparent,
                        width: 3.5,
                      ),
                    ),
                  ),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Container(
                        width: 36,
                        height: 36,
                        decoration: BoxDecoration(
                          color: isSelected ? const Color(0xFFE8F7F6) : const Color(0xFFE2E8F0),
                          shape: BoxShape.circle,
                        ),
                        child: ClipOval(
                          child: Image.network(
                            dept.effectiveImageUrl,
                            fit: BoxFit.cover,
                            errorBuilder: (_, _, _) => const Icon(Icons.category_outlined, size: 18, color: Color(0xFF087F8C)),
                          ),
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        dept.name,
                        maxLines: 2,
                        textAlign: TextAlign.center,
                        overflow: TextOverflow.ellipsis,
                        style: GoogleFonts.figtree(
                          fontSize: 10.5,
                          fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
                          color: isSelected ? const Color(0xFF087F8C) : const Color(0xFF475569),
                          height: 1.2,
                        ),
                      ),
                    ],
                  ),
                ),
              );
            },
          ),
        ),

        // Right Pane: Active Department Header & Subcategory Grid
        Expanded(
          child: Container(
            color: Colors.white,
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Active Department Banner Card
                  InkWell(
                    onTap: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (_) => ProductListScreen(
                            categoryId: activeDept.id,
                            categoryName: activeDept.name,
                          ),
                        ),
                      );
                    },
                    borderRadius: BorderRadius.circular(16),
                    child: Container(
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        gradient: const LinearGradient(
                          colors: [Color(0xFFE8F7F6), Color(0xFFF0FDF4)],
                          begin: Alignment.topLeft,
                          end: Alignment.bottomRight,
                        ),
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: const Color(0xFFB2DFDB)),
                      ),
                      child: Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'DEPARTMENT',
                                  style: GoogleFonts.figtree(
                                    fontSize: 9.5,
                                    fontWeight: FontWeight.w900,
                                    letterSpacing: 1.2,
                                    color: const Color(0xFF087F8C),
                                  ),
                                ),
                                const SizedBox(height: 2),
                                Text(
                                  activeDept.name,
                                  style: GoogleFonts.figtree(
                                    fontSize: 16,
                                    fontWeight: FontWeight.w800,
                                    color: const Color(0xFF1E293B),
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Row(
                                  children: [
                                    Text(
                                      'Shop all products',
                                      style: GoogleFonts.figtree(
                                        fontSize: 11,
                                        fontWeight: FontWeight.w700,
                                        color: const Color(0xFF087F8C),
                                      ),
                                    ),
                                    const SizedBox(width: 4),
                                    const Icon(Icons.arrow_forward_rounded, size: 12, color: Color(0xFF087F8C)),
                                  ],
                                ),
                              ],
                            ),
                          ),
                          Container(
                            width: 50,
                            height: 50,
                            decoration: BoxDecoration(
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(color: Colors.white, width: 2),
                              boxShadow: const [
                                BoxShadow(color: Color(0x14000000), blurRadius: 4, offset: Offset(0, 2)),
                              ],
                            ),
                            child: ClipRRect(
                              borderRadius: BorderRadius.circular(10),
                              child: Image.network(
                                activeDept.effectiveImageUrl,
                                fit: BoxFit.cover,
                                errorBuilder: (_, _, _) => const Icon(Icons.category_outlined, color: Color(0xFF087F8C)),
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),

                  const SizedBox(height: 18),

                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        subcategories.isNotEmpty ? 'Subcategories' : 'Explore Products',
                        style: GoogleFonts.figtree(
                          fontSize: 14,
                          fontWeight: FontWeight.w800,
                          color: const Color(0xFF1E293B),
                        ),
                      ),
                      Text(
                        '${subcategories.length} subcategories',
                        style: GoogleFonts.figtree(
                          fontSize: 11,
                          color: const Color(0xFF64748B),
                        ),
                      ),
                    ],
                  ),

                  const SizedBox(height: 12),

                  if (subcategories.isEmpty)
                    Center(
                      child: Padding(
                        padding: const EdgeInsets.symmetric(vertical: 30),
                        child: Column(
                          children: [
                            const Icon(Icons.shopping_bag_outlined, size: 40, color: Color(0xFF94A3B8)),
                            const SizedBox(height: 10),
                            Text(
                              'Browse all ${activeDept.name} items directly.',
                              style: GoogleFonts.figtree(fontSize: 12, color: const Color(0xFF64748B)),
                            ),
                            const SizedBox(height: 12),
                            ElevatedButton(
                              onPressed: () {
                                Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) => ProductListScreen(
                                      categoryId: activeDept.id,
                                      categoryName: activeDept.name,
                                    ),
                                  ),
                                );
                              },
                              style: ElevatedButton.styleFrom(
                                backgroundColor: const Color(0xFF087F8C),
                                foregroundColor: Colors.white,
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                              ),
                              child: const Text('Browse Products'),
                            ),
                          ],
                        ),
                      ),
                    )
                  else
                    GridView.builder(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount: 2,
                        crossAxisSpacing: 10,
                        mainAxisSpacing: 12,
                        childAspectRatio: 0.86,
                      ),
                      itemCount: subcategories.length,
                      itemBuilder: (context, index) {
                        final sub = subcategories[index];
                        return _buildSubcategoryCard(context, sub);
                      },
                    ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }

  /// Subcategory Card in the Right Grid
  Widget _buildSubcategoryCard(BuildContext context, CategoryModel sub) {
    return InkWell(
      onTap: () {
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => ProductListScreen(
              categoryId: sub.id,
              categoryName: sub.name,
            ),
          ),
        );
      },
      borderRadius: BorderRadius.circular(14),
      child: Container(
        decoration: BoxDecoration(
          color: const Color(0xFFF8FAF9),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: const Color(0xFFE2E8F0)),
        ),
        padding: const EdgeInsets.all(10),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            Expanded(
              child: ClipRRect(
                borderRadius: BorderRadius.circular(10),
                child: Image.network(
                  sub.effectiveImageUrl,
                  width: double.infinity,
                  fit: BoxFit.cover,
                  errorBuilder: (_, _, _) => Container(
                    color: const Color(0xFFE8F7F6),
                    child: const Center(
                      child: Icon(Icons.category_outlined, color: Color(0xFF087F8C), size: 24),
                    ),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 8),
            Text(
              sub.name,
              maxLines: 2,
              textAlign: TextAlign.center,
              overflow: TextOverflow.ellipsis,
              style: GoogleFonts.figtree(
                fontSize: 11.5,
                fontWeight: FontWeight.w700,
                color: const Color(0xFF1E293B),
                height: 1.2,
              ),
            ),
            if (sub.productsCount > 0) ...[
              const SizedBox(height: 3),
              Text(
                '${sub.productsCount} items',
                style: GoogleFonts.figtree(
                  fontSize: 10,
                  fontWeight: FontWeight.w600,
                  color: const Color(0xFF087F8C),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  /// Full-size category card used in Search Results
  Widget _buildCategoryCard(BuildContext context, CategoryModel category) {
    return GestureDetector(
      onTap: () {
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => ProductListScreen(
              categoryId: category.id,
              categoryName: category.name,
            ),
          ),
        );
      },
      child: Container(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(16),
          boxShadow: const [
            BoxShadow(
              color: Color(0x0F000000),
              blurRadius: 8,
              offset: Offset(0, 3),
            ),
          ],
        ),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(16),
          child: Stack(
            fit: StackFit.expand,
            children: [
              Image.network(
                category.effectiveImageUrl,
                fit: BoxFit.cover,
                errorBuilder: (_, _, _) => Container(
                  color: const Color(0xFFE8F7F6),
                  child: const Center(
                    child: Icon(Icons.category_outlined, color: Color(0xFF087F8C), size: 36),
                  ),
                ),
              ),
              Container(
                decoration: const BoxDecoration(
                  gradient: LinearGradient(
                    colors: [Colors.black87, Colors.transparent],
                    begin: Alignment.bottomCenter,
                    end: Alignment.topCenter,
                  ),
                ),
              ),
              Positioned(
                bottom: 12,
                left: 12,
                right: 12,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      category.name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: GoogleFonts.figtree(
                        fontSize: 13,
                        fontWeight: FontWeight.w800,
                        color: Colors.white,
                      ),
                    ),
                    if (category.productsCount > 0)
                      Text(
                        '${category.productsCount} products',
                        style: GoogleFonts.figtree(
                          fontSize: 11,
                          fontWeight: FontWeight.w600,
                          color: const Color(0xFFE8F7F6),
                        ),
                      ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
