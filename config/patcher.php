<?php

return [
    // Where `make:patch` stores new patches, and the layout `patcher:organize` moves patches into.
    // Supported: "none" (patches/), "year" (patches/YYYY/), "month" (patches/YYYY/MM/).
    // Patches are always discovered in patches/, patches/YYYY/ and patches/YYYY/MM/, whatever this is set to.
    'grouping' => env('PATCHER_GROUPING', 'none'),
];
