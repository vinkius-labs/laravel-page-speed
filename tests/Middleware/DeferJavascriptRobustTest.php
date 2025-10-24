<?php

namespace VinkiusLabs\LaravelPageSpeed\Test\Middleware;

use VinkiusLabs\LaravelPageSpeed\Middleware\DeferJavascript;
use VinkiusLabs\LaravelPageSpeed\Test\TestCase;

class DeferJavascriptRobustTest extends TestCase
{
    protected function getMiddleware()
    {
        $this->middleware = new DeferJavascript();
    }

    public function test_adds_defer_to_external_scripts(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <script src="https://cdn.example.com/library.js"></script>
</head>
<body>
</body>
</html>
HTML;

        $middleware = new DeferJavascript();
        $actual = $middleware->apply($html);

        // Note: Regex adds an extra space, but this is valid HTML
        $this->assertStringContainsString('src="https://cdn.example.com/library.js" defer>', $actual);
        $this->assertStringContainsString('defer>', $actual);
    }

    public function test_preserves_existing_defer_attribute(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <script src="https://cdn.example.com/library.js" defer></script>
</head>
<body>
</body>
</html>
HTML;

        $middleware = new DeferJavascript();
        $actual = $middleware->apply($html);

        // Should not add duplicate defer
        $this->assertStringContainsString('<script src="https://cdn.example.com/library.js" defer>', $actual);
        $this->assertEquals(1, substr_count($actual, 'defer'));
    }

    public function test_respects_data_pagespeed_no_defer_attribute(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <script src="https://cdn.example.com/critical.js" data-pagespeed-no-defer></script>
    <script src="https://cdn.example.com/normal.js"></script>
</head>
<body>
</body>
</html>
HTML;

        $middleware = new DeferJavascript();
        $actual = $middleware->apply($html);

        // Should NOT add defer to script with data-pagespeed-no-defer
        $this->assertStringContainsString('src="https://cdn.example.com/critical.js" data-pagespeed-no-defer>', $actual);
        $this->assertStringNotContainsString('critical.js" data-pagespeed-no-defer defer', $actual);
        
        // Should add defer to normal script
        $this->assertStringContainsString('src="https://cdn.example.com/normal.js" defer>', $actual);
    }

    public function test_does_not_add_defer_to_inline_scripts(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <script>
        console.log('inline script');
    </script>
</head>
<body>
</body>
</html>
HTML;

        $middleware = new DeferJavascript();
        $actual = $middleware->apply($html);

        // Should NOT add defer to inline scripts (they don't have src attribute)
        $this->assertStringNotContainsString('<script defer>', $actual);
        $this->assertStringContainsString('<script>', $actual);
    }

    public function test_handles_script_with_type_attribute(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <script type="text/javascript" src="https://cdn.example.com/library.js"></script>
    <script type="module" src="https://cdn.example.com/module.js"></script>
</head>
<body>
</body>
</html>
HTML;

        $middleware = new DeferJavascript();
        $actual = $middleware->apply($html);

        $this->assertStringContainsString('type="text/javascript" src="https://cdn.example.com/library.js" defer>', $actual);
        $this->assertStringContainsString('type="module" src="https://cdn.example.com/module.js" defer>', $actual);
    }

    public function test_handles_script_with_async_attribute(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <script src="https://cdn.example.com/async-library.js" async></script>
    <script src="https://cdn.example.com/normal-library.js"></script>
</head>
<body>
</body>
</html>
HTML;

        $middleware = new DeferJavascript();
        $actual = $middleware->apply($html);

        // Scripts with async should get defer as well (browser will use async if both are present)
        $this->assertStringContainsString('async-library.js" async defer>', $actual);
        $this->assertStringContainsString('normal-library.js" defer>', $actual);
    }

