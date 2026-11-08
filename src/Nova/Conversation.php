<?php

namespace Gmammadov\NovaSupportChat\Nova;

use Gmammadov\NovaSupportChat\ConversationThread;
use Gmammadov\NovaSupportChat\Nova\Actions\CloseConversation;
use Gmammadov\NovaSupportChat\Nova\Filters\ConversationStatusFilter;
use Gmammadov\SupportChat\Enums\ConversationStatus;
use Gmammadov\SupportChat\Models\Conversation as ConversationModel;
use Gmammadov\SupportChat\SupportChat;
use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource;

/**
 * Read-only list of website chat conversations. Conversations are created by customers only;
 * answering and closing happen in the thread on the detail page (or the Close action).
 *
 * @extends Resource<ConversationModel>
 */
class Conversation extends Resource
{
    public static $model = ConversationModel::class;

    public static $title = 'reference';

    public static $search = ['reference', 'customer_name', 'customer_email', 'customer_phone'];

    public static $polling = true;

    public static $pollingInterval = 15;

    public static $showPollingToggle = true;

    public static function uriKey()
    {
        return 'support-chat-conversations';
    }

    public static function label()
    {
        return trans('support-chat::support-chat.admin.conversations');
    }

    public static function singularLabel()
    {
        return trans('support-chat::support-chat.admin.reference');
    }

    public function subtitle()
    {
        return $this->customer_name;
    }

    public function fields(NovaRequest $request)
    {
        $t = fn (string $key) => trans('support-chat::support-chat.admin.' . $key);

        return [
            ID::make()->sortable()->hideFromIndex(),

            Text::make($t('reference'), 'reference')->sortable(),

            Text::make($t('customer'), 'customer_name')->sortable(),

            Text::make($t('contact'), fn () => $this->customer_phone ?: $this->customer_email)->onlyOnIndex(),

            Text::make(trans('support-chat::support-chat.widget.email'), 'customer_email')->hideFromIndex(),

            Text::make(trans('support-chat::support-chat.widget.phone'), 'customer_phone')->hideFromIndex(),

            Badge::make($t('status'), 'status')
                ->resolveUsing(fn ($status) => $status instanceof ConversationStatus ? $status->value : $status)
                ->map([
                    ConversationStatus::Pending->value => 'warning',
                    ConversationStatus::Open->value => 'success',
                    ConversationStatus::Closed->value => 'info',
                ])
                ->labels(ConversationStatus::labels()),

            Number::make($t('messages'), 'messages_count')->onlyOnIndex()->sortable(),

            DateTime::make($t('last_message'), 'last_message_at')->sortable()->exceptOnForms(),

            DateTime::make($t('started'), 'created_at')->onlyOnDetail(),

            DateTime::make($t('closed'), 'closed_at')->onlyOnDetail(),

            Text::make($t('ip_address'), 'ip_address')->onlyOnDetail(),

            ConversationThread::make(),
        ];
    }

    public static function indexQuery(NovaRequest $request, $query)
    {
        return $query->withCount('messages');
    }

    /**
     * Most recently active first, unless the user sorts by a column.
     */
    public static function defaultOrderings($query)
    {
        return $query->orderByDesc('last_message_at')->orderByDesc('id');
    }

    public function filters(NovaRequest $request)
    {
        return [new ConversationStatusFilter()];
    }

    public function actions(NovaRequest $request)
    {
        return [
            CloseConversation::make()
                ->canSee(fn ($request) => Gate::forUser($request->user())->allows(SupportChat::ABILITY_REPLY))
                ->canRun(fn ($request) => Gate::forUser($request->user())->allows(SupportChat::ABILITY_REPLY)),
        ];
    }

    // Authorization is enforced by Gmammadov\SupportChat\Policies\ConversationPolicy (registered by the
    // core package): Nova only enforces create/update/delete on the API when a policy exists.
}
