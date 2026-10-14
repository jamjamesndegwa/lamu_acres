<?php

namespace App\Console\Commands;

use App\Services\Research\CommitmentDesk;
use Illuminate\Console\Command;

class SyncResearchCommitments extends Command
{
    protected $signature = 'research:commitments';

    protected $description = 'Open the publication calendar and mark due, overdue, or unavailable research';

    public function handle(CommitmentDesk $desk): int
    {
        $desk->sync();
        $this->info('Publication calendar updated.');

        return self::SUCCESS;
    }
}
