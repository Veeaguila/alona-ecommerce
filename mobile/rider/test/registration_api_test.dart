import 'dart:typed_data';
import 'dart:convert';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:rider_app/services/registration_api.dart';
import 'package:rider_app/pages/registration_page.dart';

PlatformFile document(String name) =>
    PlatformFile(name: name, size: 4, bytes: Uint8List.fromList([1, 2, 3, 4]));
void main() {
  test('bicycle multipart uses canonical fields, array names, consent and identity only', () async {
    final request = RegistrationApi.request(
      {
        'vehicle_type': 'bicycle',
        'contact_no': '09123456789',
        'password_confirmation': 'test-password',
        'province_code': '',
        'terms': '1',
        'privacy': '1',
      },
      {'1374040000', '1374050000'},
      {'A'},
      {
        'id_doc': document('id.pdf'),
        'license_doc': document('license.pdf'),
        'or_cr_doc': document('orcr.pdf'),
      },
    );
    final api = RegistrationApi(
      client: MockClient((sent) async {
        expect(sent.method, 'POST');
        expect(sent.url.path, '/api/rider/register');
        expect(sent.headers['Accept'], 'application/json');
        expect(sent.body, contains('name="delivery_city_codes[0]"'));
        expect(sent.body, contains('name="delivery_city_codes[1]"'));
        expect(sent.body, contains('name="password_confirmation"'));
        expect(sent.body, contains('name="contact_no"'));
        expect(sent.body, contains('name="id_doc"'));
        expect(sent.body, isNot(contains('name="license_doc"')));
        expect(sent.body, isNot(contains('name="license_codes')));
        expect(
          sent.headers.keys.any((key) => key.toLowerCase() == 'authorization'),
          false,
        );
        return http.Response('{"data":{"approval_status":"pending"}}', 201);
      }),
    );
    expect((await api.submit(request)).statusCode, 201);
  });
  test('motorized multipart includes license arrays and documents', () {
    final request = RegistrationApi.request(
      {'vehicle_type': 'van', 'license_class': 'professional'},
      {'1374040000'},
      {'B1', 'B2'},
      {
        'id_doc': document('id.pdf'),
        'or_cr_doc': document('orcr.pdf'),
        'license_doc': document('license.pdf'),
      },
    );
    expect(request.fields['license_codes[0]'], 'B1');
    expect(request.fields['license_codes[1]'], 'B2');
    expect(
      request.files.map((file) => file.field),
      containsAll(['id_doc', 'license_doc', 'or_cr_doc']),
    );
  });
  test('partners must cover every chosen delivery city and unknown cities fail closed', () {
    final rows = <Map<String, dynamic>>[
      {
        'code': 'city-a',
        'partners': [
          {'id': 1, 'business_name': 'Center A'},
          {'id': 2, 'business_name': 'Center B'},
        ],
      },
      {
        'code': 'city-b',
        'partners': [
          {'id': 2, 'business_name': 'Center B'},
        ],
      },
    ];
    expect(
      RegistrationApi.matchingPartners(rows, {'city-a', 'city-b'}).single['id'],
      2,
    );
    expect(RegistrationApi.matchingPartners(rows, {'missing'}), isEmpty);
    expect(RegistrationApi.matchingPartners(rows, {}), isEmpty);
  });
  test('unreadable or oversized documents cannot be uploaded', () {
    for (final doc in [
      PlatformFile(name: 'id.pdf', size: 1),
      PlatformFile(name: 'id.pdf', size: 6 * 1024 * 1024, bytes: Uint8List(1)),
    ]) {
      expect(
        () => RegistrationApi.request(
          {'vehicle_type': 'bicycle'},
          {'city'},
          {},
          {'id_doc': doc},
        ),
        throwsFormatException,
      );
    }
  });
  test(
    'address directory sends parent codes and rejects non-JSON responses',
    () async {
      final api = RegistrationApi(
        client: MockClient((request) async {
          expect(request.url.path, '/api/addresses/cities');
          expect(request.url.queryParameters['parent'], '1300000000');
          return http.Response('<html>unavailable</html>', 200);
        }),
      );
      await expectLater(
        api.directory('addresses/cities', query: {'parent': '1300000000'}),
        throwsFormatException,
      );
    },
  );
  testWidgets(
    'registration preserves heading and shows real coverage choices',
    (tester) async {
      final api = RegistrationApi(
        client: MockClient(
          (request) async => http.Response(
            jsonEncode({
              'data': request.url.path.endsWith('/regions')
                  ? [
                      {'code': '1300000000', 'name': 'National Capital Region'},
                    ]
                  : [
                      {
                        'code': '1374040000',
                        'name': 'Quezon City',
                        'label': 'Quezon City · NCR',
                        'partners': [
                          {'id': 1, 'business_name': 'Approved center'},
                        ],
                      },
                    ],
            }),
            200,
          ),
        ),
      );
      await tester.pumpWidget(MaterialApp(home: RegistrationPage(api: api)));
      await tester.pumpAndSettle();
      expect(find.text('Create your rider profile'), findsOneWidget);
      expect(find.text('Delivery area to add'), findsOneWidget);
      expect(
        find.textContaining('Choose at least one delivery area'),
        findsOneWidget,
      );
      expect(tester.takeException(), isNull);
    },
  );
}
