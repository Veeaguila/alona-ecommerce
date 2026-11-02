import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/core/network/api_client.dart';
import 'package:alona_mobile/core/services/auth_state.dart';
import 'package:alona_mobile/features/buyer/services/buyer_service.dart';
import 'package:alona_mobile/features/buyer/presentation/auth/buyer_login_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/messages/buyer_conversations_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/orders/order_list_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/vouchers/vouchers_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/profile/address_management_screen.dart';
import 'package:alona_mobile/features/logistics/presentation/logistics_main_screen.dart';

class BuyerSettingsScreen extends StatefulWidget {
  final AuthState authState;

  const BuyerSettingsScreen({super.key, required this.authState});

  @override
  State<BuyerSettingsScreen> createState() => _BuyerSettingsScreenState();
}

class _BuyerSettingsScreenState extends State<BuyerSettingsScreen> {
  Map<String, String> _contactSettings = {
    'email': 'support@alona.ph',
    'phone': '+63 (02) 8888-ALONA',
    'hours': 'Mon – Sat: 8:00 AM – 8:00 PM PHT',
  };
  List<Map<String, dynamic>> _faqs = List<Map<String, dynamic>>.from(_defaultWebsiteFaqs);
  List<Map<String, dynamic>> _policies = List<Map<String, dynamic>>.from(_defaultWebsitePolicies);

  @override
  void initState() {
    super.initState();
    _loadLiveSupportInfo();
  }

  Future<void> _loadLiveSupportInfo() async {
    try {
      final res = await BuyerService.getSupportInfo();
      if (!mounted) return;
      setState(() {
        if (res['contact_settings'] is Map) {
          final cs = Map<String, dynamic>.from(res['contact_settings']);
          _contactSettings = {
            'email': cs['email']?.toString() ?? 'support@alona.ph',
            'phone': cs['phone']?.toString() ?? '+63 (02) 8888-ALONA',
            'hours': cs['hours']?.toString() ?? 'Mon – Sat: 8:00 AM – 8:00 PM PHT',
          };
        }
        if (res['faqs'] is List && (res['faqs'] as List).isNotEmpty) {
          _faqs = (res['faqs'] as List)
              .map((e) => Map<String, dynamic>.from(e as Map))
              .toList();
        }
        if (res['policies'] is List && (res['policies'] as List).isNotEmpty) {
          _policies = (res['policies'] as List)
              .map((e) => Map<String, dynamic>.from(e as Map))
              .toList();
        }
      });
    } catch (_) {
      // Fallback to built-in website data seamlessly
    }
  }

  void _openHelpCenter({bool scrollToContact = false}) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => _BuyerHelpCenterPage(
          authState: widget.authState,
          contactSettings: _contactSettings,
          faqs: _faqs,
          policies: _policies,
          initialFocusContact: scrollToContact,
        ),
      ),
    );
  }

  void _openFaqPage() {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => _BuyerFaqPage(
          faqs: _faqs,
          onOpenContact: () => _openHelpCenter(scrollToContact: true),
        ),
      ),
    );
  }

  void _openComplaintsPage() {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => _BuyerComplaintsPage(authState: widget.authState),
      ),
    );
  }

  void _openBuyerProtectionPage() {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => _BuyerProtectionPage(
          onOpenPolicy: (key) => _openPolicyPage(key),
          onOpenComplaints: _openComplaintsPage,
        ),
      ),
    );
  }

  void _openPartnerInfoPage(String partnerType) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => _BuyerPartnerInfoPage(
          partnerType: partnerType,
          authState: widget.authState,
          contactSettings: _contactSettings,
        ),
      ),
    );
  }

  void _openMyAccountDetails() {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => _BuyerAccountDetailsPage(authState: widget.authState),
      ),
    );
  }

  void _openPolicyPage(String initialKey) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => _BuyerPoliciesPage(
          policies: _policies,
          initialPolicyKey: initialKey,
          onContactSupport: () => _openHelpCenter(scrollToContact: true),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF5F6F7),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF087F8C)),
          onPressed: () => Navigator.pop(context),
        ),
        titleSpacing: 0,
        title: Text(
          'Account Settings',
          style: GoogleFonts.figtree(
            fontSize: 19,
            fontWeight: FontWeight.w700,
            color: const Color(0xFF1F2937),
          ),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.chat_bubble_outline_rounded, color: Color(0xFF087F8C), size: 22),
            tooltip: 'Messages',
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const BuyerConversationsScreen()),
              );
            },
          ),
          const SizedBox(width: 6),
        ],
        bottom: const PreferredSize(
          preferredSize: Size.fromHeight(1),
          child: Divider(height: 1, thickness: 1, color: Color(0xFFE5E7EB)),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.only(bottom: 32),
        children: [
          // SECTION 1: CUSTOMER SUPPORT
          _buildSectionHeader('Customer Support'),
          _buildSettingsGroup([
            _SettingsRowItem(
              label: 'Help Center',
              onTap: () => _openHelpCenter(scrollToContact: false),
            ),
            _SettingsRowItem(
              label: 'Frequently Asked Questions',
              onTap: _openFaqPage,
            ),
            _SettingsRowItem(
              label: 'Contact Us',
              onTap: () => _openHelpCenter(scrollToContact: true),
            ),
            _SettingsRowItem(
              label: 'Complaints & Ticket Status',
              onTap: _openComplaintsPage,
            ),
            _SettingsRowItem(
              label: 'Buyer Protection',
              onTap: _openBuyerProtectionPage,
            ),
          ]),

          // SECTION 2: PARTNER & ACCOUNT
          _buildSectionHeader('Partner & Account'),
          _buildSettingsGroup([
            _SettingsRowItem(
              label: 'Become a Seller',
              onTap: () => _openPartnerInfoPage('seller'),
            ),
            _SettingsRowItem(
              label: 'Become a Rider',
              onTap: () => _openPartnerInfoPage('rider'),
            ),
            _SettingsRowItem(
              label: 'Logistics Partner',
              onTap: () => _openPartnerInfoPage('logistics'),
            ),
            _SettingsRowItem(
              label: 'My Account',
              onTap: _openMyAccountDetails,
            ),
          ]),

          // SECTION 3: POLICIES & INFORMATION
          _buildSectionHeader('Policies & Information'),
          _buildSettingsGroup([
            _SettingsRowItem(
              label: 'Privacy Policy',
              onTap: () => _openPolicyPage('privacy'),
            ),
            _SettingsRowItem(
              label: 'Terms & Conditions',
              onTap: () => _openPolicyPage('terms'),
            ),
            _SettingsRowItem(
              label: 'Shipping & Delivery Policy',
              onTap: () => _openPolicyPage('shipping'),
            ),
            _SettingsRowItem(
              label: 'Returns & Refunds Policy',
              onTap: () => _openPolicyPage('returns'),
            ),
            _SettingsRowItem(
              label: 'Payment Methods',
              onTap: () => _openPolicyPage('payment'),
            ),
          ]),
        ],
      ),
    );
  }

  Widget _buildSectionHeader(String title) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
      child: Text(
        title,
        style: GoogleFonts.figtree(
          fontSize: 13,
          fontWeight: FontWeight.w600,
          color: const Color(0xFF6B7280),
        ),
      ),
    );
  }

  Widget _buildSettingsGroup(List<_SettingsRowItem> items) {
    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(
          top: BorderSide(color: Color(0xFFEAECEF), width: 0.8),
          bottom: BorderSide(color: Color(0xFFEAECEF), width: 0.8),
        ),
      ),
      child: Column(
        children: [
          for (int i = 0; i < items.length; i++) ...[
            InkWell(
              onTap: items[i].onTap,
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 15),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            items[i].label,
                            style: GoogleFonts.figtree(
                              fontSize: 15,
                              fontWeight: FontWeight.w500,
                              color: const Color(0xFF1F2937),
                            ),
                          ),
                          if (items[i].subtitle != null) ...[
                            const SizedBox(height: 2),
                            Text(
                              items[i].subtitle!,
                              style: GoogleFonts.figtree(
                                fontSize: 12,
                                color: const Color(0xFF9CA3AF),
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                    const Icon(
                      Icons.chevron_right_rounded,
                      color: Color(0xFF9CA3AF),
                      size: 22,
                    ),
                  ],
                ),
              ),
            ),
            if (i < items.length - 1)
              const Divider(
                height: 1,
                thickness: 0.8,
                indent: 16,
                color: Color(0xFFF1F5F9),
              ),
          ],
        ],
      ),
    );
  }
}

class _SettingsRowItem {
  final String label;
  final String? subtitle;
  final VoidCallback onTap;

  const _SettingsRowItem({
    required this.label,
    this.subtitle,
    required this.onTap,
  });
}

/* ============================================================================
 * 1. HELP CENTER & CONTACT US PAGE (Mirrors resources/js/Pages/Buyer/HelpCenter.vue)
 * ============================================================================ */
class _BuyerHelpCenterPage extends StatefulWidget {
  final AuthState authState;
  final Map<String, String> contactSettings;
  final List<Map<String, dynamic>> faqs;
  final List<Map<String, dynamic>> policies;
  final bool initialFocusContact;

  const _BuyerHelpCenterPage({
    required this.authState,
    required this.contactSettings,
    required this.faqs,
    required this.policies,
    this.initialFocusContact = false,
  });

  @override
  State<_BuyerHelpCenterPage> createState() => _BuyerHelpCenterPageState();
}

class _BuyerHelpCenterPageState extends State<_BuyerHelpCenterPage> {
  final ScrollController _scrollController = ScrollController();
  final GlobalKey _contactSectionKey = GlobalKey();
  final TextEditingController _searchController = TextEditingController();
  final TextEditingController _subjectController = TextEditingController();
  final TextEditingController _messageController = TextEditingController();

