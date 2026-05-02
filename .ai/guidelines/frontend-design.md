# frontend-design.md
# Frontend Design Conventions — Hub Digital

> **Purpose of this file:** non-negotiable rules Claude Code must follow at ALL times
> when creating or modifying any view, Blade component, Livewire component, or layout.
> This is not a tutorial. For component usage examples and patterns, activate the
> `fluxui-development`, `livewire-development`, or `tailwindcss-development` skills.

---

## 1. Color System

Hub Digital's palette is derived from EPN's institutional identity (Blue/Red), adapted to reduce visual fatigue in daily-use software. All tokens are defined in `resources/css/app.css` under `@theme`.

### 1.1 Primary Colors

| Token | Hex | Usage |
|-------|-----|-------|
| `--color-blue-navy` | `#1B365D` | Navigation bars, main headings |
| `--color-bio-green` | `#2E7D32` | Primary action buttons: Save, Confirm, Scan |
| `--color-science-blue` | `#1976D2` | Links, active states, selections |

### 1.2 Semantic Colors (System Feedback — Critical for IoT)

| Token | Hex | Usage |
|-------|-----|-------|
| `--color-success` | `#4CAF50` | "Specimen registered", "Sensor Connected", "Request logged" |
| `--color-warning` | `#FF9800` | "Loan about to expire" |
| `--color-error` | `#D32F2F` | "Box in wrong location", "Connection error" |
| `--color-info` | `#0288D1` | Contextual help |

### 1.3 Neutral Colors

| Token | Hex | Usage |
|-------|-----|-------|
| `--color-bg-main` | `#F5F7FA` | Page background (avoids pure-white glare) |
| `--color-surface` | `#FFFFFF` | Cards, panels |
| `--color-text-primary` | `#212121` | Main readable text |
| `--color-text-secondary` | `#757575` | Labels, metadata |
| `--color-border` | `#E0E0E0` | Dividers, input borders |

### 1.4 Rules

**Never:**

- ❌ Use raw hex values directly in templates (`class="bg-[#1B365D]"`, `style="color: #2E7D32"`)
- ❌ Use a semantic color for a purpose other than its defined meaning (e.g., `--color-error` for decorative red)
- ❌ Use `bg-white` for page backgrounds — use `bg-bg-main` to avoid pure-white glare

**Always:**

- ✅ Add new color tokens to `resources/css/app.css` under `@theme` before using them
- ✅ Use the `dark:` variant for every color class if the surrounding component supports dark mode

---

## 2. Typography

All sizes use **rem units**. The scale follows a "Scientific Journal" aesthetic — Roboto Slab for academic headings, Inter for all interactive UI elements.

### 2.1 Type Scale

| Level | Size | Weight | Font | Color |
|-------|------|--------|------|-------|
| H1 — Page title | `text-2xl` / 1.5rem | Bold | Roboto Slab | `text-text-primary` |
| H2 — Sections | `text-xl` / 1.25rem | SemiBold | Roboto Slab | `text-text-primary` |
| H3 — Card subtitles | `text-base` / 1rem | Medium | Inter | `text-text-primary` |
| Body — Standard text | `text-sm` / 0.875rem | Regular | Inter | `text-text-primary` |
| Caption — Labels/Metadata | `text-xs` / 0.75rem | Regular | Inter | `text-text-secondary` |

### 2.2 Rules

**Never:**

- ❌ Use px units for font sizes — always use rem-based Tailwind classes (`text-sm`, `text-xl`, etc.)
- ❌ Use Roboto Slab for anything other than H1, H2, or scientific species names
- ❌ Use Inter for page-level or section-level headings

**Always:**

- ✅ Scientific species names use Roboto Slab in **italic** (`font-serif italic`)
- ✅ Numbers in data tables use Inter — it has excellent numeric rendering at small sizes

---

## 3. Iconography & Visual Elements

### 3.1 Icons

- **Source:** Heroicons (included via Flux UI) — use `<flux:icon name="..." />` exclusively
- **Variant:** **Outline only** — stroke weight 1.5–2px. Clean and technical.
- If an icon is not available in Heroicons, import from Lucide via `php artisan flux:icon <name>`

**Never:**

- ❌ Use filled/solid icon variants — they conflict with the outline visual language
- ❌ Invent or guess icon names — look them up at heroicons.com before use

### 3.2 Borders

- **Border radius:** `rounded-lg` (8px) on cards, inputs, buttons, modals
- Avoid fully square corners (`rounded-none`) or pill shapes (`rounded-full`) on non-circular elements

### 3.3 Elevation (Shadows)

- Elevation is used only to lift cards off the background
- Use `shadow-sm` → `box-shadow: 0 2px 4px rgba(0,0,0,0.1)`
- Never apply shadows to inline elements, text, or navigation items

---

## 4. Component Architecture

Follow this decision tree **in order** before writing any template code:

```
1. Check resources/views/components/ for an existing reusable Blade component
        ↓ not found
2. Check Flux UI free edition components:
   avatar, badge, brand, breadcrumbs, button, callout, checkbox, dropdown,
   field, heading, icon, input, modal, navbar, otp-input, profile, radio,
   select, separator, skeleton, switch, text, textarea, tooltip
        ↓ not found
3. Create a new Blade component (@props + $attributes->merge())
        ↓ requires server-side reactivity
4. Create a Livewire component (call a Use Case Handler — see clean-architecture.md)
```

### 4.1 Naming & Location

**Default rule:** every view and component belongs inside its module. Only elements used across two or more modules (e.g. the navbar, app shell layouts, global error pages) belong in the root `resources/views/`.

