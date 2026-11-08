# Nova Support Chat

A [Laravel Nova](https://nova.laravel.com) panel for [gmammadov/laravel-support-chat](https://github.com/GismatUL/laravel-support-chat): read, answer and close website chat conversations from Nova. Answers appear in the customer's chat widget and are mirrored to your Telegram support group.

Requires Nova 4.35+ or Nova 5, and PHP 8.2+.

## Installation

```bash
composer require gmammadov/nova-support-chat
php artisan support-chat:install
```

Then register the tool in `app/Providers/NovaServiceProvider.php`:

```php
use Gmammadov\NovaSupportChat\SupportChatTool;

public function tools()
{
    return [
        SupportChatTool::make(),
    ];
}
```

That's it. You get:

- **Support Chat** in the main menu, with a badge counting conversations that are waiting for an answer
- a **Conversations** resource (search, status filter, auto-refresh), read-only by design
- on each conversation, a **live thread** with a reply box (Ctrl/⌘+Enter to send) and a *Close conversation* button
- a **Close conversation** action for one or many conversations
- a **Settings** page (contact fields, auto-close, retention)

If you define your own `Nova::mainMenu()`, add the section yourself: `SupportChatTool::make()->menu($request)`.

## Who can use it

By default, every user who can access Nova (your `viewNova` gate) can view, answer and manage support chats. To narrow it, add this to a service provider:

```php
use Gmammadov\SupportChat\SupportChat;

SupportChat::authorizeUsing(fn ($user, string $ability) => match ($ability) {
    SupportChat::ABILITY_VIEW, SupportChat::ABILITY_REPLY => $user->hasRole('support') || $user->is_admin,
    SupportChat::ABILITY_MANAGE => $user->is_admin, // settings + delete
});
```

Behind the scenes:

- The panel's API (`/nova-vendor/support-chat/*`) sits behind Nova's own authentication and `viewNova` check, plus the `supportChat.*` gates on every route.
- The core package's `ConversationPolicy` makes Nova enforce the same rules on its own resource endpoints. Creating or editing conversations is always denied.
- The core package's standalone admin API (`/support-chat/admin`) is switched off when this package is installed, so there is no second entry point to secure. Set `disable_core_admin_routes` to `false` in `config/nova-support-chat.php` if you need it.

## Configuration

```bash
php artisan vendor:publish --tag=nova-support-chat-config
```

| Key | Default | |
|---|---|---|
| `register_resource` | `true` | Set to `false` to register your own subclass of `Gmammadov\NovaSupportChat\Nova\Conversation` |
| `disable_core_admin_routes` | `true` | See above |
| `poll_interval` | `4` | Seconds between refreshes of an open conversation |
| `pending_badge` | `true` | Menu badge with the number of waiting conversations |

## Development

The compiled assets in `dist/` are committed. To rebuild them:

```bash
npm install
npm run build
```

Vue is not bundled: the build uses the `window.Vue` that Nova 4 and 5 provide.

Running the tests needs access to Nova (a license, with composer credentials for nova.laravel.com):

```bash
composer install && composer test
```

## License

MIT
