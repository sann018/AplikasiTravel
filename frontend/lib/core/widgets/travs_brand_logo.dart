import 'package:flutter/material.dart';

import '../services/travs_api_service.dart';
import '../theme/travs_theme.dart';

class TravSBrandLogo extends StatelessWidget {
  const TravSBrandLogo({
    required this.imageUrl,
    this.size = 72,
    this.borderRadius = 20,
    super.key,
  });

  final String? imageUrl;
  final double size;
  final double borderRadius;

  @override
  Widget build(BuildContext context) {
    final resolvedUrl = TravSApiService.resolveMediaUrl(imageUrl);

    return Container(
      width: size,
      height: size,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(borderRadius),
        border: Border.all(color: Colors.white24),
      ),
      child: resolvedUrl == null
          ? _fallback()
          : Image.network(
              resolvedUrl,
              width: size,
              height: size,
              fit: BoxFit.contain,
              filterQuality: FilterQuality.medium,
              cacheWidth: (size * 3).round(),
              loadingBuilder: (context, child, progress) {
                if (progress == null) return child;
                return const Center(
                  child: CircularProgressIndicator(
                    color: TravSTheme.navy,
                    strokeWidth: 2,
                  ),
                );
              },
              errorBuilder: (context, error, stackTrace) => _fallback(),
            ),
    );
  }

  Widget _fallback() {
    return const Center(
      child: Text(
        'R',
        style: TextStyle(
          color: TravSTheme.navy,
          fontSize: 28,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}
