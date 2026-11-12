import 'package:flutter/material.dart';

import '../widgets/rider_content.dart';

import '../services/rider_session.dart';
import '../services/rider_session_guard.dart';
import '../services/rider_portal_api.dart';
import 'rider_account_page.dart';
export 'rider_account_page.dart';
import 'rider_notifications_page.dart';
export 'rider_notifications_page.dart';
import 'job_earnings_page.dart';
import '../theme/rider_theme.dart';

Widget riderTools(BuildContext context, RiderSession session) =>
    PopupMenuButton<String>(
      tooltip: 'Rider tools',
      onSelected: (value) {
        final page = value == 'earnings'
            ? JobEarningsPage(session: session)
            : value == 'chat'
            ? RiderChatPage(session: session)
            : value == 'notifications'
            ? RiderNotificationsPage(session: session)
            : RiderAccountPage(session: session);
        Navigator.push(context, MaterialPageRoute(builder: (_) => page));
      },
      itemBuilder: (_) => const [
        PopupMenuItem(value: 'earnings', child: Text('Earnings')),
        PopupMenuItem(value: 'chat', child: Text('Messages')),
        PopupMenuItem(value: 'notifications', child: Text('Notifications')),
        PopupMenuItem(value: 'account', child: Text('My account')),
      ],
    );

class RiderChatPage extends StatefulWidget {
  final RiderSession? session;
  final int? conversationId;
  const RiderChatPage({super.key, this.session, this.conversationId});
  @override
  State<RiderChatPage> createState() => _RiderChatPageState();
}

