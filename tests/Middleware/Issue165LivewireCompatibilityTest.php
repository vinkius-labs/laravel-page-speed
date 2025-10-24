<?php

namespace VinkiusLabs\LaravelPageSpeed\Test\Middleware;

use VinkiusLabs\LaravelPageSpeed\Test\TestCase;
use VinkiusLabs\LaravelPageSpeed\Middleware\CollapseWhitespace;

class Issue165LivewireCompatibilityTest extends TestCase
{
    protected function getMiddleware()
    {
        $this->middleware = new CollapseWhitespace();
    }

    /**
     * Test Issue #165: Livewire compatibility
     * 
     * Problem: CollapseWhitespace was removing ALL spaces between tags (> <),
     * which broke Livewire's wire:* directives and Alpine.js functionality.
     * 
     * Solution: Keep at least one space between tags to maintain HTML structure
     * needed by Livewire and Alpine.js
     */
    public function test_livewire_wire_model_preserved(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
    <div wire:id="abc123">
        <input type="text" wire:model="name">
        <button wire:click="submit">Submit</button>
    </div>
</body>
</html>';

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        // Livewire attributes must be preserved
        $this->assertStringContainsString('wire:id="abc123"', $result);
        $this->assertStringContainsString('wire:model="name"', $result);
        $this->assertStringContainsString('wire:click="submit"', $result);

        // Elements should still be separate (not merged)
        $this->assertStringContainsString('<input', $result);
        $this->assertStringContainsString('<button', $result);
        $this->assertStringContainsString('</button>', $result);
    }

    /**
     * Test: Livewire components structure
     */
    public function test_livewire_component_structure(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
    <div>
        <livewire:user-profile />
    </div>
    <div>
        <livewire:notification-panel />
    </div>
</body>
</html>';

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        // Livewire components should be preserved
        $this->assertStringContainsString('livewire:user-profile', $result);
        $this->assertStringContainsString('livewire:notification-panel', $result);

        // Structure should remain valid
        $this->assertMatchesRegularExpression('/<div>.*<livewire:user-profile/s', $result);
    }

    /**
     * Test: Multiple root elements error prevention
     * 
     * Issue: "Livewire: Multiple root elements detected"
     * This happens when whitespace collapse changes the DOM structure
     */
    public function test_preserves_single_root_element_structure(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
    <div wire:id="component-1">
        <div class="container">
            <h1>Title</h1>
            <p>Content</p>
        </div>
    </div>
</body>
</html>';

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        // Should maintain proper nesting
        $this->assertStringContainsString('<div wire:id="component-1">', $result);
        $this->assertStringContainsString('<div class="container">', $result);

        // Count div tags - should have matching open/close
        $openDivs = substr_count($result, '<div');
        $closeDivs = substr_count($result, '</div>');
        $this->assertEquals($openDivs, $closeDivs, 'Divs should be properly closed');
    }

    /**
     * Test: Alpine.js x-data attribute
     */
    public function test_alpine_js_x_data_preserved(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
    <div x-data="{ open: false }">
        <button @click="open = !open">Toggle</button>
        <div x-show="open">
            Content
        </div>
    </div>
</body>
</html>';

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        // Alpine.js attributes must be preserved
        $this->assertStringContainsString('x-data=', $result);
        $this->assertStringContainsString('@click=', $result);
        $this->assertStringContainsString('x-show=', $result);
    }

    /**
     * Test: Livewire wire:loading directive
     */
    public function test_livewire_wire_loading(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
    <div>
        <button wire:click="save">Save</button>
        <span wire:loading>Saving...</span>
    </div>
</body>
</html>';

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        $this->assertStringContainsString('wire:click="save"', $result);
        $this->assertStringContainsString('wire:loading', $result);
        $this->assertStringContainsString('Saving...', $result);
    }

    /**
     * Test: Livewire wire:poll directive
     */
    public function test_livewire_wire_poll(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
    <div wire:poll.5s="refreshData">
        <p>Auto-updating content</p>
    </div>
</body>
</html>';

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        $this->assertStringContainsString('wire:poll.5s="refreshData"', $result);
    }

