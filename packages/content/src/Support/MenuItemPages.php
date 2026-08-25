<?php

namespace Lunar\Content\Support;

class MenuItemPages
{
    public const ABOUT_US = 'about-us';

    public const FAQ = 'faq';

    public const PRIVACY_POLICY = 'privacy-policy';

    public const TERMS_AND_CONDITIONS = 'terms-and-conditions';

    public const DELIVERY_AND_RETURN = 'delivery-and-return';

    public const COOKIE_POLICY = 'cookie-policy';

    /**
     * Known CMS page keys (match `lfp.{key}` storefront route names).
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return [
            self::ABOUT_US,
            self::FAQ,
            self::PRIVACY_POLICY,
            self::TERMS_AND_CONDITIONS,
            self::DELIVERY_AND_RETURN,
            self::COOKIE_POLICY,
        ];
    }

    /**
     * CMS page options for the admin select (Contact is a separate link type).
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::ABOUT_US => __('lunarpanel.content::menu_item.form.cms_page.options.about_us'),
            self::FAQ => __('lunarpanel.content::menu_item.form.cms_page.options.faq'),
            self::PRIVACY_POLICY => __('lunarpanel.content::menu_item.form.cms_page.options.privacy_policy'),
            self::TERMS_AND_CONDITIONS => __('lunarpanel.content::menu_item.form.cms_page.options.terms_and_conditions'),
            self::DELIVERY_AND_RETURN => __('lunarpanel.content::menu_item.form.cms_page.options.delivery_and_return'),
            self::COOKIE_POLICY => __('lunarpanel.content::menu_item.form.cms_page.options.cookie_policy'),
        ];
    }

    /**
     * Whether the given page key is a known CMS menu target.
     */
    public static function isValid(?string $page): bool
    {
        return filled($page) && in_array($page, self::keys(), true);
    }
}
