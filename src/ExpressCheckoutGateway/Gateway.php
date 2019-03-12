<?php # -*- coding: utf-8 -*-
/*
 * This file is part of the PayPal PLUS for WooCommerce package.
 *
 * (c) Inpsyde GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace WCPayPalPlus\ExpressCheckoutGateway;

use Inpsyde\Lib\PayPal\Api\Payment;
use Inpsyde\Lib\PayPal\Exception\PayPalConnectionException;
use WC_Logger_Interface as Logger;
use WCPayPalPlus\Api\ApiContextFactory;
use WCPayPalPlus\Api\CredentialProvider;
use WCPayPalPlus\Api\CredentialValidator;
use WCPayPalPlus\Order\OrderFactory;
use WCPayPalPlus\Notice;
use WCPayPalPlus\Payment\PaymentPatcher;
use WCPayPalPlus\Payment\PaymentPatchFactory;
use WCPayPalPlus\Setting\PlusRepositoryHelper;
use WCPayPalPlus\Setting\PlusStorable;
use WCPayPalPlus\Payment\PaymentExecutionFactory;
use WCPayPalPlus\Payment\PaymentCreatorFactory;
use WCPayPalPlus\Payment\Session;
use WCPayPalPlus\Refund\RefundFactory;
use WCPayPalPlus\WC\CheckoutDropper;
use WCPayPalPlus\WC\WCWebExperienceProfile;
use WC_Order_Refund;
use WooCommerce;
use WC_Payment_Gateway;
use RuntimeException;
use WC_Order;
use OutOfBoundsException;

/**
 * Class Gateway
 * @package WCPayPalPlus\ExpressCheckoutGateway
 */
class Gateway extends WC_Payment_Gateway implements PlusStorable
{
    use PlusRepositoryHelper;

    const GATEWAY_ID = 'paypal_express';
    const GATEWAY_TITLE_METHOD = 'PayPal Express Checkout';
    const ACTION_AFTER_PAYMENT_EXECUTION = 'woopaypalplus.after_express_checkout_payment_execution';
    const ACTION_AFTER_PAYMENT_PATCH = 'woopaypalplus.after_express_checkout_payment_patch';

    /**
     * @var CredentialProvider
     */
    private $credentialProvider;

    /**
     * @var CredentialValidator
     */
    private $credentialValidator;

    /**
     * @var GatewaySettingsModel
     */
    private $settingsModel;

    /**
     * @var RefundFactory
     */
    private $refundFactory;

    /**
     * @var OrderFactory
     */
    private $orderFactory;

    /**
     * @var PaymentExecutionFactory
     */
    private $paymentExecutionFactory;

    /**
     * @var PaymentCreatorFactory
     */
    private $paymentCreatorFactory;

    /**
     * @var Session
     */
    private $session;

    /**
     * @var WooCommerce
     */
    private $wooCommerce;

    /**
     * @var CheckoutDropper
     */
    private $checkoutDropper;

    /**
     * @var PaymentPatchFactory
     */
    private $paymentPatchFactory;

    private $logger;

    /**
     * Gateway constructor.
     * @param CredentialProvider $credentialProvider
     * @param CredentialValidator $credentialValidator
     * @param GatewaySettingsModel $settingsModel
     * @param RefundFactory $refundFactory
     * @param OrderFactory $orderFactory
     * @param PaymentExecutionFactory $paymentExecutionFactory
     * @param Session $session
     * @param CheckoutDropper $checkoutDropper
     * @param PaymentPatchFactory $paymentPatchFactory
     * @param Logger $logger
     */
    public function __construct(
        CredentialProvider $credentialProvider,
        CredentialValidator $credentialValidator,
        GatewaySettingsModel $settingsModel,
        RefundFactory $refundFactory,
        OrderFactory $orderFactory,
        PaymentExecutionFactory $paymentExecutionFactory,
        Session $session,
        CheckoutDropper $checkoutDropper,
        PaymentPatchFactory $paymentPatchFactory,
        Logger $logger
    ) {

        $this->credentialProvider = $credentialProvider;
        $this->credentialValidator = $credentialValidator;
        $this->settingsModel = $settingsModel;
        $this->refundFactory = $refundFactory;
        $this->orderFactory = $orderFactory;
        $this->paymentExecutionFactory = $paymentExecutionFactory;
        $this->session = $session;
        $this->checkoutDropper = $checkoutDropper;
        $this->paymentPatchFactory = $paymentPatchFactory;
        $this->logger = $logger;

        $this->id = self::GATEWAY_ID;
        $this->title = $this->get_option('title');
        $this->method_title = self::GATEWAY_TITLE_METHOD;
        $this->description = $this->get_option('description');
        $this->method_description = _x(
            'Allow customers to Checkout Product and cart directly.',
            'gateway-settings',
            'woo-paypalplus'
        );

        $this->has_fields = true;
        $this->supports = [
            'products',
            'refunds',
        ];

        $this->init_form_fields();
        $this->init_settings();
    }

