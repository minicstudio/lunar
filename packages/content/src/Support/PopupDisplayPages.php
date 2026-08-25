<?php

namespace Lunar\Content\Support;

class PopupDisplayPages
{
    public const HOME = 'home';

    public const COLLECTION = 'collection';

    public const PRODUCT = 'product';

    public const SEARCH = 'search';

    public const CART = 'cart';

    public const CHECKOUT = 'checkout';

    public const BLOG = 'blog';

    public const OTHER = 'other';

    /**
     * Options for the admin CheckboxList.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::HOME => __('lunarpanel.content::popup.form.display_on.options.home'),
            self::COLLECTION => __('lunarpanel.content::popup.form.display_on.options.collection'),
            self::PRODUCT => __('lunarpanel.content::popup.form.display_on.options.product'),
            self::SEARCH => __('lunarpanel.content::popup.form.display_on.options.search'),
            self::CART => __('lunarpanel.content::popup.form.display_on.options.cart'),
            self::CHECKOUT => __('lunarpanel.content::popup.form.display_on.options.checkout'),
            self::BLOG => __('lunarpanel.content::popup.form.display_on.options.blog'),
            self::OTHER => __('lunarpanel.content::popup.form.display_on.options.other'),
        ];
    }

    /**
     * Resolve the current request route name to a page type.
     */
    public static function resolveFromRoute(?string $routeName = null): string
    {
        $routeName ??= request()->route()?->getName() ?? '';

        if ($routeName === '') {
            return self::OTHER;
        }

        if (str_ends_with($routeName, '.home')) {
            return self::HOME;
        }

        if (str_contains($routeName, '.collection.')) {
            return self::COLLECTION;
        }

        if (str_contains($routeName, '.product.')) {
            return self::PRODUCT;
        }

        if (str_contains($routeName, '.search.')) {
            return self::SEARCH;
        }

        if (str_ends_with($routeName, '.cart')) {
            return self::CART;
        }

        if (str_contains($routeName, '.checkout.')) {
            return self::CHECKOUT;
        }

        if (str_contains($routeName, '.blog.')) {
            return self::BLOG;
        }

        return self::OTHER;
    }

    /**
     * Whether a popup should display on the current (or given) route.
     *
     * Only pages listed in $displayOn are eligible.
     *
     * @param  list<string>|null  $displayOn
     */
    public static function matches(?array $displayOn, ?string $routeName = null): bool
    {
        $displayOn = array_values(array_filter($displayOn ?? []));

        if ($displayOn === []) {
            return false;
        }

        return in_array(self::resolveFromRoute($routeName), $displayOn, true);
    }

    /**
     * Default selected page types for new popups.
     *
     * @return list<string>
     */
    public static function defaults(): array
    {
        return array_keys(self::options());
    }
}