    /**
     * Test: Complex Livewire form
     */
    public function test_complex_livewire_form(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
    <div wire:id="contact-form">
        <form wire:submit.prevent="submit">
            <div>
                <label for="name">Name</label>
                <input type="text" id="name" wire:model.defer="name">
                <span wire:loading wire:target="name">Validating...</span>
            </div>
            
            <div>
                <label for="email">Email</label>
                <input type="email" id="email" wire:model="email">
                @error("email")
                    <span class="error">{{ $message }}</span>
                @enderror
            </div>
            
            <button type="submit" wire:loading.attr="disabled">
                Submit
            </button>
            
            <span wire:loading wire:target="submit">
                Submitting...
            </span>
        </form>
    </div>
</body>
</html>';

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        // All Livewire directives should be preserved
        $this->assertStringContainsString('wire:id="contact-form"', $result);
        $this->assertStringContainsString('wire:submit.prevent="submit"', $result);
        $this->assertStringContainsString('wire:model.defer="name"', $result);
        $this->assertStringContainsString('wire:model="email"', $result);
        $this->assertStringContainsString('wire:loading', $result);
        $this->assertStringContainsString('wire:target="name"', $result);
        $this->assertStringContainsString('wire:target="submit"', $result);
        $this->assertStringContainsString('wire:loading.attr="disabled"', $result);

        // Form structure should remain intact
        $this->assertStringContainsString('<form', $result);
        $this->assertStringContainsString('</form>', $result);
        $this->assertStringContainsString('<input', $result);
        $this->assertStringContainsString('<button', $result);
    }

    /**
     * Test: Filament admin panel compatibility
     */
    public function test_filament_admin_panel_compatibility(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
    <div class="filament-page">
        <div wire:id="filament-form">
            <form wire:submit.prevent="submit">
                <div class="filament-forms-component-container">
                    <input type="text" wire:model="data.name">
                </div>
                <div class="filament-forms-component-container">
                    <select wire:model="data.status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <button type="submit">Save</button>
            </form>
        </div>
    </div>
</body>
</html>';

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        // Filament + Livewire should work together
        $this->assertStringContainsString('wire:id="filament-form"', $result);
        $this->assertStringContainsString('wire:submit.prevent="submit"', $result);
        $this->assertStringContainsString('wire:model="data.name"', $result);
        $this->assertStringContainsString('wire:model="data.status"', $result);

        // Filament classes should be preserved
        $this->assertStringContainsString('filament-page', $result);
        $this->assertStringContainsString('filament-forms-component-container', $result);
    }

    /**
     * Test: Livewire nested components
     */
    public function test_livewire_nested_components(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
    <div wire:id="parent-component">
        <h1>Parent Component</h1>
        <div wire:id="child-component-1">
            <p>Child 1</p>
        </div>
        <div wire:id="child-component-2">
            <p>Child 2</p>
        </div>
    </div>
</body>
</html>';

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        $this->assertStringContainsString('wire:id="parent-component"', $result);
        $this->assertStringContainsString('wire:id="child-component-1"', $result);
        $this->assertStringContainsString('wire:id="child-component-2"', $result);
    }

    /**
     * Test: Livewire wire:key for lists
     */
    public function test_livewire_wire_key_in_loops(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
    <div wire:id="user-list">
        <div wire:key="user-1">
            <span>User 1</span>
        </div>
        <div wire:key="user-2">
            <span>User 2</span>
        </div>
        <div wire:key="user-3">
            <span>User 3</span>
        </div>
    </div>
</body>
</html>';

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        $this->assertStringContainsString('wire:key="user-1"', $result);
        $this->assertStringContainsString('wire:key="user-2"', $result);
        $this->assertStringContainsString('wire:key="user-3"', $result);
    }

    /**
     * Test: Real-world Livewire + Alpine.js combination
     */
    public function test_livewire_alpine_combination(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
    <div wire:id="modal-component" x-data="{ show: @entangle(\'showModal\') }">
        <button @click="show = true" wire:click="openModal">
            Open Modal
        </button>
        
        <div x-show="show" x-transition>
            <div class="modal-content">
                <h2>Modal Title</h2>
                <input type="text" wire:model="modalInput">
                <button @click="show = false" wire:click="closeModal">Close</button>
            </div>
        </div>
    </div>
</body>
</html>';

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        // Livewire directives
        $this->assertStringContainsString('wire:id="modal-component"', $result);
        $this->assertStringContainsString('wire:click="openModal"', $result);
        $this->assertStringContainsString('wire:model="modalInput"', $result);
        $this->assertStringContainsString('wire:click="closeModal"', $result);

        // Alpine.js directives
        $this->assertStringContainsString('x-data=', $result);
        $this->assertStringContainsString('@entangle', $result);
        $this->assertStringContainsString('@click=', $result);
        $this->assertStringContainsString('x-show=', $result);
        $this->assertStringContainsString('x-transition', $result);
    }

    /**
     * Test: Ensure spaces between tags are preserved (critical for Livewire)
     */
    public function test_preserves_space_between_adjacent_tags(): void
    {
        $html = '<!DOCTYPE html>
<html>
<body>
    <div>
        <span>Text 1</span>
        <span>Text 2</span>
    </div>
</body>
</html>';

        $middleware = new CollapseWhitespace();
        $result = $middleware->apply($html);

        // Critical: Should have at least one space between tags
        // This prevents: </span><span> which can break Livewire
        // Should be: </span> <span> (with space)
        $this->assertMatchesRegularExpression(
            '/<\/span>\s+<span>/',
            $result,
            'Must preserve at least one space between adjacent tags for Livewire compatibility'
        );
    }
}
