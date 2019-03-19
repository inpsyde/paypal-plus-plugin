<?php # -*- coding: utf-8 -*-
/*
 * This file is part of the PayPal PLUS for WooCommerce package.
 *
 * (c) Inpsyde GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WCPayPalPlus\Gateway;

use WCPayPalPlus\Request\Request;
use WCPayPalPlus\Service\Container;
use WCPayPalPlus\Service\ServiceProvider as ServiceProviderInterface;
use WCPayPalPlus\Session\Session;

/**
 * Class ServiceProvider
 * @package WCPayPalPlus\Gateway
 */
class ServiceProvider implements ServiceProviderInterface
{
    /**
     * @inheritdoc
     */
    public function register(Container $container)
    {
        $container[CurrentPaymentMethod::class] = function (Container $container) {
            return new CurrentPaymentMethod(
                $container[Session::class],
                $container[Request::class]
            );
        };
    }
}
