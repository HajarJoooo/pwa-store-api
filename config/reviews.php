<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Require Installation Before Review
    |--------------------------------------------------------------------------
    |
    | When enabled, users must have installed a PWA before they can submit
    | a review for it.
    |
    */
    'require_install' => (bool) env('REVIEWS_REQUIRE_INSTALL', true),
];
