# Cases Hover Tooltip + Tag Alignment — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** On the mclair homepage, fix the sector-tag alignment above each case name (desktop), and add a cursor-following hover tooltip on each case row showing the case's image + "Desafio" text.

**Architecture:** Everything lives in the single existing file `src/pages/index.astro` (frontmatter data mapping, markup, `<style>` block, `<script>` block) — this matches the codebase's existing per-page convention (see `src/components/Header.astro`, which already implements the same "hover a list item → swap a shared preview image via `data-img` + vanilla JS `mouseenter`" pattern for its Cases mega-menu). No new files, no new dependencies.

**Tech Stack:** Astro (`.astro` single-file components), plain CSS, vanilla TypeScript-in-`<script>` (no framework) — same as the rest of this codebase.

## Global Constraints

- No new npm dependencies. No framework (React/Vue/etc.) — this site intentionally removed those for bundle size (see project history).
- Follow the existing code style in `src/pages/index.astro` and `src/components/Header.astro`: IIFE-wrapped `<script>`, `as HTMLElement` casts, `dataset.*` for data attributes, `querySelectorAll(...).forEach(...)`.
- This project has no test framework (`package.json` has only `dev`/`build`/`preview`/`astro` scripts, no `vitest`/`jest`/`node:test`). Do not introduce one for this change. Verification is via the dev server in a real browser, per this project's own convention for UI work.
- Desktop-only feature: the tooltip must never be shown/triggered by touch interaction. No extra code is needed to guarantee this — hover events (`mouseenter`/`mousemove`) simply don't fire on touch, so this falls out naturally as long as no click/touch handler is added by mistake.

---

### Task 1: Fix sector-tag alignment on desktop

**Files:**
- Modify: `src/pages/index.astro:720`

**Interfaces:** None — pure CSS, no interaction with other tasks.

The mobile breakpoint already has this exact fix (`src/pages/index.astro:951`):
```css
.case-sector { white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:100%; margin-left:-.8rem; }
```
The base (desktop) rule at line 720 is missing `margin-left:-.8rem`, so on desktop the tag's padding pushes its text right of where the client name starts below it.

- [ ] **Step 1: Apply the fix**

In `src/pages/index.astro`, find (line 720):
```css
.case-sector { font-size:.6rem;display:block;margin-bottom:8px; }
```
Change to:
```css
.case-sector { font-size:.6rem;display:block;margin-bottom:8px;margin-left:-.8rem; }
```

- [ ] **Step 2: Verify visually**

Run: `npm run dev` (from `/Users/macbook/mclair`), open `http://localhost:4321/` in a browser, scroll to the "Cases que falam por si" section. Confirm the small sector pill (e.g. "Leilões") above a client name (e.g. "Fidalgo Leilões") now starts at the same horizontal position as the first letter of the client name below it, on a normal desktop-width window. Compare against the mobile view (resize under 720px) — both should now look consistent.

- [ ] **Step 3: Commit**

```bash
cd /Users/macbook/mclair
git add src/pages/index.astro
git commit -m "fix: align case sector tag to client name on desktop"
```

---

### Task 2: Wire up case image/challenge data + tooltip markup + CSS

**Files:**
- Modify: `src/pages/index.astro:27-37` (cases data mapping — currently missing `img`/`challenge`)
- Modify: `src/pages/index.astro:285` (add `data-img`/`data-challenge` to each row)
- Modify: `src/pages/index.astro:301` (insert the shared tooltip element after `.cases-list` closes)
- Modify: `src/pages/index.astro` `<style>` block, after line 728 (add `.case-tooltip` CSS)

**Interfaces:**
- Produces (consumed by Task 3):
  - Each `.case-row` element has `data-img` (string URL, may be empty) and `data-challenge` (string, may be empty) attributes.
  - `#case-tooltip` element exists once, after `.cases-list`, containing:
    - `.case-tooltip-img` — an `<img>` with empty `src` by default.
    - `.case-tooltip-text` — a `<p>`, empty by default.
  - CSS class `.is-visible` on `#case-tooltip` toggles it visible (`opacity:1;visibility:visible`); absent by default (`opacity:0;visibility:hidden;pointer-events:none`).

The current `cases` mapping (lines 27–37) only pulls `client`, `sector`, `result`, `accent`, `slug` — it needs `img` and `challenge` too. Both fields already exist on every case entry (confirmed: all 13 cases have `challenge`, `img` is used the same way already in `src/components/Header.astro:13` and `src/pages/cases/index.astro:20` — `c.entry.img` resolves directly to a usable `src` string, no transform needed).

- [ ] **Step 1: Add `img` and `challenge` to the cases data mapping**

