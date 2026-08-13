# Homepage Cases: Hover Tooltip + Tag Alignment Fix

## Context

The homepage `Cases` section (`src/pages/index.astro`, `.cases-list`) renders each
case as a full-width row (`.case-row`): index number, sector tag, client name,
one headline result number, and a "Ver case" CTA. It's currently plain text,
no imagery, no hover preview.

Two independent fixes:

## 1. Sector tag alignment (desktop)

`.case-sector` (a `.tag` pill) sits directly above `.case-client` (the client
name) but is visually offset right, because `.tag`'s left padding (`.8rem`)
pushes the tag's text inward while the client name below has no such offset.

The mobile breakpoint (`src/pages/index.astro` ~line 951) already corrects
this with `margin-left:-.8rem`. The desktop (base) `.case-sector` rule
(~line 720) is missing the same correction.

**Fix:** add `margin-left:-.8rem` to the base `.case-sector` rule so the tag's
text-start aligns with the client name's first letter on desktop too.

## 2. Cursor-following hover tooltip

On hover over a `.case-row`, show a small floating preview: the case's
featured image (`c.img`) + the case's **"Desafio"** (`challenge` field) text,
truncated to 3 lines. The tooltip follows the mouse cursor while hovering
that row (not statically anchored).

**Why "Desafio" and not the SEO meta description:** user preference — the
challenge text is more narrative/interesting than the SEO blurb, even though
it's longer (236–336 chars across existing cases vs. ≤160 for meta
description) and needs visual truncation.

### Behavior

- One shared tooltip element (not duplicated per row) — reused, contents
  swapped via JS on hover, to avoid rendering 13+ hidden image/text blocks.
- `position: fixed`, updated on `mousemove` to track the cursor with a small
  offset (avoids the cursor overlapping the tooltip).
- Simple viewport-edge clamp: if the tooltip would overflow the right edge of
  the window, flip it to the left of the cursor instead. No vertical clamping
  needed (row hover already keeps it comfortably on-screen vertically) —
  ponytail: only the right-edge case is handled; add top/bottom clamping if a
  future layout puts rows near the viewport edges.
- Shown on `mouseenter` of a `.case-row`, hidden on `mouseleave`.
- Image: 16:9 crop, `object-fit:cover`.
- Text: `challenge` field, clamped to 3 lines via CSS `-webkit-line-clamp`
  (no JS truncation).
- Not shown on touch/mobile — no hover event fires there, which matches
  existing behavior (mobile already shows the result number inline, no gap in
  information).

### Implementation shape

- Each `.case-row` gets `data-img={c.img}` and `data-challenge={c.challenge}`
  attributes (values already available in the existing `cases.map()` loop).
- One `#case-tooltip` element (image + paragraph) added once, after
  `.cases-list`.
- One small vanilla `<script>` block (no framework, no new dependency):
  event-delegate `mouseenter`/`mousemove`/`mouseleave` across `.case-row`
  elements, update the shared tooltip's `src`, text, and `left`/`top` via
  inline style.
- CSS: `.case-tooltip { position:fixed; pointer-events:none; opacity:0;
  transition:opacity .15s; z-index:50; width:~260px; }` +
  `.case-tooltip.is-visible { opacity:1 }`.

### Out of scope

- No change to the always-visible `.case-result` number or the mobile layout.
- No change to `/cases` or `/cases/[slug]` pages — homepage only.
- No touch/mobile equivalent interaction.
