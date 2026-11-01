# Rider UI refinement

Approved 2026-10-16. Preserve the existing palette and operational behavior.

- Palette: teal `#008b8b`, orange `#ffa500`, page `#f5f5f5`, white `#ffffff`,
  input outline `#d7e2e2`. Retain existing text, error/success and workflow colors.
- Type: existing Roboto family; 30–32 px authentication headings, 18–20 px section
  headings, readable body text. Use weight and spacing rather than numbered labels.
- Login: one left-aligned column, logo → welcome → labeled inputs → primary action
  → registration link; maximum 420 px. Remove the redundant outer form outline.
- Registration: maximum 720 px; brand/back link, heading, then existing coverage,
  personal, account, address and document groups. Keep all required consent and fields.
- Work queues: center content to 840 px; narrower account/message/photo forms.
  White panels stay flat; existing teal queue summaries remain. Delivery outcomes
  use full-width controls to accommodate narrow screens and larger text.
- Earnings: one summary panel; three columns when space permits, stacked otherwise.
- Keep validation, account readiness, errors, proof requirements and status visible.
  No decorative numbering, repeated photo headings or references to screen numbers.

Layout review: a centered logo inside another outlined form repeated the same
framing without helping the rider. The chosen layout aligns the mark and form on
one reading edge; section spacing provides hierarchy without adding decoration.
No new assets, dependencies, animations or backend contracts are required.