    /**
     * @inheritdoc
     */
    public function init_form_fields()
    {
        $this->form_fields = $this->settingsModel->settings();
    }

    /**
     * @param int $orderId
     * @param null $amount
     * @param string $reason
     * @return bool
     * @throws RuntimeException
     */
    public function process_refund($orderId, $amount = null, $reason = '')
    {
        $order = $this->orderFactory->createById($orderId);

        if (!$order instanceof WC_Order_Refund) {
            return false;
        }

        if (!$this->can_refund_order($order)) {
            return false;
        }

        $apiContext = ApiContextFactory::getFromConfiguration();
        $refund = $this->refundFactory->create($order, $amount, $reason, $apiContext);

        return $refund->execute();
    }

    /**
     * @param WC_Order $order
     * @return bool
     */
    public function can_refund_order($order)
    {
        return $order && $order->get_transaction_id();
    }

    /**
     * @return bool|void
     */
    public function process_admin_options()
    {
        $credentials = $this->credentialProvider->byRequest($this->isSandboxed());
        $apiContext = ApiContextFactory::getFromCredentials($credentials);
        list($maybeValid, $message) = $this->credentialValidator->ensureCredential($apiContext);

        switch ($maybeValid) {
            case true:
                $config = [
                    'checkout_logo' => $this->get_option('checkout_logo'),
                    'local_id' => $this->experienceProfileId(),
                    'brand_name' => $this->get_option('brand_name'),
                    'country' => $this->get_option('country'),
                ];
                $webProfile = new WCWebExperienceProfile(
                    $config,
                    $apiContext
                );
                $optionKey = $this->experienceProfileKey();
                $_POST[$this->get_field_key($optionKey)] = $webProfile->save_profile();
                break;
            case false:
                // phpcs:ignore WordPress.VIP.SuperGlobalInputUsage.AccessDetected
                unset($_POST[$this->get_field_key('enabled')]);
                $this->enabled = 'no';

                $this->add_error(sprintf(
                    __(
                        'Your API credentials are either missing or invalid: %s',
                        'woo-paypalplus'
                    ),
                    $message
                ));
                break;
        }

        $this->data = $this->get_post_data();
        $checkoutLogoUrl = $this->ensureCheckoutLogoUrl(
            $this->data['woocommerce_paypal_plus_checkout_logo']
        );

        if (!$checkoutLogoUrl) {
            return;
        }

        parent::process_admin_options();
    }

    /**
     * @param array $formFields
     * @param bool $echo
     * @return false|string
     */
    public function generate_settings_html($formFields = [], $echo = true)
    {
        ob_start();
        $this->display_errors();
        do_action(Notice\Admin::ACTION_ADMIN_MESSAGES);
        $output = ob_get_clean();

        list($isValid) = $this->credentialValidator->ensureCredential(
            ApiContextFactory::getFromConfiguration()
        );

        $isValid and $this->sandboxMessage($output);
        !$isValid and $this->invalidPaymentMessage($output);

        $output .= parent::generate_settings_html($formFields, $echo);

        if ($echo) {
            echo wp_kses_post($output);
        }

        return $output;
    }

