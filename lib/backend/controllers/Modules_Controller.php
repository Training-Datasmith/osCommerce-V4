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
namespace backend\controllers;

use common\classes\modules;
use common\classes\platform;
use common\helpers\Translation;
use Yii;
class Modules_Controller extends Sceleton
{
    public $acl = ['BOX_HEADING_MODULES'];
    public $module_type;
    public $module_entity;
    public $module_directory;
    public $module_need_requiring;
    public $module_key;
    public $module_key_sort;
    public $module_class;
    public $module_namespace;
    protected $module_const_prefix;
    public $enabled;
    public $validated_extensions = ['php'];
    protected $selected_platform_id;
    private $_page_title = '';
    public function __construct($id, $module)
    {
        parent::__construct($id, $module);
        $this->selected_platform_id = \common\classes\platform::first_id();
        $try_set_platform = Yii::$app->request->get('platform_id', 0);
        if (Yii::$app->request->is_post) {
            $try_set_platform = Yii::$app->request->post('platform_id', $try_set_platform);
        }
        if ($try_set_platform > 0) {
            foreach (\common\classes\platform::get_list(false) as $_platform) {
                if ((int) $try_set_platform == (int) $_platform['id']) {
                    $this->selected_platform_id = (int) $try_set_platform;
                    break;
                }
            }
        }
        Yii::$app->get('platform')->config($this->selected_platform_id)->constant_up();
        \common\helpers\Translation::init('admin/modules');
    }
    public function rules($set)
    {
        if (tep_not_null($set)) {
            $this->module_need_requiring = true;
            switch ($set) {
                case 'label':
                    \common\helpers\Acl::check_access(['BOX_HEADING_MODULES', 'BOX_MODULES_LABEL']);
                    $path = \Yii::get_alias('@common');
                    $path .= DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'label' . DIRECTORY_SEPARATOR;
                    $this->module_type = 'label';
                    $this->module_entity = 'label';
                    $this->module_directory = $path;
                    $this->module_key = 'MODULE_LABEL_INSTALLED';
                    $this->module_key_sort = 'DD_MODULE_LABEL_SORT';
                    $this->module_const_prefix = 'MODULE_LABEL_';
                    $this->module_class = 'ModuleLabel';
                    $this->_page_title = HEADING_TITLE_MODULES_LABEL;
                    $this->module_need_requiring = false;
                    $this->module_namespace = 'common\modules\label\\';
                    break;
                case 'shipping':
                    \common\helpers\Acl::check_access(['BOX_HEADING_MODULES', 'BOX_MODULES_SHIPPING']);
                    $this->module_type = 'shipping';
                    $this->module_entity = 'shipping';
                    $path = \Yii::get_alias('@common');
                    $path .= DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'orderShipping' . DIRECTORY_SEPARATOR;
                    $this->module_directory = $path;
                    //$this->module_directory = DIR_FS_CATALOG_MODULES . 'shipping/';
                    $this->module_key = 'MODULE_SHIPPING_INSTALLED';
                    $this->module_key_sort = 'DD_MODULE_SHIPPING_SORT';
                    $this->module_const_prefix = 'MODULE_SHIPPING_';
                    $this->module_class = 'ModuleShipping';
                    $this->_page_title = HEADING_TITLE_MODULES_SHIPPING;
                    //define('HEADING_TITLE', HEADING_TITLE_MODULES_SHIPPING);
                    $this->module_need_requiring = false;
                    $this->module_namespace = 'common\modules\orderShipping\\';
                    break;
                case 'dropshipping':
                    \common\helpers\Acl::check_access(['BOX_HEADING_MODULES', 'BOX_MODULES_DROPSHIPPING']);
                    $this->module_type = 'dropshipping';
                    $this->module_entity = 'dropshipping';
                    $this->module_directory = DIR_FS_CATALOG_MODULES . 'dropshipping/';
                    $this->module_key = 'MODULE_DROPSHIPPING_INSTALLED';
                    $this->module_key_sort = 'DD_MODULE_DROPSHIPPING_SORT';
                    $this->module_const_prefix = 'MODULE_DROPSHIPPING_';
                    $this->module_class = 'ModuleDropShipping';
                    $this->_page_title = HEADING_TITLE_MODULES_DROPSHIPPING;
                    //define('HEADING_TITLE', HEADING_TITLE_MODULES_SHIPPING);
                    break;
                case 'ordertotal':
                    \common\helpers\Acl::check_access(['BOX_HEADING_MODULES', 'BOX_MODULES_ORDER_TOTAL']);
                    $this->module_type = 'order_total';
                    $this->module_entity = 'ordertotal';
                    $path = \Yii::get_alias('@common');
                    $path .= DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'orderTotal' . DIRECTORY_SEPARATOR;
                    $this->module_directory = $path;
                    //$this->module_directory = DIR_FS_CATALOG_MODULES . 'order_total/';
                    $this->module_key = 'MODULE_ORDER_TOTAL_INSTALLED';
                    $this->module_key_sort = 'DD_MODULE_ORDER_TOTAL_SORT';
                    $this->module_const_prefix = 'MODULE_ORDER_TOTAL_';
                    $this->module_class = 'ModuleTotal';
                    $this->_page_title = HEADING_TITLE_MODULES_ORDER_TOTAL;
                    //define('HEADING_TITLE', HEADING_TITLE_MODULES_ORDER_TOTAL);
                    $this->module_need_requiring = false;
                    $this->module_namespace = 'common\modules\orderTotal\\';
                    break;
                case 'extensions':
                    \common\helpers\Acl::check_access(['BOX_HEADING_MODULES', 'BOX_MODULES_EXTENSIONS']);
                    $path = \Yii::get_alias('@common') . DIRECTORY_SEPARATOR . 'extensions' . DIRECTORY_SEPARATOR;
                    $this->module_type = 'extensions';
                    $this->module_entity = 'extensions';
                    $this->module_directory = $path;
                    $this->module_key = 'MODULE_EXTENSIONS_INSTALLED';
                    $this->module_key_sort = 'DD_MODULE_EXTENSIONS_SORT';
                    $this->module_const_prefix = '';
                    $this->module_class = 'ModuleExtensions';
                    $this->_page_title = HEADING_TITLE_MODULES_EXTENSIONS;
                    $this->module_need_requiring = false;
                    $this->selected_platform_id = 0;
                    break;
                case 'payment':
                default:
                    \common\helpers\Acl::check_access(['BOX_HEADING_MODULES', 'BOX_MODULES_PAYMENT']);
                    $this->module_type = 'payment';
                    $this->module_entity = 'payment';
                    $path = \Yii::get_alias('@common');
                    $path .= DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'orderPayment' . DIRECTORY_SEPARATOR;
                    $this->module_directory = $path;
                    //$this->module_directory = DIR_FS_CATALOG_MODULES . 'payment/';
                    $this->module_key = 'MODULE_PAYMENT_INSTALLED';
                    $this->module_key_sort = 'DD_MODULE_PAYMENT_SORT';
                    $this->module_const_prefix = 'MODULE_PAYMENT_';
                    $this->module_class = 'ModulePayment';
                    $this->_page_title = HEADING_TITLE_MODULES_PAYMENT;
                    //define('HEADING_TITLE', HEADING_TITLE_MODULES_PAYMENT);
                    $this->module_need_requiring = false;
                    $this->module_namespace = 'common\modules\orderPayment\\';
                    break;
            }
        }
    }
    public function action_ppp_install()
    {
        $module = Yii::$app->request->post('module', '');
        $ppp_next = (int) Yii::$app->request->post('ppp_next', 0);
        $platform_id = (int) Yii::$app->request->post('platform_id');
        $set = Yii::$app->request->post('set', 'payment');
        $test_mode = Yii::$app->request->post('test_mode', false);
        $golive = Yii::$app->request->post('golive', false);
        if ($golive === 'false') {
            $golive = false;
        }
        if ($test_mode === 'false') {
            $test_mode = false;
        }
        if (!empty($module)) {
            if (!$golive) {
                $inst_r = $this->action_change();
            }
            $conf = \common\models\Platforms_Configuration::find_one(['configuration_key' => tep_db_input('MODULE_PAYMENT_PAYPAL_PARTNER_TRANSACTION_SERVER'), 'platform_id' => intval($this->selected_platform_id)]);
            if ($conf) {
                try {
                    $conf->configuration_value = 'Live';
                    if ($test_mode && !$golive) {
                        $conf->configuration_value = 'Sandbox';
                    }
                    $conf->save(false);
                } catch (\Exception $e) {
                    \Yii::warning($e->get_message());
                }
            }
            if (!empty($inst_r)) {
                $this->set_module_sort_first($module);
            }
            if (!empty($inst_r) || $golive) {
                // ppp_next =2 create business ppp_next=3 create individual
                return $this->as_json(['redirect' => \Yii::$app->url_manager->create_url(['modules/edit', 'platform_id' => $platform_id, 'set' => $set, 'module' => \common\helpers\Output::mb_basename($module), 'ppp_next' => $ppp_next, 'test_mode' => $test_mode]) . '#extra']);
            }
        }
    }
    protected function set_module_sort_first($module)
    {
        if (!empty($module)) {
            $module = \common\helpers\Output::mb_basename($module);
            $conf = \common\models\Platforms_Configuration::find_one(['configuration_key' => tep_db_input($this->module_key), 'platform_id' => intval($this->selected_platform_id)]);
            if (!empty($conf)) {
                $sorted = explode(';', $conf->configuration_value);
                if ($key = array_search($module . '.php', $sorted) !== false) {
                    unset($sorted[$key]);
                    array_unshift($sorted, $module . '.php');
                } elseif ($key = array_search($module, $sorted) !== false) {
                    unset($sorted[$key]);
                    array_unshift($sorted, $module);
                }
                $new_sort = implode(';', $sorted);
                if ($new_sort != $conf->configuration_value) {
                    try {
                        $conf->configuration_value = $new_sort;
                        $conf->save();
                        ///vl2do update module SORT_ORDER const
                    } catch (\Exception $e) {
                        \Yii::warning($e->get_message(), 'TLMODULES');
                    }
                }
            }
        }
    }
    public function action_ppp_status()
    {
        //could be overloaded platform (Default sales channel) $platform_config = new \common\classes\platform_config($this->selected_platform_id);
        $set = Yii::$app->request->get('set', 'payment');
        $type = Yii::$app->request->get('type', '');
        /*$ppp = \common\models\PlatformsConfiguration::findOne([
              'configuration_key' => 'MODULE_PAYMENT_PAYPAL_PARTNER_STATUS',
              'platform_id' => $this->selected_platform_id
          ]);*/
        //$ppp = $platform_config->const_value('MODULE_PAYMENT_PAYPAL_PARTNER_STATUS', null);
        $ret = ['installPPP' => false];
        if (is_null($ppp ?? null) && $set == 'payment' && $type == 'online') {
            $class = 'paypal_partner';
            $file = $class . '.php';
            if (!class_exists($class) or !is_subclass_of($class, 'common\classes\modules\ModulePayment')) {
                $class = 'common\modules\orderPayment\\' . $class;
            }
            if ($this->module_need_requiring) {
                require_once $this->module_directory . $file;
            }
            if (class_exists($class) && is_subclass_of($class, 'common\classes\modules\ModulePayment')) {
                /**
                 * @var modules\Module $module
                 */
                $module = new $class();
                //1 - sandbox 2 - live 3 - both partner's keys
                $ret = $module->get_install_options($this->selected_platform_id);
            }
        }
        return json_encode($ret);
    }
    private function set_acl($set, $type)
    {
        switch ($set) {
            case 'payment':
                $this->acl[] = 'BOX_MODULES_PAYMENT';
                if ($type == 'online') {
                    $this->acl[] = 'BOX_MODULES_PAYMENT_ONLINE';
                } else {
                    $this->acl[] = 'BOX_MODULES_PAYMENT_OFFLINE';
                }
                break;
            case 'shipping':
                $this->acl[] = 'BOX_MODULES_SHIPPING';
                if ($type == 'online') {
                    $this->acl[] = 'BOX_MODULES_SHIPPING_ONLINE';
                } else {
                    $this->acl[] = 'BOX_MODULES_SHIPPING_OFFLINE';
                }
                break;
            case 'label':
                $this->acl[] = 'BOX_MODULES_LABEL';
                break;
            case 'ordertotal':
                $this->acl[] = 'BOX_MODULES_ORDER_TOTAL';
                break;
            case 'extensions':
                $this->acl[] = 'BOX_MODULES_EXTENSIONS';
                break;
            case 'dropshipping':
                $this->acl[] = 'BOX_MODULES_DROPSHIPPING';
                break;
        }
    }
    public function action_index()
    {
        $set = Yii::$app->request->get('set', 'payment');
        $type = Yii::$app->request->get('type', 'online');
        $this->set_acl($set, $type);
        $this->rules($set);
        \common\helpers\Acl::check_access($this->acl);
        $oldaction = Yii::$app->request->get('action', '');
        $subaction = Yii::$app->request->get('subaction', '');
        $module = Yii::$app->request->get('module', '');
        if ($set == 'payment' && $oldaction == 'install' && $subaction == 'conntest' && $module != '') {
            // paypal's tests
            /// suppose not implemented after move payemnts to common/modules
            $ret = '';
            $file = $module . '.php';
            $platform_config = Yii::$app->get('platform')->config($this->selected_platform_id);
            require_once $this->module_directory . $file;
            $class = $module;
            if (class_exists($class) && is_subclass_of($class, "common\\classes\\modules\\{$this->module_class}")) {
                $module = new $class();
                if (method_exists($module, 'getTestConnectionResult')) {
                    $ret = $module->get_test_connection_result();
                }
            }
            return $ret;
            //'~~~~~' . $ret . '!!! ' . $this->selected_platform_id . '+++' .print_r($platform_config,1);
        }
        $ppp = null;
        $ppp_data = ['installPPP' => false];
        if ($set == 'payment' && $type == 'online') {
            /*            $ppp = \common\models\PlatformsConfiguration::findOne([
                                        'configuration_key' => 'MODULE_PAYMENT_PAYPAL_PARTNER_STATUS',
                                        'platform_id' => $this->selected_platform_id
                                    ]);
                        */
            if (is_null($ppp)) {
                $class = 'paypal_partner';
                $file = $class . '.php';
                if (!class_exists($class) or !is_subclass_of($class, 'common\classes\modules\ModulePayment')) {
                    if (strpos($class, $this->module_namespace) === false) {
                        $class = $this->module_namespace . $class;
                    }
                }
                if ($this->module_need_requiring) {
                    require_once $this->module_directory . $file;
                }
                if (class_exists($class) && is_subclass_of($class, 'common\classes\modules\ModulePayment')) {
                    /**
                     * @var modules\Module $module
                     */
                    $module = new $class();
                    if ($this->module_type == 'payment') {
                        if (method_exists($module, 'updateTitle')) {
                            $module->update_title($this->selected_platform_id);
                        }
                    }
                    //1 - sandbox 2 - live 3 - both partner's keys
                    $ppp_data = $module->get_install_options($this->selected_platform_id);
                }
            }
        }
        $this->selected_menu = ['modules', 'modules?set=' . $set];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('modules/index'), 'title' => $this->_page_title];
        $this->view->heading_title = $this->_page_title;
        $this->view->modules_table = [
            /*array(
            'title' => '<input type="checkbox" class="uniform">',
            'not_important' => 2
                        ),*/
            ['title' => TABLE_HEADING_MODULES, 'not_important' => 0],
            ['title' => TABLE_TEXT_STATUS, 'not_important' => 3],
        ];
        $this->view->filters = new \stdClass();
        $this->view->filters->row = (int) Yii::$app->request->get('row', 0);
        $this->view->filters->all_countries = (int) Yii::$app->request->get('all_countries', 0);
        $this->view->filters->inactive = (int) Yii::$app->request->get('inactive', 0);
        $this->view->filters->not_installed = (int) Yii::$app->request->get('not_installed', 0);
        $_platforms = \common\classes\platform::get_list(false);
        foreach ($_platforms as $_idx => $_platform) {
            $_platforms[$_idx]['link'] = Yii::$app->url_manager->create_url(['modules/index', 'platform_id' => $_platform['id'], 'set' => $set]);
        }
        $type = Yii::$app->request->get('type', '');
        return $this->render('index', ['set' => $set, 'type' => $type, 'platforms' => $_platforms, 'isMultiPlatforms' => $this->module_type != 'extensions' && \common\classes\platform::is_multi(false), 'selected_platform_id' => $this->selected_platform_id] + $ppp_data);
    }
    private function directory_list()
    {
        $directory_array = [];
        if ($dir = @dir($this->module_directory)) {
            while ($file = $dir->read()) {
                if (!is_dir($this->module_directory . $file)) {
                    if (in_array($this->module_type, ['label', 'order_total', 'payment', 'shipping'])) {
                        $directory_array[] = $this->module_namespace . $file;
                    } elseif (in_array(substr($file, strrpos($file, '.') + 1), $this->validated_extensions)) {
                        $directory_array[] = $file;
                    }
                } elseif ($ext = \common\helpers\Acl::check_extension($file, 'allowed')) {
                    $directory_array[] = $ext;
                }
            }
            sort($directory_array);
            $dir->close();
        }
        return $directory_array;
    }
    /**
     * @param modules\Module $module
     * @param $set
     * @return \objectInfo
     */
    private function create_info($module, $set)
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $module_info = ['code' => $module->code, 'title' => $module->title, 'description' => isset($module->description) ? $module->description : '', 'status' => $module->check($this->selected_platform_id)];
        $module_keys = $module->keys();
        $module_keys_full = $module->configure_keys();
        $keys_extra = [];
        for ($j = 0, $k = sizeof($module_keys); $j < $k; $j++) {
            $key_value_query = tep_db_query('select configuration_title, configuration_value, configuration_description, use_function, set_function ' . 'from ' . TABLE_PLATFORMS_CONFIGURATION . ' ' . "where platform_id = '" . intval($this->selected_platform_id) . "' AND configuration_key = '" . tep_db_input($module_keys[$j]) . "'");
            $key_value = tep_db_fetch_array($key_value_query);
            if (is_array($key_value)) {
                $keys_extra[$module_keys[$j]]['title'] = tep_not_null($value = Translation::get_translation_value($module_keys[$j] . '_TITLE', $set, $languages_id)) ? $value : $key_value['configuration_title'];
                $keys_extra[$module_keys[$j]]['value'] = $key_value['configuration_value'];
                $keys_extra[$module_keys[$j]]['description'] = tep_not_null($value = Translation::get_translation_value($module_keys[$j] . '_DESCRIPTION', $set, $languages_id)) ? $value : $key_value['configuration_description'];
                $keys_extra[$module_keys[$j]]['use_function'] = $key_value['use_function'];
                $keys_extra[$module_keys[$j]]['set_function'] = $key_value['set_function'];
                $keys_extra[$module_keys[$j]]['area'] = $module_keys_full[$module_keys[$j]]['area'] ?? null;
            }
        }
        $module_info['keys'] = $keys_extra;
        return new \Object_Info($module_info);
    }
    private function fetch_arrays($module, $file, &$installed_modules, &$modules_files)
    {
        /**
         * @var modules\Module $module
         */
        if ($module->check($this->selected_platform_id) > 0) {
            $modules_files[$module->code] = $file;
            $sort_key = $module->describe_sort_key();
            $module_sort_order = $module->sort_order;
            if (is_object($sort_key) && is_a($sort_key, 'common\classes\modules\ModuleSortOrder')) {
                $get_sort_order_value_r = tep_db_query('select configuration_value ' . 'from ' . TABLE_PLATFORMS_CONFIGURATION . ' ' . "where platform_id = '" . intval($this->selected_platform_id) . "' AND configuration_key = '" . tep_db_input($sort_key->key) . "'");
                if (tep_db_num_rows($get_sort_order_value_r) > 0) {
                    $_sort_order_value = tep_db_fetch_array($get_sort_order_value_r);
                    $module_sort_order = $_sort_order_value['configuration_value'];
                }
            }
            if ($module_sort_order > 0 && !isset($installed_modules[$module_sort_order])) {
                $installed_modules[$module_sort_order] = $file;
            } else {
                $installed_modules[] = \common\helpers\Acl::check_extension($file, 'allowed') ? (new \ReflectionClass($file))->get_short_name() : $file;
            }
        }
    }
    private function generate_click_func_for_remove_btn($module)
    {
        if ($module instanceof \common\classes\modules\Module) {
            $is_enabled = $module->is_module_enabled($this->selected_platform_id);
            $res = sprintf("changeModule('%s', 'remove', %s", $module->code, $is_enabled ? 'true' : 'false');
            if ($module instanceof \common\classes\modules\Module_Extensions) {
                $res .= ', [';
                if ($module->is_able_to_drop_datatables()) {
                    $res .= "'tables',";
                }
                if ($module->is_able_to_delete_acl()) {
                    $res .= "'acl',";
                }
                $res = rtrim($res, ',');
                $res .= ']';
            }
            $res .= ')';
            return $res;
        }
    }
    private function generate_click_func_for_install_btn($module)
    {
        if ($module instanceof \common\classes\modules\Module) {
            $res = sprintf("changeModule('%s', 'install'", $module->code);
            if ($module instanceof \common\classes\modules\Module_Extensions) {
                if ($module->is_able_to_delete_acl()) {
                    $res .= ', false, true';
                }
            }
            $res .= ')';
            return $res;
        }
    }
    public function action_list()
    {
        $draw = Yii::$app->request->get('draw', 1);
        $search = Yii::$app->request->get('search', '');
        $start = Yii::$app->request->get('start', 0);
        $length = Yii::$app->request->get('length', 15);
        $set = Yii::$app->request->get('set', 'payment');
        $form_filter = Yii::$app->request->get('filter');
        parse_str($form_filter, $output);
        if (isset($output['platform_id'])) {
            $this->selected_platform_id = (int) $output['platform_id'];
        }
        if (isset($output['all_countries']) && $output['all_countries'] == 1) {
            $show_all_countries = true;
        } else {
            $show_all_countries = false;
        }
        if (isset($output['not_installed']) && $output['not_installed'] == 1) {
            $show_not_installed = true;
        } else {
            $show_not_installed = false;
        }
        if (isset($output['inactive']) && $output['inactive'] == 1) {
            $show_inactive = true;
        } else {
            $show_inactive = false;
        }
        if (isset($output['type']) && !empty($output['type'])) {
            $type = $output['type'];
        }
        $this->rules($set);
        //$file_extension = substr($PHP_SELF, strrpos($PHP_SELF, '.'));
        $directory_array = $this->directory_list();
        $active_modules = [];
        $installed_modules = [];
        $modules_files = [];
        $response_list = [];
        $_search_active = false;
        $_sort_by_title = [];
        \common\helpers\Translation::init($this->module_entity);
        $platform_country = platform::country($this->selected_platform_id);
        for ($i = 0, $n = sizeof($directory_array); $i < $n; $i++) {
            $file = $directory_array[$i];
            $class = strpos($file, '.') !== false ? substr($file, 0, strrpos($file, '.')) : $file;
            if ($this->module_need_requiring) {
                require_once $this->module_directory . $file;
            }
            /* else {
                   $class = "common\\modules\\label\\" . $class;
               }*/
            if (class_exists($class) && is_subclass_of($class, "common\\classes\\modules\\{$this->module_class}")) {
                $module = new $class();
                $skip = false;
                if ($this->module_type == 'payment' || $this->module_type == 'shipping') {
                    if (method_exists($module, 'updateTitle')) {
                        $module->update_title($this->selected_platform_id);
                    }
                    switch ($type) {
                        case 'online':
                            $skip = !$module->is_online();
                            break;
                        case 'offline':
                            $skip = $module->is_online();
                            break;
                        default:
                            break;
                    }
                }
                /**
                 * @var modules\Module $module
                 */
                if (is_array($search) && !empty($search['value'])) {
                    $_search_active = true;
                    if (stripos($module->title, $search['value']) === false) {
                        continue;
                    }
                }
                $this->fetch_arrays($module, $file, $installed_modules, $modules_files);
                $m_info = $this->create_info($module, $set);
                if ($skip) {
                    continue;
                }
                $_sort_by_title[$file] = strtolower($module->title);
                $installed = false;
                $active = false;
                $buttons = '';
                if ($m_info->status == '1') {
                    $installed = true;
                    $active = $module->is_module_enabled($this->selected_platform_id);
                    $buttons .= '<button class="btn btn-small" onClick="return ' . $this->generate_click_func_for_remove_btn($module) . '" title="' . TEXT_REMOVE . '">' . TEXT_REMOVE . '</button>';
                } else {
                    $active = false;
                    $param_remove_data = $set === 'extensions' && $module->is_able_to_delete_acl() ? ',false, true' : '';
                    $buttons .= '<input type="button" class="btn btn-small" title="' . IMAGE_INSTALL . '" value="' . \common\helpers\Output::output_string(IMAGE_INSTALL) . '" onClick="return ' . $this->generate_click_func_for_install_btn($module) . '">';
                }
                $active_modules[$file] = $active;
                $edit_link = Yii::$app->url_manager->create_url(['modules/edit', 'platform_id' => $this->selected_platform_id, 'set' => $set, 'module' => $m_info->code]);
                if (in_array('', $module->get_countries($this->selected_platform_id)) || in_array($platform_country->countries_iso_code_3, $module->get_countries($this->selected_platform_id)) || $show_all_countries) {
                    $response_list[$file] = [
                        //'<input type="checkbox" class="uniform">',
                        '<div class="handle_cat_list click_double" ' . ($installed ? ' data-click-double="' . $edit_link . '"' : '') . '><span class="handle"><i class="icon-hand-paper-o"></i></span><div class="module_title' . ($active ? '' : ' dis_module') . '">' . (defined($module->title . '_TITLE') ? constant($module->title . '_TITLE') : $module->title) . tep_draw_hidden_field('module', $class, 'class="cell_identify" data-installed="' . ($installed ? 'true' : 'false') . '"') . '</div></div>',
                        ($installed ? '<input name="enabled" type="checkbox" data-module="' . $module->code . '" class="check_on_off" ' . ($active ? 'checked' : '') . '>' : '') . (empty($buttons) ? '' : $buttons),
                    ];
                }
            }
        }
        $get_actual_value = tep_db_fetch_array(tep_db_query('SELECT configuration_value FROM ' . TABLE_PLATFORMS_CONFIGURATION . " WHERE configuration_key='" . tep_db_input($this->module_key_sort) . "' AND platform_id='" . intval($this->selected_platform_id) . "'"));
        $get_actual_installed_value = tep_db_fetch_array(tep_db_query('SELECT configuration_value FROM ' . TABLE_PLATFORMS_CONFIGURATION . " WHERE configuration_key='" . tep_db_input($this->module_key) . "' AND platform_id='" . intval($this->selected_platform_id) . "'"));
        if (false && is_array($get_actual_value) && !empty($get_actual_value['configuration_value'])) {
            $new_response_list = [];
            foreach (explode(';', $get_actual_value['configuration_value']) as $__push_key) {
                if (!isset($response_list[$__push_key])) {
                    continue;
                }
                $new_response_list[] = $response_list[$__push_key];
                unset($response_list[$__push_key]);
            }
            $response_list = array_merge($new_response_list, array_values($response_list));
        } else {
            // {{
            if (!in_array($this->module_type, ['extensions'])) {
                $_response_list = [];
                foreach ($response_list as $__path_key => $__value) {
                    if (strpos($__path_key, '\\') !== false) {
                        $__path_key = substr($__path_key, strrpos($__path_key, '\\') + 1);
                    }
                    $_response_list[$__path_key] = $__value;
                }
                $response_list = $_response_list;
                $_active_modules = $active_modules;
                foreach ($active_modules as $__path_key => $__value) {
                    if (strpos($__path_key, '\\') !== false) {
                        $__path_key = substr($__path_key, strrpos($__path_key, '\\') + 1);
                    }
                    $_active_modules[$__path_key] = $__value;
                }
                $active_modules = $_active_modules;
            }
            // }}
            //sort installed by sort key, then uninstalled by title
            $_installed_top = [];
            $_check_installed = $installed_modules;
            if (isset($get_actual_installed_value['configuration_value'])) {
                foreach (explode(';', $get_actual_installed_value['configuration_value']) as $__installed_module_file) {
                    if (!isset($response_list[$__installed_module_file])) {
                        continue;
                    }
                    if (isset($_check_installed[$__installed_module_file])) {
                        unset($_check_installed[$__installed_module_file]);
                    }
                    if ($show_inactive || $active_modules[$__installed_module_file]) {
                        $_installed_top[] = $response_list[$__installed_module_file];
                    }
                    unset($response_list[$__installed_module_file]);
                }
            }
            if (count($_check_installed) > 0) {
                foreach ($_check_installed as $__installed_module_file) {
                    if (!isset($response_list[$__installed_module_file])) {
                        continue;
                    }
                    if ($show_inactive || $active_modules[$__installed_module_file]) {
                        $_installed_top[] = $response_list[$__installed_module_file];
                    }
                    unset($response_list[$__installed_module_file]);
                }
            }
            asort($_sort_by_title, SORT_STRING);
            $_sort_uninstalled = array_keys($_sort_by_title);
            if (is_array($get_actual_value) && !empty($get_actual_value['configuration_value'])) {
                $_sort_uninstalled = explode(';', $get_actual_value['configuration_value']);
            }
            //$responseList = array_merge($_installed_top, array_values($responseList));
            $new_response_list = $_installed_top;
            if ($show_not_installed) {
                if (count($response_list) > 0) {
                    $new_response_list[] = ['<span class="modules_divider"></span>', '<span class="modules_divider"></span>'];
                    // blackline
                }
                foreach ($_sort_uninstalled as $__push_key) {
                    if (!isset($response_list[$__push_key])) {
                        continue;
                    }
                    $new_response_list[] = $response_list[$__push_key];
                    unset($response_list[$__push_key]);
                }
                $response_list = array_merge($new_response_list, array_values($response_list));
            } else {
                $response_list = $new_response_list;
            }
        }
        $response = ['draw' => $draw, 'recordsTotal' => sizeof($directory_array), 'recordsFiltered' => sizeof($directory_array), 'params' => ['set' => $set, 'platform_id' => $this->selected_platform_id], 'data' => $response_list];
        if (!$_search_active && $this->module_type != 'extensions') {
            foreach ($installed_modules as $key => $file) {
                if (preg_match('/common\\\\modules\\\\[^\\\\]+\\\\(.+)$/si', $file, $match)) {
                    $installed_modules[$key] = $match[1];
                }
            }
            ksort($installed_modules);
            $check_query = tep_db_query('select configuration_value from ' . TABLE_PLATFORMS_CONFIGURATION . " where configuration_key = '" . tep_db_input($this->module_key) . "' AND platform_id='" . intval($this->selected_platform_id) . "'");
            if (tep_db_num_rows($check_query)) {
                $check = tep_db_fetch_array($check_query);
                if ($check['configuration_value'] != implode(';', $installed_modules)) {
                    tep_db_query('update ' . TABLE_PLATFORMS_CONFIGURATION . " set configuration_value = '" . implode(';', array_map('tep_db_input', $installed_modules)) . "', last_modified = now() where configuration_key = '" . tep_db_input($this->module_key) . "' AND platform_id='" . intval($this->selected_platform_id) . "'");
                }
            } else {
                tep_db_query('insert into ' . TABLE_PLATFORMS_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, platform_id) values ('Installed Modules', '" . tep_db_input($this->module_key) . "', '" . implode(';', array_map('tep_db_input', $installed_modules)) . "', 'This is automatically updated. No need to edit.', '6', '0', now(), '" . intval($this->selected_platform_id) . "')");
            }
        }
        echo json_encode($response);
    }
    public function action_view()
    {
        $result = new \stdClass();
        $languages_id = \Yii::$app->settings->get('languages_id');
        $set = Yii::$app->request->post('set', 'payment');
        $file = Yii::$app->request->post('module', '');
        $enabled = Yii::$app->request->post('enabled', '');
        if (empty($file)) {
            die;
        }
        $file .= '.php';
        $this->rules($set);
        $installed_modules = [];
        $modules_files = [];
        \common\helpers\Translation::init($this->module_entity);
        $class = substr($file, 0, strrpos($file, '.'));
        if ($this->module_need_requiring) {
            //if ($this->module_type != 'label') {
            require_once $this->module_directory . $file;
        }
        if (!class_exists($class) or !is_subclass_of($class, "common\\classes\\modules\\{$this->module_class}")) {
            if (!empty($this->module_namespace) && strpos($class, $this->module_namespace) === false) {
                $class = $this->module_namespace . $class;
            }
        }
        if (class_exists($class) && is_subclass_of($class, "common\\classes\\modules\\{$this->module_class}")) {
            $module = new $class();
            $manual_url = $class::get_manual_url();
            $this->fetch_arrays($module, $file, $installed_modules, $modules_files);
            if ($m_info = $this->create_info($module, $set)) {
                $sort_order_key_name = $this->module_const_prefix . strtoupper($class) . '_SORT_ORDER';
                if ($this->module_type == 'order_total') {
                    $sort_order_key_name = $this->module_const_prefix . strtoupper(preg_replace('/^ot_/', '', $class)) . '_SORT_ORDER';
                } elseif ($this->module_type == 'payment') {
                    if (!defined($sort_order_key_name) && strpos($class, 'multisafepay_') === 0) {
                        $_alter_sort_order_key_name = $this->module_const_prefix . strtoupper(preg_replace('/^multisafepay_/', 'msp_', $class)) . '_SORT_ORDER';
                        if (defined($_alter_sort_order_key_name)) {
                            $sort_order_key_name = $_alter_sort_order_key_name;
                        }
                    }
                }
                $result->title = defined($m_info->title . '_TITLE') ? constant($m_info->title . '_TITLE') : $m_info->title;
                $result->module = $module;
                if ($m_info->status == '1') {
                    $keys = '';
                    if (is_array($m_info->keys)) {
                        foreach ($m_info->keys as $__key_name => $value) {
                            if ($__key_name == $sort_order_key_name) {
                                continue;
                            }
                            $keys .= '<b>' . $value['title'] . '</b><br>';
                            $_t = Translation::get_translation_value(strtoupper(str_replace(' ', '_', $value['value'])), 'configuration', $languages_id);
                            $value['value'] = tep_not_null($_t) ? $_t : $value['value'];
                            unset($_t);
                            if ($value['use_function']) {
                                $use_function = $value['use_function'];
                                if (preg_match('/->/', $use_function)) {
                                    $class_method = explode('->', $use_function);
                                    if (!is_object(${$class_method[0]})) {
                                        ${$class_method[0]} = Yii::create_object($class_method[0]);
                                        if (!is_object(${$class_method[0]})) {
                                            include_once DIR_WS_CLASSES . $class_method[0] . '.php';
                                            ${$class_method[0]} = new $class_method[0]();
                                        }
                                    }
                                    $keys .= tep_call_function($class_method[1], $value['value'], ${$class_method[0]});
                                } elseif (preg_match('/::/', $use_function)) {
                                    $class_method = explode('::', $use_function);
                                    $ns = '';
                                    if (!method_exists($class_method[0], $class_method[1]) && method_exists('backend\models\\' . $class_method[0], $class_method[1])) {
                                        $ns = 'backend\models\\';
                                    }
                                    $keys .= tep_call_function([$ns . $class_method[0], $class_method[1]], $value['value'], ${$class_method[0]} ?? null);
                                } elseif (method_exists($class, $use_function)) {
                                    $keys .= call_user_func_array([$class, $use_function], [$value['value']]);
                                } else {
                                    $keys .= tep_call_function($use_function, $value['value']);
                                }
                            } else {
                                $keys .= $value['value'];
                            }
                            $keys .= '<br>';
                        }
                    }
                    $keys = substr($keys, 0, strrpos($keys, '<br>'));
                    $edit_link = Yii::$app->url_manager->create_url(['modules/edit', 'platform_id' => $this->selected_platform_id, 'set' => $set, 'module' => $m_info->code]);
                    $translate_link = Yii::$app->url_manager->create_url(['modules/translation', 'platform_id' => $this->selected_platform_id, 'set' => $set, 'module' => $m_info->code]);
                }
                return $this->render_partial('view', ['info' => $result, 'set' => $set, 'status' => $m_info->status, 'keys' => $keys ?? null, 'installBtn' => $this->generate_click_func_for_install_btn($module), 'removeBtn' => $this->generate_click_func_for_remove_btn($module), 'editLink' => $edit_link ?? null, 'translateLink' => $translate_link ?? null, 'description' => $module::get_description(), 'selected_platform_id' => $this->selected_platform_id, 'version' => $module::get_version_rev(), 'manualUrl' => $manual_url]);
            }
        }
    }
    private function build_key_html($class, $key, $set_function, $value, $languages_id)
    {
        $method = trim(substr($set_function ?? '', 0, strpos($set_function ?? '', '(')));
        if ($set_function && function_exists($method)) {
            //$_args = preg_replace("/".$method."[\s\(]*/i", "", $set_function). "'" . $value['value'] . "', '" . $key . "'";
            $_args = [$value, $key];
            $res = call_user_func_array($method, $_args);
        } elseif (!empty($class) && $set_function && method_exists($class, $method)) {
            $_args = [$value, $key];
            $res = call_user_func_array([$class, $method], $_args);
        } elseif ($set_function && method_exists('backend\models\Configuration', $method)) {
            if (in_array($method, ['tep_cfg_textarea', 'setTaxAddressBy'])) {
                $res = call_user_func(['backend\models\Configuration', $method], ['key' => $key, 'value' => $value]);
            } else {
                // eval('$keys .= ' . $set_function . "'" . $value['value'] . "', '" . $key . "');");
                $_args = preg_replace('/' . $method . "[\\s\\(]*/i", '', $set_function) . "'" . $value . "', '" . $key . "'";
                $res = call_user_func(['backend\models\Configuration', $method], $_args);
            }
        } else {
            $_t = Translation::get_translation_value(strtoupper(str_replace(' ', '_', $value)), 'configuration', $languages_id);
            $_t = tep_not_null($_t) ? $_t : $value;
            $res = tep_draw_input_field('configuration[' . $key . ']', $_t);
        }
        return $res;
    }
    public function action_edit($set, $module)
    {
        $type = $is_extension = null;
        $languages_id = \Yii::$app->settings->get('languages_id');
        $heading = $contents = [];
        $file = $module . '.php';
        $this->rules($set);
        $this->top_buttons[] = '<span class="btn btn-confirm" onclick="$(\'#saveModules\').trigger(\'submit\')">' . IMAGE_UPDATE . '</span>';
        $installed_modules = [];
        $modules_files = [];
        $heading = $contents = [];
        $restriction = '';
        \common\helpers\Translation::init('admin/modules');
        \common\helpers\Translation::init($this->module_entity);
        $keys = '';
        $class = substr($file, 0, strrpos($file, '.'));
        if ($this->module_type == 'label') {
            $class = 'common\modules\label\\' . $class;
        } elseif ($this->module_type == 'order_total') {
            $class = 'common\modules\orderTotal\\' . $class;
        } elseif ($this->module_type == 'payment') {
            $class = 'common\modules\orderPayment\\' . $class;
        } elseif ($this->module_type == 'shipping') {
            $class = 'common\modules\orderShipping\\' . $class;
        } else {
            if ($this->module_type == 'extensions') {
                $class = 'common\extensions\\' . $class . '\\' . $class;
            }
            if (is_file($this->module_directory . $file)) {
                include_once $this->module_directory . $file;
            }
        }
        if (class_exists($class) && is_subclass_of($class, "common\\classes\\modules\\{$this->module_class}")) {
            $module = new $class();
            /**
             * @var modules\Module $module
             */
            $type = method_exists($module, 'isOnline') && $module->is_online() ? 'online' : 'offline';
            $this->set_acl($set, $type);
            \common\helpers\Acl::check_access($this->acl);
            // $this->fetchArrays($module, $file, $installed_modules, $modules_files);
            if ($m_info = $this->create_info($module, $set)) {
                $sort_order_key_name = $this->module_const_prefix . strtoupper($class) . '_SORT_ORDER';
                if ($this->module_type == 'order_total') {
                    $sort_order_key_name = $this->module_const_prefix . strtoupper(preg_replace('/^ot_/', '', $class)) . '_SORT_ORDER';
                } elseif ($this->module_type == 'payment') {
                    if (!defined($sort_order_key_name) && strpos($class, 'multisafepay_') === 0) {
                        $_alter_sort_order_key_name = $this->module_const_prefix . strtoupper(preg_replace('/^multisafepay_/', 'msp_', $class)) . '_SORT_ORDER';
                        if (defined($_alter_sort_order_key_name)) {
                            $sort_order_key_name = $_alter_sort_order_key_name;
                        }
                    }
                }
                if (is_array($m_info->keys)) {
                    foreach ($m_info->keys as $key => $value) {
                        if ($sort_order_key_name == $key) {
                            $keys .= tep_draw_hidden_field('configuration[' . $key . ']', $value['value']);
                            continue;
                        }
                        if (!empty($value['area'])) {
                            continue;
                        }
                        $keys .= '<div class="after modules-line"><div class="modules-label"><b>' . $value['title'] . '</b><div class="modules-description">' . $value['description'] . '</div></div>';
                        $keys .= $this->build_key_html($class, $key, $value['set_function'], $value['value'], $languages_id);
                        $keys .= '</div><br>';
                    }
                }
                $keys = substr($keys, 0, strrpos($keys, '<br>'));
                $heading[] = ['text' => '<b>' . $m_info->title . '</b>'];
            }
            if (method_exists($module, 'configure_keys_platforms')) {
                $platform_keys = $module->configure_keys_platforms();
                if (is_array($platform_keys) && !empty($platform_keys)) {
                    $platforms = \common\classes\platform::get_list(false);
                    $platform_keys_array = array_keys($platform_keys);
                    $platform_values = \common\models\Platforms_Configuration::find()->where(['configuration_key' => $platform_keys_array])->index_by(function ($row) {
                        return $row['platform_id'] . $row['configuration_key'];
                    })->as_array()->all();
                    foreach ($platforms as $platform) {
                        $platform_id = $platform['id'];
                        $platforms_res[$platform_id]['id'] = $platform_id;
                        $platforms_res[$platform_id]['text'] = $platform['text'];
                        $html = '';
                        foreach ($platform_keys as $key => $value) {
                            $platform_value = $platform_values[$platform_id . $key]['configuration_value'] ?? $value['value'];
                            if (!isset($platform_values[$platform_id . $key])) {
                                $module->add_platform_key($platform_id, $key, $value);
                            }
                            $html .= '<div class="after modules-line"><div class="modules-label"><b>' . $value['title'] . '</b><div class="modules-description">' . $value['description'] . '</div></div>';
                            $html .= $this->build_key_html($class, $key, $value['set_function'] ?? null, $platform_value, $languages_id);
                            $html .= '</div><br>';
                        }
                        $html = substr($html, 0, strrpos($html, '<br>'));
                        $html = str_replace('configuration[', "pconfiguration[{$platform_id}][", $html);
                        $platforms_res[$platform_id]['html'] = $html;
                    }
                }
            }
            if (method_exists($module, 'extra_params')) {
                $this->view->extra_params = $module->extra_params();
            }
            if (method_exists($module, 'getLabels')) {
                $restriction .= $module->get_labels($this->selected_platform_id);
            }
            if (method_exists($module, 'getZeroPrice') && !$module instanceof \common\classes\modules\Module_Extensions) {
                $restriction .= $module->get_zero_price($this->selected_platform_id);
            }
            if (method_exists($module, 'getVisibility')) {
                $restriction .= $module->get_visibility($this->selected_platform_id);
            }
            if (method_exists($module, 'getGroupRestriction')) {
                $restriction .= $module->get_group_restriction($this->selected_platform_id);
            }
            if (method_exists($module, 'getRestriction')) {
                $restriction .= $module->get_restriction($this->selected_platform_id, $languages_id);
            }
            if (method_exists($module, 'getConfigureKeysArea')) {
                if (is_array($m_info->keys)) {
                    foreach ($m_info->keys as $key => $value) {
                        if ($value['area'] != 'restrictions') {
                            continue;
                        }
                        $added = true;
                        //                      $restriction .= '<div class="after modules-line"><div class="modules-label"><b>' . $value['title'] . '</b><div class="modules-description">' . $value['description'] . '</div></div>';
                        $restriction .= '<div class=""><div class=""><b>' . $value['title'] . '</b><div class="modules-description">' . $value['description'] . '</div></div>';
                        $restriction .= $this->build_key_html($class, $key, $value['set_function'], $value['value'], $languages_id);
                        $restriction .= '</div><br>';
                    }
                    if ($added ?? false) {
                        //$keys = substr($keys, 0, strrpos($keys, '<br>'));
                    }
                }
            }
            $ppp = null;
            $ppp_data = ['installPPP' => false];
            if ($module && $module->code == 'paypal_partner') {
                $ppp_data = $module->get_install_options($this->selected_platform_id);
            }
            $is_extension = $module->is_extension();
        }
        if (Yii::$app->request->is_ajax) {
            $this->layout = false;
        }
        $t_keys = $this->quick_translation_keys($module, $set);
        $_platform = Yii::$app->request->get('platform_id', 0);
        if ($_platform <= 0) {
            $_platform = $this->selected_platform_id;
        }
        $p_row = \common\models\Platforms::find()->select(['platform_name'])->where(['is_virtual' => 0, 'is_marketplace' => 0, 'platform_id' => $_platform])->as_array()->one();
        $platform_name = $p_row['platform_name'] ?? '';
        $title = $m_info->title;
        if ($this->module_type != 'extensions' && !empty($platform_name)) {
            $title .= ' - ' . $platform_name;
        }
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('modules/index'), 'title' => $title];
        $this->view->extra_params = $this->view->extra_params ?? null;
        return $this->render('edit.tpl', ['mainKey' => $keys, 'translationsKeys' => $t_keys, 'languages' => \common\helpers\Language::get_languages(true), 'description' => $module->get_description(), 'restriction' => $restriction, 'codeMod' => $m_info->code, 'set' => $set, 'selected_platform_id' => $this->selected_platform_id, 'platformKeys' => $platforms_res ?? null, 'isExtension' => $is_extension, 'class' => $class, 'type' => $type] + $ppp_data);
    }
    public function action_save()
    {
        $set = \Yii::$app->request->get('set', '');
        $module = \Yii::$app->request->post('module', '');
        $this->rules($set);
        Translation::init($this->module_entity);
        if (file_exists($this->module_directory . $module . '.php')) {
            include_once $this->module_directory . $module . '.php';
        }
        if ($this->module_namespace && in_array($set, ['label', 'ordertotal', 'payment', 'shipping'])) {
            $module = $this->module_namespace . $module;
        } elseif ($this->module_type == 'extensions') {
            $module = 'common\extensions\\' . $module . '\\' . $module;
        }
        if (\common\helpers\Acl::check_extension_allowed('ReportUniversalLog')) {
            $log_universal = \common\extensions\Report_Universal_Log\classes\Log_Universal::get_instance(\Yii::$app->request->post('module'));
            $log_universal->set_type($log_universal::ULT_EXTENSION_UPDATE)->set_relation(\Yii::$app->request->post('module'));
        }
        if (class_exists($module)) {
            $object = new $module();
            if (isset($log_universal)) {
                $log_universal->set_relation($object->code);
            }
        }
        if (is_array(\Yii::$app->request->post('configuration'))) {
            foreach (\Yii::$app->request->post('configuration') as $key => $value) {
                if (isset($log_universal)) {
                    $log_universal->merge_before_array(\common\models\Platforms_Configuration::find()->select(['configuration_value', 'platform_id', 'configuration_key'])->where(['platform_id' => (int) $this->selected_platform_id])->and_where(['configuration_key' => $key])->index_by(function ($record) {
                        return $record['platform_id'] . '|' . $record['configuration_key'];
                    })->as_array(true)->column());
                }
                if (is_array($value)) {
                    $value = implode(', ', $value);
                    $value = preg_replace('/, --none--/', '', $value);
                }
                //to encrypt sensitive data
                if (class_exists($module) && method_exists($object, 'confValueBeforeSave')) {
                    $value = $object->conf_value_before_save($key, $value, intval($this->selected_platform_id));
                }
                if (is_object($object) && $key == "{$object->code}_EXTENSION_STATUS" && (new \common\classes\platform_config($this->selected_platform_id))->const_value($key) != $value) {
                    $object->enable_module($this->selected_platform_id, $value == 'True');
                } else {
                    tep_db_query('update ' . TABLE_PLATFORMS_CONFIGURATION . " set configuration_value = '" . tep_db_input(tep_db_prepare_input($value)) . "' where configuration_key = '" . tep_db_input($key) . "' AND platform_id='" . intval($this->selected_platform_id) . "'");
                }
                if (isset($log_universal)) {
                    $log_universal->merge_after_array(\common\models\Platforms_Configuration::find()->select(['configuration_value', 'platform_id', 'configuration_key'])->where(['platform_id' => (int) $this->selected_platform_id])->and_where(['configuration_key' => $key])->index_by(function ($record) {
                        return $record['platform_id'] . '|' . $record['configuration_key'];
                    })->as_array(true)->column());
                }
            }
        }
        if (is_array(\Yii::$app->request->post('pconfiguration'))) {
            foreach (\Yii::$app->request->post('pconfiguration') as $platform_id => $keys_array) {
                $platform_id = intval($platform_id);
                foreach ($keys_array as $key => $value) {
                    if (is_array($value)) {
                        $value = implode(', ', $value);
                        $value = preg_replace('/, --none--/', '', $value);
                    }
                    //to encrypt sensitive data
                    if (class_exists($module) && method_exists($object, 'confValueBeforeSave')) {
                        $value = $object->conf_value_before_save($key, $value, $platform_id);
                    }
                    \common\models\Platforms_Configuration::update_all(['configuration_value' => $value], ['platform_id' => $platform_id, 'configuration_key' => $key]);
                }
            }
        }
        //configuration_title[10][MODULE_ORDER_TOTAL_SUBTOTAL_TITLE]
        $translations = \Yii::$app->request->post('configuration_title', []);
        if (!empty($translations) && is_array($translations)) {
            $languages = \common\helpers\Language::get_languages(true);
            //$param = 'TITLE';
            for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
                if (is_array($translations[$languages[$i]['id']])) {
                    foreach ($translations[$languages[$i]['id']] as $c_key => $c_value) {
                        /*if (in_array($cKey, $module->keys())){
                                Translation::setTranslationValue($cKey . '_' . $param, $set, $languages[$i]['id'], $cValue);
                          } else {*/
                        Translation::set_translation_value($c_key, $set, $languages[$i]['id'], $c_value);
                        //}
                    }
                }
            }
            \common\helpers\Translation::reset_cache();
        }
        if (isset($_FILES['configuration'])) {
            $path = Yii::$aliases['@common'] . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'certificates';
            \yii\helpers\File_Helper::create_directory($path);
            foreach ($_FILES['configuration']['name'] as $key => $value) {
                if (move_uploaded_file($_FILES['configuration']['tmp_name'][$key], $path . DIRECTORY_SEPARATOR . $value)) {
                    tep_db_query('update ' . TABLE_PLATFORMS_CONFIGURATION . " set configuration_value = '" . tep_db_input(tep_db_prepare_input($value)) . "' where configuration_key = '" . tep_db_input($key) . "' AND platform_id='" . intval($this->selected_platform_id) . "'");
                }
            }
        }
        if (class_exists($module)) {
            if (method_exists($object, 'setZeroPrice') && !$object instanceof \common\classes\modules\Module_Extensions) {
                $object->set_zero_price();
            }
            if (method_exists($object, 'setVisibility') && !$object instanceof \common\extensions\Modules_Visibility\Modules_Visibility) {
                $object->set_visibility();
            }
            if (method_exists($object, 'setRestriction')) {
                $object->set_restriction();
            }
            if (method_exists($object, 'setLabels')) {
                $object->set_labels();
            }
            if (method_exists($object, 'setGroupRestriction')) {
                $object->set_group_restriction();
            }
            if (method_exists($object, 'extra_params')) {
                $object->extra_params();
            }
            //plain tables re-index etc
            if (method_exists($object, 'confAfterSave')) {
                $value = $object->conf_after_save();
            }
            if (isset($log_universal)) {
                $log_universal->do_save(true);
                unset($log_universal);
            }
        }
        $message_stack = \Yii::$container->get('message_stack');
        if ($msgs = $message_stack->output()) {
            $this->layout = false;
            return '<div class="alert alert-danger">' . $msgs . '</div>';
        }
    }
    public function action_translation()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/modules');
        $_module = Yii::$app->request->get('module', '');
        $set = Yii::$app->request->get('set', 'payment');
        $row = Yii::$app->request->get('row', '0');
        $this->rules($set);
        Translation::init($this->module_entity);
        $this->selected_menu = ['modules', 'modules?set=' . $set];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('modules/index'), 'title' => $this->_page_title];
        $this->view->heading_title = $this->_page_title;
        $file_extension = '.php';
        $class = \common\helpers\Output::mb_basename($_module);
        $this->selected_menu = ['modules', 'modules?set=' . $set];
        $heading = $contents = [];
        $file = $_module . $file_extension;
        $class = substr($file, 0, strrpos($file, '.'));
        if ($this->module_type == 'label') {
            $class = 'common\modules\label\\' . $class;
        } elseif ($this->module_type == 'order_total') {
            $class = 'common\modules\orderTotal\\' . $class;
        } elseif ($this->module_type == 'payment') {
            $class = 'common\modules\orderPayment\\' . $class;
        } elseif ($this->module_type == 'shipping') {
            $class = 'common\modules\orderShipping\\' . $class;
        } else if (file_exists($this->module_directory . $file)) {
            include_once $this->module_directory . $file;
        }
        if (class_exists($class)) {
            $module = new $class();
        }
        $params = [];
        if (is_object($module)) {
            $keys = array_merge([], $module->keys());
            $_consts = get_defined_constants(true);
            $_consts = $_consts['user'];
            $language_consts = [];
            $_code = $module->code;
            if ($this->module_type == 'order_total') {
                $_code = substr($_code, 3);
            }
            if (is_array($_consts) && count($_consts) && (in_array('MODULE_' . strtoupper($this->module_type . '_' . $module->code) . '_TEXT_TITLE', $_consts) || in_array('MODULE_' . strtoupper($this->module_type . '_' . $_code) . '_TITLE', $_consts))) {
                $ex = explode('_TEXT_TITLE', 'MODULE_' . strtoupper($this->module_type . '_' . $_code) . '_TEXT_TITLE');
                if (count($ex) == 1) {
                    $ex = explode('_TITLE', 'MODULE_' . strtoupper($this->module_type . '_' . $_code) . '_TITLE');
                }
                if (count($ex) > 1) {
                    $module_prefix = $ex[0];
                    foreach ($_consts as $name => $value) {
                        if (strpos($name, $module_prefix . '_') !== false && !in_array($name, $keys)) {
                            $language_consts[] = ['key' => $name, 'value' => $value];
                        }
                    }
                }
            }
            if (is_array($keys)) {
                $languages = \common\helpers\Language::get_languages(true);
                $title_label = Translation::get_translation_value('TEXT_TITLE_LABEL', 'configuration', $languages_id);
                $desc_label = Translation::get_translation_value('TEXT_DESC_LABEL', 'configuration', $languages_id);
                for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
                    $languages[$i]['logo'] = $languages[$i]['image'];
                    if (count($language_consts)) {
                        foreach ($language_consts as $data) {
                            $key = $data['key'];
                            $params[$i][$key]['configuration_title_label'] = $title_label;
                            $_value = tep_not_null($_value = Translation::get_translation_value($key, $set, $languages[$i]['id'])) ? $_value : $data['value'];
                            $params[$i][$key]['configuration_title'] = tep_draw_input_field('configuration_title[' . $languages[$i]['id'] . '][' . $key . ']', $_value, 'class="form-control form-control-small"');
                            $params[$i][$key]['configuration_desc_label'] = '&nbsp;';
                            $params[$i][$key]['configuration_description'] = '&nbsp;';
                        }
                    }
                    foreach ($keys as $key) {
                        $param = tep_db_fetch_array(tep_db_query('select configuration_title, configuration_description from ' . TABLE_PLATFORMS_CONFIGURATION . " where configuration_key = '" . strval($key) . "' LIMIT 1"));
                        $params[$i]['id'] = $languages[$i]['id'];
                        $config_values = new \Object_Info($param);
                        $params[$i][$key]['configuration_title_label'] = $title_label;
                        $params[$i][$key]['configuration_title'] = tep_draw_input_field('configuration_title[' . $languages[$i]['id'] . '][' . $key . ']', $config_values->configuration_title, 'class="form-control form-control-small"');
                        $params[$i][$key]['configuration_desc_label'] = $desc_label;
                        $params[$i][$key]['configuration_description'] = tep_draw_input_field('configuration_description[' . $languages[$i]['id'] . '][' . $key . ']', $config_values->configuration_description, 'class="form-control form-control-small"');
                    }
                }
            }
        }
        if (Yii::$app->request->is_post) {
            $languages = \common\helpers\Language::get_languages(true);
            $accepted = ['configuration_title', 'configuration_description'];
            foreach ($_POST as $config_key => $config_variants) {
                if (!in_array($config_key, $accepted)) {
                    continue;
                }
                $ex = explode('_', $config_key);
                $param = strtoupper($ex[1]);
                for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
                    if (is_array($config_variants[$languages[$i]['id']])) {
                        foreach ($config_variants[$languages[$i]['id']] as $c_key => $c_value) {
                            if (in_array($c_key, $module->keys())) {
                                Translation::set_translation_value($c_key . '_' . $param, $set, $languages[$i]['id'], $c_value);
                            } else {
                                Translation::set_translation_value($c_key, $set, $languages[$i]['id'], $c_value);
                            }
                        }
                    }
                }
            }
            \common\helpers\Translation::reset_cache();
            if (Yii::$app->request->is_ajax) {
                $this->layout = false;
                echo 'ok';
            } else {
                return $this->redirect(Yii::$app->url_manager->create_url('modules/?set=' . $set . '&row=' . $row));
            }
        } else {
            return $this->render('translation', ['params' => $params, 'languages' => $languages, 'codeMod' => $module->code]);
        }
    }
    public function quick_translation_keys($module, $set)
    {
        $ret = $keys = [];
        $languages = \common\helpers\Language::get_languages(true);
        if (method_exists($module, 'getQuickTranslationKeys')) {
            foreach ($module->get_quick_translation_keys() as $k => $v) {
                $keys[] = ['key' => $k, 'value' => $v];
            }
        } elseif (!empty($module->quick_translation_keys) && is_array($module->quick_translation_keys)) {
            foreach ($module->quick_translation_keys as $k => $v) {
                $keys[] = ['key' => $k, 'value' => $v];
            }
        } elseif (!isset($module->is_extension)) {
            $_consts = get_defined_constants(true);
            $_consts = $_consts['user'];
            $language_consts = [];
            $_code = $module->code;
            if ($this->module_type == 'order_total') {
                $_code = substr($_code, 3);
            }
            $possible_keys = [];
            $possible_keys[] = 'MODULE_' . strtoupper($this->module_type . '_' . $module->code) . '_TEXT_TITLE';
            $possible_keys[] = 'MODULE_' . strtoupper($this->module_type . '_' . $_code) . '_TITLE';
            $possible_keys[] = 'MODULE_' . strtoupper($this->module_type . '_' . $module->code) . '_TEXT_PUBLIC_TITLE';
            $possible_keys[] = 'MODULE_' . strtoupper($this->module_type . '_' . $_code) . '_PUBLIC_TITLE';
            if (is_array($_consts) && !empty(array_intersect(array_keys($_consts), $possible_keys))) {
                foreach (array_intersect(array_keys($_consts), $possible_keys) as $name) {
                    $keys[] = ['key' => $name, 'value' => $_consts[$name]];
                }
            }
        }
        if (!empty($keys) && is_array($keys)) {
            for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
                $title = Translation::get_translation_value('TEXT_TITLE_LABEL', 'configuration', $languages[$i]['id']);
                $ret[$i]['id'] = $languages[$i]['id'];
                $ret[$i]['keys'] = [];
                foreach ($keys as $d) {
                    $key = $d['key'];
                    $_value = Translation::get_translation_value($key, $set, $languages[$i]['id']);
                    if (empty($_value)) {
                        $_value = $d['value'];
                    }
                    $ret[$i]['keys'][] = ['key' => $key, 'configuration_title_label' => $title, 'configuration_title_field' => tep_draw_input_field('configuration_title[' . $languages[$i]['id'] . '][' . $key . ']', $_value, 'class="form-control form-control-small"')];
                }
            }
        }
        return $ret;
    }
    /**
     * @deprecated always return false
     * before - always return true for all modules except extensions as $module->title already text and "bla-bla-bla"_TITLE never translated...
     * @param type $module
     * @param type $set
     * @return boolean
     */
    public function need_translation($module, $set)
    {
        return false;
        if (isset($module->is_extension)) {
            return false;
        }
        $keys = array_merge([$module->title, $module->description], $module->keys());
        if (is_array($keys)) {
            $res = [];
            foreach ($keys as $key) {
                if (!tep_not_null(Translation::get_translation_value($key . '_TITLE', $set))) {
                    $res[] = 1;
                } else {
                    $res[] = 0;
                }
            }
            if (array_sum($res) > 0) {
                // need tranlation
                return true;
            }
        }
        return false;
    }
    public function action_change()
    {
        $response = [];
        $set = Yii::$app->request->post('set', 'payment');
        $_module = Yii::$app->request->post('module', '');
        $action = Yii::$app->request->post('action', '');
        $enabled = Yii::$app->request->post('enabled', '');
        $user_confirmed_drop_datatables = Yii::$app->request->post('user_confirmed_drop_datatables', '');
        $user_confirmed_drop_acl = Yii::$app->request->post('user_confirmed_drop_acl', '');
        $file_extension = '.php';
        $this->rules($set);
        \common\helpers\Translation::init($this->module_entity);
        $class = \common\helpers\Output::mb_basename($_module);
        if ($this->module_need_requiring) {
            //if ($this->module_type != 'label') {
            if (file_exists($this->module_directory . $class . $file_extension)) {
                include_once $this->module_directory . $class . $file_extension;
            }
        }
        if ($this->module_type == 'extensions') {
            $class = \common\helpers\Acl::check_extension($class, 'enabled');
        } else {
            $class = $this->module_namespace . $_module;
        }
        if (class_exists($class) && is_subclass_of($class, "common\\classes\\modules\\{$this->module_class}")) {
            $module = new $class();
            if (isset($module->is_extension)) {
                $class = (new \ReflectionClass($class))->get_short_name();
            }
            /**
             * @var modules\Module $module
             */
            if ($action == 'install') {
                if (isset($module->is_extension)) {
                    switch ($user_confirmed_drop_acl) {
                        case 'all':
                            $access_levels = [];
                            foreach (\common\models\Access_Levels::find()->select(['access_levels_id'])->as_array()->all() as $al) {
                                $access_levels[] = $al['access_levels_id'];
                            }
                            $module->assign_to_access_levels = $access_levels;
                            break;
                        case 'my':
                            global $access_levels_id;
                            $module->assign_to_access_levels = $access_levels_id;
                            break;
                        default:
                            $module->assign_to_access_levels = 0;
                            break;
                    }
                }
                if (!$module->check($this->selected_platform_id)) {
                    // $module->remove($this->selected_platform_id);
                    $module->install($this->selected_platform_id);
                    if ($this->need_translation($module, $set)) {
                        $response['need_translate'] = $module->code;
                    }
                    $response['need_config'] = $module->code;
                    $installed_modules_str = defined($this->module_key) ? constant($this->module_key) : '';
                    $get_actual_value = tep_db_fetch_array(tep_db_query('SELECT configuration_value FROM ' . TABLE_PLATFORMS_CONFIGURATION . " WHERE configuration_key='" . tep_db_input($this->module_key) . "' AND platform_id='" . intval($this->selected_platform_id) . "'"));
                    if (is_array($get_actual_value)) {
                        $installed_modules_str = $get_actual_value['configuration_value'];
                    }
                    $order_count = (count(explode(';', $installed_modules_str)) + 1) * 10 + 10;
                    tep_db_query('update ' . TABLE_PLATFORMS_CONFIGURATION . ' ' . "set configuration_value = TRIM(BOTH ';' FROM CONCAT(configuration_value,';" . tep_db_input(($this->module_namespace ? str_replace($this->module_namespace, '', $class) : $class) . $file_extension) . "')), last_modified = now() " . "where configuration_key = '" . tep_db_input($this->module_key) . "' " . " AND platform_id='" . intval($this->selected_platform_id) . "'");
                    $module->update_sort_order($this->selected_platform_id, $order_count);
                }
                \common\helpers\Translation::reset_cache();
                \common\helpers\Hooks::reset_hooks();
            } elseif ($action == 'remove') {
                if (isset($module->is_extension) && $user_confirmed_drop_datatables == 'true') {
                    $module->user_confirmed_drop_datatables = true;
                }
                if (isset($module->is_extension) && $user_confirmed_drop_acl == 'true') {
                    $module->user_confirmed_delete_acl = true;
                }
                $module->remove($this->selected_platform_id);
                tep_db_query('update ' . TABLE_PLATFORMS_CONFIGURATION . ' ' . "set configuration_value = TRIM(BOTH ';' FROM REPLACE(CONCAT(';',configuration_value,';'),'" . tep_db_input($class . $file_extension) . "','')), last_modified = now() " . "where configuration_key = '" . tep_db_input($this->module_key) . "' " . " AND platform_id='" . intval($this->selected_platform_id) . "'");
                \common\helpers\Translation::reset_cache();
                \common\helpers\Hooks::reset_hooks();
            } elseif ($action == 'status') {
                $module->enable_module($this->selected_platform_id, $enabled == 'on');
            }
        }
        $response['redirect'] = Yii::$app->url_manager->create_url(['modules/list', 'set' => $set, 'platform_id' => $this->selected_platform_id]);
        return json_encode($response);
    }
    public function action_sort_order()
    {
        $sorted = Yii::$app->request->post('module', []);
        $set = Yii::$app->request->post('set', 'payment');
        $this->rules($set);
        $this->update_modules_sort_order($sorted);
        echo json_encode(['redirect' => Yii::$app->url_manager->create_url(['modules/list', 'set' => $set, 'platform_id' => $this->selected_platform_id])]);
    }
    protected function update_modules_sort_order($sorted)
    {
        $sorted = array_map(function ($val) {
            if (strpos($val, '\\') !== false) {
                $val = substr($val, strrpos($val, '\\') + 1);
            }
            if (strpos($val, '.php') === false) {
                $val .= '.php';
            }
            return $val;
        }, $sorted);
        $sorted = array_values($sorted);
        \common\helpers\Translation::init($this->module_entity);
        $installed_modules = \common\models\Platforms_Configuration::find_one(['configuration_key' => $this->module_key, 'platform_id' => $this->selected_platform_id]);
        if (!$installed_modules) {
            $installed_sort = $installed_modules->configuration_value;
        } else {
            $installed_sort = defined($this->module_key) ? constant($this->module_key) : '';
        }
        //inactive modules are not shown by default so $installed_sort contains them, but $sorted does NOT
        //remove inactive from $installed_sort before comparison
        $active_modules = [];
        $inactive_modules = [];
        //fill in by submitted array
        foreach (explode(';', $installed_sort) as $__installed_module) {
            if (in_array($__installed_module, $sorted)) {
                $active_modules[] = $__installed_module;
            } else {
                $inactive_modules[] = $__installed_module;
            }
        }
        //if ( strpos(implode(';', $sorted),$installed_sort)!==0 || implode(';', $sorted)!=$installed_sort ) {
        if ($sorted !== $active_modules) {
            $sorted_idx = array_flip($sorted);
            $new_order = [];
            foreach ($active_modules as $__installed_module) {
                $new_order[$sorted_idx[$__installed_module]] = $__installed_module;
            }
            foreach ($inactive_modules as $__installed_module) {
                $new_order[] = $__installed_module;
            }
            ksort($new_order);
            $installed_sort = implode(';', $new_order);
            if (!$installed_modules) {
                $installed_modules = new \common\models\Platforms_Configuration();
                $installed_modules->load_default_values();
                $installed_modules->configuration_title = 'Installed Modules';
                $installed_modules->configuration_key = $this->module_key;
                $installed_modules->configuration_description = 'This is automatically updated. No need to edit.';
                $installed_modules->configuration_group_id = '';
                $installed_modules->date_added = new \yii\db\Expression('now()');
                $installed_modules->platform_id = $this->selected_platform_id;
            }
            $installed_modules->configuration_value = $installed_sort;
            $installed_modules->last_modified = date(\common\helpers\Date::DATABASE_DATETIME_FORMAT);
            try {
                $installed_modules->save(false);
            } catch (\Exception $e) {
                \Yii::warning(' #### ' . print_r($e->get_message(), true), 'TLDEBUG');
            }
            $order_count = 0;
            foreach ($new_order as $module_file) {
                $order_count += 10;
                $class = substr($module_file, 0, strrpos($module_file, '.'));
                if ($this->module_namespace) {
                    $class = $this->module_namespace . $class;
                }
                if (class_exists($class)) {
                    $module = new $class();
                    /**
                     * @var modules\Module $module
                     */
                    $module->update_sort_order($this->selected_platform_id, $order_count);
                }
            }
        }
        /*
              $check_query = tep_db_query("select configuration_value from " . TABLE_PLATFORMS_CONFIGURATION. " where configuration_key = '" . tep_db_input($this->module_key_sort) . "' AND platform_id='".intval($this->selected_platform_id)."'");
              if (tep_db_num_rows($check_query)) {
                $check = tep_db_fetch_array($check_query);
                if ($check['configuration_value'] != implode(';', $sorted)) {
                  tep_db_query("update " . TABLE_PLATFORMS_CONFIGURATION. " set configuration_value = '" . tep_db_input(implode(';', $sorted)) . "', last_modified = now() where configuration_key = '" . tep_db_input($this->module_key_sort) . "' AND platform_id='".intval($this->selected_platform_id)."'");
                }
              } else {
                tep_db_query("insert into " . TABLE_PLATFORMS_CONFIGURATION. " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added, platform_id) values ('Modules Sort', '" . tep_db_input($this->module_key_sort) . "', '" . tep_db_input(implode(';', $sorted)) . "', 'This is automatically updated. No need to edit.', '6', '0', now(), '".intval($this->selected_platform_id)."')");
              }
        */
    }
    public function action_multisafepay()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $heading = $contents = [];
        $file = 'multisafepay.php';
        $this->rules('payment');
        $installed_modules = [];
        $modules_files = [];
        $heading = $contents = [];
        \common\helpers\Translation::init('admin/modules');
        include_once $this->module_directory . $file;
        $module_keys = ['MODULE_PAYMENT_MULTISAFEPAY_API_SERVER', 'MODULE_PAYMENT_MULTISAFEPAY_ACCOUNT_ID', 'MODULE_PAYMENT_MULTISAFEPAY_SITE_ID', 'MODULE_PAYMENT_MULTISAFEPAY_SITE_SECURE_CODE', 'MODULE_PAYMENT_MULTISAFEPAY_AUTO_REDIRECT', 'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_INITIALIZED', 'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_COMPLETED', 'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_UNCLEARED', 'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_RESERVED', 'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_VOID', 'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_DECLINED', 'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_REVERSED', 'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_REFUNDED', 'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_EXPIRED', 'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_PARTIAL_REFUNDED', 'MODULE_PAYMENT_MULTISAFEPAY_TITLES_ENABLER', 'MODULE_PAYMENT_MULTISAFEPAY_TITLES_ICON_DISABLED'];
        $check = tep_db_fetch_array(tep_db_query('select count(*) as key_exists from ' . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_MULTISAFEPAY_API_SERVER'"));
        if (!$check['key_exists']) {
            tep_db_query('INSERT INTO ' . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Type account', 'MODULE_PAYMENT_MULTISAFEPAY_API_SERVER', 'Live account', '<a href=\\'http://www.multisafepay.com/nl/klantenservice-zakelijk/open-een-testaccount.html\\' target=\\'_blank\\' style=\\'text-decoration: underline; font-weight: bold; color:#696916; \\'>Sign up for a free test account!</a>', '6', '21', 'tep_cfg_select_option(array(\\'Live account\\', \\'Test account\\'), ', now())");
        }
        $check = tep_db_fetch_array(tep_db_query('select count(*) as key_exists from ' . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_MULTISAFEPAY_ACCOUNT_ID'"));
        if (!$check['key_exists']) {
            tep_db_query('INSERT INTO ' . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Account ID', 'MODULE_PAYMENT_MULTISAFEPAY_ACCOUNT_ID', '', 'Your merchant account ID', '6', '22', now())");
        }
        $check = tep_db_fetch_array(tep_db_query('select count(*) as key_exists from ' . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_MULTISAFEPAY_SITE_ID'"));
        if (!$check['key_exists']) {
            tep_db_query('INSERT INTO ' . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Site ID', 'MODULE_PAYMENT_MULTISAFEPAY_SITE_ID', '', 'ID of this site', '6', '23', now())");
        }
        $check = tep_db_fetch_array(tep_db_query('select count(*) as key_exists from ' . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_MULTISAFEPAY_SITE_SECURE_CODE'"));
        if (!$check['key_exists']) {
            tep_db_query('INSERT INTO ' . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) VALUES ('Site Code', 'MODULE_PAYMENT_MULTISAFEPAY_SITE_SECURE_CODE', '', 'Site code for this site', '6', '24', now())");
        }
        $check = tep_db_fetch_array(tep_db_query('select count(*) as key_exists from ' . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_MULTISAFEPAY_AUTO_REDIRECT'"));
        if (!$check['key_exists']) {
            tep_db_query('INSERT INTO ' . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Auto Redirect', 'MODULE_PAYMENT_MULTISAFEPAY_AUTO_REDIRECT', 'True', 'Enable auto redirect after payment', '6', '20', 'tep_cfg_select_option(array(\\'True\\', \\'False\\'), ', now())");
        }
        $check = tep_db_fetch_array(tep_db_query('select count(*) as key_exists from ' . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_INITIALIZED'"));
        if (!$check['key_exists']) {
            tep_db_query('INSERT INTO ' . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Initialized Order Status', 'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_INITIALIZED', 0, 'In progress', '6', '0', 'tep_cfg_pull_down_order_statuses(', '\\common\\helpers\\Order::get_order_status_name', now())");
        }
        $check = tep_db_fetch_array(tep_db_query('select count(*) as key_exists from ' . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_COMPLETED'"));
        if (!$check['key_exists']) {
            tep_db_query('INSERT INTO ' . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Completed Order Status',   'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_COMPLETED',   0, 'Completed successfully', '6', '0', 'tep_cfg_pull_down_order_statuses(', '\\common\\helpers\\Order::get_order_status_name', now())");
        }
        $check = tep_db_fetch_array(tep_db_query('select count(*) as key_exists from ' . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_UNCLEARED'"));
        if (!$check['key_exists']) {
            tep_db_query('INSERT INTO ' . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Uncleared Order Status',   'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_UNCLEARED',   0, 'Not yet cleared', '6', '0', 'tep_cfg_pull_down_order_statuses(', '\\common\\helpers\\Order::get_order_status_name', now())");
        }
        $check = tep_db_fetch_array(tep_db_query('select count(*) as key_exists from ' . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_RESERVED'"));
        if (!$check['key_exists']) {
            tep_db_query('INSERT INTO ' . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Reserved Order Status',    'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_RESERVED',    0, 'Reserved', '6', '0', 'tep_cfg_pull_down_order_statuses(', '\\common\\helpers\\Order::get_order_status_name', now())");
        }
        $check = tep_db_fetch_array(tep_db_query('select count(*) as key_exists from ' . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_VOID'"));
        if (!$check['key_exists']) {
            tep_db_query('INSERT INTO ' . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Voided Order Status',      'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_VOID',        0, 'Cancelled', '6', '0', 'tep_cfg_pull_down_order_statuses(', '\\common\\helpers\\Order::get_order_status_name', now())");
        }
        $check = tep_db_fetch_array(tep_db_query('select count(*) as key_exists from ' . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_DECLINED'"));
        if (!$check['key_exists']) {
            tep_db_query('INSERT INTO ' . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Declined Order Status',    'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_DECLINED',    0, 'Declined (e.g. fraud, not enough balance)', '6', '0', 'tep_cfg_pull_down_order_statuses(', '\\common\\helpers\\Order::get_order_status_name', now())");
        }
        $check = tep_db_fetch_array(tep_db_query('select count(*) as key_exists from ' . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_REVERSED'"));
        if (!$check['key_exists']) {
            tep_db_query('INSERT INTO ' . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Reversed Order Status',    'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_REVERSED',    0, 'Undone', '6', '0', 'tep_cfg_pull_down_order_statuses(', '\\common\\helpers\\Order::get_order_status_name', now())");
        }
        $check = tep_db_fetch_array(tep_db_query('select count(*) as key_exists from ' . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_REFUNDED'"));
        if (!$check['key_exists']) {
            tep_db_query('INSERT INTO ' . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Refunded Order Status',    'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_REFUNDED',    0, 'refunded', '6', '0', 'tep_cfg_pull_down_order_statuses(', '\\common\\helpers\\Order::get_order_status_name', now())");
        }
        $check = tep_db_fetch_array(tep_db_query('select count(*) as key_exists from ' . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_EXPIRED'"));
        if (!$check['key_exists']) {
            tep_db_query('INSERT INTO ' . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Expired Order Status',     'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_EXPIRED',     0, 'Expired', '6', '0', 'tep_cfg_pull_down_order_statuses(', '\\common\\helpers\\Order::get_order_status_name', now())");
        }
        $check = tep_db_fetch_array(tep_db_query('select count(*) as key_exists from ' . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_PARTIAL_REFUNDED'"));
        if (!$check['key_exists']) {
            tep_db_query('INSERT INTO ' . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('Set Partial refunded Order Status',     'MODULE_PAYMENT_MULTISAFEPAY_ORDER_STATUS_ID_PARTIAL_REFUNDED',     0, 'Partial Refunded', '6', '0', 'tep_cfg_pull_down_order_statuses(', '\\common\\helpers\\Order::get_order_status_name', now())");
        }
        $check = tep_db_fetch_array(tep_db_query('select count(*) as key_exists from ' . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_MULTISAFEPAY_TITLES_ENABLER'"));
        if (!$check['key_exists']) {
            tep_db_query('INSERT INTO ' . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Enable gateway titles in checkout', 'MODULE_PAYMENT_MULTISAFEPAY_TITLES_ENABLER', 'True', 'Enable the gateway title in checkout', '6', '20', 'tep_cfg_select_option(array(\\'True\\', \\'False\\'), ', now())");
        }
        $check = tep_db_fetch_array(tep_db_query('select count(*) as key_exists from ' . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_MULTISAFEPAY_TITLES_ICON_DISABLED'"));
        if (!$check['key_exists']) {
            tep_db_query('INSERT INTO ' . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) VALUES ('Enable icons in gateway titles. If disabled it will overrule option above.', 'MODULE_PAYMENT_MULTISAFEPAY_TITLES_ICON_DISABLED', 'True', 'Enable the icon in the checkout title for the gateway', '6', '20', 'tep_cfg_select_option(array(\\'True\\', \\'False\\'), ', now())");
        }
        $keys_extra = [];
        for ($j = 0, $k = sizeof($module_keys); $j < $k; $j++) {
            $key_value_query = tep_db_query('select configuration_title, configuration_value, configuration_description, use_function, set_function from ' . TABLE_CONFIGURATION . " where configuration_key = '" . tep_db_input($module_keys[$j]) . "'");
            $key_value = tep_db_fetch_array($key_value_query);
            $keys_extra[$module_keys[$j]]['title'] = tep_not_null($value = Translation::get_translation_value($module_keys[$j] . '_TITLE', $set, $languages_id)) ? $value : $key_value['configuration_title'];
            $keys_extra[$module_keys[$j]]['value'] = $key_value['configuration_value'];
            $keys_extra[$module_keys[$j]]['description'] = tep_not_null($value = Translation::get_translation_value($module_keys[$j] . '_DESCRIPTION', $set, $languages_id)) ? $value : $key_value['configuration_description'];
            $keys_extra[$module_keys[$j]]['use_function'] = $key_value['use_function'];
            $keys_extra[$module_keys[$j]]['set_function'] = $key_value['set_function'];
        }
        $keys = '';
        if (is_array($keys_extra)) {
            foreach ($keys_extra as $key => $value) {
                if ($sort_order_key_name == $key) {
                    $keys .= tep_draw_hidden_field('configuration[' . $key . ']', $value['value']);
                    continue;
                }
                $keys .= '<b>' . $value['title'] . '</b><br>' . $value['description'] . '<br>';
                if ($value['set_function']) {
                    eval('$keys .= ' . $value['set_function'] . "'" . $value['value'] . "', '" . $key . "');");
                } else {
                    $keys .= tep_draw_input_field('configuration[' . $key . ']', $value['value']);
                }
                $keys .= '<br><br>';
            }
        }
        $keys = substr($keys, 0, strrpos($keys, '<br><br>'));
        $class = substr($file, 0, strrpos($file, '.'));
        if (class_exists($class)) {
            $module = new $class();
            if ($m_info = $this->create_info($module, 'payment')) {
                $heading[] = ['text' => '<b>' . $m_info->title . '</b>'];
            }
        }
        if (Yii::$app->request->is_ajax) {
            $this->layout = false;
        }
        $this->selected_menu = ['modules', 'modules/multisafepay'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('modules/multisafepay'), 'title' => $m_info->title];
        return $this->render('edit.tpl', ['mainKey' => $keys, 'codeMod' => $m_info->code, 'set' => 'payment']);
    }
    public function action_extra_params()
    {
        $set = \Yii::$app->request->post('set', '');
        $module = \Yii::$app->request->post('module', '');
        $this->rules($set);
        \common\helpers\Translation::init($this->module_entity);
        if ($this->module_type == 'label') {
            $module = 'common\modules\label\\' . $module;
        } elseif ($this->module_type == 'order_total') {
            $module = 'common\modules\orderTotal\\' . $module;
        } elseif ($this->module_type == 'payment') {
            $module = 'common\modules\orderPayment\\' . $module;
        } elseif ($this->module_type == 'shipping') {
            $module = 'common\modules\orderShipping\\' . $module;
        } elseif ($this->module_type == 'extensions') {
            $module = \common\helpers\Acl::check_extension($module, 'extra_params');
        } elseif (file_exists(DIR_FS_CATALOG_MODULES . $set . '/' . $module . '.php')) {
            include_once DIR_FS_CATALOG_MODULES . $set . '/' . $module . '.php';
        } elseif ($this->module_type == 'extensions') {
            $module = 'common\extensions\\' . $module . '\\' . $module;
        }
        if (!empty($module) && class_exists($module)) {
            $object = new $module();
            if (method_exists($object, 'extra_params')) {
                echo $object->extra_params();
            }
        }
    }
    public function action_export()
    {
        $set = \Yii::$app->request->get('set', '');
        $file = $module = \Yii::$app->request->get('module', '');
        $platform_id = \Yii::$app->request->get('platform_id', 0);
        $this->rules($set);
        \common\helpers\Translation::init($this->module_entity);
        $response = ['set' => $set, 'module' => $module, 'keys' => []];
        if ($this->module_type == 'label') {
            $module = 'common\modules\label\\' . $module;
        } elseif ($this->module_type == 'order_total') {
            $module = 'common\modules\orderTotal\\' . $module;
        } elseif ($this->module_type == 'payment') {
            $module = 'common\modules\orderPayment\\' . $module;
        } elseif ($this->module_type == 'shipping') {
            $module = 'common\modules\orderShipping\\' . $module;
        } elseif ($this->module_type == 'extensions') {
            $module = \common\helpers\Acl::check_extension($module, 'extra_params');
        } elseif (file_exists(DIR_FS_CATALOG_MODULES . $set . '/' . $module . '.php')) {
            include_once DIR_FS_CATALOG_MODULES . $set . '/' . $module . '.php';
        } elseif ($this->module_type == 'extensions') {
            $module = 'common\extensions\\' . $module . '\\' . $module;
        }
        if (!empty($module) && class_exists($module)) {
            $object = new $module();
            if (method_exists($object, 'keys')) {
                $keys = $object->keys();
                $rows = \common\models\Platforms_Configuration::find()->select(['configuration_value', 'configuration_key'])->where(['platform_id' => $platform_id])->and_where(['IN', 'configuration_key', $keys])->all();
                foreach ($rows as $row) {
                    $response['keys'][$row['configuration_key']] = base64_encode($row['configuration_value']);
                }
                if (method_exists($object, 'get_extra_params')) {
                    $extra_params = $object->get_extra_params($platform_id);
                    if (count($extra_params) > 0) {
                        $response['extra_params'] = $extra_params;
                    }
                }
            }
        }
        header('Content-Type: application/json');
        header('Content-Transfer-Encoding: utf-8');
        header('Content-disposition: attachment; filename="' . $file . '.json"');
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $response;
    }
    public function action_import()
    {
        if (isset($_FILES['file']['tmp_name'])) {
            $set = \Yii::$app->request->get('set', '');
            $module = \Yii::$app->request->get('module', '');
            $platform_id = \Yii::$app->request->get('platform_id', 0);
            $jsonfile = json_decode(file_get_contents($_FILES['file']['tmp_name']));
            if ($set != $jsonfile->set) {
                die('set error');
            }
            if (\common\helpers\Output::mb_basename($module) != $jsonfile->module) {
                die('module error');
            }
            $this->rules($set);
            if (!empty($module) && class_exists($module)) {
                $object = new $module();
                if (method_exists($object, 'save_config')) {
                    $keys = (array) $jsonfile->keys;
                    foreach ($keys as $i => $k) {
                        $keys[$i] = base64_decode($k);
                    }
                    $object->save_config($platform_id, $keys);
                    if (method_exists($object, 'set_extra_params') && isset($jsonfile->extra_params)) {
                        $object->set_extra_params($platform_id, (array) $jsonfile->extra_params);
                    }
                    die('done');
                }
            }
        }
        die('error');
    }
    public function action_export_all()
    {
        $set = \Yii::$app->request->get('set', '');
        $platform_id = \Yii::$app->request->get('platform_id', 0);
        $response = [];
        $this->rules($set);
        $directory_array = $this->directory_list();
        foreach ($directory_array as $file) {
            $class = strpos($file, '.') !== false ? substr($file, 0, strrpos($file, '.')) : $file;
            if ($this->module_need_requiring) {
                require_once $this->module_directory . $file;
            }
            if (class_exists($class)) {
                $module = new $class();
                if (method_exists($module, 'keys')) {
                    $keys = $module->keys();
                    $rows = \common\models\Platforms_Configuration::find()->select(['configuration_value', 'configuration_key'])->where(['platform_id' => $platform_id])->and_where(['IN', 'configuration_key', $keys])->all();
                    $resp = ['set' => $set, 'module' => $module->code, 'keys' => []];
                    foreach ($rows as $row) {
                        $resp['keys'][$row['configuration_key']] = base64_encode($row['configuration_value']);
                    }
                    if (count($resp['keys']) > 0) {
                        if (method_exists($module, 'get_extra_params')) {
                            $extra_params = $module->get_extra_params($platform_id);
                            if (count($extra_params) > 0) {
                                $resp['extra_params'] = $extra_params;
                            }
                        }
                        $response[] = $resp;
                        // only installed
                    }
                }
            }
        }
        header('Content-Type: application/json');
        header('Content-Transfer-Encoding: utf-8');
        header('Content-disposition: attachment; filename="' . $set . '-all.json"');
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $response;
    }
    public function action_import_all()
    {
        if (isset($_FILES['file']['tmp_name'])) {
            $set = \Yii::$app->request->get('set', '');
            $platform_id = \Yii::$app->request->get('platform_id', 0);
            $jsonfile = json_decode(file_get_contents($_FILES['file']['tmp_name']));
            if (is_array($jsonfile)) {
                $this->rules($set);
                $directory_array = $this->directory_list();
                foreach ($directory_array as $file) {
                    $class = strpos($file, '.') !== false ? substr($file, 0, strrpos($file, '.')) : $file;
                    if ($this->module_need_requiring) {
                        require_once $this->module_directory . $file;
                    }
                    if (class_exists($class)) {
                        $module = new $class();
                        $module_name = \common\helpers\Output::mb_basename($class);
                        $in_list = false;
                        foreach ($jsonfile as $row) {
                            if ($row->set == $set && $row->module == $module_name) {
                                if (!$module->check($platform_id)) {
                                    $module->remove($platform_id);
                                    $module->install($platform_id);
                                }
                                if (method_exists($module, 'save_config')) {
                                    $keys = (array) $jsonfile->keys;
                                    foreach ($keys as $i => $k) {
                                        $keys[$i] = base64_decode($k);
                                    }
                                    $module->save_config($platform_id, $keys);
                                    $in_list = true;
                                    if (method_exists($module, 'set_extra_params') && isset($row->extra_params)) {
                                        $module->set_extra_params($platform_id, (array) $row->extra_params);
                                    }
                                    break;
                                }
                            }
                        }
                        if ($in_list == false) {
                            $module->enable_module($platform_id, false);
                        }
                        unset($module);
                    }
                }
            }
        }
    }
}