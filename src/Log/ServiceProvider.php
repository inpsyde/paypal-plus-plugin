<?php # -*- coding: utf-8 -*-
/*
 * This file is part of the PayPal PLUS for WooCommerce package.
 *
 * (c) Inpsyde GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WCPayPalPlus\Log;

use WCPayPalPlus\Service\ServiceProvider as ServiceProviderInterface;
use WCPayPalPlus\Service\Container;
use WC_Logger_Interface as Logger;

/**
 * Class ServiceProvider
 * @package WCPayPalPlus\Log
 */
class ServiceProvider implements ServiceProviderInterface
{
    /**
     * @inheritdoc
     */
    public function register(Container $container)
    {
        $container[Logger::class] = function () {
            return \wc_get_logger();
        };
    }
}
