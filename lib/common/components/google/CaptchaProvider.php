<?php

declare (strict_types=1);
/**
 * This file is part of osCommerce ecommerce platform.
 * osCommerce the ecommerce
 *
 * @link https://www.oscommerce.com
 * @copyright Copyright (c) 2000-2022 osCommerce LTD
 *
 * Released under the GNU General Public License
 * For the full copyright and license information, please view the LICENSE.TXT file that was distributed with this source code.
 */
namespace common\components\google;

use common\models\repositories\Google_Settings_Repository;
class Captcha_Provider extends Providers implements Google_Provider_Interface
{
    private $gs_repository;
    private $code = 'recaptcha';
    public function get_name()
    {
        return 'reCaptcha Keys';
    }
    public function get_code()
    {
        return $this->code;
    }
    public function get_description()
    {
        return 'That pair of keys is used to provide google reCaptcha verification. They can be obtained at <a href="https://www.google.com/recaptcha/intro/v3.html" target="_blank">Google reCAPTCHA Console</a>';
    }
    public function __construct(Google_Settings_Repository $gs_repository)
    {
        $this->gs_repository = $gs_repository;
    }
    public function get_config($platform_id = 0)
    {
        static $setting = null;
        if (is_null($setting) || $setting == false || $setting && $platform_id != $setting->platform_id) {
            $setting = $this->get_setting($platform_id);
        }
        if ($setting) {
            $value = $setting->get_value();
            return $value ? $value : false;
        } else {
            $setting = false;
        }
        return false;
    }
    public function get_setting($platform_id = 0)
    {
        return $this->gs_repository->get_setting($this->code, $platform_id, 1);
    }
    private function prepare_config($data)
    {
        if (is_array($data) && isset($data['publicKey']) && isset($data['privateKey'])) {
            try {
                return \Guzzle_Http\json_encode(['publicKey' => (string) $data['publicKey'], 'privateKey' => (string) $data['privateKey'], 'version' => (string) $data['version']]);
            } catch (\Exception $ex) {
            }
        }
        return false;
    }
    public function update_setting($setting, $data, $platform_id)
    {
        if ($config = $this->prepare_config($data)) {
            return $this->gs_repository->update_setting($setting, [$this->gs_repository->get_config_holder() => $config, 'platform_id' => $platform_id]);
        }
        return false;
    }
    public function create_setting($data, $platform_id)
    {
        if ($config = $this->prepare_config($data)) {
            return $this->gs_repository->create_setting($this->get_code(), $this->get_name(), $config, $platform_id, 1);
        }
        return false;
    }
    private function _decode($config)
    {
        try {
            return \Guzzle_Http\json_decode($config, true);
        } catch (\Exception $ex) {
            return false;
        }
    }
    public function get_public_key($platform_id = 0)
    {
        $config = $this->get_config($platform_id);
        if ($config) {
            $values = $this->_decode($config);
            if ($values) {
                return $values['publicKey'];
            }
        }
        return false;
    }
    public function get_private_key($platform_id = 0)
    {
        $config = $this->get_config($platform_id);
        if ($config) {
            $values = $this->_decode($config);
            if ($values) {
                return $values['privateKey'];
            }
        }
        return false;
    }
    public function get_version($platform_id = 0)
    {
        $config = $this->get_config($platform_id);
        if ($config) {
            $values = $this->_decode($config);
            if ($values) {
                return $values['version'] ?? 'v2';
            }
        }
        return false;
    }
    public function draw_config_template($platform_id = 0)
    {
        return widgets\Captcha_Widget::widget(['publicKey' => $this->get_public_key($platform_id), 'privateKey' => $this->get_private_key($platform_id), 'version' => $this->get_version($platform_id), 'owner' => $this->get_class_name(), 'description' => $this->get_description()]);
    }
}