<?php

namespace  MollieForPaymattic\Settings;

use \WPPayForm\Framework\Support\Arr;
use \WPPayForm\App\Services\AccessControl;
use \WPPayFormPro\GateWays\BasePaymentMethod;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class MollieSettings extends BasePaymentMethod
{
   /**
     * Automatically create global payment settings page
     * @param  String: key, title, routes_query, 'logo')
     */
    public function __construct()
    {
        parent::__construct(
            'mollie',
            'Mollie',
            [],
            'mollie.svg'
        );
    }

     /**
     * @function mapperSettings, To map key => value before store
     * @function validateSettings, To validate before save settings
     */

    public function init()
    {
        add_filter('wppayform_payment_method_settings_mapper_'.$this->key, array($this, 'mapperSettings'));
        add_filter('wppayform_payment_method_settings_validation_'.$this->key, array($this, 'validateSettings'), 10, 2);
    }

    public function mapperSettings ($settings)
    {
        return $this->mapper(
            static::settingsKeys(), 
            $settings, 
            false
        );
    }

    /**
     * @return Array of default fields
     */
    public static function settingsKeys()
    {
        return array(
            'payment_mode' => 'test',
            'test_api_key' => '',
            'live_api_key' => ''
        );
    }

    public static function getSettings () {
        $setting = get_option('wppayform_payment_settings_mollie', []);
        return wp_parse_args($setting, static::settingsKeys());
    }

    public function getPaymentSettings()
    {
        $settings = $this->mapper(
            $this->globalFields(), 
            static::getSettings()
        );
        return array(
            'settings' => $settings
        ); 
    }

    /**
     * @return Array of global fields
     */
    public function globalFields()
    {
        return array(
            'payment_mode' => array(
                'value' => 'test',
                'label' => __('Payment Mode', 'mollie-for-paymattic'),
                'options' => array(
                    'test' => __('Test Mode', 'mollie-for-paymattic'),
                    'live' => __('Live Mode', 'mollie-for-paymattic')
                ),
                'type' => 'payment_mode'
            ),
            'test_api_key' => array(
                'value' => '',
                'label' => __('Test Publishable Key', 'mollie-for-paymattic'),
                'type' => 'test_secret_key',
                'placeholder' => __('Test Publishable Key', 'mollie-for-paymattic')
            ),
            'live_api_key' => array(
                'value' => '',
                'label' => __('Live Publishable Key', 'mollie-for-paymattic'),
                'type' => 'live_secret_key',
                'placeholder' => __('Live Publishable Key', 'mollie-for-paymattic')
            ),
            'desc' => array(
                'value' => '<p>See our <a href="https://paymattic.com/docs/how-to-integrate-mollie-in-wordpress-with-paymattic/" target="_blank" rel="noopener">documentation</a> to get more information about mollie setup.</p>',
                'type' => 'html_attr',
                'placeholder' => __('Description', 'mollie-for-paymattic')
            ),
            'is_pro_item' => array(
                'value' => 'yes',
                'label' => __('PayPal', 'mollie-for-paymattic'),
            ),
        );
    }

    public function validateSettings($errors, $settings)
    {
        AccessControl::checkAndPresponseError('set_payment_settings', 'global');
        $mode = Arr::get($settings, 'payment_mode');

        if ($mode == 'test') {
            if (empty(Arr::get($settings, 'test_api_key'))) {
                $errors['test_api_key'] = __('Please provide Test Publishable key and Test Secret Key', 'mollie-for-paymattic-pro');
            }
        }

        if ($mode == 'live') {
            if (empty(Arr::get($settings, 'live_api_key'))) {
                $errors['live_api_key'] = __('Please provide Live Publishable key and Live Secret Key', 'mollie-for-paymattic-pro');
            }
        }
        return $errors;
    }

    public function isLive($formId = false)
    {
        $settings = $this->getSettings();
        return $settings['payment_mode'] == 'live';
    }

    public function getApiKey($formId = false)
    {
        $isLive = $this->isLive($formId);
        $settings = $this->getSettings();

        if ($isLive) {
            return $settings['live_api_key'];
        }

        return $settings['test_api_key'];
    }
}
