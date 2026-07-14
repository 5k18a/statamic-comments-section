<?php

namespace Skalisty\Comments\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Forms\Submission;
use Statamic\Facades\Entry;

class CommentEntryCreator
{
    public function createFromSubmission(Submission $submission, bool $published = false): ?EntryContract
    {
        $sourceSubmissionId = trim((string) $submission->id());

        if ($sourceSubmissionId === '') {
            return null;
        }

        $existing = $this->existingEntry($sourceSubmissionId);

        if ($existing) {
            return $existing;
        }

        $blogEntryId = trim((string) $submission->get('blog_id'));
        $message = trim((string) $submission->get('message'));

        if ($blogEntryId === '' || $message === '') {
            return null;
        }

        $blogEntry = Entry::find($blogEntryId);

        if (! $blogEntry || $blogEntry->collectionHandle() !== 'blogs') {
            return null;
        }

        $parentId = $this->resolveParentId((string) $submission->get('parent_id'), $blogEntryId);

        if ($parentId === false) {
            return null;
        }

        $commentedAt = $this->submissionDate($submission);
        $authorName = $this->authorName($submission);
        $slug = $this->slug($sourceSubmissionId);
        $title = trim($authorName.' - '.(string) $blogEntry->value('title').' - '.$commentedAt->format('Y-m-d H:i'));

        $entry = Entry::make()
            ->collection('comments')
            ->locale((string) config('comments.site', 'pl'))
            ->slug($slug)
            ->published($published)
            ->data([
                'blueprint' => 'comment',
                'title' => $title,
                'author_name' => $authorName,
                'author_email' => mb_strtolower(trim((string) $submission->get('email'))),
                'author_website' => $this->website((string) $submission->get('your_website')),
                'message' => $message,
                'blog_entry_id' => $blogEntryId,
                'parent_comment' => $parentId ?: null,
                'source_submission_id' => $sourceSubmissionId,
                'commented_at' => $commentedAt->toIso8601String(),
                'commented_at_display' => $commentedAt->timezone((string) config('app.timezone', 'UTC'))->format('d.m.Y, H:i'),
            ]);

        $entry->save();

        return $entry;
    }

    private function existingEntry(string $sourceSubmissionId): ?EntryContract
    {
        return Entry::query()
            ->where('collection', 'comments')
            ->where('source_submission_id', $sourceSubmissionId)
            ->first();
    }

    private function resolveParentId(string $rawParentId, string $blogEntryId): string|false|null
    {
        $parentId = trim($rawParentId);

        if ($parentId === '' || in_array(mb_strtolower($parentId), ['null', 'none', '0'], true)) {
            return null;
        }

        $parent = Entry::find($parentId);

        if (! $parent || $parent->collectionHandle() !== 'comments') {
            return false;
        }

        if ((string) $parent->get('blog_entry_id') !== $blogEntryId) {
            return false;
        }

        $ancestor = trim((string) $parent->get('parent_comment'));

        return $ancestor !== '' ? $ancestor : $parent->id();
    }

    private function authorName(Submission $submission): string
    {
        $name = trim(trim((string) $submission->get('first_name')).' '.trim((string) $submission->get('last_name')));

        return $name !== '' ? $name : 'Anonymous';
    }

    private function website(string $website): ?string
    {
        $website = trim($website);

        if ($website === '') {
            return null;
        }

        return filter_var($website, FILTER_VALIDATE_URL) ? $website : null;
    }

    private function submissionDate(Submission $submission): CarbonInterface
    {
        $date = $submission->date();

        if ($date instanceof CarbonInterface) {
            return $date;
        }

        return Carbon::now();
    }

    private function slug(string $sourceSubmissionId): string
    {
        $slug = Str::slug('comment-'.str_replace('.', '-', $sourceSubmissionId));

        return $slug !== '' ? $slug : 'comment-'.Str::uuid()->toString();
    }
}
