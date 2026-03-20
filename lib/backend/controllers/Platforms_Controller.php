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

use backend\components\Message_Popup;
use common\classes\platform;
use common\helpers\Acl;
use common\helpers\Translation;
use common\models\Platforms;
use common\models\Platforms_Settings;
use common\models\Platforms_To_Themes;
use common\models\repositories\Countries_Repositiry;
use common\models\Themes_Settings;
use common\services\Countries_Service;
use common\services\Currencies_Margin_Service;
use common\services\Zones_Service;
use Yii;
use yii\helpers\Array_Helper;
use yii\helpers\Html;
use yii\helpers\Url;
class Platforms_Controller extends Sceleton
{
    public $acl = ['BOX_HEADING_FRONENDS'];
    private $service_zone;
    private $service_country;
    private $currencies_margin_service;
    public function __construct($id, $module, Currencies_Margin_Service $currencies_margin_service, Zones_Service $service_zone, Countries_Service $service_country)
    {
        $this->currencies_margin_service = $currencies_margin_service;
        $this->service_zone = $service_zone;
        $this->service_country = $service_country;
        parent::__construct($id, $module);
    }
    /**
     * Index action is the default action in a controller.
     */
    public function action_index()
    {
        $this->selected_menu = ['fronends', 'platforms'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('platforms/index'), 'title' => HEADING_TITLE];
        if (false !== \common\helpers\Acl::rule(['SUPERUSER']) && $ext = \common\helpers\Acl::check_extension_allowed('AdditionalPlatforms', 'allowed')) {
            $ext::index();
        }
        //$this->topButtons[] = '<a href="'.Yii::$app->urlManager->createUrl('platforms/edit').'" class="create_item addprbtn"><i class="icon-tag"></i>'.TEXT_CREATE_NEW_PLATFORM.'</a>';
        $this->view->heading_title = HEADING_TITLE;
        $this->view->platforms_table = [['title' => TABLE_HEADING_PLATFORM_NAME, 'not_important' => 1], ['title' => TABLE_HEADING_PLATFORM_URL, 'not_important' => 1], ['title' => TABLE_HEADING_STATUS, 'not_important' => 1]];
        $this->view->filters = new \stdClass();
        $this->view->filters->row = (int) Yii::$app->request->get('row', 0);
        $this->view->filters->pane = Yii::$app->request->get('pane', 'physical');
        return $this->render('index');
    }
    public function action_list()
    {
        $draw = Yii::$app->request->get('draw', 1);
        $start = Yii::$app->request->get('start', 0);
        $length = Yii::$app->request->get('length', 10);
        $form_filter = Yii::$app->request->get('filter');
        parse_str($form_filter, $output);
        $type = $output['pane'] ?? 'physical';
        $response_list = [];
        if ($length == -1) {
            $length = 10000;
        }
        $query_numrows = 0;
        $platforms_query = Platforms::get_platforms_by_type($type);
        $query_numrows = $platforms_query->count();
        if (isset($_GET['search']['value']) && tep_not_null($_GET['search']['value'])) {
            $keywords = tep_db_input(tep_db_prepare_input($_GET['search']['value']));
            $platforms_query->and_where(['like', 'platform_name', $keywords]);
        }
        $filter_by_platform = false;
        if (false === \common\helpers\Acl::rule(['SUPERUSER'])) {
            global $login_id;
            $filter_by_platform = [];
            $platforms = \common\models\Admin_Platforms::find()->where(['admin_id' => $login_id])->as_array()->all();
            foreach ($platforms as $platform) {
                $filter_by_platform[] = $platform['platform_id'];
            }
            $filter_by_platform[] = 0;
        }
        if ($filter_by_platform !== false) {
            $platforms_query->and_where(['in', 'platform_id', $filter_by_platform]);
        }
        $platforms_query->order_by(new \yii\db\Expression('IF(is_default,0,1)'));
        if (isset($_GET['order'][0]['column']) && $_GET['order'][0]['dir']) {
            switch ($_GET['order'][0]['column']) {
                case 0:
                    $platforms_query->add_order_by('platform_name ' . tep_db_input(tep_db_prepare_input($_GET['order'][0]['dir'])));
                    break;
                case 1:
                    $platforms_query->add_order_by('sort_order ' . tep_db_input(tep_db_prepare_input($_GET['order'][0]['dir'])) . ', platform_id ');
                    break;
                default:
                    $platforms_query->add_order_by('sort_order, platform_name');
                    break;
            }
        } else {
            $platforms_query->add_order_by('sort_order, platform_name');
        }
        $query_show = $platforms_query->count();
        $platforms = $platforms_query->limit($length)->offset($start)->all();
        if ($platforms) {
            foreach ($platforms as $platform) {
                $statement = '';
                if (!\common\helpers\Acl::check_extension_allowed('AdditionalPlatforms', 'allowed')) {
                    if ($platform->platform_id != 1) {
                        $statement = ' dis_module';
                        $platform->status = 0;
                    }
                }
                Yii::$app->get('platform')->config($platform->platform_id);
                $status = '<input type="checkbox" value="' . $platform->platform_id . '" name="status" class="check_on_off" ' . ($platform->is_default ? 'disabled="disabled" ' : '') . ((int) $platform->status > 0 ? 'checked="checked"' : '') . '>';
                $response_list[] = ['<div class="handle_cat_list' . $statement . '"><span class="handle"><i class="icon-hand-paper-o"></i></span><div class="cat_name cat_name_attr cat_no_folder">' . $platform->platform_name . '<input class="cell_identify" type="hidden" value="' . $platform->platform_id . '">' . '<input class="cell_type" type="hidden" value="top">' . '</div></div>', '<a target="_blank" href="' . ($platform->ssl_enabled == '0' ? 'http://' : 'https://') . $platform->platform_url . '">' . $platform->platform_url . '</a>', $status];
            }
        }
        $response = ['draw' => $draw, 'recordsTotal' => $query_numrows, 'recordsFiltered' => $query_show, 'data' => $response_list, 'type' => $type];
        echo json_encode($response);
    }
    public function action_switch_status()
    {
        if ($ext = \common\helpers\Acl::check_extension_allowed('AdditionalPlatforms', 'allowed')) {
            $ext::switch_status();
        }
    }
    public function action_item_preedit()
    {
        $this->layout = false;
        \common\helpers\Translation::init('admin/platforms');
        $item_id = (int) Yii::$app->request->post('item_id');
        $platform = Platforms::find_one(['platform_id' => (int) $item_id]);
        if (!$platform) {
            throw new \DomainException('Not found');
        }
        $statement = true;
        if (!\common\helpers\Acl::check_extension_allowed('AdditionalPlatforms', 'allowed')) {
            if ($item_id != 1) {
                $statement = false;
            }
        }
        $multiplatform = '';
        if (count(platform::get_categories_assign_list()) > 1) {
            $multiplatform .= '<a href="' . Yii::$app->url_manager->create_url(['platforms/edit-catalog', 'id' => $item_id]) . '" class="btn btn-edit btn-process-order js-open-tree-popup">' . BUTTON_ASSIGN_CATEGORIES_PRODUCTS . '</a>';
        }
        $theme_edit_link = Url::to_route(['platforms/choose-theme', 'id' => $platform->platform_id]);
        $watermark_edit_link = Url::to_route(['platforms/setup-watermark', 'id' => $platform->platform_id]);
        $platform_soap_server_link = '';
        if (\common\helpers\Acl::check_extension_allowed('SoapServer', 'allowed')) {
            if (\common\helpers\Acl::rule(['BOX_HEADING_FRONENDS', 'BOX_SOAP_SERVER_SETTINGS'])) {
                $platform_soap_server_link = Url::to_route(['platforms/soap-server-configure', 'id' => $platform->platform_id]);
            }
        }
        $platform_rest_server_link = '';
        if (\common\helpers\Acl::check_extension_allowed('RestServer', 'allowed')) {
            if (\common\helpers\Acl::rule(['BOX_HEADING_FRONENDS', 'BOX_REST_SERVER_SETTINGS'])) {
                $platform_rest_server_link = Url::to_route(['platforms/rest-server-configure', 'id' => $platform->platform_id]);
            }
        }
        $platform_working_timetable_link = '';
        $platform_localization_link = '';
        if (!$platform->is_virtual && !$platform->is_marketplace) {
            $platform_working_timetable_link = Url::to_route(['platforms/working-timetable', 'id' => $platform->platform_id]);
            $platform_localization_link = Url::to_route(['platforms/configure-localization', 'id' => $platform->platform_id]);
        }
        return $this->render('view', ['platform' => $platform, 'statement' => $statement, 'multiplatform' => $multiplatform, 'theme_edit_link' => $theme_edit_link, 'watermark_edit_link' => $watermark_edit_link, 'platform_soap_server_link' => $platform_soap_server_link, 'platform_rest_server_link' => $platform_rest_server_link, 'platform_working_timetable_link' => $platform_working_timetable_link, 'platform_localization_link' => $platform_localization_link]);
    }
    public function action_edit()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/platforms');
        $item_id = 1;
        if ($ext = \common\helpers\Acl::check_extension_allowed('AdditionalPlatforms', 'allowed')) {
            $item_id = $ext::edit();
        }
        if ($item_id > 0) {
            $groups_query = tep_db_query('select * from ' . TABLE_PLATFORMS . " where platform_id = '" . (int) $item_id . "'");
            $groups = tep_db_fetch_array($groups_query);
            $p_info = new \Object_Info($groups);
        } else {
            $p_info = new \Object_Info([]);
        }
        if ($item_id) {
            $address_query = tep_db_query('select ab.*, if (LENGTH(ab.entry_state), ab.entry_state, z.zone_name) as entry_state, c.countries_name  from ' . TABLE_PLATFORMS_ADDRESS_BOOK . ' ab left join ' . TABLE_COUNTRIES . " c on ab.entry_country_id=c.countries_id  and c.language_id = '" . (int) $languages_id . "' left join " . TABLE_ZONES . " z on z.zone_country_id=c.countries_id and ab.entry_zone_id=z.zone_id where platform_id = '" . (int) $item_id . "' ");
            $d = tep_db_fetch_array($address_query);
        } else {
            $d = [];
        }
        if (!isset($d['entry_country_id'])) {
            $d['entry_country_id'] = STORE_COUNTRY;
        }
        $addresses = new \Object_Info($d);
        $p_info->platform_urls = [];
        $get_platform_urls_r = tep_db_query('SELECT * FROM ' . TABLE_PLATFORMS_URL . " WHERE platform_id='" . (int) $item_id . "' ");
        if (tep_db_num_rows($get_platform_urls_r) > 0) {
            while ($_platform_url = tep_db_fetch_array($get_platform_urls_r)) {
                $p_info->platform_urls[] = $_platform_url;
            }
        }
        \common\helpers\Php8::null_obj_props($p_info, ['platform_id', 'is_default', 'is_default_address', 'is_default_contact', 'platform_images_cdn_status', 'ssl_enabled', 'is_virtual', 'is_marketplace']);
        /*        $this->view->currencies = [];
                        $currencies = Yii::$container->get('currencies');
                        foreach ($currencies->currencies as $currency) {
                            $this->view->currencies[$currency['code']] = $currency['title'];
                        }
        
                        $this->view->languages = [];
                        $languages = \common\helpers\Language::get_languages();
                        foreach ($languages as $language) {
                            $this->view->languages[$language['code']] = $language['name'];
                        }*/
        $text_new_or_edit = $item_id == 0 ? TEXT_INFO_HEADING_NEW_PLATFORM : TEXT_INFO_HEADING_EDIT_PLATFORM;
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('platforms/'), 'title' => $text_new_or_edit . ' ' . ($p_info->platforms_name ?? null)];
        $this->selected_menu = ['fronends', 'platforms'];
        if (Yii::$app->request->is_post) {
            $this->layout = false;
        }
        $this->view->show_state = ACCOUNT_STATE == 'required' || ACCOUNT_STATE == 'visible';
        $have_more_then_one_platform = true;
        if (tep_db_num_rows(tep_db_query('select platform_id from ' . TABLE_PLATFORMS . '')) < 2) {
            $have_more_then_one_platform = false;
            if (!$p_info->is_default) {
                $p_info->is_default = 1;
            }
        }
        $checkbox_default_platform_attr = [];
        if ($p_info->is_default) {
            // disable off for default - only on available
            $checkbox_default_platform_attr['readonly'] = 'readonly';
        }
        if ($p_info->platform_url ?? null) {
            $this->top_buttons[] = '<a href="//' . $p_info->platform_url . '" class="btn btn-primary" target="_blank">' . IMAGE_PREVIEW . '</a>';
        }
        $this->top_buttons[] = '<button class="btn btn-confirm" form="save_item_form">' . IMAGE_SAVE . '</button>';
        $sattelites = Platforms::get_platforms_by_type('physical')->all();
        $s_ids = $s_urls = [];
        if ($sattelites) {
            array_map(function ($pl) {
                $pl->platform_url = ($pl->ssl_enabled ? 'https://' : 'http://') . $pl->platform_url;
            }, $sattelites);
            $s_ids = \yii\helpers\Array_Helper::map($sattelites, 'platform_id', 'platform_name');
            $s_urls = \yii\helpers\Array_Helper::map($sattelites, 'platform_id', 'platform_url');
        }
        $selected_zones = \common\models\Platforms_Geo_Zones::find()->and_where(['platform_id' => (int) $item_id])->select('geo_zone_id')->as_array()->column();
        $zones = \common\models\Geo_Zones::find()->select('geo_zone_name, geo_zone_id')->order_by('geo_zone_name')->index_by('geo_zone_id')->as_array()->column();
        $repository_countries = new Countries_Repositiry();
        $countries_array = $repository_countries->get_platforms_countries($item_id);
        $selected_countries = [];
        foreach ($countries_array as $item => $country) {
            $selected_countries[] = $country->countries_id;
        }
        $countries = [TEXT_ALL => \common\helpers\Country::new_get_countries('', false)];
        $pass = dirname(__DIR__);
        $price_settings = defined('PLATFORM_OWN_PRICE') && PLATFORM_OWN_PRICE == 'true';
        $nv_platforms = null;
        $p_info->platform_settings = Platforms_Settings::find_one($p_info->platform_id);
        if (!$p_info->platform_settings) {
            $p_info->platform_settings = new Platforms_Settings();
            $p_info->platform_settings->use_owner_descriptions = \common\classes\platform::default_id();
        }
        //        $nvPlatforms = \yii\helpers\ArrayHelper::map(Platforms::getPlatformsByType('non-virtual')->andWhere(['and', ['status' => 1], ['<>', 'platform_id', (int)$pInfo->platform_id]])
        //                    ->orderBy("is_marketplace, platform_name")->asArray()->all(), 'platform_id', 'platform_name');
        $nv_platforms = Platforms::get_platforms_by_type('non-virtual')->alias('pl')->and_where(['and', ['status' => 1], ['<>', 'pl.platform_id', (int) $p_info->platform_id]])->inner_join(Platforms_Settings::table_name() . ' ps', 'pl.platform_id=ps.platform_id and ps.use_own_descriptions=1')->select('platform_name, pl.platform_id')->index_by('platform_id')->order_by('is_marketplace, platform_name')->as_array()->column();
        $warehouses = [];
        $check_query = tep_db_query('select w.warehouse_id, w.warehouse_name, ifnull(w2p.status, w.status) as status from ' . TABLE_WAREHOUSES . ' w left join ' . TABLE_WAREHOUSES_TO_PLATFORMS . " w2p on w.warehouse_id = w2p.warehouse_id and w2p.platform_id = '" . (int) $p_info->platform_id . "' where 1 order by ifnull(w2p.sort_order, w.sort_order), w.warehouse_name");
        while ($check = tep_db_fetch_array($check_query)) {
            $warehouses[] = ['id' => $check['warehouse_id'], 'text' => $check['warehouse_name'], 'status' => $check['status']];
        }
        /**
         * @var $ext \common\extensions\WarehousePriority\WarehousePriority
         */
        $warehouse_priorities = [];
        if ($ext = \common\helpers\Extensions::is_allowed('WarehousePriority')) {
            $warehouse_priorities = $ext::get_instance()->get_modules($item_id);
        }
        $exclusion_rules = unserialize($p_info->exclusion_rules ?? null);
        $exclusion_rules['method'] = is_array($exclusion_rules['method'] ?? null) ? $exclusion_rules['method'] : [];
        $exclusion_rules['type'] = is_array($exclusion_rules['type'] ?? null) ? $exclusion_rules['type'] : [];
        $exclusion_rules['value'] = is_array($exclusion_rules['value'] ?? null) ? $exclusion_rules['value'] : [];
        $organization_types = ['' => 'Organization', 'Store' => 'Store', 'Airline' => 'Airline', 'Consortium' => 'Consortium', 'Corporation' => 'Corporation', 'EducationalOrganization' => 'EducationalOrganization', 'FundingScheme' => 'FundingScheme', 'GovernmentOrganization' => 'GovernmentOrganization', 'LibrarySystem' => 'LibrarySystem', 'LocalBusiness' => 'LocalBusiness', 'MedicalOrganization' => 'MedicalOrganization', 'NGO' => 'NGO', 'NewsMediaOrganization' => 'NewsMediaOrganization', 'PerformingGroup' => 'PerformingGroup', 'Project' => 'Project', 'SportsOrganization' => 'SportsOrganization', 'WebSite' => 'WebSite', 'WorkersUnion' => 'WorkersUnion'];
        $exclusion_rule_type = ['ref' => TEXT_REFERER, 'get' => TEXT_PARAM, 'getval' => TEXT_PARAM_KEY . ' (key=value)'];
        /** @var \common\extensions\InvoiceNumberFormat\InvoiceNumberFormat $serverExt */
        if ($server_ext = \common\helpers\Acl::check_extension_allowed('InvoiceNumberFormat', 'allowed')) {
            $server_ext::on_invoice_number_format_edit($p_info);
        }
        return $this->render('edit.tpl', ['pInfo' => $p_info, 'addresses' => $addresses, 'cdn_url_types' => Yii::$app->get('mediaManager')->get_url_types(), 'checkbox_default_platform_attr' => $checkbox_default_platform_attr, 'have_more_then_one_platform' => $have_more_then_one_platform, 'sattelits' => $s_ids, 'sattelites_url' => $s_urls, 'pass' => $pass, 'price_settings' => $price_settings, 'nvPlatforms' => $nv_platforms, 'warehouses' => $warehouses, 'warehouse_priorities' => $warehouse_priorities, 'organization_types' => $organization_types, 'exclusion_rules' => $exclusion_rules, 'exclusion_rule_type' => $exclusion_rule_type]);
    }
    public function action_submit()
    {
        \common\helpers\Translation::init('admin/properties');
        $item_id = 1;
        if ($ext = \common\helpers\Acl::check_extension_allowed('AdditionalPlatforms', 'allowed')) {
            $item_id = $ext::edit();
        }
        $item_id = (int) $item_id;
        $platform_owner = tep_db_prepare_input(Yii::$app->request->post('platform_owner'));
        $platform_name = tep_db_prepare_input(Yii::$app->request->post('platform_name'));
        $platform_url = tep_db_prepare_input(Yii::$app->request->post('platform_url'));
        $platform_url = rtrim($platform_url, '/');
        $ssl_enabled = (int) Yii::$app->request->post('ssl_enabled', 0);
        $platform_url_secure = tep_db_prepare_input(Yii::$app->request->post('platform_url_secure'));
        $platform_url_secure = rtrim($platform_url_secure, '/');
        $platform_prefix = tep_db_prepare_input(Yii::$app->request->post('platform_prefix'));
        $use_social_login = (int) Yii::$app->request->post('use_social_login', 0);
        $checkout_logged_customer = (int) Yii::$app->request->post('checkout_logged_customer', 0);
        $platform_please_login = (int) Yii::$app->request->post('platform_please_login', 0);
        $platform_email_address = tep_db_prepare_input(Yii::$app->request->post('platform_email_address'));
        $platform_email_from = tep_db_prepare_input(Yii::$app->request->post('platform_email_from'));
        $platform_email_extra = tep_db_prepare_input(Yii::$app->request->post('platform_email_extra'));
        $contact_us_email = tep_db_prepare_input(Yii::$app->request->post('contact_us_email'));
        $landing_contact_email = tep_db_prepare_input(Yii::$app->request->post('landing_contact_email'));
        $platform_telephone = tep_db_prepare_input(Yii::$app->request->post('platform_telephone'));
        $platform_landline = tep_db_prepare_input(Yii::$app->request->post('platform_landline'));
        $price_settings = defined('PLATFORM_OWN_PRICE') && PLATFORM_OWN_PRICE == 'true';
        if ($price_settings) {
            $platform_use_own_prices = tep_db_prepare_input(Yii::$app->request->post('use_own_prices'));
            $platform_use_owner_prices = tep_db_prepare_input(Yii::$app->request->post('use_owner_prices'));
        }
        $platform_use_own_desc = tep_db_prepare_input(Yii::$app->request->post('use_own_descriptions'));
        $platform_use_owner_desc = tep_db_prepare_input(Yii::$app->request->post('use_owner_descriptions'));
        $is_default = false;
        if (Yii::$app->request->post('present_is_default')) {
            $is_default = Yii::$app->request->post('is_default', 0);
        }
        $status = (int) Yii::$app->request->post('status');
        if ($is_default) {
            $is_virtual = 0;
            $is_marketplace = 0;
            $is_default_contact = 0;
            $is_default_address = 0;
        } else {
            $is_virtual = (int) Yii::$app->request->post('is_virtual');
            $is_marketplace = (int) Yii::$app->request->post('is_marketplace');
            $is_default_contact = (int) Yii::$app->request->post('is_default_contact');
            $is_default_address = (int) Yii::$app->request->post('is_default_address');
        }
        $this->layout = false;
        $error = false;
        $message = '';
        $script = '';
        $delete_btn = '';
        $message_type = 'success';
        $entry_company = tep_db_prepare_input(Yii::$app->request->post('entry_company'));
        $entry_company_vat = tep_db_prepare_input(Yii::$app->request->post('entry_company_vat'));
        $entry_company_reg_number = tep_db_prepare_input(Yii::$app->request->post('entry_company_reg_number'));
        $entry_postcode = tep_db_prepare_input(Yii::$app->request->post('entry_postcode'));
        $entry_street_address = tep_db_prepare_input(Yii::$app->request->post('entry_street_address'));
        $entry_suburb = tep_db_prepare_input(Yii::$app->request->post('entry_suburb'));
        $entry_city = tep_db_prepare_input(Yii::$app->request->post('entry_city'));
        $entry_state = tep_db_prepare_input(Yii::$app->request->post('entry_state'));
        $entry_country_id = tep_db_prepare_input(Yii::$app->request->post('entry_country_id'));
        $address_book_ids = tep_db_prepare_input(Yii::$app->request->post('platforms_address_book_id'));
        $address_lat = tep_db_prepare_input(Yii::$app->request->post('lat'));
        $address_lng = tep_db_prepare_input(Yii::$app->request->post('lng'));
        $entry_zone_id = [];
        $entry_post_code_error = false;
        $entry_street_address_error = false;
        $entry_city_error = false;
        $entry_country_error = false;
        $entry_state_error = false;
        if ($is_default_address == 1) {
            $address_book_ids = [];
        }
        foreach ($address_book_ids as $address_book_key => $address_book_id) {
            $skip_address = false;
            /*if (strlen($entry_postcode[$address_book_key]) < ENTRY_POSTCODE_MIN_LENGTH) {
                            if ($address_book_id > 0) {
                                $error = true;
                                $entry_post_code_error = true;
                            }
                            $skipAddress = true;
                        }
            
                        if (strlen($entry_street_address[$address_book_key]) < ENTRY_STREET_ADDRESS_MIN_LENGTH) {
                            if ($address_book_id > 0) {
                                $error = true;
                                $entry_street_address_error = true;
                            }
                            $skipAddress = true;
                        }
            
                        if (strlen($entry_city[$address_book_key]) < ENTRY_CITY_MIN_LENGTH) {
                            if ($address_book_id > 0) {
                                $error = true;
                                $entry_city_error = true;
                            }
                            $skipAddress = true;
                        }
            
                        if ((int)$entry_country_id[$address_book_key] == 0) {
                            if ($address_book_id > 0) {
                                $error = true;
                                $entry_country_error = true;
                            }
                            $skipAddress = true;
                        }*/
            if ($address_book_id == 0 && $skip_address) {
                unset($address_book_ids[$address_book_key]);
                continue;
            }
            if (in_array(ACCOUNT_STATE, ['required', 'visible', 'required_register'])) {
                if ($entry_country_error == true) {
                    //$entry_state_error = true;
                } else {
                    $entry_zone_id[$address_book_key] = 0;
                    //$entry_state_error = false;
                    $check_query = tep_db_query('select count(*) as total from ' . TABLE_ZONES . " where zone_country_id = '" . (int) $entry_country_id[$address_book_key] . "'");
                    $check_value = tep_db_fetch_array($check_query);
                    $entry_state_has_zones = $check_value['total'] > 0;
                    if ($entry_state_has_zones == true) {
                        $zone_query = tep_db_query('select zone_id from ' . TABLE_ZONES . " where zone_country_id = '" . (int) $entry_country_id[$address_book_key] . "' and (zone_name like '" . tep_db_input($entry_state[$address_book_key]) . "' or zone_code like '" . tep_db_input($entry_state[$address_book_key]) . "')");
                        if (tep_db_num_rows($zone_query) == 1) {
                            $zone_values = tep_db_fetch_array($zone_query);
                            $entry_zone_id[$address_book_key] = $zone_values['zone_id'];
                        }
                        /*else {
                              $error = true;
                              $entry_state_error = true;
                          }*/
                    } else {
                        /*if ($entry_state[$address_book_key] == false) {
                              $error = true;
                              $entry_state_error = true;
                          }*/
                    }
                }
            }
        }
        $platform_code = '';
        $sattelite_platform_id = 0;
        if ($is_virtual == 1) {
            $platforms_open_hours_ids = [];
            $platforms_cut_off_times_ids = [];
            $platform_code = Yii::$app->request->post('platform_code', '');
            $sattelite_platform_id = (int) Yii::$app->request->post('sattelit_id');
        }
        $logo = Yii::$app->request->post('logo', '');
        $logo_upload = Yii::$app->request->post('logo_upload', '');
        $logo_remove = Yii::$app->request->post('image_delete', false);
        $platform = \common\models\Platforms::find_one($item_id);
        $logo = \common\helpers\Image::prepare_saving_image($platform->logo ?? null, $logo, $logo_upload, 'platforms', $logo_remove);
        $default_platform_id = (int) Yii::$app->request->post('default_platform_id');
        if ($error === false) {
            $pre_update_default_platform_id = \common\classes\platform::default_id();
            $sql_data_array = ['platform_owner' => $platform_owner, 'platform_name' => $platform_name, 'platform_url' => $platform_url, 'platform_url_secure' => $platform_url_secure, 'platform_prefix' => $platform_prefix, 'ssl_enabled' => $ssl_enabled, 'use_social_login' => $use_social_login, 'checkout_logged_customer' => $checkout_logged_customer, 'platform_please_login' => $platform_please_login, 'platform_email_address' => $platform_email_address, 'platform_email_from' => $platform_email_from, 'platform_email_extra' => $platform_email_extra, 'contact_us_email' => $contact_us_email, 'landing_contact_email' => $landing_contact_email, 'platform_telephone' => $platform_telephone, 'platform_landline' => $platform_landline, 'is_virtual' => $is_virtual, 'is_marketplace' => $is_marketplace, 'platform_code' => $platform_code, 'sattelite_platform_id' => $sattelite_platform_id, 'is_default_contact' => $is_default_contact, 'is_default_address' => $is_default_address, 'status' => $status, 'organization_site' => Yii::$app->request->post('organization_site', ''), 'organization_type' => Yii::$app->request->post('organization_type', ''), 'logo' => $logo, 'default_platform_id' => $default_platform_id];
            if ($is_marketplace || $is_virtual) {
                $default_platform = Platforms::find_one($default_platform_id);
                if (is_object($default_platform)) {
                    $sql_data_array['defined_languages'] = $default_platform->defined_languages;
                    $sql_data_array['defined_currencies'] = $default_platform->defined_currencies;
                    $sql_data_array['default_language'] = $default_platform->default_language;
                    $sql_data_array['default_currency'] = $default_platform->default_currency;
                }
            }
            if ($ext = \common\helpers\Acl::check_extension_allowed('BusinessToBusiness', 'allowed')) {
                $sql_data_array = array_merge($sql_data_array, $ext::save());
            }
            if ($is_default !== false) {
                $sql_data_array['is_default'] = $is_default;
                $sql_data_array['sort_order'] = 1;
            }
            $platform_updated = false;
            if ($ext = \common\helpers\Acl::check_extension_allowed('AdditionalPlatforms', 'allowed')) {
                $platform_updated = $item_id > 0;
                $item_id = $ext::save($item_id, [], [], $sql_data_array, $message);
            } else {
                $message = 'Item updated';
                $sql_data_array['last_modified'] = 'now()';
                tep_db_perform(TABLE_PLATFORMS, $sql_data_array, 'update', "platform_id = '" . $item_id . "'");
                $platform_updated = true;
            }
            $item_id = (int) $item_id;
            if ($is_default) {
                tep_db_query('UPDATE ' . TABLE_PLATFORMS . ' SET is_default=0 ' . "WHERE platform_id!='" . (int) $item_id . "'");
            }
            $pp_settings = Platforms_Settings::find_one($item_id);
            if (!$pp_settings) {
                $pp_settings = new Platforms_Settings();
                $pp_settings->platform_id = (int) $item_id;
            }
            if (!$is_virtual) {
                if ($price_settings) {
                    $pp_settings->use_own_prices = (int) $platform_use_own_prices;
                    if ($pp_settings->use_own_prices) {
                        $pp_settings->use_owner_prices = 0;
                    } else {
                        $pp_settings->use_owner_prices = (int) $platform_use_owner_prices;
                    }
                    if (!$status) {
                        Platforms_Settings::update_all(['use_owner_prices' => $pre_update_default_platform_id], ['use_owner_prices' => $item_id]);
                    }
                }
                $pp_settings->use_own_descriptions = (int) $platform_use_own_desc;
                if ($pp_settings->use_own_descriptions) {
                    $pp_settings->use_owner_descriptions = 0;
                } else {
                    $pp_settings->use_owner_descriptions = (int) $platform_use_owner_desc;
                }
                if ($is_default) {
                    $pp_settings->use_own_prices = 1;
                    $pp_settings->use_owner_prices = 0;
                    $pp_settings->use_own_descriptions = 1;
                    $pp_settings->use_owner_descriptions = 0;
                }
                $pp_settings->save();
            } else {
                Platforms_Settings::update_all(['use_owner_prices' => $pre_update_default_platform_id], ['use_owner_prices' => $item_id]);
                Platforms_Settings::update_all(['use_owner_descriptions' => $pre_update_default_platform_id], ['use_owner_descriptions' => $item_id]);
            }
            $google_tool = new \common\components\Google_Tools();
            $activeaddress_book_ids = [];
            foreach ($address_book_ids as $address_book_key => $address_book_id) {
                if ($entry_zone_id[$address_book_key] > 0) {
                    $entry_state[$address_book_key] = '';
                }
                $sql_data_array = ['entry_street_address' => $entry_street_address[$address_book_key], 'entry_postcode' => $entry_postcode[$address_book_key], 'entry_city' => $entry_city[$address_book_key], 'entry_country_id' => $entry_country_id[$address_book_key], 'entry_company_reg_number' => $entry_company_reg_number[$address_book_key], 'is_default' => 1, 'lat' => $address_lat[$address_book_key], 'lng' => $address_lng[$address_book_key]];
                $sql_data_array['entry_company'] = $entry_company[$address_book_key];
                $sql_data_array['entry_suburb'] = $entry_suburb[$address_book_key];
                $sql_data_array['entry_company_vat'] = $entry_company_vat[$address_book_key];
                if ($entry_zone_id[$address_book_key] > 0) {
                    $sql_data_array['entry_zone_id'] = $entry_zone_id[$address_book_key];
                    $sql_data_array['entry_state'] = '';
                } else {
                    $sql_data_array['entry_zone_id'] = '0';
                    $sql_data_array['entry_state'] = $entry_state[$address_book_key];
                }
                $address = $entry_postcode[$address_book_key] . ' ' . $entry_street_address[$address_book_key] . ' ' . $entry_city[$address_book_key] . ' ' . \common\helpers\Country::get_country_name($entry_country_id[$address_book_key]);
                $location = $google_tool->get_geocoding_location($address);
                if (is_array($location)) {
                    $sql_data_array['lat'] = $location['lat'];
                    $sql_data_array['lng'] = $location['lng'];
                }
                if ((int) $address_book_id > 0) {
                    tep_db_perform(TABLE_PLATFORMS_ADDRESS_BOOK, $sql_data_array, 'update', "platform_id = '" . (int) $item_id . "' and platforms_address_book_id = '" . (int) $address_book_id . "'");
                    $activeaddress_book_ids[] = $address_book_id;
                } else {
                    tep_db_perform(TABLE_PLATFORMS_ADDRESS_BOOK, array_merge($sql_data_array, ['platform_id' => $item_id]));
                    $new_customers_address_id = tep_db_insert_id();
                    $activeaddress_book_ids[] = $new_customers_address_id;
                }
            }
            if (count($activeaddress_book_ids) > 0) {
                tep_db_query('delete from ' . TABLE_PLATFORMS_ADDRESS_BOOK . " where platform_id = '" . (int) $item_id . "' and platforms_address_book_id NOT IN (" . implode(', ', $activeaddress_book_ids) . ')');
            }
            if (Yii::$app->request->post('platform_urls_present', 0)) {
                $platform_urls = tep_db_prepare_input(Yii::$app->request->post('platform_urls', []));
                if (!is_array($platform_urls)) {
                    $platform_urls = [];
                }
                $_valid_url_ids = [];
                foreach ($platform_urls as $platform_url) {
                    $platform_url_id = (int) Array_Helper::get_value($platform_url, 'platform_url_id');
                    $platform_url['url'] = preg_replace('#^https?://#i', '', $platform_url['url'] ?? null);
                    $platform_url['url'] = rtrim($platform_url['url'], '/') . '/';
                    if ($platform_url['url'] == '/') {
                        continue;
                    }
                    $url_data = ['url_type' => $platform_url['url_type'], 'status' => $platform_url['status'] ? 1 : 0, 'url' => $platform_url['url'], 'ssl_enabled' => $platform_url['ssl_enabled']];
                    if ($platform_url_id) {
                        $check_valid = tep_db_fetch_array(tep_db_query('SELECT COUNT(*) AS c ' . 'FROM ' . TABLE_PLATFORMS_URL . ' ' . "WHERE platform_url_id='" . (int) $platform_url_id . "' AND platform_id='" . (int) $item_id . "' "));
                        if ($check_valid['c'] == 0) {
                            $platform_url_id = 0;
                        }
                    }
                    if ($platform_url_id) {
                        tep_db_perform(TABLE_PLATFORMS_URL, $url_data, 'update', "platform_url_id='" . (int) $platform_url_id . "'");
                    } else {
                        $url_data['platform_id'] = (int) $item_id;
                        tep_db_perform(TABLE_PLATFORMS_URL, $url_data);
                        $platform_url_id = intval(tep_db_insert_id());
                    }
                    $_valid_url_ids[] = $platform_url_id;
                }
                tep_db_query('DELETE FROM ' . TABLE_PLATFORMS_URL . ' ' . "WHERE platform_id='" . (int) $item_id . "' " . (count($_valid_url_ids) == 0 ? '' : "AND platforms_url_id NOT IN('" . implode("','", $_valid_url_ids) . "') "));
            }
            if (!$platform_updated) {
                \common\helpers\Configuration::copy_platform_module_setting($item_id);
                \common\helpers\Mail::copy_platform_emails($item_id);
            }
            if ((int) $item_id > 0) {
                tep_db_query('INSERT IGNORE INTO ' . TABLE_PLATFORMS_CATEGORIES . ' (platform_id, categories_id) ' . "VALUES('" . (int) $item_id . "', 0)");
            }
            $warehouse_status = Yii::$app->request->post('warehouse_status', []);
            $warehouses_query = \common\models\Warehouses::find(['status' => 1])->as_array();
            foreach ($warehouses_query->each() as $warehouses) {
                if (isset($warehouse_status[$warehouses['warehouse_id']]) && $warehouse_status[$warehouses['warehouse_id']] == 1) {
                    $status = 1;
                } else {
                    $status = 0;
                }
                $object = \common\models\Warehouses_Platforms::find_one(['warehouse_id' => $warehouses['warehouse_id'], 'platform_id' => (int) $item_id]);
                if (!is_object($object)) {
                    $object = new \common\models\Warehouses_Platforms();
                    $object->sort_order = 0;
                    $object->warehouse_id = $warehouses['warehouse_id'];
                    $object->platform_id = (int) $item_id;
                }
                $object->status = $status;
                $object->save();
            }
            foreach (\common\helpers\Hooks::get_list('platforms/after-save') as $filename) {
                include $filename;
            }
            if ((int) $item_id > 0) {
                $platform_id = (int) $item_id;
                tep_db_query('INSERT IGNORE INTO `platforms_configuration` (`configuration_title`, `configuration_key`, `configuration_value`,' . ' `configuration_description`, `configuration_group_id`, `sort_order`, `last_modified`, `date_added`,' . ' `use_function`, `set_function`, `platform_id`)' . " VALUES ('Display billing address', 'DISPLAY_BILLING_ADDRESS', 'True', 'Display billing address on checkout and user account?'," . " '1', '0', NOW(), NOW(), NULL, 'tep_cfg_select_option(array(\\'True\\', \\'False\\'), ', '{$platform_id}');");
            }
        }
        if ($error === true) {
            $message_type = 'warning';
            if ($message == '') {
                $message = WARN_UNKNOWN_ERROR;
            }
        }
        echo Message_Popup::widget(['messageType' => $message_type, 'message' => $message]);
        echo '<script>location.replace("' . Yii::$app->url_manager->create_url(['platforms/edit', 'id' => $item_id]) . '");</script>';
        die;
        return $this->action_edit();
    }
    public function action_confirmitemdelete()
    {
        \common\helpers\Translation::init('admin/properties');
        $this->layout = false;
        $item_id = (int) Yii::$app->request->post('item_id');
        $message = $name = $title = '';
        $heading = [];
        $contents = [];
        $parent_id = 0;
        $groups_query = tep_db_query('select * from ' . TABLE_PLATFORMS . " where platform_id = '" . (int) $item_id . "'");
        $groups = tep_db_fetch_array($groups_query);
        $p_info = new \Object_Info($groups);
        echo tep_draw_form('item_delete', FILENAME_INVENTORY, \common\helpers\Output::get_all_get_params(['action']) . 'action=update', 'post', 'id="item_delete" onSubmit="return deleteItem();"');
        echo '<div class="or_box_head">' . TEXT_INFO_HEADING_DELETE_PLATFORM . '</div>';
        echo '<div class="col_desc">' . TEXT_INFO_DELETE_PLATFORM_INTRO . '</div>';
        echo '<div class="col_desc">' . $p_info->platform_name . '</div>';
        ?>
        <p class="btn-toolbar">
            <?php 
        echo '<input type="submit" class="btn btn-primary" value="' . IMAGE_DELETE . '" >';
        echo '<input type="button" class="btn btn-cancel" value="' . IMAGE_CANCEL . '" onClick="return resetStatement()">';
        echo tep_draw_hidden_field('item_id', $item_id);
        ?>
        </p>
        </form>
    <?php 
    }
    public function action_itemdelete()
    {
        $this->layout = false;
        $item_id = (int) Yii::$app->request->post('item_id');
        $message_type = 'success';
        $message = TEXT_INFO_DELETED;
        $check_is_default = tep_db_fetch_array(tep_db_query('SELECT COUNT(*) AS c FROM ' . TABLE_PLATFORMS . " WHERE is_default=1 AND  platform_id = '" . (int) $item_id . "'"));
        if ($check_is_default['c']) {
        } else {
            \common\components\Categories_Cache::get_cpc()::invalidate_platforms((int) $item_id);
            ///2do event
            /** @var \common\extensions\InvoiceNumberFormat\InvoiceNumberFormat $serverExt */
            if ($server_ext = \common\helpers\Acl::check_extension_allowed('InvoiceNumberFormat', 'allowed')) {
                $server_ext::on_platform_delete((int) $item_id);
            }
            //SELECT * FROM `COLUMNS` WHERE `TABLE_SCHEMA`='vlad_tlnew' and `COLUMN_NAME` like 'platform%_id'
            //114  'admin_platforms', 'affiliate_affiliate', 'banners_languages', 'banners_languages_backup', 'banners_new_backup', 'banners_to_platform', 'blog_post_to_platforms', 'catalog_pages', 'categories_images', 'categories_platform_settings',  'categories_product_to_template', 'categories_to_template', 'cloud_services', 'customer_modules', 'customer_testimonials', 'customers', 'customers_basket', 'customers_quote', 'customers_sample', 'departments_external_platforms', 'dropshipping_ships', 'ebay_profile', 'email_templates_texts', 'email_templates_to_design_template', 'ep_holbi_soap_server_kv_storage', 'freeze_orders_products_allocate', 'gapi_search', 'google_settings', 'googlezone', 'image_cache_keys', 'image_copy_reference', 'information', 'menu_items', 'meta_tags', 'modules_groups_settings', 'modules_labels', 'newsletter_passed', 'orders', 'orders_products_allocate', 'orders_status_to_design_template', 'page_styles', 'payment_fee', 'payment_offline', 'paypal_cron', 'paypal_seller_info', 'paypalipn_txn', 'plain_products_name_to_products', 'platform_currencies_margin',  'platform_inventory_control', 'platform_stock_control', 'platforms', 'platforms_address_book', 'platforms_address_book', 'platforms_api', 'platforms_categories', 'platforms_configuration', 'platforms_countries', 'platforms_cut_off_times', 'platforms_cut_off_times', 'platforms_formats', 'platforms_holidays', 'platforms_holidays', 'platforms_locations', 'platforms_locations', 'platforms_open_hours', 'platforms_open_hours', 'platforms_products', 'platforms_settings', 'platforms_to_themes', 'platforms_url', 'platforms_url', 'platforms_watermark', 'platforms_zone_countries', 'product_to_template', 'products_description', 'products_global_sort', 'products_notify', 'promotions_to_platform', 'push_configuration', 'push_subscribers', 'quotation',  'quote_orders', 'recover_cart_config', 'sample_orders', 'search_plus', 'search_plus_stats', 'seo_delivery_location', 'seo_delivery_location_text_template', 'seo_redirect', 'seo_redirects_named', 'ship_options', 'ship_zones', 'shipping_carrier_selection', 'shipping_fee', 'sms_defaults', 'sms_templates_texts',  'socials', 'subscribers', 'subscribers_lists', 'subscribers_lists_to_tags', 'subscription', 'support_system_info', 'tmp_orders', 'visibility_area', 'warehouse_inventory_control', 'warehouse_stock_control', 'warehouses_selection_priority', 'warehouses_to_platforms', 'whos_online', 'zone_table', 'zone_table_checkout_note', 'zones_to_ship_zones'
            $delete_in_tables = [TABLE_PLATFORMS, TABLE_PLATFORMS_ADDRESS_BOOK, TABLE_PLATFORMS_OPEN_HOURS, TABLE_PLATFORMS_TO_THEMES, TABLE_PLATFORMS_CATEGORIES, TABLE_PLATFORMS_PRODUCTS, TABLE_INFORMATION, TABLE_BANNERS_TO_PLATFORM, TABLE_BANNERS_LANGUAGES, TABLE_PLATFORMS_CONFIGURATION, TABLE_PLATFORMS_CUT_OFF_TIMES, TABLE_PLATFORM_FORMATS, TABLE_PLATFORMS_HOLIDAYS, TABLE_PLATFORMS_WATERMARK, TABLE_META_TAGS];
            foreach ($delete_in_tables as $tbl) {
                if (\Yii::$app->db->schema->get_table_schema($tbl)) {
                    tep_db_query('delete from ' . $tbl . " where platform_id = '" . (int) $item_id . "'");
                }
            }
            //find any unassigned categories and products
            $sales_channel_ids = \common\models\Platforms::get_platforms_by_type('physical')->select(['platform_id'])->column();
            if (count($sales_channel_ids) == 1) {
                //$default_sale_channel_id = \common\models\Platforms::find()->where(['is_default' => 1])->select('platform_id')->scalar();
                $default_sale_channel_id = $sales_channel_ids[0];
                $orphan_categories = \common\models\Categories::find()->alias('c')->join('left join', \common\models\Platforms_Categories::table_name() . ' pc', "pc.categories_id = c.categories_id AND pc.platform_id IN('" . implode("','", $sales_channel_ids) . "')")->where(['pc.categories_id' => null])->select('c.categories_id')->distinct()->column();
                foreach ($orphan_categories as $orphan_category_id) {
                    Yii::$app->get_db()->create_command()->insert(\common\models\Platforms_Categories::table_name(), ['categories_id' => $orphan_category_id, 'platform_id' => $default_sale_channel_id])->execute();
                }
                $orphan_products = \common\models\Products::find()->alias('p')->join('left join', \common\models\Platforms_Products::table_name() . ' pp', "pp.products_id = p.products_id AND pp.platform_id IN('" . implode("','", $sales_channel_ids) . "')")->where(['pp.products_id' => null])->select('p.products_id')->distinct()->column();
                foreach ($orphan_products as $orphan_product_id) {
                    Yii::$app->get_db()->create_command()->insert(\common\models\Platforms_Products::table_name(), ['products_id' => $orphan_product_id, 'platform_id' => $default_sale_channel_id])->execute();
                }
            }
            Platforms_Settings::update_all(['use_owner_prices' => \common\classes\platform::default_id()], ['use_owner_prices' => $item_id]);
            Platforms_Settings::update_all(['use_owner_descriptions' => \common\classes\platform::default_id()], ['use_owner_descriptions' => $item_id]);
            foreach (\common\helpers\Hooks::get_list('platforms/after-delete') as $filename) {
                include $filename;
            }
            \common\classes\Images::cache_key_invalidate_by_platform_id((int) $item_id);
            //\common\models\EmailTemplatesToDesignTemplate::findAll(['platform_id' => (int)$item_id])->delete();
            \common\models\Email_Templates_To_Design_Template::delete_all(['platform_id' => (int) $item_id]);
        }
        echo Message_Popup::widget(['messageType' => $message_type, 'message' => $message]);
        ?>

        <p class="btn-toolbar">
            <?php 
        echo '<input type="button" class="btn btn-primary" value="' . IMAGE_CANCEL . '" onClick="return resetStatement()">';
        ?>
        </p>
    <?php 
    }
    public function action_confirm_item_copy()
    {
        \common\helpers\Translation::init('admin/properties');
        $this->layout = false;
        $item_id = (int) Yii::$app->request->post('item_id');
        $message = $name = $title = '';
        $heading = [];
        $contents = [];
        $parent_id = 0;
        $groups_query = tep_db_query('select * from ' . TABLE_PLATFORMS . " where platform_id = '" . (int) $item_id . "'");
        $groups = tep_db_fetch_array($groups_query);
        $p_info = new \Object_Info($groups);
        echo tep_draw_form('item_copy', FILENAME_INVENTORY, \common\helpers\Output::get_all_get_params(['action']) . 'action=update', 'post', 'id="item_copy" onSubmit="return copyItem();"');
        echo '<div class="or_box_head">' . TEXT_INFO_HEADING_COPY_PLATFORM . '</div>';
        echo '<div class="col_desc">' . TEXT_INFO_COPY_PLATFORM_INTRO . '</div>';
        echo '<div class="col_desc">' . $p_info->platform_name . '</div>';
        ?>
        <p class="btn-toolbar">
            <?php 
        echo '<input type="submit" class="btn btn-primary" value="' . IMAGE_COPY . '" >';
        echo '<input type="button" class="btn btn-cancel" value="' . IMAGE_CANCEL . '" onClick="return resetStatement()">';
        echo tep_draw_hidden_field('item_id', $item_id);
        ?>
        </p>
        </form>
    <?php 
    }
    public function action_item_copy()
    {
        $item_id = (int) Yii::$app->request->post('item_id');
        $platforms_query = tep_db_query('select * from ' . TABLE_PLATFORMS . " where platform_id = '" . (int) $item_id . "'");
        $platforms = tep_db_fetch_array($platforms_query);
        unset($platforms['platform_id']);
        $platforms['is_default'] = 0;
        $platforms['date_added'] = 'now()';
        tep_db_perform(TABLE_PLATFORMS, $platforms);
        $new_item_id = tep_db_insert_id();
        $platforms_address_query = tep_db_query('select * from ' . TABLE_PLATFORMS_ADDRESS_BOOK . " where platform_id = '" . (int) $item_id . "'");
        while ($platforms_address = tep_db_fetch_array($platforms_address_query)) {
            unset($platforms_address['platforms_address_book_id']);
            $platforms_address['platform_id'] = $new_item_id;
            tep_db_perform(TABLE_PLATFORMS_ADDRESS_BOOK, $platforms_address);
        }
        $platforms_categories_query = tep_db_query('select * from ' . TABLE_PLATFORMS_CATEGORIES . " where platform_id = '" . (int) $item_id . "'");
        while ($platforms_categories = tep_db_fetch_array($platforms_categories_query)) {
            $platforms_categories['platform_id'] = $new_item_id;
            tep_db_perform(TABLE_PLATFORMS_CATEGORIES, $platforms_categories);
        }
        $platforms_configuration_query = tep_db_query('select * from ' . TABLE_PLATFORMS_CONFIGURATION . " where platform_id = '" . (int) $item_id . "'");
        while ($platforms_configuration = tep_db_fetch_array($platforms_configuration_query)) {
            unset($platforms_configuration['configuration_id']);
            $platforms_configuration['platform_id'] = $new_item_id;
            tep_db_perform(TABLE_PLATFORMS_CONFIGURATION, $platforms_configuration);
        }
        tep_db_query('DELETE FROM ' . TABLE_VISIBILITY_AREA . " WHERE platform_id='" . (int) $new_item_id . "'");
        $get_data_r = tep_db_query('SELECT * FROM ' . TABLE_VISIBILITY_AREA . " WHERE platform_id='" . (int) $item_id . "' ");
        if (tep_db_num_rows($get_data_r) > 0) {
            while ($data = tep_db_fetch_array($get_data_r)) {
                $data['platform_id'] = (int) $new_item_id;
                tep_db_perform(TABLE_VISIBILITY_AREA, $data);
            }
        }
        $platforms_cut_off_times_query = tep_db_query('select * from ' . TABLE_PLATFORMS_CUT_OFF_TIMES . " where platform_id = '" . (int) $item_id . "'");
        while ($platforms_cut_off_times = tep_db_fetch_array($platforms_cut_off_times_query)) {
            unset($platforms_cut_off_times['platforms_cut_off_times_id']);
            $platforms_cut_off_times['platform_id'] = $new_item_id;
            tep_db_perform(TABLE_PLATFORMS_CUT_OFF_TIMES, $platforms_cut_off_times);
        }
        $platforms_formats_query = tep_db_query('select * from ' . TABLE_PLATFORM_FORMATS . " where platform_id = '" . (int) $item_id . "'");
        while ($platforms_formats = tep_db_fetch_array($platforms_formats_query)) {
            unset($platforms_formats['paltform_formats_id']);
            $platforms_formats['platform_id'] = $new_item_id;
            tep_db_perform(TABLE_PLATFORM_FORMATS, $platforms_formats);
        }
        $platforms_holidays_query = tep_db_query('select * from ' . TABLE_PLATFORMS_HOLIDAYS . " where platform_id = '" . (int) $item_id . "'");
        while ($platforms_holidays = tep_db_fetch_array($platforms_holidays_query)) {
            unset($platforms_holidays['platforms_holidays_id']);
            $platforms_holidays['platform_id'] = $new_item_id;
            tep_db_perform(TABLE_PLATFORMS_HOLIDAYS, $platforms_holidays);
        }
        $platforms_open_hours_query = tep_db_query('select * from ' . TABLE_PLATFORMS_OPEN_HOURS . " where platform_id = '" . (int) $item_id . "'");
        while ($platforms_open_hours = tep_db_fetch_array($platforms_open_hours_query)) {
            unset($platforms_open_hours['platforms_open_hours_id']);
            $platforms_open_hours['platform_id'] = $new_item_id;
            tep_db_perform(TABLE_PLATFORMS_OPEN_HOURS, $platforms_open_hours);
        }
        $platforms_products_query = tep_db_query('select * from ' . TABLE_PLATFORMS_PRODUCTS . " where platform_id = '" . (int) $item_id . "'");
        while ($platforms_products = tep_db_fetch_array($platforms_products_query)) {
            $platforms_products['platform_id'] = $new_item_id;
            tep_db_perform(TABLE_PLATFORMS_PRODUCTS, $platforms_products);
        }
        $platforms_to_themes_query = tep_db_query('select * from ' . TABLE_PLATFORMS_TO_THEMES . " where platform_id = '" . (int) $item_id . "'");
        while ($platforms_to_themes = tep_db_fetch_array($platforms_to_themes_query)) {
            $platforms_to_themes['platform_id'] = $new_item_id;
            tep_db_perform(TABLE_PLATFORMS_TO_THEMES, $platforms_to_themes);
        }
        $platforms_watermark_query = tep_db_query('select * from ' . TABLE_PLATFORMS_WATERMARK . " where platform_id = '" . (int) $item_id . "'");
        while ($platforms_watermark = tep_db_fetch_array($platforms_watermark_query)) {
            $platforms_watermark['platform_id'] = $new_item_id;
            tep_db_perform(TABLE_PLATFORMS_WATERMARK, $platforms_watermark);
        }
        $information_query = tep_db_query('select * from ' . TABLE_INFORMATION . " where platform_id = '" . (int) $item_id . "'");
        while ($information = tep_db_fetch_array($information_query)) {
            $information['platform_id'] = $new_item_id;
            tep_db_perform(TABLE_INFORMATION, $information);
        }
        $meta_tags_query = tep_db_query('select * from ' . TABLE_META_TAGS . " where platform_id = '" . (int) $item_id . "'");
        while ($meta_tags = tep_db_fetch_array($meta_tags_query)) {
            $meta_tags['platform_id'] = $new_item_id;
            tep_db_perform(TABLE_META_TAGS, $meta_tags);
        }
        $banners_to_platform_query = tep_db_query('select * from ' . TABLE_BANNERS_TO_PLATFORM . " where platform_id = '" . (int) $item_id . "'");
        while ($banners_to_platform = tep_db_fetch_array($banners_to_platform_query)) {
            $banners_to_platform['platform_id'] = $new_item_id;
            tep_db_perform(TABLE_BANNERS_TO_PLATFORM, $banners_to_platform);
        }
        $banners_languages_query = tep_db_query('select * from ' . TABLE_BANNERS_LANGUAGES . " where platform_id = '" . (int) $item_id . "'");
        while ($banners_languages = tep_db_fetch_array($banners_languages_query)) {
            unset($banners_languages['blang_id']);
            $banners_languages['platform_id'] = $new_item_id;
            tep_db_perform(TABLE_BANNERS_LANGUAGES, $banners_languages);
        }
        \common\classes\Images::cache_key_invalidate_by_platform_id((int) $new_item_id);
    }
    public function action_theme_banners()
    {
        $theme_name = Yii::$app->request->get('theme_name');
        $languages_id = Yii::$app->settings->get('languages_id');
        $platform_id = Yii::$app->request->get('platform_id');
        $groups = \common\models\Design_Boxes_Settings::find()->select(['setting_value'])->distinct()->where(['theme_name' => $theme_name, 'setting_name' => 'banners_group'])->as_array()->all();
        $banner_groups = [];
        foreach ($groups as $group) {
            $banner_groups[] = $group['setting_value'];
        }
        $banners = \common\models\Banners::find()->alias('b')->select(['b.banners_id', 'bg.banners_group', 'bl.banners_title'])->left_join(\common\models\Banners_Languages::table_name() . ' bl', 'b.banners_id = bl.banners_id and bl.language_id = ' . $languages_id)->left_join(\common\models\Banners_Groups::table_name() . ' bg', 'bg.id = b.group_id')->where(['in', 'b.group_id', $banner_groups])->or_where(['in', 'bg.banners_group', $banner_groups])->order_by('bg.banners_group')->as_array()->all();
        $assigned_banners = \common\models\Banners_To_Platform::find()->where(['platform_id' => $platform_id])->as_array()->all();
        $assigned_banners_id = [];
        foreach ($assigned_banners as $banner) {
            $assigned_banners_id[] = $banner['banners_id'];
        }
        $theme_banners_ids = [];
        $path = DIR_FS_CATALOG . 'themes' . DIRECTORY_SEPARATOR . $theme_name . DIRECTORY_SEPARATOR;
        if (is_file($path . 'banners-ids.json')) {
            $theme_banners_ids = json_decode(file_get_contents($path . 'banners-ids.json'));
        }
        foreach ($banners as $key => $banner) {
            if (in_array($banner['banners_id'], $assigned_banners_id)) {
                $banners[$key]['assigned'] = true;
            }
            if (in_array($banner['banners_id'], $theme_banners_ids)) {
                $banners[$key]['theme_banner'] = true;
            }
        }
        return json_encode($banners);
    }
    public function action_assign_banners()
    {
        $post = Yii::$app->request->post();
        if (!is_array($post['banners'] ?? null)) {
            return 'done';
        }
        foreach ($post['banners'] as $banner) {
            if ($banner['assigned'] == 1) {
                if (!\common\models\Banners_To_Platform::find_one(['banners_id' => (int) $banner['id'], 'platform_id' => (int) $post['platform_id']])) {
                    $banners_to_platform = new \common\models\Banners_To_Platform();
                    $banners_to_platform->banners_id = (int) $banner['id'];
                    $banners_to_platform->platform_id = (int) $post['platform_id'];
                    $banners_to_platform->save();
                }
            } else {
                \common\models\Banners_To_Platform::delete_all(['banners_id' => (int) $banner['id'], 'platform_id' => (int) $post['platform_id']]);
            }
        }
        return 'done';
    }
    public function action_edit_catalog()
    {
        \common\helpers\Translation::init('admin/platforms');
        $platform_id = (int) Yii::$app->request->get('id');
        $this->layout = false;
        $assigned = $this->get_assigned_catalog($platform_id, true);
        $tree_init_data = $this->load_tree_slice($platform_id, 0);
        foreach ($tree_init_data as $_idx => $_data) {
            if (isset($assigned[$_data['key']])) {
                $tree_init_data[$_idx]['selected'] = true;
            }
        }
        $selected_data = json_encode($assigned);
        return $this->render('edit-catalog.tpl', ['selected_data' => $selected_data, 'tree_data' => $tree_init_data, 'tree_server_url' => Yii::$app->url_manager->create_url(['platforms/load-tree', 'platform_id' => $platform_id]), 'tree_server_save_url' => Yii::$app->url_manager->create_url(['platforms/update-catalog-selection', 'platform_id' => $platform_id])]);
    }
    private function get_assigned_catalog($platform_id, $validate = false)
    {
        return \common\helpers\Categories::get_assigned_catalog($platform_id, $validate);
    }
    private function load_tree_slice($platform_id, $category_id)
    {
        return \common\helpers\Categories::load_tree_slice($platform_id, $category_id);
    }
    private function tep_get_category_children(&$children, $platform_id, $categories_id)
    {
        if (!is_array($children)) {
            $children = [];
        }
        foreach ($this->load_tree_slice($platform_id, $categories_id) as $item) {
            $key = $item['key'];
            $children[] = $key;
            if ($item['folder'] ?? null) {
                $this->tep_get_category_children($children, $platform_id, intval(substr($item['key'], 1)));
            }
        }
    }
    public function action_load_tree()
    {
        \common\helpers\Translation::init('admin/platforms');
        $this->layout = false;
        $platform_id = Yii::$app->request->get('platform_id');
        $do = Yii::$app->request->post('do', '');
        $response_data = [];
        if ($do == 'missing_lazy') {
            $category_id = Yii::$app->request->post('id');
            $selected = Yii::$app->request->post('selected');
            $req_selected_data = tep_db_prepare_input(Yii::$app->request->post('selected_data'));
            $selected_data = json_decode($req_selected_data, true);
            if (!is_array($selected_data)) {
                $selected_data = json_decode($selected_data, true);
            }
            if (substr($category_id, 0, 1) == 'c') {
                $category_id = intval(substr($category_id, 1));
            }
            $response_data['tree_data'] = $this->load_tree_slice($platform_id, $category_id);
            foreach ($response_data['tree_data'] as $_idx => $_data) {
                $response_data['tree_data'][$_idx]['selected'] = isset($selected_data[$_data['key']]);
            }
            $response_data = $response_data['tree_data'];
        }
        if ($do == 'update_selected') {
            $id = Yii::$app->request->post('id');
            $selected = Yii::$app->request->post('selected');
            $select_children = Yii::$app->request->post('select_children');
            $req_selected_data = tep_db_prepare_input(Yii::$app->request->post('selected_data'));
            $selected_data = json_decode($req_selected_data, true);
            if (!is_array($selected_data)) {
                $selected_data = json_decode($selected_data, true);
            }
            if (substr($id, 0, 1) == 'p') {
                list($ppid, $cat_id) = explode('_', $id, 2);
                if ($selected) {
                    // check parent categories
                    $parent_ids = [(int) $cat_id];
                    \common\helpers\Categories::get_parent_categories($parent_ids, $parent_ids[0], false);
                    foreach ($parent_ids as $parent_id) {
                        if (!isset($selected_data['c' . (int) $parent_id])) {
                            $response_data['update_selection']['c' . (int) $parent_id] = true;
                            $selected_data['c' . (int) $parent_id] = 'c' . (int) $parent_id;
                        }
                    }
                    if (!isset($selected_data[$id])) {
                        $response_data['update_selection'][$id] = true;
                        $selected_data[$id] = $id;
                    }
                } else if (isset($selected_data[$id])) {
                    $response_data['update_selection'][$id] = false;
                    unset($selected_data[$id]);
                }
            } elseif (substr($id, 0, 1) == 'c') {
                $cat_id = (int) substr($id, 1);
                if ($selected) {
                    $parent_ids = [(int) $cat_id];
                    \common\helpers\Categories::get_parent_categories($parent_ids, $parent_ids[0], false);
                    foreach ($parent_ids as $parent_id) {
                        if (!isset($selected_data['c' . (int) $parent_id])) {
                            $response_data['update_selection']['c' . (int) $parent_id] = true;
                            $selected_data['c' . (int) $parent_id] = 'c' . (int) $parent_id;
                        }
                    }
                    if ($select_children) {
                        $children = [];
                        $this->tep_get_category_children($children, $platform_id, $cat_id);
                        foreach ($children as $child_key) {
                            if (!isset($selected_data[$child_key])) {
                                $response_data['update_selection'][$child_key] = true;
                                $selected_data[$child_key] = $child_key;
                            }
                        }
                    }
                    if (!isset($selected_data[$id])) {
                        $response_data['update_selection'][$id] = true;
                        $selected_data[$id] = $id;
                    }
                } else {
                    $children = [];
                    $this->tep_get_category_children($children, $platform_id, $cat_id);
                    foreach ($children as $child_key) {
                        if (isset($selected_data[$child_key])) {
                            $response_data['update_selection'][$child_key] = false;
                            unset($selected_data[$child_key]);
                        }
                    }
                    if (isset($selected_data[$id])) {
                        $response_data['update_selection'][$id] = false;
                        unset($selected_data[$id]);
                    }
                }
            }
            $response_data['selected_data'] = $selected_data;
        }
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        Yii::$app->response->data = $response_data;
    }
    public function action_update_catalog_selection()
    {
        \common\helpers\Translation::init('admin/platforms');
        $this->layout = false;
        $platform_id = Yii::$app->request->get('platform_id');
        $req_selected_data = tep_db_prepare_input(Yii::$app->request->post('selected_data'));
        $selected_data = json_decode($req_selected_data, true);
        if (!is_array($selected_data)) {
            $selected_data = json_decode($selected_data, true);
        }
        if (!isset($selected_data['c0'])) {
            $selected_data['c0'] = 'c0';
        }
        $assigned = $this->get_assigned_catalog($platform_id);
        $assigned_products = [];
        foreach ($assigned as $assigned_key) {
            if (substr($assigned_key, 0, 1) == 'p') {
                $pid = intval(substr($assigned_key, 1));
                $assigned_products[$pid] = $pid;
                unset($assigned[$assigned_key]);
            }
        }
        if (is_array($selected_data)) {
            $selected_products = [];
            foreach ($selected_data as $selection) {
                if (substr($selection, 0, 1) == 'p') {
                    $pid = intval(substr($selection, 1));
                    $selected_products[$pid] = $pid;
                    continue;
                }
                if (isset($assigned[$selection])) {
                    unset($assigned[$selection]);
                } else if (substr($selection, 0, 1) == 'c') {
                    $cat_id = (int) substr($selection, 1);
                    tep_db_perform(TABLE_PLATFORMS_CATEGORIES, ['platform_id' => $platform_id, 'categories_id' => $cat_id]);
                    unset($assigned[$selection]);
                }
            }
            foreach ($selected_products as $pid) {
                if (isset($assigned_products[$pid])) {
                    unset($assigned_products[$pid]);
                } else {
                    tep_db_perform(TABLE_PLATFORMS_PRODUCTS, ['platform_id' => $platform_id, 'products_id' => $pid]);
                }
            }
        }
        foreach ($assigned as $clean_key) {
            if (substr($clean_key, 0, 1) == 'c') {
                $cat_id = (int) substr($clean_key, 1);
                if ($cat_id == 0) {
                    continue;
                }
                tep_db_query('DELETE FROM ' . TABLE_PLATFORMS_CATEGORIES . ' ' . "WHERE platform_id ='" . $platform_id . "' AND categories_id = '" . $cat_id . "' ");
                unset($assigned[$clean_key]);
            }
        }
        if (count($assigned_products) > 1000) {
            foreach ($assigned_products as $assigned_product_id) {
                tep_db_query('DELETE FROM ' . TABLE_PLATFORMS_PRODUCTS . ' ' . "WHERE platform_id ='" . $platform_id . "' AND products_id = '" . $assigned_product_id . "' ");
            }
        } elseif (count($assigned_products) > 0) {
            tep_db_query('DELETE FROM ' . TABLE_PLATFORMS_PRODUCTS . ' ' . "WHERE platform_id ='" . $platform_id . "' AND products_id IN ('" . implode("','", $assigned_products) . "') ");
        }
        \common\components\Categories_Cache::get_cpc()::invalidate_all();
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        Yii::$app->response->data = ['status' => 'ok'];
    }
    public function action_define_formats()
    {
        \common\helpers\Translation::init('admin/languages');
        \common\helpers\Translation::init('admin/texts');
        $no_redirect = Yii::$app->request->get('no_redirect', 0);
        exec('locale -a', $output);
        if (Yii::$app->request->is_post) {
            //echo '<pre>';print_r($_POST);die;
            $id = Yii::$app->request->post('id', 0);
            if ($id) {
                if (is_array($_POST['configuration_key']) && count($_POST['configuration_key']) > 0) {
                    foreach ($_POST['configuration_key'] as $lang => $data) {
                        tep_db_query('delete from ' . TABLE_PLATFORM_FORMATS . " where platform_id='" . (int) $id . "' and language_id = '" . (int) $lang . "'");
                        foreach ($data as $key => $value) {
                            if (!tep_not_null($value) || !isset($_POST['configuration_value'][$lang][$key]) || !tep_not_null($_POST['configuration_value'][$lang][$key])) {
                                continue;
                            }
                            tep_db_query('insert into ' . TABLE_PLATFORM_FORMATS . " (configuration_key, configuration_value, platform_id, language_id) values ('" . tep_db_input($value) . "', '" . tep_db_input($_POST['configuration_value'][$lang][$key]) . "', '" . (int) $id . "', '" . (int) $lang . "')");
                        }
                    }
                }
            }
            $message_type = 'success';
            $message = TEXT_MESSEAGE_SUCCESS;
            echo Message_Popup::widget(['messageType' => $message_type, 'message' => $message]);
            if ($no_redirect) {
                return '';
            }
            return $this->action_edit();
        } else {
            $id = Yii::$app->request->get('id', 0);
        }
        $l_list = [];
        if (is_array($output) && class_exists('\ResourceBundle')) {
            $all_locales = \Resource_Bundle::get_locales('');
            foreach ($output as $line) {
                if (tep_not_null($line)) {
                    $ex = explode('.', $line);
                    if (in_array($ex[0], $all_locales)) {
                        array_push($l_list, ['id' => $ex[0], 'text' => $ex[0]]);
                    }
                }
            }
        }
        if (count($l_list) == 0) {
            $l_list[] = ['id' => 'en_EN', 'text' => 'en_EN'];
        }
        $l_formats = [];
        $formats_query = tep_db_query('select * from ' . TABLE_LANGUAGES_FORMATS . ' where 1');
        if (tep_db_num_rows($formats_query)) {
            while ($row = tep_db_fetch_array($formats_query)) {
                $l_formats[] = $row;
            }
        }
        $p_formats = [];
        $formats_query = tep_db_query('select * from ' . TABLE_PLATFORM_FORMATS . " where platform_id = '" . (int) $id . "'");
        if (tep_db_num_rows($formats_query)) {
            while ($row = tep_db_fetch_array($formats_query)) {
                $p_formats[] = $row;
            }
        }
        tep_db_free_result($formats_query);
        return $this->render_ajax('formats.tpl', ['no_redirect' => $no_redirect, 'languages' => \common\helpers\Language::get_languages(), 'lList' => $l_list, 'platform_id' => $id, 'platform_formats' => \yii\helpers\Array_Helper::map($p_formats, 'configuration_key', 'configuration_value', 'language_id'), 'defined_formats' => \yii\helpers\Array_Helper::map($l_formats, 'configuration_key', 'configuration_value', 'language_id')]);
    }
    public function action_sort_order()
    {
        $moved_id = (int) $_POST['sort_top'];
        $type = Yii::$app->request->post('type', 'physical');
        $ref_array = isset($_POST['top']) && is_array($_POST['top']) ? array_map('intval', $_POST['top']) : [];
        if ($moved_id && in_array($moved_id, $ref_array)) {
            // {{ normalize
            $order_counter = 0;
            $platforms = Platforms::get_platforms_by_type($type)->order_by(new \yii\db\Expression('IF(is_default,0,1)'))->add_order_by('sort_order, platform_name')->all();
            if ($platforms) {
                foreach ($platforms as $platform) {
                    $order_counter++;
                    $platform->sort_order = $order_counter;
                    $platform->save();
                    if ($platform->is_default && in_array($platform->platform_id, $ref_array)) {
                        if ($default_index = array_search($platform->platform_id, $ref_array)) {
                            unset($ref_array[$default_index]);
                            array_unshift($ref_array, (int) $platform->platform_id);
                        }
                    }
                }
            }
            // }} normalize
            $get_current_order_r = tep_db_query('SELECT platform_id, is_default, sort_order ' . 'FROM ' . TABLE_PLATFORMS . ' ' . "WHERE platform_id IN('" . implode("','", $ref_array) . "') " . 'ORDER BY IF(is_default,0,1), sort_order');
            $ref_ids = [];
            $ref_so = [];
            while ($_current_order = tep_db_fetch_array($get_current_order_r)) {
                $ref_ids[] = (int) $_current_order['platform_id'];
                $ref_so[] = (int) $_current_order['sort_order'];
            }
            foreach ($ref_array as $_idx => $id) {
                tep_db_query('UPDATE ' . TABLE_PLATFORMS . " SET sort_order='{$ref_so[$_idx]}' WHERE platform_id='{$id}' ");
            }
        }
    }
    public function action_file_manager_upload()
    {
        $text = '';
        if (isset($_FILES['files'])) {
            $path = DIR_FS_CATALOG . 'images/stamp/';
            if (!file_exists($path)) {
                mkdir($path, 0777, true);
            }
            $uploadfile = $path . basename($_FILES['files']['name']);
            if (move_uploaded_file($_FILES['files']['tmp_name'], $uploadfile)) {
                \common\classes\Images::cache_key_invalidate_by_watermark(basename($uploadfile));
                // override existing file
                $text = $_FILES['files']['name'];
            }
        }
        echo $text;
    }
    public function action_configuration()
    {
        \common\helpers\Translation::init('configuration');
        $platform_id = (int) Yii::$app->request->get('platform_id');
        $languages_id = \Yii::$app->settings->get('languages_id');
        $formats_query = tep_db_query('select platform_name from ' . TABLE_PLATFORMS . " where platform_id = '" . (int) $platform_id . "'");
        $formats = tep_db_fetch_array($formats_query);
        $this->selected_menu = ['fronends', 'platforms'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('platforms/configuration'), 'title' => BOX_HEADING_CONFIGURATION . '::' . $formats['platform_name']];
        $this->view->heading_title = BOX_HEADING_CONFIGURATION . '::' . $formats['platform_name'];
        $this->view->admin_table = [['title' => TEXT_TABLE_TITLE, 'not_important' => 0], ['title' => TEXT_TABLE_VALUE, 'not_important' => 0]];
        $filter_entity = [];
        $group_query = tep_db_query('select configuration_group_id from platforms_configuration where platform_id=' . $platform_id . ' group by configuration_group_id');
        while ($group = tep_db_fetch_array($group_query)) {
            $title = \common\helpers\Translation::get_translation_value($group['configuration_group_id'], 'admin/main', $languages_id);
            if (tep_not_null($title)) {
                $group['configuration_group_title'] = $title;
            } else {
                $group['configuration_group_title'] = $group['configuration_group_id'];
            }
            $filter_entity[] = ['id' => $group['configuration_group_id'], 'text' => $group['configuration_group_title']];
        }
        $this->view->row = (int) \Yii::$app->request->get('row');
        $this->view->filter_groups = tep_draw_pull_down_menu('group_id', $filter_entity, \Yii::$app->request->get('group_id', 1), 'class="form-control" onchange="return applyFilter();"');
        $this->view->platform_id = $platform_id;
        return $this->render('configuration');
    }
    public function action_getgroupcontent()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $this->layout = false;
        $customers_query_numrows = 1;
        $draw = (int) Yii::$app->request->get('draw');
        $start = (int) Yii::$app->request->get('start');
        $length = (int) Yii::$app->request->get('length');
        $form_filter = Yii::$app->request->get('filter');
        parse_str($form_filter, $filter);
        $groupid = (string) $filter['group_id'];
        $platform_id = (int) $filter['platform_id'];
        $response_list = [];
        $extra_html = '';
        $search = '';
        $search_condition = ' where 1 ';
        if (isset($_GET['search']) && tep_not_null($_GET['search'])) {
            if (is_array($_GET['search'])) {
                if (isset($_GET['search']['value'])) {
                    if (trim($_GET['search']['value']) != '') {
                        $keywords = tep_db_input(tep_db_prepare_input($_GET['search']['value']));
                        $search_condition = " where (configuration_title like '%" . $keywords . "%' or configuration_description like '%" . $keywords . "%' )";
                    }
                }
            }
        }
        if (isset($_GET['order'][0]['column']) && $_GET['order'][0]['dir']) {
            switch ($_GET['order'][0]['column']) {
                case 0:
                    $order_by = 'configuration_title ' . tep_db_input(tep_db_prepare_input($_GET['order'][0]['dir']));
                    break;
                case 1:
                    $order_by = 'configuration_description ' . tep_db_input(tep_db_prepare_input($_GET['order'][0]['dir']));
                    break;
                default:
                    $order_by = 'sort_order';
                    break;
            }
        } else {
            $order_by = 'sort_order';
        }
        $_query = 'select configuration_id, configuration_title, configuration_value, use_function, configuration_key
                   from ' . TABLE_PLATFORMS_CONFIGURATION . "\n\n                    {$search_condition}\n                    and configuration_group_id = '" . $groupid . "'\n                    and platform_id = '" . (int) $platform_id . "'\n                    order by {$order_by} ";
        $current_page_number = $start / $length + 1;
        $db_split = new \Split_Page_Results($current_page_number, $length, $_query, $configuration_query_numrows, 'configuration_id');
        $configuration_query = tep_db_query($_query);
        while ($configuration = tep_db_fetch_array($configuration_query)) {
            $cfg_value = null;
            if (tep_not_null($configuration['use_function'])) {
                $use_function = $configuration['use_function'];
                if (preg_match('/->/', $use_function)) {
                    $class_method = explode('->', $use_function);
                    if (!is_object(${$class_method[0]})) {
                        if ($class_method[0] == 'currencies') {
                            ${$class_method[0]} = new \common\classes\Currencies();
                        } else {
                            ${$class_method[0]} = new $class_method[0]();
                        }
                    }
                    $cfg_value = tep_call_function($class_method[1], $configuration['configuration_value'], ${$class_method[0]});
                } else if (method_exists('backend\models\Configuration', $use_function)) {
                    $cfg_value = call_user_func(['backend\models\Configuration', $use_function], $configuration['configuration_value']);
                } elseif (function_exists($use_function)) {
                    $cfg_value = tep_call_function($use_function, $configuration['configuration_value']);
                }
            } else {
                $_t = \common\helpers\Translation::get_translation_value(strtoupper(str_replace(' ', '_', $configuration['configuration_value'])), 'configuration', $languages_id);
                $_t = tep_not_null($_t) ? $_t : $configuration['configuration_value'];
                $cfg_value = $_t;
            }
            $cfg_extra_query = tep_db_query('select configuration_key, configuration_description, date_added, last_modified, use_function, set_function from ' . TABLE_PLATFORMS_CONFIGURATION . " where configuration_id = '" . (int) $configuration['configuration_id'] . "'");
            $cfg_extra = tep_db_fetch_array($cfg_extra_query);
            $c_info_array = array_merge($configuration, $cfg_extra);
            if ($configuration['configuration_key'] == 'STORE_COUNTRY') {
                $cfg_value = \common\helpers\Country::get_country_name($configuration['configuration_value']);
            }
            if ($configuration['configuration_key'] == 'DOWNLOADS_CONTROLLER_ORDERS_STATUS' || $configuration['configuration_key'] == 'AFFILIATE_PAYMENT_ORDER_MIN_STATUS') {
                $extra_html = \common\helpers\Order::get_status_name($cfg_value);
            } elseif ($configuration['configuration_key'] == 'DEFAULT_USER_GROUP' || $configuration['configuration_key'] == 'DEFAULT_USER_LOGIN_GROUP') {
                $extra_html = \common\helpers\Group::get_user_group_name($cfg_value);
            } else {
                $extra_html = htmlspecialchars($cfg_value);
            }
            if (strip_tags(trim(strtolower($extra_html))) === strip_tags(trim(strtolower($cfg_value)))) {
                $extra_html = '';
            }
            $title = \common\helpers\Translation::get_translation_value($configuration['configuration_key'] . '_TITLE', 'configuration', $languages_id);
            if (!tep_not_null($title)) {
                $title = $c_info_array['configuration_title'];
            }
            $response_list[] = [$title . "<input class='cell_identify' type='hidden' value='" . $c_info_array['configuration_id'] . "' />", $cfg_value . "<br/> {$extra_html} "];
        }
        $configuration_query_numrows1 = 0;
        $response = ['draw' => $draw, 'recordsTotal' => $configuration_query_numrows + $configuration_query_numrows1, 'recordsFiltered' => $configuration_query_numrows + $configuration_query_numrows1, 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_preedit()
    {
        global $access_levels_id;
        $this->layout = false;
        $languages_id = \Yii::$app->settings->get('languages_id');
        $param_id = (int) Yii::$app->request->post('param_id');
        $platform_id = (int) Yii::$app->request->post('platform_id');
        $table = TABLE_PLATFORMS_CONFIGURATION;
        $_query = 'select * from ' . $table . " where configuration_id = '{$param_id}'";
        $configuration_query = tep_db_query($_query);
        $configuration = tep_db_fetch_array($configuration_query);
        if (!is_array($configuration)) {
            return;
        }
        $group_id = $configuration['configuration_group_id'];
        $title = \common\helpers\Translation::get_translation_value($configuration['configuration_key'] . '_TITLE', 'configuration', $languages_id);
        if (tep_not_null($title)) {
            $configuration['configuration_title'] = $title;
        }
        ?>
                <div class="or_box_head"> <?php 
        echo $configuration['configuration_title'];
        ?></div>
                <div class="row_or"><?php 
        echo '<div>' . TEXT_INFO_DATE_ADDED . '</div><div>' . \common\helpers\Date::date_short($configuration['date_added']);
        ?></div></div>

                <input name="param_id" type="hidden" value="<?php 
        echo $param_id;
        ?>">
                <input name="group_id" type="hidden" value="<?php 
        echo $group_id;
        ?>">

                <div class="btn-toolbar btn-toolbar-order">
                    <button class="btn btn-primary btn-process-order btn-edit" onclick="return editItem( <?php 
        echo "{$param_id}, {$platform_id}";
        ?>)"><?php 
        echo IMAGE_EDIT;
        ?></button>
        <?php 
        if ($access_levels_id == 1) {
            ?>
                              <button class="btn btn-process-order btn-delete" onclick="return deleteTrashedItem( <?php 
            echo "{$param_id}, {$platform_id}";
            ?>)"><?php 
            echo IMAGE_DELETE;
            ?></button>
            <?php 
        }
        ?>
                </div>
        <?php 
    }
    public function action_getparam()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $this->layout = false;
        $param_id = Yii::$app->request->post('param_id');
        $_query = 'select * from ' . TABLE_PLATFORMS_CONFIGURATION . " where configuration_id = '{$param_id}'";
        $configuration_query = tep_db_query($_query);
        $configuration = tep_db_fetch_array($configuration_query);
        $group_id = $configuration['configuration_group_id'];
        if (!is_array($configuration)) {
            die('Wrong data');
        }
        $method = trim(strtolower(substr($configuration['set_function'], 0, strpos($configuration['set_function'], '('))));
        if ((string) $configuration['set_function'] && method_exists('backend\models\Configuration', $method)) {
            $_args = preg_replace('/' . $method . "[\\s\\(]*/i", '', $configuration['set_function']) . "'" . htmlspecialchars($configuration['configuration_value']) . "', '" . $configuration['configuration_key'] . "'";
            $value_field = call_user_func(['backend\models\Configuration', $method], $_args);
            /*
                          if( strpos( $configuration['set_function'], 'tep_cfg_select_multioption' ) !== FALSE ) {
                          eval( '$value_field = ' . $configuration['set_function'] . '"' . htmlspecialchars( $configuration['configuration_value'] ) . '","' . $configuration['configuration_key'] . '");' );
                          } else {
                          eval( '$value_field = ' . $configuration['set_function'] . '"' . htmlspecialchars( $configuration['configuration_value'] ) . '");' );
                          } */
        } else {
            $value_field = tep_draw_input_field('configuration_value', $configuration['configuration_value'], 'class="form-control"');
        }
        $translated_title = \common\helpers\Translation::get_translation_value($configuration['configuration_key'] . '_TITLE', 'configuration', $languages_id);
        echo tep_draw_form('save_param_form', 'configuration/index', \common\helpers\Output::get_all_get_params(['action']) . 'action=update', 'post', 'id="save_param_form" onSubmit="return saveParam();"') . tep_draw_hidden_field('group_id', $group_id) . tep_draw_hidden_field('param_id', $param_id) . tep_draw_hidden_field('configuration_key', $configuration['configuration_key']);
        $languages = \common\helpers\Language::get_languages(true);
        $title = \common\helpers\Translation::get_translation_value($configuration['configuration_key'] . '_TITLE', 'configuration', $languages_id);
        if (tep_not_null($title)) {
            $configuration['configuration_title'] = $title;
        }
        $description = \common\helpers\Translation::get_translation_value($configuration['configuration_key'] . '_DESC', 'configuration', $languages_id);
        if (tep_not_null($description)) {
            $configuration['configuration_description'] = $description;
        }
        ?>
        				<div class="or_box_head"><?php 
        echo $configuration['configuration_title'];
        ?></div>
        				<div class="row_or dataTableContent"><?php 
        echo $configuration['configuration_description'];
        ?></div>
        				<div class="row_or dataTableContent"><?php 
        echo $value_field;
        ?></div>
        <?php 
        if (!tep_not_null($translated_title)) {
            ?>
                    <br>
                    <div class="row_or dataTableContent">
                        <div class="tab-pane">
                            <div class="tabbable tabbable-custom">
                                <ul class="nav nav-tabs">
            <?php 
            foreach ($languages as $l_key => $l_item) {
                ?>
                                        <li <?php 
                if ($l_key == 0) {
                    ?> class="active"<?php 
                }
                ?> data-bs-toggle="tab" data-bs-target="#tab_2_<?php 
                echo $l_item['id'];
                ?>"><a class="flag-span"><?php 
                echo $l_item['image'];
                ?><span><?php 
                echo $l_item['name'];
                ?></span></a></li>
            <?php 
            }
            ?>
                                </ul>
                                <div class="tab-content">
            <?php 
            foreach ($languages as $l_key => $l_item) {
                ?>
                                        <div class="tab-pane<?php 
                if ($l_key == 0) {
                    ?>  active<?php 
                }
                ?>" id="tab_2_<?php 
                echo $l_item['id'];
                ?>">
                                            <div class="">
                                                <label><?php 
                echo \common\helpers\Translation::get_translation_value('TEXT_TITLE', 'admin/main', $l_item['id']);
                ?></label>
                <?php 
                echo Html::text_input($configuration['configuration_key'] . '_TITLE[' . $l_item['id'] . ']', $configuration['configuration_title']);
                ?>
                                            </div>
                                            <div class="">
                                                <label><?php 
                echo \common\helpers\Translation::get_translation_value('TEXT_DESCRIPTION', 'admin/main', $l_item['id']);
                ?></label>
                <?php 
                echo Html::textarea($configuration['configuration_key'] . '_DESC[' . $l_item['id'] . ']', $configuration['configuration_description']);
                ?>
                                            </div>
                                        </div>
            <?php 
            }
            ?>
                                </div>
                            </div>
                        </div>
                    </div>
        <?php 
        }
        ?>
        				<div class="btn-toolbar btn-toolbar-order">
        					<button class="btn btn-no-margin"><?php 
        echo IMAGE_UPDATE;
        ?></button><button class="btn" onclick="return resetStatement()"><?php 
        echo IMAGE_BACK;
        ?></button>
        				</div>
                </form>
        <?php 
    }
    public function action_delete_param()
    {
        $error = false;
        $message = '';
        $message_type = null;
        $configuration_id = (int) Yii::$app->request->post('param_id');
        tep_db_query('delete from ' . TABLE_PLATFORMS_CONFIGURATION . " where configuration_id = {$configuration_id}");
        if (true) {
            $message = TEXT_PARAM_CHANGE_SUCCESS;
        }
        if ($error === true) {
            $message_type = 'warning';
        }
        if ($message != '') {
            echo Message_Popup::widget(['messageType' => $message_type, 'message' => $message, 'clickJs' => 'resetStatement();']);
        }
    }
    public function action_saveparam()
    {
        $this->layout = false;
        $error = false;
        $message = '';
        $message_type = 'success';
        $html = '';
        $configuration_id = (int) Yii::$app->request->post('param_id');
        $configuration_key = Yii::$app->request->post('configuration_key');
        $configuration_value = Yii::$app->request->post('configuration_value');
        $configuration = Yii::$app->request->post('configuration');
        if (is_array($configuration_value)) {
            $configuration_value = implode(', ', $configuration_value);
            $configuration_value = preg_replace('/, --none--/', '', $configuration_value);
        } elseif (is_array($configuration)) {
            $configuration_value = $configuration[$configuration_key];
            if (is_array($configuration_value)) {
                $configuration_value = implode(', ', $configuration_value);
                $configuration_value = preg_replace('/, --none--/', '', $configuration_value);
            }
        }
        tep_db_query('update ' . TABLE_PLATFORMS_CONFIGURATION . "\n          set configuration_value = '" . tep_db_input(tep_db_prepare_input($configuration_value)) . "', last_modified = now()\n          where configuration_id = '" . $configuration_id . "'");
        if (is_array($_POST)) {
            foreach (tep_db_prepare_input($_POST) as $translation_key => $value) {
                if (strpos($translation_key, 'TITLE') !== false || strpos($translation_key, 'DESC') !== false) {
                    if (is_array($value)) {
                        foreach ($value as $language_id => $translation_value) {
                            \common\helpers\Translation::set_translation_value($translation_key, 'configuration', $language_id, $translation_value);
                        }
                    } else {
                        $language_id = key($value);
                        $translation_value = current($value);
                        \common\helpers\Translation::set_translation_value($translation_key, 'configuration', $language_id, $translation_value);
                    }
                }
            }
        }
        // TODO Check if there were no MySql errors
        if (true) {
            $message = TEXT_PARAM_CHANGE_SUCCESS;
        }
        if ($error === true) {
            $message_type = 'warning';
        }
        if ($message != '') {
            echo Message_Popup::widget(['messageType' => $message_type, 'message' => $message, 'clickJs' => 'resetStatement();']);
        }
        $this->action_get_param();
    }
    public function action_holidays()
    {
        \common\helpers\Translation::init('admin/platforms');
        $this->view->holidays_table = [['title' => TABLE_HEADING_DAY, 'not_important' => 1]];
        $platform_id = Yii::$app->request->get('platform_id');
        if (Yii::$app->request->is_post) {
            $hdate = Yii::$app->request->post('hdate', []);
            $platform_id = Yii::$app->request->post('platform_id');
            if ($platform_id) {
                $action = Yii::$app->request->post('action');
                $search = Yii::$app->request->post('search');
                if ($action == 'load') {
                    $dates = \common\helpers\Date::get_holidays($platform_id, DATE_FORMAT_DATEPICKER_PHP, $search);
                    echo json_encode($dates);
                    exit;
                }
                if (empty($search)) {
                    tep_db_query('delete from ' . TABLE_PLATFORMS_HOLIDAYS . " where platform_id = '" . (int) $platform_id . "'");
                } else {
                    tep_db_query('delete from ' . TABLE_PLATFORMS_HOLIDAYS . " where platform_id = '" . (int) $platform_id . "' and year(holidate) = '" . tep_db_input($search) . "'");
                }
                if (is_array($hdate)) {
                    foreach ($hdate as $date) {
                        if (empty($date)) {
                            continue;
                        }
                        $_d = \common\helpers\Date::prepare_input_date($date);
                        if ($_d) {
                            $sql_data_array = ['platform_id' => $platform_id, 'holidate' => $_d];
                            tep_db_perform(TABLE_PLATFORMS_HOLIDAYS, $sql_data_array);
                        }
                    }
                }
            }
            echo json_encode(['messageType' => 'success', 'message' => TEXT_MESSEAGE_SUCCESS]);
            exit;
        }
        $dates = \common\helpers\Date::get_holidays($platform_id, DATE_FORMAT_DATEPICKER_PHP, date('Y'));
        return $this->render_ajax('holidays', ['platform_id' => $platform_id, 'dates' => $dates, 'hyear' => date('Y')]);
    }
    public function action_generate_key()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return ['platform_code' => \common\helpers\Password::create_random_value(6)];
    }
    public function action_setup_watermark()
    {
        Translation::init('admin/platforms');
        $message = '';
        $item_id = 1;
        if ($ext = \common\helpers\Acl::check_extension_allowed('AdditionalPlatforms', 'allowed')) {
            $item_id = $ext::edit();
        }
        $this->top_buttons[] = '<span class="btn btn-confirm" onclick="$(\'#save_item_form\').trigger(\'submit\')">' . IMAGE_SAVE . '</span>';
        if (Yii::$app->request->is_post) {
            $sql_data_array = ['platform_id' => (int) $item_id, 'status' => (int) Yii::$app->request->post('watermark_status'), 'top_left_watermark30' => Yii::$app->request->post('top_left_watermark30'), 'top_watermark30' => Yii::$app->request->post('top_watermark30'), 'top_right_watermark30' => Yii::$app->request->post('top_right_watermark30'), 'left_watermark30' => Yii::$app->request->post('left_watermark30'), 'watermark30' => Yii::$app->request->post('watermark30'), 'right_watermark30' => Yii::$app->request->post('right_watermark30'), 'bottom_left_watermark30' => Yii::$app->request->post('bottom_left_watermark30'), 'bottom_watermark30' => Yii::$app->request->post('bottom_watermark30'), 'bottom_right_watermark30' => Yii::$app->request->post('bottom_right_watermark30'), 'top_left_watermark170' => Yii::$app->request->post('top_left_watermark170'), 'top_watermark170' => Yii::$app->request->post('top_watermark170'), 'top_right_watermark170' => Yii::$app->request->post('top_right_watermark170'), 'left_watermark170' => Yii::$app->request->post('left_watermark170'), 'watermark170' => Yii::$app->request->post('watermark170'), 'right_watermark170' => Yii::$app->request->post('right_watermark170'), 'bottom_left_watermark170' => Yii::$app->request->post('bottom_left_watermark170'), 'bottom_watermark170' => Yii::$app->request->post('bottom_watermark170'), 'bottom_right_watermark170' => Yii::$app->request->post('bottom_right_watermark170'), 'top_left_watermark300' => Yii::$app->request->post('top_left_watermark300'), 'top_watermark300' => Yii::$app->request->post('top_watermark300'), 'top_right_watermark300' => Yii::$app->request->post('top_right_watermark300'), 'left_watermark300' => Yii::$app->request->post('left_watermark300'), 'watermark300' => Yii::$app->request->post('watermark300'), 'right_watermark300' => Yii::$app->request->post('right_watermark300'), 'bottom_left_watermark300' => Yii::$app->request->post('bottom_left_watermark300'), 'bottom_watermark300' => Yii::$app->request->post('bottom_watermark300'), 'bottom_right_watermark300' => Yii::$app->request->post('bottom_right_watermark300')];
            $watermark_model = \common\models\Platforms_Watermark::find()->where(['platform_id' => (int) $item_id])->one();
            if (!$watermark_model) {
                $watermark_model = new \common\models\Platforms_Watermark(['platform_id' => (int) $item_id]);
                $watermark_model->load_default_values();
            }
            $watermark_model->set_attributes($sql_data_array, false);
            if (!$watermark_model->is_new_record) {
                if ($watermark_model->get_dirty_attributes(['watermark30'])) {
                    \common\classes\Images::cache_key_invalidate_by_watermark($watermark_model->get_old_attribute('watermark30'), $item_id);
                }
                if ($watermark_model->get_dirty_attributes(['watermark170'])) {
                    \common\classes\Images::cache_key_invalidate_by_watermark($watermark_model->get_old_attribute('watermark170'), $item_id);
                }
                if ($watermark_model->get_dirty_attributes(['watermark300'])) {
                    \common\classes\Images::cache_key_invalidate_by_watermark($watermark_model->get_old_attribute('watermark300'), $item_id);
                }
            }
            $watermark_model->save(false);
            $item_id = $watermark_model->platform_id;
            $message = Message_Popup::widget(['messageType' => Message_Popup::MESSAGE_TYPE_SUCCESS, 'message' => 'Updated']);
            //$this->redirect(Url::current());
        }
        $watermark_model = \common\models\Platforms_Watermark::find()->where(['platform_id' => (int) $item_id])->one();
        if (!$watermark_model) {
            $watermark_model = new \common\models\Platforms_Watermark(['platform_id' => (int) $item_id]);
            $watermark_model->load_default_values();
        }
        $watermark = $watermark_model->get_attributes(null, ['status']);
        $watermark['watermark_status'] = $watermark_model->status;
        if ($item_id > 0) {
            $platform_model = \common\models\Platforms::find_one((int) $item_id);
            $p_info = new \Object_Info(array_merge($platform_model->get_attributes(), $watermark));
        } else {
            $p_info = new \Object_Info($watermark);
        }
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('platforms/'), 'title' => sprintf(TEXT_SETUP_PLATFORM_WATERMARK_HEAD, strval($p_info->platform_name ?? ''))];
        $this->selected_menu = ['fronends', 'platforms'];
        $render_params = ['message' => $message, 'pInfo' => $p_info];
        if (Yii::$app->request->is_ajax && Yii::$app->request->is_post) {
            return $this->render_ajax('setup-watermark', $render_params);
        }
        return $this->render('setup-watermark', $render_params);
    }
    public function action_choose_theme()
    {
        Translation::init('admin/platforms');
        $platform_id = 1;
        if ($ext = Acl::check_extension_allowed('AdditionalPlatforms', 'allowed')) {
            $platform_id = $ext::edit();
        }
        if (Yii::$app->request->is_post) {
            $theme_id = (int) Yii::$app->request->post('theme_id');
            Platforms_To_Themes::delete_all(['platform_id' => (int) $platform_id]);
            if ($theme_id > 0) {
                $platforms_to_themes = new Platforms_To_Themes();
                $platforms_to_themes->platform_id = $platform_id;
                $platforms_to_themes->theme_id = $theme_id;
                $platforms_to_themes->is_default = 1;
                $platforms_to_themes->save(false);
            }
            return json_encode(['message' => TEXT_MESSEAGE_SUCCESS]);
        }
        $theme_id = Platforms_To_Themes::find_one(['platform_id' => $platform_id])->theme_id ?? 0;
        $platform_name = Platforms::find_one((int) $platform_id)->platform_name ?? '';
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('platforms/'), 'title' => sprintf(TEXT_CHOOSE_PLATFORM_THEME_HEAD, $platform_name)];
        $this->selected_menu = ['fronends', 'platforms'];
        $this->top_buttons[] = '<span class="btn" onclick="return window.history.back()">' . IMAGE_BACK . '</span>';
        $themes_array = \common\models\Themes::find()->order_by('sort_order')->as_array()->all();
        $themes = [];
        foreach ($themes_array as $item) {
            if ($item['theme_name'] == \common\classes\design::page_name(BACKEND_THEME_NAME)) {
                continue;
            }
            $theme_image = Themes_Settings::find_one(['theme_name' => $item['theme_name'], 'setting_group' => 'hide', 'setting_name' => 'theme_image'])->setting_value ?? null;
            if (is_file(DIR_FS_CATALOG . $theme_image)) {
                $item['image'] = DIR_WS_CATALOG . $theme_image;
            } elseif (is_file(DIR_FS_CATALOG . 'themes/' . $item['theme_name'] . '/screenshot.png')) {
                $item['image'] = DIR_WS_CATALOG . 'themes/' . $item['theme_name'] . '/screenshot.png';
            } else {
                $item['image'] = '';
            }
            $themes[] = $item;
        }
        return $this->render('choose-theme', ['themeId' => $theme_id, 'platformId' => $platform_id, 'themes' => $themes]);
    }
    public function action_working_timetable()
    {
        Translation::init('admin/platforms');
        $message = '';
        $days = [0 => TEXT_EVERYDAY, 1 => TEXT_MONDAY, 2 => TEXT_TUESDAY, 3 => TEXT_WEDNESDAY, 4 => TEXT_THURSDAY, 5 => TEXT_FRIDAY, 6 => TEXT_SATURDAY, 7 => TEXT_SUNDAY];
        $item_id = 1;
        if ($ext = \common\helpers\Acl::check_extension_allowed('AdditionalPlatforms', 'allowed')) {
            $item_id = $ext::edit();
        }
        $this->top_buttons[] = '<span class="btn btn-confirm" onclick="$(\'#save_item_form\').trigger(\'submit\')">' . IMAGE_SAVE . '</span>';
        if (Yii::$app->request->is_post) {
            $platforms_cut_off_times_ids = Yii::$app->request->post('platforms_cut_off_times_id');
            $platforms_cut_off_times_keys = Yii::$app->request->post('platforms_cut_off_times_key');
            $cut_off_times_today = Yii::$app->request->post('cut_off_times_today');
            $cut_off_times_next_day = Yii::$app->request->post('cut_off_times_next_day');
            $platforms_open_hours_ids = Yii::$app->request->post('platforms_open_hours_id');
            $platforms_open_hours_keys = Yii::$app->request->post('platforms_open_hours_key');
            $open_time_from = Yii::$app->request->post('open_time_from');
            $open_time_to = Yii::$app->request->post('open_time_to');
            $active_open_hours_ids = [];
            foreach ($platforms_open_hours_ids as $platforms_open_hours_key => $platforms_open_hours_id) {
                $open_days = Yii::$app->request->post('open_days_' . $platforms_open_hours_keys[$platforms_open_hours_key]);
                if ($open_days) {
                    $sql_data_array = ['open_days' => implode(',', $open_days), 'open_time_from' => $open_time_from[$platforms_open_hours_key], 'open_time_to' => $open_time_to[$platforms_open_hours_key]];
                    if ((int) $platforms_open_hours_id > 0) {
                        tep_db_perform(TABLE_PLATFORMS_OPEN_HOURS, $sql_data_array, 'update', "platform_id = '" . (int) $item_id . "' and platforms_open_hours_id = '" . (int) $platforms_open_hours_id . "'");
                        $active_open_hours_ids[] = $platforms_open_hours_id;
                    } else {
                        tep_db_perform(TABLE_PLATFORMS_OPEN_HOURS, array_merge($sql_data_array, ['platform_id' => $item_id]));
                        $new_open_hours_id = tep_db_insert_id();
                        $active_open_hours_ids[] = $new_open_hours_id;
                    }
                }
            }
            if (count($active_open_hours_ids) > 0) {
                tep_db_query('delete from ' . TABLE_PLATFORMS_OPEN_HOURS . " where platform_id = '" . (int) $item_id . "' and platforms_open_hours_id NOT IN (" . implode(', ', $active_open_hours_ids) . ')');
            }
            $active_cut_off_times_ids = [];
            if (is_array($platforms_cut_off_times_ids)) {
                foreach ($platforms_cut_off_times_ids as $platforms_cut_off_times_key => $platforms_cut_off_times_id) {
                    $cut_off_times_days = Yii::$app->request->post('cut_off_times_days_' . $platforms_cut_off_times_keys[$platforms_cut_off_times_key]);
                    if (!is_array($cut_off_times_days)) {
                        $cut_off_times_days = [];
                    }
                    $sql_data_array = ['cut_off_times_days' => implode(',', $cut_off_times_days), 'cut_off_times_today' => $cut_off_times_today[$platforms_cut_off_times_key], 'cut_off_times_next_day' => $cut_off_times_next_day[$platforms_cut_off_times_key]];
                    if ((int) $platforms_cut_off_times_id > 0) {
                        tep_db_perform(TABLE_PLATFORMS_CUT_OFF_TIMES, $sql_data_array, 'update', "platform_id = '" . (int) $item_id . "' and platforms_cut_off_times_id = '" . (int) $platforms_cut_off_times_id . "'");
                        $active_cut_off_times_ids[] = $platforms_cut_off_times_id;
                    } else {
                        tep_db_perform(TABLE_PLATFORMS_CUT_OFF_TIMES, array_merge($sql_data_array, ['platform_id' => $item_id]));
                        $active_cut_off_times_ids[] = tep_db_insert_id();
                    }
                }
            }
            if (count($active_cut_off_times_ids) > 0) {
                tep_db_query('delete from ' . TABLE_PLATFORMS_CUT_OFF_TIMES . " where platform_id = '" . (int) $item_id . "' and platforms_cut_off_times_id NOT IN (" . implode(', ', $active_cut_off_times_ids) . ')');
            }
            $message = Message_Popup::widget(['messageType' => Message_Popup::MESSAGE_TYPE_SUCCESS, 'message' => TEXT_MESSEAGE_SUCCESS]);
        }
        if ($item_id > 0) {
            $platform_model = \common\models\Platforms::find_one((int) $item_id);
            $p_info = new \Object_Info(array_merge($platform_model->get_attributes(), []));
        } else {
            $p_info = new \Object_Info();
        }
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('platforms/'), 'title' => sprintf(TEXT_PLATFORM_WORKING_TIMETABLE_HEAD, strval($p_info->platform_name ?? ''))];
        $this->selected_menu = ['fronends', 'platforms'];
        $open_hours = [];
        $open_hours_query = tep_db_query('select * from ' . TABLE_PLATFORMS_OPEN_HOURS . " where platform_id = '" . (int) $item_id . "' ");
        while ($d = tep_db_fetch_array($open_hours_query)) {
            if (isset($d['open_days'])) {
                $d['open_days'] = explode(',', $d['open_days']);
            }
            $open_hours[] = new \Object_Info($d);
        }
        if (count($open_hours) == 0) {
            $open_hours[] = new \Object_Info([]);
        }
        $cut_off_times = [];
        $cut_off_times_query = tep_db_query('select * from ' . TABLE_PLATFORMS_CUT_OFF_TIMES . " where platform_id = '" . (int) $item_id . "' ");
        while ($d = tep_db_fetch_array($cut_off_times_query)) {
            if (isset($d['cut_off_times_days'])) {
                $d['cut_off_times_days'] = explode(',', $d['cut_off_times_days']);
            }
            $cut_off_times[] = new \Object_Info($d);
        }
        if (count($cut_off_times) == 0) {
            $cut_off_times[] = new \Object_Info([]);
        }
        $render_params = ['message' => $message, 'pInfo' => $p_info, 'open_hours' => $open_hours, 'count_open_hours' => count($open_hours), 'cut_off_times' => $cut_off_times, 'count_cut_off_times' => count($cut_off_times), 'days' => $days];
        if (Yii::$app->request->is_ajax && Yii::$app->request->is_post) {
            return $this->render_ajax('working-timetable', $render_params);
        }
        return $this->render('working-timetable', $render_params);
    }
    public function action_configure_localization()
    {
        Translation::init('admin/platforms');
        Translation::init('admin/platforms/edit');
        $message = '';
        $this->top_buttons[] = '<span class="btn btn-confirm" onclick="$(\'#save_item_form\').trigger(\'submit\')">' . IMAGE_SAVE . '</span>';
        $item_id = 1;
        if ($ext = \common\helpers\Acl::check_extension_allowed('AdditionalPlatforms', 'allowed')) {
            $item_id = $ext::edit();
        }
        if (Yii::$app->request->is_post) {
            $default_language = strtolower(tep_db_prepare_input(Yii::$app->request->post('default_language')));
            $planguages = [$default_language => $default_language];
            if (is_array(Yii::$app->request->post('planguages'))) {
                foreach (Yii::$app->request->post('planguages') as $l) {
                    $planguages[$l] = $l;
                }
            }
            $default_currency = strtoupper(tep_db_prepare_input(Yii::$app->request->post('default_currency')));
            $pcurrencies = [$default_currency => $default_currency];
            if (is_array(Yii::$app->request->post('pcurrencies'))) {
                foreach (Yii::$app->request->post('pcurrencies') as $c) {
                    $pcurrencies[$c] = $c;
                }
            }
            if ($item_id > 0) {
                if ($platform_model = \common\models\Platforms::find_one((int) $item_id)) {
                    $platform_model->set_attributes(['defined_languages' => strtolower(implode(',', $planguages)), 'defined_currencies' => implode(',', $pcurrencies), 'default_language' => $default_language, 'default_currency' => $default_currency], false);
                    $platform_model->save(false);
                    $countries = Yii::$app->request->post('countries');
                    $this->service_country->delete_countries($item_id);
                    if (is_array($countries)) {
                        $this->service_country->save_countries($countries, $item_id);
                    }
                    $selected_zones = Yii::$app->request->post('zones');
                    \common\models\Platforms_Geo_Zones::delete_all(['platform_id' => $item_id]);
                    if (is_array($selected_zones)) {
                        $selected_zones = array_unique(array_map('intval', $selected_zones));
                        $selected_zones = array_map(function ($el) use ($item_id) {
                            return ['platform_id' => $item_id, 'geo_zone_id' => $el];
                        }, $selected_zones);
                        Yii::$app->db->create_command()->batch_insert(\common\models\Platforms_Geo_Zones::table_name(), ['platform_id', 'geo_zone_id'], $selected_zones)->execute();
                    }
                    $platforms_locations = Yii::$app->request->post('platform_locations');
                    if (!is_array($platforms_locations)) {
                        $platforms_locations = [];
                    }
                    $valid_locations_ids = [];
                    foreach ($platforms_locations as $platforms_location) {
                        $platforms_locations_id = (int) $platforms_location['platforms_locations_id'];
                        if ($platforms_locations_id > 0) {
                            $check_valid = tep_db_fetch_array(tep_db_query('SELECT COUNT(*) AS c ' . 'FROM ' . TABLE_PLATFORMS_LOCATIONS . ' ' . "WHERE platforms_locations_id='" . (int) $platforms_locations_id . "' AND platform_id='" . (int) $item_id . "' "));
                            if ($check_valid['c'] == 0) {
                                $platforms_locations_id = 0;
                            }
                        }
                        if ($platforms_locations_id) {
                            tep_db_perform(TABLE_PLATFORMS_LOCATIONS, $platforms_location, 'update', "platforms_locations_id='" . (int) $platforms_locations_id . "'");
                        } else {
                            $platforms_location['platform_id'] = (int) $item_id;
                            tep_db_perform(TABLE_PLATFORMS_LOCATIONS, $platforms_location);
                            $platforms_locations_id = intval(tep_db_insert_id());
                        }
                        $valid_locations_ids[] = $platforms_locations_id;
                    }
                    tep_db_query('DELETE FROM ' . TABLE_PLATFORMS_LOCATIONS . ' ' . "WHERE platform_id='" . (int) $item_id . "' " . (count($valid_locations_ids) == 0 ? '' : "AND platforms_locations_id NOT IN('" . implode("','", $valid_locations_ids) . "') "));
                    $currency_margin = Yii::$app->request->post('currency_margin', []);
                    $this->currencies_margin_service->delete_currencies_margin($item_id);
                    if (is_array($currency_margin)) {
                        $this->currencies_margin_service->save_currencies_margin($currency_margin, $item_id);
                    }
                    $message = Message_Popup::widget(['messageType' => Message_Popup::MESSAGE_TYPE_SUCCESS, 'message' => TEXT_MESSEAGE_SUCCESS]);
                }
            }
        }
        if ($item_id > 0) {
            $platform_model = \common\models\Platforms::find_one((int) $item_id);
            $p_info = new \Object_Info(array_merge($platform_model->get_attributes(), ['platform_locations' => []]));
        } else {
            $p_info = new \Object_Info(['platform_locations' => []]);
        }
        $p_info->platform_locations = [];
        $get_platform_locations_r = tep_db_query('SELECT * FROM ' . TABLE_PLATFORMS_LOCATIONS . " WHERE platform_id='" . (int) $item_id . "' ");
        if (tep_db_num_rows($get_platform_locations_r) > 0) {
            while ($_platform_locations = tep_db_fetch_array($get_platform_locations_r)) {
                $p_info->platform_locations[] = $_platform_locations;
            }
        }
        if ($item_id) {
            $languages_id = $languages_id ?? null;
            $address_query = tep_db_query('select ab.*, if (LENGTH(ab.entry_state), ab.entry_state, z.zone_name) as entry_state, c.countries_name  from ' . TABLE_PLATFORMS_ADDRESS_BOOK . ' ab left join ' . TABLE_COUNTRIES . " c on ab.entry_country_id=c.countries_id  and c.language_id = '" . (int) $languages_id . "' left join " . TABLE_ZONES . " z on z.zone_country_id=c.countries_id and ab.entry_zone_id=z.zone_id where platform_id = '" . (int) $item_id . "' ");
            $d = tep_db_fetch_array($address_query);
        } else {
            $d = [];
        }
        if (!isset($d['entry_country_id'])) {
            $d['entry_country_id'] = STORE_COUNTRY;
        }
        $addresses = new \Object_Info($d);
        $this->view->currencies = [];
        $currencies = Yii::$container->get('currencies');
        foreach ($currencies->currencies as $currency) {
            $this->view->currencies[$currency['code']] = $currency['title'];
        }
        $this->view->languages = [];
        $languages = \common\helpers\Language::get_languages();
        foreach ($languages as $language) {
            $this->view->languages[$language['code']] = $language['name'];
        }
        $selected_zones = \common\models\Platforms_Geo_Zones::find()->and_where(['platform_id' => (int) $item_id])->select('geo_zone_id')->as_array()->column();
        $zones = \common\models\Geo_Zones::find()->select('geo_zone_name, geo_zone_id')->order_by('geo_zone_name')->index_by('geo_zone_id')->as_array()->column();
        $repository_countries = new Countries_Repositiry();
        $countries_array = $repository_countries->get_platforms_countries($item_id);
        $selected_countries = [];
        foreach ($countries_array as $item => $country) {
            $selected_countries[] = $country->countries_id;
        }
        $countries = [TEXT_ALL => \common\helpers\Country::new_get_countries('', false)];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('platforms/'), 'title' => sprintf(TEXT_PLATFORM_LOCALIZATION_HEAD, strval($p_info->platform_name ?? ''))];
        $this->selected_menu = ['fronends', 'platforms'];
        $render_params = ['message' => $message, 'pInfo' => $p_info, 'pass' => dirname(__DIR__), 'addresses' => $addresses, 'selected_zones' => $selected_zones, 'zones' => $zones, 'selected_countries' => $selected_countries, 'countries' => $countries, 'languages' => \common\helpers\Language::get_languages(), 'platform_languages' => explode(',', strtolower($p_info->defined_languages ?? null)), 'currencies' => new \common\classes\Currencies(isset($p_info->platform_id) ? (int) $p_info->platform_id : 0), 'platform_currencies' => explode(',', $p_info->defined_currencies ?? null)];
        if (Yii::$app->request->is_ajax && Yii::$app->request->is_post) {
            return $this->render_ajax('configure-localization', $render_params);
        }
        return $this->render('configure-localization', $render_params);
    }
}