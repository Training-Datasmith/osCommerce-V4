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
namespace common\classes;

use frontend\design\Info;
class Currencies
{
    public $currencies;
    public $platform_currencies = [];
    public $dp_currency;
    public $currency_codes = [];
    //cache: codes by IDs
    public $margin_platform_id;
    public $margin_array;
    public function __construct($platform_id = null)
    {
        $this->currencies = [];
        if (!Info::is_totally_admin()) {
            $this->platform_currencies = Info::platform_currencies();
            $this->dp_currency = \frontend\design\Info::platform_def_currency();
            if (!is_array($this->platform_currencies) || count($this->platform_currencies) == 0) {
                $this->platform_currencies = [DEFAULT_CURRENCY];
                $this->dp_currency = DEFAULT_CURRENCY;
            }
        } else {
            $this->dp_currency = DEFAULT_CURRENCY;
            $this->platform_currencies = [DEFAULT_CURRENCY];
        }
        $currencies_response = \common\models\Currencies::find()->where(['status' => 1])->order_by(['sort_order' => SORT_ASC, 'title' => SORT_ASC])->as_array()->all();
        foreach ($currencies_response as $currencies) {
            $this->currencies[$currencies['code']] = ['title' => $currencies['title'], 'id' => $currencies['currencies_id'], 'code' => $currencies['code'], 'code_number' => $currencies['code_number'] ?? '', 'symbol_left' => $currencies['symbol_left'], 'symbol_right' => $currencies['symbol_right'], 'decimal_point' => $currencies['decimal_point'], 'thousands_point' => $currencies['thousands_point'], 'decimal_places' => (int) $currencies['decimal_places'], '_value' => $currencies['value'], 'value' => $currencies['value']];
            $this->currency_codes[$currencies['currencies_id']] = $currencies['code'];
        }
        if (is_null($platform_id)) {
            $platform_id = \Yii::$app->get('platform')->config()->get_id();
        }
        $this->apply_platform_margin($platform_id);
        if (USE_MARKET_PRICES == 'True') {
            $currency = \Yii::$app->settings->get('currency');
            if (empty($currency)) {
                $currency = DEFAULT_CURRENCY;
                \Yii::$app->settings->set('currency', $currency);
            }
            $currency_value = $this->currencies[$currency]['value'];
            foreach ($this->currencies as $code => $curr) {
                $this->currencies[$code]['value'] /= $currency_value;
            }
        }
    }
    public function apply_platform_margin($platform_id)
    {
        $this->margin_platform_id = $platform_id;
        $this->margin_array = [];
        foreach ($this->currencies as $currency) {
            $this->margin_array[$currency['id']] = ['margin_value' => 0, 'use_custom_currency_value' => false, 'custom_currency_value' => 1, 'margin_value_show' => '0', 'margin_type' => '%'];
        }
        $get_platform_margin_r = \common\models\Platforms_Currencies_Margin::find()->where(['platform_id' => $this->margin_platform_id])->as_array()->all();
        $count = count($get_platform_margin_r);
        if ($count > 0) {
            foreach ($get_platform_margin_r as $array => $get_platform_margin) {
                if (!isset($this->margin_array[$get_platform_margin['currencies_id']])) {
                    continue;
                }
                $this->margin_array[$get_platform_margin['currencies_id']] = ['use_custom_currency_value' => !!$get_platform_margin['use_custom_currency_value'], 'custom_currency_value' => $get_platform_margin['currency_value'], 'margin_value' => floatval($get_platform_margin['margin_value']), 'margin_value_show' => rtrim(rtrim($get_platform_margin['margin_value'], '0'), '.'), 'margin_type' => $get_platform_margin['margin_type']];
            }
        }
        foreach ($this->currencies as $currency_code => $currency) {
            $new_value = $currency['_value'];
            if ($this->margin_array[$currency['id']]['use_custom_currency_value']) {
                $new_value = $this->margin_array[$currency['id']]['custom_currency_value'];
            }
            $margin_type = $this->margin_array[$currency['id']]['margin_type'];
            $margin_value = $this->margin_array[$currency['id']]['margin_value'];
            if ($margin_value != 0) {
                if ($margin_type == '+') {
                    $new_value += $margin_value;
                } elseif ($margin_type == '%') {
                    $new_value = $new_value * (100 + $margin_value) / 100;
                }
            }
            $this->currencies[$currency_code]['value'] = $new_value;
        }
    }
    /**
     *
     * @param decimal $number
     * @param bool  $calculate_currency_value default true
     * @param string $currency_type def ''
     * @param decimal $currency_value def ''
     * @param bool $microdata def false
     * @param bool $metaTags def false
     * @return string
     */
    public function format($number, $calculate_currency_value = true, $currency_type = '', $currency_value = '', $microdata = false, $meta_tags = false)
    {
        $currency = \Yii::$app->settings->get('currency');
        if (\frontend\design\Info::is_totally_admin() && empty($currency)) {
            $currency = DEFAULT_CURRENCY;
            \Yii::$app->settings->set('currency', $currency);
        }
        if (empty($currency_type)) {
            $currency_type = $currency;
        }
        $format_string = '';
        $this->currencies[$currency_type]['symbol_left'] = $this->currencies[$currency_type]['symbol_left'] ?? null;
        $this->currencies[$currency_type]['symbol_right'] = $this->currencies[$currency_type]['symbol_right'] ?? null;
        $this->currencies[$currency_type]['decimal_point'] = $this->currencies[$currency_type]['decimal_point'] ?? null;
        $this->currencies[$currency_type]['thousands_point'] = $this->currencies[$currency_type]['thousands_point'] ?? null;
        $this->currencies[$currency_type]['decimal_places'] = $this->currencies[$currency_type]['decimal_places'] ?? null;
        $this->currencies[$currency_type]['value'] = $this->currencies[$currency_type]['value'] ?? null;
        if ($this->currencies[$currency_type]['symbol_left']) {
            if ($microdata) {
                $format_string .= '<span itemprop="priceCurrency" content="' . $currency_type . '">' . $this->currencies[$currency_type]['symbol_left'] . '</span>';
            } else {
                $format_string .= $this->currencies[$currency_type]['symbol_left'];
            }
        }
        if ($calculate_currency_value == true && USE_MARKET_PRICES != 'True') {
            $rate = tep_not_null($currency_value) ? $currency_value : $this->currencies[$currency_type]['value'];
            $seo_price = number_format(round($number * $rate, $this->currencies[$currency_type]['decimal_places']), 2, '.', '');
            $format_string .= ($microdata ? '<span itemprop="price" content="' . $seo_price . '">' : '') . number_format(round($number * $rate, $this->currencies[$currency_type]['decimal_places']), $this->currencies[$currency_type]['decimal_places'], $this->currencies[$currency_type]['decimal_point'], $this->currencies[$currency_type]['thousands_point']) . ($microdata ? '</span>' : '');
            // if the selected currency is in the european euro-conversion and the default currency is euro,
            // the currency will displayed in the national currency and euro currency
            if (DEFAULT_CURRENCY == 'EUR' && ($currency_type == 'DEM' || $currency_type == 'BEF' || $currency_type == 'LUF' || $currency_type == 'ESP' || $currency_type == 'FRF' || $currency_type == 'IEP' || $currency_type == 'ITL' || $currency_type == 'NLG' || $currency_type == 'ATS' || $currency_type == 'PTE' || $currency_type == 'FIM' || $currency_type == 'GRD')) {
                $format_string .= ' <small>[' . $this->format($number, true, 'EUR') . ']</small>';
            }
        } else {
            $seo_price = number_format(round($number, $this->currencies[$currency_type]['decimal_places']), 2, '.', '');
            $format_string .= ($microdata ? '<span itemprop="price" content="' . $seo_price . '">' : '') . number_format(round($number, $this->currencies[$currency_type]['decimal_places']), $this->currencies[$currency_type]['decimal_places'], $this->currencies[$currency_type]['decimal_point'], $this->currencies[$currency_type]['thousands_point']) . ($microdata ? '</span>' : '');
        }
        if ($meta_tags && !(\Yii::$app->user->is_guest && \common\helpers\Platform_Config::get_field_value('platform_please_login'))) {
            \Yii::$app->get_view()->register_meta_tag(['property' => 'product:price:amount', 'content' => $seo_price], 'product:price:amount');
            \Yii::$app->get_view()->register_meta_tag(['property' => 'product:price:currency', 'content' => $currency_type], 'product:price:currency');
        }
        if ($this->currencies[$currency_type]['symbol_right']) {
            if ($microdata) {
                $format_string .= '<span itemprop="priceCurrency" content="' . $currency_type . '">' . $this->currencies[$currency_type]['symbol_right'] . '</span>';
            } else {
                $format_string .= $this->currencies[$currency_type]['symbol_right'];
            }
        }
        if ($number < 0) {
            $format_string = '-' . preg_replace("/\\-/", '', $format_string);
        }
        if ($ext = \common\helpers\Extensions::is_allowed('Maintenance')) {
            if ($ext::option_prices_off()) {
                $format_string = '';
            }
        }
        if ($ext = \common\helpers\Acl::check_extension_allowed('BusinessToBusiness', 'allowed')) {
            $customer_groups_id = (int) \Yii::$app->storage->get('customer_groups_id');
            if ($ext::check_price_is_hidden($customer_groups_id)) {
                $checkout = in_array(\Yii::$app->controller->id, ['checkout', 'sample-checkout', 'quote-checkout']) && \Yii::$app->controller->action->id == 'process' || \Yii::$app->controller->id == 'callback';
                if (!$checkout) {
                    $format_string = '&nbsp;';
                }
            }
        }
        return $format_string;
    }
    public function format_clear($number, $calculate_currency_value = true, $currency_type = '', $currency_value = '', $unclear = false)
    {
        $currency = \Yii::$app->settings->get('currency');
        if (\frontend\design\Info::is_totally_admin() && empty($currency)) {
            $currency = DEFAULT_CURRENCY;
            \Yii::$app->settings->set('currency', $currency);
        }
        if (empty($currency_type)) {
            $currency_type = $currency;
        }
        if ($calculate_currency_value == true && USE_MARKET_PRICES != 'True') {
            $rate = tep_not_null($currency_value) ? $currency_value : $this->currencies[$currency_type]['value'];
            $format_string = number_format(round($unclear ? $number / $rate : $number * $rate, $this->currencies[$currency_type]['decimal_places']), $this->currencies[$currency_type]['decimal_places'], '.', '');
        } else {
            $format_string = number_format(round($number, $this->currencies[$currency_type]['decimal_places']), $this->currencies[$currency_type]['decimal_places'], '.', '');
        }
        if ($ext = \common\helpers\Extensions::is_allowed('Maintenance')) {
            if ($ext::option_prices_off()) {
                $format_string = 0;
            }
        }
        /*
                if ($ext = \common\helpers\Acl::checkExtensionAllowed('BusinessToBusiness', 'allowed')) {
                    $customer_groups_id = (int) \Yii::$app->storage->get('customer_groups_id');
                    if ($ext::checkPriceIsHidden($customer_groups_id)) {
                      $format_string = '';
                    }
                }*/
        return $format_string;
    }
    public function format_by_id($number, $calculate_currency_value = true, $currency_id = '', $currency_value = '', $microdata = false)
    {
        $currency = \Yii::$app->settings->get('currency');
        if (\frontend\design\Info::is_totally_admin() && empty($currency)) {
            $currency = DEFAULT_CURRENCY;
            \Yii::$app->settings->set('currency', $currency);
        }
        if (!empty($currency_id) && isset($this->currency_codes[$currency_id])) {
            $currency_type = $this->currency_codes[$currency_id];
        } else {
            $currency_type = $currency;
        }
        $format_string = '';
        if (empty($number)) {
            // php8: '' uses in tpl as 0 value
            $number = 0;
        }
        if ($this->currencies[$currency_type]['symbol_left']) {
            if ($microdata) {
                $format_string .= '<span itemprop="priceCurrency" content="' . $currency_type . '">' . $this->currencies[$currency_type]['symbol_left'] . '</span>';
            } else {
                $format_string .= $this->currencies[$currency_type]['symbol_left'];
            }
        }
        if ($calculate_currency_value == true && USE_MARKET_PRICES != 'True') {
            $rate = tep_not_null($currency_value) ? $currency_value : $this->currencies[$currency_type]['value'];
            $format_string .= ($microdata ? '<span itemprop="price" content="' . number_format(round($number * $rate, $this->currencies[$currency_type]['decimal_places']), 2, '.', '') . '">' : '') . number_format(round($number * $rate, $this->currencies[$currency_type]['decimal_places']), $this->currencies[$currency_type]['decimal_places'], $this->currencies[$currency_type]['decimal_point'], $this->currencies[$currency_type]['thousands_point']) . ($microdata ? '</span>' : '');
            if (DEFAULT_CURRENCY == 'EUR' && ($currency_type == 'DEM' || $currency_type == 'BEF' || $currency_type == 'LUF' || $currency_type == 'ESP' || $currency_type == 'FRF' || $currency_type == 'IEP' || $currency_type == 'ITL' || $currency_type == 'NLG' || $currency_type == 'ATS' || $currency_type == 'PTE' || $currency_type == 'FIM' || $currency_type == 'GRD')) {
                $format_string .= ' <small>[' . $this->format($number, true, 'EUR') . ']</small>';
            }
        } else {
            $format_string .= number_format(round($number, $this->currencies[$currency_type]['decimal_places']), $this->currencies[$currency_type]['decimal_places'], $this->currencies[$currency_type]['decimal_point'], $this->currencies[$currency_type]['thousands_point']);
        }
        if ($this->currencies[$currency_type]['symbol_right']) {
            if ($microdata) {
                $format_string .= '<span itemprop="priceCurrency" content="' . $currency_type . '">' . $this->currencies[$currency_type]['symbol_right'] . '</span>';
            } else {
                $format_string .= $this->currencies[$currency_type]['symbol_right'];
            }
        }
        if ($ext = \common\helpers\Extensions::is_allowed('Maintenance')) {
            if ($ext::option_prices_off()) {
                $format_string = '';
            }
        }
        if ($ext = \common\helpers\Acl::check_extension_allowed('BusinessToBusiness', 'allowed')) {
            $customer_groups_id = (int) \Yii::$app->storage->get('customer_groups_id');
            if ($ext::check_price_is_hidden($customer_groups_id)) {
                $format_string = '';
            }
        }
        return $format_string;
    }
    public function is_set($code)
    {
        if (isset($this->currencies[$code]) && tep_not_null($this->currencies[$code])) {
            return true;
        } else {
            return false;
        }
    }
    public function get_value($code)
    {
        return $this->currencies[$code]['value'];
    }
    public function get_decimal_places($code)
    {
        return $this->currencies[$code]['decimal_places'];
    }
    public function get_decimal_places_by_id($id)
    {
        if (isset($this->currency_codes[$id])) {
            $code = $this->currency_codes[$id];
        } else {
            $code = DEFAULT_CURRENCY;
        }
        return $this->currencies[$code]['decimal_places'];
    }
    public function calculate_price_in_order($order_info, $products_price, $products_tax = 0, $quantity = 1)
    {
        if (\common\helpers\Currencies::currency_exists($order_info['currency'])) {
            if (defined('PRICE_WITH_BACK_TAX') && PRICE_WITH_BACK_TAX == 'True') {
                $products_tax = 0;
            }
            if (!is_numeric($quantity)) {
                $quantity = 1;
            }
            if ($order_info['products_price_qty_round'] ?? null) {
                return round(\common\helpers\Tax::add_tax_always($products_price, $products_tax), $this->currencies[$order_info['currency']]['decimal_places']) * $quantity;
            } elseif (defined('PRODUCTS_PRICE_EXC_ROUND') && PRODUCTS_PRICE_EXC_ROUND == 'true') {
                return round(\common\helpers\Tax::add_tax_always($products_price * $quantity, $products_tax), $this->currencies[$order_info['currency']]['decimal_places']);
            } else {
                return round(\common\helpers\Tax::add_tax_always($products_price, $products_tax) * $quantity, $this->currencies[$order_info['currency']]['decimal_places']);
            }
        }
    }
    /**
     *
     * @param float $products_price ex 9.99
     * @param float $products_tax 19.5 (usually <100)
     * @param int $quantity default 1
     * @param string $currency
     * @param bool $add_tax_always default false
     * @return number
     */
    public function calculate_price($products_price, $products_tax, $quantity = 1, $currency = '', $add_tax_always = false)
    {
        if (empty($currency)) {
            $currency = \Yii::$app->settings->get('currency');
        }
        if (\frontend\design\Info::is_totally_admin() && empty($currency)) {
            $currency = DEFAULT_CURRENCY;
            \Yii::$app->settings->set('currency', $currency);
        }
        if (defined('PRICE_WITH_BACK_TAX') && PRICE_WITH_BACK_TAX == 'True') {
            if ($products_tax < 0) {
                $method = 'reduce_tax_always';
                $products_tax = abs($products_tax);
            } else {
                $method = 'add_tax';
            }
        } elseif ($add_tax_always || $products_tax > 0 && \common\helpers\Tax::display_taxable()) {
            $method = 'add_tax_always';
        } else {
            $method = 'add_tax';
        }
        if (!is_numeric($quantity)) {
            $quantity = 1;
        }
        if (defined('PRODUCTS_PRICE_QTY_ROUND') && PRODUCTS_PRICE_QTY_ROUND == 'true') {
            return round(\common\helpers\Tax::$method($products_price, $products_tax), $this->currencies[$currency]['decimal_places']) * $quantity;
        } elseif (defined('PRODUCTS_PRICE_EXC_ROUND') && PRODUCTS_PRICE_EXC_ROUND == 'true') {
            return round(\common\helpers\Tax::$method($products_price * $quantity, $products_tax), $this->currencies[$currency]['decimal_places']);
        } elseif (defined('DISPLAY_PRICE_WITH_TAX') && PRODUCTS_PRICE_EXC_ROUND == 'false') {
            // 2check US/b2b tax-style fix (no inc VAT price on frontend)
            return \common\helpers\Tax::$method($products_price * $quantity, $products_tax);
        } else {
            return round(\common\helpers\Tax::$method($products_price, $products_tax) * $quantity, $this->currencies[$currency]['decimal_places']);
        }
    }
    /**
     * Display nothing or price with/without tax (format(calculate_price) )
     * @param decimal|bool $products_price ex 9.99
     * @param float  $products_tax 19.5 (usually <100)
     * @param int $quantity default 1
     * @param bool $microdata add microdata <span itemprop="priceCurrency|price">  default true
     * @param bool $metaTags register meta microdata  default false
     * @return string formatted price
     */
    public function display_price($products_price, $products_tax, $quantity = 1, $microdata = true, $meta_tags = false)
    {
        $ret = $this->get_price_taxable($products_price, $products_tax, $quantity);
        if ($ret != '') {
            $ret = $this->format($ret, true, '', '', $microdata, $meta_tags);
        }
        return $ret;
        /*
                if ($products_price === false) {
                    return '';
                } else {
                    if ( !is_numeric($quantity) ) $quantity = 1;
                    if ($products_tax > 0  && !\common\helpers\Tax::displayTaxable() ){
                      $products_tax = 0;
                    }
                    return $this->format($this->calculate_price($products_price, $products_tax, $quantity), true, '', '', $microdata, $metaTags);
                }*/
    }
    /**
     *
     * @param type $products_price
     * @param type $products_tax
     * @param type $quantity
     * @return string
     */
    public function display_price_clear($products_price, $products_tax, $quantity = 1)
    {
        $ret = $this->get_price_taxable($products_price, $products_tax, $quantity);
        if ($ret != '') {
            $ret = $this->format_clear($ret);
        }
        return $ret;
    }
    public function get_price_taxable($products_price, $products_tax, $quantity = 1)
    {
        $ret = '';
        if ($products_price === false) {
            return '';
        } else {
            if ($products_tax > 0 && !\common\helpers\Tax::display_taxable()) {
                $products_tax = 0;
            }
            return $this->calculate_price($products_price, $products_tax, $quantity);
        }
    }
    public function display_gift_card_price($products_price, $products_tax, $gift_card_currency = '')
    {
        $currency = \Yii::$app->settings->get('currency');
        if (tep_not_null($gift_card_currency) && \common\helpers\Currencies::currency_exists($gift_card_currency)) {
            $currency = $gift_card_currency;
        }
        $old_decimal_places = $this->currencies[$currency]['decimal_places'];
        $this->currencies[$currency]['decimal_places'] = 0;
        $return = $this->format(\common\helpers\Tax::add_tax($products_price, $products_tax));
        $this->currencies[$currency]['decimal_places'] = $old_decimal_places;
        return $return;
    }
    public function get_market_price_rate($from_currency, $to_currency)
    {
        $div = $this->get_value($from_currency);
        if (!$div) {
            $div = 1;
        }
        return $this->get_value($to_currency) / $div;
    }
    public function add_currency_icon(float $number, string $currency_type = ''): string
    {
        $currency = \Yii::$app->settings->get('currency');
        if (\frontend\design\Info::is_totally_admin() && empty($currency)) {
            $currency = DEFAULT_CURRENCY;
            \Yii::$app->settings->set('currency', $currency);
        }
        if (empty($currency_type)) {
            $currency_type = $currency;
        }
        $format_string = '';
        if ($this->currencies[$currency_type]['symbol_left']) {
            $format_string .= $this->currencies[$currency_type]['symbol_left'];
        }
        $format_string .= $number;
        if ($this->currencies[$currency_type]['symbol_right']) {
            $format_string .= $this->currencies[$currency_type]['symbol_right'];
        }
        return $format_string;
    }
    public function rate($currency_type = '', $currency_value = '')
    {
        $currency = \Yii::$app->settings->get('currency');
        if (\frontend\design\Info::is_totally_admin() && empty($currency)) {
            $currency = DEFAULT_CURRENCY;
            \Yii::$app->settings->set('currency', $currency);
        }
        if (empty($currency_type)) {
            $currency_type = $currency;
        }
        return tep_not_null($currency_value) ? $currency_value : $this->currencies[$currency_type]['value'];
    }
    /**
     * returns ISO 4217 currency codes (numeric)
     * @param string $currency_type
     * @return numeric
     */
    public function get_code_number($currency_type = '')
    {
        $currency = \Yii::$app->settings->get('currency');
        if (\frontend\design\Info::is_totally_admin() && empty($currency)) {
            $currency = DEFAULT_CURRENCY;
            \Yii::$app->settings->set('currency', $currency);
        }
        if (empty($currency_type)) {
            $currency_type = $currency;
        }
        return $this->currencies[$currency_type]['code_number'];
    }
}