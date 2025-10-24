<?php

namespace RenatoMarinho\LaravelPageSpeed\Test\Middleware;

use RenatoMarinho\LaravelPageSpeed\Middleware\ElideAttributes;
use RenatoMarinho\LaravelPageSpeed\Test\TestCase;

class ElideAttributesTest extends TestCase
{
    protected function getMiddleware()
    {
        $this->middleware = new ElideAttributes();
    }

    public function test_elide_attributes(): void
    {
        $response = $this->middleware->handle($this->request, $this->getNext());

        $this->assertStringContainsString('<input type="text" disabled value="teste" width="100%">', $response->getContent());
        $this->assertStringContainsString('<input type="text" disabled>', $response->getContent());
        $this->assertStringContainsString('<option selected class="selected" style="cursor: default">', $response->getContent());
        $this->assertStringContainsString('<button name="ok" disabled class="btn" style="border:3px solid blue;">OK</button>', $response->getContent());
        $this->assertStringContainsString('<form class="form" style="display:block;border:1px solid red;">', $response->getContent());
    }

    public function test_support_ng_disabled(): void
    {
        $response = $this->middleware->handle($this->request, $this->getNext());

        $this->assertStringContainsString('<button type="submit" class="btn btn-success btn-block" ng-disabled="form.$invalid || btnLoading">', $response->getContent());
    }
}
