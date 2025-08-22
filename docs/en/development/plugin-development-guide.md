# XBoard Plugin Development Guide

## 📦 Plugin Structure

Each plugin is an independent directory with the following structure:

```
plugins/
└── YourPlugin/               # Plugin directory (PascalCase naming)
    ├── Plugin.php           # Main plugin class (required)
    ├── config.json          # Plugin configuration (required)
    ├── routes/
    │   └── api.php          # API routes
    ├── Controllers/         # Controllers directory
    │   └── YourController.php
    ├── Commands/            # Artisan commands directory
    │   └── YourCommand.php
    └── README.md            # Documentation
```

## 🚀 Quick Start

### 1. Create Configuration File `config.json`

```json
{
    "name": "My Plugin",
    "code": "my_plugin", // Corresponds to plugin directory (lowercase + underscore)
    "version": "1.0.0",
    "description": "Plugin functionality description",
    "author": "Author Name",
    "require": {
        "xboard": ">=1.0.0" // Version not fully implemented yet
    },
    "config": {
        "api_key": {
            "type": "string",
            "default": "",
            "label": "API Key",
            "description": "API Key"
        },
        "timeout": {
            "type": "number",
            "default": 300,
            "label": "Timeout (seconds)",
            "description": "Timeout in seconds"
        }
    }
}
```

### 2. Create Main Plugin Class `Plugin.php`

```php
<?php

namespace Plugin\YourPlugin;

use App\Services\Plugin\AbstractPlugin;

class Plugin extends AbstractPlugin
{
    /**
     * Called when plugin starts
     */
    public function boot(): void
    {
        // Register frontend configuration hook
        $this->filter('guest_comm_config', function ($config) {
            $config['my_plugin_enable'] = true;
            $config['my_plugin_setting'] = $this->getConfig('api_key', '');
            return $config;
        });
    }
}
```

### 3. Create Controller

**Recommended approach: Extend PluginController**

```php
<?php

namespace Plugin\YourPlugin\Controllers;

use App\Http\Controllers\PluginController;
use Illuminate\Http\Request;

class YourController extends PluginController
{
    public function handle(Request $request)
    {
        // Get plugin configuration
        $apiKey = $this->getConfig('api_key');
        $timeout = $this->getConfig('timeout', 300);

        // Your business logic...

        return $this->success(['message' => 'Success']);
    }
}
```

### 4. Create Routes `routes/api.php`

```php
<?php

use Illuminate\Support\Facades\Route;
use Plugin\YourPlugin\Controllers\YourController;

Route::group([
    'prefix' => 'api/v1/your-plugin'
], function () {
    Route::post('/handle', [YourController::class, 'handle']);
});
```

## 🔧 Configuration Access

In controllers, you can easily access plugin configuration:

```php
// Get single configuration
$value = $this->getConfig('key', 'default_value');

// Get all configurations
$allConfig = $this->getConfig();

// Check if plugin is enabled
$enabled = $this->isPluginEnabled();
```

## 🎣 Hook System

### Popular Hooks (Recommended to follow)

XBoard has built-in hooks for many business-critical nodes. Plugin developers can flexibly extend through `filter` or `listen` methods. Here are the most commonly used and valuable hooks:

| Hook Name                 | Type   | Typical Parameters       | Description      |
| ------------------------- | ------ | ----------------------- | ---------------- |
| user.register.before      | action | Request                 | Before user registration |
| user.register.after       | action | User                    | After user registration |
| user.login.after          | action | User                    | After user login |
| user.password.reset.after | action | User                    | After password reset |
| order.cancel.before       | action | Order                   | Before order cancellation |
| order.cancel.after        | action | Order                   | After order cancellation |
| payment.notify.before     | action | method, uuid, request   | Before payment callback |
| payment.notify.verified   | action | array                   | Payment callback verification successful |
| payment.notify.failed     | action | method, uuid, request   | Payment callback verification failed |
| traffic.reset.after       | action | User                    | After traffic reset |
| ticket.create.after       | action | Ticket                  | After ticket creation |
| ticket.reply.user.after   | action | Ticket                  | After user replies to ticket |
| ticket.close.after        | action | Ticket                  | After ticket closure |

