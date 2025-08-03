# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Xboard is a Laravel-based proxy panel system for managing various protocol servers (v2ray, shadowsocks, trojan, etc.). It features a modern tech stack with Laravel 12 + Octane backend, React admin panel, and Vue3 user frontend.

## Development Commands

### Laravel/PHP Commands
```bash
# Install dependencies
composer install

# Laravel Artisan commands
php artisan migrate                    # Run database migrations
php artisan db:seed                   # Seed database
php artisan horizon                   # Start queue worker
php artisan octane:start             # Start Octane server
php artisan config:cache             # Cache configuration
php artisan route:cache              # Cache routes
php artisan view:cache               # Cache views
php artisan storage:link             # Create storage symlink

# Custom Xboard commands
php artisan xboard:install          # Install Xboard
php artisan xboard:update           # Update Xboard
php artisan xboard:rollback         # Rollback Xboard
php artisan xboard:statistics       # Generate statistics
```

### Testing & Quality
```bash
# Run tests
vendor/bin/phpunit

# Static analysis
vendor/bin/phpstan analyze

# Code formatting (if available)
vendor/bin/php-cs-fixer fix
```

### Docker Commands
```bash
# Build and run with Docker Compose
docker compose up -d

# Install with Docker (from README)
docker compose run -it --rm \
    -e ENABLE_SQLITE=true \
    -e ENABLE_REDIS=true \
    -e ADMIN_ACCOUNT=admin@demo.com \
    web php artisan xboard:install

# Restart services
docker compose restart
```

## Architecture Overview

### Backend Structure
- **Laravel 12 + Octane**: High-performance PHP framework with Swoole/RoadRunner
- **Multi-version API**: V1 (legacy) and V2 (modern) API controllers
- **Plugin System**: Extensible architecture with hooks and plugins
- **Protocol Support**: Multiple proxy protocols (Shadowsocks, Trojan, V2Ray, etc.)
- **Queue System**: Laravel Horizon for job processing

### Key Directories
- `app/Http/Controllers/V1/`: Legacy API controllers (Client, Guest, User, Server)
- `app/Http/Controllers/V2/`: Modern API controllers (Admin, User, Passport)
- `app/Services/`: Business logic layer (Auth, Payment, Order, etc.)
- `app/Models/`: Eloquent models for database entities
- `app/Plugins/`: Plugin system implementation
- `app/Protocols/`: Protocol-specific implementations
- `app/Payments/`: Payment gateway integrations
- `plugins/`: Third-party plugins

### Frontend Structure
- **Admin Panel**: React + Shadcn UI (`public/assets/admin/`)
- **User Frontend**: Vue3 + TypeScript + NaiveUI (`theme/` directory)
- **Theming**: Configurable themes in `theme/` with config.json files

### Database
- **Migrations**: Located in `database/migrations/`
- **Models**: All models prefixed with `v2_` in database
- **Seeders**: Database seeders for initial data

## Important Configuration

### Environment Variables
- `APP_ENV`: Application environment (production/development)
- `APP_DEBUG`: Debug mode toggle
- `ENABLE_REDIS`: Redis caching toggle
- `ENABLE_SQLITE`: SQLite support toggle
- `ADMIN_ACCOUNT`: Admin account for installation

### Key Config Files
- `config/octane.php`: Octane server configuration
- `config/horizon.php`: Queue worker configuration
- `config/database.php`: Database connections
- `compose.sample.yaml`: Docker Compose template

## Plugin System

Xboard has an extensible plugin architecture:
- Plugins located in `app/Plugins/` and `plugins/`
- Hook-based system for extending functionality
- Plugin configuration via `HasPluginConfig` trait
- Service providers for plugin registration

## Development Notes

### Code Organization
- Follow Laravel conventions and PSR standards
- Use service classes for business logic
- Implement proper error handling with custom exceptions
- Utilize Laravel's built-in features (middleware, requests, resources)

### Database Patterns
- All tables use `v2_` prefix
- Use Eloquent models with proper relationships
- Implement proper indexing for performance
- Use migrations for schema changes

### Security Considerations
- Admin paths are configurable and hashed
- CSRF protection enabled
- Input validation through Request classes
- Authentication via Laravel Sanctum

### Performance Optimizations
- Octane for improved PHP performance
- Redis caching where applicable
- Proper database indexing
- Queue jobs for heavy operations

## Common Tasks

### Adding New Features
1. Create appropriate controllers in V2 namespace
2. Add corresponding service classes
3. Update routes in `app/Http/Routes/V2/`
4. Add database migrations if needed
5. Update frontend components accordingly

### Running Single Tests
```bash
# Run specific test file
vendor/bin/phpunit tests/Feature/ExampleTest.php

# Run specific test method
vendor/bin/phpunit --filter testBasicExample

# Run tests with coverage
vendor/bin/phpunit --coverage-html coverage/
```

### Development Environment
```bash
# Start development server (requires Docker)
docker compose up -d

# Access application logs
docker compose logs -f web

# Execute artisan commands in container
docker compose exec web php artisan migrate

# Install Xboard fresh installation
docker compose run -it --rm \
    -e ENABLE_SQLITE=true \
    -e ENABLE_REDIS=true \
    -e ADMIN_ACCOUNT=admin@demo.com \
    web php artisan xboard:install
```

### Plugin Development
1. Create plugin class extending appropriate base
2. Implement required interfaces
3. Register hooks and services
4. Add configuration options
5. Test plugin functionality

### Theme Development
1. Create theme directory in `theme/`
2. Add `config.json` with theme metadata
3. Implement required blade templates
4. Add assets (CSS, JS, images)
5. Test theme switching functionality