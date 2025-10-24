<?php

namespace RenatoMarinho\LaravelPageSpeed\Test\Entities;

use PHPUnit\Framework\TestCase;
use RenatoMarinho\LaravelPageSpeed\Entities\HtmlSpecs;

class HtmlSpecsTest extends TestCase
{
    /** @test */
    public function it_returns_void_elements()
    {
        $voidElements = HtmlSpecs::voidElements();
        
        $this->assertIsArray($voidElements);
        $this->assertNotEmpty($voidElements);
    }

    /** @test */
    public function it_contains_common_void_elements()
    {
        $voidElements = HtmlSpecs::voidElements();
        
        $expectedElements = ['br', 'hr', 'img', 'input', 'link', 'meta'];
        
        foreach ($expectedElements as $element) {
            $this->assertContains($element, $voidElements);
        }
    }

    /** @test */
    public function it_contains_all_html5_void_elements()
    {
        $voidElements = HtmlSpecs::voidElements();
        
        $allVoidElements = [
            'area',
            'base',
            'br',
            'col',
            'embed',
            'hr',
            'img',
            'input',
            'link',
            'meta',
            'param',
            'source',
            'track',
            'wbr',
        ];
        
        $this->assertEquals(count($allVoidElements), count($voidElements));
        
        foreach ($allVoidElements as $element) {
            $this->assertContains($element, $voidElements);
        }
    }

    /** @test */
    public function void_elements_are_lowercase()
    {
        $voidElements = HtmlSpecs::voidElements();
        
        foreach ($voidElements as $element) {
            $this->assertEquals(strtolower($element), $element);
        }
    }
}
