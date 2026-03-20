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

use common\components\Socials;
use Yii;
use yii\helpers\Array_Helper;
class Socials_Controller extends Sceleton
{
    public $acl = ['TEXT_SETTINGS', 'BOX_HEADING_SOCIALS'];
    public function __construct($id, $module)
    {
        parent::__construct($id, $module);
        \common\helpers\Translation::init('admin/socials');
    }
    public function action_index()
    {
        $this->selected_menu = ['settings', 'socials'];
        $this->navigation[] = ['link' => \Yii::$app->url_manager->create_url('socials/index'), 'title' => HEADING_TITLE];
        $this->view->heading_title = HEADING_TITLE;
        $platform_id = Yii::$app->request->get('platform_id', 0);
        $platforms = \common\classes\platform::get_list(false);
        $this->view->tab_list = [['title' => TABLE_SOCIAL_MODULE, 'not_important' => 0], ['title' => TABLE_HEADING_STATUS . ' (Auth)', 'not_important' => 0]];
        $this->view->group_id = (int) Yii::$app->request->get('group_id', 0);
        $messages = Yii::$app->session->get_all_flashes();
        Yii::$app->session->remove_all_flashes();
        return $this->render('index', ['platforms' => $platforms, 'first_platform_id' => $platform_id ? $platform_id : \common\classes\platform::first_id(), 'default_platform_id' => \common\classes\platform::default_id(), 'isMultiPlatforms' => \common\classes\platform::is_multi(), 'messages' => $messages]);
    }
    public function action_list()
    {
        $draw = (int) Yii::$app->request->get('draw', 1);
        $start = (int) Yii::$app->request->get('start', 0);
        $length = (int) Yii::$app->request->get('length', 10);
        $platform_id = (int) Yii::$app->request->get('platform_id');
        $response_list = [];
        if ($length == -1) {
            $length = 10000;
        }
        $records_total = 0;
        if (isset($_GET['search']['value']) && tep_not_null($_GET['search']['value'])) {
            $keywords = tep_db_input(tep_db_prepare_input($_GET['search']['value']));
            $search_condition = " where module like '%" . $keywords . "%' ";
        } else {
            $search_condition = ' where 1 ';
        }
        if (isset($_GET['order'][0]['column']) && $_GET['order'][0]['dir']) {
            switch ($_GET['order'][0]['column']) {
                case 0:
                    $order_by = 'module ' . tep_db_prepare_input($_GET['order'][0]['dir']);
                    break;
                default:
                    $order_by = 'module';
                    break;
            }
        } else {
            $order_by = 'module';
        }
        $current_page_number = $start / $length + 1;
        $socials_raw = 'select * from ' . TABLE_SOCIALS . " {$search_condition}  and platform_id = '{$platform_id}' order by {$order_by}";
        $_split = new \Split_Page_Results($current_page_number, $length, $socials_raw, $records_total, 'socials_id');
        $socials_query = tep_db_query($socials_raw);
        while ($social = tep_db_fetch_array($socials_query)) {
            if (!empty(\common\components\Socials::get_site_url($social['module']))) {
                $response_list[] = [$social['module'] . '<input class="cell_identify" type="hidden" value="' . $social['socials_id'] . '">', !in_array($social['module'], ['instagram', 'youtube']) ? '<input name="active" type="checkbox" class="check_on_off" ' . ($social['active'] ? ' checked' : '') . '>' : ''];
            }
        }
        $response = ['draw' => $draw, 'recordsTotal' => $records_total, 'recordsFiltered' => $records_total, 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_preview()
    {
        $this->layout = false;
        $socials_id = (int) Yii::$app->request->get('socials_id');
        $social_query = tep_db_query('select * from ' . TABLE_SOCIALS . " where socials_id = '" . (int) $socials_id . "'");
        $social = tep_db_fetch_array($social_query);
        if (is_array($social)) {
            echo '<div class="or_box_head">' . $social['module'] . '</div>';
            echo '<div class="btn-toolbar btn-toolbar-order">';
            echo '<a class="btn btn-edit btn-no-margin" href="' . \yii\helpers\Url::to(['socials/edit', 'socials_id' => $socials_id, 'module' => $social['module'], 'platform_id' => $social['platform_id']]) . '">' . IMAGE_EDIT . '</a>';
            echo '<button class="btn btn-delete" onclick="moduleDelete(\'' . $socials_id . '\')">' . IMAGE_DELETE . '</button>';
            echo '</div>';
        }
    }
    public function action_new()
    {
        $platform_id = (int) Yii::$app->request->get('platform_id');
        $_social = new \Std_Class();
        $_social->socials_id = 0;
        $_social->platform_id = $platform_id;
        $_social->module = '';
        $_social->client_id = '';
        $_social->client_secret = '';
        $_social->active = 0;
        $_social->addon_settings = [];
        $_defined_modules = Socials::get_defined_modules();
        $defined_modules = [];
        $exist = [];
        if (is_array($_defined_modules)) {
            $_modules = tep_db_query('select module from ' . TABLE_SOCIALS . " where platform_id = '{$platform_id}'");
            if (tep_db_num_rows($_modules)) {
                while ($row = tep_db_fetch_array($_modules)) {
                    $exist[] = $row['module'];
                }
            }
            foreach ($_defined_modules as $mod) {
                if (!in_array($mod['name'], $exist)) {
                    $defined_modules[$mod['name']] = $mod['name'];
                }
            }
        }
        return $this->render_ajax('new', ['social' => $_social, 'modules' => $defined_modules, 'platform_id' => $platform_id]);
    }
    public function action_edit()
    {
        \common\helpers\Translation::init('admin/adminfiles');
        $this->selected_menu = ['settings', 'socials'];
        $this->navigation[] = ['link' => \Yii::$app->url_manager->create_url('socials/index'), 'title' => HEADING_TITLE];
        $this->view->heading_title = HEADING_TITLE;
        $this->top_buttons[] = '<span class="btn btn-confirm" onclick="$(\'form[name=social_form]\').trigger(\'submit\')">' . IMAGE_SAVE . '</span>';
        if (Yii::$app->request->is_post) {
            $socials_id = (int) Yii::$app->request->post('socials_id');
            $this->layout = false;
        } else {
            $socials_id = (int) Yii::$app->request->get('socials_id');
        }
        $platform_id = (int) Yii::$app->request->get('platform_id');
        $_defined_modules = Socials::get_defined_modules();
        $mc = \yii\helpers\Array_Helper::map($_defined_modules, 'name', 'class');
        $_social = new \Std_Class();
        if ($socials_id) {
            $social = tep_db_fetch_array(tep_db_query('select * from ' . TABLE_SOCIALS . " where socials_id = '" . $socials_id . "' and platform_id = '{$platform_id}'"));
            $_social->socials_id = $socials_id;
            $_social->platform_id = $social['platform_id'];
            $_social->module = $social['module'];
            $_social->client_id = $social['client_id'];
            $_social->client_secret = $social['client_secret'];
            $_social->active = $social['active'];
            $_social->link = $social['link'];
            $_social->image = DIR_WS_IMAGES . $social['image'];
            $_social->css_class = $social['css_class'];
            $_social->addon_settings = [];
            if (method_exists($mc[$social['module']], 'getAddonSettings')) {
                $_social->addon_settings = $mc[$social['module']]::get_addon_settings();
            }
            $title = 'Edit Social Module';
        }
        $addons_query = tep_db_query('select * from ' . TABLE_SOCIALS_ADDONS . " where socials_id = '" . $socials_id . "'");
        if (tep_db_num_rows($addons_query)) {
            while ($row = tep_db_fetch_array($addons_query)) {
                if (isset($_social->addon_settings[$row['block_name']])) {
                    if (isset($_social->addon_settings[$row['block_name']][$row['configuration_key']])) {
                        $_social->addon_settings[$row['block_name']][$row['configuration_key']]['value'] = $row['configuration_value'];
                    }
                }
            }
        }
        //echo'<pre>';print_r($_social);die;
        $_social->has_auth = in_array($_social->module, ['instagram', 'youtube']) ? false : true;
        $messages = Yii::$app->session->get_all_flashes();
        Yii::$app->session->remove_all_flashes();
        return $this->render('edit', ['social' => $_social, 'title' => $title, 'socials_id' => $socials_id, 'platform_id' => $platform_id, 'messages' => $messages, 'test_url' => \yii\helpers\Url::to(['socials/test', 'socials_id' => $socials_id, 'module' => $social['module'], 'platform_id' => $social['platform_id'], 'url' => HTTP_SERVER . DIR_WS_CATALOG . 'account/auth'])]);
    }
    public function action_save()
    {
        $platform_id = (int) Yii::$app->request->post('platform_id');
        $socials_id = (int) Yii::$app->request->post('socials_id', 0);
        $module = Yii::$app->request->post('module');
        $settings = Yii::$app->request->post('settings');
        if (tep_not_null($module)) {
            if (isset($settings['auth'])) {
                $sql_data_array = ['platform_id' => $platform_id, 'module' => $module, 'client_id' => $settings['auth']['client_id'], 'client_secret' => $settings['auth']['client_secret']];
                if ($socials_id > 0) {
                    tep_db_perform(TABLE_SOCIALS, $sql_data_array, 'update', "socials_id = '" . (int) $socials_id . "'");
                } else {
                    tep_db_perform(TABLE_SOCIALS, $sql_data_array);
                    $socials_id = tep_db_insert_id();
                }
                unset($settings['auth']);
            } elseif (!$socials_id) {
                $sql_data_array = ['platform_id' => $platform_id, 'module' => $module, 'client_id' => '', 'client_secret' => '', 'active' => 0];
                tep_db_perform(TABLE_SOCIALS, $sql_data_array);
                $socials_id = tep_db_insert_id();
                Yii::$app->session->set_flash('success', TEXT_MESSEAGE_SUCCESS_ADDED);
            }
            if ($settings['link'] ?? null) {
                $socials = \common\models\Socials::find_one($socials_id);
                $socials->link = $settings['link'];
                $socials->save(false);
            }
            if ($settings['css_class'] ?? null) {
                $socials = \common\models\Socials::find_one($socials_id);
                $socials->css_class = $settings['css_class'];
                $socials->save(false);
            }
            if (Array_Helper::get_value($settings, 'image') || Array_Helper::get_value($settings, 'image_upload')) {
                $socials = \common\models\Socials::find_one($socials_id);
                $image = \common\helpers\Image::prepare_saving_image($socials->image ?? '', $settings['image'], $settings['image_upload'], 'socials', false);
                if ($socials && $socials->image != $image) {
                    $socials->image = $image;
                    $socials->save(false);
                }
            }
            if (is_array($settings ?? null) && count($settings) > 0) {
                tep_db_query('delete from ' . TABLE_SOCIALS_ADDONS . " where socials_id = '" . (int) $socials_id . "'");
                foreach ($settings as $block => $info) {
                    if (is_array($info) && count($info)) {
                        foreach ($info as $key => $value) {
                            if (isset($_FILES['settings']['name'][$block][$key])) {
                                $path = \Yii::get_alias('@webroot');
                                $path .= DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
                                $uploadfile = $path . basename($value);
                                $dest_path = \Yii::get_alias('@common') . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'google' . DIRECTORY_SEPARATOR;
                                if (!is_dir($dest_path)) {
                                    mkdir($dest_path);
                                    chmod($dest_path, 0777);
                                }
                                $dest = $dest_path . basename($value);
                                $rewrite = false;
                                if (file_exists($uploadfile)) {
                                    $rewrite = true;
                                    if (file_exists($dest) && $dest == $uploadfile) {
                                        $info = pathinfo($dest);
                                        $info['filename'] .= '-' . rand();
                                        $dest = $info['dirname'] . DIRECTORY_SEPARATOR . $info['filename'] . '.' . $info['extension'];
                                    }
                                    if (copy($uploadfile, $dest)) {
                                        unset($uploadfile);
                                    }
                                }
                                $sql_data_array = ['socials_id' => $socials_id, 'configuration_key' => $key, 'configuration_value' => $dest, 'block_name' => strtolower($block)];
                                tep_db_perform(TABLE_SOCIALS_ADDONS, $sql_data_array);
                            } else {
                                $sql_data_array = ['socials_id' => $socials_id, 'configuration_key' => $key, 'configuration_value' => $value, 'block_name' => strtolower($block)];
                                tep_db_perform(TABLE_SOCIALS_ADDONS, $sql_data_array);
                            }
                        }
                    }
                }
                Yii::$app->session->set_flash('success', TEXT_MESSEAGE_SUCCESS);
            }
        } else {
            // Yii::$app->session->setFlash('danger', TEXT_MESSAGE_ERROR);
            return $this->redirect(['socials/']);
        }
        return $this->redirect(['socials/edit', 'socials_id' => $socials_id, 'platform_id' => $platform_id]);
    }
    public function action_delete()
    {
        $socials_id = (int) Yii::$app->request->post('socials_id');
        tep_db_query('delete from ' . TABLE_SOCIALS . " where socials_id = '" . (int) $socials_id . "'");
        echo 'ok';
    }
    public function action_change()
    {
        $socials_id = (int) Yii::$app->request->post('socials_id');
        $platform_id = Yii::$app->request->post('platform_id', 0);
        $status = Yii::$app->request->post('status', 'false') == 'true' ? 1 : 0;
        $message = '';
        $error = true;
        if ($platform_id && $socials_id) {
            $_status = tep_db_fetch_array(tep_db_query('select test_success, module from ' . TABLE_SOCIALS . " where socials_id = '" . (int) $socials_id . "'"));
            if ($_status['test_success']) {
                tep_db_query('update ' . TABLE_SOCIALS . " set active='" . (int) $status . "' where socials_id = '" . (int) $socials_id . "' and platform_id = '" . (int) $platform_id . "'");
                $message = TEXT_MESSEAGE_SUCCESS;
                $error = false;
                $title = TEXT_MESSTYPE_SUCCESS;
            } else {
                $title = TEXT_TEST_MODULE;
                $message = TEXT_CHECK_SOCIAL_MODULE_SETTINGS;
                $message .= ' ' . \common\components\Socials::get_site_url($_status['module']);
            }
        }
        return $this->render_ajax('message', ['type' => $error ? 'error' : 'success', 'message' => $message, 'title' => $title]);
    }
    public function action_test()
    {
        $module = Yii::$app->request->get('module');
        $platform_id = (int) Yii::$app->request->get('platform_id');
        $socials_id = (int) Yii::$app->request->get('socials_id');
        \common\components\Socials::load_components($platform_id, $module);
        return $this->render_ajax('test', ['module' => $module, 'socials_id' => $socials_id, 'platform_id' => $platform_id]);
    }
}