<?php

namespace App\Models;

use App\Observers\PaymentGatewayObserver;
use App\Traits\ModelRequestLoader;
use Database\Factories\PaymentGatewayFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

#[ObservedBy([PaymentGatewayObserver::class])]
class PaymentGateway extends Model
{
    /** @use HasFactory<PaymentGatewayFactory> */
    use HasFactory;

    use HasUuids;
    use ModelRequestLoader;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'logo_path',
        'disabled_at',
        'is_default',
        'sort_order',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['logo_url', 'is_disabled'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'credential_schema' => 'array',
            'setting_schema' => 'array',
            'supported_currencies' => 'array',
        ];
    }

    /**
     * Name accessor
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
     * Search scope
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $query) use ($term) {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhere('disabled_reason', 'like', "%{$term}%");
        });
    }

    /**
     * Get the URL to the user's profile photo.
     */
    public function logoUrl(): Attribute
    {
        return Attribute::make(function (): string {

            if (is_null($this->logo_path) || ! Storage::disk($this->logoDisk())->exists($this->logo_path)) {
                return $this->defaultLogoUrl();
            }

            return Storage::disk($this->logoDisk())->url($this->logo_path);

        });
    }

    /**
     * Update the user's logo.
     *
     * @param  string  $storagePath
     * @return void
     */
    public function updateLogo(UploadedFile|string $logo, $storagePath = 'logos')
    {
        if (is_string($logo)) {
            $parts = explode(';base64,', $logo);
            $file_data = base64_decode($parts[1]);
            $mime_type = str_replace('data:', '', $parts[0]);

            $tmp_file_path = tempnam(sys_get_temp_dir(), 'base64_');
            file_put_contents($tmp_file_path, $file_data);

            $extension = explode('/', $mime_type)[1];
            $file_name = uniqid().'.'.$extension;

            $logo = new UploadedFile($tmp_file_path, $file_name, $mime_type, null, true);
        }
        tap($this->logo_path, function ($previous) use ($logo, $storagePath) {

            $this->forceFill([
                'logo_path' => $logo->storePublicly(
                    $storagePath, ['disk' => $this->logoDisk()]
                ),
            ])->save();

            if ($previous) {
                Storage::disk($this->logoDisk())->delete($previous);
            }

        });
    }

    /**
     * Delete the payment gateway's logo.
     *
     * @return void
     */
    public function deletelogo()
    {
        if (is_null($this->logo_path)) {
            return;
        }

        Storage::disk($this->logoDisk())->delete($this->logo_path);

        $this->forceFill([
            'logo_path' => null,
        ])->save();
    }

    /**
     * Get the default logo URL if no logo has been uploaded.
     *
     * @return string
     */
    protected function defaultLogoUrl()
    {
        $name = trim(collect(explode(' ', $this->name))->map(function ($segment) {
            return mb_substr($segment, 0, 1);
        })->join(' '));

        return 'https://ui-avatars.com/api/?name='.urlencode($name).'&color=7F9CF5&background=EBF4FF';
    }

    /**
     * Get the disk that logo disk should be stored on.
     */
    protected function logoDisk(): string
    {
        $disk = Storage::disk()->getConfig()['driver'];

        return $disk == 'local' ? 'public' : $disk;
    }
}
