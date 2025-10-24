<?php

namespace VinkiusLabs\LaravelPageSpeed\Entities;

class HtmlSpecs
{
    public static function voidElements(): array
    {
        return [
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
    }
}
