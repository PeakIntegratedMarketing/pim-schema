# PIM Schema: working notes for Claude sessions

Reusable structured-data plugin for PIM client sites (Etch + ACSS + Rank Math free).

**This repo is public.** No client credentials, staging URLs, usernames or client work notes in it (files or commit messages). Site-specific notes and access details live in each client's private project notes.

**Start every session by reading `docs/ROADMAP.md`** (decisions, schema audit, status, next steps) and `README.md` (what's built, markup, hooks). At the end of a session, update ROADMAP "Status" / "Next steps" and the README changelog.

## Rules

- Keep it client-agnostic. Anything site-specific is a setting, a filter, or lives in the site's own plugin (e.g. its `pim_schema_post_types` mapping).
- Don't break pages: the output buffer callback must return the HTML unchanged on any problem.
- FAQ markup must stay compatible with the Etch convention (`details.faq-item > summary.faq-item__question + .faq-item__answer`, `aria-hidden` icon span).
- Blocks are dynamic (save = InnerBlocks only). Changing attributes is fine; changing what `save` returns needs a deprecation.
- Editor JS is plain ES5-style `wp.element.createElement`, no build step. Keep the parser (`assets/faq-parse.js`) pure so Node tests can run it.
- CSS stays zero-specificity (`:where()`), ACSS variables with fallbacks.
- Test fixtures use made-up content (Example Village, Sample Town), not client pages.

## Workflow

- PHP: `/opt/homebrew/bin/php` (lint + tests). Node for `tests/faq-parse.test.js`. `./build.sh` runs all of it and builds `dist/pim-schema.zip`.
- Release: bump the version in `pim-schema.php` (header + `PIM_SCHEMA_VERSION`), add a README changelog line, commit, then `./release.sh` (builds, tags `vX.Y.Z`, pushes, creates the GitHub release with the zip). Every site then offers the update on its Plugins screen. `./release.sh --pre` makes a pre-release that sites ignore (install that zip on a staging site by hand to test first).
- `lib/plugin-update-checker/` is vendored (v5.7, MIT); update it by replacing the folder.
- Verify on a site by fetching pages and parsing the `script.pim-schema` JSON.
