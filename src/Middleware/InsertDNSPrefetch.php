<?php

namespace VinkiusLabs\LaravelPageSpeed\Middleware;

class InsertDNSPrefetch extends PageSpeed
{
    public function apply($buffer)
    {
        // Extract URLs only from HTML attributes, not from script/style content
        $urls = [];
        
        // Step 1: Extract URLs from script src/href attributes
        preg_match_all(
            '#<script[^>]+src=["\']([^"\']+)["\']#i',
            $buffer,
            $scriptMatches
        );
        if (!empty($scriptMatches[1])) {
            $urls = array_merge($urls, $scriptMatches[1]);
        }
        
        // Step 2: Extract URLs from link href attributes
        preg_match_all(
            '#<link[^>]+href=["\']([^"\']+)["\']#i',
            $buffer,
            $linkMatches
        );
        if (!empty($linkMatches[1])) {
            $urls = array_merge($urls, $linkMatches[1]);
        }
        
        // Step 3: Extract URLs from img src attributes
        preg_match_all(
            '#<img[^>]+src=["\']?([^"\'\s>]+)["\']?#i',
            $buffer,
            $imgMatches
        );
        if (!empty($imgMatches[1])) {
            $urls = array_merge($urls, $imgMatches[1]);
        }
        
        // Step 4: Extract URLs from anchor href attributes
        preg_match_all(
            '#<a[^>]+href=["\']([^"\']+)["\']#i',
            $buffer,
            $anchorMatches
        );
        if (!empty($anchorMatches[1])) {
            $urls = array_merge($urls, $anchorMatches[1]);
        }
        
        // Step 5: Extract URLs from iframe src attributes
        preg_match_all(
            '#<iframe[^>]+src=["\']([^"\']+)["\']#i',
            $buffer,
            $iframeMatches
        );
        if (!empty($iframeMatches[1])) {
            $urls = array_merge($urls, $iframeMatches[1]);
        }
        
        // Step 6: Extract URLs from video/audio source elements
        preg_match_all(
            '#<(?:video|audio|source)[^>]+src=["\']([^"\']+)["\']#i',
            $buffer,
            $mediaMatches
        );
        if (!empty($mediaMatches[1])) {
            $urls = array_merge($urls, $mediaMatches[1]);
        }

        // Filter to keep only external URLs (http:// or https://)
        $externalUrls = array_filter($urls, function ($url) {
            return preg_match('#^https?://#i', $url);
        });

        $dnsPrefetch = collect($externalUrls)->map(function ($url) {
            $domain = (new TrimUrls)->apply($url);
            $domain = explode(
                '/',
                str_replace('//', '', $domain)
            );

            return "<link rel=\"dns-prefetch\" href=\"//{$domain[0]}\">";
        })->unique()->implode("\n");

        $replace = [
            '#<head>(.*?)#' => "<head>\n{$dnsPrefetch}"
        ];

        return $this->replace($replace, $buffer);
    }
}