> ⚡️ The hook system will continue to expand. Developers can always follow this documentation and the `php artisan hook:list` command to get the latest supported hooks.

### Filter Hooks

Used to modify data:

```php
// In Plugin.php boot() method
$this->filter('guest_comm_config', function ($config) {
    // Add configuration for frontend
    $config['my_setting'] = $this->getConfig('setting');
    return $config;
});
```

### Action Hooks

Used to execute operations:

```php
$this->listen('user.created', function ($user) {
    // Operations after user creation
    $this->doSomething($user);
});
```

## 📝 Real Example: Telegram Login Plugin

Using TelegramLogin plugin as an example to demonstrate complete implementation:

**Main Plugin Class** (23 lines):

```php
<?php

namespace Plugin\TelegramLogin;

use App\Services\Plugin\AbstractPlugin;

class Plugin extends AbstractPlugin
{
    public function boot(): void
    {
        $this->filter('guest_comm_config', function ($config) {
            $config['telegram_login_enable'] = true;
            $config['telegram_login_domain'] = $this->getConfig('domain', '');
            $config['telegram_bot_username'] = $this->getConfig('bot_username', '');
            return $config;
        });
    }
}
```

**Controller** (extends PluginController):

```php
class TelegramLoginController extends PluginController
{
    public function telegramLogin(Request $request)
    {
        // Check plugin status
        if ($error = $this->beforePluginAction()) {
            return $error[1];
        }

        // Get configuration
        $botToken = $this->getConfig('bot_token');
        $timeout = $this->getConfig('auth_timeout', 300);

        // Business logic...

        return $this->success($result);
    }
}
```

## ⏰ Plugin Scheduled Tasks (Scheduler)

Plugins can register their own scheduled tasks by implementing the `schedule(Schedule $schedule)` method in the main class.

**Example:**

```php
use Illuminate\Console\Scheduling\Schedule;

class Plugin extends AbstractPlugin
{
    public function schedule(Schedule $schedule): void
    {
        // Execute every hour
        $schedule->call(function () {
            // Your scheduled task logic
            \Log::info('Plugin scheduled task executed');
        })->hourly();
    }
}
```

- Just implement the `schedule()` method in Plugin.php.
- All plugin scheduled tasks will be automatically scheduled by the main program.
- Supports all Laravel scheduler usage.

## 🖥️ Plugin Artisan Commands

Plugins can automatically register Artisan commands by creating command classes in the `Commands/` directory.

### Command Directory Structure

```
plugins/YourPlugin/
├── Commands/
│   ├── TestCommand.php      # Test command
│   ├── BackupCommand.php    # Backup command
│   └── CleanupCommand.php   # Cleanup command
```

### Create Command Class

**Example: TestCommand.php**

```php
<?php

namespace Plugin\YourPlugin\Commands;

use Illuminate\Console\Command;

class TestCommand extends Command
{
    protected $signature = 'your-plugin:test {action=ping} {--message=Hello}';
    protected $description = 'Test plugin functionality';

    public function handle(): int
    {
        $action = $this->argument('action');
        $message = $this->option('message');

        try {
            return match ($action) {
                'ping' => $this->ping($message),
                'info' => $this->showInfo(),
                default => $this->showHelp()
            };
        } catch (\Exception $e) {
            $this->error('Operation failed: ' . $e->getMessage());
            return 1;
        }
    }

    protected function ping(string $message): int
    {
        $this->info("✅ {$message}");
        return 0;
    }

    protected function showInfo(): int
    {
        $this->info('Plugin Information:');
        $this->table(
            ['Property', 'Value'],
            [
                ['Plugin Name', 'YourPlugin'],
                ['Version', '1.0.0'],
                ['Status', 'Enabled'],
            ]
        );
        return 0;
    }

    protected function showHelp(): int
    {
        $this->info('Usage:');
        $this->line('  php artisan your-plugin:test ping --message="Hello"  # Test');
        $this->line('  php artisan your-plugin:test info                    # Show info');
        return 0;
    }
}
```

### Automatic Command Registration

