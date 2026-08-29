<?php

namespace Wireboard\Cmp\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool isEnabled()
 * @method static bool isAdSenseEnabled()
 * @method static string|null getAdSensePubId()
 * @method static bool isGa4Enabled()
 * @method static string|null getGa4MeasurementId()
 * @method static string getGa4CookieFlags()
 * @method static bool isWireBoardEnabled()
 * @method static array getWireBoardConfig()
 * @method static array getSpaConfig()
 * @method static bool isSpaTrackingEnabled()
 * @method static array getCustomCmpConfig()
 * @method static array getThemeConfig()
 * @method static array getConsentDefaults()
 * @method static string getCmpType()
 * @method static array getConfig()
 * @method static mixed get(string $key, mixed $default = null)
 * @method static string getThemeCssVariables()
 *
 * @see \Wireboard\Cmp\Cmp
 */
class Cmp extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'wireboard-cmp';
    }
}