class _RiderChatPageState extends State<RiderChatPage>
    with RiderSessionGuard<RiderChatPage> {
  @override
  late final riderSession = widget.session ?? RiderSession();
  late final api = RiderPortalApi(riderSession);
  final text = TextEditingController();
  final recipientSearch = TextEditingController();
  final recipientFocus = FocusNode();
  bool showRecipientResults = false;
  List<Map<String, dynamic>> recipients = [], conversations = [], messages = [];
  int? recipientId, conversationId;
  int revision = 0;
  bool loading = true, opening = false, sending = false;
  String? error;
  @override
  void initState() {
    super.initState();
    recipientFocus.addListener(recipientFocusChanged);
    load();
  }

  @override
  void dispose() {
    text.dispose();
    recipientSearch.dispose();
    recipientFocus.removeListener(recipientFocusChanged);
    recipientFocus.dispose();
    super.dispose();
  }

  Future<void> load() async {
    setState(() {
      loading = true;
      error = null;
    });
    try {
      final result = await sessionAction(
        () => Future.wait([
          api.request('rider/messages/recipients'),
          api.request('rider/messages/conversations'),
        ]),
      );
      if (!mounted) return;
      setState(() {
        recipients = List<Map<String, dynamic>>.from(result[0]['data'] as List);
        if (recipientId != null) {
          final selected = recipients.where((r) => r['id'] == recipientId);
          if (selected.isEmpty) {
            recipientId = null;
            recipientSearch.clear();
          } else {
            recipientSearch.text = selected.first['name'].toString();
          }
        }
        conversations = List<Map<String, dynamic>>.from(
          result[1]['data'] as List,
        );
        loading = false;
      });
      if (widget.conversationId != null && conversationId == null) {
        await open(widget.conversationId!);
      }
    } catch (failure) {
      if (mounted) {
        setState(() {
          loading = false;
          error = portalError(failure);
        });
      }
    }
  }

  Future<void> open(int id) async {
    if (sending) return;
    final current = ++revision;
    setState(() {
      conversationId = id;
      messages = [];
      opening = true;
      showRecipientResults = false;
      error = null;
    });
    try {
      final payload = await sessionAction(
        () => api.request('rider/messages/conversations/$id'),
      );
      if (!mounted || current != revision) return;
      setState(() {
        messages = List<Map<String, dynamic>>.from(payload['data'] as List);
        opening = false;
        for (final row in conversations) {
          if (row['id'] == id) {
            row['unread_count'] = 0;
          }
        }
      });
    } catch (failure) {
      if (mounted && current == revision) {
        setState(() {
          opening = false;
          error = portalError(failure);
        });
      }
    }
  }

  Future<void> send() async {
    if (sending ||
        opening ||
        text.text.trim().isEmpty ||
        (conversationId == null && recipientId == null)) {
      return;
    }
    setState(() {
      sending = true;
      error = null;
    });
    try {
      final id = conversationId;
      final payload = await sessionAction(
        () => api.request(
          id == null ? 'rider/messages' : 'rider/messages/conversations/$id',
          method: 'POST',
          body: {
            'body': text.text.trim(),
            if (id == null) 'recipient_id': recipientId,
          },
        ),
      );
      if (!mounted) return;
      final savedId = (payload['conversation_id'] as num).toInt();
      text.clear();
      setState(() => sending = false);
      await open(savedId);
      await load();
    } catch (failure) {
      if (mounted) setState(() => error = portalError(failure));
    } finally {
      if (mounted) setState(() => sending = false);
    }
  }

  void searchRecipients() {
    setState(() {
      showRecipientResults = true;
      recipientId = null;
      conversationId = null;
      messages = [];
      revision++;
    });
  }

  void recipientFocusChanged() {
    if (!recipientFocus.hasFocus) dismissRecipientSearch();
  }

  void dismissRecipientSearch() {
    if (!showRecipientResults) return;
    setState(() {
      showRecipientResults = false;
      if (recipientId == null) recipientSearch.clear();
    });
  }

  Iterable<Map<String, dynamic>> get matchingRecipients {
    final query = recipientSearch.text.trim().toLowerCase();
    return recipients.where(
      (recipient) => recipient['name'].toString().toLowerCase().contains(query),
    );
  }

  void selectRecipient(Map<String, dynamic> recipient) {
    setState(() {
      recipientId = (recipient['id'] as num).toInt();
      recipientSearch.text = recipient['name'].toString();
      showRecipientResults = false;
      conversationId = null;
      messages = [];
      revision++;
    });
    FocusScope.of(context).unfocus();
  }

  @override
  Widget build(BuildContext context) => RiderTheme.wrap(Scaffold(
    backgroundColor: RiderColors.portalBackground,
    appBar: AppBar(
      flexibleSpace: const DecoratedBox(
        decoration: BoxDecoration(gradient: RiderColors.headerGradient),
      ),
      title: const Text('Messages'),
      actions: [
        IconButton(
          onPressed: loading || sending ? null : load,
          icon: const Icon(Icons.refresh),
          tooltip: 'Refresh',
        ),
      ],
    ),
    body: ListView(
      padding: riderPagePadding(context, maxWidth: 720),
      children: [
        if (loading) const Center(child: CircularProgressIndicator()),
        if (error != null)
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Text(error!, style: const TextStyle(color: RiderColors.danger)),
          ),
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            gradient: RiderColors.cardGradient,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: RiderColors.portalBorder),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'New message to',
                style: TextStyle(
                  fontWeight: FontWeight.w700,
                  fontSize: 14,
                  color: RiderColors.text,
                ),
              ),
              const SizedBox(height: 8),
              TextField(
                key: const ValueKey('rider-recipient-search'),
                groupId: recipientSearch,
                controller: recipientSearch,
                focusNode: recipientFocus,
                enabled: !loading && !sending && !opening,
                selectAllOnFocus: true,
                textInputAction: TextInputAction.search,
                style: const TextStyle(color: RiderColors.text),
                cursorColor: RiderColors.teal,
                decoration: InputDecoration(
                  hintText: 'Search recipient name',
                  prefixIcon: const Icon(Icons.search, color: RiderColors.teal),
                  suffixIcon: recipientSearch.text.isEmpty
                      ? null
                      : IconButton(
                          tooltip: 'Clear recipient search',
                          icon: const Icon(Icons.close),
                          onPressed: loading || sending || opening
                              ? null
                              : () {
                                  recipientSearch.clear();
                                  recipientFocus.requestFocus();
                                  searchRecipients();
                                },
                        ),
                  contentPadding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 12,
                  ),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: RiderColors.border),
                  ),
                  enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: RiderColors.border),
                  ),
                ),
                onTap: () => setState(() => showRecipientResults = true),
                onChanged: (_) => searchRecipients(),
                onTapOutside: (_) {
                  recipientFocus.unfocus();
                  dismissRecipientSearch();
                },
                onSubmitted: (_) {
                  recipientFocus.unfocus();
                  dismissRecipientSearch();
                },
              ),
              if (showRecipientResults && !loading)
                TextFieldTapRegion(
                  groupId: recipientSearch,
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxHeight: 200),
                    child: ListView(
                      shrinkWrap: true,
                      padding: EdgeInsets.zero,
                      children: [
                        if (matchingRecipients.isEmpty)
                          Padding(
                            padding: const EdgeInsets.all(12),
                            child: Text(
                              recipients.isEmpty
                                  ? 'No available recipients'
                                  : 'No matching recipients',
                              style: const TextStyle(color: RiderColors.textSecondary),
                            ),
                          ),
                        for (final recipient in matchingRecipients)
                          ListTile(
                            key: ValueKey('rider-recipient-${recipient['id']}'),
                            leading: const Icon(
                              Icons.person_outline,
                              color: RiderColors.teal,
                            ),
                            title: Text(
                              recipient['name'].toString(),
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                            ),
                            onTap: sending || opening
                                ? null
                                : () => selectRecipient(recipient),
                          ),
                      ],
                    ),
                  ),
                ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        for (final row in conversations)
          Container(
            margin: const EdgeInsets.only(bottom: 10),
            decoration: BoxDecoration(
              color: const Color(0xFFE6F7F7),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: const Color(0xFFA7F3D0)),
            ),
            child: Material(
              color: Colors.transparent,
              child: ListTile(
                leading: Container(
                  width: 44,
                  height: 44,
                  decoration: const BoxDecoration(
                    color: RiderColors.teal,
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    Icons.home_work_outlined,
                    color: Colors.white,
                    size: 22,
                  ),
                ),
                title: Text(
                  row['recipient']?['name']?.toString() ?? 'Alona Logistics Hub',
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    fontSize: 15,
                    color: RiderColors.text,
                  ),
                ),
                subtitle: Text(
                  row['latest_message']?['body']?.toString() ??
                      'Your sorting center',
                  style: const TextStyle(
                    fontSize: 13,
                    color: RiderColors.textSecondary,
                  ),
                ),
                trailing: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color: RiderColors.amber,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Text(
                    '${row['unread_count'] ?? 0} unread',
                    style: const TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF78350F),
                    ),
                  ),
                ),
                onTap: sending ? null : () => open((row['id'] as num).toInt()),
              ),
            ),
          ),
        if (opening) const LinearProgressIndicator(),
        if (messages.isNotEmpty)
          const Padding(
            padding: EdgeInsets.symmetric(vertical: 12),
            child: Center(
              child: Text(
                'Today',
                style: TextStyle(
                  color: RiderColors.textSecondary,
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ),
        for (final message in messages)
          Align(
            alignment: message['is_mine'] == true
                ? Alignment.centerRight
                : Alignment.centerLeft,
            child: Container(
              margin: const EdgeInsets.symmetric(vertical: 6),
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              constraints: BoxConstraints(
                maxWidth: MediaQuery.of(context).size.width * 0.78,
              ),
              decoration: BoxDecoration(
                color: message['is_mine'] == true
                    ? RiderColors.teal
                    : Colors.white,
                borderRadius: BorderRadius.circular(16).copyWith(
                  bottomRight: message['is_mine'] == true
                      ? const Radius.circular(2)
                      : const Radius.circular(16),
                  bottomLeft: message['is_mine'] == true
                      ? const Radius.circular(16)
                      : const Radius.circular(2),
                ),
                border: message['is_mine'] == true
                    ? null
                    : Border.all(color: RiderColors.portalBorder),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.03),
                    blurRadius: 6,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: Column(
                crossAxisAlignment: message['is_mine'] == true
                    ? CrossAxisAlignment.end
                    : CrossAxisAlignment.start,
                children: [
                  Text(
                    '${message['sender']?['name'] ?? 'Rider'}: ${message['body']}',
                    style: TextStyle(
                      color: message['is_mine'] == true
                          ? Colors.white
                          : RiderColors.text,
                      fontSize: 14,
                      height: 1.3,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    message['is_mine'] == true ? 'Seen' : 'Today',
                    style: TextStyle(
                      color: message['is_mine'] == true
                          ? Colors.white.withValues(alpha: 0.7)
                          : RiderColors.textSecondary,
                      fontSize: 11,
                    ),
                  ),
                ],
              ),
            ),
          ),
        const SizedBox(height: 16),
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            gradient: RiderColors.cardGradient,
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: RiderColors.portalBorder),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text(
                'Message',
                style: TextStyle(
                  fontWeight: FontWeight.w700,
                  fontSize: 14,
                  color: RiderColors.text,
                ),
              ),
              const SizedBox(height: 8),
              TextField(
                controller: text,
                maxLength: 4000,
                maxLines: 3,
                decoration: InputDecoration(
                  hintText: 'Write to the Logistics Hub',
                  contentPadding: const EdgeInsets.all(14),
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
              const SizedBox(height: 12),
              SizedBox(
                height: 50,
                child: FilledButton.icon(
                  onPressed: loading || sending || opening ||
                          (conversationId == null && recipientId == null)
                      ? null
                      : send,
                  icon: const Icon(Icons.send_rounded, size: 18),
                  label: Text(
                    sending ? 'Sending...' : 'Send message',
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  style: FilledButton.styleFrom(
                    backgroundColor: RiderColors.teal,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  ));
}

String portalError(Object failure) => failure is SessionFailure
    ? failure.message
    : 'The request could not be confirmed. Refresh before retrying.';
