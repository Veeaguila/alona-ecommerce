import 'package:flutter/material.dart';

import 'pages/login_page.dart';
import 'theme/rider_theme.dart';

class RiderApp extends StatelessWidget {
  const RiderApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'ALONA Rider',
      debugShowCheckedModeBanner: false,
      theme: RiderTheme.theme,
      home: const LoginPage(),
    );
  }
}
