<?php # -*- coding: utf-8 -*-
/*
 * This file is part of the PayPal PLUS for WooCommerce package.
 *
 * (c) Inpsyde GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WCPayPalPlus\Utils;

use WC_Logger_Interface as Logger;
use WCPayPalPlus\Service\Container;
use WCPayPalPlus\Service\ServiceProvider as ServiceProviderInterface;

/**
 * Class ServiceProvider
 * @package WCPayPalPlus\Utils
 */
class ServiceProvider implements ServiceProviderInterface
{
    /**
     * @inheritdoc
     */
    public function register(Container $container)
    {
        $container[AjaxJsonRequest::class] = function (Container $container) {
            return new AjaxJsonRequest(
                $container[Logger::class]
            );
        };
    }
}
