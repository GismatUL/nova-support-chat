<?php

namespace Gmammadov\NovaSupportChat\Nova\Filters;

use Gmammadov\SupportChat\Enums\ConversationStatus;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

class ConversationStatusFilter extends Filter
{
    public $component = 'select-filter';

    public function name()
    {
        return trans('support-chat::support-chat.admin.status');
    }

    public function apply(NovaRequest $request, $query, $value)
    {
        // Only known statuses reach the query.
        $status = ConversationStatus::tryFrom((string) $value);

        return $status ? $query->where('status', $status->value) : $query;
    }

    public function options(NovaRequest $request)
    {
        return array_flip(ConversationStatus::labels());
    }
}
