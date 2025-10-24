# Docker Setup for Laravel Page Speed

This document explains how to use Docker to set up a development and testing environment for the Laravel Page Speed package.

## Prerequisites

- Docker
- Docker Compose

## Getting Started

### 1. Build and start the containers

```bash
docker-compose up -d
```

### 2. Install dependencies

```bash
docker-compose exec app composer install
```

### 3. Run tests

```bash
docker-compose exec app composer test
```

Or directly with PHPUnit:

```bash
docker-compose exec app vendor/bin/phpunit
```

### 4. Check code style

```bash
docker-compose exec app composer check-style
```

### 5. Fix code style

```bash
docker-compose exec app composer fix-style
```

## Useful Commands

### Access the container shell

```bash
docker-compose exec app bash
```

### Stop the containers

```bash
docker-compose down
```

### Rebuild the containers

```bash
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

### View logs

```bash
docker-compose logs -f app
```

### Run specific tests

```bash
docker-compose exec app vendor/bin/phpunit --filter TestClassName
```

## Container Services

- **app**: PHP 8.2 CLI container with Composer and all dependencies

> **Note**: This package only processes HTML/CSS/JS responses and does not require a database.

## Troubleshooting

### Permission issues

If you encounter permission issues:

```bash
docker-compose exec app chmod -R 755 vendor
```

### Clear cache

```bash
docker-compose exec app composer clear-cache
```

### Reinstall dependencies

```bash
docker-compose exec app rm -rf vendor
docker-compose exec app composer install
```
