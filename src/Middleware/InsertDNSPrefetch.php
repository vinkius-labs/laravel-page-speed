<?php

namespace VinkiusLabs\LaravelPageSpeed\Middleware;

class InsertDNSPrefetch extends PageSpeed
{
    public function apply($buffer)
    {
        preg_match_all(
            '#\bhttps?://[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/))#',
            $buffer,
            $matches,
            PREG_OFFSET_CAPTURE
        );

        $dnsPrefetch = collect($matches[0])->map(function ($item) {

            $domain = (new TrimUrls)->apply($item[0]);
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
