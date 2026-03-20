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

use backend\models\Product_Name_Decorator;
use common\helpers\Html;
use Yii;
use yii\helpers\Array_Helper;
/**
 * Coupon admin controller to handle user requests.
 */
class Coupon_admin_Controller extends Sceleton
{
    public $acl = ['BOX_HEADING_MARKETING_TOOLS', 'BOX_HEADING_GV_ADMIN', 'BOX_COUPON_ADMIN'];
    private static $date_options = ['active_on', 'start_between', 'end_between'];
    private static $by = [['name' => 'TEXT_ANY', 'value' => '', 'selected' => ''], ['name' => 'COUPON_CODE', 'value' => 'coupon_code', 'selected' => ''], ['name' => 'TEXT_COUPON', 'value' => 'coupon_name', 'selected' => ''], ['name' => 'COUPON_DESC', 'value' => 'coupon_description', 'selected' => '']];
    private static $filter_fields = ['search' => '', 'date' => '', 'inactive' => 'intval', 'pfrom' => 'floatval', 'pto' => 'floatval', 'dfrom' => ['list' => ['\common\helpers\Date', 'prepareInputDate']], 'dto' => ['list' => ['\common\helpers\Date', 'prepareInputDate']]];
    public function before_action($action)
    {
        if (false === \common\helpers\Acl::check_extension_allowed('CouponsAndVauchers', 'allowed')) {
            $this->redirect(['/']);
            return false;
        }
        return parent::before_action($action);
    }
    public function action_index()
    {
        \common\helpers\Translation::init('admin/coupon_admin');
        $this->selected_menu = ['marketing', 'gv_admin', 'coupon_admin'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('coupon_admin/index'), 'title' => HEADING_TITLE];
        $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url('coupon_admin/voucheredit') . '" class="btn btn-primary">' . IMAGE_INSERT . '</a>';
        $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url('coupon_admin/download-sample') . '" class="btn btn-primary backup"><i class="icon-file-text"></i>' . TEXT_SAMPLE . '</a>';
        $this->top_buttons[] = '<a href="javascript:void(0)" class="btn-import btn btn-primary backup"><i class="icon-file-text"></i>' . IMAGE_UPLOAD . '</a>';
        $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url('coupon_admin/voucherreport') . '" class="btn btn-primary"><i class="icon-file-text"></i>' . 'Redeem report' . '</a>';
        $this->view->heading_title = HEADING_TITLE;
        $this->view->coupon_table = [['title' => Html::checkbox('select_all', false, ['id' => 'select_all']), 'not_important' => 2], ['title' => DATE_CREATED, 'not_important' => 0], ['title' => COUPON_CODE, 'not_important' => 0], ['title' => COUPON_NAME, 'not_important' => 0], ['title' => TEXT_START_DATE, 'not_important' => 0], ['title' => TEXT_END_DATE, 'not_important' => 0], ['title' => COUPON_AMOUNT, 'not_important' => 0], ['title' => TEXT_REDEMPTIONS, 'not_important' => 0]];
        $this->view->sort_columns = '1,2,3,4,5,6,7';
        $this->view->filters = new \stdClass();
        $this->view->filters->row = (int) Yii::$app->request->get('row', 0);
        $gets = Yii::$app->request->get();
        $by = self::$by;
        foreach ($by as $key => $value) {
            $by[$key]['name'] = defined($by[$key]['name']) ? constant($by[$key]['name']) : strtolower(str_replace('_', ' ', $by[$key]['name']));
            if (isset($gets['by']) && $value['value'] == $gets['by']) {
                $by[$key]['selected'] = 'selected';
            }
        }
        $this->view->filters->by = $by;
        foreach (self::$date_options as $opt) {
            $this->view->filters->date_options[$opt] = defined('TEXT_' . strtoupper($opt)) ? constant('TEXT_' . strtoupper($opt)) : strtoupper($opt);
        }
        foreach (self::$filter_fields as $v => $f) {
            if (!empty($gets[$v])) {
                if (is_callable($f)) {
                    $this->view->filters->{$v} = call_user_func($f, $gets[$v]);
                } elseif (is_array($f) && !empty($f['filter']) && is_callable($f['filter'])) {
                    $this->view->filters->{$v} = call_user_func($f['filter'], $gets[$v]);
                } else {
                    $this->view->filters->{$v} = $gets[$v];
                }
            } else {
                $this->view->filters->{$v} = '';
            }
        }
        return $this->render('index');
    }
    public function action_list()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $currencies = Yii::$container->get('currencies');
        \common\helpers\Translation::init('admin/coupon_admin');
        $draw = (int) Yii::$app->request->get('draw', 1);
        $start = (int) Yii::$app->request->get('start', 0);
        $length = (int) Yii::$app->request->get('length', 10);
        $form_filter = Yii::$app->request->get('filter');
        $gets = [];
        parse_str($form_filter, $gets);
        if (isset($gets['date']) && in_array($gets['date'], self::$date_options)) {
            $date = $gets['date'];
        } else {
            $date = 'active_on';
        }
        if (isset($gets['by']) && in_array($gets['by'], \yii\helpers\Array_Helper::get_column(self::$by, 'value'))) {
            $by = $gets['by'];
        } else {
            $by = '';
        }
        $list_query = \common\models\Coupons::find()->alias('c')->join_with(['description'])->select('c.coupon_id, c.coupon_code, c.coupon_amount, c.coupon_currency, c.coupon_type, c.coupon_start_date, c.coupon_expire_date, c.coupon_active, c.date_created, c.date_modified');
        $inactive = false;
        foreach (self::$filter_fields as $v => $f) {
            if (!empty($gets[$v])) {
                if (is_callable($f)) {
                    if (is_array($gets[$v])) {
                        foreach ($gets[$v] as $k => $vv) {
                            $gets[$v][$k] = call_user_func($f, $vv);
                        }
                        $val = $gets[$v];
                    } else {
                        $val = call_user_func($f, $gets[$v]);
                    }
                } elseif (is_array($f) && !empty($f['list']) && is_callable($f['list'])) {
                    $val = call_user_func($f['list'], $gets[$v]);
                } else {
                    $val = $gets[$v];
                }
                switch ($v) {
                    case 'inactive':
                        $inactive = true;
                        break;
                    case 'pfrom':
                        $list_query->and_where(['>=', 'coupon_amount', $val]);
                        break;
                    case 'pto':
                        $list_query->and_where(['<=', 'coupon_amount', $val]);
                        break;
                    case 'dfrom':
                        if (in_array($date, ['start_between'])) {
                            $list_query->and_where(['>=', 'coupon_start_date', $val]);
                        } elseif (in_array($date, ['active_on'])) {
                            $list_query->and_where(['or', ['>=', 'coupon_expire_date', $val], ['<', 'coupon_expire_date', '1980-01-01']]);
                        } else {
                            $list_query->and_where(['>=', 'coupon_expire_date', $val]);
                        }
                        break;
                    case 'dto':
                        if (in_array($date, ['start_between'])) {
                            $list_query->and_where(['<=', 'coupon_start_date', $val]);
                        } elseif (in_array($date, ['active_on'])) {
                            $list_query->and_where(['<=', 'coupon_start_date', $val]);
                        } else {
                            $list_query->and_where(['<=', 'coupon_expire_date', $val]);
                        }
                        break;
                    case 'search':
                        if ($by == '') {
                            //all
                            $tmp = [];
                            foreach (\yii\helpers\Array_Helper::get_column(self::$by, 'value') as $field) {
                                if (!empty($field) && is_string($field)) {
                                    $tmp[] = ['like', $field, $val];
                                }
                            }
                            if (!empty($tmp)) {
                                $list_query->and_where(array_merge(['or'], $tmp));
                            }
                        } else {
                            $list_query->and_where(['like', $by, $val]);
                        }
                        break;
                }
            }
        }
        if (!$inactive) {
            $list_query->active();
        }
        $gets = Yii::$app->request->get();
        if (!empty($gets['search']['value'])) {
            $val = $gets['search']['value'];
            $tmp = [];
            foreach (\yii\helpers\Array_Helper::get_column(self::$by, 'value') as $field) {
                if (!empty($field) && is_string($field)) {
                    $tmp[] = ['like', $field, $val];
                }
            }
            if (!empty($tmp)) {
                $list_query->and_where(array_merge(['or'], $tmp));
            }
        }
        if (!empty($gets['order'][0]['column'])) {
            $dir = 'asc';
            if (!empty($gets['order'][0]['dir']) && $gets['order'][0]['dir'] == 'desc') {
                $dir = 'desc';
            }
            switch ($gets['order'][0]['column']) {
                case 1:
                    $list_query->add_order_by(' date_created ' . $dir);
                    $list_query->add_order_by(' coupon_code ');
                    break;
                case 2:
                    $list_query->add_order_by(' coupon_code ' . $dir);
                    break;
                case 3:
                    $list_query->add_order_by(' coupon_name ' . $dir);
                    break;
                case 4:
                    $list_query->add_order_by(' coupon_start_date ' . $dir);
                    break;
                case 5:
                    $list_query->add_order_by(' coupon_expire_date ' . $dir);
                    break;
                case 6:
                    $list_query->add_order_by(' coupon_amount ' . $dir);
                    break;
                case 7:
                    $list_query->left_join(['crt' => \common\models\Coupon_Redeem_Track::table_name()], 'c.coupon_id = crt.coupon_id')->add_group_by('c.coupon_id');
                    $list_query->add_order_by(new \yii\db\Expression('count(crt.order_id) ' . $dir));
                    break;
                default:
                    $list_query->add_order_by(' date_created desc ');
                    break;
            }
        } else {
            $list_query->add_order_by(' date_created desc ');
        }
        $response_list = [];
        if ($length == -1) {
            $length = 10000;
        }
        $current_page_number = $start / $length + 1;
        $query_numrows = $list_query->count();
        $list_query->offset($start)->limit($length);
        $list_query->add_select('coupon_name, coupon_description');
        if (!Yii::$app->request->is_ajax) {
            $list_query->select('coupon_code');
            $list_query->offset(null)->limit(null);
            $coupons = $list_query->as_array()->all();
            $this->layout = false;
            Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
            Yii::$app->response->send_content_as_file(implode("\r\n", Array_Helper::get_column($coupons, 'coupon_code')), 'coupon_codes_' . date('ymd') . '.txt', ['mimeType' => 'text/plain']);
            return;
        }
        $coupons = $list_query->as_array()->all();
        foreach ($coupons as $coupon) {
            $row = [];
            $row[] = Html::checkbox('bulkProcess[]', false, ['value' => $coupon['coupon_id']]) . Html::hidden_input('coupons_' . $coupon['coupon_id'], $coupon['coupon_id'], ['class' => 'cell_identify']) . ($coupon['coupon_active'] != 'Y' ? Html::hidden_input('coupons_st' . $coupon['coupon_id'], 'dis_module', ['class' => 'tr-status-class']) : '');
            if ($coupon['date_created'] > '1980-01-01') {
                $row[] = \common\helpers\Date::date_short($coupon['date_created']);
            } else {
                $row[] = '';
            }
            $row[] = $coupon['coupon_code'];
            $row[] = $coupon['coupon_name'];
            if ($coupon['coupon_start_date'] > '1980-01-01') {
                $row[] = \common\helpers\Date::date_short($coupon['coupon_start_date']);
            } else {
                $row[] = '';
            }
            if ($coupon['coupon_expire_date'] > '1980-01-01') {
                $row[] = \common\helpers\Date::date_short($coupon['coupon_expire_date']);
            } else {
                $row[] = '';
            }
            $coupon_amount = '';
            if ($coupon['coupon_type'] == 'P') {
                $coupon_amount = number_format($coupon['coupon_amount'], 2) . '%';
            } elseif ($coupon['coupon_amount'] > 0) {
                $coupon_amount = $currencies->format($coupon['coupon_amount'], false, $coupon['coupon_currency']);
            }
            if ($coupon['free_shipping'] ?? null) {
                if (!empty($coupon_amount)) {
                    $coupon_amount .= ' + ' . TEXT_FREE_SHIPPING;
                } else {
                    $coupon_amount = TEXT_FREE_SHIPPING;
                }
            }
            $row[] = $coupon_amount;
            $row[] = \common\models\Coupon_Redeem_Track::find()->where(['coupon_id' => $coupon['coupon_id']])->count();
            $response_list[] = $row;
        }
        $response = ['draw' => $draw, 'recordsTotal' => $query_numrows, 'recordsFiltered' => $query_numrows, 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_itempreedit()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/coupon_admin');
        $currencies = Yii::$container->get('currencies');
        $this->layout = false;
        $item_id = (int) Yii::$app->request->post('item_id', 0);
        $c_info = \common\models\Coupons::find()->and_where(['coupon_id' => (int) $item_id])->one();
        if (!$c_info) {
            die;
        }
        echo '<div class="or_box_head">[' . $c_info->coupon_id . ']  ' . $c_info->coupon_code . '</div>';
        if ($c_info->coupon_type == 'P') {
            $amount = number_format($c_info->coupon_amount, 2) . '%';
        } else {
            $amount = $currencies->format($c_info->coupon_amount, false, $c_info->coupon_currency);
        }
        $prod_details = TEXT_NONE;
        $cat_details = TEXT_NONE;
        $prod_ex_details = TEXT_NONE;
        $cat_ex_details = TEXT_NONE;
        if ($c_info->exclude_products) {
            $prod_ex_details = '<a href="#exclude_products" class="popUp" id="excProducts">' . IMAGE_VIEW . '</a>' . '<div id="exclude_products" style="display: none">' . \common\helpers\Product::get_admin_details_list($c_info->exclude_products) . '<script type="text/javascript">(function($){$(function(){$(\'#excProducts\').popUp();})})(jQuery)</script>' . '</div>';
        }
        if ($c_info->restrict_to_products) {
            $prod_details = '<a href="#include_products" class="popUp" id="incProducts">' . IMAGE_VIEW . '</a>' . '<div id="include_products" style="display: none">' . \common\helpers\Product::get_admin_details_list($c_info->restrict_to_products) . '<script type="text/javascript">(function($){$(function(){$(\'#incProducts\').popUp();})})(jQuery)</script>' . '</div>';
        }
        if ($c_info->exclude_categories) {
            $cat_ex_details = '<a href="#exclude_cats" class="popUp" id="excCats">' . IMAGE_VIEW . '</a>' . '<div id="exclude_cats" style="display: none">' . \common\helpers\Categories::get_admin_details_list($c_info->exclude_categories) . '<script type="text/javascript">(function($){$(function(){$(\'#excCats\').popUp();})})(jQuery)</script>' . '</div>';
        }
        if ($c_info->restrict_to_categories) {
            $cat_details = '<a href="#include_cats" class="popUp" id="incCats">' . IMAGE_VIEW . '</a>' . '<div id="include_cats" style="display: none">' . \common\helpers\Categories::get_admin_details_list($c_info->restrict_to_categories) . '<script type="text/javascript">(function($){$(function(){$(\'#incCats\').popUp();})})(jQuery)</script>' . '</div>';
        }
        $coupon_name_query = tep_db_query('select coupon_description from ' . TABLE_COUPONS_DESCRIPTION . " where coupon_id = '" . $c_info->coupon_id . "' and language_id = '" . $languages_id . "'");
        $coupon_name = tep_db_fetch_array($coupon_name_query);
        if ($c_info->tax_class_id == -1) {
            $taxc_class = TEXT_BY_ORDER_TAXES;
        } elseif ($c_info->tax_class_id) {
            $taxc_class = \common\helpers\Tax::get_tax_class_title($c_info->tax_class_id);
        } else {
            $taxc_class = TEXT_NONE;
        }
        echo '<div class="row_or_wrapp">';
        echo '<div class="row_or"><div>' . COUPON_DESC . ':</div><div>' . $coupon_name['coupon_description'] . '</div></div>';
        echo '<div class="row_or"><div>' . COUPON_AMOUNT . ':</div><div>' . $amount . '</div></div>';
        echo '<div class="row_or"><div>' . COUPON_STARTDATE . ':</div><div>' . \common\helpers\Date::date_short($c_info->coupon_start_date) . '</div></div>';
        echo '<div class="row_or"><div>' . COUPON_FINISHDATE . ':</div><div>' . \common\helpers\Date::date_short($c_info->coupon_expire_date) . '</div></div>';
        echo '<div class="row_or"><div>' . TEXT_RESTRICT_TO_CUSTOMERS . ':</div><div>' . $c_info->restrict_to_customers . '</div></div>';
        echo '<div class="row_or"><div>' . COUPON_USES_COUPON . ':</div><div>' . $c_info->uses_per_coupon . '</div></div>';
        echo '<div class="row_or"><div>' . COUPON_USES_USER . ':</div><div>' . $c_info->uses_per_user . '</div></div>';
        echo '<div class="row_or"><div>' . COUPON_PRODUCTS . ':</div><div>' . $prod_details . '</div></div>';
        echo '<div class="row_or"><div>' . COUPON_CATEGORIES . ':</div><div>' . $cat_details . '</div></div>';
        echo '<div class="row_or"><div>' . TEXT_EXCLUDE_PRODUCTS . ':</div><div>' . $prod_ex_details . '</div></div>';
        echo '<div class="row_or"><div>' . TEXT_EXCLUDE_CATEGORIES . ':</div><div>' . $cat_ex_details . '</div></div>';
        echo '<div class="row_or"><div>' . COUPON_USES_SHIPPING . ':</div><div>' . ($c_info->uses_per_shipping ? TEXT_BTN_YES : TEXT_BTN_NO) . '</div></div>';
        echo '<div class="row_or"><div>' . TEXT_PRODUCTS_TAX_CLASS . ':</div><div>' . $taxc_class . '</div></div>';
        echo '<div class="row_or"><div>' . DATE_CREATED . ':</div><div>' . \common\helpers\Date::date_short($c_info->date_created) . '</div></div>';
        echo '<div class="row_or"><div>' . DATE_MODIFIED . ':</div><div>' . \common\helpers\Date::date_short($c_info->date_modified) . '</div></div>';
        echo '</div>';
        echo '<div class="btn-toolbar btn-toolbar-order">';
        echo '<a href="' . tep_href_link('coupon_admin/couponemail', 'cid=' . $c_info->coupon_id, 'NONSSL') . '" class="btn btn-email-cus btn-no-margin">' . TEXT_EMAIL . '</a>';
        echo '<a href="' . Yii::$app->url_manager->create_url(['coupon_admin/voucheredit', 'cid' => $c_info->coupon_id]) . '" class="btn btn-edit">' . TEXT_EDIT . '</a>';
        echo '<a href="javascript:void(0)" onclick="deleteItemConfirm(' . $c_info->coupon_id . ')" class="btn btn-delete btn-no-margin">' . TEXT_DELETE . '</a>';
        echo '<a href="' . tep_href_link('coupon_admin/voucherreport', 'cid=' . $c_info->coupon_id, 'NONSSL') . '" class="btn btn-ord-cus">' . TEXT_REPORT . '</a>';
        echo '</div>';
    }
    public function action_voucheredit()
    {
        global $languages_id;
        \common\helpers\Translation::init('admin/coupon_admin');
        $this->selected_menu = ['marketing', 'gv_admin', 'coupon_admin'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('coupon_admin/index'), 'title' => HEADING_TITLE];
        $cid = (int) Yii::$app->request->get('cid');
        $coupon_name = [];
        $coupon_desc = [];
        $languages = \common\helpers\Language::get_languages();
        for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
            $languages[$i]['logo'] = $languages[$i]['image'];
            $language_id = $languages[$i]['id'];
            $coupon_query = tep_db_query('select coupon_name,coupon_description from ' . TABLE_COUPONS_DESCRIPTION . " where coupon_id = '" . $cid . "' and language_id = '" . $language_id . "'");
            $coupon = tep_db_fetch_array($coupon_query);
            if (isset($coupon['coupon_name'])) {
                $coupon_name[$language_id] = $coupon['coupon_name'];
            } else {
                $coupon_name[$language_id] = '';
            }
            if (isset($coupon['coupon_description'])) {
                $coupon_desc[$language_id] = $coupon['coupon_description'];
            } else {
                $coupon_desc[$language_id] = '';
            }
        }
        $coupon_free_ship = false;
        $coupon = \common\models\Coupons::find_one(['coupon_id' => $cid]);
        if ($coupon) {
            if ($coupon['coupon_type'] == 'P') {
                $coupon['coupon_amount'] = number_format($coupon['coupon_amount'], 2) . '%';
            }
        } else {
            $coupon = ['coupon_amount' => '', 'coupon_currency' => DEFAULT_CURRENCY, 'free_shipping' => 0, 'coupon_minimum_order' => '', 'coupon_code' => '', 'coupon_for_recovery_email' => 0, 'pos_only' => 0, 'spend_partly' => 0, 'uses_per_coupon' => '', 'uses_per_user' => '', 'uses_per_shipping' => '', 'flag_with_tax' => 0, 'restrict_to_products' => '', 'products_max_allowed_qty' => '', 'products_id_per_coupon' => '', 'restrict_to_categories' => '', 'restrict_to_manufacturers' => '', 'coupon_groups' => '', 'restrict_to_countries' => '', 'tax_class_id' => 0, 'coupon_start_date' => date('Y-m-d'), 'coupon_expire_date' => date('Y-m-d', strtotime('+ 1 month')), 'coupon_amount_maximum' => 0.0];
        }
        $coupon_currency = tep_draw_pull_down_menu('coupon_currency', \common\helpers\Currencies::get_currencies(1), $coupon['coupon_currency'], 'class="form-control"');
        $csv_imported_data = \common\models\Coupons_Customer_Codes_List::find()->and_where(['coupon_id' => (int) $cid])->one();
        if (!empty($coupon['check_platforms'])) {
            $this->view->platform_assigned = \common\models\Coupons_To_Platform::find()->select(['platform_id'])->and_where(['coupon_id' => (int) $cid])->as_array()->index_by('platform_id')->column();
        }
        $restrict_to_products_names = '';
        $products = \common\models\Products_Description::find()->select(['products_name'])->where(['IN', 'products_id', explode(',', $coupon['restrict_to_products'])])->and_where(['language_id' => $languages_id])->and_where(['platform_id' => \common\classes\platform::default_id()])->as_array()->all();
        foreach ($products as $product) {
            if ($product['products_name']) {
                $restrict_to_products_names .= '- ' . addslashes($product['products_name']) . "\n";
            }
        }
        if (!$restrict_to_products_names) {
            $restrict_to_products_names = defined('TEXT_ALL') ? TEXT_ALL : 'All';
        }
        $restrict_to_categories_names = '';
        $categories = \common\models\Categories_Description::find()->select(['categories_name'])->where(['IN', 'categories_id', explode(',', $coupon['restrict_to_categories'])])->and_where(['language_id' => $languages_id])->as_array()->all();
        foreach ($categories as $category) {
            if ($category['categories_name']) {
                $restrict_to_categories_names .= '- ' . addslashes($category['categories_name']) . "\n";
            }
        }
        if (!$restrict_to_categories_names) {
            $restrict_to_categories_names = defined('TEXT_ALL') ? TEXT_ALL : 'All';
        }
        $exclude_products_names = '';
        $products = \common\models\Products_Description::find()->select(['products_name'])->where(['IN', 'products_id', explode(',', $coupon['exclude_products'] ?? null)])->and_where(['language_id' => $languages_id])->and_where(['platform_id' => \common\classes\platform::default_id()])->as_array()->all();
        foreach ($products as $product) {
            if ($product['products_name']) {
                $exclude_products_names .= '- ' . addslashes($product['products_name']) . "\n";
            }
        }
        if (!$exclude_products_names) {
            $exclude_products_names = defined('OPTION_NONE') ? OPTION_NONE : 'None';
        }
        $exclude_categories_names = '';
        $categories = \common\models\Categories_Description::find()->select(['categories_name'])->where(['IN', 'categories_id', explode(',', $coupon['exclude_categories'] ?? null)])->and_where(['language_id' => $languages_id])->as_array()->all();
        foreach ($categories as $category) {
            if ($category['categories_name']) {
                $exclude_categories_names .= '- ' . addslashes($category['categories_name']) . "\n";
            }
        }
        if (!$exclude_categories_names) {
            $exclude_categories_names = defined('OPTION_NONE') ? OPTION_NONE : 'None';
        }
        $this->top_buttons[] = '<span class="btn btn-confirm" onclick="$(\'#save_voucher_form\').trigger(\'submit\')">' . IMAGE_SAVE . '</span>';
        if (!$cid) {
            $this->top_buttons[] = '<span class="btn btn-primary js-batch-create">' . TEXT_SAVE_BATCH . '</span>';
        }
        return $this->render('voucheredit', ['restrict_to_products_names' => $restrict_to_products_names, 'restrict_to_categories_names' => $restrict_to_categories_names, 'exclude_products_names' => $exclude_products_names, 'exclude_categories_names' => $exclude_categories_names, 'cid' => $cid, 'languages' => $languages, 'coupon_name' => $coupon_name, 'coupon_desc' => $coupon_desc, 'coupon_for_recovery_email' => $coupon['coupon_for_recovery_email'], 'pos_only' => $coupon['pos_only'], 'spend_partly' => $coupon['spend_partly'], 'coupon_currency' => $coupon_currency, 'coupon' => $coupon, 'coupon_start_date' => $coupon['coupon_start_date'] > 0 ? \common\helpers\Date::date_short($coupon['coupon_start_date']) : '', 'coupon_expire_date' => $coupon['coupon_expire_date'] > 0 ? \common\helpers\Date::date_short($coupon['coupon_expire_date']) : '', 'has_csv_data' => $csv_imported_data ? true : false, 'customers_coupons_csv' => \common\helpers\Coupon::get_customers_coupons_emails_list($cid), 'coupon_taxes' => [-1 => TEXT_BY_ORDER_TAXES, 0 => TEXT_NONE] + \common\models\Tax_Class::find()->select('tax_class_title, tax_class_id')->order_by('tax_class_title')->as_array()->index_by('tax_class_id')->column()]);
    }
    public function action_voucher_submit()
    {
        \common\helpers\Translation::init('admin/coupon_admin');
        $cid = (int) Yii::$app->request->post('coupon_id');
        $coupon_count = (int) Yii::$app->request->post('coupon_count', 1);
        if ($coupon_count <= 0) {
            $coupon_count = 1;
        }
        $check_platforms = (int) Yii::$app->request->post('check_platforms', 0);
        $coupon_startdate = '0';
        if (!empty($_POST['coupon_startdate'])) {
            $coupon_startdate = \common\helpers\Date::prepare_input_date($_POST['coupon_startdate']);
        }
        $coupon_finishdate = '0';
        if (!empty($_POST['coupon_finishdate'])) {
            $coupon_finishdate = \common\helpers\Date::prepare_input_date($_POST['coupon_finishdate']);
        }
        $coupon_code = tep_db_prepare_input($_POST['coupon_code'], false);
        $batch_coupon_code_prefix = trim($coupon_code);
        if (trim($coupon_code) === '') {
            $coupon_code = \common\helpers\Coupon::create_coupon_code();
        } else {
            //2do check for duplicate active coupon codes
        }
        $batch_mode = $coupon_count > 1 && empty($cid);
        $coupon_csv_loaded_coupon_file_name = Yii::$app->request->post('coupon_csv_loaded', false);
        if ($coupon_csv_loaded_coupon_file_name) {
            $coupon_code = '';
            $batch_mode = false;
            $coupon_count = 1;
        }
        $languages = \common\helpers\Language::get_languages();
        $batch_range = [0, 0];
        do {
            if ($batch_mode) {
                $coupon_code = \common\helpers\Coupon::create_prefixed_code($batch_coupon_code_prefix);
            }
            $coupon_type = 'F';
            if (substr(\Yii::$app->request->post('coupon_amount'), -1) == '%') {
                $coupon_type = 'P';
            }
            $sql_data_array = ['coupon_code' => $coupon_code, 'check_platforms' => $check_platforms, 'coupon_amount' => tep_db_prepare_input(\Yii::$app->request->post('coupon_amount')), 'coupon_currency' => tep_db_prepare_input(\Yii::$app->request->post('coupon_currency')), 'coupon_type' => $coupon_type, 'free_shipping' => \Yii::$app->request->post('free_shipping', 0) ? 1 : 0, 'uses_per_coupon' => tep_db_prepare_input(\Yii::$app->request->post('uses_per_coupon')), 'uses_per_user' => tep_db_prepare_input(\Yii::$app->request->post('uses_per_user')), 'single_per_order' => (int) Yii::$app->request->post('single_per_order', 0), 'uses_per_shipping' => tep_db_prepare_input(\Yii::$app->request->post('uses_per_shipping')), 'coupon_minimum_order' => tep_db_prepare_input(\Yii::$app->request->post('coupon_minimum_order')), 'restrict_to_products' => tep_db_prepare_input(\Yii::$app->request->post('restrict_to_products')), 'restrict_to_categories' => tep_db_prepare_input(\Yii::$app->request->post('restrict_to_categories')), 'restrict_to_customers' => tep_db_prepare_input(\Yii::$app->request->post('restrict_to_customers')), 'exclude_products' => tep_db_prepare_input(\Yii::$app->request->post('exclude_products')), 'exclude_categories' => tep_db_prepare_input(\Yii::$app->request->post('exclude_categories')), 'products_max_allowed_qty' => (int) \Yii::$app->request->post('products_max_allowed_qty') > 0 ? intval(\Yii::$app->request->post('products_max_allowed_qty')) : '', 'products_id_per_coupon' => (int) \Yii::$app->request->post('products_id_per_coupon') > 0 ? intval(\Yii::$app->request->post('products_id_per_coupon')) : '', 'disable_for_special' => intval(\Yii::$app->request->post('disable_for_special')), 'coupon_for_recovery_email' => intval(\Yii::$app->request->post('coupon_for_recovery_email')), 'pos_only' => intval(\Yii::$app->request->post('pos_only', 0)), 'spend_partly' => intval(\Yii::$app->request->post('spend_partly')), 'coupon_start_date' => $coupon_startdate, 'coupon_expire_date' => $coupon_finishdate, 'date_created' => 'now()', 'date_modified' => 'now()', 'coupon_amount_maximum' => tep_db_prepare_input(\Yii::$app->request->post('coupon_amount_maximum')), 'restrict_to_manufacturers' => implode(',', tep_db_prepare_input(\Yii::$app->request->post('restrict_to_manufacturers') ?? [])), 'coupon_groups' => implode(',', tep_db_prepare_input(\Yii::$app->request->post('coupon_groups') ?? [])), 'restrict_to_countries' => implode(',', tep_db_prepare_input(\Yii::$app->request->post('restrict_to_countries') ?? [])), 'tax_class_id' => tep_db_prepare_input(\Yii::$app->request->post('configuration_value')), 'flag_with_tax' => tep_db_prepare_input(\Yii::$app->request->post('flag_with_tax'))];
            for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
                $language_id = $languages[$i]['id'];
                $sql_data_marray[$i] = ['coupon_name' => tep_db_prepare_input(\Yii::$app->request->post('coupon_name')[$language_id] ?? null), 'coupon_description' => tep_db_prepare_input(\Yii::$app->request->post('coupon_description')[$language_id] ?? null)];
            }
            if ($cid > 0) {
                unset($sql_data_array['date_created']);
                tep_db_perform(TABLE_COUPONS, $sql_data_array, 'update', "coupon_id='" . $cid . "'");
                for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
                    $language_id = $languages[$i]['id'];
                    $check_lang = tep_db_fetch_array(tep_db_query('SELECT COUNT(*) AS c FROM ' . TABLE_COUPONS_DESCRIPTION . " WHERE coupon_id = '" . (int) $cid . "' AND language_id = '" . (int) $language_id . "'"));
                    if ($check_lang['c'] > 0) {
                        tep_db_perform(TABLE_COUPONS_DESCRIPTION, $sql_data_marray[$i], 'update', "coupon_id = '" . (int) $cid . "' AND language_id = '" . (int) $language_id . "'");
                    } else {
                        //            $update = tep_db_query("insert into " . TABLE_COUPONS_DESCRIPTION . " set coupon_name = '" . tep_db_prepare_input($_POST['coupon_name'][$language_id]) . "', coupon_description = '" . tep_db_prepare_input($_POST['coupon_desc'][$language_id]) . "', coupon_id = '" . $cid . "', language_id = '" . $language_id . "'");
                        $sql_data_marray[$i]['coupon_id'] = $cid;
                        $sql_data_marray[$i]['language_id'] = $language_id;
                        tep_db_perform(TABLE_COUPONS_DESCRIPTION, $sql_data_marray[$i]);
                    }
                }
            } else {
                tep_db_perform(TABLE_COUPONS, $sql_data_array);
                $cid = tep_db_insert_id();
                if ($coupon_csv_loaded_coupon_file_name != false) {
                    $res = \common\helpers\Coupon::save_csv_customers_coupons($coupon_csv_loaded_coupon_file_name, $cid);
                    if (!$res) {
                        //empty incorrect file upoloaded - delete coupon
                        \common\models\Coupons::delete_all(['coupon_id' => $cid]);
                        $cid = 0;
                        $message = TEXT_COUPON_INCORRECT_DUPLICATE_FILE;
                    }
                }
                if ($cid) {
                    for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
                        $language_id = $languages[$i]['id'];
                        $sql_data_marray[$i]['coupon_id'] = $cid;
                        $sql_data_marray[$i]['language_id'] = $language_id;
                        tep_db_perform(TABLE_COUPONS_DESCRIPTION, $sql_data_marray[$i]);
                    }
                }
            }
            //coupon to platforms
            try {
                \common\models\Coupons_To_Platform::delete_all('coupon_id =  :cid ', [':cid' => (int) $cid]);
                if ($check_platforms > 0) {
                    $platforms = Yii::$app->request->post('platform', []);
                    if (is_array($platforms)) {
                        $platforms = array_unique(array_map('intval', $platforms));
                        \Yii::$app->db->create_command('insert into ' . \common\models\Coupons_To_Platform::table_name() . ' (coupon_id, platform_id) ' . \common\models\Platforms::find()->select([new \yii\db\Expression((int) $cid), 'platform_id'])->andwhere(['platform_id' => $platforms])->create_command()->raw_sql)->execute();
                    }
                }
            } catch (\Exception $e) {
                \Yii::warning(' #### ' . print_r($e->get_message(), true), 'TLDEBUG');
            }
            foreach (\common\helpers\Hooks::get_list('coupon_admin/voucher-submit') as $filename) {
                include $filename;
            }
            if ($batch_mode) {
                $batch_range[1] = $cid;
                if (empty($batch_range[0])) {
                    $batch_range[0] = $cid;
                }
                $cid = 0;
            } else {
                break;
            }
            $coupon_count--;
        } while ($batch_mode && $coupon_count > 0);
        $message = TEXT_COUPON_UPDATED_NOTICE;
        if ($batch_mode) {
            $message .= '<br>' . Html::begin_form(['export-codes'], 'post', ['target' => '_blank']) . Html::hidden_input('batch_range_1', $batch_range[0]) . Html::hidden_input('batch_range_2', $batch_range[1]) . 'Download generated coupon codes <button type="submit" class="btn"><i class="icon-download"></i> Download</button>' . Html::end_form();
        }
        $message_type = 'success';
        if (!$batch_mode && $cid == 0) {
            $message_type = 'error';
        }
        ?>
    <div class="popup-box-wrap pop-mess">
      <div class="around-pop-up"></div>
      <div class="popup-box">
        <div class="pop-up-close pop-up-close-alert"></div>
        <div class="pop-up-content">
          <div class="popup-heading"><?php 
        echo TEXT_NOTIFIC;
        ?></div>
          <div class="popup-content pop-mess-cont pop-mess-cont-<?php 
        echo $message_type;
        ?>">
    <?php 
        echo $message;
        ?>
          </div>
        </div>
        <div class="noti-btn">
          <div></div>
          <div><span class="btn btn-primary"><?php 
        echo TEXT_BTN_OK;
        ?></span></div>
        </div>
      </div>
      <script>
        $('body').scrollTop(0);
        $('.pop-mess .pop-up-close-alert, .noti-btn .btn').click(function () {
          $(this).parents('.pop-mess').remove();
          <?php 
        if ($batch_mode) {
            ?>
            window.location.href="<?php 
            echo Yii::$app->url_manager->create_url(['coupon_admin/index']);
            ?>";
          <?php 
        }
        ?>
        });
      </script>
    </div>
    <?php 
        if ($batch_mode) {
            //echo '<script>setTimeout(function(){ window.location.href="' . Yii::$app->urlManager->createUrl(['coupon_admin/index']) . '";}, 1000);</script>';
        } else {
            echo '<script>setTimeout(function(){ window.location.href="' . Yii::$app->url_manager->create_url(['coupon_admin/voucheredit', 'cid' => $cid]) . '";}, 2000);</script>';
        }
    }
    public function action_export_codes()
    {
        $this->layout = false;
        $range1 = Yii::$app->request->post('batch_range_1');
        $range2 = Yii::$app->request->post('batch_range_2');
        $code_array = \common\models\Coupons::find()->select('coupon_code')->where(['>=', 'coupon_id', (int) $range1])->and_where(['<=', 'coupon_id', (int) $range2])->order_by(['coupon_id' => SORT_ASC])->as_array()->all();
        $coupon_name = \common\models\Coupons_Description::find()->where(['coupon_id' => (int) $range1])->and_where(['language_id' => \Yii::$app->settings->get('languages_id')])->select('coupon_name')->scalar();
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        Yii::$app->response->send_content_as_file(implode("\r\n", Array_Helper::get_column($code_array, 'coupon_code')), empty($coupon_name) ? 'coupon_codes_' . date('ymd') . '.txt' : 'coupon_codes_' . $coupon_name . '_' . date('ymd') . '.txt', ['mimeType' => 'text/plain']);
    }
    public function action_confirmitemdelete()
    {
        \common\helpers\Translation::init('admin/coupon_admin');
        $this->layout = false;
        $item_id = (int) Yii::$app->request->post('item_id');
        $cc_query = tep_db_query('select coupon_id, coupon_code, coupon_amount, coupon_currency, coupon_type, coupon_start_date,coupon_expire_date,uses_per_user,uses_per_coupon,restrict_to_products, restrict_to_categories, date_created,date_modified from ' . TABLE_COUPONS . " where coupon_id = '" . (int) $item_id . "'");
        $cc_list = tep_db_fetch_array($cc_query);
        $c_info = new \Object_Info($cc_list);
        echo tep_draw_form('item_delete', 'coupon_admin', \common\helpers\Output::get_all_get_params(['action']) . 'action=update', 'post', 'id="item_delete" onSubmit="return deleteItem();"');
        echo '<div class="or_box_head">' . '[' . $c_info->coupon_id . ']  ' . $c_info->coupon_code . '</div>';
        echo '<div class="col_desc">' . TEXT_CONFIRM_DELETE . '</div>';
        echo '<div class="btn-toolbar btn-toolbar-order">';
        echo '<button class="btn btn-no-margin btn-delete">' . IMAGE_DELETE . '</button>';
        echo '<input type="button" class="btn btn-cancel" value="' . IMAGE_CANCEL . '" onClick="return resetStatement()">';
        echo '</div>';
        echo tep_draw_hidden_field('item_id', $item_id);
        echo '</form>';
    }
    public function action_itemdelete()
    {
        $item_id = (int) Yii::$app->request->post('item_id');
        tep_db_query('update ' . TABLE_COUPONS . " set coupon_active = 'N' where coupon_id='" . $item_id . "'");
    }
    public function action_voucherreport()
    {
        // $this->view->headingTitle = HEADING_TITLE1;
        $this->view->heading_title = 'Reedem report';
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('coupon_admin/index'), 'title' => 'Reedem report'];
        $this->selected_menu = ['marketing', 'gv_admin', 'coupon_admin'];
        \common\helpers\Translation::init('admin/coupon_admin');
        //$this->layout = false;
        $coupon_id = intval(Yii::$app->request->get('cid', 0));
        $this->view->catalog_table = [['title' => CUSTOMER_NAME, 'not_important' => 0], ['title' => TEXT_ORDER_ID, 'not_important' => 0], ['title' => IP_ADDRESS, 'not_important' => 0], ['title' => REDEEM_DATE, 'not_important' => 0], ['title' => 'Discount Amount', 'not_important' => 0]];
        if (empty($coupon_id)) {
            array_splice($this->view->catalog_table, 4, null, [['title' => 'Coupon code', 'not_important' => 0]]);
        }
        $this->view->filters = new \stdClass();
        $this->view->filters->coupon_id = (int) Yii::$app->request->get('cid');
        $this->view->row_id = (int) Yii::$app->request->get('row');
        $gets = Yii::$app->request->get();
        $by = self::$by;
        foreach ($by as $key => $value) {
            $by[$key]['name'] = defined($by[$key]['name']) ? constant($by[$key]['name']) : strtolower(str_replace('_', ' ', $by[$key]['name']));
            if (isset($gets['by']) && $value['value'] == $gets['by']) {
                $by[$key]['selected'] = 'selected';
            }
        }
        $this->view->filters->by = $by;
        foreach (self::$date_options as $opt) {
            $this->view->filters->date_options[$opt] = defined('TEXT_' . strtoupper($opt)) ? constant('TEXT_' . strtoupper($opt)) : strtoupper($opt);
        }
        foreach (['search' => ''] as $v => $f) {
            if (!empty($gets[$v])) {
                if (is_callable($f)) {
                    $this->view->filters->{$v} = call_user_func($f, $gets[$v]);
                } elseif (is_array($f) && !empty($f['filter']) && is_callable($f['filter'])) {
                    $this->view->filters->{$v} = call_user_func($f['filter'], $gets[$v]);
                } else {
                    $this->view->filters->{$v} = $gets[$v];
                }
            } else {
                $this->view->filters->{$v} = '';
            }
        }
        return $this->render('voucherreport', ['coupon_id' => $coupon_id]);
    }
    public function action_report_usage_list()
    {
        $this->layout = false;
        \common\helpers\Translation::init('admin/coupon_admin');
        $form_filter = Yii::$app->request->get('filter');
        $output = [];
        parse_str($form_filter, $output);
        $filter = '';
        $coupon_id = intval($output['cid']);
        if ($coupon_id > 0) {
            $filter = " AND crt.coupon_id = '" . (int) $coupon_id . "' ";
        }
        $draw = Yii::$app->request->get('draw', 1);
        $start = Yii::$app->request->get('start', 0);
        $length = Yii::$app->request->get('length', 10);
        if ($length == -1) {
            $length = 10000;
        }
        $query_numrows = 0;
        $response_list = [];
        $join_description = false;
        $gets = $output;
        if (isset($gets['date']) && in_array($gets['date'], self::$date_options)) {
            $date = $gets['date'];
        } else {
            $date = 'active_on';
        }
        if (isset($gets['by']) && in_array($gets['by'], \yii\helpers\Array_Helper::get_column(self::$by, 'value'))) {
            $by = $gets['by'];
        } else {
            $by = '';
        }
        foreach (['search' => ''] as $v => $f) {
            if (!empty($gets[$v])) {
                if (is_callable($f)) {
                    if (is_array($gets[$v])) {
                        foreach ($gets[$v] as $k => $vv) {
                            $gets[$v][$k] = call_user_func($f, $vv);
                        }
                        $val = $gets[$v];
                    } else {
                        $val = call_user_func($f, $gets[$v]);
                    }
                } elseif (is_array($f) && !empty($f['list']) && is_callable($f['list'])) {
                    $val = call_user_func($f['list'], $gets[$v]);
                } else {
                    $val = $gets[$v];
                }
                switch ($v) {
                    case 'search':
                        if ($by == '') {
                            //all
                            $tmp = [];
                            foreach (\yii\helpers\Array_Helper::get_column(self::$by, 'value') as $field) {
                                if (!empty($field) && is_string($field)) {
                                    //$tmp[] = ['like', $field, $val];
                                    $by_prefix = 'cc.';
                                    if (in_array($field, ['coupon_name', 'coupon_description'])) {
                                        $by_prefix = 'ccd.';
                                    }
                                    $tmp[] = " {$by_prefix}{$field} like '%" . tep_db_input($val) . "%' ";
                                }
                            }
                            if (!empty($tmp)) {
                                //$listQuery->andWhere(array_merge(['or'], $tmp));
                                $filter .= ' AND (' . implode('or', $tmp) . ') ';
                            }
                        } else {
                            //$listQuery->andWhere(['like', $by, $val]);
                            $by_prefix = 'cc.';
                            if (in_array($by, ['coupon_name', 'coupon_description'])) {
                                $by_prefix = 'ccd.';
                            }
                            $filter .= " AND {$by_prefix}{$by} LIKE '%" . tep_db_input($val) . "%' ";
                        }
                        $join_description = true;
                        break;
                }
            }
        }
        if (isset($_GET['search']['value']) && tep_not_null($_GET['search']['value'])) {
            $keywords = tep_db_input(tep_db_prepare_input($_GET['search']['value']));
            $filter .= "AND (crt.redeem_ip LIKE '%{$keywords}%' OR c.customers_firstname LIKE '%{$keywords}%' OR c.customers_lastname LIKE '%{$keywords}%') ";
        }
        if (isset($_GET['order'][0]['column']) && $_GET['order'][0]['dir']) {
            $_dir = $_GET['order'][0]['dir'] == 'asc' ? 'asc' : 'desc';
            switch ($_GET['order'][0]['column']) {
                case 0:
                    $order_by = "c.customers_firstname {$_dir}, c.customers_lastname {$_dir} ";
                    break;
                case 1:
                    $order_by = "crt.order_id {$_dir} ";
                    break;
                case 2:
                    $order_by = "redeem_ip {$_dir} ";
                    break;
                default:
                    $order_by = "redeem_date {$_dir}";
                    break;
            }
        } else {
            $order_by = 'redeem_date desc';
        }
        $cc_query_raw = 'select distinct crt.*, ' . ' cc.coupon_code, ' . ' c.customers_id, c.customers_firstname, c.customers_lastname, ' . ' ot_coupon.value_inc_tax as discount_value, ot_coupon.text_inc_tax as discount_text, ' . ' o.orders_id ' . 'from ' . TABLE_COUPON_REDEEM_TRACK . ' crt ' . ' inner join ' . TABLE_COUPONS . ' cc ON cc.coupon_id=crt.coupon_id ' . ($join_description ? ' inner join ' . TABLE_COUPONS_DESCRIPTION . " ccd ON cc.coupon_id=ccd.coupon_id and ccd.language_id='" . \Yii::$app->settings->get('languages_id') . "' " : '') . ' left join ' . TABLE_CUSTOMERS . ' c ON c.customers_id=crt.customer_id ' . ' left join ' . TABLE_ORDERS . ' o ON o.orders_id=crt.order_id ' . ' left join ' . TABLE_ORDERS_TOTAL . " ot_coupon ON o.orders_id=ot_coupon.orders_id and ot_coupon.class='ot_coupon' and abs(crt.spend_amount-ot_coupon.value_inc_tax)<=0.01 " . 'where 1 ' . "{$filter} " . "order by {$order_by}";
        //"select coupon_id, coupon_code, coupon_amount, coupon_currency, coupon_type, coupon_start_date,coupon_expire_date,uses_per_user,uses_per_coupon,restrict_to_products, restrict_to_categories, date_created,date_modified from " . TABLE_COUPONS . " $search_condition order by $orderBy ";
        if (!Yii::$app->request->is_ajax) {
            $this->layout = false;
            Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
            Yii::$app->response->set_download_headers('Redeemed Codes.csv', 'application/vnd.ms-excel');
            $writer = new \backend\models\EP\Writer\CSV(['filename' => 'php://output', 'output_encoding' => 'UTF-8']);
            $writer->set_columns(['customers_firstname' => 'Customer Firstname', 'customers_lastname' => 'Customer Firstname', 'orders_id' => 'Order Id', 'redeem_date' => 'Redeem date', 'coupon_code' => 'Coupon Code', 'discount_value' => 'Discount Amount', 'redeems_count' => 'Overall Code Reedem Count']);
            $cc_query = tep_db_query($cc_query_raw);
            $redeem_counter = [];
            while ($cc_list = tep_db_fetch_array($cc_query)) {
                if (!isset($redeem_counter[$cc_list['coupon_id']])) {
                    $count_redemptions = tep_db_fetch_array(tep_db_query('select count(*) as cnt from ' . TABLE_COUPON_REDEEM_TRACK . " where coupon_id = '" . $cc_list['coupon_id'] . "'"));
                    $redeem_counter[$cc_list['coupon_id']] = (int) $count_redemptions['cnt'];
                }
                $cc_list['redeems_count'] = $redeem_counter[$cc_list['coupon_id']];
                $writer->write($cc_list);
            }
            return;
        }
        $current_page_number = $start / $length + 1;
        $_split = new \Split_Page_Results($current_page_number, $length, $cc_query_raw, $query_numrows, 'unique_id');
        $cc_query = tep_db_query($cc_query_raw);
        while ($cc_list = tep_db_fetch_array($cc_query)) {
            $response_row = [($cc_list['customers_id'] ? '<a target="_blank" href="' . Yii::$app->url_manager->create_url(['customers/customeredit', 'customers_id' => $cc_list['customers_id']]) . '">' . $cc_list['customers_firstname'] . ' ' . $cc_list['customers_lastname'] . '</a>' : $cc_list['customer_id']) . '<input class="cell_identify" type="hidden" value="' . $cc_list['unique_id'] . '">', $cc_list['orders_id'] ? '<a target="_blank" href="' . Yii::$app->url_manager->create_url(['orders/process-order', 'orders_id' => $cc_list['orders_id']]) . '">' . $cc_list['order_id'] . '</a>' : $cc_list['order_id'], $cc_list['redeem_ip'], \common\helpers\Date::date_short($cc_list['redeem_date']), (string) $cc_list['discount_text']];
            if (empty($coupon_id)) {
                array_splice($response_row, 4, null, [$cc_list['coupon_code']]);
            }
            $response_list[] = $response_row;
        }
        $response = ['draw' => $draw, 'recordsTotal' => $query_numrows, 'recordsFiltered' => $query_numrows, 'data' => $response_list];
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        Yii::$app->response->data = $response;
    }
    public function action_report_usage_info()
    {
        $currencies = Yii::$container->get('currencies');
        \common\helpers\Translation::init('admin/coupon_admin');
        $this->layout = false;
        $item_id = Yii::$app->request->post('item_id');
        $redeem_info = tep_db_fetch_array(tep_db_query('SELECT crt.*, cd.coupon_name ' . 'FROM ' . TABLE_COUPON_REDEEM_TRACK . ' crt ' . ' LEFT JOIN ' . TABLE_COUPONS_DESCRIPTION . " cd ON cd.coupon_id=crt.coupon_id AND cd.language_id='" . \Yii::$app->settings->get('languages_id') . "' " . "WHERE crt.unique_id='" . (int) $item_id . "'"));
        $count_redemptions = tep_db_fetch_array(tep_db_query('select count(*) as cnt from ' . TABLE_COUPON_REDEEM_TRACK . " where coupon_id = '" . Array_Helper::get_value($redeem_info, 'coupon_id') . "'"));
        $redemptions_total = $count_redemptions['cnt'];
        $count_customers = tep_db_fetch_array(tep_db_query('select count(*) as cnt from ' . TABLE_COUPON_REDEEM_TRACK . " where coupon_id = '" . Array_Helper::get_value($redeem_info, 'coupon_id') . "' and customer_id = '" . Array_Helper::get_value($redeem_info, 'customer_id') . "'"));
        $redemptions_customer = $count_customers['cnt'];
        echo '<div class="or_box_head">' . '[' . Array_Helper::get_value($redeem_info, 'coupon_id') . ']' . COUPON_NAME . ' ' . Array_Helper::get_value($redeem_info, 'coupon_name') . '</div>';
        echo '<div class="row_or_wrapp">';
        echo '<div class="row_or">' . '<b>' . TEXT_REDEMPTIONS . '</b>' . '</div>';
        // {{
        $amount_redemptions_query = tep_db_query('select o.currency, count(o.orders_id) as cnt, sum(ot.value) as amount from ' . TABLE_COUPON_REDEEM_TRACK . ' crt left join ' . TABLE_ORDERS . ' o ON o.orders_id = crt.order_id left join ' . TABLE_ORDERS_TOTAL . " ot ON o.orders_id = ot.orders_id and ot.class = 'ot_total' where coupon_id = '" . (int) $redeem_info['coupon_id'] . "' group by o.currency");
        while ($amount_redemptions = tep_db_fetch_array($amount_redemptions_query)) {
            echo '<div class="row_or"><div>' . $amount_redemptions['currency'] . ' (' . $amount_redemptions['cnt'] . '):</div><div>' . $currencies->format($amount_redemptions['amount'], false, $amount_redemptions['currency']) . '</div></div>';
        }
        // }}
        echo '<div class="row_or"><div>' . TEXT_REDEMPTIONS_TOTAL . '</div><div>' . $redemptions_total . '</div></div>';
        echo '<div class="row_or"><div>' . TEXT_REDEMPTIONS_CUSTOMER . '=</div><div>' . $redemptions_customer . '</div></div>';
        echo '</div>';
    }
    public function action_couponemail()
    {
        $message_stack = \Yii::$container->get('message_stack');
        $this->selected_menu = ['marketing', 'gv_admin', 'coupon_admin'];
        \common\helpers\Translation::init('admin/coupon_admin');
        $this->view->heading_title = HEADING_TITLE_SEND;
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('coupon_admin/couponemail'), 'title' => $this->view->heading_title];
        $msg = '';
        $send_coupon_id = intval(Yii::$app->request->get('cid', 0));
        if (Yii::$app->request->is_post) {
            $this->layout = false;
            $customers_email_address = Yii::$app->request->post('customers_email_address', '');
            $email_subject = Yii::$app->request->post('email_subject', '');
            $email_content = Yii::$app->request->post('email_content', '');
            $confirmed = Yii::$app->request->post('confirm_mul', 0);
            $mail_sent_to = TEXT_NONE;
            $send_status = 'success';
            if (empty($customers_email_address)) {
                $message_stack->add(ERROR_NO_CUSTOMER_SELECTED);
                $send_status = 'error';
            } else {
                switch ($customers_email_address) {
                    case '***':
                        if ($confirmed) {
                            $mail_query = tep_db_query('select customers_firstname, customers_lastname, customers_email_address from ' . TABLE_CUSTOMERS);
                            $mail_sent_to = TEXT_ALL_CUSTOMERS;
                        }
                        break;
                    case '**D':
                        if ($confirmed) {
                            /** @var \common\extensions\Subscribers\Subscribers $subscr  */
                            if ($subscr = \common\helpers\Acl::check_extension_allowed('Subscribers', 'allowed')) {
                                $mail_query = $subscr::get_db_query(['where' => 'all_lists = 1']);
                            } else {
                                //!! where 0
                                $mail_query = tep_db_query('select customers_firstname, customers_lastname, customers_email_address from ' . TABLE_CUSTOMERS . " where 0 and customers_newsletter = '1'");
                            }
                            $mail_sent_to = TEXT_NEWSLETTER_CUSTOMERS;
                        }
                        break;
                    default:
                        $mail_query = tep_db_query('select customers_firstname, customers_lastname, customers_email_address from ' . TABLE_CUSTOMERS . " where customers_email_address = '" . tep_db_input($customers_email_address) . "'");
                        $mail_sent_to = $customers_email_address;
                        break;
                }
                $send_counter = 0;
                $current_platform_id = \Yii::$app->get('platform')->config()->get_id();
                $platform_config = \Yii::$app->get('platform')->config($current_platform_id);
                $STORE_OWNER_EMAIL_ADDRESS = $platform_config->const_value('STORE_OWNER_EMAIL_ADDRESS');
                $STORE_OWNER = $platform_config->const_value('STORE_OWNER');
                while ($mail = tep_db_fetch_array($mail_query)) {
                    //Let's build a message object using the email class
                    \common\helpers\Mail::send($mail['customers_firstname'] . ' ' . $mail['customers_lastname'], $mail['customers_email_address'], $email_subject, $email_content, $STORE_OWNER, $STORE_OWNER_EMAIL_ADDRESS);
                    $send_counter++;
                }
                $msg = sprintf(NOTICE_EMAIL_SENT_TO, $mail_sent_to);
                $message_stack->add($msg, 'header', 'success');
            }
            return '<div class="pop-up-content">
                        <div class="popup-content pop-mess-cont pop-mess-cont-' . $send_status . '">
                        ' . $msg . '
                        </div>
                  </div>
                  <div class="noti-btn">
                            <div></div>
                            <div><span class="btn btn-primary" onclick="$(\'.popup-box-wrap:last\').remove();return false">' . TEXT_BTN_OK . '</span></div>
                        </div>';
        }
        $customers = [];
        $customers[] = ['id' => '', 'text' => TEXT_SELECT_CUSTOMER];
        $customers[] = ['id' => '***', 'text' => TEXT_ALL_CUSTOMERS];
        /** @var \common\extensions\Subscribers\Subscribers $subscr  */
        if ($subscr = \common\helpers\Acl::check_extension_allowed('Subscribers', 'allowed')) {
            $customers[] = ['id' => '**D', 'text' => TEXT_NEWSLETTER_CUSTOMERS];
        }
        $mail_query = tep_db_query('select customers_email_address, customers_firstname, customers_lastname from ' . TABLE_CUSTOMERS . ' where 1 order by customers_lastname');
        while ($customers_values = tep_db_fetch_array($mail_query)) {
            $customers[] = ['id' => $customers_values['customers_email_address'], 'text' => $customers_values['customers_lastname'] . ', ' . $customers_values['customers_firstname'] . ' (' . $customers_values['customers_email_address'] . ')'];
        }
        $coupon_query = tep_db_query('select c.coupon_code, cd.coupon_name, cd.coupon_description from ' . TABLE_COUPONS . ' c ' . ' left join ' . TABLE_COUPONS_DESCRIPTION . " cd ON cd.coupon_id=c.coupon_id and cd.language_id = '" . \Yii::$app->settings->get('languages_id') . "' " . "where c.coupon_id = '" . $send_coupon_id . "'");
        if (tep_db_num_rows($coupon_query) > 0) {
            $coupon_data = tep_db_fetch_array($coupon_query);
        } else {
            $coupon_data = [];
        }
        $email_params = [];
        $email_params['STORE_NAME'] = STORE_NAME;
        $email_params['STORE_URL'] = \common\helpers\Output::get_clickable_link(tep_catalog_href_link('', '', 'NONSSL'));
        $email_params['COUPON_CODE'] = $coupon_data['coupon_code'];
        $email_params['COUPON_NAME'] = $coupon_data['coupon_name'];
        $email_params['COUPON_DESCRIPTION'] = $coupon_data['coupon_description'];
        list($email_subject, $email_text) = \common\helpers\Mail::get_parsed_email_template('Send coupon', $email_params);
        return $this->render('couponemail', ['customers_variants' => $customers, 'customers_selected' => Yii::$app->request->get('customers', ''), 'email_from' => EMAIL_FROM, 'email_subject' => $email_subject, 'email_text' => $email_text, 'send_coupon_action' => tep_href_link('coupon_admin/couponemail', \common\helpers\Output::get_all_get_params(['action']))]);
    }
    public function action_treeview()
    {
        \common\helpers\Translation::init('admin/coupon_admin');
        $this->layout = false;
        ob_start();
        ?>
    <link rel="stylesheet" type="text/css" href="<?php 
        echo DIR_WS_ADMIN;
        ?>plugins/dtree/dtree.css" />
    <script language="javascript" type="text/javascript" src="<?php 
        echo DIR_WS_ADMIN;
        ?>plugins/dtree/dtree.js"></script>
    <div class="dtree" style="padding: 10px;"><form>
        <p><a href="javascript: d.openAll();"><?php 
        echo TEXT_OPEN_ALL;
        ?></a> | <a href="javascript: d.closeAll();"><?php 
        echo TEXT_CLOSE_ALL;
        ?></a></p>
        <div class="holder" style="overflow-y: scroll;"></div>

            <script type='text/javascript'>

                var d = new dTree('d');
                d.add(0,-1,'Catalog','','');
                window.productsArray = {};
                window.categoriesArray = {};
    <?php 
        $def_l_id = \common\helpers\Language::get_default_language_id();
        $categories_query_raw = 'SELECT c.categories_id, cd.categories_name, c.parent_id FROM ' . TABLE_CATEGORIES_DESCRIPTION . ' AS cd INNER JOIN ' . TABLE_CATEGORIES . ' as c ON cd.categories_id = c.categories_id WHERE cd.language_id =' . (int) $def_l_id . ' ORDER BY c.sort_order';
        $categories_query = tep_db_query($categories_query_raw);
        while ($categories = tep_db_fetch_array($categories_query)) {
            echo 'd.add(' . $categories['categories_id'] . ',' . $categories['parent_id'] . ',' . \json_encode((string) $categories['categories_name']) . ",'', '<input type=checkbox name=categories value=" . $categories['categories_id'] . ">');\n";
            //,," . $categories['categories_id'] . ",,,); \n";
            echo 'categoriesArray[' . $categories['categories_id'] . "] = '" . addslashes($categories['categories_name']) . "';\n";
        }
        //end while
        $products_query_raw = 'SELECT distinct pc.categories_id, pd.products_id, ' . Product_Name_Decorator::instance()->listing_query_expression('pd', '') . ' AS products_name FROM ' . TABLE_PRODUCTS_TO_CATEGORIES . ' as pc INNER JOIN ' . TABLE_PRODUCTS_DESCRIPTION . " as pd ON pc.products_id = pd.products_id where pd.language_id = '" . (int) $def_l_id . "' and pd.platform_id='" . (int) \common\classes\platform::default_id() . "'";
        $products_query = tep_db_query($products_query_raw);
        while ($products = tep_db_fetch_array($products_query)) {
            echo 'd.add(' . $products['products_id'] . '0000,' . $products['categories_id'] . ',' . \json_encode((string) $products['products_name']) . ",'', '<input type=checkbox name=products value=" . $products['products_id'] . ">');\n";
            //,," . $products['products_id'] . ",,,); \n";
            echo 'productsArray[' . $products['products_id'] . "] = '" . addslashes($products['products_name']) . "';\n";
        }
        //end while
        if (\Yii::$app->request->get('id', '') != '' && \Yii::$app->request->get('input', '') == 'exclude') {
            $cat_js_el = "document.querySelector('[data-id=exclude_categories_'+" . \Yii::$app->request->get('id', '') . "+']').value";
            $cat_js_el_names = "document.querySelector('[data-id=exclude_categories_names_'+" . \Yii::$app->request->get('id', '') . "+']').value";
            $prod_js_el = "document.querySelector('[data-id=exclude_products_'+" . \Yii::$app->request->get('id', '') . "+']').value";
            $prod_js_el_names = "document.querySelector('[data-id=exclude_products_names_'+" . \Yii::$app->request->get('id', '') . "+']').value";
        } elseif (\Yii::$app->request->get('id', '') != '') {
            $cat_js_el = "document.querySelector('[data-id=restrict_to_categories_'+" . \Yii::$app->request->get('id', '') . "+']').value";
            $cat_js_el_names = "document.querySelector('[data-id=restrict_to_categories_names_'+" . \Yii::$app->request->get('id', '') . "+']').value";
            $prod_js_el = "document.querySelector('[data-id=restrict_to_products_'+" . \Yii::$app->request->get('id', '') . "+']').value";
            $prod_js_el_names = "document.querySelector('[data-id=restrict_to_products_names_'+" . \Yii::$app->request->get('id', '') . "+']').value";
        } elseif (\Yii::$app->request->get('input', '') == 'exclude') {
            $cat_js_el = 'document.new_voucher.exclude_categories.value';
            $cat_js_el_names = 'document.new_voucher.exclude_categories_names.value';
            $prod_js_el = 'document.new_voucher.exclude_products.value';
            $prod_js_el_names = 'document.new_voucher.exclude_products_names.value';
        } else {
            $cat_js_el = 'document.new_voucher.restrict_to_categories.value';
            $cat_js_el_names = 'document.new_voucher.restrict_to_categories_names.value';
            $prod_js_el = 'document.new_voucher.restrict_to_products.value';
            $prod_js_el_names = 'document.new_voucher.restrict_to_products_names.value';
        }
        ?>
        $('.dtree .holder').append(d.toString());

            catIds = <?php 
        echo $cat_js_el;
        ?>.split(',').map(id => id.trim());//processed multiple times in threads
            prodIds = <?php 
        echo $prod_js_el;
        ?>.split(',').map(id => id.trim());//processed multiple times in threads
            catIds.forEach(id => {
                $('.dtree input[name="categories"][value="' + id + '"]').prop('checked', true)
            })
            prodIds.forEach(id => {
                $('.dtree input[name="products"][value="' + id + '"]').prop('checked', true)
            })

        </script>
            <button class="btn btn-primary" onClick="cycleCheckboxes(this.form)" style="float: right"><?php 
        echo TEXT_APPLY;
        ?></button>
            <span class="btn btn-cancle" onClick="return closePopup();"><?php 
        echo IMAGE_CANCEL;
        ?></span>
      </form>
      <script type='text/javascript'>

        $('.holder').css('max-height', document.body.clientHeight - 200);
        function cycleCheckboxes(what) {
           <?php 
        echo $prod_js_el;
        ?> = "";
           <?php 
        echo $prod_js_el_names;
        ?> = "";
           <?php 
        echo $cat_js_el;
        ?> = "";
           <?php 
        echo $cat_js_el_names;
        ?> = "";
          for (var i = 0; i < what.elements.length; i++) {
            if ((what.elements[i].name.indexOf('products') > -1)) {
              if (what.elements[i].checked) {
                <?php 
        echo $prod_js_el;
        ?> += what.elements[i].value + ',';
                <?php 
        echo $prod_js_el_names;
        ?> += '- ' + productsArray[what.elements[i].value] + "\n";
              }
            }
          }

          for (var i = 0; i < what.elements.length; i++) {
            if ((what.elements[i].name.indexOf('categories') > -1)) {
              if (what.elements[i].checked) {
                <?php 
        echo $cat_js_el;
        ?>  += what.elements[i].value + ',';
                <?php 
        echo $cat_js_el_names;
        ?>  += '- ' + categoriesArray[what.elements[i].value] + "\n";
              }
            }
          }

            if (!document.new_voucher.exclude_categories_names.value) document.new_voucher.exclude_categories_names.value = '<?php 
        echo OPTION_NONE;
        ?>';
            if (!document.new_voucher.exclude_products_names.value) document.new_voucher.exclude_products_names.value = '<?php 
        echo OPTION_NONE;
        ?>';
            if (!document.new_voucher.restrict_to_categories_names.value) document.new_voucher.restrict_to_categories_names.value = '<?php 
        echo TEXT_ALL;
        ?>';
            if (!document.new_voucher.restrict_to_products_names.valu) document.new_voucher.restrict_to_products_names.valu = '<?php 
        echo TEXT_ALL;
        ?>';


          closePopup();
        }
      </script>
    <?php 
        $buf = ob_get_contents();
        ob_end_clean();
        return $this->render('treeview', ['content' => $buf]);
    }
    private function get_import_fields()
    {
        return ['Code', 'Name', 'Description', 'Type', 'Amount', 'Currency', 'Amount with Tax', 'Minimum Order', 'Include Shipping', 'Tax Class', 'Coupon for Recovery Cart', 'Disable for special products', 'Only for customer(email)', 'Uses per Coupon', 'Uses per Customer', 'Valid From', 'Valid To', 'Valid Categories List', 'Valid Product List', 'Exclude products', 'Exclude categories', 'Valid Countries'];
    }
    private function get_sample_data()
    {
        return [['FIX299', 'Fixed', 'Fixed Discount', 'F', '2.99', DEFAULT_CURRENCY, 'YES', '50.00', 'YES', '', '', '', '', '1000', '1', '2021-01-01', '2021-01-31', 'Category Name 1;Category Name 2', 'SKU1;SKU2;SKU3', '', '', 'GB;US'], ['PERCENT10', 'Percent', 'Percent Discount', 'P', '10', '', 'YES', '', '', '', '', '', '', '1000', '1', '2021-01-01', '2021-01-31', '', '', '', '', ''], ['FREESHIP', 'Free Shipping', 'Free Shipping Code', 'S', '', '', 'YES', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '']];
    }
    public function action_download_sample()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        $writer = new \backend\models\EP\Formatter\CSV('write', [], 'Discount Coupons Upload.csv');
        $writer->write_array($this->get_import_fields());
        foreach ($this->get_sample_data() as $row) {
            $writer->write_array($row);
        }
    }
    public function action_import()
    {
        if (isset($_FILES['file']['tmp_name'])) {
            $tax_classes_by_ids = \common\models\Tax_Class::find()->select(['tax_class_title', 'tax_class_id'])->as_array()->index_by('tax_class_id')->column();
            $tax_classes = array_flip($tax_classes_by_ids);
            $languages = \common\helpers\Language::get_languages(true);
            $filename = $_FILES['file']['tmp_name'];
            //$uploadedHeaders = $this->getImportFields();//static header
            $CSV = new \backend\models\EP\Formatter\CSV('read', [], $filename);
            $uploaded_headers = $CSV->get_headers();
            //dynamic header
            $CSV->close();
            $CSV = new \backend\models\EP\Formatter\CSV('read', [], $filename);
            $uploaded_keys = array_flip($uploaded_headers);
            $CSV->set_read_remap_array($uploaded_headers);
            while ($data = $CSV->read_array()) {
                $object = \common\models\Coupons::find()->where(['coupon_code' => $data[$uploaded_keys['Code']]])->one();
                if (is_object($object)) {
                    continue;
                }
                $object = new \common\models\Coupons();
                $object->load_default_values();
                $object->coupon_code = $data[$uploaded_keys['Code']];
                $type = $data[$uploaded_keys['Type']];
                switch ($data[$uploaded_keys['Type']]) {
                    case 'F':
                    case 'P':
                    case 'S':
                        $type = $data[$uploaded_keys['Type']];
                        break;
                    default:
                        $type = 'P';
                        break;
                }
                $object->coupon_type = $type;
                $object->date_created = date(\common\helpers\Date::DATABASE_DATETIME_FORMAT);
                $object->coupon_amount = (float) $data[$uploaded_keys['Amount']];
                $object->coupon_currency = !empty($data[$uploaded_keys['Currency']]) ? $data[$uploaded_keys['Currency']] : DEFAULT_CURRENCY;
                $object->flag_with_tax = $data[$uploaded_keys['Amount with Tax']] == 'YES' ? 1 : 0;
                $object->coupon_minimum_order = (float) $data[$uploaded_keys['Minimum Order']];
                $object->uses_per_shipping = $data[$uploaded_keys['Include Shipping']] == 'YES' ? 1 : 0;
                $object->tax_class_id = isset($tax_classes[$data[$uploaded_keys['Tax Class']]]) ? $tax_classes[$data[$uploaded_keys['Tax Class']]] : (isset($tax_classes_by_ids[(int) $data[$uploaded_keys['Tax Class']]]) ? (int) $data[$uploaded_keys['Tax Class']] : 0);
                $object->coupon_for_recovery_email = $data[$uploaded_keys['Coupon for Recovery Cart']] == 'YES' ? 1 : 0;
                $object->pos_only = $data[$uploaded_keys['For POS only']] == 'YES' ? 1 : 0;
                $object->disable_for_special = $data[$uploaded_keys['Disable for special products']] == 'YES' ? 1 : 0;
                $object->restrict_to_customers = $data[$uploaded_keys['Only for customer(email)']];
                $object->uses_per_coupon = (int) $data[$uploaded_keys['Uses per Coupon']];
                $object->uses_per_user = (int) $data[$uploaded_keys['Uses per Customer']];
                $object->coupon_start_date = empty($data[$uploaded_keys['Valid From']]) ? '0000-00-00 00:00:00' : date('Y-m-d', strtotime($data[$uploaded_keys['Valid From']]));
                $object->coupon_expire_date = empty($data[$uploaded_keys['Valid To']]) ? '0000-00-00 00:00:00' : date('Y-m-d', strtotime($data[$uploaded_keys['Valid To']]));
                $object->coupon_active = 'Y';
                if (!empty($data[$uploaded_keys['Valid Categories List']])) {
                    $cat_list = explode(';', $data[$uploaded_keys['Valid Categories List']]);
                    $cat_checked = [];
                    foreach ($cat_list as $cat) {
                        if (!empty($cat)) {
                            $find_cat = \common\models\Categories_Description::find()->where(['categories_name' => $cat])->one();
                            if ($find_cat instanceof \common\models\Categories_Description) {
                                $cat_checked[] = $find_cat->categories_id;
                            }
                        }
                    }
                    if (count($cat_checked) > 0) {
                        $object->restrict_to_categories = implode(',', $cat_checked);
                    }
                }
                if (!empty($data[$uploaded_keys['Valid Product List']])) {
                    $prod_list = explode(';', $data[$uploaded_keys['Valid Product List']]);
                    $prod_checked = [];
                    foreach ($prod_list as $prod) {
                        if (!empty($prod)) {
                            $find_prod = \common\models\Products::find()->where(['products_model' => $prod])->one();
                            if ($find_prod instanceof \common\models\Products) {
                                $prod_checked[] = $find_prod->products_id;
                            }
                        }
                    }
                    if (count($prod_checked) > 0) {
                        $object->restrict_to_products = implode(',', $prod_checked);
                    }
                }
                if (!empty($data[$uploaded_keys['Exclude categories']])) {
                    $cat_list = explode(';', $data[$uploaded_keys['Exclude categories']]);
                    $cat_checked = [];
                    foreach ($cat_list as $cat) {
                        if (!empty($cat)) {
                            $find_cat = \common\models\Categories_Description::find()->where(['categories_name' => $cat])->one();
                            if ($find_cat instanceof \common\models\Categories_Description) {
                                $cat_checked[] = $find_cat->categories_id;
                            }
                        }
                    }
                    if (count($cat_checked) > 0) {
                        $object->exclude_categories = implode(',', $cat_checked);
                    }
                }
                if (!empty($data[$uploaded_keys['Exclude products']])) {
                    $prod_list = explode(';', $data[$uploaded_keys['Exclude products']]);
                    $prod_checked = [];
                    foreach ($prod_list as $prod) {
                        if (!empty($prod)) {
                            $find_prod = \common\models\Products::find()->where(['products_model' => $prod])->one();
                            if ($find_prod instanceof \common\models\Products) {
                                $prod_checked[] = $find_prod->products_id;
                            }
                        }
                    }
                    if (count($prod_checked) > 0) {
                        $object->exclude_products = implode(',', $prod_checked);
                    }
                }
                if (!empty($data[$uploaded_keys['Valid Countries']])) {
                    $count_list = explode(';', $data[$uploaded_keys['Valid Countries']]);
                    $count_checked = [];
                    foreach ($count_list as $count) {
                        if (!empty($count)) {
                            $find_count = \common\models\Countries::find()->where(['countries_iso_code_2' => $count])->one();
                            if ($find_count instanceof \common\models\Countries) {
                                $count_checked[] = $find_count->countries_id;
                            }
                        }
                    }
                    if (count($count_checked) > 0) {
                        $object->restrict_to_countries = implode(',', $count_checked);
                    }
                }
                $object->save(false);
                $coupon_id = $object->coupon_id;
                if ($coupon_id > 0) {
                    foreach ($languages as $_lang) {
                        $desc_object = new \common\models\Coupons_Description();
                        $desc_object->load_default_values();
                        $desc_object->coupon_id = $coupon_id;
                        $desc_object->language_id = $_lang['id'];
                        $desc_object->coupon_name = $data[$uploaded_keys['Name']];
                        $desc_object->coupon_description = $data[$uploaded_keys['Description']];
                        $desc_object->save(false);
                    }
                }
            }
            $CSV->close();
            unlink($filename);
        }
    }
}