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

use backend\components\Navigation;
use common\helpers\Translation;
use Yii;
class Data
{
    public static $js_global_data = [];
    public static function add_js_data($arr = [])
    {
        self::$js_global_data = \yii\helpers\Array_Helper::merge(self::$js_global_data, $arr);
    }
    private static $layout_translations_list = ['TEXT_MY_ACCOUNT', 'TEXT_HEADER_LOGOUT', 'DIR_WS_CATALOG_IMAGES', 'TEXT_HEADER_CONTACT_US', 'TEXT_SUPPORT', 'TEXT_ECOMMERCE_DEVELOPMENT', 'TEXT_EVERYDAY_ACTIVITIES', 'TEXT_FULL_MENU', 'TEXT_CURRENT_TIME', 'TEXT_SERVER_TIME', 'TEXT_COPYRIGHT', 'TEXT_COPYRIGHT_HOLBI', 'TEXT_FOOTER_BOTTOM', 'TEXT_FOOTER_COPYRIGHT', 'HEADER_PHONE', 'TEXT_ENTERED_CHARACTERS', 'TEXT_LEFT_CHARACTERS', 'TEXT_OVERFLOW_CHARACTERS', 'TEXT_VIEW_SHOP', 'SW_ON', 'SW_OFF'];
    private static $wl_list = ['WL_ENABLED', 'WL_COMPANY_NAME', 'WL_COMPANY_PHONE', 'WL_CONTACT_TEXT', 'WL_CONTACT_WWW', 'WL_CONTACT_URL', 'WL_SUPPORT_URL', 'WL_SUPPORT_TEXT', 'WL_SUPPORT_WWW', 'WL_SERVICES_TEXT'];
    private static $day_of_week = ['dayOfWeek' => [TEXT_SUNDAY, TEXT_MONDAY, TEXT_TUESDAY, TEXT_WEDNESDAY, TEXT_THURSDAY, TEXT_FRIDAY, TEXT_SATURDAY]];
    private static $month_names = ['monthNames' => [TEXT_JAN, TEXT_FAB, TEXT_MAR, TEXT_APR, TEXT_MAY, TEXT_JUN, TEXT_JUL, TEXT_AUG, TEXT_SEP, TEXT_OCT, TEXT_NOV, TEXT_DEC]];
    private static $edit_image_translations_list = ['UPLOAD_FROM_COMPUTER', 'TEXT_OR', 'UPLOAD_FROM_GALLERY', 'IMAGE_UPLOAD', 'TEXT_DROP_FILES', 'TEXT_EDIT_IMAGE', 'TEXT_AFTER_SAVING', 'TEXT_CHOOSE_SIDE_COLOR', 'IMAGE_CANCEL', 'IMAGE_SAVE', 'TEXT_ALIGN_BORDERS', 'TEXT_THEMES_FOLDER', 'TEXT_GENERAL_FOLDER', 'TEXT_ALL_FILES', 'TEXT_POOR_QUALITY', 'OPTION_NONE', 'IMAGE_APPLY'];
    public static function get_json_data()
    {
        return addslashes(json_encode(self::$js_global_data));
    }
    public static function main_data()
    {
        if (WL_ENABLED && WL_COMPANY_LOGO) {
            self::add_js_data(['wl' => ['companLogoUrl' => Yii::$app->view->theme->base_url . '/img/' . WL_COMPANY_LOGO]]);
        } else {
            self::add_js_data(['wl' => ['companLogoUrl' => Yii::$app->view->theme->base_url . '/img/logo3.svg']]);
        }
        if (WL_ENABLED && WL_COMPANY_NAME) {
            self::add_js_data(['wl' => ['logoAlt' => WL_COMPANY_NAME, 'wlFooter' => true]]);
        } else {
            self::add_js_data(['wl' => ['logoAlt' => 'logo']]);
        }
        if (WL_ENABLED && WL_SERVICES_URL && WL_SERVICES_WWW && WL_SERVICES_TEXT) {
            self::add_js_data(['wl' => ['servicesUrl' => WL_SERVICES_WWW]]);
        } else {
            self::add_js_data(['wl' => ['servicesUrl' => 'http://www.holbi.co.uk/ecommerce-development']]);
        }
        if (WL_ENABLED && WL_SERVICES_URL && WL_SERVICES_WWW && WL_SERVICES_TEXT) {
            self::add_js_data(['wl' => ['servicesText' => WL_SERVICES_TEXT]]);
        } else {
            self::add_js_data(['wl' => ['servicesText' => TEXT_ECOMMERCE_DEVELOPMENT]]);
        }
        if (WL_ENABLED && WL_SUPPORT_URL && WL_SUPPORT_TEXT && WL_SUPPORT_WWW) {
            self::add_js_data(['wl' => ['supportUrl' => WL_SUPPORT_WWW]]);
        } else {
            self::add_js_data(['wl' => ['supportUrl' => 'http://www.holbi.co.uk/ecommerce-support']]);
        }
        if (WL_ENABLED && WL_SUPPORT_URL && WL_SUPPORT_TEXT && WL_SUPPORT_WWW) {
            self::add_js_data(['wl' => ['supportText' => WL_SUPPORT_TEXT]]);
        } else {
            self::add_js_data(['wl' => ['supportText' => TEXT_SUPPORT]]);
        }
        if (WL_ENABLED && WL_CONTACT_URL && WL_CONTACT_TEXT && WL_CONTACT_WWW) {
            self::add_js_data(['wl' => ['contactUsText' => WL_CONTACT_TEXT]]);
        } else {
            self::add_js_data(['wl' => ['contactUsText' => TEXT_HEADER_CONTACT_US]]);
        }
        if (WL_ENABLED && WL_CONTACT_URL && WL_CONTACT_TEXT && WL_CONTACT_WWW) {
            self::add_js_data(['wl' => ['contactUsUrl' => WL_CONTACT_WWW]]);
        } else {
            self::add_js_data(['wl' => ['contactUsUrl' => 'http://www.holbi.co.uk/contact-us']]);
        }
        $layout_translations_arr = Translation::translations_for_js(self::$layout_translations_list, false);
        $edit_image_translations_arr = Translation::translations_for_js(self::$edit_image_translations_list, false);
        $page_translations_arr = Translation::translations_for_js(Yii::$app->controller->view->translations, false);
        $tr = array_merge($layout_translations_arr, $edit_image_translations_arr, $page_translations_arr, self::$day_of_week, self::$month_names);
        $wl_arr = array_merge(Translation::translations_for_js(self::$wl_list, false), ['CONTACT_US_URL' => 'https://www.holbi.co.uk/contact-us', 'SERVICES_URL' => 'http://www.holbi.co.uk/ecommerce-development']);
        self::add_js_data(['mainUrl' => preg_replace("/(\\/)+\$/", '', Yii::$app->url_manager->create_absolute_url('')), 'baseUrl' => Yii::$app->url_manager->create_url(''), 'themeBaseUrl' => Yii::$app->view->theme->base_url, 'frontendUrl' => tep_catalog_href_link(), 'adminData' => \common\helpers\Admin_Box::get_data(tep_session_var('login_id')), 'adminAccountUrl' => Yii::$app->url_manager->create_url('adminaccount'), 'adminLogoutUrl' => Yii::$app->url_manager->create_url('logout'), 'mainMenu' => json_decode(Navigation::widget(['noHtml' => true])), 'selectedMenu' => Yii::$app->controller->selected_menu, 'pageTitle' => Yii::$app->controller->view->heading_title, 'serverTime' => date('U'), 'serverTimeFormat' => [date('Y'), date('n') - 1, date('j'), date('G'), date('i'), date('s')]]);
        self::add_js_data(['config' => ['META_TITLE_MAX_TAG_LENGTH' => META_TITLE_MAX_TAG_LENGTH, 'META_DESCRIPTION_TAG_LENGTH' => META_DESCRIPTION_TAG_LENGTH], 'mainUrl' => preg_replace("/(\\/)+\$/", '', Yii::$app->url_manager->create_absolute_url('')), 'tr' => $tr, 'wl' => $wl_arr]);
    }
}