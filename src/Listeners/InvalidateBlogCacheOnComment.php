<?php

namespace Skalisty\Comments\Listeners;

use Statamic\Facades\Entry;
use Statamic\StaticCaching\Cacher;

class InvalidateBlogCacheOnComment
{
    public function __construct(private readonly Cacher $cacher) {}

    public function handle(object $event): void
    {
        $comment = $event->entry ?? null;

        if (! $comment || $comment->collectionHandle() !== 'comments') {
            return;
        }

        $blogEntryId = trim((string) $comment->get('blog_entry_id'));

        if ($blogEntryId === '') {
            return;
        }

        $blogEntry = Entry::find($blogEntryId);
        $url = $blogEntry?->absoluteUrl();

        if (! is_string($url) || $url === '') {
            return;
        }

        $this->cacher->invalidateUrl($url);
    }
}
