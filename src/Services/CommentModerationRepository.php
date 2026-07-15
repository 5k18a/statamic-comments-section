<?php

namespace Skalisty\Comments\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Skalisty\Comments\Settings\CommentsSettings;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\CP\Column;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;

class CommentModerationRepository
{
    private const int MAX_PER_PAGE = 50;

    public function __construct(private readonly CommentsSettings $settings) {}

    /**
     * @return array<string, mixed>
     */
    public function indexData(): array
    {
        $cards = $this->blogCards();

        return [
            'cards' => $cards,
            'summary' => $this->summary($cards),
            'sites' => $this->siteOptions($cards),
            'commentsEnabled' => $this->settings->enabled(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function blogCards(): array
    {
        return $this->allComments()
            ->filter(fn (EntryContract $comment): bool => $this->blogEntryId($comment) !== '')
            ->groupBy(fn (EntryContract $comment): string => $this->blogEntryId($comment))
            ->map(fn (Collection $comments, string $blogEntryId): array => $this->blogCard($blogEntryId, $comments))
            ->sortByDesc('latest_timestamp')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function rows(string $blogEntryId, Request $request): array
    {
        $comments = $this->commentsForBlog($blogEntryId);
        $comments = $this->applySearch($comments, trim((string) $request->query('search', '')));
        $rows = $this->threadedRows(
            $comments,
            $this->safeSort((string) $request->query('sort', 'commented_at')),
            $this->safeOrder((string) $request->query('order', 'desc')),
        );

        return $this->paginatedListingResponse(
            $rows,
            max(1, (int) $request->query('page', 1)),
            max(1, min(self::MAX_PER_PAGE, (int) $request->query('perPage', 15))),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function setPublished(string $commentId, bool $published): array
    {
        $comment = $this->findCommentOrFail($commentId);

        $comment->published($published)->save();

        return [
            'message' => $published ? __('Komentarz opublikowany.') : __('Publikacja komentarza cofnięta.'),
            'cards' => $this->blogCards(),
            'comment' => $this->row($comment, 0, $this->commentsForBlog($this->blogEntryId($comment))),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function destroy(string $commentId): array
    {
        $comment = $this->findCommentOrFail($commentId);
        $blogEntryId = $this->blogEntryId($comment);
        $commentsForBlog = $this->commentsForBlog($blogEntryId);
        $toDelete = $this->cascadeComments($comment, $commentsForBlog);

        $toDelete
            ->sortByDesc(fn (EntryContract $entry): int => $entry->id() === $comment->id() ? 0 : 1)
            ->each(fn (EntryContract $entry): bool => $entry->delete());

        $replyCount = max(0, $toDelete->count() - 1);

        return [
            'message' => $this->deleteMessage($replyCount),
            'deleted' => $toDelete->pluck('id')->values()->all(),
            'cards' => $this->blogCards(),
        ];
    }

    private function findCommentOrFail(string $commentId): EntryContract
    {
        $comment = Entry::find($commentId);

        if (! $comment || $comment->collectionHandle() !== 'comments') {
            abort(404);
        }

        return $comment;
    }

    /**
     * @return Collection<int, EntryContract>
     */
    private function allComments(): Collection
    {
        return Entry::query()
            ->where('collection', 'comments')
            ->get()
            ->values();
    }

    /**
     * @return Collection<int, EntryContract>
     */
    private function commentsForBlog(string $blogEntryId): Collection
    {
        return Entry::query()
            ->where('collection', 'comments')
            ->where('blog_entry_id', $blogEntryId)
            ->get()
            ->values();
    }

    /**
     * @param  Collection<int, EntryContract>  $comments
     * @return array<string, mixed>
     */
    private function blogCard(string $blogEntryId, Collection $comments): array
    {
        $blogEntry = Entry::find($blogEntryId);
        $siteHandle = $blogEntry?->locale() ?: 'unknown';

        return [
            'blog_entry_id' => $blogEntryId,
            'title' => $blogEntry?->value('title') ?: __('Usunięty wpis blogowy'),
            'site' => $siteHandle,
            'site_label' => $this->siteLabel($siteHandle),
            'count' => $comments->count(),
            'published_count' => $comments->filter->published()->count(),
            'draft_count' => $comments->reject->published()->count(),
            'latest_at' => $this->latestDisplayDate($comments),
            'latest_timestamp' => $this->latestTimestamp($comments),
            'missing_blog' => ! $blogEntry,
            'edit_url' => $blogEntry?->editUrl(),
            'public_url' => $blogEntry?->absoluteUrl(),
            'rows_url' => cp_route('utilities.comments-manager.rows', ['blogEntryId' => $blogEntryId]),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $cards
     * @return array<string, int>
     */
    private function summary(array $cards): array
    {
        return [
            'blogs' => count($cards),
            'comments' => array_sum(array_column($cards, 'count')),
            'drafts' => array_sum(array_column($cards, 'draft_count')),
            'published' => array_sum(array_column($cards, 'published_count')),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $cards
     * @return array<int, array{handle: string, label: string, count: int}>
     */
    private function siteOptions(array $cards): array
    {
        return collect($cards)
            ->groupBy('site')
            ->map(fn (Collection $siteCards, string $site): array => [
                'handle' => $site,
                'label' => $this->siteLabel($site),
                'count' => $siteCards->sum('count'),
            ])
            ->sortKeys()
            ->values()
            ->all();
    }

    private function siteLabel(string $siteHandle): string
    {
        if ($siteHandle === 'unknown') {
            return __('Brak języka');
        }

        $site = Site::get($siteHandle);

        return $site ? sprintf('%s (%s)', $site->name(), $site->handle()) : strtoupper($siteHandle);
    }

    private function deleteMessage(int $replyCount): string
    {
        if ($replyCount === 0) {
            return __('Usunięto komentarz.');
        }

        if ($replyCount === 1) {
            return __('Usunięto komentarz oraz 1 odpowiedź.');
        }

        return __('Usunięto komentarz oraz :count odpowiedzi.', ['count' => $replyCount]);
    }

    /**
     * @param  Collection<int, EntryContract>  $comments
     * @return Collection<int, EntryContract>
     */
    private function applySearch(Collection $comments, string $search): Collection
    {
        if ($search === '') {
            return $comments;
        }

        $needle = Str::lower($search);

        return $comments
            ->filter(function (EntryContract $comment) use ($needle): bool {
                $haystack = Str::lower(implode(' ', [
                    (string) $comment->get('author_name'),
                    (string) $comment->get('author_email'),
                    (string) $comment->get('message'),
                    (string) $comment->get('commented_at_display'),
                ]));

                return Str::contains($haystack, $needle);
            })
            ->values();
    }

    /**
     * @param  Collection<int, EntryContract>  $comments
     * @return Collection<int, array<string, mixed>>
     */
    private function threadedRows(Collection $comments, string $sort, string $order): Collection
    {
        $ids = $comments->map->id()->all();
        $children = $comments
            ->filter(fn (EntryContract $comment): bool => in_array($this->parentCommentId($comment), $ids, true))
            ->groupBy(fn (EntryContract $comment): string => $this->parentCommentId($comment));

        $parents = $comments
            ->reject(fn (EntryContract $comment): bool => in_array($this->parentCommentId($comment), $ids, true));

        return $this->sortComments($parents, $sort, $order)
            ->flatMap(function (EntryContract $comment) use ($children, $comments, $sort, $order): array {
                $rows = [$this->row($comment, 0, $comments)];

                foreach ($this->sortComments($children->get($comment->id(), collect()), $sort, $order) as $reply) {
                    $rows[] = $this->row($reply, 1, $comments);
                }

                return $rows;
            })
            ->values();
    }

    /**
     * @param  Collection<int, EntryContract>  $comments
     * @return Collection<int, EntryContract>
     */
    private function sortComments(Collection $comments, string $sort, string $order): Collection
    {
        $sorted = $comments->sortBy(fn (EntryContract $comment): mixed => match ($sort) {
            'author' => Str::lower((string) $comment->get('author_name')),
            'message' => Str::lower((string) $comment->get('message')),
            'status' => $comment->published() ? 1 : 0,
            default => $this->timestamp($comment),
        });

        return ($order === 'desc' ? $sorted->reverse() : $sorted)->values();
    }

    /**
     * @param  Collection<int, EntryContract>  $commentsForBlog
     * @return array<string, mixed>
     */
    private function row(EntryContract $comment, int $depth, Collection $commentsForBlog): array
    {
        $message = trim((string) $comment->get('message'));

        return [
            'id' => $comment->id(),
            'author' => (string) $comment->get('author_name', __('Anonymous')),
            'author_email' => (string) $comment->get('author_email'),
            'avatar' => [
                'name' => (string) $comment->get('author_name', __('Anonymous')),
                'initials' => $this->initials((string) $comment->get('author_name', __('Anonymous'))),
            ],
            'message' => $message,
            'message_excerpt' => Str::limit($message, 300),
            'status' => $comment->published() ? 'published' : 'draft',
            'published' => $comment->published(),
            'commented_at' => $this->timestamp($comment),
            'commented_at_display' => $this->displayDate($comment),
            'parent_comment' => $this->parentCommentId($comment),
            'depth' => $depth,
            'reply_count' => $commentsForBlog
                ->filter(fn (EntryContract $reply): bool => $this->parentCommentId($reply) === $comment->id())
                ->count(),
            'publish_url' => cp_route('utilities.comments-manager.publish', ['commentId' => $comment->id()]),
            'unpublish_url' => cp_route('utilities.comments-manager.unpublish', ['commentId' => $comment->id()]),
            'delete_url' => cp_route('utilities.comments-manager.destroy', ['commentId' => $comment->id()]),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function paginatedListingResponse(Collection $rows, int $page, int $perPage): array
    {
        $total = $rows->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;
        $data = $rows->slice($offset, $perPage)->values();

        return [
            'data' => $data->all(),
            'meta' => [
                'columns' => $this->columns(),
                'activeFilterBadges' => [],
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $total > 0 ? $offset + 1 : null,
                'to' => $total > 0 ? $offset + $data->count() : null,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function columns(): array
    {
        return [
            Column::make('author')->label(__('Autor'))->sortable(true)->toArray(),
            Column::make('message')->label(__('Treść'))->sortable(true)->toArray(),
            Column::make('status')->label(__('Status'))->sortable(true)->toArray(),
            Column::make('commented_at')->label(__('Data'))->sortable(true)->toArray(),
        ];
    }

    /**
     * @param  Collection<int, EntryContract>  $commentsForBlog
     * @return Collection<int, EntryContract>
     */
    private function cascadeComments(EntryContract $comment, Collection $commentsForBlog): Collection
    {
        $toDelete = collect([$comment->id() => $comment]);
        $queue = collect([$comment->id()]);

        while ($queue->isNotEmpty()) {
            $parentId = $queue->shift();

            $children = $commentsForBlog
                ->filter(fn (EntryContract $candidate): bool => $this->parentCommentId($candidate) === $parentId)
                ->reject(fn (EntryContract $candidate): bool => $toDelete->has($candidate->id()));

            $children->each(function (EntryContract $child) use ($toDelete, $queue): void {
                $toDelete->put($child->id(), $child);
                $queue->push($child->id());
            });
        }

        return $toDelete->values();
    }

    private function blogEntryId(EntryContract $comment): string
    {
        return trim((string) $comment->get('blog_entry_id'));
    }

    private function parentCommentId(EntryContract $comment): string
    {
        return trim((string) $comment->get('parent_comment'));
    }

    private function latestDisplayDate(Collection $comments): string
    {
        $latest = $comments->sortByDesc(fn (EntryContract $comment): int => $this->timestamp($comment))->first();

        return $latest ? $this->displayDate($latest) : '';
    }

    private function latestTimestamp(Collection $comments): int
    {
        return (int) $comments->max(fn (EntryContract $comment): int => $this->timestamp($comment));
    }

    private function displayDate(EntryContract $comment): string
    {
        $display = trim((string) $comment->get('commented_at_display'));

        if ($display !== '') {
            return $display;
        }

        return Carbon::createFromTimestamp($this->timestamp($comment))
            ->timezone((string) config('app.timezone', 'UTC'))
            ->format('d.m.Y, H:i');
    }

    private function timestamp(EntryContract $comment): int
    {
        $rawDate = trim((string) $comment->get('commented_at'));

        if ($rawDate === '') {
            return 0;
        }

        try {
            return Carbon::parse($rawDate)->timestamp;
        } catch (\Throwable) {
            return 0;
        }
    }

    private function safeSort(string $sort): string
    {
        return in_array($sort, ['author', 'message', 'status', 'commented_at'], true) ? $sort : 'commented_at';
    }

    private function safeOrder(string $order): string
    {
        return $order === 'asc' ? 'asc' : 'desc';
    }

    private function initials(string $name): string
    {
        $parts = collect(preg_split('/\s+/u', trim($name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)));

        return $parts->isNotEmpty() ? $parts->implode('') : 'A';
    }
}
