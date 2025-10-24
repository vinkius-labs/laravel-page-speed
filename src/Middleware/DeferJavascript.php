<?php

namespace VinkiusLabs\LaravelPageSpeed\Middleware;

class DeferJavascript extends PageSpeed
{
    public function apply($buffer)
    {
        $replace = [
            '/<script(?=[^>]+src[^>]+)((?![^>]+defer|data-pagespeed-no-defer[^>]+)[^>]+)/i' => '<script $1 defer',
        ];

        return $this->replace($replace, $buffer);
    }
}
