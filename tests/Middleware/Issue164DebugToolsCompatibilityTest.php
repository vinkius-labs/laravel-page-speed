<?php

namespace VinkiusLabs\LaravelPageSpeed\Test\Middleware;

use VinkiusLabs\LaravelPageSpeed\Test\TestCase;
use VinkiusLabs\LaravelPageSpeed\Middleware\CollapseWhitespace;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class Issue164DebugToolsCompatibilityTest extends TestCase
{
    /**
     * Test Issue #164: Debug tools compatibility
     * 
     * Problem: Laravel Page Speed was processing routes for development tools like:
     * - Laravel Debugbar
     * - Laravel Horizon
     * - Laravel Ignition (error pages)
     * - Telescope
     * - Clockwork
     * 
     * This broke these tools because the minification/optimization broke their
     * JavaScript, CSS, and HTML structure.
     * 
     * Solution: Add these routes to the default skip list in config
     */

    protected function getMiddleware()
    {
        $this->middleware = new CollapseWhitespace();
    }

    public function test_debugbar_routes_should_be_skipped(): void
    {
        $config = include __DIR__ . '/../../config/laravel-page-speed.php';

        $this->assertContains(
            '_debugbar/*',
            $config['skip'],
            'Debugbar routes should be in default skip list (Issue #164)'
        );
    }

    public function test_horizon_routes_should_be_skipped(): void
    {
        $config = include __DIR__ . '/../../config/laravel-page-speed.php';

        $this->assertContains(
            'horizon/*',
            $config['skip'],
            'Horizon routes should be in default skip list (Issue #164)'
        );
    }

    public function test_ignition_routes_should_be_skipped(): void
    {
        $config = include __DIR__ . '/../../config/laravel-page-speed.php';

        $this->assertContains(
            '_ignition/*',
            $config['skip'],
            'Ignition routes should be in default skip list (Issue #164)'
        );
    }

    public function test_telescope_routes_should_be_skipped(): void
    {
        $config = include __DIR__ . '/../../config/laravel-page-speed.php';

        $this->assertContains(
            'telescope/*',
            $config['skip'],
            'Telescope routes should be in default skip list (Issue #164)'
        );
    }

    public function test_clockwork_routes_should_be_skipped(): void
    {
        $config = include __DIR__ . '/../../config/laravel-page-speed.php';

        $this->assertContains(
            'clockwork/*',
            $config['skip'],
            'Clockwork routes should be in default skip list (Issue #164)'
        );
    }

    /**
     * Test: Verify debugbar JavaScript is not broken
     */
    public function test_debugbar_javascript_structure_preserved(): void
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <script>
        // Debugbar initialization
        var PhpDebugBar = {
            init: function() {
                this.loadConfig();
            },
            loadConfig: function() {
                var config = { relevance: 10 };
                return config;
            }
        };
        
        jQuery(function($) {
            PhpDebugBar.init();
        });
    </script>
</head>
<body>
    <div class="phpdebugbar">
        <div class="phpdebugbar-widgets"></div>
    </div>
</body>
</html>';

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        // Critical: PhpDebugBar object should remain valid
        $this->assertStringContainsString('var PhpDebugBar', $result);
        $this->assertStringContainsString('PhpDebugBar.init', $result);

        // Ensure regex patterns in JS are not broken
        $this->assertStringContainsString('relevance: 10', $result);

        // jQuery should be referenced correctly
        $this->assertStringContainsString('jQuery(function($)', $result);
    }

    /**
     * Test: Ignition error page structure
     */
    public function test_ignition_error_page_structure(): void
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <script>
        window.ignite = function(config) {
            var pattern = /,{relevance:10}/;
            return pattern.test(config);
        };
    </script>
</head>
<body>
    <div id="ignition-error-page">
        <pre class="error-trace">
Error trace here
        </pre>
    </div>
