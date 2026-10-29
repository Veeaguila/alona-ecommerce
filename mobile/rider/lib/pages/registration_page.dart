import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

import '../services/registration_api.dart';
import '../services/api_error.dart';
import '../theme/rider_theme.dart';

class RegistrationPage extends StatefulWidget {
  final RegistrationApi? api;
  const RegistrationPage({super.key, this.api});
  @override
  State<RegistrationPage> createState() => _RegistrationPageState();
}

class _RegistrationPageState extends State<RegistrationPage> {
  final formKey = GlobalKey<FormState>();
  final firstName = TextEditingController(),
      middleInitial = TextEditingController(),
      lastName = TextEditingController();
  final email = TextEditingController(),
      password = TextEditingController(),
      passwordConfirmation = TextEditingController();
  final contact = TextEditingController(),
      street = TextEditingController(),
      houseNumber = TextEditingController();
  final plateNumber = TextEditingController(),
      postalCode = TextEditingController(),
      vehicleModel = TextEditingController(),
      licenseNumber = TextEditingController();
  late final api = widget.api ?? RegistrationApi();
  List<Map<String, dynamic>> coverage = [],
      regions = [],
      provinces = [],
      cities = [],
      barangays = [];
  List<Map<String, dynamic>> sortingCenters = [];
  final deliveryCities = <String>{}, licenseCodes = <String>{};
  final documents = <String, PlatformFile>{};
  int? sortingCenterId;
  String? region,
      province,
      municipality,
      barangay,
      sex,
      vehicleType,
      sortingCentersError,
      error,
      success;
  DateTime? birthday, licenseExpiry;
  bool loadingSortingCenters = true,
      addressLoading = false,
      submitting = false,
      showPasswords = false,
      acceptedTerms = false,
      acceptedPrivacy = false;
  int addressRevision = 0;
  bool get motor => vehicleType != null && vehicleType != 'bicycle';
  PlatformFile? get orCr => documents['or_cr_doc'];
  PlatformFile? get license => documents['license_doc'];

  @override
  void initState() {
    super.initState();
    loadDirectory();
  }

