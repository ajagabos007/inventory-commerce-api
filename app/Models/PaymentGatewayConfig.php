<?php

namespace App\Models;

use App\Observers\PaymentGatewayConfigObserver;
use App\Traits\ModelRequestLoader;
use Database\Factories\PaymentGatewayConfigFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

#[ObservedBy([PaymentGatewayConfigObserver::class])]
class PaymentGatewayConfig extends Model
{
    /** @use HasFactory<PaymentGatewayConfigFactory> */
    use HasFactory;

    use HasUuids;
    use ModelRequestLoader;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'payment_gateway_id',
        'mode',
        'settings',
        'credentials',
    ];

    /**
     * Name accessor for disabled state.
     */
    protected function isDisabled(): Attribute
    {
        return Attribute::make(
            get: fn () => filled($this->disabled_at)
        );
    }

    /**
     * Scope enabled
     */
    public function scopeEnabled($query, $enabled = true)
    {
        $enabled = filter_var($enabled, FILTER_VALIDATE_BOOLEAN);

        return $query->when($enabled, function ($query) {
            $query->whereNull('disabled_at');
        }, function ($query) {
            $query->whereNotNull('disabled_at');
        });
    }

    /**
     * Scope disabled
     */
    public function scopeDisabled($query, $disabled = true)
    {
        $disabled = filter_var($disabled, FILTER_VALIDATE_BOOLEAN);

        return $query->when($disabled, function ($query) {
            $query->whereNotNull('disabled_at');
        }, function ($query) {
            $query->whereNull('disabled_at');
        });
    }

    /**
     * Get the gateway.
     */
    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id');
    }

    /**
     * Attribute: credentials
     *
     * Encrypt sensitive credential fields when setting.
     * Do NOT auto-decrypt when getting (use decryptedCredentials()).
     */
    protected function credentials(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? json_decode($value, true) : [],
            set: fn (array $value) => json_encode($this->encryptSensitiveFields($value, $this->getSchemaForCredentials())),
        );
    }

    /**
     * Attribute: settings
     *
     * Encrypt sensitive setting fields when setting.
     * Do NOT auto-decrypt when getting (use decryptedSettings()).
     */
    protected function settings(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? json_decode($value, true) : [],
            set: fn (array $value) => json_encode($this->encryptSensitiveFields($value, $this->getSchemaForSettings())),
        );
    }

    /**
     * Get credential schema, loading gateway if necessary.
     */
    private function getSchemaForCredentials(): array
    {
        return $this->gateway?->credential_schema['fields'] ?? [];
    }

    /**
     * Get setting schema, loading gateway if necessary.
     */
    private function getSchemaForSettings(): array
    {
        return $this->gateway?->setting_schema['fields'] ?? [];
    }

    /**
     * Encrypt sensitive fields in values based on schema.
     */
    private function encryptSensitiveFields(array $values, array $schema): array
    {
        foreach ($schema as $field) {
            $key = $field['key'] ?? null;

            if ($key && ($field['sensitive'] ?? false) && array_key_exists($key, $values)) {
                try {
                    $values[$key] = Crypt::encryptString((string) $values[$key]);
                } catch (\Throwable $e) {
                    // Log error in production if needed; fallback to unencrypted
                    \Log::warning('Failed to encrypt credential field: '.$key, ['error' => $e->getMessage()]);
                }
            }
        }

        return $values;
    }

    /**
     * Decrypt sensitive fields in values based on schema.
     */
    private function decryptSensitiveFields(array $values, array $schema): array
    {
        foreach ($schema as $field) {
            $key = $field['key'] ?? null;

            if ($key && ($field['sensitive'] ?? false) && array_key_exists($key, $values)) {
                try {
                    $values[$key] = Crypt::decryptString($values[$key]);
                } catch (\Throwable $e) {
                    // Skip if invalid or already decrypted; log if needed
                    \Log::warning('Failed to decrypt credential field: '.$key, ['error' => $e->getMessage()]);
                }
            }
        }

        return $values;
    }

    /**
     * Get decrypted credentials.
     */
    public function getDecryptedCredentialsAttribute(): array
    {
        return $this->decryptSensitiveFields(
            $this->credentials ?? [],
            $this->getSchemaForCredentials()
        );
    }

    /**
     * Get decrypted settings.
     */
    public function getDecryptedSettingsAttribute(): array
    {
        return $this->decryptSensitiveFields(
            $this->settings ?? [],
            $this->getSchemaForSettings()
        );
    }
}
