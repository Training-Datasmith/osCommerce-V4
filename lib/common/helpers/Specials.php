<?php

declare (strict_types=1);
/*
 * This file is part of osCommerce ecommerce platform.
 * osCommerce the ecommerce
 *
 * @link https://www.oscommerce.com
 * @copyright Copyright (c) 2005 Holbi Group Ltd
 *
 * Released under the GNU General Public License
 * For the full copyright and license information, please view the LICENSE.TXT file that was distributed with this source code.
 */
namespace common\helpers;

use Yii;
class Specials
{
    public static function validate_save($products_id, $special_price, $prices, $specials_id = 0, $status = 1, $specials_start_date = '', $specials_expires_date = '', $specials_type_id = 0, $specials_disabled = 0, $specials_enabled = 0, $promote_type = 0, $total_qty = 0, $max_per_order = 0)
    {
        $validate_error = false;
        if (defined('SALE_STRICT_DATE') && SALE_STRICT_DATE == 'True') {
            //check update other specials
            //start date in other special range
            if (!empty($specials_start_date)) {
                $e = \common\models\Specials::find()->select('specials_id, start_date ')->and_where("specials_id <> '" . (int) $specials_id . "'")->and_where(['products_id' => $products_id])->start_before($specials_start_date)->end_after($specials_start_date)->as_array()->all();
                if (!empty($e)) {
                    if (defined('ALLOW_SALES_UPDATE_DATES') && ALLOW_SALES_UPDATE_DATES == 'True') {
                        $ids = \yii\helpers\Array_Helper::get_column($e, 'specials_id');
                        $columns = ['start_date' => new \yii\db\Expression('if(start_date="' . tep_db_input($specials_start_date) . '", ' . ' DATE_SUB("' . tep_db_input($specials_start_date) . '", INTERVAL 1 SECOND), start_date)'), 'specials_last_modified' => new \yii\db\Expression('now()'), 'expires_date' => new \yii\db\Expression("DATE_SUB('" . tep_db_input($specials_start_date) . "', INTERVAL 1 SECOND)")];
                        \common\models\Specials::update_all($columns, ['specials_id' => $ids]);
                    } else {
                        $validate_error = true;
                    }
                }
            }
            //end date in other special range
            if (!$validate_error && !empty($specials_expires_date)) {
                $e = \common\models\Specials::find()->select('specials_id')->and_where("specials_id <> '" . (int) $specials_id . "'")->and_where(['products_id' => $products_id])->start_before($specials_expires_date)->end_after($specials_expires_date)->as_array()->all();
                if (!empty($e)) {
                    if (defined('ALLOW_SALES_UPDATE_DATES') && ALLOW_SALES_UPDATE_DATES == 'True') {
                        $ids = \yii\helpers\Array_Helper::get_column($e, 'specials_id');
                        $columns = ['start_date' => new \yii\db\Expression(' DATE_ADD("' . tep_db_input($specials_expires_date) . '", INTERVAL 1 SECOND)'), 'specials_last_modified' => new \yii\db\Expression('now()')];
                        \common\models\Specials::update_all($columns, ['specials_id' => $ids]);
                    } else {
                        $validate_error = true;
                    }
                }
            }
        }
        if (!$validate_error) {
            // fix possible errors with dates (set the same and deactivate).
            tep_db_query('update ' . TABLE_SPECIALS . " set specials_last_modified = now(), /*expires_date=start_date,*/ status=0 where expires_date<start_date and expires_date>'1980-01-01' and products_id='" . (int) $products_id . "'");
            if ($specials_start_date > date(\common\helpers\Date::DATABASE_DATETIME_FORMAT) && !$specials_enabled) {
                $_status = 0;
            } else {
                $_status = $status;
            }
            if ((int) $specials_id > 0) {
                //date_status_change
                $date_status_change = '';
                $sp = \common\models\Specials::find()->and_where(['specials_id' => $specials_id])->as_array()->one();
                if ($sp && $sp['status'] != $_status) {
                    $date_status_change = 'date_status_change=now(), ';
                }
                tep_db_query('update ' . TABLE_SPECIALS . " set {$date_status_change} specials_new_products_price = '" . (float) $special_price . "', specials_last_modified = now(), expires_date = '" . tep_db_input($specials_expires_date) . "', start_date = '" . tep_db_input($specials_start_date) . "', status = '" . $_status . "', specials_type_id='" . (int) $specials_type_id . "', specials_disabled='" . (int) $specials_disabled . "', specials_enabled=" . (int) $specials_enabled . ', promote_type=' . (int) $promote_type . ', total_qty=' . (int) $total_qty . ', max_per_order=' . (int) $max_per_order . " where specials_id = '" . (int) $specials_id . "'");
                \common\models\Specials_Prices::delete_all(['specials_id' => (int) $specials_id]);
            } else {
                tep_db_query('insert into ' . TABLE_SPECIALS . " set products_id = '" . (int) $products_id . "', specials_new_products_price = '" . (float) $special_price . "', specials_date_added = now(), expires_date = '" . tep_db_input($specials_expires_date) . "', start_date = '" . tep_db_input($specials_start_date) . "', status = '" . $_status . "', specials_type_id='" . (int) $specials_type_id . "', specials_disabled='" . (int) $specials_disabled . "', specials_enabled=" . (int) $specials_enabled . ', promote_type=' . (int) $promote_type . ', total_qty=' . (int) $total_qty . ', max_per_order=' . (int) $max_per_order . '');
                $specials_id = tep_db_insert_id();
            }
            if (is_array($prices)) {
                foreach ($prices as $price) {
                    try {
                        $price['specials_id'] = $specials_id;
                        $m = new \common\models\Specials_Prices();
                        $m->load_default_values();
                        $m->set_attributes($price, false);
                        $m->save(false);
                    } catch (\Exception $e) {
                        \Yii::warning($e->get_message(), 'SPECIALPRICES_ERROR');
                    }
                }
            }
        } else {
            $specials_id = false;
        }
        return $specials_id;
    }
    /**
     * save specials (either on categories or sales page in admin.
     * @param int $products_id
     * @param bool $deleteInactive default false
     * @return bool|string  true|false|error message?
     */
    public static function save_from_post($products_id, $delete_inactive = false)
    {
        $ret = false;
        $currencies = Yii::$container->get('currencies');
        $_def_curr_id = $currencies->currencies[DEFAULT_CURRENCY]['id'];
        $specials_type_id = (int) Yii::$app->request->post('specials_type_id', 0);
        $promote_type = (int) Yii::$app->request->post('promote_type', 0);
        $specials_id = (int) \backend\models\Product_Edit\Post_Array_Helper::get_from_post_arrays(['db' => 'specials_id', 'dbdef' => 0, 'post' => 'specials_id'], $_def_curr_id, 0);
        //$status = (int) \backend\models\ProductEdit\PostArrayHelper::getFromPostArrays(['db' => 'status', 'dbdef' => 0, 'post' => 'special_status'], $_def_curr_id, 0);
        $status = (int) Yii::$app->request->post('special_status', 0);
        if (!$status && $delete_inactive) {
            $s = \common\models\Specials::find_one(['specials_id' => $specials_id]);
            if ($s) {
                $s->delete();
                $ret = true;
            }
        } else {
            //$specials_expires_date =  \backend\models\ProductEdit\PostArrayHelper::getFromPostArrays(['db' => 'expires_date', 'dbdef' => '', 'post' => 'special_expires_date'], $_def_curr_id, 0);
            $specials_expires_date = Yii::$app->request->post('special_expires_date', '');
            $specials_expires_date = \common\helpers\Date::prepare_input_date($specials_expires_date, true);
            //$specials_start_date =  \backend\models\ProductEdit\PostArrayHelper::getFromPostArrays(['db' => 'start_date', 'dbdef' => 'NULL', 'post' => 'special_start_date'], $_def_curr_id, 0);
            $specials_start_date = Yii::$app->request->post('special_start_date', '');
            $specials_start_date = \common\helpers\Date::prepare_input_date($specials_start_date, true);
            $special_price = \backend\models\Product_Edit\Post_Array_Helper::get_from_post_arrays(['db' => 'specials_new_products_price', 'dbdef' => '-1', 'post' => 'special_price'], $_def_curr_id, 0);
            $total_qty = (int) Yii::$app->request->post('total_qty', 0);
            $max_per_order = (int) Yii::$app->request->post('max_per_order', 0);
            if ($status == -1) {
                $specials_disabled = 1;
                $status = 0;
            } elseif ($status == 1) {
                $specials_disabled = 0;
                $specials_enabled = 1;
            } else {
                if ($status > 1) {
                    $status = 1;
                }
                $specials_disabled = 0;
                $specials_enabled = 0;
            }
            if (!$specials_expires_date || $specials_expires_date == '' || $specials_expires_date == 'NULL') {
                /*
                        if ($special_price>0) {
                          $dateFormat = date_create("+30 days");
                          $specials_expires_date = $dateFormat?$dateFormat->format(\common\helpers\Date::DATABASE_DATETIME_FORMAT):'';
                        } else {
                          $status = 0;
                        }
                */
            }
            if (!$specials_start_date || $specials_start_date == '' || $specials_start_date == 'NULL') {
                $date_format = date_create();
                $specials_start_date = $date_format ? $date_format->format(\common\helpers\Date::DATABASE_DATETIME_FORMAT) : '';
            }
            $prices = $currencies_ids = $groups = $groups_price = [];
            if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroups', 'allowed')) {
                $groups = $ext::get_groups_array();
                if (!isset($groups['0'])) {
                    $groups['0'] = ['groups_id' => 0, 'per_product_price' => 1];
                }
                $groups_price = array_filter($groups, function ($e) {
                    return $e['per_product_price'];
                });
                if ($groups_price == $groups) {
                    unset($groups_price);
                }
            }
            if (USE_MARKET_PRICES == 'True') {
                foreach ($currencies->currencies as $key => $value) {
                    $currencies_ids[$currencies->currencies[$key]['id']] = $currencies->currencies[$key]['id'];
                }
            } else {
                $currencies_ids[$_def_curr_id] = '0';
                /// here is the post and db currencies_id are different.
            }
            if (USE_MARKET_PRICES == 'True' || \common\helpers\Extensions::is_customer_groups_allowed()) {
                foreach ($currencies_ids as $post_currencies_id => $currencies_id) {
                    foreach ($groups_price ?? null ? $groups_price : $groups as $groups_id => $non) {
                        $prices[] = ['currencies_id' => $currencies_id, 'groups_id' => $groups_id, 'specials_new_products_price' => \backend\models\Product_Edit\Post_Array_Helper::get_from_post_arrays(['db' => 'specials_new_products_price', 'dbdef' => -2, 'post' => 'special_price', 'f' => ['self', 'defGroupPrice']], $currencies_id, $groups_id)];
                    }
                }
            }
            $ret = self::validate_save($products_id, $special_price, $prices, $specials_id, $status, $specials_start_date, $specials_expires_date, $specials_type_id, $specials_disabled, $specials_enabled ?? null, $promote_type, $total_qty, $max_per_order);
            if ($ret) {
                tep_db_perform(TABLE_PRODUCTS, ['products_last_modified' => 'now()'], 'update', "products_id='" . (int) $products_id . "'");
            }
        }
        return $ret;
    }
    public static function specials_cleanup()
    {
        try {
            \common\models\Specials::delete_all(['and', ['status' => 0], ['or', ['<', 'start_date', new \yii\db\Expression('now()')], ['is', 'start_date', new \yii\db\Expression('null')]]]);
            //quick as DeleteAll doesn't trigger before Delete
            \common\models\Specials_Prices::cleanup();
        } catch (\Exception $e) {
            \Yii::warning($e->get_message(), 'specials');
            echo $e->get_message();
        }
    }
    /**
     * incorrect name (copied out) Activates scheduled and disable expired sales (special prices)
     * @param bool $force - ignore admin "by_cron" setting
     */
    public static function tep_expire_specials($force = false)
    {
        if ($force || !defined('EXPIRE_SPECIALS_BY_CRON') || EXPIRE_SPECIALS_BY_CRON == 'False' || date('H:i') == '00:03') {
            //enable
            tep_db_query('update ' . TABLE_SPECIALS . ' set status=1, date_status_change=now() ' . ' where specials_disabled=0 and status=0 ' . " and (specials_enabled=1 or now() >= start_date or start_date is null or start_date='" . \common\models\queries\Specials_Query::$start_epoch . "')" . " and (specials_enabled=1 or expires_date is null or expires_date='" . \common\models\queries\Specials_Query::$start_epoch . "' or now() < expires_date)");
            // not expired
            //disable (expire)
            tep_db_query('update ' . TABLE_SPECIALS . ' set status = 0, date_status_change = now() where status>0 and specials_enabled=0 and (specials_disabled=1 or (now() >= expires_date and expires_date > 0))');
        }
    }
    /**
     * for admin part only text according 3 flags and date
     * @param bool $enabled
     * @param bool $disabled
     * @param bool $expired
     * @param bool $scheduled
     * @return string
     */
    public static function status_description_text($enabled, $disabled, $expired, $scheduled)
    {
        $cur_status = '<span class="sales-active">' . TEXT_ACTIVE . '</span>';
        if ($enabled) {
            $cur_status = '<span class="sales-active sales-manual">' . TEXT_MANUALLY_ACTIVATED . '</span>';
        } elseif ($disabled) {
            $cur_status = '<span class="sales-inactive sales-manual">' . TEXT_MANUALLY_DISABLED . '</span>';
        } else if ($expired) {
            $cur_status = '<span class="sales-inactive">' . TEXT_EXPIRED . '</span>';
        } elseif ($scheduled) {
            $cur_status = '<span class="sales-scheduled">' . TEXT_SCHEDULED . '</span>';
        }
        return $cur_status;
    }
    /**
     * description by specials_id admin only - 2do group
     * @param integer $specials_id
     * @param integer $tax
     * @param integer $group_id
     * @param integer $currencies_id
     * @return array
     */
    public static function get_status($specials_id, $tax = 0, $group_id = 0, $currencies_id = 0)
    {
        $ret = [];
        $expired = $scheduled = false;
        $specials_id = intval($specials_id);
        $currencies = Yii::$container->get('currencies');
        $_def_curr_id = $currencies->currencies[DEFAULT_CURRENCY]['id'];
        $list_query = \common\models\Specials::find()->select(\common\models\Specials::table_name() . '.*');
        $list_query->and_where(['specials_id' => $specials_id]);
        //$listQuery->joinWith(['backendProductDescription', 'specialsType'])->addSelect('products_name, products_price, specials_type_name');
        // echo $listQuery->createCommand()->rawSql; die;
        $special = $list_query->one();
        if (!empty($special)) {
            if ($special['start_date'] > '1980-01-01') {
                if ($special['start_date'] > date('Y-m-d H:i:s') && !$special['status']) {
                    $scheduled = true;
                }
            }
            if ($special['expires_date'] > '1980-01-01') {
                if ($special['expires_date'] < date('Y-m-d H:i:s')) {
                    $expired = true;
                }
            }
            $ret['description'] = self::status_description_text($special['specials_enabled'], $special['specials_disabled'], $expired, $scheduled);
            $prices = self::get_prices($special, $tax);
            if (!empty($prices)) {
                foreach ($prices as $cid => $value) {
                    if ($cid != $currencies_id && !($cid == 0 && $_def_curr_id == $currencies_id)) {
                        continue;
                    }
                    foreach ($value as $gid => $price) {
                        if ($gid != $group_id) {
                            continue;
                        }
                        $ret['prices'] = $price;
                        break;
                    }
                    break;
                }
            }
        }
        return $ret;
    }
    /**
     * Description by products_id - active, scheduled, disabled not expired - admin only - 2do group
     * @param integer $products_id
     * @param integer $group_id
     * @param integer $currencies_id
     * @return array
     */
    public static function get_product_status($products_id, $tax = 0, $group_id = 0, $currencies_id = 0)
    {
        $list_query = \common\models\Specials::find()->select(\common\models\Specials::table_name() . '.*');
        $list_query->and_where(['products_id' => $products_id]);
        $list_query->order_by('status desc, start_date<now(), start_date, specials_disabled  desc')->limit(1);
        //vl2check active, scheduled, disabled not expired
        $special = $list_query->one();
        $currencies = Yii::$container->get('currencies');
        $_def_curr_id = $currencies->currencies[DEFAULT_CURRENCY]['id'];
        $ret = [];
        $expired = $scheduled = false;
        if (!empty($special)) {
            if ($special['start_date'] > '1980-01-01' && $special['start_date'] > date('Y-m-d H:i:s') && !$special['status']) {
                $scheduled = true;
            }
            if ($special['expires_date'] > '1980-01-01' && $special['expires_date'] < date('Y-m-d H:i:s')) {
                $expired = true;
            }
            $ret = ['description' => self::status_description_text($special['specials_enabled'], $special['specials_disabled'], $expired, $scheduled), 'start_date' => Date::datetime_short($special['start_date']), 'expires_date' => Date::datetime_short($special['expires_date']), 'total_qty' => $special['total_qty'], 'max_per_order' => $special['max_per_order'], 'sold' => !empty($special['total_qty']) ? self::get_sold_only_qty(['specials_id' => $special['specials_id']]) : 0, 'id' => $special['specials_id']];
            $prices = self::get_prices($special, $tax);
            if (!empty($prices)) {
                foreach ($prices as $cid => $value) {
                    if ($cid != $currencies_id && !($cid == 0 && $_def_curr_id == $currencies_id)) {
                        continue;
                    }
                    foreach ($value as $gid => $price) {
                        if ($gid != $group_id) {
                            continue;
                        }
                        $ret['prices'] = $price;
                        break;
                    }
                    break;
                }
            }
            if (empty($ret['prices'])) {
                $ret['description'] .= ' ' . TEXT_SALES_DISABLED_GROUP;
            }
        } else {
            $ret = ['description' => '<span class="sales-active">' . TEXT_NOT_SET_UP_CLICK_MORE . '</span>'];
        }
        return $ret;
    }
    /**
     * get array of prices of specified special
     * @staticvar array $cPrices cache...
     * @param \common\models\Specials $special
     * @param float $tax ex 20
     * @return array [currency_id][group_id][value*, text* ...]
     */
    public static function get_prices($special, $tax = 0)
    {
        static $c_prices = null;
        if ($special instanceof \common\models\Specials) {
            if (isset($c_prices[$special->specials_id])) {
                return $c_prices[$special->specials_id];
            }
            $s_prices = $special->prices;
            //if there isn't records for group/currency the main price is applied to def group, currency.
            if (empty($s_prices) || !\common\helpers\Extensions::is_customer_groups_allowed() && USE_MARKET_PRICES != 'True') {
                $s_prices[0] = $special->attributes;
                $s_prices[0]['groups_id'] = $s_prices[0]['currencies_id'] = 0;
            } elseif (!empty($s_prices) && is_array($s_prices)) {
                /// if no record sales_price for def cur/group and exists for other - admin shows incorrectly disabled for main and show on frontend
                $def_currency_id = \common\helpers\Currencies::get_currency_id(DEFAULT_CURRENCY);
                $missed = true;
                foreach ($s_prices as $price_info) {
                    if ($price_info['groups_id'] == 0 && ($price_info['currencies_id'] == 0 || $price_info['currencies_id'] == $def_currency_id)) {
                        $missed = false;
                        break;
                    }
                }
                if ($missed) {
                    $tmp = $special->attributes;
                    $tmp['groups_id'] = $tmp['currencies_id'] = 0;
                    $s_prices[] = $tmp;
                }
            }
            $c_prices[$special->specials_id] = $prices = self::calculate_prices($s_prices, $tax);
        }
        return $prices;
    }
    public static function calculate_prices($s_prices, $tax)
    {
        $prices = [];
        $def_price = null;
        if (is_array($s_prices)) {
            /** @var \common\classes\Currencies $currencies */
            $currencies = Yii::$container->get('currencies');
            $groups = \common\helpers\Group::get_customer_groups();
            $def_currency_id = \common\helpers\Currencies::get_currency_id(DEFAULT_CURRENCY);
            foreach ($s_prices as $price_info) {
                if ($price_info['specials_new_products_price'] > 0 || $price_info['specials_new_products_price'] == -2) {
                    if ($price_info['currencies_id'] == 0 || $price_info['currencies_id'] == $def_currency_id) {
                        $c_code = DEFAULT_CURRENCY;
                        $c_id = 0;
                    } else {
                        $c_code = \common\helpers\Currencies::get_currency_code($price_info['currencies_id']);
                        $c_id = $price_info['currencies_id'];
                    }
                    $price = false;
                    if ($price_info['specials_new_products_price'] == -2) {
                        //def price could be not first in the $sPrices array
                        if (is_null($def_price) && is_array($s_prices)) {
                            if (!empty($prices[0][0])) {
                                $def_price = $prices[0][0];
                            } else {
                                $def_price = false;
                                foreach ($s_prices as $_p) {
                                    if ($_p['groups_id'] == 0 && $_p['currencies_id'] == 0) {
                                        $def_price = $_p;
                                        $price = $_p['specials_new_products_price'];
                                        if ($price > 0) {
                                            $prices[0][0] = [
                                                'value' => $price,
                                                'text' => $currencies->format($currencies->calculate_price($price, 0, 1, $c_code), true, $c_code),
                                                'value_inc' => $currencies->calculate_price($price, $tax, 1, $c_code),
                                                'text_inc' => $currencies->format($currencies->calculate_price($price, $tax, 1, $c_code), true, $c_code),
                                                //'currency_code' => $cCode,
                                                'group_name' => TEXT_MAIN,
                                            ];
                                        }
                                        $price = false;
                                        break;
                                    }
                                }
                            }
                        }
                        if (is_array($groups[$price_info['groups_id']] ?? null) && isset($prices[0][0]['value']) && $prices[0][0]['value'] > 0 && is_numeric($groups[$price_info['groups_id']]['groups_discount'])) {
                            if ($groups[$price_info['groups_id']]['apply_groups_discount_to_specials']) {
                                $price = $prices[0][0]['value'] * (1 - $groups[$price_info['groups_id']]['groups_discount'] / 100);
                            } else {
                                $price = $prices[0][0]['value'];
                            }
                        }
                    } else {
                        $price = $price_info['specials_new_products_price'];
                    }
                    if (!empty($price)) {
                        $prices[$c_id][$price_info['groups_id']] = [
                            'value' => $price,
                            'text' => $currencies->format($currencies->calculate_price($price, 0, 1, $c_code), true, $c_code),
                            'value_inc' => $currencies->calculate_price($price, $tax, 1, $c_code),
                            'text_inc' => $currencies->format($currencies->calculate_price($price, $tax, 1, $c_code), true, $c_code),
                            //'currency_code' => $cCode,
                            'group_name' => $price_info['groups_id'] == 0 ? TEXT_MAIN : $groups[$price_info['groups_id']]['groups_name'],
                        ];
                    }
                    if (is_null($def_price) && !empty($prices[0][0])) {
                        $def_price = $prices[0][0];
                    }
                }
            }
        }
        return $prices;
    }
    /**
     * compare sold and allocated products with special price with allowed total_qty
     * @param array $params [specials_id, total_qty]
     * @return boolean
     */
    public static function check_sold_out($params, $qty = 1)
    {
        $ret = false;
        if (!empty($params['total_qty']) && !empty($params['specials_id'])) {
            $ret = $params['total_qty'] < self::get_sold_qty($params) + $qty;
        }
        return $ret;
    }
    /**
     * get sold and allocated products with special price
     * @param array $params [specials_id]
     * @return int
     */
    public static function get_sold_qty($params)
    {
        $ret = 0;
        static $cache = [];
        if (!empty($params['specials_id'])) {
            if (!isset($cache[$params['specials_id']])) {
                $sold_qty = self::get_sold_only_qty($params);
                $temporary_stock_qty = 0;
                if (defined('USE_TEMP_STOCK_ON_SPECIALS_CAP') && USE_TEMP_STOCK_ON_SPECIALS_CAP == 'True') {
                    if (!(($ext = \common\helpers\Acl::check_extension_allowed('ReportFreezeStock')) && $ext::is_freezed())) {
                        if (\Yii::$app->id == 'app-console') {
                            $guid = \Yii::$app->storage->get('guid');
                        } else {
                            $guid = tep_session_id();
                        }
                        $temporary_stock_qty = (int) \common\models\Orders_Products_Temporary_Stock::find()->and_where(['!=', 'session_id', $guid])->and_where(['specials_id' => $params['specials_id']])->sum('temporary_stock_quantity');
                    }
                }
                $cache[$params['specials_id']] = $sold_qty + $temporary_stock_qty;
            }
            $ret = $cache[$params['specials_id']];
        }
        return $ret;
    }
    /**
     * get sold products with special price
     * @param array $params [specials_id]
     * @return int
     */
    public static function get_sold_only_qty($params)
    {
        $ret = 0;
        static $cache = [];
        if (!empty($params['specials_id'])) {
            if (!isset($cache[$params['specials_id']])) {
                $exclude_order_statuses_array = \common\helpers\Order::extract_statuses(DASHBOARD_EXCLUDE_ORDER_STATUSES);
                $sold_qty = (int) \common\models\Orders_Products::find()->join_with('order', false)->and_where(['not in', 'orders_status', $exclude_order_statuses_array])->and_where(['specials_id' => $params['specials_id']])->sum('products_quantity');
                $temporary_stock_qty = 0;
                $cache[$params['specials_id']] = $sold_qty + $temporary_stock_qty;
            }
            $ret = $cache[$params['specials_id']];
        }
        return $ret;
    }
    /**
     * get sales description by ID
     * @param int $specials_id
     * @return string
     */
    public static function get_description($specials_id = 0, $promo_id = 0)
    {
        $ret = '';
        if ((int) $specials_id > 0) {
            $ret .= sprintf(defined('TEXT_SPECIAL_DESCRIPTION') ? TEXT_SPECIAL_DESCRIPTION : 'Special %s %s', '', '');
        }
        if ((int) $promo_id > 0) {
            $ret .= ' ' . (defined('TEXT_PROMOTIONS') ? TEXT_PROMOTIONS : 'Promotion');
        }
        try {
            if (\common\helpers\Extensions::is_customer_groups_allowed() && !\Yii::$app->user->is_guest && \Yii::$app->storage->has('customer_groups_id')) {
                $check = false;
                /** @var \common\extensions\PersonalDiscount\PersonalDiscount  $personalDiscount */
                if ($personal_discount = \common\helpers\Acl::check_extension_allowed('PersonalDiscount', 'allowed')) {
                    $check = $personal_discount::get_personal_discount_percent();
                    if ($check) {
                        $ret = (defined('TEXT_PERSONAL_DISCOUNT') ? constant('TEXT_PERSONAL_DISCOUNT') : '') . ' ' . $check . ' ' . $ret;
                    }
                }
                $customer_groups_id = (int) \Yii::$app->storage->get('customer_groups_id');
                if (!$check && $customer_groups_id > 0) {
                    $groups = Group::get_customer_groups();
                    if (!empty($groups[$customer_groups_id])) {
                        $ret = $groups[$customer_groups_id]['groups_name'] . ' ' . $ret;
                    }
                }
            }
        } catch (\Exception $e) {
            \Yii::warning(' #### ' . print_r($e->get_message(), true), 'TLDEBUG-specials');
        }
        return $ret;
    }
    public static function get_special_id($product_details, $qty)
    {
        $specials_id = 0;
        if (!empty($product_details)) {
            $products_id = $product_details['products_id'];
            if (isset($product_details['parent']) && $product_details['parent'] != '') {
                if ($product_details['products_pctemplates_id']) {
                    // if parent is configurator
                    $price_instance = \common\models\Product\Configurator_Price::get_instance($products_id);
                    $special_price = $price_instance->get_configurator_special_price(['qty' => $qty]);
                    if ($special_price !== false) {
                        $_spd = $price_instance->get_special_price_details(['qty' => $qty]);
                        if (self::qty_in_range($_spd, $qty)) {
                            $specials_id = $_spd['specials_id'] ?? 0;
                        }
                    }
                } else if ($ext = \common\helpers\Acl::check_extension_allowed('ProductBundles', 'allowed')) {
                }
            } else {
                $price_instance = \common\models\Product\Price::get_instance($products_id);
                $special_price = $price_instance->get_inventory_special_price(['qty' => $qty]);
                if ($special_price !== false) {
                    $_spd = $price_instance->get_special_price_details(['qty' => $qty]);
                    if (self::qty_in_range($_spd, $qty)) {
                        $specials_id = $_spd['specials_id'] ?? 0;
                    }
                }
            }
        }
        return $specials_id;
    }
    /**
     * check specials total_qty and max_per_order with $qty
     * @param int|array|object $special
     * @param int $qty
     * @return bool|null
     */
    public static function qty_in_range($special, $qty)
    {
        if ($special instanceof \common\models\Specials) {
            $special = $special->attributes();
        } elseif (is_array($special) && !empty($special['specials_id'])) {
            if (!isset($special['total_qty']) || !isset($special['max_per_order'])) {
                $special = \common\models\Specials::find()->where(['specials_id' => $special['specials_id']])->as_array()->one();
            }
        } elseif (is_scalar($special)) {
            $special = \common\models\Specials::find()->where(['specials_id' => $special])->as_array()->one();
        } else {
            $special = false;
        }
        if (!empty($special)) {
            $ret = true;
            if (!empty($special['total_qty']) && \common\helpers\Specials::check_sold_out($special)) {
                $ret = false;
            }
            if (!empty($special['max_per_order']) && $special['max_per_order'] < $qty) {
                $ret = false;
            }
        } else {
            $ret = null;
        }
        return $ret;
    }
    public static function get_link_admin($specials_id, $description)
    {
        $ret = $description;
        if ((int) $specials_id > 0) {
            $check = \common\models\Specials::find()->where(['specials_id' => $specials_id])->exists();
            if ($check) {
                $ret = '<a target="top" href="' . Yii::$app->url_manager->create_url(['specials/specialedit', 'id' => (int) $specials_id]) . '" class="sales-link">' . $ret . '</a>';
            }
        }
        return $ret;
    }
}