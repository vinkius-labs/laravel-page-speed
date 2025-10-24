<?php

namespace VinkiusLabs\LaravelPageSpeed\Test\Middleware;

use VinkiusLabs\LaravelPageSpeed\Middleware\InlineCss;
use VinkiusLabs\LaravelPageSpeed\Test\TestCase;

class InlineCssJavaScriptFrameworksTest extends TestCase
{
    protected function getMiddleware()
    {
        $this->middleware = new InlineCss();
    }

    /**
     * Test Issue #75: AngularJS ng-class should not be affected
     * 
     * The InlineCss middleware was matching all attributes containing "class",
     * including ng-class, which broke AngularJS applications.
     */
    public function test_angularjs_ng_class_preserved(): void
    {
        $html = '<!DOCTYPE html>
<html ng-app="myApp">
<head></head>
<body>
    <div ng-class="{active: isActive, disabled: isDisabled}" style="padding: 10px;">
        <p class="text-bold" style="font-weight: bold;">Regular class with style</p>
        <span ng-class="dynamicClass">Dynamic</span>
    </div>
</body>
</html>';

        $middleware = new InlineCss();
        $result = $middleware->apply($html);

        // ng-class attributes must be preserved exactly
        $this->assertStringContainsString('ng-class="{active: isActive, disabled: isDisabled}"', $result);
        $this->assertStringContainsString('ng-class="dynamicClass"', $result);
        
        // Should have style tag with converted inline styles
        $this->assertStringContainsString('<style>', $result);
        $this->assertStringContainsString('page_speed_', $result);
        
        // Inline styles should be converted to classes
        $this->assertStringNotContainsString('style="padding: 10px;"', $result);
        $this->assertStringNotContainsString('style="font-weight: bold;"', $result);
    }

    /**
     * Test Issue #154: Alpine.js :class shorthand should not be affected
     */
    public function test_alpinejs_class_shorthand_preserved(): void
    {
        $html = '<!DOCTYPE html>
<html>
<head></head>
<body>
    <div x-data="{ open: false }">
        <button :class="{ \'bg-blue\': open, \'bg-gray\': !open }" style="padding: 5px;">Toggle</button>
        <div class="container" :class="open ? \'visible\' : \'hidden\'" style="margin: 10px;">
            <p style="color: red;">Content</p>
        </div>
    </div>
</body>
</html>';

        $middleware = new InlineCss();
        $result = $middleware->apply($html);

        // Alpine.js :class must be preserved
        $this->assertStringContainsString(':class="{ \'bg-blue\': open, \'bg-gray\': !open }"', $result);
        $this->assertStringContainsString(':class="open ? \'visible\' : \'hidden\'"', $result);
        
        // Inline styles should be converted to classes
        $this->assertStringNotContainsString('style="padding: 5px;"', $result);
        $this->assertStringNotContainsString('style="margin: 10px;"', $result);
        $this->assertStringNotContainsString('style="color: red;"', $result);
        
        // Should have page_speed classes
        $this->assertStringContainsString('page_speed_', $result);
    }

    /**
     * Test: Vue.js v-bind:class should not be affected
     */
    public function test_vuejs_vbind_class_preserved(): void
    {
        $html = '<!DOCTYPE html>
<html>
<head></head>
<body>
    <div id="app">
        <div v-bind:class="{ active: isActive, \'text-danger\': hasError }" style="padding: 20px;">
            <p style="font-size: 14px;">Message</p>
        </div>
        <span v-bind:class="classObject">Object</span>
        <button v-bind:class="[activeClass, errorClass]" style="border: 1px solid;">Array</button>
    </div>
</body>
</html>';

        $middleware = new InlineCss();
        $result = $middleware->apply($html);

        // Vue.js v-bind:class must be preserved
        $this->assertStringContainsString('v-bind:class="{ active: isActive, \'text-danger\': hasError }"', $result);
        $this->assertStringContainsString('v-bind:class="classObject"', $result);
        $this->assertStringContainsString('v-bind:class="[activeClass, errorClass]"', $result);
        
        // Inline styles should be converted
        $this->assertStringNotContainsString('style="padding: 20px;"', $result);
        $this->assertStringNotContainsString('style="font-size: 14px;"', $result);
        $this->assertStringNotContainsString('style="border: 1px solid;"', $result);
    }

