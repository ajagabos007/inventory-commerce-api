<?php

namespace App\Console\Commands;

use App\Models\ProductVariant;
use Cviebrock\EloquentSluggable\Services\SlugService;
use Illuminate\Console\Command;

class SlugifyProductVariant extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:slugify-product-variant
                            {--chunk=500 : Number of records to process per chunk}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate slugs for all product variants based on their name.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $chunkSize = (int) $this->option('chunk');
        $count = ProductVariant::count();

        if ($count === 0) {
            $this->info('No product variants found.');

            return 0;
        }

        $this->info("Slugifying {$count} product variants...");
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        ProductVariant::whereNull('slug')
            ->orWhere('slug', '')
            ->chunkById($chunkSize, function ($variants) use ($bar) {
                foreach ($variants as $variant) {
                    // Let Eloquent Sluggable handle slug creation
                    $variant->slug = SlugService::createSlug(ProductVariant::class, 'slug', $variant->name);
                    $variant->saveQuietly();

                    $bar->advance();
                }
            });

        $bar->finish();
        $this->newLine(2);
        $this->info('✅ Slug generation complete.');

        return 0;
    }
}
