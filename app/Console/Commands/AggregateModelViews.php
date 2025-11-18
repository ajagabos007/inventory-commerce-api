<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ModelView;
use App\Models\ModelViewStat;

class AggregateModelViews extends Command
{
    protected $signature = 'analytics:aggregate-model-views';

    public function handle()
    {
        $views = ModelView::selectRaw('viewable_type, viewable_id, DATE(viewed_at) as date, COUNT(*) as views, COUNT(DISTINCT ip_address, session_id, user_id) as unique_views')
            ->groupBy('viewable_type', 'viewable_id', 'date')
            ->get();

        foreach ($views as $row) {
            ModelViewStat::updateOrCreate(
                [
                    'viewable_type' => $row->viewable_type,
                    'viewable_id'   => $row->viewable_id,
                    'date'          => $row->date,
                ],
                [
                    'views'        => $row->views,
                    'unique_views' => $row->unique_views,
                ]
            );
        }

        $this->info("Model view stats aggregated.");
    }
}
