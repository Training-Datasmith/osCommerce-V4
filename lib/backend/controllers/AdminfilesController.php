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

use Yii;
class Adminfiles_Controller extends Sceleton
{
    public $acl = ['BOX_HEADING_ADMINISTRATOR', 'BOX_ADMINISTRATOR_BOXES'];
    public function action_index()
    {
        $this->selected_menu = ['administrator', 'adminfiles'];
        $this->navigation[] = ['link' => \Yii::$app->url_manager->create_url('adminfiles/index'), 'title' => HEADING_TITLE];
        $this->view->heading_title = HEADING_TITLE;
        $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url('adminfiles/edit') . '" class="btn btn-primary" onclick="return editItem(0)">' . IMAGE_INSERT . '</a>';
        $this->view->access_table = [['title' => TABLE_HEADING_NAME, 'not_important' => 0]];
        $this->view->filters = new \stdClass();
        $this->view->filters->row = (int) Yii::$app->request->get('row', 0);
        return $this->render('index');
    }
    public function action_list()
    {
        $draw = Yii::$app->request->get('draw', 1);
        $start = Yii::$app->request->get('start', 0);
        $length = Yii::$app->request->get('length', 10);
        $response_list = [];
        if ($length == -1) {
            $length = 10000;
        }
        $records_total = 0;
        if (isset($_GET['search']['value']) && tep_not_null($_GET['search']['value'])) {
            $keywords = tep_db_input(tep_db_prepare_input($_GET['search']['value']));
            $search_condition = " where access_levels_name like '%" . $keywords . "%' ";
        } else {
            $search_condition = ' where 1 ';
        }
        /*if (isset($_GET['order'][0]['column']) && $_GET['order'][0]['dir']) {
              switch ($_GET['order'][0]['column']) {
                  case 0:
                      $orderBy = "access_levels_name " . tep_db_input(tep_db_prepare_input($_GET['order'][0]['dir']));
                      break;
                  default:
                      $orderBy = "access_levels_name";
                      break;
              }
          } else {
              $orderBy = "access_levels_name";
          }*/
        $order_by = 'sort_order, access_levels_name';
        $current_page_number = $start / $length + 1;
        $access_query_raw = 'select * from ' . TABLE_ACCESS_LEVELS . " {$search_condition} order by {$order_by}";
        $_split = new \Split_Page_Results($current_page_number, $length, $access_query_raw, $records_total, 'access_levels_id');
        $access_query = tep_db_query($access_query_raw);
        while ($access = tep_db_fetch_array($access_query)) {
            $response_list[] = ['<div class="handle_cat_list"><span class="handle"><i class="icon-hand-paper-o"></i></span><div class="cat_name cat_name_attr cat_no_folder">' . $access['access_levels_name'] . '<input class="cell_identify" type="hidden" value="' . $access['access_levels_id'] . '">' . '<input class="cell_type" type="hidden" value="top" >' . '</div></div>'];
        }
        $response = ['draw' => $draw, 'recordsTotal' => $records_total, 'recordsFiltered' => $records_total, 'data' => $response_list];
        echo json_encode($response);
    }
    private function get_access_level_by_id_array($access_level_id = 0)
    {
        $return = [];
        try {
            $return = \common\models\Access_Levels::find()->where(['access_levels_id' => (int) $access_level_id])->as_array(true)->one();
            try {
                if (is_array($return) and isset($return['access_levels_persmissions'])) {
                    if ($return['access_levels_persmissions'] == '') {
                        $return['access_levels_persmissions'] = [];
                    } else {
                        $return['access_levels_persmissions'] = explode(',', $return['access_levels_persmissions']);
                        $return['access_levels_persmissions'] = array_combine($return['access_levels_persmissions'], $return['access_levels_persmissions']);
                    }
                }
            } catch (\Exception $exc) {
            }
            $return['admin_templates'] = \common\models\Admin_Templates::find()->where(['access_levels_id' => (int) $access_level_id])->index_by('admin_template_id')->as_array(true)->all();
        } catch (\Exception $exc) {
            \Yii::warning($exc->get_message() . ' ' . $exc->get_trace_as_string(), 'ErrorAdminfilesGetAccessLevelByIdArray');
        }
        return $return;
    }
    public function action_sort_order()
    {
        $moved_id = (int) $_POST['sort_top'];
        $ref_array = isset($_POST['top']) && is_array($_POST['top']) ? array_map('intval', $_POST['top']) : [];
        if ($moved_id && in_array($moved_id, $ref_array)) {
            // {{ normalize
            $order_counter = 0;
            $order_list_r = tep_db_query('SELECT access_levels_id, sort_order ' . 'FROM ' . TABLE_ACCESS_LEVELS . ' ' . 'WHERE 1 ' . 'ORDER BY sort_order, access_levels_name');
            while ($order_list = tep_db_fetch_array($order_list_r)) {
                $order_counter++;
                tep_db_query('UPDATE ' . TABLE_ACCESS_LEVELS . " SET sort_order='{$order_counter}' WHERE access_levels_id='{$order_list['access_levels_id']}' ");
            }
            // }} normalize
            $get_current_order_r = tep_db_query('SELECT access_levels_id, sort_order ' . 'FROM ' . TABLE_ACCESS_LEVELS . ' ' . "WHERE access_levels_id IN('" . implode("','", $ref_array) . "') " . 'ORDER BY sort_order');
            $ref_ids = [];
            $ref_so = [];
            while ($_current_order = tep_db_fetch_array($get_current_order_r)) {
                $ref_ids[] = (int) $_current_order['access_levels_id'];
                $ref_so[] = (int) $_current_order['sort_order'];
            }
            foreach ($ref_array as $_idx => $id) {
                tep_db_query('UPDATE ' . TABLE_ACCESS_LEVELS . " SET sort_order='{$ref_so[$_idx]}' WHERE access_levels_id='{$id}' ");
            }
        }
    }
    public function action_preview()
    {
        $this->layout = false;
        $item_id = (int) Yii::$app->request->post('item_id');
        $access_query = tep_db_query('select * from ' . TABLE_ACCESS_LEVELS . " where access_levels_id = '" . $item_id . "'");
        $access = tep_db_fetch_array($access_query);
        if (is_array($access)) {
            echo '<div class="or_box_head">' . $access['access_levels_name'] . '</div>';
            echo '<div class="btn-toolbar btn-toolbar-order">';
            echo '<a class="btn btn-edit btn-no-margin" href="' . Yii::$app->url_manager->create_url(['adminfiles/edit', 'item_id' => $item_id]) . '">' . IMAGE_EDIT . '</a>';
            echo '<button class="btn btn-delete" onclick="accessDelete(\'' . $item_id . '\')">' . IMAGE_DELETE . '</button>';
            echo '<button class="btn btn-copy btn-no-margin" onclick="confirmAclCopy(' . $item_id . ')">' . IMAGE_COPY_TO . '</button>';
            echo '<button class="btn btn-copy" onclick="confirmAclDublicate(' . $item_id . ')">' . IMAGE_DUBLICATE . '</button>';
            /**
             * @var $ext \common\extensions\Messages\Messages
             */
            if ($ext = \common\helpers\Extensions::is_allowed('Messages', 'allowed')) {
                $ext::admin_action_pre_edit($access);
            }
            /**
             * @var $handlers \common\extensions\Handlers\Handlers
             */
            if ($handlers = \common\helpers\Extensions::is_allowed('Handlers')) {
                $handlers::admin_action_pre_edit($item_id);
            }
            echo '</div>';
        }
    }
    public function action_confirm_acl_copy()
    {
        \common\helpers\Translation::init('admin/adminfiles');
        $this->layout = false;
        $item_id = (int) Yii::$app->request->post('item_id');
        $acl = \common\models\Access_Levels::find()->where(['access_levels_id' => $item_id])->one();
        if ($acl) {
            $acl_list = [];
            foreach (\common\models\Access_Levels::find()->where(['NOT IN', 'access_levels_id', $item_id])->all() as $record) {
                $acl_list[$record->access_levels_id] = $record->access_levels_name;
            }
            $params = ['obj' => $acl, 'aclList' => $acl_list];
            return $this->render('confirmaclcopy.tpl', $params);
        }
    }
    public function action_acl_copy()
    {
        $item_id = (int) Yii::$app->request->post('item_id');
        $move_to_acl_id = (int) Yii::$app->request->post('move_to_acl_id');
        $acl = \common\models\Access_Levels::find()->where(['access_levels_id' => $item_id])->one();
        $target = \common\models\Access_Levels::find()->where(['access_levels_id' => $move_to_acl_id])->one();
        if ($acl && $target) {
            if (\common\helpers\Acl::check_extension_allowed('ReportUniversalLog')) {
                $log_universal = \common\extensions\Report_Universal_Log\classes\Log_Universal::get_instance();
                $log_universal->set_type($log_universal::ULT_ACCESS_LEVEL_UPDATE)->set_relation($move_to_acl_id)->set_before_array($this->get_access_level_by_id_array($move_to_acl_id));
            }
            $target->access_levels_persmissions = $acl->access_levels_persmissions;
            $target->save(false);
            if (isset($log_universal)) {
                $log_universal->set_after_array($this->get_access_level_by_id_array($move_to_acl_id))->do_save(true);
                unset($log_universal);
            }
        }
    }
    public function action_confirm_acl_dublicate()
    {
        \common\helpers\Translation::init('admin/adminfiles');
        $this->layout = false;
        $item_id = (int) Yii::$app->request->post('item_id');
        $acl = \common\models\Access_Levels::find()->where(['access_levels_id' => $item_id])->one();
        if ($acl) {
            $params = ['obj' => $acl];
            return $this->render('confirmacldublicate.tpl', $params);
        }
    }
    public function action_acl_dublicate()
    {
        $item_id = (int) Yii::$app->request->post('item_id');
        $new_title = (string) tep_db_prepare_input(Yii::$app->request->post('new_title', ''));
        $acl = \common\models\Access_Levels::find()->where(['access_levels_id' => $item_id])->as_array()->one();
        if ($acl) {
            if (\common\helpers\Acl::check_extension_allowed('ReportUniversalLog')) {
                $log_universal = \common\extensions\Report_Universal_Log\classes\Log_Universal::get_instance();
                $log_universal->set_type($log_universal::ULT_ACCESS_LEVEL_CREATE)->set_before_array($this->get_access_level_by_id_array(0));
            }
            unset($acl['access_levels_id']);
            $acl['access_levels_name'] = $new_title;
            $dublicate = new \common\models\Access_Levels();
            $dublicate->load_default_values();
            $dublicate->set_attributes($acl);
            $dublicate->save(false);
            if (isset($log_universal)) {
                $log_universal->set_relation($dublicate->access_levels_id)->set_after_array($this->get_access_level_by_id_array($dublicate->access_levels_id))->do_save(true);
                unset($log_universal);
            }
        }
    }
    public function action_edit()
    {
        \common\helpers\Translation::init('admin/adminfiles');
        \common\helpers\Translation::init('admin/categories');
        if (Yii::$app->request->is_post) {
            $item_id = (int) Yii::$app->request->post('item_id');
            $this->layout = false;
        } else {
            $item_id = (int) Yii::$app->request->get('item_id');
        }
        $this->selected_menu = ['administrator', 'adminfiles'];
        $this->navigation[] = ['link' => \Yii::$app->url_manager->create_url('adminfiles/index'), 'title' => HEADING_TITLE];
        $this->top_buttons[] = '<span class="btn btn-confirm" onclick="$(\'#save_item_form\').trigger(\'submit\')">' . IMAGE_SAVE . '</span>';
        if ($item_id > 0) {
            $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url(['adminfiles/export-acl', 'item_id' => $item_id]) . '" class="btn btn-primary backup"><i class="icon-file-text"></i>' . TEXT_EXPORT . '</a>';
            $this->top_buttons[] = '<a href="javascript:void(0)" class="btn-import btn btn-primary backup"><i class="icon-file-text"></i>' . TEXT_IMPORT . '</a>';
        }
        $this->view->heading_title = HEADING_TITLE;
        if ($item_id > 0) {
            $action_name = IMAGE_EDIT;
        } else {
            $action_name = IMAGE_INSERT;
        }
        $access_query = tep_db_query('select * from ' . TABLE_ACCESS_LEVELS . " where access_levels_id = '" . $item_id . "'");
        $access = tep_db_fetch_array($access_query);
        $access_info = new \Object_Info($access);
        $acl_tree = \common\helpers\Acl::build_tree($access_info->access_levels_persmissions ?? null);
        return $this->render('edit', ['actionName' => $action_name, 'accessInfo' => $access_info, 'aclTree' => $acl_tree, 'item_id' => $item_id, 'templatesList' => \common\helpers\Admin_Templates::templates_list($item_id)]);
    }
    public function action_submit()
    {
        \common\helpers\Translation::init('admin/adminfiles');
        $item_id = (int) Yii::$app->request->post('item_id');
        if (\common\helpers\Acl::check_extension_allowed('ReportUniversalLog')) {
            $log_universal = \common\extensions\Report_Universal_Log\classes\Log_Universal::get_instance();
            $log_universal->set_before_array($this->get_access_level_by_id_array($item_id));
        }
        $access_levels_name = Yii::$app->request->post('access_levels_name');
        $persmissions = Yii::$app->request->post('persmissions');
        if (!is_array($persmissions)) {
            $persmissions = [];
        }
        $access_levels_persmissions = implode(',', $persmissions);
        $sql_data_array = ['access_levels_name' => $access_levels_name, 'access_levels_persmissions' => $access_levels_persmissions];
        if ($item_id > 0) {
            if (isset($log_universal)) {
                $log_universal->set_type($log_universal::ULT_ACCESS_LEVEL_UPDATE);
            }
            tep_db_perform(TABLE_ACCESS_LEVELS, $sql_data_array, 'update', "access_levels_id = '" . (int) $item_id . "'");
        } else {
            if (isset($log_universal)) {
                $log_universal->set_type($log_universal::ULT_ACCESS_LEVEL_CREATE);
            }
            tep_db_perform(TABLE_ACCESS_LEVELS, $sql_data_array);
            $item_id = tep_db_insert_id();
        }
        \common\helpers\Admin_Templates::save(Yii::$app->request->post('pages'), $item_id);
        if (isset($log_universal)) {
            $log_universal->set_relation((int) $item_id)->set_after_array($this->get_access_level_by_id_array($item_id))->do_save(true);
            unset($log_universal);
        }
        $message_type = 'success';
        $message = TEXT_MESSEAGE_SUCCESS;
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
                $('.pop-mess .pop-up-close-alert, .noti-btn .btn').click(function(){
                    $(this).parents('.pop-mess').remove();
                });

            </script>
            </div>
<?php 
        echo '<script> window.location.replace("' . Yii::$app->url_manager->create_url(['adminfiles/edit', 'item_id' => $item_id]) . '");</script>';
        //return $this->actionEdit();
    }
    public function action_delete()
    {
        $item_id = (int) Yii::$app->request->post('item_id');
        if (\common\helpers\Acl::check_extension_allowed('ReportUniversalLog')) {
            $log_universal = \common\extensions\Report_Universal_Log\classes\Log_Universal::get_instance();
            $log_universal->set_type($log_universal::ULT_ACCESS_LEVEL_DELETE)->set_relation($item_id)->set_before_array($this->get_access_level_by_id_array($item_id));
        }
        tep_db_query('delete from ' . TABLE_ACCESS_LEVELS . " where access_levels_id = '" . $item_id . "'");
        if (isset($log_universal)) {
            $log_universal->set_after_array($this->get_access_level_by_id_array($item_id))->do_save(true);
            unset($log_universal);
        }
    }
    public function action_recalc_acl()
    {
        $this->layout = false;
        $persmissions = Yii::$app->request->post('persmissions');
        $acl_tree = \common\helpers\Acl::build_tree($persmissions);
        return $this->render('recalc-acl', ['aclTree' => $acl_tree]);
    }
    public function action_export_acl()
    {
        $access_levels_id = Yii::$app->request->get('item_id');
        $this->layout = false;
        $xml = new \yii\web\Xml_Response_Formatter();
        $xml->root_tag = 'Acl';
        Yii::$app->response->format = 'custom_xml';
        Yii::$app->response->formatters['custom_xml'] = $xml;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'text/xml; charset=utf-8');
        $headers->add('Content-Disposition', 'attachment; filename="admin-acl.xml"');
        $headers->add('Pragma', 'no-cache');
        $acl = \common\models\Access_Levels::find()->where(['access_levels_id' => $access_levels_id])->one();
        if (is_string($acl->access_levels_persmissions)) {
            $selected_ids = explode(',', $acl->access_levels_persmissions);
        }
        if (!is_array($selected_ids)) {
            $selected_ids = [];
        }
        unset($acl);
        $acl = \common\models\Access_Control_List::find()->select(['access_control_list_key'])->where(['IN', 'access_control_list_id', $selected_ids])->order_by('sort_order')->as_array()->all();
        $response = [];
        foreach ($acl as $item) {
            $response[] = $item['access_control_list_key'];
        }
        return $response;
    }
    public function action_import_acl()
    {
        if (isset($_FILES['file']['tmp_name'])) {
            $xmlfile = file_get_contents($_FILES['file']['tmp_name']);
            $ob = simplexml_load_string($xmlfile);
            if (isset($ob->item)) {
                $access_levels_id = (int) Yii::$app->request->get('item_id');
                $selected_ids = [];
                foreach ($ob->item as $key) {
                    $acl = \common\models\Access_Control_List::find()->where(['access_control_list_key' => (string) $key])->one();
                    if (is_object($acl)) {
                        $selected_ids[] = $acl->access_control_list_id;
                    }
                }
                if (count($selected_ids) > 0) {
                    $access_levels_persmissions = implode(',', $selected_ids);
                } else {
                    $access_levels_persmissions = '';
                }
                $al = \common\models\Access_Levels::find()->where(['access_levels_id' => $access_levels_id])->one();
                if (is_object($al)) {
                    if (\common\helpers\Acl::check_extension_allowed('ReportUniversalLog')) {
                        $log_universal = \common\extensions\Report_Universal_Log\classes\Log_Universal::get_instance();
                        $log_universal->set_type($log_universal::ULT_ACCESS_LEVEL_UPDATE)->set_relation($access_levels_id)->set_before_array($this->get_access_level_by_id_array($access_levels_id));
                    }
                    $al->access_levels_persmissions = $access_levels_persmissions;
                    $al->save();
                    if (isset($log_universal)) {
                        $log_universal->set_after_array($this->get_access_level_by_id_array($access_levels_id))->do_save(true);
                        unset($log_universal);
                    }
                }
            }
            unlink($_FILES['file']['tmp_name']);
        }
    }
}