<?php

namespace VinkiusLabs\LaravelPageSpeed\Middleware;

class InlineCss extends PageSpeed
{
    private $html = '';
    private $class = [];
    private $style = [];
    private $inline = [];
    private static $uniqueCounter = 0;

    /**
     * Apply inline CSS optimization
     * 
     * Performance improvements:
     * - Replaced rand() with counter-based unique IDs (faster)
     * - Eliminated explode('<', $html) that creates large arrays
     * - Uses preg_replace_callback for single-pass processing
     * 
     * @param string $buffer
     * @return string
     */
    public function apply($buffer)
    {
        // Early return when no inline style attributes are present
        if (stripos($buffer, 'style="') === false) {
            return $buffer;
        }

        $this->html = $buffer;

        preg_match_all(
            '#style="(.*?)"#',
            $this->html,
            $matches,
            PREG_OFFSET_CAPTURE
        );

        // Performance: Use counter instead of rand() - much faster
        $this->class = collect($matches[1])->mapWithKeys(function ($item) {
            return ['page_speed_' . (++self::$uniqueCounter) => $item[0]];
        })->unique();

        return $this->injectStyle()->injectClass()->fixHTML()->html;
    }

    private function injectStyle()
    {
        collect($this->class)->each(function ($attributes, $class) {

            $this->inline[] = ".{$class}{ {$attributes} }";

            $this->style[] = [
                'class' => $class,
                'attributes' => preg_quote($attributes, '/')
            ];
        });

        $injectStyle = implode(' ', $this->inline);

        $replace = [
            '#</head>(.*?)#' => "\n<style>{$injectStyle}</style>\n</head>"
        ];

        $this->html = $this->replace($replace, $this->html);

        return $this;
    }

    private function injectClass()
    {
        collect($this->style)->each(function ($item) {
            $replace = [
                '/style="' . $item['attributes'] . '"/' => "class=\"{$item['class']}\"",
            ];

            $this->html = $this->replace($replace, $this->html);
        });

        return $this;
    }

    /**
     * Fix HTML by consolidating multiple class attributes
     * 
     * Performance: Optimized to use preg_replace_callback instead of explode/loop
     */
    private function fixHTML()
    {
        // Performance: Use preg_replace_callback instead of explode('<') + loop
        // This avoids creating a large array from exploding HTML
        $this->html = preg_replace_callback(
            '/<([^>]+)>/s',
            function ($matches) {
                $tagContent = $matches[1];

                // Check if this tag has multiple class attributes
                preg_match_all('/(?<![-:])class="(.*?)"/i', $tagContent, $classMatches);

                if (count($classMatches[1]) > 1) {
                    // Multiple class attributes found - consolidate them
                    $allClasses = implode(' ', $classMatches[1]);

                    // Remove all existing class attributes
                    $tagContent = preg_replace('/(?<![-:])class="(.*?)"/i', '', $tagContent);

                    // Add single consolidated class attribute at the end
                    // Remove extra spaces
                    $tagContent = preg_replace('/\s+/', ' ', $tagContent);
                    $tagContent = trim($tagContent);

                    return "<{$tagContent} class=\"{$allClasses}\">";
                }

                return $matches[0];
            },
            $this->html
        );

        return $this;
    }
}
