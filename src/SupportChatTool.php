<?php

namespace Gismatul\NovaSupportChat;

use Gismatul\NovaSupportChat\Nova\Conversation;
use Gismatul\SupportChat\Enums\ConversationStatus;
use Gismatul\SupportChat\Models\Conversation as ConversationModel;
use Gismatul\SupportChat\SupportChat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Menu\MenuItem;
use Laravel\Nova\Menu\MenuSection;
use Laravel\Nova\Nova;
use Laravel\Nova\Tool;

/**
 * Adds the "Support Chat" menu section (conversations + settings).
 * Register it in NovaServiceProvider::tools(): return [SupportChatTool::make()];
 */
class SupportChatTool extends Tool
{
    public function __construct()
    {
        parent::__construct();

        $this->canSee(fn (Request $request) => Gate::forUser($request->user())->allows(SupportChat::ABILITY_VIEW));
    }

    public function menu(Request $request)
    {
        $conversations = MenuItem::resource(Conversation::class);

        if (config('nova-support-chat.pending_badge', true)) {
            $conversations->withBadgeIf(
                fn () => (string) ConversationModel::query()->where('status', ConversationStatus::Pending->value)->count(),
                'warning',
                fn () => ConversationModel::query()->where('status', ConversationStatus::Pending->value)->exists()
            );
        }

        return MenuSection::make(trans('support-chat::support-chat.name'), [
            $conversations,
            MenuItem::link(trans('support-chat::support-chat.admin.settings'), '/support-chat/settings')
                ->canSee(fn (Request $request) => Gate::forUser($request->user())->allows(SupportChat::ABILITY_MANAGE)),
        // Nova 4 ships Heroicons v1, Nova 5 Heroicons v2.
        ])->icon(version_compare(Nova::version(), '5.0', '>=') ? 'chat-bubble-left-right' : 'chat-alt-2')->collapsable();
    }
}
