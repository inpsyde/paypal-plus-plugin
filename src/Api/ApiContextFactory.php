<?php # -*- coding: utf-8 -*-
/*
 * This file is part of the PayPal PLUS for WooCommerce package.
 *
 * (c) Inpsyde GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WCPayPalPlus\Api;

use Inpsyde\Lib\PayPal\Auth\OAuthTokenCredential;
use Inpsyde\Lib\PayPal\Rest\ApiContext;

/**
 * Class ServiceProvider
 * @package WCPayPalPlus\Api
 */
class ApiContextFactory
{
    /**
     * Get PayPal apiContext with credentials from configuration
     *
     * @return ApiContext
     */
    public static function getFromConfiguration()
    {
        return new ApiContext(
            null,
            self::requestId()
        );
    }

    /**
     * Get PayPal apiContext with credentials
     *
     * @param OAuthTokenCredential $credential
     * @return ApiContext
     */
    public static function getFromCredentials(OAuthTokenCredential $credential)
    {
        return new ApiContext($credential, self::requestId());
    }

    /**
     * Generate a uniqi Request ID
     *
     * @return string
     */
    public static function requestId()
    {
        return \uniqid(\home_url(), false);
    }
}
