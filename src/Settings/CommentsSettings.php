<?php

namespace Skalisty\Comments\Settings;

use Statamic\Facades\GlobalSet;
use Statamic\Facades\Site;

class CommentsSettings
{
    public function enabled(): bool
    {
        $value = $this->globalValue('enabled');

        if ($value === null) {
            return (bool) config('comments.enabled', false);
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function requireModeration(): bool
    {
        $value = $this->globalValue('require_moderation');

        if ($value === null) {
            return (bool) config('comments.require_moderation', true);
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function perPage(): int
    {
        $fallback = (int) config('comments.per_page', 5);
        $value = $this->globalValue('per_page');

        if (! is_numeric($value)) {
            return max(1, min(50, $fallback));
        }

        return max(1, min(50, (int) $value));
    }

    public function site(): string
    {
        $site = trim((string) config('comments.site', 'pl'));

        return $site !== '' ? $site : Site::default()->handle();
    }

    protected function globalValue(string $field): mixed
    {
        $handle = (string) config('comments.settings.global_handle', 'comments');

        if ($handle === '') {
            return null;
        }

        $set = GlobalSet::find($handle);

        if (! $set) {
            return null;
        }

        $site = Site::default()->handle();

        return $set->in($site)?->data()->get($field);
    }
}