| Type | Naming | Location |
|------|--------|----------|
| **Shared** Blade component (cross-module) | `kebab-case.blade.php` | `resources/views/components/` |
| **Shared** page layout (cross-module) | `kebab-case.blade.php` | `resources/views/layouts/` |
| Module page / Livewire template | `kebab-case.blade.php` | `Modules/<Module>/resources/views/` |
| Module Blade component | `kebab-case.blade.php` | `Modules/<Module>/resources/views/components/` |
| Livewire class | `PascalCase.php` | `Modules/<Module>/app/Presentation/Http/Controllers/` |

**Never:**

- ❌ Place a module-specific view or component under the root `resources/views/` — it must live in `Modules/<Module>/resources/views/`
- ❌ Move a component to root `resources/views/components/` just because it is reused within the same module; it only moves there when a second, different module needs it

### 4.2 Blade Component Rules

```blade
{{-- ✅ Correct — @props declared, attributes forwarded --}}
@props(['title', 'description' => null])

<div {{ $attributes->merge(['class' => 'rounded-lg bg-surface shadow-sm p-4']) }}>
    <h3 class="text-base font-medium text-text-primary">{{ $title }}</h3>
    @if($description)
        <p class="text-xs text-text-secondary">{{ $description }}</p>
    @endif
</div>

{{-- ❌ Incorrect — no @props, hardcoded styles, no attribute forwarding --}}
<div style="background: #fff; border-radius: 8px; padding: 16px;">
    <h3>{{ $title }}</h3>
</div>
```

**Rules:**

- ✅ Every new Blade component declares `@props([])` at the top
- ✅ Always use `{{ $attributes->merge(['class' => '...']) }}` to forward extra attributes to the root element
- ✅ Use `wire:navigate` on all internal `<a>` links (SPA navigation)

**Never:**

- ❌ Use `style=""` inline attributes — all styling through Tailwind utility classes
- ❌ Duplicate a Flux UI component when one already exists for the use case

---

## 5. Usability Heuristics (Critical)

These three rules from the Design Manual apply across **all modules** without exception.

### 5.1 Visibility of System Status

Always show whether the IoT system is online or offline with a visual indicator in the page header.

```blade
{{-- ✅ Correct --}}
<span class="inline-flex items-center gap-1.5 text-xs">
    <span class="size-2 rounded-full bg-success"></span>
    Online
</span>
```

**Never** hide system connectivity state from the user.

### 5.2 Error Prevention

In taxonomy forms, use **database-backed autocomplete** for scientific names to prevent typographical errors.

```blade
{{-- ✅ Correct --}}
<flux:field>
    <flux:label>Scientific Name</flux:label>
    <flux:input wire:model.live="scientificName" list="taxa-list" />
    <datalist id="taxa-list">
        @foreach($taxa as $taxon)
            <option value="{{ $taxon->name }}">
        @endforeach
    </datalist>
    <flux:error name="scientificName" />
</flux:field>
```

**Never** use a plain free-text input for scientific names without validation against the database.

### 5.3 Recognition Over Recall

When referencing a domain entity (box, cabinet, specimen, request), always display its **human-readable name** alongside or instead of its ID.

```blade
{{-- ✅ Correct --}}
Moving <strong>Box A1</strong> to <strong>Cabinet 2 — Row 3</strong>

{{-- ❌ Incorrect --}}
Moving item #142 to location #87
```

This applies to: loan requests, specimen movements, box transfers, and any entity reference in status messages or confirmations.

---

## 6. Responsive Design & Accessibility

Hub Digital is used in laboratories on tablets via QR code scanning. Design for this context first.

### 6.1 Mobile-First Breakpoints

- Always start with the mobile layout; add `md:` and `lg:` variants to expand for larger screens
- Minimum touch target size: **44×44px** for any button, link, or interactive element used with a barcode/QR scanner
- Use `gap-*` utilities for spacing between siblings, not margins

### 6.2 Contrast & Readability

- High contrast is required — lab lighting is variable
- **Never** use `text-text-secondary` (`#757575`) on colored backgrounds — it will fail WCAG AA
- Use `text-text-primary` (`#212121`) for any text that must be readable under harsh lighting

### 6.3 Dark Mode

- If surrounding pages support `dark:` variants, new components must include `dark:` variants too
- Check existing sibling components before assuming dark mode is not in use

---

## 7. Checklist Before Marking a Component as Done

- [ ] No hardcoded hex colors — only Tailwind utility classes referencing `@theme` tokens
- [ ] No `style=""` inline attributes anywhere
- [ ] Typography: H1/H2 use Roboto Slab; H3/Body/Caption use Inter
- [ ] Font sizes use rem-based Tailwind classes (`text-sm`, `text-xl`), never `text-[14px]`
- [ ] Icons are Heroicons **outline** variant only via `<flux:icon>`
- [ ] Cards and inputs use `rounded-lg` (8px border-radius)
- [ ] Elevation: `shadow-sm` only on cards — not on inline elements
- [ ] Checked `Modules/<Module>/resources/views/components/`, then `resources/views/components/`, then Flux UI before creating a new component
- [ ] Module-specific views/components are inside `Modules/<Module>/resources/views/` — not under root `resources/views/`
- [ ] A component was only promoted to root `resources/views/components/` because a second, different module needs it
- [ ] New Blade components declare `@props([])` at the top
- [ ] `$attributes->merge()` used on the root element of new Blade components
- [ ] `wire:navigate` on all internal `<a>` links
- [ ] Layout is mobile-first with responsive breakpoints
- [ ] Touch targets are ≥ 44×44px
- [ ] IoT system status is visible in header (if page includes IoT data)
- [ ] Entity names shown alongside codes/IDs — not raw IDs alone
