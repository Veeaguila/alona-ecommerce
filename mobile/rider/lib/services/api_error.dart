import 'dart:convert';

import 'package:http/http.dart' as http;

String apiErrorMessage(http.Response response) {
  if (response.statusCode == 422) {
    try {
      final payload = jsonDecode(response.body);
      if (payload['code'] == 'valid_photo_required') {
        return 'A valid delivery photo is required. Capture and upload the photo again, then retry.';
      }
      final errors = payload['errors'];
      if (errors is Map) {
        final messages = errors.values
            .whereType<List>()
            .expand((values) => values.whereType<String>())
            .take(5)
            .toList();
        if (messages.isNotEmpty) {
          return messages
              .map(
                (value) => value.length > 200 ? value.substring(0, 200) : value,
              )
              .join('\n');
        }
      }
    } catch (_) {
      /* Never display raw HTML or server traces. */
    }
    return 'Some fields or the current task state are invalid. Check the details and retry.';
  }
  return switch (response.statusCode) {
    401 => 'Your session has expired. Please sign in.',
    403 => 'This action is not allowed for your account or assigned task.',
    404 => 'This item is no longer available. Refresh and retry.',
    409 => 'This task has changed or was accepted by another rider. Refresh and retry.',
    413 => 'This upload exceeds the server limit. Use a smaller file or contact your sorting center.',
    429 => 'Too many requests. Wait a moment before trying again.',
    _ => 'The service could not complete this request. Please retry shortly.',
  };
}
