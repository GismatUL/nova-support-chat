<?php

return [

    // Register the Conversation resource automatically. Disable it to register your own subclass
    // of Gmammadov\NovaSupportChat\Nova\Conversation in NovaServiceProvider::resources().
    'register_resource' => true,

    // The core package's own admin API (/support-chat/admin, behind web + auth) is not needed with Nova:
    // Nova uses an identical API mounted behind Nova's authentication. Keeping it off reduces attack surface.
    'disable_core_admin_routes' => true,

    // Seconds between refreshes of an open conversation in Nova.
    'poll_interval' => 4,

    // Menu badge with the number of conversations waiting for an answer.
    'pending_badge' => true,
];