In `src/pages/index.astro`, find (lines 27–37):
```astro
const allCases = await reader.collections.cases.all().catch(() => []);
const cases = (allCases as any[])
  .sort((a, b) => (a.entry.num ?? '').localeCompare(b.entry.num ?? ''))
  .slice(0, 5)
  .map(c => ({
    client: c.entry.client,
    sector: c.entry.sector ?? '',
    result: c.entry.homeResult || (c.entry.results?.[0] ? `${c.entry.results[0].v} ${c.entry.results[0].l}` : ''),
    accent: c.entry.accent || '#C8102E',
    slug:   c.slug,
  }));
```
Change to:
```astro
const allCases = await reader.collections.cases.all().catch(() => []);
const cases = (allCases as any[])
  .sort((a, b) => (a.entry.num ?? '').localeCompare(b.entry.num ?? ''))
  .slice(0, 5)
  .map(c => ({
    client: c.entry.client,
    sector: c.entry.sector ?? '',
    result: c.entry.homeResult || (c.entry.results?.[0] ? `${c.entry.results[0].v} ${c.entry.results[0].l}` : ''),
    accent: c.entry.accent || '#C8102E',
    slug:   c.slug,
    img:    c.entry.img ?? '',
    challenge: c.entry.challenge ?? '',
  }));
```

- [ ] **Step 2: Add data attributes to each case row**

Find (line 285):
```astro
        <a href={`/cases/${c.slug}`} class="case-row" style={`--accent:${c.accent}`} data-anim data-delay={(i % 2 + 1).toString()}>
```
Change to:
```astro
        <a href={`/cases/${c.slug}`} class="case-row" style={`--accent:${c.accent}`} data-anim data-delay={(i % 2 + 1).toString()} data-img={c.img} data-challenge={c.challenge}>
```

- [ ] **Step 3: Add the shared tooltip element**

