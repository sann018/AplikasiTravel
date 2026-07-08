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

    return SizedBox(
      width: size,
      height: size,
      child: resolvedUrl == null
          ? _fallback()
          : ClipRRect(
              borderRadius: BorderRadius.circular(borderRadius),
              child: Image.network(
                resolvedUrl,
                width: size,
                height: size,
                fit: BoxFit.contain,
                filterQuality: FilterQuality.medium,
                cacheWidth: (size * 3).round(),
                // Logo ditampilkan langsung agar PNG transparan tidak tertutup
                // bingkai putih pada halaman login, daftar, dan splash.
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
            ),
    );
  }

  Widget _fallback() {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.18),
        shape: BoxShape.circle,
      ),
      child: const Center(
        child: Text(
          'R',
          style: TextStyle(
            color: TravSTheme.navy,
            fontSize: 28,
            fontWeight: FontWeight.w800,
          ),
        ),
      ),
    );
  }
}
