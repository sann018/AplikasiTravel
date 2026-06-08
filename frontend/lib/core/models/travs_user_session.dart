import 'app_role_preview.dart';

class TravSUserProfile {
  const TravSUserProfile({
    required this.id,
    required this.nama,
    required this.email,
    required this.noHp,
    required this.peran,
    required this.statusAktif,
    this.fotoProfil,
    this.nomorSim,
  });

  final int id;
  final String nama;
  final String email;
  final String noHp;
  final String peran;
  final bool statusAktif;
  final String? fotoProfil;
  final String? nomorSim;

  bool get isPassenger => peran == 'penumpang';
  bool get isDriver => peran == 'pengemudi';
  bool get isAdmin => peran == 'admin';

  TravSUserProfile copyWith({
    String? nama,
    String? email,
    String? noHp,
    String? fotoProfil,
  }) {
    return TravSUserProfile(
      id: id,
      nama: nama ?? this.nama,
      email: email ?? this.email,
      noHp: noHp ?? this.noHp,
      peran: peran,
      statusAktif: statusAktif,
      fotoProfil: fotoProfil ?? this.fotoProfil,
      nomorSim: nomorSim,
    );
  }

  AppRolePreview get rolePreview {
    switch (peran) {
      case 'pengemudi':
        return AppRolePreview.driver;
      case 'penumpang':
        return AppRolePreview.passenger;
      default:
        return AppRolePreview.guest;
    }
  }

  factory TravSUserProfile.fromJson(Map<String, dynamic> json) {
    return TravSUserProfile(
      id: _readInt(json['id']),
      nama: json['nama'] as String? ?? '-',
      email: json['email'] as String? ?? '-',
      noHp: json['no_hp'] as String? ?? '-',
      peran: json['peran'] as String? ?? 'guest',
      statusAktif: json['status_aktif'] as bool? ?? false,
      fotoProfil:
          json['foto_profil_url'] as String? ?? json['foto_profil'] as String?,
      nomorSim: json['nomor_sim'] as String?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'nama': nama,
      'email': email,
      'no_hp': noHp,
      'peran': peran,
      'status_aktif': statusAktif,
      'foto_profil': fotoProfil,
      'foto_profil_url': fotoProfil,
      'nomor_sim': nomorSim,
    };
  }
}

class TravSUserSession {
  const TravSUserSession({required this.token, required this.user});

  final String token;
  final TravSUserProfile user;

  AppRolePreview get rolePreview => user.rolePreview;

  TravSUserSession copyWith({TravSUserProfile? user}) {
    return TravSUserSession(token: token, user: user ?? this.user);
  }

  factory TravSUserSession.fromJson(Map<String, dynamic> json) {
    return TravSUserSession(
      token: json['token'] as String? ?? '',
      user: TravSUserProfile.fromJson(
        json['user'] as Map<String, dynamic>? ?? <String, dynamic>{},
      ),
    );
  }

  Map<String, dynamic> toJson() {
    return {'token': token, 'user': user.toJson()};
  }
}

int _readInt(dynamic value) {
  if (value is int) {
    return value;
  }

  if (value is num) {
    return value.toInt();
  }

  return int.tryParse(value?.toString() ?? '') ?? 0;
}
