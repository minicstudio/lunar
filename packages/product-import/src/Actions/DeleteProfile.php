<?php

namespace Lunar\ProductImport\Actions;

use Lunar\ProductImport\Models\ProductImportProfile;
use Lunar\ProductImport\Models\ProductImportSheet;

/**
 * Deletes a profile. Sheets that used it keep their mappings; sheets it was
 * offered to lose the offer.
 */
class DeleteProfile
{
    public function handle(ProductImportProfile $profile): void
    {
        ProductImportSheet::query()->where('offered_profile_id', $profile->id)->update(['offered_profile_id' => null, 'offered_profile_score' => null]);
        ProductImportSheet::query()->where('profile_id', $profile->id)->update(['profile_id' => null]);

        $profile->delete();
    }
}
