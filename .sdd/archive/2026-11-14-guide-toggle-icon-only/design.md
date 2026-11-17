# Design — guide toggle as one icon, with descriptions inside

> **Status:** frozen · 2026-11-12
> **Frozen proposal:** `.sdd/changes/guide-toggle-icon-only/proposal.md`
> **Baseline:** current `fedora-backend` shared partials (apply-time HEAD
> `d2d91ae`, after the Round 2 replacement-arrangement top zone)
> **Reference:** My Timetable header, top-right question-mark icon
>
> **Apply-time sync (2026-11-12):** no decision changed. Two implementation
> details were pinned while applying:
> 1. The flex header is scoped with `.page-header:has(> .page-header-main)` so
>    the frozen raw design-template header (bare `h1` + `p`) keeps block flow.
> 2. The mobile 44px icon explicitly sets `min-width`/`min-height: 44px` to opt
>    out of the global `button { min-width/min-height: 48px }` mobile floor.

## 1. Page header structure

`partials.ui-page-header.blade.php` becomes a flex row with one wrapper for the
existing content and an optional icon sibling:

```blade
<div class="page-header">
    <div class="page-header-main">
        @if($title)<h1 class="page-title">{{ $title }}</h1>@endif
        ...chips/description as today...
    </div>
    @if($guideToggle)
        <button class="guide-toggle-icon" type="button"
                aria-controls="guideBlock"
                aria-expanded="false"
                aria-label="{{ $guideLabel ?? 'How to use this page' }}"
                onclick="var b=document.getElementById('guideBlock');if(!b)return;var o=b.classList.toggle('open');this.setAttribute('aria-expanded',o)">
            @include('partials.ui-guide-icon', ['size' => 18])
        </button>
    @endif
</div>
```

- `$guideToggle = $guideToggle ?? false`; `$guideLabel` defaults to
  `How to use this page`.
- No absolute positioning. The icon is `flex-shrink: 0` at the right; the
  main wrapper shrinks first.
- The header partial still renders `description`/`descriptionHtml` when a
  caller passes it. The 8 live pages stop passing it because their copy moves
  into the guide.
- The `replacement-arrangement` header has no title/chips; the flex row still
  gives the icon a real 40/44px row with no overlap.

## 2. Guide icon partial

New `partials.ui-guide-icon.blade.php`:

```blade
@php $size = $size ?? 16; @endphp
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24"
     fill="none" stroke="currentColor" stroke-width="2"
     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <circle cx="12" cy="12" r="10"></circle>
    <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
    <line x1="12" y1="17" x2="12.01" y2="17"></line>
</svg>
```

- Included from the header icon (size 18) and the legacy `.guide-toggle` full
  bar (size 16). No duplicated SVG markup.

## 3. Guide block partial

`partials.ui-guide-block.blade.php` gains:

```blade
@php
    $guideTitle = $guideTitle ?? 'Quick Guide';
    $guideItems = $guideItems ?? [];
    $showToggle = $showToggle ?? true;
    $guideDescriptionHtml = $guideDescriptionHtml ?? null;
@endphp
<div class="guide-block{{ $showToggle ? '' : ' guide-block--content' }}" id="guideBlock">
    @if($showToggle)
        <button class="guide-toggle" onclick="document.getElementById('guideBlock').classList.toggle('open')">
            <span class="guide-icon">
                @include('partials.ui-guide-icon', ['size' => 16])
            </span>
            {{ $guideTitle }}
            <span class="guide-chevron">&#9662;</span>
        </button>
    @endif
    <div class="guide-content">
        @if($guideDescriptionHtml)
            <div class="guide-description">{!! $guideDescriptionHtml !!}</div>
        @endif
        @if(count($guideItems) > 0)
            <ul class="guide-list">
                @foreach($guideItems as $item)<li>{!! $item !!}</li>@endforeach
            </ul>
        @endif
    </div>
</div>
```

- Keep the legacy `.guide-icon` wrapper and the full bar's `onclick` exactly;
  only replace the inline SVG with the shared icon partial.
- `guideItems` defaults to `[]`, so the two student pages (no items) render a
  description-only guide without an undefined-variable error.
- The plain `guideDescription` variant is intentionally dropped: all seven
  descriptions are rich HTML. Record this as a declarative simplification from
  the proposal's optional plain arm.

## 4. CSS

