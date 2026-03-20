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
namespace backend\design;

use common\classes\design;
use Yii;
use yii\helpers\Array_Helper;
class Frontend_Structure
{
    private static $fielded = false;
    private static $theme_name = '';
    private static $group_by_type = [];
    private static $groups = [
        'home' => ['name' => 'home', 'title' => TEXT_HOME, 'types' => ['home']],
        'catalog' => ['name' => 'catalog', 'title' => TEXT_CATALOG, 'types' => ['product', 'catalog', 'categories', 'products']],
        'productListing' => ['name' => 'productListing', 'title' => TEXT_PRODUCT_LISTING_ITEMS, 'types' => ['productListing']],
        'informations' => ['name' => 'informations', 'title' => TEXT_INFORMATIONS, 'types' => ['inform', 'delivery-location', 'delivery-location-default', 'promotions', 'sitemap', 'reviews']],
        'account' => ['name' => 'account', 'title' => TEXT_ACCOUNT, 'types' => ['account']],
        'cart' => ['name' => 'cart', 'title' => TEXT_CART, 'types' => ['cart', 'quote', 'sample', 'wishlist']],
        'checkout2' => ['name' => 'checkout2', 'title' => TEXT_STEPS_CHECKOUT, 'types' => ['checkout']],
        'checkout' => ['name' => 'checkout', 'title' => TEXT_CHECKOUT, 'types' => ['checkout']],
        /*'checkoutQuote2' => [
              'name' => 'checkoutQuote2',
              'title' => TEXT_QUOTE_CHECKOUT,
              'types' => ['checkout'],
          ],*/
        'checkoutQuote' => ['name' => 'checkoutQuote', 'title' => TEXT_QUOTE_CHECKOUT, 'types' => ['checkout']],
        'orders' => ['name' => 'orders', 'title' => IMAGE_ORDERS, 'types' => ['invoice', 'packingslip', 'orders']],
        'emails' => ['name' => 'emails', 'title' => TEXT_EMAIL_GIFT_CARD, 'types' => ['email', 'gift', 'gift_card', 'gift_card_pdf']],
        'components' => ['name' => 'components', 'title' => TEXT_COMPONENTS, 'types' => ['components']],
    ];
    private static $backend_groups = ['backendOrder' => ['name' => 'backendOrder', 'title' => TABLE_HEADING_ORDER, 'types' => ['backendOrder']], 'backendOrdersList' => ['name' => 'backendOrdersList', 'title' => ORDERS_LIST, 'types' => ['backendOrdersList']]];
    private static $types = ['home' => ['mainAction' => ''], 'product' => ['mainAction' => 'catalog/product'], 'catalog' => ['mainAction' => 'catalog'], 'categories' => ['mainAction' => 'catalog'], 'products' => ['mainAction' => 'catalog'], 'productListing' => ['mainAction' => 'catalog/product-listing'], 'inform' => ['mainAction' => 'info/index'], 'delivery-location' => ['mainAction' => ''], 'delivery-location-default' => ['mainAction' => ''], 'promotions' => ['mainAction' => ''], 'sitemap' => ['mainAction' => ''], 'reviews' => ['mainAction' => ''], 'account' => ['mainAction' => 'account/index'], 'cart' => ['mainAction' => ''], 'quote' => ['mainAction' => ''], 'sample' => ['mainAction' => ''], 'wishlist' => ['mainAction' => 'account/index'], 'checkout' => ['mainAction' => ''], 'invoice' => ['mainAction' => 'orders/invoice'], 'packingslip' => ['mainAction' => 'orders/packingslip'], 'orders' => ['mainAction' => 'orders/invoice'], 'email' => ['mainAction' => 'email-template'], 'gift' => ['mainAction' => 'catalog/gift-card'], 'gift_card' => ['mainAction' => 'catalog/gift'], 'gift_card_pdf' => ['mainAction' => 'catalog/gift'], 'pdf' => ['mainAction' => 'pdf'], 'components' => ['mainAction' => ''], 'login' => ['mainAction' => ''], 'login_checkout' => ['mainAction' => ''], 'subscribe' => ['mainAction' => ''], 'compare' => ['mainAction' => ''], 'backendOrder' => ['mainAction' => 'orders'], 'backendOrdersList' => ['mainAction' => 'orders/list']];
    private static $united_types = [['inform', 'sitemap'], ['delivery-location', 'delivery-location-default'], ['cart', 'quote', 'sample', 'wishlist'], ['invoice', 'packingslip', 'orders'], ['catalog', 'categories', 'products']];
    private static $pages = ['home' => ['action' => '', 'name' => 'home', 'page_name' => 'main', 'title' => TEXT_HOME, 'type' => 'home', 'group' => 'home', 'settings' => true], 'product' => ['action' => 'catalog/product', 'name' => 'product', 'page_name' => 'product', 'title' => TEXT_PRODUCT, 'type' => 'product', 'group' => 'catalog', 'settings' => true], 'categories' => ['action' => 'catalog', 'name' => 'categories', 'page_name' => 'categories', 'title' => TEXT_LISTING_CATEGORIES, 'type' => 'categories', 'group' => 'catalog', 'settings' => true], 'products' => ['action' => 'catalog', 'name' => 'products', 'page_name' => 'products', 'title' => TEXT_LISTING_PRODUCTS, 'type' => 'products', 'group' => 'catalog', 'settings' => true], 'productListing' => ['action' => 'catalog/product-listing', 'name' => 'productListing', 'page_name' => 'productListing', 'title' => TEXT_PRODUCT, 'type' => 'productListing', 'group' => 'productListing'], 'manufacturers' => ['action' => 'catalog/brands', 'name' => 'manufacturers', 'page_name' => 'manufacturers', 'title' => TEXT_BRANDS, 'type' => 'catalog', 'group' => 'catalog'], 'compare' => ['action' => 'catalog/compare', 'name' => 'compare', 'page_name' => 'compare', 'title' => TEXT_COMPARE, 'type' => 'compare', 'group' => 'catalog'], 'info' => ['action' => 'info/index', 'name' => 'info', 'page_name' => 'info', 'title' => TEXT_INFORMATION, 'type' => 'inform', 'group' => 'informations', 'settings' => true], '404' => ['action' => 'index/404', 'name' => '404', 'page_name' => '404', 'title' => '404', 'type' => 'inform', 'group' => 'informations'], 'cart' => ['action' => 'shopping-cart/index', 'name' => 'cart', 'page_name' => 'cart', 'title' => TEXT_SHOPPING_CART, 'type' => 'cart', 'group' => 'cart'], 'quote' => ['action' => 'quote-cart/index', 'name' => 'quote', 'page_name' => 'quote', 'title' => TEXT_QUOTE_CART, 'type' => 'quote', 'group' => 'cart'], 'wishlist' => ['action' => 'account/wishlist', 'name' => 'wishlist-cart', 'page_name' => 'wishlist-cart', 'title' => TEXT_WISHLIST, 'type' => 'wishlist', 'group' => 'cart'], 'login_checkout' => ['action' => 'checkout/login', 'name' => 'login_checkout', 'page_name' => 'login_checkout', 'title' => 'Login', 'type' => 'login', 'group' => 'checkout'], 'checkout' => ['action' => 'checkout/index', 'name' => 'checkout', 'page_name' => 'checkout', 'title' => TEXT_CHECKOUT, 'type' => 'checkout', 'group' => 'checkout'], 'confirmation' => ['action' => 'checkout/confirmation', 'name' => 'confirmation', 'page_name' => 'confirmation', 'title' => TEXT_CHECKOUT_CONFIRMATION, 'type' => 'confirmation', 'group' => 'checkout'], 'checkout_no_shipping' => ['action' => 'checkout/index', 'name' => 'checkout_no_shipping', 'page_name' => 'checkout_no_shipping', 'title' => CHECKOUT_NO_SHIPPING, 'type' => 'checkout', 'group' => 'checkout'], 'confirmation_no_shipping' => ['action' => 'checkout/confirmation', 'name' => 'checkout_no_shipping', 'page_name' => 'checkout_no_shipping', 'title' => CHECKOUT_CONFIRMATION_NO_SHIPPING, 'type' => 'checkout', 'group' => 'checkout'], 'success' => ['action' => 'checkout/success', 'name' => 'success', 'page_name' => 'success', 'title' => TEXT_CHECKOUT_SUCCESS, 'type' => 'success', 'group' => 'checkout'], 'login_checkout2' => ['action' => 'checkout/login', 'name' => 'login_checkout2', 'page_name' => 'login_checkout2', 'title' => 'Login', 'type' => 'login', 'group' => 'checkout2'], 'checkout2' => ['action' => 'checkout/index', 'name' => 'checkout2', 'page_name' => 'checkout2', 'title' => TEXT_CHECKOUT, 'type' => 'checkout', 'group' => 'checkout2'], 'confirmation2' => ['action' => 'checkout/confirmation', 'name' => 'confirmation2', 'page_name' => 'confirmation2', 'title' => TEXT_CHECKOUT_CONFIRMATION, 'type' => 'confirmation', 'group' => 'checkout2'], 'checkout_no_shipping2' => ['action' => '', 'name' => 'checkout_no_shipping2', 'page_name' => 'checkout_no_shipping2', 'title' => CHECKOUT_NO_SHIPPING, 'type' => 'checkout', 'group' => 'checkout2'], 'confirmation_no_shipping2' => ['action' => 'checkout/confirmation', 'name' => 'confirmation_no_shipping2', 'page_name' => 'confirmation_no_shipping2', 'title' => CHECKOUT_CONFIRMATION_NO_SHIPPING, 'type' => 'confirmation', 'group' => 'checkout2'], 'success2' => ['action' => 'checkout/success', 'name' => 'success2', 'page_name' => 'success2', 'title' => TEXT_CHECKOUT_SUCCESS, 'type' => 'success', 'group' => 'checkout2'], 'login_checkout_q' => ['action' => 'quote-checkout/login', 'name' => 'login_checkout_q', 'page_name' => 'login_checkout_q', 'title' => 'Login', 'type' => 'login', 'group' => 'checkoutQuote'], 'checkout_q' => ['action' => 'quote-checkout/index', 'name' => 'checkout_q', 'page_name' => 'checkout_q', 'title' => TEXT_CHECKOUT, 'type' => 'checkout', 'group' => 'checkoutQuote'], 'confirmation_q' => ['action' => 'quote-checkout/confirmation', 'name' => 'confirmation_q', 'page_name' => 'confirmation_q', 'title' => TEXT_CHECKOUT_CONFIRMATION, 'type' => 'confirmation', 'group' => 'checkoutQuote'], 'checkout_no_shipping_q' => ['action' => 'quote-checkout/index', 'name' => 'checkout_no_shipping_q', 'page_name' => 'checkout_no_shipping_q', 'title' => CHECKOUT_NO_SHIPPING, 'type' => 'checkout', 'group' => 'checkoutQuote'], 'confirmation_no_shipping_q' => ['action' => 'quote-checkout/confirmation', 'name' => 'confirmation_no_shipping_q', 'page_name' => 'confirmation_no_shipping_q', 'title' => CHECKOUT_CONFIRMATION_NO_SHIPPING, 'type' => 'confirmation', 'group' => 'checkoutQuote'], 'success_q' => ['action' => 'quote-checkout/success', 'name' => 'success_q', 'page_name' => 'success_q', 'title' => TEXT_CHECKOUT_SUCCESS, 'type' => 'success', 'group' => 'checkoutQuote'], 'login_checkout2_q' => ['action' => 'quote-checkout/login', 'name' => 'login_checkout2_q', 'page_name' => 'login_checkout2_q', 'title' => 'Login', 'type' => 'login', 'group' => 'checkoutQuote2'], 'checkout2_q' => ['action' => 'quote-checkout/index', 'name' => 'checkout2_q', 'page_name' => 'checkout2_q', 'title' => TEXT_CHECKOUT, 'type' => 'checkout', 'group' => 'checkoutQuote2'], 'confirmation2_q' => ['action' => 'quote-checkout/confirmation', 'name' => 'confirmation2_q', 'page_name' => 'confirmation2_q', 'title' => TEXT_CHECKOUT_CONFIRMATION, 'type' => 'confirmation', 'group' => 'checkoutQuote2'], 'checkout_no_shipping2_q' => ['action' => 'quote-checkout/index', 'name' => 'checkout_no_shipping2_q', 'page_name' => 'checkout_no_shipping2_q', 'title' => CHECKOUT_NO_SHIPPING, 'type' => 'checkout', 'group' => 'checkoutQuote2'], 'confirmation_no_shipping2_q' => ['action' => 'quote-checkout/confirmation', 'name' => 'confirmation_no_shipping2_q', 'page_name' => 'confirmation_no_shipping2_q', 'title' => CHECKOUT_CONFIRMATION_NO_SHIPPING, 'type' => 'confirmation', 'group' => 'checkoutQuote2'], 'success2_q' => ['action' => 'quote-checkout/success', 'name' => 'success2_q', 'page_name' => 'success2_q', 'title' => TEXT_CHECKOUT_SUCCESS, 'type' => 'success', 'group' => 'checkoutQuote2'], 'email' => ['action' => 'email-template', 'name' => 'email', 'page_name' => 'email', 'title' => 'Email', 'type' => 'inform', 'group' => 'emails'], 'gift_card' => ['action' => 'catalog/gift-card', 'name' => 'gift', 'page_name' => 'gift', 'title' => GIFT_PAGE, 'type' => 'gift', 'group' => 'emails'], 'gift' => ['action' => 'catalog/gift', 'name' => 'gift_card', 'page_name' => 'gift_card', 'title' => TEXT_GIFT_CARD, 'type' => 'gift_card', 'group' => 'emails'], 'gift_pdf' => ['action' => 'catalog/gift', 'name' => 'gift_card_pdf', 'page_name' => 'gift_card_pdf', 'title' => TEXT_GIFT_CARD . ' PDF', 'type' => 'gift_card_pdf', 'group' => 'emails'], 'account' => ['action' => 'account/index', 'name' => 'account', 'page_name' => 'account', 'title' => 'Account', 'type' => 'account', 'group' => 'account'], 'login_account' => ['action' => 'account/login', 'name' => 'login_account', 'page_name' => 'login_account', 'title' => 'Login', 'type' => 'login', 'group' => 'account'], 'logoff' => ['action' => 'account/logoff', 'name' => 'logoff', 'page_name' => 'logoff', 'title' => 'Logoff', 'type' => 'inform', 'group' => 'account'], 'logoff_forever' => ['action' => 'account/logoff', 'name' => 'logoff_forever', 'page_name' => 'logoff_forever', 'title' => 'Account Deleted', 'type' => 'inform', 'group' => 'account'], 'password_forgotten' => ['action' => 'account/password-forgotten', 'name' => 'password_forgotten', 'page_name' => 'password_forgotten', 'title' => 'Password forgotte', 'type' => 'account', 'group' => 'account'], 'invoice' => ['action' => 'orders/invoice', 'name' => 'invoice', 'page_name' => 'invoice', 'title' => TEXT_INVOICE, 'type' => 'invoice', 'group' => 'orders', 'settings' => true], 'packingslip' => ['action' => 'orders/packingslip', 'name' => 'packingslip', 'page_name' => 'packingslip', 'title' => TEXT_PACKINGSLIP, 'type' => 'packingslip', 'group' => 'orders', 'settings' => true], 'credit_note' => ['action' => 'orders/credit-note', 'name' => 'credit_note', 'page_name' => 'credit_note', 'title' => TEXT_CREDITNOTE, 'type' => 'orders', 'group' => 'orders', 'settings' => true], 'sitemap' => ['action' => 'sitemap', 'name' => 'sitemap', 'page_name' => 'sitemap', 'title' => 'Sitemap', 'type' => 'sitemap', 'group' => 'informations'], 'reviews' => ['action' => 'reviews', 'name' => 'reviews', 'page_name' => 'reviews', 'title' => 'Reviews', 'type' => 'reviews', 'group' => 'informations']];
    private static $has_settings = ['product', 'categories', 'products', 'home', 'order', 'inform', 'invoice', 'packingslip'];
    private static function init()
    {
        if (self::$fielded) {
            return false;
        }
        self::$fielded = true;
        if (Yii::$app->request->get('theme_name')) {
            self::$theme_name = Yii::$app->request->get('theme_name');
        } elseif (defined('THEME_NAME')) {
            self::$theme_name = THEME_NAME;
        } else {
            return false;
        }
        self::set_group_by_type();
        self::field_pages();
        self::field_page_get_params();
        return false;
    }
    private static function field_page_get_params()
    {
        $theme_platforms = \common\classes\platform::get_list();
        foreach ($theme_platforms as $platform) {
            foreach (self::$pages as $page_key => $page) {
                switch ($page['type']) {
                    case 'product':
                        self::field_page_get_params_product($page_key, $platform['id'], $page['page_name']);
                        break;
                    case 'categories':
                        self::field_page_get_params_categories($page_key, $platform['id'], $page['page_name']);
                        break;
                    case 'products':
                        self::field_page_get_params_products($page_key, $platform['id'], $page['page_name']);
                        break;
                    case 'inform':
                        self::field_page_get_params_inform($page_key, $platform['id'], $page['page_name']);
                        break;
                    case 'delivery-location':
                        self::field_page_get_params_delivery_location($page_key, $platform['id'], $page['page_name']);
                        break;
                    case 'delivery-location-default':
                        self::field_page_get_params_delivery_location_default($page_key, $platform['id'], $page['page_name']);
                        break;
                    case 'invoice':
                    case 'packingslip':
                    case 'orders':
                        self::field_page_get_params_orders($page_key, $platform['id'], $page['page_name']);
                        break;
                }
            }
        }
    }
    private static function field_page_get_params_product($page_key, $platform_id, $page_name)
    {
        $template_products = \common\models\Product_To_Template::find()->select(['products_id'])->where(['platform_id' => $platform_id, 'template_name' => $page_name])->as_array()->all();
        $products_id = \common\models\Products::find()->alias('p')->select(['p.products_id'])->inner_join(['plp' => \common\models\Platforms_Products::table_name()], "plp.products_id=p.products_id and plp.platform_id='" . $platform_id . "'")->where(['p.products_status' => '1'])->one()->products_id ?? null;
        self::$pages[$page_key]['get_params'][$platform_id]['products_id'] = $products_id;
    }
    private static function field_page_get_params_categories($page_key, $platform_id, $page_name)
    {
        $categories_id = \common\models\Categories::find()->alias('c')->select(['c.parent_id'])->inner_join(['plc' => \common\models\Platforms_Categories::table_name()], "plc.categories_id=c.categories_id and plc.platform_id='" . $platform_id . "'")->where('parent_id != 0 and categories_status = 1')->one()->parent_id ?? null;
        self::$pages[$page_key]['get_params'][$platform_id]['cPath'] = $categories_id;
    }
    private static function field_page_get_params_products($page_key, $platform_id, $page_name)
    {
        self::$pages[$page_key]['action'] = 'catalog/all-products';
        //self::$pages[$pageKey]['get_params'][$platformId]['cPath'] = $categoriesId;
    }
    private static function field_page_get_params_inform($page_key, $platform_id, $page_name)
    {
        $information = \common\models\Information::find_one(['visible' => '1', 'platform_id' => $platform_id, 'template_name' => $page_name]);
        if ($information && $information->information_id) {
            $information_id = $information->information_id;
        } else {
            $information_id = \common\models\Information::find_one(['visible' => '1', 'platform_id' => $platform_id])->information_id ?? null;
        }
        self::$pages[$page_key]['get_params'][$platform_id]['info_id'] = $information_id;
    }
    private static function field_page_get_params_delivery_location($page_key, $platform_id, $page_name)
    {
        self::$pages[$page_key]['get_params'][$platform_id][''] = '';
    }
    private static function field_page_get_params_delivery_location_default($page_key, $platform_id, $page_name)
    {
        self::$pages[$page_key]['get_params'][$platform_id][''] = '';
    }
    private static function field_page_get_params_orders($page_key, $platform_id, $page_name)
    {
        self::$pages[$page_key]['get_params'][$platform_id][''] = '';
    }
    private static function set_group_by_type()
    {
        foreach (array_merge(self::$groups, self::$backend_groups) as $group) {
            foreach ($group['types'] as $type) {
                self::$group_by_type[$type] = $group['name'];
            }
        }
    }
    private static function field_pages()
    {
        foreach (\common\helpers\Acl::get_extension_pages() as $page) {
            $group_name = design::page_name($page['group'] ?? null);
            self::$pages[$page['name']] = ['action' => $page['action'], 'name' => $page['name'], 'page_name' => $page['name'], 'title' => $page['title'], 'type' => $page['type'], 'group' => $group_name, 'settings' => $page['settings'] ?? false];
            if (!Array_Helper::get_value(self::$groups, $group_name)) {
                self::$groups[$group_name] = ['name' => $group_name, 'title' => $page['group'], 'types' => [$page['type']]];
            } else if (!in_array($page['type'], self::$groups[$group_name]['types'])) {
                self::$groups[$group_name]['types'] = array_merge(self::$groups[$group_name]['types'], [$page['type']]);
            }
            if (!Array_Helper::get_value(self::$types, $page['type'])) {
                self::$types[$page['type']] = ['mainAction' => $page['action']];
            }
        }
        self::set_group_by_type();
        $added_pages = \common\models\Themes_Settings::find()->select(['setting_name', 'setting_value'])->where(['theme_name' => self::$theme_name, 'setting_group' => 'added_page'])->order_by('setting_name')->as_array()->all();
        foreach ($added_pages as $page) {
            if ($page['setting_name'] == 'info') {
                $page['setting_name'] = 'inform';
            }
            self::$pages[design::page_name($page['setting_value'])] = ['action' => self::$types[$page['setting_name']]['mainAction'] ?? null, 'name' => $page['setting_value'], 'page_name' => design::page_name($page['setting_value']), 'title' => $page['setting_value'], 'type' => $page['setting_name'], 'group' => self::$group_by_type[$page['setting_name']] ?? null, 'settings' => in_array($page['setting_name'] ?? null, self::$has_settings), 'added' => true];
            if ($page['setting_name'] == 'gift_card') {
                self::$pages[design::page_name($page['setting_value']) . '_pdf'] = ['action' => self::$types[$page['setting_name']]['mainAction'], 'name' => $page['setting_value'] . '_pdf', 'page_name' => design::page_name($page['setting_value']) . '_pdf', 'title' => $page['setting_value'] . ' pdf', 'type' => $page['setting_name'] . '_pdf', 'group' => self::$group_by_type[$page['setting_name']], 'settings' => in_array($page['setting_name'], self::$has_settings), 'added' => true];
            }
        }
    }
    public static function get_theme_platforms()
    {
        self::init();
        $theme_name = str_replace('-mobile', '', self::$theme_name);
        $theme = \common\models\Themes::find_one(['theme_name' => $theme_name]);
        if (!$theme) {
            return [];
        }
        $_theme_id = (int) $theme->id;
        $theme_platforms = [];
        $platforms = \common\models\Platforms_To_Themes::find()->where(['theme_id' => $_theme_id])->as_array()->all();
        if (is_array($platforms)) {
            foreach ($platforms as $platform) {
                foreach (\common\classes\platform::get_list() as $_platform_info) {
                    if ($_platform_info['id'] == $platform['platform_id']) {
                        $theme_platforms[] = $_platform_info;
                    }
                }
            }
        }
        if (count($theme_platforms) == 0) {
            $theme_platforms = \common\classes\platform::get_list();
            $theme_platforms = array_slice($theme_platforms, 0, 1);
        }
        return $theme_platforms;
    }
    public static function get_united_types_group($type = false)
    {
        if (!$type) {
            return self::$united_types;
        }
        foreach (self::$united_types as $united) {
            if (in_array($type, $united)) {
                return $united;
            }
        }
        return [$type];
    }
    public static function get_page_groups()
    {
        self::init();
        if (self::$theme_name == \common\classes\design::page_name(BACKEND_THEME_NAME)) {
            return self::$backend_groups;
        }
        return self::$groups;
    }
    public static function get_page_types()
    {
        self::init();
        return self::$types;
    }
    public static function get_pages()
    {
        self::init();
        return self::$pages;
    }
    public static function get_group_categories()
    {
        $categories = ['header' => ['tile' => 'Header'], 'footer' => ['tile' => 'Footer']];
        $categories = array_merge($categories, self::get_pages());
        return $categories;
    }
}