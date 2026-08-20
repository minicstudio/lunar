<?php

namespace Lunar\Content\Support;

use Lunar\Admin\Support\Forms\Components\TranslatedText;

class ContentRichtext
{
    /**
     * Configure a translatable field for CMS rich text with public attachment storage.
     */
    public static function configure(TranslatedText $field): TranslatedText
    {
        return $field
            ->optionRichtext(true)
            ->richtextFileAttachmentsDisk(config('lunar.content.richtext_attachments_disk'))
            ->richtextFileAttachmentsDirectory(config('lunar.content.richtext_attachments_directory'))
            ->richtextFileAttachmentsVisibility('public');
    }

    /**
     * Prepare admin rich text HTML for storefront output.
     *
     * Image captions are useful in the admin editor but should not appear on the
     * storefront (popups, FAQ answers, contact intro, etc.).
     */
    public static function forStorefront(?string $html): ?string
    {
        if (! filled($html)) {
            return null;
        }

        $withoutCaptions = preg_replace('/<figcaption\b[^>]*>[\s\S]*?<\/figcaption>/i', '', $html) ?? $html;

        return trim($withoutCaptions);
    }
}
