# Changelog

## 0.2.5

- Added an explicit close (X) button to the comment detail modal (top-right). The modal was already dismissible by clicking outside; the X gives a clearer affordance. Uses the kit `ModalClose` + `x` icon.

## 0.2.4

- Fixed blank in-panel Control Panel icons (Statamic 6 renamed several icons in its set). Remapped to existing S6 icons: `comments` -> `mail-chat-bubble-text` (empty state + detail modal), `check` -> `checkmark` (publish), `remove` -> `eye-slash` (unpublish), `view` -> `eye` (view details); `trash` unchanged. Rebuilt the CP bundle.

## 0.2.3

- Fixed the Control Panel utility icon (Tools > Comments). The previous `comments` icon name does not exist in the Statamic 6 icon set, so the utility rendered without an icon; switched to `mail-chat-bubble-text` (a chat bubble with text lines). Note: in-panel action and empty-state icons still use legacy Statamic 5 names and are tracked for a follow-up.

## 0.2.2

- Localized the Control Panel comment-listing search placeholder to Polish ("Szukaj komentarzy"). Scoped to this panel only (the Statamic UI Listing hardcodes the placeholder, so it is set locally after mount rather than via a global CP translation).

## 0.2.1

- Changed the Control Panel panel layout to an **inline accordion**: each blog card now expands its comment list directly beneath it (chevron toggle, one open at a time, listing lazy-loaded on first expand), replacing the previous two-column master-detail. Fixes the confusing "clickable card that appears to do nothing" when a blog was already selected.

## 0.2.0

- Added a Control Panel moderation panel under **Tools › Comments** (native Statamic Utility rendered with Inertia/Vue + the `@statamic/cms/ui` kit).
  - Comments grouped into per-blog-entry cards across all site locales (no CP site switching); cards show published/draft/all counts; only blogs with at least one comment appear.
  - Per-blog comment listing loaded lazily from a JSON endpoint (search, sorting, pagination); replies nested under their parent.
  - Full-comment modal (name, email, full text, date) and inline row actions.
  - Moderation actions: publish / unpublish (native `published` status) and delete with **cascade** (removing a top-level comment removes its replies).
  - Panel stays available even when front-end comments are disabled, so moderation is always possible.
- Registered `access comments_manager utility` permission (CP routes gated by it).

## 0.1.0

- Added entry-based blog comments collection integration.
- Added comments Global Set kill switch and moderation settings.
- Added submission listener, static cache invalidation listener, and migration command.
