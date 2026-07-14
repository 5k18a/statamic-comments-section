<?php

namespace Skalisty\Comments;

use Illuminate\Support\Facades\Event;
use Skalisty\Comments\Console\MigrateCommentsCommand;
use Skalisty\Comments\Listeners\CreateCommentFromSubmission;
use Skalisty\Comments\Listeners\InvalidateBlogCacheOnComment;
use Skalisty\Comments\Modifiers\AvatarColor;
use Skalisty\Comments\Modifiers\AvatarInitial;
use Skalisty\Comments\Tags\CommentsTag;
use Statamic\Events\EntryDeleted;
use Statamic\Events\EntryDeleting;
use Statamic\Events\EntrySaved;
use Statamic\Events\SubmissionCreated;
use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
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

        if ($this->app->runningInConsole()) {
            $this->commands([
                MigrateCommentsCommand::class,
            ]);
        }

        $this->publishes([
            __DIR__.'/../config/comments.php' => config_path('comments.php'),
        ], 'comments-config');
    }
}
