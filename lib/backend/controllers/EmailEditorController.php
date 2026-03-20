<?php

declare (strict_types=1);
/**
 * This file is part of True Loaded.
 *
 * @link http://www.holbi.co.uk
 * @copyright Copyright (c) 2005 Holbi Group LTD
 *
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */
namespace backend\controllers;

use common\models\Emails;
use common\models\Themes;
use common\models\Themes_Settings;
use Yii;
use yii\db\Expression;
/**
 * default controller to handle user requests.
 */
class Email_Editor_Controller extends Sceleton
{
    public $acl = ['BOX_HEADING_DESIGN_CONTROLS', 'BOX_HEADING_BUILD_NEWSLETTER'];
    public function action_index()
    {
        $this->selected_menu = ['design_controls', 'email-editor'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('email-editor'), 'title' => BOX_HEADING_BUILD_NEWSLETTER];
        $this->view->heading_title = BOX_HEADING_BUILD_NEWSLETTER;
        $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url('email-editor/edit') . '" class="btn btn-primary">' . IMAGE_NEW . '</a>';
        return $this->render('index.tpl', []);
    }
    public function action_list()
    {
        $draw = Yii::$app->request->get('draw', 1);
        $length = Yii::$app->request->get('length', 25);
        $search = Yii::$app->request->get('search');
        $start = Yii::$app->request->get('start', 0);
        $order = Yii::$app->request->get('order', 0);
        if ($length == -1) {
            $length = 10000;
        }
        if (!$search['value']) {
            $search['value'] = '';
        }
        switch ($order[0]['column']) {
            case 0:
                $sort_col = 'subject';
                break;
            case 1:
                $sort_col = 'date_modified';
                break;
            case 2:
                $sort_col = 'date_added';
                break;
            default:
                $sort_col = 'subject';
        }
        $emails = Emails::find()->where(['like', 'subject', $search['value']])->limit($length)->offset($start)->order_by([$sort_col => $order[0]['dir'] == 'asc' ? SORT_ASC : SORT_DESC])->all();
        $response_list = [];
        foreach ($emails as $email) {
            $response_list[] = ['<div class="item" data-item-id="' . $email->emails_id . '">' . $email->subject . '</div>', \common\helpers\Date::datetime_short($email->date_modified), \common\helpers\Date::datetime_short($email->date_added)];
        }
        $count_items = Emails::find()->count();
        $response = ['draw' => $draw, 'recordsTotal' => $count_items, 'recordsFiltered' => $count_items, 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_edit()
    {
        $email_id = (int) Yii::$app->request->get('email_id', 0);
        $this->selected_menu = ['design_controls', 'email-editor'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('email-editor'), 'title' => BOX_HEADING_BUILD_NEWSLETTER];
        $this->view->heading_title = BOX_HEADING_BUILD_NEWSLETTER;
        $this->top_buttons[] = '
            <span class="btn btn-confirm btn-save-boxes btn-elements">' . IMAGE_SAVE . '</span>
            <span class="btn btn-elements btn-preview">' . IMAGE_PREVIEW . '</span>
            <span class="btn btn-elements btn-export">' . EXPORT_HTML . '</span>
            <a href="' . Yii::$app->url_manager->create_url(['email-editor', 'item_id' => $email_id]) . '"
                class="btn btn-cancel">' . IMAGE_CANCEL . '</a>
                ';
        $themes = Themes::find()->order_by('title')->as_array()->all();
        $styles = [];
        $theme_templates = [];
        foreach ($themes as $theme) {
            $templates = Themes_Settings::find()->select('setting_value')->where(['setting_group' => 'added_page', 'setting_name' => 'email', 'theme_name' => $theme['theme_name']])->as_array()->all();
            $theme['templates']['email'] = 'Default';
            foreach ($templates as $template) {
                $theme['templates'][\common\classes\design::page_name($template['setting_value'])] = $template['setting_value'];
            }
            $theme_templates[] = $theme;
            $st = \backend\design\Style::get_styles_by_classes($theme['theme_name'], '.b-email-editor');
            foreach ($st['attributesText'] as $class => $attr_text) {
                $attr_arr = explode(';', $attr_text);
                foreach ($attr_arr as $attr) {
                    $_attr = explode(':', $attr);
                    if (trim($_attr[0])) {
                        $styles[$theme['theme_name']][$class][trim($_attr[0])] = trim($_attr[1]);
                    }
                }
            }
        }
        $email = Emails::find_one($email_id);
        return $this->render('edit.tpl', ['platforms' => \common\classes\platform::get_list(false), 'id' => $email_id, 'themes' => $theme_templates, 'themesJSON' => json_encode($theme_templates), 'subject' => $email->subject ?? null, 'data' => $email->data ?? null, 'theme_name' => $email->theme_name ?? null, 'template' => $email->template ?? null, 'styles' => json_encode($styles), 'tr' => \common\helpers\Translation::translations_for_js(['DROP_IMAGE_HERE', 'DROP_PRODUCT_HERE', 'EDIT_WIDGET', 'MOVE_BLOCK', 'REMOVE_WIDGET', 'IMAGE_SAVE', 'IMAGE_CANCEL', 'PRODUCTS_IN_ROW', 'EDIT_PRODUCTS_ROW', 'CHOOSE_IMAGE', 'CHOOSE_PRODUCT', 'START_TYPING_PRODUCT_NAME', 'EDIT_TEXT'])]);
    }
    public function action_bar()
    {
        $email_id = Yii::$app->request->get('email_id', 0);
        if (!$email_id) {
            return '';
        }
        $email = Emails::find_one($email_id);
        $this->layout = false;
        return $this->render('bar.tpl', ['emailId' => $email_id, 'title' => $email->subject]);
    }
    public function action_upload()
    {
        if (isset($_FILES['file'])) {
            $path = \Yii::get_alias('@webroot');
            $path .= DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'emails' . DIRECTORY_SEPARATOR;
            $response = [];
            $name = $_FILES['file']['name'];
            $upload_file = $path . $name;
            if (!is_writeable(dirname($upload_file))) {
                $response[] = ['status' => 'error', 'text' => sprintf(ERROR_DATA_DIRECTORY_NOT_WRITEABLE, $upload_file), 'file' => $name];
            } elseif (!is_uploaded_file($_FILES['file']['tmp_name']) || filesize($_FILES['file']['tmp_name']) == 0) {
                $response[] = ['status' => 'error', 'text' => WARNING_NO_FILE_UPLOADED, 'file' => $name];
            } elseif (is_file($upload_file)) {
                $response[] = ['status' => 'choice', 'text' => FILE_ALREADY_EXIST . ' <span>' . DO_YOU_WANT_USE_UPLOADED_FILE . '</span>', 'file' => $name];
            } elseif (move_uploaded_file($_FILES['file']['tmp_name'], $upload_file)) {
                self::resize_img($upload_file, $path, $name);
                $response[] = ['status' => 'ok', 'text' => TEXT_MESSEAGE_SUCCESS_ADDED, 'file' => $name];
            } else {
                $response[] = ['status' => 'error', 'text' => 'error', 'file' => $name];
            }
        }
        return json_encode($response);
    }
    public static function resize_img($upload_file, $path, $name)
    {
        $imagine = new \Imagine\Gd\Imagine();
        $art_image = $imagine->open($upload_file);
        $size = @get_image_size($upload_file);
        $width = $size[0];
        $height = $size[1];
        $options = ['resolution-units' => \Imagine\Image\Image_Interface::RESOLUTION_PIXELSPERINCH, 'resolution-x' => 72, 'resolution-y' => 72, 'resampling-filter' => \Imagine\Image\Image_Interface::FILTER_LANCZOS, 'png_compression_level' => 9];
        if ($width > 100 || $height > 100) {
            $scaled_size = $art_image->get_size()->scale(min([100 / $art_image->get_size()->get_width(), 100 / $art_image->get_size()->get_height()]));
            $art_image = $art_image->resize($scaled_size);
        }
        $t_location = $path . 'thumbnails' . DIRECTORY_SEPARATOR . $name;
        $art_image->save($t_location, $options);
    }
    public function action_save()
    {
        $email_id = (int) Yii::$app->request->post('email_id', '');
        $subject = Yii::$app->request->post('subject', '');
        $html = Yii::$app->request->post('html', '');
        $data = Yii::$app->request->post('data', '');
        $theme_name = Yii::$app->request->post('theme_name', '');
        $template = Yii::$app->request->post('template', '');
        if (!$subject) {
            $subject = ' ';
        }
        if (!$html) {
            $html = ' ';
        }
        if (!$data) {
            $data = '{}';
        }
        $attr = ['subject' => $subject, 'html' => $html, 'data' => $data, 'theme_name' => $theme_name, 'template' => $template, 'date_modified' => new Expression('NOW()')];
        if ($email_id) {
            $email = Emails::find_one($email_id);
            if (!$email) {
                $email = new Emails();
            }
        } else {
            $email = new Emails();
            $attr['date_added'] = new Expression('NOW()');
        }
        $email->attributes = $attr;
        $email->save();
        $response = ['status' => 'ok', 'text' => 'Saved', 'email_id' => $email->emails_id];
        return json_encode($response);
    }
    public function action_delete_confirm()
    {
        $email_id = (int) Yii::$app->request->get('email_id', 0);
        $email = Emails::find_one($email_id);
        $this->layout = false;
        return $this->render('delete-confirm.tpl', ['emails_id' => $email_id, 'title' => $email->subject]);
    }
    public function action_delete()
    {
        $email_id = (int) Yii::$app->request->get('emails_id', 0);
        if (!$email_id) {
            return '';
        }
        Emails::delete_all(['emails_id' => $email_id]);
        $response = ['status' => 'ok', 'text' => 'Removed', 'emails_id' => $email_id];
        return json_encode($response);
    }
    public function action_gallery()
    {
        $path = DIR_FS_CATALOG . 'images' . DIRECTORY_SEPARATOR . 'emails' . DIRECTORY_SEPARATOR;
        if (!file_exists($path)) {
            mkdir($path);
        }
        if (!file_exists($path . DIRECTORY_SEPARATOR . 'thumbnails' . DIRECTORY_SEPARATOR)) {
            mkdir($path . 'thumbnails' . DIRECTORY_SEPARATOR);
        }
        $images = [];
        $files = scandir($path);
        foreach ($files as $item) {
            $s = strtolower(substr($item, -3));
            if ($s == 'gif' || $s == 'png' || $s == 'jpg' || $s == 'peg') {
                if (!file_exists($path . DIRECTORY_SEPARATOR . 'thumbnails' . DIRECTORY_SEPARATOR)) {
                    mkdir($path . 'thumbnails' . DIRECTORY_SEPARATOR);
                }
                if (!file_exists($path . DIRECTORY_SEPARATOR . 'thumbnails' . DIRECTORY_SEPARATOR . $item)) {
                    self::resize_img($path . $item, $path, $item);
                }
                $images[] = $item;
            }
        }
        return json_encode($images);
    }
    public function action_pass()
    {
        $platform_id = Yii::$app->request->get('platform_id');
        $email_id = Yii::$app->request->get('email_id');
        $update = Yii::$app->request->get('update', 0);
        $ret = \common\extensions\Newsletters\Newsletters::pass_html_template_confirm($platform_id, $email_id, $update);
        echo json_encode($ret);
    }
}