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

use backend\models\Product_Edit\View_Price_Data;
use backend\models\Product_Name_Decorator;
use common\helpers\Date;
use common\helpers\Html;
use Yii;
use yii\helpers\Array_Helper;
class Specials_Controller extends Sceleton
{
    public $acl = ['BOX_HEADING_MARKETING_TOOLS', 'BOX_CATALOG_SPECIALS'];
    /**
     * @var \backend\models\ProductEdit\TabAccess
     */
    public $product_edit_tab_access;
    private static $date_options = ['active_on', 'start_between', 'end_between'];
    private static $by = [['name' => 'TEXT_ANY', 'value' => '', 'selected' => ''], ['name' => 'PRODUCTS_ID', 'value' => 'specials.products_id', 'selected' => ''], ['name' => 'PRODUCTS_MODEL', 'value' => 'products_model', 'selected' => ''], ['name' => 'PRODUCTS_NAME', 'value' => 'products_name', 'selected' => ''], ['name' => 'PRODUCTS_UPC', 'value' => 'products_upc', 'selected' => ''], ['name' => 'PRODUCTS_EAN', 'value' => 'products_ean', 'selected' => ''], ['name' => 'PRODUCTS_ISBN', 'value' => 'products_isbn', 'selected' => '']];
    private static $filter_fields = ['search' => '', 'date' => '', 'specials_type_id' => 'intval', 'group_id' => 'intval', 'inactive' => 'intval', 'pfrom' => 'floatval', 'pto' => 'floatval', 'dfrom' => ['list' => ['\common\helpers\Date', 'prepareInputDate']], 'dto' => ['list' => ['\common\helpers\Date', 'prepareInputDate']]];
    public function init()
    {
        parent::init();
        $this->product_edit_tab_access = new \backend\models\Product_Edit\Tab_Access();
    }
    public function action_index()
    {
        $this->selected_menu = ['marketing', 'specials'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('specials/index'), 'title' => HEADING_TITLE];
        $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url(['specials/specialedit']) . '" class="btn btn-primary" >' . IMAGE_INSERT . '</a>';
        $this->view->heading_title = HEADING_TITLE;
        $this->view->specials_table = [['title' => Html::checkbox('select_all', false, ['id' => 'select_all']), 'not_important' => 2], ['title' => DATE_CREATED, 'not_important' => 0], ['title' => TABLE_HEADING_PRODUCTS, 'not_important' => 0], ['title' => TABLE_HEADING_PRODUCTS_PRICE_OLD, 'not_important' => 0], ['title' => TABLE_HEADING_PRODUCTS_PRICE, 'not_important' => 0], ['title' => TEXT_START_DATE, 'not_important' => 0], ['title' => TEXT_END_DATE, 'not_important' => 0], ['title' => TEXT_QTY_LIMITS, 'not_important' => 0], ['title' => TABLE_HEADING_STATUS, 'not_important' => 1]];
        $languages_id = \Yii::$app->settings->get('languages_id');
        $specials_types_arr = \common\models\Specials_Types::find()->where(['language_id' => $languages_id])->select('specials_type_name, specials_type_id')->as_array()->index_by('specials_type_id')->column();
        if (!is_array($specials_types_arr)) {
            $specials_types_arr = [];
        }
        $specials_types_arr[0] = '';
        $specials_types_arr[-1] = TEXT_ALL;
        ksort($specials_types_arr);
        $this->view->types = $specials_types_arr;
        if (\common\helpers\Extensions::is_customer_groups_allowed()) {
            $this->view->groups = [];
            /** @var \common\extensions\UserGroups\UserGroups $ext */
            if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroups', 'allowed')) {
                $ext::get_groups();
            }
            $this->view->groups = array_merge([['groups_id' => -1, 'groups_name' => TEXT_ALL], ['groups_id' => 0, 'groups_name' => TEXT_MAIN]], $this->view->groups);
            $this->view->groups = \yii\helpers\Array_Helper::map($this->view->groups, 'groups_id', 'groups_name');
        }
        $this->view->filters = new \stdClass();
        $this->view->filters->row = (int) Yii::$app->request->get('row', 0);
        $gets = Yii::$app->request->get();
        $this->view->sort_columns = '1,2,3,4,5,6,7,8';
        if (!empty($gets['order']) && is_array($gets['order'])) {
        } else {
            \Yii::$app->controller->view->sort_now = '0,6';
            \Yii::$app->controller->view->sort_now_dir = 'desc,asc';
        }
        if (empty($gets)) {
            $gets['inactive'] = 1;
        }
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
        return $this->render('index', ['selected_type_id' => (int) \Yii::$app->request->get('specials_type_id', -1), 'group_id' => (int) \Yii::$app->request->get('group_id', -1)]);
    }
    public function action_index_popup()
    {
        \common\helpers\Translation::init('admin/categories');
        $prid = (int) \Yii::$app->request->get('prid', 0);
        if ($prid <= 0) {
            return '';
        }
        $this->view->specials_table = [['title' => DATE_CREATED, 'not_important' => 0], ['title' => TABLE_HEADING_PRICE_EXCLUDING_TAX, 'not_important' => 0], ['title' => TABLE_HEADING_PRICE_INCLUDING_TAX, 'not_important' => 0], ['title' => TEXT_START_DATE, 'not_important' => 0], ['title' => TEXT_END_DATE, 'not_important' => 0], ['title' => TEXT_QTY_LIMITS, 'not_important' => 0], ['title' => TABLE_HEADING_STATUS, 'not_important' => 1], ['title' => TABLE_HEADING_ACTION, 'not_important' => 1]];
        $this->view->sort_columns = '0,1,2,3,4,5,6';
        \Yii::$app->controller->view->sort_now = '0,6';
        \Yii::$app->controller->view->sort_now_dir = 'desc,asc';
        $list_query = \common\models\Specials::find()->with(['prices'])->select(\common\models\Specials::table_name() . '.*');
        $list_query->and_where([\common\models\Specials::table_name() . '.products_id' => $prid]);
        $list_query->join_with(['backendProductDescription'])->add_select('products_name');
        //    $listQuery->orderBy('status desc, start_date<now(), start_date, specials_disabled  desc');
        $items = $special = $list_query->all();
        $p = \common\models\Products::find()->and_where(['products_id' => (int) $prid]);
        $p_info = $p->as_array()->one();
        $tax = \common\helpers\Tax::get_tax_rate_value($p_info['products_tax_class_id']);
        /** @var \common\classes\Currencies $currencies */
        $currencies = Yii::$container->get('currencies');
        $params['price'] = $currencies->format($p_info['products_price']);
        $params['priceGross'] = $currencies->display_price($p_info['products_price'], $tax);
        $params['hash'] = \Yii::$app->request->get('_hash_', false);
        return $this->render_ajax('index-popup', $params + ['prid' => $prid, 'items' => $items, 'tax' => $tax]);
    }
    public function action_list()
    {
        $draw = Yii::$app->request->get('draw', 1);
        $start = Yii::$app->request->get('start', 0);
        $length = Yii::$app->request->get('length', 10);
        /** @var \common\classes\Currencies $currencies */
        $currencies = Yii::$container->get('currencies');
        $response_list = [];
        if ($length == -1) {
            $length = 10000;
        }
        $query_numrows = 0;
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
        $list_query = \common\models\Specials::find()->join_with(['backendProductDescription', 'specialsType'])->select(\common\models\Specials::table_name() . '.*');
        $inactive = false;
        $check_group = 0;
        foreach (self::$filter_fields as $v => $f) {
            if (isset($gets[$v]) && $gets[$v] != '') {
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
                    case 'specials_type_id':
                        if ($val >= 0) {
                            $list_query->and_where([\common\models\Specials::table_name() . '.specials_type_id' => $val]);
                        }
                        break;
                    case 'group_id':
                        if ($val >= 0) {
                            if ($val == 0) {
                                $list_query->and_where(['not exists', (new \yii\db\Query())->from(\common\models\Specials_Prices::table_name())->and_where(\common\models\Specials_Prices::table_name() . '.specials_id=' . \common\models\Specials::table_name() . '.specials_id')->and_where(['specials_new_products_price' => -1, 'groups_id' => 0])])->and_where('specials_new_products_price>0');
                            } else {
                                $list_query->and_where(['exists', (new \yii\db\Query())->from(\common\models\Specials_Prices::table_name())->and_where(\common\models\Specials_Prices::table_name() . '.specials_id=' . \common\models\Specials::table_name() . '.specials_id' . ' and specials_new_products_price<>-1')->and_where(['groups_id' => (int) $val])]);
                                $check_group = (int) $val;
                            }
                        }
                        break;
                    case 'pfrom':
                        $list_query->join_with('prices');
                        $list_query->and_where(['>=', \common\models\Specials_Prices::table_name() . '.specials_new_products_price', $val]);
                        $list_query->distinct();
                        break;
                    case 'pto':
                        $list_query->join_with('prices');
                        $list_query->and_where(['<=', \common\models\Specials_Prices::table_name() . '.specials_new_products_price', $val]);
                        $list_query->and_where(['>', \common\models\Specials_Prices::table_name() . '.specials_new_products_price', -0.0001]);
                        $list_query->distinct();
                        break;
                    case 'dfrom':
                        if (in_array($date, ['start_between'])) {
                            $list_query->start_after($val);
                        } elseif (in_array($date, ['active_on'])) {
                            $list_query->end_after($val);
                        } else {
                            //end between
                            $list_query->end_after($val);
                        }
                        break;
                    case 'dto':
                        if (in_array($date, ['start_between'])) {
                            $list_query->start_before($val);
                        } elseif (in_array($date, ['active_on'])) {
                            $list_query->start_before($val);
                        } else {
                            //end between
                            $list_query->end_before($val);
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
            $list_query->and_where('status=1');
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
        if (!empty($gets['order']) && is_array($gets['order'])) {
            foreach ($gets['order'] as $sort) {
                $dir = 'asc';
                if (!empty($sort['dir']) && $sort['dir'] == 'desc') {
                    $dir = 'desc';
                }
                switch ($sort['column']) {
                    case 1:
                        $list_query->add_order_by(' specials_date_added ' . $dir);
                        break;
                    case 2:
                        $list_query->add_order_by(' products_name ' . $dir);
                        break;
                    case 3:
                        $list_query->add_order_by(' products_price ' . $dir);
                        break;
                    case 4:
                        $list_query->add_order_by(' specials_new_products_price ' . $dir);
                        break;
                    case 5:
                        $list_query->add_order_by(' start_date ' . $dir);
                        break;
                    case 6:
                        $list_query->add_order_by(' expires_date ' . $dir);
                        break;
                    case 7:
                        $list_query->add_order_by(' total_qty ' . $dir);
                        break;
                    case 8:
                        $list_query->add_order_by(' status ' . $dir . ', specials_enabled desc, specials_disabled');
                        break;
                    default:
                        $list_query->add_order_by(' specials_date_added desc ');
                        break;
                }
            }
            $list_query->add_order_by(' products_name ');
        } else {
            $list_query->add_order_by(' specials_date_added desc, specials_enabled desc, specials_disabled ');
        }
        $response_list = [];
        $current_page_number = $start / $length + 1;
        $query_numrows = $list_query->count();
        if ($query_numrows < $start) {
            $start = 0;
        }
        $list_query->offset($start)->limit($length);
        $list_query->add_select('products_name, products_price, specials_type_name');
        //echo $listQuery->createCommand()->rawSql; die;
        $specials = $list_query->all();
        $groups = \common\helpers\Group::get_customer_groups();
        foreach ($specials as $s_info) {
            $special = $s_info->attributes;
            $special['product'] = $s_info->product->attributes;
            $special['productPrices'] = \yii\helpers\Array_Helper::index($s_info->product_prices, 'groups_id', 'currencies_id');
            $special['specialsType'] = $s_info->specials_type->attributes ?? null;
            $special['backendProductDescription'] = $s_info->backend_product_description->attributes;
            $row = [];
            $sold_out = $sold = 0;
            if (!empty($special['total_qty'])) {
                $sold = \common\helpers\Specials::get_sold_only_qty(['specials_id' => $special['specials_id']]);
                $sold_out = $sold >= $special['total_qty'];
            }
            $row[] = Html::checkbox('bulkProcess[]', false, ['value' => $special['specials_id']]) . Html::hidden_input('coupons_' . $special['specials_id'], $special['specials_id'], ['class' => 'cell_identify']) . (!$special['status'] ? Html::hidden_input('coupons_st' . $special['specials_id'], 'dis_module', ['class' => 'tr-status-class']) : ($sold_out ? Html::hidden_input('coupons_sts' . $special['specials_id'], 'alert alert-danger', ['class' => 'tr-status-class']) : '')) . Html::hidden_input('pid', $special['products_id'], ['class' => 'product-id']);
            if ($special['specials_date_added'] > '1980-01-01') {
                $row[] = \common\helpers\Date::date_short($special['specials_date_added']);
            } else {
                $row[] = '';
            }
            $name = $special['backendProductDescription']['products_name'] ?? '';
            if (!empty($special['specialsType']['specials_type_name'])) {
                $name = $special['specialsType']['specials_type_name'] . '<br>' . $name;
            }
            foreach (['products_model', 'products_upc', 'products_ean', 'products_isbn'] as $value) {
                if (!empty($special['product'][$value])) {
                    $name .= '<br>' . $special['product'][$value];
                }
            }
            $row[] = $name;
            //product prices
            $all_gross = $all_both = $all_net = '';
            $tax = \common\helpers\Tax::get_tax_rate_value($special['product']['products_tax_class_id']);
            if (!defined('USE_MARKET_PRICES') || USE_MARKET_PRICES != 'True') {
                $price = $special['product']['products_price'];
                $p['text'] = $currencies->format($price, true);
                $p['text_inc'] = $currencies->format($price * (1 + $tax / 100), true);
                $all_gross .= TEXT_MAIN . ': ' . $p['text_inc'] . " \n";
                $all_net .= TEXT_MAIN . ': ' . $p['text'] . " \n";
                $all_both .= TEXT_MAIN . ': ' . $p['text'] . ' ' . $p['text_inc'] . " \n";
                $new_price = $p['text'];
                $new_price_inc = $p['text_inc'];
            } else {
                $groups[0]['groups_name'] = TEXT_MAIN;
                $groups[0]['groups_discount'] = 0;
            }
            if (is_array($special['productPrices'])) {
                $prices = $special['productPrices'];
                foreach ($prices as $currencies_id => $cur) {
                    foreach ($cur as $gid => $p1) {
                        if ((!defined('USE_MARKET_PRICES') || USE_MARKET_PRICES != 'True') && $gid == 0) {
                            continue;
                        }
                        $p = [];
                        /** @var \common\classes\Currencies $currencies */
                        if ($p1['products_group_price'] == -2) {
                            $price = $special['product']['products_price'] * (1 - Array_Helper::get_value($groups, ['gid', 'groups_discount']) / 100);
                            $p['text'] = $currencies->format($price, true, $currencies_id > 0 ? \common\helpers\Currencies::get_currency_code($currencies_id) : '');
                            $p['text_inc'] = $currencies->format($price * (1 + $tax / 100), true, $currencies_id > 0 ? \common\helpers\Currencies::get_currency_code($currencies_id) : '');
                        } else {
                            $p['text'] = $currencies->format($p1['products_group_price'], true, $currencies_id > 0 ? \common\helpers\Currencies::get_currency_code($currencies_id) : '');
                            $p['text_inc'] = $currencies->format($p1['products_group_price'] * (1 + $tax / 100), true, $currencies_id > 0 ? \common\helpers\Currencies::get_currency_code($currencies_id) : '');
                        }
                        $all_gross .= $groups[$gid]['groups_name'] . ': ' . $p['text_inc'] . " \n";
                        $all_net .= $groups[$gid]['groups_name'] . ': ' . $p['text'] . " \n";
                        $all_both .= $groups[$gid]['groups_name'] . ': ' . $p['text'] . ' ' . $p['text_inc'] . " \n";
                        if ($check_group == $gid) {
                            $new_price = $p['text'];
                            $new_price_inc = $p['text_inc'];
                        }
                    }
                }
            }
            $row[] = "<div title='{$all_both}'><span style='display:block' class='net-price price'>{$new_price}</span> <span class='sale-info'></span> <span class='gross-price price'>" . $new_price_inc . '</span></div>';
            ///special prices
            $all_gross = $all_both = $all_net = '';
            $prices = \common\helpers\Specials::get_prices($s_info, $tax);
            if (is_array($prices)) {
                foreach ($prices as $cur) {
                    foreach ($cur as $p) {
                        $all_gross .= $p['group_name'] . ': ' . $p['text_inc'] . " \n";
                        $all_net .= $p['group_name'] . ': ' . $p['text'] . " \n";
                        $all_both .= $p['group_name'] . ': ' . $p['text'] . ' ' . $p['text_inc'] . " \n";
                    }
                }
                if (empty($prices[0][$check_group]['text'])) {
                    if (!\common\helpers\Extensions::is_customer_groups_allowed()) {
                        $new_price = TEXT_DISABLED;
                    } elseif ($check_group == 0) {
                        $new_price = sprintf(TEXT_PRICE_SWITCH_DISABLE, TEXT_MAIN);
                    } else {
                        $new_price = sprintf(TEXT_PRICE_SWITCH_DISABLE, $prices[0]['group_name']);
                    }
                    $new_price_inc = '';
                } else {
                    $new_price = $prices[0][$check_group]['text'];
                    $new_price_inc = $prices[0][$check_group]['text_inc'];
                }
            }
            $row[] = "<div title='{$all_both}'><span style='display:block' class='net-price price'>{$new_price}</span> <span class='sale-info'></span> <span class='gross-price price'>{$new_price_inc}</span></div>";
            $expired = $scheduled = false;
            if ($special['start_date'] > '1980-01-01') {
                $row[] = \common\helpers\Date::datetime_short($special['start_date']);
                if ($special['start_date'] > date('Y-m-d H:i:s') && !$special['status']) {
                    $scheduled = true;
                }
            } else {
                $row[] = '';
            }
            if ($special['expires_date'] > '1980-01-01') {
                $row[] = \common\helpers\Date::datetime_short($special['expires_date']);
                if ($special['expires_date'] < date('Y-m-d H:i:s')) {
                    $expired = true;
                }
            } else {
                $row[] = '';
            }
            $row[] = !empty($special['total_qty'] || !empty($special['max_per_order'])) ? $special['total_qty'] . '/' . $special['max_per_order'] . ($sold ? '<span class="right-link">(' . $sold . ')</span>' : '') : '';
            $row[] = \common\helpers\Specials::status_description_text($special['specials_enabled'], $special['specials_disabled'], $expired, $scheduled);
            /*($expired? TEXT_EXPIRED . '<BR>':
              ($special['specials_disabled']? TEXT_DISABLED. '<br>':
               ($scheduled?TEXT_SCHEDULED . '<br>':'')))
              . (!$expired ||$special['status'] ?
                  Html::checkbox('specials_status' . $special['specials_id'], $special['status'], ['value' => $special['specials_id'], 'class' => ($length < CATALOG_SPEED_UP_DESIGN ? 'check_on_off' : 'check_on_off_check' )]):'')*/
            $response_list[] = $row;
        }
        $response = ['draw' => $draw, 'recordsTotal' => $query_numrows, 'recordsFiltered' => $query_numrows, 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_itempreedit()
    {
        \common\helpers\Translation::init('admin/specials');
        /** @var \common\classes\Currencies $currencies */
        $currencies = Yii::$container->get('currencies');
        $groups = [];
        /** @var \common\extensions\UserGroups\UserGroups $ext */
        if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroups', 'allowed')) {
            $groups = $ext::get_groups_array();
        }
        //$groups = array_merge(array(array('groups_id' => 0, 'groups_name' => TEXT_MAIN)), array_filter($groups, function($e) { return (!isset($e['per_product_price']) || $e['per_product_price']); }));
        $groups = array_merge([['groups_id' => 0, 'groups_name' => TEXT_MAIN]], $groups);
        $_def_curr_id = $currencies->currencies[DEFAULT_CURRENCY]['id'];
        $this->layout = false;
        $item_id = (int) Yii::$app->request->post('item_id', 0);
        $s_info = \common\models\Specials::find()->and_where(['specials_id' => $item_id])->with(['prices', 'backendProductDescription'])->one();
        if (!empty($s_info->specials_id)) {
            $back_params = [];
            parse_str(Yii::$app->request->post('bp'), $back_params);
            $back_params = array_filter($back_params);
            ?>
    <div class="or_box_head or_box_head_no_margin"><?php 
            echo $s_info->backend_product_description->products_name;
            ?></div>
    <div class="row_or_wrapp">
    <?php 
            echo '<div class="row_or"><div>' . TEXT_INFO_DATE_ADDED . '</div><div>' . \common\helpers\Date::date_short($s_info->specials_date_added) . '</div></div>';
            echo '<div class="row_or"><div>' . TEXT_INFO_LAST_MODIFIED . '</div><div>' . \common\helpers\Date::date_short($s_info->specials_last_modified) . '</div></div>';
            echo '<div class="row_or"><div>' . TEXT_INFO_STATUS_CHANGE . '</div><div class="date-time-smaller">' . \common\helpers\Date::datetime_short($s_info->date_status_change) . '</div></div><hr>';
            $p = \common\models\Products::find()->and_where(['products_id' => (int) $s_info->products_id]);
            $p_info = $p->as_array()->one();
            $tax = \common\helpers\Tax::get_tax_rate_value($p_info['products_tax_class_id']);
            $prices = \common\helpers\Specials::get_prices($s_info, $tax);
            if (is_array($prices)) {
                $res = '';
                $res .= '<div class="row_or"><div>' . TEXT_INFO_NEW_PRICE . '</div></div>';
                foreach ($groups as $group) {
                    //$salesDetails = \common\helpers\Specials::getStatus($sInfo->specials_id, $tax, $group['groups_id'], 0);
                    $_exists = false;
                    $_price_details = '';
                    if (defined('USE_MARKET_PRICES') && USE_MARKET_PRICES == 'True') {
                        $_price_details .= '<div class="m-prices">';
                        foreach ($currencies->currencies as $value) {
                            if (!empty($group['per_product_price']) || isset($prices[$value['id']][$group['groups_id']])) {
                                $_exists = true;
                                //$salesDetails = \common\helpers\Specials::getStatus($sInfo->specials_id, $tax, $group['groups_id'], $value['id']);
                                $sales_details = $prices[$value['id']][$group['groups_id']];
                                $_price_details .= '<div class="currency">' . ($sales_details['text'] ?? TEXT_DISABLED) . '</div>';
                                if (abs($sales_details['value'] - $sales_details['value_inc']) >= 0.01) {
                                    $_price_details .= '<div class="currency">' . ($sales_details['text_inc'] ?? '') . '</div>';
                                }
                            }
                        }
                        $_price_details .= '</div>';
                    } else if (!empty($group['per_product_price']) || isset($prices[0][$group['groups_id']])) {
                        $_exists = true;
                        $sales_details = $prices[0][$group['groups_id']] ?? null;
                        $_price_details .= '<div class="row_or"><div style="font-weight:normal;" class="currency currency-net">' . ($sales_details['text'] ?? TEXT_DISABLED) . '</div>';
                        if (abs(Array_Helper::get_value($sales_details, 'value') - Array_Helper::get_value($sales_details, 'value_inc')) >= 0.01) {
                            $_price_details .= '<div class="currency">&nbsp;' . ($sales_details['text_inc'] ?? '') . '</div>';
                        }
                        $_price_details .= '</div>';
                    }
                    if ($_exists) {
                        $res .= '<div class="row_or"><div class="group-name" style="vertical-align: top;">' . $group['groups_name'] . '</div>';
                        $res .= $_price_details;
                        $res .= '</div>';
                    }
                }
                echo $res;
            }
            echo '<div class="row_or">&nbsp;</div><div class="row_or"><div>' . TEXT_START_DATE . '</div><div  class="date-time-smaller">' . \common\helpers\Date::datetime_short($s_info->start_date) . '</div></div>';
            echo '<div class="row_or"><div>' . TEXT_INFO_EXPIRES_DATE . '</div><div  class="date-time-smaller">' . \common\helpers\Date::datetime_short($s_info->expires_date) . '</div></div>';
            ?>
    </div>
    <div class="btn-toolbar btn-toolbar-order">
      <a class="btn btn-edit btn-no-margin" href="<?php 
            echo Yii::$app->url_manager->create_url(['specials/specialedit', 'id' => $s_info->specials_id, 'bp' => $back_params]);
            ?>"><?php 
            echo IMAGE_EDIT;
            ?></a><button class="btn btn-delete" onclick="return deleteItemConfirm(<?php 
            echo $item_id;
            ?>)"><?php 
            echo IMAGE_DELETE;
            ?></button>
      <?php 
            if (\common\helpers\Acl::check_extension_allowed('ReportOrderedProducts')) {
                ?>
      <a class="btn btn-no-margin" href="<?php 
                echo Yii::$app->url_manager->create_url(['ordered-products-report', 'specials_id' => $s_info->specials_id, 'start_date' => Date::format_calendar_date($s_info->specials_date_added)]);
                ?>"><?php 
                echo IMAGE_REPORT;
                ?></a>
      <?php 
            }
            ?>
    </div>
    <?php 
        }
    }
    /**
     * @deprecated
     */
    public function action_itemedit()
    {
        $this->layout = false;
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/specials');
        $item_id = (int) Yii::$app->request->post('item_id');
        $currencies = Yii::$container->get('currencies');
        //$_params = Yii::app()->getParams();
        //if( !isset( $_params->currencies ) ) Yii::app()->setParams( array( 'currencies' => $currencies ) );
        $header = '';
        $script = '';
        $delete_btn = '';
        $form_html = '';
        $fields = [];
        $languages = \common\helpers\Language::get_languages();
        if ($item_id === 0) {
            // Insert
            $header = 'Insert';
            $s_info = new \Object_Info([]);
            $specials_array = [];
            $specials_query = tep_db_query('select p.products_id from ' . TABLE_PRODUCTS . ' p, ' . TABLE_SPECIALS . ' s where s.products_id = p.products_id');
            while ($specials = tep_db_fetch_array($specials_query)) {
                $specials_array[] = $specials['products_id'];
            }
            $special_product_html = \common\helpers\Product::draw_products_pull_down('products_id', 'style="font-size:10px"', $specials_array);
            $fields[] = ['type' => 'field', 'title' => TEXT_SPECIALS_PRODUCT, 'value' => $special_product_html];
            $fields[] = ['name' => 'products_price', 'type' => 'hidden', 'value' => ''];
            if (USE_MARKET_PRICES == 'True') {
                foreach ($currencies->currencies as $key => $value) {
                    $specials_products_price_html = tep_draw_input_field('specials_new_products_price[' . $currencies->currencies[$key]['id'] . ']', \common\helpers\Product::get_specials_price($s_info->specials_id, $currencies->currencies[$key]['id']), 'size="20"');
                    $fields[] = ['type' => 'field', 'title' => $currencies->currencies[$key]['title'], 'value' => $specials_products_price_html];
                }
                $data_query = tep_db_query('select * from ' . TABLE_GROUPS . ' order by groups_id');
                while ($data = tep_db_fetch_array($data_query)) {
                    $data_html = tep_draw_input_field('specials_new_products_price_' . $data['groups_id'] . '[' . $currencies->currencies[$key]['id'] . ']', \common\helpers\Product::get_specials_price($s_info->specials_id, $currencies->currencies[$key]['id'], $data['groups_id'], '-2'), 'size="20"');
                    $fields[] = ['type' => 'field', 'title' => $data['groups_name'], 'value' => $data_html];
                }
            } else {
                $fields[] = ['name' => 'specials_price', 'title' => TEXT_SPECIALS_SPECIAL_PRICE, 'value' => ''];
            }
            $fields[] = ['name' => 'expires_date', 'title' => TEXT_SPECIALS_EXPIRES_DATE, 'class' => 'datepicker', 'value' => ''];
        } else {
            // Update
            $header = 'Edit';
            $product_query = tep_db_query('select p.products_id, s.specials_id, ' . Product_Name_Decorator::instance()->listing_query_expression('pd', '') . ' AS products_name, p.products_price, s.specials_new_products_price, s.expires_date, s.status from ' . TABLE_PRODUCTS . ' p, ' . TABLE_PRODUCTS_DESCRIPTION . ' pd, ' . TABLE_SPECIALS . " s where p.products_id = pd.products_id and pd.language_id = '" . (int) $languages_id . "' and pd.platform_id = '" . intval(\common\classes\platform::default_id()) . "' and p.products_id = s.products_id and s.specials_id = '" . (int) $item_id . "'");
            $product = tep_db_fetch_array($product_query);
            $s_info = new \Object_Info($product);
            $specials_array = [];
            $specials_query = tep_db_query('select p.products_id from ' . TABLE_PRODUCTS . ' p, ' . TABLE_SPECIALS . ' s where s.products_id = p.products_id');
            while ($specials = tep_db_fetch_array($specials_query)) {
                $specials_array[] = $specials['products_id'];
            }
            if (isset($s_info->products_name)) {
                $special_product_html = $s_info->products_name . ' <small>(' . $currencies->format(\common\helpers\Product::get_products_price($s_info->products_id, 1, 0, $currencies->currencies[DEFAULT_CURRENCY]['id'])) . ')</small>';
            } else {
                $special_product_html = \common\helpers\Product::draw_products_pull_down('products_id', 'style="font-size:10px"', $specials_array);
            }
            $fields[] = ['type' => 'field', 'title' => TEXT_SPECIALS_PRODUCT, 'value' => $special_product_html];
            $status_checked_disabled = false;
            $status_checked_active = false;
            if ((int) $s_info->status > 0) {
                $status_checked_active = true;
            } else {
                $status_checked_disabled = true;
            }
            $status_html = tep_draw_checkbox_field('status', '1', $status_checked_active, '', 'class="check_on_off"');
            /*                $status_html .= "Active " . tep_draw_radio_field( 'status', 1, $status_checked_active );
                          $status_html .= '<br>';
                          $status_html .= "Inactive " . tep_draw_radio_field( 'status', '0', $status_checked_disabled ); */
            $fields[] = ['type' => 'field', 'title' => TABLE_HEADING_STATUS, 'value' => $status_html];
            if (USE_MARKET_PRICES == 'True') {
                $specials_products_price_html = '';
                foreach ($currencies->currencies as $key => $value) {
                    $specials_products_price_html = tep_draw_input_field('specials_new_products_price[' . $currencies->currencies[$key]['id'] . ']', $specials_new_products_price[$currencies->currencies[$key]['id']] ? stripslashes($specials_new_products_price[$currencies->currencies[$key]['id']]) : \common\helpers\Product::get_specials_price($s_info->specials_id, $currencies->currencies[$key]['id']), 'size="20"');
                    $fields[] = ['type' => 'field', 'title' => $currencies->currencies[$key]['title'], 'value' => $specials_products_price_html];
                }
                $data_query = tep_db_query('select * from ' . TABLE_GROUPS . ' order by groups_id');
                while ($data = tep_db_fetch_array($data_query)) {
                    $group_html = tep_draw_input_field('specials_new_products_price_' . $data['groups_id'] . '[' . $currencies->currencies[$key]['id'] . ']', \common\helpers\Product::get_specials_price($s_info->specials_id, $currencies->currencies[$key]['id'], $data['groups_id'], '-2'), 'size="20"');
                    $fields[] = ['type' => 'field', 'title' => $data['groups_name'], 'value' => $group_html];
                }
            } else {
                $fields[] = ['name' => 'specials_price', 'title' => TEXT_SPECIALS_SPECIAL_PRICE, 'value' => \common\helpers\Product::get_specials_price($s_info->specials_id)];
                $fields[] = ['name' => 'products_price', 'type' => 'hidden', 'value' => isset($s_info->products_price) ? $s_info->products_price : ''];
            }
            if ($s_info->expires_date == '0000-00-00 00:00:00') {
                $expires_date = '';
            } else {
                $expires_date = explode('-', $s_info->expires_date);
                @$Y = $expires_date[0];
                @$M = $expires_date[1];
                @$d = $expires_date[2];
                @$D = explode(' ', $d);
                $expires_date = $M . '/' . $D[0] . '/' . $Y;
            }
            if ($expires_date == '//') {
                $expires_date = '';
            }
            $fields[] = ['name' => 'expires_date', 'title' => TEXT_SPECIALS_EXPIRES_DATE, 'class' => 'datepicker', 'value' => \common\helpers\Date::date_short($s_info->expires_date)];
            $fields[] = ['type' => 'field', 'title' => '', 'value' => TEXT_SPECIALS_PRICE_TIP];
        }
        echo tep_draw_form('save_item_form', 'specials/submit', \common\helpers\Output::get_all_get_params(['action']), 'post', 'id="save_item_form" onSubmit="return saveItem();"') . tep_draw_hidden_field('item_id', $item_id);
        ?>
    <div class="or_box_head"><?php 
        echo $header;
        ?></div>

    <?php 
        foreach ($fields as $field) {
            if (isset($field['title'])) {
                $field_title = $field['title'];
            } else {
                $field_title = '';
            }
            if (isset($field['name'])) {
                $field_name = $field['name'];
            } else {
                $field_name = '';
            }
            if (isset($field['value'])) {
                $field_value = $field['value'];
            } else {
                $field_value = '';
            }
            if (isset($field['type'])) {
                $field_type = $field['type'];
            } else {
                $field_type = 'text';
            }
            if (isset($field['class'])) {
                $field_class = $field['class'];
            } else {
                $field_class = '';
            }
            if (isset($field['required'])) {
                $field_required = '<span class="fieldRequired">* Required</span>';
            } else {
                $field_required = '';
            }
            if (isset($field['maxlength'])) {
                $field_maxlength = 'maxlength="' . $field['maxlength'] . '"';
            } else {
                $field_maxlength = '';
            }
            if (isset($field['size'])) {
                $field_size = 'size="' . $field['size'] . '"';
            } else {
                $field_size = '';
            }
            if (isset($field['post_html'])) {
                $field_post_html = $field['post_html'];
            } else {
                $field_post_html = '';
            }
            if (isset($field['pre_html'])) {
                $field_pre_html = $field['pre_html'];
            } else {
                $field_pre_html = '';
            }
            if (isset($field['cols'])) {
                $field_cols = $field['cols'];
            } else {
                $field_cols = '70';
            }
            if (isset($field['rows'])) {
                $field_rows = $field['rows'];
            } else {
                $field_rows = '15';
            }
            if ($field_type == 'hidden') {
                $form_html .= tep_draw_hidden_field($field_name, $field_value);
            } elseif ($field_type == 'field') {
                echo ' <div class="main_row">';
                echo '      <div class="main_title">' . $field_title . '</div>';
                echo '       <div class="main_value">       ';
                echo "        {$field_value}";
                echo '       </div>       ';
                echo ' </div>';
            } elseif ($field_type == 'textarea') {
                $field_html = tep_draw_textarea_field($field_name, 'soft', $field_cols, $field_rows, $field_value);
                echo ' <div class="main_row">';
                echo '      <div class="main_title">' . $field_title . '</div>       ';
                echo '       <div class="main_value">       ';
                echo "        {$field_pre_html} {$field_html}  {$field_required} {$field_post_html}";
                echo '       </div>       ';
                echo ' </div>';
            } else {
                echo ' <div class="main_row">';
                echo '      <div class="main_title">' . $field_title . '</div>       ';
                echo '       <div class="main_value">       ';
                echo "        {$field_pre_html} <input type='{$field_type}' name='{$field_name}' value='{$field_value}' {$field_maxlength} {$field_size} class='{$field_class}'> {$field_post_html} {$field_required}";
                echo '       </div>       ';
                echo ' </div>';
            }
        }
        ?>
    <div class="btn-toolbar btn-toolbar-order">
      <input class="btn btn-no-margin" type="submit" value="<?php 
        echo IMAGE_SAVE;
        ?>"><?php 
        echo $delete_btn;
        ?><input class="btn btn-cancel" type="button" onclick="return resetStatement()" value="<?php 
        echo IMAGE_CANCEL;
        ?>">
    </div>

    <?php 
        echo $form_html;
        ?>
    </form>
    <script>
      $(document).ready(function () {
        $(".widget-content .check_on_off").bootstrapSwitch(
        {
          onText: "<?php 
        echo SW_ON;
        ?>",
          offText: "<?php 
        echo SW_OFF;
        ?>",
          handleWidth: '20px',
          labelWidth: '24px'
        }
        );

        $(".datepicker").datepicker({
          changeMonth: true,
          changeYear: true,
          showOtherMonths: true,
          autoSize: false,
          minDate: '1',
          dateFormat: '<?php 
        echo DATE_FORMAT_DATEPICKER;
        ?>',

        });
      })
    </script>
    <?php 
    }
    public function action_validate()
    {
        if (!defined('SALE_STRICT_DATE') || SALE_STRICT_DATE != 'True') {
            $ret = ['valid' => 1];
        } else {
            $post = \Yii::$app->request->post();
            $currencies = Yii::$container->get('currencies');
            $_def_curr_id = $currencies->currencies[DEFAULT_CURRENCY]['id'];
            $specials_expires_date = \backend\models\Product_Edit\Post_Array_Helper::get_from_post_arrays(['db' => 'expires_date', 'dbdef' => '', 'post' => 'special_expires_date'], $_def_curr_id, 0);
            $specials_expires_date = \common\helpers\Date::prepare_input_date($specials_expires_date, true);
            $specials_start_date = \backend\models\Product_Edit\Post_Array_Helper::get_from_post_arrays(['db' => 'start_date', 'dbdef' => 'NULL', 'post' => 'special_start_date'], $_def_curr_id, 0);
            $specials_start_date = \common\helpers\Date::prepare_input_date($specials_start_date, true);
            if (empty($specials_expires_date)) {
                $specials_expires_date = '9999-01-01';
            }
            if (empty($specials_start_date)) {
                $specials_start_date = '0000-00-00';
            }
            $list_query = \common\models\Specials::find()->alias('s')->join_with(['prices'])->select('s.*');
            $list_query->and_where(['products_id' => (int) $post['products_id']]);
            if (intval($post['specials_id']) > 0) {
                $list_query->and_where(['<>', 's.specials_id', intval($post['specials_id'])]);
            }
            $list_query->dates_in_range($specials_start_date, $specials_expires_date);
            //echo $listQuery->createCommand()->rawSql;
            $q = $list_query->as_array()->all();
            if (empty($q)) {
                $ret = ['valid' => 1];
            } else {
                $ret = ['list' => '<span class="date start-date">' . TEXT_START_DATE . ': ' . \common\helpers\Date::datetime_short($specials_start_date) . '</span>' . ' <span class="date start-date">' . TEXT_SPECIALS_EXPIRES_DATE . ' ' . \common\helpers\Date::datetime_short($specials_expires_date) . '</span><br>' . TEXT_OVERLAPPED_DATE_RANGE . ':<br>', 'valid' => 0];
                foreach ($q as $price) {
                    $ret['list'] .= '<br>';
                    //$ret['list'] .= $listQuery->createCommand()->rawSql .' <br>';
                    $ret['list'] .= ' <span class="date start-date" title="' . $price['specials_id'] . '">' . TEXT_START_DATE . ': ' . \common\helpers\Date::datetime_short($price['start_date']) . '</span>';
                    $ret['list'] .= ' <span class="date start-date">' . TEXT_SPECIALS_EXPIRES_DATE . ' ' . \common\helpers\Date::datetime_short($price['expires_date']) . '</span>';
                    $ret['list'] .= ' <a href="' . Yii::$app->url_manager->create_url(['specials/specialedit', 'id' => $price['specials_id']]) . '" target="_blank"><span class="group">' . TEXT_MAIN . ' <span class="price group-price0">' . $currencies->format($price['specials_new_products_price']) . '</span></span></a>';
                }
            }
        }
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $ret;
    }
    public function action_submit()
    {
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $ret = ['result' => 0];
        \common\helpers\Translation::init('admin/specials');
        $products_id = (int) Yii::$app->request->post('products_id');
        $res = \common\helpers\Specials::save_from_post($products_id, 0);
        if (is_string($res)) {
            $ret['message'] = $res;
        } elseif ($res === true) {
            $ret = ['result' => 1];
        } elseif (is_int($res) && $res > 0) {
            $ret = ['result' => 1, 'id' => $res];
        } else {
            $ret['message'] = TEXT_MESSAGE_ERROR;
        }
        return $ret;
    }
    public function action_confirmitemdelete()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/specials');
        $this->layout = false;
        $item_id = (int) Yii::$app->request->post('item_id');
        $message = $name = $title = '';
        $parent_id = 0;
        $specials_query = tep_db_query('select p.products_id, s.specials_id, ' . Product_Name_Decorator::instance()->listing_query_expression('pd', '') . ' AS products_name, p.products_price, s.specials_new_products_price, s.expires_date, s.status from ' . TABLE_PRODUCTS . ' p, ' . TABLE_PRODUCTS_DESCRIPTION . ' pd, ' . TABLE_SPECIALS . " s where p.products_id = pd.products_id and pd.language_id = '" . (int) $languages_id . "' and pd.platform_id = '" . intval(\common\classes\platform::default_id()) . "' and p.products_id = s.products_id and s.specials_id = '" . (int) $item_id . "'");
        $specials = tep_db_fetch_array($specials_query);
        $s_info = new \Object_Info($specials);
        echo '<div class="or_box_head top_spec">' . TEXT_INFO_HEADING_DELETE_SPECIALS . '</div>';
        echo '<div class="col_desc">' . TEXT_INFO_DELETE_INTRO . '</div>';
        echo '<div class="col_desc"><strong>' . $s_info->products_name . '</strong></div>';
        echo tep_draw_form('item_delete', FILENAME_SPECIALS, \common\helpers\Output::get_all_get_params(['action']) . 'action=update', 'post', 'id="item_delete" onSubmit="return deleteItem();"');
        ?>
    <div class="btn-toolbar btn-toolbar-order">
    <?php 
        echo '<button class="btn btn-delete btn-no-margin">' . IMAGE_DELETE . '</button>';
        echo '<button class="btn btn-cancel" onclick="return resetStatement()">' . IMAGE_CANCEL . '</button>';
        echo tep_draw_hidden_field('item_id', $item_id);
        ?>
    </div>
    </form>
    <?php 
    }
    public function action_itemdelete()
    {
        $this->layout = false;
        $specials_id = (int) Yii::$app->request->post('item_id');
        $message_type = 'success';
        $message = TEXT_INFO_DELETED;
        tep_db_query('delete from ' . TABLE_SPECIALS . " where specials_id = '" . (int) $specials_id . "'");
        if (USE_MARKET_PRICES == 'True' || \common\helpers\Extensions::is_customer_groups_allowed()) {
            tep_db_query('delete from ' . TABLE_SPECIALS_PRICES . " where specials_id = '" . tep_db_input($specials_id) . "'");
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
        });
      </script>
    </div>


    <p class="btn-toolbar">
    <?php 
        echo '<input type="button" class="btn btn-primary" value="' . IMAGE_BACK . '" onClick="return resetStatement()">';
        ?>
    </p>
    <?php 
    }
    public function action_specialedit()
    {
        $specials_id = (int) Yii::$app->request->get('id');
        $products_id = (int) Yii::$app->request->get('products_id');
        $popup = (int) Yii::$app->request->get('popup', 0);
        $popup_edit = (int) Yii::$app->request->get('popup_edit', 0);
        $bp = Yii::$app->request->get('bp', []);
        $this->view->heading_title = BOX_CATALOG_SPECIALS;
        $this->view->use_market_prices = USE_MARKET_PRICES == 'True';
        $this->selected_menu = ['marketing', 'specials'];
        \common\helpers\Translation::init('admin/categories');
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('specials/index'), 'title' => BOX_CATALOG_SPECIALS];
        $params = [];
        $s_info = $p_info = null;
        if (!empty($specials_id) || !empty($products_id)) {
            $template = 'specialedit';
            $currencies = Yii::$container->get('currencies');
            if (!empty($specials_id)) {
                $s_info = \common\models\Specials::find()->and_where(['specials_id' => $specials_id])->with(['prices', 'backendProductDescription'])->one();
                if (!empty($s_info->specials_id)) {
                    $p_info = $s_info->product;
                    unset($s_info->product);
                }
            }
            if (empty($s_info->specials_id) && !empty($products_id)) {
                $p_info = \common\models\Products::find()->and_where(['products_id' => $products_id])->with(['backendDescription'])->one();
            }
            if (!empty($p_info)) {
                //fill in tabs details
                $params['currencies'] = $currencies;
                $_tax = \common\helpers\Tax::get_tax_rate_value($p_info->products_tax_class_id) / 100;
                $_round_to = $currencies->get_decimal_places(DEFAULT_CURRENCY);
                $params['sInfo'] = (object) \yii\helpers\Array_Helper::to_array($s_info);
                $params['pInfo'] = (object) \yii\helpers\Array_Helper::to_array($p_info);
                if (!empty($s_info->specials_id)) {
                    $params['pInfo']->specials_id = $s_info->specials_id;
                }
                $params['price'] = $currencies->format($p_info->products_price);
                $params['priceGross'] = $currencies->format($p_info->products_price + round($p_info->products_price * $_tax, 6), $_round_to);
                $params['backendProductDescription'] = \yii\helpers\Array_Helper::to_array($p_info->backend_description, ['products_name']);
                $params['default_currency'] = $currencies->currencies[DEFAULT_CURRENCY];
                //$this->view->defaultCurrency = $currencies->currencies[DEFAULT_CURRENCY]['id'];
                $this->view->default_sale_id = empty($s_info->specials_id) ? 0 : $s_info->specials_id;
                $this->view->default_currency = $this->view->default_currency ?? null;
                $price_view_obj = new View_Price_Data($p_info);
                $price_view_obj->populate_view($this->view);
                $this->view->tax_classes = [0 => TEXT_NONE];
                $tmp = \common\models\Tax_Class::find()->select('tax_class_id, tax_class_title')->order_by('tax_class_title')->as_array()->index_by('tax_class_id')->all();
                if (!empty($tmp)) {
                    $this->view->tax_classes += \yii\helpers\Array_Helper::get_column($tmp, 'tax_class_title');
                }
                /// price and cost----
                /*
                                  if ( $pInfo->products_id_price && $pInfo->products_id!=$pInfo->products_id_price ) {
                                      $priceViewObj = new ViewPriceData(\common\models\Products::findOne($pInfo->products_id_price));
                                  } else {
                                      $priceViewObj = new ViewPriceData($pInfo);
                                  }
                                  $priceViewObj->populateView($this->view, $currencies);
                */
                ////--------------
                // init price tabs
                $this->view->price_tabs = $this->view->price_tabparams = [];
                ////currencies tabs and params
                if ($this->view->use_market_prices) {
                    $this->view->currencies_tabs = [];
                    foreach ($currencies->currencies as $value) {
                        $value['def_data'] = ['currencies_id' => $value['id']];
                        $value['title'] = $value['symbol_left'] . ' ' . $value['code'] . ' ' . $value['symbol_right'];
                        $this->view->currencies_tabs[] = $value;
                    }
                    $this->view->price_tabs[] = $this->view->currencies_tabs;
                    $this->view->price_tabparams[] = ['cssClass' => 'tabs-currencies', 'tabs_type' => 'hTab'];
                }
                //// groups tabs and params
                if (\common\helpers\Extensions::is_customer_groups_allowed()) {
                    $this->view->groups = [];
                    /** @var \common\extensions\UserGroups\UserGroups $ext */
                    if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroups', 'allowed')) {
                        $ext::get_groups();
                    }
                    $this->view->groups_m = array_merge([['groups_id' => 0, 'groups_name' => TEXT_MAIN]], array_filter($this->view->groups, function ($e) {
                        return $e['per_product_price'];
                    }));
                    $tmp = [];
                    foreach ($this->view->groups_m as $value) {
                        $value['id'] = $value['groups_id'];
                        $value['title'] = $value['groups_name'];
                        $value['def_data'] = ['groups_id' => $value['id']];
                        if (empty($value['apply_groups_discount_to_specials'])) {
                            $value['groups_discount'] = 0;
                        }
                        unset($value['groups_name']);
                        unset($value['groups_id']);
                        $tmp[] = $value;
                    }
                    $this->view->price_tabs[] = $tmp;
                    unset($tmp);
                    $this->view->price_tabparams[] = [
                        'cssClass' => 'tabs-groups',
                        // add to tabs and tab-pane
                        //'callback' => 'productPriceBlock', // smarty function which will be called before children tabs , data passed as params params
                        'callback_bottom' => '',
                        'tabs_type' => 'lTab',
                        'aboveTabs' => !empty($s_info->specials_id) && count($this->view->groups_m) < 1 + count($this->view->groups) ? '../productedit/edit-price-link.tpl' : '',
                        'all_hidden' => count($this->view->groups_m) == 1,
                        'maxHeight' => '400px',
                    ];
                }
                //$this->view->price_tabs['sInfo'] = $sInfo; //star, end dates, statu, flags are the same for all tabs now
                $languages_id = \Yii::$app->settings->get('languages_id');
                $specials_types_arr = \common\models\Specials_Types::find()->where(['language_id' => $languages_id])->select('specials_type_name, specials_type_id')->as_array()->index_by('specials_type_id')->column();
                if (!is_array($specials_types_arr)) {
                    $specials_types_arr = [];
                }
                $specials_types_arr[0] = '';
                ksort($specials_types_arr);
                $params['specials_types'] = $specials_types_arr;
                $def = '';
                if (defined('SALES_DEFAULT_PROMO_TYPE')) {
                    switch (SALES_DEFAULT_PROMO_TYPE) {
                        case 'None':
                            $def = ' (' . TEXT_DISABLED . ')';
                            break;
                        case 'Percent':
                            $def = ' (' . TEXT_PERCENT . ')';
                            break;
                        case 'Fixed':
                            $def = ' (' . TEXT_FIXED . ')';
                            break;
                    }
                }
                $params['promote_types'] = [-1 => TEXT_DISABLED, 0 => TEXT_DEFAULT . $def, 1 => TEXT_PERCENT, 2 => TEXT_FIXED];
            } else {
                $template = 'choose_product';
            }
        } else {
            $template = 'choose_product';
        }
        if ($popup) {
            $params['back_url'] = \Yii::$app->url_manager->create_url(['specials/index-popup', 'prid' => $products_id]);
            //old jquery ajax with hash compatibility
            $hash = \Yii::$app->request->get('_hash_', false);
            return $this->render_ajax($template, $params + ['popup' => 1, 'popup_edit' => $popup_edit, 'hash' => $hash]);
        } else {
            if (is_array($bp)) {
                $params['back_url'] = \Yii::$app->url_manager->create_url(['specials'] + $bp);
            } else {
                $params['back_url'] = \Yii::$app->url_manager->create_url(['specials']);
            }
            if ($template == 'choose_product') {
                $catalog = new \backend\components\Products_Catalog();
                return $catalog->make();
            }
            return $this->render($template, $params);
        }
    }
    public function action_switch_status()
    {
        $id = (int) Yii::$app->request->post('id', 0);
        $status = Yii::$app->request->post('status') == 'true' ? 1 : 0;
        if ($id > 0) {
            $special = \common\models\Specials::find()->and_where(['specials_id' => $id])->one();
            if ($special && $special->status != $status) {
                try {
                    // to disable active by date range you need to set disabled flag
                    if (!$status && (empty($special->start_date) || $special->start_date < date('Y-m-d H:i:s')) && (!empty($special->expires_date) && $special->expires_date >= date('Y-m-d H:i:s'))) {
                        $special->specials_disabled = 1;
                    } elseif ($status && $special->specials_disabled == 1) {
                        $special->specials_disabled = 0;
                    }
                    $special->status = $status;
                    $special->date_status_change = date(\common\helpers\Date::DATABASE_DATETIME_FORMAT);
                    $special->save(false);
                } catch (\Exception $e) {
                    \Yii::warning(' #### ' . print_r($e->get_message(), 1), 'TLDEBUG');
                }
            }
        }
    }
    public function action_delete_selected()
    {
        $this->layout = false;
        $sp_ids = Yii::$app->request->post('bulkProcess', []);
        if (is_array($sp_ids) && !empty($sp_ids)) {
            $sp_ids = array_map('intval', $sp_ids);
            \common\models\Specials::delete_all(['specials_id' => $sp_ids]);
            \common\models\Specials_Prices::delete_all(['specials_id' => $sp_ids]);
        }
    }
    /**
     * works only with customers groups.
     */
    public function action_product_price_edit()
    {
        if (!\common\helpers\Extensions::is_customer_groups_allowed()) {
            return;
        }
        \common\helpers\Translation::init('admin/categories');
        $currencies = Yii::$container->get('currencies');
        $this->layout = false;
        $params = [];
        $params['currencies'] = $currencies;
        $params['currencies_id'] = $currencies_id = \Yii::$app->request->post('currencies_id', \Yii::$app->request->get('currencies_id', 0));
        $group_id = \Yii::$app->request->post('group_id', 0);
        $only_price = \Yii::$app->request->post('only_price', 0);
        $params['specials_id'] = $specials_id = (int) \Yii::$app->request->post('id', \Yii::$app->request->get('id', 0));
        $params['products_id'] = $products_id = (int) \Yii::$app->request->post('products_id', \Yii::$app->request->get('products_id', 0));
        $popup = (int) Yii::$app->request->get('popup', 0);
        $this->view->use_market_prices = USE_MARKET_PRICES == 'True';
        $this->view->heading_title = BOX_CATALOG_SPECIALS;
        \common\helpers\Translation::init('admin/categories');
        $s_info = $p_info = null;
        $error = false;
        if (!empty($specials_id) && !empty($products_id)) {
            $template = 'specialedit-group-popup';
            $currencies = Yii::$container->get('currencies');
            if (!empty($specials_id)) {
                $s_info = \common\models\Specials::find()->and_where(['specials_id' => $specials_id])->with(['prices', 'backendProductDescription'])->one();
                if (!empty($s_info->specials_id)) {
                    $p_info = $s_info->product;
                    unset($s_info->product);
                }
            }
            if (empty($s_info->specials_id) && !empty($products_id)) {
                $p_info = \common\models\Products::find()->and_where(['products_id' => $products_id])->with(['backendDescription'])->one();
            }
            if (!empty($p_info)) {
                //fill in tabs details
                $params['sInfo'] = (object) \yii\helpers\Array_Helper::to_array($s_info);
                $params['pInfo'] = (object) \yii\helpers\Array_Helper::to_array($p_info);
                if (!empty($s_info->specials_id)) {
                    $params['pInfo']->specials_id = $s_info->specials_id;
                }
                $this->view->default_sale_id = empty($s_info->specials_id) ? 0 : $s_info->specials_id;
                $this->view->default_currency = $this->view->default_currency ?? null;
            }
        } else {
            $error = true;
        }
        if ($error) {
            return '';
        }
        ////currencies tabs and params
        $this->view->price_tabs = $this->view->price_tabparams = [];
        if ($this->view->use_market_prices) {
            $this->view->currencies_tabs = [];
            foreach ($currencies->currencies as $value) {
                $value['def_data'] = ['currencies_id' => $value['id']];
                $value['title'] = $value['symbol_left'] . ' ' . $value['code'] . ' ' . $value['symbol_right'];
                $this->view->currencies_tabs[] = $value;
            }
            $this->view->price_tabs[] = $this->view->currencies_tabs;
            $this->view->price_tabparams[] = ['cssClass' => 'tabs-currencies', 'tabs_type' => 'hTab'];
        }
        //// groups tabs and params
        $this->view->groups = [];
        /** @var \common\extensions\UserGroups\UserGroups $ext */
        if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroups', 'allowed')) {
            $ext::get_groups();
        }
        $this->view->groups_m = $this->view->groups;
        $tabdata = $groups = $tmp = [];
        foreach ($this->view->groups_m as $value) {
            $value['id'] = $value['groups_id'];
            $value['title'] = $value['groups_name'];
            $value['def_data'] = ['groups_id' => $value['id']];
            unset($value['groups_name']);
            //unset($value['groups_id']);
            if (empty($value['apply_groups_discount_to_specials'])) {
                $value['groups_discount'] = 0;
            }
            $tmp[] = $value;
            if ($group_id == $value['id']) {
                $tabdata = $value;
            }
            if ($value['per_product_price'] == 0) {
                $groups[$value['id']] = $value['title'];
            }
        }
        //$this->view->price_tabs[] = $tmp;
        $this->view->price_tabs = $tabdata;
        unset($tmp);
        if ($only_price) {
            if ($group_id == 0) {
                return '';
            }
            if ($p_info->products_id_price && $p_info->products_id != $p_info->products_id_price) {
                $price_view_obj = new View_Price_Data(\common\models\Products::find_one($p_info->products_id_price));
            } else {
                $price_view_obj = new View_Price_Data($p_info);
            }
            $price_view_obj->populate_view($this->view);
            $this->view->tax_classes = [0 => TEXT_NONE];
            $tmp = \common\models\Tax_Class::find()->select('tax_class_id, tax_class_title')->order_by('tax_class_title')->as_array()->index_by('tax_class_id')->all();
            if (!empty($tmp)) {
                $this->view->tax_classes += \yii\helpers\Array_Helper::get_column($tmp, 'tax_class_title');
            }
            if ($this->view->use_market_prices) {
                $data = $this->view->price_tabs_data[$currencies_id][$group_id];
                $data['currencies_id'] = $currencies_id;
            } else {
                $data = $this->view->price_tabs_data[$group_id] ?? null;
            }
            $data['tabdata'] = $tabdata;
            $data['groups_id'] = $group_id;
            $this->product_edit_tab_access->set_product($p_info);
            unset($this->view->price_tabs);
            unset($this->view->price_tabs_data);
            $this->view->price_tabs_data = $data;
            $params += ['pInfo' => $p_info, 'data' => $data, 'TabAccess' => $this->product_edit_tab_access, 'idSuffix' => '_' . ($this->view->use_market_prices ? $currencies_id . '_' : '') . $group_id, 'fieldSuffix' => ($this->view->use_market_prices ? '[' . $currencies_id . ']' : '') . '[' . $group_id . ']', 'default_currency' => $currencies->currencies[DEFAULT_CURRENCY], 'only_price' => 1];
            return $this->render_ajax($template, $params);
        } else {
            $groups = [0 => TEXT_CHOOSE_GROUP] + $groups;
            $params['groups'] = $groups;
            return $this->render($template, $params);
        }
    }
    public function action_group_price_submit()
    {
        if (!\common\helpers\Extensions::is_customer_groups_allowed()) {
            return;
        }
        /*
        
               products_id	"1240"
                specials_id	"165"
                currencies_id	"0"
                group_id	"1"
                special_price[cur_id]?[group_id]?	"9"
        */
        \common\helpers\Translation::init('admin/categories');
        $currencies = Yii::$container->get('currencies');
        $this->layout = false;
        $msg = '';
        $error = false;
        $currencies_id = \Yii::$app->request->post('currencies_id', 0);
        $group_id = \Yii::$app->request->post('group_id', 0);
        $specials_id = (int) \Yii::$app->request->post('specials_id', 0);
        $special_price = \Yii::$app->request->post('special_price', []);
        if (defined('USE_MARKET_PRICES') && USE_MARKET_PRICES == 'True') {
            $price = $special_price[$currencies_id][$group_id] ?? null;
        } else {
            $price = $special_price[$group_id] ?? null;
        }
        if (!is_null($price) && $price != '' && !empty($specials_id) && !empty($group_id)) {
            try {
                $spq = \common\models\Specials_Prices::find()->and_where(['specials_id' => $specials_id, 'groups_id' => $group_id]);
                if (!defined('USE_MARKET_PRICES') || USE_MARKET_PRICES != 'True') {
                    $currencies_id = 0;
                }
                $spq->and_where(['currencies_id' => $currencies_id]);
                $sp = $spq->one();
                if (empty($sp)) {
                    $sp = new \common\models\Specials_Prices();
                    $sp->set_attributes(['specials_id' => $specials_id, 'groups_id' => $group_id, 'currencies_id' => $currencies_id], false);
                }
                $sp->specials_new_products_price = $price;
                $sp->save(false);
            } catch (\Exception $e) {
                $error = true;
                $msg = $e->get_message();
                \Yii::warning(' #### ' . print_r($e->get_message() . ' ' . $e->get_trace_as_string(), true), 'TLDEBUG');
            }
        } else {
            $error = true;
            $msg = TEXT_ERROR_ON_SAVE;
        }
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return ['result' => !$error, 'message' => $msg];
    }
}