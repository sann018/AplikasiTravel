import 'dart:async';
import 'dart:convert';

import 'package:web_socket_channel/web_socket_channel.dart';

import '../models/app_settings.dart';

typedef TravSSeatChangedCallback = Future<void> Function();

class TravSSeatRealtimeService {
  TravSSeatRealtimeService({
    required TravSRealtimeSettings settings,
    required int scheduleId,
    required TravSSeatChangedCallback onSeatChanged,
  }) : _settings = settings,
       _scheduleId = scheduleId,
       _onSeatChanged = onSeatChanged;

  final TravSRealtimeSettings _settings;
  final int _scheduleId;
  final TravSSeatChangedCallback _onSeatChanged;

  WebSocketChannel? _channel;
  StreamSubscription<dynamic>? _subscription;
  Timer? _reconnectTimer;
  bool _connecting = false;
  bool _disposed = false;
  int _reconnectAttempt = 0;

  String get _channelName => '${_settings.seatChannelPrefix}$_scheduleId';

  Uri get _socketUri {
    final scheme = _settings.scheme.toLowerCase() == 'wss' ? 'wss' : 'ws';

    return Uri(
      scheme: scheme,
      host: _settings.host,
      port: _settings.port,
      path: '/app/${Uri.encodeComponent(_settings.appKey)}',
      queryParameters: const <String, String>{
        'protocol': '7',
        'client': 'travs-flutter',
        'version': '1.0',
        'flash': 'false',
      },
    );
  }

  /// Koneksi gagal tidak menutup halaman karena polling API tetap menjadi cadangan.
  Future<void> connect() async {
    if (_disposed ||
        !_settings.isConfigured ||
        _connecting ||
        _channel != null) {
      return;
    }

    _connecting = true;
    try {
      final channel = WebSocketChannel.connect(_socketUri);
      await channel.ready;

      if (_disposed) {
        await channel.sink.close();
        return;
      }

      _channel = channel;
      _reconnectAttempt = 0;
      _subscription = channel.stream.listen(
        _handleMessage,
        onError: (_) => _handleDisconnected(),
        onDone: _handleDisconnected,
        cancelOnError: true,
      );
    } catch (_) {
      _scheduleReconnect();
    } finally {
      _connecting = false;
    }
  }

  Future<void> _handleMessage(dynamic rawMessage) async {
    if (rawMessage is! String) return;

    final decoded = jsonDecode(rawMessage);
    if (decoded is! Map) return;

    final message = Map<String, dynamic>.from(decoded);
    final eventName = message['event']?.toString();

    if (eventName == 'pusher:connection_established') {
      _channel?.sink.add(
        jsonEncode(<String, dynamic>{
          'event': 'pusher:subscribe',
          'data': <String, dynamic>{'channel': _channelName},
        }),
      );
      return;
    }

    if (eventName == 'pusher:ping') {
      _channel?.sink.add(
        jsonEncode(<String, dynamic>{
          'event': 'pusher:pong',
          'data': const <String, dynamic>{},
        }),
      );
      return;
    }

    if (eventName == _settings.seatEvent &&
        message['channel']?.toString() == _channelName) {
      // Event hanya menjadi sinyal; data kursi terbaru tetap diambil dari API.
      await _onSeatChanged();
    }
  }

  void _handleDisconnected() {
    _subscription = null;
    _channel = null;
    _scheduleReconnect();
  }

  void _scheduleReconnect() {
    if (_disposed || !_settings.isConfigured || _reconnectTimer != null) {
      return;
    }

    _reconnectAttempt++;
    final delaySeconds = switch (_reconnectAttempt) {
      <= 1 => 2,
      2 => 5,
      3 => 10,
      _ => 20,
    };

    _reconnectTimer = Timer(Duration(seconds: delaySeconds), () {
      _reconnectTimer = null;
      unawaited(connect());
    });
  }

  Future<void> reconnect() async {
    if (_disposed) return;
    await _closeCurrentConnection();
    await connect();
  }

  Future<void> dispose() async {
    _disposed = true;
    _reconnectTimer?.cancel();
    _reconnectTimer = null;
    await _closeCurrentConnection();
  }

  Future<void> _closeCurrentConnection() async {
    final subscription = _subscription;
    final channel = _channel;
    _subscription = null;
    _channel = null;

    await subscription?.cancel();
    await channel?.sink.close();
  }
}
