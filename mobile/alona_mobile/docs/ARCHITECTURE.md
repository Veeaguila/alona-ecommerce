# Architecture

- `main.dart`: module/session revision keys rebuild the Navigator when auth changes,
  removing protected nested routes after expiration; keys contain no tokens.
- `core/config/api_config.dart`: explicit origin, HTTPS for release, no credentials
  or query in API URLs. Build-time target overrides saved settings on startup.
- `core/network/api_client.dart`: role-scoped request tokens, injectable HTTP client,
  timeouts/validation errors, private bytes/multipart and stale-session rejection.
  Delayed401 from a previous token cannot clear a newer login. No heartbeat.
- `AuthState`/`SessionStore`: separate Buyer/Logistics credentials, server binding,
  native secure storage and web preferences. Changing servers clears both sessions.
- `LogisticsAccessGate`: current server profile/approval at entry and resume; Hub
  Staff only mount their scoped workspace. Backend enforces30-minute request idle.
- Native Logistics screens call existing sorting-center APIs: request acceptance,
  pickup dispatch, physical receipt, sort, eligible delivery assignment, outbound,
  hub workspace, monitoring, private proofs, Rider coverage/decisions, returns,
  supervised handover, incident/override audit and actual COD cash receipt.
- `workflow_widgets.dart`: reason dialog owns/disposes its field controller with
  route lifetime. Physical movements require explicit operator confirmation.
- Monitoring consumes Laravel pages; pickup/returns/COD have page controls. Queues
  refresh on bottom-tab entry. No fabricated fleet, reference/status or money.
- Existing web owner setup remains responsible for hubs/routes/staff/rates and
  account onboarding. No duplicate mobile database, rules engine or migration.
- `logistics/presentation/payroll/rider_payouts_screen.dart` is an owner Operations
  screen using `/api/sorting-center/payroll`: approved future rates, date/Rider job
  review, grouped preparation, receiving-detail review, manual payment image proof
  and reference, and cancellation of unsent batches only. Pending requests disable
  duplicate actions; payment dialogs prevent dismissal during a write. Hub Staff
  cannot use company payroll. Teammate palette/Figtree/cards/buttons are preserved.
  Gallery selection has an independent pending guard and retryable error handling;
  a disposed payment dialog cannot begin an upload after its asynchronous file read.
  Compensated complete transfer manifests are created on the web; destination
  receipts use the existing mobile hub workspace. COD stays in the existing screen.
- Buyer preview and booking delegate to `BuyerOrderController` shared quote/store
  allocation and the existing stock-locked booking transaction. Mobile sends the
  reviewed prices/shipping/total; a request UUID plus 24-hour server-cache receipt
  handles unchanged retries. Buy Now uses a transaction-local cart row, preserving
  saved cart contents. A shared lock-capable cache is required; this is not durable
  exactly-once processing after cache loss or client state loss.
- Buyer API routes enforce verified approved Buyer access. Address APIs map the
  original mobile form onto the existing canonical columns and full snapshot.
  Order details/cancellation/receipt reuse web behavior and tracking metadata.
- Buyer reviews/voucher validation/reorder delegate to canonical web behavior.
  Cart writes enforce availability/ownership and purchase limits; mobile serializes
  pending mutations and reloads server state. Order reads discard stale revisions.
- Buyer mobile chat uses the existing Buyer/Seller web threads. Conversation list
  previews preserve unread status; detail reads mark incoming messages read.
  `ConversationOrderAccess` scopes attached orders to both participants on writes
  and reads, including legacy references. The client pauses background polling,
  invalidates pre-send reads and preserves drafts on failed sends.
- Buyer OTP verification consumes a valid, unexpired code once under a row lock;
  already verified/restricted users cannot mint OTP tokens. Login/OTP UI remains
  pending through session persistence and ignores late disposed-screen responses.
  Imported Google client identity claims fail closed (503); real provider support
  is pending. Existing session revocation is an owner-controlled rollout action.
- Delivery OTP has a dedicated owning-Buyer, no-store endpoint shared with web;
  the temporary dialog clears on close/background/expiry. Codes never enter DTOs,
  persistent preferences, notification histories or evidence logs.
- `test/`: synthetic HTTP/plugin fixtures. Parent PHPUnit uses SQLite memory.
  Profile web is an acceptance build, not native/device certification.
