<?php

namespace Wireboard\Cmp\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Blade's component tag compiler is a regular expression pass over the raw
 * template text. It has no notion of JavaScript, so a `<x-cmp-... />` written
 * inside a `//` comment is still compiled and rendered. The component's own
 * `</script>` then closes the surrounding script early, the rest of the file
 * spills onto the page as text, and the browser reports "Unexpected end of
 * input" for the truncated block. That shipped in 1.6.0 and silently disabled
 * the tracker, which is what these tests guard.
 */
class ScriptIntegrityTest extends TestCase
{
    /** @return array<string, mixed> */
    private function wireboardOn(string $loadingMode): array
    {
        return [
            'wireboard.enabled' => true,
            'wireboard.pipeline' => 'pipeline-0.collector.wireboard.io',
            'wireboard.app_id' => 'finimo',
            'wireboard.loading_mode' => $loadingMode,
        ];
    }

    /** @return array<int, array{0: string}> */
    public static function loadingModes(): array
    {
        return [['cookieless_first'], ['consent_required']];
    }

    /**
     * The source-level guard: naming a component in a JavaScript comment
     * renders it. Describe it in prose instead.
     */
    public function test_no_view_names_a_blade_component_inside_a_javascript_comment(): void
    {
        $views = glob(__DIR__ . '/../resources/views/components/*.blade.php') ?: [];
        $this->assertNotEmpty($views, 'no package views found to scan');

        // Collected rather than asserted in the loop: the 1.6.0 bug had two
        // occurrences, and failing on the first would hide the second.
        $offenders = [];

        foreach ($views as $view) {
            foreach (file($view) ?: [] as $number => $line) {
                $tag = strpos($line, '<x-');
                $comment = strpos($line, '//');

                if ($tag !== false && $comment !== false && $comment < $tag) {
                    $offenders[] = basename($view) . ':' . ($number + 1);
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            'these lines name a Blade component inside a JavaScript comment; Blade '
            . 'renders it and the injected </script> truncates the block: '
            . implode(', ', $offenders)
        );
    }

    /**
     * A `</script>` always ends the current script, so a block that still
     * contains an opening `<script` was cut short by something rendered into
     * it. The truncated remainder never parses.
     */
    #[DataProvider('loadingModes')]
    public function test_every_rendered_script_block_is_self_contained(string $loadingMode): void
    {
        $html = $this->renderScripts($this->wireboardOn($loadingMode));

        preg_match_all('#<script[^>]*>(.*?)</script>#s', $html, $matches);
        $this->assertNotEmpty($matches[1], 'the bundle rendered no script blocks');

        foreach ($matches[1] as $index => $block) {
            $this->assertStringNotContainsString(
                '<script',
                $block,
                "script block $index in $loadingMode mode was truncated by a nested <script>"
            );
        }
    }

    /**
     * The bundle pulls the bridge in once, deliberately. A second copy means
     * something rendered it by accident.
     */
    #[DataProvider('loadingModes')]
    public function test_the_spa_bridge_is_rendered_exactly_once(string $loadingMode): void
    {
        $html = $this->renderScripts($this->wireboardOn($loadingMode));

        $this->assertSame(
            1,
            substr_count($html, 'window.__cmpSpaBridge = true'),
            "the SPA bridge should appear once in $loadingMode mode"
        );
    }

    /**
     * The listener lives at the tail of the wireboard view, past the comment
     * that used to truncate it. If it is not inside a script block it is page
     * text, not code.
     */
    #[DataProvider('loadingModes')]
    public function test_the_wireboard_page_view_listener_stays_inside_a_script_block(string $loadingMode): void
    {
        $html = $this->renderScripts($this->wireboardOn($loadingMode));

        preg_match_all('#<script[^>]*>(.*?)</script>#s', $html, $matches);
        $scripts = implode("\n", $matches[1]);

        $this->assertStringContainsString("window.addEventListener('cmp:pageview'", $scripts);
        $this->assertStringContainsString('wireboard(\'trackPageView\'', $scripts);
    }
}
