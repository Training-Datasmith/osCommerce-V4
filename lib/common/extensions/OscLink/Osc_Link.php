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
namespace common\extensions\Osc_Link;

use common\extensions\Osc_Link\models\Configuration;
use common\extensions\Osc_Link\models\Entity;
use common\extensions\Osc_Link\models\Mapping;
use common\helpers\Assert;
use Yii;
require_once Yii::$aliases['@common'] . '/extensions/OscLink/lib/autoload.php';
class Osc_Link extends \common\classes\modules\Module_Extensions
{
    private static $platform_array = false;
    public const FEEDS = ['tax_zones', 'taxes', 'brands', 'categories', 'products_options', 'products', 'customers', 'groups', 'reviews', 'orders'];
    public const FEEDS_NAMES = ['Tax Zones', 'Taxes', 'Brands', 'Categories', 'Products Options', 'Products', 'Customers', 'Groups', 'Reviews', 'Orders'];
    public const FEED_GROUPS = ['taxes' => ['tax_zones', 'taxes'], 'products' => ['brands', 'categories', 'products_options', 'products'], 'customers' => ['customers', 'groups', 'reviews', 'orders']];
    // <editor-fold defaultstate="collapsed" desc="actions">
    public static function admin_action_index()
    {
        if (!self::allowed()) {
            tep_redirect(Yii::$app->url_manager->create_url(['modules', 'set' => 'extensions']));
            return '';
        }
        \Yii::$app->controller->view->tab = \Yii::$app->request->get('tab');
        if (!in_array(\Yii::$app->controller->view->tab, ['tab_connection', 'tab_mapping', 'tab_actions'])) {
            \Yii::$app->controller->view->tab = 'tab_connection';
        }
        // Settings in controller->view
        \Yii::$app->controller->view->osc_state_status_array = [];
        try {
            self::downloader()->test_connection();
            \Yii::$app->controller->view->osc_state_status_array = self::get_order_state_status_array();
            \Yii::$app->controller->view->connection_success = true;
        } catch (\Exception $ex) {
            \Yii::$app->controller->view->connection_success = false;
        }
        \Yii::$app->controller->view->is_mapped_exist = Entity::is_mapped_exist();
        Yii::$app->controller->navigation[] = ['link' => Yii::$app->url_manager->create_url(['extensions', 'module' => 'OscLink']), 'title' => \common\helpers\Php8::get_const('BOX_MODULES_CONNECTORS_OSCLINK')];
        Yii::$app->controller->view->heading_title = \common\helpers\Php8::get_const('BOX_MODULES_CONNECTORS_OSCLINK');
        Yii::$app->controller->view->view_table = [['title' => \common\helpers\Php8::get_const('EXTENSION_OSCLINK_CONFIGURATION_KEY'), 'not_important' => 0], ['title' => \common\helpers\Php8::get_const('EXTENSION_OSCLINK_CONFIGURATION_VALUE'), 'not_important' => 0]];
        Yii::$app->controller->view->configuration_array = self::get_configuration_array();
        if (\Yii::$app->controller->view->connection_success) {
            Yii::$app->controller->view->configuration_array['api_status_map']['cmc_value'] = self::get_mapping_table(\Yii::$app->controller->view->osc_state_status_array);
        }
        Yii::$app->controller->view->platform_list = self::get_platform_list();
        //[0 => \common\helpers\Php8::getConst('EXTENSION_OSCLINK_API_PLATFORM_DEFAULT')];
        Yii::$app->controller->view->writer_html_interface = [];
        Yii::$app->controller->view->order_status_array = [-1 => EXTENSION_OSCLINK_TEXT_MAPPING_ITEM_SKIPPED] + \common\helpers\Order::get_status_list(false, false, 0);
        Yii::$app->controller->view->actions_array = self::get_actions_array();
        Yii::$app->controller->view->cleaning_array = self::get_cleaning_array();
        $html = Render::widget(['template' => 'index.tpl', 'params' => ['tab' => Yii::$app->controller->view->tab]]);
        return Yii::$app->controller->render_content($html);
    }
    public static function admin_action_save()
    {
        self::allowed_or_die();
        \common\helpers\Translation::init('extensions/osclink');
        Assert::assert(Yii::$app->request->is_post, 'Bad request');
        try {
            self::save_configuration(Yii::$app->request->post());
            self::check_prerequisites();
            $connection = self::downloader();
            $connection->test_connection();
            return 'success';
        } catch (\Exception $ex) {
            \Yii::warning($ex->get_message() . "\n" . $ex->get_trace_as_string());
            return \common\helpers\Php8::get_const('EXTENSION_OSCLINK_MSG_CONNECT_ERROR') . ': ' . $ex->get_message();
        }
    }
    public static function admin_action_cancel()
    {
        self::allowed_or_die();
        //Assert::assert(Yii::$app->request->isPost, 'Bad request');
        Configuration::create_cancel_sign();
        echo 'Import process will be canceled soon. Wait for finish message';
    }
    public static function admin_action_execute()
    {
        self::allowed_or_die();
        if (Yii::$app->request->is_post) {
            $feed = trim(Yii::$app->request->post('feed'));
            if (strtoupper($feed) == 'ALL') {
                $feed = self::FEEDS;
            } elseif (!in_array($feed, self::FEEDS)) {
                return false;
            }
            try {
                header('X-Accel-Buffering: no');
                //header('Content-Encoding: none;'); don't use - the error under Chrome
                Configuration::delete_cancel_sign();
                self::check_prerequisites();
                $importer = new \Osc_Link\Importer(self::get_configuration_array());
                $importer->Import($feed);
                $success = true;
            } catch (\yii\base\User_Exception $ex) {
                \Osc_Link\Progress::Log('Import was interrupted: ' . $ex->get_message());
                $success = false;
            } catch (\Throwable $ex) {
                \Yii::warning('Import was interrupted due an error: ' . $ex->get_message() . "\n" . $ex->get_trace_as_string(), 'Extensions\OscLink');
                \Osc_Link\Progress::Log('Import was interrupted due an error: ' . $ex->get_message());
                $success = false;
            }
            \Osc_Link\Progress::Done($success);
            Configuration::delete_cancel_sign();
        }
    }
    public static function admin_action_clean()
    {
        self::allowed_or_die();
        if (\Yii::$app->request->is_post) {
            $clean_fully = \Yii::$app->request->post('CleanFully') == 'fully';
            $selected_feeds = $clean_fully ? self::FEEDS : array_filter(self::FEEDS, function ($feed) {
                return \Yii::$app->request->post($feed) == 'on';
            });
            if (count($selected_feeds) > 0) {
                try {
                    Configuration::delete_cancel_sign();
                    $importer = new \Osc_Link\Importer(self::get_configuration_array());
                    $error_sum = $importer->Clean($selected_feeds);
                    if ($clean_fully) {
                        if ($error_sum > 0) {
                            \Osc_Link\Helper::progress_and_log("Mapped data won't be removed because: {$error_sum} error(s) during cleaning.");
                        } else {
                            \Osc_Link\Helper::progress_and_log('Start clean mapped data.');
                            Entity::clean_mapping();
                            \Osc_Link\Helper::progress_and_log('Mapped data was successfully removed!');
                        }
                    }
                } catch (\yii\base\User_Exception $ex) {
                    \Osc_Link\Progress::Log($ex->get_message());
                } catch (\Throwable $ex) {
                    $msg = 'Cleaning was interrupted due an error: ' . $ex->get_message();
                    \Yii::warning("{$msg}\n" . $ex->get_trace_as_string(), 'Extensions\OscLink');
                    \Osc_Link\Helper::progress_and_log($msg);
                }
                \Osc_Link\Progress::Done();
                Configuration::delete_cancel_sign();
            }
        }
    }
    public static function admin_action_show_log()
    {
        self::allowed_or_die();
        $basename = \Yii::$app->request->get('log');
        if (\Yii::$app->request->is_get && !empty($basename)) {
            try {
                $fn = \Osc_Link\Logger::build_file_name($basename);
                \common\helpers\Assert::file_exists($fn);
                //\Yii::$app->response->sendFile($fn)->send();
                Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
                return file_get_contents($fn);
            } catch (\Exception $e) {
                $error_msg = 'Error: ' . $e->get_message() . "\n";
                \Yii::warning($error_msg . $e->get_trace_as_string(), 'Extensions\OscLink');
                return \common\helpers\System::is_production() ? '' : $error_msg;
            }
        }
    }
    public static function admin_action_tab_states()
    {
        self::allowed_or_die();
        $res['cleaning'] = Entity::is_mapped_exist();
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $res;
    }
    // </editor-fold>
    // <editor-fold defaultstate="collapsed" desc="private functions">
    private static function get_platform_list()
    {
        $res = [];
        foreach (\common\classes\platform::get_list(true, true) as $platform_record) {
            $res[(int) $platform_record['id']] = $platform_record['text'];
        }
        asort($res, SORT_STRING);
        return $res;
    }
    private static function correct_platform_if_not_in_list($platform_id)
    {
        $list = self::get_platform_list();
        return isset($list[(int) $platform_id]) ? $platform_id : \common\helpers\Php8::array_key_first($list);
    }
    private static function get_actions_array()
    {
        $result = [];
        foreach (self::FEEDS as $feed) {
            $group_info = \Osc_Link\Helper::get_feed_group_info($feed);
            $result[$feed] = ['feed' => $feed, 'entity' => \Osc_Link\Helper::get_feed_name($feed), 'group_name' => $group_info['index'] == 0 ? \Osc_Link\Helper::get_group_name($group_info['group']) : '', 'group_count' => $group_info['index'] == 0 ? $group_info['count'] : 0, 'description' => \common\helpers\Php8::get_const('EXTENSION_OSCLINK_TEXT_DESCRIPTION_' . strtoupper($feed)), 'action' => \common\helpers\Php8::get_const('EXTENSION_OSCLINK_TEXT_REWRITE_BUTTON')];
        }
        return $result;
    }
    private static function get_cleaning_array()
    {
        $result = [];
        foreach (self::FEED_GROUPS as $group => $feeds) {
            $result[$group] = ['group' => $group, 'group_name' => \common\helpers\Php8::get_const('EXTENSION_OSCLINK_TEXT_GROUP_' . strtoupper($group))];
            foreach ($feeds as $feed) {
                $result[$group]['feeds'][] = ['feed' => $feed, 'feed_name' => \common\helpers\Php8::get_const('EXTENSION_OSCLINK_TEXT_ENTITY_' . strtoupper($feed))];
            }
        }
        return $result;
    }
    private static function save_configuration($arr)
    {
        $configuration_array = self::get_configuration_array();
        foreach ($arr as $key => $value) {
            try {
                $key = trim($key);
                if (isset($configuration_array[$key])) {
                    if (is_scalar($value)) {
                        $value = trim($value);
                    } elseif (is_array($value)) {
                        if ($key == 'api_status_map') {
                            $status_map_array = [];
                            if (isset($value['tstatus']) and is_array($value['tstatus'])) {
                                $check_map_array = ['t' => [], 'm' => []];
                                foreach ($value['tstatus'] as $index => $tstatus) {
                                    $tstatus = (int) $tstatus;
                                    $mstatus = (int) (isset($value['mstatus'][$index]) ? $value['mstatus'][$index] : 0);
                                    if ($tstatus > 0 and $mstatus > 0 and !isset($check_map_array['t'][$tstatus]) and !isset($check_map_array['m'][$mstatus])) {
                                        $status_map_array[$tstatus] = $mstatus;
                                        $check_map_array['t'][$tstatus] = true;
                                        $check_map_array['m'][$mstatus] = true;
                                    }
                                    unset($mstatus);
                                }
                                unset($check_map_array);
                                unset($tstatus);
                                unset($index);
                                unset($index);
                            }
                            $value = $status_map_array;
                            self::save_mapping_table($value);
                            unset($status_map_array);
                        } elseif ($key == 'api_tax_map') {
                            $tax_map_array = [];
                            if (isset($value['ttax']) and is_array($value['ttax'])) {
                                $check_map_array = ['t' => [], 'm' => []];
                                foreach ($value['ttax'] as $index => $ttax) {
                                    $ttax = (int) $ttax;
                                    $mtax = (int) (isset($value['mtax'][$index]) ? $value['mtax'][$index] : 0);
                                    if ($ttax > 0 and $mtax > 0 and !isset($check_map_array['t'][$ttax]) and !isset($check_map_array['m'][$mtax])) {
                                        $tax_map_array[$ttax] = $mtax;
                                        $check_map_array['t'][$ttax] = true;
                                        $check_map_array['m'][$mtax] = true;
                                    }
                                    unset($mtax);
                                }
                                unset($check_map_array);
                                unset($ttax);
                                unset($index);
                            }
                            $value = $tax_map_array;
                            unset($tax_map_array);
                        }
                    }
                    if ($value != $configuration_array[$key]['cmc_value']) {
                        $configuration_record = Configuration::find_one(['cmc_key' => $key]);
                        if (!$configuration_record instanceof Configuration) {
                            $configuration_record = new Configuration();
                            $configuration_record->cmc_key = $key;
                        }
                        $value = trim(is_array($value) ? json_encode($value) : $value);
                        if ($configuration_record->is_new_record or $configuration_record->cmc_value != $value) {
                            $configuration_record->cmc_value = $value;
                            $configuration_record->save(false);
                        }
                    }
                }
            } catch (\Exception $exc) {
                \Yii::error('Error while saving configuration key ' . $key . ': ' . $exc->get_message(), 'Extensions\OscLink');
            }
        }
        unset($configuration_array);
        unset($value);
        unset($key);
        self::clear_config_cache();
    }
    private static function allowed_or_die()
    {
        if (!self::allowed()) {
            die;
        }
    }
    private static $config = null;
    private static function clear_config_cache()
    {
        self::$config = null;
    }
    public static function get_configuration_array($key = '')
    {
        if (is_null(self::$config)) {
            $key = trim($key);
            $return = [];
            foreach (['connection' => 'api_url, api_method, api_key', 'mapping' => 'api_platform, api_measurement, api_status_map'] as $type => $keys) {
                foreach (explode(',', $keys) as $k) {
                    $return[trim($k)] = ['cmc_key' => trim($k), 'cmc_value' => '', 'cmc_type' => $type];
                }
            }
            $configuration_array = Configuration::find()->as_array(true)->all();
            foreach ($configuration_array as $item_array) {
                if (isset($return[$item_array['cmc_key']])) {
                    $return[$item_array['cmc_key']]['cmc_value'] = $item_array['cmc_value'];
                }
            }
            unset($configuration_array);
            unset($item_array);
            foreach ($return as &$item_array) {
                $title = 'EXTENSION_OSCLINK_' . strtoupper($item_array['cmc_key']);
                $title = \common\helpers\Php8::get_const($title);
                $item_array['title'] = $title;
                unset($title);
                if (in_array($item_array['cmc_key'], ['api_status_map', 'api_tax_map'])) {
                    $item_array['cmc_value'] = json_decode($item_array['cmc_value'], true);
                    $item_array['cmc_value'] = is_array($item_array['cmc_value']) ? $item_array['cmc_value'] : [];
                }
            }
            unset($item_array);
            $return['api_platform']['cmc_value'] = self::correct_platform_if_not_in_list($return['api_platform']['cmc_value']);
            if (!in_array($return['api_measurement']['cmc_value'], ['english', 'metric'])) {
                $return['api_measurement']['cmc_value'] = 'metric';
            }
            self::$config = $return;
        }
        return $key == '' ? self::$config : (isset(self::$config[$key]['cmc_value']) ? self::$config[$key]['cmc_value'] : false);
    }
    private static function load_mapping_array($entity_name)
    {
        return Entity::find()->select('external_id, internal_id')->join_with('mapping', false)->where(['entity_name' => $entity_name])->as_array()->index_by('external_id')->all();
    }
    private static function downloader()
    {
        return new \Osc_Link\Downloader(self::get_configuration_array());
    }
    public static function get_order_state_status_array()
    {
        $filename = self::downloader()->get_feed('order_statuses');
        $xml = simplexml_load_file($filename);
        $statuses_only_en = $xml->xpath("//OrdersStatuses/OrdersStatus[language_id[@language='en']]");
        // if english is not exist - try to find the first
        if (empty($statuses_only_en)) {
            $statuses_first = $xml->xpath('//OrdersStatuses/OrdersStatus[language_id]')[0] ?? null;
            if ($statuses_first instanceof \Simple_Xml_Element && $statuses_first->language_id instanceof \Simple_Xml_Element && !empty($statuses_first->language_id['language'])) {
                $other_lang = $statuses_first->language_id['language'][0];
                $statuses_only_en = $xml->xpath("//OrdersStatuses/OrdersStatus[language_id[@language='{$other_lang}']]");
            }
        }
        $result = [];
        foreach ($statuses_only_en as $value) {
            //            'language' => (string) $value->language_id->attributes()['language'],
            $id = (int) $value->orders_status_id->attributes()['internalId'];
            $name = (string) $value->orders_status_name;
            $result[$id] = ucfirst($name);
        }
        return $result;
    }
    public static function get_mapping_table($status_array, $entity_name = '@order_status')
    {
        $res = [];
        $map = self::load_mapping_array($entity_name);
        //        foreach($statusArray as $id=>$val) {
        //            $res[$id] = $map[$id]['internal_id'] ?? -1;
        //        }
        foreach ($map as $id => $val) {
            $res[$id] = $map[$id]['internal_id'] ?? '';
        }
        return $res;
    }
    public static function save_mapping_table($value_array, $entity_name = '@order_status')
    {
        self::clear_config_cache();
        $entity_id = Entity::force_entity_id($entity_name);
        Mapping::delete_all(['entity_id' => $entity_id]);
        foreach ($value_array as $key => $value) {
            if (!empty($value) && !empty($key)) {
                $map = new Mapping();
                $map->entity_id = $entity_id;
                $map->internal_id = $key;
                $map->external_id = $value;
                $map->save(false);
            }
        }
    }
    public static function get_link_product_tax_array()
    {
        return [2 => 'Taxable Goods', 4 => 'Shipping', 6 => 'Tax Exempt'];
    }
    public static function get_platform_value_array($key = '')
    {
        $key = trim($key);
        if (!is_array(self::$platform_array)) {
            $platform_array = ['platform_id' => (int) \common\classes\platform::default_id(), 'language_id' => (int) \common\classes\language::default_id(), 'language_code' => trim(\common\classes\language::get_code(\common\classes\language::default_id(), true)), 'currency_id' => (int) \common\helpers\Currencies::get_currency_id(\Yii::$app->settings->get('currency')), 'currency_code' => trim(\Yii::$app->settings->get('currency')), 'affiliate_id' => (int) 0, 'warehouse_id' => \common\helpers\Warehouses::get_default_warehouse(), 'supplier_id' => \common\helpers\Suppliers::get_default_supplier_id(), 'location_id' => (int) 0];
            $platform_select_array = false;
            $platform_id = (int) self::get_configuration_array('api_platform');
            if ($platform_id > 0) {
                $platform_select_array = \common\models\Platforms::find()->alias('p')->left_join(\common\models\Languages::table_name() . ' l', 'l.code = p.default_language')->left_join(\common\models\Currencies::table_name() . ' c', 'c.code = p.default_currency')->where(['p.platform_id' => $platform_id])->select(['p.platform_id', 'l.languages_id AS language_id', 'l.code AS language_code', 'c.currencies_id AS currency_id', 'c.code AS currency_code'])->as_array(true)->one();
            }
            unset($platform_id);
            $platform_default_array = \common\models\Platforms::find()->alias('p')->left_join(\common\models\Languages::table_name() . ' l', 'l.code = p.default_language')->left_join(\common\models\Currencies::table_name() . ' c', 'c.code = p.default_currency')->where(['p.is_virtual' => 0, 'p.status' => 1])->order_by(['p.is_default' => SORT_DESC, 'p.platform_id' => SORT_ASC])->select(['p.platform_id', 'l.languages_id AS language_id', 'l.code AS language_code', 'c.currencies_id AS currency_id', 'c.code AS currency_code'])->as_array(true)->one();
            if (!is_array($platform_select_array)) {
                $platform_select_array = $platform_default_array;
            } else {
                if ((int) $platform_select_array['language_id'] <= 0) {
                    $platform_select_array['language_id'] = (int) $platform_default_array['language_id'];
                    $platform_select_array['language_code'] = trim($platform_default_array['language_code']);
                }
                if ((int) $platform_select_array['currency_id'] <= 0) {
                    $platform_select_array['currency_id'] = (int) $platform_default_array['currency_id'];
                    $platform_select_array['currency_code'] = trim($platform_default_array['currency_code']);
                }
            }
            unset($platform_default_array);
            if (is_array($platform_select_array)) {
                $platform_select_array['affiliate_id'] = $platform_array['affiliate_id'];
                $platform_select_array['warehouse_id'] = $platform_array['warehouse_id'];
                $platform_select_array['supplier_id'] = $platform_array['supplier_id'];
                $platform_select_array['location_id'] = $platform_array['location_id'];
                if ((int) $platform_select_array['language_id'] <= 0) {
                    $platform_select_array['language_id'] = (int) $platform_array['language_id'];
                    $platform_select_array['language_code'] = trim($platform_array['language_code']);
                }
                if ((int) $platform_select_array['currency_id'] <= 0) {
                    $platform_select_array['currency_id'] = (int) $platform_array['currency_id'];
                    $platform_select_array['currency_code'] = trim($platform_array['currency_code']);
                }
                $platform_array = $platform_select_array;
            }
            unset($platform_select_array);
            $platform_array = ['platform_id' => (int) $platform_array['platform_id'], 'language_id' => (int) $platform_array['language_id'], 'language_code' => trim($platform_array['language_code']), 'currency_id' => (int) $platform_array['currency_id'], 'currency_code' => trim($platform_array['currency_code']), 'affiliate_id' => (int) $platform_array['affiliate_id'], 'warehouse_id' => (int) $platform_array['warehouse_id'], 'supplier_id' => (int) $platform_array['supplier_id'], 'location_id' => (int) $platform_array['location_id']];
            self::$platform_array = $platform_array;
            unset($platform_array);
        }
        return $key == '' ? self::$platform_array : (isset(self::$platform_array[$key]) ? self::$platform_array[$key] : null);
    }
    private static function check_prerequisites()
    {
        \common\helpers\Assert_User::assert(ini_get('allow_url_fopen'), 'PHP option <a href="https://www.php.net/manual/en/filesystem.configuration.php#ini.allow-url-fopen" target="_blank">allow_url_option</a> must be enabled for this operation. Please correct settings in your php.ini');
    }
    // </editor-fold>
}