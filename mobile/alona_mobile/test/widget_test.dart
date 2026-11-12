import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:alona_mobile/core/config/app_theme.dart';
import 'package:alona_mobile/core/services/auth_state.dart';
import 'package:alona_mobile/shared/widgets/status_badge.dart';
import 'package:alona_mobile/shared/widgets/empty_state_view.dart';
import 'package:alona_mobile/shared/widgets/alona_button.dart';
import 'package:alona_mobile/features/buyer/presentation/orders/order_list_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/profile/buyer_profile_screen.dart';
import 'package:alona_mobile/features/buyer/presentation/buyer_main_screen.dart';
import 'package:alona_mobile/features/buyer/models/buyer_models.dart';
import 'package:alona_mobile/features/buyer/presentation/home/widgets/product_card.dart';

void main() {
  testWidgets('Product cards fit compact phone grids with larger text', (
    WidgetTester tester,
  ) async {
    final product = ProductModel(
      id: 1,
      name: 'Radiant Citrus Eau de Parfum',
      slug: 'sample-product',
      price: 1200,
      oldPrice: 1500,
      stock: 20,
      rating: 5,
      reviewsCount: 2,
      categoryName: 'Electronics & Mobiles',
      sellerStoreName: 'Sample store',
      imageUrl: '',
    );

    for (final scale in [1.0, 1.3]) {
      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.buyerTheme,
          home: MediaQuery(
            data: MediaQueryData(textScaler: TextScaler.linear(scale)),
            child: Scaffold(
              body: Center(
                child: SizedBox(
                  width: 156,
                  height: 241,
                  child: ProductCard(product: product),
                ),
              ),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(tester.takeException(), isNull);
      expect(find.text('₱1,200.00'), findsOneWidget);
    }
  });

  testWidgets('StatusBadge renders correctly with status', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(body: StatusBadge(status: 'DELIVERED')),
      ),
    );
    expect(find.text('Delivered'), findsOneWidget);
    expect(find.text('Completed'), findsNothing);
  });

  testWidgets('EmptyStateView renders title and action button', (
    WidgetTester tester,
  ) async {
    bool tapped = false;
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: EmptyStateView(
            icon: Icons.shopping_bag_outlined,
            title: 'No items yet',
            message: 'Start browsing to add items',
            buttonText: 'Browse Now',
            onButtonPressed: () => tapped = true,
          ),
        ),
      ),
    );
    expect(find.text('No items yet'), findsOneWidget);
    expect(find.text('Browse Now'), findsOneWidget);
    await tester.tap(find.text('Browse Now'));
    expect(tapped, isTrue);
  });

  testWidgets('AlonaButton renders and triggers callback', (
    WidgetTester tester,
  ) async {
    bool pressed = false;
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.lightTheme,
        home: Scaffold(
          body: AlonaButton(text: 'Checkout', onPressed: () => pressed = true),
        ),
      ),
    );
    expect(find.text('Checkout'), findsOneWidget);
    await tester.tap(find.text('Checkout'));
    expect(pressed, isTrue);
  });

  testWidgets('BuyerProfileScreen renders redesign elements correctly', (
    WidgetTester tester,
  ) async {
    SharedPreferences.setMockInitialValues({});
    final authState = AuthState();
    // Simulate logged in buyer
    await authState.setBuyerSession('fake-token', {
      'id': 1,
      'name': 'aguilajayve85',
      'email': 'aguilajayve85@gmail.com',
    });

    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.lightTheme,
        home: BuyerProfileScreen(authState: authState),
      ),
    );
    await tester.pump();

    expect(find.text('My Account'), findsOneWidget);
    expect(find.text('aguilajayve85'), findsOneWidget);
    expect(find.text('aguilajayve85@gmail.com'), findsOneWidget);
    expect(find.text('Buyer'), findsOneWidget);
    expect(find.text('My Purchases'), findsOneWidget);
    expect(find.text('View all orders'), findsOneWidget);
    expect(find.text('To Pay'), findsOneWidget);
    expect(find.text('To Ship'), findsOneWidget);
    expect(find.text('To Receive'), findsOneWidget);
    expect(find.text('Completed'), findsOneWidget);
    expect(find.text('Delivery Addresses'), findsOneWidget);
    expect(find.text('My Wishlist'), findsOneWidget);
    expect(find.text('Vouchers & Coupons'), findsOneWidget);
  });

  testWidgets(
    'OrderListScreen renders redesign empty state and tabs correctly',
    (WidgetTester tester) async {
      SharedPreferences.setMockInitialValues({});
      bool browseTapped = false;

      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.lightTheme,
          home: OrderListScreen(onBrowseProducts: () => browseTapped = true),
        ),
      );
      await tester.pump();

      expect(find.text('My Orders'), findsOneWidget);
      expect(find.text('All'), findsOneWidget);
      expect(find.text('To Pay'), findsOneWidget);
      expect(find.text('To Ship'), findsOneWidget);
      expect(find.text('To Receive'), findsOneWidget);
      expect(find.text('Completed'), findsOneWidget);
      expect(find.text('Cancelled'), findsOneWidget);
      expect(browseTapped, isFalse);
    },
  );

  testWidgets(
    'BuyerMainScreen renders floating nav with Wishlist instead of Cart',
    (WidgetTester tester) async {
      SharedPreferences.setMockInitialValues({});
      final authState = AuthState();

      await tester.pumpWidget(
        MaterialApp(
          theme: AppTheme.lightTheme,
          home: BuyerMainScreen(authState: authState),
        ),
      );
      await tester.pump();

      // Verify Wishlist and navigation tabs in bottom nav
      expect(find.text('Wishlist'), findsOneWidget);
      expect(find.text('Home'), findsOneWidget);
      expect(find.text('Shop'), findsOneWidget);
      expect(find.text('Notifications'), findsOneWidget);
      expect(find.text('Account'), findsOneWidget);
    },
  );
}
