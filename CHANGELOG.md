# Changelog

All notable changes to `laravel-page-speed` will be documented in this file.

## [Unreleased]

## [4.4.0] - 2026-02-21

### Added

- ✅ Laravel 13.x support (#215 by @laravel-shift)
- ✅ PHPUnit 12.x support (`^12.5.12`)
- ✅ Orchestra Testbench 11.x support

### Changed

- 📦 Updated `illuminate/support` constraint to `^10.0 || ^11.0 || ^12.0 || ^13.0`
- 📦 Updated `phpunit/phpunit` constraint to `^10.5 || ^11.0 || ^12.5.12`
- 📦 Updated `orchestra/testbench` constraint to `^8.0 || ^9.0 || ^10.0 || ^11.0`
- 🔄 CI/CD testing matrix expanded to include Laravel 13 on PHP 8.3
- 📝 Updated documentation references to include Laravel 13

## [4.3.2] - 2025-11-15

### Fixed

- 🐛 **InlineCss Middleware**: Fixed regex pattern to prevent matching framework-specific class attributes (Issues #75, #133, #154)
    - Changed from `/class="(.*?)"/` to `/(?<![-:])class="(.*?)"/i` using negative lookbehind
    - Now correctly ignores `ng-class` (AngularJS), `:class` (Alpine.js), `v-bind:class` (Vue.js)
    - Horizon dashboard now works correctly with InlineCss (Issue #133)
    - AngularJS applications with `ng-class` work correctly (Issue #75)
    - Alpine.js `:class` shorthand works correctly (Issue #154)
    - Vue.js `v-bind:class` works correctly

### Added

- ✅ New test suite `InlineCssJavaScriptFrameworksTest` with 7 comprehensive tests (42 assertions)
- ✅ Tests for AngularJS `ng-class` compatibility
- ✅ Tests for Alpine.js `:class` shorthand compatibility
- ✅ Tests for Vue.js `v-bind:class` compatibility
- ✅ Tests for mixed framework scenarios

## [3.0.0] - 2025-01-24

### ⚠️ BREAKING CHANGES

- **PHP Requirements**: Minimum PHP version increased to 8.2 (was 8.0)
- **Laravel Support**: Removed support for Laravel 6.x, 7.x, 8.x, and 9.x
- **Dependencies**: Updated minimum versions for all dependencies

### Added

- ✅ Laravel 12.x support
- ✅ Laravel 11.x support
- ✅ PHPUnit 11.x support
- ✅ PHP 8.3 support
- ✅ New tests for ServiceProvider (5 tests)
- ✅ New tests for HtmlSpecs entity (4 tests)
- ✅ GitHub Actions workflow for automated testing

### Changed

- 📦 Updated Laravel support to ^10.0 || ^11.0 || ^12.0
- 📦 Updated PHP requirement to ^8.2 || ^8.3
- 📦 Updated PHPUnit to ^10.5 || ^11.0
- 📦 Updated Orchestra Testbench to ^8.0 || ^9.0 || ^10.0
- 📦 Updated Mockery to ^1.6
- 🧪 Migrated all tests from `@test` annotation to `test_*` method naming convention
- 🧹 Removed deprecated `$defer` property from ServiceProvider
- ✨ Added void return types to ServiceProvider methods
- 📋 Updated phpunit.xml.dist to PHPUnit 11.5 schema

### Removed

- ❌ Laravel 6.x, 7.x, 8.x, 9.x support (use v2.x for older Laravel versions)
- ❌ PHP 8.0 and 8.1 support (use v2.x for PHP 8.0/8.1)
- ❌ Deprecated `$defer` property from ServiceProvider

### Testing

- 🎯 Test coverage increased from 24 to 33 tests (37.5% increase)
- ✅ All 33 tests passing with 125 assertions
- 🔄 CI/CD testing across PHP 8.2/8.3 with Laravel 10/11/12/13

### Migration Guide

#### From v2.x to v3.x

**Requirements:**

- Update PHP to 8.2 or 8.3
- Update Laravel to 10.x, 11.x, or 12.x

**Steps:**

1. Update your `composer.json`:

```json
{
    "require": {
        "php": "^8.2 || ^8.3",
        "laravel/framework": "^10.0 || ^11.0 || ^12.0",
        "vinkius-labs/laravel-page-speed": "^3.0"
    }
}
```

2. Run composer update:

```bash
composer update vinkius-labs/laravel-page-speed
```

3. Clear config cache:

```bash
php artisan config:clear
php artisan cache:clear
```

**Breaking Changes:**

- If you're extending the `ServiceProvider` class, remove the `$defer` property
- If you have custom middleware extending package middleware, ensure compatibility with Laravel 10+

**Staying on v2.x:**

If you need to stay on Laravel 6-9 or PHP 8.0/8.1, use version constraint:

```json
{
    "require": {
        "vinkius-labs/laravel-page-speed": "^2.1"
    }
}
```

---

## [2.1.0] - Previous Release

See previous releases for v2.x changelog.