  @override
  void dispose() {
    for (final c in [
      firstName,
      middleInitial,
      lastName,
      email,
      password,
      passwordConfirmation,
      contact,
      street,
      houseNumber,
      plateNumber,
      postalCode,
      vehicleModel,
      licenseNumber,
    ]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> loadDirectory() async {
    setState(() {
      loadingSortingCenters = true;
      sortingCentersError = null;
    });
    try {
      final results = await Future.wait([
        api.directory('delivery-coverage'),
        api.directory('addresses/regions'),
      ]);
      if (!mounted) return;
      setState(() {
        coverage = results[0];
        regions = results[1];
        loadingSortingCenters = false;
        refreshPartners();
      });
    } catch (_) {
      if (mounted) {
        setState(() {
          loadingSortingCenters = false;
          sortingCentersError = 'Could not load registration options. Check your connection and retry.';
        });
      }
    }
  }

  void refreshPartners() {
    sortingCenters = RegistrationApi.matchingPartners(coverage, deliveryCities);
    if (!sortingCenters.any((row) => row['id'] == sortingCenterId)) {
      sortingCenterId = null;
    }
    sortingCentersError = deliveryCities.isEmpty
        ? 'Choose at least one delivery area first.'
        : sortingCenters.isEmpty
        ? 'No eligible partner serves all selected areas. Choose other areas or retry later.'
        : null;
  }

  Future<void> chooseRegion(String? code) async {
    final revision = ++addressRevision;
    setState(() {
      region = code;
      province = null;
      municipality = null;
      barangay = null;
      provinces = [];
      cities = [];
      barangays = [];
      addressLoading = true;
      error = null;
    });
    if (code == null) {
      setState(() => addressLoading = false);
      return;
    }
    try {
      final rows = await Future.wait([
        api.directory('addresses/provinces', query: {'parent': code}),
        api.directory('addresses/cities', query: {'parent': code}),
      ]);
      if (!mounted || revision != addressRevision) return;
      setState(() {
        provinces = rows[0];
        cities = rows[1];
        addressLoading = false;
      });
    } catch (_) {
      if (mounted && revision == addressRevision) {
        setState(() {
          addressLoading = false;
          error = 'Could not load this region. Choose it again to retry.';
        });
      }
    }
  }

  Future<void> chooseCity(String? code) async {
    final revision = ++addressRevision;
    final city = cities.where((row) => row['code'] == code).firstOrNull;
    setState(() {
      municipality = code;
      province = city?['province_code']?.toString();
      barangay = null;
      barangays = [];
      addressLoading = true;
      error = null;
    });
    if (code == null) {
      setState(() => addressLoading = false);
      return;
    }
    try {
      final rows = await api.directory(
        'addresses/barangays',
        query: {'parent': code},
      );
      if (!mounted || revision != addressRevision) return;
      setState(() {
        barangays = rows;
        addressLoading = false;
      });
    } catch (_) {
      if (mounted && revision == addressRevision) {
        setState(() {
          addressLoading = false;
          error = 'Could not load barangays. Choose the city again to retry.';
        });
      }
    }
  }

  int? get age {
    if (birthday == null) return null;
    final now = DateTime.now();
    var years = now.year - birthday!.year;
    if (now.month < birthday!.month ||
        (now.month == birthday!.month && now.day < birthday!.day)) {
      years--;
    }
    return years;
  }

  String? requiredValue(String? value) =>
      value == null || value.trim().isEmpty ? 'Required' : null;
  Future<void> pickBirthday() async {
    final now = DateTime.now();
    final latest = DateTime(now.year - 18, now.month, now.day);
    final value = await showDatePicker(
      context: context,
      initialDate: birthday ?? latest,
      firstDate: DateTime(1940),
      lastDate: latest,
    );
    if (value != null && mounted) setState(() => birthday = value);
  }

  Future<void> pickExpiry() async {
    final now = DateTime.now();
    final value = await showDatePicker(
      context: context,
      initialDate: licenseExpiry ?? now,
      firstDate: DateTime(now.year, now.month, now.day),
      lastDate: DateTime(now.year + 20),
    );
    if (value != null && mounted) setState(() => licenseExpiry = value);
  }

  Future<void> pickDocument(String key) async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['jpg', 'jpeg', 'png', 'pdf'],
      withData: true,
    );
    if (!mounted || result == null || result.files.single.bytes == null) return;
    final file = result.files.single;
    if (file.size > 5 * 1024 * 1024) {
      setState(() => error = 'Documents must be at most 5 MB.');
      return;
    }
    setState(() {
      documents[key] = file;
      error = null;
    });
  }

  Future<void> submit() async {
    if (submitting) return;
    FocusScope.of(context).unfocus();
    setState(() {
      error = null;
      success = null;
    });
    if (!formKey.currentState!.validate() ||
        sortingCenterId == null ||
        birthday == null ||
        deliveryCities.isEmpty ||
        !documents.containsKey('id_doc') ||
        !acceptedTerms ||
        !acceptedPrivacy ||
        (motor &&
            (!documents.containsKey('license_doc') ||
                !documents.containsKey('or_cr_doc') ||
                licenseExpiry == null ||
                licenseCodes.isEmpty))) {
      setState(
        () => error = 'Complete required details, documents, delivery areas, terms and privacy consent.',
      );
      return;
    }
    final requiredCodes = vehicleType == 'motorcycle'
        ? {'A'}
        : vehicleType == 'car'
        ? {'B'}
        : {'B1', 'B2'};
    if (motor && licenseCodes.intersection(requiredCodes).isEmpty) {
      setState(() => error = 'Select a license code valid for this vehicle.');
      return;
    }
    setState(() => submitting = true);
    try {
      final fields = <String, String>{
        'first_name': firstName.text.trim(),
        'middle_name': middleInitial.text.trim(),
        'last_name': lastName.text.trim(),
        'email': email.text.trim(),
        'password': password.text,
        'password_confirmation': passwordConfirmation.text,
        'contact_no': contact.text.trim(),
        'birthday': birthday!.toIso8601String().split('T').first,
        'region_code': region!,
        'province_code': province ?? '',
        'city_code': municipality!,
        'barangay_code': barangay!,
        'postal_code': postalCode.text.trim(),
        'street': street.text.trim(),
        'house_number': houseNumber.text.trim(),
        'terms': '1',
        'privacy': '1',
        'vehicle_type': vehicleType!,
        'sex': ?sex,
        'sorting_center_id': sortingCenterId.toString(),
        if (motor) ...{
          'vehicle_model': vehicleModel.text.trim(),
          'plate_number': plateNumber.text.trim(),
          'license_number': licenseNumber.text.trim(),
          'license_class': 'professional',
          'license_expiry': licenseExpiry!.toIso8601String().split('T').first,
        },
      };
      final response = await api.submit(
        RegistrationApi.request(
          fields,
          deliveryCities,
          licenseCodes,
          documents,
        ),
      );
      if (!mounted) return;
      setState(() {
        if (response.statusCode == 201) {
          success = 'Application submitted. Verify your email in a browser and await approval.';
        } else {
          error = apiErrorMessage(response);
        }
      });
    } catch (_) {
      if (mounted) {
        setState(
          () => error = 'Registration could not be confirmed. Check your connection and account status before retrying.',
        );
      }
    } finally {
      if (mounted) setState(() => submitting = false);
    }
  }

