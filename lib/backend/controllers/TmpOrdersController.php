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
use backend\models\Product_Name_Decorator;
use common\classes\order_total;
use common\classes\platform;
use common\classes\platform_config;
use common\helpers\Coupon;
use common\helpers\Html;
use common\helpers\Order as OrderHelper;
use common\helpers\Status;
use common\models\Tmp_Orders as Orders;
use Yii;
use yii\helpers\Array_Helper;
/**
 * default controller to handle user requests.
 */
class Tmp_Orders_Controller extends Sceleton
{
    public $acl = ['BOX_HEADING_CUSTOMERS', 'BOX_CUSTOMERS_TMP_ORDERS'];
    private $tmp_order_controller = true;
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
        \common\helpers\Translation::init('admin/orders');
        parent::__construct($id, $module);
    }
    /**
     *
     * @global int $login_id
     * @global type $navigation
     * @return string
     */
    public function action_index()
    {
        global $login_id, $navigation;
        if (is_object($navigation) && method_exists($navigation, 'set_snapshot')) {
            $navigation->set_snapshot();
        }
        $this->selected_menu = ['BOX_HEADING_CUSTOMERS', 'BOX_CUSTOMERS_TMP_ORDERS'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('tmporders/index'), 'title' => HEADING_TMP_ORDERS];
        $this->view->heading_title = HEADING_TMP_ORDERS;
        $this->view->orders_table = [];
        $this->view->orders_table[] = ['title' => '<input type="checkbox" class="uniform">', 'not_important' => 2];
        if (!$this->tmp_order_controller && $ext = \common\helpers\Acl::check_extension_allowed('OrderMarkers', 'allowed')) {
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
        if (!$this->tmp_order_controller && $ext = \common\helpers\Acl::check_extension_allowed('OrderMarkers', 'allowed')) {
            $markers = $ext::get_markers_list(true);
        }
        $this->view->markers = $markers;
        $this->view->filters->marker = (int) Yii::$app->request->get('marker', 0);
        $flags = [];
        if (!$this->tmp_order_controller && $ext = \common\helpers\Acl::check_extension_allowed('OrderMarkers', 'allowed')) {
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
            /*[
                  'name' => TEXT_WAREHOUSES_PRODUCTS_BATCH_NAME,
                  'value' => 'batchName',
                  'selected' => '',
              ],*/
            ['name' => TEXT_CLIENT_NAME, 'value' => 'fullname', 'selected' => ''],
            ['name' => TEXT_CLIENT_EMAIL, 'value' => 'email', 'selected' => ''],
            ['name' => TEXT_CLIENT_PHONE, 'value' => 'phone', 'selected' => ''],
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
        /*
                foreach(\common\helpers\Admin::getAdminsWithWalkinOrders() as $admin){
                    $this->view->filters->admin[$admin->admin_id] = $admin->admin_firstname .' '. $admin->admin_lastname;
                }
        */
        $this->view->filters->status = \common\helpers\Order::get_status_list();
        $this->view->filters->status_selected = $GET['status'] ?? [];
        $this->view->filters->fcoupon = 'byId';
        $this->view->filters->fc_id = $GET['fc_id'] ?? [];
        /*      if (!empty($GET['fc_code'])) {
                        $this->view->filters->fc_code = htmlspecialchars($GET['fc_code']);
                        $this->view->filters->fcoupon = 'like';
                        $this->view->filters->fc_id = [];
                      }
                      $this->view->filters->fCoupons = \yii\helpers\ArrayHelper::map(Coupon::getOrderedList(), 'coupon_id', 'coupon_code');
        */
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
        $this->view->filters->payments = $payments = $o_model_query->select(['payment_method'])->distinct()->order_by('payment_method')->as_array()->index_by('payment_method')->column();
        $this->view->filters->shipping = array_map('html_entity_decode', $this->view->filters->payments);
        $this->view->filters->payments_selected = $GET['payments'] ?? [];
        $this->view->filters->shipping = $payments = \yii\helpers\Array_Helper::map($o_model_query->select(['shipping_method'])->group_by('shipping_method')->order_by('shipping_method')->as_array()->all(), 'shipping_method', 'shipping_method');
        $this->view->filters->shipping = array_map('html_entity_decode', $this->view->filters->shipping);
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
        /*$admin->loadCustomersBaskets();
          $ids = $admin->getVirtualCartIDs();*/
        $this->view->filters->admin_choice = [];
        if (!$this->tmp_order_controller && $ids) {
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
        /*
                // batch print extra documents
                        $addedPages = \common\models\ThemesSettings::find()->alias('ts')
                            ->innerJoin(TABLE_THEMES . ' t', 't.theme_name=ts.theme_name')
                            ->innerJoin(TABLE_PLATFORMS_TO_THEMES . ' p2t', 't.id=p2t.theme_id')
                            ->select(['setting_name','setting_value', 'ts.id'])
                            ->where([
                                'p2t.platform_id' => $theme_platform_id,
                                'setting_group' => 'added_page',
                                'setting_name' => ['packingslip', 'invoice'],
                            ])
                            ->orderBy('setting_name')
                            ->asArray()
                            ->all();
                        $addedPages = ArrayHelper::map($addedPages, 'id', 'setting_value', 'setting_name');*/
        $added_pages = [];
        return $this->render('index', ['isMultiPlatform' => \common\classes\platform::is_multi(), 'platforms' => \common\classes\platform::get_list(true, true), 'departments' => $departments, 'ordersStatuses' => $orders_statuses, 'ordersStatusesOptions' => $orders_statuses_options, 'addedPages' => $added_pages, 'tmpOrderController' => true]);
    }
    public function action_order_history()
    {
        $this->layout = false;
        $orders_id = Yii::$app->request->get('orders_id');
        $params = [];
        $history = [];
        $orders_history_query = tep_db_query('select * from tmp_orders_history o left join ' . TABLE_ADMIN . " a on a.admin_id = o.admin_id where orders_id='" . (int) $orders_id . "' order by orders_history_id desc");
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
        if (($ext = \common\helpers\Acl::check_extension_allowed('RecoverShoppingCart')) && defined('RCS_SHOW_AT_ORDERS') && RCS_SHOW_AT_ORDERS == 'true' && $orders_id && $cid) {
            $params['show_recovery_details'] = true;
            $ext::init_translation('order-history');
            $params['ua'] = \common\helpers\System::get_ga_detection($orders_id);
            $params['ua_tracking'] = \common\models\Ecommerce_Tracking::find_all(['orders_id' => $orders_id]);
            //errors
            $params['errors'] = \common\models\Customers_Errors::find()->linking_to(\common\models\Tmp_Orders::class)->where(['orders_id' => $orders_id])->order_by('error_date desc')->all();
            //contacts
            $scart = tep_db_query('select * from ' . TABLE_SCART . " s inner join tmp_orders o on o.orders_id = '" . (int) $orders_id . "' where o.basket_id = s.basket_id and s.customers_id = '" . (int) $cid . "'");
            if (tep_db_num_rows($scart)) {
                $_scart = tep_db_fetch_array($scart);
                $_scart['recovered'] = $_scart['recovered'] ? TEXT_BTN_YES : TEXT_BTN_NO;
                $_scart['contacted'] = $_scart['contacted'] ? TEXT_BTN_YES : TEXT_BTN_NO;
                $_scart['workedout'] = $_scart['workedout'] ? TEXT_BTN_YES : TEXT_BTN_NO;
                $params['scart'] = $_scart;
                //gv && cc
                $coupons = tep_db_query('select cet.coupon_id, cet.sent_firstname, cet.sent_lastname, cet.date_sent, c.coupon_code, c.coupon_amount, c.coupon_currency, c.coupon_type, c.coupon_active from ' . TABLE_COUPON_EMAIL_TRACK . ' cet left join ' . TABLE_COUPONS . " c on c.coupon_id = cet.coupon_id inner join tmp_orders o on o.orders_id = '" . (int) $orders_id . "' where o.basket_id = cet.basket_id and cet.customer_id_sent = '" . (int) $cid . "'");
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
        return $this->render_ajax('recovery', $params);
        //return $this->render('order-history.tpl');
    }
    /**
     *
     * @global int $login_id
     * @global int  $access_levels_id
     */
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
            $search_fields = ['o.customers_telephone', 'o.delivery_telephone', 'o.billing_telephone', 'o.customers_lastname', 'o.customers_firstname', 'o.customers_email_address', 'o.orders_id', 'op.products_model', 'op.products_name'];
            if (is_numeric($keywords)) {
                $search_fields[] = 'o.api_client_order_id';
            }
            /** @var \common\extensions\InvoiceNumberFormat\InvoiceNumberFormat $infExt */
            if (!$this->tmp_order_controller && $inf_ext = \common\helpers\Acl::check_extension_allowed('InvoiceNumberFormat', 'allowed')) {
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
        $orders_query_raw = \common\models\Tmp_Orders::find()->select('o.orders_id, s.orders_status_name, s.orders_status_groups_id ')->from([TABLE_ORDERS_STATUS . ' s', 'tmp_orders o']);
        if (isset($_GET['in_stock']) && $_GET['in_stock'] != '') {
            $_orders_products_joined = true;
            $orders_query_raw->left_join('tmp_orders_products op', '(op.orders_id = o.orders_id)');
            $orders_query_raw->add_select('BIT_AND(' . (\common\helpers\Extensions::is_allowed('Inventory') ? 'if(i.products_quantity is not null,if((i.products_quantity>=op.products_quantity),1,0),if((p.products_quantity>=op.products_quantity),1,0))' : 'if((p.products_quantity>=op.products_quantity),1,0)') . ') as in_stock');
            $orders_query_raw->left_join(TABLE_PRODUCTS . ' p', '(p.products_id = op.products_id)');
            if (\common\helpers\Extensions::is_allowed('Inventory')) {
                $orders_query_raw->left_join(TABLE_INVENTORY . ' i', '(i.prid = op.products_id and i.products_id = op.uprid)');
            }
        }
        if (\common\helpers\Acl::check_extension_allowed('Handlers', 'allowed')) {
            if (!$_orders_products_joined) {
                $_orders_products_joined = true;
                $orders_query_raw->left_join('tmp_orders_products op', '(op.orders_id = o.orders_id)');
            }
            $orders_query_raw->left_join('handlers_products hp', 'hp.products_id = op.products_id');
        }
        $_orders_products_allocate_joined = false;
        if (!$this->tmp_order_controller && !\common\helpers\Acl::rule(['BOX_HEADING_CUSTOMERS', 'BOX_CUSTOMERS_TMP_ORDERS', 'RULE_ALLOW_WAREHOUSES'])) {
            $orders_query_raw->left_join('orders_products_allocate opa', 'opa.orders_id = o.orders_id');
            $_orders_products_allocate_joined = true;
        }
        if (!$this->tmp_order_controller && !\common\helpers\Acl::rule(['BOX_HEADING_CUSTOMERS', 'BOX_CUSTOMERS_TMP_ORDERS', 'RULE_ALLOW_SUPPLIERS'])) {
            if (!$_orders_products_allocate_joined) {
                $orders_query_raw->left_join('orders_products_allocate opa', 'opa.orders_id = o.orders_id');
                $_orders_products_allocate_joined = true;
            }
        }
        //$orders_query_raw->leftJoin(TABLE_CUSTOMERS . " c", "(o.customers_id = c.customers_id)");
        $orders_query_raw->where('o.orders_status = s.orders_status_id ' . $search_condition . " and s.language_id = '" . (int) $languages_id . "' and s.orders_status_groups_id IN('" . implode("','", array_keys($status_group_data)) . "') ");
        if (strpos($search_condition, ' op.') !== false && !$_orders_products_joined) {
            $orders_query_raw->left_join('tmp_orders_products op', '(op.orders_id = o.orders_id)');
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
            $handlers_array = [];
            $handlers_array = $ext::get_handlers_query((int) $access_levels_id);
            $orders_query_raw->and_where(['in', 'hp.handlers_id', $handlers_array]);
        }
        if (!$this->tmp_order_controller && !\common\helpers\Acl::rule(['BOX_HEADING_CUSTOMERS', 'BOX_CUSTOMERS_TMP_ORDERS', 'RULE_ALLOW_WAREHOUSES'])) {
            $warehouses_array = [];
            foreach (\common\models\Admin_Warehouses::find()->where(['admin_id' => $login_id])->as_array()->all() as $warehouse) {
                $warehouses_array[] = $warehouse['warehouse_id'];
            }
            unset($warehouse);
            $orders_query_raw->and_where(['in', 'opa.warehouse_id', $warehouses_array]);
            unset($warehouses_array);
        }
        if (!$this->tmp_order_controller && !\common\helpers\Acl::rule(['BOX_HEADING_CUSTOMERS', 'BOX_CUSTOMERS_TMP_ORDERS', 'RULE_ALLOW_SUPPLIERS'])) {
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
                    $orders_query_raw->and_where(['o.orders_id' => (int) $search]);
                    //$orders_query_raw->andWhere([ 'or', ["o.orders_id" => (int) $search], ['o.order_number' => $search]]);
                    break;
                case 'model':
                default:
                    if (!$_orders_products_joined) {
                        $_orders_products_joined = true;
                        $orders_query_raw->left_join('tmp_orders_products op', '(op.orders_id = o.orders_id)');
                    }
                    $orders_query_raw->and_where([$operator, 'op.products_model', $search]);
                    break;
                case 'name':
                    if (!$_orders_products_joined) {
                        $_orders_products_joined = true;
                        $orders_query_raw->left_join('tmp_orders_products op', '(op.orders_id = o.orders_id)');
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
                        $orders_query_raw->left_join('tmp_orders_products op', '(op.orders_id = o.orders_id)');
                    }
                    $orders_query_raw->left_join('tmp_orders_status_history osh', 'o.orders_id = osh.orders_id');
                    $orders_query_raw->and_filter_where(['or', ['o.orders_id' => $search], [
                        $operator == 'LIKE' ? 'OR' : 'AND',
                        //[$operator, 'o.order_number',$search],
                        [$operator, 'op.products_model', $search],
                        [$operator, 'op.products_name', $search],
                        [$operator, 'o.customers_name', $search],
                        [$operator, 'o.customers_email_address', $search],
                        [$operator, 'osh.comments', $search],
                        [$operator, 'o.tracking_number', $search],
                        [$operator, 'o.customers_telephone', $search],
                        [$operator, 'o.delivery_telephone', $search],
                        [$operator, 'o.billing_telephone', $search],
                    ]]);
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
            $orders_query_raw->and_where(['in', 'o.payment_method', $output['payments']]);
        }
        if (isset($output['shipping']) && !empty($output['shipping'])) {
            $orders_query_raw->and_where(['in', 'o.shipping_method', $output['shipping']]);
        }
        if (isset($output['fc_id']) && is_array($output['fc_id']) && count($output['fc_id'])) {
            $orders_query_raw->inner_join(TABLE_COUPON_REDEEM_TRACK . ' crt', 'o.orders_id=crt.order_id and crt.coupon_id in (' . implode(',', $output['fc_id']) . ') ');
        }
        if (isset($output['fc_code']) && !empty($output['fc_code'])) {
            $orders_query_raw->inner_join('tmp_orders_total otfc', "o.orders_id=otfc.orders_id and otfc.class='ot_coupon' and otfc.title like '%" . tep_db_input($output['fc_code']) . "%'");
        }
        if (isset($output['flag']) && $output['flag'] > 0) {
            $orders_query_raw->inner_join('orders_markers' . ' omf', "o.orders_id=omf.orders_id and omf.flags='" . (int) $output['flag'] . "'");
        }
        if (!$this->tmp_order_controller && isset($output['marker']) && $output['marker'] > 0) {
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
            $orders_query_raw->inner_join('tmp_orders_total otfp', "o.orders_id=otfp.orders_id and otfp.class='" . tep_db_input($output['fp_class']) . "'" . (tep_not_null($output['fp_from']) ? " and round(otfp.value, 2)>='" . tep_db_input(round($fp_from, 2)) . "'" : '') . (tep_not_null($output['fp_to']) ? " and round(otfp.value,2)<='" . tep_db_input(round($fp_to, 2)) . "'" : '') . '');
        }
        if (isset($output['walkin']) && is_array($output['walkin'])) {
            $orders_query_raw->and_where(['in', 'o.admin_id', $output['walkin']]);
        }
        if (isset($output['deficit_only']) and (int) $output['deficit_only'] > 0) {
            if (!$_orders_products_joined) {
                $_orders_products_joined = true;
                $orders_query_raw->left_join('tmp_orders_products op', '(op.orders_id = o.orders_id)');
            }
            $orders_query_raw->and_where('((op.products_quantity - op.qty_cnld) > op.qty_rcvd)');
        }
        $orders_query_raw->group_by('o.orders_id');
        if (isset($_GET['in_stock']) && $_GET['in_stock'] != '') {
            $orders_query_raw->having('in_stock ' . ($_GET['in_stock'] > 0 ? ' > 0' : ' < 1'));
        }
        $orders_query_raw->order_by($order_by);
        /** @var \common\extensions\Neighbour\Neighbour $ext */
        if (!$this->tmp_order_controller && $ext = \common\helpers\Acl::check_extension('Neighbour', 'allowed')) {
            if ($ext::allowed()) {
                $ext_query = $ext::get_query($orders_query_raw);
            }
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
            $complete_page_data = \common\models\Tmp_Orders::find()->select('o.*')->add_select('c.customers_gender')->add_select('ad.admin_firstname, ad.admin_lastname')->add_select('ot.text_inc_tax as order_total')->from('tmp_orders o')->left_join('tmp_orders_total ot', "(o.orders_id = ot.orders_id and ot.class = 'ot_total')")->left_join(TABLE_ADMIN . ' ad', '(ad.admin_id = o.admin_id)')->left_join(TABLE_CUSTOMERS . ' c', '(o.customers_id = c.customers_id)')->where(['IN', 'o.orders_id', $_page_order_ids])->as_array()->all();
            foreach ($complete_page_data as $__order_data) {
                $__idx = $_page_order_id_to_idx[$__order_data['orders_id']];
                $orders_all[$__idx] = array_merge($__order_data, $orders_all[$__idx]);
            }
            if (!$this->tmp_order_controller && $ext = \common\helpers\Acl::check_extension_allowed('OrderMarkers', 'allowed')) {
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
        if (!$this->tmp_order_controller && $ext = \common\helpers\Acl::check_extension_allowed('OrderMarkers', 'allowed')) {
            $markers = $ext::get_markers();
        }
        $flags = [];
        if (!$this->tmp_order_controller && $ext = \common\helpers\Acl::check_extension_allowed('OrderMarkers', 'allowed')) {
            $flags = $ext::get_flags();
        }
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
                $purchased_date = \common\helpers\Date::datetime_short($orders['date_purchased']);
                $today_date = \common\helpers\Date::date_short(date('Y-m-d'));
                $purchased_date = str_replace($today_date, TEXT_TODAY, $purchased_date);
                $cus_column = '';
                if ($orders['customers_id']) {
                    $cus_column = '<div class="ord-name ord-gender ord-gender-' . $orders['customers_gender'] . ' click_double" data-click-double="' . \Yii::$app->url_manager->create_url(['tmp-orders/process-order', 'orders_id' => $orders['orders_id']]) . '">' . (\common\models\Customers::find_one($orders['customers_id']) ? '<a href="' . \Yii::$app->url_manager->create_url(['customers/customeredit', 'customers_id' => $orders['customers_id']]) . '" title="' . strip_tags($orders['customers_name']) . '">' . Html::encode(self::crop_str($orders['customers_name'], 22)) . '</a>' : Html::encode(self::crop_str($orders['customers_name'], 22))) . '</div><a href="mailto:' . $orders['customers_email_address'] . '" class="ord-name-email" title="' . strip_tags($customers_email_address) . '">' . self::crop_str($customers_email_address, 22) . '</a><div class="ord-location" style="margin-top: 5px;">' . Html::encode($orders['customers_postcode']) . '<div class="ord-total-info ord-location-info"><div class="ord-box-img"></div><b>' . Html::encode($orders['customers_name']) . '</b>' . Html::encode($orders['customers_street_address']) . '<br>' . Html::encode($orders['customers_city'] . ', ' . $orders['customers_state']) . '&nbsp;' . Html::encode($orders['customers_postcode']) . '<br>' . $orders['customers_country'] . '</div></div>';
                } elseif ($orders['admin_id']) {
                    $customer_delivery_name = '(' . $orders['delivery_name'] . ')';
                    $customer_delivery_info = '<div class="ord-location" style="margin-top: 5px;">' . $orders['delivery_postcode'] . '<div class="ord-total-info ord-location-info"><div class="ord-box-img"></div><b>' . Html::encode($orders['delivery_name']) . '</b>' . Html::encode($orders['delivery_street_address']) . '<br>' . Html::encode($orders['delivery_city'] . ', ' . $orders['delivery_state']) . '&nbsp;' . Html::encode($orders['delivery_postcode']) . '<br>' . $orders['delivery_country'] . '</div></div>';
                    $cus_column = '<div class="ord-name click_double" data-click-double="' . \Yii::$app->url_manager->create_url(['tmp-orders/process-order', 'orders_id' => $orders['orders_id']]) . '">' . (defined('TEXT_WALKIN_ORDER') ? TEXT_WALKIN_ORDER : '') . $orders['admin_firstname'] . ' ' . $orders['admin_lastname'] . ' ' . $customer_delivery_name . '</div>' . $customer_delivery_info;
                }
                $order_row = [];
                if ($orders['hold_on_date']) {
                    $order_row['DT_RowClass'] = Array_Helper::get_value($order_row, 'DT_RowClass') . ' holdOnOrder';
                    $purchased_date .= '<div class="holdOrderInfo">' . sprintf(LIST_ORDER_HOLD_ON, \common\helpers\Date::date_short($orders['hold_on_date'])) . '</div>';
                }
                if (isset($orders['isFraud']) && $orders['isFraud']) {
                    $order_row['DT_RowClass'] = Array_Helper::get_value($order_row, 'DT_RowClass') . ' fraudOrder';
                }
                $order_row[] = '<input type="checkbox" class="uniform">' . '<input class="cell_identify" type="hidden" value="' . $orders['orders_id'] . '">';
                $colored_row = '';
                if (!$this->tmp_order_controller && $ext = \common\helpers\Acl::check_extension_allowed('OrderMarkers', 'allowed')) {
                    $order_markers = $orders['orderMarkers'];
                    if (isset($order_markers['markers']) && isset($markers[$order_markers['markers']])) {
                        $colored_row = $markers[$order_markers['markers']];
                    }
                    $paint = '<div class="fa-paint-brush" onclick="sendOrderMarker(' . (int) $orders['orders_id'] . ', ' . (int) ($order_markers['markers'] ?? 0) . ')"></div>';
                    if (isset($order_markers['flags']) && isset($flags[$order_markers['flags']])) {
                        $order_row[] = '<div class="fa-flag" style="color: ' . $flags[$order_markers['flags']] . ';" onclick="sendOrderFlag(' . (int) $orders['orders_id'] . ', ' . (int) $order_markers['flags'] . ')"></div>' . $paint;
                    } else {
                        $order_row[] = '<div class="fa-flag-o" onclick="sendOrderFlag(' . (int) $orders['orders_id'] . ')"></div>' . $paint;
                    }
                }
                $order_row[] = $cus_column . '<input class="row_colored" type="hidden" value="' . $colored_row . '">';
                $order_row[] = '<div class="ord-total click_double" data-click-double="' . \Yii::$app->url_manager->create_url(['tmp-orders/process-order', 'orders_id' => $orders['orders_id']]) . '">' . $orders['order_total'] . '<div class="ord-total-info"><div class="ord-box-img"></div>' . $order_totals . '</div></div>';
                $order_row[] = '<div class="ord-desc-tab click_double" data-click-double="' . \Yii::$app->url_manager->create_url(['tmp-orders/process-order', 'orders_id' => $orders['orders_id']]) . '"><a href="' . \Yii::$app->url_manager->create_url(['tmp-orders/process-order', 'orders_id' => $orders['orders_id']]) . '" class="order-inf"><span class="ord-id">' . TEXT_ORDER_NUM . (!empty($orders['order_number']) ? $orders['order_number'] : $orders['orders_id']) . '</span> ' . (!empty($orders['invoice_number']) ? ' <span class="inv-id"><span class="title">' . TEXT_INVOICE . '</span>' . $orders['invoice_number'] . '</span> ' : '') . $department_info . (tep_not_null($orders['payment_method']) ? (SHOW_PRODUCTS_ON_ORDER_LIST === 'False' ? '<br>' : ' ') . TEXT_VIA . ' ' . strip_tags($orders['payment_method']) : '') . (tep_not_null($orders['shipping_method']) ? ' ' . TEXT_DELIVERED_BY . ' ' . strip_tags($orders['shipping_method']) : '') . '</a>' . (SHOW_PRODUCTS_ON_ORDER_LIST !== 'False' ? $p_list : '') . '</div>';
                $order_row[] = '<div class="ord-date-purch click_double" data-click-double="' . \Yii::$app->url_manager->create_url(['tmp-orders/process-order', 'orders_id' => $orders['orders_id']]) . '">' . $purchased_date . $delivery_info;
                $order_row[] = '<div class="ord-status click_double" data-click-double="' . \Yii::$app->url_manager->create_url(['tmp-orders/process-order', 'orders_id' => $orders['orders_id']]) . '"><span><i style="background: ' . $orders['orders_status_groups_color'] . ';"></i>' . $orders['orders_status_groups_name'] . '</span><div>' . $orders['orders_status_name'] . '</div></div>';
                if (!$this->tmp_order_controller && $ext = \common\helpers\Acl::check_extension('Neighbour', 'allowed')) {
                    if ($ext::allowed()) {
                        $order_row[] = $orders['to_neighbour'] ? '<div class=" ord-date-purch-delivery ord-date-purch-delivery-check">' : '';
                    }
                }
                $response_list[] = $order_row;
            }
        }
        $response = ['draw' => $draw, 'recordsTotal' => $orders_query_numrows, 'recordsFiltered' => $orders_query_numrows, 'data' => $response_list];
        echo json_encode($response, JSON_PARTIAL_OUTPUT_ON_ERROR);
        //die();
    }
    private static function crop_str($str, $length)
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
        $orders = Orders::find()->alias('o')->and_where(['orders_id' => (int) $orders_id])->as_array()->one();
        if (empty($orders)) {
            die('Please select order.');
        }
        $_pl = Array_Helper::map(platform::get_list(false), 'id', 'id');
        if (!in_array($orders['platform_id'], $_pl)) {
            $orders['platform_id'] = platform::default_id();
        }
        $o_info = new \Object_Info($orders);
        return $this->render('actions', ['oInfo' => $o_info]);
    }
    public function action_order_reassign()
    {
        \common\helpers\Translation::init('admin/orders');
        $this->layout = false;
        $orders_id = Yii::$app->request->post('orders_id');
        $orders_query = tep_db_query('select o.settlement_date, o.approval_code, o.last_xml_export, o.transaction_id, o.orders_id, o.customers_name, o.payment_method, o.date_purchased, o.last_modified, o.currency, o.currency_value, s.orders_status_name, ot.text as order_total from ' . TABLE_ORDERS_STATUS . " s, tmp_orders o left join tmp_orders_total ot on (o.orders_id = ot.orders_id) where o.orders_id = '" . (int) $orders_id . "'");
        $orders = tep_db_fetch_array($orders_query);
        if (!is_array($orders)) {
            die('Wrong order data.');
        }
        $o_info = new \Object_Info($orders);
        return $this->render('reassign', ['oInfo' => $o_info]);
    }
    public function action_confirmed_order_reassign()
    {
        $customers_id = Yii::$app->request->post('customers_id');
        $orders_id = Yii::$app->request->post('orders_id');
        $customers_query = tep_db_query('select * from ' . TABLE_CUSTOMERS . " where customers_id = '" . (int) $customers_id . "'");
        $customers = tep_db_fetch_array($customers_query);
        if (is_array($customers) && $orders_id > 0) {
            tep_db_query("update tmp_orders set customers_id = '" . (int) $customers_id . "', customers_name = '" . tep_db_input($customers['customers_firstname'] . ' ' . $customers['customers_lastname']) . "', customers_firstname = '" . tep_db_input($customers['customers_firstname']) . "', customers_lastname = '" . tep_db_input($customers['customers_lastname']) . "', customers_email_address = '" . tep_db_input($customers['customers_email_address']) . "' where orders_id = '" . (int) $orders_id . "';");
        }
    }
    public function action_orderdelete()
    {
        $this->layout = false;
        $orders_id = Yii::$app->request->post('orders_id');
        $admin = new Admin_Carts();
        //2do $admin->deleteCartByOrder($orders_id);
        \common\helpers\Order::remove_tmp_order($orders_id);
    }
    public function action_confirmorderdelete()
    {
        \common\helpers\Translation::init('admin/orders');
        $this->layout = false;
        $orders_id = Yii::$app->request->post('orders_id');
        $orders_query = tep_db_query('select o.child_id, o.settlement_date, o.approval_code, o.last_xml_export, o.transaction_id, o.orders_id, o.customers_name, o.payment_method, o.date_purchased, o.last_modified, o.currency, o.currency_value, s.orders_status_name, ot.text as order_total from ' . TABLE_ORDERS_STATUS . " s, tmp_orders o left join tmp_orders_total ot on (o.orders_id = ot.orders_id) where o.orders_id = '" . (int) $orders_id . "'");
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
        ?>
        <div class="btn-toolbar btn-toolbar-order">
            <?php 
        if (empty($o_info->child_id)) {
            echo '<button class="btn btn-delete btn-no-margin">' . IMAGE_DELETE . '</button><input type="button" class="btn btn-cancel" value="' . IMAGE_CANCEL . '" onClick="return cancelStatement()">';
        } else {
            echo TEXT_CANT_DELETE_HAS_CHILD . '<a href="' . \Yii::$app->url_manager->create_url(['orders/process-order', 'orders_id' => $o_info->child_id]) . '">' . $o_info->child_id . '</a><br>';
            echo '<input type="button" class="btn btn-no-margin btn-cancel" value="' . IMAGE_CANCEL . '" onClick="return cancelStatement()">';
        }
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
    /**
     * filter - countries suggest
     */
    public function action_countries()
    {
        $term = tep_db_prepare_input(Yii::$app->request->get('term'));
        $delivery_countries = \common\models\Tmp_Orders::find()->select('delivery_country')->and_where(['like', 'delivery_country', $term])->order_by('delivery_country')->distinct()->column();
        return $this->as_json($delivery_countries);
    }
    /**
     * filter - states suggest
     */
    public function action_state()
    {
        $term = tep_db_prepare_input(Yii::$app->request->get('term'));
        $country = tep_db_prepare_input(Yii::$app->request->get('country'));
        $delivery_states = \common\models\Tmp_Orders::find()->select('delivery_state')->and_where(['like', 'delivery_country', $country])->and_where(['like', 'delivery_state', $term])->order_by('delivery_state')->distinct()->column();
        return $this->as_json($delivery_states);
    }
    public function action_ordersdelete()
    {
        $this->layout = false;
        $selected_ids = Yii::$app->request->post('selected_ids');
        foreach ($selected_ids as $orders_id) {
            \common\helpers\Order::remove_tmp_order((int) $orders_id);
        }
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
    public function action_ordersexport()
    {
        if (tep_not_null($_POST['orders'])) {
            $filename = 'tmp_orders_' . strftime('%Y%b%d_%H%M') . '.csv';
            $writer = new \backend\models\EP\Formatter\CSV('write', [], $filename);
            $writer->write_array(['Order ID', 'Ship Method', 'Shipping Company', 'Shipping Street 1', 'Shipping Street 2', 'Shipping Suburb', 'Shipping State', 'Shipping Zip', 'Shipping Country', 'Shipping Name']);
            foreach (\common\models\Tmp_Orders::find()->where(['orders_id' => array_map('intval', explode(',', $_POST['orders']))])->all() as $order) {
                $writer->write_array([$order->orders_id, $order->shipping_method, $order->delivery_company, $order->delivery_street_address, $order->delivery_suburb, $order->delivery_city, $order->delivery_state, $order->delivery_postcode, $order->delivery_country, $order->delivery_name]);
            }
        }
        exit;
    }
    public function action_convert()
    {
        $ret = ['error' => 1, 'msg' => TEXT_UNEXPECTED_ERROR];
        $tmp_oid = (int) Yii::$app->request->post('orders_id');
        $current_date = (int) Yii::$app->request->post('current_date', 0);
        $manager = \common\services\Order_Manager::load_manager(new \common\classes\shopping_cart());
        $manager->cleanup_temporary_guests();
        $manager->clear_order_instance();
        $o_query = Orders::find()->where(['orders_id' => $tmp_oid]);
        if (false === \common\helpers\Acl::rule(['SUPERUSER'])) {
            global $login_id;
            $platforms = \common\models\Admin_Platforms::find()->select('platform_id')->where(['admin_id' => $login_id])->as_array()->column();
            $platforms[] = 0;
            $o_query->and_where(['platform_id' => $platforms]);
        }
        if (!$o_query->exists()) {
            $message_stack = \Yii::$container->get('message_stack');
            $message_stack->add_session(TEXT_ADMIN_ORDER_NOT_FOUND_ASSIGN_PLATFORMS, 'header', 'warning');
            return $this->redirect(\Yii::$app->url_manager->create_url(['tmp-orders/', 'by' => 'oID', 'search' => $tmp_oid]));
        } else {
            $o_model = $o_query->one();
            if ($o_model->platform_id) {
                $selected_platform_id = $o_model->platform_id;
            } else {
                $selected_platform_id = \common\classes\platform::first_id();
            }
        }
        $manager->set('platform_id', $selected_platform_id);
        Yii::$app->get('platform')->config($selected_platform_id)->constant_up();
        Yii::$app->get('platform')->config(\common\classes\platform::default_id())->constant_up();
        /* @var \common\classes\TmpOrder $tmporder */
        $tmporder = $manager->get_parent_to_instance_with_id('\common\classes\TmpOrder', $tmp_oid);
        if ($tmporder) {
            if ($current_date) {
                $tmporder->info['date_purchased'] = date(\common\helpers\Date::DATABASE_DATETIME_FORMAT);
            }
            $order_id = $tmporder->create_order();
            if (!$order_id) {
                $ret = ['error' => 1, 'msg' => TEXT_ERROR_TMP_ORDER_PROCESSED];
                \Yii::warning("tmporder is incorrect or processed {$tmp_oid}", 'TLDEBUG');
            } else {
                $ret = ['error' => 0, 'url' => \Yii::$app->url_manager->create_url(['orders/process-order', 'orders_id' => $order_id])];
            }
        } else {
            $ret = ['error' => 1, 'msg' => TEXT_ERROR_INCORRECT_TMP_ORDER];
            \Yii::warning("tmporder is incorrect {$tmp_oid}", 'TLDEBUG');
        }
        return $this->as_json($ret);
    }
    public function action_process_order()
    {
        global $login_id;
        define('THEME_NAME', \common\classes\design::page_name(BACKEND_THEME_NAME));
        $this->selected_menu = ['BOX_HEADING_CUSTOMERS', 'BOX_CUSTOMERS_TMP_ORDERS'];
        if (Yii::$app->request->is_post) {
            $o_id = Yii::$app->request->post('orders_id');
        } else {
            $o_id = Yii::$app->request->get('orders_id');
        }
        $manager = \common\services\Order_Manager::load_manager(new \common\classes\shopping_cart());
        $manager->cleanup_temporary_guests();
        $manager->clear_order_instance();
        $order = $manager->get_order_instance_with_id('\common\classes\TmpOrder', $o_id);
        $o_query = $order->get_ar_model()->where(['orders_id' => $o_id]);
        if (false === \common\helpers\Acl::rule(['SUPERUSER'])) {
            global $login_id;
            $platforms = \common\models\Admin_Platforms::find()->select('platform_id')->where(['admin_id' => $login_id])->as_array()->column();
            $platforms[] = 0;
            $o_query->and_where(['platform_id' => $platforms]);
        }
        if (!$o_query->exists()) {
            $message_stack = \Yii::$container->get('message_stack');
            $message_stack->add_session(TEXT_ADMIN_ORDER_NOT_FOUND_ASSIGN_PLATFORMS, 'header', 'warning');
            return $this->redirect(\Yii::$app->url_manager->create_url(['tmp-orders/', 'by' => 'oID', 'search' => $o_id]));
        } else {
            $o_model = $o_query->one();
            if ($o_id != $o_model->orders_id) {
                $o_id = $o_model->orders_id;
                $order = $manager->get_order_instance_with_id('\common\classes\TmpOrder', $o_id);
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
            return $this->redirect(\Yii::$app->url_manager->create_url(['tmp-orders/process-order', 'orders_id' => (int) $o_id]));
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
        $pagin_model = \common\models\Orders::find()->select('o.orders_id')->from('tmp_orders o ');
        if ($_session->has('search_condition')) {
            $pagin_model->and_where($_session->get('search_condition'));
        }
        $_orders_products_allocate_joined = false;
        if ($filter) {
            $pagin_model->left_join('tmp_orders_products op', 'o.orders_id = op.orders_id')->left_join(TABLE_ORDERS_STATUS . ' s', 'o.orders_status=s.orders_status_id')->left_join(TABLE_ORDERS_STATUS_GROUPS . ' sg', 's.orders_status_groups_id = sg.orders_status_groups_id');
            $pagin_model->left_join('tmp_orders_status_history osh', 'o.orders_id = osh.orders_id');
        }
        if ($filter && \common\helpers\Acl::check_extension_allowed('Handlers', 'allowed')) {
            $pagin_model->left_join('handlers_products hp', 'hp.products_id = op.products_id');
        }
        $order_next = $pagin_model->where("o.orders_id > '" . (int) $order->order_id . "'")->and_where($filter)->order_by('orders_id ASC')->limit(1)->as_array()->one();
        $order_prev = $pagin_model->where("o.orders_id < '" . (int) $order->order_id . "'")->and_where($filter)->order_by('orders_id DESC')->limit(1)->as_array()->one();
        $this->view->order_next = isset($order_next['orders_id']) ? $order_next['orders_id'] : 0;
        $this->view->order_prev = isset($order_prev['orders_id']) ? $order_prev['orders_id'] : 0;
        $order_language = \common\classes\language::get_code($order->info['language_id']);
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('tmp-orders/process-order?orders_id=' . $order->order_id), 'title' => TEXT_PROCESS_TMP_ORDER . '' . (!empty($order->info['order_number']) ? '<span class="order-number">' . $order->info['order_number'] . '</span> ' : '') . $order->order_id . ' <div class="head-or-time">' . TEXT_DATE_AND_TIME . '' . $order->info['date_purchased'] . '</div><div class="order-platform">' . TABLE_HEADING_PLATFORM . ':' . \common\classes\platform::name($order->info['platform_id']) . '</div>'];
        $_pl = Array_Helper::map(platform::get_list(false), 'id', 'id');
        if (!in_array($order->info['platform_id'], $_pl)) {
            $theme_platform_id = platform::default_id();
        } else {
            $theme_platform_id = $order->info['platform_id'];
        }
        $added_pages = \common\models\Themes_Settings::find()->alias('ts')->inner_join(TABLE_THEMES . ' t', 't.theme_name=ts.theme_name')->inner_join(TABLE_PLATFORMS_TO_THEMES . ' p2t', 't.id=p2t.theme_id')->select(['setting_name', 'setting_value', 'ts.id'])->where(['p2t.platform_id' => $theme_platform_id, 'setting_group' => 'added_page', 'setting_name' => ['packingslip', 'invoice']])->order_by('setting_name')->as_array()->all();
        $added_pages = Array_Helper::map($added_pages, 'id', 'setting_value', 'setting_name');
        $added_pages['invoice'] = $added_pages['invoice'] ?? [];
        $added_pages['invoice'] = [];
        // remove Ticket button
        $added_pages['packingslip'] = $added_pages['packingslip'] ?? [];
        $fraud_view = false;
        if (\common\helpers\Acl::check_extension_allowed('FraudAddress', 'allowed')) {
            $fraud_view = \common\extensions\Fraud_Address\Fraud_Address::fraud_view($order);
        }
        global $navigation;
        if (sizeof($navigation->snapshot) > 0) {
            $added_pages['backUrl'] = Yii::$app->url_manager->create_url(array_merge([$navigation->snapshot['page']], $navigation->snapshot['get']));
        } else {
            $added_pages['backUrl'] = Yii::$app->url_manager->create_url(['tmp-orders']);
        }
        $details = $order->get_details();
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
            'child_id' => $details['child_id'],
        ]);
    }
}