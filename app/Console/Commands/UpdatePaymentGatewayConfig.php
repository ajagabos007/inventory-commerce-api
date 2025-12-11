<?php

namespace App\Console\Commands;

use App\Models\PaymentGateway;
use App\Models\PaymentGatewayConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class UpdatePaymentGatewayConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:config 
                            {gateway : The code of the payment gateway (e.g., paystack, flutterwave)} 
                            {--mode=test : The mode to update (test or live)} 
                            {--use-env : Read configuration from environment variables} 
                            {data?* : Key=value pairs to update specific settings}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update payment gateway configuration credentials and settings';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $gatewayCode = $this->argument('gateway');
        $mode = $this->option('mode');
        $useEnv = $this->option('use-env');
        $dataArgs = $this->argument('data');

        $gateway = PaymentGateway::where('code', $gatewayCode)->first();

        if (! $gateway) {
            $this->error("Payment gateway '{$gatewayCode}' not found.");
            return 1;
        }

        $this->info("Updating configuration for {$gateway->name} ({$mode})...");

        // Find or create config
        $config = PaymentGatewayConfig::firstOrNew([
            'payment_gateway_id' => $gateway->id,
            'mode' => $mode,
        ]);

        // Get existing values (decrypted)
        $currentCredentials = $config->exists ? $config->decrypted_credentials : [];
        $currentSettings = $config->exists ? $config->decrypted_settings : [];

        // Prepare new values
        $newCredentials = [];
        $newSettings = [];

        // 1. Load from ENV if requested
        if ($useEnv) {
            $envValues = $this->getEnvValues($gatewayCode, $mode);
            $this->info('Loaded values from environment variables.');
            
            // Separate env values into credentials and settings based on schema
            [$envCreds, $envSettings] = $this->categorizeValues($gateway, $envValues);
            $newCredentials = array_merge($newCredentials, $envCreds);
            $newSettings = array_merge($newSettings, $envSettings);
        }

        // 2. Load from arguments (overrides ENV)
        if (! empty($dataArgs)) {
            $argValues = $this->parseDataArguments($dataArgs);
            
             // Separate arg values into credentials and settings based on schema
            [$argCreds, $argSettings] = $this->categorizeValues($gateway, $argValues);
            $newCredentials = array_merge($newCredentials, $argCreds);
            $newSettings = array_merge($newSettings, $argSettings);
        }

        // 3. Merge with existing values (only update what's provided)
        // We merge new values ON TOP of existing ones. 
        // If a key is NOT in new values, the existing one remains.
        $finalCredentials = array_merge($currentCredentials, $newCredentials);
        $finalSettings = array_merge($currentSettings, $newSettings);

        // 4. Interactive Prompts
        $this->info('Please provide values for the following configuration (press Enter to keep current/default):');
        
        $finalCredentials = $this->promptForValues(
            'Credential', 
            $gateway->credential_schema['fields'] ?? [], 
            $finalCredentials
        );

        $finalSettings = $this->promptForValues(
            'Setting', 
            $gateway->setting_schema['fields'] ?? [], 
            $finalSettings
        );

        // Save
        $config->credentials = $finalCredentials;
        $config->settings = $finalSettings;
        $config->save();

        $this->info('Configuration updated successfully.');
        
        // Display what was updated (masking sensitive data)
        $this->table(
            ['Type', 'Key', 'Value'],
            array_merge(
                $this->formatForTable('Credential', $finalCredentials, $gateway->credential_schema['fields'] ?? []),
                $this->formatForTable('Setting', $finalSettings, $gateway->setting_schema['fields'] ?? [])
            )
        );

        return 0;
    }

    /**
     * Parse key=value arguments.
     */
    protected function parseDataArguments(array $args): array
    {
        $data = [];
        foreach ($args as $arg) {
            if (str_contains($arg, '=')) {
                [$key, $value] = explode('=', $arg, 2);
                $data[$key] = $value;
            }
        }
        return $data;
    }

    /**
     * Get environment values based on gateway code.
     */
    protected function getEnvValues(string $gatewayCode, string $mode): array
    {
        // Define mappings: gateway_code => [ config_key => env_var_pattern ]
        // We can use placeholders like {MODE} which will be replaced by uppercase mode (SANDBOX/LIVE)
        // Or we can just try standard patterns.
        
        $values = [];
        $prefix = strtoupper($gatewayCode); // e.g., PAYSTACK, FLUTTERWAVE
        
        // Common keys mapping
        // This is a heuristic mapping. We might need to adjust based on actual .env usage.
        // Based on .env.example:
        // FLUTTERWAVE_PUBLIC_KEY='...'
        // FLUTTERWAVE_SECRET_KEY='...'
        // FLUTTERWAVE_ENCRYPTION_KEY='...'
        
        // We will look for keys defined in the gateway schema and try to find matching ENV vars.
        
        // However, standard naming often doesn't include MODE for some single-mode setups, 
        // but for a robust system, we might expect PAYSTACK_SECRET_KEY or PAYSTACK_SANDBOX_SECRET_KEY.
        // Let's assume a simple mapping for now as per user request "check paymentgatew seeeder to understad the values".
        
        // Let's try to map specific known keys.
        
        if ($gatewayCode === 'paystack') {
             $values['secret_key'] = env('PAYSTACK_SECRET_KEY');
             $values['public_key'] = env('PAYSTACK_PUBLIC_KEY');
             $values['merchant_email'] = env('PAYSTACK_MERCHANT_EMAIL');
             $values['webhook_secret'] = env('PAYSTACK_WEBHOOK_SECRET'); // Often shared
        } elseif ($gatewayCode === 'flutterwave') {
             $values['public_key'] = env('FLUTTERWAVE_PUBLIC_KEY');
             $values['secret_key'] = env('FLUTTERWAVE_SECRET_KEY');
             $values['encryption_key'] = env('FLUTTERWAVE_ENCRYPTION_KEY');
             $values['secret_hash'] = env('FLUTTERWAVE_SECRET_HASH');
             $values['merchant_email'] = env('FLUTTERWAVE_MERCHANT_EMAIL');
        }

        // Filter out nulls
        return array_filter($values, fn($v) => !is_null($v));
    }

    /**
     * Categorize values into credentials and settings based on gateway schema.
     */
    protected function categorizeValues(PaymentGateway $gateway, array $values): array
    {
        $credentials = [];
        $settings = [];

        $credentialKeys = collect($gateway->credential_schema['fields'] ?? [])->pluck('key')->toArray();
        $settingKeys = collect($gateway->setting_schema['fields'] ?? [])->pluck('key')->toArray();

        foreach ($values as $key => $value) {
            if (in_array($key, $credentialKeys)) {
                $credentials[$key] = $value;
            } elseif (in_array($key, $settingKeys)) {
                $settings[$key] = $value;
            } else {
                // If key is not found in either, we might warn or just ignore. 
                // For now, let's ignore or maybe add to settings if it looks like a setting?
                // Safer to ignore to avoid junk data.
                $this->warn("Key '{$key}' not found in gateway schema. Skipping.");
            }
        }

        return [$credentials, $settings];
    }

    /**
     * Prompt user for values based on schema.
     */
    protected function promptForValues(string $type, array $schema, array $currentValues): array
    {
        foreach ($schema as $field) {
            $key = $field['key'];
            $label = $field['label'] ?? $key;
            $isSensitive = $field['sensitive'] ?? false;
            $currentValue = $currentValues[$key] ?? null;
            
            // Prepare question
            $question = "{$type}: {$label} ({$key})";
            
            if ($isSensitive) {
                // For sensitive fields, we can't easily show the default in the prompt text 
                // without revealing it. So we indicate if a value is set.
                $defaultText = $currentValue ? '********' : 'null';
                $question .= " [Current: {$defaultText}]";
                
                $value = $this->secret($question);
                
                // If user pressed enter (null), keep current value
                if ($value === null) {
                    $value = $currentValue;
                }
            } else {
                $value = $this->ask($question, $currentValue);
            }

            // Update value if provided (or kept default)
            // Note: secret() returns null if empty, ask() returns default if empty.
            // We need to ensure we don't overwrite with null if we want to keep current.
            // Actually, for secret(), if user enters nothing, it returns null. 
            // If we want to keep existing, we handle it above.
            
            if ($value !== null) {
                $currentValues[$key] = $value;
            }
        }
        return $currentValues;
    }

    /**
     * Format data for table display.
     */
    protected function formatForTable(string $type, array $data, array $schema): array
    {
        $rows = [];
        foreach ($data as $key => $value) {
            $fieldDef = collect($schema)->firstWhere('key', $key);
            $isSensitive = $fieldDef['sensitive'] ?? false;
            
            $displayValue = $isSensitive ? '********' : $value;
            $rows[] = [$type, $key, $displayValue];
        }
        return $rows;
    }
}
