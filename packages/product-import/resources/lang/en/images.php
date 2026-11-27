<?php

return [
    'reasons' => [
        'not_web' => 'it is not an HTTP or HTTPS address',
        'not_allowed' => 'the address is not allowed, because it is not a public web address',
        'unresolved' => 'its host could not be found',
        'unreachable' => 'the server could not be reached or did not answer in time',
        'too_many_redirects' => 'it redirects more than :count times',
        'http_status' => 'the server answered with status :status',
        'too_large' => 'it is larger than :size KB',
        'wrong_type' => 'it is not a JPEG, PNG, GIF, or WebP image',
        'not_saved' => 'it could not be saved',
        'product_missing' => 'its product no longer exists',
    ],
];
