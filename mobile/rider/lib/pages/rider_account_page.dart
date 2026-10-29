import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';

import '../services/api_config.dart';
import '../services/api_error.dart';
import '../services/rider_portal_api.dart';
import '../services/rider_session.dart';
import '../services/rider_session_guard.dart';
import '../theme/rider_theme.dart';
import '../widgets/rider_content.dart';
import '../widgets/rider_handovers.dart';
import 'login_page.dart';
import 'rider_address_edit_page.dart';
import 'rider_cod_page.dart';
import 'rider_returns_page.dart';

class RiderAccountPage extends StatefulWidget {
  final RiderSession? session;
  final Future<XFile?> Function()? choosePhoto;
  const RiderAccountPage({super.key, this.session, this.choosePhoto});
  @override
  State<RiderAccountPage> createState() => _RiderAccountPageState();
}

class _RiderAccountPageState extends State<RiderAccountPage>
    with RiderSessionGuard<RiderAccountPage> {
  @override
  late final riderSession = widget.session ?? RiderSession();
  late final api = RiderPortalApi(riderSession);
  final contact = TextEditingController();
  final scroll = ScrollController();
  Map<String, dynamic>? profile;
  Uint8List? photo;
  bool loading = true, saving = false;
  String? error, success;
  Map get details => profile?['rider_profile'] as Map? ?? {};

  @override
  void initState() {
    super.initState();
    load();
  }

  @override
  void dispose() {
    scroll.dispose();
    contact.dispose();
    super.dispose();
  }

  String value(dynamic field) =>
      field == null || field.toString().trim().isEmpty
          ? 'Not provided'
          : field.toString();

  Future<String> token() async {
    final value = await riderSession.tokens.read();
    if (value == null || value.isEmpty) {
      throw const SessionFailure('Please sign in.', accessDenied: true);
    }
    return value;
  }

  Future<void> check(http.Response response) async {
    if (response.statusCode == 401) {
      await riderSession.tokens.clear();
      throw const SessionFailure(
        'Your session has expired. Please sign in.',
        accessDenied: true,
      );
    }
    if (response.statusCode != 200) {
      throw SessionFailure(apiErrorMessage(response));
    }
  }

  Future<void> load() async {
    if (!mounted) return;
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final payload = await sessionAction(
        () => api.request('partner/profile', ready: false),
      );
      final data = Map<String, dynamic>.from(payload['data'] as Map);
      if (data['role'] != 'rider') {
        throw const SessionFailure(
          'This account is not a rider.',
          accessDenied: true,
        );
      }
      if (!mounted) return;
      setState(() {
        profile = data;
        contact.text =
            data['rider_profile']?['contact_no']?.toString() ??
            data['profile']?['details']?['contact_no']?.toString() ??
            '';
        photo = null;
      });
      if (details['photo_available'] == true) {
        final response = await sessionAction(() async {
          final response = await riderSession.get(
            Uri.parse('$apiBaseUrl/rider/profile-photo'),
            headers: {'Authorization': 'Bearer ${await token()}'},
          );
          await check(response);
          return response;
        });
        if (mounted) setState(() => photo = response.bodyBytes);
      }
    } catch (failure) {
      if (mounted) {
        setState(
          () => error = failure is SessionFailure
              ? failure.message
              : 'Could not load your profile. Refresh to retry.',
        );
      }
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  Future<void> save() async {
    if (saving || loading) return;
    if (!RegExp(r'^(09\d{9}|\+639\d{9})$').hasMatch(contact.text.trim())) {
      setState(() => error = 'Use a valid 09 or +639 mobile number.');
      return;
    }
    setState(() {
      saving = true;
      error = null;
      success = null;
    });
    try {
      await sessionAction(
        () => api.request(
          'rider/profile',
          method: 'PATCH',
          body: {'contact_no': contact.text.trim()},
          ready: false,
        ),
      );
      if (mounted) setState(() => success = 'Contact number updated.');
    } catch (failure) {
      if (mounted) {
        setState(
          () => error = failure is SessionFailure
              ? failure.message
              : 'Profile update could not be confirmed. Refresh before retrying.',
        );
      }
    } finally {
      if (mounted) {
        if (scroll.hasClients) scroll.jumpTo(0);
        setState(() => saving = false);
      }
    }
  }

  Future<void> uploadPhoto() async {
    if (saving || loading) return;
    setState(() {
      saving = true;
      error = null;
      success = null;
    });
    try {
      final image =
          await (widget.choosePhoto?.call() ??
              ImagePicker().pickImage(
                source: ImageSource.gallery,
                maxWidth: 1200,
                maxHeight: 1200,
                imageQuality: 85,
              ));
      if (image == null) return;
      if (await image.length() > 5 * 1024 * 1024) {
        throw const SessionFailure('Choose a photo smaller than 5 MB.');
      }
      final bytes = await image.readAsBytes();
      if (bytes.isEmpty) {
        throw const SessionFailure(
          'This photo is empty. Choose another photo.',
        );
      }
      final buffer = await ui.ImmutableBuffer.fromUint8List(bytes);
      try {
        final descriptor = await ui.ImageDescriptor.encoded(buffer);
        try {
          if (descriptor.width > 2000 || descriptor.height > 2000) {
            throw const SessionFailure(
              'Use a photo no larger than 2000 × 2000 pixels.',
            );
          }
        } finally {
          descriptor.dispose();
        }
      } finally {
        buffer.dispose();
      }
      await sessionAction(() async {
        final request =
            http.MultipartRequest(
                'POST',
                Uri.parse('$apiBaseUrl/rider/profile-photo'),
              )
              ..headers.addAll({
                'Authorization': 'Bearer ${await token()}',
                'Accept': 'application/json',
              })
              ..files.add(
                http.MultipartFile.fromBytes(
                  'photo',
                  bytes,
                  filename: image.name,
                ),
              );
        await check(await riderSession.sendMultipart(request));
      });
      await load();
      if (mounted) setState(() => success = 'Profile picture updated.');
    } on PlatformException catch (failure) {
      if (mounted) {
        setState(
          () => error =
              failure.code.contains('denied') ||
                  failure.code.contains('restricted')
              ? 'Photo access is denied. Allow Photos access in device Settings, then retry.'
              : 'Photo selection is unavailable on this device.',
        );
      }
    } catch (failure) {
      if (mounted) {
        setState(
          () => error = failure is SessionFailure
              ? failure.message
              : 'Could not upload photo. Retry with a smaller image.',
        );
      }
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  Future<void> editAddress() async {
    if (saving || loading) return;
    final changed = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => RiderAddressEditPage(
          session: riderSession,
          initial: Map<String, dynamic>.from(
            details['address_fields'] as Map? ?? {},
          ),
        ),
      ),
    );
    if (changed == true && mounted) {
      await load();
      setState(
        () => success =
            'Address saved. Check your approval status before working.',
      );
    }
  }

  Future<void> logout() async {
    if (saving || loading) return;
    setState(() => saving = true);
    try {
      await riderSession.logout();
      if (mounted) {
        Navigator.of(context).pushAndRemoveUntil(
          MaterialPageRoute(builder: (_) => const LoginPage()),
          (_) => false,
        );
      }
    } catch (failure) {
      if (mounted) {
        setState(
          () => error = failure is SessionFailure
              ? failure.message
              : 'Could not sign out. Please retry.',
        );
      }
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  Widget _profileRow(String label, dynamic data, {bool isLast = false}) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label.toUpperCase(),
          style: const TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.w600,
            color: RiderColors.textSecondary,
            letterSpacing: 0.5,
          ),
        ),
        const SizedBox(height: 4),
        SelectableText(
          value(data),
          style: const TextStyle(
            fontWeight: FontWeight.w700,
            fontSize: 15,
            color: RiderColors.text,
          ),
        ),
        if (!isLast) const Divider(height: 24, color: RiderColors.border),
      ],
    );
  }

  Widget _statusRow(String label, String statusText, bool isPositive) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w600,
              color: RiderColors.text,
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
            decoration: BoxDecoration(
              color: isPositive ? RiderColors.mintBadge : RiderColors.amberBadge,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Text(
              statusText,
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w700,
                color: isPositive
                    ? RiderColors.mintBadgeText
                    : RiderColors.amberBadgeText,
              ),
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: RiderColors.background,
    appBar: AppBar(
      title: const Text(
        'My account',
        style: TextStyle(fontWeight: FontWeight.bold),
      ),
      backgroundColor: RiderColors.teal,
      foregroundColor: Colors.white,
      centerTitle: true,
      elevation: 0,
    ),
    body: ListView(
      controller: scroll,
      padding: riderPagePadding(context, maxWidth: 640),
      children: [
        if (loading || saving) const LinearProgressIndicator(),
        if (error != null)
          Container(
            padding: const EdgeInsets.all(14),
            margin: const EdgeInsets.only(bottom: 12),
            decoration: BoxDecoration(
              color: const Color(0xFFFEE2E2),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: const Color(0xFFFCA5A5)),
            ),
            child: Text(
              error!,
              style: const TextStyle(
                color: RiderColors.danger,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        if (success != null)
          Container(
            padding: const EdgeInsets.all(14),
            margin: const EdgeInsets.only(bottom: 12),
            decoration: BoxDecoration(
              color: const Color(0xFFECFDF5),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: const Color(0xFFA7F3D0)),
            ),
            child: Text(
              success!,
              style: const TextStyle(
                color: RiderColors.success,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        if (profile != null) ...[
          const SizedBox(height: 8),
          // Centered Avatar Section matching Screen 5
          Center(
            child: Column(
              children: [
                Stack(
                  children: [
                    Container(
                      width: 92,
                      height: 92,
                      decoration: const BoxDecoration(
                        color: Color(0xFFE6F7F7),
                        shape: BoxShape.circle,
                      ),
                      child: ClipOval(
                        child: photo != null
                            ? Image.memory(
                                photo!,
                                fit: BoxFit.cover,
                                cacheWidth: 184,
                                errorBuilder: (_, e, s) => const Icon(
                                  Icons.person_outline,
                                  size: 52,
                                  color: RiderColors.teal,
                                ),
                              )
                            : details['public_photo_url'] != null
                            ? Image.network(
                                details['public_photo_url'].toString(),
                                fit: BoxFit.cover,
                                errorBuilder: (_, e, s) => const Icon(
                                  Icons.person_outline,
                                  size: 52,
                                  color: RiderColors.teal,
                                ),
                              )
                            : const Icon(
                                Icons.person_outline,
                                size: 52,
                                color: RiderColors.teal,
                              ),
                      ),
                    ),
                    Positioned(
                      bottom: 0,
                      right: 0,
                      child: Container(
                        padding: const EdgeInsets.all(6),
                        decoration: BoxDecoration(
                          color: RiderColors.amber,
                          shape: BoxShape.circle,
                          border: Border.all(color: Colors.white, width: 2),
                        ),
                        child: const Icon(
                          Icons.camera_alt,
                          size: 14,
                          color: Color(0xFF78350F),
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                TextButton(
                  onPressed: loading || saving ? null : uploadPhoto,
                  child: const Text(
                    'Change profile picture',
                    style: TextStyle(
                      color: RiderColors.teal,
                      fontWeight: FontWeight.w700,
                      fontSize: 14,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          // Profile Details Card matching Screen 5
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: RiderColors.border),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.02),
                  blurRadius: 10,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _profileRow('Full name', profile!['name']),
                _profileRow('Email', profile!['email']),
                _profileRow('Birthday', details['birthday']),
                _profileRow('Address', details['address']),
                _profileRow('Vehicle type', details['vehicle_type']),
                _profileRow(
                  'Plate number',
                  details['plate_number'],
                  isLast: true,
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          // Account Status Card matching Screen 5
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: RiderColors.border),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.02),
                  blurRadius: 10,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Account status',
                  style: TextStyle(
                    fontSize: 17,
                    fontWeight: FontWeight.w800,
                    color: RiderColors.text,
                  ),
                ),
                const SizedBox(height: 12),
                _statusRow(
                  'Status',
                  value(profile!['status']),
                  profile!['status'] == 'approved',
                ),
                _statusRow(
                  'Approval',
                  value(profile!['approvalStatus']),
                  profile!['approvalStatus'] == 'approved',
                ),
                _statusRow(
                  'Active',
                  profile!['is_active'] == true ? 'Yes' : 'No',
                  profile!['is_active'] == true,
                ),
                _statusRow(
                  'Email verified',
                  profile!['verified'] == true ? 'Yes' : 'No',
                  profile!['verified'] == true,
                ),
                SizedBox(
                  height: 0,
                  child: OverflowBox(
                    minHeight: 0,
                    maxHeight: 0,
                    alignment: Alignment.topLeft,
                    child: Text(
                      'Approval: ${value(profile!['approvalStatus'])}',
                      style: const TextStyle(fontSize: 0, color: Colors.transparent),
                    ),
                  ),
                ),
                if (profile!['reason'] != null) ...[
                  const SizedBox(height: 8),
                  Text(
                    profile!['reason'].toString(),
                    style: const TextStyle(
                      fontSize: 13,
                      color: RiderColors.danger,
                    ),
                  ),
                ],
                if (profile!['verification_required'] == true &&
                    profile!['verified'] != true)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 8),
                    child: Text(
                      'Open the verification email in a browser and sign in to ALONA when prompted.',
                      style: TextStyle(
                        fontSize: 13,
                        color: RiderColors.textSecondary,
                      ),
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          // Contact Details Card matching Screen 5
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: RiderColors.border),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withValues(alpha: 0.02),
                  blurRadius: 10,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Contact details',
                  style: TextStyle(
                    fontSize: 17,
                    fontWeight: FontWeight.w800,
                    color: RiderColors.text,
                  ),
                ),
                const SizedBox(height: 14),
                const Text(
                  'Contact number',
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: RiderColors.textSecondary,
                  ),
                ),
                const SizedBox(height: 8),
                TextField(
                  controller: contact,
                  enabled: !saving && !loading,
                  keyboardType: TextInputType.phone,
                  decoration: InputDecoration(
                    hintText: '09XX XXX XXXX',
                    contentPadding: const EdgeInsets.symmetric(
                      horizontal: 16,
                      vertical: 14,
                    ),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(14),
                      borderSide: const BorderSide(color: RiderColors.border),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(14),
                      borderSide: const BorderSide(color: RiderColors.border),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(14),
                      borderSide: const BorderSide(
                        color: RiderColors.teal,
                        width: 1.5,
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                SizedBox(
                  width: double.infinity,
                  height: 50,
                  child: FilledButton(
                    onPressed: saving || loading ? null : save,
                    style: FilledButton.styleFrom(
                      backgroundColor: RiderColors.teal,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                    ),
                    child: const Text(
                      'Save contact number',
                      style: TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 15,
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 10),
                SizedBox(
                  width: double.infinity,
                  height: 50,
                  child: OutlinedButton(
                    onPressed: saving || loading ? null : editAddress,
                    style: OutlinedButton.styleFrom(
                      side: const BorderSide(color: RiderColors.teal),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                    ),
                    child: const Text(
                      'Update address',
                      style: TextStyle(
                        color: RiderColors.teal,
                        fontWeight: FontWeight.w700,
                        fontSize: 15,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          // Info Notice matching Screen 5
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: const Color(0xFFE6F7F7),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: const Color(0xFFA7F3D0)),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: const [
                Icon(Icons.info_outline, color: RiderColors.teal, size: 22),
                SizedBox(width: 12),
                Expanded(
                  child: Text(
                    'Address changes require Logistics reapproval and pause operational access. Identity, email, vehicle and coverage changes must be reviewed by your sorting center.',
                    style: TextStyle(
                      fontSize: 13,
                      color: RiderColors.tealDark,
                      height: 1.35,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          // Operational Tools (COD & Returns)
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => RiderCodPage(session: riderSession),
                    ),
                  ),
                  style: OutlinedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                    side: const BorderSide(color: RiderColors.border),
                  ),
                  child: const Text(
                    'COD cash records',
                    style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: OutlinedButton(
                  onPressed: () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => RiderReturnsPage(session: riderSession),
                    ),
                  ),
                  style: OutlinedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                    side: const BorderSide(color: RiderColors.border),
                  ),
                  child: const Text(
                    'Return to Seller jobs',
                    style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                  ),
                ),
              ),
            ],
          ),
          RiderHandovers(session: riderSession),
          const SizedBox(height: 24),
          // Logout Button matching Screen 5
          SizedBox(
            width: double.infinity,
            height: 52,
            child: OutlinedButton.icon(
              onPressed: loading || saving ? null : logout,
              icon: const Icon(Icons.logout, color: RiderColors.danger, size: 20),
              label: const Text(
                'Log out',
                style: TextStyle(
                  color: RiderColors.danger,
                  fontWeight: FontWeight.w700,
                  fontSize: 15,
                ),
              ),
              style: OutlinedButton.styleFrom(
                side: const BorderSide(color: Color(0xFFFCA5A5)),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16),
                ),
              ),
            ),
          ),
          const SizedBox(height: 24),
        ],
      ],
    ),
  );
}