  Widget codeDropdown(
    String label,
    String? value,
    List<Map<String, dynamic>> rows,
    ValueChanged<String?> onChanged, {
    bool required = true,
  }) {
    return DropdownButtonFormField<String>(
      key: ValueKey('$label-$value-${rows.length}'),
      initialValue: value,
      isExpanded: true,
      items: rows
          .map(
            (row) => DropdownMenuItem<String>(
              value: row['code'].toString(),
              child: Text(
                row['name'].toString(),
                overflow: TextOverflow.ellipsis,
              ),
            ),
          )
          .toList(),
      onChanged: submitting ? null : onChanged,
      validator: required ? requiredValue : null,
      decoration: InputDecoration(labelText: label),
    );
  }

  Widget field(
    TextEditingController controller,
    String label, {
    TextInputType? type,
    bool obscure = false,
    String? Function(String?)? validator,
  }) {
    return TextFormField(
      controller: controller,
      keyboardType: type,
      textInputAction: TextInputAction.next,
      obscureText: obscure && !showPasswords,
      validator: validator ?? requiredValue,
      decoration: InputDecoration(
        labelText: label,
        suffixIcon: obscure
            ? IconButton(
                tooltip: showPasswords ? 'Hide password' : 'Show password',
                onPressed: () => setState(() => showPasswords = !showPasswords),
                icon: Icon(
                  showPasswords ? Icons.visibility_off : Icons.visibility,
                ),
              )
            : null,
      ),
    );
  }

  Widget dropdown(
    String label,
    String? value,
    List<String> items,
    ValueChanged<String?> onChanged,
  ) {
    return DropdownButtonFormField<String>(
      initialValue: value,
      isExpanded: true,
      items: items
          .map((item) => DropdownMenuItem(value: item, child: Text(item)))
          .toList(),
      onChanged: onChanged,
      validator: requiredValue,
      decoration: InputDecoration(labelText: label),
    );
  }

