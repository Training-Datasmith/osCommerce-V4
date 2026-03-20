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
namespace common\classes\modules;

use common\modules\Order_Shipping\np;
use common\modules\Order_Total\ot_shipping;
require_once __DIR__ . '/VersionTrait.php';
// thanks for require Module in configure.php
#[\Allow_Dynamic_Properties]
abstract class Module
{
    use Version_Trait;
    /**
     * @var \common\services\OrderManager $manager
     */
    public $manager;
    public $code;
    public $sort_order = 0;
    protected $countries = [''];
    protected $visibility = [];
    protected $default_translation_array = [];
    protected $encrypted_keys = [];
    public function __construct()
    {
        $this->_init();
    }
    public static function get_description()
    {
        return '';
    }
    protected function _init()
    {
        foreach ($this->default_translation_array as $define => $translation) {
            if (!defined($define)) {
                define($define, $translation);
            }
        }
    }
    public function get_title($method = '')
    {
        return $this->title;
    }
    public function check($platform_id)
    {
        $keys = $this->keys();
        if (count($keys) == 0 || (int) $platform_id == 0 && !$this->is_extension) {
            return 0;
        }
        $check_keys_r = tep_db_query('SELECT configuration_key ' . 'FROM ' . TABLE_PLATFORMS_CONFIGURATION . ' ' . "WHERE configuration_key IN('" . implode("', '", array_map('tep_db_input', $keys)) . "') AND platform_id='" . (int) $platform_id . "'");
        $installed_keys = [];
        while ($check_key = tep_db_fetch_array($check_keys_r)) {
            $installed_keys[$check_key['configuration_key']] = $check_key['configuration_key'];
        }
        $check_status = isset($installed_keys[$keys[0]]) ? 1 : 0;
        $install_keys = false;
        foreach ($keys as $idx => $module_key) {
            if (!isset($installed_keys[$module_key]) && $check_status) {
                // missing key
                if (!is_array($install_keys)) {
                    $install_keys = $this->get_install_keys($platform_id);
                }
                $this->add_config_key($platform_id, $module_key, $install_keys[$module_key]);
            }
        }
        return $check_status;
    }
    public function install($platform_id)
    {
        $keys = $this->get_install_keys($platform_id);
        if (count($keys) == 0 || (int) $platform_id == 0 && !$this->is_extension) {
            return false;
        }
        foreach ($keys as $key => $data) {
            $this->add_config_key($platform_id, $key, $data);
        }
        if (method_exists($this, 'configure_keys_platforms')) {
            $platform_keys = $this->configure_keys_platforms();
            if (is_array($platform_keys) && !empty($platform_keys)) {
                $platform_values = \common\models\Platforms_Configuration::find()->where(['configuration_key' => array_keys($platform_keys)])->index_by(function ($row) {
                    return $row['platform_id'] . $row['configuration_key'];
                })->as_array()->all();
                $platforms = \common\classes\platform::get_list(false);
                foreach ($platform_keys as $key => $data) {
                    foreach ($platforms as $platform_data) {
                        $platform_id = $platform_data['id'];
                        if (!isset($platform_values[$platform_id . $key])) {
                            $this->add_config_key($platform_id, $key, $data);
                        }
                    }
                }
            }
        }
        $installed = self::get_installed();
        if (empty($installed) || empty($installed->version_db)) {
            \common\helpers\Modules::change_module($this->code, 'install', [], self::get_type(), $platform_id);
        } else {
            $this->upgrade();
        }
        if (\common\helpers\Acl::check_extension_allowed('ReportUniversalLog')) {
            $log_universal = \common\extensions\Report_Universal_Log\classes\Log_Universal::get_instance();
            $log_universal->set_relation($this->code)->set_type($log_universal::ULT_EXTENSION_INSTALL)->set_before_array([$platform_id => 0])->set_after_array([$platform_id => 1])->do_save(true);
            unset($log_universal);
        }
    }
    protected function add_config_key($platform_id, $key, $data)
    {
        $sql_data = ['platform_id' => (int) $platform_id, 'configuration_key' => $key, 'configuration_title' => isset($data['title']) ? $data['title'] : '', 'configuration_value' => isset($data['value']) ? $data['value'] : '', 'configuration_description' => isset($data['description']) ? $data['description'] : '', 'configuration_group_id' => isset($data['group_id']) ? $data['group_id'] : '6', 'sort_order' => isset($data['sort_order']) ? $data['sort_order'] : '0', 'date_added' => 'now()'];
        if (isset($data['use_function'])) {
            $sql_data['use_function'] = $data['use_function'];
        }
        if (isset($data['set_function'])) {
            $sql_data['set_function'] = $data['set_function'];
        }
        $model = \common\models\Platforms_Configuration::find_one(['platform_id' => (int) $platform_id, 'configuration_key' => $key]);
        if (empty($model)) {
            $model = new \common\models\Platforms_Configuration();
            $model->load_default_values();
        }
        $model->set_attributes($sql_data, false);
        $model->save(false);
    }
    public function remove($platform_id)
    {
        $keys = $this->keys();
        if ($this->user_confirmed_drop_datatables ?? false) {
            \common\helpers\Modules::change_module($this->code, 'remove_drop', [], self::get_type(), $platform_id);
        } else {
            \common\helpers\Modules::change_module($this->code, 'remove', [], self::get_type(), $platform_id);
        }
        if (\common\helpers\Acl::check_extension_allowed('ReportUniversalLog')) {
            $log_universal = \common\extensions\Report_Universal_Log\classes\Log_Universal::get_instance();
            $log_universal->set_relation($this->code)->set_type($log_universal::ULT_EXTENSION_REMOVE)->set_before_array(\common\models\Platforms_Configuration::find()->select(['configuration_value', 'platform_id', 'configuration_key'])->where(['IN', 'configuration_key', $keys])->index_by(function ($record) {
                return $record['platform_id'] . '|' . $record['configuration_key'];
            })->as_array(true)->column());
        }
        if (count($keys) > 0 && ((int) $platform_id != 0 || isset($this->is_extension))) {
            tep_db_query('DELETE FROM ' . TABLE_PLATFORMS_CONFIGURATION . ' ' . "WHERE platform_id='" . (int) $platform_id . "' AND configuration_key IN('" . implode("', '", $keys) . "')");
        }
        if (isset($log_universal)) {
            $log_universal->set_after_array([])->do_save(true);
            unset($log_universal);
        }
    }
    public function keys()
    {
        return array_keys($this->configure_keys());
    }
    /**
     * @return ModuleStatus
     */
    abstract public function describe_status_key();
    /**
     * @return ModuleSortOrder
     */
    abstract public function describe_sort_key();
    /**
     * @return array
     */
    abstract public function configure_keys();
    public function enable_module($platform_id, $flag)
    {
        $key_info = $this->describe_status_key();
        if (!is_object($key_info) || !is_a($key_info, 'common\classes\modules\ModuleStatus')) {
            return false;
        }
        if (\common\helpers\Acl::check_extension_allowed('ReportUniversalLog')) {
            $log_universal = \common\extensions\Report_Universal_Log\classes\Log_Universal::get_instance();
            $log_universal->set_relation($this->code)->set_type($log_universal::ULT_EXTENSION_STATUS)->set_before_array([$platform_id => (int) $this->is_module_enabled($platform_id)]);
        }
        $this->update_config_key($platform_id, $key_info->key, $flag ? $key_info->value_enabled : $key_info->value_disabled);
        if (isset($log_universal)) {
            $log_universal->set_after_array([$platform_id => (int) $this->is_module_enabled($platform_id)])->do_save(true);
            unset($log_universal);
        }
    }
    /**
     * @param $platform_id
     * @return bool
     */
    public function is_module_enabled($platform_id)
    {
        $key_info = $this->describe_status_key();
        if (!is_object($key_info) || !is_a($key_info, 'common\classes\modules\ModuleStatus')) {
            return false;
        }
        return $this->get_config_key($platform_id, $key_info->key) == $key_info->value_enabled;
    }
    public function update_sort_order($platform_id, $new_sort_order)
    {
        $key_info = $this->describe_sort_key();
        if (!is_object($key_info) || !is_a($key_info, 'common\classes\modules\ModuleSortOrder')) {
            return;
        }
        $this->update_config_key($platform_id, $key_info->key, (int) $new_sort_order);
    }
    protected function update_config_key($platform_id, $key, $value)
    {
        tep_db_query('UPDATE ' . TABLE_PLATFORMS_CONFIGURATION . ' ' . "SET configuration_value='" . tep_db_input($value) . "', last_modified=NOW() " . "WHERE configuration_key='" . tep_db_input($key) . "' AND platform_id='" . (int) $platform_id . "'");
    }
    protected function get_config_key($platform_id, $key)
    {
        $get_key_value_r = tep_db_query('SELECT configuration_value ' . 'FROM ' . TABLE_PLATFORMS_CONFIGURATION . ' ' . "WHERE configuration_key='" . tep_db_input($key) . "' AND platform_id='" . (int) $platform_id . "'");
        if (tep_db_num_rows($get_key_value_r) > 0) {
            $key_value = tep_db_fetch_array($get_key_value_r);
            return $key_value['configuration_value'];
        }
        return false;
    }
    public function save_config($platform_id, $new_data_array)
    {
        if (is_array($new_data_array)) {
            $module_keys = $this->keys();
            foreach ($new_data_array as $update_key => $new_value) {
                if (!in_array($update_key, $module_keys)) {
                    continue;
                }
                $this->update_config_key($platform_id, $update_key, $new_value);
            }
        }
    }
    protected function get_install_keys($platform_id)
    {
        return $this->configure_keys();
    }
    public function get_countries($platform_id)
    {
        $modules_countries = \common\models\Modules_Countries::find_one(['platform_id' => $platform_id, 'code' => $this->code]);
        if (is_object($modules_countries)) {
            $countries = explode(',', $modules_countries->countries);
            return array_merge($this->countries, $countries);
        }
        return $this->countries;
    }
    public function get_restriction($platform_id, $languages_id, $ignore_visibility = false)
    {
        if ((int) $platform_id == 0) {
            return '';
        }
        $countries_access = $this->get_countries($platform_id);
        $variants = [];
        $variants[''] = 'Worldwide';
        global $languages_id;
        $countries = tep_db_query('SELECT c.countries_name, c.countries_iso_code_3 FROM ' . TABLE_PLATFORMS_ADDRESS_BOOK . ' AS pab LEFT JOIN ' . TABLE_COUNTRIES . " AS c ON (c.countries_id = pab.entry_country_id) where c.language_id = '" . (int) $languages_id . "' group by c.countries_id");
        while ($countries_value = tep_db_fetch_array($countries)) {
            $variants[$countries_value['countries_iso_code_3']] = $countries_value['countries_name'];
        }
        foreach ($countries_access as $code) {
            if (!isset($variants[$code])) {
                $country = \common\models\Countries::find_one(['countries_iso_code_3' => $code, 'language_id' => $languages_id]);
                if (is_object($country)) {
                    $variants[$code] = $country->countries_name;
                } else {
                    $variants[$code] = $code;
                }
            }
        }
        ksort($variants);
        $response = '<table width="50%"><thead><tr><th>' . TEXT_FOR_COUNTRIES . '</th></thead><tbody>';
        foreach ($variants as $code => $name) {
            $response .= '<tr><td>';
            $params = 'class="uniform" ';
            if (in_array($code, $this->countries)) {
                $params .= 'disabled';
            }
            $response .= '<label>';
            $response .= tep_draw_checkbox_field('countries[' . $code . ']', '1', in_array($code, $countries_access), '', $params);
            $response .= $name;
            $response .= '</label>';
            $response .= '</td></tr>';
        }
        $response .= '</tbody></table>';
        if ($ignore_visibility) {
            return $response;
        }
        $response .= '<table width="50%"><thead><tr><th>' . TEXT_VISIBILITY . '</th></thead><tbody>';
        $variants = ['shop_order' => 'Checkout', 'shop_quote' => 'Quotation', 'shop_sample' => 'Sample', 'admin' => 'Admin area', 'moderator' => 'Group Administrator', 'pos' => 'POS'];
        $visibility_access = $this->visibility;
        $modules_visibility = \common\models\Modules_Visibility::find_one(['platform_id' => $platform_id, 'code' => $this->code]);
        if (is_object($modules_visibility)) {
            $visibility = explode(',', $modules_visibility->area);
            $visibility_access = array_merge($this->visibility, $visibility);
        }
        foreach ($variants as $code => $name) {
            if (!\common\helpers\Extensions::is_visibility_variant($code)) {
                continue;
            }
            $response .= '<tr><td>';
            $params = 'class="uniform" ';
            if (in_array($code, $this->visibility)) {
                $params .= 'disabled';
            }
            $response .= '<label>';
            $response .= tep_draw_checkbox_field('visibility_a[' . $code . ']', '1', in_array($code, $visibility_access), '', $params);
            $response .= $name;
            $response .= '</label>';
            $response .= '</td></tr>';
        }
        $response .= '</tbody></table>';
        return $response;
    }
    public function set_restriction()
    {
        $platform_id = (int) \Yii::$app->request->post('platform_id');
        if ((int) $platform_id == 0) {
            return false;
        }
        $countries = \Yii::$app->request->post('countries');
        $selected_countries = [];
        if (is_array($countries)) {
            foreach ($countries as $code => $checked) {
                if ($code === 0) {
                    $code = '';
                }
                if ($checked == 1) {
                    $selected_countries[] = $code;
                }
            }
        }
        sort($selected_countries);
        $modules_countries = \common\models\Modules_Countries::find_one(['platform_id' => $platform_id, 'code' => $this->code]);
        if (!is_object($modules_countries)) {
            $modules_countries = new \common\models\Modules_Countries();
            $modules_countries->platform_id = $platform_id;
            $modules_countries->code = $this->code;
        }
        if (\common\helpers\Acl::check_extension_allowed('ReportUniversalLog') && \common\extensions\Report_Universal_Log\classes\Log_Universal::is_instance($this->code)) {
            $log_universal = \common\extensions\Report_Universal_Log\classes\Log_Universal::get_instance($this->code);
            $log_universal->merge_before_array(['restriction_country' => trim($modules_countries->countries)]);
        }
        $modules_countries->countries = implode(',', $selected_countries);
        $modules_countries->save();
        if (isset($log_universal)) {
            $log_universal->merge_after_array(['restriction_country' => trim(array_shift(\common\models\Modules_Countries::find()->select('countries')->where(['platform_id' => $platform_id, 'code' => $this->code])->as_array(true)->column()))]);
        }
        $visibility = \Yii::$app->request->post('visibility_a');
        $selected_visibility = $this->visibility;
        if (is_array($visibility)) {
            foreach ($visibility as $code => $checked) {
                if ($code === 0) {
                    $code = '';
                }
                if ($checked == 1) {
                    $selected_visibility[] = $code;
                }
            }
        }
        sort($selected_visibility);
        $modules_visibility = \common\models\Modules_Visibility::find_one(['platform_id' => $platform_id, 'code' => $this->code]);
        if (!is_object($modules_visibility)) {
            $modules_visibility = new \common\models\Modules_Visibility();
            $modules_visibility->platform_id = $platform_id;
            $modules_visibility->code = $this->code;
        }
        if (isset($log_universal)) {
            $log_universal->merge_before_array(['restriction_area' => trim($modules_visibility->area)]);
        }
        $modules_visibility->area = implode(',', $selected_visibility);
        $modules_visibility->save();
        if (isset($log_universal)) {
            $log_universal->merge_after_array(['restriction_area' => trim(array_shift(\common\models\Modules_Visibility::find()->select('area')->where(['platform_id' => $platform_id, 'code' => $this->code])->as_array(true)->column()))]);
        }
        return true;
    }
    public function get_visibily($platform_id, $restrict = [])
    {
        $result = false;
        $modules_visibility = \common\helpers\Modules::load_visibility($platform_id, $this->code);
        $modules_visibility = is_array($modules_visibility) ? $modules_visibility : [];
        if (is_array($this->visibility)) {
            $modules_visibility = array_merge($modules_visibility, $this->visibility);
        }
        if (is_array($modules_visibility)) {
            $result = (bool) count(array_intersect($restrict, $modules_visibility));
        }
        return $result;
        /*
        $modulesVisibility = \common\models\ModulesVisibility::findOne(['platform_id' => $platform_id, 'code' => $this->code]);
        $result = false;
        if (is_object($modulesVisibility)) {
            $modulesVisibility = explode(',', $modulesVisibility->area);
            $result = (bool)count(array_intersect($restrict, $modulesVisibility));
        }
        return $result;
        */
    }
    public function get_group_restriction($platform_id)
    {
        if ((int) $platform_id == 0) {
            return '';
        }
        $groups = \common\helpers\Group::get_customer_groups_list();
        $modules_groups = \common\models\Modules_Groups_Settings::find_one(['platform_id' => $platform_id, 'code' => $this->code]);
        $visibility_access = [];
        if (!is_null($modules_groups) && !empty($modules_groups->group_list)) {
            $visibility_access = explode(',', $modules_groups->group_list);
        }
        $response = '<table width="50%" id="module_group_restriction" style="max-height:350px"><thead><tr><th>' . TEXT_FOR_GROUPS . ' ' . tep_draw_checkbox_field('group_restriction', '1', !is_null($modules_groups), '', 'onchange="return updateGroupRestriction(this);" class="uniform" ') . '</th></thead><tbody>';
        foreach ($groups as $id => $name) {
            $response .= '<tr><td>';
            $params = 'class="uniform" ';
            if (is_null($modules_groups)) {
                $params .= 'disabled';
            }
            $response .= '<label>';
            $response .= tep_draw_checkbox_field('group_visibility[]', $id, in_array($id, $visibility_access), '', $params);
            $response .= $name;
            $response .= '</label>';
            $response .= '</td></tr>';
        }
        $response .= '</tbody></table>';
        $response .= '<script type="text/javascript">function updateGroupRestriction(obj) { if ( $(obj).is(":checked") ) { $("input[name^=\'group_visibility\']").prop("disabled", false); $("#module_group_restriction div.checker").length && $("#module_group_restriction div.checker.disabled").removeClass("disabled"); } else {$("input[name^=\'group_visibility\']").prop("disabled", true); $("#module_group_restriction div.checker").length && $("#module_group_restriction tbody div.checker").addClass("disabled"); } }</script>';
        return $response;
    }
    public function set_group_restriction()
    {
        $platform_id = (int) \Yii::$app->request->post('platform_id');
        if ((int) $platform_id == 0) {
            return false;
        }
        if (\common\helpers\Acl::check_extension_allowed('ReportUniversalLog') && \common\extensions\Report_Universal_Log\classes\Log_Universal::is_instance($this->code)) {
            $log_universal = \common\extensions\Report_Universal_Log\classes\Log_Universal::get_instance($this->code);
            $log_universal->merge_before_array(['restriction_group' => trim(array_shift(\common\models\Modules_Groups_Settings::find()->select('group_list')->where(['platform_id' => $platform_id, 'code' => $this->code])->as_array(true)->column()))]);
        }
        $modules_groups = \common\models\Modules_Groups_Settings::find_one(['platform_id' => $platform_id, 'code' => $this->code]);
        $group_restriction = (int) \Yii::$app->request->post('group_restriction');
        if ($group_restriction == 1) {
            $group_visibility = \Yii::$app->request->post('group_visibility', []);
            if (is_null($modules_groups)) {
                $modules_groups = new \common\models\Modules_Groups_Settings();
                $modules_groups->platform_id = $platform_id;
                $modules_groups->code = $this->code;
            }
            try {
                $modules_groups->group_list = implode(',', $group_visibility);
                $modules_groups->save(false);
            } catch (\Exception $e) {
                \Yii::warning($e->get_message() . ' ' . $e->get_trace_as_string());
            }
        } else if (!is_null($modules_groups)) {
            $modules_groups->delete();
        }
        if (isset($log_universal)) {
            $log_universal->merge_after_array(['restriction_group' => trim(array_shift(\common\models\Modules_Groups_Settings::find()->select('group_list')->where(['platform_id' => $platform_id, 'code' => $this->code])->as_array(true)->column()))]);
        }
    }
    public function get_group_visibily($platform_id, $groups_id)
    {
        if ((int) $platform_id == 0) {
            return true;
        }
        if (\common\helpers\System::is_backend()) {
            return true;
        }
        // allow all modules in order edit
        //allow to disable for all groups if ( (int)$groups_id==0 ) return true;
        $modules_groups = \common\models\Modules_Groups_Settings::find_one(['platform_id' => $platform_id, 'code' => $this->code]);
        if (!is_null($modules_groups)) {
            if (!empty(trim($modules_groups->group_list))) {
                $visibility_access = explode(',', $modules_groups->group_list);
            } else {
                $visibility_access = [];
            }
            if (!in_array($groups_id, $visibility_access)) {
                return false;
            }
        }
        return true;
    }
    public $billing;
    public $delivery;
    public function set_billing(array $billing)
    {
        $this->billing = $billing;
    }
    public function set_delivery(array $delivery)
    {
        $this->delivery = $delivery;
    }
    /**
    * get tax rate and tax description by tax class id (for current order delivery/billing address)
    * @param int $tax_class_id
    * @return array [
               'tax_class_id' => $tax_class_id,
    *
               'tax' => $tax, //Tax::get_tax_rate
    *
               'tax_description' => $tax_description
           ];
    */
    public function get_tax_values($tax_class_id)
    {
        if (defined('TAX_ADDRESS_OPTION') && (int) TAX_ADDRESS_OPTION == 1) {
            if ($this->manager->is_shipping_needed()) {
                $delivery_tax_values = \common\helpers\Tax::get_tax_values($this->manager->get_platform_id(), $tax_class_id, $this->delivery['country']['id'] ?? null, $this->delivery['zone_id'] ?? null);
            } else {
                $delivery_tax_values = \common\helpers\Tax::get_tax_values($this->manager->get_platform_id(), $tax_class_id, $this->billing['country']['id'] ?? null, $this->billing['zone_id'] ?? null);
            }
        } elseif (defined('TAX_ADDRESS_OPTION') && (int) TAX_ADDRESS_OPTION == 0) {
            $delivery_tax_values = \common\helpers\Tax::get_tax_values($this->manager->get_platform_id(), $tax_class_id, $this->billing['country']['id'] ?? null, $this->billing['zone_id'] ?? null);
        } else {
            // Seems DAA specific - any of (on checkout only)
            $delivery_tax_values = \common\helpers\Tax::get_tax_values($this->manager->get_platform_id(), $tax_class_id, $this->delivery['country']['id'] ?? null, $this->delivery['zone_id'] ?? null);
            if ($delivery_tax_values['tax'] > 0) {
            } else {
                $delivery_tax_values = \common\helpers\Tax::get_tax_values($this->manager->get_platform_id(), $tax_class_id, $this->billing['country']['id'] ?? null, $this->billing['zone_id'] ?? null);
            }
        }
        return $delivery_tax_values;
    }
    public static function round($number, $precision)
    {
        if (abs($number) < 1 / pow(10, $precision + 1)) {
            $number = 0;
        }
        if (strpos($number, '.') and strlen(substr($number, strpos($number, '.') + 1)) > $precision) {
            $number = substr($number, 0, strpos($number, '.') + 1 + $precision + 1);
            if (substr($number, -1) >= 5) {
                if ($precision > 1) {
                    $number = substr($number, 0, -1) + ('0.' . str_repeat(0, $precision - 1) . '1');
                } elseif ($precision == 1) {
                    $number = substr($number, 0, -1) + 0.1;
                } else {
                    $number = substr($number, 0, -1) + 1;
                }
            } else {
                $number = substr($number, 0, -1);
            }
        }
        return $number;
    }
    /**
     * Dump method for caption instead cost in order totals
     * @see np
     * @see ot_shipping
     * @return string|bool
     */
    public function cost_user_caption()
    {
        return false;
    }
    /**
     * get platform Id (in admin - from POST, GET; common - current)
     * @return int
     */
    protected static function get_platform_id()
    {
        $platform_id = null;
        if (\Yii::$app->id == 'app-backend') {
            $platform_id = \Yii::$app->request->post('platform_id', false);
            $gets = \Yii::$app->request->get();
            if (!$platform_id && intval($gets['platform_id'] ?? null)) {
                $platform_id = intval($gets['platform_id']);
            }
            if (!$platform_id && !empty($gets['filter'])) {
                $tmp = [];
                parse_str($gets['filter'], $tmp);
                if (intval($tmp['platform_id'])) {
                    $platform_id = intval($tmp['platform_id']);
                }
            }
        }
        if (isset($this) && $this instanceof self) {
            if (!$platform_id && $this->manager && $this->manager->has('platform_id')) {
                $platform_id = $this->manager->get('platform_id');
            }
        }
        if (!$platform_id) {
            $platform_id = \common\classes\platform::current_id();
        }
        return $platform_id;
    }
    /**
     * get module encryption key from config or false
     * @return string|false
     */
    protected function get_encryption_key()
    {
        $val = false;
        //this->code - to camel case
        $key = lcfirst(str_replace(' ', '', ucwords($this->code, '_')));
        if (!empty(\Yii::$app->params[$key . 'EncryptKey']) && strlen(trim(\Yii::$app->params[$key . 'EncryptKey'])) > 8) {
            $val = \Yii::$app->params[$key . 'EncryptKey'];
        }
        return $val;
    }
    /**
     * compare encrypted values in DB and parameters
     * @param string $key
     * @param string $value
     * @param int $platform_id
     * @return bool
     */
    protected function conf_changed($key, $value, $platform_id)
    {
        $place_holder = defined('PASSWORD_HIDDEN') ? PASSWORD_HIDDEN : '--Encrypted--';
        $changed = false;
        if ($value != $place_holder) {
            $pc = new \common\classes\platform_config($platform_id);
            $old = $pc->const_value($key, '');
            $changed = $old != $value;
        }
        return $changed;
    }
    /**
     * encrypt value before save in dB
     * @param string $key
     * @param string $value
     * @param int $platform_id
     * @return string
     */
    public function conf_value_before_save($key, $value, $platform_id)
    {
        if (in_array($key, $this->encrypted_keys)) {
            if ($this->conf_changed($key, $value, $platform_id)) {
                if (!empty($value)) {
                    $key = $this->get_encryption_key();
                    if (empty($key)) {
                        $key = \Yii::$app->params['secKey.backend'];
                    }
                    $value = utf8_encode(\Yii::$app->security->encrypt_by_key($value, $key));
                }
            } else {
                $pc = new \common\classes\platform_config($platform_id);
                $value = $pc->const_value($key, '');
                //        $value = base64_decode($value );
            }
        }
        return $value;
    }
    /**
     * base 64 encoded encrypted value in text input (better look)
     * @param string $val
     * @param string $key
     * @return string (HTML input element)
     */
    public static function set_conf($val, $key)
    {
        //return \common\helpers\Html::textInput('configuration[' . $key .  ']', base64_encode($val));
        return \common\helpers\Html::text_input('configuration[' . $key . ']', empty($val) ? '' : (defined('PASSWORD_HIDDEN') ? PASSWORD_HIDDEN : '--Encrypted--'));
    }
    /**
     * text encrypted instead of encrypted string
     * @return string
     */
    public static function use_conf()
    {
        return defined('PASSWORD_HIDDEN') ? PASSWORD_HIDDEN : '--Encrypted--';
    }
    /**
     *
     * @param string $key
     * @return string
     */
    protected function decrypt_const($key)
    {
        $ret = defined($key) ? constant($key) : (new \common\classes\platform_config($this->get_platform_id()))->const_value($key);
        if (!empty($ret)) {
            $key = $this->get_encryption_key();
            if (empty($key)) {
                $key = \Yii::$app->params['secKey.backend'];
            }
            $ret = \Yii::$app->security->decrypt_by_key(utf8_decode($ret), $key);
        }
        return $ret;
    }
    public static function always()
    {
        return true;
    }
    public static function get_module_code()
    {
        return (new \ReflectionClass(get_called_class()))->get_short_name();
    }
    private const MODULE_TYPES = ['extension' => ['class' => Module_Extensions::class, 'namespace' => '\common\modules\extensions'], 'payment' => ['class' => Module_Payment::class, 'namespace' => '\common\modules\orderPayment'], 'shipping' => ['class' => Module_Shipping::class, 'namespace' => '\common\modules\orderShipping'], 'order_totals' => ['class' => Module_Total::class, 'namespace' => '\common\modules\orderTotal'], 'label' => ['class' => Module_Label::class, 'namespace' => '\common\modules\label']];
    public static function get_type()
    {
        $class = get_called_class();
        foreach (self::MODULE_TYPES as $type => $data) {
            if (is_a($class, $data['class'], true)) {
                return $type;
            }
        }
    }
    public static function is_extension()
    {
        return self::get_type() == 'extension';
    }
    public static function get_namespace(string $type)
    {
        \common\helpers\Assert::key_exists(self::MODULE_TYPES, $type, 'Unknown module type: ' . $type);
        return self::MODULE_TYPES[$type]['namespace'];
    }
    public static function get_class(string $type, string $code)
    {
        $class = self::get_namespace($type) . '\\' . $code;
        return class_exists($class) ? $class : null;
    }
    public static function get_installed($platform_id = 0)
    {
        return \common\helpers\Modules::get_module_installed(self::get_module_code(), self::get_type(), $platform_id);
    }
    public function upgrade()
    {
        \common\helpers\Modules_Migrations::up($this->code, null, self::get_type());
        \common\helpers\Modules::change_module($this->code, 'upgrade', ['version_db' => static::get_version()], self::get_type());
    }
    public function downgrade($to_ver)
    {
        \common\helpers\Modules_Migrations::down($this->code, $to_ver, self::get_type());
        \common\helpers\Modules::change_module($this->code, 'downgrade', ['version_db' => $to_ver], self::get_type());
    }
    public static function get_module($code, $type = 'extension')
    {
        $res = null;
        switch ($type) {
            case 'extension':
                $res = \common\helpers\Acl::check_extension($code, 'always');
                break;
            default:
                $res = self::get_class($type, $code);
        }
        \common\helpers\Assert::is_not_null($res, "Cannot find {$type}: {$code}");
        return $res;
    }
    public static function get_revision()
    {
    }
    public static function get_version_rev(): string
    {
        return static::get_version_obj()->to_common_format() . (is_null($rev = static::get_revision()) ? '' : ".{$rev}");
    }
    /**
     *
     * @param mixed $sinceVer
     * @param mixed $toVer
     * @return array - subarray of getVersionHistory()
     */
    public static function get_version_range($since_ver, $to_ver = null)
    {
        $since_ver = \common\classes\modules\Module_Ver::parse($since_ver);
        $to_ver = empty($to_ver) ? static::get_version() : \common\classes\modules\Module_Ver::parse($to_ver);
        $history = static::get_version_history();
        if (empty($history)) {
            return null;
        } else {
            return array_filter($history, function ($ver) use ($since_ver, $to_ver) {
                return $since_ver->compare_to($ver) < 0 && $to_ver->compare_to($ver) >= 0;
            }, ARRAY_FILTER_USE_KEY);
        }
    }
    public static function get_migration_dir()
    {
        $ref = new \ReflectionClass(get_called_class());
        return dirname($ref->get_filename()) . '/migrations';
    }
    public static function get_migration_class()
    {
        $ref = new \ReflectionClass(get_called_class());
        return $ref->get_namespace_name() . '\migrations\\';
    }
    public static function get_migration_file_mask_full($code, $ver = null)
    {
        $ver = empty($ver) ? static::get_version() : \common\classes\modules\Module_Ver::parse($ver);
        return static::get_migration_dir() . '/' . static::get_migration_file_mask($code, $ver);
    }
    public static function get_migration_file_mask($code, \common\classes\modules\Module_Ver $ver)
    {
        return sprintf('%s_v%s*.php', $code, $ver->to_file_format());
    }
    public static function get_migrations($code, $ver = null)
    {
        $mask = static::get_migration_file_mask_full($code, $ver);
        $mask = str_replace('\\\\', '/', $mask);
        $mask = str_replace('\\', '/', $mask);
        $files = glob($mask, GLOB_NOESCAPE);
        return array_map(function ($file) {
            return self::get_migration_class() . basename($file, '.php');
        }, $files);
    }
    public static function get_migrations_since($code, $since_ver, $up = true, $to_ver = null)
    {
        $range = static::get_version_range($since_ver, $to_ver);
        if (!empty($range)) {
            $versions = array_keys($range);
            if ($up) {
                $versions = array_reverse($versions);
            }
            $res = [];
            foreach ($versions as $ver) {
                $res = array_merge($res, static::get_migrations($code, $ver));
            }
            return $res;
        }
    }
    /**
     * use in your module's update_status() overwritten in modulePayment (status by billing address)
     * @param int $zone_id
     * @param string $which delivery|billing
     * @return bool true - ok false - switch off
     */
    protected function check_status_by_zone($zone_id, $which = 'delivery')
    {
        $which = strtolower($which);
        if ($which != 'billing') {
            $which = 'delivery';
        }
        $check_flag = false;
        $check_query = tep_db_query('select zone_id from ' . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . $zone_id . "' and zone_country_id = '" . ($this->{$which}['country']['id'] ?? 0) . "' order by zone_id");
        while ($check = tep_db_fetch_array($check_query)) {
            if ($check['zone_id'] < 1) {
                // zone_id == 0  => all zones
                $check_flag = true;
                break;
            } elseif ($check['zone_id'] == ($this->{$which}['zone_id'] ?? 0)) {
                $check_flag = true;
                break;
            }
        }
        return $check_flag;
    }
    /**
     *
     * @param string|array $data [external_id => SSS, customers_id => NNNN]
     * @return boolean | true - success
     */
    public function save_external_customers_id($data)
    {
        $ret = $ext_id = $cid = false;
        if (is_scalar($data)) {
            $ext_id = $data;
        } elseif (is_array($data) && isset($data['external_id'])) {
            $ext_id = $data['external_id'];
            if (isset($data['customers_id'])) {
                $cid = $data['customers_id'];
            }
        }
        if (empty($cid) && !empty($this->manager) && $this->manager->is_customer_assigned()) {
            $cid = $this->manager->get_customer_assigned();
        }
        if (!empty($ext_id) && !empty($cid)) {
            $model = \common\models\Customers_External_Ids::find_one(['customers_id' => $cid, 'system_name' => $this->code]);
            if (!$model) {
                $model = new \common\models\Customers_External_Ids();
                $model->set_attributes(['customers_id' => $cid, 'system_name' => $this->code]);
            }
            $model->external_id = $ext_id;
            try {
                $model->save(false);
                $ret = true;
            } catch (\Exception $e) {
                \Yii::warning(' #### ' . print_r($e->get_message() . ' ' . $e->get_trace_as_string(), true), 'TLDEBUG');
            }
        }
        return $ret;
    }
    public function get_external_customers_id($cid = 0)
    {
        $ret = false;
        if (empty($cid) && !empty($this->manager) && $this->manager->is_customer_assigned()) {
            $cid = $this->manager->get_customer_assigned();
        }
        if (!empty($cid)) {
            $model = \common\models\Customers_External_Ids::find_one(['customers_id' => $cid, 'system_name' => $this->code]);
            if (!empty($model->external_id)) {
                $ret = $model->external_id;
            }
        }
        return $ret;
    }
    public static function get_manual_url()
    {
        return sprintf('https://www.oscommerce.com/application/manual?app=%s&type=%s', self::get_module_code(), self::get_type());
    }
}