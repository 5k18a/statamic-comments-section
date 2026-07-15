# Statamic Comments Section

[![Latest Version](https://img.shields.io/github/v/tag/5k18a/statamic-comments-section?label=version&sort=semver)](https://github.com/5k18a/statamic-comments-section/tags)
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Statamic 6](https://img.shields.io/badge/Statamic-6-FF269E.svg)](https://statamic.com)

A self-contained Statamic addon that adds **entry-based blog comments** with moderation and a global on/off kill switch. Comments are stored as regular Statamic entries — Git-friendly, queryable, and fully under your control. No external service, no database table, no third party.

> Package name on Packagist/Composer: **`skalisty/comments`**. Source repository: **`5k18a/statamic-comments-section`**.

## Features

- **Entry-based storage** — every comment is an entry in a `comments` collection (flat files, versionable).
- **Moderation** — new comments are created as **drafts** and only appear once published (`require_moderation`).
- **Threaded replies** — each comment records its parent, so replies nest under the comment they answer.
- **Global kill switch** — enable/disable the whole system from a Global Set; when off, nothing renders on the front end and the submission listener is inert.
- **Static-cache aware** — the related blog entry's static cache is invalidated automatically whenever a comment is created, published, or deleted.
- **Deterministic avatars** — `avatar_initial` and `avatar_color` modifiers generate initial-based avatars with a stable per-author color (no uploads, no Gravatar dependency).
- **Moderation panel (Control Panel)** — a **Tools › Comments** utility (native Statamic UI kit, Inertia/Vue) to browse comments grouped per blog entry across all locales, with a lazy per-blog listing, full-comment modal, and inline publish / unpublish / cascade-delete. Available even when front-end comments are disabled.
- **Migration command** — convert existing form submissions into comment entries.

## Requirements

- PHP 8.4+
- Statamic 6

## Installation

```bash
composer require skalisty/comments
```

Until the package is listed on Packagist, install straight from the repository by adding it to your project `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/5k18a/statamic-comments-section"
        }
    ]
}
```

then run `composer require skalisty/comments:dev-main`.

Publish the config file (optional):

```bash
php artisan vendor:publish --tag=comments-config
```

## Configuration

`config/comments.php`:

```php
return [
    'enabled'            => false,   // master switch (fallback for the Global Set)
    'require_moderation' => true,    // new comments start as drafts
    'per_page'           => 5,       // comments per page on the front end
    'site'               => 'pl',    // site handle the comments collection lives on
    'settings' => [
        'global_handle' => 'comments', // Global Set that overrides these values in the CP
    ],
];
```

The `comments` Global Set overrides `enabled`, `require_moderation` and `per_page` at runtime, so editors can toggle the system from the Control Panel without a deploy. Config values are the fallback.

## Setup

The addon expects:

1. A **`comments` collection** (site handle matching `config('comments.site')`) using the bundled `comment` blueprint fields: `title`, `author_name`, `author_email`, `message`, `commented_at`, `blog_entry_id`, `parent_comment`, `source_submission_id`.
2. A **form** (default handle `blog_comment`) whose submissions are converted into comment entries. Hidden fields `blog_id` (the blog entry id) and `parent_id` (optional parent comment id) drive the association and threading.

## Usage

Render the comments block on a blog entry template:

```antlers
{{ comments:section }}
```

Parameters:

| Parameter       | Default            | Description                                   |
|-----------------|--------------------|-----------------------------------------------|
| `blog_entry_id` | current entry `id` | Which blog entry the comments belong to.      |
| `variant`       | `three`            | Layout variant of the bundled view.           |
| `per_page`      | config `per_page`  | Comments per page.                            |
| `comments_site` | config `site`      | Site handle the comments collection lives on. |

When the system is disabled (Global Set or config), the tag renders an empty string.

### Avatar modifiers

```antlers
<span style="background: {{ author_name | avatar_color }}">
    {{ author_name | avatar_initial }}
</span>
```

- `avatar_initial` — first letter of the author's name (uppercase), `?` fallback.
- `avatar_color` — deterministic `hsl(...)` background derived from the name (same author → same color).

## Migration command

Convert existing `blog_comment` submissions into comment entries:

```bash
php please comments:migrate            # create drafts
php please comments:migrate --publish  # publish immediately
php please comments:migrate --dry-run  # preview without writing
```

## Roadmap

- Packagist listing for a plain `composer require skalisty/comments`.
- Optional email notifications on new comments.

## License

[MIT](LICENSE) — free to use, modify and distribute, for everyone.

## Credits

Created and maintained by [Marcin Skibicki (5k18a)](https://github.com/5k18a).
