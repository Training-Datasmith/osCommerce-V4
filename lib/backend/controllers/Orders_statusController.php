<?php

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

use backend\services\Configuration_Service;
use Yii;
/**
 * default controller to handle user requests.
 */
class Orders_status_Controller extends Sceleton
{
    public $acl = ['TEXT_SETTINGS', 'BOX_LOCALIZATION_ORDERS_STATUS', 'BOX_ORDERS_STATUS'];
    /** @var ConfigurationService */
    private $configuration_service;
    public function __construct($id, $module, Configuration_Service $configuration_service, array $config = [])
    {
        parent::__construct($id, $module, $config);
        $this->configuration_service = $configuration_service;
    }
    public function action_index()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $type_id = (int) Yii::$app->request->get('type_id', 1);
        $row = (int) Yii::$app->request->get('row');
        $osg_id = (int) Yii::$app->request->get('osgID');
        $this->selected_menu = ['settings', 'status', 'orders_status'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('orders_status/index'), 'title' => HEADING_TITLE];
        $this->view->heading_title = HEADING_TITLE;
        $this->top_buttons[] = '<a href="' . \Yii::$app->url_manager->create_url(['orders_status/edit', 'type_id' => $type_id]) . '" class="btn btn-primary">' . TEXT_INFO_HEADING_NEW_ORDERS_STATUS . '</a>';
        $this->view->status_table = [['title' => TABLE_HEADING_ORDERS_STATUS, 'not_important' => 0]];
        // \common\helpers\Status::getStatusGroupsList(true)
        $orders_status_groups = [];
        $orders_status_groups[''] = TEXT_ALL_ORDERS_STATUS_GROUPS;
        $orders_status_groups_query = tep_db_query('select orders_status_groups_id, orders_status_groups_name, orders_status_groups_color from ' . TABLE_ORDERS_STATUS_GROUPS . " where language_id = '" . (int) $languages_id . "' and orders_status_type_id = '" . $type_id . "'");
        while ($orders_status_groups = tep_db_fetch_array($orders_status_groups_query)) {
            $orders_status_groups[$orders_status_groups['orders_status_groups_id']] = $orders_status_groups['orders_status_groups_name'];
        }
        $this->view->filter_status_groups = \yii\helpers\Html::drop_down_list('osgID', (int) $osg_id, $orders_status_groups, ['class' => 'form-control', 'onchange' => 'return applyFilter();']);
        $messages = [];
        if (isset($_SESSION['messages'])) {
            $messages = $_SESSION['messages'];
            unset($_SESSION['messages']);
            if (!is_array($messages)) {
                $messages = [];
            }
        }
        return $this->render('index', ['messages' => $messages, 'types' => \common\helpers\Status::get_status_type_list(false), 'type_id' => $type_id, 'row' => $row]);
    }
    public function action_list()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $draw = Yii::$app->request->get('draw', 1);
        $start = Yii::$app->request->get('start', 0);
        $length = Yii::$app->request->get('length', 10);
        $search = '';
        if (isset($_GET['search']['value']) && tep_not_null($_GET['search']['value'])) {
            $keywords = tep_db_input(tep_db_prepare_input($_GET['search']['value']));
            $search .= " and (os.orders_status_name like '%" . $keywords . "%')";
        }
        $form_filter = Yii::$app->request->get('filter');
        parse_str($form_filter, $filter);
        if ($filter['type_id'] > 0) {
            $search .= " and osg.orders_status_type_id = '" . (int) $filter['type_id'] . "'";
        }
        if ($filter['osgID'] > 0) {
            $search .= " and os.orders_status_groups_id = '" . (int) $filter['osgID'] . "'";
        }
        $current_page_number = $start / $length + 1;
        $response_list = [];
        if (isset($_GET['order'][0]['column']) && $_GET['order'][0]['dir']) {
            switch ($_GET['order'][0]['column']) {
                case 0:
                    $order_by = 'os.orders_status_name ' . tep_db_input(tep_db_prepare_input($_GET['order'][0]['dir']));
                    break;
                default:
                    $order_by = 'os.orders_status_name';
                    break;
            }
        } else {
            $order_by = 'os.orders_status_name';
        }
        //$orders_status_query_raw = "select os.orders_status_id, os.orders_status_name, osg.orders_status_groups_name, ost.orders_status_type_name from " . TABLE_ORDERS_STATUS . " as os left join " . TABLE_ORDERS_STATUS_GROUPS . " as osg on os.orders_status_groups_id=osg.orders_status_groups_id left join " . TABLE_ORDERS_STATUS_TYPE . " as ost on osg.orders_status_type_id=ost.orders_status_type_id where os.language_id = '" . (int)$languages_id . "' and osg.language_id = '" . (int)$languages_id . "' and ost.language_id = '" . (int)$languages_id . "' " . $search . " order by orders_status_type_name, orders_status_groups_name, " . $orderBy;
        $orders_status_query_raw = 'select os.orders_status_id, os.orders_status_name, osg.orders_status_groups_name, os.hidden from ' . TABLE_ORDERS_STATUS . ' as os left join ' . TABLE_ORDERS_STATUS_GROUPS . " as osg on os.orders_status_groups_id=osg.orders_status_groups_id where os.language_id = '" . (int) $languages_id . "' and osg.language_id = '" . (int) $languages_id . "' " . $search . ' order by os.hidden, osg.orders_status_groups_id, orders_status_groups_name, ' . $order_by;
        $orders_status_split = new \Split_Page_Results($current_page_number, $length, $orders_status_query_raw, $orders_status_query_numrows);
        $orders_status_query = tep_db_query($orders_status_query_raw);
        while ($orders_status = tep_db_fetch_array($orders_status_query)) {
            $default_payment_os_text = '';
            if ($this->configuration_service->is_default_order_status_id_for_online_payment((int) $orders_status['orders_status_id'])) {
                $default_payment_os_text = sprintf(' <b>(%s)</b> ', DEFAULT_ONLINE_PAYMENT_ORDERS_STATUS);
            }
            if ($this->configuration_service->is_default_order_status_id_for_online_payment_success((int) $orders_status['orders_status_id'])) {
                $default_payment_os_text .= sprintf(' <b>(%s)</b> ', TEXT_DEFAULT_ONLINE_PAYMENT_SUCCESS_ORDERS_STATUS);
            }
            $response_list[] = ['<div class="wrap ' . (!empty($orders_status['hidden']) ? ' dis_module' : '') . '"><span class="or-st-color">' . $orders_status['orders_status_groups_name'] . '</span>/' . (DEFAULT_ORDERS_STATUS_ID == $orders_status['orders_status_id'] ? '<b>' . $orders_status['orders_status_name'] . ' (' . TEXT_DEFAULT . ')</b>' : $orders_status['orders_status_name']) . $default_payment_os_text . tep_draw_hidden_field('id', $orders_status['orders_status_id'], 'class="cell_identify"') . '</div>'];
        }
        $response = ['draw' => $draw, 'recordsTotal' => $orders_status_query_numrows, 'recordsFiltered' => $orders_status_query_numrows, 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_statusactions()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/orders_status');
        $orders_status_id = Yii::$app->request->post('orders_status_id', 0);
        $this->layout = false;
        if ($orders_status_id) {
            $ostatus = tep_db_fetch_array(tep_db_query('select orders_status_id, orders_status_name from ' . TABLE_ORDERS_STATUS . " where language_id = '" . (int) $languages_id . "' and orders_status_id='" . (int) $orders_status_id . "'"));
            $o_info = new \Object_Info($ostatus, false);
            if (is_object($o_info)) {
                echo '<div class="or_box_head">' . $o_info->orders_status_name . '</div>';
                $status_query = tep_db_query('select count(*) as count from ' . TABLE_ORDERS . " where orders_status = '" . (int) $orders_status_id . "'");
                $status = tep_db_fetch_array($status_query);
                $orders_status_inputs_string = '';
                $languages = \common\helpers\Language::get_languages();
                for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
                    $orders_status_inputs_string .= '<div class="col_desc">' . $languages[$i]['image'] . '&nbsp;' . \common\helpers\Order::get_order_status_name($o_info->orders_status_id, $languages[$i]['id']) . '</div>';
                }
                $gets = array_filter(\Yii::$app->request->get_query_params());
                $gets['orders_status_id'] = $orders_status_id;
                echo $orders_status_inputs_string;
                echo '<div class="btn-toolbar btn-toolbar-order">';
                echo '<a class="btn btn-edit btn-no-margin" href="' . \Yii::$app->url_manager->create_url(['orders_status/edit'] + $gets) . '">' . IMAGE_EDIT . '</a><button class="btn btn-delete" onclick="statusDelete(' . $orders_status_id . ')">' . IMAGE_DELETE . '</button>';
                echo '</div>';
            }
        }
    }
    public function action_edit()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/orders_status');
        \common\helpers\Translation::init('admin/email/templates');
        $this->top_buttons[] = '<span class="btn btn-confirm">' . IMAGE_SAVE . '</span>';
        $orders_status_template = ['' => ''] + \common\helpers\Mail::email_templates_list();
        $orders_status_id = Yii::$app->request->get('orders_status_id', 0);
        $ostatus = tep_db_fetch_array(tep_db_query('select * from ' . TABLE_ORDERS_STATUS . " where language_id = '" . (int) $languages_id . "' and orders_status_id='" . (int) $orders_status_id . "'"));
        $o_info = new \Object_Info($ostatus, false);
        $o_info->orders_status_id = $o_info->orders_status_id ?? null;
        $o_info->orders_status_groups_id = $o_info->orders_status_groups_id ?? null;
        $o_info->order_evaluation_state_id = $o_info->order_evaluation_state_id ?? null;
        $orders_status_inputs_string = [];
        $languages = \common\helpers\Language::get_languages();
        for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
            $orders_status_inputs_string[$languages[$i]['id']] = \common\helpers\Html::input('text', 'orders_status_name[' . $languages[$i]['id'] . ']', \common\helpers\Order::get_order_status_name($o_info->orders_status_id, $languages[$i]['id']), ['class' => 'form-control']);
        }
        if ($orders_status_id) {
            $title = TEXT_INFO_HEADING_EDIT_ORDERS_STATUS;
        } else {
            $title = TEXT_INFO_HEADING_NEW_ORDERS_STATUS;
        }
        $this->selected_menu = ['settings', 'status', 'orders_status'];
        $this->view->heading_title = $title;
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('orders_status/index'), 'title' => $title];
        //$this->topButtons[] = '<a href="#" class="create_item" onclick="return statusEdit(0)">'.TEXT_INFO_HEADING_NEW_ORDERS_STATUS.'</a>';
        $platforms = \common\classes\platform::get_list(false);
        $design_templates = [];
        $email_design_template = [];
        foreach ($platforms as $platform) {
            $theme_id = \common\models\Platforms_To_Themes::find_one($platform['id'])->theme_id;
            $theme_name = \common\models\Themes::find_one(['id' => $theme_id])->theme_name;
            $templates = \common\models\Themes_Settings::find()->select(['setting_value'])->where(['theme_name' => $theme_name, 'setting_group' => 'added_page', 'setting_name' => 'email'])->as_array()->all();
            $design_templates[$platform['id']][] = TEXT_DEFAULT;
            foreach ($templates as $template) {
                $design_templates[$platform['id']][\common\classes\design::page_name($template['setting_value'])] = $template['setting_value'];
            }
            $email_design_template[$platform['id']] = \common\models\Orders_Status_To_Design_Template::find_one(['orders_status_id' => $orders_status_id, 'platform_id' => $platform['id']])->email_design_template ?? null;
        }
        $os_oes_list = false;
        $o_info->orders_status_send_ga = 0;
        $osg_record = \common\models\Orders_Status_Groups::find_one(['orders_status_groups_id' => $o_info->orders_status_groups_id]);
        if ($orders_status_id == 0 or $osg_record instanceof \common\models\Orders_Status_Groups) {
            if ($orders_status_id > 0 and $osg_record->orders_status_type_id != \common\helpers\Order::get_status_type_id()) {
                unset($o_info->orders_status_allocate_allow);
            } elseif ($orders_status_id == 0) {
                $o_info->orders_status_allocate_allow = 0;
            }
            if ($orders_status_id == 0 or $osg_record->orders_status_type_id == \common\helpers\Order::get_status_type_id()) {
                $os_oes_list = [0 => ''];
                foreach (\common\helpers\Order::get_evaluation_state_array() as $oes_id => $oes_array) {
                    $os_oes_list[$oes_id] = defined('TEXT_EVALUATION_STATE_LONG_' . $oes_array['key']) ? constant('TEXT_EVALUATION_STATE_LONG_' . $oes_array['key']) : $oes_array['long'];
                }
                unset($oes_array);
                unset($oes_id);
            }
            if (\common\helpers\Acl::check_extension_allowed('PurchaseOrders') && ($orders_status_id == 0 or $osg_record->orders_status_type_id == \common\extensions\Purchase_Orders\helpers\Purchase_Order::get_status_type_id())) {
                $os_oes_list_pointer =& $os_oes_list;
                if (is_array($os_oes_list)) {
                    $st_list = \common\helpers\Status::get_status_type_list();
                    unset($os_oes_list[0]);
                    $os_oes_list = [0 => '', $st_list[\common\helpers\Order::get_status_type_id()] => $os_oes_list, $st_list[\common\extensions\Purchase_Orders\helpers\Purchase_Order::get_status_type_id()] => []];
                    $os_oes_list_pointer =& $os_oes_list[$st_list[\common\extensions\Purchase_Orders\helpers\Purchase_Order::get_status_type_id()]];
                    unset($st_list);
                } else {
                    $os_oes_list_pointer = [0 => ''];
                }
                foreach (\common\extensions\Purchase_Orders\helpers\Purchase_Order::get_evaluation_state_array() as $poes_id => $poes_array) {
                    $os_oes_list_pointer[$poes_id] = defined('TEXT_EVALUATION_STATE_LONG_' . $poes_array['key']) ? constant('TEXT_EVALUATION_STATE_LONG_' . $poes_array['key']) : $poes_array['long'];
                }
                unset($os_oes_list_pointer);
                unset($poes_array);
                unset($poes_id);
            }
            $o_info->orders_status_send_ga = (int) ($osg_record->orders_status_groups_send_ga ?? null);
        }
        unset($osg_record);
        $comment_templates['selected'] = $o_info->comment_template_id ?? null;
        $comment_templates['items'] = ['' => ''];
        $comment_templates['options'] = [];
        foreach (\common\helpers\Comment_Template::get_active_variants($comment_templates['selected']) as $variant) {
            $comment_templates['items'][$variant['id']] = $variant['text'];
            //$comment_templates['options']['items'] = $variant['visibility'];
        }
        //TODO: need show/hide item list according visibility (rel orders_status_groups_id select)
        $orders_status_template_sms = [['id' => '', 'text' => '']];
        foreach (\common\models\Sms_Templates::find()->group_by(['sms_templates_key'])->order_by(['sms_templates_key' => SORT_ASC])->as_array(true)->all() as $sms_templates_record) {
            $orders_status_template_sms[] = ['id' => $sms_templates_record['sms_templates_key'], 'text' => $sms_templates_record['sms_templates_key']];
        }
        $gets = array_filter(\Yii::$app->request->get_query_params());
        $gets['orders_status_id'] = $o_info->orders_status_id;
        $type_id = (int) \Yii::$app->request->get('type_id', 0);
        return $this->render('edit', ['oInfo' => $o_info, 'actionUrl' => Yii::$app->url_manager->create_url(['orders_status/save'] + $gets), 'cancelUrl' => Yii::$app->url_manager->create_url(['orders_status/index'] + $gets), 'orders_status_id' => $orders_status_id, 'orders_status_template' => $orders_status_template, 'orders_status_template_sms' => $orders_status_template_sms, 'comment_templates' => $comment_templates, 'oInfo_orders_status_id' => $o_info->orders_status_id ? $o_info->orders_status_id : 0, 'orders_status_inputs_string' => $orders_status_inputs_string, 'languages' => $languages, 'platforms' => $platforms, 'designTemplates' => $design_templates, 'emailDesignTemplate' => $email_design_template, 'typeId' => $type_id, 'osOesList' => $os_oes_list]);
    }
    public function action_save()
    {
        \common\helpers\Translation::init('admin/orders_status');
        $orders_status_id = intval(Yii::$app->request->get('orders_status_id', 0));
        $orders_status_groups_id = intval(Yii::$app->request->post('orders_status_groups_id', 0));
        $order_evaluation_state_id = Yii::$app->request->post('order_evaluation_state_id', false);
        $order_evaluation_state_default = (int) Yii::$app->request->post('order_evaluation_state_default');
        $hidden = (int) Yii::$app->request->post('hidden', 0);
        if ($orders_status_id == 0) {
            $next_id_query = tep_db_query('select max(orders_status_id) as orders_status_id from ' . TABLE_ORDERS_STATUS . " where orders_status_id <> '99999'");
            //paypal
            $next_id = tep_db_fetch_array($next_id_query);
            $insert_id = $next_id['orders_status_id'] + 1;
        }
        if ($order_evaluation_state_id !== false) {
            $osg_record = \common\models\Orders_Status_Groups::find_one(['orders_status_groups_id' => $orders_status_groups_id]);
            if ($osg_record instanceof \common\models\Orders_Status_Groups) {
                $es_array = [];
                if ($osg_record->orders_status_type_id == \common\helpers\Order::get_status_type_id()) {
                    $es_array = \common\helpers\Order::get_evaluation_state_array();
                } elseif (\common\helpers\Acl::check_extension_allowed('PurchaseOrders') && $osg_record->orders_status_type_id == \common\extensions\Purchase_Orders\helpers\Purchase_Order::get_status_type_id()) {
                    $es_array = \common\extensions\Purchase_Orders\helpers\Purchase_Order::get_evaluation_state_array();
                }
                if (isset($es_array[$order_evaluation_state_id])) {
                    //\common\models\OrdersStatus::updateAll(['order_evaluation_state_id' => 0], ['order_evaluation_state_id' => $order_evaluation_state_id]);
                    if ($order_evaluation_state_default > 0) {
                        \common\models\Orders_Status::update_all(['order_evaluation_state_default' => 0], ['order_evaluation_state_id' => $order_evaluation_state_id]);
                    }
                } else {
                    $order_evaluation_state_id = 0;
                    $order_evaluation_state_default = 0;
                }
                unset($es_array);
            } else {
                $order_evaluation_state_id = 0;
                $order_evaluation_state_default = 0;
            }
            unset($osg_record);
        }
        $languages = \common\helpers\Language::get_languages(true);
        $orders_status_name_array = $_POST['orders_status_name'];
        $orders_status_name_array = is_array($orders_status_name_array) ? $orders_status_name_array : [];
        $orders_status_name_default = '';
        foreach ($orders_status_name_array as $key => &$value) {
            $value = trim($value);
            if ($value == '') {
                unset($orders_status_name_array[$key]);
            }
            if ($orders_status_name_default == '') {
                $orders_status_name_default = $value;
            }
            unset($value);
        }
        if (count($orders_status_name_array) == 0) {
            echo json_encode(['message' => 'Status name can\'t be empty!', 'messageType' => 'alert-error']);
            return false;
        }
        $language_installed_array = [];
        for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
            $language_id = $languages[$i]['id'];
            $language_installed_array[] = $language_id;
            $o_orders_status = \common\models\Orders_Status::find_one(['orders_status_id' => $orders_status_id, 'language_id' => (int) $language_id]);
            $action = 'updated';
            $added = false;
            if (!$o_orders_status instanceof \common\models\Orders_Status) {
                $added = $insert_id;
                $action = 'added';
                $o_orders_status = new \common\models\Orders_Status();
                $o_orders_status->language_id = $language_id;
                $o_orders_status->orders_status_id = $orders_status_id == 0 ? $insert_id : $orders_status_id;
            }
            $o_orders_status->orders_status_template = tep_db_prepare_input(Yii::$app->request->post('orders_status_template'));
            $o_orders_status->comment_template_id = intval(tep_db_prepare_input(Yii::$app->request->post('comment_template_id')));
            $o_orders_status->orders_status_template_confirm = tep_db_prepare_input(Yii::$app->request->post('orders_status_template_confirm'));
            $o_orders_status->orders_status_template_sms = tep_db_prepare_input(Yii::$app->request->post('orders_status_template_sms'));
            $o_orders_status->automated = (int) Yii::$app->request->post('automated');
            $o_orders_status->orders_status_groups_id = $orders_status_groups_id;
            if (!isset($orders_status_name_array[$language_id]) and trim($o_orders_status->orders_status_name) != '') {
                $orders_status_name_array[$language_id] = trim($o_orders_status->orders_status_name);
            }
            $o_orders_status->orders_status_name = tep_db_prepare_input(isset($orders_status_name_array[$language_id]) ? $orders_status_name_array[$language_id] : $orders_status_name_default);
            if ($order_evaluation_state_id !== false) {
                $o_orders_status->order_evaluation_state_id = $order_evaluation_state_id;
                $o_orders_status->order_evaluation_state_default = $order_evaluation_state_default;
            }
            $o_orders_status->orders_status_allocate_allow = (int) Yii::$app->request->post('orders_status_allocate_allow');
            $o_orders_status->orders_status_release_deferred = (int) Yii::$app->request->post('orders_status_release_deferred');
            $o_orders_status->orders_status_send_ga = -1;
            //(int)Yii::$app->request->post('orders_status_send_ga');
            $o_orders_status->hidden = $hidden;
            try {
                $o_orders_status->save(false);
            } catch (\Exception $e) {
                \Yii::warning($e->get_message() . ' ' . $e->get_trace_as_string());
            }
        }
        if (count($language_installed_array) > 0) {
            \common\models\Orders_Status::delete_all(['not in', 'language_id', $language_installed_array]);
        }
        if ($orders_status_id == 0) {
            $orders_status_id = $insert_id;
        }
        if (isset($_POST['default']) && $_POST['default'] == 'on') {
            tep_db_query('update ' . TABLE_CONFIGURATION . " set configuration_value = '" . tep_db_input($orders_status_id) . "' where configuration_key = 'DEFAULT_ORDERS_STATUS_ID'");
        }
        $default_online_payment_status_change = (int) \Yii::$app->request->post('defaultOnlinePaymentStatus', 0);
        if ($default_online_payment_status_change === 1) {
            $this->configuration_service->set_default_order_status_id_for_online_payment($orders_status_id);
        }
        $default_online_payment_success_status_change = (int) \Yii::$app->request->post('defaultOnlinePaymentSuccessStatus', 0);
        if ($default_online_payment_success_status_change > 0) {
            $this->configuration_service->set_default_order_status_id_for_online_payment_success($orders_status_id);
        }
        $design_templates = Yii::$app->request->post('designTemplates');
        $platforms = \common\classes\platform::get_list(false);
        foreach ($platforms as $platform) {
            $design_templates[$platform['id']];
            $template = \common\models\Orders_Status_To_Design_Template::find_one(['orders_status_id' => $orders_status_id, 'platform_id' => $platform['id']]);
            if ($design_templates[$platform['id']]) {
                if (!$template) {
                    $template = new \common\models\Orders_Status_To_Design_Template();
                }
                $template->attributes = ['orders_status_id' => $orders_status_id, 'platform_id' => $platform['id'], 'email_design_template' => $design_templates[$platform['id']]];
                $template->save();
            } elseif ($template) {
                $template->delete();
            }
        }
        if ($ext = \common\helpers\Extensions::is_allowed('OrderStatusRules')) {
            $ext::order_status_save($orders_status_id);
        }
        echo json_encode(['message' => 'Status ' . $action, 'messageType' => 'alert-success', 'added' => $added]);
    }
    public function action_order_status_rules()
    {
        if ($ext = \common\helpers\Extensions::is_allowed('OrderStatusRules')) {
            return $ext::order_status_action($this);
        }
        return '';
    }
    public function action_delete()
    {
        global $language;
        \common\helpers\Translation::init('admin/orders_status');
        $orders_status_id = (int) Yii::$app->request->post('orders_status_id', 0);
        if ($orders_status_id) {
            $remove_status = true;
            $status = tep_db_fetch_array(tep_db_query('SELECT COUNT(*) AS `count` FROM ' . TABLE_ORDERS . " WHERE orders_status='" . $orders_status_id . "' "));
            $error = [];
            if ($orders_status_id == DEFAULT_ORDERS_STATUS_ID) {
                $remove_status = false;
                $error = ['message' => ERROR_REMOVE_DEFAULT_ORDER_STATUS, 'messageType' => 'alert-danger'];
            } elseif ($this->configuration_service->is_default_order_status_id_for_online_payment($orders_status_id)) {
                $remove_status = false;
                $error = ['message' => ERROR_REMOVE_DEFAULT_ONLINE_PAYMENT_ORDERS_STATUS, 'messageType' => 'alert-danger'];
            } elseif ($this->configuration_service->is_default_order_status_id_for_online_payment_success($orders_status_id)) {
                $remove_status = false;
                $error = ['message' => TEXT_ERROR_REMOVE_DEFAULT_ONLINE_PAYMENT_SUCCESS_ORDERS_STATUS, 'messageType' => 'alert-danger'];
            } elseif ($status['count'] > 0) {
                $remove_status = false;
                $error = ['message' => ERROR_STATUS_USED_IN_ORDERS, 'messageType' => 'alert-danger'];
            } else {
                $history_query = tep_db_query('select count(*) as count from ' . TABLE_ORDERS_STATUS_HISTORY . " where orders_status_id = '" . (int) $orders_status_id . "'");
                $history = tep_db_fetch_array($history_query);
                if ($history['count'] > 0) {
                    $remove_status = false;
                    $error = ['message' => ERROR_STATUS_USED_IN_HISTORY, 'messageType' => 'alert-danger'];
                }
            }
            if (!$remove_status) {
                ?>
              <div class="alert fade in <?php 
                echo $error['messageType'];
                ?>">
                  <i data-dismiss="alert" class="icon-remove close"></i>
                  <span id="message_plce"><?php 
                echo $error['message'];
                ?></span>
              </div>
                <?php 
            } else {
                $orders_status_query = tep_db_query('select configuration_value from ' . TABLE_CONFIGURATION . " where configuration_key = 'DEFAULT_ORDERS_STATUS_ID'");
                $orders_status = tep_db_fetch_array($orders_status_query);
                if ($orders_status['configuration_value'] == $orders_status_id) {
                    tep_db_query('update ' . TABLE_CONFIGURATION . " set configuration_value = '' where configuration_key = 'DEFAULT_ORDERS_STATUS_ID'");
                }
                if ($this->configuration_service->is_default_order_status_id_for_online_payment($orders_status_id)) {
                    $this->configuration_service->set_default_order_status_id_for_online_payment((int) $orders_status['configuration_value']);
                }
                tep_db_query('delete from ' . TABLE_ORDERS_STATUS . " where orders_status_id = '" . tep_db_input($orders_status_id) . "'");
                echo 'reset';
            }
        }
    }
}