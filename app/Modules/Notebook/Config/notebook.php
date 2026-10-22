<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Voice notes
    |--------------------------------------------------------------------------
    |
    | A note said rather than written: recorded in the browser, kept on the
    | private disk under `notebook/`, and played back when the notebook is
    | gone through. A clip is short by design — a name and what was taken —
    | and stops by itself at `max_seconds`.
    |
    */

    'voice' => [
        'disk' => 'local',
        'max_seconds' => 60,
        'max_kilobytes' => 4096,
        'types' => ['audio/webm', 'video/webm', 'audio/ogg', 'audio/mp4', 'video/mp4', 'audio/x-m4a', 'audio/mpeg', 'audio/wav', 'audio/x-wav'],
    ],

];
