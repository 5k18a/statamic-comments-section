<?php

namespace Skalisty\Comments\Console;

use Illuminate\Console\Command;
use Skalisty\Comments\Services\CommentEntryCreator;
use Statamic\Facades\Form;

class MigrateCommentsCommand extends Command
{
    protected $signature = 'comments:migrate {--dry-run : Show what would be migrated without writing entries} {--publish : Publish migrated comments instead of creating drafts}';

    protected $description = 'Migrate blog_comment form submissions into the comments entries collection.';

    public function handle(CommentEntryCreator $creator): int
    {
        $form = Form::find('blog_comment');

        if (! $form) {
            $this->error('Form blog_comment was not found.');

            return self::FAILURE;
        }

        $submissions = $form->querySubmissions()->get();
        $this->info('Found '.$submissions->count().' blog_comment submissions.');

        if ($this->option('dry-run')) {
            $submissions->each(function ($submission): void {
                $this->line('- '.$submission->id().' -> blog_id: '.(string) $submission->get('blog_id'));
            });

            return self::SUCCESS;
        }

        $created = 0;
        $skipped = 0;

        foreach ($submissions as $submission) {
            $entry = $creator->createFromSubmission($submission, (bool) $this->option('publish'));

            if ($entry) {
                $created++;

                continue;
            }

            $skipped++;
        }

        $this->info('Migrated or already present: '.$created);
        $this->info('Skipped invalid submissions: '.$skipped);

        return self::SUCCESS;
    }
}
