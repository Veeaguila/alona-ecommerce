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

void main() {
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
    expect(find.text('Server API Configuration'), findsOneWidget);
    expect(find.text('Logistics Portal'), findsOneWidget);
    expect(find.text('Sign Out'), findsOneWidget);
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

      // Verify Wishlist is in bottom nav
      expect(find.text('Wishlist'), findsOneWidget);
      expect(find.text('Home'), findsOneWidget);
      expect(find.text('Shop'), findsOneWidget);
      expect(find.text('Orders'), findsOneWidget);
      expect(find.text('Account'), findsOneWidget);
    },
  );
}
