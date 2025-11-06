<?php

namespace Database\Seeders;

use App\Models\PaymentGateway;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PaymentGatewaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (PaymentGateway::query()->exists()) {
            $this->command->warn('Payment gateways already seeded. Skipping seeder.');

            return;
        }
        $now = now()->format('Y-m-d H:i:s');

        $gateways = [
            [
                'code' => 'paystack',
                'name' => 'Paystack',
                'description' => 'Paystack payment gateway for Nigerian merchants. Supports NGN, USD, GHS, ZAR.',
            ],
            [
                'code' => 'flutterwave',
                'name' => 'Flutterwave',
                'description' => 'Flutterwave gateway for Africa-wide multi-currency payments.',
            ],
        ];

        // Base gateway setup
        foreach ($gateways as $gateway) {
            PaymentGateway::updateOrCreate(
                ['code' => $gateway['code']],
                [
                    'id' => Str::uuid(),
                    'name' => $gateway['name'],
                    'description' => $gateway['description'],
                    'is_default' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        /**
         * --- PAYSTACK CONFIG SCHEMA ---
         */
        $paystackSchema = [
            'supported_currencies' => ['NGN', 'USD', 'GHS', 'ZAR'],
            'credentials' => [
                [
                    'key' => 'secret_key',
                    'label' => 'Secret Key',
                    'input' => 'password',
                    'hint' => 'Your Paystack secret key, e.g., sk_test_xxxxxxx.',
                    'required' => true,
                    'sensitive' => true,
                    'validation' => 'required|string',
                ],
                [
                    'key' => 'public_key',
                    'label' => 'Public Key',
                    'input' => 'text',
                    'hint' => 'Your Paystack public key, e.g., pk_test_xxxxxxx.',
                    'required' => true,
                    'sensitive' => false,
                    'validation' => 'required|string',
                ],
            ],
            'settings' => [
                [
                    'key' => 'merchant_email',
                    'label' => 'Merchant Email',
                    'input' => 'text',
                    'hint' => 'Email associated with your Paystack account.',
                    'required' => false,
                    'sensitive' => false,
                    'validation' => 'nullable|email',
                ],
                [
                    'key' => 'webhook_secret',
                    'label' => 'Webhook Secret',
                    'input' => 'password',
                    'hint' => 'Secret used to verify Paystack webhook signatures.',
                    'required' => false,
                    'sensitive' => true,
                    'validation' => 'nullable|string',
                ],
            ],
        ];

        $this->attachGatewaySchema('paystack', $paystackSchema);

        /**
         * --- FLUTTERWAVE CONFIG SCHEMA ---
         */
        $flutterwaveSchema = [
            'supported_currencies' => [
                'NGN', 'USD', 'GHS', 'KES', 'TZS', 'UGX', 'ZMW', 'ZAR', 'GBP', 'EUR',
            ],
            'credentials' => [
                [
                    'key' => 'secret_key',
                    'label' => 'Secret Key',
                    'input' => 'password',
                    'hint' => 'Your Flutterwave secret key, e.g., FLWSECK-xxxxxx.',
                    'required' => true,
                    'sensitive' => true,
                    'validation' => 'required|string',
                ],
                [
                    'key' => 'public_key',
                    'label' => 'Public Key',
                    'input' => 'text',
                    'hint' => 'Your Flutterwave public key, e.g., FLWPUBK-xxxxxx.',
                    'required' => true,
                    'sensitive' => false,
                    'validation' => 'required|string',
                ],
                [
                    'key' => 'encryption_key',
                    'label' => 'Encryption Key',
                    'input' => 'password',
                    'hint' => 'Your Flutterwave encryption key used for secure requests.',
                    'required' => true,
                    'sensitive' => true,
                    'validation' => 'required|string',
                ],
                [
                    'key' => 'secret_hash',
                    'label' => 'Webhook Secret Hash',
                    'input' => 'password',
                    'hint' => 'Secret used to verify Flutterwave webhook payloads.',
                    'required' => true,
                    'sensitive' => true,
                    'validation' => 'required|string',
                ],
            ],
            'settings' => [
                [
                    'key' => 'merchant_email',
                    'label' => 'Merchant Email',
                    'input' => 'text',
                    'hint' => 'Email associated with your Flutterwave merchant account.',
                    'required' => false,
                    'sensitive' => false,
                    'validation' => 'nullable|email',
                ],

            ],
        ];

        $this->attachGatewaySchema('flutterwave', $flutterwaveSchema);
    }

    /**
     * Attach schema to a specific gateway.
     */
    protected function attachGatewaySchema(string $code, array $schema): void
    {
        $gateway = PaymentGateway::where('code', $code)->first();

        if (! $gateway) {
            return;
        }

        $gateway->supported_currencies = $schema['supported_currencies'];
        $gateway->credential_schema = ['fields' => $schema['credentials']];
        $gateway->setting_schema = ['fields' => $schema['settings']];
        $gateway->save();
    }
}
