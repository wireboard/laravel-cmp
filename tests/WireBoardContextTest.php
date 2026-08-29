<?php

namespace Wireboard\Cmp\Tests;

class WireBoardContextTest extends TestCase
{
    /** @return array<string, mixed> */
    private function wireboardOn(): array
    {
        return [
            'wireboard.enabled' => true,
            'wireboard.pipeline' => 'pipeline-0.collector.wireboard.io',
            'wireboard.app_id' => 'finimo',
        ];
    }

    public function test_performance_timing_is_on_by_default(): void
    {
        $html = $this->renderScripts($this->wireboardOn());

        $this->assertStringContainsString('performanceTiming: true', $html);
    }

    public function test_performance_timing_can_be_turned_off(): void
    {
        $html = $this->renderScripts(
            array_merge($this->wireboardOn(), ['wireboard.contexts.performance_timing' => false]),
        );

        $this->assertStringContainsString('performanceTiming: false', $html);
    }

    public function test_configuration_is_json_encoded_not_html_escaped(): void
    {
        $html = $this->renderScripts(
            array_merge($this->wireboardOn(), ['wireboard.app_id' => "o'brien & sons"]),
        );

        // HTML escaping would emit &#039; inside a JS string literal, which
        // reaches the SDK as the literal entity.
        $this->assertStringNotContainsString('&#039;', $html);
        $this->assertStringContainsString('o\\u0027brien', $html);
    }

    public function test_it_renders_nothing_without_a_pipeline_or_app_id(): void
    {
        $html = $this->renderScripts(['wireboard.enabled' => true, 'wireboard.app_id' => null]);

        $this->assertStringNotContainsString('newTracker', $html);
    }
}
