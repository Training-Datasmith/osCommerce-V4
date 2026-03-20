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

use common\components\Customer;
use common\extensions\Subscribers\models\Customers_To_Lists;
use common\extensions\Subscribers\models\Subscribers_Lists;
use common\forms\Address_Form;
use common\helpers\Affiliate;
use common\helpers\Html;
use common\models\Customers;
use frontend\forms\registration\Customer_Registration;
use Yii;
/**
 * default controller to handle user requests.
 */
class Customers_Controller extends Sceleton
{
    public $acl = ['BOX_HEADING_CUSTOMERS', 'BOX_CUSTOMERS_CUSTOMERS'];
    private $default_currency = DEFAULT_CURRENCY;
    public function __construct($id, $module = null)
    {
        $this->default_currency = \Yii::$app->get('platform')->config()->get_default_currency();
        if ($this->default_currency) {
            \Yii::$app->settings->set('currency', $this->default_currency);
        }
        parent::__construct($id, $module);
    }
    /**
     * Index action is the default action in a controller.
     */
    public function action_index()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $this->selected_menu = ['customers', 'customers'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('customers/index'), 'title' => HEADING_TITLE];
        if (\common\helpers\Acl::rule(['ACL_ORDER', 'IMAGE_NEW'])) {
            $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url(['editor/order-edit', 'back' => 'customers']) . '" class="btn btn-primary"><i class="icon-file-text"></i>' . TEXT_CREATE_NEW_OREDER . '</a>';
        }
        if (\common\helpers\Acl::rule(['ACL_CUSTORER', 'IMAGE_NEW'])) {
            $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url('customers/customeredit') . '" class="btn btn-primary add_new_cus_item"><i class="icon-user-plus"></i>' . TEXT_ADD_NEW_CUSTOMER . '</a>';
        }
        if (defined('ACCOUNT_GDPR') && ACCOUNT_GDPR == 'true' && \common\helpers\Acl::rule(['TEXT_GDPR'])) {
            $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url(['customers/gdpr-check']) . '" onclick="return confirm(\'' . GDPR_CHECK_NOTICE . '\');" class="btn btn-primary">' . TEXT_GDPR_CHECK . '</a>';
            $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url(['customers/gdpr-cleanup']) . '" onclick="return confirm(\'' . GDPR_CLEANUP_NOTICE . '\');" class="btn btn-primary">' . TEXT_GDPR_CLEANUP . '</a>';
        }
        $this->view->heading_title = HEADING_TITLE;
        $this->view->customers_table = [['title' => '<input type="checkbox" class="uniform">', 'not_important' => 2], ['title' => ENTRY_LAST_NAME, 'not_important' => 0], ['title' => ENTRY_FIRST_NAME, 'not_important' => 0], ['title' => TABLE_HEADING_EMAIL . '/' . (defined('SUPERADMIN_ENABLED') && SUPERADMIN_ENABLED == true ? TABLE_HEADING_DEPARTMENT : TABLE_HEADING_PLATFORM), 'not_important' => 0], ['title' => TABLE_HEADING_ACCOUNT_CREATED, 'not_important' => 1], ['title' => TABLE_HEADING_LOCATION, 'not_important' => 0], ['title' => TABLE_HEADING_ORDER_COUNT, 'not_important' => 0], ['title' => TABLE_HEADING_TOTAL_ORDERED, 'not_important' => 0], ['title' => TABLE_HEADING_DATE_LAST_ORDER, 'not_important' => 0]];
        if ($cf_ext = \common\helpers\Acl::check_extension_allowed('CustomerFlag')) {
            array_splice($this->view->customers_table, 1, 0, [['title' => 'Flag', 'not_important' => 0]]);
        }
        $GET = Yii::$app->request->get();
        $admin_filters = \common\models\Admin_Filters::find_one(['filter_type' => 'customers']);
        if ($admin_filters instanceof \common\models\Admin_Filters) {
            $GET += \Opis\Closure\unserialize($admin_filters->filter_data);
        }
        $this->view->filters = new \stdClass();
        $by = [['name' => TEXT_ANY, 'value' => '', 'selected' => ''], ['name' => ENTRY_FIRST_NAME, 'value' => 'firstname', 'selected' => ''], ['name' => ENTRY_LAST_NAME, 'value' => 'lastname', 'selected' => ''], ['name' => TEXT_EMAIL, 'value' => 'email', 'selected' => ''], ['name' => ENTRY_COMPANY, 'value' => 'companyname', 'selected' => ''], ['name' => ENTRY_TELEPHONE_NUMBER, 'value' => 'phone', 'selected' => ''], ['name' => TEXT_ZIP_CODE, 'value' => 'postcode', 'selected' => '']];
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
        $this->view->filters->flag = (int) Yii::$app->request->get('flag');
        $this->view->filters->marker = (int) Yii::$app->request->get('marker');
        $this->view->filters->show_group = \common\helpers\Extensions::is_customer_groups_allowed();
        $group = '';
        if (isset($GET['group'])) {
            $group = $GET['group'];
        }
        $this->view->filters->group = $group;
        $country = '';
        if (isset($GET['country'])) {
            $country = $GET['country'];
        }
        $this->view->filters->country = $country;
        $state = '';
        if (ACCOUNT_STATE == 'required' || ACCOUNT_STATE == 'visible') {
            $this->view->show_state = true;
        } else {
            $this->view->show_state = false;
        }
        if (isset($GET['state'])) {
            $state = $GET['state'];
        }
        $this->view->filters->state = $state;
        $city = '';
        if (isset($GET['city'])) {
            $city = $GET['city'];
        }
        $this->view->filters->city = $city;
        $company = '';
        if (isset($GET['company'])) {
            $company = $GET['company'];
        }
        $this->view->filters->company = $company;
        $guest = [['name' => TEXT_ALL_CUSTOMERS, 'value' => '', 'selected' => ''], ['name' => TEXT_BTN_YES, 'value' => 'y', 'selected' => ''], ['name' => TEXT_BTN_NO, 'value' => 'n', 'selected' => '']];
        foreach ($guest as $key => $value) {
            if (isset($GET['guest']) && $value['value'] == $GET['guest']) {
                $guest[$key]['selected'] = 'selected';
            }
        }
        $this->view->filters->guest = $guest;
        /** @var \common\extensions\Subscribers\Subscribers $subscr  */
        if ($subscr = \common\helpers\Acl::check_extension_allowed('Subscribers', 'allowed')) {
            $newsletter = [['name' => TEXT_ANY, 'value' => '', 'selected' => ''], ['name' => TEXT_SUBSCRIBED, 'value' => 's', 'selected' => ''], ['name' => TEXT_NOT_SUBSCRIBED, 'value' => 'ns', 'selected' => '']];
            $q = Subscribers_Lists::find()->alias('l')->and_where(['exists', (new \yii\db\Query())->from(['s2l' => Customers_To_Lists::table_name()])->and_where('l.subscribers_lists_id=s2l.subscribers_lists_id')])->and_where(['l.language_id' => $languages_id])->add_order_by('l.sort_order, l.name')->distinct()->select(['value' => 'l.subscribers_lists_id', 'l.name'])->as_array()->all();
            if (!empty($q)) {
                $newsletter = array_merge($newsletter, $q);
            }
            if (!empty(\Yii::$app->request->get('newsletter', ''))) {
                foreach ($newsletter as $key => $value) {
                    if (isset($GET['newsletter']) && $value['value'] == $GET['newsletter']) {
                        $newsletter[$key]['selected'] = 'selected';
                        break;
                    }
                }
            }
            $this->view->filters->newsletter = $newsletter;
        }
        $status = [['name' => TEXT_ALL, 'value' => '', 'selected' => ''], ['name' => TEXT_ACTIVE, 'value' => 'y', 'selected' => ''], ['name' => TEXT_NOT_ACTIVE, 'value' => 'n', 'selected' => '']];
        foreach ($status as $key => $value) {
            if (isset($GET['status']) && $value['value'] == $GET['status']) {
                $status[$key]['selected'] = 'selected';
            }
        }
        $this->view->filters->status = $status;
        $title = [['name' => TEXT_ALL, 'value' => '', 'selected' => ''], ['name' => T_MR, 'value' => 'm', 'selected' => ''], ['name' => T_MRS, 'value' => 'f', 'selected' => ''], ['name' => T_MISS, 'value' => 's', 'selected' => '']];
        foreach ($title as $key => $value) {
            if (isset($GET['title']) && $value['value'] == $GET['title']) {
                $title[$key]['selected'] = 'selected';
            }
        }
        $this->view->filters->title = $title;
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
        $this->view->filters->platform = [];
        if (isset($GET['platform']) && is_array($GET['platform'])) {
            foreach ($GET['platform'] as $_platform_id) {
                if ((int) $_platform_id > 0) {
                    $this->view->filters->platform[] = (int) $_platform_id;
                }
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
        $this->view->filters->row = (int) Yii::$app->request->get('row', 0);
        return $this->render('index', ['isMultiPlatform' => \common\classes\platform::is_multi(), 'platforms' => \common\classes\platform::get_list(), 'departments' => $departments]);
    }
    public function action_customerlist()
    {
        global $login_id;
        $languages_id = \Yii::$app->settings->get('languages_id');
        $draw = Yii::$app->request->get('draw');
        $start = Yii::$app->request->get('start');
        $length = Yii::$app->request->get('length');
        if ($length == -1) {
            $length = 10000;
        }
        $currencies = Yii::$container->get('currencies');
        if (defined('SUPERADMIN_ENABLED') && SUPERADMIN_ENABLED == true) {
            $departments = [];
            $departments_list = \common\classes\department::get_list(false);
            foreach ($departments_list as $department) {
                $departments[$department['departments_id']] = $department['departments_store_name'];
            }
        }
        $customers_query = \common\models\Customers::find()->alias('c')->where('1');
        $_join_address_book = false;
        $_join_zones = false;
        $_join_customer_info = false;
        $search = '';
        if (isset($_GET['search']) && tep_not_null($_GET['search']) && tep_not_null($_GET['search']['value'])) {
            $keywords = tep_db_input(tep_db_prepare_input($_GET['search']['value']));
            $customers_query->and_where("(c.customers_lastname like '%" . $keywords . "%' or c.customers_firstname like '%" . $keywords . "%' or c.customers_email_address like '%" . $keywords . "%')");
        }
        $form_filter = Yii::$app->request->get('filter');
        parse_str($form_filter, $output);
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
                $customers_query->and_where("c.departments_id IN ('" . implode("', '", $filter_by_departments) . "')");
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
            $customers_query->and_where("c.platform_id IN ('" . implode("', '", $filter_by_platform) . "')");
        }
        if (tep_not_null($output['search'])) {
            $search = tep_db_prepare_input($output['search']);
            switch ($output['by']) {
                case 'firstname':
                    $customers_query->and_where("c.customers_firstname like '%" . tep_db_input($search) . "%'");
                    break;
                case 'lastname':
                    $customers_query->and_where("c.customers_lastname like '%" . tep_db_input($search) . "%'");
                    break;
                case 'email':
                default:
                    $customers_query->and_where("c.customers_email_address like '%" . tep_db_input($search) . "%'");
                    break;
                case 'companyname':
                    $_join_address_book = true;
                    $customers_query->and_where(" a.entry_company like '%" . tep_db_input($search) . "%' ");
                    break;
                case 'phone':
                    $customers_query->and_where("c.customers_telephone like '%" . tep_db_input($search) . "%'");
                    break;
                case 'postcode':
                    $_join_address_book = true;
                    $customers_query->and_where("a.entry_postcode like '%" . tep_db_input($search) . "%' ");
                    break;
                case '':
                case 'any':
                    $_join_address_book = true;
                    $search_keywords = explode(' ', $search);
                    if (is_array($search_keywords) && count($search_keywords) > 1) {
                        foreach ($search_keywords as $key => $keyword) {
                            $customers_query->and_where('(' . " c.customers_firstname like '%" . tep_db_input($keyword) . "%' " . " or c.customers_lastname like '%" . tep_db_input($keyword) . "%' " . " or c.customers_email_address like '%" . tep_db_input($keyword) . "%' " . " or a.entry_company like '%" . tep_db_input($keyword) . "%' " . " or c.customers_telephone like '%" . tep_db_input($keyword) . "%' " . " or a.entry_postcode like '%" . tep_db_input($keyword) . "%' " . ') ');
                        }
                    } else {
                        $customers_query->and_where(' (' . " c.customers_firstname like '%" . tep_db_input($search) . "%' " . " or c.customers_lastname like '%" . tep_db_input($search) . "%' " . " or c.customers_email_address like '%" . tep_db_input($search) . "%' " . " or a.entry_company like '%" . tep_db_input($search) . "%' " . " or c.customers_telephone like '%" . tep_db_input($search) . "%' " . " or a.entry_postcode like '%" . tep_db_input($search) . "%' " . ') ');
                    }
                    break;
            }
        }
        if (tep_not_null($output['group'] ?? null)) {
            $_filter_group_ids = \yii\helpers\Array_Helper::map(\common\models\Groups::find()->select(['groups_id'])->where(['LIKE', 'groups_name', $output['group']])->as_array()->all(), 'groups_id', 'groups_id');
            $filter_group = "(c.groups_id IN('" . implode("','", $_filter_group_ids) . "') ";
            /** @var \common\extensions\ExtraGroups\ExtraGroups $extraGroups */
            if ($extra_groups = \common\helpers\Acl::check_extension('ExtraGroups', 'allowed')) {
                if ($extra_groups::allowed()) {
                    $filter_group .= " or exists (select * from customer_extra_groups ceg, groups g1 where g1.groups_name like '%" . tep_db_input($output['group']) . "%' and ceg.group_id=g1.groups_id and c.customers_id=ceg.customer_id)";
                }
            }
            $filter_group .= ')';
            $customers_query->and_where($filter_group);
        }
        if ($cf_ext = \common\helpers\Acl::check_extension_allowed('CustomerFlag')) {
            $cf_ext::filter_customer_query($customers_query, $output);
        }
        if (tep_not_null($output['country'])) {
            $_join_address_book = true;
            $_need_countries = \yii\helpers\Array_Helper::map(\common\models\Countries::find()->select('countries_id')->distinct()->where(['like', 'countries_name', $output['country']])->as_array()->all(), 'countries_id', 'countries_id');
            if (count($_need_countries) == 0) {
                $_need_countries = [-111];
            }
            $customers_query->and_where(['IN', 'a.entry_country_id', $_need_countries]);
        }
        if (isset($output['state']) && !empty($output['state'])) {
            $_join_zones = true;
            $_join_address_book = true;
            $customers_query->and_where("(a.entry_state like '%" . tep_db_input($output['state']) . "%' or z.zone_name like '%" . tep_db_input($output['state']) . "%')");
        }
        if (isset($output['city']) && !empty($output['city'])) {
            $_join_address_book = true;
            $customers_query->and_where("a.entry_city like '%" . tep_db_input($output['city']) . "%'");
        }
        if (isset($output['company']) && !empty($output['company'])) {
            $_join_address_book = true;
            $customers_query->and_where("a.entry_company like '%" . tep_db_input($output['company']) . "%'");
        }
        /** @var \common\extensions\Subscribers\Subscribers $subscr  */
        if ($subscr = \common\helpers\Acl::check_extension_allowed('Subscribers', 'allowed')) {
            if (isset($output['newsletter'])) {
                switch ($output['newsletter']) {
                    case 's':
                        $customers_query->and_where(['exists', (new \yii\db\Query())->from(['subscr' => 'subscribers'])->and_where('c.customers_id=subscr.customers_id and all_lists = 1')]);
                        //$customersQuery->andWhere("c.customers_newsletter='1'");
                        /*$customersQuery->andWhere([ 'or',
                          ['exists',
                          (new \yii\db\Query())->from (['s2l' => CustomersToLists::tableName()])
                            ->andWhere(['s2l.subscribers_lists_id' => $output['newsletter'] ])
                            ->andWhere('c.customers_id=s2l.customers_id')
                          ],
                          ['exists',
                          (new \yii\db\Query())->from (['subscr' => 'subscribers'])
                            ->andWhere('c.customers_id=subscr.customers_id')
                          ]
                          ]);*/
                        break;
                    case 'ns':
                        $customers_query->left_join('subscribers subscr', 'c.customers_id=subscr.customers_id and all_lists = 1 ')->and_where('subscr.customers_id is null');
                        //$customersQuery->andWhere("c.customers_newsletter='0'");
                        break;
                    default:
                        if ($output['newsletter'] > 0) {
                            $customers_query->and_where(['exists', (new \yii\db\Query())->from(['s2l' => Customers_To_Lists::table_name()])->and_where(['s2l.subscribers_lists_id' => $output['newsletter']])->and_where('c.customers_id=s2l.customers_id')]);
                        }
                        break;
                }
            }
        }
        if (isset($output['status'])) {
            switch ($output['status']) {
                case 'y':
                    $customers_query->and_where("c.customers_status = '1'");
                    break;
                case 'n':
                    $customers_query->and_where("c.customers_status = '0'");
                    break;
                default:
                    break;
            }
        }
        if (isset($output['guest'])) {
            switch ($output['guest']) {
                case 'y':
                    $customers_query->and_where("c.opc_temp_account = '1'");
                    break;
                case 'n':
                    $customers_query->and_where("c.opc_temp_account = '0'");
                    break;
                default:
                    break;
            }
        }
        if (isset($output['date'])) {
            switch ($output['date']) {
                case 'exact':
                    if (isset($output['from']) && !empty($output['from'])) {
                        $from = tep_db_prepare_input($output['from']);
                        $_join_customer_info = true;
                        $customers_query->and_where("to_days(ci.customers_info_date_account_created) >= to_days('" . \common\helpers\Date::prepare_input_date($from) . "')");
                    }
                    if (isset($output['to']) && !empty($output['to'])) {
                        $to = tep_db_prepare_input($output['to']);
                        $_join_customer_info = true;
                        $customers_query->and_where(" to_days(ci.customers_info_date_account_created) <= to_days('" . \common\helpers\Date::prepare_input_date($to) . "')");
                    }
                    break;
                case 'presel':
                    if (isset($output['interval'])) {
                        switch ($output['interval']) {
                            case 'week':
                                $customers_query->and_where("ci.customers_info_date_account_created >= '" . date('Y-m-d', strtotime('monday this week')) . "'");
                                $_join_customer_info = true;
                                break;
                            case 'month':
                                $customers_query->and_where("ci.customers_info_date_account_created >= '" . date('Y-m-d', strtotime('first day of this month')) . "'");
                                $_join_customer_info = true;
                                break;
                            case 'year':
                                $customers_query->and_where("ci.customers_info_date_account_created >= '" . date('Y') . '-01-01' . "'");
                                $_join_customer_info = true;
                                break;
                            case '1':
                                $customers_query->and_where("ci.customers_info_date_account_created >= '" . date('Y-m-d') . "'");
                                $_join_customer_info = true;
                                break;
                            case '3':
                            case '7':
                            case '14':
                            case '30':
                                $customers_query->and_where('ci.customers_info_date_account_created >= date_sub(now(), interval ' . (int) $output['interval'] . ' day)');
                                $_join_customer_info = true;
                                break;
                        }
                    }
                    break;
            }
        }
        if (isset($output['title'])) {
            switch ($output['title']) {
                case 'm':
                case 'f':
                case 's':
                    $customers_query->and_where("c.customers_gender = '" . tep_db_input($output['title']) . "'");
                    break;
                default:
                    break;
            }
        }
        if ($_join_address_book || $_join_zones) {
            $customers_query->left_join('address_book a', 'a.customers_id=c.customers_id');
            $customers_query->group_by('c.customers_id');
        }
        if ($_join_zones) {
            $customers_query->left_join(TABLE_ZONES . ' z', 'z.zone_country_id=a.entry_country_id and a.entry_zone_id=z.zone_id');
        }
        $customers_query->order_by(['c.customers_lastname' => SORT_ASC, 'c.customers_firstname' => SORT_ASC]);
        if (isset($_GET['order'][0]['column']) && $_GET['order'][0]['dir']) {
            $_sort_dir = strtolower($_GET['order'][0]['dir']) == 'desc' ? SORT_DESC : SORT_ASC;
            switch ($_GET['order'][0]['column']) {
                case 1:
                    $customers_query->order_by(['c.customers_lastname' => $_sort_dir]);
                    break;
                case 2:
                    $customers_query->order_by(['c.customers_firstname' => $_sort_dir]);
                    break;
                case 3:
                    $customers_query->order_by(['c.customers_email_address' => $_sort_dir]);
                    break;
                case 6:
                    $customers_query->order_by(['cstat.total_orders' => $_sort_dir, 'ci.customers_info_date_account_created' => $_sort_dir]);
                    $customers_query->join('left join', '(select ostat.customers_id, count(*) as total_orders from orders ostat group by ostat.customers_id) cstat', 'cstat.customers_id=c.customers_id');
                    $_join_customer_info = true;
                    break;
                case 7:
                    $customers_query->order_by(['cstat.amount_ordered' => $_sort_dir, 'ci.customers_info_date_account_created' => $_sort_dir]);
                    $customers_query->join('left join', '(select ostat.customers_id, sum(IF(otstat.currency_value=0,1,otstat.currency_value)*otstat.value) as amount_ordered from orders ostat inner join orders_total otstat on otstat.orders_id=ostat.orders_id group by ostat.customers_id) cstat', 'cstat.customers_id=c.customers_id');
                    $_join_customer_info = true;
                    break;
                case 8:
                    $customers_query->order_by(['cstat.last_purchased' => $_sort_dir, 'ci.customers_info_date_account_created' => $_sort_dir]);
                    $customers_query->join('left join', '(select ostat.customers_id, max(ostat.date_purchased) as last_purchased from orders ostat group by ostat.customers_id) cstat', 'cstat.customers_id=c.customers_id');
                    $_join_customer_info = true;
                    break;
                case 4:
                default:
                    $customers_query->order_by(['ci.customers_info_date_account_created' => strtolower($_GET['order'][0]['dir']) == 'desc' ? SORT_DESC : SORT_ASC]);
                    $_join_customer_info = true;
                    break;
            }
        }
        if ($_join_customer_info) {
            $customers_query->left_join(['ci' => TABLE_CUSTOMERS_INFO], 'c.customers_id=ci.customers_info_id');
        }
        $customers_query->select(['c.customers_id', 'c.platform_id', 'c.departments_id', 'c.customers_default_address_id']);
        foreach (\common\helpers\Hooks::get_list('customers/customerlist') as $filename) {
            include $filename;
        }
        //echo $customersQuery->createCommand()->getRawSql()."\n\n";
        $customers_query_numrows = $customers_query->count();
        $customers_query->limit($length)->offset($start);
        $customers_all = $customers_query->as_array()->all();
        $current_page_number = $start / $length + 1;
        // {{ attach page info
        if (count($customers_all) > 0) {
            $_page_customer_ids = array_map(function ($row) {
                return $row['customers_id'];
            }, $customers_all);
            $_page_customer_id_to_idx = array_flip($_page_customer_ids);
            $_fill_in_data = \common\models\Customers::find()->select(['customers_id', 'customers_gender', 'customers_lastname', 'customers_firstname', 'customers_email_address', 'customers_status', 'c.groups_id', 'g.groups_name', 'opc_temp_account', 'date_account_created' => 'ci.customers_info_date_account_created'])->alias('c')->left_join(['ci' => TABLE_CUSTOMERS_INFO], 'c.customers_id=ci.customers_info_id')->left_join(['g' => TABLE_GROUPS], 'c.groups_id=g.groups_id')->where(['IN', 'customers_id', array_keys($_page_customer_id_to_idx)])->as_array()->all();
            foreach ($_fill_in_data as $_fill_in_row) {
                $__idx = $_page_customer_id_to_idx[$_fill_in_row['customers_id']];
                $customers_all[$__idx] = array_merge($customers_all[$__idx], $_fill_in_row);
            }
            $_page_customer_default_ab_ids = array_map(function ($row) {
                return $row['customers_default_address_id'];
            }, $customers_all);
            $_page_customer_default_ab_ids = array_flip($_page_customer_default_ab_ids);
            foreach (\common\models\Address_Book::find()->select(['address_book_id', 'entry_country_id', 'entry_postcode', 'entry_firstname', 'entry_lastname', 'entry_street_address', 'entry_city', 'state' => new \yii\db\Expression('IF(LENGTH(a.entry_state), a.entry_state, z.zone_name)'), 'country' => 'cn.countries_name'])->alias('a')->left_join(['cn' => TABLE_COUNTRIES], "a.entry_country_id=cn.countries_id  and cn.language_id = '" . (int) $languages_id . "'")->left_join(['z' => TABLE_ZONES], 'z.zone_country_id=a.entry_country_id and a.entry_zone_id=z.zone_id')->where(['IN', 'address_book_id', array_keys($_page_customer_default_ab_ids)])->as_array()->all() as $default_address) {
                $__idx = $_page_customer_default_ab_ids[$default_address['address_book_id']];
                $customers_all[$__idx] = array_merge($customers_all[$__idx], $default_address);
            }
            // order stat
            $exclude_order_statuses_array = \common\helpers\Order::extract_statuses(DASHBOARD_EXCLUDE_ORDER_STATUSES);
            $info_query = tep_db_query('select o.customers_id, count(*) as total_orders, max(o.date_purchased) as last_purchased, ' . '  sum(ot.value) as total_sum, ot.class ' . 'from ' . TABLE_ORDERS . ' o ' . '  left join ' . TABLE_ORDERS_TOTAL . ' ot on (o.orders_id = ot.orders_id) ' . 'where ' . (USE_MARKET_PRICES == 'True' ? "o.currency = '" . \Yii::$app->settings->get('currency') . "'" : '1') . ' ' . "  and ot.class='ot_total' and o.customers_id IN ('" . implode("','", array_keys($_page_customer_id_to_idx)) . "') " . "  and o.orders_status not in ('" . implode("','", $exclude_order_statuses_array) . "') " . 'group by o.customers_id');
            if (tep_db_num_rows($info_query) > 0) {
                while ($info = tep_db_fetch_array($info_query)) {
                    $__idx = $_page_customer_id_to_idx[$info['customers_id']];
                    $customers_all[$__idx]['statInfo'] = $info;
                }
            }
            if ($cf_ext = \common\helpers\Acl::check_extension_allowed('CustomerFlag')) {
                $cf_ext::fill_customer_listing($customers_all);
            }
        }
        // }} attach page info
        $response_list = [];
        //while ($customers = tep_db_fetch_array($customers_query)) {
        foreach ($customers_all as $customers) {
            $customers['groups_name'] = $customers['groups_id'] ? \common\helpers\Group::get_user_group_name($customers['groups_id']) : '';
            $info = isset($customers['statInfo']) ? $customers['statInfo'] : ['total_orders' => 0, 'total_sum' => 0, 'last_purchased' => ''];
            if (trim($search) != '') {
                $hilite_function = function ($search, $text) {
                    $w = preg_quote(trim($search), '/');
                    $regexp = "/({$w})(?![^<]+>)/i";
                    $replacement = '<b style="color:#ff0000">\1</b>';
                    return preg_replace($regexp, $replacement, $text);
                };
            } else {
                $hilite_function = function ($search, $text) {
                    return $text;
                };
            }
            //------
            if (defined('SUPERADMIN_ENABLED') && SUPERADMIN_ENABLED == true) {
                $department_info = $customers['departments_id'] > 0 ? '<b>' . TABLE_HEADING_DEPARTMENT . ':</b>&nbsp;' . $departments[$customers['departments_id']] : '';
            } else {
                $department_info = \common\classes\platform::is_multi() >= 1 ? '<b>' . TABLE_HEADING_PLATFORM . ':</b>&nbsp;' . \common\classes\platform::name($customers['platform_id']) : '';
            }
            $department_info = '<b class="customer-group" ' . (strlen($customers['groups_name']) > 30 ? ' title="' . $customers['groups_name'] . '"' : '') . '>' . substr($customers['groups_name'], 0, 30) . (strlen($customers['groups_name']) > 30 ? '...' : '') . '</b></br>' . $department_info;
            $response_list[] = ['<input type="checkbox" class="uniform">' . '<input class="cell_identify" type="hidden" value="' . $customers['customers_id'] . '">', ($customers['opc_temp_account'] == 1 ? '<i style="color: #03a2a0;">' . TEXT_GUEST . '</i><br>' : '') . '<div class="c-list-name ord-gender click_double ord-gender-' . $customers['customers_gender'] . '" data-click-double="' . \Yii::$app->url_manager->create_url(['customers/customeredit', 'customers_id' => $customers['customers_id']]) . '">' . $hilite_function($search, Html::encode($customers['customers_lastname'])) . '<input class="cell_identify" type="hidden" value="' . $customers['customers_id'] . '"></div>', '<div class="c-list-name click_double"  data-click-double="' . \Yii::$app->url_manager->create_url(['customers/customeredit', 'customers_id' => $customers['customers_id']]) . '">' . $hilite_function($search, Html::encode($customers['customers_firstname'])) . '</div>', '<div class="click_double" data-click-double="' . \Yii::$app->url_manager->create_url(['customers/customeredit', 'customers_id' => $customers['customers_id']]) . '"><a class="ord-name-email" href="mailto:' . $customers['customers_email_address'] . '"><b' . (strlen($customers['customers_email_address']) > 30 ? ' title="' . Html::encode($customers['customers_email_address']) . '"' : '') . '>' . $hilite_function($search, substr($customers['customers_email_address'], 0, 30)) . (strlen($customers['customers_email_address']) > 30 ? '...' : '') . '</b></a><br>' . $department_info . '</div>', '<div class="click_double" data-click-double="' . \Yii::$app->url_manager->create_url(['customers/customeredit', 'customers_id' => $customers['customers_id']]) . '">' . \common\helpers\Date::date_short($customers['date_account_created']) . '</div>', '<div class="ord-location click_double" data-click-double="' . \Yii::$app->url_manager->create_url(['customers/customeredit', 'customers_id' => $customers['customers_id']]) . '">' . $hilite_function($search, Html::encode($customers['entry_postcode'] ?? null)) . '<div class="ord-total-info ord-location-info"><div class="ord-box-img"></div><b>' . Html::encode(($customers['entry_firstname'] ?? null) . ' ' . ($customers['entry_lastname'] ?? null)) . '</b>' . Html::encode($customers['entry_street_address'] ?? null) . '<br>' . Html::encode(($customers['entry_city'] ?? null) . ', ' . ($customers['state'] ?? null)) . '&nbsp;' . Html::encode($customers['entry_postcode'] ?? null) . '<br>' . ($customers['country'] ?? null) . '</div></div>', '<div class="c-list-count click_double" data-click-double="' . \Yii::$app->url_manager->create_url(['customers/customeredit', 'customers_id' => $customers['customers_id']]) . '">' . $info['total_orders'] . '</div>', '<div class="c-list-total click_double" data-click-double="' . \Yii::$app->url_manager->create_url(['customers/customeredit', 'customers_id' => $customers['customers_id']]) . '">' . $currencies->format($info['total_sum']) . '</div>', '<div class="c-list-date-last click_double" data-click-double="' . \Yii::$app->url_manager->create_url(['customers/customeredit', 'customers_id' => $customers['customers_id']]) . '"><span>' . \common\helpers\Date::datetime_short($info['last_purchased']) . '</span>' . \common\helpers\Date::get_date_range(date('Y-m-d'), $info['last_purchased']) . '</div>'];
            if (!$customers['customers_status']) {
                $response_list[count($response_list) - 1]['DT_RowClass'] = 'dis_module';
            }
            if ($cf_ext = \common\helpers\Acl::check_extension_allowed('CustomerFlag')) {
                $markers = \yii\helpers\Array_Helper::index($cf_ext::markers_list(), 'id');
                $colored_row = '';
                if (isset($customers['markers']) && isset($markers[$customers['markers']])) {
                    $colored_row = $markers[$customers['markers']]['color'];
                }
                $flags = \yii\helpers\Array_Helper::index($cf_ext::flags_list(), 'id');
                $paint = '<div class="fa-paint-brush" onclick="sendCustomerMarker(' . (int) $customers['customers_id'] . ', ' . (int) ($customers['markers'] ?? 0) . ')"></div>';
                if (isset($customers['flags']) && isset($flags[$customers['flags']])) {
                    $flag_cell = '<div class="fa-flag" style="' . $flags[$customers['flags']]['style'] . ';" onclick="sendCustomerFlag(' . (int) $customers['customers_id'] . ', ' . (int) $customers['flags'] . ')"></div>' . $paint;
                } else {
                    $flag_cell = '<div class="fa-flag-o" onclick="sendCustomerFlag(' . (int) $customers['customers_id'] . ')"></div>' . $paint;
                }
                if ($colored_row) {
                    $flag_cell .= '<input class="row_colored" type="hidden" value="' . $colored_row . '">';
                }
                array_splice($response_list[count($response_list) - 1], 1, 0, $flag_cell);
            }
        }
        $response = ['draw' => $draw, 'recordsTotal' => $customers_query_numrows, 'recordsFiltered' => $customers_query_numrows, 'data' => $response_list];
        echo json_encode($response);
        //die();
    }
    public function action_customeractions()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/customers');
        $message_stack = \Yii::$container->get('message_stack');
        $currencies = Yii::$container->get('currencies');
        $this->layout = false;
        $customers_id = Yii::$app->request->post('customers_id');
        $customers = \common\models\Customers::find()->and_where(['customers_id' => $customers_id])->with(['defaultAddress', 'info', 'group'])->as_array()->one();
        if (!is_array($customers)) {
            die('Wrong customer data.');
        }
        $exclude_order_statuses_array = \common\helpers\Order::extract_statuses(DASHBOARD_EXCLUDE_ORDER_STATUSES);
        $orders_query = tep_db_query('select count(*) as total_orders, max(o.date_purchased) as last_purchased, sum(ot.value) as total_sum, ot.class from ' . TABLE_ORDERS . ' o left join ' . TABLE_ORDERS_TOTAL . ' ot on (o.orders_id = ot.orders_id) where ' . (USE_MARKET_PRICES == 'True' ? "o.currency = '" . \Yii::$app->settings->get('currency') . "'" : '1') . " and ot.class='ot_total' and o.customers_id = " . (int) $customers['customers_id'] . "  AND o.orders_status not in ('" . implode("','", $exclude_order_statuses_array) . "') ");
        $orders = tep_db_fetch_array($orders_query);
        if (!is_array($orders)) {
            $orders = [];
        }
        $reviews_query = tep_db_query('select count(*) as number_of_reviews from ' . TABLE_REVIEWS . " where customers_id = '" . (int) $customers['customers_id'] . "'");
        $reviews = tep_db_fetch_array($reviews_query);
        if (!is_array($reviews)) {
            $reviews = [];
        }
        $customer_info = array_merge($reviews, $orders);
        $c_info_array = array_merge($customers, $customer_info);
        $c_info = json_decode(json_encode($c_info_array));
        //echo "#### <PRE>" .print_r($cInfo, 1) ."</PRE>"; die;
        if ($message_stack->size() > 0) {
            if (\Yii::$app->request->get('read') == 'only') {
            } else {
                echo $message_stack->output();
            }
        }
        echo '<div class="or_box_head">' . Html::encode($c_info->customers_firstname . ' ' . $c_info->customers_lastname) . '</div>';
        if (!empty($c_info->customers_company) || !empty($c_info->default_address->entry_company)) {
            echo '<div class="row_or_wrap text-center strong">' . Html::encode($c_info->default_address->entry_company . (empty($c_info->default_address->entry_company) ? ' ' . $c_info->customers_company : '')) . '</div>';
        }
        echo '<div class="row_or_wrapp">';
        if (!empty($c_info->group->groups_name)) {
            echo '<div class="row_or"><div>' . ENTRY_GROUP . '</div><div>' . $c_info->group->groups_name . '</div></div>';
        }
        echo '<div class="row_or"><div>' . TEXT_TOTAL_ORDERED . '</div><div>' . $currencies->format($c_info->total_sum) . '</div></div>';
        echo '<div class="row_or"><div>' . TEXT_ORDER_COUNT . '</div><div>' . $c_info->total_orders . '</div></div>';
        echo '<div class="row_or">
					<div>' . TEXT_DATE_ACCOUNT_CREATED . '</div>
					<div>' . \common\helpers\Date::date_short($c_info->info->customers_info_date_account_created ?? null) . '</div>
				</div>';
        /* echo '<div class="update_password">
           <div class="update_password_title">Update customers password:</div>
           <div class="update_password_content"><form name="passw_form" action="' . tep_href_link(FILENAME_CUSTOMERS, \common\helpers\Output::get_all_get_params(array('cID', 'action')) . 'cID=' . $cInfo->customers_id . '&action=password') . '" method="post" onsubmit="return check_passw_form('.(int)ENTRY_PASSWORD_MIN_LENGTH.');"><input type="hidden" name="cID" value="'.$cInfo->customers_id.'"><input type="text" name="change_pass" class="form-control" size="16" placeholder="New password"><input type="submit" value="Update Password" class="btn"></form></div>
           </div>'; */
        echo '<div class="row_or">
					<div>' . TEXT_DATE_ACCOUNT_LAST_MODIFIED . '</div>
					<div>' . \common\helpers\Date::date_short($c_info->info->customers_info_date_account_last_modified ?? null) . '</div>
				</div>';
        echo '<div class="row_or">
					<div>' . TEXT_INFO_DATE_LAST_LOGON . '</div>
					<div>' . \common\helpers\Date::date_short($c_info->info->customers_info_date_of_last_logon ?? null) . '</div>
				</div>';
        echo '<div class="row_or">
					<div>' . TEXT_INFO_COUNTRY . '</div>
					<div>' . ($c_info->default_address->country->countries_name ?? null) . '</div>
				</div>';
        echo '<div class="row_or">
					<div>' . TEXT_INFO_NUMBER_OF_LOGONS . '</div>
					<div>' . ($c_info->info->customers_info_number_of_logons ?? null) . '</div>
				</div>';
        echo '<div class="row_or">
					<div>' . TEXT_INFO_NUMBER_OF_REVIEWS . '</div>
					<div>' . ($c_info->number_of_reviews ?? null) . '</div>
				</div>';
        echo '</div>';
        echo '<div class="btn-toolbar btn-toolbar-order">';
        if (\common\helpers\Acl::rule(['ACL_ORDER', 'IMAGE_NEW'])) {
            echo '<a href="' . \Yii::$app->url_manager->create_url(['editor/create-order', 'customers_id' => $c_info->customers_id, 'back' => 'customers']) . '" class="btn btn-primary btn-process-order btn-process-order-cus">' . TEXT_CREATE_NEW_OREDER . '</a>';
        }
        if (\common\helpers\Acl::rule(['ACL_CUSTORER', 'IMAGE_EDIT'])) {
            echo '<a class="btn btn-edit btn-no-margin" href="' . \Yii::$app->url_manager->create_url(['customers/customeredit', 'customers_id' => $c_info->customers_id]) . '">' . IMAGE_EDIT . '</a>';
        }
        if (\common\helpers\Acl::rule(['ACL_CUSTORER', 'IMAGE_DELETE'])) {
            echo '<button class="btn btn-delete" onclick="confirmDeleteCustomer(' . $c_info->customers_id . ')">' . IMAGE_DELETE . '</button>';
        }
        echo '<a class="btn btn-no-margin btn-ord-cus" href="' . \Yii::$app->url_manager->create_url(['orders/', 'by' => 'cID', 'search' => $c_info->customers_id]) . '">' . IMAGE_ORDERS . '</a><a class="btn btn-email-cus" href="mailto:' . $c_info->customers_email_address . '">' . IMAGE_EMAIL . '</a>';
        if (\common\helpers\Acl::rule(['ACL_CUSTORER', 'T_SEND_COUPON'])) {
            echo '<a href="' . \Yii::$app->url_manager->create_url(['gv_mail/index', 'type' => 'C', 'customer' => $c_info->customers_email_address, 'only' => $c_info->customers_id]) . '" class="btn btn-no-margin btn-coup-cus popup">' . T_SEND_COUPON . '</a>';
        }
        foreach (\common\helpers\Hooks::get_list('customers/customeractions') as $filename) {
            include $filename;
        }
        if (extension_loaded('openssl') && (\common\helpers\Acl::rule(['SUPERUSER']) || \common\helpers\Acl::rule(['ACL_CUSTORER', 'T_SUPER_LOGIN']))) {
            $aup = \common\helpers\Password::encrypt_auth_user_param($c_info->customers_id, $c_info->customers_email_address, 'login', $c_info->auth_key);
            $_active_platform_id = Yii::$app->get('platform')->config()->get_id();
            $per_platform_login_list = [];
            foreach (\common\classes\platform::get_list(false) as $platform) {
                Yii::$app->get('platform')->config($platform['id']);
                $per_platform_login_list[] = ['href' => tep_catalog_href_link('account/login-me', 'aup=' . $aup . '&idf=' . (int) $_SESSION['login_id']), 'name' => $platform['text']];
            }
            Yii::$app->get('platform')->config($_active_platform_id);
            $super_login_button = '';
            if (count($per_platform_login_list) > 0) {
                if (count($per_platform_login_list) == 1) {
                    $super_login_button = Html::a('Super login', $per_platform_login_list[0]['href'], ['target' => '_blank', 'class' => 'btn btn-no-margin btn-coup-cus']);
                } else {
                    $super_login_button = '<div class="dropdown"><button class="btn btn-pass-cus dropdown-toggle" type="button" id="customerSuperLoginMenu" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="true">' . T_SUPER_LOGIN . '</button>';
                    $super_login_button .= '<ul class="dropdown-menu" aria-labelledby="customerSuperLoginMenu">';
                    foreach ($per_platform_login_list as $per_platform_login) {
                        $super_login_button .= '<li>' . Html::a($per_platform_login['name'], $per_platform_login['href'], ['target' => '_blank', 'class' => 'dropdown-item']) . '</li>';
                    }
                    $super_login_button .= '</ul>';
                    $super_login_button .= '</div>';
                }
            }
            echo $super_login_button;
        }
        echo '</div>';
        if (\common\helpers\Acl::rule(['ACL_CUSTORER', 'T_UPDATE_PASS'])) {
            $title_data_pattern = sprintf(ENTRY_PASSWORD_ERROR, ENTRY_PASSWORD_MIN_LENGTH);
            $pass_data_pattern = '.{' . ENTRY_PASSWORD_MIN_LENGTH . '}';
            if (defined('PASSWORD_STRONG_REQUIRED')) {
                if (PASSWORD_STRONG_REQUIRED == 'ULNS') {
                    $title_data_pattern = sprintf(ENTRY_PASSWORD_ULNS_ERROR, ENTRY_PASSWORD_MIN_LENGTH);
                    $pass_data_pattern = addslashes('(?=.*\d)(?=.*\W+)(?=.*[a-z])(?=.*[A-Z]).{' . ENTRY_PASSWORD_MIN_LENGTH . '}');
                } elseif (PASSWORD_STRONG_REQUIRED == 'ULN') {
                    $title_data_pattern = sprintf(ENTRY_PASSWORD_ULN_ERROR, ENTRY_PASSWORD_MIN_LENGTH);
                    $pass_data_pattern = addslashes('(?=.*\d)(?=.*[a-z])(?=.*[A-Z]).{' . ENTRY_PASSWORD_MIN_LENGTH . '}');
                }
            }
            echo '<div class="btn-toolbar btn-toolbar-order btn-toolbar-pass"><span class="btn btn-pass-cus js-update-customer-pass">' . T_UPDATE_PASS . '</span>
                                <script>
                                $(document).ready(function() {
                                $("a.popup").popUp();
                                $(".js-update-customer-pass").on("click", function(){
                                    alertMessage("<div class=\"popup-heading popup-heading-pass\">' . TEXT_UPDATE_PASSWORD_FOR . ' ' . Html::encode($c_info->customers_firstname) . '&nbsp;' . Html::encode($c_info->customers_lastname) . '</div><div class=\"popup-content\"><form name=\"passw_form\" id=\"passw_form\" action=\"' . tep_href_link('customers', \common\helpers\Output::get_all_get_params(['cID', 'action']) . 'cID=' . $c_info->customers_id . '&action=password') . '\" method=\"post\"><table cellspacing=\"0\" cellpadding=\"0\" width=\"100%\"><tr><td class=\"dataTableContent\"><a href=\"#\" class=\"generate_password\">' . TEXT_GENERATE_PASSWORD . '</a></td></tr><tr><td class=\"dataTableContent\">' . T_NEW_PASS . ':</td><td class=\"dataTableContent\"><input type=\"password\" data-required=\"' . $title_data_pattern . '\" data-pattern=\"' . $pass_data_pattern . '\" name=\"change_pass\" class=\"form-control\"></td></tr></table><div class=\"btn-bar\" style=\"padding-bottom: 0;\"><div class=\"btn-left\"><span class=\"btn btn-cancel\">' . IMAGE_CANCEL . '</span></div><div class=\"btn-right\"><input type=\"submit\" value=\"' . IMAGE_UPDATE . '\" class=\"btn btn-primary\"></div></div><input type=\"hidden\" name=\"cID\" value=\"' . $c_info->customers_id . '\"></form></div>");
                                    passFormAfretShow();
                                });
                                });
                                </script>
                                </div>';
        }
    }
    public function action_customeredit()
    {
        \common\helpers\Translation::init('admin/customers');
        $currencies = Yii::$container->get('currencies');
        $message_stack = \Yii::$container->get('message_stack');
        if (Yii::$app->request->is_post) {
            $customers_id = Yii::$app->request->post('customers_id');
        } else {
            $customers_id = Yii::$app->request->get('customers_id');
        }
        $customer_form = new Customer_Registration(['scenario' => Customer_Registration::SCENARIO_EDIT, 'shortName' => Customer_Registration::SCENARIO_EDIT]);
        $customer_form->use_extending = true;
        $my_promos = [];
        $this->top_buttons[] = '<span class="btn btn-confirm" onclick="$(\'#customer_management_data .btn-confirm\').trigger(\'click\')"><i class="icon-ticket"></i>' . IMAGE_CONFIRM . '</span>';
        if ($customers_id && $c_info = Customer::find_one($customers_id)) {
            if (!\common\helpers\Acl::rule(['ACL_CUSTORER', 'IMAGE_EDIT'])) {
                return $this->redirect(['customers/']);
            }
            $exclude_order_statuses_array = \common\helpers\Order::extract_statuses(DASHBOARD_EXCLUDE_ORDER_STATUSES);
            $orders_query = tep_db_query('select count(*) as total_orders, max(o.date_purchased) as last_purchased, sum(ot.value) as total_sum, ot.class from ' . TABLE_ORDERS . ' o left join ' . TABLE_ORDERS_TOTAL . ' ot on (o.orders_id = ot.orders_id) where ' . (USE_MARKET_PRICES == 'True' ? "o.currency = '" . \Yii::$app->settings->get('currency') . "'" : '1') . " and ot.class='ot_total' and o.customers_id = " . (int) $customers_id . "  AND o.orders_status not in ('" . implode("','", $exclude_order_statuses_array) . "') ");
            $orders = tep_db_fetch_array($orders_query);
            /** @var \common\extensions\Subscribers\Subscribers $subscr  */
            if ($subscr = \common\helpers\Acl::check_extension_allowed('Subscribers', 'allowed')) {
                $lists = \yii\helpers\Array_Helper::map($c_info->subscribers_lists, 'subscribers_lists_id', 'name');
                $c_info->set('subscribers_lists', $lists);
            }
            $reviews = tep_db_fetch_array(tep_db_query('select count(*) as total_reviews from reviews where customers_id=' . $c_info->customers_id));
            $c_info->set('total_reviews', $reviews['total_reviews']);
            $c_info->set('total_orders', $orders['total_orders']);
            $c_info->set('last_purchased', \common\helpers\Date::date_short($orders['last_purchased']));
            $c_info->set('last_purchased_days', \common\helpers\Date::get_date_range(date('Y-m-d'), $orders['last_purchased']));
            $c_info->set('total_sum', $currencies->format($orders['total_sum']));
            $str_full_head = Html::encode($c_info->customers_firstname) . '&nbsp;' . Html::encode($c_info->customers_lastname);
            if (strlen($str_full_head) > 22) {
                $st_full_name = mb_substr($str_full_head, 0, 22);
                $st_full_name .= '...';
                $st_full_name_view = '<span title="' . Html::encode($str_full_head) . '">' . $st_full_name . '</span>';
            } else {
                $st_full_name_view = $str_full_head;
            }
            $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('customers/customeredit'), 'title' => T_EDITING_CUS . '&nbsp;"' . $st_full_name_view . '"'];
            $this->view->heading_title = T_EDITING_CUS;
            if (\common\helpers\Acl::rule(['ACL_ORDER', 'IMAGE_NEW'])) {
                $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url(['editor/create-order', 'customers_id' => $c_info->customers_id, 'back' => 'orders']) . '" class="btn btn-primary"><i class="icon-file-text"></i>' . TEXT_CREATE_NEW_OREDER . '</a>';
            }
            if (\common\helpers\Acl::rule(['ACL_CUSTORER', 'T_SEND_COUPON'])) {
                $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url(['gv_mail/index', 'type' => 'C', 'customer' => $c_info->customers_email_address, 'only' => $c_info->customers_id]) . '" class="btn btn-primary popup"><i class="icon-ticket"></i>' . T_SEND_COUPON . '</a>';
            }
        } else {
            if (!\common\helpers\Acl::rule(['ACL_CUSTORER', 'IMAGE_NEW'])) {
                return $this->redirect(['customers/']);
            }
            $c_info = new Customer();
            $c_info->customers_status = 1;
            $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('customers/customeredit'), 'title' => TEXT_ADD_NEW_CUSTOMER];
            $this->view->heading_title = TEXT_ADD_NEW_CUSTOMER;
        }
        $customer_form->preload_customers_data($c_info);
        $c_info->set('view_credit_amount', $currencies->format($c_info->credit_amount));
        $c_info->set('credit_amount_mask', $currencies->format(0));
        $discount = \common\helpers\Customer::get_additional_discount($c_info->groups_id, $c_info->customers_id);
        $group = \common\models\Groups::find_one($c_info->groups_id);
        if ($group) {
            $discount += $group->groups_discount;
        }
        $c_info->set('discount', $discount);
        $addresses = [];
        foreach ($c_info->get_address_books() as $a_book) {
            $form = new Address_Form(['scenario' => Address_Form::CUSTOM_ADDRESS]);
            $form->preload($a_book);
            $addresses[$a_book->address_book_id] = $form;
        }
        if (count($addresses) < MAX_ADDRESS_BOOK_ENTRIES) {
            $addresses[0] = new Address_Form(['scenario' => Address_Form::CUSTOM_ADDRESS]);
        }
        if (Yii::$app->request->is_post) {
            if (\common\helpers\Acl::check_extension_allowed('ReportChangesHistory')) {
                $logger = new \common\extensions\Report_Changes_History\classes\Logger();
                $before_object = new \common\api\Classes\Customer();
                $before_object->load($c_info->customers_id);
                $logger->set_before_object($before_object);
                unset($before_object);
            }
            $customer_form->load(Yii::$app->request->post());
            $customer_form->validate();
            $customer_form->check_pin('pin', $c_info->customers_id);
            $customer_form->email_unique('email_address', ['customers_id' => $c_info->customers_id]);
            $c_valid = !$customer_form->has_errors();
            $has_errors = !$c_valid;
            if ($c_valid) {
                $c_info->update_customer($customer_form->get_attributes_by_scenario());
                $c_info->add_customers_info();
            } else {
                foreach ($customer_form->get_errors() as $error) {
                    $message_stack->add(is_array($error) ? implode('<br>', $error) : $error, 'account', 'danger');
                }
            }
            $data = Yii::$app->request->post('Custom_address');
            if ($ext = \common\helpers\Acl::check_extension_allowed('SplitCustomerAddresses', 'allowed')) {
                $customers_shipping_address_id = Yii::$app->request->post('customers_shipping_address_id', null);
                $data2 = Yii::$app->request->post('Billing_address');
                if (is_array($data2)) {
                    foreach ($data2 as $idx => $dta) {
                        $data[$idx] = $dta;
                    }
                }
                $data3 = Yii::$app->request->post('Shipping_address');
                if (is_array($data3)) {
                    foreach ($data3 as $idx => $dta) {
                        $data[$idx] = $dta;
                    }
                }
            }
            if ($addresses) {
                $remove = [];
                $customers_default_address_id = Yii::$app->request->post('customers_default_address_id', null);
                foreach ($addresses as $a_book_id => $address) {
                    if (isset($data[$a_book_id])) {
                        $address->load($data, $a_book_id);
                        if ($address->not_empty()) {
                            $address->validate();
                            if (!$address->has_errors() && $c_info->customers_id) {
                                $attributes = $c_info->get_address_from_model($address);
                                if ($a_book_id) {
                                    $a_book = $c_info->update_address($a_book_id, $attributes);
                                } else {
                                    $a_book = $c_info->add_address($attributes);
                                    if ($ext = \common\helpers\Acl::check_extension_allowed('SplitCustomerAddresses', 'allowed')) {
                                        if (!is_null($customers_default_address_id) && !$customers_default_address_id && $a_book->entry_type == \common\forms\Address_Form::BILLING_ADDRESS) {
                                            $customers_default_address_id = $a_book->address_book_id;
                                        }
                                        if (!is_null($customers_shipping_address_id) && !$customers_shipping_address_id && $a_book->entry_type == \common\forms\Address_Form::SHIPPING_ADDRESS) {
                                            $customers_shipping_address_id = $a_book->address_book_id;
                                        }
                                    } else if (!is_null($customers_default_address_id) && !$customers_default_address_id) {
                                        $customers_default_address_id = $a_book->address_book_id;
                                    }
                                }
                            } else {
                                $has_errors = true;
                                foreach ($address->get_errors() as $error) {
                                    $message_stack->add(is_array($error) ? implode('<br>', $error) : $error, 'account', 'danger');
                                }
                            }
                        }
                    } else {
                        $remove[] = $a_book_id;
                    }
                }
                if ($remove) {
                    foreach ($remove as $ab_id) {
                        $c_info->remove_address($ab_id);
                    }
                }
            }
            $ab_ids = \yii\helpers\Array_Helper::get_column($c_info->get_address_books(), 'address_book_id');
            if ($customers_default_address_id && in_array($customers_default_address_id, $ab_ids) && $ab_ids) {
                $c_info->customers_default_address_id = $customers_default_address_id;
                $c_info->save(false);
            }
            if (!in_array($c_info->customers_default_address_id, $ab_ids) && $ab_ids) {
                $c_info->customers_default_address_id = $ab_ids[0];
                $c_info->save(false);
            }
            if ($ext = \common\helpers\Acl::check_extension_allowed('SplitCustomerAddresses', 'allowed')) {
                if ($customers_shipping_address_id && in_array($customers_shipping_address_id, $ab_ids) && $ab_ids) {
                    $c_info->customers_shipping_address_id = $customers_shipping_address_id;
                    $c_info->save(false);
                }
                if (!in_array($c_info->customers_shipping_address_id, $ab_ids) && $ab_ids) {
                    $c_info->customers_shipping_address_id = $ab_ids[0];
                    $c_info->save(false);
                }
            }
            if ($c_info->customers_id) {
                $platform_config = \Yii::$app->get('platform')->config($c_info->platform_id);
                $STORE_OWNER_EMAIL_ADDRESS = $platform_config->const_value('STORE_OWNER_EMAIL_ADDRESS');
                $STORE_OWNER = $platform_config->const_value('STORE_OWNER');
                $credit_amount = number_format(floatval(tep_db_prepare_input(\Yii::$app->request->post('credit_amount'))), 5, '.', '');
                if ($credit_amount > 0) {
                    $currencies = Yii::$container->get('currencies');
                    $credit_prefix = tep_db_prepare_input(\Yii::$app->request->post('credit_prefix'));
                    $comments = tep_db_prepare_input(\Yii::$app->request->post('comments'));
                    $customer_notified = '0';
                    if (\Yii::$app->request->post('notify') == 'on') {
                        $customer_notified = '1';
                        $email_params['STORE_NAME'] = $STORE_OWNER;
                        $email_params['CUSTOMER_FIRSTNAME'] = $c_info->customers_firstname;
                        $email_params['CUSTOMER_LASTNAME'] = $c_info->customers_lastname;
                        $email_params['CREDIT_AMOUNT'] = $credit_prefix . $currencies->format($credit_amount, true, DEFAULT_CURRENCY, $currencies->currencies[DEFAULT_CURRENCY]['value']);
                        $email_params['CREDIT_AMOUNT_COMMENTS'] = $comments;
                        [$email_subject, $email_content] = \common\helpers\Mail::get_parsed_email_template('Credit amount notification', $email_params, $c_info->language_id, $c_info->platform_id);
                        \common\helpers\Mail::send($c_info->customers_firstname . ' ' . $c_info->customers_lastname, $c_info->customers_email_address, $email_subject, $email_content, $STORE_OWNER, $STORE_OWNER_EMAIL_ADDRESS, [], '', '', ['add_br' => 'no']);
                    }
                    $c_info->save_credit_history($c_info->customers_id, $credit_amount, $credit_prefix, DEFAULT_CURRENCY, $currencies->currencies[DEFAULT_CURRENCY]['value'], $comments, 0, $customer_notified);
                    tep_db_query('update ' . TABLE_CUSTOMERS . ' set credit_amount = credit_amount ' . $credit_prefix . ' ' . $credit_amount . ' where customers_id =' . (int) $customers_id);
                }
                // may be called customers/customer-after-save
                foreach (\common\helpers\Hooks::get_list('customers/customeredit') as $filename) {
                    include $filename;
                }
            }
            if (\common\helpers\Acl::check_extension_allowed('ReportChangesHistory') && isset($logger)) {
                $after_object = new \common\api\Classes\Customer();
                $after_object->load($c_info->customers_id);
                $logger->set_after_object($after_object);
                unset($after_object);
                $logger->run();
            }
            if (!$has_errors) {
                $message_stack->add_session(TEXT_MESSEAGE_SUCCESS, 'account', 'success');
                return $this->redirect(['customers/customeredit', 'customers_id' => $c_info->customers_id]);
            }
        }
        $messages = [];
        if ($message_stack->size('account') > 0) {
            $messages = $message_stack->as_array('account');
        }
        if ($customer_form->erp_customer_id == 0) {
            $customer_form->erp_customer_id = '';
        }
        $this->selected_menu = ['customers', 'customers'];
        $this->view->show_other_groups = false;
        $this->view->show_group = \common\helpers\Extensions::is_customer_groups_allowed();
        if ($this->view->show_group) {
            /** @var \common\extensions\ExtraGroups\ExtraGroups $ext */
            if ($ext = \common\helpers\Acl::check_extension_allowed('ExtraGroups', 'allowed')) {
                $this->view->group_status_array = $ext::get_main_groups();
                $this->view->group_extra_arrays = $ext::get_other_groups();
                $this->view->show_other_groups = true;
                $this->view->group_extra_selected = $ext::get_other_groups_selected($c_info->customers_id);
            } else {
                $this->view->group_status_array = \common\models\Groups::find()->as_array()->select('groups_name')->order_by('sort_order, groups_name')->index_by('groups_id')->column();
            }
        }
        $guest_status_array = [0 => TEXT_BTN_NO, 1 => TEXT_BTN_YES];
        $this->view->guest_status_array = $guest_status_array;
        $this->view->show_dob = in_array(ACCOUNT_DOB, ['required', 'required_register', 'visible', 'visible_register']);
        $this->view->show_state = in_array(ACCOUNT_STATE, ['required', 'required_register', 'visible', 'visible_register']);
        $platform_variants = [];
        foreach (\common\classes\platform::get_list(false) as $_p) {
            $platform_variants[$_p['id']] = $_p['text'];
        }
        $languages = array_column(\common\classes\language::get_all(), 'name', 'id');
        $currency = \Yii::$app->settings->get('currency');
        switch ($currency) {
            case 'USD':
                $prefix_class = 'global-currency-usd';
                break;
            case 'GBP':
                $prefix_class = 'global-currency-gbp';
                break;
            case 'EUR':
                $prefix_class = 'global-currency-eur';
                break;
            default:
                $prefix_class = '';
                break;
        }
        foreach (\common\helpers\Hooks::get_list('customers/customeredit/before-render') as $filename) {
            include $filename;
        }
        return $this->render('edit', ['cInfo' => $c_info, 'addresses' => $addresses, 'platforms' => $platform_variants, 'admins' => [0 => ''] + \yii\helpers\Array_Helper::map(\common\helpers\Admin::get_list(), 'admin_id', 'listTitle'), 'customerForm' => $customer_form, 'myPromos' => $my_promos, 'messages' => $messages, 'prefix' => $prefix_class, 'languages' => $languages]);
    }
    public function action_customerdelete()
    {
        $this->layout = false;
        $customers_id = Yii::$app->request->post('customers_id');
        $anonimize_orders = Yii::$app->request->post('anonimize_orders', 0);
        \common\helpers\Customer::delete_customer($customers_id, false);
        if ($anonimize_orders) {
            $removed_id = \common\helpers\Customer::find_create_anonymous_customer();
            \common\helpers\Customer::anonimize_orders($customers_id, $removed_id);
        }
    }
    public function action_customersdelete()
    {
        $this->layout = false;
        $selected_ids = Yii::$app->request->post('selected_ids');
        $anonimize_orders = Yii::$app->request->post('anonimize_orders', 0);
        if ($anonimize_orders) {
            $removed_id = \common\helpers\Customer::find_create_anonymous_customer();
        }
        foreach ($selected_ids as $customers_id) {
            \common\helpers\Customer::delete_customer($customers_id, false);
            if ($anonimize_orders) {
                $removed_id = \common\helpers\Customer::find_create_anonymous_customer();
                \common\helpers\Customer::anonimize_orders($customers_id, $removed_id);
            }
        }
    }
    public function action_confirmcustomerdelete()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/customers');
        $this->layout = false;
        $customers_id = Yii::$app->request->post('customers_id');
        $customers_query = tep_db_query('select distinct(c.customers_id), c.last_xml_export, c.customers_lastname, c.customers_firstname, c.customers_email_address, c.customers_status, c.groups_id, a.entry_country_id, c.admin_id from ' . TABLE_CUSTOMERS . ' c left join ' . TABLE_ADDRESS_BOOK . ' a on  a.address_book_id = c.customers_default_address_id left join ' . TABLE_ADMIN . " ad on ad.admin_id=c.admin_id where c.customers_id = '" . (int) $customers_id . "'");
        $customers = tep_db_fetch_array($customers_query);
        if (!is_array($customers)) {
            die('Wrong customer data.');
        }
        $info_query = tep_db_query('select customers_info_date_account_created as date_account_created, customers_info_date_account_last_modified as date_account_last_modified, customers_info_date_of_last_logon as date_last_logon, customers_info_number_of_logons as number_of_logons from ' . TABLE_CUSTOMERS_INFO . " where customers_info_id = '" . $customers['customers_id'] . "'");
        $info = tep_db_fetch_array($info_query);
        $info = $info ?? [];
        $country_query = tep_db_query('select countries_name from ' . TABLE_COUNTRIES . " where countries_id = '" . (int) $customers['entry_country_id'] . "' and language_id = '" . (int) $languages_id . "'");
        $country = tep_db_fetch_array($country_query);
        $country = $country ?? [];
        $reviews_query = tep_db_query('select count(*) as number_of_reviews from ' . TABLE_REVIEWS . " where customers_id = '" . (int) $customers['customers_id'] . "'");
        $reviews = tep_db_fetch_array($reviews_query);
        $customer_info = array_merge($country, $info, $reviews);
        $c_info_array = array_merge($customers, $customer_info);
        $c_info = new \Object_Info($c_info_array);
        echo tep_draw_form('customers', FILENAME_CUSTOMERS, \common\helpers\Output::get_all_get_params(['action']) . 'action=update', 'post', 'id="customers_edit" onSubmit="return deleteCustomer();"');
        echo '<div class="or_box_head">' . TEXT_INFO_HEADING_DELETE_CUSTOMER . '</div>';
        echo '<div class="col_desc">' . TEXT_DELETE_INTRO . '</div>';
        echo '<div class="col_desc">' . $c_info->customers_firstname . ' ' . $c_info->customers_lastname . '</div>';
        if (isset($c_info->number_of_reviews) && $c_info->number_of_reviews > 0) {
            echo '<div class="main_row">';
            echo '<div class="main_title">' . sprintf(TEXT_DELETE_REVIEWS, $c_info->number_of_reviews) . '</div>';
            echo '<div class="main_value">' . tep_draw_checkbox_field('delete_reviews', 'on', true) . '</div>';
            echo '</div>';
        }
        if (defined('ANONIMIZE_ORDERS_ON_CUSTOMER_DELETE') && ANONIMIZE_ORDERS_ON_CUSTOMER_DELETE != 'True') {
            echo '<div class="col_desc">' . '<label>' . tep_draw_checkbox_field('anonimize_orders', '1', false) . ' <span>' . TEXT_ANONIMIZE_ORDERS . '</span></label></div><br />';
        }
        ?>
        <p class="btn-toolbar">
        <?php 
        echo '<input type="submit" class="btn btn-primary" value="' . IMAGE_DELETE . '" >';
        echo '<input type="button" class="btn btn-cancel" value="' . IMAGE_CANCEL . '" onClick="return resetStatement()">';
        echo tep_draw_hidden_field('customers_id', $c_info->customers_id);
        ?>
        </p>
        </form>
        <?php 
    }
    public function action_generatepassword()
    {
        $message_stack = \Yii::$container->get('message_stack');
        \common\helpers\Translation::init('admin/customers');
        $customers_id = (int) Yii::$app->request->post('cID', 0);
        $check_customer = Customers::find()->where(['customers_id' => $customers_id])->one();
        if ($check_customer instanceof Customers) {
            $change_pass = trim(Yii::$app->request->post('change_pass', ''));
            if ($change_pass == '') {
                $new_password = \common\helpers\Password::create_random_value(ENTRY_PASSWORD_MIN_LENGTH);
            } else {
                $new_password = $change_pass;
            }
            unset($change_pass);
            $crypted_password = \common\helpers\Password::encrypt_password($new_password, 'frontend');
            if (\common\helpers\Acl::check_extension_allowed('ReportChangesHistory')) {
                $logger = new \common\extensions\Report_Changes_History\classes\Logger();
                $before_object = new \common\api\Classes\Customer();
                $before_object->load($customers_id);
                $logger->set_before_object($before_object);
                unset($before_object);
            }
            $check_customer->customers_password = $crypted_password;
            if ($check_customer->save(false)) {
                if (\common\helpers\Acl::check_extension_allowed('ReportChangesHistory') && isset($logger)) {
                    $after_object = new \common\api\Classes\Customer();
                    $after_object->load($customers_id);
                    $logger->set_after_object($after_object);
                    unset($after_object);
                    $logger->run();
                }
                $platform_config = Yii::$app->get('platform')->config($check_customer->platform_id);
                $e_mail_store = $platform_config->const_value('STORE_NAME');
                $e_mail_address = $platform_config->const_value('STORE_OWNER_EMAIL_ADDRESS');
                $e_mail_store_owner = $platform_config->const_value('STORE_OWNER');
                $email_params = [];
                $email_params['STORE_URL'] = \common\helpers\Output::get_clickable_link(tep_catalog_href_link(''));
                $email_params['CUSTOMER_FIRSTNAME'] = $check_customer->customers_firstname;
                $email_params['CUSTOMER_LASTNAME'] = $check_customer->customers_lastname;
                $email_params['NEW_PASSWORD'] = $new_password;
                $email_params['STORE_NAME'] = $e_mail_store;
                list($email_subject, $email_text) = \common\helpers\Mail::get_parsed_email_template('Account update', $email_params, $check_customer->language_id, $check_customer->platform_id);
                //$email_text = sprintf(TEXT_EMAIL_ACCOUNT_UPDATE, $check_customer->customers_firstname . ' ' . $check_customer->customers_lastname, HTTP_CATALOG_SERVER . DIR_WS_CATALOG, $new_password, $eMail_store);
                \common\helpers\Mail::send($check_customer->customers_firstname . ' ' . $check_customer->customers_lastname, $check_customer->customers_email_address, $email_subject, $email_text, $e_mail_store_owner, $e_mail_address, [], '', '', ['add_br' => 'no']);
                $message_stack->add_session(PASSWORD_SENT_MESSAGE, 'header', 'success');
                \common\helpers\Session::delete_customer_sessions($check_customer->customers_id);
            }
        }
        //$this->redirect(array('customers/customeractions', 'customers_id'=>  $customers_id));
        echo json_encode(['customers_id' => $customers_id]);
    }
    public function action_send_coupon()
    {
        $message_stack = \Yii::$container->get('message_stack');
        $this->layout = false;
        if (Yii::$app->request->is_post) {
            $customers_id = Yii::$app->request->post('customers_id', 0);
        } else {
            $customers_id = Yii::$app->request->get('customers_id', 0);
        }
        if ($customers_id) {
            \common\helpers\Translation::init('admin/coupon_admin');
            $customers_query = tep_db_query('select c.customers_id, c.customers_firstname, c.customers_lastname, c.customers_email_address from ' . TABLE_CUSTOMERS . ' c left join ' . TABLE_ADMIN . " ad on ad.admin_id=c.admin_id where c.customers_id = '" . (int) $customers_id . "' " . (Affiliate::is_logged() ? " and c.affiliate_id = '" . $login_id . "'" : ''));
            $customers = tep_db_fetch_array($customers_query);
            if (Yii::$app->request->is_post) {
                $current_platform_id = \Yii::$app->get('platform')->config()->get_id();
                $platform_config = \Yii::$app->get('platform')->config($current_platform_id);
                $STORE_NAME = $platform_config->const_value('STORE_NAME');
                $STORE_OWNER_EMAIL_ADDRESS = $platform_config->const_value('STORE_OWNER_EMAIL_ADDRESS');
                $STORE_OWNER = $platform_config->const_value('STORE_OWNER');
                $email_text = TEXT_VOUCHER_IS . ' ' . $_POST['coupon_code'] . "\n" . TEXT_TO_REDEEM . "\n" . TEXT_REMEMBER . "\n";
                if (tep_not_null($_POST['coupon_message'])) {
                    $email_text .= "\n" . strip_tags($_POST['coupon_message']);
                }
                $subject = tep_not_null($_POST['coupon_subject']) ? $_POST['coupon_subject'] : sprintf(TEXT_SUBJECT_CODE, $STORE_NAME);
                \common\helpers\Mail::send($customers['customers_firstname'] . ' ' . $customers['customers_lastname'], $customers['customers_email_address'], $subject, $email_text, $STORE_OWNER, $STORE_OWNER_EMAIL_ADDRESS);
                $message_stack->add_session(MESSAGE_COUPON_SENT, 'header', 'success');
                echo json_encode(['customers_id' => $customers_id]);
                exit;
            }
        }
        return $this->render('send-coupon.tpl', ['customers' => $customers]);
    }
    /**
     * Autocomplete - filter by group
     */
    public function action_group()
    {
        $term = tep_db_prepare_input(Yii::$app->request->get('term'));
        $q = \common\models\Groups::find()->select('groups_name')->distinct();
        /** @var \common\extensions\ExtraGroups\ExtraGroups $ExtraGroups */
        if ($extra_groups = \common\helpers\Acl::check_extension('ExtraGroups', 'allowed')) {
            if ($extra_groups::allowed()) {
                $q->order_by('groups_type_id');
            }
        }
        if (!empty($term)) {
            $q->and_where(['like', 'groups_name', tep_db_input($term)]);
        }
        $groups = $q->add_order_by('groups_name')->column();
        echo json_encode($groups);
    }
    public function action_countries()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $term = tep_db_prepare_input(Yii::$app->request->get('term'));
        $search = '1';
        if (!empty($term)) {
            $search = "c.countries_name like '%" . tep_db_input($term) . "%'";
        }
        $countries = [];
        $address_query = tep_db_query('select c.countries_name as country from ' . TABLE_ADDRESS_BOOK . ' ab left join ' . TABLE_COUNTRIES . " c on ab.entry_country_id=c.countries_id  and c.language_id = '" . (int) $languages_id . "' left join " . TABLE_ZONES . ' z on z.zone_country_id=c.countries_id and ab.entry_zone_id=z.zone_id where ' . $search . ' group by c.countries_name order by c.countries_name');
        while ($response = tep_db_fetch_array($address_query)) {
            if (!empty($response['country'])) {
                $countries[] = $response['country'];
            }
        }
        echo json_encode($countries);
    }
    public function action_state()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $term = tep_db_prepare_input(Yii::$app->request->get('term'));
        $country = tep_db_prepare_input(Yii::$app->request->get('country'));
        $search = '1';
        if (!empty($country)) {
            $search = "c.countries_name like '%" . tep_db_input($country) . "%'";
        }
        if (!empty($term)) {
            $search .= " and (ab.entry_state like '%" . tep_db_input($term) . "%' or z.zone_name like '%" . tep_db_input($term) . "%')";
        }
        $states = [];
        $address_query = tep_db_query('select if (LENGTH(ab.entry_state), ab.entry_state, z.zone_name) as state from ' . TABLE_ADDRESS_BOOK . ' ab left join ' . TABLE_COUNTRIES . " c on ab.entry_country_id=c.countries_id  and c.language_id = '" . (int) $languages_id . "' left join " . TABLE_ZONES . ' z on z.zone_country_id=c.countries_id and ab.entry_zone_id=z.zone_id where ' . $search . ' group by state order by state');
        while ($response = tep_db_fetch_array($address_query)) {
            if (!empty($response['state'])) {
                $states[] = $response['state'];
            }
        }
        echo json_encode($states);
    }
    public function action_city()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $term = tep_db_prepare_input(Yii::$app->request->get('term'));
        $country = tep_db_prepare_input(Yii::$app->request->get('country'));
        $state = tep_db_prepare_input(Yii::$app->request->get('state'));
        $search = '1';
        if (!empty($country)) {
            $search = "c.countries_name like '%" . tep_db_input($country) . "%'";
        }
        if (!empty($state)) {
            $search .= " and (ab.entry_state like '%" . tep_db_input($state) . "%' or z.zone_name like '%" . tep_db_input($state) . "%')";
        }
        if (!empty($term)) {
            $search = "ab.entry_city like '%" . tep_db_input($term) . "%'";
        }
        $cities = [];
        $address_query = tep_db_query('select ab.entry_city as city from ' . TABLE_ADDRESS_BOOK . ' ab left join ' . TABLE_COUNTRIES . " c on ab.entry_country_id=c.countries_id  and c.language_id = '" . (int) $languages_id . "' left join " . TABLE_ZONES . ' z on z.zone_country_id=c.countries_id and ab.entry_zone_id=z.zone_id where ' . $search . ' group by city order by city');
        while ($response = tep_db_fetch_array($address_query)) {
            if (!empty($response['city'])) {
                $cities[] = $response['city'];
            }
        }
        echo json_encode($cities);
    }
    public function action_company()
    {
        $term = tep_db_prepare_input(Yii::$app->request->get('term'));
        $search = '1';
        if (!empty($term)) {
            $search = "entry_company like '%" . tep_db_input($term) . "%'";
        }
        $companies = [];
        $address_query = tep_db_query('select entry_company from ' . TABLE_ADDRESS_BOOK . ' where ' . $search . ' group by entry_company order by entry_company');
        while ($response = tep_db_fetch_array($address_query)) {
            if (!empty($response['entry_company'])) {
                $companies[] = $response['entry_company'];
            }
        }
        echo json_encode($companies);
    }
    public function action_states()
    {
        $term = tep_db_prepare_input(Yii::$app->request->get('term'));
        $country = (int) Yii::$app->request->get('country');
        $search = '1';
        if ($country > 0) {
            $search = "zone_country_id = '" . $country . "'";
        }
        if (!empty($term)) {
            $search .= " and zone_name like '%" . tep_db_input($term) . "%'";
        }
        $states = [];
        $address_query = tep_db_query('SELECT zone_name FROM ' . TABLE_ZONES . ' where ' . $search . ' group by zone_name order by zone_name');
        while ($response = tep_db_fetch_array($address_query)) {
            if (!empty($response['zone_name'])) {
                $states[] = $response['zone_name'];
            }
        }
        echo json_encode($states);
    }
    public function action_credithistory()
    {
        $customers_id = (int) Yii::$app->request->get('customers_id');
        $type = Yii::$app->request->get('type', 'credit');
        $type = $type == 'credit' ? 0 : 1;
        \common\helpers\Translation::init('admin/customers');
        $this->view->heading_title = HEADING_TITLE;
        $this->layout = false;
        $currencies = Yii::$container->get('currencies');
        $history = [];
        $customer_history_query = tep_db_query('select * from ' . TABLE_CUSTOMERS_CREDIT_HISTORY . " where customers_id='" . $customers_id . "' and credit_type = '{$type}' order by customers_credit_history_id DESC ");
        while ($customer_history = tep_db_fetch_array($customer_history_query)) {
            $admin = '';
            if ($customer_history['admin_id'] > 0) {
                $check_admin_query = tep_db_query('select * from ' . TABLE_ADMIN . " where admin_id = '" . (int) $customer_history['admin_id'] . "'");
                $check_admin = tep_db_fetch_array($check_admin_query);
                if (is_array($check_admin)) {
                    $admin = $check_admin['admin_firstname'] . ' ' . $check_admin['admin_lastname'];
                }
            }
            $history[] = ['date' => $type ? \common\helpers\Date::datepicker_date($customer_history['date_added']) : \common\helpers\Date::datetime_short($customer_history['date_added']), 'credit' => $customer_history['credit_prefix'] . ($customer_history['credit_type'] == '0' ? $currencies->format($customer_history['credit_amount'], true, $customer_history['currency'], $customer_history['currency_value']) : $customer_history['credit_amount']), 'notified' => $customer_history['customer_notified'], 'comments' => $customer_history['comments'], 'admin' => $admin];
        }
        if ($type) {
            if (\common\helpers\Acl::check_extension_allowed('BonusActions')) {
                $_history = \common\extensions\Bonus_Actions\models\Promotions_Bonus_History::find()->where('customer_id = :id', [':id' => (int) $customers_id])->as_array()->order_by(['promotions_bonus_history_id' => SORT_DESC])->all();
                if ($_history) {
                    $titles = [];
                    foreach ($_history as $h) {
                        if (!isset($titles[$h['bonus_points_id']])) {
                            $titles[$h['bonus_points_id']] = \common\extensions\Bonus_Actions\models\Promotions_Bonus_Points::find()->where('bonus_points_id = ' . (int) $h['bonus_points_id'])->with('description')->one();
                        }
                        $history[] = ['date' => \common\helpers\Date::datepicker_date($h['action_date']), 'credit' => '+' . $h['bonus_points_award'], 'notified' => 1, 'comments' => $titles[$h['bonus_points_id']]->description->points_title, 'admin' => ''];
                    }
                    //\yii\helpers\ArrayHelper::multisort($history, 'date');
                }
            }
        }
        return $this->render('credithistory', ['history' => $history]);
    }
    public function action_customermerge()
    {
        if ($ext = \common\helpers\Acl::check_extension_allowed('MergeCustomers', 'allowed')) {
            return $ext::action_customermerge();
        }
        return $this->redirect(Yii::$app->url_manager->create_url(['customers/']));
    }
    public function action_customer_merge_info()
    {
        if ($ext = \common\helpers\Acl::check_extension_allowed('MergeCustomers', 'allowed')) {
            return $ext::action_customer_merge_info();
        }
    }
    public function action_do_customer_merge()
    {
        if ($ext = \common\helpers\Acl::check_extension_allowed('MergeCustomers', 'allowed')) {
            return $ext::action_do_customer_merge();
        }
    }
    public function action_download_customer_file()
    {
        $customer_id = Yii::$app->user->get_id();
        $file = Yii::$app->request->get('file');
        $redirect = true;
        if (!$customer_id) {
            $customer_id = Yii::$app->request->get('customer_id');
        }
        if (!$customer_id || !$file) {
            $path = DIR_FS_DOWNLOAD;
        } else {
            $path = DIR_FS_DOWNLOAD . 'customers' . DIRECTORY_SEPARATOR . $customer_id . DIRECTORY_SEPARATOR;
            $redirect = false;
        }
        $message_stack = \Yii::$container->get('message_stack');
        // Die if file is not there
        if (!file_exists($path . $file)) {
            $message_stack->add_session('TEXT_DOWNLOAD_FILE_NOT_FOUND', 'download');
            tep_redirect(tep_href_link(FILENAME_DEFAULT));
        }
        header('Expires: Mon, 26 Nov 1962 00:00:00 GMT');
        header('Last-Modified: ' . gmdate('D,d M Y H:i:s') . ' GMT');
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        $mime_type = mime_content_type($path . $file);
        if (in_array($mime_type, ['image/gif', 'image/jpeg', 'image/pjpeg', 'image/png', 'image/tiff', 'image/webp', 'application/pdf'])) {
            header('Content-Type: ' . $mime_type);
            header('Content-disposition: inline; filename=' . $file);
        } else {
            header('Content-Type: Application/octet-stream');
            header('Content-disposition: attachment; filename=' . $file);
        }
        if ($redirect && DOWNLOAD_BY_REDIRECT == 'true') {
            // This will work only on Unix/Linux hosts
            \common\helpers\Download::unlink_temp_dir(DIR_FS_DOWNLOAD_PUBLIC);
            $tempdir = \common\helpers\Download::random_name();
            umask(00);
            mkdir(DIR_FS_DOWNLOAD_PUBLIC . $tempdir, 0777);
            symlink($path . $file, DIR_FS_DOWNLOAD_PUBLIC . $tempdir . '/' . $file);
            tep_redirect(DIR_WS_DOWNLOAD_PUBLIC . $tempdir . '/' . $file);
        } else {
            // This will work on all systems, but will need considerable resources
            // We could also loop with fread($fp, 4096) to save memory
            readfile($path . $file);
        }
    }
    public function action_trade_acc()
    {
        if ($ext = \common\helpers\Acl::check_extension_allowed('TradeForm')) {
            $ext::action_trade_form_acc();
        }
    }
    public function action_gdpr_check()
    {
        if (in_array(ACCOUNT_DOB, ['required_register', 'visible_register', 'required', 'visible'])) {
            //dob present
            $current_platform_id = \Yii::$app->get('platform')->config()->get_id();
            $platform_config = \Yii::$app->get('platform')->config($current_platform_id);
            $STORE_NAME = $platform_config->const_value('STORE_NAME');
            $STORE_OWNER_EMAIL_ADDRESS = $platform_config->const_value('STORE_OWNER_EMAIL_ADDRESS');
            $STORE_OWNER = $platform_config->const_value('STORE_OWNER');
            $check_customer_query = tep_db_query('select customers_id, customers_dob, customers_firstname, customers_lastname, customers_email_address, opc_temp_account, customers_status from ' . TABLE_CUSTOMERS . " where opc_temp_account = 0 and dob_flag = 0 and customers_dob > '" . date('Y-m-d', strtotime('-13 years')) . "' || customers_dob = '0000-00-00 00:00:00'");
            while ($check_customer = tep_db_fetch_array($check_customer_query)) {
                if ($check_customer['customers_status'] == 0) {
                    \common\helpers\Customer::delete_customer($check_customer['customers_id'], false);
                    //delete without notification
                } else {
                    $gdpr_check_query = tep_db_query("select * from gdpr_check where customers_id = '" . (int) $check_customer['customers_id'] . "'");
                    if (tep_db_num_rows($gdpr_check_query) == 0) {
                        do {
                            $new_token = \common\helpers\Password::create_random_value(32);
                            $token_check_query = tep_db_query("select token from gdpr_check where token = '" . $new_token . "'");
                        } while (tep_db_num_rows($token_check_query) > 0);
                        $sql_data_array = ['customers_id' => (int) $check_customer['customers_id'], 'email' => $check_customer['customers_email_address'], 'date_send' => 'now()', 'token' => $new_token];
                        tep_db_perform('gdpr_check', $sql_data_array);
                        //send email
                        $email_params = [];
                        $email_params['STORE_NAME'] = $STORE_NAME;
                        $email_params['STORE_URL'] = \common\helpers\Output::get_clickable_link(tep_catalog_href_link('', '', 'NONSSL'));
                        $email_params['CUSTOMER_FIRSTNAME'] = $check_customer['customers_firstname'];
                        $email_params['STORE_OWNER_EMAIL_ADDRESS'] = $STORE_OWNER_EMAIL_ADDRESS;
                        $email_params['HTTP_HOST'] = \common\helpers\Output::get_clickable_link(tep_catalog_href_link('account/update', 'token=' . $new_token, 'SSL'));
                        list($email_subject, $email_text) = \common\helpers\Mail::get_parsed_email_template('GDPR update request', $email_params);
                        \common\helpers\Mail::send($check_customer['customers_firstname'] . ' ' . $check_customer['customers_lastname'], $check_customer['customers_email_address'], $email_subject, $email_text, $STORE_OWNER, $STORE_OWNER_EMAIL_ADDRESS);
                    }
                }
            }
        }
        return $this->redirect(Yii::$app->url_manager->create_url(['customers/']));
    }
    public function action_gdpr_cleanup()
    {
        if (in_array(ACCOUNT_DOB, ['required_register', 'visible_register', 'required', 'visible'])) {
            //dob present
            $check_customer_query = tep_db_query('select customers_id, customers_dob, customers_firstname, customers_lastname, customers_email_address, opc_temp_account, customers_status from ' . TABLE_CUSTOMERS . " where opc_temp_account = 0 and dob_flag = 0 and customers_dob > '" . date('Y-m-d', strtotime('-13 years')) . "' || customers_dob = '0000-00-00 00:00:00'");
            while ($check_customer = tep_db_fetch_array($check_customer_query)) {
                if ($check_customer['customers_status'] == 0) {
                    \common\helpers\Customer::delete_customer($check_customer['customers_id'], false);
                    //delete without notification
                } else {
                    \common\helpers\Customer::delete_customer($check_customer['customers_id']);
                    //delete with notification
                }
            }
        }
        return $this->redirect(Yii::$app->url_manager->create_url(['customers/']));
    }
    public function action_customer_products_save()
    {
        $ret = '';
        $c_id = intval(\Yii::$app->request->get('customers_id', 0));
        if ($c_id > 0) {
            /** @var \common\extensions\CustomerProducts\CustomerProducts $ext */
            if ($ext = \common\helpers\Acl::check_extension('CustomerProducts', 'saveCustomerProducts')) {
                if ($ext::allowed()) {
                    $products = array_map('intval', \Yii::$app->request->post('customer_products', []));
                    $ret = $ext::save_customer_products($c_id, $products);
                }
            }
        }
        return $ret;
    }
    public function action_customer_products()
    {
        $ret = '';
        /** @var \common\extensions\CustomerProducts\CustomerProducts $ext */
        if ($ext = \common\helpers\Acl::check_extension_allowed('CustomerProducts', 'allowed')) {
            $c_info = new \Object_Info(['customers_id' => intval(\Yii::$app->request->get('customers_id'))]);
            $ret = $ext::view_customer_products($c_info);
        }
        return $ret;
    }
    public function action_search_ajax()
    {
        $ret = '';
        $prod_restricted = (int) \Yii::$app->request->get('prod_restricted', 0);
        $q = \Yii::$app->request->get('q');
        $c_q = (new \yii\db\Query())->select('customers_id, customers_firstname, customers_lastname, customers_email_address, customers_alt_email_address, customers_status')->from(TABLE_CUSTOMERS)->and_where(['or', ['like', 'customers_firstname', tep_db_input($q)], ['like', 'customers_lastname', tep_db_input($q)], ['like', 'customers_email_address', tep_db_input($q)], ['like', 'customers_alt_email_address', tep_db_input($q)]])->order_by('customers_status desc, customers_lastname, customers_firstname, customers_email_address')->limit(20);
        /** @var \common\extensions\CustomerProducts\CustomerProducts $ext  */
        if ($prod_restricted > 0 && $ext = \common\helpers\Acl::check_extension('CustomerProducts', 'allowed')) {
            if ($ext::allowed()) {
                $c_q->and_where('restrict_products=1');
            }
        }
        //echo $cQ->createCommand()->rawSql;
        $customers = $c_q->all();
        if (is_array($customers) && !empty($customers)) {
            foreach ($customers as $c) {
                $option = '';
                if ($c['customers_status'] == 0) {
                    $option .= ' class="dis_mod"';
                }
                $ret .= '<a data-id="' . $c['customers_id'] . '" ' . $option . '>' . implode(' ', [$c['customers_lastname'], $c['customers_firstname'], $c['customers_email_address']]) . '</a><br />';
            }
        }
        return $ret;
    }
}