Find (lines 300–302):
```astro
        </a>
      ))}
    </div>
    <div class="cases-cta-row" data-anim>
```
Change to:
```astro
        </a>
      ))}
    </div>

    <div id="case-tooltip" class="case-tooltip" aria-hidden="true">
      <div class="case-tooltip-img-wrap">
        <img class="case-tooltip-img" src="" alt="" />
      </div>
      <p class="case-tooltip-text"></p>
    </div>

    <div class="cases-cta-row" data-anim>
```
(Note: the tooltip `div` is a sibling of `.cases-list`, not nested inside any `.case-row` — this matters for Task 3, since it means the tooltip is never affected by `.case-row`'s `overflow:hidden` or by the `[data-anim]` transform applied to rows.)

- [ ] **Step 4: Add the tooltip CSS**

Find (lines 723–730):
```css
.case-cta {
  display:flex;align-items:center;gap:6px;white-space:nowrap;
  font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;
  color:var(--ink-4);transition:color .2s,gap .2s;
}
.case-row:hover .case-cta { color:var(--accent,var(--red));gap:10px; }

.cases-cta-row { text-align:center;margin-top:56px; }
```
Change to:
```css
.case-cta {
  display:flex;align-items:center;gap:6px;white-space:nowrap;
  font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;
  color:var(--ink-4);transition:color .2s,gap .2s;
}
.case-row:hover .case-cta { color:var(--accent,var(--red));gap:10px; }

.case-tooltip {
  position:fixed;top:0;left:0;z-index:50;width:230px;
  background:#fff;border:1px solid var(--line);border-radius:12px;
  box-shadow:0 14px 34px rgba(0,0,0,.16);overflow:hidden;
  opacity:0;visibility:hidden;pointer-events:none;
  transition:opacity .15s ease;
}
.case-tooltip.is-visible { opacity:1;visibility:visible; }
.case-tooltip-img-wrap { aspect-ratio:21/9;background:var(--cream-2); }
.case-tooltip-img { width:100%;height:100%;object-fit:cover;display:block; }
.case-tooltip-text {
  margin:0;padding:10px 12px 12px;font-size:.78rem;line-height:1.5;color:var(--ink-3);
  display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;
}

.cases-cta-row { text-align:center;margin-top:56px; }
```

- [ ] **Step 5: Verify the markup renders correctly (no JS yet — tooltip should stay invisible)**

Run: `npm run dev`, then in a separate terminal:
```bash
curl -s http://localhost:4321/ | grep -o 'data-img="[^"]*"' | head -5
curl -s http://localhost:4321/ | grep -o 'data-challenge="[^"]*"' | head -1
curl -s http://localhost:4321/ | grep -c 'id="case-tooltip"'
```
Expected: the first command prints 5 non-empty `data-img="..."` values (one per homepage case), the second prints a `data-challenge="..."` with real sentence text (not empty), the third prints `1`. Then open `http://localhost:4321/` in a browser and confirm hovering a case row does **not** show anything yet (Task 3 hasn't wired up the JS) — this isolates markup/CSS correctness from interaction correctness.

- [ ] **Step 6: Commit**

```bash
cd /Users/macbook/mclair
git add src/pages/index.astro
git commit -m "feat: wire up case image/challenge data and tooltip markup+CSS"
```

---

### Task 3: Cursor-following tooltip interaction

**Files:**
- Modify: `src/pages/index.astro:416-430` (existing `<script>` block)

**Interfaces:**
- Consumes (from Task 2): `.case-row[data-img]`, `.case-row[data-challenge]`, `#case-tooltip`, `.case-tooltip-img`, `.case-tooltip-text`, `.is-visible` class contract.
- Produces: nothing consumed by other tasks — this is the last task.

- [ ] **Step 1: Add the hover-tracking script**

Find (lines 416–430):
```astro
<script>
  (function () {
    // Mentoria swipe hint + nudge
    const hint = document.querySelector('.mt-swipe-hint') as HTMLElement;
    const row = document.querySelector('.mt-cards-row') as HTMLElement;
    if (hint && row && window.innerWidth <= 640) {
      setTimeout(() => {
        row.scrollTo({ left: 56, behavior: 'smooth' });
        setTimeout(() => row.scrollTo({ left: 0, behavior: 'smooth' }), 550);
      }, 1000);
      row.addEventListener('scroll', () => { hint.style.opacity = '0'; }, { once: true });
    }

  })();
</script>
```
Change to:
```astro
<script>
  (function () {
    // Mentoria swipe hint + nudge
    const hint = document.querySelector('.mt-swipe-hint') as HTMLElement;
    const row = document.querySelector('.mt-cards-row') as HTMLElement;
    if (hint && row && window.innerWidth <= 640) {
      setTimeout(() => {
        row.scrollTo({ left: 56, behavior: 'smooth' });
        setTimeout(() => row.scrollTo({ left: 0, behavior: 'smooth' }), 550);
      }, 1000);
      row.addEventListener('scroll', () => { hint.style.opacity = '0'; }, { once: true });
    }

    // Cases hover tooltip — follows cursor, shows image + challenge text
    const tooltip = document.getElementById('case-tooltip') as HTMLElement;
    const tooltipImg = tooltip?.querySelector('.case-tooltip-img') as HTMLImageElement;
    const tooltipText = tooltip?.querySelector('.case-tooltip-text') as HTMLElement;
    const caseRows = document.querySelectorAll('.case-row');
    const TOOLTIP_OFFSET = 20;
    const TOOLTIP_WIDTH = 230;

    if (tooltip && tooltipImg && tooltipText) {
      caseRows.forEach((row) => {
        const el = row as HTMLElement;

        el.addEventListener('mouseenter', () => {
          tooltipImg.src = el.dataset.img || '';
          tooltipText.textContent = el.dataset.challenge || '';
          tooltip.classList.add('is-visible');
        });

        el.addEventListener('mousemove', (e) => {
          const evt = e as MouseEvent;
          let x = evt.clientX + TOOLTIP_OFFSET;
          const y = evt.clientY - TOOLTIP_OFFSET;
          if (x + TOOLTIP_WIDTH > window.innerWidth) {
            x = evt.clientX - TOOLTIP_OFFSET - TOOLTIP_WIDTH;
          }
          tooltip.style.left = x + 'px';
          tooltip.style.top = y + 'px';
        });

        el.addEventListener('mouseleave', () => {
          tooltip.classList.remove('is-visible');
        });
      });
    }

  })();
</script>
```

- [ ] **Step 2: Verify in a real browser (this project has no test framework — this is the actual test for this feature)**

Run: `npm run dev`, open `http://localhost:4321/` in a browser at a normal desktop width (e.g. 1440px).

Golden path:
1. Scroll to the Cases section, hover over any case row (e.g. "Fidalgo Leilões").
2. Confirm a small card appears near the cursor, offset down-right, showing that case's image (21:9 crop) and up to 3 lines of its "Desafio" text.
3. Move the mouse around while still over the same row — confirm the card follows the cursor smoothly.
4. Move to a different case row without leaving the list — confirm the image and text swap to the new case.
5. Move the mouse off the cases list entirely — confirm the tooltip disappears.

Edge case (the viewport-edge flip):
6. Resize the browser to a narrower width or hover a case row while the mouse is near the right edge of the window — confirm the tooltip flips to appear to the *left* of the cursor instead of running off-screen to the right.

Mobile (must show nothing):
7. Resize below 720px width (or use device toolbar). Confirm there is no way to trigger the tooltip via touch/tap — tapping a case row should just navigate to `/cases/{slug}` as before.

If any of these don't match, fix `src/pages/index.astro` before proceeding — do not commit a partially-working interaction.

- [ ] **Step 3: Commit**

```bash
cd /Users/macbook/mclair
git add src/pages/index.astro
git commit -m "feat: add cursor-following hover tooltip to case rows"
```
