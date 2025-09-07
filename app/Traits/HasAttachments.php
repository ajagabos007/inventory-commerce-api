<?php

namespace App\Traits;

use App\Models\Attachment;
use Exception;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait HasAttachments
{
    /**
     * Get the models's attachments.
     *
     * @return Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Get the model's attachment.
     *
     * @return Illuminate\Database\Eloquent\Relations\MorphOne
     */
    public function attachment(): MorphOne
    {
        return $this->morphOne(Attachment::class, 'attachable');
    }

    /**
     * Attach Attachment to a Model
     *
     * @param \Illuminate\Http\UploadedAttacFile
     * @param  array <string, string>  $options
     */
    public function attachUploadedFile(UploadedFile $file, array $options = [], string $base_folder = ''): Attachment
    {
        if (! file_exists($file)) {
            throw new Exception('Please pass in a file that exists');
        }

        if (\strlen($base_folder) == 0) {
            // set base folder using the model's class name
            $model_class = Str::of($this::class)->classBasename();
            $base_folder = Str::kebab(Str::plural($model_class));
        }

        $base_folder = \strip_tags($base_folder);
        $base_folder = \stripslashes($base_folder);

        // remove trailling forward slashes
        foreach ($explodes = \explode('/', $base_folder) as $key => $value) {
            if ($key == array_key_first($explodes)) {
                $base_folder = '';
            }

            if (strlen($value) == 0) {
                continue;
            }

            if (strlen($base_folder) == 0) {
                $base_folder = $value;
            } else {
                $base_folder = $base_folder.'/'.$value;
            }
        }

        // remove trailling backward slashes
        foreach ($explodes = \explode('\\', $base_folder) as $key => $value) {
            if ($key == array_key_first($explodes)) {
                $base_folder = '';
            }

            if (strlen($value) == 0) {
                continue;
            }

            if (strlen($base_folder) == 0) {
                $base_folder = $value;
            } else {
                $base_folder = $base_folder.'/'.$value;
            }
        }

        // reset base folder to uploads if empty
        if (\strlen($base_folder) == 0) {
            $base_folder = 'uploads';
        }

        $directory = $base_folder.'/'.now()->format('Y').'/'.now()->format('m');

        $storage = Storage::disk($this->attachmentDefaultDisk());
        $path = $storage->put($directory, $file);

        /**
         * @disregard
         */
        $url = $storage->url($path);
        $mime_type = $file->getClientMimeType();

        $_attachment = new Attachment;
        $_attachment->name = $options['file_name'] ?? $file->getClientOriginalName();
        $_attachment->path = $path;
        $_attachment->url = $url;
        $_attachment->type = \explode('/', $mime_type)[0];
        $_attachment->mime_type = $mime_type;
        $_attachment->extension = $file->extension();
        $_attachment->size = $storage->size($path);  // size in bytes
        $_attachment->storage = $this->attachmentDefaultDisk();

        /**
         * @disregard
         */
        $_attachment->user_id = auth()->id();

        $this->attachments()->save($_attachment);

        return $_attachment;
    }

    /**
     * Attach Attachment to a Model
     *
     * @param Illuminate\Http\UploadedAttacFilee
     * @param  array <string, string>  $options
     * @return App\Models\Attachment
     */
    public function updateUploadedFile(UploadedFile $file, array $options = [], string $base_folder = ''): Attachment
    {
        if (! file_exists($file)) {
            throw new Exception('Please pass in a file that exists');
        }

        $file_name = $options['file_name'] ?? $file->getClientOriginalName();
        $_attachments = $this->attachments()->where('name', $file_name)->get();

        $this->detachAttachments($_attachments);

        return $this->attachUploadedFile($file, $options, $base_folder);
    }

    /**
     * Attach Attachment to a Model
     *
     * @param  array <string, string>  $options
     * @return App\Models\Attachment
     */
    public function updateUploadedBase64File(string $base64_file, array $options = [], string $base_folder = ''): Attachment
    {
        $parts = explode(';base64,', $base64_file);
        $file_data = base64_decode($parts[1]);
        $mime_type = str_replace('data:', '', $parts[0]);
        $file_name = $options['file_name'] ?? null;

        $tmp_file_path = tempnam(sys_get_temp_dir(), 'base64_');
        file_put_contents($tmp_file_path, $file_data);

        if (is_null($file_name) || (is_string($file_name) && strlen($file_name) <= 0)) {
            $extension = explode('/', $mime_type)[1];
            $options['file_name'] = uniqid().'.'.$extension;
        }

        $uploaded_file = new UploadedFile($tmp_file_path, $options['file_name'], $mime_type, null, true);
        // unlink($tmp_file_path);

        /**
         * @disregard
         */
        return $this->updateUploadedFile($uploaded_file, $options, $base_folder);
    }

    /**
     * Attach Attachment to a Model
     *
     *
     * @return App\Models\Attachment
     */
    public function attachAttachment(Attachment $attachment): Attachment
    {
        $this->attachments()->save($attachment);

        return $attachment;
    }

    /**
     * Delete all/one/multiple attachment(s) associated with the model
     *
     * @param App\Models\Attachment | Illuminate\Support\Collection | null
     */
    public function detachAttachments(null|Attachment|Collection $attachment): void
    {
        if (is_null($attachment)) {
            $attachments = $this->attachments()->get();
        } elseif ($attachment instanceof Attachment) {
            $attachments = $this->attachments()->whereIn('id', [$attachment->id])->get();
        } elseif ($attachment instanceof Collection) {
            $attachments = $this->attachments()->whereIn('id', $attachment->pluck('id'))->get();
        }

        foreach ($attachments as $key => $attachment) {
            $this->detachAttachment($attachment);
        }

    }

    /**
     * Delete a specific attachment associated with the model
     *
     * @param App\Models\Attachment
     */
    public function detachAttachment(Attachment $attachment): void
    {
        $attachment->delete();

        if ($attachment->path) {
            Storage::disk($attachment->storage ?? $this->attachmentDefaultDisk())->delete($attachment->path);
        }
    }

    /**
     * Get the first attachment relating to the model
     *
     * @return App\Models\Attachment | null
     */
    public function firstAttachment()
    {
        return $this->attachments()->first();
    }

    /**
     * Get the last attachment relating to the model
     *
     * @return App\Models\Attachment | null
     */
    public function lastAttachment()
    {
        return $this->attachments()->last();
    }

    /**
     * Get the latest Attachment relating the model
     *
     * @return App\Models\Attachment | null
     */
    public function latestAttachment()
    {
        return $this->attachments()->latest()->first();
    }

    /**
     * Get the disk that profile photos should be stored on.
     */
    protected function attachmentDefaultDisk(): string
    {
        /**
         * @disregard
         */
        $disk = Storage::disk()->getConfig()['driver'];

        return $disk == 'local' ? 'public' : $disk;
    }

    /**
     * Handle attachable delete event
     */
    public static function bootAttachment()
    {
        static::deleted(function ($model) {
            $model->detachAttachments();
            dd('ajkds');
        });
    }
}