- ✅ Automatically register all commands in `Commands/` directory when plugin is enabled
- ✅ Command namespace automatically set to `Plugin\YourPlugin\Commands`
- ✅ Supports all Laravel command features (arguments, options, interaction, etc.)

### Usage Examples

```bash
# Test command
php artisan your-plugin:test ping --message="Hello World"

# Show information
php artisan your-plugin:test info

# View help
php artisan your-plugin:test --help
```

### Best Practices

1. **Command Naming**: Use `plugin-name:action` format, e.g., `telegram:test`
2. **Error Handling**: Wrap main logic with try-catch
3. **Return Values**: Return 0 for success, 1 for failure
4. **User Friendly**: Provide clear help information and error messages
5. **Type Declarations**: Use PHP 8.2 type declarations

## 🛠️ Development Tools

### Controller Base Class Selection

**Method 1: Extend PluginController (Recommended)**

- Automatic configuration access: `$this->getConfig()`
- Automatic status checking: `$this->beforePluginAction()`
- Unified error handling

**Method 2: Use HasPluginConfig Trait**

```php
use App\Http\Controllers\Controller;
use App\Traits\HasPluginConfig;

class YourController extends Controller
{
    use HasPluginConfig;

    public function handle()
    {
        $config = $this->getConfig('key');
        // ...
    }
}
```

### Configuration Types

Supported configuration types:

- `string` - String
- `number` - Number
- `boolean` - Boolean
- `json` - Array
- `yaml`

## 🎯 Best Practices

### 1. Concise Main Class

- Plugin main class should be as concise as possible
- Mainly used for registering hooks and routes
- Complex logic should be placed in controllers or services

### 2. Configuration Management

- Define all configuration items in `config.json`
- Use `$this->getConfig()` to access configuration
- Provide default values for all configurations

### 3. Route Design

- Use semantic route prefixes
- Place API routes in `routes/api.php`
- Place Web routes in `routes/web.php`

### 4. Error Handling

```php
public function handle(Request $request)
{
    // Check plugin status
    if ($error = $this->beforePluginAction()) {
        return $error[1];
    }

    try {
        // Business logic
        return $this->success($result);
    } catch (\Exception $e) {
        return $this->fail([500, $e->getMessage()]);
    }
}
```

## 🔍 Debugging Tips

### 1. Logging

```php
\Log::info('Plugin operation', ['data' => $data]);
\Log::error('Plugin error', ['error' => $e->getMessage()]);
```

### 2. Configuration Checking

```php
// Check required configuration
if (!$this->getConfig('required_key')) {
    return $this->fail([400, 'Missing configuration']);
}
```

### 3. Development Mode

```php
if (config('app.debug')) {
    // Detailed debug information for development environment
}
```

## 📋 Plugin Lifecycle

1. **Installation**: Validate configuration, register to database
2. **Enable**: Load plugin, register hooks and routes
3. **Running**: Handle requests, execute business logic

## 🎉 Summary

Based on TelegramLogin plugin practical experience:

- **Simplicity**: Main class only 23 lines, focused on core functionality
- **Practicality**: Extends PluginController, convenient configuration access
- **Maintainability**: Clear directory structure, standard development patterns
- **Extensibility**: Hook-based architecture, easy to extend functionality

Following this guide, you can quickly develop plugins with complete functionality and concise code! 🚀

## 🖥️ Complete Plugin Artisan Commands Guide

### Feature Highlights

✅ **Auto Registration**: Automatically register all commands in `Commands/` directory when plugin is enabled  
✅ **Namespace Isolation**: Each plugin's commands use independent namespaces  
✅ **Type Safety**: Support PHP 8.2 type declarations  
✅ **Error Handling**: Comprehensive exception handling and error messages  
✅ **Configuration Integration**: Commands can access plugin configuration  
✅ **Interaction Support**: Support user input and confirmation operations

### Real Case Demonstrations

#### 1. Telegram Plugin Commands

```bash
# Test Bot connection
php artisan telegram:test ping

# Send message
php artisan telegram:test send --message="Hello World"

# Get Bot information
php artisan telegram:test info
```

#### 2. TelegramExtra Plugin Commands

