<?php

namespace App\Console\Commands;

use App\Models\CheckoutSession;
use App\Models\WishList;
use Hnooz\LaravelCart\Models\CartItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClearGuestSession extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'guest:clear-sessions
                            {--days=30 : Number of days after which guest sessions should be cleared}
                            {--dry-run : Preview what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear guest checkout sessions, wishlists, and cart items older than specified days';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $cutoffDate = now()->subDays($days);

        $this->info("🧹 Clearing guest sessions older than {$days} days (before {$cutoffDate->toDateTimeString()})");

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No data will be deleted');
        }

        $this->newLine();

        try {
            DB::beginTransaction();

            // Clear Checkout Sessions
            $checkoutCount = $this->clearModel(
                CheckoutSession::class,
                'Checkout Sessions',
                $cutoffDate,
                $dryRun
            );

            // Clear WishLists
            $wishlistCount = $this->clearModel(
                WishList::class,
                'WishLists',
                $cutoffDate,
                $dryRun
            );

            // Clear Cart Items
            $cartCount = $this->clearModel(
                CartItem::class,
                'Cart Items',
                $cutoffDate,
                $dryRun
            );

            if (!$dryRun) {
                DB::commit();
            }

            $this->newLine();
            $this->info('✅ Summary:');
            $this->table(
                ['Type', 'Records Deleted'],
                [
                    ['Checkout Sessions', $checkoutCount],
                    ['WishLists', $wishlistCount],
                    ['Cart Items', $cartCount],
                    ['Total', $checkoutCount + $wishlistCount + $cartCount],
                ]
            );

            if ($dryRun) {
                $this->warn('⚠️  This was a dry run. No data was actually deleted.');
                $this->info('Run without --dry-run to perform actual deletion.');
            } else {
                $this->info('🎉 Guest sessions cleared successfully!');
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();

            $this->error('❌ Error clearing guest sessions: ' . $e->getMessage());

            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    /**
     * Clear guest records for a specific model.
     *
     * @param string $modelClass
     * @param string $label
     * @param \Carbon\Carbon $cutoffDate
     * @param bool $dryRun
     * @return int
     */
    protected function clearModel(string $modelClass, string $label, $cutoffDate, bool $dryRun): int
    {
        $query = $modelClass::query()
            ->whereNull('user_id')
            ->where('created_at', '<', $cutoffDate);

        $count = $query->count();

        if ($count > 0) {
            $this->line("📋 Found {$count} {$label} to clear");

            if (!$dryRun) {
                $deleted = $query->delete();
                $this->info("   ✓ Deleted {$deleted} {$label}");
            } else {
                $this->comment("   ⚠ Would delete {$count} {$label}");
            }
        } else {
            $this->comment("   ℹ No {$label} found to clear");
        }

        return $count;
    }
}
