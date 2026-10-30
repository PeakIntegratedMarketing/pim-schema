# PIM Schema (pim-schema)

Reusable structured-data plugin for PIM's WordPress sites (Etch + ACSS stack). Not tied to one client: site-specific bits are settings or filters.

- **Plan, decisions, status, next steps:** [`docs/ROADMAP.md`](docs/ROADMAP.md). Read it before changing anything.
- **Install (once per site):** download `pim-schema.zip` from the [latest release](https://github.com/PeakIntegratedMarketing/pim-schema/releases/latest), then WP admin → Plugins → Add New → Upload.
- **Updates:** new releases appear on each site's Plugins screen like any other plugin update (checked about every 12 hours; Dashboard → Updates → *Check again* to see one right away). One click to update; WordPress auto-updates can be turned on per site if wanted.
- **Build / release:** `./build.sh` (lints + runs the tests, then makes `dist/pim-schema.zip`); `./release.sh` publishes the version as a GitHub release (see `CLAUDE.md`).
- **Tests:** `php tests/extractor.test.php`, `php tests/entities.test.php`, `php tests/post-types.test.php`, `node tests/faq-parse.test.js`. WordPress isn't installed locally; behaviour is verified on a staging site.

## FAQ module (v0.1.x)

### FAQ block (`pim/faq` + `pim/faq-item`), for the block editor

For FAQs written by non-technical editors (blog articles).

1. Write the FAQ in Google Docs: **each question as Heading 3**, the answer as normal text under it (paragraphs, lists, links). A heading above the questions (e.g. "Frequently Asked Questions") becomes the FAQ title.
2. Copy the section, add an **FAQ** block, click its paste box, paste.
   Or paste the text anywhere in the post, select those blocks, **Transform → FAQ**.
3. Content above the first question stays above the FAQ; a heading above the question level (and what follows) stays below it.

Question detection fallback order: Heading 3 → fully bold paragraphs → most-used heading level (2+ uses) → short paragraphs ending in "?". `core/details` blocks always convert (summary = question). "Q1." / "1." / "A:" prefixes are removed.

Block options: title + level, questions as plain text or H2–H4 (plain is the accessible default), icon (site default / chevron / plus / triangle / browser default marker / none), one open at a time (native `<details name>`), include in schema. Items: open by default. Extra classes and anchors via the Advanced panel. FAQ → Group transform turns it back into H3 + paragraphs.

The blocks are dynamic: post content stores only the answer blocks, PHP renders the wrapper. Markup changes never invalidate saved posts.

Front-end markup (same convention as the Etch FAQs):

```html
<div class="wp-block-pim-faq faq" data-faq-icon="chevron">
  <h2 class="faq__title">…</h2>
  <div class="faq__list">
    <details class="wp-block-pim-faq-item faq-item" data-faq>
      <summary class="faq-item__question"><span class="faq-item__icon" aria-hidden="true"></span><span class="faq-item__title">Question</span></summary>
      <div class="faq-item__answer">…answer blocks…</div>
    </details>
  </div>
</div>
```

Styles (`assets/faq.css`) are zero-specificity (`:where()`), neutral (questions inherit the surrounding font, bold), use ACSS spacing variables when present, and expose `--pim-faq-*` custom properties (gap, divider, question font/size/weight/transform/letter-spacing/colour, icon size/colour, answer gap). Can be turned off in settings.

### FAQ schema (any markup)

One `FAQPage` per page, built from the **rendered HTML** (so it covers Etch pages, templates, components and loops, plus the block):

- An FAQ item is a `<details>` with `data-faq` or one of the classes in Settings → PIM Schema (default `faq-item`). Question = `<summary>` text minus `aria-hidden` parts; answer = the rest, reduced to Google's allowed tags (links keep `href`, root-relative links become absolute).
- `data-faq-schema="off"` on an item or any wrapper leaves it out (block option "Include in FAQ schema").
- Duplicate questions on a page are output once.
- Skipped when the page already contains `FAQPage` JSON-LD (e.g. a Rank Math/Yoast FAQ block).
- Output: `<script type="application/ld+json" class="pim-schema">` before `</head>`.

### Hooks

| Hook | Type | Use |
|---|---|---|
| `pim_schema_page_nodes` | filter `( array $nodes, string $html )` | Add schema nodes for the current page (all modules use this) |
| `pim_schema_should_run` | filter `bool` | Disable the output buffer for a request |
| `pim_schema_current_url` | filter `string` | URL used for `@id` / `url` |
| `pim_schema_faq_enabled` | filter `bool` | Disable FAQ schema for a request |
| `pim_schema_faq_items` | filter `array` | Edit the extracted Q&As |
| `pim_schema_faq_node` | filter `( array $node, array $items )` | Edit the FAQPage node |

## Entities module (v0.2.0)

Places (and other things) defined once with stable `@id`s (`{home}/#entity-{key}`), which pages declare they're **about** or **mention**.

- Define them in **Settings → PIM Schema → Entities**: name, key, schema.org type (e.g. `GatedResidenceCommunity`, `City`, `Volcano`, `LakeBodyOfWater`, `Park`), description, other names, main page, image, address, coordinates, map link (Google Business Profile `?cid=`), parent place ("Inside"), sameAs (Wikipedia, Wikidata, GBP, socials), amenities, "This is the organization's location".
- Link pages from the entity card (page pickers) or the **Schema: About / Mentions** panel in the block editor sidebar. Both edit the post meta `_pim_schema_about` / `_pim_schema_mentions` (arrays of keys; also in the REST `meta` of posts whose type supports custom-fields).
- Output on a page with links: `WebPage` `{url}#webpage` with `about` / `mentions` refs (merges into Rank Math's WebPage), the referenced entities plus their parent chain. Entities flagged as the organization's location add `Organization` `{home}/#organization` → `location` on the front page and on pages that reference them (only when Rank Math, or a `pim_schema_has_organization` filter, defines the Organization).
- REST: `pim_schema_entities` in `/wp/v2/settings` (array of entity objects; keys as in the form, list fields as arrays, `orgLocation` boolean).
- Hooks: `pim_schema_entity_node` ( $node, $entity ), `pim_schema_entity_refs` ( $refs, $post_id ), `pim_schema_has_organization` ( bool ).

## Post-type mappings (v0.3.0)

The site's own plugin maps its custom post types with the `pim_schema_post_types` filter: per post type a list of node **templates** (first = the page's main entity) and optionally `item_list` (page IDs/slugs, or `true`). Full syntax in the header of `includes/types/class-post-types.php`; a full example (listings, rentals, team) is `docs/examples/real-estate.php`.

```php
add_filter( 'pim_schema_post_types', function ( $types ) {
	$types['property_listing'] = array(
		'item_list' => array( 'villas-for-sale' ),
		'nodes'     => array(
			array(
				'@type'  => 'RealEstateListing',
				'@id'    => '#listing',                        // Relative to the permalink.
				'name'   => '{title}',
				'image'  => array( '{image}', '{meta_gallery:gallery|10}' ),
				'offers' => array( '@if' => '{meta:price}', '@type' => 'Offer', 'price' => '{meta:price}', 'priceCurrency' => 'USD', 'offeredBy' => '{post:agent}' ),
				'containedInPlace' => '{entity:example-village}',
			),
		),
	);
	return $types;
} );
```

- Tokens: `title url excerpt content date modified image org website webpage`, `meta:` / `meta_text:` / `meta_list:` / `meta_image:` / `meta_gallery:<key>`, `terms:<taxonomy>`, `post:<key>` (linked post → reference; its nodes are added to the page when its type is mapped), `entity:<key>`, `ref:#fragment`. A value that is exactly one token keeps its type (numbers stay numbers). Closures get `( $post, $engine )`.
- `'@if' => '{token}'` drops an object when the token is empty or 0. Empty values and objects with only `@type` are removed.
- On a single: the nodes, plus `WebPage {url}#webpage` → `mainEntity`. On `item_list` pages: `ItemList {url}#list-{post_type}` of the mapped posts linked from `<main>` (header/footer/nav ignored), in page order, when there are at least 2.
- Hooks: `pim_schema_post_types`, `pim_schema_post_type_nodes` ( $nodes, $post, $config ), `pim_schema_item_list` ( $list, $post_type, $post_ids ).

## Rank Math compatibility (v0.3.0)

With a post type's Schema Type set to **None** (recommended for pages: otherwise every page becomes an "Article" by the admin user), Rank Math also drops its Organization / WebSite / WebPage on those posts. The **Base schema everywhere** setting (`rm_global_entities`, on by default) keeps them via `rank_math/schema/add_global_entities`, and removes the author Person Rank Math then adds when nothing references it.

Nodes from all modules that share an `@id` are merged before output (e.g. WebPage `about` from entities + `mainEntity` from post types).

## Settings via REST

The option is exposed at `/wp/v2/settings` as `pim_schema` (`faq_schema`, `faq_styles`, `faq_icon`, `faq_classes`, `rm_global_entities`), so a site can be configured with an application password, e.g. `POST /wp-json/wp/v2/settings {"pim_schema": {"faq_icon": "native", …}}` (send all keys).

## Changelog

- **0.4.0** (2026-10-30): Updates from GitHub releases (Plugin Update Checker 5.7, built zip only; pre-releases ignored); `Update URI` header; public repo with made-up test fixtures and a generic example mapping (`docs/examples/real-estate.php`); GPL-2.0 license file.
- **0.3.0** (2026-10-30): Post-type mappings (`pim_schema_post_types` templates, linked posts, entity refs, ItemList on loop pages); Rank Math base graph kept on Schema Type "None" posts (`rm_global_entities`), unused author Person removed; nodes with the same `@id` merged.
- **0.2.0** (2026-10-30): Entities module: entity definitions (settings tab + REST), per-page About / Mentions (entity cards, block-editor panel, post meta), WebPage about/mentions + entity nodes with parent chain, Organization location link.
- **0.1.1** (2026-10-30): "Browser default" icon (native `<details>` marker); neutral question typography (inherits the surrounding font, bold); answer spacing via `gap` so site `p { margin: 0 }` rules can't collapse it; settings registered on `init` and exposed to REST.
- **0.1.0** (2026-10-23): FAQ module. FAQ block with Google Docs paste box and Transform → FAQ; FAQPage schema from rendered HTML (blocks + Etch `details.faq-item`); settings page (schema, item classes, default styles, default icon).
