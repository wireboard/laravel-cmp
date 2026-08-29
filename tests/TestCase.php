<?php

namespace Wireboard\Cmp\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Wireboard\Cmp\CmpServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [CmpServiceProvider::class];
    }

    /**
     * Render the full script bundle with a given configuration overlay.
     *
     * @param  array<string, mixed>  $overrides  dot-notation config overrides
     */
    protected function renderScripts(array $overrides = []): string
    {
        foreach ($overrides as $key => $value) {
            config()->set("cmp.$key", $value);
        }

        return (string) view('cmp::components.scripts')->render();
    }
}
