import 'package:intl/date_symbol_data_local.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'package:frontend/app.dart';

void main() {
  setUpAll(() async {
    await initializeDateFormatting('id_ID');
  });

  setUp(() {
    SharedPreferences.setMockInitialValues(<String, Object>{});
  });

  testWidgets('renders auth flow when no saved session', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(const TravSApp());
    await tester.pumpAndSettle();

    expect(find.text('TravS'), findsOneWidget);
    expect(find.text('Masuk ke TravS'), findsOneWidget);
    expect(find.text('Lanjut Sebagai Tamu'), findsOneWidget);
  });
}
