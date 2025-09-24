<?php

namespace App\Models;

use App\Observers\UserObserver;
use App\Traits\HasAttachments;
use App\Traits\HasVerificationTokens;
use App\Traits\ModelRequestLoader;
use App\Traits\Scopeable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[ObservedBy([UserObserver::class])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens;

    use HasAttachments;
    use HasFactory;
    use HasRoles;
    use HasUuids;
    use HasVerificationTokens;
    use ModelRequestLoader;
    use Notifiable;
    use Scopeable;
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'middle_name',
        'phone_number',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'profile_photo_path',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phone_number_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['profile_photo_url'];

    /**
     * Scope a query to only include staff for the current store.
     */
    public function scopeForRequestStore(Builder $builder): void
    {
        $builder->whereHas('staff', function ($builder) {
            $builder->where('store_id', request()->header('x-store'));
        })
            ->orWhereDoesntHave('staff');
    }

    /*
    |--------------------------------------------------------------------------
    | Relationship
    |--------------------------------------------------------------------------
    |
    |
    */

    /*
    |--------------------------------------------------------------------------
    | User Accessors, Mutators & Casting
    |--------------------------------------------------------------------------
    |
    |
    */

    /**
     * Name accessor
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->full_name,
        );
    }

    /**
     * Full Name accessor
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->last_name.' '.$this->first_name.' '.$this->middle_name,
        );
    }

    /**
     * Get the URL to the user's profile photo.
     */
    public function profilePhotoUrl(): Attribute
    {
        return Attribute::make(function (): string {

            if (is_null($this->profile_photo_path) || ! Storage::disk($this->profilePhotoDisk())->exists($this->profile_photo_path)) {
                return $this->defaultProfilePhotoUrl();
            }

            return Storage::disk($this->profilePhotoDisk())->url($this->profile_photo_path);

        });
    }

    /**
     * is admin accessor
     */
    protected function isAdmin(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->hasRole('admin')
        );
    }


    /**
     * is admin accessor
     */
    protected function isStaff(): Attribute
    {
        return Attribute::make(
            get: fn () => ! blank($this->staff)
        );
    }

    /**
     * is deactivated
     */
    protected function isDeactivated(): Attribute
    {
        return Attribute::make(
            get: fn () => ! is_null($this->deactivated_at)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Functions
    |--------------------------------------------------------------------------
    |
    |
    */

    /**
     * Send the email verification notification.
     *
     * @return void
     */
    public function sendEmailVerificationNotification()
    {

        $verification_token = $this->createVerificationToken('email');

        $this->notify(new \App\Notifications\Auth\VerifyEmail($verification_token));

    }

    /**
     * Update the user's profile photo.
     *
     * @param  string  $storagePath
     * @return void
     */
    public function updateProfilePhoto(UploadedFile|string $photo, $storagePath = 'profile-photos')
    {
        if (is_string($photo)) {
            $parts = explode(';base64,', $photo);
            $file_data = base64_decode($parts[1]);
            $mime_type = str_replace('data:', '', $parts[0]);

            $tmp_file_path = tempnam(sys_get_temp_dir(), 'base64_');
            file_put_contents($tmp_file_path, $file_data);

            $extension = explode('/', $mime_type)[1];
            $file_name = uniqid().'.'.$extension;

            $photo = new UploadedFile($tmp_file_path, $file_name, $mime_type, null, true);
        }
        tap($this->profile_photo_path, function ($previous) use ($photo, $storagePath) {

            $this->forceFill([
                'profile_photo_path' => $photo->storePublicly(
                    $storagePath, ['disk' => $this->profilePhotoDisk()]
                ),
            ])->save();

            if ($previous) {
                Storage::disk($this->profilePhotoDisk())->delete($previous);
            }

        });
    }

    /**
     * Delete the user's profile photo.
     *
     * @return void
     */
    public function deleteProfilePhoto()
    {
        if (is_null($this->profile_photo_path)) {
            return;
        }

        Storage::disk($this->profilePhotoDisk())->delete($this->profile_photo_path);

        $this->forceFill([
            'profile_photo_path' => null,
        ])->save();
    }

    /**
     * Get the default profile photo URL if no profile photo has been uploaded.
     *
     * @return string
     */
    protected function defaultProfilePhotoUrl()
    {
        $name = trim(collect(explode(' ', $this->name))->map(function ($segment) {
            return mb_substr($segment, 0, 1);
        })->join(' '));

        return 'https://ui-avatars.com/api/?name='.urlencode($name).'&color=7F9CF5&background=EBF4FF';
    }

    /**
     * Get the disk that profile photos should be stored on.
     */
    protected function profilePhotoDisk(): string
    {
        $disk = Storage::disk()->getConfig()['driver'];

        return $disk == 'local' ? 'public' : $disk;
    }

    /**
     * Scope a query to only include users that are/are note deactivated.
     *
     * @param  bool  $is_deactivated
     */
    public function scopeDeactivated(Builder $query, $is_deactivated = true): void
    {
        $is_deactivated = filter_var($is_deactivated, FILTER_VALIDATE_BOOLEAN);
        if ($is_deactivated) {
            $query->whereNotNull('deactivated_at');
        } else {
            $query->whereNull('deactivated_at');
        }
    }

    /**
     * Scope a query to only include default wallet address.
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('deactivated_at');
    }

    /**
     * Scope a query to only include default wallet address.
     */
    public function scopeInactive(Builder $query): void
    {
        $query->whereNotNull('deactivated_at');
    }

    /**
     * Get the store managed by the user.
     */
    public function staff(): HasOne
    {
        return $this->hasOne(Staff::class, 'user_id');
    }
}
