<?php

namespace Gmammadov\NovaSupportChat\Nova\Actions;

use Gmammadov\SupportChat\Models\Conversation;
use Gmammadov\SupportChat\Services\ChatService;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Closes the selected conversations. The customer sees that the conversation was closed,
 * and the Telegram support chat is notified.
 */
class CloseConversation extends Action
{
    public $withoutActionEvents = true;

    public function name()
    {
        return trans('support-chat::support-chat.admin.close_conversation');
    }

    public function handle(ActionFields $fields, Collection $models)
    {
        $chat = app(ChatService::class);
        $admin = request()->user();

        /** @var Conversation $conversation */
        foreach ($models as $conversation) {
            if (! $conversation->isClosed()) {
                $chat->closeConversation($conversation, reason: 'closed', admin: $admin);
            }
        }

        return Action::message(trans('support-chat::support-chat.admin.closed_success'));
    }

    public function fields(NovaRequest $request)
    {
        return [];
    }
}