```css
.page-header {
    margin-bottom: 20px;
}
.page-header:has(> .page-header-main) {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}
.page-header-main {
    min-width: 0;
    flex: 1 1 auto;
}
.guide-toggle-icon {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    padding: 0;
    border: 1px solid var(--color-outline);
    border-radius: var(--radius-md);
    background: var(--color-surface);
    color: var(--color-primary);
    cursor: pointer;
    transition: background var(--transition), border-color var(--transition),
                color var(--transition);
}
.guide-toggle-icon:hover {
    background: var(--color-surface-variant);
    border-color: var(--color-outline-strong);
}
.guide-toggle-icon:focus-visible {
    outline: 2px solid var(--color-primary);
    outline-offset: 2px;
}
.guide-toggle-icon[aria-expanded="true"] {
    background: var(--color-primary-container);
    border-color: var(--color-primary);
    color: var(--color-on-primary-container);
}
.guide-block--content {
    margin: 8px 0 12px;
    background: transparent;
    border: 0;
    border-radius: 0;
    overflow: visible;
}
.guide-block--content .guide-content { padding: 8px 0 12px; }
.guide-description {
    margin: 0 0 10px;
    font-size: 14px;
    line-height: 1.5;
    color: var(--color-on-surface-variant);
}
@media (max-width: 768px) {
    .guide-toggle-icon {
        width: 44px;
        height: 44px;
        min-width: 44px;  /* opt out of the global 48px button floor */
        min-height: 44px;
    }
}
```

- The description uses the same rich `.info-keyword` HTML; no colour override.
- The icon is used at all widths 320px+; no separate desktop bar.
- `page-desc` stays defined for non-opted callers.

## 5. Page edits

For the six existing guide pages:

- add `'guideToggle' => true` to `ui-page-header`;
- remove `descriptionHtml` from the header include;
- add `'guideDescriptionHtml' => <the same string>` and
  `'showToggle' => false` to `ui-guide-block`;
- drop the now-unused `guideTitle` (or leave it only where a non-opted caller
  needs it).

Pages: `my-timetable`, `venue-timetable`, `cohort-timetable`,
`replacement-home`, `replacement-arrangement` (no description; items only),
`request-approval`.

For the two student pages:

- add `'guideToggle' => true` to the header;
- remove `descriptionHtml` from the header;
- add a new `ui-guide-block` immediately after the header include (before
  `.semester-progress` on `student-my-timetable`, before the toolbar on
  `replacement-history`) with `'showToggle' => false` and
  `'guideDescriptionHtml' => <the page description>`; no `guideItems`.

Pages: `student-my-timetable`, `replacement-history` (Class Replacements).

The `replacement-history` include has a trailing Blade comment and is pinned by
a feature test; update the pin or keep the header arguments on one line.

## 6. Tests

- New `tests/guide-toggle-icon.spec.ts`:
  - roles/addresses: staff-lecturer for `/my-timetable-ui`,
    `/venue-timetable-ui`, `/cohort-timetable`, `/replacement-home-ui`,
    `/replacement-arrangement`; PL for `/request-approval-ui`; student for
    `/student-my-timetable-ui` and `/student-class-replacements`. Reuse one
    session per role.
  - for all 8 pages: exactly one `.guide-toggle-icon`, no `.guide-toggle` bar;
  - clicking the icon opens `#guideBlock` and flips `aria-expanded`;
  - `.guide-description` contains the moved page description and is hidden
    while the guide is closed; assert this on the **7 pages that have a
    description** (all except `replacement-arrangement`).
  - 320/390/768/desktop: icon 44px at mobile, no intersection with
    `.page-title`/`.page-chips`, title not clipped;
  - the two student pages render description-only guides with no `<ul>`.
- Non-opted path is a **Blade render test** (not a routable live page), landed as
  `tests/Feature/GuideTogglePartialTest.php`: both partials keep their old
  defaults (bar/description present, icon absent), and the opted header renders
  the icon; the optional `APP_MOCK_FALLBACK` template visual check still applies.
- `tests/venue-timetable.spec.ts`: replace the `.page-desc` assertion with
  `open guide → .guide-description` text.
- `tests/Feature/StudentReplacementHistoryParityTest.php` needs **no edit**: the
  header include stays one line and its
  `@include('partials.ui-page-header', ['title' => 'Class Replacements'`
  substring still matches.
- Existing header/nav tests remain valid through `.page-header-main`.
- Note: the guide-list hairline rules were tuned for a filled card; on the
  transparent content-only block they remain as-is unless the visual check
  says otherwise.

## 7. Changelog and close-out

- Append a dated entry to each affected page's changelog; create one where no
  changelog exists. If the set is large, one shared entry is acceptable only if
  every page name is listed.
- Static checks: `php -l`, `php artisan view:cache`/`clear`, `git diff --check`.
- Restart through `scripts/dev-serve.sh` after the Blade/partial change.
- All 8 affected pages already have changelogs; a shared cross-page entry that
  lists all 8 pages is acceptable and preferred over eight near-duplicate
  lines.
- No JS file change, no DB/schema, no booking-rule change.

## 8. Risks

| Risk | Mitigation |
|---|---|
| 320px title/icon collision | `min-width:0` main + bounding-box test |
| Description disappears | It is moved, not removed; test each of the 7 descriptions |
| Old bar remains on an opted page | `showToggle=false` + `.guide-toggle` count assertion |
| Description appears twice | Remove `descriptionHtml` from the 8 headers |
| Student pages have no items | Omit the `<ul>`; `guideItems` still defaults to `[]` |
| Livewire re-render closes guide | Existing client-only state; documented |
| Legacy templates lose the icon wrapper | Keep `.guide-icon` around the shared SVG |
| Design templates inherit flex | Visual check; no icon added |
