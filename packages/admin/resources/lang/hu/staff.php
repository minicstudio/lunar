<?php

return [

    'label' => 'Munkatárs',

    'plural_label' => 'Munkatársak',

    'table' => [
        'firstname' => [
            'label' => 'Keresztnév',
        ],
        'lastname' => [
            'label' => 'Vezetéknév',
        ],
        'email' => [
            'label' => 'Email',
        ],
        'admin' => [
            'badge' => 'Szuper Admin',
        ],
    ],

    'form' => [
        'firstname' => [
            'label' => 'Keresztnév',
        ],
        'lastname' => [
            'label' => 'Vezetéknév',
        ],
        'email' => [
            'label' => 'Email',
        ],
        'password' => [
            'label' => 'Jelszó',
            'hint' => 'Jelszó visszaállítása',
        ],
        'admin' => [
            'label' => 'Szuper Admin',
            'helper' => 'A szuper admin szerepkörök nem módosíthatók a hubban.',
        ],
        'roles' => [
            'label' => 'Szerepkörök',
            'helper' => ':roles teljes hozzáféréssel rendelkeznek',
        ],
        'permissions' => [
            'label' => 'Engedélyek',
        ],
        'role' => [
            'label' => 'Szerepkör neve',
        ],
    ],

    'action' => [
        'acl' => [
            'label' => 'Hozzáférés-vezérlés',
        ],
        'add-role' => [
            'label' => 'Szerepkör hozzáadása',
        ],
        'delete-role' => [
            'label' => 'Szerepkör törlése',
            'heading' => 'Szerepkör törlése: :role',
        ],
    ],

    'acl' => [
        'title' => 'Hozzáférés-vezérlés',
        'tooltip' => [
            'roles-included' => 'Az engedély a következő szerepkörökben szerepel',
        ],
        'notification' => [
            'updated' => 'Frissítve',
            'error' => 'Hiba',
            'no-role' => 'Szerepkör nincs regisztrálva a Lunar-ban',
            'no-permission' => 'Engedély nincs regisztrálva a Lunar-ban',
            'no-role-permission' => 'Szerepkör és engedély nincs regisztrálva a Lunar-ban',
        ],
    ],

];
