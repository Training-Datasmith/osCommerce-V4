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
namespace backend\controllers;

use Yii;
use yii\web\Response;
class Groups_Controller extends Sceleton
{
    public $acl = ['BOX_HEADING_CUSTOMERS', 'BOX_CUSTOMERS_GROUPS'];
    public function action_index()
    {
        $this->selected_menu = ['customers', 'groups'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('groups/index'), 'title' => HEADING_TITLE];
        $this->view->heading_title = HEADING_TITLE;
        $this->view->groups_table = [['title' => TABLE_HEADING_GROUPS, 'not_important' => 1], ['title' => TABLE_HEADING_DISCOUNT, 'not_important' => 1]];
        /** @var \common\extensions\UserGroups\UserGroups $ext */
        if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroups', 'allowed')) {
            return $ext::admin_groups();
        }
        $row = Yii::$app->request->get('row', 0);
        $messages = Yii::$app->session->get_all_flashes();
        return $this->render('index', ['messages' => $messages]);
    }
    public function action_list()
    {
        $draw = Yii::$app->request->get('draw', 1);
        $start = Yii::$app->request->get('start', 0);
        $length = Yii::$app->request->get('length', 10);
        $gets = Yii::$app->request->get();
        $response_list = [];
        if ($length == -1) {
            $length = 10000;
        }
        $query_numrows = 0;
        $q = \common\models\Groups::find()->select('groups_id, groups_name, groups_discount')->as_array();
        if (isset($_GET['search']['value']) && tep_not_null($_GET['search']['value'])) {
            $keywords = tep_db_input(tep_db_prepare_input($_GET['search']['value']));
            $q->and_where(['like', 'groups_name', $keywords]);
        }
        $filters = [];
        $form_filter = Yii::$app->request->get('filter');
        parse_str($form_filter, $filters);
        /** @var \common\extensions\ExtraGroups\ExtraGroups $ext */
        if ($ext = \common\helpers\Acl::check_extension('ExtraGroups', 'allowed')) {
            if ($ext::allowed() && isset($filters['groups_type_id'])) {
                $q->and_where(['groups_type_id' => (int) $filters['groups_type_id']]);
            }
        }
        if (!empty($gets['order']) && is_array($gets['order'])) {
            foreach ($gets['order'] as $so) {
                if (!empty($so['dir']) && strtolower($so['dir']) == 'desc') {
                    $sd = SORT_DESC;
                } else {
                    $sd = SORT_ASC;
                }
                switch ($so['column']) {
                    default:
                    case 0:
                        $q->add_order_by(['groups_name' => $sd]);
                        break;
                    case 1:
                        $q->add_order_by(['groups_discount' => $sd]);
                        break;
                }
            }
        } else {
            $q->order_by(['groups_name' => SORT_ASC]);
        }
        //echo $q->createCommand()->rawSql; die;
        $current_page_number = $start / $length + 1;
        new \Split_Page_Results($current_page_number, $length, $q, $query_numrows, 'groups_id');
        foreach ($q->all() as $groups) {
            $div_dbl = '<div class="click_double" data-click-double="' . \yii\helpers\Url::to(['groups/itemedit', 'item_id' => $groups['groups_id'], 'row_id' => Yii::$app->request->post('row_id', 0)]) . '">';
            $response_list[] = [$div_dbl . $groups['groups_name'] . '<input class="cell_identify" type="hidden" value="' . $groups['groups_id'] . '"></div>', $div_dbl . trim(rtrim($groups['groups_discount'], '0'), '.') . '% </div>'];
        }
        $response = ['draw' => $draw, 'recordsTotal' => $query_numrows, 'recordsFiltered' => $query_numrows, 'data' => $response_list];
        return json_encode($response);
    }
    public function action_itempreedit()
    {
        $this->layout = false;
        $html = '';
        $restrictions_html = '';
        if ($ext = \common\helpers\Acl::check_extension('UserGroupsRestrictions', 'allowed')) {
            if ($ext::allowed()) {
                $restrictions_html = $ext::admin_show();
            }
        }
        /** @var \common\extensions\ExtraGroups\ExtraGroups $ExtraGroups */
        if ($extra_groups = \common\helpers\Acl::check_extension_allowed('ExtraGroups', 'allowed')) {
            if (!$extra_groups::allowed_product_restriction(\Yii::$app->request->post('item_id', 0))) {
                $restrictions_html = '';
            }
        }
        /** @var \common\extensions\UserGroups\UserGroups $ext */
        if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroups', 'allowed')) {
            $html = $ext::admin_preedit_groups();
        }
        return $html . $restrictions_html;
    }
    public function action_itemedit()
    {
        \common\helpers\Translation::init('admin/groups');
        $this->selected_menu = ['customers', 'groups'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('groups/index'), 'title' => HEADING_TITLE];
        $this->view->heading_title = TEXT_HEADING_EDIT_GROUP;
        $content = '';
        /** @var \common\extensions\UserGroups\UserGroups $ext */
        if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroups', 'allowed')) {
            $content = $ext::admin_edit_groups();
        }
        if (Yii::$app->request->is_ajax) {
            return $this->render_ajax('edit', ['content' => $content]);
        } else {
            return $this->render('edit', ['content' => $content]);
        }
    }
    public function action_confirmitemdelete()
    {
        $this->layout = false;
        /** @var \common\extensions\UserGroups\UserGroups $ext */
        if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroups', 'allowed')) {
            return $ext::admin_confirm_delete_groups();
        }
    }
    public function action_submit()
    {
        $this->layout = false;
        /** @var \common\extensions\UserGroups\UserGroups $ext */
        if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroups', 'allowed')) {
            $ext::admin_submit_groups();
        }
        return $this->redirect(['groups/index', 'row' => Yii::$app->request->post('row_id', 0), 'groups_type_id' => Yii::$app->request->post('groups_type_id', 0)]);
    }
    public function action_itemdelete()
    {
        $this->layout = false;
        /** @var \common\extensions\UserGroups\UserGroups $ext */
        if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroups', 'allowed')) {
            return $ext::admin_delete_groups();
        }
    }
    public function action_customers()
    {
        $this->layout = false;
        /** @var \common\extensions\UserGroups\UserGroups $ext */
        if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroups', 'allowed')) {
            return $ext::admin_customers_groups();
        }
    }
    public function action_customers_add()
    {
        $groups_id = Yii::$app->request->get('groups_id');
        $customers_id = Yii::$app->request->get('customers_id');
        tep_db_query('update ' . TABLE_CUSTOMERS . " set groups_id = '" . (int) $groups_id . "' where customers_id = '" . (int) $customers_id . "'");
        return $this->action_customers();
    }
    public function action_customers_delete()
    {
        $customers_id = Yii::$app->request->get('customers_id');
        tep_db_query('update ' . TABLE_CUSTOMERS . " set groups_id = '0' where customers_id = '" . (int) $customers_id . "'");
        return $this->action_customers();
    }
    public function action_restrictions()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $html = '';
        if ($ext = \common\helpers\Acl::check_extension('UserGroupsRestrictions', 'allowed')) {
            if ($ext::allowed()) {
                $group_id = (int) Yii::$app->request->get('groupId', 0);
                $html = $ext::admin_show_form($group_id, $languages_id);
            }
        }
        return $html;
    }
    public function action_load_tree()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $response_data = [];
        if ($ext = \common\helpers\Acl::check_extension('UserGroupsRestrictions', 'allowed')) {
            if ($ext::allowed()) {
                $do = Yii::$app->request->post('do', '');
                $req_selected_data = tep_db_prepare_input(Yii::$app->request->post('selected_data'));
                $response_data = $ext::load_tree($do, $req_selected_data, $languages_id);
            }
        }
        Yii::$app->response->format = Response::FORMAT_JSON;
        return $response_data;
    }
    public function action_update_catalog_selection()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        if ($ext = \common\helpers\Acl::check_extension('UserGroupsRestrictions', 'allowed')) {
            if ($ext::allowed()) {
                $req_selected_data = tep_db_prepare_input(Yii::$app->request->post('selected_data'));
                $group_id = (int) Yii::$app->request->get('groupId', 0);
                $response_data = $ext::update_selection($group_id, $req_selected_data, $languages_id);
            }
        }
        Yii::$app->response->format = Response::FORMAT_JSON;
        Yii::$app->response->data = ['status' => 'ok'];
    }
    public function action_manufacturer_search()
    {
        if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroupsExtraDiscounts', 'allowed')) {
            return $ext::admin_manufacturer_search();
        }
    }
    public function action_new_manufacturer()
    {
        $this->layout = false;
        if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroupsExtraDiscounts', 'allowed')) {
            return $ext::admin_new_manufacturer();
        }
    }
    public function action_product_search()
    {
        if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroupsExtraDiscounts', 'allowed')) {
            return $ext::admin_product_search();
        }
    }
    public function action_new_product()
    {
        $this->layout = false;
        if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroupsExtraDiscounts', 'allowed')) {
            return $ext::admin_new_product();
        }
    }
    public function action_category_search()
    {
        if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroupsExtraDiscounts', 'allowed')) {
            return $ext::admin_category_search();
        }
    }
    public function action_new_category()
    {
        $this->layout = false;
        if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroupsExtraDiscounts', 'allowed')) {
            return $ext::admin_new_category();
        }
    }
}