```bash
# Show all statistics
php artisan telegram-extra:stats all

# User statistics
php artisan telegram-extra:stats users

# JSON format output
php artisan telegram-extra:stats users --format=json
```

#### 3. Example Plugin Commands

```bash
# Basic usage
php artisan example:hello

# With arguments and options
php artisan example:hello Bear --message="Welcome!"
```

### Development Best Practices

#### 1. Command Naming Conventions

```php
// ✅ Recommended: Use plugin name as prefix
protected $signature = 'telegram:test {action}';
protected $signature = 'telegram-extra:stats {type}';
protected $signature = 'example:hello {name}';

// ❌ Avoid: Use generic names
protected $signature = 'test {action}';
protected $signature = 'stats {type}';
```

#### 2. Error Handling Pattern

```php
public function handle(): int
{
    try {
        // Main logic
        return $this->executeAction();
    } catch (\Exception $e) {
        $this->error('Operation failed: ' . $e->getMessage());
        return 1;
    }
}
```

#### 3. User Interaction

```php
// Get user input
$chatId = $this->ask('Please enter chat ID');

// Confirm operation
if (!$this->confirm('Are you sure you want to execute this operation?')) {
    $this->info('Operation cancelled');
    return 0;
}

// Choose operation
$action = $this->choice('Choose operation', ['ping', 'send', 'info']);
```

#### 4. Configuration Access

```php
// Access plugin configuration in commands
protected function getConfig(string $key, $default = null): mixed
{
    // Get plugin instance through PluginManager
    $plugin = app(\App\Services\Plugin\PluginManager::class)
        ->getEnabledPlugins()['example_plugin'] ?? null;

    return $plugin ? $plugin->getConfig($key, $default) : $default;
}
```

### Advanced Usage

#### 1. Multi-Command Plugins

```php
// One plugin can have multiple commands
plugins/YourPlugin/Commands/
├── BackupCommand.php      # Backup command
├── CleanupCommand.php     # Cleanup command
├── StatsCommand.php       # Statistics command
└── TestCommand.php        # Test command
```

#### 2. Inter-Command Communication

```php
// Share data between commands through cache or database
Cache::put('plugin:backup:progress', $progress, 3600);
$progress = Cache::get('plugin:backup:progress');
```

#### 3. Scheduled Task Integration

```php
// Call commands in plugin's schedule method
public function schedule(Schedule $schedule): void
{
    $schedule->command('your-plugin:backup')->daily();
    $schedule->command('your-plugin:cleanup')->weekly();
}
```

### Debugging Tips

#### 1. Command Testing

```bash
# View command help
php artisan your-plugin:command --help

# Verbose output
php artisan your-plugin:command --verbose

# Debug mode
php artisan your-plugin:command --debug
```

#### 2. Logging

```php
// Log in commands
Log::info('Plugin command executed', [
    'command' => $this->signature,
    'arguments' => $this->arguments(),
    'options' => $this->options()
]);
```

#### 3. Performance Monitoring

```php
// Record command execution time
$startTime = microtime(true);
// ... execution logic
$endTime = microtime(true);
$this->info("Execution time: " . round(($endTime - $startTime) * 1000, 2) . "ms");
```

### Common Issues

#### Q: Commands not showing in list?

A: Check if plugin is enabled and ensure `Commands/` directory exists and contains valid command classes.

#### Q: Command execution failed?

A: Check if command class namespace is correct and ensure it extends `Illuminate\Console\Command`.

#### Q: How to access plugin configuration?

A: Get plugin instance through `PluginManager`, then call `getConfig()` method.

#### Q: Can commands call other commands?

A: Yes, use `Artisan::call()` method to call other commands.

```php
Artisan::call('other-plugin:command', ['arg' => 'value']);
```

### Summary

The plugin command system provides powerful extension capabilities for XBoard:

- 🚀 **Development Efficiency**: Quickly create management commands
- 🔧 **Operational Convenience**: Automate daily operations
- 📊 **Monitoring Capability**: Real-time system status viewing
- 🛠️ **Debug Support**: Convenient problem troubleshooting tools

By properly using plugin commands, you can greatly improve system maintainability and user experience! 🎉
