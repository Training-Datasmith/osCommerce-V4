<?php

declare (strict_types=1);
/**
 * Get access to modules, captcha, map, printers, analytics and their settings
 */
namespace common\components;

use Yii;
#[\Allow_Dynamic_Properties]
class Google_Tools
{
    private static $instance = null;
    public static function instance()
    {
        if (!is_object(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    private $providers = ['ModulesProvider' => false, 'MapProvider' => false, 'PrinterProvider' => false, 'CaptchaProvider' => false, 'AnalyticsProvider' => false];
    public function get_provider($name)
    {
        if (isset($this->providers[$name])) {
            $get_provier = 'get' . ucfirst($name);
            if (method_exists($this, $get_provier)) {
                return $this->{$get_provier}();
            }
        }
        return false;
    }
    public function update_provider_config(google\Google_Provider_Interface $provider, $config, $platform_id = 0)
    {
        $setting = $provider->get_setting($platform_id);
        if ($setting) {
            return $provider->update_setting($setting, $config, $platform_id);
        } else {
            return $provider->create_setting($config, $platform_id);
        }
    }
    public function get_modules_provider()
    {
        if (!is_object($this->provider['ModulesProvider'] ?? null)) {
            $this->provider['ModulesProvider'] = Yii::create_object(__NAMESPACE__ . '\google\ModuleProvider');
        }
        return $this->provider['ModulesProvider'];
    }
    public function get_map_provider()
    {
        if (!is_object($this->provider['MapProvider'] ?? null)) {
            $this->provider['MapProvider'] = Yii::create_object(__NAMESPACE__ . '\google\MapProvider');
        }
        return $this->provider['MapProvider'];
    }
    public function get_captcha_provider()
    {
        if (!is_object($this->provider['CaptchaProvider'] ?? null)) {
            $this->provider['CaptchaProvider'] = Yii::create_object(__NAMESPACE__ . '\google\CaptchaProvider');
        }
        return $this->provider['CaptchaProvider'];
    }
    public function get_analytics_provider()
    {
        if (!is_object($this->provider['AnalyticsProvider'] ?? null)) {
            $this->provider['AnalyticsProvider'] = Yii::create_object(__NAMESPACE__ . '\google\AnalyticsProvider');
        }
        return $this->provider['AnalyticsProvider'];
    }
    public function get_geocoding_location(string $address)
    {
        if (empty($address)) {
            return false;
        }
        return $this->get_map_provider()->get_location_by_address($address);
    }
    public function check_order_position(\common\classes\extended\Order_Abstract $order)
    {
        if ($order->order_id) {
            return false;
        }
        $nostreetaddress = implode(' ', [$order->customer['postcode'] ?? '', $order->customer['city'] ?? '', $order->customer['country']['title'] ?? '']);
        $address = implode(' ', [$order->customer['postcode'] ?? '', $order->customer['street_address'] ?? '', $order->customer['city'], $order->customer['country']['title'] ?? '']);
        $addressnocode = implode(' ', [$order->customer['street_address'] ?? '', $order->customer['city'] ?? '', $order->customer['country']['title'] ?? '']);
        foreach ([$address, $addressnocode, $nostreetaddress] as $addr) {
            if ($resp = $this->get_map_provider()->get_location_by_address($addr)) {
                $o_model = $order->get_ar_model()->where(['orders_id' => $order->order_id]);
                if ($o_model) {
                    $o_model->lat = $resp['lat'];
                    $o_model->lng = $resp['lng'];
                    return $o_model->save(false);
                }
            }
        }
        return false;
    }
}