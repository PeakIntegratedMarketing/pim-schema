# PIM Schema: roadmap, decisions, status

Living doc. Update **Status** and **Next steps** at the end of every session; add decisions with dates.

## Goal

One reusable plugin for structured data on PIM's WordPress sites (Etch + ACSS + Rank Math free). First site: a real-estate community site (its project notes are kept privately, outside this repo).

## Decisions

- **2026-10-23: one plugin, modules.** Started as an FAQ plugin; widened to a schema plugin with FAQ as module 1 after the schema audit below.
- **2026-10-23: two FAQ inputs, one output.**
  - *Site/page FAQs* are built in Etch by developers as `details.faq-item` (unchanged workflow).
  - *Blog FAQs* are made by non-technical editors in Gutenberg with the `pim/faq` block. **This is the surface to optimise.** No classes required. Google Docs paste is the main path; questions = **Heading 3** (user-approved format).
  - Both render the same BEM markup, so one stylesheet covers both, and the schema is built from the rendered HTML.
- **2026-10-23: extend Rank Math, don't replace it.** Rank Math free stays on with its Schema module (Organization, WebSite, WebPage, BreadcrumbList, BlogPosting + author). We don't use Rank Math's FAQ block. Our nodes reference Rank Math's `@id`s where relevant (`{home}/#organization`, `{home}/#website`, `{url}#webpage`). Without Rank Math the plugin still outputs its own nodes.
- **2026-10-23: rendered-HTML pipeline.** An output buffer (`template_redirect`) hands the final HTML to modules (`pim_schema_page_nodes`) and prints one JSON-LD `@graph` before `</head>`. Reason: Etch `raw-html` script blocks render empty, and Etch content (templates, components, loops) isn't reliably visible earlier. The callback never breaks the page (any error returns the HTML unchanged).
- **2026-10-23: dynamic blocks, no build step.** Saved content = answer blocks only; wrapper from PHP. Editor JS is plain `wp.element.createElement` (no JSX/npm), so any session can edit it directly.
- **2026-10-23: questions as plain text by default.** Headings inside `<summary>` lose heading semantics in some screen readers; H2–H4 is a block option.
- Expectations: Google shows FAQ rich results only for government/health sites (since 2023), and real-estate/rental types have no general rich result (VacationRental is limited to Google Hotel Center partners). The value is AI search + entity understanding. Don't promise snippets to clients.

## Schema audit (2026-10-23, first client site; typical for PIM sites)

| Where | Schema | Rank Math free | Plan |
|---|---|---|---|
| All pages | Organization (logo, contact, social, GBP), WebSite, WebPage | ✅ (one location free) | Rank Math |
| All pages | BreadcrumbList | ✅ | Rank Math |
| Blog posts | BlogPosting + author Person | ✅ | Rank Math |
| Blog + site FAQs | FAQPage | ❌ (only via its own block) | **Module 1: FAQ** ✅ v0.1.0 |
| Listings (`property_listing`) | RealEstateListing → SingleFamilyResidence (beds, baths, floorSize, geo) + Offer (price, availability from status) | ❌ (custom builder is Pro, no field mapping) | Module 3: post-type mappings |
| Listing, rental and team overview pages | CollectionPage + ItemList | ❌ | Module 3 |
| Rentals (`rental`) | VacationRental / Accommodation (occupancy, rooms, amenities, geo) | ❌ | Module 3. **No aggregateRating from Airbnb reviews** (third-party reviews aren't allowed) |
| Team members | Person (jobTitle, worksFor, image, sameAs) | ⚠️ manual, one per post | Module 3 |
| Community pages (about, amenities, master plan) | The community as a `Place` with @id, geo, containedInPlace, amenityFeature; pages reference it | ❌ | Module 2: entities |
| Area guide pages | WebPage `about` / `mentions` → Place with Wikipedia/Wikidata `sameAs` | ❌ | Module 2: per-page about/mentions field |
| Model homes | VideoObject for tours | ⚠️ | Later, once each design has its own videos |

Rank Math free vs Pro (per its KB): multiple schemas per post, custom schema builder, schema templates/display conditions and schema import are **Pro**. Its own pricing page contradicts this; check on the install before relying on any of it.

## Modules

1. **FAQ** (v0.1.1, done; verified 2026-10-30): block + paste + transforms, FAQPage from rendered HTML, settings. See README.
2. **Entities** (v0.2.0, built 2026-10-30; was planned as): site-level entity definitions (e.g. the community `Place`, key places) stored as options with stable `@id`s; per-page "About / Mentions" picker (post meta, block-editor sidebar + REST for Etch pages); output via `pim_schema_page_nodes`. Links into Rank Math's Organization.
3. **Post-type mappings** (v0.3.0, built 2026-10-30): engine in this plugin (`includes/types/`), declarative node templates registered by each site's plugin with `pim_schema_post_types`; ItemList on loop pages. Example mapping: `docs/examples/real-estate.php`.
4. **Updates** (v0.4.0): GitHub releases of this public repo show up as plugin updates on every site (Plugin Update Checker). See README.
5. **Later:** VideoObject for model-home tours; BreadcrumbList check on CPT singles; Person `sameAs` (LinkedIn) once team profiles have them.

## Status

- 2026-10-23 → 2026-10-30: modules 1–3 built (v0.1.0 → v0.3.0) and verified on the first client site (a real-estate community: Etch FAQ pages, blog FAQs migrated to the block, entities with about/mentions, listings / rentals / team mappings in the site's own plugin). Schema.org validator: 0 errors.
- Findings worth knowing on any site:
  - Rank Math with a post type's Schema Type = None drops its whole graph on those posts → `rm_global_entities` (v0.3.0).
  - Rank Math free doesn't output JSON-LD on CPT singles by default (Schema Type None for CPTs) → same setting covers it.
  - Etch: REST edits to an Etch page's content are overwritten when an open builder tab saves, because one save writes back every page that tab loaded. Reload builder tabs before REST content edits.
  - Etch `raw-html` blocks with `<script>` render empty (why the plugin works from the rendered HTML).
- 2026-10-30: repo made public; updates via GitHub releases (v0.4.0, `release.sh`). Pre-public history kept privately.

## Next steps

1. Install 0.4.0 by hand once on each site (done on the first site 2026-10-30; next: SBR); later versions come through Plugins → Update. Confirm on the next release that the update notice appears.
2. Later modules as content arrives: VideoObject (e.g. model-home tours), Person `sameAs`.
3. New site: install, define entities (Settings → PIM Schema → Entities), add a `pim_schema_post_types` mapping in that site's plugin (start from `docs/examples/real-estate.php`).

## Open questions

- None open. Site-specific questions live in each client's project notes.
