# KABITAB Story System

Version: 1.0.0

## Changelog

### 0.6.0

- Added the `[kabitab_reader_library]` shortcode, which displays each signed-in reader's saved KABITAB Stories using the existing Favorites plugin.

### 0.5.0

- Added the existing Favorites plugin's save control to Story pages.
- Enabled native WordPress discussion on Chapters only; new Chapters open comments by default without changing global comment settings.

### 0.4.0

- Added the `[kabitab_writer_dashboard]` shortcode for signed-in writers.
- The dashboard lists only the current writer's Stories, shows each Story's Chapters, and provides scoped links to create or edit content.
- Added the `KABITAB Writer` role with the same capabilities as WordPress's Author role. Existing user roles and accounts are unchanged.

### 0.2.2

- Added the reusable `[kabitab_story_card id="2475"]` shortcode for an isolated dynamic Story-card pilot.
- The card reads Story data, uses the existing public-author helper, and links to the Story permalink.
- The pilot does not change Home, Explore, or any existing manual cards.

### 0.2.1

- Added a centralized public-author display helper for Story and Chapter frontend output.
- Maps `rijan-khadg` and the existing local `rijan-khadgi` display value to the public pen name `a_reason`.
- Leaves WordPress accounts, usernames, and post ownership unchanged.

This plugin provides the Phase 2B reusable Story and Chapter logic for KABITAB: public `kabitab_story` and `kabitab_chapter` post types, the non-hierarchical `story_genre` taxonomy, protected native metadata, Unicode-aware reading-time calculations, chapter navigation helpers, and editor safeguards.

## Content model

- Stories use `/story/` as their temporary local rewrite base and include title, editor, excerpt, featured image (cover), author, and revisions.
- Chapters use `/chapter/` as their temporary local rewrite base and include title, editor, featured image (chapter image), author, and revisions.
- Genres are attached to Stories and are exposed through the WordPress REST API.
- Metadata is registered natively and is managed through the WordPress editor; it is not made publicly writable through REST.

## Data safety

All editor saves use nonces, capability checks, autosave/revision protection, validation, sanitization, and escaped output. The plugin never flushes rewrite rules during normal requests. It only flushes them during activation and deactivation.

Deactivation preserves all stories, chapters, terms, and metadata. `uninstall.php` intentionally does not delete any data. To remove data manually, first take a verified backup, deactivate the plugin, and use a separately reviewed migration or cleanup plan. Removing the plugin files alone does not delete stored KABITAB content or metadata.

## Phase boundary

The isolated pilot adds one shortcode template and scoped stylesheet only. It does not alter Home, Explore, existing manual cards, migration, reader rendering, navigation buttons, or automatic public counters.