</body>
</html>';

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        // Ignition function should work
        $this->assertStringContainsString('window.ignite', $result);

        // Regex patterns should not be broken by comment removal
        $this->assertStringContainsString('relevance:10', $result);

        // Error trace formatting should be preserved (inside <pre>)
        $this->assertStringContainsString('<pre class="error-trace">
Error trace here
        </pre>', $result);
    }

    /**
     * Test: Horizon dashboard structure
     */
    public function test_horizon_dashboard_structure(): void
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <title>Horizon - Dashboard</title>
</head>
<body>
    <div id="horizon">
        <div class="horizon-dashboard">
            <pre class="job-payload">
{
    "job": "ProcessPodcast",
    "data": {}
}
            </pre>
        </div>
    </div>
</body>
</html>';

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        // Horizon structure should remain
        $this->assertStringContainsString('id="horizon"', $result);

        // Job payload should preserve formatting
        $this->assertStringContainsString('<pre class="job-payload">
{
    "job": "ProcessPodcast",
    "data": {}
}
            </pre>', $result);
    }

    /**
     * Test: Configuration documentation for users
     */
    public function test_config_has_debug_tools_documentation(): void
    {
        $configFile = file_get_contents(__DIR__ . '/../../config/laravel-page-speed.php');

        // Verify the config file mentions debug tools
        $this->assertStringContainsString(
            'Development/Debug Tools',
            $configFile,
            'Config should document debug tools skip pattern'
        );

        $this->assertStringContainsString(
            'Issue #164',
            $configFile,
            'Config should reference Issue #164'
        );
    }

    /**
     * Test: Real-world debugbar HTML structure
     */
    public function test_real_world_debugbar_html(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
    <div class="content">
        <h1>My Page</h1>
    </div>
    
    <div class="phpdebugbar phpdebugbar-openhandler" data-renderid="1234">
        <div class="phpdebugbar-header">
            <div class="phpdebugbar-header-left">
                <a class="phpdebugbar-tab" href="#phpdebugbar-messages">
                    Messages <span class="phpdebugbar-badge">3</span>
                </a>
            </div>
        </div>
        <div class="phpdebugbar-body">
            <div class="phpdebugbar-panel">
                <pre>
Query: SELECT * FROM users
Time: 15.2ms
                </pre>
            </div>
        </div>
    </div>
    
    <script type="text/javascript">
        var phpdebugbar = new PhpDebugBar.DebugBar();
        phpdebugbar.addTab("messages", new PhpDebugBar.Widgets.MessagesWidget());
    </script>
</body>
</html>';

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        // Debugbar structure should be intact
        $this->assertStringContainsString('class="phpdebugbar', $result);
        $this->assertStringContainsString('data-renderid="1234"', $result);

        // Query formatting should be preserved
        $this->assertStringContainsString('<pre>
Query: SELECT * FROM users
Time: 15.2ms
                </pre>', $result);

        // JavaScript should work
        $this->assertStringContainsString('var phpdebugbar', $result);
        $this->assertStringContainsString('new PhpDebugBar.DebugBar()', $result);
    }

    /**
     * Test that custom routes can be configured
     * 
     * Important: If users customize their debug tool routes (e.g., '/admin/horizon'),
     * they must update the skip patterns in config/laravel-page-speed.php
     */
    public function test_custom_routes_can_be_configured(): void
    {
        // Simulate custom Horizon route
        $request = Request::create('/admin/horizon/dashboard', 'GET');
        $response = new Response('<html><body>Custom Horizon</body></html>');

        // Configure custom skip pattern
        config(['laravel-page-speed.skip' => ['admin/horizon/*']]);

        $middleware = new CollapseWhitespace();
        $result = $middleware->handle($request, function () use ($response) {
            return $response;
        });

        // Should not process (skip pattern matched)
        $this->assertEquals(
            '<html><body>Custom Horizon</body></html>',
            $result->getContent(),
            'Custom routes should be skippable when configured'
        );
    }

    /**
     * Test documentation for custom routes exists in config
     */
    public function test_config_documents_custom_routes(): void
    {
        $configFile = file_get_contents(__DIR__ . '/../../config/laravel-page-speed.php');

        // Check for documentation about custom routes
        $this->assertStringContainsString(
            'customized the routes',
            $configFile,
            'Config should document how to handle custom routes'
        );

        $this->assertStringContainsString(
            'Examples of custom routes',
            $configFile,
            'Config should provide examples of custom routes'
        );
    }
}
