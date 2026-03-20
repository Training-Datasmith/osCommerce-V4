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
namespace common\extensions\Error_Log_Viewer\backend\controllers;

use common\extensions\Error_Log_Viewer\Error_Log_Viewer;
use common\extensions\Error_Log_Viewer\Log_Reader;
use Yii;
class Error_Log_Viewer_Controller extends \common\classes\modules\Sceleton_Extensions_Backend
{
    public function __construct($id, $module = null, $config = [])
    {
        parent::__construct($id, $module, $config);
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('error-log-viewer/index'), 'title' => EXT_ELV_HEADING_TITLE];
        if ((new Error_Log_Viewer())->delete_old_zip() !== true) {
            \Yii::$app->session->set_flash('ELV', sprintf(EXT_ELV_ERR_DELETE_OLD_ZIP, (new Error_Log_Viewer())->delete_old_zip()));
        }
    }
    public function action_index()
    {
        $languages_id = Yii::$app->settings->get('languages_id');
        $this->top_buttons[] = '<button onclick="deleteAllLog()" class="btn btn-primary"><i class="icon-trash"></i>' . EXT_ELV_TEXT_CLEAR_ALL . '</button>';
        $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url('error-log-viewer/download') . '" class="btn btn-primary"><i class="icon-download"></i>' . EXT_ELV_TEXT_DOWNLOAD_ALL_LOGS . '</a>';
        $this->view->heading_title = EXT_ELV_HEADING_TITLE;
        $this->view->log_table = [['title' => '<input type="checkbox" class="checkbox">', 'not_important' => 2], ['title' => EXT_ELV_TABLE_FILENAME, 'not_important' => 0], ['title' => EXT_ELV_TABLE_FILESIZE, 'not_important' => 0], ['title' => EXT_ELV_TABLE_LAST_MODIFIED, 'not_important' => 0], ['title' => EXT_ELV_TABLE_FILESIZE, 'not_important' => 0]];
        $this->view->filters = new \stdClass();
        $by = [['name' => EXT_ELV_TEXT_BACKEND, 'value' => 'backend', 'selected' => ''], ['name' => EXT_ELV_TEXT_FRONTEND, 'value' => 'frontend', 'selected' => ''], ['name' => EXT_ELV_TEXT_CONSOLE, 'value' => 'console', 'selected' => '']];
        foreach ($by as $key => $value) {
            if (isset($_GET['by']) && $value['value'] == $_GET['by']) {
                $by[$key]['selected'] = 'selected';
            }
        }
        $this->view->filters->by = $by;
        return $this->render('index', []);
    }
    public function action_list()
    {
        $type = strtolower(Yii::$app->request->get('by', 'backend'));
        if (Error_Log_Viewer::get_files($type)) {
            foreach (Error_Log_Viewer::get_files($type) as $file) {
                $list[] = ['<input type="checkbox" class="checkbox">' . '<input class="cell_identify" type="hidden" value="' . $type . '/' . $file->name . '">', $file->name, $file->size_text, $file->date, $file->size];
            }
        } else {
            $list = [];
        }
        $response = ['data' => $list];
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $response;
    }
    public function action_delete_all()
    {
        if (Yii::$app->request->is_ajax) {
            Error_Log_Viewer::delete_all();
        } else {
            throw new \Exception('Direct request denied');
        }
    }
    public function action_logs_delete()
    {
        $data = Yii::$app->request->post('logs');
        if (is_array($data)) {
            foreach ($data as $file) {
                $log = Error_Log_Viewer::get_file($file);
                if (!unlink($log->full_path)) {
                    throw new \Exception("Can't delete file: " . $log->full_path);
                }
            }
        } elseif (is_string($data)) {
            $log = Error_Log_Viewer::get_file($data);
            if (!unlink($log->full_path)) {
                throw new \Exception("Can't delete file: " . $log->full_path);
            }
        }
    }
    public function action_actions()
    {
        $log = Yii::$app->request->post('log');
        if (!is_null($log)) {
            $file = Error_Log_Viewer::get_file($log);
            return $this->render_partial('actions', ['file' => $file]);
        }
    }
    public function action_advanced_actions()
    {
        $id = \Yii::$app->request->post('id', false);
        $file = \Yii::$app->request->post('file', false);
        $file = str_replace('|', '.', $file);
        $mask = str_replace('.', '|', $file);
        $reader = new Log_Reader($file);
        $tmp = explode('/', $file);
        try {
            $headers = $reader->get_headers();
            if (!array_key_exists($id, $headers)) {
                return false;
            }
            $result = new \stdClass();
            $result->date = $headers[$id][1];
            $result->ip = $headers[$id][2];
            $result->level = $headers[$id][5];
            $result->category = $headers[$id][6];
            $result->text = $headers[$id][7];
            $result->description = $reader->get_details($id);
            $result->file = $file;
            $result->source = $tmp[0];
            $result->mask = $mask;
            return $this->render_partial('advanced-actions', ['log' => $result]);
        } catch (\Exception $e) {
            // if file content not support
            return null;
        }
    }
    public function action_view()
    {
        $this->top_buttons[] = '<button class="btn btn-delete btn-no-margin" onclick="deleteLog()">' . IMAGE_DELETE . '</button>';
        $this->top_buttons[] = '<button onclick="viewAsText()" class="btn btn-primary"><i class="icon-file-text-alt"></i>' . EXT_ELV_TEXT_VIEW_AS_TEXT . '</button>';
        $this->view->heading_title = EXT_ELV_HEADING_TITLE;
        $file = \Yii::$app->request->get('log', false);
        $mask = str_replace('.', '|', $file);
        $real_fiename = str_replace('|', '.', $file);
        if (!$file) {
            throw new \Exception('Invalid request');
        }
        $this->view->log_table = [['title' => 'ID', 'not_important' => 0], ['title' => EXT_ELV_TEXT_LOG_POSITION_DATE, 'not_important' => 0], ['title' => EXT_ELV_TEXT_IP, 'not_important' => 0], ['title' => EXT_ELV_TEXT_ERROR_LEVEL, 'not_important' => 0], ['title' => EXT_ELV_TEXT_CATEGORY, 'not_important' => 0], ['title' => EXT_ELV_TEXT_ERROR_DESCRIPTION, 'not_important' => 0]];
        return $this->render('view', ['file' => $file, 'back' => explode('/', $file)[0], 'mask' => $mask, 'filename' => $real_fiename]);
    }
    public function action_advanced_list()
    {
        $file = \Yii::$app->request->get('file', false);
        $file = str_replace('|', '.', $file);
        if (!$file) {
            throw new \Exception('Invalid request');
        }
        $logger = new Log_Reader($file);
        try {
            foreach ($logger->get_headers() as $key => $header) {
                $list[] = [$key, $header[1] . '<input class="cell_identify" type="hidden" value="' . $key . '">', $header[2], $header[5], $header[6], $header[7]];
            }
            $response = ['data' => array_reverse($list ?? [])];
        } catch (\Exception $e) {
            \Yii::$app->session->set_flash('ELV', sprintf('File %s not supported', $file));
            $response = ['error' => true];
        }
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $response;
    }
    public function action_download()
    {
        $zip = (new Error_Log_Viewer())->Zipping();
        if ($zip['status'] == 'ok') {
            if (file_exists($zip['description'])) {
                \Yii::$app->response->send_file($zip['description'])->on(\yii\web\Response::EVENT_AFTER_SEND, function ($event) {
                    unlink($event->data);
                }, $zip['description']);
            } else {
                \Yii::$app->session->set_flash('ELV', EXT_ELV_ERR_NO_FILE_TO_DOWNLOAD);
                return $this->redirect(\Yii::$app->url_manager->create_url(['error-log-viewer']));
            }
        } else {
            \Yii::$app->session->set_flash('ELV', sprintf(EXT_ELV_ERR_CREATE_ZIP, $zip['description']));
            return $this->redirect(\Yii::$app->url_manager->create_url(['error-log-viewer']));
        }
    }
    public function action_view_as_text()
    {
        $log = Yii::$app->request->get('file', 'false');
        $file = Error_Log_Viewer::get_file($log);
        if ($file->error) {
            $content = '<pre>' . $file->error_message . '</pre>';
            Yii::$app->response->content = $content;
        } else {
            header('Content-type: text/plain');
            header('Pragma: no-cache');
            header('Expires: 0');
            header('Content-Length: ' . filesize($file->full_path));
            if (ob_get_level()) {
                ob_end_clean();
            }
            readfile("{$file->full_path}");
            exit;
        }
    }
}