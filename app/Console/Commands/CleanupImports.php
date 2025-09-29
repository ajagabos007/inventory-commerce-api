<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CleanupImports extends Command
{
    protected $signature = 'app:cleanup-imports
                            {--archive : Archive files instead of deleting them}
                            {--days=30 : Delete archives older than N days}
                            {--force : Skip confirmation prompt}
                            {--dry-run : Preview what would be deleted}';

    protected $description = 'Clean up imported files and optionally archive them';

    private string $importsPath;
    private string $archivePath;
    private array $deletedFiles = [];
    private array $archivedFiles = [];
    private int $totalSize = 0;

    public function handle(): int
    {
        $this->importsPath = storage_path('app/private/imports');
        $this->archivePath = storage_path('app/private/imports/archive');

        if (!File::exists($this->importsPath)) {
            $this->error('❌ Imports directory not found');
            return Command::FAILURE;
        }

        $dryRun = $this->option('dry-run');
        $archive = $this->option('archive');
        $days = (int) $this->option('days');
        $force = $this->option('force');

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No files will be modified');
            $this->newLine();
        }

        // Show what will be cleaned
        $this->displayCleanupPlan($archive, $days);

        // Confirm unless forced
        if (!$force && !$dryRun && !$this->confirm('Do you want to proceed?')) {
            $this->info('Cleanup cancelled');
            return Command::SUCCESS;
        }

        // Execute cleanup
        if ($archive) {
            $this->archiveImports($dryRun);
        } else {
            $this->deleteImports($dryRun);
        }

        // Clean old archives
        if (!$dryRun) {
            $this->cleanOldArchives($days);
        }

        // Display summary
        $this->displaySummary($dryRun);

        return Command::SUCCESS;
    }

    /**
     * Display cleanup plan
     */
    private function displayCleanupPlan(bool $archive, int $days): void
    {
        $this->info('📋 Cleanup Plan:');
        $this->line("   Directory: {$this->importsPath}");
        $this->line("   Action: " . ($archive ? 'Archive files' : 'Delete files'));

        if ($archive) {
            $this->line("   Archive location: {$this->archivePath}");
        }

        $this->line("   Old archives: Delete after {$days} days");
        $this->newLine();

        // Count files to be cleaned
        $files = $this->getImportFiles();
        $this->info("📊 Files to clean:");
        $this->line("   CSV files: " . count($files['csv']));
        $this->line("   Image files: " . count($files['images']));
        $this->line("   Total size: " . $this->formatBytes($this->calculateTotalSize($files)));
        $this->newLine();
    }

    /**
     * Get list of import files
     */
    private function getImportFiles(): array
    {
        $files = [
            'csv' => [],
            'images' => [],
        ];

        // Get CSV files
        $csvFiles = File::glob($this->importsPath . '/*.csv');
        foreach ($csvFiles as $file) {
            $files['csv'][] = $file;
        }

        // Get image files
        $imagesPath = $this->importsPath . '/images';
        if (File::exists($imagesPath)) {
            $imageFiles = File::allFiles($imagesPath);
            foreach ($imageFiles as $file) {
                $files['images'][] = $file->getPathname();
            }
        }

        return $files;
    }

    /**
     * Calculate total size of files
     */
    private function calculateTotalSize(array $files): int
    {
        $size = 0;

        foreach ($files['csv'] as $file) {
            if (File::exists($file)) {
                $size += File::size($file);
            }
        }

        foreach ($files['images'] as $file) {
            if (File::exists($file)) {
                $size += File::size($file);
            }
        }

        return $size;
    }

    /**
     * Archive import files
     */
    private function archiveImports(bool $dryRun): void
    {
        $timestamp = Carbon::now()->format('Y-m-d_His');
        $archiveDir = $this->archivePath . '/' . $timestamp;

        $this->info('📦 Archiving files...');

        if (!$dryRun) {
            File::makeDirectory($archiveDir, 0755, true);
            File::makeDirectory($archiveDir . '/images', 0755, true);
        }

        $files = $this->getImportFiles();

        // Archive CSV files
        foreach ($files['csv'] as $file) {
            $filename = basename($file);
            $destination = $archiveDir . '/' . $filename;

            if ($dryRun) {
                $this->line("   Would archive: {$filename}");
            } else {
                File::move($file, $destination);
                $this->line("   ✓ Archived: {$filename}");
            }

            $this->archivedFiles[] = $filename;
            $this->totalSize += File::exists($dryRun ? $file : $destination)
                ? File::size($dryRun ? $file : $destination)
                : 0;
        }

        // Archive images
        foreach ($files['images'] as $file) {
            $filename = basename($file);
            $destination = $archiveDir . '/images/' . $filename;

            if ($dryRun) {
                $this->line("   Would archive image: {$filename}");
            } else {
                File::move($file, $destination);
                $this->line("   ✓ Archived image: {$filename}");
            }

            $this->archivedFiles[] = $filename;
        }

        // Clean up empty images directory
        if (!$dryRun && File::exists($this->importsPath . '/images')) {
            $remaining = File::files($this->importsPath . '/images');
            if (empty($remaining)) {
                File::deleteDirectory($this->importsPath . '/images');
            }
        }
    }

    /**
     * Delete import files without archiving
     */
    private function deleteImports(bool $dryRun): void
    {
        $this->info('🗑️  Deleting files...');

        $files = $this->getImportFiles();

        // Delete CSV files
        foreach ($files['csv'] as $file) {
            $filename = basename($file);

            if ($dryRun) {
                $this->line("   Would delete: {$filename}");
            } else {
                $size = File::size($file);
                File::delete($file);
                $this->line("   ✓ Deleted: {$filename}");
                $this->totalSize += $size;
            }

            $this->deletedFiles[] = $filename;
        }

        // Delete images
        foreach ($files['images'] as $file) {
            $filename = basename($file);

            if ($dryRun) {
                $this->line("   Would delete image: {$filename}");
            } else {
                File::delete($file);
                $this->line("   ✓ Deleted image: {$filename}");
            }

            $this->deletedFiles[] = $filename;
        }

        // Delete images directory if empty
        if (!$dryRun && File::exists($this->importsPath . '/images')) {
            $remaining = File::files($this->importsPath . '/images');
            if (empty($remaining)) {
                File::deleteDirectory($this->importsPath . '/images');
                $this->line("   ✓ Removed empty images directory");
            }
        }
    }

    /**
     * Clean old archives
     */
    private function cleanOldArchives(int $days): void
    {
        if (!File::exists($this->archivePath)) {
            return;
        }

        $this->info("🧹 Cleaning archives older than {$days} days...");

        $cutoffDate = Carbon::now()->subDays($days);
        $archives = File::directories($this->archivePath);
        $deletedCount = 0;

        foreach ($archives as $archive) {
            $dirname = basename($archive);

            // Skip if not a date-based archive
            if (!preg_match('/^\d{4}-\d{2}-\d{2}_\d{6}$/', $dirname)) {
                continue;
            }

            try {
                $archiveDate = Carbon::createFromFormat('Y-m-d_His', $dirname);

                if ($archiveDate->lt($cutoffDate)) {
                    File::deleteDirectory($archive);
                    $this->line("   ✓ Deleted old archive: {$dirname}");
                    $deletedCount++;
                }
            } catch (\Exception $e) {
                $this->warn("   ⚠️  Could not parse date for: {$dirname}");
            }
        }

        if ($deletedCount === 0) {
            $this->line("   No old archives to delete");
        }
    }

    /**
     * Display cleanup summary
     */
    private function displaySummary(bool $dryRun): void
    {
        $this->newLine();
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📊 Cleanup Summary');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        if (!empty($this->archivedFiles)) {
            $this->info("📦 Archived: " . count($this->archivedFiles) . " files");
        }

        if (!empty($this->deletedFiles)) {
            $this->info("🗑️  Deleted: " . count($this->deletedFiles) . " files");
        }

        if ($this->totalSize > 0) {
            $this->info("💾 Space " . ($dryRun ? 'would be freed' : 'freed') . ": " . $this->formatBytes($this->totalSize));
        }

        if (empty($this->archivedFiles) && empty($this->deletedFiles)) {
            $this->info("✨ No files to clean up");
        }

        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }

    /**
     * Format bytes to human-readable
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;

        return number_format($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }
}