  String _selectedCategory = 'all';
  String _selectedSupportCategory = 'General Inquiry';
  bool _isSubmitting = false;

  static const List<String> _supportCategories = [
    'General Inquiry',
    'Order Status & Tracking',
    'Payment & Billing',
    'Return & Refund Dispute',
    'Vouchers & Promotions',
    'Seller Report / Issue',
    'Account & Security',
  ];

  static const List<Map<String, String>> _helpTopics = [
    {
      'id': 'orders',
      'category': 'Orders & Shipping',
      'title': 'How do I track my order?',
      'summary':
          'Find your package whereabouts, courier tracking number, and delivery milestones in real-time.',
    },
    {
      'id': 'returns',
      'category': 'Returns & Refunds',
      'title': 'How to request a return or refund?',
      'summary':
          'Submit a return request within the return window with photo or video evidence.',
    },
    {
      'id': 'payments',
      'category': 'Payments & Checkout',
      'title': 'Payment methods accepted on Alona',
      'summary':
          'Learn about Cash on Delivery (COD), GCash, Maya, and Credit/Debit cards.',
    },
    {
      'id': 'vouchers',
      'category': 'Vouchers & Deals',
      'title': 'How to claim and apply vouchers?',
      'summary':
          'Claim store and platform vouchers to get instant discounts during checkout.',
    },
    {
      'id': 'safety',
      'category': 'Safety & Protection',
      'title': 'Buyer Protection & Reporting',
      'summary':
          'How Alona protects your payments and allows reporting suspicious items or sellers.',
    },
    {
      'id': 'account',
      'category': 'Account & Security',
      'title': 'Managing addresses & password security',
      'summary':
          'Update delivery addresses, verify your email, and protect your login credentials.',
    },
  ];

