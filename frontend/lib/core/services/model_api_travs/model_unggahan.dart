part of '../travs_api_service.dart';

class TravSUploadedBaggagePhoto {
  const TravSUploadedBaggagePhoto({
    required this.path,
    required this.url,
    required this.originalName,
  });

  final String path;
  final String url;
  final String originalName;

  factory TravSUploadedBaggagePhoto.fromJson(Map<String, dynamic> json) {
    return TravSUploadedBaggagePhoto(
      path: json['path'] as String? ?? '',
      url: json['url'] as String? ?? '',
      originalName: json['original_name'] as String? ?? '',
    );
  }
}

class TravSUploadedProfilePhoto {
  const TravSUploadedProfilePhoto({required this.path, required this.url});

  final String path;
  final String url;

  factory TravSUploadedProfilePhoto.fromJson(Map<String, dynamic> json) {
    return TravSUploadedProfilePhoto(
      path: json['foto_profil'] as String? ?? '',
      url: json['foto_profil_url'] as String? ?? '',
    );
  }
}

class TravSUploadedVehiclePhoto {
  const TravSUploadedVehiclePhoto({required this.path, required this.url});

  final String path;
  final String url;

  factory TravSUploadedVehiclePhoto.fromJson(Map<String, dynamic> json) {
    return TravSUploadedVehiclePhoto(
      path: json['foto_path'] as String? ?? '',
      url: json['foto_url'] as String? ?? '',
    );
  }
}
