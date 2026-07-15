<?php

namespace Skalisty\Comments;

use Illuminate\Support\Facades\Event;
use Skalisty\Comments\Console\MigrateCommentsCommand;
use Skalisty\Comments\Http\Controllers\CommentsUtilityController;
use Skalisty\Comments\Listeners\CreateCommentFromSubmission;
use Skalisty\Comments\Listeners\InvalidateBlogCacheOnComment;
use Skalisty\Comments\Modifiers\AvatarColor;
use Skalisty\Comments\Modifiers\AvatarInitial;
use Skalisty\Comments\Tags\CommentsTag;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntryDeleting;
use Statamic\Events\EntrySaved;
use Statamic\Events\SubmissionCreated;
use Statamic\Facades\Utility;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    protected $vite = [
        'input' => 'resources/js/cp.js',
        'publicDirectory' => 'public',
    ];

    protected $tags = [
        CommentsTag::class,
    ];

    protected $modifiers = [
        AvatarColor::class,
        AvatarInitial::class,
    ];

    public function bootAddon()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/comments.php', 'comments');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'comments');

        Event::listen(SubmissionCreated::class, CreateCommentFromSubmission::class);
        Event::listen(EntrySaved::class, InvalidateBlogCacheOnComment::class);
        Event::listen(EntryDeleting::class, InvalidateBlogCacheOnComment::class);
        Event::listen(EntryDeleted::class, InvalidateBlogCacheOnComment::class);

        $this->registerCommentsUtility();

        if ($this->app->runningInConsole()) {
            $this->commands([
                MigrateCommentsCommand::class,
            ]);
        }

        $this->publishes([
            __DIR__.'/../config/comments.php' => config_path('comments.php'),
        ], 'comments-config');
    }

    private function registerCommentsUtility(): void
    {
        Utility::extend(function (): void {
            Utility::register('comments_manager')
                ->title(__('Komentarze'))
                ->navTitle(__('Komentarze'))
                ->description(__('Moderacja komentarzy blogowych ze wszystkich języków.'))
                ->icon('mail-chat-bubble-text')
                ->inertia('comments::manager', fn () => app(CommentsUtilityController::class)->indexData())
                ->routes(function ($router): void {
                    $router->get('rows/{blogEntryId}', [CommentsUtilityController::class, 'rows'])->name('rows');
                    $router->post('comments/{commentId}/publish', [CommentsUtilityController::class, 'publish'])->name('publish');
                    $router->post('comments/{commentId}/unpublish', [CommentsUtilityController::class, 'unpublish'])->name('unpublish');
                    $router->delete('comments/{commentId}', [CommentsUtilityController::class, 'destroy'])->name('destroy');
                });
        });
    }
}
