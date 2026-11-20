<?php

namespace App\Console\Commands;

use App\Livewire\Profile\ImportHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Ruimt CSV-bestanden op van imports die nooit zijn afgemaakt (bestand gekozen, maar niet geïmporteerd of geannuleerd).
 * Een import die nog bezig is, is jonger dan HOURS uur en blijft staan.
 */
class PruneImportFiles extends Command
{
    public const HOURS = 24;

    protected $signature = 'fitness:prune-imports';

    protected $description = 'Verwijder geüploade importbestanden ouder dan '.self::HOURS.' uur';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $before = now()->subHours(self::HOURS)->getTimestamp();
        $deleted = 0;

        foreach ($disk->files(ImportHistory::UPLOAD_DIR) as $file) {
            if ($disk->lastModified($file) < $before && $disk->delete($file)) {
                $deleted++;
            }
        }

        $this->info($deleted === 1 ? '1 importbestand verwijderd.' : "{$deleted} importbestanden verwijderd.");

        return self::SUCCESS;
    }
}
