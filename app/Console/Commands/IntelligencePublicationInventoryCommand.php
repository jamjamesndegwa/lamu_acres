<?php

namespace App\Console\Commands;

use App\Services\Intelligence\PublicationInventory;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('intelligence:inventory')]
#[Description('Summarize intelligence publication counts and completeness for the configured database')]
class IntelligencePublicationInventoryCommand extends Command
{
    public function handle(PublicationInventory $inventory): int
    {
        $snapshot = $inventory->snapshot();

        $this->components->info('Environment: '.$snapshot['environment']);
        $this->line('Generated: '.$snapshot['generated_at']);
        $this->newLine();

        foreach ($snapshot['categories'] as $category) {
            $this->components->twoColumnDetail($category['label'], sprintf(
                'draft %d · review %d · released %d · withdrawn %d · last published %s',
                $category['counts']['draft'],
                $category['counts']['review'],
                $category['counts']['released'],
                $category['counts']['withdrawn'],
                $category['last_published_at'] ?? 'never',
            ));

            foreach ($category['completeness'] as $metric => $values) {
                if ($values['total'] === 0) {
                    $this->line('  · '.$metric.': n/a (no released records)');

                    continue;
                }

                $this->line(sprintf(
                    '  · %s: %d/%d (%d%%)',
                    $metric,
                    $values['complete'],
                    $values['total'],
                    $values['percent'],
                ));
            }

            $this->newLine();
        }

        return self::SUCCESS;
    }
}