  @override
  void initState() {
    super.initState();
    if (widget.initialFocusContact) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        final ctx = _contactSectionKey.currentContext;
        if (ctx != null) {
          Scrollable.ensureVisible(
            ctx,
            duration: const Duration(milliseconds: 350),
            curve: Curves.easeOutCubic,
          );
        }
      });
    }
  }

  @override
  void dispose() {
    _scrollController.dispose();
    _searchController.dispose();
    _subjectController.dispose();
    _messageController.dispose();
    super.dispose();
  }

  List<Map<String, String>> get _filteredTopics {
    final q = _searchController.text.trim().toLowerCase();
    return _helpTopics.where((topic) {
      final matchesCat = _selectedCategory == 'all' ||
          topic['category']!.toLowerCase().contains(_selectedCategory.toLowerCase());
      final matchesSearch = q.isEmpty ||
          topic['title']!.toLowerCase().contains(q) ||
          topic['summary']!.toLowerCase().contains(q) ||
          topic['category']!.toLowerCase().contains(q);
      return matchesCat && matchesSearch;
    }).toList();
  }

  Future<void> _submitSupportForm() async {
    final subject = _subjectController.text.trim();
    final message = _messageController.text.trim();
    if (subject.isEmpty || message.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please enter both a subject and message.'),
          backgroundColor: AppColors.sale,
        ),
      );
      return;
    }

    if (!ApiClient.isAuthenticated) {
      final loggedIn = await Navigator.push<bool>(
        context,
        MaterialPageRoute(builder: (_) => BuyerLoginScreen(authState: widget.authState)),
      );
      if (loggedIn != true || !mounted) return;
    }

    setState(() => _isSubmitting = true);
    try {
      final res = await BuyerService.submitSupportContact(
        category: _selectedSupportCategory,
        subject: subject,
        message: message,
      );
      if (!mounted) return;
      _subjectController.clear();
      _messageController.clear();
      setState(() => _isSubmitting = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            res['message']?.toString() ??
                'Your message has been sent to Alona Customer Support. We will get back to you shortly.',
          ),
          backgroundColor: const Color(0xFF087F8C),
        ),
      );
    } catch (e) {
      if (!mounted) return;
      setState(() => _isSubmitting = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed to send inquiry: $e'), backgroundColor: AppColors.sale),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAF9),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF087F8C)),
          onPressed: () => Navigator.pop(context),
        ),
        titleSpacing: 0,
        title: Text(
          widget.initialFocusContact ? 'Contact Us' : 'Help Center',
          style: GoogleFonts.figtree(
            fontSize: 18,
            fontWeight: FontWeight.w800,
            color: const Color(0xFF1F2937),
          ),
        ),
        bottom: const PreferredSize(
          preferredSize: Size.fromHeight(1),
          child: Divider(height: 1, thickness: 1, color: Color(0xFFE5E7EB)),
        ),
      ),
      body: SingleChildScrollView(
        controller: _scrollController,
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // HERO BANNER matching HelpCenter.vue
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(24),
                gradient: const LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [Color(0xFF087F8C), Color(0xFF066B76), Color(0xFF044850)],
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                        width: 8,
                        height: 8,
                        decoration: const BoxDecoration(
                          color: Color(0xFFF4B942),
                          shape: BoxShape.circle,
                        ),
                      ),
                      const SizedBox(width: 6),
                      Text(
                        'ALONA SUPPORT CENTER',
                        style: GoogleFonts.figtree(
                          fontSize: 11,
                          fontWeight: FontWeight.w800,
                          letterSpacing: 1.5,
                          color: const Color(0xFFF4B942),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'How can we help you today?',
                    style: GoogleFonts.figtree(
                      fontSize: 22,
                      fontWeight: FontWeight.w900,
                      color: Colors.white,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Find quick answers to common questions, track your packages, resolve disputes, or reach out to our customer care team.',
                    style: GoogleFonts.figtree(
                      fontSize: 12.5,
                      color: const Color(0xFFE6FFFA),
                      height: 1.45,
                    ),
                  ),
                  const SizedBox(height: 14),
                  Container(
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: TextField(
                      controller: _searchController,
                      onChanged: (_) => setState(() {}),
                      style: GoogleFonts.figtree(fontSize: 13),
                      decoration: InputDecoration(
                        hintText: 'Search by topic, e.g. return refund, tracking...',
                        hintStyle: GoogleFonts.figtree(fontSize: 12.5, color: Colors.grey.shade400),
                        prefixIcon: const Icon(Icons.search_rounded, color: Color(0xFF087F8C), size: 20),
                        suffixIcon: _searchController.text.isNotEmpty
                            ? IconButton(
                                icon: const Icon(Icons.close, size: 16),
                                onPressed: () {
                                  _searchController.clear();
                                  setState(() {});
                                },
                              )
                            : null,
                        border: InputBorder.none,
                        contentPadding: const EdgeInsets.symmetric(vertical: 12),
                      ),
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 20),

            // HELP ARTICLES & GUIDES
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Help Articles & Guides',
                  style: GoogleFonts.figtree(
                    fontSize: 17,
                    fontWeight: FontWeight.w800,
                    color: const Color(0xFF111827),
                  ),
                ),
                TextButton(
                  onPressed: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => _BuyerFaqPage(
                          faqs: widget.faqs,
                          onOpenContact: () {
                            final ctx = _contactSectionKey.currentContext;
                            if (ctx != null) {
                              Scrollable.ensureVisible(ctx);
                            }
                          },
                        ),
                      ),
                    );
                  },
                  child: Text(
                    'View full FAQ →',
                    style: GoogleFonts.figtree(
                      fontSize: 12.5,
                      fontWeight: FontWeight.w800,
                      color: const Color(0xFF087F8C),
                    ),
                  ),
                ),
              ],
            ),

            // Category Filter Chips
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: ['all', 'Orders', 'Returns', 'Payments', 'Vouchers', 'Safety'].map((cat) {
                  final active = _selectedCategory.toLowerCase() == cat.toLowerCase();
                  return Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ChoiceChip(
                      label: Text(cat == 'all' ? 'All Topics' : cat),
                      selected: active,
                      onSelected: (_) => setState(() => _selectedCategory = cat),
                      selectedColor: const Color(0xFF087F8C),
                      backgroundColor: Colors.white,
                      labelStyle: GoogleFonts.figtree(
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        color: active ? Colors.white : const Color(0xFF4B5563),
                      ),
                    ),
                  );
                }).toList(),
              ),
            ),

            const SizedBox(height: 12),

            // Topics Cards
            ..._filteredTopics.map(
              (topic) => Container(
                margin: const EdgeInsets.only(bottom: 10),
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: const Color(0xFFE5E7EB)),
                ),
                child: InkWell(
                  onTap: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => _BuyerFaqPage(
                          faqs: widget.faqs,
                          initialCategory: topic['id'] ?? 'all',
                          onOpenContact: () {},
                        ),
                      ),
                    );
                  },
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Icon(Icons.help_outline_rounded, color: Color(0xFF087F8C), size: 18),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                            decoration: BoxDecoration(
                              color: const Color(0xFFF1F5F9),
                              borderRadius: BorderRadius.circular(10),
                            ),
                            child: Text(
                              topic['category'] ?? '',
                              style: GoogleFonts.figtree(
                                fontSize: 10,
                                fontWeight: FontWeight.w700,
                                color: const Color(0xFF64748B),
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                      Text(
                        topic['title'] ?? '',
                        style: GoogleFonts.figtree(
                          fontSize: 14.5,
                          fontWeight: FontWeight.w800,
                          color: const Color(0xFF111827),
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        topic['summary'] ?? '',
                        style: GoogleFonts.figtree(
                          fontSize: 12.5,
                          color: const Color(0xFF6B7280),
                          height: 1.4,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'Read guide →',
                        style: GoogleFonts.figtree(
                          fontSize: 12,
                          fontWeight: FontWeight.w800,
                          color: const Color(0xFF087F8C),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),

            const SizedBox(height: 10),

            // BUYER PROTECTION NOTICE (from HelpCenter.vue)
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: const Color(0xFFFFFBEB),
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: const Color(0xFFFED7AA)),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(Icons.verified_user_outlined, color: Color(0xFF92400E), size: 22),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Alona Buyer Protection Guarantee',
                          style: GoogleFonts.figtree(
                            fontSize: 14,
                            fontWeight: FontWeight.w800,
                            color: const Color(0xFF92400E),
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          'Every order placed on Alona is protected. If you receive an item that is damaged, counterfeit, or significantly different from what was described, you can request a return or report the seller for dispute investigation.',
                          style: GoogleFonts.figtree(
                            fontSize: 12.5,
                            color: const Color(0xFF78350F),
                            height: 1.45,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 20),

            // DIRECT CONTACT FORM CARD (Contact Us section from HelpCenter.vue)
            Container(
              key: _contactSectionKey,
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: const Color(0xFFE5E7EB)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Container(
                        width: 8,
                        height: 8,
                        decoration: const BoxDecoration(
                          color: Color(0xFF087F8C),
                          shape: BoxShape.circle,
                        ),
                      ),
                      const SizedBox(width: 6),
                      Text(
                        'DIRECT CONTACT',
                        style: GoogleFonts.figtree(
                          fontSize: 10.5,
                          fontWeight: FontWeight.w800,
                          letterSpacing: 1.4,
                          color: const Color(0xFF087F8C),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Contact Platform Support',
                    style: GoogleFonts.figtree(
                      fontSize: 17,
                      fontWeight: FontWeight.w800,
                      color: const Color(0xFF111827),
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Send a message directly to Alona customer care. We typically respond within 2-4 hours.',
                    style: GoogleFonts.figtree(
                      fontSize: 12.5,
                      color: const Color(0xFF6B7280),
                    ),
                  ),
                  const SizedBox(height: 16),
                  Text(
                    'Inquiry Category',
                    style: GoogleFonts.figtree(
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                      color: const Color(0xFF374151),
                    ),
                  ),
                  const SizedBox(height: 6),
                  DropdownButtonFormField<String>(
                    initialValue: _selectedSupportCategory,
                    items: _supportCategories
                        .map((c) => DropdownMenuItem(value: c, child: Text(c, style: GoogleFonts.figtree(fontSize: 13))))
                        .toList(),
                    onChanged: (v) {
                      if (v != null) setState(() => _selectedSupportCategory = v);
                    },
                    decoration: InputDecoration(
                      filled: true,
                      fillColor: const Color(0xFFF8FAF9),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: const BorderSide(color: Color(0xFFE5E7EB)),
                      ),
                      enabledBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: const BorderSide(color: Color(0xFFE5E7EB)),
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    'Subject',
                    style: GoogleFonts.figtree(
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                      color: const Color(0xFF374151),
                    ),
                  ),
                  const SizedBox(height: 6),
                  TextField(
                    controller: _subjectController,
                    style: GoogleFonts.figtree(fontSize: 13),
                    decoration: InputDecoration(
                      hintText: 'Brief summary of your question or issue...',
                      filled: true,
                      fillColor: const Color(0xFFF8FAF9),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: const BorderSide(color: Color(0xFFE5E7EB)),
                      ),
                      enabledBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: const BorderSide(color: Color(0xFFE5E7EB)),
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    'Message / Details',
                    style: GoogleFonts.figtree(
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                      color: const Color(0xFF374151),
                    ),
                  ),
                  const SizedBox(height: 6),
                  TextField(
                    controller: _messageController,
                    maxLines: 4,
                    style: GoogleFonts.figtree(fontSize: 13),
                    decoration: InputDecoration(
                      hintText: 'Please provide details including order number or seller name if applicable...',
                      filled: true,
                      fillColor: const Color(0xFFF8FAF9),
                      contentPadding: const EdgeInsets.all(12),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: const BorderSide(color: Color(0xFFE5E7EB)),
                      ),
                      enabledBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: const BorderSide(color: Color(0xFFE5E7EB)),
                      ),
                    ),
                  ),
                  const SizedBox(height: 14),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton.icon(
                      onPressed: _isSubmitting ? null : _submitSupportForm,
                      icon: const Icon(Icons.mail_outline_rounded, size: 18),
                      label: Text(
                        _isSubmitting ? 'Sending Inquiry...' : 'Send Message to Support',
                        style: GoogleFonts.figtree(fontWeight: FontWeight.w800, fontSize: 13.5),
                      ),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF087F8C),
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 13),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        elevation: 0,
                      ),
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 16),

            // PLATFORM SUPPORT CHANNELS (from HelpCenter.vue)
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: const Color(0xFFE5E7EB)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Platform Support Channels',
                    style: GoogleFonts.figtree(
                      fontSize: 14.5,
                      fontWeight: FontWeight.w800,
                      color: const Color(0xFF111827),
                    ),
                  ),
                  const SizedBox(height: 12),
                  _buildChannelTile(
                    icon: Icons.mail_outline_rounded,
                    iconBg: const Color(0xFFE8F7F6),
                    iconColor: const Color(0xFF087F8C),
                    title: 'Support Email',
                    value: widget.contactSettings['email'] ?? 'support@alona.ph',
                  ),
                  const SizedBox(height: 10),
                  _buildChannelTile(
                    icon: Icons.phone_outlined,
                    iconBg: const Color(0xFFFFF7E6),
                    iconColor: const Color(0xFFB47A08),
                    title: 'Customer Helpline',
                    value: widget.contactSettings['phone'] ?? '+63 (02) 8888-ALONA',
                  ),
                  const SizedBox(height: 10),
                  _buildChannelTile(
                    icon: Icons.access_time_rounded,
                    iconBg: const Color(0xFFEBF5FF),
                    iconColor: const Color(0xFF2563EB),
                    title: 'Operating Hours',
                    value: widget.contactSettings['hours'] ?? 'Mon – Sat: 8:00 AM – 8:00 PM PHT',
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),
          ],
        ),
      ),
    );
  }

  Widget _buildChannelTile({
    required IconData icon,
    required Color iconBg,
    required Color iconColor,
    required String title,
    required String value,
  }) {
    return Row(
      children: [
        Container(
          width: 38,
          height: 38,
          decoration: BoxDecoration(
            color: iconBg,
            borderRadius: BorderRadius.circular(10),
          ),
          child: Icon(icon, color: iconColor, size: 19),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: GoogleFonts.figtree(
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                  color: const Color(0xFF1F2937),
                ),
              ),
              Text(
                value,
                style: GoogleFonts.figtree(
                  fontSize: 12,
                  color: const Color(0xFF6B7280),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

/* ============================================================================
 * 2. FREQUENTLY ASKED QUESTIONS PAGE (Mirrors resources/js/Pages/Buyer/Faq.vue)
 * ============================================================================ */
class _BuyerFaqPage extends StatefulWidget {
  final List<Map<String, dynamic>> faqs;
  final String initialCategory;
  final VoidCallback onOpenContact;

  const _BuyerFaqPage({
    required this.faqs,
    this.initialCategory = 'all',
    required this.onOpenContact,
  });

  @override
  State<_BuyerFaqPage> createState() => _BuyerFaqPageState();
}

class _BuyerFaqPageState extends State<_BuyerFaqPage> {
  final TextEditingController _searchController = TextEditingController();
  late String _activeCategory;
  int? _openIndex = 0;

  static const Map<String, String> _categoryLabels = {
    'all': 'All FAQs',
    'orders': 'Orders & Tracking',
    'payments': 'Payments & Checkout',
    'shipping': 'Shipping & Delivery',
    'returns': 'Returns & Refunds',
    'vouchers': 'Vouchers & Deals',
    'safety': 'Safety & Protection',
    'account': 'Account & Profile',
  };

  @override
  void initState() {
    super.initState();
    _activeCategory = _categoryLabels.containsKey(widget.initialCategory)
        ? widget.initialCategory
        : 'all';
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  List<Map<String, dynamic>> get _filteredFaqs {
    final s = _searchController.text.trim().toLowerCase();
    return widget.faqs.where((item) {
      final cat = (item['category']?.toString() ?? 'general').toLowerCase();
      final matchesCat = _activeCategory == 'all' || cat == _activeCategory;
      final q = (item['question']?.toString() ?? '').toLowerCase();
      final a = (item['answer']?.toString() ?? '').toLowerCase();
      final matchesSearch = s.isEmpty || q.contains(s) || a.contains(s);
      return matchesCat && matchesSearch;
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    final list = _filteredFaqs;
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAF9),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF087F8C)),
          onPressed: () => Navigator.pop(context),
        ),
        titleSpacing: 0,
        title: Text(
          'Frequently Asked Questions',
          style: GoogleFonts.figtree(
            fontSize: 18,
            fontWeight: FontWeight.w800,
            color: const Color(0xFF1F2937),
          ),
        ),
        bottom: const PreferredSize(
          preferredSize: Size.fromHeight(1),
          child: Divider(height: 1, thickness: 1, color: Color(0xFFE5E7EB)),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // Header Card matching Faq.vue
          Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: const Color(0xFFE5E7EB)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      width: 8,
                      height: 8,
                      decoration: const BoxDecoration(
                        color: Color(0xFFF4B942),
                        shape: BoxShape.circle,
                      ),
                    ),
                    const SizedBox(width: 6),
                    Text(
                      'KNOWLEDGE BASE',
                      style: GoogleFonts.figtree(
                        fontSize: 10.5,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 1.4,
                        color: const Color(0xFF087F8C),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 6),
                Text(
                  'Frequently Asked Questions',
                  style: GoogleFonts.figtree(
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                    color: const Color(0xFF111827),
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  'Find instant answers to questions regarding ordering, tracking, payments, returns, and buyer protection.',
                  style: GoogleFonts.figtree(
                    fontSize: 12.5,
                    color: const Color(0xFF6B7280),
                    height: 1.4,
                  ),
                ),
                const SizedBox(height: 14),
                TextField(
                  controller: _searchController,
                  onChanged: (_) => setState(() {}),
                  style: GoogleFonts.figtree(fontSize: 13),
                  decoration: InputDecoration(
                    hintText: 'Search questions...',
                    prefixIcon: const Icon(Icons.search_rounded, size: 19, color: Color(0xFF9CA3AF)),
                    suffixIcon: _searchController.text.isNotEmpty
                        ? IconButton(
                            icon: const Icon(Icons.close, size: 16),
                            onPressed: () {
                              _searchController.clear();
                              setState(() {});
                            },
                          )
                        : null,
                    filled: true,
                    fillColor: const Color(0xFFF8FAF9),
                    contentPadding: const EdgeInsets.symmetric(vertical: 10),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: const BorderSide(color: Color(0xFFE5E7EB)),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                      borderSide: const BorderSide(color: Color(0xFFE5E7EB)),
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: _categoryLabels.entries.map((entry) {
                      final active = _activeCategory == entry.key;
                      return Padding(
                        padding: const EdgeInsets.only(right: 8),
                        child: ChoiceChip(
                          label: Text(entry.value),
                          selected: active,
                          onSelected: (_) => setState(() => _activeCategory = entry.key),
                          selectedColor: const Color(0xFF087F8C),
                          backgroundColor: const Color(0xFFF8FAF9),
                          labelStyle: GoogleFonts.figtree(
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                            color: active ? Colors.white : const Color(0xFF4B5563),
                          ),
                        ),
                      );
                    }).toList(),
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 16),

          // FAQ Accordion Items
          for (int i = 0; i < list.length; i++)
            Container(
              margin: const EdgeInsets.only(bottom: 10),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(
                  color: _openIndex == i ? const Color(0xFF087F8C) : const Color(0xFFE5E7EB),
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  InkWell(
                    borderRadius: BorderRadius.circular(16),
                    onTap: () => setState(() => _openIndex = _openIndex == i ? null : i),
                    child: Padding(
                      padding: const EdgeInsets.all(14),
                      child: Row(
                        children: [
                          Container(
                            width: 28,
                            height: 28,
                            decoration: BoxDecoration(
                              color: const Color(0xFFE8F7F6),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            alignment: Alignment.center,
                            child: Text(
                              'Q',
                              style: GoogleFonts.figtree(
                                fontSize: 12,
                                fontWeight: FontWeight.w900,
                                color: const Color(0xFF087F8C),
                              ),
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Text(
                              list[i]['question']?.toString() ?? '',
                              style: GoogleFonts.figtree(
                                fontSize: 13.5,
                                fontWeight: FontWeight.w800,
                                color: const Color(0xFF111827),
                              ),
                            ),
                          ),
                          Icon(
                            _openIndex == i
                                ? Icons.keyboard_arrow_up_rounded
                                : Icons.keyboard_arrow_down_rounded,
                            color: const Color(0xFF087F8C),
                          ),
                        ],
                      ),
                    ),
                  ),
                  if (_openIndex == i)
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.fromLTRB(14, 12, 14, 14),
                      decoration: const BoxDecoration(
                        color: Color(0xFFFCFDFC),
                        border: Border(top: BorderSide(color: Color(0xFFF1F5F9))),
                        borderRadius: BorderRadius.vertical(bottom: Radius.circular(16)),
                      ),
                      child: Text(
                        list[i]['answer']?.toString() ?? '',
                        style: GoogleFonts.figtree(
                          fontSize: 13,
                          color: const Color(0xFF4B5563),
                          height: 1.55,
                        ),
                      ),
                    ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

/* ============================================================================
 * 3. COMPLAINTS & TICKET STATUS PAGE (Mirrors resources/js/Pages/Buyer/Complaints.vue)
 * ============================================================================ */
class _BuyerComplaintsPage extends StatefulWidget {
  final AuthState authState;

  const _BuyerComplaintsPage({required this.authState});

  @override
  State<_BuyerComplaintsPage> createState() => _BuyerComplaintsPageState();
}

class _BuyerComplaintsPageState extends State<_BuyerComplaintsPage> {
  String _selectedStatus = 'all';
  bool _isLoading = false;
  List<Map<String, dynamic>> _complaints = [];
  Map<String, dynamic> _counts = {};

  static const List<Map<String, String>> _tabs = [
    {'key': 'all', 'label': 'All Cases'},
    {'key': 'pending', 'label': 'Pending'},
    {'key': 'reviewing', 'label': 'Under Review'},
    {'key': 'resolved', 'label': 'Resolved'},
    {'key': 'rejected', 'label': 'Closed / Rejected'},
  ];

  @override
  void initState() {
    super.initState();
    _loadComplaints();
  }

  Future<void> _loadComplaints() async {
    if (!ApiClient.isAuthenticated) return;
    setState(() => _isLoading = true);
    try {
      final res = await BuyerService.getComplaints(status: _selectedStatus);
      if (!mounted) return;
      setState(() {
        _complaints = (res['complaints'] as List? ?? [])
            .map((e) => Map<String, dynamic>.from(e as Map))
            .toList();
        _counts = res['counts'] is Map ? Map<String, dynamic>.from(res['counts']) : {};
        _isLoading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _isLoading = false);
    }
  }

  void _showFileComplaintSheet() async {
    if (!ApiClient.isAuthenticated) {
      final loggedIn = await Navigator.push<bool>(
        context,
        MaterialPageRoute(builder: (_) => BuyerLoginScreen(authState: widget.authState)),
      );
      if (loggedIn != true || !mounted) return;
      _loadComplaints();
    }

    final subjectCtrl = TextEditingController();
    final descCtrl = TextEditingController();
    String selectedType = 'complaint';

    if (!mounted) return;
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
      ),
      builder: (ctx) {
        bool saving = false;
        return StatefulBuilder(
          builder: (ctx, setSheetState) {
            return Padding(
              padding: EdgeInsets.only(
                left: 18,
                right: 18,
                top: 18,
                bottom: MediaQuery.of(ctx).viewInsets.bottom + 20,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'File a Complaint / Ticket',
                    style: GoogleFonts.figtree(fontSize: 18, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Submit your dispute or support case for investigation by Alona administration.',
                    style: GoogleFonts.figtree(fontSize: 12.5, color: const Color(0xFF6B7280)),
                  ),
                  const SizedBox(height: 14),
                  DropdownButtonFormField<String>(
                    initialValue: selectedType,
                    items: const [
                      DropdownMenuItem(value: 'complaint', child: Text('Order / Seller Dispute')),
                      DropdownMenuItem(value: 'product_report', child: Text('Product Quality / Counterfeit Issue')),
                      DropdownMenuItem(value: 'support_ticket', child: Text('General Support Ticket')),
                    ],
                    onChanged: (v) {
                      if (v != null) setSheetState(() => selectedType = v);
                    },
                    decoration: InputDecoration(
                      labelText: 'Case Type',
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: subjectCtrl,
                    decoration: InputDecoration(
                      labelText: 'Subject',
                      hintText: 'Brief summary of your issue',
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: descCtrl,
                    maxLines: 4,
                    decoration: InputDecoration(
                      labelText: 'Description',
                      hintText: 'Provide full details of your complaint...',
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                  ),
                  const SizedBox(height: 16),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: saving
                          ? null
                          : () async {
                              if (subjectCtrl.text.trim().isEmpty || descCtrl.text.trim().isEmpty) {
                                return;
                              }
                              setSheetState(() => saving = true);
                              try {
                                await BuyerService.submitComplaint(
                                  subject: subjectCtrl.text.trim(),
                                  description: descCtrl.text.trim(),
                                  type: selectedType,
                                );
                                if (ctx.mounted) Navigator.pop(ctx);
                                _loadComplaints();
                                if (mounted) {
                                  ScaffoldMessenger.of(context).showSnackBar(
                                    const SnackBar(
                                      content: Text('Complaint submitted successfully.'),
                                      backgroundColor: Color(0xFF087F8C),
                                    ),
                                  );
                                }
                              } catch (e) {
                                setSheetState(() => saving = false);
                                if (mounted) {
                                  ScaffoldMessenger.of(context).showSnackBar(
                                    SnackBar(content: Text('Error: $e'), backgroundColor: AppColors.sale),
                                  );
                                }
                              }
                            },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF087F8C),
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 13),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                      child: Text(saving ? 'Submitting...' : 'Submit Ticket'),
                    ),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAF9),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF087F8C)),
          onPressed: () => Navigator.pop(context),
        ),
        titleSpacing: 0,
        title: Text(
          'Complaints & Ticket Status',
          style: GoogleFonts.figtree(
            fontSize: 18,
            fontWeight: FontWeight.w800,
            color: const Color(0xFF1F2937),
          ),
        ),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 12),
            child: TextButton.icon(
              onPressed: _showFileComplaintSheet,
              icon: const Icon(Icons.add_rounded, size: 18, color: Color(0xFF087F8C)),
              label: Text(
                'File Ticket',
                style: GoogleFonts.figtree(
                  fontSize: 13,
                  fontWeight: FontWeight.w800,
                  color: const Color(0xFF087F8C),
                ),
              ),
            ),
          ),
        ],
        bottom: const PreferredSize(
          preferredSize: Size.fromHeight(1),
          child: Divider(height: 1, thickness: 1, color: Color(0xFFE5E7EB)),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: const Color(0xFFE5E7EB)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'DISPUTE & CASE MANAGEMENT',
                  style: GoogleFonts.figtree(
                    fontSize: 10.5,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 1.4,
                    color: const Color(0xFF087F8C),
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  'Complaints & Report Tracking',
                  style: GoogleFonts.figtree(
                    fontSize: 19,
                    fontWeight: FontWeight.w900,
                    color: const Color(0xFF111827),
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  'Track the status, investigation updates, and official administrator resolutions for your submitted complaints.',
                  style: GoogleFonts.figtree(
                    fontSize: 12.5,
                    color: const Color(0xFF6B7280),
                    height: 1.4,
                  ),
                ),
                const SizedBox(height: 14),
                SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: _tabs.map((tab) {
                      final key = tab['key']!;
                      final active = _selectedStatus == key;
                      final count = _counts[key] ?? 0;
                      return Padding(
                        padding: const EdgeInsets.only(right: 8),
                        child: ChoiceChip(
                          label: Text('${tab['label']} ($count)'),
                          selected: active,
                          onSelected: (_) {
                            setState(() => _selectedStatus = key);
                            _loadComplaints();
                          },
                          selectedColor: const Color(0xFF087F8C),
                          backgroundColor: const Color(0xFFF8FAF9),
                          labelStyle: GoogleFonts.figtree(
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                            color: active ? Colors.white : const Color(0xFF4B5563),
                          ),
                        ),
                      );
                    }).toList(),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          if (!ApiClient.isAuthenticated)
            Container(
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: const Color(0xFFE5E7EB)),
              ),
              child: Column(
                children: [
                  const Icon(Icons.lock_outline_rounded, size: 36, color: Color(0xFF087F8C)),
                  const SizedBox(height: 10),
                  Text(
                    'Sign in to view your tickets',
                    style: GoogleFonts.figtree(fontSize: 16, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Log in to track your submitted complaints, disputes, and administrator resolutions.',
                    textAlign: TextAlign.center,
                    style: GoogleFonts.figtree(fontSize: 12.5, color: const Color(0xFF6B7280)),
                  ),
                  const SizedBox(height: 14),
                  ElevatedButton(
                    onPressed: () async {
                      final loggedIn = await Navigator.push<bool>(
                        context,
                        MaterialPageRoute(
                          builder: (_) => BuyerLoginScreen(authState: widget.authState),
                        ),
                      );
                      if (loggedIn == true) _loadComplaints();
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF087F8C),
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    child: const Text('Sign In'),
                  ),
                ],
              ),
            )
          else if (_isLoading)
            const Padding(
              padding: EdgeInsets.all(32),
              child: Center(child: CircularProgressIndicator(color: Color(0xFF087F8C))),
            )
          else if (_complaints.isEmpty)
            Container(
              padding: const EdgeInsets.all(28),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: const Color(0xFFE5E7EB)),
              ),
              child: Column(
                children: [
                  const Icon(Icons.assignment_turned_in_outlined, size: 38, color: Color(0xFF087F8C)),
                  const SizedBox(height: 10),
                  Text(
                    'No complaints or tickets found',
                    style: GoogleFonts.figtree(fontSize: 15.5, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'If you experience any issue with an order or seller, tap "+ File Ticket" above.',
                    textAlign: TextAlign.center,
                    style: GoogleFonts.figtree(fontSize: 12.5, color: const Color(0xFF6B7280)),
                  ),
                ],
              ),
            )
          else
            ..._complaints.map((c) {
              final status = (c['status']?.toString() ?? 'pending').toLowerCase();
              return Container(
                margin: const EdgeInsets.only(bottom: 10),
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: const Color(0xFFE5E7EB)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          'Ticket #${c['id']}',
                          style: GoogleFonts.figtree(
                            fontSize: 12,
                            fontWeight: FontWeight.w800,
                            color: const Color(0xFF087F8C),
                          ),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color: status == 'resolved'
                                ? const Color(0xFFEAF8F1)
                                : const Color(0xFFFFF7E6),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            status.toUpperCase(),
                            style: GoogleFonts.figtree(
                              fontSize: 10,
                              fontWeight: FontWeight.w800,
                              color: status == 'resolved'
                                  ? const Color(0xFF16A34A)
                                  : const Color(0xFFB47A08),
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Text(
                      c['subject']?.toString() ?? 'Support Ticket',
                      style: GoogleFonts.figtree(
                        fontSize: 14.5,
                        fontWeight: FontWeight.w800,
                        color: const Color(0xFF111827),
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      c['description']?.toString() ?? '',
                      style: GoogleFonts.figtree(
                        fontSize: 12.5,
                        color: const Color(0xFF4B5563),
                        height: 1.4,
                      ),
                    ),
                    if (c['admin_notes'] != null && c['admin_notes'].toString().isNotEmpty) ...[
                      const SizedBox(height: 8),
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          color: const Color(0xFFE8F7F6),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Text(
                          'Admin Resolution: ${c['admin_notes']}',
                          style: GoogleFonts.figtree(
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                            color: const Color(0xFF087F8C),
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
              );
            }),
        ],
      ),
    );
  }
}

/* ============================================================================
 * 4. BUYER PROTECTION PAGE (Website Buyer Protection Guarantee & Policies)
 * ============================================================================ */
class _BuyerProtectionPage extends StatelessWidget {
  final void Function(String policyKey) onOpenPolicy;
  final VoidCallback onOpenComplaints;

  const _BuyerProtectionPage({
    required this.onOpenPolicy,
    required this.onOpenComplaints,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAF9),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF087F8C)),
          onPressed: () => Navigator.pop(context),
        ),
        titleSpacing: 0,
        title: Text(
          'Buyer Protection',
          style: GoogleFonts.figtree(
            fontSize: 18,
            fontWeight: FontWeight.w800,
            color: const Color(0xFF1F2937),
          ),
        ),
        bottom: const PreferredSize(
          preferredSize: Size.fromHeight(1),
          child: Divider(height: 1, thickness: 1, color: Color(0xFFE5E7EB)),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(22),
              gradient: const LinearGradient(
                colors: [Color(0xFF087F8C), Color(0xFF044850)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Icon(Icons.verified_user_rounded, color: Color(0xFFF4B942), size: 32),
                const SizedBox(height: 10),
                Text(
                  'Alona Buyer Protection Guarantee',
                  style: GoogleFonts.figtree(
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                    color: Colors.white,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  'Every order placed on Alona is protected from checkout to delivery. Shop with confidence knowing your payment and rights are safeguarded.',
                  style: GoogleFonts.figtree(
                    fontSize: 13,
                    color: const Color(0xFFE6FFFA),
                    height: 1.45,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          _buildGuaranteeCard(
            icon: Icons.assignment_return_outlined,
            title: '7-Day Return & Refund Guarantee',
            body:
                'Buyers may submit a Return & Refund request within seven (7) calendar days from confirmed package delivery for damaged, defective, incorrect, missing, or counterfeit items.',
          ),
          _buildGuaranteeCard(
            icon: Icons.lock_outline_rounded,
            title: 'Secure Payment & Escrow Protection',
            body:
                'Payments made via GCash, Maya, Credit/Debit Cards, or COD are protected. Sellers only receive payout after delivery is verified or the return window is completed.',
          ),
          _buildGuaranteeCard(
            icon: Icons.camera_alt_outlined,
            title: 'Photographic Proof of Delivery & OTP',
            body:
                'Every dispatched parcel is tracked with real-time milestones. Riders must capture photographic Proof of Delivery and verify handoff.',
          ),
          _buildGuaranteeCard(
            icon: Icons.gavel_rounded,
            title: 'Platform Dispute Mediation',
            body:
                'Sellers have 48–72 hours to respond to return requests. If a seller unjustifiably rejects an eligible claim, Alona Platform Administration steps in to mediate and resolve the refund in your favor.',
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: () => onOpenPolicy('returns'),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: const Color(0xFF087F8C),
                    side: const BorderSide(color: Color(0xFF087F8C)),
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  child: Text(
                    'Returns Policy',
                    style: GoogleFonts.figtree(fontWeight: FontWeight.w800),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: ElevatedButton(
                  onPressed: onOpenComplaints,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF087F8C),
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    elevation: 0,
                  ),
                  child: Text(
                    'Resolution Center',
                    style: GoogleFonts.figtree(fontWeight: FontWeight.w800),
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildGuaranteeCard({
    required IconData icon,
    required String title,
    required String body,
  }) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: const Color(0xFFE8F7F6),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: const Color(0xFF087F8C), size: 22),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: GoogleFonts.figtree(
                    fontSize: 14.5,
                    fontWeight: FontWeight.w800,
                    color: const Color(0xFF111827),
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  body,
                  style: GoogleFonts.figtree(
                    fontSize: 12.5,
                    color: const Color(0xFF4B5563),
                    height: 1.45,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/* ============================================================================
 * 5. PARTNER & ACCOUNT INFO PAGES (Become a Seller, Become a Rider, Logistics Partner)
 * ============================================================================ */
class _BuyerPartnerInfoPage extends StatelessWidget {
  final String partnerType; // 'seller' | 'rider' | 'logistics'
  final AuthState authState;
  final Map<String, String> contactSettings;

  const _BuyerPartnerInfoPage({
    required this.partnerType,
    required this.authState,
    required this.contactSettings,
  });

  @override
  Widget build(BuildContext context) {
    late final String title;
    late final String badge;
    late final String headline;
    late final String description;
    late final List<String> benefits;
    late final List<String> requirements;
    late final String webPath;

    if (partnerType == 'seller') {
      title = 'Become a Seller';
      badge = 'MERCHANT ONBOARDING';
      headline = 'Start Selling on Alona Marketplace';
      description =
          'Reach thousands of buyers across the Philippines, manage your product catalog, run flash sales & store vouchers, and grow your business with Alona Seller Center.';
      benefits = [
        'Dedicated Storefront with custom banner, categories, and verified merchant badge.',
        'Built-in promotional tools: Store Vouchers, Flash Deals, and real-time Buyer Chat.',
        'Seamless parcel pickup & nationwide delivery via accredited Alona Sorting Centers.',
        'Transparent sales analytics, order management, and automated payout settlements.',
      ];
      requirements = [
        'Valid personal email address and verified Philippine mobile number (09XXXXXXXXX).',
        'Store Name, Store Address (Region, Province, City/Municipality, Barangay), and pickup location.',
        'Valid Government-Issued ID and Business Permit / DTI or SEC Registration for verification.',
      ];
      webPath = 'https://shopalona.online/register/seller';
    } else if (partnerType == 'rider') {
      title = 'Become a Rider';
      badge = 'DELIVERY FLEET PARTNER';
      headline = 'Deliver with Alona Logistics Fleet';
      description =
          'Earn flexible income by picking up parcels from merchants and delivering orders to buyers within your assigned municipal coverage areas.';
      benefits = [
        'Real-time pickup and delivery dispatch through the Alona Logistics & Rider Portal.',
        'Transparent per-trip and per-parcel delivery earnings with digital payout tracking.',
        'Flexible online/offline availability and route-optimized hub assignments.',
        'Built-in Proof of Delivery (POD), OTP confirmation, and COD remittance tracking.',
      ];
      requirements = [
        'Must be at least 18 years old with a valid Philippine mobile number.',
        'Valid Professional / Non-Professional Driver’s License.',
        'Vehicle Official Receipt (OR) and Certificate of Registration (CR).',
        'Valid Government ID and affiliation with an approved Alona Sorting Center hub.',
      ];
      webPath = 'https://shopalona.online/register';
    } else {
      title = 'Logistics Partner';
      badge = 'SORTING CENTER & HUB PARTNER';
      headline = 'Partner as an Alona Sorting Center';
      description =
          'Operate a regional or municipal sorting hub on the Alona Logistics Network. Manage incoming seller parcels, hub-to-hub transfers, rider fleet dispatch, and last-mile delivery coverage.';
      benefits = [
        'Full Sorting Center Hub Workspace with barcode/reference lookup and queue management.',
        'Rider fleet management, coverage area configuration, and automated payroll preparation.',
        'COD collection monitoring, remittance reconciliation, and delivery performance reports.',
        'Direct integration with Alona sellers for scheduled parcel pickups and returns.',
      ];
      requirements = [
        'Registered Logistics Company / Hub Name and complete facility address.',
        'Valid Business Permit, DTI/SEC Registration, and authorized operator credentials.',
        'Dedicated sorting facility capable of handling local parcel intake and rider dispatch.',
      ];
      webPath = 'https://shopalona.online/register/logistics';
    }

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAF9),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF087F8C)),
          onPressed: () => Navigator.pop(context),
        ),
        titleSpacing: 0,
        title: Text(
          title,
          style: GoogleFonts.figtree(
            fontSize: 18,
            fontWeight: FontWeight.w800,
            color: const Color(0xFF1F2937),
          ),
        ),
        bottom: const PreferredSize(
          preferredSize: Size.fromHeight(1),
          child: Divider(height: 1, thickness: 1, color: Color(0xFFE5E7EB)),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(22),
              gradient: const LinearGradient(
                colors: [Color(0xFF087F8C), Color(0xFF044850)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  badge,
                  style: GoogleFonts.figtree(
                    fontSize: 10.5,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 1.5,
                    color: const Color(0xFFF4B942),
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  headline,
                  style: GoogleFonts.figtree(
                    fontSize: 21,
                    fontWeight: FontWeight.w900,
                    color: Colors.white,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  description,
                  style: GoogleFonts.figtree(
                    fontSize: 13,
                    color: const Color(0xFFE6FFFA),
                    height: 1.45,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: const Color(0xFFE5E7EB)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Why Partner with Alona?',
                  style: GoogleFonts.figtree(
                    fontSize: 15.5,
                    fontWeight: FontWeight.w800,
                    color: const Color(0xFF111827),
                  ),
                ),
                const SizedBox(height: 12),
                ...benefits.map(
                  (b) => Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Icon(Icons.check_circle_rounded, color: Color(0xFF087F8C), size: 18),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            b,
                            style: GoogleFonts.figtree(
                              fontSize: 13,
                              color: const Color(0xFF4B5563),
                              height: 1.4,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: const Color(0xFFE5E7EB)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Onboarding Requirements',
                  style: GoogleFonts.figtree(
                    fontSize: 15.5,
                    fontWeight: FontWeight.w800,
                    color: const Color(0xFF111827),
                  ),
                ),
                const SizedBox(height: 12),
                ...requirements.map(
                  (r) => Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Icon(Icons.description_outlined, color: Color(0xFF087F8C), size: 18),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            r,
                            style: GoogleFonts.figtree(
                              fontSize: 13,
                              color: const Color(0xFF4B5563),
                              height: 1.4,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                const Divider(height: 22, color: Color(0xFFE5E7EB)),
                Text(
                  'Official Web Registration Portal:',
                  style: GoogleFonts.figtree(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: const Color(0xFF6B7280),
                  ),
                ),
                const SizedBox(height: 4),
                SelectableText(
                  webPath,
                  style: GoogleFonts.figtree(
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                    color: const Color(0xFF087F8C),
                  ),
                ),
              ],
            ),
          ),
          if (partnerType == 'rider' || partnerType == 'logistics') ...[
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: () async {
                  await authState.switchModule('logistics');
                  if (!context.mounted) return;
                  Navigator.pushAndRemoveUntil(
                    context,
                    MaterialPageRoute(builder: (_) => LogisticsMainScreen(authState: authState)),
                    (route) => false,
                  );
                },
                icon: const Icon(Icons.local_shipping_outlined, size: 18),
                label: Text(
                  'Open Logistics & Rider Portal',
                  style: GoogleFonts.figtree(fontWeight: FontWeight.w800, fontSize: 14),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF087F8C),
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  elevation: 0,
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/* ============================================================================
 * 6. MY ACCOUNT OVERVIEW PAGE (From Partner & Account -> My Account)
 * ============================================================================ */
class _BuyerAccountDetailsPage extends StatelessWidget {
  final AuthState authState;

  const _BuyerAccountDetailsPage({required this.authState});

  @override
  Widget build(BuildContext context) {
    final isAuth = authState.isBuyerAuthenticated;
    final user = authState.buyerUser;

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAF9),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF087F8C)),
          onPressed: () => Navigator.pop(context),
        ),
        titleSpacing: 0,
        title: Text(
          'My Account',
          style: GoogleFonts.figtree(
            fontSize: 18,
            fontWeight: FontWeight.w800,
            color: const Color(0xFF1F2937),
          ),
        ),
        bottom: const PreferredSize(
          preferredSize: Size.fromHeight(1),
          child: Divider(height: 1, thickness: 1, color: Color(0xFFE5E7EB)),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: const Color(0xFFE5E7EB)),
            ),
            child: isAuth
                ? Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Account Profile',
                        style: GoogleFonts.figtree(
                          fontSize: 15.5,
                          fontWeight: FontWeight.w800,
                          color: const Color(0xFF111827),
                        ),
                      ),
                      const SizedBox(height: 12),
                      _infoRow('Full Name', user?['name']?.toString() ?? 'Buyer'),
                      const Divider(height: 20, color: Color(0xFFF1F5F9)),
                      _infoRow('Email Address', user?['email']?.toString() ?? 'Not set'),
                      const Divider(height: 20, color: Color(0xFFF1F5F9)),
                      _infoRow('Contact Number', user?['contact_no']?.toString() ?? 'Not set'),
                      const Divider(height: 20, color: Color(0xFFF1F5F9)),
                      _infoRow('Account Type', 'Verified Alona Buyer'),
                    ],
                  )
                : Column(
                    children: [
                      Text(
                        'You are currently browsing as a Guest',
                        style: GoogleFonts.figtree(fontSize: 15.5, fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        'Sign in to manage your profile, saved addresses, orders, and vouchers.',
                        textAlign: TextAlign.center,
                        style: GoogleFonts.figtree(fontSize: 12.5, color: const Color(0xFF6B7280)),
                      ),
                      const SizedBox(height: 14),
                      ElevatedButton(
                        onPressed: () {
                          Navigator.push(
                            context,
                            MaterialPageRoute(builder: (_) => BuyerLoginScreen(authState: authState)),
                          );
                        },
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFF087F8C),
                          foregroundColor: Colors.white,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        ),
                        child: const Text('Log In / Register'),
                      ),
                    ],
                  ),
          ),
          const SizedBox(height: 16),
          Container(
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: const Color(0xFFE5E7EB)),
            ),
            child: Column(
              children: [
                ListTile(
                  leading: const Icon(Icons.location_on_outlined, color: Color(0xFF087F8C)),
                  title: Text('Delivery Addresses', style: GoogleFonts.figtree(fontWeight: FontWeight.w700, fontSize: 14)),
                  trailing: const Icon(Icons.chevron_right_rounded, color: Color(0xFF9CA3AF)),
                  onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const AddressManagementScreen())),
                ),
                const Divider(height: 1, indent: 16, color: Color(0xFFF1F5F9)),
                ListTile(
                  leading: const Icon(Icons.receipt_long_outlined, color: Color(0xFF087F8C)),
                  title: Text('My Orders', style: GoogleFonts.figtree(fontWeight: FontWeight.w700, fontSize: 14)),
                  trailing: const Icon(Icons.chevron_right_rounded, color: Color(0xFF9CA3AF)),
                  onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const OrderListScreen(initialTab: 'all'))),
                ),
                const Divider(height: 1, indent: 16, color: Color(0xFFF1F5F9)),
                ListTile(
                  leading: const Icon(Icons.local_offer_outlined, color: Color(0xFF087F8C)),
                  title: Text('My Vouchers & Coupons', style: GoogleFonts.figtree(fontWeight: FontWeight.w700, fontSize: 14)),
                  trailing: const Icon(Icons.chevron_right_rounded, color: Color(0xFF9CA3AF)),
                  onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const VouchersScreen())),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _infoRow(String label, String value) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: GoogleFonts.figtree(fontSize: 13, color: const Color(0xFF6B7280))),
        Flexible(
          child: Text(
            value,
            textAlign: TextAlign.right,
            style: GoogleFonts.figtree(fontSize: 13.5, fontWeight: FontWeight.w700, color: const Color(0xFF111827)),
          ),
        ),
      ],
    );
  }
}

/* ============================================================================
 * 7. POLICIES & INFORMATION PAGE (Mirrors resources/js/Pages/Buyer/Policies.vue)
 * ============================================================================ */
class _BuyerPoliciesPage extends StatefulWidget {
  final List<Map<String, dynamic>> policies;
  final String initialPolicyKey;
  final VoidCallback onContactSupport;

  const _BuyerPoliciesPage({
    required this.policies,
    required this.initialPolicyKey,
    required this.onContactSupport,
  });

  @override
  State<_BuyerPoliciesPage> createState() => _BuyerPoliciesPageState();
}

class _BuyerPoliciesPageState extends State<_BuyerPoliciesPage> {
  late int _selectedIndex;

  @override
  void initState() {
    super.initState();
    _selectedIndex = _resolveInitialIndex(widget.initialPolicyKey);
  }

  int _resolveInitialIndex(String targetKey) {
    final target = targetKey.toLowerCase().trim();
    for (int i = 0; i < widget.policies.length; i++) {
      final title = (widget.policies[i]['title']?.toString() ?? '').toLowerCase();
      if (title == target) return i;
      if (target.contains('privacy') && title.contains('privacy')) return i;
      if (target.contains('terms') && title.contains('terms')) return i;
      if (target.contains('shipping') && title.contains('shipping')) return i;
      if ((target.contains('return') || target.contains('refund')) &&
          (title.contains('return') || title.contains('refund'))) {
        return i;
      }
      if (target.contains('payment') && title.contains('payment')) return i;
    }
    return 0;
  }

  @override
  Widget build(BuildContext context) {
    final activePolicy = widget.policies.isNotEmpty
        ? widget.policies[_selectedIndex.clamp(0, widget.policies.length - 1)]
        : null;

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAF9),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        scrolledUnderElevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF087F8C)),
          onPressed: () => Navigator.pop(context),
        ),
        titleSpacing: 0,
        title: Text(
          activePolicy?['title']?.toString() ?? 'Policies & Information',
          style: GoogleFonts.figtree(
            fontSize: 18,
            fontWeight: FontWeight.w800,
            color: const Color(0xFF1F2937),
          ),
        ),
        bottom: const PreferredSize(
          preferredSize: Size.fromHeight(1),
          child: Divider(height: 1, thickness: 1, color: Color(0xFFE5E7EB)),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // Header Card matching Policies.vue
          Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: const Color(0xFFE5E7EB)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      width: 8,
                      height: 8,
                      decoration: const BoxDecoration(
                        color: Color(0xFFF4B942),
                        shape: BoxShape.circle,
                      ),
                    ),
                    const SizedBox(width: 6),
                    Text(
                      'LEGAL & COMPLIANCE',
                      style: GoogleFonts.figtree(
                        fontSize: 10.5,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 1.4,
                        color: const Color(0xFF087F8C),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 6),
                Text(
                  'Alona Platform Policies & Terms',
                  style: GoogleFonts.figtree(
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                    color: const Color(0xFF111827),
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  'Read our official platform terms, privacy standards, return rules, and buyer protection guarantees.',
                  style: GoogleFonts.figtree(
                    fontSize: 12.5,
                    color: const Color(0xFF6B7280),
                    height: 1.4,
                  ),
                ),
                const SizedBox(height: 12),
                SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: [
                      for (int i = 0; i < widget.policies.length; i++)
                        Padding(
                          padding: const EdgeInsets.only(right: 8),
                          child: ChoiceChip(
                            label: Text(widget.policies[i]['title']?.toString() ?? 'Policy'),
                            selected: _selectedIndex == i,
                            onSelected: (_) => setState(() => _selectedIndex = i),
                            selectedColor: const Color(0xFFE8F7F6),
                            backgroundColor: Colors.white,
                            side: BorderSide(
                              color: _selectedIndex == i
                                  ? const Color(0xFF087F8C)
                                  : const Color(0xFFE5E7EB),
                            ),
                            labelStyle: GoogleFonts.figtree(
                              fontSize: 12,
                              fontWeight: FontWeight.w800,
                              color: _selectedIndex == i
                                  ? const Color(0xFF087F8C)
                                  : const Color(0xFF374151),
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 16),

          // Active Policy Content Card
          if (activePolicy != null)
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: const Color(0xFFE5E7EB)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      const Icon(Icons.menu_book_rounded, color: Color(0xFF087F8C), size: 20),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          activePolicy['title']?.toString() ?? '',
                          style: GoogleFonts.figtree(
                            fontSize: 18,
                            fontWeight: FontWeight.w900,
                            color: const Color(0xFF111827),
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Version ${activePolicy['version'] ?? '1.0'}',
                    style: GoogleFonts.figtree(
                      fontSize: 11.5,
                      color: const Color(0xFF6B7280),
                    ),
                  ),
                  const Divider(height: 24, color: Color(0xFFF1F5F9)),
                  Text(
                    activePolicy['content']?.toString() ?? '',
                    style: GoogleFonts.figtree(
                      fontSize: 13.5,
                      color: const Color(0xFF374151),
                      height: 1.65,
                    ),
                  ),
                ],
              ),
            ),

          const SizedBox(height: 14),

          // Have policy questions card matching Policies.vue
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: const Color(0xFFE5E7EB)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Have policy questions?',
                  style: GoogleFonts.figtree(
                    fontSize: 13.5,
                    fontWeight: FontWeight.w800,
                    color: const Color(0xFF111827),
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  'If you have questions regarding these terms, our support team is available to help.',
                  style: GoogleFonts.figtree(
                    fontSize: 12,
                    color: const Color(0xFF6B7280),
                  ),
                ),
                const SizedBox(height: 8),
                InkWell(
                  onTap: () {
                    Navigator.pop(context);
                    widget.onContactSupport();
                  },
                  child: Text(
                    'Contact Support →',
                    style: GoogleFonts.figtree(
                      fontSize: 12.5,
                      fontWeight: FontWeight.w800,
                      color: const Color(0xFF087F8C),
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),
        ],
      ),
    );
  }
}

/* ============================================================================
 * WEBSITE SEEDED POLICIES & FAQS (Exact match with Laravel database seeders)
 * ============================================================================ */
const List<Map<String, dynamic>> _defaultWebsitePolicies = [
  {
    'id': 1,
    'title': 'Privacy Policy',
    'version': '1.0',
    'content': '1. Overview & Commitment\n'
        'Alona Marketplace ("Alona", "we", "our") is committed to protecting the personal data and privacy rights of our buyers, sellers, and delivery partners in accordance with the Data Privacy Act of 2012 (Republic Act No. 10173).\n\n'
        '2. Information We Collect\n'
        '• Personal Identifiers: Name, verified email address, mobile phone number, and account credentials.\n'
        '• Fulfillment Information: Shipping and delivery addresses, recipient contact details, and recipient identification for delivery verification.\n'
        '• Transactional Data: Purchase records, payment references, order items, and cancellation or refund requests.\n'
        '• Device & Usage Data: IP address, browser type, login session timestamps, and device identifiers used to detect unauthorized access.\n\n'
        '3. How We Use Your Information\n'
        'We process personal data strictly to fulfill your marketplace purchases, dispatch couriers to your address, provide real-time package tracking, resolve customer support inquiries and ticket complaints, and prevent fraudulent transactions.\n\n'
        '4. Data Sharing & Third Parties\n'
        'We only share your delivery name, address, and contact number with the designated seller and authorized courier partner for the sole purpose of delivering your orders. We do not sell or monetize personal data with third-party advertising networks.\n\n'
        '5. Security & Retention\n'
        'Your data is stored using industry-standard SSL encryption and secured cloud databases. Financial transactions are tokenized through licensed payment gateways. You may manage, download, or request deletion of your account data by contacting support@alona.ph.',
  },
  {
    'id': 2,
    'title': 'Terms & Conditions',
    'version': '1.0',
    'content': '1. Agreement to Terms\n'
        'By creating an account, browsing products, or completing an order on Alona Marketplace, you agree to comply with and be bound by these Terms and Conditions.\n\n'
        '2. Marketplace Intermediary Role\n'
        'Alona functions as an online e-commerce platform that connects independent merchants ("Sellers") with shoppers ("Buyers"). While Alona enforces seller quality guidelines and guarantees buyer protection, contracts of sale are formed directly between the buyer and the seller.\n\n'
        '3. Buyer Obligations & Account Security\n'
        'Buyers must provide accurate, truthful personal and delivery details. You are responsible for safeguarding your login credentials, OTP codes, and password. Any actions executed through your authenticated session are deemed authorized by you.\n\n'
        '4. Pricing, Orders, and Acceptance\n'
        'All listed prices are displayed in Philippine Peso (PHP) inclusive of applicable taxes unless stated otherwise. An order is confirmed once payment authorization is received or Cash on Delivery (COD) eligibility is verified.\n\n'
        '5. Prohibited Conduct\n'
        'Users must not post abusive reviews, fraudulent dispute claims, counterfeit merchandise, or manipulate ratings and inventory. Violations may result in immediate suspension or termination of marketplace privileges.\n\n'
        '6. Governing Law & Dispute Resolution\n'
        'These terms are governed by the laws of the Republic of the Philippines. Parties agree to attempt amicable resolution via the Alona Resolution Center before seeking judicial remedies.',
  },
  {
    'id': 3,
    'title': 'Shipping & Delivery Policy',
    'version': '1.0',
    'content': '1. Delivery Network & Coverage\n'
        'Alona partners with accredited logistics service providers and dedicated sorting centers to offer reliable nationwide parcel delivery across Metro Manila, Luzon, Visayas, and Mindanao.\n\n'
        '2. Standard Shipping Timelines\n'
        '• Metro Manila & Nearby Provinces: 1 to 3 business days following seller dispatch.\n'
        '• Major Provincial Centers: 3 to 5 business days.\n'
        '• Remote or Island Locations: 5 to 7 business days.\n'
        'Delivery estimates may be subject to minor delays during adverse weather disturbances or peak holiday shopping festivals.\n\n'
        '3. Tracking & Delivery Proof\n'
        'Every dispatched parcel is assigned a unique tracking number accessible on your Order Details page. Couriers are strictly required to capture photographic Proof of Delivery (e.g., photo of package with recipient or premises) to confirm handoff.\n\n'
        '4. Delivery Attempts & Failed Handoff\n'
        'Couriers will attempt delivery up to two (2) times. If the recipient is unreachable, the parcel will be held at the local sorting hub for 48 hours before being returned to the seller (Return to Sender / RTS).\n\n'
        '5. Damaged or Tampered Packages\n'
        'If a package arrives visibly broken, unsealed, or tampered with, buyers are advised to take photos before opening and immediately lodge a claim through Complaints & Ticket Status.',
  },
  {
    'id': 4,
    'title': 'Returns & Refunds Policy',
    'version': '1.0',
    'content': '1. Alona Buyer Guarantee Window\n'
        'Buyers may submit a Return & Refund request within seven (7) calendar days from the date of confirmed package delivery.\n\n'
        '2. Eligible Return Reasons\n'
        '• Damaged, defective, or non-functional item.\n'
        '• Incorrect item, size, color, or variant delivered.\n'
        '• Missing components, accessories, or free gifts.\n'
        '• Counterfeit, replica, or materially misrepresented goods.\n\n'
        '3. Non-Returnable Items\n'
        'Items that cannot be returned include perishable items, opened personal care or hygiene goods, digital codes, and custom personalized merchandise, unless defective upon arrival.\n\n'
        '4. Process & Resolution Timeline\n'
        '• Step 1: Open Order Details, select "Request Return", and provide photos/videos of the item.\n'
        '• Step 2: The seller has 48 hours to review the request, accept a return, or issue an immediate refund.\n'
        '• Step 3: If accepted, return the parcel using the provided prepaid return waybill.\n'
        '• Step 4: Upon inspection confirmation, refunds are automatically credited back to your original payment channel (GCash, Maya, Card, or COD e-wallet refund) within 24–72 hours.\n\n'
        '5. Escalation & Platform Mediation\n'
        'If a seller unjustifiably rejects an eligible claim, buyers can escalate the case to Alona Platform Support for definitive mediation.',
  },
  {
    'id': 5,
    'title': 'Payment Methods',
    'version': '1.0',
    'content': '1. Supported Checkout Options\n'
        'Alona supports convenient and flexible payment channels to suit every shopper:\n'
        '• Cash on Delivery (COD): Pay directly in cash to the rider upon parcel delivery.\n'
        '• E-Wallets: Instant checkout via GCash and Maya with QR code or mobile wallet verification.\n'
        '• Credit & Debit Cards: Visa, Mastercard, and JCB cards secured by 3D-Secure authentication.\n'
        '• Platform Vouchers: Combine official platform vouchers and seller promotional discounts for additional savings.\n\n'
        '2. Payment Security & Encryption\n'
        'All payment transactions are encrypted using TLS 1.3 protocol. Alona does not store sensitive card CVV numbers or banking PINs. Card details are tokenized through PCI-DSS Level 1 certified payment gateways.\n\n'
        '3. Cash on Delivery (COD) Rules\n'
        'Please ensure that the exact payment amount or sufficient cash is prepared for the courier upon delivery. COD is available for orders where the total amount and destination address meet courier safety guidelines.\n\n'
        '4. Invoices & Billing Receipts\n'
        'Digital order summaries and payment receipts are generated automatically upon order placement and can be viewed or printed anytime under "My Orders" -> "Order Details".\n\n'
        '5. Billing Inquiries & Unauthorized Transactions\n'
        'If you detect an unrecognized transaction or charge discrepancy, contact our billing help desk immediately at support@alona.ph or through the Complaints & Ticket Status portal.',
  },
];

const List<Map<String, dynamic>> _defaultWebsiteFaqs = [
  {
    'category': 'orders',
    'question': 'How do I place an order on Alona?',
    'answer':
        'Browse products or search for what you need, choose your preferred variant (such as color or size) and quantity, then click "Add to Cart" or "Buy Now". Once in your cart, select the items you wish to purchase, proceed to Checkout, choose your delivery address and payment method, and confirm your order.',
  },
  {
    'category': 'orders',
    'question': 'How do I track the shipping status of my order?',
    'answer':
        'Go to "My Orders" and click on your order to view its real-time tracking timeline. You will see fulfillment milestones (To Pack, Shipped, Out for Delivery, Delivered) as well as the assigned courier name and tracking number. You can click "Copy #" to track the parcel directly with the courier.',
  },
  {
    'category': 'orders',
    'question': 'Can I cancel an order after placing it?',
    'answer':
        'Yes, you can cancel an order as long as it has not reached the shipped or out-for-delivery stage. Open the order in "My Orders", click "Cancel Order", select a cancellation reason, and submit. Any reserved inventory will be instantly restored.',
  },
  {
    'category': 'orders',
    'question': 'How do I reorder items I purchased before?',
    'answer':
        'You can easily reorder past purchases by navigating to "My Orders" or "Order Details" and clicking the "Buy Again" button next to any eligible item or for the whole order.',
  },
  {
    'category': 'payments',
    'question': 'What payment methods can I use at checkout?',
    'answer':
        'Alona supports multiple flexible payment options: Cash on Delivery (COD), e-wallets (GCash and Maya), and Credit/Debit Cards (Visa, Mastercard, JCB). You can also save payment methods securely in your account for rapid one-click checkout.',
  },
  {
    'category': 'payments',
    'question': 'Is my card and payment information secure?',
    'answer':
        'Yes, absolutely. Alona complies with modern payment security standards. Only the last 4 digits and expiration date are stored for saved payment methods. Sensitive card numbers and CVVs are never saved on our servers.',
  },
  {
    'category': 'payments',
    'question': 'How are shipping fees calculated at checkout?',
    'answer':
        'Shipping fees are calculated based on the delivery province and municipality you select, combined with the courier shipping option chosen (e.g. Standard Delivery vs. Express Shipping). Any applicable shipping discount vouchers will be automatically applied to the total.',
  },
  {
    'category': 'shipping',
    'question': 'How long will it take for my order to arrive?',
    'answer':
        'Metro Manila and nearby provinces typically take 1–3 business days. Provincial areas usually arrive within 3–7 business days. You can monitor courier tracking updates directly on your Order Details page.',
  },
  {
    'category': 'shipping',
    'question': 'What should I do if my package is delayed?',
    'answer':
        'If your order tracking has not updated for several days past the expected delivery date, you can click "Contact Seller" on the order page or open a complaint ticket through the Help Center for platform assistance.',
  },
  {
    'category': 'returns',
    'question': 'How do I request a return or refund?',
    'answer':
        'Go to "My Orders", open the delivered order, and click "Return / Refund" next to the specific item. Select whether you are requesting a full return & refund or refund only, choose the appropriate reason (e.g. damaged, wrong item, counterfeit), provide a description, and upload photo/video proof.',
  },
  {
    'category': 'returns',
    'question': 'How long does a seller have to respond to a return request?',
    'answer':
        'Sellers are given 48 to 72 hours to review and respond to return requests. If the seller approves or fails to respond within the timeframe, the platform administrative team will step in to resolve the refund in your favor.',
  },
  {
    'category': 'returns',
    'question': 'When will I receive my refund?',
    'answer':
        'Once approved, refunds are credited back to your original payment method or wallet within 2 to 5 business days depending on your financial institution.',
  },
  {
    'category': 'vouchers',
    'question': 'How do I claim and use discount vouchers?',
    'answer':
        'Visit the "Vouchers & Discounts" page from the main navigation or dashboard. Click "Claim Voucher" on any active promotion. Claimed vouchers will automatically appear in your voucher selection menu during checkout if your cart meets the minimum spend requirements.',
  },
  {
    'category': 'vouchers',
    'question': 'Will I be notified when my vouchers are about to expire?',
    'answer':
        'Yes! Alona sends automated notification alerts to your inbox when a claimed voucher has 3 days or fewer remaining before expiration.',
  },
  {
    'category': 'safety',
    'question': 'How do I report a suspicious product or misconduct by a seller?',
    'answer':
        'On any product detail page or seller store page, click the "Report Product" or "Report Seller" button. Select the reason for your report (e.g. Counterfeit / Fake item, Prohibited content, Fraud, Misleading specifications) and attach evidence. Our compliance and safety team investigates all reports thoroughly.',
  },
  {
    'category': 'safety',
    'question': 'How can I track the status of my submitted complaints?',
    'answer':
        'Navigate to "My Complaints" under your buyer account menu or Help Center. You can view the review stage (Pending, Reviewing, Resolved, Rejected), submitted evidence, and the official admin resolution notes.',
  },
  {
    'category': 'account',
    'question': 'How do I change my saved delivery addresses?',
    'answer':
        'Go to your account profile dropdown and select "My Addresses". You can add new addresses, set a default address for instant checkout, edit existing entries, or delete addresses you no longer use.',
  },
  {
    'category': 'account',
    'question': 'How do I update my password and security settings?',
    'answer':
        'Navigate to "My Account" → "Password & Security". Enter your current password and your new secure password to update your login credentials.',
  },
];

