<?php

namespace Gismatul\NovaSupportChat;

use Gismatul\NovaSupportChat\Http\Middleware\Authorize;
use Gismatul\NovaSupportChat\Nova\Conversation;
use Gismatul\SupportChat\SupportChat;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Nova\Events\ServingNova;
use Laravel\Nova\Http\Middleware\Authenticate;
use Laravel\Nova\Nova;

class NovaSupportChatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/nova-support-chat.php', 'nova-support-chat');

        // Runs before any provider boots, so the core package sees it when registering its routes.
        if (config('nova-support-chat.disable_core_admin_routes', true)) {
            config(['support-chat.routes.admin.enabled' => false]);
        }

        // Default access rule: whoever may use Nova (your viewNova gate) may use the support chat.
        // Narrow it with SupportChat::authorizeUsing() or by defining the supportChat.* gates yourself.
        SupportChat::defaultAuthorizeUsing(fn ($user, string $ability) => Nova::check(request()));
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/nova-support-chat.php' => config_path('nova-support-chat.php'),
        ], 'nova-support-chat-config');

        $this->app->booted(fn () => $this->routes());

        Nova::serving(function (ServingNova $event) {
            Nova::script('nova-support-chat', __DIR__ . '/../dist/js/tool.js');
            Nova::style('nova-support-chat', __DIR__ . '/../dist/css/tool.css');

            if (config('nova-support-chat.register_resource', true)) {
                Nova::resources([Conversation::class]);
            }

            Nova::provideToScript([
                'supportChat' => [
                    'apiBase' => '/nova-vendor/support-chat',
                    'pollInterval' => max(2, (int) config('nova-support-chat.poll_interval', 4)) * 1000,
                    'maxLength' => (int) config('support-chat.message_max_length', 2000),
                    'i18n' => array_merge(
                        trans('support-chat::support-chat.admin'),
                        ['settings_page' => trans('support-chat::support-chat.settings')],
                        ['statuses' => trans('support-chat::support-chat.statuses')],
                        ['senders' => trans('support-chat::support-chat.senders')],
                    ),
                ],
            ]);
        });
    }

    protected function routes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        // Inertia page (settings), behind Nova's web authentication.
        Nova::router(['nova', Authenticate::class, Authorize::class], 'support-chat')
            ->group(function () {
                Route::get('settings', fn () => inertia('SupportChatSettings'))->name('nova-support-chat.settings');
            });

        // JSON API used by the Vue components: Nova's API middleware (authentication + viewNova)
        // plus our own check; each route also checks a supportChat.* gate.
        Route::middleware(['nova:api', Authorize::class])
            ->prefix('nova-vendor/support-chat')
            ->name('nova-support-chat.')
            ->group(fn () => SupportChat::adminRoutes());
    }
}