    /**
     * Test: Mixed usage of regular class and framework-specific class attributes
     */
    public function test_mixed_class_and_framework_attributes(): void
    {
        $html = '<!DOCTYPE html>
<html>
<head></head>
<body>
    <!-- AngularJS -->
    <div class="card" ng-class="{expanded: isExpanded}" style="border: 1px solid;">Angular</div>
    
    <!-- Vue.js -->
    <div class="box" v-bind:class="vueClasses" style="padding: 10px;">Vue</div>
    
    <!-- Alpine.js -->
    <div class="panel" :class="alpineClasses" style="margin: 5px;">Alpine</div>
    
    <!-- Multiple inline styles -->
    <div style="display: flex;">Regular</div>
</body>
</html>';

        $middleware = new InlineCss();
        $result = $middleware->apply($html);

        // Framework-specific class attributes must be preserved
        $this->assertStringContainsString('ng-class="{expanded: isExpanded}"', $result);
        $this->assertStringContainsString('v-bind:class="vueClasses"', $result);
        $this->assertStringContainsString(':class="alpineClasses"', $result);
        
        // Inline styles should be converted to page_speed classes
        $this->assertStringNotContainsString('style="border: 1px solid;"', $result);
        $this->assertStringNotContainsString('style="padding: 10px;"', $result);
        $this->assertStringNotContainsString('style="margin: 5px;"', $result);
        $this->assertStringNotContainsString('style="display: flex;"', $result);
        
        // Should have style tag with converted classes
        $this->assertStringContainsString('<style>', $result);
    }

    /**
     * Test Issue #133: Ensure the fix doesn't break normal class handling
     */
    public function test_normal_class_still_works(): void
    {
        $html = '<!DOCTYPE html>
<html>
<head></head>
<body>
    <div style="width: 100%;">
        <p style="font-weight: bold;">Bold text</p>
        <span style="color: yellow;">Highlighted</span>
    </div>
</body>
</html>';

        $middleware = new InlineCss();
        $result = $middleware->apply($html);

        // Inline styles should be converted to page_speed classes
        $this->assertStringContainsString('<style>', $result);
        $this->assertStringNotContainsString('style="width: 100%;"', $result);
        $this->assertStringNotContainsString('style="font-weight: bold;"', $result);
        $this->assertStringNotContainsString('style="color: yellow;"', $result);
        
        // Should have page_speed classes instead
        $this->assertStringContainsString('class="page_speed_', $result);
    }

    /**
     * Test: Edge case - attribute that ends with "class"
     */
    public function test_attribute_ending_with_class(): void
    {
        $html = '<!DOCTYPE html>
<html>
<head></head>
<body>
    <div data-css-class="some-value" style="background: blue;">
        Content
    </div>
</body>
</html>';

        $middleware = new InlineCss();
        $result = $middleware->apply($html);

        // Attribute ending with "class" should not be affected
        $this->assertStringContainsString('data-css-class="some-value"', $result);
        
        // Inline style should be converted
        $this->assertStringNotContainsString('style="background: blue;"', $result);
    }

    /**
     * Test: Complex real-world scenario with multiple frameworks
     */
    public function test_complex_multi_framework_scenario(): void
    {
        $html = '<!DOCTYPE html>
<html ng-app="app">
<head></head>
<body>
    <div id="vue-app">
        <!-- Navigation with Alpine.js -->
        <nav style="background: white;" x-data="{ open: false }">
            <button style="padding: 10px;" :class="{ active: open }">Menu</button>
            <ul style="list-style: none;" :class="open ? \'show\' : \'hide\'">
                <li style="margin: 5px;">Item</li>
            </ul>
        </nav>
        
        <!-- Content with Vue.js -->
        <main style="min-height: 100vh;" v-bind:class="contentClasses">
            <div style="border: 1px solid;" ng-class="{loading: isLoading}">
                <h1 style="font-size: 24px;">Title</h1>
                <p style="line-height: 1.5;">Description</p>
            </div>
        </main>
    </div>
</body>
</html>';

        $middleware = new InlineCss();
        $result = $middleware->apply($html);

        // All framework-specific attributes must be preserved
        $this->assertStringContainsString(':class="{ active: open }"', $result);
        $this->assertStringContainsString(':class="open ? \'show\' : \'hide\'"', $result);
        $this->assertStringContainsString('v-bind:class="contentClasses"', $result);
        $this->assertStringContainsString('ng-class="{loading: isLoading}"', $result);
        
        // Inline styles should be converted
        $this->assertStringNotContainsString('style="background: white;"', $result);
        $this->assertStringNotContainsString('style="padding: 10px;"', $result);
        $this->assertStringNotContainsString('style="border: 1px solid;"', $result);
        $this->assertStringNotContainsString('style="font-size: 24px;"', $result);
        
        // Should have style tag
        $this->assertStringContainsString('<style>', $result);
    }
}