  Widget section(String title, List<Widget> children, {IconData? icon}) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: RiderColors.border),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.03),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              if (icon != null) ...[
                Container(
                  padding: const EdgeInsets.all(6),
                  decoration: BoxDecoration(
                    color: RiderColors.teal.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Icon(icon, size: 18, color: RiderColors.teal),
                ),
                const SizedBox(width: 10),
              ],
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(
                    fontSize: 17,
                    fontWeight: FontWeight.w700,
                    color: RiderColors.text,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          ...children,
        ],
      ),
    );
  }

  Widget documentButton(String label, PlatformFile? file, String key) {
    return Expanded(
      child: OutlinedButton.icon(
        onPressed: submitting ? null : () => pickDocument(key),
        icon: Icon(
          file == null ? Icons.cloud_upload_outlined : Icons.check_circle_rounded,
          color: file == null ? RiderColors.teal : RiderColors.success,
        ),
        label: Text(
          file == null ? label : file.name,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            fontWeight: FontWeight.w600,
            color: file == null ? RiderColors.teal : RiderColors.text,
          ),
        ),
        style: OutlinedButton.styleFrom(
          side: BorderSide(
            color: file == null ? RiderColors.teal : RiderColors.success,
            width: 1.5,
          ),
          padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 12),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          alignment: Alignment.centerLeft,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final municipalityItems = cities
        .where(
          (row) => province == null || (row['province_code'] ?? '') == province,
        )
        .toList();
    return Scaffold(
      backgroundColor: RiderColors.background,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 720),
              child: Form(
                key: formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Flexible(
                          child: Image.asset(
                            'assets/images/alona/alonalogo2.png',
                            height: 44,
                            fit: BoxFit.contain,
                          ),
                        ),
                        Flexible(
                          child: TextButton(
                            onPressed: () => Navigator.of(context).pop(),
                            child: const Text(
                              'Back to sign in',
                              textAlign: TextAlign.center,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 28),
                    const Text(
                      'Create your rider profile',
                      style: TextStyle(
                        fontSize: 30,
                        height: 1.2,
                        fontWeight: FontWeight.w700,
                        letterSpacing: -0.5,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Submit your details for sorting center approval.',
                      style: TextStyle(color: Colors.grey.shade700),
                    ),
                    const SizedBox(height: 28),
                    section(
                      'Sorting center',
                      icon: Icons.store_mall_directory_outlined,
                      [
                      codeDropdown(
                        'Delivery area to add',
                        null,
                        coverage
                            .where(
                              (row) => !deliveryCities.contains(row['code']),
                            )
                            .map(
                              (row) => {
                                'code': row['code'],
                                'name': row['label'] ?? row['name'],
                              },
                            )
                            .toList(),
                        (code) => setState(() {
                          if (code != null && deliveryCities.length < 20) {
                            deliveryCities.add(code);
                          }
                          refreshPartners();
                        }),
                        required: false,
                      ),
                      Wrap(
                        spacing: 8,
                        children: [
                          for (final code in deliveryCities)
                            InputChip(
                              label: Text(
                                (coverage
                                            .where((row) => row['code'] == code)
                                            .firstOrNull?['name'] ??
                                        code)
                                    .toString(),
                              ),
                              onDeleted: submitting
                                  ? null
                                  : () => setState(() {
                                      deliveryCities.remove(code);
                                      refreshPartners();
                                    }),
                            ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      if (loadingSortingCenters)
                        const Center(
                          child: Padding(
                            padding: EdgeInsets.all(12),
                            child: CircularProgressIndicator(),
                          ),
                        )
                      else if (sortingCenters.isEmpty)
                        Text(
                          sortingCentersError ??
                              'No approved sorting centers are available.',
                          style: const TextStyle(color: Color(0xff9b3125)),
                        )
                      else
                        DropdownButtonFormField<int>(
                          key: ValueKey(
                            'center-$sortingCenterId-${sortingCenters.length}',
                          ),
                          initialValue: sortingCenterId,
                          isExpanded: true,
                          items: sortingCenters.map((center) {
                            final id = (center['id'] as num).toInt();
                            return DropdownMenuItem<int>(
                              value: id,
                              child: Text(
                                center['business_name'].toString(),
                                overflow: TextOverflow.ellipsis,
                              ),
                            );
                          }).toList(),
                          onChanged: submitting
                              ? null
                              : (value) =>
                                    setState(() => sortingCenterId = value),
                          validator: (value) =>
                              value == null ? 'Choose a sorting center' : null,
                          decoration: const InputDecoration(
                            labelText: 'Apply to sorting center',
                          ),
                        ),
                      if (sortingCentersError != null &&
                          sortingCenters.isNotEmpty)
                        Padding(
                          padding: const EdgeInsets.only(top: 8),
                          child: Text(
                            sortingCentersError!,
                            style: const TextStyle(color: Color(0xff9b3125)),
                          ),
                        ),
                    ]),
                    const SizedBox(height: 16),
                    if (sortingCentersError != null)
                      TextButton(
                        onPressed: submitting ? null : loadDirectory,
                        child: const Text('Retry registration options'),
                      ),
                    section(
                      'Personal details',
                      icon: Icons.person_outline_rounded,
                      [
                      LayoutBuilder(
                        builder: (context, constraints) {
                          final items = [
                            field(firstName, 'First name'),
                            field(
                              middleInitial,
                              'Middle name (optional)',
                              validator: (_) => null,
                            ),
                            field(lastName, 'Last name'),
                          ];
                          if (constraints.maxWidth <= 560) {
                            return Column(
                              children: items
                                  .map(
                                    (item) => Padding(
                                      padding: const EdgeInsets.only(
                                        bottom: 12,
                                      ),
                                      child: item,
                                    ),
                                  )
                                  .toList(),
                            );
                          }
                          return Row(
                            children: [
                              for (var i = 0; i < items.length; i++)
                                Expanded(
                                  child: Padding(
                                    padding: EdgeInsets.only(
                                      right: i == items.length - 1 ? 0 : 10,
                                    ),
                                    child: items[i],
                                  ),
                                ),
                            ],
                          );
                        },
                      ),
                      const SizedBox(height: 12),
                      dropdown('Sex', sex, const [
                        'male',
                        'female',
                        'other',
                      ], (value) => setState(() => sex = value)),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: OutlinedButton.icon(
                              onPressed: pickBirthday,
                              icon: const Icon(Icons.calendar_today),
                              label: Text(
                                birthday == null
                                    ? 'Birthday'
                                    : '${birthday!.year}-${birthday!.month.toString().padLeft(2, '0')}-${birthday!.day.toString().padLeft(2, '0')}',
                              ),
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: InputDecorator(
                              decoration: const InputDecoration(
                                labelText: 'Age',
                              ),
                              child: Text(age?.toString() ?? 'Auto-generated'),
                            ),
                          ),
                        ],
                      ),
                    ]),
                    const SizedBox(height: 16),
                    section(
                      'Account details',
                      icon: Icons.lock_outline_rounded,
                      [
                      field(
                        email,
                        'Email',
                        type: TextInputType.emailAddress,
                        validator: (value) =>
                            value == null || !value.contains('@')
                            ? 'Enter a valid email'
                            : null,
                      ),
                      const SizedBox(height: 12),
                      field(
                        password,
                        'Password',
                        obscure: true,
                        validator: (value) => value == null || value.length < 8
                            ? 'Minimum 8 characters'
                            : null,
                      ),
                      const SizedBox(height: 12),
                      field(
                        passwordConfirmation,
                        'Confirm password',
                        obscure: true,
                        validator: (value) => value != password.text
                            ? 'Passwords do not match'
                            : null,
                      ),
                    ]),
                    const SizedBox(height: 16),
                    section(
                      'Contact and address',
                      icon: Icons.location_on_outlined,
                      [
                      field(
                        contact,
                        'Contact number',
                        type: TextInputType.phone,
                        validator: (value) =>
                            RegExp(r'^(09\d{9}|\+639\d{9})$')
                                .hasMatch(value?.trim() ?? '')
                            ? null
                            : 'Use 09 or +639 followed by the mobile number',
                      ),
                      const SizedBox(height: 12),
                      codeDropdown('Region', region, regions, chooseRegion),
                      const SizedBox(height: 12),
                      if (provinces.isNotEmpty)
                        codeDropdown(
                          'Province',
                          province,
                          [
                            {
                              'code': '',
                              'name': 'Independent city / no province',
                            },
                            ...provinces,
                          ],
                          (value) => setState(() {
                            addressRevision++;
                            addressLoading = false;
                            province = value;
                            municipality = null;
                            barangay = null;
                            barangays = [];
                          }),
                          required: false,
                        ),
                      const SizedBox(height: 12),
                      codeDropdown(
                        'City / municipality',
                        municipality,
                        municipalityItems,
                        chooseCity,
                      ),
                      const SizedBox(height: 12),
                      codeDropdown(
                        'Barangay',
                        barangay,
                        barangays,
                        (value) => setState(() => barangay = value),
                      ),
                      if (addressLoading) const LinearProgressIndicator(),
                      const SizedBox(height: 12),
                      field(street, 'Street'),
                      const SizedBox(height: 12),
                      field(houseNumber, 'House number'),
                      const SizedBox(height: 12),
                      field(
                        postalCode,
                        'Postal code',
                        type: TextInputType.number,
                        validator: (value) =>
                            RegExp(r'^\d{4}$').hasMatch(value ?? '')
                            ? null
                            : 'Enter a four-digit postal code',
                      ),
                    ]),
                    const SizedBox(height: 16),
                    section(
                      'Vehicle and documents',
                      icon: Icons.two_wheeler_outlined,
                      [
                      dropdown('Vehicle type', vehicleType, const [
                        'motorcycle',
                        'bicycle',
                        'car',
                        'van',
                      ], (value) => setState(() => vehicleType = value)),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          documentButton(
                            'Upload identity document',
                            documents['id_doc'],
                            'id_doc',
                          ),
                        ],
                      ),
                      if (motor) ...[
                        const SizedBox(height: 12),
                        field(vehicleModel, 'Vehicle model'),
                        const SizedBox(height: 12),
                        field(plateNumber, 'Plate number'),
                        const SizedBox(height: 12),
                        field(licenseNumber, 'Professional license number'),
                        const SizedBox(height: 12),
                        Text('Professional license codes'),
                        Wrap(
                          children: [
                            for (final code in ['A', 'A1', 'B', 'B1', 'B2'])
                              FilterChip(
                                label: Text(code),
                                selected: licenseCodes.contains(code),
                                onSelected: submitting
                                    ? null
                                    : (selected) => setState(() {
                                        if (selected) {
                                          licenseCodes.add(code);
                                        } else {
                                          licenseCodes.remove(code);
                                        }
                                      }),
                              ),
                          ],
                        ),
                        OutlinedButton.icon(
                          onPressed: submitting ? null : pickExpiry,
                          icon: const Icon(Icons.calendar_today),
                          label: Text(
                            licenseExpiry == null
                                ? 'License expiry'
                                : licenseExpiry!
                                      .toIso8601String()
                                      .split('T')
                                      .first,
                          ),
                        ),
                      ],
                      const SizedBox(height: 16),
                      if (motor)
                        Row(
                          children: [
                            documentButton('Upload OR/CR', orCr, 'or_cr_doc'),
                            const SizedBox(width: 12),
                            documentButton(
                              'Upload license',
                              license,
                              'license_doc',
                            ),
                          ],
                        ),
                      const SizedBox(height: 8),
                      Text(
                        'JPG, PNG, or PDF. Maximum 5 MB each.',
                        style: TextStyle(
                          color: Colors.grey.shade600,
                          fontSize: 12,
                        ),
                      ),
                    ]),
                    CheckboxListTile(
                      value: acceptedTerms,
                      onChanged: submitting
                          ? null
                          : (value) =>
                                setState(() => acceptedTerms = value ?? false),
                      activeColor: RiderColors.teal,
                      contentPadding: EdgeInsets.zero,
                      controlAffinity: ListTileControlAffinity.leading,
                      title: const Text(
                        'I agree to the rider registration terms and verification process.',
                        style: TextStyle(fontSize: 14),
                      ),
                    ),
                    CheckboxListTile(
                      value: acceptedPrivacy,
                      onChanged: submitting
                          ? null
                          : (value) => setState(
                              () => acceptedPrivacy = value ?? false,
                            ),
                      activeColor: RiderColors.teal,
                      contentPadding: EdgeInsets.zero,
                      controlAffinity: ListTileControlAffinity.leading,
                      title: const Text(
                        'I consent to the privacy policy and identity verification.',
                        style: TextStyle(fontSize: 14),
                      ),
                    ),
                    if (error != null) ...[
                      const SizedBox(height: 16),
                      message(error!, false),
                    ],
                    if (success != null) ...[
                      const SizedBox(height: 16),
                      message(success!, true),
                    ],
                    const SizedBox(height: 22),
                    SizedBox(
                      width: double.infinity,
                      child: FilledButton.icon(
                        onPressed: submitting ? null : submit,
                        icon: submitting
                            ? const SizedBox(
                                width: 18,
                                height: 18,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: Colors.white,
                                ),
                              )
                            : const Icon(Icons.arrow_forward),
                        label: Text(
                          submitting ? 'Submitting...' : 'Submit registration',
                        ),
                        style: FilledButton.styleFrom(
                          padding: const EdgeInsets.symmetric(vertical: 17),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget message(String text, bool isSuccess) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: isSuccess ? const Color(0xFFDCFCE7) : const Color(0xFFFEE2E2),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: isSuccess ? RiderColors.success.withValues(alpha: 0.3) : RiderColors.danger.withValues(alpha: 0.3),
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            isSuccess ? Icons.check_circle_outline : Icons.error_outline,
            color: isSuccess ? RiderColors.success : RiderColors.danger,
            size: 20,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              text,
              style: TextStyle(
                color: isSuccess ? const Color(0xFF166534) : const Color(0xFF991B1B),
                fontWeight: FontWeight.w500,
                fontSize: 14,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