    public function test_handles_script_with_integrity_and_crossorigin(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-test123" crossorigin="anonymous"></script>
</head>
<body>
</body>
</html>
HTML;

        $middleware = new DeferJavascript();
        $actual = $middleware->apply($html);

        $this->assertStringContainsString('integrity="sha256-test123"', $actual);
        $this->assertStringContainsString('crossorigin="anonymous"', $actual);
        $this->assertStringContainsString('defer>', $actual);
    }

    public function test_handles_multiple_scripts_correctly(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <script src="https://cdn.example.com/jquery.js"></script>
    <script src="https://cdn.example.com/bootstrap.js"></script>
    <script src="https://cdn.example.com/app.js" data-pagespeed-no-defer></script>
    <script>
        console.log('inline');
    </script>
    <script src="https://cdn.example.com/analytics.js" defer></script>
</head>
<body>
</body>
</html>
HTML;

        $middleware = new DeferJavascript();
        $actual = $middleware->apply($html);

        // Should add defer to first two
        $this->assertStringContainsString('src="https://cdn.example.com/jquery.js" defer>', $actual);
        $this->assertStringContainsString('src="https://cdn.example.com/bootstrap.js" defer>', $actual);
        
        // Should NOT add defer to no-defer script
        $this->assertStringContainsString('src="https://cdn.example.com/app.js" data-pagespeed-no-defer>', $actual);
        
        // Should NOT modify inline script (no src attribute means no defer added)
        $this->assertStringContainsString("console.log('inline');", $actual);
        $this->assertStringNotContainsString("console.log('inline');" . ' defer', $actual);
        
        // Should preserve existing defer
        $this->assertStringContainsString('src="https://cdn.example.com/analytics.js" defer>', $actual);
    }

    public function test_preserves_script_order_and_content(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <title>Test</title>
    <script src="/js/library.js"></script>
    <script>
        var config = {
            apiUrl: "https://api.example.com"
        };
    </script>
    <script src="/js/app.js"></script>
</head>
<body>
    <h1>Content</h1>
</body>
</html>
HTML;

        $middleware = new DeferJavascript();
        $actual = $middleware->apply($html);

        // Check order is preserved
        $posLibrary = strpos($actual, 'src="/js/library.js" defer>');
        $posInline = strpos($actual, 'var config = {');
        $posApp = strpos($actual, 'src="/js/app.js" defer>');
        
        $this->assertNotFalse($posLibrary, 'Library script should be found');
        $this->assertNotFalse($posInline, 'Inline script should be found');
        $this->assertNotFalse($posApp, 'App script should be found');
        
        $this->assertLessThan($posInline, $posLibrary, 'Library script should come before inline script');
        $this->assertLessThan($posApp, $posInline, 'Inline script should come before app script');
        
        // Check content is preserved
        $this->assertStringContainsString('apiUrl: "https://api.example.com"', $actual);
        $this->assertStringContainsString('<h1>Content</h1>', $actual);
    }

    public function test_handles_glightbox_scenario_from_issue_173(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <title>GLightbox Test</title>
    <script src="https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js"></script>
    <script type="text/javascript">
        const lightbox = GLightbox({
            touchNavigation: true,
            loop: false,
            closeEffect: 'fade'
        });
    </script>
</head>
<body>
    <a href="image.jpg" class="glightbox">
        <img src="thumb.jpg" alt="Image">
    </a>
</body>
</html>
HTML;

        $middleware = new DeferJavascript();
        $actual = $middleware->apply($html);

        // External script should get defer
        $this->assertStringContainsString('src="https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js" defer>', $actual);
        
        // Inline script should NOT get defer (it doesn't have src)
        $this->assertStringContainsString('<script type="text/javascript">', $actual);
        $this->assertStringContainsString('const lightbox = GLightbox({', $actual);
        
        // Note: This test documents the current behavior
        // The issue is that with defer, GLightbox won't be defined when inline script runs
        // Users need to wrap inline code in DOMContentLoaded or use data-pagespeed-no-defer
    }

