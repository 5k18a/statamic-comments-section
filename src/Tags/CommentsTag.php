<?php

namespace Skalisty\Comments\Tags;

use Skalisty\Comments\Settings\CommentsSettings;
use Statamic\Tags\Tags;

class CommentsTag extends Tags
{
    protected static $handle = 'comments';

    public function section(): string
    {
        $settings = app(CommentsSettings::class);

        if (! $settings->enabled()) {
            return '';
        }

        $blogEntryId = $this->blogEntryId();

        if ($blogEntryId === '') {
            return '';
        }

        return view('comments::section', [
            'blog_entry_id' => $blogEntryId,
            'comments_site' => (string) $this->params->get('comments_site', $settings->site()),
            'per_page' => (int) $this->params->get('per_page', $settings->perPage()),
            'variant' => $this->variant(),
            'pending_message' => __('Your comment is awaiting moderation.'),
        ])->withoutExtractions()->render();
    }

    private function blogEntryId(): string
    {
        $fromParameter = trim((string) $this->params->get('blog_entry_id', ''));

        if ($fromParameter !== '') {
            return $fromParameter;
        }

        $fromContext = $this->context->raw('id');

        return is_string($fromContext) ? trim($fromContext) : '';
    }

    private function variant(): string
    {
        $variant = trim((string) $this->params->get('variant', 'three'));
        $variant = preg_replace('/[^a-z0-9_-]/i', '', $variant) ?: 'three';

        return strtolower($variant);
    }
}
