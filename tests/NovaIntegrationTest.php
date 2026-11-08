<?php

namespace Gismatul\NovaSupportChat\Tests;

use Gismatul\NovaSupportChat\NovaSupportChatServiceProvider;
use Gismatul\NovaSupportChat\SupportChatTool;
use Gismatul\SupportChat\Models\Conversation;
use Gismatul\SupportChat\SupportChat;
use Gismatul\SupportChat\SupportChatServiceProvider;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Laravel\Nova\Nova;
use Laravel\Nova\NovaApplicationServiceProvider;
use Laravel\Nova\NovaCoreServiceProvider;
use Laravel\Nova\NovaServiceProvider as NovaPackageServiceProvider;
use Orchestra\Testbench\TestCase;

class User extends Authenticatable
{
    protected $guarded = [];

    protected $table = 'users';
}

class AppNovaServiceProvider extends NovaApplicationServiceProvider
{
    protected function gate()
    {
        Gate::define('viewNova', fn ($user) => $user->email === 'admin@example.com');
    }

    protected function resources()
    {
        // Our package registers its own resource.
    }

    public function tools()
    {
        return [SupportChatTool::make()];
    }
}

class NovaIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app)
    {
        return [
            NovaCoreServiceProvider::class,
            NovaPackageServiceProvider::class,
            \Inertia\ServiceProvider::class,
            SupportChatServiceProvider::class,
            NovaSupportChatServiceProvider::class,
            AppNovaServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('app.env', 'production');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('support-chat.telegram.bot_token', '123:TESTTOKEN');
        $app['config']->set('support-chat.telegram.chat_id', '-100123');
        $app['config']->set('support-chat.telegram.webhook_secret', 'secret-secret-secret-123');
        $app['config']->set('support-chat.queue.after_response', false);
        $app['config']->set('queue.default', 'sync');
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadLaravelMigrations();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Nova renders error pages with its published assets (normally done by `php artisan nova:publish`).
        if (! is_dir(public_path('vendor/nova'))) {
            \Illuminate\Support\Facades\File::copyDirectory(
                dirname((new \ReflectionClass(Nova::class))->getFileName(), 2) . '/public',
                public_path('vendor/nova')
            );
        }

        $next = 900;
        Http::fake(['api.telegram.org/*' => function () use (&$next) {
            return Http::response(['ok' => true, 'result' => ['message_id' => $next++]]);
        }]);
    }

    protected function tearDown(): void
    {
        SupportChat::flushState();
        parent::tearDown();
    }

    protected function inertiaHeaders(): array
    {
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'X-Inertia-Version' => (string) app(\Laravel\Nova\Http\Middleware\HandleInertiaRequests::class)->version(request())];
    }

    protected function user(string $email): User
    {
        return User::query()->create(['name' => 'Nova ' . $email, 'email' => $email, 'password' => bcrypt('x')]);
    }

    protected function startConversation(): array
    {
        $r = $this->postJson('/api/support-chat/conversations', ['name' => 'John', 'message' => 'Help please'])->assertCreated();

        return [$r->json('data.conversation.uuid'), $r->json('data.token'), Conversation::query()->latest('id')->value('id')];
    }

    public function test_core_admin_routes_are_disabled_when_nova_package_is_installed(): void
    {
        $this->actingAs($this->user('admin@example.com'))
            ->getJson('/support-chat/admin/conversations')
            ->assertNotFound();
    }

    public function test_guest_cannot_reach_the_nova_chat_api(): void
    {
        $this->getJson('/nova-vendor/support-chat/conversations')->assertUnauthorized();
        $this->getJson('/nova-api/support-chat-conversations')->assertUnauthorized();
    }

    public function test_user_without_nova_access_is_forbidden(): void
    {
        $this->actingAs($this->user('someone@example.com'));

        $this->getJson('/nova-vendor/support-chat/conversations')->assertForbidden();
        $this->getJson('/nova-api/support-chat-conversations')->assertForbidden();
    }

    public function test_nova_user_can_list_read_and_answer_and_the_customer_sees_it(): void
    {
        [$uuid, $token, $id] = $this->startConversation();

        $this->actingAs($this->user('admin@example.com'));

        $this->getJson('/nova-api/support-chat-conversations')
            ->assertOk()
            ->assertJsonPath('resources.0.id.value', $id);

        $this->getJson("/nova-api/support-chat-conversations/{$id}")->assertOk();

        $this->getJson("/nova-vendor/support-chat/conversations/{$id}")
            ->assertOk()
            ->assertJsonPath('data.can.reply', true)
            ->assertJsonPath('data.telegram_configured', true);

        $this->postJson("/nova-vendor/support-chat/conversations/{$id}/messages", ['message' => 'Answer from Nova'])
            ->assertCreated()
            ->assertJsonPath('data.source', 'admin');

        $this->getJson("/api/support-chat/conversations/{$uuid}/messages", ['X-Chat-Token' => $token])
            ->assertJsonPath('data.messages.1.sender', 'operator')
            ->assertJsonPath('data.messages.1.message', 'Answer from Nova');

        Http::assertSent(fn ($request) => str_contains((string) $request['text'], 'answered from the admin panel'));
    }

    public function test_resource_is_read_only_and_delete_needs_manage(): void
    {
        [, , $id] = $this->startConversation();
        $this->actingAs($this->user('admin@example.com'));

        $this->postJson('/nova-api/support-chat-conversations', ['customer_name' => 'Hacker'])->assertForbidden();
        $this->putJson("/nova-api/support-chat-conversations/{$id}", ['customer_name' => 'Hacker'])->assertForbidden();
        $this->assertSame('John', Conversation::query()->find($id)->customer_name);

        // Narrow permissions: may view, not manage.
        SupportChat::authorizeUsing(fn ($user, $ability) => $ability === SupportChat::ABILITY_VIEW);

        $this->deleteJson('/nova-api/support-chat-conversations?resources[]=' . $id)->assertOk();
        $this->assertTrue(Conversation::query()->whereKey($id)->exists(), 'Conversation must not be deleted without manage ability');

        $this->postJson("/nova-vendor/support-chat/conversations/{$id}/messages", ['message' => 'x'])->assertForbidden();
        $this->putJson('/nova-vendor/support-chat/settings', [])->assertForbidden();

        $this->postJson('/nova-api/support-chat-conversations/action?action=close-conversation', ['resources' => (string) $id]);
        $this->assertFalse(Conversation::query()->find($id)->isClosed(), 'View-only user must not close');

        // Full rights: delete works.
        SupportChat::authorizeUsing(fn ($user, $ability) => true);
        $this->deleteJson('/nova-api/support-chat-conversations?resources[]=' . $id)->assertOk();
        $this->assertFalse(Conversation::query()->whereKey($id)->exists());
    }

    public function test_close_action(): void
    {
        [, , $id] = $this->startConversation();
        $this->actingAs($this->user('admin@example.com'));

        $this->postJson('/nova-api/support-chat-conversations/action?action=close-conversation', ['resources' => (string) $id])
            ->assertOk();

        $this->assertTrue(Conversation::query()->find($id)->isClosed());
    }

    public function test_settings_api_and_page(): void
    {
        $this->actingAs($this->user('admin@example.com'));

        $this->putJson('/nova-vendor/support-chat/settings', [
            'email_field' => 'required', 'phone_field' => 'hidden', 'auto_close_hours' => 12, 'delete_after_days' => 0,
        ])->assertOk()->assertJsonPath('data.settings.phone_field', 'hidden');

        $this->get('/nova/support-chat/settings', $this->inertiaHeaders())
            ->assertJsonPath('component', 'SupportChatSettings');
    }

    public function test_menu_script_and_provided_config(): void
    {
        $this->startConversation();
        $this->actingAs($this->user('admin@example.com'));

        $json = json_encode($this->get('/nova/support-chat/settings', $this->inertiaHeaders())->json('props'));
        $this->assertStringContainsString('"apiBase":"\/nova-vendor\/support-chat"', $json);
        $this->assertStringNotContainsString('TESTTOKEN', $json);

        // Nova caches its menu JSON statically per process, so resolve the tool menu directly.
        $request = \Laravel\Nova\Http\Requests\NovaRequest::create('/nova');
        $request->setUserResolver(fn () => auth()->user());
        $menu = json_encode(SupportChatTool::make()->menu($request), JSON_UNESCAPED_SLASHES);

        $this->assertStringContainsString('Support Chat', $menu);
        $this->assertStringContainsString('support-chat-conversations', $menu);
        $this->assertStringContainsString('/support-chat/settings', $menu);
        $this->assertMatchesRegularExpression('/"badge":\{[^}]*"value":"1"/', $menu, 'Pending badge shows 1');

        // A user who may view but not manage does not see the settings link.
        SupportChat::authorizeUsing(fn ($user, $ability) => $ability === SupportChat::ABILITY_VIEW);
        $this->assertStringNotContainsString('/support-chat/settings', json_encode(SupportChatTool::make()->menu($request), JSON_UNESCAPED_SLASHES));

        $this->get('/nova-api/scripts/nova-support-chat')->assertOk();
        $this->get('/nova-api/styles/nova-support-chat')->assertOk();
    }
}