    /**
     * TODO May be logic of Patching and Payment Execution can be split. Use actions?
     *      About this see the similar code for Plus Gateway.
     *
     * @param int $orderId
     * @return array
     * @throws OutOfBoundsException
     */
    public function process_payment($orderId)
    {
        assert(is_int($orderId));

        $order = null;
        $paymentId = $this->session->get(Session::PAYMENT_ID);
        $payerId = $this->session->get(Session::PAYER_ID);

        if (!$payerId || !$paymentId || !$orderId) {
            $this->logger->error('Payment Excecution: Insufficient data to make payment.');
            // TODO Where redirect the user.
            return [
                'result' => 'failed',
                'redirect' => '',
            ];
        }

        try {
            $order = $this->orderFactory->createById($orderId);
        } catch (RuntimeException $exc) {
            $this->logger->error('Payment Execution: ' . $exc);
            $this->checkoutDropper->abortCheckout();
        }

        $paymentPatcher = $this->paymentPatchFactory->create(
            $order,
            $paymentId,
            $this->invoicePrefix(),
            ApiContextFactory::getFromConfiguration()
        );

        $isSuccessPatched = $paymentPatcher->execute();

        /**
         * Action After Payment Patch
         *
         * @param PaymentPatcher $paymentPatcher
         * @oparam bool $isSuccessPatched
         * @param CheckoutDropper $checkoutDropper
         */
        do_action(
            self::ACTION_AFTER_PAYMENT_PATCH,
            $paymentPatcher,
            $isSuccessPatched,
            $this->checkoutDropper
        );

        if (!$isSuccessPatched) {
            $this->checkoutDropper->abortCheckout();

            return [
                'result' => 'failed',
                'redirect' => $order->get_cancel_order_url(),
            ];
        }

        $payment = $this->paymentExecutionFactory->create(
            $order,
            $payerId,
            $paymentId,
            ApiContextFactory::getFromConfiguration()
        );

        try {
            $payment = $payment->execute();

            /**
             * Action After Payment has been Executed
             *
             * @param Payment $payment
             * @param WC_Order $order
             */
            do_action(self::ACTION_AFTER_PAYMENT_EXECUTION, $payment, $order);
        } catch (PayPalConnectionException $exc) {
            $this->logger->error('Payment Execution: ' . $exc);
            $this->checkoutDropper->abortCheckout();
        }

        return [
            'result' => 'success',
            'redirect' => $order->get_checkout_order_received_url(),
        ];
    }

    /**
     * @param $checkoutLogoUrl
     * @return string
     */
    private function ensureCheckoutLogoUrl($checkoutLogoUrl)
    {
        if (strlen($checkoutLogoUrl) > 127) {
            $this->add_error(
                __('Checkout Logo cannot contains more than 127 characters.', 'woo-paypalplus')
            );
            return '';
        }

        if (strpos($checkoutLogoUrl, 'https') === false) {
            $this->add_error(
                __(
                    'Checkout Logo must use the http secure protocol HTTPS. EG. (https://my-url)',
                    'woo-paypalplus'
                )
            );
            return '';
        }

        return $checkoutLogoUrl;
    }

    /**
     * @param $output
     * @param $message
     */
    private function credentialInformations(&$output, $message)
    {
        $output .= sprintf(
            '<div><p>%s</p></div>',
            esc_html__(
                'Below you can see if your account is successfully hooked up to use PayPal Express Checkout.',
                'woo-paypalplus'
            ) . "<br />{$message}"
        );
    }

    /**
     * @param $output
     */
    private function invalidPaymentMessage(&$output)
    {
        $this->credentialInformations(
            $output,
            sprintf(
                '<strong class="error-text">%s</strong>',
                esc_html__(
                    'Error connecting to the API. Check that the credentials are correct.',
                    'woo-paypalplus'
                )
            )
        );
    }

    /**
     * @param $output
     */
    private function sandboxMessage(&$output)
    {
        $msgSandbox = $this->isSandboxed()
            ? esc_html__(
                'Note: This is connected to your sandbox account.',
                'woo-paypalplus'
            )
            : esc_html__(
                'Note: This is connected to your live PayPal account.',
                'woo-paypalplus'
            );

        $this->credentialInformations(
            $output,
            sprintf('<strong>%s</strong>', $msgSandbox)
        );
    }

    /**
     * @return string
     */
    private function experienceProfileKey()
    {
        return $this->isSandboxed()
            ? PlusStorable::OPTION_PROFILE_ID_SANDBOX_NAME
            : PlusStorable::OPTION_PROFILE_ID_LIVE_NAME;
    }
}
