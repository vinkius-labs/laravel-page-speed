<?php

namespace VinkiusLabs\LaravelPageSpeed\Middleware;

class TrimUrls extends PageSpeed
{
    public function apply($buffer)
    {
        // Early return when no URLs are present to trim
        if (stripos($buffer, 'https:') === false && stripos($buffer, 'http:') === false) {
            return $buffer;
        }

        $replace = [
            '/https:/' => '',
            '/http:/' => ''
        ];

        return $this->replace($replace, $buffer);
    }
}
