<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Services\Tips\TipImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Loads tech tips from a JSON file (format: App\Services\Tips\TipImporter); pictures are downloaded / copied. */
#[Signature('tips:import {file : JSON file with the tips} {--replace : Delete all existing tips first} {--force : Do not ask}')]
#[Description('Import tech tips (with their pictures) from a JSON file')]
class ImportTips extends Command
{
    public function handle(TipImporter $importer): int
    {
        $file = $this->argument('file');
        if (! is_file($file)) {
            $this->error("File not found: $file");

            return self::FAILURE;
        }
        if ($this->option('replace') && ! $this->option('force') && ! $this->confirm('Delete ALL existing tech tips and their pictures first?')) {
            return self::FAILURE;
        }

        try {
            $count = $importer->importFile($file, (bool) $this->option('replace'), fn (string $line) => $this->line($line));
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        ActivityLog::record('tech_tip.imported', null, ['file' => basename($file), 'count' => $count, 'replace' => (bool) $this->option('replace')]);
        $this->info("Imported $count tips.");

        return self::SUCCESS;
    }
}
