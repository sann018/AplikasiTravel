import 'package:flutter/material.dart';

import '../../core/theme/travs_theme.dart';
import '../../core/models/app_settings.dart';
import '../../core/widgets/travs_brand_logo.dart';

class TravSSplashPage extends StatelessWidget {
  const TravSSplashPage({required this.appSettings, super.key});

  final AppSettings appSettings;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(gradient: TravSTheme.headerGradient),
        child: SafeArea(
          child: Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                TravSBrandLogo(
                  imageUrl: appSettings.appLogoUrl,
                  size: 90,
                  borderRadius: 28,
                ),
                const SizedBox(height: 20),
                Text(
                  appSettings.appName,
                  style: theme.textTheme.headlineMedium?.copyWith(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  'Pemesanan travel lintas kota\nlebih tertata dan lebih jelas.',
                  textAlign: TextAlign.center,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: Colors.white70,
                  ),
                ),
                const SizedBox(height: 36),
                SizedBox(
                  width: 42,
                  child: LinearProgressIndicator(
                    minHeight: 4,
                    borderRadius: BorderRadius.circular(999),
                    backgroundColor: Colors.white24,
                    valueColor: const AlwaysStoppedAnimation<Color>(
                      TravSTheme.orange,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
