<?php # -*- coding: utf-8 -*-
/*
 * This file is part of the PayPal PLUS for WooCommerce package.
 *
 * (c) Inpsyde GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WCPayPalPlus\Core;

use UnexpectedValueException;
use WCPayPalPlus\Service\Container;
use WCPayPalPlus\Service\ServiceProvider as PluginServiceProvider;
use WP_Filesystem_Base;
use wpdb;

/**
 * Class ServiceProvider
 * @package WCPayPalPlus\Core
 */
class ServiceProvider implements PluginServiceProvider
{
    /**
     * @inheritDoc
     */
    public function register(Container $container)
    {
        $container->share(
            wpdb::class,
            function () {
                global $wpdb;
                return $wpdb;
            }
        );

        $container->share(
            'wp_filesystem',
            function () {
                global $wp_filesystem;

                if (!function_exists('WP_Filesystem')) {
                    require_once ABSPATH . '/wp-admin/includes/file.php';
                }
                $args = [];
                $ftpCredentials = get_option('ftp_credentials');
                if ($ftpCredentials) {
                    $args = [
                        // TODO Define keys as Constants
                        'hostname' => array_key_exists('hostname', $ftpCredentials) ? $ftpCredentials['hostname'] : '',
                        'username' => array_key_exists('username', $ftpCredentials) ? $ftpCredentials['username'] : '',
                        'password' => array_key_exists('password', $ftpCredentials) ? $ftpCredentials['password'] : '',
                    ];
                }

                $initilized = WP_Filesystem($args);

                if (!$initilized || !$wp_filesystem instanceof WP_Filesystem_Base) {
                    throw new UnexpectedValueException('There were problem in initializing Wp FileSystem');
                }

                return $wp_filesystem;
            }
        );
    }
}
