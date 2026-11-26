<?php

namespace Lunar\ProductImport\Actions;

use Illuminate\Support\Str;
use Lunar\ProductImport\Models\ProductImportProfile;

/**
 * Renames a profile. Past imports keep their mappings.
 */
class RenameProfile
{
    public function __construct(protected SaveProfile $saver) {}

    public function handle(ProductImportProfile $profile, string $name): ProductImportProfile
    {
        $name = Str::trim($name);

        $this->saver->ensureNameIsFree($name, $profile);

        $profile->forceFill(['name' => $name])->save();

        return $profile;
    }
}
