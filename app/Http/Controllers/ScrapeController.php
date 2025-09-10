<?php

namespace App\Http\Controllers;

use App\Enums\Type;
use App\Http\Requests\AddScrapeToInventoryRequest;
use App\Http\Requests\StoreScrapeRequest;
use App\Http\Requests\UpdateScrapeRequest;
use App\Http\Resources\ScrapeResource;
use App\Models\Customer;
use App\Models\Scrape;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Spatie\QueryBuilder\QueryBuilder;

class ScrapeController extends Controller
{
    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this->authorizeResource(Scrape::class, 'scrape');
    }

    /**
     * Display a listing of the resource.
     *
     * @method GET|HEAD /api/scrapes
     */
    public function index()
    {
        $paginate = request()->has('paginate') ? request()->paginate : true;
        $perPage = request()->has('per_page') ? request()->per_page : 15;

        $scrapeQ = Scrape::when(! auth()->user()?->is_admin, function ($query) {
            $query->whereHas('inventory.item', function ($query) {
                $query->where('store_id', auth()->user()?->staff->store_id);
            });
        });

        $scrapes = QueryBuilder::for($scrapeQ)
            ->defaultSort('-created_at')
            ->allowedSorts(
                'quantity',
                'created_at',
                'updated_at',
            )
            ->allowedFilters([
                'inventory_id',
                'customer_id',
                'staff_id',
                'inventory.item.id',
                'inventory.item.type_id',
                'inventory.item.colour_id',
                'inventory.item.category_id',
                'inventory.item.material',
            ])
            ->allowedIncludes([
                'staff.user',
                'inventory.item',
                'inventory.item.category',
                'inventory.item.colour',
                'inventory.item.type',
                'inventory.store',
                'customer.user',
            ]);

        if (request()->has('q')) {
            $searchTerm = '%'.request()->q.'%';

            $scrapes->where(function ($query) use ($searchTerm) {
                $model = $query->getModel();
                $table = $model->getTable();

                $cacheKey = "{$table}_column_listing";
                $columns = Cache::rememberForever($cacheKey, function () use ($table) {
                    return Schema::getColumnListing($table);
                });

                foreach ($columns as $index => $column) {
                    $command = $index == 0 ? 'where' : 'orWhere';
                    $query->{$command}($column, 'like', $searchTerm);
                }
            })
                ->orWhereHas('inventory.item', function ($query) use ($searchTerm) {
                    $model = $query->getModel();
                    $table = $model->getTable();

                    $cacheKey = "{$table}_column_listing";
                    $columns = Cache::rememberForever($cacheKey, function () use ($table) {
                        return Schema::getColumnListing($table);
                    });

                    foreach ($columns as $index => $column) {
                        $command = $index == 0 ? 'where' : 'orWhere';
                        $query->{$command}($column, 'like', $searchTerm);
                    }
                });
        }

        /**
         * Check if pagination is not disabled
         */
        if (! in_array($paginate, [false, 'false', 0, '0'], true)) {
            /**
             * Ensure per_page is integer and >= 1
             */
            if (! is_numeric($perPage)) {
                $perPage = 15;
            } else {
                $perPage = intval($perPage);
                $perPage = $perPage >= 1 ? $perPage : 15;
            }

            $scrapes = $scrapes->paginate($perPage)
                ->appends(request()->query());

        } else {
            $scrapes = $scrapes->get();
        }

        $scrapes_collection = ScrapeResource::collection($scrapes)->additional([
            'status' => 'success',
            'message' => 'Scrapes retrieved successfully',
        ]);

        return $scrapes_collection;
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreScrapeRequest $request)
    {
        $validated = $request->validated();
        $validated['quantity'] = max(1, $validated['quantity'] ?? 1);

        DB::beginTransaction();

        try {

            $customer = data_get($validated, 'customer', []);

            $scrapeQ = Scrape::where('inventory_id', $validated['inventory_id'])
                ->where('type', $validated['type']);

            if ($validated['type'] == Type::RETURNED->value) {
                $the_customer = Customer::find($customer['id'] ?? null);
                if (! $the_customer) {
                    $the_customer = Customer::firstOrCreate(
                        [
                            'email' => $customer['email'] ?? null,
                            'phone_number' => $customer['phone_number'] ?? null,
                        ],
                        [
                            'name' => $customer['name'] ?? null,
                        ]
                    );
                }
                $validated['customer_id'] = $the_customer->id;
                $scrape = $scrapeQ->clone()
                    ->where('customer_id', $validated['customer_id'])
                    ->first();
                if (is_null($scrape)) {
                    $scrape = Scrape::create($validated);
                } else {
                    $scrape->quantity += $validated['quantity'];
                    $scrape->comment = $validated['comment'] ?? $scrape->comment;
                    $scrape->save();
                }
            } else {
                $scrape = $scrapeQ->clone()->first();

                if (is_null($scrape)) {
                    $scrape = Scrape::create($validated);
                } else {
                    $scrape->quantity += $validated['quantity'];
                    $scrape->comment = $validated['comment'] ?? $scrape->comment;
                    $scrape->save();
                }

                $scrape->inventory->decrementQuantity($validated['quantity']);
            }

            DB::commit();

            $scrape->load([
                'staff.user',
                'inventory.item.category',
                'inventory.item.colour',
                'inventory.item.type',
                'customer.user',
            ]);

            $scrape_resource = (new ScrapeResource($scrape))->additional([
                'message' => 'Scrape created successfully',
            ]);

            return $scrape_resource;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);

            return response()->json([
                'message' => 'Failed to create scrape.',
                'errors' => ['create_scrape' => $e->getMessage()],
            ], 500);
        }

    }

    /**
     * Display the specified resource.
     */
    public function show(Scrape $scrape)
    {
        $scrape->loadFromRequest();

        $scrape_resource = (new ScrapeResource($scrape))->additional([
            'message' => 'Scrape retrieved successfully',
        ]);

        return $scrape_resource;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateScrapeRequest $request, Scrape $scrape)
    {
        $validated = $request->validated();
        $scrape->update($validated);

        $scrape->loadFromRequest();

        $scrape_resource = (new ScrapeResource($scrape))->additional([
            'message' => 'Scrape updated successfully',
        ]);

        return $scrape_resource;

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Scrape $scrape)
    {
        $scrape->delete();

        $scrape_resource = (new ScrapeResource(null))->additional([
            'message' => 'Scrape deleted successfully',
        ]);

        return $scrape_resource;
    }

    /**
     * Add scrape to inventory
     */
    public function addToInventory(Scrape $scrape, AddScrapeToInventoryRequest $request)
    {
        $validated = $request->validated();

        $scrape->load('inventory');

        if (! $scrape->inventory) {
            return response()->json([
                'message' => 'Scrape does not have an inventory to add to.',
                'errors' => ['scrape_inventory' => 'Scrape does not have an inventory to add to.'],
            ], 400);
        }

        $quantity = $validated['quantity'] ?? $scrape->quantity;

        $quantity = min($scrape->inventory->quantity, intval($quantity));

        $scrape->inventory->quantity += $quantity;
        $scrape->inventory->save();

        if ($scrape->quantity > $quantity) {
            $scrape->quantity -= $quantity;
            $scrape->save();
        } else {
            $scrape->delete();
            $scrape = null;
        }

        $scrape_resource = (new ScrapeResource($scrape))->additional([
            'message' => 'Scrape added to inventory successfully',
        ]);

        return $scrape_resource;

    }
}
