<?php

return [
    /**
     * Szerepkörök
     */
    'roles.admin.label' => 'Adminisztrátor',
    'roles.admin.description' => 'Adminisztrátor teljes hozzáféréssel',
    'roles.staff.label' => 'Munkatárs',
    'roles.staff.description' => 'Munkatárs alapvető hozzáféréssel',

    /**
     * Jogosultságok
     */
    'permissions.settings.label' => 'Beállítások',
    'permissions.settings.description' => 'Hozzáférést biztosít az adminisztrációs felület beállításaihoz',

    'permissions.settings:core.label' => 'Alapvető beállítások',
    'permissions.settings:core.description' => 'Hozzáférés az alapvető áruházbeállításokhoz, például csatornák, nyelvek, pénznemek stb.',

    'permissions.settings:manage-staff.label' => 'Munkatársak kezelése',
    'permissions.settings:manage-staff.description' => 'Engedélyezi a munkatársak szerkesztését',

    'permissions.settings:manage-attributes.label' => 'Attribútumok kezelése',
    'permissions.settings:manage-attributes.description' => 'Engedélyezi további attribútumok létrehozását és szerkesztését',

    'permissions.catalog:manage-products.label' => 'Termékek kezelése',
    'permissions.catalog:manage-products.description' => 'Engedélyezi a termékek, terméktípusok és márkák szerkesztését',

    'permissions.catalog:manage-collections.label' => 'Gyűjtemények kezelése',
    'permissions.catalog:manage-collections.description' => 'Engedélyezi a gyűjtemények és azok csoportjainak szerkesztését',

    'permissions.sales:manage-orders.label' => 'Rendelések kezelése',
    'permissions.sales:manage-orders.description' => 'Engedélyezi a rendelések kezelését',

    'permissions.sales:manage-customers.label' => 'Vásárlók kezelése',
    'permissions.sales:manage-customers.description' => 'Engedélyezi a vásárlók kezelését',

    'permissions.sales:manage-discounts.label' => 'Kedvezmények kezelése',
    'permissions.sales:manage-discounts.description' => 'Engedélyezi a kedvezmények kezelését',
];
