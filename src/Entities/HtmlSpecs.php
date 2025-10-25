<?php

namespace VinkiusLabs\LaravelPageSpeed\Entities;

class HtmlSpecs
{
    /**
     * Cached void elements array to avoid recreating on every call
     * 
     * @var array|null
     */
    private static $voidElementsCache = null;

    /**
     * Get list of HTML void elements (self-closing tags)
     * Uses static cache for performance - called multiple times per request
     * 
     * @return array
     */
    public static function voidElements(): array
    {
        if (self::$voidElementsCache === null) {
            self::$voidElementsCache = [
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

        return self::$voidElementsCache;
    }
}
