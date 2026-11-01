# Buyer / Logistics mobile guidance

This Flutter app is ordinary source in the ALONA monorepo. Backend: `../..`.
Read `docs/PROJECT_CONTEXT.md`, `docs/CURRENT_STATE.md` and the parent's AGENTS.md.
Use the active `../rider/scripts/integration_checkpoint.cjs` for synchronous shared
checkpoints before meaningful edits/checks and after confirmed outcomes. Preserve
Rider and the current web workflow; do not repeat completed integration phases.

ALONA owns approval, company/hub scope, eligibility, parcel transitions, tariffs,
proofs/OTP, returns/restocking and cash records. Follow its existing API contracts.
Never invent a Rider/status, fallback to an ineligible fleet, infer receipt from
delivery, or connect directly to SQL. No automatic migrations, commits or pushes.

Tests use mocked HTTP or the parent's SQLite `:memory:` fixtures only. Configure
API_BASE_URL explicitly; no default production target. Native credentials use
secure storage. Clear sessions when changing servers; never log credentials/OTP.
Buyer checkout uses the canonical ALONA quote/booking rules; see current state.

Preserve the original Buyer and Logistics mobile design from upstream4277995.
Keep its branding, palette, Figtree typography, card/input/button styles and existing
screen/navigation layouts. Follow the existing mobile components for new workflow
controls; adapting web business rules does not authorize a visual redesign.
Keep necessary eligibility, session and workflow fixes. Phone overflow/contrast
corrections are allowed, but describe visible differences honestly. Buyer checkout
uses its original form design; preserve it while maintaining the shared contracts.
