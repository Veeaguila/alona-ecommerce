import 'dart:convert';

import 'package:file_picker/file_picker.dart';
import 'package:http/http.dart' as http;

import 'api_config.dart';

class RegistrationApi {
  final http.Client? client;
  RegistrationApi({this.client});

  Future<List<Map<String, dynamic>>> directory(
    String endpoint, {
    Map<String, String> query = const {},
  }) async {
    final uri = Uri.parse('$apiBaseUrl/$endpoint')
        .replace(queryParameters: query.isEmpty ? null : query);
    final response =
        await (client != null
                ? client!.get(uri, headers: {'Accept': 'application/json'})
                : http.get(uri, headers: {'Accept': 'application/json'}))
            .timeout(const Duration(seconds: 15));
    if (response.statusCode != 200) {
      throw const FormatException('Directory unavailable');
    }
    final payload = jsonDecode(response.body) as Map<String, dynamic>;
    return List<Map<String, dynamic>>.from(payload['data'] as List);
  }

  static List<Map<String, dynamic>> matchingPartners(
    List<Map<String, dynamic>> coverage,
    Set<String> codes,
  ) {
    if (codes.isEmpty) return [];
    Map<int, Map<String, dynamic>>? common;
    for (final code in codes) {
      final rows = coverage.where((row) => row['code'] == code);
      if (rows.isEmpty) return [];
      final partners = <int, Map<String, dynamic>>{
        for (final p in List<Map<String, dynamic>>.from(
          rows.first['partners'] as List,
        ))
          (p['id'] as num).toInt(): p,
      };
      if (common == null) {
        common = partners;
      } else {
        common.removeWhere((id, _) => !partners.containsKey(id));
      }
    }
    final result = common!.values.toList();
    result.sort(
      (a, b) => a['business_name'].toString().compareTo(
        b['business_name'].toString(),
      ),
    );
    return result;
  }

  static http.MultipartRequest request(
    Map<String, String> fields,
    Set<String> deliveryCities,
    Set<String> licenseCodes,
    Map<String, PlatformFile> documents,
  ) {
    final request = http.MultipartRequest('POST', Uri.parse(apiUrl))
      ..headers['Accept'] = 'application/json'
      ..fields.addAll(fields);
    var index = 0;
    for (final code in deliveryCities) {
      request.fields['delivery_city_codes[${index++}]'] = code;
    }
    index = 0;
    final motor = fields['vehicle_type'] != 'bicycle';
    if (motor) {
      for (final code in licenseCodes) {
        request.fields['license_codes[${index++}]'] = code;
      }
    }
    for (final entry in documents.entries) {
      if (!motor && ['license_doc', 'or_cr_doc'].contains(entry.key)) continue;
      final file = entry.value;
      if (file.bytes == null || file.size > 5 * 1024 * 1024) {
        throw const FormatException(
          'Documents must be readable and at most 5 MB.',
        );
      }
      request.files.add(
        http.MultipartFile.fromBytes(
          entry.key,
          file.bytes!,
          filename: file.name,
        ),
      );
    }
    return request;
  }

  Future<http.Response> submit(http.MultipartRequest request) async {
    final response =
        await (client != null ? client!.send(request) : request.send()).timeout(
          const Duration(seconds: 30),
        );
    return http.Response.fromStream(response)
        .timeout(const Duration(seconds: 30));
  }
}
