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

use backend\models\Admin_Carts;
use backend\models\EP\Messages;
use backend\models\Product_Name_Decorator;
use common\classes\modules\Module_Label;
use common\classes\order_total;
use common\classes\payment;
use common\classes\platform;
use common\classes\platform_config;
use common\components\Customer;
use common\helpers\Acl;
use common\helpers\Coupon;
use common\helpers\Order as OrderHelper;
use common\helpers\Status;
use common\helpers\Translation;
use common\models\Orders;
use Yii;
use yii\helpers\Array_Helper;
use yii\helpers\Html;
use yii\helpers\Url;
/**
 * default controller to handle user requests.
 */
class Orders_Controller extends Sceleton
{
    public $acl = ['BOX_HEADING_CUSTOMERS', 'BOX_CUSTOMERS_ORDERS'];
    /**
     * Index action is the default action in a controller.
     */
    public function __construct($id, $module = '')
    {
        if ($ext = \common\helpers\Acl::check_extension_allowed('BusinessToBusiness', 'allowed')) {
            $ext::check_customer_groups();
        }
        defined('GROUPS_IS_SHOW_PRICE') or define('GROUPS_IS_SHOW_PRICE', true);
        defined('GROUPS_DISABLE_CHECKOUT') or define('GROUPS_DISABLE_CHECKOUT', false);
        defined('GROUPS_DISABLE_CART') or define('GROUPS_DISABLE_CART', false);
        defined('SHOW_OUT_OF_STOCK') or define('SHOW_OUT_OF_STOCK', 1);
        \common\helpers\Translation::init('ordertotal');
        parent::__construct($id, $module);
    }
    public function action_index()
    {
        global $login_id, $navigation;
        if (is_object($navigation) && method_exists($navigation, 'set_snapshot')) {
            $navigation->set_snapshot();
        }
        $this->selected_menu = ['customers', 'orders'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('orders/index'), 'title' => HEADING_TITLE];
        if (\common\helpers\Acl::rule(['ACL_ORDER', 'IMAGE_NEW'])) {
            $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url(['editor/order-edit', 'back' => 'orders']) . '" class="btn btn-primary"><i class="icon-file-text"></i>' . TEXT_CREATE_NEW_OREDER . '</a>';
        }
        $this->view->heading_title = HEADING_TITLE;
        $this->view->orders_table = [];
        $this->view->orders_table[] = ['title' => '<input type="checkbox" class="uniform form-check-input">', 'not_important' => 2];
        if ($ext = \common\helpers\Acl::check_extension_allowed('OrderMarkers', 'allowed')) {
            $this->view->orders_table[] = ['title' => TABLE_HEADING_FLAG, 'not_important' => 0];
        }
        $this->view->orders_table[] = ['title' => TABLE_HEADING_CUSTOMERS];
        $this->view->orders_table[] = ['title' => TABLE_HEADING_ORDER_TOTAL, 'not_important' => 0];
        $this->view->orders_table[] = ['title' => TABLE_HEADING_DETAILS, 'not_important' => 0];
        $this->view->orders_table[] = ['title' => TABLE_HEADING_DATE_PURCHASED, 'not_important' => 0];
        $this->view->orders_table[] = ['title' => TABLE_HEADING_STATUS, 'not_important' => 1];
        if (\common\helpers\Acl::check_extension_allowed('Neighbour')) {
            $this->view->orders_table[] = ['title' => defined('EXT_NEIGHBOUR_TABLE_HEADING') ? EXT_NEIGHBOUR_TABLE_HEADING : TABLE_HEADING_NEIGHBOUR, 'not_important' => 0];
        }
        $GET = Yii::$app->request->get();
        $admin_filters = \common\models\Admin_Filters::find_one(['filter_type' => 'orders']);
        if ($admin_filters instanceof \common\models\Admin_Filters) {
            $GET += \Opis\Closure\unserialize($admin_filters->filter_data);
        }
        $this->view->filters = new \stdClass();
        $markers = [];
        if ($ext = \common\helpers\Acl::check_extension_allowed('OrderMarkers', 'allowed')) {
            $markers = $ext::get_markers_list(true);
        }
        $this->view->markers = $markers;
        $this->view->filters->marker = (int) Yii::$app->request->get('marker', 0);
        $flags = [];
        if ($ext = \common\helpers\Acl::check_extension_allowed('OrderMarkers', 'allowed')) {
            $flags = $ext::get_flags_list(true);
        }
        $this->view->flags = $flags;
        $this->view->filters->flag = (int) Yii::$app->request->get('flag', 0);
        $this->view->filters->mode = Yii::$app->request->get('mode', '');
        $by = [
            ['name' => TEXT_ANY, 'value' => '', 'selected' => ''],
            ['name' => TEXT_ORDER_ID, 'value' => 'oID', 'selected' => ''],
            ['name' => TEXT_CUSTOMER_ID, 'value' => 'cID', 'selected' => ''],
            ['name' => TEXT_MODEL, 'value' => 'model', 'selected' => ''],
            ['name' => TEXT_PRODUCT_NAME, 'value' => 'name', 'selected' => ''],
            /* [
               'name' => 'Brand',
               'value' => 'brand',
               'selected' => '',
               ], */
            ['name' => TEXT_WAREHOUSES_PRODUCTS_BATCH_NAME, 'value' => 'batchName', 'selected' => ''],
            ['name' => TEXT_CLIENT_NAME, 'value' => 'fullname', 'selected' => ''],
            ['name' => TEXT_CLIENT_EMAIL, 'value' => 'email', 'selected' => ''],
            ['name' => TEXT_CLIENT_PHONE, 'value' => 'phone', 'selected' => ''],
            ['name' => TEXT_TRACKING_NUMBER, 'value' => 'tracking_number', 'selected' => ''],
        ];
        foreach ($by as $key => $value) {
            if (isset($GET['by']) && $value['value'] == $GET['by']) {
                $by[$key]['selected'] = 'selected';
            }
        }
        $this->view->filters->by = $by;
        $search = '';
        if (isset($GET['search'])) {
            $search = $GET['search'];
        }
        $this->view->filters->search = $search;
        if (isset($GET['date']) && $GET['date'] == 'exact') {
            $this->view->filters->presel = false;
            $this->view->filters->exact = true;
        } else {
            $this->view->filters->presel = true;
            $this->view->filters->exact = false;
        }
        $interval = [['name' => TEXT_ALL, 'value' => '', 'selected' => ''], ['name' => TEXT_TODAY, 'value' => '1', 'selected' => ''], ['name' => TEXT_WEEK, 'value' => 'week', 'selected' => ''], ['name' => TEXT_THIS_MONTH, 'value' => 'month', 'selected' => ''], ['name' => TEXT_THIS_YEAR, 'value' => 'year', 'selected' => ''], ['name' => TEXT_LAST_THREE_DAYS, 'value' => '3', 'selected' => ''], ['name' => TEXT_LAST_SEVEN_DAYS, 'value' => '7', 'selected' => ''], ['name' => TEXT_LAST_FOURTEEN_DAYS, 'value' => '14', 'selected' => ''], ['name' => TEXT_LAST_THIRTY_DAYS, 'value' => '30', 'selected' => '']];
        foreach ($interval as $key => $value) {
            if (isset($GET['interval']) && $value['value'] == $GET['interval']) {
                $interval[$key]['selected'] = 'selected';
            }
        }
        $this->view->filters->interval = $interval;
        $this->view->filters->walkin = Yii::$app->request->get('walkin') ?? false;
        $this->view->filters->admin = [];
        foreach (\common\helpers\Admin::get_admins_with_walkin_orders() as $admin) {
            $this->view->filters->admin[$admin->admin_id] = $admin->admin_firstname . ' ' . $admin->admin_lastname;
        }
        $this->view->filters->status = \common\helpers\Order::get_status_list();
        $this->view->filters->status_selected = $GET['status'] ?? [];
        $this->view->filters->fcoupon = 'byId';
        $this->view->filters->fc_id = $GET['fc_id'] ?? [];
        if (!empty($GET['fc_code'])) {
            $this->view->filters->fc_code = htmlspecialchars($GET['fc_code']);
            $this->view->filters->fcoupon = 'like';
            $this->view->filters->fc_id = [];
        }
        $this->view->filters->f_coupons = \yii\helpers\Array_Helper::map(Coupon::get_ordered_list(), 'coupon_id', 'coupon_code');
        if (!empty($GET['fp_from'])) {
            //summ
            $this->view->filters->fp_from = htmlspecialchars($GET['fp_from']);
            $this->view->filters->fp_from = true;
            //flag
        } else {
            $this->view->filters->fp_from = false;
            $this->view->filters->fp_from = null;
        }
        if (!empty($GET['fp_to'])) {
            //summ
            $this->view->filters->fp_to = htmlspecialchars($GET['fp_to']);
            $this->view->filters->fp_to = true;
            //flag
        } else {
            $this->view->filters->fp_to = null;
            $this->view->filters->fp_to = false;
        }
        $this->view->filters->fp_class = Order_Helper::get_used_total_class_list($GET['fp_class'] ?? '');
        $o_model_query = Orders::find();
        $o_model_query->select(['payment_class', 'payment_method'])->where(['not', ['payment_class' => '']])->and_where(['not', ['payment_method' => '']]);
        $payments = \yii\helpers\Array_Helper::map($o_model_query->group_by('payment_class')->order_by('payment_class, payment_method')->as_array()->all(), 'payment_class', 'payment_method');
        $payments = array_map('html_entity_decode', array_map('strip_tags', $payments));
        foreach ($payments as $class => $method) {
            if (tep_not_null($method)) {
                $this->view->filters->payments[$class] = trim($method) . ' (' . $class . ')';
            }
        }
        if (!empty($this->view->filters->payments)) {
            asort($this->view->filters->payments);
        }
        $this->view->filters->payments_selected = $GET['payments'] ?? [];
        $o_model_query->select(['shipping_class', 'shipping_method'])->where(['not', ['shipping_class' => '']])->and_where(['not', ['shipping_method' => '']]);
        $shippings = \yii\helpers\Array_Helper::map($o_model_query->group_by('shipping_class')->order_by('shipping_class, shipping_method')->as_array()->all(), 'shipping_class', 'shipping_method');
        $shippings = array_map('html_entity_decode', array_map('strip_tags', $shippings));
        $this->view->filters->shipping = [];
        foreach ($shippings as $class => $method) {
            list($class, ) = explode('_', $class);
            if (tep_not_null($method)) {
                $this->view->filters->shipping[$class] = trim($method) . ' (' . $class . ')';
            }
        }
        asort($this->view->filters->shipping);
        $this->view->filters->shipping_selected = $GET['shipping'] ?? [];
        $delivery_country = '';
        if (isset($GET['delivery_country'])) {
            $delivery_country = $GET['delivery_country'];
        }
        $this->view->filters->delivery_country = $delivery_country;
        $delivery_state = '';
        if (in_array(ACCOUNT_STATE, ['required', 'required_register', 'visible', 'visible_register'])) {
            $this->view->show_state = true;
        } else {
            $this->view->show_state = false;
        }
        if (isset($GET['delivery_state'])) {
            $delivery_state = $GET['delivery_state'];
        }
        $this->view->filters->delivery_state = $delivery_state;
        $from = '';
        if (isset($GET['from'])) {
            $from = $GET['from'];
        }
        $this->view->filters->from = $from;
        $to = '';
        if (isset($GET['to'])) {
            $to = $GET['to'];
        }
        $this->view->filters->to = $to;
        $this->view->filters->row = (int) Yii::$app->request->get('row', 0);
        $fs = 'closed';
        if (isset($GET['fs'])) {
            $fs = $GET['fs'];
        }
        $this->view->filters->fs = $fs;
        $this->view->filters->platform = [];
        if (isset($GET['platform']) && is_array($GET['platform'])) {
            foreach ($GET['platform'] as $_platform_id) {
                if ((int) $_platform_id > 0) {
                    $this->view->filters->platform[] = (int) $_platform_id;
                }
            }
        }
        $this->view->filters->deficit_only = (int) Yii::$app->request->get('deficit_only', 0);
        $admin = new Admin_Carts();
        $admin->load_customers_baskets();
        $ids = $admin->get_virtual_cart_i_ds();
        $this->view->filters->admin_choice = [];
        if ($ids) {
            foreach ($ids as $_ids) {
                $this->view->filters->admin_choice[] = $this->render_ajax('mini', ['ids' => $_ids, 'customer' => \common\helpers\Customer::get_customer_data($_ids)]);
            }
        }
        $departments = false;
        if (defined('SUPERADMIN_ENABLED') && SUPERADMIN_ENABLED == true) {
            $this->view->filters->departments = [];
            if (isset($GET['departments']) && is_array($GET['departments'])) {
                foreach ($GET['departments'] as $_department_id) {
                    if ((int) $_department_id > 0) {
                        $this->view->filters->departments[] = (int) $_department_id;
                    }
                }
            }
            $departments = \common\classes\department::get_list(false);
        }
        $orders_statuses = \common\helpers\Order::get_status_list(false, true, 0);
        $orders_statuses_options = [];
        foreach (\common\helpers\Order::get_statuses(true, 0) as $orders_status) {
            if (is_array($orders_status->statuses)) {
                foreach ($orders_status->statuses as $status) {
                    if ($status->order_evaluation_state_id > 0) {
                        $orders_statuses_options[$status->orders_status_id]['evaluation_state_id'] = $status->order_evaluation_state_id;
                    }
                }
            }
        }
        $pl_filters = \Yii::$app->request->get('platform', []);
        $_pl = Array_Helper::map(platform::get_list(false), 'id', 'id');
        if (!empty($pl_filters)) {
            $pl_filters = array_intersect($pl_filters, $_pl);
        }
        if (is_array($pl_filters) && count($pl_filters) == 1) {
            $theme_platform_id = $pl_filters[0];
        } else {
            $theme_platform_id = platform::default_id();
        }
        // batch print extra documents
        $added_pages = \common\models\Themes_Settings::find()->alias('ts')->inner_join(TABLE_THEMES . ' t', 't.theme_name=ts.theme_name')->inner_join(TABLE_PLATFORMS_TO_THEMES . ' p2t', 't.id=p2t.theme_id')->select(['setting_name', 'setting_value', 'ts.id'])->where(['p2t.platform_id' => $theme_platform_id, 'setting_group' => 'added_page', 'setting_name' => ['packingslip', 'invoice']])->order_by('setting_name')->as_array()->all();
        $added_pages = Array_Helper::map($added_pages, 'id', 'setting_value', 'setting_name');
        $table_heading = '';
        $admin_table = \common\models\Admin::find_one(['admin_id' => (int) $login_id]);
        if ($admin_table) {
            $admin_templates = \common\models\Admin_Templates::find_one(['access_levels_id' => $admin_table->access_levels_id, 'page' => 'backendOrdersList']);
            if ($admin_templates) {
                defined('THEME_NAME') or define('THEME_NAME', \common\classes\design::page_name(BACKEND_THEME_NAME));
                $params = [];
                $params['backendOrdersList\BatchCheckbox'] = '<div class="checkbox-column"><input type="checkbox" class="uniform form-check-input"></div>';
                if ($admin_templates->template) {
                    $table_heading = \frontend\design\boxes\Table_Row::heading_row($params, $admin_templates->template);
                }
            }
        }
        return $this->render('index', ['isMultiPlatform' => \common\classes\platform::is_multi(), 'platforms' => \common\classes\platform::get_list(true, true), 'departments' => $departments, 'ordersStatuses' => $orders_statuses, 'ordersStatusesOptions' => $orders_statuses_options, 'addedPages' => $added_pages, 'tableHeading' => $table_heading]);
    }
    public function action_order_history()
    {
        $this->layout = false;
        $orders_id = Yii::$app->request->get('orders_id');
        \common\helpers\Translation::init('admin/orders');
        $params = [];
        $history = [];
        $orders_history_query = tep_db_query('select * from ' . TABLE_ORDERS_HISTORY . ' o left join ' . TABLE_ADMIN . " a on a.admin_id = o.admin_id where orders_id='" . (int) $orders_id . "' order by orders_history_id desc");
        while ($orders_history = tep_db_fetch_array($orders_history_query)) {
            $history[] = [
                'date' => \common\helpers\Date::datetime_short($orders_history['date_added']),
                'comments' => $orders_history['comments'],
                //Edited by Name of admin
                'admin' => $orders_history['admin_id'] ? $orders_history['admin_firstname'] . ' ' . $orders_history['admin_lastname'] : '',
            ];
        }
        $params['history'] = $history;
        $cid = Yii::$app->request->get('cid', 0);
        $params['show_recovery_details'] = false;
        if ($orders_id && $cid) {
            $params['ua'] = \common\helpers\System::get_ga_detection($orders_id);
            $params['ua_tracking'] = \common\models\Ecommerce_Tracking::find_all(['orders_id' => $orders_id]);
            //errors
            $params['errors'] = \common\models\Customers_Errors::find()->linking_to(\common\models\Orders::class)->where(['orders_id' => $orders_id])->order_by('error_date desc')->all();
            if (($ext = \common\helpers\Acl::check_extension_allowed('RecoverShoppingCart')) && defined('RCS_SHOW_AT_ORDERS') && RCS_SHOW_AT_ORDERS == 'true' && $orders_id && $cid) {
                $params['show_recovery_details'] = true;
                $ext::init_translation('order-history');
                //contacts
                $scart = tep_db_query('select * from ' . TABLE_SCART . ' s inner join ' . TABLE_ORDERS . " o on o.orders_id = '" . (int) $orders_id . "' where o.basket_id = s.basket_id and s.customers_id = '" . (int) $cid . "'");
                if (tep_db_num_rows($scart)) {
                    $_scart = tep_db_fetch_array($scart);
                    $_scart['recovered'] = $_scart['recovered'] ? TEXT_BTN_YES : TEXT_BTN_NO;
                    $_scart['contacted'] = $_scart['contacted'] ? TEXT_BTN_YES : TEXT_BTN_NO;
                    $_scart['workedout'] = $_scart['workedout'] ? TEXT_BTN_YES : TEXT_BTN_NO;
                    $params['scart'] = $_scart;
                    //gv && cc
                    $coupons = tep_db_query('select cet.coupon_id, cet.sent_firstname, cet.sent_lastname, cet.date_sent, c.coupon_code, c.coupon_amount, c.coupon_currency, c.coupon_type, c.coupon_active from ' . TABLE_COUPON_EMAIL_TRACK . ' cet left join ' . TABLE_COUPONS . ' c on c.coupon_id = cet.coupon_id inner join ' . TABLE_ORDERS . " o on o.orders_id = '" . (int) $orders_id . "' where o.basket_id = cet.basket_id and cet.customer_id_sent = '" . (int) $cid . "'");
                    if (tep_db_num_rows($coupons)) {
                        $_cops = [];
                        $currencies = Yii::$container->get('currencies');
                        while ($cop = tep_db_fetch_array($coupons)) {
                            $_cops[$cop['coupon_id']] = $cop;
                            $_cops[$cop['coupon_id']]['coupon_amount'] = $cop['coupon_code'] . ' (' . ($cop['coupon_type'] == 'F' || $cop['coupon_type'] == 'G' ? $currencies->format($cop['coupon_amount'], false, $cop['coupon_currency']) : ($cop['coupon_type'] == 'P' ? round($cop['coupon_amount'], 2) . '%' : '')) . ') ' . ($cop['coupon_type'] == 'G' && $cop['coupon_active'] == 'N' ? TEXT_USED : '') . ' - ' . $cop['sent_firstname'] . ' ' . $cop['sent_lastname'];
                            $_cops[$cop['coupon_id']]['coupon_type'] = $cop['coupon_type'] == 'G' ? GIFT_CERTIFICATE : DISCOUNT_COUPON;
                        }
                        $params['coupons'] = $_cops;
                    }
                    tep_db_free_result($coupons);
                }
            }
        }
        return $this->render_ajax('recovery', $params);
        //return $this->render('order-history.tpl');
    }
    public function action_orderlist()
    {
        global $login_id;
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/orders');
        $manager = \common\services\Order_Manager::load_manager(new \common\classes\shopping_cart());
        $manager->cleanup_temporary_guests();
        if (defined('SUPERADMIN_ENABLED') && SUPERADMIN_ENABLED == true) {
            $departments = [];
            $departments_list = \common\classes\department::get_list(false);
            foreach ($departments_list as $department) {
                $departments[$department['departments_id']] = $department['departments_store_name'];
            }
        }
        $draw = Yii::$app->request->get('draw');
        $start = Yii::$app->request->get('start');
        $length = Yii::$app->request->get('length');
        if ($length == -1) {
            $length = 10000;
        }
        $_session = Yii::$app->session;
        $search = '';
        if (isset($_GET['search']['value']) && tep_not_null($_GET['search']['value'])) {
            $keywords = tep_db_input(tep_db_prepare_input($_GET['search']['value']));
            $search_fields = ['o.order_number', 'o.customers_telephone', 'o.delivery_telephone', 'o.billing_telephone', 'o.customers_lastname', 'o.customers_firstname', 'o.customers_email_address', 'o.orders_id', 'op.products_model', 'op.products_name'];
            if (is_numeric($keywords)) {
                $search_fields[] = 'o.api_client_order_id';
            }
            /** @var \common\extensions\InvoiceNumberFormat\InvoiceNumberFormat $infExt */
            if ($inf_ext = \common\helpers\Acl::check_extension_allowed('InvoiceNumberFormat', 'allowed')) {
                if ($inf_ext::has_invoice_number()) {
                    $search_fields[] = 'o.invoice_number';
                }
            }
            $operator = 'LIKE';
            $operator1 = 'or';
            if (substr($keywords, 0, 1) == '!') {
                $operator = 'NOT LIKE';
                $operator1 = 'and';
                $keywords = substr($keywords, 1);
            }
            if (!empty($search_fields) && is_array($search_fields)) {
                $search_condition = ' and ( ' . implode(" {$operator} '%" . $keywords . "%' {$operator1} ", $search_fields) . " {$operator} '%" . $keywords . "%' )";
            }
        } else {
            $search_condition = '';
        }
        $_session->set('search_condition', $search_condition);
        $form_filter = Yii::$app->request->get('filter');
        $output = [];
        parse_str($form_filter, $output);
        if (isset($_GET['order'][0]['column']) && $_GET['order'][0]['dir'] && $_GET['draw'] != 1) {
            switch ($_GET['order'][0]['column']) {
                case 0:
                    $order_by = 'o.customers_name ' . tep_db_prepare_input($_GET['order'][0]['dir']);
                    break;
                case 1:
                    $order_by = 'ot.text ' . tep_db_prepare_input($_GET['order'][0]['dir']);
                    break;
                case 2:
                    $order_by = 'o.date_purchased ' . tep_db_prepare_input($_GET['order'][0]['dir']);
                    break;
                case 3:
                    $order_by = 's.orders_status_name ' . tep_db_prepare_input($_GET['order'][0]['dir']);
                    break;
                default:
                    $order_by = 'o.date_purchased desc, o.orders_id desc';
                    break;
            }
        } else {
            $order_by = 'o.date_purchased desc, o.orders_id desc';
            if (isset($output['mode']) && $output['mode'] == 'need_process') {
                $order_by = 'IFNULL(`o`.`hold_on_date`, `o`.`date_purchased`) desc';
            }
        }
        $cut_off_time = new \common\classes\Cut_Off_Time();
        $status_group_data = \yii\helpers\Array_Helper::index(\common\models\Orders_Status_Groups::find()->select(['orders_status_groups_name', 'orders_status_groups_color', 'orders_status_groups_id'])->where(['language_id' => (int) $languages_id, 'orders_status_type_id' => \common\helpers\Order::get_status_type_id()])->as_array()->all(), 'orders_status_groups_id');
        $_orders_products_joined = false;
        $orders_query_raw = \common\models\Orders::find()->select('o.orders_id, s.orders_status_name, s.orders_status_groups_id ')->from([TABLE_ORDERS_STATUS . ' s', TABLE_ORDERS . ' o']);
        if (isset($_GET['in_stock']) && $_GET['in_stock'] != '') {
            $_orders_products_joined = true;
            $orders_query_raw->left_join(TABLE_ORDERS_PRODUCTS . ' op', '(op.orders_id = o.orders_id)');
            $orders_query_raw->add_select('BIT_AND(' . (\common\helpers\Extensions::is_allowed('Inventory') ? 'if(i.products_quantity is not null,if((i.products_quantity>=op.products_quantity),1,0),if((p.products_quantity>=op.products_quantity),1,0))' : 'if((p.products_quantity>=op.products_quantity),1,0)') . ') as in_stock');
            $orders_query_raw->left_join(TABLE_PRODUCTS . ' p', '(p.products_id = op.products_id)');
            if (\common\helpers\Extensions::is_allowed('Inventory')) {
                $orders_query_raw->left_join(TABLE_INVENTORY . ' i', '(i.prid = op.products_id and i.products_id = op.uprid)');
            }
        }
        if (\common\helpers\Extensions::is_allowed('Handlers')) {
            if (!$_orders_products_joined) {
                $_orders_products_joined = true;
                $orders_query_raw->left_join(TABLE_ORDERS_PRODUCTS . ' op', '(op.orders_id = o.orders_id)');
            }
            $orders_query_raw->left_join('handlers_products hp', 'hp.products_id = op.products_id');
        }
        $_orders_products_allocate_joined = false;
        if (!\common\helpers\Acl::rule(['BOX_HEADING_CUSTOMERS', 'BOX_CUSTOMERS_ORDERS', 'RULE_ALLOW_WAREHOUSES'])) {
            $orders_query_raw->left_join('orders_products_allocate opa', 'opa.orders_id = o.orders_id');
            $_orders_products_allocate_joined = true;
        }
        if (!\common\helpers\Acl::rule(['BOX_HEADING_CUSTOMERS', 'BOX_CUSTOMERS_ORDERS', 'RULE_ALLOW_SUPPLIERS'])) {
            if (!$_orders_products_allocate_joined) {
                $orders_query_raw->left_join('orders_products_allocate opa', 'opa.orders_id = o.orders_id');
                $_orders_products_allocate_joined = true;
            }
        }
        //$orders_query_raw->leftJoin(TABLE_CUSTOMERS . " c", "(o.customers_id = c.customers_id)");
        $orders_query_raw->where('o.orders_status = s.orders_status_id ' . $search_condition . " and s.language_id = '" . (int) $languages_id . "' and s.orders_status_groups_id IN('" . implode("','", array_keys($status_group_data)) . "') ");
        if (strpos($search_condition, ' op.') !== false && !$_orders_products_joined) {
            $orders_query_raw->left_join(TABLE_ORDERS_PRODUCTS . ' op', '(op.orders_id = o.orders_id)');
            $_orders_products_joined = true;
        }
        $filter = '';
        if (defined('SUPERADMIN_ENABLED') && SUPERADMIN_ENABLED == true) {
            $filter_by_departments = [];
            if (isset($output['departments']) && is_array($output['departments'])) {
                foreach ($output['departments'] as $_department_id) {
                    if ((int) $_department_id > 0) {
                        $filter_by_departments[] = (int) $_department_id;
                    }
                }
            }
            if (count($filter_by_departments) > 0) {
                $orders_query_raw->and_where(['in', 'o.department_id', $filter_by_departments]);
            }
        }
        $filter_by_platform = [];
        if (isset($output['platform']) && is_array($output['platform'])) {
            foreach ($output['platform'] as $_platform_id) {
                if ((int) $_platform_id > 0) {
                    $filter_by_platform[] = (int) $_platform_id;
                }
            }
        } elseif (false === \common\helpers\Acl::rule(['SUPERUSER'])) {
            $platforms = \common\models\Admin_Platforms::find()->where(['admin_id' => $login_id])->as_array()->all();
            foreach ($platforms as $platform) {
                $filter_by_platform[] = $platform['platform_id'];
            }
            $filter_by_platform[] = 0;
        }
        if (count($filter_by_platform) > 0) {
            $orders_query_raw->and_where(['in', 'o.platform_id', $filter_by_platform]);
        }
        /**
         * @var $ext \common\extensions\Handlers\Handlers
         */
        if ($ext = \common\helpers\Extensions::is_allowed('Handlers')) {
            global $access_levels_id;
            $orders_query_raw->and_where(['OR', ['in', 'hp.handlers_id', $ext::get_handlers_query((int) $access_levels_id)], ['hp.handlers_id' => null]]);
        }
        if (!\common\helpers\Acl::rule(['BOX_HEADING_CUSTOMERS', 'BOX_CUSTOMERS_ORDERS', 'RULE_ALLOW_WAREHOUSES'])) {
            $warehouses_array = [];
            foreach (\common\models\Admin_Warehouses::find()->where(['admin_id' => $login_id])->as_array()->all() as $warehouse) {
                $warehouses_array[] = $warehouse['warehouse_id'];
            }
            unset($warehouse);
            $orders_query_raw->and_where(['in', 'opa.warehouse_id', $warehouses_array]);
            unset($warehouses_array);
        }
        if (!\common\helpers\Acl::rule(['BOX_HEADING_CUSTOMERS', 'BOX_CUSTOMERS_ORDERS', 'RULE_ALLOW_SUPPLIERS'])) {
            $suppliers_array = [];
            foreach (\common\models\Admin_Suppliers::find()->where(['admin_id' => $login_id])->as_array()->all() as $supplier) {
                $suppliers_array[] = $supplier['suppliers_id'];
            }
            unset($supplier);
            $orders_query_raw->and_where(['in', 'opa.suppliers_id', $suppliers_array]);
            unset($suppliers_array);
        }
        if (tep_not_null($output['search'])) {
            $search = tep_db_prepare_input($output['search']);
            $operator = 'LIKE';
            if (substr($search, 0, 1) == '!') {
                $operator = 'NOT LIKE';
                $search = substr($search, 1);
            }
            switch ($output['by']) {
                case 'cID':
                    $orders_query_raw->and_where("o.customers_id = '" . (int) $search . "'");
                    break;
                case 'oID':
                    $orders_query_raw->and_where(['or', ['o.orders_id' => (int) $search], ['o.order_number' => $search]]);
                    break;
                case 'model':
                default:
                    if (!$_orders_products_joined) {
                        $_orders_products_joined = true;
                        $orders_query_raw->left_join(TABLE_ORDERS_PRODUCTS . ' op', '(op.orders_id = o.orders_id)');
                    }
                    $orders_query_raw->and_where([$operator, 'op.products_model', $search]);
                    break;
                case 'name':
                    if (!$_orders_products_joined) {
                        $_orders_products_joined = true;
                        $orders_query_raw->left_join(TABLE_ORDERS_PRODUCTS . ' op', '(op.orders_id = o.orders_id)');
                    }
                    $orders_query_raw->and_where([$operator, 'op.products_name', $search]);
                    break;
                case 'brand':
                    break;
                case 'batchName':
                    if (!$_orders_products_allocate_joined) {
                        $orders_query_raw->left_join('orders_products_allocate opa', 'opa.orders_id = o.orders_id');
                        $_orders_products_allocate_joined = true;
                    }
                    $orders_query_raw->left_join('warehouses_products_batches wpb', 'wpb.batch_id = opa.batch_id');
                    $orders_query_raw->and_where([$operator, 'wpb.batch_name', $search]);
                    break;
                case 'fullname':
                    $orders_query_raw->and_where([$operator, 'o.customers_name', $search]);
                    break;
                case 'email':
                    $orders_query_raw->and_where([$operator, 'o.customers_email_address', $search]);
                    break;
                case 'phone':
                    $orders_query_raw->and_where([$operator == 'LIKE' ? 'OR' : 'AND', [$operator, 'o.customers_telephone', $search], [$operator, 'o.delivery_telephone', $search], [$operator, 'o.billing_telephone', $search]]);
                    break;
                case 'tracking_number':
                    $orders_query_raw->and_where([$operator, 'o.tracking_number', $search]);
                    break;
                case '':
                case 'any':
                    if (!$_orders_products_joined) {
                        $_orders_products_joined = true;
                        $orders_query_raw->left_join(TABLE_ORDERS_PRODUCTS . ' op', '(op.orders_id = o.orders_id)');
                    }
                    $orders_query_raw->left_join(TABLE_ORDERS_STATUS_HISTORY . ' osh', 'o.orders_id = osh.orders_id');
                    $orders_query_raw->and_filter_where(['or', ['o.orders_id' => $search], [$operator == 'LIKE' ? 'OR' : 'AND', [$operator, 'o.order_number', $search], [$operator, 'op.products_model', $search], [$operator, 'op.products_name', $search], [$operator, 'o.customers_name', $search], [$operator, 'o.customers_email_address', $search], [$operator, 'osh.comments', $search], [$operator, 'o.tracking_number', $search], [$operator, 'o.customers_telephone', $search], [$operator, 'o.delivery_telephone', $search], [$operator, 'o.billing_telephone', $search]]]);
                    break;
            }
        }
        if (isset($output['delivery_country']) && !empty($output['delivery_country'])) {
            $orders_query_raw->and_where("o.delivery_country='" . tep_db_input($output['delivery_country']) . "'");
        }
        if (isset($output['delivery_state']) && !empty($output['delivery_state'])) {
            $orders_query_raw->and_where("o.delivery_state='" . tep_db_input($output['delivery_state']) . "'");
        }
        if (isset($output['status']) && is_array($output['status'])) {
            $orders_query_raw->and_where(['in', 's.orders_status_id', $output['status']]);
        }
        if (isset($output['mode']) && $output['mode'] == 'need_process') {
            if (defined('ORDERS_NOT_PROCESSED_ORDER_STATUSES')) {
                $_filter_need_statuses = implode("','", array_map('intval', explode(',', ORDERS_NOT_PROCESSED_ORDER_STATUSES)));
                if (strlen($_filter_need_statuses) > 0) {
                    $orders_query_raw->and_where("o.orders_status IN ('" . $_filter_need_statuses . "')");
                }
            } else {
                $orders_query_raw->and_where('s.orders_status_groups_id IN (1,2)');
            }
        }
        if (isset($output['date'])) {
            switch ($output['date']) {
                case 'exact':
                    if (tep_not_null($output['from'])) {
                        $from = tep_db_prepare_input($output['from']);
                        $orders_query_raw->and_where("to_days(o.date_purchased) >= to_days('" . \common\helpers\Date::prepare_input_date($from) . "')");
                    }
                    if (tep_not_null($output['to'])) {
                        $to = tep_db_prepare_input($output['to']);
                        $orders_query_raw->and_where("to_days(o.date_purchased) <= to_days('" . \common\helpers\Date::prepare_input_date($to) . "')");
                    }
                    break;
                case 'presel':
                    if (tep_not_null($output['interval'])) {
                        switch ($output['interval']) {
                            case 'week':
                                $orders_query_raw->and_where("o.date_purchased >= '" . date('Y-m-d', strtotime('monday this week')) . "'");
                                break;
                            case 'month':
                                $orders_query_raw->and_where("o.date_purchased >= '" . date('Y-m-d', strtotime('first day of this month')) . "'");
                                break;
                            case 'year':
                                $orders_query_raw->and_where("o.date_purchased >= '" . date('Y') . '-01-01' . "'");
                                break;
                            case '1':
                                $orders_query_raw->and_where("o.date_purchased >= '" . date('Y-m-d') . "'");
                                break;
                            case '3':
                            case '7':
                            case '14':
                            case '30':
                                $orders_query_raw->and_where('o.date_purchased >= date_sub(now(), interval ' . (int) $output['interval'] . ' day)');
                                break;
                        }
                    }
                    break;
            }
        }
        if (isset($output['payments']) && !empty($output['payments'])) {
            $orders_query_raw->and_where(['in', 'o.payment_class', $output['payments']]);
        }
        if (isset($output['shipping']) && !empty($output['shipping'])) {
            $orders_query_raw->and_where(['in', 'SUBSTRING_INDEX(o.shipping_class, "_", 1)', $output['shipping']]);
        }
        if (isset($output['fc_id']) && is_array($output['fc_id']) && count($output['fc_id'])) {
            $orders_query_raw->inner_join(TABLE_COUPON_REDEEM_TRACK . ' crt', 'o.orders_id=crt.order_id and crt.coupon_id in (' . implode(',', $output['fc_id']) . ') ');
        }
        if (isset($output['fc_code']) && !empty($output['fc_code'])) {
            $orders_query_raw->inner_join(TABLE_ORDERS_TOTAL . ' otfc', "o.orders_id=otfc.orders_id and otfc.class='ot_coupon' and otfc.title like '%" . tep_db_input($output['fc_code']) . "%'");
        }
        if (isset($output['flag']) && $output['flag'] > 0) {
            $orders_query_raw->inner_join('orders_markers' . ' omf', "o.orders_id=omf.orders_id and omf.flags='" . (int) $output['flag'] . "'");
        }
        if (isset($output['marker']) && $output['marker'] > 0) {
            $orders_query_raw->inner_join('orders_markers' . ' omm', "o.orders_id=omm.orders_id and omm.markers='" . (int) $output['marker'] . "'");
        }
        if ((isset($output['fp_from']) && !empty($output['fp_from']) || isset($output['fp_to']) && !empty($output['fp_to'])) && (isset($output['fp_class']) && !empty($output['fp_class']))) {
            if (strpos($output['fp_from'], ',') !== false) {
                if (strpos($output['fp_from'], '.') !== false) {
                    $output['fp_from'] = str_replace(',', '', $output['fp_from']);
                } else {
                    $output['fp_from'] = str_replace(',', '.', $output['fp_from']);
                }
            }
            $fp_from = preg_replace('/[^0-9\.]/', '', $output['fp_from']);
            if (strpos($output['fp_to'], ',') !== false) {
                if (strpos($output['fp_to'], '.') !== false) {
                    $output['fp_to'] = str_replace(',', '', $output['fp_to']);
                } else {
                    $output['fp_to'] = str_replace(',', '.', $output['fp_to']);
                }
            }
            $fp_to = preg_replace('/[^0-9\.]/', '', $output['fp_to']);
            $orders_query_raw->inner_join(TABLE_ORDERS_TOTAL . ' otfp', "o.orders_id=otfp.orders_id and otfp.class='" . tep_db_input($output['fp_class']) . "'" . (tep_not_null($output['fp_from']) ? " and round(otfp.value, 2)>='" . tep_db_input(round($fp_from, 2)) . "'" : '') . (tep_not_null($output['fp_to']) ? " and round(otfp.value,2)<='" . tep_db_input(round($fp_to, 2)) . "'" : '') . '');
        }
        if (isset($output['walkin']) && is_array($output['walkin'])) {
            $orders_query_raw->and_where(['in', 'o.admin_id', $output['walkin']]);
        }
        if (isset($output['deficit_only']) and (int) $output['deficit_only'] > 0) {
            if (!$_orders_products_joined) {
                $_orders_products_joined = true;
                $orders_query_raw->left_join(TABLE_ORDERS_PRODUCTS . ' op', '(op.orders_id = o.orders_id)');
            }
            $orders_query_raw->and_where('((op.products_quantity - op.qty_cnld) > op.qty_rcvd)');
        }
        $orders_query_raw->group_by('o.orders_id');
        if (isset($_GET['in_stock']) && $_GET['in_stock'] != '') {
            $orders_query_raw->having('in_stock ' . ($_GET['in_stock'] > 0 ? ' > 0' : ' < 1'));
        }
        $orders_query_raw->order_by($order_by);
        if ($ext = \common\helpers\Acl::check_extension('Neighbour', 'allowed')) {
            if ($ext::allowed()) {
                $ext_query = $ext::get_query($orders_query_raw);
            }
        }
        foreach (\common\helpers\Hooks::get_list('orders/orderlist') as $filename) {
            include $filename;
        }
        $stats = [];
        if (isset($output['show_stats']) && $output['show_stats']) {
            $stats['show'] = '1';
            $orders_query_stats = clone $orders_query_raw;
            $all_orders_ids = $orders_query_stats->select('o.orders_id')->column();
            $stats['products'] = (int) \common\models\Orders::find()->alias('o')->where(['o.orders_id' => $all_orders_ids])->left_join(TABLE_ORDERS_PRODUCTS . ' op', '(op.orders_id = o.orders_id)')->sum('op.products_quantity');
            $stats['total'] = \common\models\Orders::find()->alias('o')->where(['o.orders_id' => $all_orders_ids])->left_join(TABLE_ORDERS_TOTAL . ' ot', "(o.orders_id = ot.orders_id and ot.class = 'ot_total')")->sum(new \yii\db\Expression('ifnull(ot.value_inc_tax, 0) * if(o.currency_value_default > 0, o.currency_value_default, 1)'));
            $currencies = Yii::$container->get('currencies');
            $stats['total_format'] = $currencies->format($stats['total'], false);
        }
        //echo $orders_query_raw->createCommand()->getRawSql();
        $_session->set('filter', $orders_query_raw->where);
        $orders_query_numrows = $orders_query_raw->count();
        $orders_query_raw->limit($length)->offset($start)->with('ordersTotals');
        if (SHOW_PRODUCTS_ON_ORDER_LIST !== 'False') {
            $orders_query_raw->with('ordersProducts');
        }
        //echo $orders_query_raw->createCommand()->getRawSql()."\n\n";
        $orders_all = $orders_query_raw->as_array()->all();
        // {{ append orders status group table
        foreach ($orders_all as $__idx => $_row) {
            if (isset($status_group_data[$_row['orders_status_groups_id']])) {
                $orders_all[$__idx] = array_merge($orders_all[$__idx], $status_group_data[$_row['orders_status_groups_id']]);
            }
        }
        // }} append orders status group table
        // {{ append page data
        if (count($orders_all) > 0) {
            $_page_order_ids = array_map(function ($row) {
                return $row['orders_id'];
            }, $orders_all);
            $_page_order_id_to_idx = array_flip($_page_order_ids);
            $complete_page_data = \common\models\Orders::find()->select('o.*')->add_select('c.customers_gender')->add_select('ad.admin_firstname, ad.admin_lastname')->add_select('ot.text_inc_tax as order_total')->from(TABLE_ORDERS . ' o')->left_join(TABLE_ORDERS_TOTAL . ' ot', "(o.orders_id = ot.orders_id and ot.class = 'ot_total')")->left_join(TABLE_ADMIN . ' ad', '(ad.admin_id = o.admin_id)')->left_join(TABLE_CUSTOMERS . ' c', '(o.customers_id = c.customers_id)')->where(['IN', 'o.orders_id', $_page_order_ids])->as_array()->all();
            foreach ($complete_page_data as $__order_data) {
                $__idx = $_page_order_id_to_idx[$__order_data['orders_id']];
                $orders_all[$__idx] = array_merge($__order_data, $orders_all[$__idx]);
            }
            if ($ext = \common\helpers\Acl::check_extension_allowed('OrderMarkers', 'allowed')) {
                $order_markers = $ext::get_order_markers_batch($_page_order_ids);
                foreach ($order_markers as $_test_order_id => $_order_marker) {
                    $__idx = $_page_order_id_to_idx[$_test_order_id];
                    $orders_all[$__idx]['orderMarkers'] = $_order_marker;
                }
            }
        }
        // }} append page data
        if (\common\helpers\Acl::check_extension_allowed('FraudAddress', 'allowed')) {
            $orders_all = \common\extensions\Fraud_Address\Fraud_Address::orders_listing($orders_all);
        }
        $markers = [];
        if ($ext = \common\helpers\Acl::check_extension_allowed('OrderMarkers', 'allowed')) {
            $markers = $ext::get_markers();
        }
        $flags = [];
        if ($ext = \common\helpers\Acl::check_extension_allowed('OrderMarkers', 'allowed')) {
            $flags = $ext::get_flags();
        }
        $page_name = false;
        $admin_table = \common\models\Admin::find_one(['admin_id' => (int) $login_id]);
        if ($admin_table) {
            $admin_templates = \common\models\Admin_Templates::find_one(['access_levels_id' => $admin_table->access_levels_id, 'page' => 'backendOrdersList']);
            if ($admin_templates) {
                $page_name = $admin_templates->template;
            }
        }
        defined('THEME_NAME') or define('THEME_NAME', \common\classes\design::page_name(BACKEND_THEME_NAME));
        $response_list = [];
        $stack = [];
        if ($orders_all) {
            $selected_platform_id = \common\classes\platform::first_id();
            Yii::$app->get('platform')->config($selected_platform_id)->constant_up();
            foreach ($orders_all as $orders) {
                $p_list = '';
                $p_list2 = '';
                $counter = 0;
                $max_view = MAX_PRODUCTS_IN_ORDERS;
                if (SHOW_PRODUCTS_ON_ORDER_LIST !== 'False') {
                    $_product_block_cache_key = 'orders_list_products_' . (int) $orders['orders_id'] . '_' . strtotime($orders['last_modified']);
                    if (!$p_list = Yii::$app->get_cache()->get($_product_block_cache_key)) {
                        if (is_array($orders['ordersProducts']) && count($orders['ordersProducts']) > 0) {
                            foreach ($orders['ordersProducts'] as $__idx => $orders_product) {
                                $orders['ordersProducts'][$__idx]['name'] = $orders_product['products_name'];
                                $orders['ordersProducts'][$__idx]['id'] = $orders_product['uprid'];
                            }
                            if (Product_Name_Decorator::instance()->use_internal_name_for_order()) {
                                $orders['ordersProducts'] = Product_Name_Decorator::instance()->get_updated_order_products($orders['ordersProducts'], $orders['language_id'], $orders['platform_id']);
                            }
                            foreach ($orders['ordersProducts'] as $products) {
                                $products['name'] = htmlentities($products['name']);
                                $counter++;
                                $p_list_tmp = '<div class="ord-desc-row"><div>' . $products['products_quantity'] . ' x ' . (mb_strlen($products['name']) > 48 ? mb_substr($products['name'], 0, 48) . '...' : $products['name']) . '</div><div class="order_pr_model">' . 'SKU: ' . (mb_strlen($products['products_model']) > 8 ? mb_substr($products['products_model'], 0, 8) . '...' : $products['products_model']) . ($products['products_model'] ? '<span>' . $products['products_model'] . '</span>' : '') . '</div></div>';
                                if ($counter <= $max_view) {
                                    $p_list .= $p_list_tmp;
                                }
                                if ($counter == $max_view + 1) {
                                    $p_list2 = $p_list_tmp;
                                }
                            }
                        }
                        if ($counter == $max_view + 1) {
                            $p_list .= $p_list2;
                        }
                        if ($counter > $max_view + 1) {
                            $p_list .= '<div class="ord-desc-row ord-desc-row-more"><div>...</div></div>';
                            $p_list .= '<div class="ord-desc-row ord-desc-row-more"><div>' . $max_view . ' ' . TEXT_OF_TOTAL . ' ' . $counter . '</div></div>';
                        }
                        Yii::$app->get_cache()->set($_product_block_cache_key, $p_list, 600);
                    }
                }
                $delivery_info = '';
                $timestamp = strtotime($orders['date_purchased']);
                if ($ext = \common\helpers\Acl::check_extension_allowed('DelayedDespatch', 'allowed')) {
                    $delivery_info = $ext::show_delivery_date($orders['delivery_date']);
                }
                if (date('Y-m-d', $timestamp) == date('Y-m-d') && mb_strlen($delivery_info) == 0) {
                    $delivery_info .= '</div><div class="ord-date-purch-delivery' . ($cut_off_time->is_today_delivery($orders['date_purchased'], $orders['platform_id']) ? ' ord-date-purch-delivery-check' : '') . '">' . TEXT_TODAY_DELIVERY . ':</div><div class="ord-date-purch-delivery' . ($cut_off_time->is_next_day_delivery($orders['date_purchased'], $orders['platform_id']) ? ' ord-date-purch-delivery-check' : '') . '">' . TEXT_NEXT_DELIVERY . ':</div>';
                }
                //------
                $customers_email_address = $orders['customers_email_address'];
                $w = preg_quote(trim($search));
                if (!empty($w)) {
                    $regexp = "/({$w})(?![^<]+>)/i";
                    $replacement = '<b style="color:#ff0000">\1</b>';
                    $orders['customers_name'] = preg_replace($regexp, $replacement, $orders['customers_name']);
                    $p_list = preg_replace($regexp, $replacement, $p_list);
                    $customers_email_address = preg_replace($regexp, $replacement, $orders['customers_email_address']);
                }
                //------
                $order_totals = '';
                if (is_array($orders['ordersTotals']) && count($orders['ordersTotals'])) {
                    foreach ($orders['ordersTotals'] as $totals) {
                        if (file_exists(\Yii::get_alias('@common') . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'orderTotal' . DIRECTORY_SEPARATOR . $totals['class'] . '.php')) {
                            include_once \Yii::get_alias('@common') . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . 'orderTotal' . DIRECTORY_SEPARATOR . $totals['class'] . '.php';
                            $totals['class'] = '\common\modules\orderTotal\\' . $totals['class'];
                        } elseif (file_exists(DIR_FS_CATALOG . DIR_WS_MODULES . 'order_total/' . $totals['class'] . '.php')) {
                            include_once DIR_FS_CATALOG . DIR_WS_MODULES . 'order_total/' . $totals['class'] . '.php';
                        }
                        if (class_exists($totals['class'])) {
                            if (!array_key_exists($totals['class'], $stack)) {
                                $stack[$totals['class']] = new $totals['class']();
                            }
                            $object = $stack[$totals['class']];
                            if (!is_object($object)) {
                                $object = new $totals['class']();
                            }
                            if (method_exists($object, 'visibility')) {
                                if (true == $object->visibility(platform::default_id(), 'TEXT_ADMINORDER')) {
                                    if (method_exists($object, 'visibility')) {
                                        $result = $object->display_text(platform::default_id(), 'TEXT_ADMINORDER', $totals);
                                        $order_totals .= '<div class="' . $result['class'] . (Array_Helper::get_value($result, 'show_line') ? ' totals-line' : '') . '"><span>' . $result['title'] . '</span><span>' . $result['text'] . '</span></div>';
                                    } else {
                                        $order_totals .= '<div><span>' . $totals['title'] . '</span><span>' . $totals['text'] . '</span></div>';
                                    }
                                }
                            }
                        }
                    }
                }
                if (defined('SUPERADMIN_ENABLED') && SUPERADMIN_ENABLED == true) {
                    $department_info = TEXT_FROM . ' ' . $departments[$orders['department_id']];
                    if ($orders['api_client_order_id']) {
                        $department_info .= ' (#' . $orders['api_client_order_id'] . ')';
                    }
                } else {
                    $department_info = $orders['admin_id'] > 0 ? '&nbsp;by admin' : (\common\classes\platform::is_multi() >= 0 ? (SHOW_PRODUCTS_ON_ORDER_LIST === 'False' ? '<br>' : ' ') . TEXT_FROM . ' ' . \common\classes\platform::name($orders['platform_id']) : '');
                }
                $table_order_row = [];
                $purchased_date = \common\helpers\Date::datetime_short($orders['date_purchased']);
                $today_date = \common\helpers\Date::date_short(date('Y-m-d'));
                $purchased_date = str_replace($today_date, TEXT_TODAY, $purchased_date);
                $cus_column = '';
                if ($orders['customers_id']) {
                    $cus_column = '<div class="ord-name ord-gender ord-gender-' . $orders['customers_gender'] . ' click_double" data-click-double="' . \Yii::$app->url_manager->create_url(['orders/process-order', 'orders_id' => $orders['orders_id']]) . '">' . (\common\models\Customers::find_one($orders['customers_id']) ? '<a href="' . \Yii::$app->url_manager->create_url(['customers/customeredit', 'customers_id' => $orders['customers_id']]) . '" title="' . strip_tags($orders['customers_name']) . '">' . Html::encode(self::crop_str($orders['customers_name'], 22)) . '</a>' : Html::encode(self::crop_str($orders['customers_name'], 22))) . '</div><a href="mailto:' . $orders['customers_email_address'] . '" class="ord-name-email" title="' . strip_tags($customers_email_address) . '">' . self::crop_str($customers_email_address, 22) . '</a><div class="ord-location" style="margin-top: 5px;">' . Html::encode($orders['customers_postcode']) . '<div class="ord-total-info ord-location-info"><div class="ord-box-img"></div><b>' . Html::encode($orders['customers_name']) . '</b>' . Html::encode($orders['customers_street_address']) . '<br>' . Html::encode($orders['customers_city'] . ', ' . $orders['customers_state']) . '&nbsp;' . Html::encode($orders['customers_postcode']) . '<br>' . $orders['customers_country'] . '</div></div>';
                    $table_order_row['backendOrdersList\CustomerGender'] = $orders['customers_gender'];
                    $table_order_row['backendOrdersList\CustomerName'] = \common\models\Customers::find_one($orders['customers_id']) ? '<a href="' . \Yii::$app->url_manager->create_url(['customers/customeredit', 'customers_id' => $orders['customers_id']]) . '" title="' . strip_tags($orders['customers_name']) . '">' . Html::encode(self::crop_str($orders['customers_name'], 22)) . '</a>' : Html::encode(self::crop_str($orders['customers_name'], 22));
                    $table_order_row['backendOrdersList\CustomerEmail'] = '<a href="mailto:' . $orders['customers_email_address'] . '" class="ord-name-email" title="' . strip_tags($customers_email_address) . '">' . self::crop_str($customers_email_address, 22) . '</a>';
                    $table_order_row['backendOrdersList\OrderLocation'] = '<div class="ord-total-info ord-location-info"><div class="ord-box-img"></div><b>' . Html::encode($orders['customers_name']) . '</b>' . Html::encode($orders['customers_street_address']) . '<br>' . Html::encode($orders['customers_city'] . ', ' . $orders['customers_state']) . '&nbsp;' . Html::encode($orders['customers_postcode']) . '<br>' . $orders['customers_country'] . '</div></div>';
                } elseif ($orders['admin_id']) {
                    $customer_delivery_name = '(' . $orders['delivery_name'] . ')';
                    $customer_delivery_info = '<div class="ord-location" style="margin-top: 5px;">' . $orders['delivery_postcode'] . '<div class="ord-total-info ord-location-info"><div class="ord-box-img"></div><b>' . Html::encode($orders['delivery_name']) . '</b>' . Html::encode($orders['delivery_street_address']) . '<br>' . Html::encode($orders['delivery_city'] . ', ' . $orders['delivery_state']) . '&nbsp;' . Html::encode($orders['delivery_postcode']) . '<br>' . $orders['delivery_country'] . '</div></div>';
                    $cus_column = '<div class="ord-name click_double" data-click-double="' . \Yii::$app->url_manager->create_url(['orders/process-order', 'orders_id' => $orders['orders_id']]) . '">' . (defined('TEXT_WALKIN_ORDER') ? TEXT_WALKIN_ORDER : '') . $orders['admin_firstname'] . ' ' . $orders['admin_lastname'] . ' ' . $customer_delivery_name . '</div>' . $customer_delivery_info;
                    $table_order_row['backendOrdersList\CustomerName'] = $customer_delivery_name;
                    $table_order_row['backendOrdersList\OrderLocation'] = $customer_delivery_info;
                    $table_order_row['backendOrdersList\WalkinOrder'] = (defined('TEXT_WALKIN_ORDER') ? TEXT_WALKIN_ORDER : '') . $orders['admin_firstname'] . ' ' . $orders['admin_lastname'];
                }
                $order_row = [];
                if ($orders['hold_on_date']) {
                    $order_row['DT_RowClass'] = Array_Helper::get_value($order_row, 'DT_RowClass') . ' holdOnOrder';
                    $purchased_date .= '<div class="holdOrderInfo">' . sprintf(LIST_ORDER_HOLD_ON, \common\helpers\Date::date_short($orders['hold_on_date'])) . '</div>';
                    $table_order_row['DT_RowClass'] = $order_row['DT_RowClass'];
                }
                if (isset($orders['isFraud']) && $orders['isFraud']) {
                    $order_row['DT_RowClass'] = Array_Helper::get_value($order_row, 'DT_RowClass') . ' fraudOrder';
                    $table_order_row['DT_RowClass'] = $order_row['DT_RowClass'];
                }
                $batch_checkbox = '<input type="checkbox" class="uniform form-check-input">' . '<input class="cell_identify" type="hidden" value="' . $orders['orders_id'] . '">';
                $order_row[] = $batch_checkbox;
                $table_order_row['backendOrdersList\BatchCheckbox'] = $batch_checkbox;
                $colored_row = '';
                if ($ext = \common\helpers\Acl::check_extension_allowed('OrderMarkers', 'allowed')) {
                    $order_markers = $orders['orderMarkers'];
                    if (isset($order_markers['markers']) && isset($markers[$order_markers['markers']])) {
                        $colored_row = $markers[$order_markers['markers']];
                    }
                    $paint = '<div class="fa-paint-brush" onclick="sendOrderMarker(' . (int) $orders['orders_id'] . ', ' . (int) ($order_markers['markers'] ?? 0) . ')"></div>';
                    if (isset($order_markers['flags']) && isset($flags[$order_markers['flags']])) {
                        $order_markers = '<div class="fa-flag" style="color: ' . $flags[$order_markers['flags']] . ';" onclick="sendOrderFlag(' . (int) $orders['orders_id'] . ', ' . (int) $order_markers['flags'] . ')"></div>' . $paint;
                    } else {
                        $order_markers = '<div class="fa-flag-o" onclick="sendOrderFlag(' . (int) $orders['orders_id'] . ')"></div>' . $paint;
                    }
                    $order_row[] = $order_markers;
                    $table_order_row['backendOrdersList\OrderMarkersCell'] = '<input type="checkbox" class="uniform form-check-input">' . '<input class="cell_identify" type="hidden" value="' . $orders['orders_id'] . '">';
                }
                $customer_column = $cus_column . '<input class="row_colored" type="hidden" value="' . $colored_row . '">';
                $order_row[] = $customer_column;
                $table_order_row['backendOrdersList\CustomerColumnCell'] = $customer_column;
                $order_totals = '<div class="ord-total click_double" data-click-double="' . \Yii::$app->url_manager->create_url(['orders/process-order', 'orders_id' => $orders['orders_id']]) . '">' . $orders['order_total'] . '<div class="ord-total-info"><div class="ord-box-img"></div>' . $order_totals . '</div></div>';
                $order_row[] = $order_totals;
                $table_order_row['backendOrdersList\OrderTotalsCell'] = $order_totals;
                $order_description = '<div class="ord-desc-tab click_double" data-click-double="' . \Yii::$app->url_manager->create_url(['orders/process-order', 'orders_id' => $orders['orders_id']]) . '"><a href="' . \Yii::$app->url_manager->create_url(['orders/process-order', 'orders_id' => $orders['orders_id']]) . '" class="order-inf"><span class="ord-id">' . TEXT_ORDER_NUM . (!empty($orders['order_number']) ? $orders['order_number'] : $orders['orders_id']) . '</span> ' . (!empty($orders['invoice_number']) ? ' <span class="inv-id"><span class="title">' . TEXT_INVOICE . '</span>' . $orders['invoice_number'] . '</span> ' : '') . $department_info . (tep_not_null($orders['payment_method']) ? (SHOW_PRODUCTS_ON_ORDER_LIST === 'False' ? '<br>' : ' ') . TEXT_VIA . ' ' . strip_tags($orders['payment_method']) : '') . (tep_not_null($orders['shipping_method']) ? ' ' . TEXT_DELIVERED_BY . ' ' . strip_tags($orders['shipping_method']) : '') . '</a>' . (SHOW_PRODUCTS_ON_ORDER_LIST !== 'False' ? $p_list : '') . '</div>';
                $order_row[] = $order_description;
                $table_order_row['backendOrdersList\OrderDescriptionCell'] = $order_description;
                $order_purchase = '<div class="ord-date-purch click_double" data-click-double="' . \Yii::$app->url_manager->create_url(['orders/process-order', 'orders_id' => $orders['orders_id']]) . '">' . $purchased_date . $delivery_info;
                $order_row[] = $order_purchase;
                $table_order_row['backendOrdersList\OrderPurchaseCell'] = $order_purchase;
                $table_order_row['backendOrdersList\OrderPurchase'] = $purchased_date;
                $order_status = '<div class="ord-status click_double" data-click-double="' . \Yii::$app->url_manager->create_url(['orders/process-order', 'orders_id' => $orders['orders_id']]) . '"><span><i style="background: ' . $orders['orders_status_groups_color'] . ';"></i>' . $orders['orders_status_groups_name'] . ',</span> <div data-status="' . (int) $orders['orders_status'] . '">' . $orders['orders_status_name'] . '</div></div>';
                $order_row[] = $order_status;
                $table_order_row['backendOrdersList\OrderStatusCell'] = $order_status;
                if ($ext = \common\helpers\Acl::check_extension('Neighbour', 'allowed')) {
                    if ($ext::allowed()) {
                        $neighbour = $orders['to_neighbour'] ? '<div class=" ord-date-purch-delivery ord-date-purch-delivery-check">' : '';
                        $order_row[] = $neighbour;
                        $table_order_row['backendOrdersList\NeighbourCell'] = $neighbour;
                    }
                }
                $table_order_row['backendOrdersList\OrderId'] = $orders['orders_id'];
                $table_order_row['backendOrdersList\OrderProducts'] = $p_list;
                $table_order_row['backendOrdersList\Platform'] = \common\classes\platform::name($orders['platform_id']);
                $table_order_row['backendOrdersList\PaymentMethod'] = $orders['payment_method'] ?? '';
                $table_order_row['backendOrdersList\ShippingMethod'] = $orders['shipping_method'] ?? '';
                if ($page_name) {
                    $response_list[] = \frontend\design\boxes\Table_Row::row($table_order_row, $page_name);
                } else {
                    $response_list[] = $order_row;
                }
            }
        }
        $response = ['draw' => $draw, 'recordsTotal' => $orders_query_numrows, 'recordsFiltered' => $orders_query_numrows, 'stats' => $stats, 'data' => $response_list];
        echo json_encode($response, JSON_PARTIAL_OUTPUT_ON_ERROR);
        //die();
    }
    public static function crop_str($str, $length)
    {
        if (mb_strlen($str) > $length) {
            $str = strip_tags($str);
            if (mb_strlen($str) > $length) {
                $str = mb_substr($str, 0, $length) . '...';
            }
        }
        return $str;
    }
    public function action_orderactions()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/orders');
        $this->layout = false;
        $orders_id = Yii::$app->request->post('orders_id');
        /*
                $orders_query = tep_db_query("select o.customers_id, o.settlement_date, o.approval_code, o.last_xml_export, o.transaction_id, o.orders_id, o.platform_id, o.customers_name, o.payment_method, o.date_purchased, o.last_modified, o.currency, o.language_id, o.currency_value, s.orders_status_name, ot.text as order_total from " . TABLE_ORDERS_STATUS . " s, " . TABLE_ORDERS . " o left join " . TABLE_ORDERS_TOTAL . " ot on (o.orders_id = ot.orders_id) where o.orders_id = '" . (int) $orders_id . "' and s.orders_status_id=o.orders_status and s.language_id='" . (int)$languages_id . "'");
        
                $orders = tep_db_fetch_array($orders_query);
        */
        $orders = Orders::find()->alias('o')->and_where(['orders_id' => (int) $orders_id])->as_array()->one();
        if (empty($orders)) {
            die('Please select order.');
        }
        $_pl = Array_Helper::map(platform::get_list(false), 'id', 'id');
        if (!in_array($orders['platform_id'], $_pl)) {
            $orders['platform_id'] = platform::default_id();
        }
        $added_pages = \common\models\Themes_Settings::find()->alias('ts')->inner_join(TABLE_THEMES . ' t', 't.theme_name=ts.theme_name')->inner_join(TABLE_PLATFORMS_TO_THEMES . ' p2t', 't.id=p2t.theme_id')->select(['setting_name', 'setting_value', 'ts.id'])->where(['p2t.platform_id' => $orders['platform_id'], 'setting_group' => 'added_page', 'setting_name' => ['packingslip', 'invoice']])->order_by('setting_name')->as_array()->all();
        $added_pages = Array_Helper::map($added_pages, 'id', 'setting_value', 'setting_name');
        $added_pages['invoice'] = $added_pages['invoice'] ?? [];
        $added_pages['packingslip'] = $added_pages['packingslip'] ?? [];
        $can_anonimize = $tmp = false;
        if (defined('GDPR_CUSTOMER_DELETE_OPEN_ORDER_STATUSES') && !empty(trim(GDPR_CUSTOMER_DELETE_OPEN_ORDER_STATUSES))) {
            $tmp = array_map('intval', explode(',', GDPR_CUSTOMER_DELETE_OPEN_ORDER_STATUSES));
        }
        if ($orders['customers_id'] != \common\helpers\Customer::find_create_anonymous_customer() && (!is_array($tmp) || !in_array($orders['orders_status'], $tmp))) {
            $can_anonimize = true;
        }
        $o_info = new \Object_Info($orders);
        return $this->render('actions', ['oInfo' => $o_info, 'addedPages' => $added_pages, 'canAnonimize' => $can_anonimize]);
    }
    public function action_order_reassign()
    {
        \common\helpers\Translation::init('admin/orders');
        $this->layout = false;
        $orders_id = Yii::$app->request->post('orders_id');
        $orders_query = tep_db_query('select o.settlement_date, o.approval_code, o.last_xml_export, o.transaction_id, o.orders_id, o.customers_name, o.payment_method, o.date_purchased, o.last_modified, o.currency, o.currency_value, s.orders_status_name, ot.text as order_total from ' . TABLE_ORDERS_STATUS . ' s, ' . TABLE_ORDERS . ' o left join ' . TABLE_ORDERS_TOTAL . " ot on (o.orders_id = ot.orders_id) where o.orders_id = '" . (int) $orders_id . "'");
        $orders = tep_db_fetch_array($orders_query);
        if (!is_array($orders)) {
            die('Wrong order data.');
        }
        $o_info = new \Object_Info($orders);
        return $this->render('reassign', ['oInfo' => $o_info]);
    }
    public function action_anonimize_order()
    {
        $orders_id = Yii::$app->request->get('orders_id');
        $this->layout = false;
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        \common\helpers\Order::anonimize_order($orders_id);
        return ['status' => 'ok'];
    }
    public function action_confirmed_order_reassign()
    {
        $customers_id = Yii::$app->request->post('customers_id');
        $orders_id = Yii::$app->request->post('orders_id');
        $customers_query = tep_db_query('select * from ' . TABLE_CUSTOMERS . " where customers_id = '" . (int) $customers_id . "'");
        $customers = tep_db_fetch_array($customers_query);
        if (is_array($customers) && $orders_id > 0) {
            tep_db_query('update ' . TABLE_ORDERS . " set customers_id = '" . (int) $customers_id . "', customers_name = '" . tep_db_input($customers['customers_firstname'] . ' ' . $customers['customers_lastname']) . "', customers_firstname = '" . tep_db_input($customers['customers_firstname']) . "', customers_lastname = '" . tep_db_input($customers['customers_lastname']) . "', customers_email_address = '" . tep_db_input($customers['customers_email_address']) . "' where orders_id = '" . (int) $orders_id . "';");
        }
    }
    public function action_process_order()
    {
        global $login_id;
        defined('THEME_NAME') or define('THEME_NAME', \common\classes\design::page_name(BACKEND_THEME_NAME));
        \common\helpers\Translation::init('admin/orders');
        $this->selected_menu = ['customers', 'orders'];
        if (Yii::$app->request->is_post) {
            $o_id = Yii::$app->request->post('orders_id');
        } else {
            $o_id = Yii::$app->request->get('orders_id');
        }
        $manager = \common\services\Order_Manager::load_manager(new \common\classes\shopping_cart());
        $manager->cleanup_temporary_guests();
        $manager->clear_order_instance();
        $order = $manager->get_order_instance_with_id('\common\classes\Order', $o_id);
        $o_query = $order->get_ar_model()->where(['or', ['orders_id' => $o_id], ['order_number' => $o_id]]);
        if (false === \common\helpers\Acl::rule(['SUPERUSER'])) {
            global $login_id;
            $platforms = \common\models\Admin_Platforms::find()->select('platform_id')->where(['admin_id' => $login_id])->as_array()->column();
            $platforms[] = 0;
            $o_query->and_where(['platform_id' => $platforms]);
        }
        foreach (\common\helpers\Hooks::get_list('orders/process-order/check-query') as $filename) {
            include $filename;
        }
        if (!$o_query->exists()) {
            $message_stack = \Yii::$container->get('message_stack');
            $message_stack->add_session(TEXT_ADMIN_ORDER_NOT_FOUND_ASSIGN_PLATFORMS, 'header', 'warning');
            return $this->redirect(\Yii::$app->url_manager->create_url(['orders/', 'by' => 'oID', 'search' => $o_id]));
        } else {
            $o_model = $o_query->one();
            if ($o_id != $o_model->orders_id) {
                $o_id = $o_model->orders_id;
                $order = $manager->get_order_instance_with_id('\common\classes\Order', $o_id);
            }
            if ($o_model->platform_id) {
                $selected_platform_id = $o_model->platform_id;
            } else {
                $selected_platform_id = \common\classes\platform::first_id();
            }
        }
        $manager->set('platform_id', $selected_platform_id);
        $manager->set_modules_visibility(['admin']);
        $manager->set_render_path('\backend\design\orders\\');
        Yii::$app->get('platform')->config($selected_platform_id)->constant_up();
        Yii::$app->get('platform')->config(\common\classes\platform::default_id())->constant_up();
        $action = Yii::$app->request->get('action', '');
        $dropshippingcode = Yii::$app->request->get('dropshipping', '');
        if ($action == 'd-execute' && !empty($dropshippingcode)) {
            $dropshipping = new \common\classes\dropshipping();
            $dropshipping->process($dropshippingcode, Yii::$app->request->get_query_params());
            return $this->redirect(\Yii::$app->url_manager->create_url(['orders/process-order', 'orders_id' => (int) $o_id]));
        }
        $message_stack = \Yii::$container->get('message_stack');
        $message_stack->init_flash();
        $query_params = Yii::$app->request->get_query_params();
        unset($query_params['action']);
        $access_levels_id = \common\models\Admin::find_one(['admin_id' => (int) $login_id])->access_levels_id;
        $page_name = \common\models\Admin_Templates::find_one(['access_levels_id' => $access_levels_id, 'page' => 'backendOrder'])->template;
        if (Yii::$app->request->is_ajax) {
            echo json_encode(['content' => $this->render_ajax('process-order', ['queryParams' => $query_params, 'manager' => $manager, 'order' => $order, 'pageName' => $page_name]), 'message' => $message_stack->as_array('header')]);
            exit;
        }
        $_session = Yii::$app->session;
        $filter = '';
        if ($_session->has('filter')) {
            $filter = $_session->get('filter');
        }
        $pagin_model = \common\models\Orders::find()->select('o.orders_id')->from(TABLE_ORDERS . ' o USE INDEX (PRIMARY) ');
        if ($_session->has('search_condition')) {
            $pagin_model->and_where($_session->get('search_condition'));
        }
        $_orders_products_allocate_joined = false;
        if ($filter) {
            $pagin_model->left_join(TABLE_ORDERS_PRODUCTS . ' op', 'o.orders_id = op.orders_id')->left_join(TABLE_ORDERS_STATUS . ' s', 'o.orders_status=s.orders_status_id')->left_join(TABLE_ORDERS_STATUS_GROUPS . ' sg', 's.orders_status_groups_id = sg.orders_status_groups_id');
            if (!\common\helpers\Acl::rule(['BOX_HEADING_CUSTOMERS', 'BOX_CUSTOMERS_ORDERS', 'RULE_ALLOW_WAREHOUSES']) || !\common\helpers\Acl::rule(['BOX_HEADING_CUSTOMERS', 'BOX_CUSTOMERS_ORDERS', 'RULE_ALLOW_SUPPLIERS'])) {
                $pagin_model->left_join('orders_products_allocate opa', 'opa.orders_id = o.orders_id');
                $_orders_products_allocate_joined = true;
            }
            $pagin_model->left_join(TABLE_ORDERS_STATUS_HISTORY . ' osh', 'o.orders_id = osh.orders_id');
        }
        if ($filter && \common\helpers\Extensions::is_allowed('Handlers')) {
            $pagin_model->left_join('handlers_products hp', 'hp.products_id = op.products_id');
        }
        if ($filter && strpos($pagin_model->and_where($filter)->create_command()->get_raw_sql(), 'wpb') !== false) {
            if (!$_orders_products_allocate_joined) {
                $pagin_model->left_join('orders_products_allocate opa', 'opa.orders_id = o.orders_id');
                $_orders_products_allocate_joined = true;
            }
            $pagin_model->left_join('warehouses_products_batches wpb', 'wpb.batch_id = opa.batch_id');
        }
        foreach (\common\helpers\Hooks::get_list('orders/process-order/before-next-prev-query') as $filename) {
            include $filename;
        }
        $order_next = $pagin_model->where("o.orders_id > '" . (int) $order->order_id . "'")->and_where($filter)->order_by('orders_id ASC')->limit(1)->as_array()->one();
        $order_prev = $pagin_model->where("o.orders_id < '" . (int) $order->order_id . "'")->and_where($filter)->order_by('orders_id DESC')->limit(1)->as_array()->one();
        $this->view->order_next = isset($order_next['orders_id']) ? $order_next['orders_id'] : 0;
        $this->view->order_prev = isset($order_prev['orders_id']) ? $order_prev['orders_id'] : 0;
        $order_language = \common\classes\language::get_code($order->info['language_id']);
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('orders/process-order?orders_id=' . $order->order_id), 'title' => TEXT_PROCESS_ORDER . ' #' . (!empty($order->info['order_number']) ? '<span class="order-number">' . $order->info['order_number'] . '</span> ' : '') . $order->order_id . ' <div class="head-or-time">' . TEXT_DATE_AND_TIME . '' . $order->info['date_purchased'] . '</div><div class="order-platform">' . TABLE_HEADING_PLATFORM . ':' . \common\classes\platform::name($order->info['platform_id']) . '</div>'];
        $_pl = Array_Helper::map(platform::get_list(false), 'id', 'id');
        if (!in_array($order->info['platform_id'], $_pl)) {
            $theme_platform_id = platform::default_id();
        } else {
            $theme_platform_id = $order->info['platform_id'];
        }
        $added_pages = \common\models\Themes_Settings::find()->alias('ts')->inner_join(TABLE_THEMES . ' t', 't.theme_name=ts.theme_name')->inner_join(TABLE_PLATFORMS_TO_THEMES . ' p2t', 't.id=p2t.theme_id')->select(['setting_name', 'setting_value', 'ts.id'])->where(['p2t.platform_id' => $theme_platform_id, 'setting_group' => 'added_page', 'setting_name' => ['packingslip', 'invoice']])->order_by('setting_name')->as_array()->all();
        $added_pages = Array_Helper::map($added_pages, 'id', 'setting_value', 'setting_name');
        $added_pages['invoice'] = $added_pages['invoice'] ?? [];
        // remove ticket button. define('ENABLE_ORDER_TICKET', 1) to enable
        if (is_array($added_pages['invoice']) && !defined('ENABLE_ORDER_TICKET') && ($key = array_search('ticket', $added_pages['invoice'])) !== false) {
            unset($added_pages['invoice'][$key]);
        }
        $added_pages['packingslip'] = $added_pages['packingslip'] ?? [];
        if (defined('CREDIT_NOTE_AVAILABLE') && CREDIT_NOTE_AVAILABLE) {
            $splitter = $manager->get_order_splitter();
            $cn1 = $splitter->get_instances_from_splinters($o_id, $splitter::STATUS_RETURNING);
            $cn2 = $splitter->get_instances_from_splinters($o_id, $splitter::STATUS_RETURNED);
            if (!empty($cn1) || !empty($cn2)) {
                $added_pages['credit_note'] = [];
            }
        }
        $fraud_view = false;
        if (\common\helpers\Acl::check_extension_allowed('FraudAddress', 'allowed')) {
            $fraud_view = \common\extensions\Fraud_Address\Fraud_Address::fraud_view($order);
        }
        global $navigation;
        if (sizeof($navigation->snapshot) > 0) {
            $added_pages['backUrl'] = Yii::$app->url_manager->create_url(array_merge([$navigation->snapshot['page']], $navigation->snapshot['get']));
        } else {
            $added_pages['backUrl'] = Yii::$app->url_manager->create_url(['orders']);
        }
        return $this->render('update', [
            'queryParams' => $query_params,
            'messsages' => $message_stack->messages,
            'manager' => $manager,
            'order' => $order,
            'customer_id' => (int) $order->customer['customer_id'],
            'qr_img_url' => HTTP_CATALOG_SERVER . DIR_WS_CATALOG . 'account/order-qrcode?oID=' . (int) $order->order_id . '&cID=' . (int) $order->customer['customer_id'] . '&tracking=1',
            //'order_platform_id' => $order->info['platform_id'], //using undefined
            //'order_language' => $order_language, //using undefined
            'ref_id' => $order->get_reference_id(),
            'fraudView' => $fraud_view,
            'dropshipping' => $dropshipping ?? null,
            'addedPages' => $added_pages,
            'pageName' => $page_name,
        ]);
    }
    public function action_ordersubmit()
    {
        global $login_id;
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/orders');
        $admin_id = $login_id;
        $this->layout = false;
        /** @var \common\classes\Currencies $currencies*/
        $currencies = Yii::$container->get('currencies');
        $orders_statuses = [];
        $orders_status_array = [];
        $orders_status_query = tep_db_query('select orders_status_id, orders_status_name from ' . TABLE_ORDERS_STATUS . " where language_id = '" . (int) $languages_id . "'");
        while ($orders_status = tep_db_fetch_array($orders_status_query)) {
            $orders_statuses[] = ['id' => $orders_status['orders_status_id'], 'text' => $orders_status['orders_status_name']];
            $orders_status_array[$orders_status['orders_status_id']] = $orders_status['orders_status_name'];
        }
        $o_id = Yii::$app->request->post('orders_id');
        $manager = \common\services\Order_Manager::load_manager(new \common\classes\shopping_cart());
        $manager->set_modules_visibility(['admin']);
        $order = $manager->get_order_instance_with_id('\common\classes\Order', $o_id);
        /**
         * @var \common\classes\Order $order
         */
        if (!$order->get_details()) {
            die('Wrong order data.');
        }
        $check_status = $order->get_details();
        $order_updated = false;
        /**
         * @var $platform_config platform_config
         */
        $platform_config = Yii::$app->get('platform')->config($check_status['platform_id']);
        Yii::$app->get('platform')->config($check_status['platform_id'])->constant_up();
        $status = tep_db_prepare_input($_POST['status']);
        $comments = tep_db_prepare_input($_POST['comments']);
        $update_paid_amount_flag = (int) Yii::$app->request->post('use_update_amount', 0);
        $update_paid_amount = (float) Yii::$app->request->post('update_paid_amount', 0);
        $t_status = (int) Yii::$app->request->post('t_status', \common\helpers\Order_Payment::OPYS_PENDING);
        $t_number = Yii::$app->request->post('transaction_id', '');
        if ($update_paid_amount_flag && is_numeric($update_paid_amount) && $update_paid_amount) {
            $order->info['comments'] = '';
            $totals = \yii\helpers\Array_Helper::map($order->totals, 'code', 'value_inc_tax');
            if (!empty($totals['ot_due']) && !empty($totals['ot_paid'])) {
                $value = (float) $update_paid_amount * $currencies->get_market_price_rate($order->info['currency'], DEFAULT_CURRENCY);
                $paid_prefix = \Yii::$app->request->post('paid_prefix', '+');
                $value = $paid_prefix == '-' ? -$value : $value;
                ////
                if (!empty($t_number)) {
                    $payment = $manager->get_payment_collection($order->info['payment_class'])->get($order->info['payment_class'], true);
                    $tm = $manager->get_transaction_manager($payment ? $payment : null);
                    //offline methods are added (always as payment->code != $order->info['payment_class'] (offline != offline_NN)
                    $res = $tm->update_payment_transaction($t_number, [
                        'fulljson' => '',
                        'status_code' => $t_status,
                        'status' => defined('TEXT_STATUS_OPYS_SUCCESSFUL') ? TEXT_STATUS_OPYS_SUCCESSFUL : '',
                        'amount' => (float) $value,
                        //'comments'  => $value . ' ' . $order->getOrderNumber(),
                        'date' => date('Y-m-d H:i:s'),
                        'payment_class' => $order->info['payment_class'],
                        'payment_method' => $order->info['payment_method'],
                        'parent_transaction_id' => 0,
                        'orders_id' => 0,
                    ]);
                }
                ////
                $manager->load_cart(new \common\classes\shopping_cart());
                $cart = $manager->get_cart();
                $comment = $comments . ' ' . TEXT_PAID_AMOUNT . ' ' . $paid_prefix . $currencies->format($update_paid_amount, true, $order->info['currency'], 1);
                $value += $totals['ot_paid'];
                $cart->set_total_paid($value, '+', $comment);
                $manager->get_total_collection()->process(['ot_paid', 'ot_due']);
                $order->is_paid_updated = true;
                if ($order->maintain_splittering()) {
                    $manager->get_order_splitter()->make_splinters($order->order_id);
                }
                $order->save_details();
                $order_updated = true;
            }
        }
        $order_stock_updated_flag = false;
        $update_order_stock = Yii::$app->request->post('update_order_stock', 0);
        if ($update_order_stock && !\common\helpers\Order::is_stock_updated((int) $o_id)) {
            $_get_order_products_r = tep_db_query('select IF(LENGTH(uprid)>0,uprid,products_id) AS uprid, products_quantity ' . 'from ' . TABLE_ORDERS_PRODUCTS . ' ' . "where orders_id='" . (int) $o_id . "'");
            while ($ordered_uprid = tep_db_fetch_array($_get_order_products_r)) {
                \common\helpers\Product::update_stock($ordered_uprid['uprid'], 0, $ordered_uprid['products_quantity']);
            }
            tep_db_query('UPDATE ' . TABLE_ORDERS . " SET stock_updated=1 WHERE orders_id='" . (int) $o_id . "'");
            $order_stock_updated_flag = true;
        }
        // BOF: WebMakers.com Added: Downloads Controller
        // always update date and time on order_status
        // original        if ( ($check_status['orders_status'] != $status) || tep_not_null($comments)) {
        $order_comment = Yii::$app->request->post('order_comment');
        if (!empty($order_comment)) {
            $visible = Yii::$app->request->post('visible_to', '');
            $visibility = [];
            if ($visible) {
                $vis = explode('_', $visible);
                if ($vis) {
                    $visibility = [mb_substr($vis[0], 0, 1) => $vis[1]];
                }
            }
            $o_comment = \common\models\Orders_Comments::create($order->order_id, $login_id, $order_comment, 0, $visibility);
            $order_updated = true;
        }
        $invoice_comment = Yii::$app->request->post('invoice_comment');
        $i_comment = \common\models\Orders_Comments::find_invoice_comment($order->order_id);
        if (!empty($invoice_comment) || $i_comment) {
            if ($i_comment) {
                $i_comment->set_attribute('comments', $invoice_comment);
            } else {
                $i_comment = \common\models\Orders_Comments::create($order->order_id, $login_id, $invoice_comment, 1);
            }
            $order_updated = true;
        }
        $messages = [];
        $smscomments = trim(isset($_POST['smscomments']) ? $_POST['smscomments'] : '');
        if ($check_status['orders_status'] != $status || $comments != '' || $smscomments != '' || $status == DOWNLOADS_ORDERS_STATUS_UPDATED_VALUE) {
            /*tep_db_query("update " . TABLE_ORDERS . " set orders_status = '" . tep_db_input($status) . "', last_modified = now() where orders_id = '" . (int) $oID . "'");
              $check_status_query2 = tep_db_query("select customers_name, customers_email_address, orders_status, date_purchased from " . TABLE_ORDERS . " where orders_id = '" . (int) $oID . "'");
              $check_status2 = tep_db_fetch_array($check_status_query2);
              if ($check_status2['orders_status'] == DOWNLOADS_ORDERS_STATUS_UPDATED_VALUE) {*/
            if ($status == DOWNLOADS_ORDERS_STATUS_UPDATED_VALUE) {
                tep_db_query('update ' . TABLE_ORDERS_PRODUCTS_DOWNLOAD . " set download_maxdays = '" . tep_db_input(\common\helpers\Configuration::get_configuration_key_value('DOWNLOAD_MAX_DAYS')) . "', download_count = '" . tep_db_input(\common\helpers\Configuration::get_configuration_key_value('DOWNLOAD_MAX_COUNT')) . "' where orders_id = '" . (int) $o_id . "'");
            }
            // EOF: WebMakers.com Added: Downloads Controller
            $email_headers = '';
            $customer_notified = '0';
            if (isset($_POST['notify']) && $_POST['notify'] == '1') {
                $notify_comments = '';
                if (isset($_POST['notify_comments']) && $_POST['notify_comments'] == '1' && $comments) {
                    $EMAIL_TEXT_COMMENTS_UPDATE = Translation::get_translation_value('EMAIL_TEXT_COMMENTS_UPDATE', 'admin/main', $order->info['language_id']);
                    $notify_comments = trim(sprintf($EMAIL_TEXT_COMMENTS_UPDATE, $comments)) . "\n\n";
                    //  $notify_comments = trim(sprintf(EMAIL_TEXT_COMMENTS_UPDATE, $comments)) . "\n\n";
                }
                $order->info['order_status'] = $status;
                $customer_notified = $order->send_status_notify($notify_comments, []);
                if ($sms_service = \common\helpers\Acl::check_extension_allowed('SmsService', 'allowed')) {
                    $order_status_record = \common\models\Orders_Status::find()->where(['orders_status_id' => $status, 'language_id' => $languages_id])->as_array(true)->one();
                    if (is_array($order_status_record) and isset($order_status_record['orders_status_template_sms'])) {
                        $parameter_array = [];
                        $sms_message = \common\helpers\Mail::get_sms_template_parsed($order_status_record['orders_status_template_sms'], $parameter_array);
                        $customer_phone = '';
                        $country_record = \common\models\Countries::find_one($order->customer['country_id']);
                        if ($country_record instanceof \common\models\Countries) {
                            $customer_phone = $country_record->check_phone($order->customer['telephone']);
                        }
                        unset($country_record);
                        if ($customer_phone != '' and $sms_message != '') {
                            $parameter_array = ['phone' => $customer_phone, 'message' => $sms_message, 'sender' => null];
                            $is_sent = true;
                            //false;
                            $platform_configuration_record = \common\models\Platforms_Configuration::find_one(['configuration_key' => 'PLATFORM_SMS_SERVICE', 'platform_id' => $check_status['platform_id']]);
                            if ($platform_configuration_record instanceof \common\models\Platforms_Configuration) {
                                if (trim($platform_configuration_record->configuration_value) != '') {
                                    if ($sms_service::send_sms($platform_configuration_record->configuration_value, $parameter_array) != false) {
                                        $smscomments = $sms_message;
                                        $customer_notified = '1';
                                        $is_sent = true;
                                    }
                                }
                            }
                            if ($is_sent != true) {
                                if (defined('ADMIN_TWO_STEP_AUTH_SERVICE_SMS') and ADMIN_TWO_STEP_AUTH_SERVICE_SMS != '') {
                                    if ($sms_service::send_sms(ADMIN_TWO_STEP_AUTH_SERVICE_SMS, $parameter_array) != false) {
                                        $smscomments = $sms_message;
                                        $customer_notified = '1';
                                        $is_sent = true;
                                    }
                                }
                            }
                            if ($is_sent != true and $check_status['platform_id'] != \common\classes\platform::default_id()) {
                                $platform_configuration_record = \common\models\Platforms_Configuration::find_one(['configuration_key' => 'PLATFORM_SMS_SERVICE', 'platform_id' => \common\classes\platform::default_id()]);
                                if ($platform_configuration_record instanceof \common\models\Platforms_Configuration) {
                                    if (trim($platform_configuration_record->configuration_value) != '') {
                                        if ($sms_service::send_sms($platform_configuration_record->configuration_value, $parameter_array) != false) {
                                            $smscomments = $sms_message;
                                            $customer_notified = '1';
                                            $is_sent = true;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            /*if (!$order_updated){
                  tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, array(
                      'orders_id' => (int) $oID,
                      'orders_status_id' => (int) $status,
                      'date_added' => 'now()',
                      'customer_notified' => $customer_notified,
                      'comments' => $comments,
                      'admin_id' => $admin_id,
                  ));
              }*/
            $is_alternative_behaviour = false;
            $order_status_record = \common\models\Orders_Status::find_one(['orders_status_id' => (int) $status]);
            if ($order_status_record->order_evaluation_state_id == \common\helpers\Order::OES_PENDING) {
                $is_alternative_behaviour = (int) Yii::$app->request->post('evaluation_state_reset_cancel', 0);
            } elseif ($order_status_record->order_evaluation_state_id == \common\helpers\Order::OES_PROCESSING) {
            } elseif ($order_status_record->order_evaluation_state_id == \common\helpers\Order::OES_CANCELLED) {
                $is_alternative_behaviour = (int) Yii::$app->request->post('evaluation_state_restock', 0);
            } elseif ($order_status_record->order_evaluation_state_id == \common\helpers\Order::OES_DISPATCHED) {
                $is_alternative_behaviour = (int) Yii::$app->request->post('evaluation_state_force', 0);
            } elseif ($order_status_record->order_evaluation_state_id == \common\helpers\Order::OES_DELIVERED) {
                $is_alternative_behaviour = (int) Yii::$app->request->post('evaluation_state_force', 0);
            }
            unset($order_status_record);
            \common\helpers\Order::set_status($o_id, $status, ['comments' => $comments, 'smscomments' => $smscomments, 'customer_notified' => $customer_notified], false, $is_alternative_behaviour);
            if ($trustpilot_class = Acl::check_extension_allowed('Trustpilot', 'allowed')) {
                $trustpilot_class::on_order_update_email((int) $o_id, '');
            }
            if (Acl::check_extension_allowed('SMS', 'showOnOrderPage') && $sms = Acl::check_extension_allowed('SMS', 'allowed')) {
                $commentid = tep_db_insert_id();
                $response = $sms::send_sms($o_id, $commentid);
                if (is_array($response) && count($response)) {
                    $messages[] = ['message' => $response['message'], 'messageType' => $response['messageType']];
                }
            }
            if (method_exists('\common\helpers\Coupon', 'credit_order_check_state')) {
                \common\helpers\Coupon::credit_order_check_state((int) $o_id);
            }
            $order_updated = true;
        }
        if ($order_updated == true || $order_stock_updated_flag) {
            $message_type = 'success';
            if ($order_stock_updated_flag) {
                $message = '<p>' . TEXT_MESSAGE_ORDER_STOCK_UPDATED . '</p>';
            }
            if ($order_updated) {
                $message = '<p>' . SUCCESS_ORDER_UPDATED . '</p>';
            }
            $messages[] = ['messageType' => 'success', 'message' => $message];
        } else {
            $message = '<p>' . WARNING_ORDER_NOT_UPDATED . '</p>';
            $messages[] = ['messageType' => 'warning', 'message' => $message];
        }
        foreach (\common\helpers\Hooks::get_list('orders/process-order') as $filename) {
            include $filename;
        }
        $message_stack = \Yii::$container->get('message_stack');
        if (is_array($messages) && count($messages)) {
            foreach ($messages as $message) {
                $message_stack->add($message['message'], 'header', $message['messageType']);
            }
        }
        return $this->action_process_order();
    }
    public function action_reset_admin()
    {
        $basket_id = Yii::$app->request->post('basket_id');
        $customer_id = Yii::$app->request->post('customer_id');
        $orders_id = Yii::$app->request->post('orders_id', 0);
        $admin = new Admin_Carts();
        if ($basket_id && $customer_id) {
            $admin->relocate_cart($basket_id, $customer_id);
        }
        if ($orders_id) {
            $reload = Url::to(['order-edit', 'orders_id' => $orders_id]);
        } else {
            $reload = Url::to(['order-edit']);
        }
        echo json_encode(['reload' => $reload]);
        exit;
    }
    public function action_reset_cart()
    {
        $id = Yii::$app->request->get('id');
        $admin = new Admin_Carts();
        $admin->set_last_virtual_id($id);
        return $this->redirect('order-edit');
    }
    public function action_deletecart()
    {
        $id = Yii::$app->request->post('deleteCart');
        $admin = new Admin_Carts();
        $_cb = explode('-', $id);
        if ($admin->delete_cart_by_bc($_cb[0], $_cb[1])) {
            $ids = $admin->get_virtual_cart_i_ds();
            if ($ids) {
                $_last = $admin->get_last_virtual_id();
                if (!in_array($_last, $ids)) {
                    // last was deleted
                    echo json_encode(['goto' => Url::to(['orders/order-edit', 'currentCart' => $ids[0]])]);
                    exit;
                }
            } else {
                echo json_encode(['goto' => Url::to(['orders/'])]);
                exit;
            }
        }
        echo json_encode(['reload' => true]);
        exit;
    }
    public function action_orderdelete()
    {
        $this->layout = false;
        $orders_id = Yii::$app->request->post('orders_id');
        $admin = new Admin_Carts();
        $admin->delete_cart_by_order($orders_id);
        \common\helpers\Order::remove_order($orders_id, Yii::$app->request->post('restock'), 'Manually deleted');
    }
    public function action_confirmorderdelete()
    {
        \common\helpers\Translation::init('admin/orders');
        $this->layout = false;
        $orders_id = Yii::$app->request->post('orders_id');
        $orders_query = tep_db_query('select o.settlement_date, o.approval_code, o.last_xml_export, o.transaction_id, o.orders_id, o.customers_name, o.payment_method, o.date_purchased, o.last_modified, o.currency, o.currency_value, s.orders_status_name, ot.text as order_total from ' . TABLE_ORDERS_STATUS . ' s, ' . TABLE_ORDERS . ' o left join ' . TABLE_ORDERS_TOTAL . " ot on (o.orders_id = ot.orders_id) where o.orders_id = '" . (int) $orders_id . "'");
        $orders = tep_db_fetch_array($orders_query);
        if (!is_array($orders)) {
            die('Wrong order data.');
        }
        $o_info = new \Object_Info($orders);
        echo tep_draw_form('orders', FILENAME_ORDERS, \common\helpers\Output::get_all_get_params(['action']) . 'action=deleteconfirm', 'post', 'id="orders_edit" onSubmit="return deleteOrder();"');
        echo '<div class="or_box_head">' . TEXT_INFO_HEADING_DELETE_ORDER . '</div>';
        echo '<div class="col_desc">' . TEXT_INFO_DELETE_INTRO . '</div>';
        echo '<div class="row_or_wrapp">';
        echo '<div class="row_or"><div>' . TEXT_INFO_DELETE_DATA . ':</div><div>' . $o_info->customers_name . '</div></div>';
        echo '<div class="row_or"><div>' . TEXT_INFO_DELETE_DATA_OID . ':</div><div>' . $o_info->orders_id . '</div></div>';
        echo '</div>';
        $order_stock_updated = \common\helpers\Order::is_stock_updated($o_info->orders_id);
        echo '<div class="col_desc_check">' . tep_draw_checkbox_field('restock', 'on', $order_stock_updated, '', $order_stock_updated ? '' : 'disabled="disabled" readonly="readonly"') . '<span>' . TEXT_INFO_RESTOCK_PRODUCT_QUANTITY . '</span>' . '</div>';
        ?>
        <div class="btn-toolbar btn-toolbar-order">
            <?php 
        echo '<button class="btn btn-delete btn-no-margin">' . IMAGE_DELETE . '</button><input type="button" class="btn btn-cancel" value="' . IMAGE_CANCEL . '" onClick="return cancelStatement()">';
        echo tep_draw_hidden_field('orders_id', $o_info->orders_id);
        ?>
        </div>
        </form>
        <?php 
    }
    public function load_platform_details($entry, $platform = 0)
    {
        $entry->platforms = platform::get_list(false);
        if (!$platform) {
            $platform = platform::default_id();
        }
        $entry->default_platform = $platform;
        $platform_config = new platform_config($entry->default_platform);
        //currency
        $platform_currencies = $platform_config->get_allowed_currencies();
        if ($platform_currencies) {
            $_tmp = [];
            foreach ($platform_currencies as $pc) {
                $_tmp[] = ['id' => $pc, 'text' => $pc];
            }
            $entry->platform_currencies = $_tmp;
        } else {
            $entry->platform_currencies = [['id' => DEFAULT_CURRENCY, 'text' => DEFAULT_CURRENCY]];
        }
        if ($this->view->convert && isset($_GET['basket_id'])) {
            $params = tep_db_fetch_array(tep_db_query('select currency, language_id from ' . TABLE_CUSTOMERS_BASKET . " where customers_id = '" . (int) $entry->customer_id . "' and basket_id = '" . (int) $_GET['basket_id'] . "'"));
        }
        if ($this->view->convert && isset($params['currency']) && tep_not_null($params['currency'])) {
            $entry->defualt_platform_currency = $params['currency'];
        } elseif ($c = $platform_config->get_default_currency()) {
            $entry->defualt_platform_currency = $c;
        } else {
            $entry->defualt_platform_currency = DEFAULT_CURRENCY;
        }
        //language
        global $lng;
        $platform_languages = $platform_config->get_allowed_languages();
        if ($platform_languages) {
            $_tmp = [];
            foreach ($platform_languages as $pl) {
                $_tmp[] = ['id' => $lng->catalog_languages[$pl]['id'], 'text' => $lng->catalog_languages[$pl]['name']];
            }
            $entry->platform_languages = $_tmp;
        } else {
            $entry->platform_languages = [['id' => $lng->catalog_languages[DEFAULT_LANGUAGE]['id'], 'text' => $lng->catalog_languages[DEFAULT_LANGUAGE]['name']]];
        }
        if ($this->view->convert && isset($params['language_id']) && $params['language_id'] > 0) {
            $entry->defualt_platform_language = $params['language_id'];
        } elseif ($c = $platform_config->get_default_language()) {
            $entry->defualt_platform_language = $lng->catalog_languages[$c]['id'];
        } else {
            $entry->defualt_platform_language = $lng->catalog_languages[DEFAULT_LANGUAGE]['id'];
        }
    }
    public function action_get_platform_details()
    {
        $paltform_id = Yii::$app->request->get('platform_id', 0);
        if ($paltform_id) {
            $entry = new \stdClass();
            $this->load_platform_details($entry, $paltform_id);
            return $this->render_ajax('currency_language', ['entry' => $entry]);
        }
        return '';
    }
    public function action_get_states()
    {
        $response = '';
        if (Yii::$app->request->is_post) {
            $country_id = Yii::$app->request->post('country_id', 0);
            $prefix = Yii::$app->request->post('prefix', 0);
            $value = Yii::$app->request->post('value', '');
            $def_country_id = Yii::$app->request->post('def_country_id', 0);
            if ($country_id) {
                $zones = \common\helpers\Zones::get_country_zones($country_id);
                if (is_array($zones) && count($zones)) {
                    if (!is_numeric($value)) {
                        $value = \common\helpers\Zones::get_zone_id($country_id, $value);
                    }
                    $response = tep_draw_pull_down_menu($prefix . 'entry_state', $zones, $value, 'class="form-control"');
                } else {
                    $def_zones = \common\helpers\Zones::get_country_zones($def_country_id);
                    if (is_array($def_zones) && count($def_zones)) {
                        $hepler = \yii\helpers\Array_Helper::map($def_zones, 'id', 'text');
                        $value = $hepler[$value];
                    }
                    $response = tep_draw_input_field($prefix . 'entry_state', $value, 'class="form-control"');
                }
            }
        }
        echo $response;
        exit;
    }
    private function tep_get_category_children(&$children, $platform_id, $categories_id, $search = '')
    {
        if (!is_array($children)) {
            $children = [];
        }
        $l = \common\helpers\Categories::load_tree_slice($platform_id, $categories_id, true, $search, true);
        foreach ($l as $item) {
            $key = $item['key'];
            $children[] = $key;
            if ($item['folder']) {
                $this->tep_get_category_children($children, $platform_id, intval(mb_substr($item['key'], 1)));
            }
        }
    }
    public function action_countries()
    {
        $term = tep_db_prepare_input(Yii::$app->request->get('term'));
        $delivery_countries = \common\helpers\Order::get_orders_query(['delivery_country' => $term])->group_by('delivery_country')->order_by('delivery_country')->all();
        echo json_encode(\yii\helpers\Array_Helper::get_column($delivery_countries, 'delivery_country'));
    }
    public function action_state()
    {
        $term = tep_db_prepare_input(Yii::$app->request->get('term'));
        $country = tep_db_prepare_input(Yii::$app->request->get('country'));
        $delivery_states = \common\helpers\Order::get_orders_query(['delivery_state' => $term, 'delivery_country' => $country])->group_by('delivery_state')->order_by('delivery_state')->all();
        echo json_encode(\yii\helpers\Array_Helper::get_column($delivery_states, 'delivery_state'));
        exit;
    }
    public function action_ordersdelete()
    {
        $this->layout = false;
        $selected_ids = Yii::$app->request->post('selected_ids');
        foreach ($selected_ids as $orders_id) {
            \common\helpers\Order::remove_order((int) $orders_id, (int) $_POST['restock'], 'Batch deleting');
        }
    }
    public function action_ordersbatch()
    {
        \common\helpers\Translation::init('main');
        \common\helpers\Translation::init('admin/orders');
        $currencies = Yii::$container->get('currencies');
        $use_pdf = true;
        $pages = [];
        $filename = 'document';
        if (!\Yii::$app->request->post('orders') && \Yii::$app->request->get('orders_id')) {
            $_POST['orders'] = $_GET['orders_id'];
        }
        $default_language_id = \common\classes\language::default_id();
        if ($_GET['action'] == 'selected' && tep_not_null($_POST['orders'])) {
            $orders_query = tep_db_query('select orders_id, platform_id, orders_status, language_id from ' . TABLE_ORDERS . ' where orders_id in(' . $_POST['orders'] . ')');
        } elseif (isset($_GET['oID']) && !empty($_GET['oID'])) {
            $orders_query = tep_db_query('select orders_id, platform_id, orders_status, language_id from ' . TABLE_ORDERS . " where orders_id ='" . (int) $_GET['oID'] . "'");
        } else {
            $orders_query = tep_db_query('select orders_id, platform_id, orders_status, language_id from ' . TABLE_ORDERS . ' where orders_status = 1');
        }
        $is_invoice = isset($_GET['pdf']) && $_GET['pdf'] == 'invoice' ? true : false;
        $manager = \common\services\Order_Manager::load_manager();
        $manager->set_modules_visibility(['shop_order']);
        $splitter = $manager->get_order_splitter();
        $_qty = tep_db_num_rows($orders_query);
        if ($_qty) {
            $pn = $is_invoice ? 'invoice' : 'packingslip';
            $pn = \Yii::$app->request->get('page_name', $pn);
            while ($orders = tep_db_fetch_array($orders_query)) {
                $invoices = $splitter->get_instances_from_splinters($orders['orders_id'], $splitter::STATUS_PAYED);
                if ($is_invoice && $invoices) {
                    if ($_qty == 1 && $is_invoice) {
                        //$orderId = $invoice->getOrderId();
                        $order_id = $orders['orders_id'];
                    }
                    foreach ($invoices as $invoice) {
                        $lan_id = $orders['language_id'] ? $orders['language_id'] : $default_language_id;
                        $pages[] = ['name' => $pn, 'params' => ['orders_id' => $orders['orders_id'], 'platform_id' => $invoice->info['platform_id'] ? $invoice->info['platform_id'] : 1, 'language_id' => $lan_id, 'order' => $invoice, 'currencies' => $currencies, 'theme_name' => \backend\design\Theme::get_theme_name($invoice->info['platform_id'] ? $invoice->info['platform_id'] : 1), 'oID' => $orders['orders_id']]];
                    }
                } else {
                    $order = $manager->get_order_instance_with_id('\common\classes\Order', $orders['orders_id']);
                    $order->add_legend(($is_invoice ? 'Invoice' : 'Packingslip') . ' printed', $_SESSION['login_id']);
                    if ($_qty == 1) {
                        $order_id = $order->get_order_id();
                    }
                    $lan_id = $orders['language_id'] ? $orders['language_id'] : $default_language_id;
                    $pages[] = ['name' => $pn, 'params' => ['orders_id' => $orders['orders_id'], 'platform_id' => $orders['platform_id'] ? $orders['platform_id'] : 1, 'language_id' => $lan_id, 'order' => $order, 'currencies' => $currencies, 'theme_name' => \backend\design\Theme::get_theme_name($orders['platform_id'] ? $orders['platform_id'] : 1), 'oID' => $orders['orders_id']]];
                }
                //$filename = ($isInvoice ? str_replace(' ', '_', TEXT_INVOICE) : str_replace(' ', '_', TEXT_PACKINGSLIP));
                $platform_id = $orders['platform_id'];
            }
        }
        $filename = $is_invoice ? str_replace(' ', '_', TEXT_INVOICE) : str_replace(' ', '_', TEXT_PACKINGSLIP);
        if ($_qty == 1) {
            $filename .= $order_id;
            $title = ($is_invoice ? TEXT_INVOICE : TEXT_PACKINGSLIP) . ' ' . $order_id;
            $subject = ($is_invoice ? TEXT_INVOICE : TEXT_PACKINGSLIP) . ' ' . $order_id;
        } else {
            $title = $subject = $filename;
        }
        if ($_qty > 1) {
            // print product list
            if (defined('PACKING_SLIPS_SUMMARY') && PACKING_SLIPS_SUMMARY == 'True') {
                $products = [];
                foreach ($pages as $page) {
                    $order = $page['params']['order'];
                    foreach ($order->get_ordered_products('packing_slip') as $product) {
                        if (isset($products[$product['id']])) {
                            $products[$product['id']]['qty'] += $product['qty'];
                        } else {
                            $products[$product['id']] = $product;
                        }
                    }
                }
                if (count($products) > 0) {
                    $pages[] = ['name' => 'products-list', 'params' => ['products' => $products]];
                }
            }
        }
        \backend\design\Pdf_Block::widget(['pages' => $pages, 'params' => ['theme_name' => \backend\design\Theme::get_theme_name($platform_id), 'document_name' => $filename . '.pdf', 'title' => $title, 'subject' => $subject]]);
        die;
    }
    public function action_customer()
    {
        $search = Yii::$app->request->get('term');
        $customers = [];
        if (!empty($search)) {
            $c_rep = new \common\models\repositories\Customers_Repository();
            foreach ($c_rep->search($search, [0, 1], [0, 1])->all() as $customer) {
                $customers[] = ['id' => $customer->customers_id, 'value' => Html::encode($customer->customers_firstname . ' ' . $customer->customers_lastname . ' (' . $customer->customers_email_address . ')') . (empty($customer->customers_status) ? ' ' . TEXT_INACTIVE : '') . (!empty($customer->opc_temp_account) ? ' ' . TEXT_GUEST : '')];
            }
        }
        echo json_encode($customers);
    }
    public function action_gettracking()
    {
        \common\helpers\Translation::init('admin/orders');
        $this->layout = false;
        $this->view->use_popup_mode = true;
        if (Yii::$app->request->is_post) {
            $o_id = Yii::$app->request->post('orders_id');
        } else {
            $o_id = Yii::$app->request->get('orders_id');
        }
        $view = Yii::$app->request->get('view', 0);
        $get_tracking = tep_db_query('select customers_id, tracking_number from ' . TABLE_ORDERS . ' where orders_id = ' . (int) $o_id);
        if (tep_db_num_rows($get_tracking) > 0) {
            $result_tracking = tep_db_fetch_array($get_tracking);
            $trackings = [];
            if ($result_tracking && tep_not_null($result_tracking['tracking_number'])) {
                $trackings = explode(';', $result_tracking['tracking_number']);
            }
            return $this->render_ajax('tracking' . ($view ? '_view' : ''), ['trackings' => $trackings, 'order_id' => (int) $o_id, 'customers_id' => $result_tracking['customers_id']]);
        } else {
            return false;
        }
    }
    public function action_parse_tracking()
    {
        $this->layout = false;
        $o_id = \Yii::$app->request->post('order_id', 0);
        $tracking_number = \Yii::$app->request->post('tracking_number', '');
        $order = new \common\classes\Order($o_id);
        $platform_config = Yii::$app->get('platform')->config($order->info['platform_id']);
        $parsed_tracking = \common\helpers\Order::parse_tracking_number($tracking_number);
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        Yii::$app->response->data = ['tracking' => $parsed_tracking, 'qr_image_src' => tep_catalog_href_link('account/order-qrcode', 'oID=' . (int) $o_id . '&cID=' . (int) $order->customer['customer_id'] . '&tracking=1&tracking_number=' . urlencode($parsed_tracking['number']), 'SSL')];
    }
    /**
     * @deprecated new action with table tracking support actionTrackingSave
     *
     */
    public function action_savetracking()
    {
        global $admin_id;
        \common\helpers\Translation::init('admin/orders');
        $message_type = '';
        $message = '';
        $tracks = [];
        if (Yii::$app->request->is_post) {
            $o_id = intval(Yii::$app->request->post('orders_id'));
            $tracking_number = Yii::$app->request->post('tracking_number', []);
            if (is_array($tracking_number)) {
                for ($i = 0; $i < count($tracking_number); $i++) {
                    if (!empty($tracking_number[$i]) && !in_array($tracking_number[$i], $tracks)) {
                        $tracks[] = tep_db_prepare_input($tracking_number[$i]);
                    }
                }
            }
        } else {
            //??
            $o_id = intval(Yii::$app->request->get('orders_id'));
            $tracking_number = tep_db_prepare_input(Yii::$app->request->get('tracking_number'));
        }
        $order = new \common\classes\Order($o_id);
        $platform_config = Yii::$app->get('platform')->config($order->info['platform_id']);
        if (count($tracks) > 0) {
            // {{
            if (array_diff($tracks, $order->info['tracking_number']) || count($tracks) != count($order->info['tracking_number'])) {
                //if ($order->info['tracking_number'] != $tracking_number) {
                $notify_comments = $notify_comments_mail = '';
                $new_tracking_codes = [];
                $_check_old = array_map('strtolower', $order->info['tracking_number']);
                foreach ($tracks as $_check_track) {
                    $_old_index = array_search(strtolower($_check_track), $_check_old);
                    if ($_old_index === false) {
                        $new_tracking_codes[] = $_check_track;
                    } else {
                        unset($_check_old[$_old_index]);
                    }
                }
                if (count($new_tracking_codes) > 0) {
                    $email_params_tracking = ['TRACKING_NUMBER' => '', 'TRACKING_NUMBER_URL' => ''];
                    foreach ($new_tracking_codes as $track) {
                        $tracking_data = \common\helpers\Order::parse_tracking_number($track);
                        $notify_comments .= TEXT_TRACKING_NUMBER . ': ' . $tracking_data['number'] . "\n";
                        $email_params_tracking['TRACKING_NUMBER'] .= (empty($email_params_tracking['TRACKING_NUMBER']) ? '' : ', ') . $tracking_data['number'];
                        $email_params_tracking['TRACKING_NUMBER_URL'] .= (empty($email_params_tracking['TRACKING_NUMBER_URL']) ? '' : ', ') . '<a href="' . $tracking_data['url'] . '" target="_blank"><img border="0" alt="' . $tracking_data['number'] . '" src="' . tep_catalog_href_link('account/order-qrcode', 'oID=' . (int) $o_id . '&cID=' . (int) $order->customer['customer_id'] . '&tracking=1&tracking_number=' . urlencode($track), 'SSL') . '"></a>';
                    }
                    $notify_comments = rtrim($notify_comments);
                    $STORE_OWNER_EMAIL_ADDRESS = $platform_config->const_value('STORE_OWNER_EMAIL_ADDRESS');
                    $STORE_OWNER = $platform_config->const_value('STORE_OWNER');
                    $email_params = \common\helpers\Mail::email_params_from_order($order);
                    $email_params['TRACKING_NUMBER'] = $email_params_tracking['TRACKING_NUMBER'];
                    $email_params['TRACKING_NUMBER_URL'] = $email_params_tracking['TRACKING_NUMBER_URL'];
                    [$email_subject, $email_text] = \common\helpers\Mail::get_parsed_email_template('Add Tracking Number', $email_params, $order->info['language_id'], $order->info['platform_id']);
                    \common\helpers\Mail::send($order->customer['name'], $order->customer['email_address'], $email_subject, $email_text, $STORE_OWNER, $STORE_OWNER_EMAIL_ADDRESS);
                    tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, ['orders_id' => $order->order_id, 'orders_status_id' => $order->info['order_status'], 'date_added' => 'now()', 'customer_notified' => 1, 'comments' => $notify_comments, 'admin_id' => $admin_id]);
                }
                tep_db_perform(TABLE_ORDERS, ['tracking_number' => implode(';', $tracks), 'last_modified' => 'now()'], 'update', "orders_id = '" . (int) $o_id . "'");
            }
            // }}
            $message_type = 'success';
            $message = TEXT_TRACKING_MESSAGE_SUCCESS;
        } else {
            if (!empty($order->info['tracking_number'])) {
                tep_db_perform(TABLE_ORDERS, ['tracking_number' => '', 'last_modified' => 'now()'], 'update', "orders_id = '" . (int) $o_id . "'");
            }
            $message_type = 'warning';
            $message = TEXT_TRACKING_MESSAGE_WARNING;
        }
        echo json_encode(['message' => '<div class="alert alert-' . $message_type . ' fade in"><i data-dismiss="alert" class="icon-remove close"></i>' . $message . '</div>']);
        exit;
    }
    /**
     * for order view
     * @return bool|string
     */
    public function action_tracking_list()
    {
        \common\helpers\Translation::init('admin/orders');
        $this->layout = false;
        $this->view->use_popup_mode = true;
        if (Yii::$app->request->is_post) {
            $orders_id = Yii::$app->request->post('orders_id');
        } else {
            $orders_id = Yii::$app->request->get('orders_id');
        }
        $get_order = tep_db_fetch_array(tep_db_query('select o.customers_id, sum(op.products_quantity) as products_quantity from ' . TABLE_ORDERS . ' o left join ' . TABLE_ORDERS_PRODUCTS . " op on o.orders_id = op.orders_id where o.orders_id = '" . (int) $orders_id . "'"));
        if ($get_order['customers_id'] > 0) {
            $order = new \common\classes\Order($orders_id);
            //$trackings = array_map(function($item){ return $item->getAttributes(); },$order->info['tracking_number']);
            $trackings = $order->info['tracking_number'];
            $selected_products_quantity = 0;
            foreach (\common\helpers\Order::get_allocated_array($orders_id, true) as $opa_record) {
                $selected_products_quantity += $opa_record['allocate_received'] - $opa_record['allocate_dispatched'];
            }
            unset($opa_record);
            $get_tracking = tep_db_query('select trn.tracking_numbers_id, trn.tracking_carriers_id, trn.tracking_number, sum(trn2op.products_quantity) as products_quantity from ' . TABLE_TRACKING_NUMBERS . ' trn left join ' . TABLE_TRACKING_NUMBERS_TO_ORDERS_PRODUCTS . " trn2op on trn2op.tracking_numbers_id = trn.tracking_numbers_id and trn2op.orders_id = trn.orders_id where trn.orders_id = '" . (int) $orders_id . "' group by trn.tracking_numbers_id");
            $products_per_tracking = [];
            while ($result_tracking = tep_db_fetch_array($get_tracking)) {
                //$trackings[$result_tracking['tracking_numbers_id']] = $result_tracking;
                //$selected_products_quantity += $result_tracking['products_quantity'];
                $products_arr = [];
                $tracking_products_query = tep_db_query('select tracking_numbers_id, orders_products_id, products_quantity from ' . TABLE_TRACKING_NUMBERS_TO_ORDERS_PRODUCTS . " where tracking_numbers_id = '" . (int) $result_tracking['tracking_numbers_id'] . "' and orders_id = '" . (int) $orders_id . "'");
                while ($tracking_products = tep_db_fetch_array($tracking_products_query)) {
                    for ($i = 0, $n = sizeof($order->products); $i < $n; $i++) {
                        if ($order->products[$i]['orders_products_id'] == $tracking_products['orders_products_id']) {
                            $products_arr[] = $order->products[$i];
                            $products_arr[count($products_arr) - 1]['qty'] = $tracking_products['products_quantity'];
                            foreach ($trackings as &$tracking_record) {
                                if ($tracking_record->tracking_numbers_id == $tracking_products['tracking_numbers_id']) {
                                    $tracking_record->products_quantity += $tracking_products['products_quantity'];
                                    break;
                                }
                            }
                            unset($tracking_record);
                        }
                    }
                }
                $products_per_tracking[$result_tracking['tracking_numbers_id']] = $products_arr;
                //$trackings[$result_tracking['tracking_numbers_id']]['products'] = $productsArr;
            }
            return $this->render_ajax('tracking-list', ['trackings' => $trackings, 'products_per_tracking' => $products_per_tracking, 'orders_id' => (int) $orders_id, 'customers_id' => $get_order['customers_id'], 'products_left' => $selected_products_quantity]);
        } else {
            return false;
        }
    }
    public function action_tracking_edit()
    {
        $this->layout = false;
        $this->view->use_popup_mode = true;
        $orders_id = Yii::$app->request->get('orders_id');
        $tracking_numbers_id = Yii::$app->request->get('tracking_numbers_id');
        $get_tracking = tep_db_fetch_array(tep_db_query('select o.customers_id, trn.tracking_numbers_id, trn.tracking_carriers_id, trn.tracking_number ' . 'from ' . TABLE_ORDERS . ' o ' . '  left join ' . TABLE_TRACKING_NUMBERS . " trn on o.orders_id = trn.orders_id and trn.tracking_numbers_id = '" . (int) $tracking_numbers_id . "' " . "where o.orders_id = '" . (int) $orders_id . "'"));
        $order = new \common\classes\Order($orders_id);
        $orders_products = [];
        for ($i = 0, $n = sizeof($order->products); $i < $n; $i++) {
            $product = $order->products[$i];
            $product['qty_max'] = (int) $product['qty_rcvd'] - (int) $product['qty_dspd'];
            $product['qty_min'] = min(1, $product['qty_max']);
            $product['qty'] = $product['qty_max'];
            $orders_products[$order->products[$i]['orders_products_id']] = $product;
            unset($product);
        }
        if ($tracking_numbers_id > 0) {
            $selected_products_query = tep_db_query('select orders_products_id, products_quantity from ' . TABLE_TRACKING_NUMBERS_TO_ORDERS_PRODUCTS . " where tracking_numbers_id = '" . (int) $tracking_numbers_id . "' and orders_id = '" . (int) $orders_id . "'");
            if (tep_db_num_rows($selected_products_query) > 0) {
                while ($selected_products = tep_db_fetch_array($selected_products_query)) {
                    $orders_products[$selected_products['orders_products_id']]['selected'] = true;
                    $orders_products[$selected_products['orders_products_id']]['qty'] = $selected_products['products_quantity'];
                    $orders_products[$selected_products['orders_products_id']]['qty_min'] = $selected_products['products_quantity'];
                    $orders_products[$selected_products['orders_products_id']]['qty_max'] += $selected_products['products_quantity'];
                }
            }
            //draft - 2do extra payment when TN is added to another transaction
            $payment_tracking_q = \common\models\Tracking_Numbers_Export::find()->and_where(['tracking_numbers_id' => $tracking_numbers_id])->join_with('payments p', false, 'INNER JOIN')->select(['date_added', 'status', 'message', 'id' => 'p.orders_payment_id', 'paid_on' => 'orders_payment_transaction_date', 'payment_class' => 'orders_payment_module', 'payment' => 'orders_payment_module_name', 'transaction' => 'orders_payment_transaction_id'])->and_where('status>0')->order_by('status desc, orders_payment_module');
            $transactions = $payment_tracking_q->as_array()->all();
        }
        if ($get_tracking['customers_id'] > 0) {
            if (empty($transactions)) {
                $payment_q = \common\models\Orders_Payment::find()->select(['id' => 'orders_payment_id', 'paid_on' => 'orders_payment_transaction_date', 'payment_class' => 'orders_payment_module', 'payment' => 'orders_payment_module_name', 'transaction' => 'orders_payment_transaction_id'])->and_where(['orders_payment_order_id' => $orders_id]);
                $transactions = $payment_q->as_array()->all();
                $skip = $keep = [];
                foreach ($transactions as $i => $transaction) {
                    if (in_array($transaction['payment_class'], $keep)) {
                        continue;
                    }
                    if (!in_array($transaction['payment_class'], $skip)) {
                        $manager = \common\services\Order_Manager::load_manager();
                        /** @var common\classes\Order $order */
                        $order = $manager->get_order_instance_with_id('\common\classes\Order', $orders_id);
                        try {
                            //payment could be switched off
                            $builder = new \common\classes\modules\Module_Builder($manager);
                            $class = $builder(['class' => "\\common\\modules\\orderPayment\\{$transaction['payment_class']}"]);
                            if (method_exists($class, 'add_tracking')) {
                                $keep[] = $transaction['payment_class'];
                                continue;
                            }
                        } catch (\Exception $e) {
                        }
                    }
                    unset($transactions[$i]);
                }
            }
            if (!empty($transactions)) {
                \common\helpers\Translation::init('payment');
                $sync['transactions'] = $transactions;
                $sync['added'] = !empty($transactions[0]['status']);
            }
            return $this->render_ajax('tracking-edit', ['orders_id' => (int) $orders_id, 'customers_id' => $get_tracking['customers_id'], 'tracking_number' => $get_tracking['tracking_number'], 'tracking_numbers_id' => $get_tracking['tracking_numbers_id'], 'orders_products' => $orders_products, 'platform_id' => $order->info['platform_id'], 'sync' => $sync ?? null]);
        } else {
            return false;
        }
    }
    public function action_tracking_save()
    {
        \common\helpers\Translation::init('admin/orders');
        \common\helpers\Translation::init('payment');
        $orders_id = Yii::$app->request->post('orders_id');
        $tracking_numbers_id = Yii::$app->request->post('tracking_numbers_id');
        $tracking_number = Yii::$app->request->post('tracking_number');
        $selected_products = Yii::$app->request->post('selected_products', []);
        $selected_products_qty = Yii::$app->request->post('selected_products_qty', []);
        $selected_products_qty_max = Yii::$app->request->post('selected_products_qty_max', []);
        foreach (\common\models\Tracking_Numbers_To_Orders_Products::find()->where(['tracking_numbers_id' => $tracking_numbers_id])->and_where(['orders_id' => $orders_id])->all() as $tracking_product_record) {
            if (!in_array($tracking_product_record->orders_products_id, $selected_products)) {
                $selected_products[] = $tracking_product_record->orders_products_id;
            }
        }
        unset($tracking_product_record);
        $tracking_order_products = [];
        foreach ($selected_products as $orders_products_id) {
            $selected_qty = min($selected_products_qty[$orders_products_id], $selected_products_qty_max[$orders_products_id]);
            if ($selected_qty > 0) {
                $tracking_order_products[$orders_products_id] = $selected_qty;
            }
        }
        if (tep_not_null($tracking_number)) {
            $order = new \common\classes\Order($orders_id);
            $_update_tracking = false;
            if (!empty($tracking_numbers_id)) {
                foreach ($order->info['tracking_number'] as $_idx => $tracking_number) {
                    /**
                     * @var $trackingNumber \common\classes\OrderTrackingNumber
                     */
                    if ($tracking_number->tracking_numbers_id == $tracking_numbers_id) {
                        $_update_tracking = true;
                        if (empty($tracking_number)) {
                            unset($order->info['tracking_number'][$_idx]);
                        } else {
                            $tracking_number->tracking_number = $tracking_number;
                        }
                        $tracking_number->set_order_products($tracking_order_products);
                    }
                }
            }
            if (!$_update_tracking && !empty($tracking_number)) {
                $add_tracking = \common\classes\Order_Tracking_Number::instance_from_string($tracking_number, $order->order_id);
                $add_tracking->set_order_products($tracking_order_products);
                $order->info['tracking_number'][] = $add_tracking;
            }
            $order->save_tracking_numbers();
            if ($ext = \common\helpers\Acl::check_extension_allowed('Ebay', 'allowed')) {
                $ext::set_update_order($orders_id);
            }
            if ($ext = \common\helpers\Acl::check_extension_allowed('Amazon', 'allowed')) {
                $ext::set_update_order($orders_id);
            }
            $transactions = Yii::$app->request->post('sync_to_payment', []);
            if (!empty($transactions)) {
                $payment_q = \common\models\Orders_Payment::find()->select(['id' => 'orders_payment_id', 'paid_on' => 'orders_payment_transaction_date', 'payment_class' => 'orders_payment_module', 'payment' => 'orders_payment_module_name', 'transaction' => 'orders_payment_transaction_id'])->and_where(['orders_payment_id' => array_values($transactions)]);
                $transactions = $payment_q->as_array()->all();
                $keep = [];
                foreach ($transactions as $i => $transaction) {
                    if (!isset($keep[$transaction['payment_class']])) {
                        $manager = \common\services\Order_Manager::load_manager();
                        try {
                            //payment could be switched off
                            $builder = new \common\classes\modules\Module_Builder($manager);
                            $class = $builder(['class' => "\\common\\modules\\orderPayment\\{$transaction['payment_class']}"]);
                            if (method_exists($class, 'add_tracking')) {
                                $keep[$transaction['payment_class']] = $class;
                            }
                        } catch (\Exception $e) {
                        }
                    }
                    if (isset($keep[$transaction['payment_class']])) {
                        if (empty($tracking_numbers_id) && !empty($add_tracking)) {
                            if (empty($add_tracking->tracking_numbers_id)) {
                                $add_tracking->refresh();
                            }
                            $tracking_numbers_id = $add_tracking->tracking_numbers_id;
                        }
                        if (!empty($tracking_numbers_id)) {
                            $keep[$transaction['payment_class']]->add_tracking(['transaction_id' => $transaction['transaction'], 'tracking_number' => $tracking_number, 'orders_payment_id' => $transaction['id'], 'tracking_numbers_id' => $tracking_numbers_id, 'orders_id' => $orders_id]);
                        }
                    }
                }
            }
        }
    }
    public function action_tracking_delete()
    {
        $orders_id = Yii::$app->request->post('orders_id');
        $tracking_numbers_id = Yii::$app->request->post('tracking_numbers_id');
        //payment tracking before deleting TN
        if ($tracking_numbers_id) {
            $ptns = \common\models\Tracking_Numbers_Export::find_all(['tracking_numbers_id' => $tracking_numbers_id]);
            foreach ($ptns as $ptn) {
                if (!empty($ptn) && !empty($ptn->classname)) {
                    $manager = \common\services\Order_Manager::load_manager();
                    try {
                        //payment could be switched off
                        $builder = new \common\classes\modules\Module_Builder($manager);
                        $class = $builder(['class' => "\\common\\modules\\orderPayment\\{$ptn->classname}"]);
                        if (method_exists($class, 'delete_tracking')) {
                            $class->delete_tracking($ptn->get_attributes());
                        }
                        $ptn->delete();
                    } catch (\Exception $e) {
                    }
                }
            }
        }
        $order = new \common\classes\Order($orders_id);
        $order->remove_tracking_number($tracking_numbers_id);
    }
    public function action_ordersexport()
    {
        if (tep_not_null($_POST['orders'])) {
            $filename = 'orders_' . strftime('%Y%b%d_%H%M') . '.csv';
            $writer = new \backend\models\EP\Formatter\CSV('write', [], $filename);
            $writer->write_array(['Order ID', 'Ship Method', 'Shipping Company', 'Shipping Street 1', 'Shipping Street 2', 'Shipping Suburb', 'Shipping State', 'Shipping Zip', 'Shipping Country', 'Shipping Name']);
            foreach (\common\models\Orders::find()->where(['orders_id' => array_map('intval', explode(',', $_POST['orders']))])->all() as $order) {
                $writer->write_array([$order->orders_id, $order->shipping_method, $order->delivery_company, $order->delivery_street_address, $order->delivery_suburb, $order->delivery_city, $order->delivery_state, $order->delivery_postcode, $order->delivery_country, $order->delivery_name]);
            }
        }
        exit;
    }
    public function action_gv_change_state()
    {
        \common\helpers\Translation::init('admin/orders');
        $op_id = intval(Yii::$app->request->get('opID', 0));
        $_order_id = tep_db_fetch_array(tep_db_query('SELECT orders_id, gv_state FROM ' . TABLE_ORDERS_PRODUCTS . " WHERE orders_products_id='" . (int) $op_id . "'"));
        if (Yii::$app->request->is_post) {
            \common\helpers\Coupon::credit_order_manual_update_state($op_id, Yii::$app->request->post('new_gv_state', $_order_id['gv_state']));
            echo 'ok';
        }
        ?>
        <?php 
        echo tep_draw_form('update_gv', 'orders/gv-change-state', \common\helpers\Output::get_all_get_params(), 'post', 'id="frmGvChangeState"');
        ?>
        <div class="pop-up-content">
            <div class="popup-content">
                <div><label><?php 
        echo tep_draw_radio_field('new_gv_state', 'pending', $_order_id['gv_state'] == 'pending', '', in_array($_order_id['gv_state'], ['released']) ? 'disabled="disabled" readonly="readonly"' : '');
        ?>
                        <?php 
        echo TEXT_GV_STATE_SWITCH_TO_PENDING;
        ?></label></div>
                <div><label><?php 
        echo tep_draw_radio_field('new_gv_state', 'released', $_order_id['gv_state'] == 'released', '', in_array($_order_id['gv_state'], ['released']) ? 'disabled="disabled" readonly="readonly"' : '');
        ?>
                        <?php 
        echo TEXT_GV_STATE_SWITCH_TO_RELEASED;
        ?></label></div>
                <div><label><?php 
        echo tep_draw_radio_field('new_gv_state', 'canceled', $_order_id['gv_state'] == 'canceled', '', in_array($_order_id['gv_state'], ['released']) ? 'disabled="disabled" readonly="readonly"' : '');
        ?>
                        <?php 
        echo TEXT_GV_STATE_SWITCH_TO_CANCELED;
        ?></label></div>
            </div>
        </div>
        <div class="noti-btn">
            <div><span class="btn btn-cancel"><?php 
        echo IMAGE_CANCEL;
        ?></span></div>
            <div><span class="btn btn-primary" id="btnGvChangeState"><?php 
        echo IMAGE_UPDATE;
        ?></span></div>
        </div>
        </form>
        <script type="text/javascript">
            $('#btnGvChangeState').on('click', function () {
                $.ajax({
                    type: "POST",
                    url: $('#frmGvChangeState').attr('action'),
                    data: $('#frmGvChangeState').serializeArray(),
                    success: function (data) {
                        window.location.href = window.location.href;
                        $('#frmGvChangeState .btn-cancel').trigger('click');
                    }
                });
            });
        </script>
        <?php 
    }
    public function action_products_status_history()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/orders');
        $op_id = Yii::$app->request->get('opID');
        $orders_products_statuses = [['id' => 0, 'text' => ''], ['id' => \common\helpers\Order_Product::OPS_QUOTED, 'text' => TEXT_STATUS_LONG_OPS_QUOTED], ['id' => \common\helpers\Order_Product::OPS_RECEIVED, 'text' => TEXT_STATUS_LONG_OPS_RECEIVED], ['id' => \common\helpers\Order_Product::OPS_DISPATCHED, 'text' => TEXT_STATUS_LONG_OPS_DISPATCHED], ['id' => \common\helpers\Order_Product::OPS_DELIVERED, 'text' => TEXT_STATUS_LONG_OPS_DELIVERED], ['id' => \common\helpers\Order_Product::OPS_CANCELLED, 'text' => TEXT_STATUS_LONG_OPS_CANCELLED]];
        $orders_products_statuses_manual = [['id' => 0, 'text' => '']];
        $orders_products_status_array = [];
        $order_product_array = [];
        $order_product_record = \common\helpers\Order_Product::get_record($op_id);
        if (\common\helpers\Order_Product::is_valid_allocated($order_product_record) == true) {
            if (count(\common\helpers\Order_Product::get_child_array($order_product_record)) == 0) {
                $warehouse_name_list = [];
                foreach (\common\models\Warehouses::find()->as_array(true)->all() as $warehouse_record) {
                    $warehouse_name_list[$warehouse_record['warehouse_id']] = $warehouse_record['warehouse_name'];
                }
                unset($warehouse_record);
                $supplier_name_list = [];
                foreach (\common\models\Suppliers::find()->as_array(true)->all() as $supplier_record) {
                    $supplier_name_list[$supplier_record['suppliers_id']] = $supplier_record['suppliers_name'];
                }
                unset($supplier_record);
                $location_block_list = [];
                foreach (\common\models\Location_Blocks::find()->as_array(true)->all() as $location_block_record) {
                    $location_block_list[$location_block_record['block_id']] = $location_block_record['block_name'];
                }
                unset($location_block_record);
                $quoted_array = [];
                $received_array = [];
                $cancelled_array = [];
                $delivered_array = [];
                $dispatched_array = [];
                $quantity_received = 0;
                $quantity_dispatched = 0;
                $quantity_real = (int) \common\helpers\Order_Product::get_quantity_real($order_product_record);
                $quantity_real_parent = $quantity_real;
                $opp_record = \common\helpers\Order_Product::get_parent($order_product_record, false);
                if ($opp_record instanceof \common\models\Orders_Products) {
                    $opc_quantity_multiplier = 1;
                    if ((int) $opp_record->products_quantity > 0) {
                        $opc_quantity_multiplier = (int) ceil((int) $order_product_record->products_quantity / (int) $opp_record->products_quantity);
                    }
                    $opc_quantity_real = \common\helpers\Order_Product::get_quantity_real($opp_record) * $opc_quantity_multiplier;
                    unset($opc_quantity_multiplier);
                    if ($quantity_real_parent > $opc_quantity_real) {
                        $quantity_real_parent = $opc_quantity_real;
                    }
                    unset($opc_quantity_real);
                }
                unset($opp_record);
                foreach (\common\helpers\Order_Product::get_allocated_array($order_product_record, true) as $opa_record) {
                    $warehouse_name = isset($warehouse_name_list[$opa_record['warehouse_id']]) ? $warehouse_name_list[$opa_record['warehouse_id']] : 'N/A';
                    $supplier_name = isset($supplier_name_list[$opa_record['suppliers_id']]) ? $supplier_name_list[$opa_record['suppliers_id']] : 'N/A';
                    $location_name = trim(\common\helpers\Warehouses::get_location_path($opa_record['location_id'], $opa_record['warehouse_id'], $location_block_list));
                    $location_name = $location_name != '' ? $location_name : 'N/A';
                    $layers_name = \common\helpers\Date::date_short(\common\helpers\Warehouses::get_expiry_date_by_layers_id($opa_record['layers_id']));
                    $layers_name = $layers_name != '' ? \common\helpers\Translation::get_translation_value('TEXT_EXPIRY_DATE', 'admin/categories') . ' ' . $layers_name : 'N/A';
                    $batch_name = \common\helpers\Warehouses::get_batch_name_by_batch_id($opa_record['batch_id']);
                    $batch_name = $batch_name != '' ? TEXT_WAREHOUSES_PRODUCTS_BATCH_NAME . ' ' . $batch_name : 'N/A';
                    // QUOTED
                    $min = 0;
                    $max = (int) $opa_record['allocate_received'];
                    if ($min != $max) {
                        $quoted_array[$opa_record['warehouse_id']][$opa_record['suppliers_id']][$opa_record['location_id']][$opa_record['layers_id']][$opa_record['batch_id']] = ['value' => 0, 'min' => $min, 'max' => $max, 'warning' => ['>' => ['value' => $max - (int) $opa_record['allocate_dispatched'], 'message' => TEXT_ORDER_PRODUCT_RESTOCK_WARNING_MESSAGE, 'calculate' => 'value - ' . ($max - (int) $opa_record['allocate_dispatched']), 'calculateAfter' => 'x&nbsp;']], 'warehouseName' => $warehouse_name, 'supplierName' => $supplier_name, 'locationName' => $location_name, 'layersName' => $layers_name, 'batchName' => $batch_name];
                    }
                    unset($max);
                    unset($min);
                    // EOF QUOTED
                    // RECEIVED
                    $min = 0;
                    $max = (int) $opa_record['allocate_received'];
                    $quantity_received += $max;
                    if ($min != $max) {
                        $received_array[$opa_record['warehouse_id']][$opa_record['suppliers_id']][$opa_record['location_id']][$opa_record['layers_id']][$opa_record['batch_id']] = ['value' => $max, 'min' => $min, 'max' => $max, 'awaiting' => $quantity_real_parent, 'warning' => ['<' => ['value' => (int) $opa_record['allocate_dispatched'], 'message' => TEXT_ORDER_PRODUCT_RESTOCK_WARNING_MESSAGE, 'calculate' => 'Math.abs(value - ' . (int) $opa_record['allocate_dispatched'] . ')', 'calculateAfter' => 'x&nbsp;']], 'warehouseName' => $warehouse_name, 'supplierName' => $supplier_name, 'locationName' => $location_name, 'layersName' => $layers_name, 'batchName' => $batch_name];
                    }
                    unset($max);
                    unset($min);
                    // EOF RECEIVED
                    // DISPATCHED
                    $min = 0;
                    $max = (int) $opa_record['allocate_received'] - (int) $opa_record['allocate_dispatched'];
                    if ($min != $max) {
                        $dispatched_array[$opa_record['warehouse_id']][$opa_record['suppliers_id']][$opa_record['location_id']][$opa_record['layers_id']][$opa_record['batch_id']] = ['value' => 0, 'min' => $min, 'max' => $max, 'warehouseName' => $warehouse_name, 'supplierName' => $supplier_name, 'locationName' => $location_name, 'layersName' => $layers_name, 'batchName' => $batch_name];
                    }
                    unset($max);
                    unset($min);
                    // EOF DISPATCHED
                    // DELIVERED
                    $min = 0;
                    $max = (int) $opa_record['allocate_dispatched'] - (int) $opa_record['allocate_delivered'];
                    if ($min != $max) {
                        $delivered_array[$opa_record['warehouse_id']][$opa_record['suppliers_id']][$opa_record['location_id']][$opa_record['layers_id']][$opa_record['batch_id']] = ['value' => 0, 'min' => $min, 'max' => $max, 'warehouseName' => $warehouse_name, 'supplierName' => $supplier_name, 'locationName' => $location_name, 'layersName' => $layers_name, 'batchName' => $batch_name];
                    }
                    unset($max);
                    unset($min);
                    // EOF DELIVERED
                    $quantity_dispatched += (int) $opa_record['allocate_dispatched'];
                    unset($warehouse_name);
                    unset($supplier_name);
                    unset($location_name);
                    unset($layers_name);
                    unset($batch_name);
                }
                unset($opa_record);
                // CANCELLED
                $min = 0;
                $max = $quantity_real - $quantity_received;
                if ($min != $max or $quantity_real == 0) {
                    $cancelled_array[0][0][0][0][0] = ['value' => $order_product_record->qty_cnld, 'min' => $min, 'max' => $max + (int) $order_product_record->qty_cnld];
                } else {
                    $cancelled_array[0][0][0][0][0] = ['html' => \yii\helpers\Html::checkbox('evaluation_state_restock', false, ['label' => TEXT_EVALUATION_STATE_RESTOCK])];
                }
                unset($max);
                unset($min);
                // EOF CANCELLED
                unset($quantity_dispatched);
                unset($quantity_received);
                $u_product_id = \common\helpers\Inventory::get_inventory_id($order_product_record->uprid);
                $pa_array = [];
                foreach (\common\helpers\Product::get_allocated_array($u_product_id) as $pa_record) {
                    $pa_array[$pa_record['warehouse_id']][$pa_record['suppliers_id']][$pa_record['location_id']][$pa_record['layers_id']][$pa_record['batch_id']][] = $pa_record;
                }
                unset($pa_record);
                $pat_array = [];
                foreach (\common\helpers\Product::get_allocated_temporary_array($u_product_id) as $pat_record) {
                    $pat_array[$pat_record['warehouse_id']][$pat_record['suppliers_id']][$pat_record['location_id']][$pat_record['layers_id']][$pat_record['batch_id']][] = $pat_record;
                }
                unset($pat_record);
                foreach (\common\helpers\Warehouses::get_product_array($u_product_id) as $wp_record) {
                    $warehouse_id = $wp_record['warehouse_id'];
                    $supplier_id = $wp_record['suppliers_id'];
                    $location_id = $wp_record['location_id'];
                    $layers_id = $wp_record['layers_id'];
                    $batch_id = $wp_record['batch_id'];
                    $available = (int) $wp_record['warehouse_stock_quantity'];
                    if (isset($pa_array[$warehouse_id][$supplier_id][$location_id][$layers_id][$batch_id])) {
                        foreach ($pa_array[$warehouse_id][$supplier_id][$location_id][$layers_id][$batch_id] as $pa_record) {
                            $available -= (int) $pa_record['allocate_received'];
                        }
                        unset($pa_array[$warehouse_id][$supplier_id][$location_id][$layers_id][$batch_id]);
                        unset($pa_record);
                    }
                    if (isset($pat_array[$warehouse_id][$supplier_id][$location_id][$layers_id][$batch_id])) {
                        foreach ($pat_array[$warehouse_id][$supplier_id][$location_id][$layers_id][$batch_id] as $pat_record) {
                            $available -= (int) $pat_record['temporary_stock_quantity'];
                        }
                        unset($pat_array[$warehouse_id][$supplier_id][$location_id][$layers_id][$batch_id]);
                        unset($pat_record);
                    }
                    if (isset($received_array[$warehouse_id][$supplier_id][$location_id][$layers_id][$batch_id])) {
                        $received_record =& $received_array[$warehouse_id][$supplier_id][$location_id][$layers_id][$batch_id];
                        $available += $received_record['max'];
                        if ($received_record['max'] < $available) {
                            $received_record['max'] = $available;
                        }
                        unset($received_record);
                    } elseif ($available > 0) {
                        $warehouse_name = isset($warehouse_name_list[$warehouse_id]) ? $warehouse_name_list[$warehouse_id] : 'N/A';
                        $supplier_name = isset($supplier_name_list[$supplier_id]) ? $supplier_name_list[$supplier_id] : 'N/A';
                        $location_name = trim(\common\helpers\Warehouses::get_location_path($location_id, $warehouse_id, $location_block_list));
                        $location_name = $location_name != '' ? $location_name : 'N/A';
                        $layers_name = \common\helpers\Date::date_short(\common\helpers\Warehouses::get_expiry_date_by_layers_id($layers_id));
                        $layers_name = $layers_name != '' ? \common\helpers\Translation::get_translation_value('TEXT_EXPIRY_DATE', 'admin/categories') . ' ' . $layers_name : 'N/A';
                        $batch_name = \common\helpers\Warehouses::get_batch_name_by_batch_id($batch_id);
                        $batch_name = $batch_name != '' ? TEXT_WAREHOUSES_PRODUCTS_BATCH_NAME . ' ' . $batch_name : 'N/A';
                        $received_array[$warehouse_id][$supplier_id][$location_id][$layers_id][$batch_id] = ['value' => 0, 'min' => 0, 'max' => $available, 'awaiting' => $quantity_real_parent, 'warehouseName' => $warehouse_name, 'supplierName' => $supplier_name, 'locationName' => $location_name, 'layersName' => $layers_name, 'batchName' => $batch_name];
                        unset($warehouse_name);
                        unset($supplier_name);
                        unset($location_name);
                        unset($layers_name);
                        unset($batch_name);
                    }
                    unset($warehouse_id);
                    unset($supplier_id);
                    unset($location_id);
                    unset($layers_id);
                    unset($batch_id);
                    unset($available);
                }
                unset($quantity_real_parent);
                unset($location_block_list);
                unset($warehouse_name_list);
                unset($supplier_name_list);
                unset($quantity_real);
                unset($u_product_id);
                unset($pat_array);
                unset($wp_record);
                unset($pa_array);
                if (count($quoted_array) == 0) {
                    $quoted_array[0][0][0][0][0] = ['html' => \yii\helpers\Html::checkbox('evaluation_state_reset_cancel', false, ['label' => TEXT_EVALUATION_STATE_RESET_CANCEL])];
                }
                if (count($dispatched_array) == 0) {
                    $dispatched_array[0][0][0][0][0] = ['html' => \yii\helpers\Html::checkbox('evaluation_state_force', false, ['label' => TEXT_EVALUATION_STATE_FORCE])];
                }
                if (count($delivered_array) == 0) {
                    $delivered_array[0][0][0][0][0] = ['html' => \yii\helpers\Html::checkbox('evaluation_state_force', false, ['label' => TEXT_EVALUATION_STATE_FORCE])];
                }
                $order_product_array = [\common\helpers\Order_Product::OPS_QUOTED => $quoted_array, \common\helpers\Order_Product::OPS_RECEIVED => $received_array, \common\helpers\Order_Product::OPS_DISPATCHED => $dispatched_array, \common\helpers\Order_Product::OPS_DELIVERED => $delivered_array, \common\helpers\Order_Product::OPS_CANCELLED => $cancelled_array];
                unset($dispatched_array);
                unset($delivered_array);
                unset($cancelled_array);
                unset($received_array);
                unset($quoted_array);
            } else {
                $orders_products_statuses = [];
            }
        }
        foreach (\common\models\Orders_Products_Status::find()->where(['language_id' => (int) $languages_id])->all() as $ops_record) {
            if (is_object($order_product_record) and $order_product_record->orders_products_status == $ops_record->orders_products_status_id) {
                foreach ($ops_record->get_matrix_array() as $opsmm_record) {
                    $orders_products_statuses_manual[] = ['id' => $opsmm_record->orders_products_status_manual_id, 'text' => $opsmm_record->orders_products_status_manual_name_long];
                }
                unset($opsmm_record);
            }
            $orders_products_status_array[$ops_record->orders_products_status_id] = $ops_record->orders_products_status_name_long;
        }
        unset($ops_record);
        $orders_products_status_manual_array = [];
        foreach (\common\models\Orders_Products_Status_Manual::find()->as_array(true)->where(['language_id' => (int) $languages_id])->all() as $opsm_record) {
            $orders_products_status_manual_array[$opsm_record['orders_products_status_manual_id']] = $opsm_record['orders_products_status_manual_name_long'];
        }
        unset($opsm_record);
        foreach (\common\models\Orders_Products_Status_History::find()->as_array(true)->where(['orders_products_id' => (int) $op_id])->order_by(['orders_products_history_id' => SORT_DESC])->all() as $opsh_record) {
            $admin_name = '';
            if ($opsh_record['admin_id'] > 0) {
                $admin_record = \common\models\Admin::find_one(['admin_id' => (int) $opsh_record['admin_id']]);
                if (is_object($admin_record)) {
                    $admin_name = trim($admin_record->admin_firstname . ' ' . $admin_record->admin_lastname);
                }
                unset($admin_record);
            }
            $history[] = ['id' => $opsh_record['orders_products_history_id'], 'date' => \common\helpers\Date::datetime_short($opsh_record['date_added']), 'status' => $orders_products_status_array[$opsh_record['orders_products_status_id']], 'status_manual' => $orders_products_status_manual_array[$opsh_record['orders_products_status_manual_id']], 'comments' => $opsh_record['comments'], 'admin' => $admin_name];
            unset($admin_name);
        }
        unset($orders_products_status_manual_array);
        unset($orders_products_status_array);
        unset($opsh_record);
        return $this->render_ajax('products-status-history', ['history' => $history ?? null, 'product' => $order_product_record->to_array(), 'statuses_array' => $orders_products_statuses, 'statuses_manual_array' => $orders_products_statuses_manual, 'orderProductArray' => $order_product_array]);
    }
    public function action_products_status_update()
    {
        global $login_id;
        $languages_id = \Yii::$app->settings->get('languages_id');
        $o_status = 0;
        $op_status = (int) Yii::$app->request->post('status', 0);
        $order_product_id = (int) Yii::$app->request->post('opID', 0);
        $commentary = trim(Yii::$app->request->post('comments', ''));
        $op_status_manual = (int) Yii::$app->request->post('status_manual', 0);
        $op_record = \common\helpers\Order_Product::get_record($order_product_id);
        if ($op_record instanceof \common\models\Orders_Products) {
            $op_status_value = (int) $op_record->orders_products_status;
            $op_status_manual_value = (int) $op_record->orders_products_status_manual;
            if (count(\common\helpers\Order_Product::get_child_array($op_record)) > 0) {
                \common\helpers\Order_Product::evaluate($op_record);
            } elseif ($op_status > 0) {
                $op_update_array = Yii::$app->request->post('update_order_product_' . $op_status, false);
                if ($op_status == \common\helpers\Order_Product::OPS_QUOTED) {
                    if (is_array($op_update_array)) {
                        foreach ($op_update_array as $warehouse_id => $supplier_array) {
                            foreach ($supplier_array as $supplier_id => $location_array) {
                                foreach ($location_array as $location_id => $layers_array) {
                                    foreach ($layers_array as $layers_id => $batch_array) {
                                        foreach ($batch_array as $batch_id => $quantity_update) {
                                            if ($quantity_update > 0) {
                                                \common\helpers\Order_Product::do_quote_specific($op_record, $quantity_update, $warehouse_id, $supplier_id, $location_id, $layers_id, $batch_id);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        \common\helpers\Order_Product::do_quote($op_record, (int) Yii::$app->request->post('evaluation_state_reset_cancel', 0));
                    }
                } elseif ($op_status == \common\helpers\Order_Product::OPS_RECEIVED) {
                    if (is_array($op_update_array)) {
                        foreach (\common\helpers\Order_Product::get_allocated_array($op_record, true) as $opa_record) {
                            if (isset($op_update_array[$opa_record['warehouse_id']][$opa_record['suppliers_id']][$opa_record['location_id']][$opa_record['layers_id']][$opa_record['batch_id']]) and (int) $op_update_array[$opa_record['warehouse_id']][$opa_record['suppliers_id']][$opa_record['location_id']][$opa_record['layers_id']][$opa_record['batch_id']] < (int) $opa_record['allocate_received']) {
                                \common\helpers\Order_Product::do_allocate_specific($op_record, $op_update_array[$opa_record['warehouse_id']][$opa_record['suppliers_id']][$opa_record['location_id']][$opa_record['layers_id']][$opa_record['batch_id']], $opa_record['warehouse_id'], $opa_record['suppliers_id'], $opa_record['location_id'], $opa_record['layers_id'], $opa_record['batch_id']);
                                unset($op_update_array[$opa_record['warehouse_id']][$opa_record['suppliers_id']][$opa_record['location_id']][$opa_record['layers_id']][$opa_record['batch_id']]);
                            }
                        }
                        unset($opa_record);
                        foreach ($op_update_array as $warehouse_id => $supplier_array) {
                            foreach ($supplier_array as $supplier_id => $location_array) {
                                foreach ($location_array as $location_id => $layers_array) {
                                    foreach ($layers_array as $layers_id => $batch_array) {
                                        foreach ($batch_array as $batch_id => $quantity_update) {
                                            \common\helpers\Order_Product::do_allocate_specific($op_record, $quantity_update, $warehouse_id, $supplier_id, $location_id, $layers_id, $batch_id);
                                        }
                                    }
                                }
                            }
                        }
                    }
                } elseif ($op_status == \common\helpers\Order_Product::OPS_DISPATCHED) {
                    if (is_array($op_update_array)) {
                        foreach ($op_update_array as $warehouse_id => $supplier_array) {
                            foreach ($supplier_array as $supplier_id => $location_array) {
                                foreach ($location_array as $location_id => $layers_array) {
                                    foreach ($layers_array as $layers_id => $batch_array) {
                                        foreach ($batch_array as $batch_id => $quantity_update) {
                                            \common\helpers\Order_Product::do_dispatch_specific($op_record, $quantity_update, $warehouse_id, $supplier_id, $location_id, $layers_id, $batch_id);
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        \common\helpers\Order_Product::do_dispatch($op_record, (int) Yii::$app->request->post('evaluation_state_force', 0));
                    }
                } elseif ($op_status == \common\helpers\Order_Product::OPS_DELIVERED) {
                    if (is_array($op_update_array)) {
                        foreach ($op_update_array as $warehouse_id => $supplier_array) {
                            foreach ($supplier_array as $supplier_id => $location_array) {
                                foreach ($location_array as $location_id => $layers_array) {
                                    foreach ($layers_array as $layers_id => $batch_array) {
                                        foreach ($batch_array as $batch_id => $quantity_update) {
                                            \common\helpers\Order_Product::do_deliver_specific($op_record, $quantity_update, $warehouse_id, $supplier_id, $location_id, $layers_id, $batch_id);
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        \common\helpers\Order_Product::do_deliver($op_record, (int) Yii::$app->request->post('evaluation_state_force', 0));
                    }
                } elseif ($op_status == \common\helpers\Order_Product::OPS_CANCELLED) {
                    if (is_array($op_update_array)) {
                        foreach ($op_update_array as $warehouse_id => $supplier_array) {
                            foreach ($supplier_array as $supplier_id => $location_array) {
                                foreach ($location_array as $location_id => $layers_array) {
                                    foreach ($layers_array as $layers_id => $batch_array) {
                                        foreach ($batch_array as $batch_id => $quantity_update) {
                                            $quantity_received = \common\helpers\Order_Product::get_received($op_record, true);
                                            if ($quantity_update < 0) {
                                                $quantity_update = 0;
                                            }
                                            if ($quantity_update > $op_record->products_quantity - $quantity_received) {
                                                $quantity_update = $op_record->products_quantity - $quantity_received;
                                            }
                                            $op_record->qty_cnld = $quantity_update;
                                            try {
                                                $op_record->save();
                                            } catch (\Exception $exc) {
                                            }
                                            \common\helpers\Order_Product::evaluate($op_record);
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        \common\helpers\Order_Product::do_cancel($op_record, (int) Yii::$app->request->post('evaluation_state_restock', 0));
                    }
                }
                if ($op_status_value != (int) $op_record->orders_products_status) {
                    $op_status_manual = false;
                }
            }
            $o_status = \common\helpers\Order::evaluate($op_record->orders_id);
            if ($commentary != '' or $op_status_manual != $op_status_manual_value or $op_status_value != (int) $op_record->orders_products_status) {
                if ($op_status_manual !== false) {
                    $op_record->orders_products_status_manual = $op_status_manual;
                    try {
                        $op_record->save();
                    } catch (\Exception $exc) {
                        $op_record->orders_products_status_manual = $op_status_manual_value;
                    }
                }
                $opsh_record = new \common\models\Orders_Products_Status_History();
                $opsh_record->orders_id = (int) $op_record->orders_id;
                $opsh_record->orders_products_id = (int) $op_record->orders_products_id;
                $opsh_record->orders_products_status_id = (int) $op_record->orders_products_status;
                $opsh_record->orders_products_status_manual_id = (int) $op_record->orders_products_status_manual;
                $opsh_record->comments = $commentary;
                $opsh_record->admin_id = (int) $login_id;
                $opsh_record->date_added = date('Y-m-d H:i:s');
                try {
                    $opsh_record->save();
                } catch (\Exception $exc) {
                }
                unset($opsh_record);
            }
            unset($op_status_manual_value);
            unset($op_status_manual);
            unset($op_status_value);
        }
        if (Yii::$app->request->is_ajax) {
            $qty_dfct = 0;
            $qty_cnld = 0;
            $qty_rcvd = 0;
            $qty_dspd = 0;
            $qty_dlvd = 0;
            $ops_status = '';
            $opsm_status = '';
            $ops_colour = '#000000';
            $opsm_colour = '#000000';
            if ($op_record instanceof \common\models\Orders_Products) {
                $qty_dfct = \common\helpers\Order_Product::get_stock_deficit($op_record);
                $qty_cnld = $op_record->qty_cnld;
                $qty_rcvd = $op_record->qty_rcvd;
                $qty_dspd = $op_record->qty_dspd;
                $qty_dlvd = $op_record->qty_dlvd;
                $ops_record = \common\models\Orders_Products_Status::find_one(['orders_products_status_id' => $op_record->orders_products_status, 'language_id' => (int) $languages_id]);
                if ($ops_record instanceof \common\models\Orders_Products_Status) {
                    $ops_status = $ops_record->orders_products_status_name;
                    $ops_colour = $ops_record->get_colour();
                }
                unset($ops_record);
                $opsm_record = \common\models\Orders_Products_Status_Manual::find_one(['orders_products_status_manual_id' => $op_record->orders_products_status_manual, 'language_id' => (int) $languages_id]);
                if ($opsm_record instanceof \common\models\Orders_Products_Status_Manual) {
                    $opsm_status = $opsm_record->orders_products_status_manual_name;
                    $opsm_colour = $opsm_record->get_colour();
                }
                unset($opsm_record);
            }
            $op_array = [(int) $op_record->orders_products_id => ['qty_dfct' => $qty_dfct, 'qty_cnld' => $qty_cnld, 'qty_rcvd' => $qty_rcvd, 'qty_dspd' => $qty_dspd, 'qty_dlvd' => $qty_dlvd, 'ops' => ['status' => $ops_status, 'colour' => $ops_colour], 'opsm' => ['status' => $opsm_status, 'colour' => $opsm_colour], 'prid' => (int) $op_record->products_id]];
            $opp_record = \common\helpers\Order_Product::get_parent($op_record, false);
            if ($opp_record instanceof \common\models\Orders_Products) {
                $opps_status = '';
                $oppsm_status = '';
                $opps_colour = '#000000';
                $oppsm_colour = '#000000';
                $opps_record = \common\models\Orders_Products_Status::find_one(['orders_products_status_id' => $opp_record->orders_products_status, 'language_id' => (int) $languages_id]);
                if ($opps_record instanceof \common\models\Orders_Products_Status) {
                    $opps_status = $opps_record->orders_products_status_name;
                    $opps_colour = $opps_record->get_colour();
                }
                unset($opps_record);
                $oppsm_record = \common\models\Orders_Products_Status_Manual::find_one(['orders_products_status_manual_id' => $opp_record->orders_products_status_manual, 'language_id' => (int) $languages_id]);
                if ($oppsm_record instanceof \common\models\Orders_Products_Status_Manual) {
                    $oppsm_status = $oppsm_record->orders_products_status_manual_name;
                    $oppsm_colour = $oppsm_record->get_colour();
                }
                unset($oppsm_record);
                $op_array[(int) $opp_record->orders_products_id] = ['qty_dfct' => \common\helpers\Order_Product::get_stock_deficit($opp_record), 'qty_cnld' => $opp_record->qty_cnld, 'qty_rcvd' => $opp_record->qty_rcvd, 'qty_dspd' => $opp_record->qty_dspd, 'qty_dlvd' => $opp_record->qty_dlvd, 'ops' => ['status' => $opps_status, 'colour' => $opps_colour], 'opsm' => ['status' => $oppsm_status, 'colour' => $oppsm_colour], 'prid' => (int) $opp_record->products_id];
            }
            unset($opp_record);
            foreach ($op_array as &$op_information) {
                $op_information['qty_dfct'] = \common\helpers\Product::get_virtual_item_quantity($op_information['prid'], $op_information['qty_dfct']);
                $op_information['qty_cnld'] = \common\helpers\Product::get_virtual_item_quantity($op_information['prid'], $op_information['qty_cnld']);
                $op_information['qty_rcvd'] = \common\helpers\Product::get_virtual_item_quantity($op_information['prid'], $op_information['qty_rcvd']);
                $op_information['qty_dspd'] = \common\helpers\Product::get_virtual_item_quantity($op_information['prid'], $op_information['qty_dspd']);
                $op_information['qty_dlvd'] = \common\helpers\Product::get_virtual_item_quantity($op_information['prid'], $op_information['qty_dlvd']);
            }
            unset($op_information);
            echo json_encode(['status' => 'ok', 'op' => $op_array, 'os' => ['status' => $o_status]]);
        } else {
            $url = Url::to(['orders/process-order', 'orders_id' => $data['orders_id']]);
            return $this->redirect($url);
        }
    }
    public function action_send_request()
    {
        $orders_id = Yii::$app->request->get('orders_id');
        \common\helpers\Translation::init('admin/recover_cart_sales');
        \common\helpers\Translation::init('admin/manufacturers');
        $manager = \common\services\Order_Manager::load_manager();
        $order = $manager->get_order_instance_with_id('\common\classes\Order', $orders_id);
        $platform_config = Yii::$app->get('platform')->config($order->info['platform_id']);
        $platform_config->constant_up();
        $message = ['type' => 'danger', 'text' => WARN_UNKNOWN_ERROR];
        $customer_id = $order->customer['customer_id'];
        if ($customer_id) {
            $manager->assign_customer($customer_id);
            $currencies = Yii::$container->get('currencies');
            $totals = \yii\helpers\Array_Helper::map($order->totals, 'class', 'value_inc_tax');
            $ot_paid_value = $totals['ot_paid'];
            $ot_total_value = $totals['ot_total'];
            $paid = $manager->get_total_collection()->get('ot_paid');
            $update_and_pay_amount = round($ot_total_value, 2) - round($ot_paid_value, 2);
            $customer = $manager->get_customers_identity();
            $customer->update_user_token();
            $token = $customer->get_customers_info()->get_token();
            if ($order->customer['email_address']) {
                $STORE_NAME = $platform_config->const_value('STORE_NAME');
                $STORE_OWNER_EMAIL_ADDRESS = $platform_config->const_value('STORE_OWNER_EMAIL_ADDRESS');
                $STORE_OWNER = $platform_config->const_value('STORE_OWNER');
                $email_params = [];
                $email_params['STORE_NAME'] = $STORE_NAME;
                $email_params['CUSTOMER_NAME'] = $order->customer['firstname'] . ' ' . $order->customer['lastname'];
                $email_params['ORDER_NUMBER'] = method_exists($order, 'getOrderNumber') ? $order->get_order_number() : $order->order_id;
                $email_params['REQUEST_MESSAGE'] = $currencies->format(abs($update_and_pay_amount));
                $email_params['REQUEST_URL'] = tep_catalog_href_link(FILENAME_ACCOUNT_HISTORY_INFO, 'action=payment_request&order_id=' . $order->order_id . '&email_address=' . $order->customer['email_address'] . '&token=' . $token, 'SSL', false);
                $email_params['CUSTOMER_FIRSTNAME'] = $order->customer['firstname'];
                $email_params['ORDER_DATE_LONG'] = strftime(DATE_FORMAT_LONG);
                $email_params['ORDER_DATE_LONG'] = strftime(DATE_FORMAT_LONG);
                if ($ext = \common\helpers\Acl::check_extension_allowed('DelayedDespatch', 'allowed')) {
                    $email_params['ORDER_DATE_LONG'] .= $ext::mail_info($order->info['delivery_date']);
                }
                $email_params['PRODUCTS_ORDERED'] = $order->get_products_html_for_email();
                $email_params['ORDER_TOTALS'] = '';
                $order_total_output = $manager->get_total_output(true, 'TEXT_EMAIL');
                $email_params['ORDER_TOTALS'] = \frontend\design\boxes\email\Order_Totals::widget(['params' => ['order_total_output' => $order_total_output, 'platform_id' => $order->info['platform_id']]]);
                $email_params['BILLING_ADDRESS'] = \common\helpers\Address::address_format($order->billing['format_id'], $order->billing, 0, '', '<br>');
                $email_params['DELIVERY_ADDRESS'] = '';
                if ($order->content_type != 'virtual') {
                    $email_params['DELIVERY_ADDRESS'] = \common\helpers\Address::address_format($order->delivery['format_id'], $order->delivery, 0, '', '<br>');
                    [$class, $method] = explode('_', $order->info['shipping_class']);
                    $shipping = $manager->get_shipping_collection()->get($class);
                    if (is_object($shipping)) {
                        $collect = $shipping->to_collect($method);
                        if ($collect && method_exists($shipping, 'getAdditionalOrderParams')) {
                            $email_params['DELIVERY_ADDRESS'] = $shipping->get_additional_order_params([], $order->order_id, $order->table_prefix);
                        }
                    }
                }
                $email_params['PAYMENT_METHOD'] = $order->info['payment_method'];
                $email_params['SHIPPING_METHOD'] = $order->info['shipping_method'];
                [$email_subject, $email_text] = \common\helpers\Mail::get_parsed_email_template('Request for payment', $email_params, -1, $order->info['platform_id']);
                \common\helpers\Mail::send($order->customer['firstname'] . ' ' . $order->customer['lastname'], $order->customer['email_address'], $email_subject, $email_text, $STORE_OWNER, $STORE_OWNER_EMAIL_ADDRESS, [], '', '', ['add_br' => 'no', 'platform_id' => $order->info['platform_id']]);
                //add message to history
                $message = ['type' => 'success', 'text' => NOTICE_EMAILS_SENT];
                $order->add_legend('Sent payment Request', $_SESSION['login_id']);
                Yii::$app->get('storage')->remove_all();
            }
        } else {
            $message['text'] = ERROR_NO_CUSTOMER_SELECTED;
        }
        echo json_encode($message);
        exit;
    }
    /**
     *
     * @return string
     */
    public function action_exchange_state_switch()
    {
        $this->layout = false;
        $order_id = Yii::$app->request->post('order_id', 0);
        $directory_id = Yii::$app->request->post('directory_id', 0);
        /**
         *  -1 - disable // add record to tracking table with -1 (incorrect) external id
         *   0 - export again  // clean up tracking table
         *   1 - exported
         *   2 - export error
         */
        $new_state = Yii::$app->request->post('new_state');
        if (is_numeric($new_state) && in_array((int) $new_state, [0, -1, 2])) {
            if ((int) $new_state == 0) {
                tep_db_query("DELETE FROM ep_holbi_soap_link_orders WHERE local_orders_id='" . (int) $order_id . "' and ep_directory_id='" . (int) $directory_id . "'");
                tep_db_query("DELETE FROM ep_order_issues WHERE orders_id='" . (int) $order_id . "' and ep_directory_id='" . (int) $directory_id . "'");
            } elseif ((int) $new_state == -1) {
                tep_db_query("DELETE FROM ep_holbi_soap_link_orders WHERE local_orders_id='" . (int) $order_id . "' and ep_directory_id='" . (int) $directory_id . "'");
                $d = ['local_orders_id' => (int) $order_id, 'remote_orders_id' => -1, 'track_remote_order' => 0, 'ep_directory_id' => (int) $directory_id];
                tep_db_perform('ep_holbi_soap_link_orders', $d);
            }
            return 'ok';
        }
        return 'fail';
    }
    public function action_exchange_export_now()
    {
        $this->layout = false;
        \common\helpers\Translation::init('admin/easypopulate');
        $result = ['status' => 'fail'];
        $order_ids = Yii::$app->request->post('order_id');
        $directory_id = Yii::$app->request->post('directory_id');
        //sleep(1);
        $order_ids = !is_array($order_ids) ? [$order_ids] : $order_ids;
        $order_ids = array_unique(array_map('intval', $order_ids));
        if (count($order_ids) == 0) {
            $result['messages'][] = 'Orders not selected';
        } else {
            ob_start();
            $ep_directory = \backend\models\EP\Directory::load_by_id($directory_id);
            $provider_name = $ep_directory->directory_config[0]['file_format'];
            $job_id = $ep_directory->touch_import_job($provider_name . '_ExportOrders_' . date('YmdHis'), 'configured', $provider_name . '\ExportOrders');
            $export_order_job = \backend\models\EP\Job::load_by_id($job_id);
            if ($export_order_job) {
                if (!is_array($export_order_job->job_configure)) {
                    $export_order_job->job_configure = [];
                }
                $export_order_job->job_configure['oneTimeJob'] = true;
                $export_order_job->job_configure['forceProcessOrders'] = $order_ids;
                $export_order_job->save_configure_state();
                $export_order_job->set_job_start_time(time());
                $messages = new Messages(['job_id' => $job_id, 'output' => 'db']);
                ob_start();
                try {
                    $messages->info('Run export manually');
                    $export_order_job->run($messages);
                    $result['status'] = 'ok';
                    $result['messages'] = $messages->get_messages();
                } catch (\Exception $ex) {
                    $result['messages'][] = $ex->get_message();
                }
                ob_end_flush();
                $export_order_job->job_finished();
            }
            ob_get_clean();
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            Yii::$app->response->data = $result;
            if (count($order_ids) == 1) {
                Yii::$app->response->data = array_merge(Yii::$app->response->data, ['exchange_info_block' => self::render_exchange_info($directory_id, $order_ids[0], true)]);
            }
        }
    }
    public static function render_exchange_info($directory_id, $order_id, $skip_header = false)
    {
        $issues = '';
        $remote_id = $status = 0;
        $directory_id = intval($directory_id);
        $order_id = intval($order_id);
        $get_issues_r = tep_db_query('SELECT epo.*, oi.status, oi.date_added, oi.issue_text ' . "FROM ep_holbi_soap_link_orders epo left join ep_order_issues oi on oi.orders_id='" . (int) $order_id . "' and oi.ep_directory_id='" . (int) $directory_id . "' " . "WHERE epo.local_orders_id='" . (int) $order_id . "' and epo.ep_directory_id='" . (int) $directory_id . "' and cfg_export_as = 'order'" . 'ORDER BY oi.date_added DESC ' . 'LIMIT 4');
        while ($get_issue = tep_db_fetch_array($get_issues_r)) {
            if ($issues == '') {
                $remote_id = $get_issue['remote_orders_id'];
                if (!empty($get_issue['remote_order_number'])) {
                    $remote_id = $get_issue['remote_order_number'];
                } elseif (!empty($get_issue['remote_guid']) && $remote_id == $order_id) {
                    $remote_id = $get_issue['remote_guid'];
                }
                $status = $get_issue['status'];
                $export_date = \common\helpers\Date::datetime_short($get_issue['date_added']);
            }
            if (!empty($get_issue['issue_text'])) {
                if ($issues == '') {
                    $issues .= '<span>Export issues:</span>';
                    $issues .= '<ol style="padding: 0 0 0 16px" class="js-exchange_try_again' . $directory_id . '">';
                }
                $issues .= '<li style="padding: 0; "> ' . $get_issue['issue_text'] . ' (' . \common\helpers\Date::datetime_short($get_issue['date_added']) . ')</li>';
            }
        }
        if ($issues != '') {
            $issues .= '</ol>';
        }
        if ($remote_id >= 0) {
            $ep_directory = \backend\models\EP\Directory::load_by_id($directory_id);
            if (!$skip_header) {
                $info = '<div class="cr-ord-cust cr-ord-cust-datasource" id="jsBlkExchangeInfo' . $directory_id . '">';
            } else {
                $info = '';
            }
            $info .= '<span>' . $ep_directory->directory . '</span>';
            if ($remote_id > 0) {
                $info .= '<div>';
                $info .= TEXT_EXTERNAL_ORDERS_ID . ' ' . $remote_id . '<br />';
                $info .= TEXT_DATE_ADDED . ' ' . $export_date;
                $info .= '</div>';
            }
            if ($status != 1) {
                //$info .= '<div class="cr-ord-cust cr-ord-cust-client-order-id" id="jsBlkSapInfo">';
                //$info .= '<span>'.TEXT_SAP_HEADING.'</span>';
                //echo '<p style="display:block;" class="">' . TEXT_SAP_EXPORT_MODE.' '.($order->info['sap_export_mode']=='auto'?TEXT_SAP_EXPORT_AUTO:TEXT_SAP_EXPORT_MANUAL).'</p>';
                $info .= '<p><button type="button" class="btn btn-1" id="js-exchange-export' . $directory_id . '" >' . TEXT_EXPORT . '</button></p>';
                if ($status == 2) {
                    $info .= '<p style="display:block;" class="js-exchange_try_again' . $directory_id . '">' . TEXT_ERROR_INTRO . ' <button type="button" id="exchange_try_again' . $directory_id . '" class="btn btn-2">' . IMAGE_RESET . '</button><br><small style="opacity:0.8;">' . TEXT_RESET_ERROR_NOTE . '</small></p>';
                }
                $info .= '<div style="padding: 0;margin: 0; font-weight: inherit; font-size: inherit; line-height: inherit;" id="jsBlkSapIssues' . $directory_id . "\">{$issues}</div>";
                $info .= '<p ' . ($status == 2 ? ' style="display:none;" ' : '') . ' class="js-exchange_on_off' . $directory_id . '"><input type="checkbox" ' . ($status == -1 ? ' checked="checked" ' : '') . ' value="1" id="exchange_export_switch' . $directory_id . '"> ' . TEXT_DISABLE_EXPORT . '</p>';
                if (!$skip_header) {
                    $info .= '</div>';
                }
                ob_start();
                ?>
            <script type="text/javascript">
                $(document).ready(function(){
                    $('#js-exchange-export<?php 
                echo $directory_id;
                ?>').on('click',function () {
                        bootbox.dialog({
                            message: '<div id="exchangeExportResult">Export in progress...</div>',
                            title: "<?php 
                echo $ep_directory->directory;
                ?>",
                            buttons: {
                                done:{
                                    label: "<?php 
                echo TEXT_BTN_OK;
                ?>",
                                    className: "btn-cancel"
                                }
                            }
                        })
                        .on('shown.bs.modal', function(){
                           $.post(
                              "<?php 
                echo Yii::$app->url_manager->create_url('orders/exchange-export-now');
                ?>",
                              [ {name:'order_id', value:'<?php 
                echo $order_id;
                ?>'}, {name:'directory_id', value:'<?php 
                echo $directory_id;
                ?>'}, {name:'page',value:'order-detail'} ],
                              function(data, status){
                                  if ( data.exchange_info_block && data.exchange_info_block != 'null') {
                                      $('#jsBlkExchangeInfo<?php 
                echo $directory_id;
                ?>').html(data.exchange_info_block);
                                  }
                                  /*if ( data.exchange_export_issues ) {
                                      $('#jsBlkExchangeIssues<?php 
                echo $directory_id;
                ?>').html(data.exchange_export_issues);
                                  }*/

                                  $('#exchangeExportResult').html('Complete');
                                  if ( data.messages && data.messages.length ) {
                                      $('#exchangeExportResult').html('');
                                      for(var i=0; i<data.messages.length;i++){
                                          $('#exchangeExportResult').append('<div>'+data.messages[i]+'</div>');
                                      }
                                  }
                              },
                              'json'
                          );

                        });
                    });
                    if (typeof setNewState !== 'function') {
                      setNewState = function(newState, onComplete, directoryId){
                          $.post(
                              "<?php 
                echo Yii::$app->url_manager->create_url('orders/exchange-state-switch');
                ?>",
                              [ {name:'order_id', value:'<?php 
                echo $order_id;
                ?>'}, {name:'directory_id', value:directoryId}, {name:'new_state', value:newState} ],
                              function(data, status){
                                  if ( data=='ok' )
                                      $('.js-exchange_try_again' + directoryId).remove();
                                  if ( typeof onComplete === 'function' ) onComplete(data);
                              }
                          );
                      };
                    }

                    $('#exchange_export_switch<?php 
                echo $directory_id;
                ?>').bootstrapSwitch({
                        onText: "<?php 
                echo defined('SW_ON') ? SW_ON : '';
                ?>",
                        offText: "<?php 
                echo defined('SW_OFF') ? SW_OFF : '';
                ?>",
                        onSwitchChange: function () {
                            if($(this).is(':checked')){
                                setNewState(-1, '', '<?php 
                echo $directory_id;
                ?>');
                            }else{
                                setNewState(0, '', '<?php 
                echo $directory_id;
                ?>');
                            }
                            bootbox.alert('<?php 
                echo str_replace(["'", "\n"], ["\\'", '\n'], TEXT_EXCHANGE_SWITCH_UPDATED);
                ?>');
                        }
                    });
                    $('#exchange_try_again<?php 
                echo $directory_id;
                ?>').on('click', function(){
                        setNewState(0, function(data){
                            if ( data=='ok' ) {
                                $('.js-exchange_on_off<?php 
                echo $directory_id;
                ?>').show();
                                bootbox.alert('<?php 
                echo str_replace(["'", "\n"], ["\\'", '\n'], TEXT_EXCHANGE_RESET_ERROR_OK);
                ?>');
                            }
                        }, '<?php 
                echo $directory_id;
                ?>');
                    });
                });
            </script>
            <?php 
                $info .= ob_get_clean();
            }
        }
        return $info;
    }
    public function action_print_label()
    {
        $this->view->errors = [];
        \common\helpers\Translation::init('admin/orders');
        \common\helpers\Translation::init('shipping');
        $orders_label_id = \Yii::$app->request->get('orders_label_id', 0);
        $orders_id = \Yii::$app->request->get('orders_id', 0);
        if ($orders_label_id > 0) {
            $o_label = \common\models\Orders_Label::find_one(['orders_label_id' => $orders_label_id, 'orders_id' => $orders_id]);
            if ($o_label) {
                [$label_module, $label_method] = array_pad(explode('_', $o_label->label_class, 2), 2, null);
                if ($label_module && $label_method) {
                    $class = 'common\modules\label\\' . $label_module;
                    if (class_exists($class) && is_subclass_of($class, Module_Label::class)) {
                        $label = new $class();
                        if ($label->without_settings($o_label)) {
                            $orders_label_id = 0;
                            $o_label->delete();
                        }
                    }
                }
            }
        }
        $action = \Yii::$app->request->get('action', '');
        $new_module_method = \Yii::$app->request->get('method', '');
        $all_methods = \Yii::$app->request->get('all_methods', 1);
        [$new_module, $new_method] = array_pad(explode('_', $new_module_method, 2), 2, null);
        $delivery_date = \Yii::$app->request->get('delivery_date', '');
        if ($action == 'set_delivery') {
            $delivery_date = \common\helpers\Date::check_input_date($delivery_date, false);
            $date = date_create_from_format(DATE_FORMAT_DATEPICKER_PHP, $delivery_date);
            tep_db_query('update ' . TABLE_ORDERS . " set delivery_date ='" . $date->format('Y-m-d') . "' where orders_id = '" . (int) $orders_id . "'");
            $manager = \common\services\Order_Manager::load_manager();
            $order = $manager->get_order_instance_with_id('\common\classes\Order', $orders_id);
            $order->add_legend('Invoice printed', $_SESSION['login_id']);
            unset($order);
            unset($manager);
        }
        if ($orders_label_id > 0 && !empty($new_module) && !empty($new_method)) {
            $o_label = \common\models\Orders_Label::find_one(['orders_label_id' => $orders_label_id, 'orders_id' => $orders_id]);
            $o_label->label_class = $new_module_method;
            $class = 'common\modules\label\\' . $new_module;
            if (class_exists($class) && is_subclass_of($class, 'common\classes\modules\ModuleLabel')) {
                $label_obj = new $class();
                $extra_params = $label_obj->extract_extra_params_values(\Yii::$app->request->get());
                if (!empty($extra_params)) {
                    $o_label->extra_params = json_encode($extra_params);
                }
            }
            $o_label->save(false);
        }
        if ($action == 'save_label_products') {
            $selected_products = Yii::$app->request->get('selected_products', []);
            $selected_products_qty = Yii::$app->request->get('selected_products_qty', []);
            $selected_products_qty_max = Yii::$app->request->get('selected_products_qty_max', []);
            if ($orders_label_id > 0) {
                //$oLabel = \common\models\OrdersLabel::findOne(['orders_label_id' => $orders_label_id, 'orders_id' => $orders_id]);
            } else {
                $o_label = new \common\models\Orders_Label();
                $o_label->orders_id = $orders_id;
                $o_label->insert();
                $orders_label_id = $o_label->orders_label_id;
            }
            Yii::$app->db->create_command()->delete(TABLE_ORDERS_LABEL_TO_ORDERS_PRODUCTS, ['orders_label_id' => $orders_label_id, 'orders_id' => $orders_id])->execute();
            foreach ($selected_products as $orders_products_id) {
                $selected_qty = min($selected_products_qty[$orders_products_id], $selected_products_qty_max[$orders_products_id]);
                if ($selected_qty > 0) {
                    Yii::$app->db->create_command()->insert(TABLE_ORDERS_LABEL_TO_ORDERS_PRODUCTS, ['orders_label_id' => $orders_label_id, 'orders_id' => $orders_id, 'orders_products_id' => $orders_products_id, 'products_quantity' => $selected_qty])->execute();
                }
            }
        }
        $manager = \common\services\Order_Manager::load_manager();
        $order = $manager->get_order_instance_with_id('\common\classes\Order', $orders_id);
        Yii::$app->get('platform')->config($order->info['platform_id'])->constant_up();
        $manager->set('platform_id', $order->info['platform_id']);
        [$module, $method] = explode('_', $order->info['shipping_class']);
        $shipping_modules = $manager->get_shipping_collection();
        $shipping = $shipping_modules->get($module);
        $this->view->methods = [];
        if ($order->can_be_delivered()) {
            if (method_exists($shipping, 'checkDeliveryDate')) {
                if ($shipping->need_delivery_date() && false === $shipping->check_delivery_date($order->info['delivery_date'])) {
                    return $this->render_partial('print-label-date.tpl', ['orders_id' => $orders_id, 'all_methods' => $all_methods]);
                }
            }
            if ($orders_label_id > 0) {
                $o_label = \common\models\Orders_Label::find_one(['orders_label_id' => $orders_label_id, 'orders_id' => $orders_id]);
                [$label_module, $label_method] = array_pad(explode('_', $o_label->label_class ?? '', 2), 2, '');
                if (!empty($label_module) && !empty($label_method)) {
                    $class = 'common\modules\label\\' . $label_module;
                    if (class_exists($class) && is_subclass_of($class, 'common\classes\modules\ModuleLabel')) {
                        $label = new $class();
                        if ($action == 'delete' && !$label->shipment_exists($orders_id, $orders_label_id)) {
                            $o_label->delete();
                            echo '<script type="text/javascript"> setTimeout(function(){ $.get("' . \Yii::$app->url_manager->create_url(['orders/process-order', 'orders_id' => $orders_id]) . '", function(data) { $("#order_management_data").html(data.content); },"json"); },100); </script>';
                            $this->view->errors = ['The label has been canceled.'];
                            $this->layout = false;
                            return $this->render('print-label.tpl', ['orders_id' => $orders_id]);
                            exit;
                        }
                        if ($action == 'cancel' && $label->shipment_exists($orders_id, $orders_label_id)) {
                            $result = $label->cancel_shipment($orders_id, $orders_label_id);
                            if (tep_not_null($result['success'])) {
                                echo '<script type="text/javascript"> setTimeout(function(){ $.get("' . \Yii::$app->url_manager->create_url(['orders/process-order', 'orders_id' => $orders_id]) . '", function(data) { $("#order_management_data").html(data.content); },"json"); },100); </script>';
                                $this->view->errors = [$result['success']];
                            } elseif (is_array($result['errors']) && count($result['errors']) > 0) {
                                $this->view->errors = $result['errors'];
                            }
                            $this->layout = false;
                            return $this->render('print-label.tpl', ['orders_id' => $orders_id]);
                            exit;
                        }
                        $methods = $label->get_methods($order->delivery['country']['iso_code_2'], $label_method, $order->info['shipping_weight'], method_exists($shipping, 'calc_order_num_of_sheets') ? $shipping->calc_order_num_of_sheets($orders_id) : '');
                        if (isset($methods[$label_module . '_' . $label_method])) {
                            if ($action == 'update' && $label->shipment_exists($orders_id, $orders_label_id)) {
                                $result = $label->update_shipment($orders_id, $orders_label_id);
                            } else {
                                $result = $label->create_shipment($orders_id, $orders_label_id, $label_method);
                                $order->add_legend('Print Label created', $_SESSION['login_id']);
                            }
                            if ($ext = \common\helpers\Acl::check_extension_allowed('Ebay', 'allowed')) {
                                $ext::set_update_order($orders_id);
                            }
                            if ($ext = \common\helpers\Acl::check_extension_allowed('Amazon', 'allowed')) {
                                $ext::set_update_order($orders_id);
                            }
                            if (tep_not_null($result['tracking_number']) && Yii::$app->request->is_ajax) {
                                echo '<script type="text/javascript"> setTimeout(function(){ $.get("' . \Yii::$app->url_manager->create_url(['orders/process-order', 'orders_id' => $orders_id]) . '", function(data) { $("#order_management_data").html(data.content); },"json"); },100); </script>';
                            }
                            if (tep_not_null($result['parcel_label'])) {
                                if (Yii::$app->request->is_ajax) {
                                    if ($result['parcel_label_format'] == 'vnd.zebra-zpl') {
                                        echo $this->render_partial('label/label-zpl.tpl', ['orders_id' => $orders_id, 'orders_label_id' => $orders_label_id, 'parcel_label' => $result['parcel_label']]);
                                        exit;
                                    }
                                    echo $this->render_partial('label/label-info.tpl', ['text' => TEXT_PLEASE_WAIT]);
                                    echo '<script type="text/javascript">
                                                var pop = $(".pop-up-close:last");
                                                pop.on("click", function() {
                                                  $.get("' . \Yii::$app->url_manager->create_url(['orders/process-order', 'orders_id' => $orders_id]) . '", function(data) { $("#order_management_data").html(data.content); },"json");
                                                } );
                                                window.location.href = "' . \Yii::$app->url_manager->create_url(['orders/print-label', 'orders_id' => $orders_id, 'orders_label_id' => $orders_label_id]) . '";
                                                pop.trigger("click");
                                          </script>';
                                    // echo '<script type="text/javascript"> setTimeout(function(){ $.get("' . \Yii::$app->urlManager->createUrl(['orders/process-order', 'orders_id' => $orders_id]) . '", function(data) { $("#order_management_data").html(data.content); },"json"); },100); </script>';
                                } elseif ($result['parcel_label_format'] == 'html') {
                                    header('Content-type: text/html');
                                    header('Content-disposition: attachment; filename=parcel_label_' . $orders_id . '.html');
                                    echo $result['parcel_label'];
                                } elseif ($result['parcel_label_format'] == 'vnd.eltron-epl') {
                                    header('Content-type: text/vnd.eltron-epl');
                                    header('Content-disposition: attachment; filename=parcel_label_' . $orders_id . '.epl');
                                    echo $result['parcel_label'];
                                } elseif ($result['parcel_label_format'] == 'vnd.zebra-zpl') {
                                    header('Content-type: text/vnd.zebra-zpl');
                                    header('Content-disposition: attachment; filename=parcel_label_' . $orders_id . '.zpl');
                                    echo $result['parcel_label'];
                                } else {
                                    header('Content-type: application/pdf');
                                    header('Content-disposition: attachment; filename=parcel_label_' . $orders_id . '.pdf');
                                    echo $result['parcel_label'];
                                }
                                exit;
                            } else {
                                /*if (is_array($result['errors']) && count($result['errors']) > 0) {
                                      $this->view->errors = $result['errors'];
                                  }/**/
                                echo $this->render_partial('label/label-info.tpl', ['text' => is_array($result['errors']) ? implode('<br>', $result['errors']) : $result['errors']]);
                                echo '<script type="text/javascript">
                                                var pop = $(".pop-up-close:last");
                                                pop.on("click", function() {
                                                  $.get("' . \Yii::$app->url_manager->create_url(['orders/process-order', 'orders_id' => $orders_id]) . '", function(data) { $("#order_management_data").html(data.content); },"json");
                                                } );
                                          </script>';
                                die;
                            }
                        }
                    }
                }
            } else {
                $orders_products = [];
                $__order_products = $order->products;
                if (Product_Name_Decorator::instance()->use_internal_name_for_order()) {
                    $__order_products = Product_Name_Decorator::instance()->get_updated_order_products($__order_products, $order->info['language_id'], $order->info['platform_id']);
                }
                $__order_products = \common\helpers\Product::remove_order_sub_products($__order_products);
                for ($i = 0, $n = sizeof($__order_products); $i < $n; $i++) {
                    $orders_products[$__order_products[$i]['orders_products_id']] = $__order_products[$i];
                    $orders_products[$__order_products[$i]['orders_products_id']]['selected'] = true;
                }
                $already_selected_products_query = (new \yii\db\Query())->select('orders_products_id, products_quantity')->from(TABLE_ORDERS_LABEL_TO_ORDERS_PRODUCTS)->where(['orders_id' => $orders_id])->all();
                foreach ($already_selected_products_query as $already_selected_products) {
                    if ($orders_products[$already_selected_products['orders_products_id']]['qty'] > $already_selected_products['products_quantity']) {
                        $orders_products[$already_selected_products['orders_products_id']]['qty'] -= $already_selected_products['products_quantity'];
                    } else {
                        unset($orders_products[$already_selected_products['orders_products_id']]);
                    }
                }
                $auto_send_form = false;
                if (is_array($orders_products) && count($orders_products) === 1) {
                    $product = array_values($orders_products)[0];
                    if (array_key_exists('qty', $product) && (int) $product['qty'] === 1) {
                        $auto_send_form = true;
                    }
                }
                return $this->render_partial('print-label-products.tpl', ['orders_id' => $orders_id, 'orders_label_id' => $orders_label_id, 'orders_products' => $orders_products, 'autoSendForm' => $auto_send_form]);
            }
            $shipping_labels = [];
            if (is_object($shipping)) {
                $shipping_labels = $shipping->get_preferred_labels();
            }
            if ($all_methods || empty($shipping_labels)) {
                $labels = \common\helpers\Modules::get_labels_list($order->info['platform_id']);
                if (count($shipping_labels) > 0) {
                    $labels = array_unique(array_merge($shipping_labels, $labels));
                }
            } else {
                $labels = $shipping_labels;
            }
            $auto_selected_label = '';
            /** @var \common\extensions\ShippingCarrierPick\ShippingCarrierPick $ext */
            if ($ext = \common\helpers\Extensions::is_allowed('ShippingCarrierPick')) {
                $auto_selected_label = $ext::suggest_on_order($order);
                if (!empty($auto_selected_label)) {
                    if (strpos($auto_selected_label, '_') !== false) {
                        [$auto_selected_label_class, $auto_selected_label_method] = explode('_', $auto_selected_label, 2);
                        if (!in_array($auto_selected_label_class, $labels)) {
                            $labels[] = $auto_selected_label_class;
                        }
                    } else {
                        $labels[] = $auto_selected_label;
                    }
                }
            }
            $_selected_accordion = false;
            foreach ($labels as $class) {
                $namespace_module_class = 'common\modules\label\\' . $class;
                if (class_exists($namespace_module_class) && is_subclass_of($namespace_module_class, 'common\classes\modules\ModuleLabel')) {
                    $label = new $namespace_module_class();
                    $extra_params = '';
                    if (method_exists($label, 'getExtraParams')) {
                        $extra_params = trim($label->get_extra_params($order, $orders_label_id > 0 ? $orders_label_id : $o_label->orders_label_id));
                    }
                    $methods = $label->get_methods($order->delivery['country']['iso_code_2'], $new_method, $order->info['shipping_weight'], method_exists($shipping, 'calc_order_num_of_sheets') ? $shipping->calc_order_num_of_sheets($orders_id) : '', $orders_label_id > 0 ? $orders_label_id : $o_label->orders_label_id);
                    $this->view->methods[] = ['title' => $label->title, 'accordion' => strpos($auto_selected_label, $class . '_') === 0, 'selected' => $auto_selected_label, 'methods' => $methods, 'extraParams' => $extra_params];
                    $_selected_accordion = $_selected_accordion || strpos($auto_selected_label, $class . '_') === 0;
                }
            }
            if (!$_selected_accordion && count($this->view->methods) > 0) {
                $this->view->methods[0]['accordion'] = true;
            }
        } else {
            $this->view->errors = ['The shipping module (' . $module . ') was not found.'];
        }
        $this->layout = false;
        return $this->render('print-label.tpl', ['orders_id' => $orders_id, 'orders_label_id' => $orders_label_id, 'all_methods' => $all_methods, 'hypashipTracking' => $result ?? null]);
    }
    public function action_make_order_label()
    {
        Translation::init('admin/orders');
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        /** @var \common\extensions\ShippingCarrierPick\ShippingCarrierPick $ext */
        $ext = \common\helpers\Extensions::is_allowed('ShippingCarrierPick');
        if (!$ext) {
            Yii::$app->response->data = ['status' => 'error', 'message' => 'Batch shipping label not allowed'];
            return;
        }
        $orders_id = (int) Yii::$app->request->post('order_id', 0);
        Yii::$app->response->data = $ext::make_order_label($orders_id);
    }
    public function action_hold_on()
    {
        \common\helpers\Translation::init('admin/orders');
        $this->layout = false;
        $orders_id = Yii::$app->request->get('orders_id', 0);
        $order_model = \common\models\Orders::find_one($orders_id);
        if (Yii::$app->request->is_post) {
            $hold_on_date = Yii::$app->request->post('hold_on_date', '');
            if (empty($hold_on_date)) {
                $hold_on_date = null;
            }
            $order_model->set_attribute('hold_on_date', $hold_on_date);
            $update_history = false;
            if ($order_model->is_attribute_changed('hold_on_date')) {
                $update_history = true;
            }
            $order_model->save();
            $order_model->refresh();
            if ($update_history) {
                $order = new \common\classes\Order($orders_id);
                if (empty($hold_on_date)) {
                    $order->add_admin_comment('Hold on date cleared.', (int) $_SESSION['login_id']);
                } else {
                    $order->add_admin_comment('Hold on date changed: ' . $hold_on_date, (int) $_SESSION['login_id']);
                }
            }
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            Yii::$app->response->data = ['status' => 'ok', 'hold_on_date' => $order_model->hold_on_date];
            return;
        }
        return $this->render('hold-on.tpl', ['updateUrl' => \Yii::$app->url_manager->create_url(['orders/hold-on', 'orders_id' => $orders_id]), 'currentHoldOnDate' => $order_model->hold_on_date]);
    }
    /**
     * @deprecated
     * @return type
     */
    public function action_transactions()
    {
        \common\helpers\Translation::init('admin/orders');
        $order_id = Yii::$app->request->get('orders_id');
        if ($order_id) {
            /** @var \common\services\OrderManager $manager */
            $manager = \common\services\Order_Manager::load_manager();
            $order = $manager->get_order_instance_with_id('\common\classes\Order', $order_id);
            $manager->set_modules_visibility(['admin']);
            Yii::$app->get('platform')->config($order->info['platform_id'])->constant_up();
            $manager->set_render_path('\backend\design\orders\\');
            $data = ['type' => 'full'];
            $format = 'html';
            $response = [];
            if (Yii::$app->request->is_post) {
                $format = 'json';
                $_action = Yii::$app->request->post('action');
                if ($_action == 'get_children') {
                    $data = ['type' => 'children', 'statuses' => ['parent' => Yii::$app->request->post('parent')]];
                } elseif ($_action == 'check_server_refunds') {
                    $data = ['type' => 'children'];
                    $orders_transactions = Yii::$app->request->post('orders_transactions');
                    if (!is_array($orders_transactions)) {
                        $orders_transactions = [$orders_transactions];
                    }
                    $data['statuses'] = $manager->get_transaction_manager()->get_transactions_status($orders_transactions);
                    echo json_encode($data);
                    exit;
                } elseif ($_action == 'get_fields') {
                    $payment = $manager->get_payment_collection()->get(Yii::$app->request->post('payment_class'), true);
                    if ($payment) {
                        $t_manager = $manager->get_transaction_manager($payment);
                        return $manager->render('payments\PaymentFields', ['manager' => $manager, 'rules' => $t_manager->get_fields()], 'json');
                    }
                    exit;
                } elseif ($_action == 'search_transactions') {
                    $class = Yii::$app->request->post('payment_class');
                    $payment = $manager->get_payment_collection()->get($class, true);
                    if ($payment) {
                        $t_manager = $manager->get_transaction_manager($payment);
                        if ($t_manager->prepare_query(Yii::$app->request->post())) {
                            $transactions = $t_manager->execute_query();
                            $response['transactions'] = $manager->render('FoundTransactionsList', ['manager' => $manager, 'transactions' => $transactions, 'payment' => $class]);
                        } else {
                            $response['errors'] = $t_manager->get_errors();
                        }
                    }
                    echo json_encode($response);
                    exit;
                } elseif ($_action == 'assign_transaction') {
                    $transaction_id = Yii::$app->request->post('transaction_id');
                    if ($transaction_id) {
                        $class = Yii::$app->request->post('payment_class');
                        $payment = $manager->get_payment_collection()->get($class, true);
                        if ($payment) {
                            /** @var \common\services\PaymentTransactionManager $tManager */
                            $t_manager = $manager->get_transaction_manager($payment);
                            $transaction = $t_manager->get_transaction($transaction_id);
                            if (!$transaction) {
                                if ($t_manager->add_transaction($transaction_id, 'undefined', 0, null, 'Manually assigned transaction')) {
                                    $payment->get_transaction_details($transaction_id, $t_manager);
                                    $transaction = $t_manager->get_transaction($transaction_id);
                                    $t_manager->link_local_transaction($transaction_id);
                                    $response['message'] = [TEXT_MESSEAGE_SUCCESS_ADDED];
                                    $response['done'] = $transaction->orders_transactions_id;
                                } else {
                                    $response['errors'] = ['Transaction already assigned to another order'];
                                }
                            } else {
                                $response['errors'] = ['Transaction already assigned'];
                            }
                        }
                    }
                    echo json_encode($response);
                    exit;
                } elseif ($_action == 'unlink_transaction') {
                    $transaction_orders_id = Yii::$app->request->post('transaction_orders_id');
                    $t_manager = $manager->get_transaction_manager();
                    $t_manager->unlink_transaction_by_id($transaction_orders_id);
                    $response = [];
                    echo json_encode($response);
                    exit;
                } elseif (in_array($_action, ['make_void', 'make_refund'])) {
                    //return per transaction
                    $transaction_orders_id = Yii::$app->request->post('transaction_orders_id');
                    $t_manager = $manager->get_transaction_manager();
                    $tr = $t_manager->get_transaction_by_id($transaction_orders_id);
                    if ($tr) {
                        $payment = $manager->get_payment_collection()->get($tr->payment_class, true);
                        if ($payment) {
                            $t_manager->use_payment($payment);
                            if ($_action == 'make_void') {
                                $payment_response = $t_manager->payment_void($tr->transaction_id);
                            } else {
                                $amount = Yii::$app->request->post('amount', 0);
                                if (number_format($tr->transaction_amount, 2) == number_format($amount, 2)) {
                                    $amount = 0;
                                }
                                $payment_response = $t_manager->payment_refund($tr->transaction_id, $amount);
                            }
                            if ($payment_response) {
                                // ORDER CANCELLATION IF NEEDED
                                $order = $manager->get_order_instance();
                                $totals = \yii\helpers\Array_Helper::map($order->totals, 'code', 'value_inc_tax');
                                $paid = (float) ($totals['ot_paid'] ?? 0);
                                $refund = (float) ($totals['ot_refund'] ?? 0);
                                $order_status = false;
                                if ($refund >= $paid - 0.01) {
                                    if (method_exists($payment, 'refundOrderStatus')) {
                                        $order_status = $payment->refund_order_status();
                                    } else {
                                        $order_status = \common\models\Orders_Status::get_default_by_order_evaluation_state(\common\helpers\Order::OES_CANCELLED);
                                    }
                                } else if (method_exists($payment, 'partialRefundOrderStatus')) {
                                    $order_status = $payment->partial_refund_order_status();
                                } else {
                                    $order_status = \common\models\Orders_Status::get_default_by_order_evaluation_state(\common\helpers\Order::OES_PARTIAL_CANCELLED);
                                }
                                if (is_object($order_status)) {
                                    \common\helpers\Order::set_status($order->order_id, $order_status->orders_status_id, [], false, true);
                                }
                                unset($totals);
                                unset($refund);
                                unset($paid);
                                // EOF ORDER CANCELLATION IF NEEDED
                                $response['statuses'] = $t_manager->get_transactions_status([$transaction_orders_id]);
                            } else {
                                //need error status
                            }
                        }
                    }
                    echo json_encode($response);
                    exit;
                } elseif ($_action == 'return_by_credit') {
                    //return by full credit, may have several transactions
                    $transaction_data = Yii::$app->request->post('transaction_data', []);
                    $full_returning_amount = $transaction_data['amount'];
                    $log = [];
                    $hide = [];
                    $doc_id = 0;
                    if ($full_returning_amount) {
                        if (is_array($transaction_data['to_return'])) {
                            $t_manager = $manager->get_transaction_manager();
                            //                            $tManager->stopPropagination();
                            $completed = false;
                            $fully_completed = true;
                            $returned_amount = 0;
                            $children = [];
                            $log[] = TEXT_LOG_REFUND_START;
                            foreach ($transaction_data['to_return'] as &$transaction) {
                                if ($returned_amount >= $full_returning_amount) {
                                    $log[] = TEXT_LOG_REFUND_AMOUNT_LIMIT;
                                    break;
                                }
                                $tr = $t_manager->get_transaction_by_id($transaction['transaction_orders_id']);
                                if ($tr) {
                                    $transaction['returning_amount'] = round($transaction['returning_amount'], 2);
                                    $payment = $manager->get_payment_collection()->get($tr->payment_class, true);
                                    $t_manager->use_payment($payment);
                                    if ($t_manager->can_payment_void($tr->transaction_id)) {
                                        if ($t_manager->payment_void($tr->transaction_id)) {
                                            $log[] = sprintf(TEXT_LOG_REFUND_SUCCESSFUL, $tr->transaction_id);
                                            $child = $tr->get_last_childtransaction();
                                            if ($child) {
                                                $children[] = $child;
                                                $returned_amount += $transaction['returning_amount'];
                                                $transaction['success'] = true;
                                            }
                                        } else {
                                            $log[] = sprintf(TEXT_LOG_REFUND_ERROR, $tr->transaction_id);
                                        }
                                    } elseif ($t_manager->payment_refund($tr->transaction_id, $transaction['returning_amount'])) {
                                        $log[] = sprintf(TEXT_LOG_REFUND_SUCCESSFUL, $tr->transaction_id);
                                        $child = $tr->get_last_childtransaction();
                                        if ($child) {
                                            $children[] = $child;
                                            $returned_amount += $transaction['returning_amount'];
                                            $transaction['success'] = true;
                                        }
                                    } else {
                                        $log[] = sprintf(TEXT_LOG_REFUND_ERROR, $tr->transaction_id);
                                    }
                                }
                                $fully_completed = $fully_completed && $transaction['success'];
                                $completed = $completed || $transaction['success'];
                                if ($transaction['success']) {
                                    $hide[] = $tr->orders_transactions_id;
                                    //$tr->transaction_id;
                                }
                            }
                            $parent_invoice_id = null;
                            $doc_id = $t_manager->finalize_refunding($parent_invoice_id, $children, $returned_amount);
                            if ($fully_completed) {
                                $log[] = TEXT_LOG_REFUND_COMPLETE;
                            } elseif ($completed) {
                                $log[] = TEXT_LOG_REFUND_IMCOMPLETE;
                            } else {
                                $log[] = TEXT_LOG_REFUND_PROCESS_ERROR;
                            }
                        }
                    }
                    $currencies = Yii::$container->get('currencies');
                    $response = ['log' => $log, 'returned_amount' => $currencies->format($returned_amount, true, $order->info['currency'], $order->info['currency_value']), 'cn_id' => $doc_id, 'hide' => $hide];
                    echo json_encode($response);
                    exit;
                }
            }
            return $manager->render('Transactions', ['manager' => $manager, 'orders_id' => $order_id, 'data' => $data], $format);
        }
        exit;
    }
    /**
     * transactional payments actions
     * @return type
     */
    public function action_p_transactions()
    {
        $update_status_and_notify = true;
        \common\helpers\Translation::init('admin/orders');
        $order_id = Yii::$app->request->get('orders_id');
        $ret = ['status' => 'fail', 'message' => TEXT_MESSAGE_ERROR];
        /** @var \common\services\OrderManager $manager */
        $manager = \common\services\Order_Manager::load_manager();
        $manager->set_render_path('\backend\design\orders\\');
        /** @var common\classes\Order $order */
        $order = $manager->get_order_instance_with_id('\common\classes\Order', $order_id);
        if ($order_id && Yii::$app->request->is_post && is_object($order)) {
            ///$manager->setModulesVisibility(['admin']);
            \Yii::$app->get('platform')->config($order->info['platform_id'])->constant_up();
            $_action = Yii::$app->request->post('action');
            switch ($_action) {
                case 'make_void':
                case 'make_refund':
                case 'make_capture':
                case 'make_reauthorize':
                    $method = str_replace('make_', '', $_action);
                    $op_id = Yii::$app->request->post('op_id', 0);
                    $amount = (float) Yii::$app->request->post('amount', 0);
                    $data = \common\helpers\Order_Payment::get_record($op_id);
                    if (!$data || in_array($_action, ['make_refund']) && !in_array($data->orders_payment_status, [\common\helpers\Order_Payment::OPYS_SUCCESSFUL]) || in_array($_action, ['make_void', 'make_reauthorize']) && !in_array($data->orders_payment_status, [\common\helpers\Order_Payment::OPYS_PENDING, \common\helpers\Order_Payment::OPYS_PROCESSING]) || in_array($_action, ['make_capture']) && !in_array($data->orders_payment_status, [\common\helpers\Order_Payment::OPYS_PENDING, \common\helpers\Order_Payment::OPYS_PROCESSING, \common\helpers\Order_Payment::OPYS_SUCCESSFUL])) {
                        $ret['message'] = TEXT_MESSAGE_ERROR_INCORRECT_TRANSACTION;
                    } elseif ($amount <= 0.01 && !in_array($method, ['void'])) {
                        $ret['message'] = TEXT_MESSAGE_ERROR_INCORRECT_AMOUNT;
                    } else {
                        $class = $data->orders_payment_module;
                        $builder = new \common\classes\modules\Module_Builder($manager);
                        $class = $builder(['class' => "\\common\\modules\\orderPayment\\{$class}"]);
                        $tmp = 'can' . ucfirst($method);
                        if (is_object($class) && method_exists($class, $tmp) && $class->{$tmp}($data->orders_payment_transaction_id)) {
                            $tmp = $class->{$method}($data->orders_payment_transaction_id, $amount);
                            if ($tmp === true) {
                                $ret = ['status' => 'OK'];
                                //all other - not related to transaction
                                /*in payment module (now?)
                                                                                  $updated = $order->updatePaidTotals();
                                
                                                                                  if ($updated) { //update order status and notify customer if required
                                                                                    $status = '';
                                                                                    if (isset($updated['paid']) ) {
                                                                                      //if ($updated['details']['status']>0) {// has due
                                                                                      if (abs(
                                                                                          round($updated['details']['total'], 2)-
                                                                                          round($updated['details']['debit'], 2)
                                                                                          ) < 0.01) {
                                                                                        $status = $class->paidOrderStatus();
                                                                                      } else {
                                                                                        $status = $class->partlyPaidOrderStatus();
                                                                                      }
                                                                                    } elseif (isset($updated['refund']) && $updated['details']['credit']>0) {
                                                                                      if (abs(
                                                                                          round($updated['details']['total'], 2)-
                                                                                          round($updated['details']['credit'], 2)
                                                                                          ) < 0.01) {
                                                                                        $status = $class->refundOrderStatus();
                                                                                      } else {
                                                                                        $status = $class->partialRefundOrderStatus();
                                                                                      }
                                                                                    }
                                
                                                                                    if ($updateStatusAndNotify && !empty($status) && $status != $order->info['order_status']) {
                                                                                      $order->update_status_and_notify($status);
                                                                                    }
                                
                                                                                  }*/
                            } elseif (is_string($tmp)) {
                                $ret['message'] = $tmp;
                            }
                        }
                    }
                    break;
                case 'make_delete':
                    $method = str_replace('make_', '', $_action);
                    $op_id = Yii::$app->request->post('op_id', 0);
                    $data = \common\helpers\Order_Payment::get_record($op_id);
                    if (!$data) {
                        $ret['message'] = TEXT_MESSAGE_ERROR_INCORRECT_TRANSACTION;
                    } elseif ($data['orders_payment_admin_create'] == 0 || !in_array($data['orders_payment_status'], [\common\helpers\Order_Payment::OPYS_PENDING, \common\helpers\Order_Payment::OPYS_DISCOUNTED]) || \common\helpers\Order_Payment::has_children($data['orders_payment_id'])) {
                        $ret['message'] = TEXT_MESSAGE_ERROR_TRANSACTION_CANT_DELETE;
                    } else {
                        $data->delete();
                        $ret = ['status' => 'OK'];
                    }
                    break;
                case 'get_fields':
                    $payment = $manager->get_payment_collection()->get(Yii::$app->request->post('payment_class'), true);
                    if ($payment) {
                        $t_manager = $manager->get_transaction_manager($payment);
                        return $manager->render('payments\PaymentFields', ['manager' => $manager, 'rules' => $t_manager->get_fields()], 'json');
                    }
                    break;
                case 'search_transactions':
                    $class = Yii::$app->request->post('payment_class');
                    $payment = $manager->get_payment_collection()->get($class, true);
                    if ($payment) {
                        $t_manager = $manager->get_transaction_manager($payment);
                        if ($t_manager->prepare_query(Yii::$app->request->post())) {
                            $transactions = $t_manager->execute_query();
                            $url = Yii::$app->url_manager->create_url(['orders/p-transactions', 'orders_id' => $order_id, 'platform_id' => $order->info['platform_id']]);
                            $ret['transactions'] = $this->render_ajax('payment-found-list', ['url' => $url, 'transactions' => $transactions, 'payment' => $payment->code]);
                            //$manager->render('FoundTransactionsList', ['manager' => $manager, 'transactions' => $transactions, 'payment' => $class ]);
                        } else {
                            $ret['errors'] = $t_manager->get_errors();
                        }
                    }
                    break;
                case 'assign_transaction':
                    //vl2do
                    $transaction_id = Yii::$app->request->post('transaction_id', false);
                    $type = Yii::$app->request->post('type', '');
                    if ($transaction_id) {
                        $class = Yii::$app->request->post('payment_class');
                        $payment = $manager->get_payment_collection()->get($class, true);
                        //$builder = new \common\classes\modules\ModuleBuilder($manager);
                        //$payment = $builder(['class' => "\\common\\modules\\orderPayment\\{$class}"]);
                        if ($payment) {
                            // check if already added
                            $op = \common\helpers\Order_Payment::search_record($class, $transaction_id);
                            if ($op && !empty($op->orders_payment_id)) {
                                $ret['errors'] = [TEXT_ERROR_PAYMENT_EXISTS];
                            } elseif ($op) {
                                /** @var \common\services\PaymentTransactionManager $tManager */
                                $t_manager = $manager->get_transaction_manager($payment);
                                try {
                                    $op->set_attributes(['orders_payment_order_id' => $order_id, 'orders_payment_module' => $class, 'orders_payment_module_name' => $payment->title, 'orders_payment_status' => \common\helpers\Order_Payment::OPYS_PENDING, 'orders_payment_amount' => 0, 'orders_payment_currency' => $order->info['currency'], 'orders_payment_transaction_id' => $transaction_id, 'payment_type' => $type, 'orders_payment_transaction_commentary' => 'Assigned transaction id', 'orders_payment_transaction_date' => date(\common\helpers\Date::DATABASE_DATETIME_FORMAT)]);
                                    $op->save(false);
                                } catch (\Exception $e) {
                                    $ret['errors'] = [$e->get_message()];
                                }
                                if ($op && !empty($op->orders_payment_id)) {
                                    $res = false;
                                    if (is_object($payment)) {
                                        $res = \common\helpers\Order_Payment::update_transaction_details($op, $payment, $manager, false);
                                    }
                                    if ($res !== true) {
                                        if (!empty($res)) {
                                            $ret['message'] = [$res];
                                        }
                                    } else {
                                        $ret['message'] = [TEXT_MESSEAGE_SUCCESS_ADDED];
                                        $ret['done'] = $op->orders_payment_id;
                                    }
                                }
                            }
                        }
                    }
                    break;
            }
        }
        $this->layout = false;
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $ret;
    }
    public function action_credit_notes()
    {
        $cn_id = Yii::$app->request->get('cnId');
        $orders_id = Yii::$app->request->get('orders_id');
        if ($orders_id) {
            $manager = \common\services\Order_Manager::load_manager();
            /** @var \common\services\SplitterManager  $splitter */
            $splitter = $manager->get_order_splitter();
            $credit_notes = $splitter->get_instances_from_splinters($orders_id, $splitter::STATUS_RETURNED, $cn_id);
            if (empty($credit_notes)) {
                $credit_notes = $splitter->get_instances_from_splinters($orders_id, $splitter::STATUS_RETURNING, $cn_id);
            }
            if ($credit_notes) {
                $languages_id = Yii::$app->settings->get('languages_id');
                $currencies = \Yii::$container->get('currencies');
                $credit_note = array_pop($credit_notes);
                $platform_id = $credit_note->info['platform_id'] ?? \common\classes\platform::default_id();
                $__platform = Yii::$app->get('platform');
                $platform_config = $__platform->config($platform_id);
                if ($platform_config->is_virtual()) {
                    $detected = false;
                    if ($ext = \common\helpers\Acl::check_extension_allowed('AdditionalPlatforms', 'allowed')) {
                        $_plid = $ext::get_virtual_sattelit_id($platform_id);
                        if ($_plid) {
                            $platform_id = $_plid;
                            $detected = true;
                        }
                    }
                    if (!$detected) {
                        $platform_id = \common\classes\platform::default_id();
                    }
                }
                $pages = [['name' => 'credit_note', 'params' => ['orders_id' => $credit_note->order_id, 'platform_id' => $platform_id, 'language_id' => $languages_id, 'order' => $credit_note, 'currencies' => $currencies, 'oID' => $credit_note->order_id]]];
                $theme_id = \common\models\Platforms_To_Themes::find_one($platform_id)->theme_id;
                $theme_name = \common\models\Themes::find_one($theme_id)->theme_name;
                define('THEME_NAME', $theme_name);
                return \backend\design\Pdf_Block::widget(['pages' => $pages, 'params' => ['theme_name' => $theme_name, 'document_name' => str_replace(' ', '_', TEXT_CREDITNOTE) . '.pdf']]);
            }
        }
        die;
    }
    public function action_sort_products()
    {
        $this->layout = false;
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        Yii::$app->response->data = ['status' => 'ok'];
        $order_id = Yii::$app->request->get('order_id', 0);
        $sortkey = Yii::$app->request->post('sortkey', []);
        if (is_array($sortkey) && count($sortkey) > 0) {
            $sort_order = 0;
            foreach ($sortkey as $op_id) {
                tep_db_query('UPDATE ' . TABLE_ORDERS_PRODUCTS . " SET sort_order = '" . $sort_order++ . "' WHERE orders_id = '" . (int) $order_id . "' AND orders_products_id = '" . (int) $op_id . "'");
                $check_orders_products = tep_db_fetch_array(tep_db_query('select template_uprid, sub_products from ' . TABLE_ORDERS_PRODUCTS . " where orders_id = '" . (int) $order_id . "' AND orders_products_id = '" . (int) $op_id . "'"));
                if (tep_not_null($check_orders_products['sub_products'])) {
                    $orders_subproducts_query = tep_db_query('select orders_products_id from ' . TABLE_ORDERS_PRODUCTS . " where orders_id = '" . (int) $order_id . "' and parent_product = '" . tep_db_input($check_orders_products['template_uprid']) . "' order by orders_products_id");
                    while ($orders_subproducts = tep_db_fetch_array($orders_subproducts_query)) {
                        tep_db_query('UPDATE ' . TABLE_ORDERS_PRODUCTS . " set sort_order = '" . $sort_order++ . "' where orders_id = '" . (int) $order_id . "' and orders_products_id = '" . (int) $orders_subproducts['orders_products_id'] . "'");
                    }
                }
            }
        } else {
            Yii::$app->response->data = ['status' => 'error'];
        }
    }
    public function action_merge()
    {
        if ($ext = \common\helpers\Acl::check_extension_allowed('MergeOrders', 'allowed')) {
            return $ext::action_merge();
        }
        return $this->redirect(Yii::$app->url_manager->create_url(['orders/']));
    }
    public function action_product_allocate_temporary_information()
    {
        $order_status_expired_duration_hours = (int) \common\helpers\Configuration::get_configuration_key_value('ORDER_STATUS_TEMPORARY_ALLOCATION_EXPIRED_DURATION');
        if ($order_status_expired_duration_hours < 1) {
            $order_status_expired_duration_hours = 1;
        }
        echo '<div class="popup-heading">';
        foreach (\common\helpers\Order_Product::get_allocated_array(Yii::$app->request->get('opID', 0), true) as $opa_record) {
            $qty_rcvd = (int) $opa_record['allocate_received'] - (int) $opa_record['allocate_dispatched'];
            if ((int) $opa_record['is_temporary'] > 0 and $qty_rcvd > 0) {
                $time_expire = strtotime($opa_record['datetime']) + $order_status_expired_duration_hours * 60 * 60;
                $time_delta = $time_expire - time();
                $is_expired = $time_delta < 0;
                $time_delta = $time_delta / 60;
                $time_hour = floor($time_delta / 60);
                $time_minute = floor($time_delta - $time_hour * 60);
                $qty_rcvd = \common\helpers\Product::get_virtual_item_quantity($opa_record['prid'], $qty_rcvd);
                if ($is_expired == true) {
                    echo sprintf(MESSAGE_ORDER_STATUS_TEMPORARY_ALLOCATION_EXPIRED, $qty_rcvd, $order_status_expired_duration_hours) . '<br />';
                } else {
                    echo sprintf(MESSAGE_ORDER_STATUS_TEMPORARY_ALLOCATION_EXPIRE_IN, $qty_rcvd, $time_hour, $time_minute, $order_status_expired_duration_hours) . '<br />';
                }
            }
        }
        echo '</div>';
    }
    /**
     * Set status selected orders in orders list
     */
    public function action_set_status()
    {
        $this->layout = false;
        $languages_id = \Yii::$app->settings->get('languages_id');
        $selected_ids = Yii::$app->request->post('selected_ids', []);
        if (!is_array($selected_ids) && (int) $selected_ids > 0) {
            $selected_ids = [];
            $selected_ids[] = (int) Yii::$app->request->post('selected_ids', 0);
        }
        $status = (int) Yii::$app->request->post('status');
        $force = Yii::$app->request->post('force', 'false') === 'true' ? 1 : 0;
        $restock = Yii::$app->request->post('restock', 'false') === 'true' ? 1 : 0;
        $cancel = Yii::$app->request->post('cancel', 'false') === 'true' ? 1 : 0;
        $comments = Yii::$app->request->post('comments', '');
        $customer_notified = Yii::$app->request->post('notify', 'false') === 'true' ? 1 : 0;
        $paid = (int) Yii::$app->request->post('paid', '');
        $is_alternative_behaviour = false;
        $order_status_record = \common\models\Orders_Status::find_one(['orders_status_id' => $status]);
        if ($order_status_record->order_evaluation_state_id == \common\helpers\Order::OES_PENDING) {
            $is_alternative_behaviour = $cancel;
        } elseif ($order_status_record->order_evaluation_state_id == \common\helpers\Order::OES_PROCESSING) {
        } elseif ($order_status_record->order_evaluation_state_id == \common\helpers\Order::OES_CANCELLED) {
            $is_alternative_behaviour = $restock;
        } elseif ($order_status_record->order_evaluation_state_id == \common\helpers\Order::OES_DISPATCHED) {
            $is_alternative_behaviour = $force;
        } elseif ($order_status_record->order_evaluation_state_id == \common\helpers\Order::OES_DELIVERED) {
            $is_alternative_behaviour = $force;
        }
        unset($order_status_record);
        $orders_statuses = [];
        $orders_status_array = [];
        $orders_status_query = tep_db_query('select orders_status_id, orders_status_name from ' . TABLE_ORDERS_STATUS . " where language_id = '" . (int) $languages_id . "'");
        while ($orders_status = tep_db_fetch_array($orders_status_query)) {
            $orders_statuses[] = ['id' => $orders_status['orders_status_id'], 'text' => $orders_status['orders_status_name']];
            $orders_status_array[$orders_status['orders_status_id']] = $orders_status['orders_status_name'];
        }
        $manager = \common\services\Order_Manager::load_manager();
        $manager->set_modules_visibility(['shop_order']);
        foreach ($selected_ids as $o_id) {
            $customer_notified_status = 0;
            if ($customer_notified) {
                //$order = new \common\classes\Order($oID);
                $order = $manager->get_order_instance_with_id('\common\classes\Order', $o_id);
                /**
                 * @var \common\classes\Order $order
                 */
                $notify_comments = '';
                $EMAIL_TEXT_COMMENTS_UPDATE = Translation::get_translation_value('EMAIL_TEXT_COMMENTS_UPDATE', 'admin/main', $order->info['language_id']);
                $notify_comments = trim(sprintf($EMAIL_TEXT_COMMENTS_UPDATE, $comments)) . "\n\n";
                $order->info['order_status'] = $status;
                $customer_notified_status = $order->send_status_notify($notify_comments, []);
            }
            \common\helpers\Order::set_status($o_id, $status, [
                'comments' => $comments,
                //'smscomments' => $smscomments,
                'customer_notified' => $customer_notified_status,
            ], false, $is_alternative_behaviour);
        }
        //return \common\helpers\Order::change_order_status($selected_ids,$status,$comments,$snotify);
    }
    public function action_payment_list()
    {
        Translation::init('admin/orders');
        $o_id = Yii::$app->request->get('oID');
        $list_only = Yii::$app->request->get('list_only', 0);
        $currencies = new \common\classes\Currencies();
        $opy_status_list = \common\helpers\Order_Payment::get_status_list();
        $admin_array = $modules = $payment_array = [];
        $manager = new \common\services\Order_Manager(Yii::$app->get('storage'));
        $order = $manager->get_order_instance_with_id('\common\classes\Order', $o_id);
        Yii::$app->get('platform')->config($order->info['platform_id'])->constant_up();
        if (!empty($order->orders_id)) {
            $orders_id = $order->orders_id;
        } else {
            $orders_id = $o_id;
        }
        $_active_platform_id = $order->info['platform_id'];
        $builder = new \common\classes\modules\Module_Builder($manager);
        foreach (\common\helpers\Order_Payment::get_array_by_order_id($o_id) as $payment_record) {
            if (!isset($admin_array[$payment_record['orders_payment_admin_create']])) {
                $admin_array[$payment_record['orders_payment_admin_create']] = new \backend\models\Admin($payment_record['orders_payment_admin_create']);
            }
            if (!isset($admin_array[$payment_record['orders_payment_admin_update']])) {
                $admin_array[$payment_record['orders_payment_admin_update']] = new \backend\models\Admin($payment_record['orders_payment_admin_update']);
            }
            if ($payment_record['orders_payment_admin_create'] > 0) {
                $manaual = true;
                $payment_record['orders_payment_admin_create'] = $admin_array[$payment_record['orders_payment_admin_create']]->get_info('admin_firstname') . ' ' . $admin_array[$payment_record['orders_payment_admin_create']]->get_info('admin_lastname');
            } else {
                $manaual = false;
            }
            if ($payment_record['orders_payment_admin_update'] > 0) {
                $payment_record['orders_payment_admin_update'] = $admin_array[$payment_record['orders_payment_admin_update']]->get_info('admin_firstname') . ' ' . $admin_array[$payment_record['orders_payment_admin_update']]->get_info('admin_lastname');
            }
            $colour = 'black';
            /// 2do something (with _type field) Auth->reauth->Capture(payment)->refund
            //if ($paymentRecord['orders_payment_id_parent'] == 0) {
            if ($payment_record['orders_payment_status'] == \common\helpers\Order_Payment::OPYS_SUCCESSFUL) {
                $colour = 'green';
            } elseif ($payment_record['orders_payment_status'] == \common\helpers\Order_Payment::OPYS_REFUNDED) {
                $colour = 'blue';
            } elseif ($payment_record['orders_payment_status'] == \common\helpers\Order_Payment::OPYS_DISCOUNTED) {
                $colour = 'purple';
            }
            /*} else {
                  if ($paymentRecord['orders_payment_status'] == \common\helpers\OrderPayment::OPYS_REFUNDED) {
                      $colour = 'blue';
                      if ($paymentRecord['orders_payment_is_credit'] == 0) {
                          $colour = 'green';
                      }
                  } elseif ($paymentRecord['orders_payment_status'] == \common\helpers\OrderPayment::OPYS_DISCOUNTED) {
                      $colour = 'purple';
                      if ($paymentRecord['orders_payment_is_credit'] == 0) {
                          $colour = 'green';
                      }
                  }
              }*/
            $payment_record['orders_payment_amount_colour'] = $colour;
            $payment_record['orders_payment_is_refund'] = 0;
            if (in_array($payment_record['orders_payment_status'], [\common\helpers\Order_Payment::OPYS_SUCCESSFUL, \common\helpers\Order_Payment::OPYS_DISCOUNTED])) {
                $payment_record['orders_payment_is_refund'] = 1;
            }
            $class = $payment_record['orders_payment_module'];
            if (empty($modules[$class])) {
                try {
                    $modules[$class] = $builder(['class' => "\\common\\modules\\orderPayment\\{$class}"]);
                } catch (\Exception $e) {
                }
                // offline payment -not important: no extra links buttons
            }
            if (is_object($modules[$class]) && $modules[$class] instanceof \common\classes\modules\Transactional_Interface) {
                $payment_record['transactional'] = true;
                foreach (['can_refund' => 'canRefund', 'can_void' => 'canVoid', 'can_capture' => 'canCapture', 'can_reauthorize' => 'canReauthorize'] as $key => $method) {
                    if (($method != 'canRefund' || $payment_record['orders_payment_is_refund'] != 0) && !empty($method) && method_exists($modules[$class], $method)) {
                        $payment_record[$key] = $modules[$class]->{$method}($payment_record['orders_payment_transaction_id']);
                    }
                }
            }
            /// manual, pending,  and no children - can delete
            if ($manaual && in_array($payment_record['orders_payment_status'], [\common\helpers\Order_Payment::OPYS_PENDING, \common\helpers\Order_Payment::OPYS_DISCOUNTED]) && !\common\helpers\Order_Payment::has_children($payment_record['orders_payment_id'])) {
                $payment_record['can_delete'] = 1;
            } else {
                $payment_record['can_delete'] = 0;
            }
            $payment_record['orders_payment_status'] = $opy_status_list[$payment_record['orders_payment_status']];
            $payment_record['payment_amount'] = $currencies->format_clear($payment_record['orders_payment_amount'], false, $payment_record['orders_payment_currency']);
            $payment_record['orders_payment_amount'] = $currencies->format($payment_record['orders_payment_amount'], false, $payment_record['orders_payment_currency']);
            $payment_record['orders_payment_date_create'] = \common\helpers\Date::datetime_short($payment_record['orders_payment_date_create']);
            $payment_record['orders_payment_date_update'] = \common\helpers\Date::datetime_short($payment_record['orders_payment_date_update']);
            $payment_record['orders_payment_transaction_date'] = \common\helpers\Date::datetime_short($payment_record['orders_payment_transaction_date']);
            $payment_record['orders_payment_transaction_commentary'] = nl2br($payment_record['orders_payment_transaction_commentary']);
            $payment_array[] = $payment_record;
        }
        $on_behalf_url = false;
        if (extension_loaded('openssl')) {
            $actions[] = ['value' => 'on_behalf', 'name' => TEXT_PAY_ON_BEHALF];
            $c_info = \common\models\Customers::find()->where(['customers_id' => $order->customer['id']])->one();
            $aup = \common\helpers\Password::encrypt_auth_user_param($order->customer['id'], $order->customer['email_address'], 'payment', $c_info->auth_key ?? '');
            \Yii::$app->get('platform')->config($_active_platform_id);
            $due = array_filter($order->totals, function ($el) {
                return $el['class'] == 'ot_due' && round($el['value'], 2) > 0.01;
            });
            if (count($due) > 0) {
                $on_behalf_url = tep_catalog_href_link('account/login-me', 'order_id=' . (int) $orders_id . '&payer=1&aup=' . $aup);
            }
        }
        $url = Yii::$app->url_manager->create_url(['orders/p-transactions', 'orders_id' => $orders_id, 'platform_id' => $_active_platform_id ?? 0]);
        return $this->render_ajax('payment-list', ['oID' => $o_id, 'url' => $url, 'listOnly' => $list_only, 'onBehalfUrl' => $on_behalf_url, 'platform_id' => $_active_platform_id ?? 0, 'paymentArray' => $payment_array]);
    }
    /**
     * update transaction status from payment gateway if possible.
     */
    public function action_payment_update_status()
    {
        $ret = ['status' => 'fail', 'message' => TEXT_MESSAGE_ERROR];
        $opy_id = Yii::$app->request->post('opyID');
        $payment_record = \common\helpers\Order_Payment::get_record($opy_id);
        if ($payment_record instanceof \common\models\Orders_Payment && $payment_record->orders_payment_order_id > 0) {
            $order_manager = new \common\services\Order_Manager(Yii::$app->get('storage'));
            /** @var \common\classes\Order $order */
            $order = $order_manager->get_order_instance_with_id('\common\classes\Order', $payment_record->orders_payment_order_id);
            $platform_id = $order->info['platform_id'];
            $config = new \common\classes\platform_config($platform_id);
            $config->constant_up();
            $builder = new \common\classes\modules\Module_Builder($order_manager);
            $class = $builder(['class' => "\\common\\modules\\orderPayment\\{$payment_record->orders_payment_module}"]);
            $res = false;
            if (is_object($class)) {
                $res = \common\helpers\Order_Payment::update_transaction_details($payment_record, $class, $order_manager, false);
            }
            if ($res !== true) {
                if (!empty($res)) {
                    $ret['message'] = $res;
                }
            } else {
                $ret = ['status' => 'OK'];
            }
        }
        $this->layout = false;
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $ret;
    }
    public function action_payment_edit()
    {
        $opy_id = Yii::$app->request->get('opyID');
        $order_payment_status_array = [];
        $payment_record = \common\helpers\Order_Payment::get_record($opy_id);
        if ($payment_record instanceof \common\models\Orders_Payment) {
            $payment_child_count = count(\common\helpers\Order_Payment::get_array_child_by_parent_id($payment_record->orders_payment_id));
            foreach (\common\helpers\Order_Payment::get_status_list($payment_record['orders_payment_status']) as $status_id => $status_name) {
                if ($payment_child_count > 0 and $payment_record['orders_payment_status'] != $status_id) {
                    continue;
                }
                $order_payment_status_array[] = ['id' => $status_id, 'text' => $status_name];
            }
            return $this->render_ajax('payment-edit', ['orderPaymentStatusArray' => $order_payment_status_array, 'paymentRecord' => $payment_record]);
        } else {
            foreach (\common\helpers\Order_Payment::get_status_list() as $status_id => $status_name) {
                if (in_array($status_id, [\common\helpers\Order_Payment::OPYS_REFUSED, \common\helpers\Order_Payment::OPYS_REFUNDED, \common\helpers\Order_Payment::OPYS_CANCELLED, \common\helpers\Order_Payment::OPYS_DISCOUNTED])) {
                    continue;
                }
                $order_payment_status_array[] = ['id' => $status_id, 'text' => $status_name];
            }
            $o_id = Yii::$app->request->get('oID');
            $order_record = \common\helpers\Order::get_record($o_id);
            $cart_instance = new \common\classes\shopping_cart((int) $order_record->orders_id);
            $manager_instance = \common\services\Order_Manager::load_manager($cart_instance);
            Yii::$app->get('platform')->config((int) $order_record->platform_id)->constant_up();
            $manager_instance->set('platform_id', (int) $order_record->platform_id);
            $payments = new \common\classes\payment('', $manager_instance);
            $payment_array = $p_search_list = [];
            $payment_array[] = ['id' => '', 'text' => ''];
            foreach ($payments->get_enabled_modules() as $payment_class) {
                $payment_array[] = ['id' => $payment_class->code, 'text' => $payment_class->title];
                if ($payment_class instanceof \common\classes\modules\Transaction_Search_Interface) {
                    $p_search_list[$payment_class->code] = $payment_class->title;
                }
            }
            $currencies = new \common\classes\Currencies((int) $order_record->platform_id);
            $currency_array = [];
            $currency_array[] = ['id' => '', 'text' => ''];
            foreach ($currencies->currencies as $currency_data) {
                $currency_array[] = ['id' => $currency_data['code'], 'text' => $currency_data['title']];
            }
            $url = Yii::$app->url_manager->create_url(['orders/p-transactions', 'orders_id' => $o_id, 'platform_id' => (int) $order_record->platform_id ?? 0]);
            $mode = Yii::$app->request->get('search', 0);
            return $this->render_ajax('payment-edit-add', ['oID' => $o_id, 'paymentArray' => $payment_array, 'currencyArray' => $currency_array, 'currencyDefaultCode' => $currencies->dp_currency, 'orderPaymentStatusArray' => $order_payment_status_array, 'url' => $url, 'list' => $p_search_list, 'mode' => $mode, 'search' => Yii::$app->request->get('search', false)]);
        }
    }
    public function action_payment_refund()
    {
        $opy_id = Yii::$app->request->get('opyID');
        $order_payment_status_array = [];
        //?? refund statuses?
        foreach (\common\helpers\Order_Payment::get_status_list() as $status_id => $status_name) {
            if (in_array($status_id, [\common\helpers\Order_Payment::OPYS_PENDING, \common\helpers\Order_Payment::OPYS_PROCESSING, \common\helpers\Order_Payment::OPYS_SUCCESSFUL, \common\helpers\Order_Payment::OPYS_REFUSED, \common\helpers\Order_Payment::OPYS_CANCELLED])) {
                continue;
            }
            $order_payment_status_array[] = ['id' => $status_id, 'text' => $status_name];
        }
        $payment_record = \common\helpers\Order_Payment::get_record($opy_id);
        if ($payment_record instanceof \common\models\Orders_Payment) {
            if ($payment_record->orders_payment_id_parent > 0 or !in_array($payment_record->orders_payment_status, [\common\helpers\Order_Payment::OPYS_SUCCESSFUL, \common\helpers\Order_Payment::OPYS_DISCOUNTED])) {
                return 'Selected order payment record can\'t be refunded!';
            }
            $order_record = \common\helpers\Order::get_record($payment_record->orders_payment_order_id);
            $cart_instance = new \common\classes\shopping_cart((int) $order_record->orders_id);
            $manager_instance = \common\services\Order_Manager::load_manager($cart_instance);
            Yii::$app->get('platform')->config((int) $order_record->platform_id)->constant_up();
            $manager_instance->set('platform_id', (int) $order_record->platform_id);
            $payments = new \common\classes\payment('', $manager_instance);
            $payment_array = [];
            $payment_array[] = ['id' => '', 'text' => ''];
            foreach ($payments->get_enabled_modules() as $payment_class) {
                $payment_array[] = ['id' => $payment_class->code, 'text' => $payment_class->title];
            }
            return $this->render_ajax('payment-refund', ['orderPaymentStatusArray' => $order_payment_status_array, 'paymentRecord' => $payment_record, 'paymentArray' => $payment_array]);
        } else {
            return 'Order payment record not found!';
        }
    }
    public function action_payment_save()
    {
        global $login_id;
        $return = ['status' => 'error', 'message' => '', 'information' => '', 'reload' => 0];
        $order_payment_record = false;
        $order_payment_is_credit = false;
        $order_payment_currency_rate = null;
        $o_id = (int) Yii::$app->request->post('oID');
        $o_py_id = (int) Yii::$app->request->post('orders_payment_id');
        $o_py_id_parent = (int) Yii::$app->request->post('orders_payment_id_parent');
        $order_payment_status = (int) Yii::$app->request->post('orders_payment_status');
        $order_payment_module = trim(Yii::$app->request->post('orders_payment_module'));
        $order_payment_currency = trim(Yii::$app->request->post('orders_payment_currency'));
        $order_payment_amount = (float) trim(Yii::$app->request->post('orders_payment_amount'));
        $order_payment_transaction_id = trim(Yii::$app->request->post('orders_payment_transaction_id'));
        $order_payment_transaction_date = \common\helpers\Date::unformat_calendar_date(trim(Yii::$app->request->post('orders_payment_transaction_date')));
        $order_payment_transaction_date = empty($order_payment_transaction_date) ? '0000-00-00 00:00:00' : $order_payment_transaction_date;
        $order_payment_transaction_commentary = trim(strip_tags(Yii::$app->request->post('orders_payment_transaction_commentary')));
        if (!in_array($order_payment_status, array_keys(\common\helpers\Order_Payment::get_status_list()))) {
            $return['message'] = 'Selected status is invalid!';
        } elseif (in_array($order_payment_status, [\common\helpers\Order_Payment::OPYS_PROCESSING, \common\helpers\Order_Payment::OPYS_SUCCESSFUL, \common\helpers\Order_Payment::OPYS_REFUNDED]) and ($order_payment_transaction_id == '' or $order_payment_transaction_date == '0000-00-00 00:00:00')) {
            $return['message'] = 'Transaction information is invalid!';
        } elseif ($order_payment_amount <= 0) {
            $return['message'] = 'Amount is invalid!';
        } else if ($o_py_id > 0) {
            $order_payment_record = \common\helpers\Order_Payment::get_record($o_py_id);
            if ($order_payment_record instanceof \common\models\Orders_Payment) {
                $o_id = (int) $order_payment_record->orders_payment_order_id;
                $o_py_id_parent = (int) $order_payment_record->orders_payment_id_parent;
                $order_payment_record->orders_payment_amount = (float) $order_payment_record->orders_payment_amount;
                $order_payment_amount_available = \common\helpers\Order_Payment::get_amount_available($order_payment_record);
                if ($order_payment_record->orders_payment_id_parent == 0) {
                    if ($order_payment_amount < $order_payment_record->orders_payment_amount - $order_payment_amount_available) {
                        $order_payment_amount = $order_payment_record->orders_payment_amount - $order_payment_amount_available;
                    }
                } else if ($order_payment_amount > $order_payment_record->orders_payment_amount + $order_payment_amount_available) {
                    $order_payment_amount = $order_payment_record->orders_payment_amount + $order_payment_amount_available;
                }
                if (count(\common\helpers\Order_Payment::get_array_child_by_parent_id($order_payment_record->orders_payment_id)) > 0) {
                    $order_payment_status = $order_payment_record->orders_payment_status;
                }
            } else {
                $return['message'] = 'Payment record not found!';
            }
        } elseif ($o_py_id_parent > 0) {
            $order_payment_parent_record = \common\helpers\Order_Payment::get_record($o_py_id_parent);
            if ($order_payment_parent_record instanceof \common\models\Orders_Payment) {
                $o_id = (int) $order_payment_parent_record->orders_payment_order_id;
                $order_payment_currency = $order_payment_parent_record->orders_payment_currency;
                $order_payment_currency_rate = (float) $order_payment_parent_record->orders_payment_currency_rate;
                $order_payment_is_credit = (int) $order_payment_parent_record->orders_payment_is_credit > 0 ? 0 : 1;
                $order_payment_amount_available = \common\helpers\Order_Payment::get_amount_available($order_payment_parent_record);
                if ($order_payment_amount > $order_payment_amount_available) {
                    $order_payment_amount = $order_payment_amount_available;
                }
                if ($order_payment_amount <= 0) {
                    $return['message'] = 'Amount already refunded!';
                }
            } else {
                $return['message'] = 'Parent payment record not found!';
            }
        }
        if ($return['message'] == '') {
            $order_record = \common\helpers\Order::get_record($o_id);
            if ($order_record instanceof \common\models\Orders) {
                $cart_instance = new \common\classes\shopping_cart((int) $order_record->orders_id);
                if (is_object($cart_instance)) {
                    $manager_instance = \common\services\Order_Manager::load_manager($cart_instance);
                    if (is_object($manager_instance)) {
                        $order_instance = $manager_instance->get_order_instance_with_id('\common\classes\Order', (int) $order_record->orders_id);
                        if (is_object($order_instance)) {
                            Yii::$app->get('platform')->config((int) $order_record->platform_id)->constant_up();
                            $manager_instance->set('platform_id', (int) $order_record->platform_id);
                        } else {
                            $return['message'] = 'Invalid Order instance!';
                        }
                    } else {
                        $return['message'] = 'Invalid Manager instance!';
                    }
                } else {
                    $return['message'] = 'Invalid Cart instance!';
                }
            } else {
                $return['message'] = 'Order record not found!';
            }
        }
        if ($return['message'] == '') {
            if (!$order_payment_record instanceof \common\models\Orders_Payment) {
                $currencies = new \common\classes\Currencies((int) $order_record->platform_id);
                $currency_array = [];
                foreach ($currencies->currencies as $currency_data) {
                    $currency_array[$currency_data['code']] = $currency_data['value'];
                }
                $order_payment_currency_rate = (float) ($order_payment_currency_rate > 0 ? $order_payment_currency_rate : (isset($currency_array[$order_payment_currency]) ? $currency_array[$order_payment_currency] : 0));
                if ($order_payment_currency_rate <= 0) {
                    $return['message'] = 'Payment currency is invalid!';
                } else {
                    $payments = new \common\classes\payment('', $manager_instance);
                    $payment_array = [];
                    foreach ($payments->get_enabled_modules() as $payment_class) {
                        $payment_array[$payment_class->code] = $payment_class->title;
                    }
                    if (!isset($payment_array[$order_payment_module])) {
                        $return['message'] = 'Payment method is invalid!';
                    } else {
                        $order_payment_record = new \common\models\Orders_Payment();
                        $order_payment_record->orders_payment_id_parent = $o_py_id_parent;
                        $order_payment_record->orders_payment_order_id = $order_record->orders_id;
                        $order_payment_record->orders_payment_currency = $order_payment_currency;
                        $order_payment_record->orders_payment_currency_rate = $order_payment_currency_rate;
                        $order_payment_record->orders_payment_module = $order_payment_module;
                        $order_payment_record->orders_payment_module_name = $payment_array[$order_payment_module];
                        $order_payment_record->orders_payment_snapshot = json_encode(\common\helpers\Order_Payment::get_order_payment_snapshot($order_instance));
                        $order_payment_record->orders_payment_transaction_status = '';
                        $order_payment_record->orders_payment_admin_create = $login_id;
                        if ($order_payment_is_credit !== false) {
                            $order_payment_record->orders_payment_is_credit = $order_payment_is_credit;
                        } elseif (in_array($order_payment_status, [\common\helpers\Order_Payment::OPYS_REFUNDED, \common\helpers\Order_Payment::OPYS_DISCOUNTED])) {
                            $order_payment_record->orders_payment_is_credit = 1;
                        }
                    }
                }
            } else if ($order_payment_status != \common\helpers\Order_Payment::OPYS_PENDING and $order_payment_amount != $order_payment_record->orders_payment_amount) {
                $order_payment_amount = $order_payment_record->orders_payment_amount;
                $return['message'] = 'Amount not changed due to payment status!';
            }
            if (($order_payment_record->orders_payment_amount ?? null) != $order_payment_amount or (int) $order_payment_record->orders_payment_status != $order_payment_status) {
                $return['reload'] = 1;
            }
            $order_payment_record->orders_payment_status = $order_payment_status;
            $order_payment_record->orders_payment_amount = $order_payment_amount;
            $order_payment_record->orders_payment_transaction_id = $order_payment_transaction_id;
            $order_payment_record->orders_payment_transaction_date = $order_payment_transaction_date;
            $order_payment_record->orders_payment_transaction_commentary = $order_payment_transaction_commentary;
            $order_payment_record->orders_payment_admin_update = $login_id;
            $order_payment_record->orders_payment_date_update = date('Y-m-d H:i:s');
            try {
                if ($order_payment_record->save()) {
                    try {
                        /*
                         $totalCollection = $managerInstance->getTotalCollection();
                         $totalCollection = $totalCollection->process(['ot_paid', 'ot_due', 'ot_refund']);
                         if (is_array($totalCollection) AND count($totalCollection) > 0) {
                             $orderInstance->totals = array_replace($orderInstance->totals, $totalCollection); //??? STUPID!!! SORT ORDER CHANGE ISSUE
                             $orderInstance->save_totals();
                         }
                        */
                        $updated = $order_instance->update_paid_totals();
                        //?? auto switch status??
                        $return['status'] = 'ok';
                    } catch (\Exception $exc) {
                        $return['message'] = 'Error while updating Order totals!';
                    }
                } else {
                    $message = '';
                    foreach ($order_payment_record->get_errors() as $error_array) {
                        $message .= implode("\n", $error_array) . "\n";
                    }
                    unset($error_array);
                    $return['message'] = trim($message);
                    unset($message);
                }
            } catch (\Exception $exc) {
                $return['message'] = 'Error while updating Payment record!' . $exc->get_message();
                \Yii::error(' #### ' . print_r($exc->get_message(), 1), 'TLDEBUG');
            }
        }
        if ($return['message'] != '') {
            $return['reload'] = 0;
        }
        echo json_encode($return);
        exit;
    }
}