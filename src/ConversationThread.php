<?php

namespace Gmammadov\NovaSupportChat;

use Laravel\Nova\ResourceTool;

/**
 * The chat thread with a reply box, shown on a conversation's detail page.
 */
class ConversationThread extends ResourceTool
{
    public function name()
    {
        return trans('support-chat::support-chat.admin.messages');
    }

    public function toolComponent()
    {
        return 'support-chat-thread';
    }
}
