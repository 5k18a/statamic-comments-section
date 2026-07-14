<?php

namespace Skalisty\Comments\Listeners;

use Skalisty\Comments\Services\CommentEntryCreator;
use Skalisty\Comments\Settings\CommentsSettings;
use Statamic\Events\SubmissionCreated;

class CreateCommentFromSubmission
{
    public function __construct(
        private readonly CommentsSettings $settings,
        private readonly CommentEntryCreator $creator,
    ) {}

    public function handle(SubmissionCreated $event): void
    {
        $submission = $event->submission;

        if ($submission->form()->handle() !== 'blog_comment') {
            return;
        }

        if (! $this->settings->enabled()) {
            return;
        }

        $this->creator->createFromSubmission(
            $submission,
            ! $this->settings->requireModeration()
        );
    }
}
