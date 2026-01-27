<?php

namespace Lunar\Admin\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class SecureMediaUploadRule implements Rule
{
    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     */
    public function passes($attribute, $value): bool
    {
        // Defensive: ensure UploadedFile
        if (! $value instanceof UploadedFile) {
            return false;
        }

        // Allowed MIME types (whitelist)
        $allowedMimes = config('lunar.media.accepted_file_types', []);

        // Allowed extensions (secondary whitelist)
        $allowedExtensions = config('lunar.media.allowed_file_extensions', []);

        // Content-based real MIME type check
        $realMime = $value->getMimeType();

        if (! in_array($realMime, $allowedMimes, true)) {
            return false;
        }

        // Extension whitelist check
        $extension = Str::lower($value->getClientOriginalExtension());

        if (! in_array($extension, $allowedExtensions, true)) {
            return false;
        }

        // Filename security checks
        $filename = $value->getClientOriginalName();

        // Double extensions (e.g. image.jpg.php)
        $dangerousExtensions = collect(config('lunar.media.dangerous_file_extensions', []));
        if ($dangerousExtensions->some(fn($dangerousExt) => Str::contains($filename, '.' . $dangerousExt . '.'))) {
            return false;
        }

        // Null byte injection
        if (Str::contains($filename, "\0")) {
            return false;
        }

        // Image integrity validation
        if (! $value->getSize()) {
            return false;
        }

        // Force decode to ensure real image content
        try {
            
            imagecreatefromstring($value->getContent());
        } catch (\Throwable $e) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string|array
     */
    public function message(): string
    {
        return 'The :attribute field must be a file of type:' . implode(', ', config('lunar.media.accepted_file_types', [])) . '.';
    }
}