    public function test_handles_json_ld_script_type(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "url": "https://example.com"
    }
    </script>
    <script src="https://cdn.example.com/app.js"></script>
</head>
<body>
</body>
</html>
HTML;

        $middleware = new DeferJavascript();
        $actual = $middleware->apply($html);

        // JSON-LD should NOT get defer (it's inline, no src)
        $this->assertStringContainsString('<script type="application/ld+json">', $actual);
        
        // External script should get defer
        $this->assertStringContainsString('src="https://cdn.example.com/app.js" defer>', $actual);
    }

    public function test_handles_nomodule_attribute(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <script type="module" src="/js/modern.js"></script>
    <script nomodule src="/js/legacy.js"></script>
</head>
<body>
</body>
</html>
HTML;

        $middleware = new DeferJavascript();
        $actual = $middleware->apply($html);

        $this->assertStringContainsString('type="module" src="/js/modern.js" defer>', $actual);
        $this->assertStringContainsString('nomodule src="/js/legacy.js" defer>', $actual);
    }

    public function test_handles_empty_src_attribute(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <script src=""></script>
    <script src="https://cdn.example.com/valid.js"></script>
</head>
<body>
</body>
</html>
HTML;

        $middleware = new DeferJavascript();
        $actual = $middleware->apply($html);

        // Empty src should still get defer (browser will ignore it anyway)
        $this->assertStringContainsString('src="" defer>', $actual);
        $this->assertStringContainsString('src="https://cdn.example.com/valid.js" defer>', $actual);
    }

    public function test_handles_relative_and_absolute_paths(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <script src="/js/local.js"></script>
    <script src="js/relative.js"></script>
    <script src="https://cdn.example.com/external.js"></script>
    <script src="//cdn.example.com/protocol-relative.js"></script>
</head>
<body>
</body>
</html>
HTML;

        $middleware = new DeferJavascript();
        $actual = $middleware->apply($html);

        $this->assertStringContainsString('src="/js/local.js" defer>', $actual);
        $this->assertStringContainsString('src="js/relative.js" defer>', $actual);
        $this->assertStringContainsString('src="https://cdn.example.com/external.js" defer>', $actual);
        $this->assertStringContainsString('src="//cdn.example.com/protocol-relative.js" defer>', $actual);
    }

    public function test_handles_script_with_single_quotes(): void
    {
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <script src='https://cdn.example.com/library.js'></script>
    <script src='https://cdn.example.com/app.js' type='text/javascript'></script>
</head>
<body>
</body>
</html>
HTML;

        $middleware = new DeferJavascript();
        $actual = $middleware->apply($html);

        $this->assertStringContainsString("src='https://cdn.example.com/library.js' defer>", $actual);
        $this->assertStringContainsString("src='https://cdn.example.com/app.js' type='text/javascript' defer>", $actual);
    }

    public function test_handles_script_without_quotes(): void
    {
        $html = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <script src=/js/app.js></script>
</head>
<body>
</body>
</html>
HTML;

        $middleware = new DeferJavascript();
        $actual = $middleware->apply($html);

        $this->assertStringContainsString('src=/js/app.js defer>', $actual);
    }

    public function test_performance_with_many_scripts(): void
    {
        // Generate HTML with 100 scripts
        $scripts = '';
        for ($i = 1; $i <= 100; $i++) {
            $scripts .= "<script src=\"https://cdn.example.com/script{$i}.js\"></script>\n";
        }
        
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    {$scripts}
</head>
<body>
</body>
</html>
HTML;

        $middleware = new DeferJavascript();
        
        $startTime = microtime(true);
        $actual = $middleware->apply($html);
        $endTime = microtime(true);
        
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        
        // Should complete in less than 100ms for 100 scripts
        $this->assertLessThan(100, $executionTime, 'Processing 100 scripts should take less than 100ms');
        
        // Verify all scripts got defer
        $this->assertEquals(100, substr_count($actual, 'defer>'));
    }
}
