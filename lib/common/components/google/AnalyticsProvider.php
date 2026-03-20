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
use Yii;
class Analytics_Provider extends Providers implements Google_Provider_Interface
{
    private $gs_repository;
    private $code = 'report';
    public $has_config_file = true;
    private $upload_path;
    private $config_path;
    public function get_name()
    {
        return 'Google Analytics Keys';
    }
    public function get_code()
    {
        return $this->code;
    }
    public function get_description()
    {
        return 'Analytics View Id & its accompanied file with credentials are used to measurement statistical information. <a href="https://developers.google.com/analytics/devguides/collection/protocol/v1/" target="_blank">More details</a>';
    }
    public function __construct(Google_Settings_Repository $gs_repository)
    {
        $this->gs_repository = $gs_repository;
        if (\frontend\design\Info::is_totally_admin()) {
            $this->upload_path = Yii::$aliases['@webroot'] . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
        }
        $this->config_path = Yii::$aliases['@common'] . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'google' . DIRECTORY_SEPARATOR;
        if (!is_dir($this->config_path)) {
            \yii\helpers\File_Helper::create_directory($this->config_path, 0755);
        }
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
        return $this->gs_repository->get_setting($this->code, $platform_id, null);
    }
    protected function prepare_config($data)
    {
        if (is_array($data) && (isset($data['jsonFile']) || isset($data['viewId']))) {
            $json_file = (string) $data['jsonFile'];
            if (is_file($this->upload_path . $json_file)) {
                try {
                    if (copy($this->upload_path . $json_file, $this->config_path . $json_file)) {
                        unlink($this->upload_path . $json_file);
                    }
                } catch (\Exception $ex) {
                }
            }
            return \Guzzle_Http\json_encode(['jsonFile' => $json_file, 'viewId' => (string) $data['viewId']]);
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
    public function get_file_key($platform_id = 0)
    {
        $config = $this->get_config($platform_id);
        if ($config) {
            $values = $this->_decode($config);
            if ($values && is_file($this->config_path . $values['jsonFile'])) {
                return $this->config_path . $values['jsonFile'];
            }
        }
        return false;
    }
    public function get_view_id($platform_id = 0)
    {
        $config = $this->get_config($platform_id);
        if ($config) {
            $values = $this->_decode($config);
            if ($values) {
                return $values['viewId'];
            }
        }
        return false;
    }
    public function draw_config_template($platform_id = 0)
    {
        return widgets\Analytics_Widget::widget(['jsonFile' => $this->get_file_key($platform_id), 'viewId' => $this->get_view_id($platform_id), 'owner' => $this->get_class_name(), 'description' => $this->get_description(), 'platformId' => $platform_id]);
    }
}