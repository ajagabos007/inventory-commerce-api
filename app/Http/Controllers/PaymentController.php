<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentException;
use App\Handlers\PaymentHandler;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PaymentController extends Controller
{
    /**
     * Create the controller instance.
     */
    public function __construct()
    {
        $this->authorizeResource(Payment::class, 'payment');
    }

    /**
     * Display a listing of the resource.
     *
     * @method GET|HEAD /api/payments
     */
    public function index()
    {
        $payments = QueryBuilder::for(Payment::class)
            ->defaultSort('-created_at')
            ->allowedSorts(
                'barcode',
                'created_at',
                'updated_at',
            )
            ->allowedIncludes([
                'payables.payable',
                'gateway',
                'user',
            ])
            ->allowedFilters([
                'user_id',
                'status',
                'payment_gateway_id',
                AllowedFilter::scope('is_paid', 'isPaid'),
                AllowedFilter::scope('is_verified', 'isVerified'),

                // Date filters
                AllowedFilter::scope('period', 'period'),
                AllowedFilter::scope('last_days', 'lastDays'),
            ])
            // perform search if 'q' is present in request
            ->when(request()->filled('q'), function ($query) {
                $query->search(request()->q);
            })
           // paginate based on 'paginate' parameter
            ->when(! in_array(request()->paginate, [false, 'false', 0, '0', 'no'], true), function ($query) {
                $perPage = ! is_numeric(request()->per_page) ? 15 : max(intval(request()->per_page), 1);

                return $query->paginate($perPage)->appends(request()->query());
            }, function ($query) {
                return $query->get();
            });

        return PaymentResource::collection($payments)->additional([
            'status' => 'success',
            'message' => 'Payments retrieved successfully',
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePaymentRequest $request): \Illuminate\Http\JsonResponse|PaymentResource
    {
        return new PaymentResource(null);
    }

    /**
     * Display the specified resource.
     */
    public function show(Payment $payment): PaymentResource
    {
        $payment->loadFromRequest();

        return (new PaymentResource($payment))->additional([
            'message' => 'Payment retrieved successfully',
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePaymentRequest $request, Payment $payment): PaymentResource
    {
        $validated = $request->validated();

        $payment->update($validated);

        return (new PaymentResource($payment))->additional([
            'message' => 'Payment updated successfully',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Payment $payment): PaymentResource
    {
        $payment->delete();

        return (new PaymentResource(null))->additional([
            'message' => 'Payment deleted successfully',
        ]);
    }

    public function initialize(Request $request)
    {
        $validated = $request->validate([
            'payment_gateway_id' => 'required|exists:payment_gateways,id',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|size:3',
            'description' => 'nullable|string',
            'callback_url' => 'nullable|url',
            'cancel_url' => 'nullable|url',
        ]);

        DB::beginTransaction();
        try {
            $payment = Payment::create([
                'user_id' => auth()->id(),
                'payment_gateway_id' => $validated['payment_gateway_id'],
                'amount' => $validated['amount'],
                'currency' => $validated['currency'] ?? 'NGN',
                'description' => $validated['description'] ?? null,
                'callback_url' => $validated['callback_url'] ?? null,
                'cancel_url' => $validated['cancel_url'] ?? null,
                'ip_address' => $request->ip(),
                'status' => 'initiated',
            ]);

            $handler = new PaymentHandler($payment);
            $result = $handler->initializePayment();

            DB::commit();

            return response()->json($result);

        } catch (PaymentException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment initialization error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while initializing payment',
            ], 500);
        }
    }

    public function reinitialize(Payment $payment)
    {

        DB::beginTransaction();
        try {

            $handler = new PaymentHandler($payment);
            $result = $handler->initializePayment();

            DB::commit();

            return response()->json($result);

        } catch (PaymentException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $e->getStatusCode());
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment reinitialization error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while reinitializing payment',
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function verify(Payment $payment): PaymentResource
    {
        $handler = new PaymentHandler($payment);
        $response = $handler->verifyPayment();

        return new PaymentResource($response);
    }

    /**
     * Handle callback from payment gateway
     */
    public function callback(Request $request, string $gateway)
    {

        $callbackData = $request->query();
        $headers = $request->headers->all();

        try {

            $reference = $this->extractReference($callbackData, $gateway);

            $payment = Payment::where('transaction_reference', $reference)
                ->orWhere('gateway_reference', $reference)
                ->firstOrFail();

            $gateway = $payment->gateway?->code ?? $gateway;

            $handler = new PaymentHandler($payment);

            // Verify and update payment
            $result = $handler->verifyFromCallback($callbackData);

            Log::info('callback processed successfully', [
                'gateway' => $gateway,
                'payment_id' => $payment->id,
                'status' => $payment->status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Callback processed successful',
            ], 200);

        } catch (\Exception $e) {
            Log::error('Callback processing error', [
                'gateway' => $gateway,
                'error' => $e->getMessage(),
                'query' => $callbackData,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Callback processing failed',
            ], 500);
        }
    }

    /**
     * Handle webhook from payment gateway
     */
    public function webhook(Request $request, string $gateway)
    {
        $payload = $request->all();
        $headers = $request->headers->all();

        try {
            // Find payment from webhook data
            $reference = $this->extractReference($payload, $gateway);

            $payment = Payment::where('transaction_reference', $reference)
                ->orWhere('gateway_reference', $reference)
                ->firstOrFail();

            $handler = new PaymentHandler($payment);

            // Validate webhook signature
            if (! $handler->getGateway()->validateWebhookSignature($headers, json_encode($payload))) {
                Log::warning('Invalid webhook signature', [
                    'gateway' => $gateway,
                    'payment_id' => $payment->id,
                ]);

                return response()->json(['message' => 'Invalid signature'], 401);
            }

            // Verify and update payment
            $result = $handler->verifyFromWebhook($payload);

            Log::info('Webhook processed successfully', [
                'gateway' => $gateway,
                'payment_id' => $payment->id,
                'status' => $payment->status,
                'payload' => $payload,
            ]);

            return response(200);

        } catch (\Exception $e) {
            Log::error('Webhook processing error', [
                'gateway' => $gateway,
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return response()->json(['message' => 'Webhook processing failed'], 500);
        }
    }

    /**
     * Extract reference from webhook data based on gateway
     */
    private function extractReference(array $data, string $gateway): ?string
    {
        return match (strtolower($gateway)) {
            'paystack' => $data['data']['reference'] ?? $data['reference'] ?? $data['trxref'] ?? null,
            'flutterwave' => $data['data']['tx_ref'] ?? $data['tx_ref'] ?? null,
            default => $data['data']['reference']
                ?? $data['data']['tx_ref']
                ?? $data['tx_ref']
                ?? $data['trxref']
                ?? $data['reference']
                ?? null ,
        };
    }

    /**
     * Get payment status (for React polling)
     */
    public function status(Payment $payment)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $payment->id,
                'status' => $payment->status,
                'transaction_status' => $payment->transaction_status,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'is_paid' => $payment->is_paid,
                'paid_at' => $payment->paid_at,
            ],
        ]);
    }

    /**
     * Get payment analytics/statistics
     */
    public function analytics()
    {
        $totalRevenue = Payment::successful()->sum('amount');
        $totalTransactions = Payment::successful()->count();
        $averageAmount = Payment::successful()->avg('amount');
        $todayRevenue = Payment::successful()->today()->sum('amount');
        $monthRevenue = Payment::successful()->thisMonth()->sum('amount');

        $statusCounts = Payment::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status');

        $revenueByGateway = Payment::revenueByGateway()->get();
        $dailyRevenue = Payment::dailyRevenue(30)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'overview' => [
                    'total_revenue' => $totalRevenue,
                    'total_transactions' => $totalTransactions,
                    'average_amount' => $averageAmount,
                    'today_revenue' => $todayRevenue,
                    'month_revenue' => $monthRevenue,
                ],
                'status_breakdown' => $statusCounts,
                'revenue_by_gateway' => $revenueByGateway,
                'daily_revenue' => $dailyRevenue,
            ],
        ]);
    }
}
