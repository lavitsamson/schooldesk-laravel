<?php

declare(strict_types=1);

return [

    'invitations' => [
        'expire_after_days' => (int) env('INVITATION_EXPIRE_DAYS', 7),
    ],

];
