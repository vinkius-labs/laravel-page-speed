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

        // Only strip protocols from src, href, and action attributes
        // to avoid breaking JavaScript, meta tags, and inline content
        $replace = [
            '/(src|href|action)=("|\')https:/' => '$1=$2',
            '/(src|href|action)=("|\')http:/' => '$1=$2',
        ];

        return $this->replace($replace, $buffer);
    }
}
