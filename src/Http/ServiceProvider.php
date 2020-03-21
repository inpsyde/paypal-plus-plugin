<?php # -*- coding: utf-8 -*-
/*
 * This file is part of the PayPal PLUS for WooCommerce package.
 *
 * (c) Inpsyde GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WCPayPalPlus\Http;

use Inpsyde\Lib\Psr\Log\LoggerInterface as Logger;
use UnexpectedValueException;
use WCPayPalPlus\Banner\BannerSdkScriptUrl;
use WCPayPalPlus\Http\PayPalAssetsCache\AssetsStoreUpdater;
use WCPayPalPlus\Http\PayPalAssetsCache\CronScheduler;
use WCPayPalPlus\Http\PayPalAssetsCache\RemoteResourcesStorer;
use WCPayPalPlus\Http\PayPalAssetsCache\ResourceDictionary;
use WCPayPalPlus\Service\BootstrappableServiceProvider;
use WCPayPalPlus\Service\Container;
use WCPayPalPlus\Setting\SharedRepository;

/**
 * Class ServiceProvider
 * @package WCPayPalPlus\Http
 */
class ServiceProvider implements BootstrappableServiceProvider
{
    /**
     * @inheritDoc
     */
    public function register(Container $container)
    {
        $uploadDir = wp_upload_dir();
        $uploadDir = isset($uploadDir['basedir']) ? $uploadDir['basedir'] : '';

        if (!$uploadDir) {
            return;
        }

        try {
            $fileSystem = $container->get('wp_filesystem');
        } catch (UnexpectedValueException $exc) {
            $container->get(Logger::class)->warning($exc->getMessage());
            return;
        }

        $container->addService(
            CronScheduler::class,
            function (Container $container) {
                return new CronScheduler(
                    $container[AssetsStoreUpdater::class]
                );
            }
        );

        $container->addService(
            RemoteResourcesStorer::class,
            function () use ($fileSystem) {
                return new RemoteResourcesStorer($fileSystem);
            }
        );

        $container->addService(
            'banner_sdk_script_url',
            function () {
                $clientId = get_option('banner_settings_client_id');
                $currency = get_woocommerce_currency();
                if (!isset($clientId) || !isset($currency)) {
                    return '';
                }

                return "https://www.paypal.com/sdk/js?client-id={$clientId}&components=messages&currency={$currency}";
            }
        );

        $container->addService(
            ResourceDictionary::class,
            function (Container $container) use ($uploadDir) {
                return new ResourceDictionary(
                    [
                        "{$uploadDir}/woo-paypalplus/resources/js/paypal/expressCheckout.min.js" => 'https://www.paypalobjects.com/api/checkout.min.js',
                        "{$uploadDir}/woo-paypalplus/resources/js/paypal/payPalplus.min.js" => 'https://www.paypalobjects.com/webstatic/ppplus/ppplus.min.js',
                        "{$uploadDir}/woo-paypalplus/resources/js/paypal/paypalBanner.min.js" => $container->get(
                            'banner_sdk_script_url'
                        ),
                    ]
                );
            }
        );

        $container->addService(
            AssetsStoreUpdater::class,
            function (Container $container) {
                return new AssetsStoreUpdater(
                    $container->get(RemoteResourcesStorer::class),
                    $container->get(ResourceDictionary::class)
                );
            }
        );
    }

    /**
     * @inheritDoc
     */
    public function bootstrap(Container $container)
    {
        $cronScheduler = $container->get(CronScheduler::class);

        add_filter(
            'cron_schedules',
            function (array $schedules) use ($cronScheduler) {
                return $cronScheduler->addWeeklyRecurrence($schedules);
            }
        );

        add_action('wp_enqueue_scripts', [$cronScheduler, 'schedule'], 0);

        add_action(
            CronScheduler::CRON_HOOK_NAME,
            [$container->get(AssetsStoreUpdater::class), 'update']
        );
    }
}
