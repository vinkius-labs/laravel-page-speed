<p align="center">
    <img width="400" src="https://raw.githubusercontent.com/vinkius-labs/laravel-page-speed/master/art/logo.png" alt="Laravel Page Speed logo" />
</p>

<p align="center">
<a href="https://travis-ci.org/vinkius-labs/laravel-page-speed"><img src="https://travis-ci.org/vinkius-labs/laravel-page-speed.svg?branch=master" alt="Build Status"></a>
<a href="https://packagist.org/packages/vinkius-labs/laravel-page-speed"><img src="https://poser.pugx.org/vinkius-labs/laravel-page-speed/version" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/vinkius-labs/laravel-page-speed"><img src="https://poser.pugx.org/vinkius-labs/laravel-page-speed/downloads" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/vinkius-labs/laravel-page-speed"><img src="https://poser.pugx.org/vinkius-labs/laravel-page-speed/license" alt="License"></a>
</p>

# Laravel Page Speed

Simple package to minify HTML output on demand which results in a 35%+ optimization. Laravel Page Speed was created by [Renato Marinho][link-author], and currently maintained by [João Roberto P. Borges][link-maintainer], [Lucas Mesquita Borges][link-maintainer-2] and [Renato Marinho][link-author].

## Installation

> **Requires:**
- **[PHP 7.2.5+](https://php.net/releases/)**
- **[Laravel 6.0+](https://github.com/laravel/laravel)**

You can install the package via composer:

```sh
composer require vinkius-labs/laravel-page-speed
```

This package supports Laravel [Package Discovery][link-package-discovery].

### Publish configuration file

 `php artisan vendor:publish --provider="VinkiusLabs\LaravelPageSpeed\ServiceProvider"`

## Do not forget to register middlewares

Next, the `\VinkiusLabs\LaravelPageSpeed\Middleware\CollapseWhitespace::class` and other middleware must be registered in the kernel, for example:

```php
//app/Http/Kernel.php

protected $middleware = [
    ...
    \VinkiusLabs\LaravelPageSpeed\Middleware\InlineCss::class,
    \VinkiusLabs\LaravelPageSpeed\Middleware\ElideAttributes::class,
    \VinkiusLabs\LaravelPageSpeed\Middleware\InsertDNSPrefetch::class,
    \VinkiusLabs\LaravelPageSpeed\Middleware\RemoveComments::class,
    //\VinkiusLabs\LaravelPageSpeed\Middleware\TrimUrls::class, 
    //\VinkiusLabs\LaravelPageSpeed\Middleware\RemoveQuotes::class,
    \VinkiusLabs\LaravelPageSpeed\Middleware\CollapseWhitespace::class, // Note: This middleware invokes "RemoveComments::class" before it runs.
    \VinkiusLabs\LaravelPageSpeed\Middleware\DeferJavascript::class,
]
```

## Middlewares Details

### \VinkiusLabs\LaravelPageSpeed\Middleware\RemoveComments::class

The **RemoveComments::class** filter eliminates HTML, JS and CSS comments.
The filter reduces the transfer size of HTML files by removing the comments. Depending on the HTML file, this filter can significantly reduce the number of bytes transmitted on the network.

### \VinkiusLabs\LaravelPageSpeed\Middleware\CollapseWhitespace::class

The **CollapseWhitespace::class** filter reduces bytes transmitted in an HTML file by removing unnecessary whitespace.
This middleware invoke **RemoveComments::class** filter before executation.

> **Note**: Do not register the "RemoveComments::class" filter with it. Because it will be called automatically by "CollapseWhitespace::class"

> **Important**: Whitespace is automatically **preserved** inside `<pre>`, `<code>`, and `<textarea>` tags to maintain proper formatting for code blocks and user input fields. This makes it safe to use on blogs, documentation sites, and technical content. (Issue [#170](https://github.com/vinkius-labs/laravel-page-speed/issues/170))

> **✅ Livewire & Filament Compatible**: Fully compatible with [Laravel Livewire](https://laravel-livewire.com/) and [Filament](https://filamentphp.com/). The middleware preserves the necessary spacing between HTML tags to ensure `wire:*` directives and Alpine.js `x-*` attributes work correctly. (Issue [#165](https://github.com/vinkius-labs/laravel-page-speed/issues/165))

#### Before

![Before of Laravel Page Speed][link-before]

#### After

![After of Laravel Page Speed][link-after]

### \VinkiusLabs\LaravelPageSpeed\Middleware\RemoveQuotes::class

The **RemoveQuotes::class** filter eliminates unnecessary quotation marks from HTML attributes. While required by the various HTML specifications, browsers permit their omission when the value of an attribute is composed of a certain subset of characters (alphanumerics and some punctuation characters).

Quote removal produces a modest savings in byte count on most pages.

### \VinkiusLabs\LaravelPageSpeed\Middleware\ElideAttributes::class

The **ElideAttributes::class** filter reduces the transfer size of HTML files by removing attributes from tags when the specified value is equal to the default value for that attribute. This can save a modest number of bytes, and may make the document more compressible by canonicalizing the affected tags.

### \VinkiusLabs\LaravelPageSpeed\Middleware\InsertDNSPrefetch::class

The **InsertDNSPrefetch::class** filter Injects <link rel="dns-prefetch" href="//www.example.com"> tags in the HEAD to enable the browser to do DNS prefetching.

DNS resolution time varies from <1ms for locally cached results, to hundreds of milliseconds due to the cascading nature of DNS. This can contribute significantly towards total page load time. This filter reduces DNS lookup time by providing hints to the browser at the beginning of the HTML, which allows the browser to pre-resolve DNS for resources on the page.

 ### ⚠️ \VinkiusLabs\LaravelPageSpeed\Middleware\TrimUrls::class,

The **TrimUrls::class** filter trims URLs by resolving them by making them relative to the base URL for the page.

> **Warning**: **TrimUrls::class** is considered **medium risk**. It can cause problems if it uses the wrong base URL. This can happen, for example, if you serve HTML that will be pasted verbatim into other HTML pages. If URLs are trimmed on the first page, they will be incorrect for the page they are inserted into. In this case, just disable the middleware.

### \VinkiusLabs\LaravelPageSpeed\Middleware\InlineCss::class

The **InlineCss::class** filter transforms the inline "style" attribute of tags into classes by moving the CSS to the header.

### \VinkiusLabs\LaravelPageSpeed\Middleware\DeferJavascript::class

Defers the execution of javascript in the HTML.

> If necessary cancel deferring in some script, use `data-pagespeed-no-defer` as script attribute to cancel deferring.

<hr>

## Configuration

After installing package, you may need to configure some options.

### Disable Service

You would probably like to set up the local environment to get a readable output.

```php
//config/laravel-page-speed.php

//Set this field to false to disable the laravel page speed service.
'enable' => env('LARAVEL_PAGE_SPEED_ENABLE', true),
```
### Skip routes

You would probably like to configure the package to skip some routes.

```php
//config/laravel-page-speed.php

//You can use * as wildcard.
'skip' => [
    // Development/Debug Tools (automatically skipped by default)
    '_debugbar/*',      // Laravel Debugbar
    'horizon/*',        // Laravel Horizon  
    '_ignition/*',      // Laravel Ignition (error pages)
    'telescope/*',      // Laravel Telescope
    'clockwork/*',      // Clockwork
    
    // Binary/Document Files
    '*.pdf', //Ignore all routes with final .pdf
    '*/downloads/*',//Ignore all routes that contain 'downloads'
    'assets/*', // Ignore all routes with the 'assets' prefix
];
```

By default this field comes configured with some options, so feel free to configure according to your needs...

> *Notice*: This package skip automatically 'binary' and 'streamed' responses. See [File Downloads][link-file-download].

> **✅ Development Tools Compatible**: Development and debugging tools like **Laravel Debugbar**, **Horizon**, **Telescope**, **Ignition**, and **Clockwork** are automatically skipped by default, ensuring they work correctly without interference from HTML minification. (Issue [#164](https://github.com/vinkius-labs/laravel-page-speed/issues/164))

> **⚠️ Important for Custom Routes**: If you've customized the routes for debug tools in your application (e.g., Horizon at `/admin/horizon` instead of `/horizon`), you MUST update the skip patterns in your config file to match your custom routes. For example:
> ```php
> 'skip' => [
>     'admin/horizon/*',      // Custom Horizon route
>     'debug/telescope/*',    // Custom Telescope route
>     '_debugbar/*',          // Debugbar (usually fixed)
>     // ... other routes
> ],
> ```
> See your `HorizonServiceProvider`, `TelescopeServiceProvider`, or other debug tool configurations for custom path settings.

## Testing

```sh
$ composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Contributors

- [Caneco](https://twitter.com/caneco) (for the logo)
- [All Contributors][link-contributors]

## Inspiration

#### Mod Page Speed (https://www.modpagespeed.com/)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[link-before]: https://i.imgur.com/cN3MWYh.png
[link-after]: https://i.imgur.com/IKWKLkL.png
[link-author]: https://github.com/renatomarinho
[link-maintainer]: https://github.com/joaorobertopb
[link-maintainer-2]: https://github.com/lucasMesquitaBorges
[link-contributors]: ../../contributors
[link-file-download]: https://laravel.com/docs/6.0/responses#file-downloads
[link-package-discovery]: https://laravel.com/docs/6.0/packages#package-discovery
