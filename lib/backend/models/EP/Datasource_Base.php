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
namespace backend\models\EP;

use yii\base\Base_Object;
abstract class Datasource_Base extends Base_Object
{
    public $code = '';
    public $class_name = 'DatasourceBase';
    public $settings = [];
    public function __construct(array $config = [])
    {
        if (isset($config['settings']) && is_string($config['settings'])) {
            $config['settings'] = json_decode($config['settings'], true);
        }
        if (!is_array($config['settings'] ?? null)) {
            $config['settings'] = [];
        }
        $init_config = [];
        foreach ($config as $key => $val) {
            if (isset($this->{$key})) {
                $init_config[$key] = $val;
            }
        }
        parent::__construct($init_config);
    }
    /**
     * [
     *   '{ProviderClass}\\{DatasourceClass}' => [
     *     'group' => '{ProviderName}',
     *     'name' => '{Datasource Name}',
     *     'class' => 'Provider\\{ProviderClass}\\{DatasourceClass}',
     *     'export' =>[
     *       'disableSelectFields' => true,
     *     ],
     *   ],
     *   ....
     * ]
     *
     * @return array
     */
    public static function get_provider_list()
    {
        return [];
    }
    abstract public function get_name();
    abstract public function get_view_template();
    public function order_view($order_id)
    {
        return false;
    }
    /**
     * @deprecated
     * @param $configArray
     * @return mixed
     */
    public static function configure_array($config_array)
    {
        return $config_array;
    }
    public function prepare_config_for_view($config_array)
    {
        return $config_array;
    }
    /**
     * @param $data
     * @return array
     * @throws \InvalidArgumentException
     */
    public static function before_setting_save($data)
    {
        $settings = is_array($data) ? $data : [];
        return $settings;
    }
    public static function after_setting_save()
    {
    }
    public function update($settings)
    {
        $settings = static::before_setting_save($settings);
        $this->settings = $settings;
        tep_db_query("UPDATE ep_datasources SET settings='" . tep_db_input(json_encode($this->settings)) . "' WHERE code='" . tep_db_input($this->code) . "' ");
        static::after_setting_save();
    }
    public function configure_view()
    {
        $settings = $this->prepare_config_for_view($this->settings);
        $settings['code'] = $this->code;
        return [$this->get_view_template(), $settings];
    }
    public function get_job_config()
    {
        return $this->settings;
    }
    public function update_setting_key($key, $value)
    {
        $changed = false;
        if (is_null($value)) {
            if (array_key_exists($key, $this->settings)) {
                unset($this->settings[$key]);
                $changed = true;
            }
        } else if (!isset($this->settings[$key]) || $this->settings[$key] != $value) {
            $this->settings[$key] = $value;
            $changed = true;
        }
        if ($changed) {
            tep_db_query("UPDATE ep_datasources SET settings='" . tep_db_input(json_encode($this->settings)) . "' WHERE code='" . tep_db_input($this->code) . "' ");
        }
    }
    public function allow_product_view()
    {
        return false;
    }
    public function product_view($config)
    {
        return '';
    }
    public function product_save(Directory $directory, $product)
    {
    }
}