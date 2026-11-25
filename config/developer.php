<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Maximum Pending Applications per Developer
    |--------------------------------------------------------------------------
    |
    | Limits the maximum number of PWAs a developer can submit in pending review
    | status simultaneously to prevent spam and flood.
    |
    */
    'max_pending' => (int) env('DEVELOPER_MAX_PENDING', 10),
];
