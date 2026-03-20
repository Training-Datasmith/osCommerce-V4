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

use common\components\google\Google_Printers;
use Yii;
use yii\helpers\File_Helper;
class Printers_Controller extends Sceleton
{
    public $acl = ['TEXT_SETTINGS', 'BOX_HEADING_PRINTERS'];
    public $config_dir;
    public function __construct($id, $module)
    {
        parent::__construct($id, $module);
        \common\helpers\Translation::init('admin/printers');
        $this->config_dir = Yii::get_alias('@common') . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'google' . DIRECTORY_SEPARATOR;
    }
    public function action_index()
    {
        $this->selected_menu = ['settings', 'printers'];
        $this->navigation[] = ['link' => \Yii::$app->url_manager->create_url('printers/index'), 'title' => HEADING_TITLE];
        $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url(['printers/service']) . '" class="btn add-service">New Service</a>';
        $this->view->heading_title = HEADING_TITLE;
        $platform_id = Yii::$app->request->get('platform_id', 0);
        $platforms = \common\classes\platform::get_list(true, true);
        $this->view->tab_list = [['title' => 'Service', 'not_important' => 0], ['title' => TEXT_ACCEPTED_PRINTERS, 'not_important' => 0]];
        $messages = Yii::$app->session->get_all_flashes();
        Yii::$app->session->remove_all_flashes();
        return $this->render('index', ['platforms' => $platforms, 'first_platform_id' => $platform_id ? $platform_id : \common\classes\platform::first_id(), 'default_platform_id' => \common\classes\platform::default_id(), 'isMultiPlatforms' => \common\classes\platform::is_multi(), 'messages' => $messages]);
    }
    public function action_list()
    {
        $draw = Yii::$app->request->get('draw', 1);
        $start = Yii::$app->request->get('start', 0);
        $length = Yii::$app->request->get('length', 10);
        $platform_id = Yii::$app->request->get('platform_id');
        $response_list = [];
        if ($length == -1) {
            $length = 10000;
        }
        $records_total = 0;
        $condition = ['and', ['platform_id' => $platform_id]];
        if (isset($_GET['search']['value']) && tep_not_null($_GET['search']['value'])) {
            $keywords = tep_db_input(tep_db_prepare_input($_GET['search']['value']));
            $condition[] = ['like', 'service', $keywords];
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
        foreach (\common\models\Cloud_Services::find()->where($condition)->all() as $service) {
            $response_list[] = ['<div>' . $service->service . '<input class="cell_identify" type="hidden" value="' . $service->id . '"></div>', $service->get_printers()->count()];
        }
        $response = ['draw' => $draw, 'recordsTotal' => $records_total, 'recordsFiltered' => $records_total, 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_preview()
    {
        $this->layout = false;
        $service_id = (int) Yii::$app->request->get('service_id');
        $service = \common\models\Cloud_Services::find()->where(['id' => $service_id])->one();
        return $this->render('preview', ['service' => $service]);
    }
    public function action_service()
    {
        \common\helpers\Translation::init('admin/adminfiles');
        $this->selected_menu = ['settings', 'printers'];
        $this->navigation[] = ['link' => \Yii::$app->url_manager->create_url('printers/index'), 'title' => HEADING_TITLE];
        $this->view->heading_title = HEADING_TITLE;
        $messages = [];
        $service_id = (int) Yii::$app->request->post('id', 0);
        if (!$service_id) {
            $service_id = (int) Yii::$app->request->get('id', 0);
        }
        $service = \common\models\Cloud_Services::find_one(['id' => $service_id]);
        if (!$service) {
            $service = new \common\models\Cloud_Services();
            if (!\common\classes\platform::is_multi()) {
                $service = \common\classes\platform::default_id();
            }
        }
        $upload_dir = Yii::get_alias('@webroot') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
        if (Yii::$app->request->is_post) {
            if ($service->load(Yii::$app->request->post()) && $service->validate()) {
                if (empty($service->service)) {
                    $service->service = 'Cloud Service';
                }
                if (file_exists($upload_dir . $service->key) && is_file($upload_dir . $service->key)) {
                    if (!is_dir($this->config_dir)) {
                        File_Helper::create_directory($this->config_dir, 0775);
                    }
                    if (copy($upload_dir . $service->key, $this->config_dir . $service->key)) {
                        File_Helper::unlink($upload_dir . $service->key);
                    }
                }
                if ($service->save()) {
                    return $this->redirect(['printers/service', 'id' => $service->id]);
                }
            } else if ($service->has_errors()) {
                foreach ($service->get_errors() as $errors) {
                    $messages['danger'] .= is_array($errors) ? implode('<br>', $errors) : $errors;
                }
            }
        }
        $platforms = \common\classes\platform::get_list(true, true);
        return $this->render('edit', ['messages' => $messages, 'service' => $service, 'platforms' => $platforms, 'isMultiPlatforms' => \common\classes\platform::is_multi()]);
    }
    public function action_delete()
    {
        $service_id = (int) Yii::$app->request->post('id');
        if ($service_id) {
            $service = \common\models\Cloud_Services::find_one(['id' => $service_id]);
            if ($service) {
                $service->delete();
            }
        }
        return 'ok';
    }
    public function action_check_printers()
    {
        $this->layout = false;
        $service_id = Yii::$app->request->post('id');
        $response = [];
        if ($service_id) {
            $service = \common\models\Cloud_Services::find_one(['id' => $service_id]);
            if ($service) {
                $file = $this->config_dir . $service->key;
                $google_printers = new Google_Printers($file);
                if ($google_printers) {
                    $printers = $google_printers->search_printers();
                    if ($printers) {
                        $accepted = $service->get_printers()->index_by('cloud_printer_id')->all();
                        $printers = array_diff_key($printers, $accepted);
                        $response['printers'] = $this->render_partial('cloud-printers', ['printers' => $printers]);
                    } else {
                        $response['error'] = $google_printers->get_error();
                    }
                }
            }
        }
        echo json_encode($response);
        exit;
    }
    public function action_describe()
    {
        $service_id = Yii::$app->request->get('sid');
        $printer_id = Yii::$app->request->get('pid');
        $data = $this->describe_printer($service_id, $printer_id);
        return $this->render_partial('printer-describe', ['data' => $data]);
    }
    public function describe_printer($servce_id, $printer_id)
    {
        $data = [];
        if ($servce_id && $printer_id) {
            $service = \common\models\Cloud_Services::find_one(['id' => $servce_id]);
            if ($service) {
                $printer = $service->get_printers()->where(['id' => $printer_id])->one();
                $file = $this->config_dir . $service->key;
                $google_printers = new Google_Printers($file);
                if ($google_printers) {
                    $resposne = $google_printers->get_printer_description($printer->cloud_printer_id);
                    if ($resposne) {
                        $data = $resposne;
                    } else {
                        $data['error'] = $google_printers->get_error();
                    }
                }
            }
        }
        return $data;
    }
    public function action_acepted()
    {
        $draw = Yii::$app->request->get('draw', 1);
        $start = Yii::$app->request->get('start', 0);
        $length = Yii::$app->request->get('length', 10);
        $service_id = Yii::$app->request->get('id');
        $response_list = [];
        $records_total = 0;
        if ($service_id) {
            foreach (\common\models\Cloud_Printers::find_all(['service_id' => $service_id]) as $printer) {
                $response_list[] = [$printer->cloud_printer_id, $printer->cloud_printer_name, $this->render_partial('actions', ['printer' => $printer])];
                $records_total++;
            }
        }
        $response = ['draw' => $draw, 'recordsTotal' => $records_total, 'recordsFiltered' => $records_total, 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_accept()
    {
        $service_id = Yii::$app->request->post('id');
        $to_accept = Yii::$app->request->post('printers', null);
        $response = [];
        if ($service_id) {
            $service = \common\models\Cloud_Services::find_one(['id' => $service_id]);
            if ($service) {
                $accepted = $service->get_printers()->index_by('cloud_printer_id')->all();
                if (!isset($accepted[$to_accept])) {
                    $accept = new \common\models\Cloud_Printers();
                    $file = $this->config_dir . $service->key;
                    $google_printers = new Google_Printers($file);
                    if ($google_printers) {
                        $printer = $google_printers->get_printer($to_accept);
                        if ($printer) {
                            $accept->cloud_printer_id = $printer['id'];
                            $accept->cloud_printer_name = $printer['name'];
                            $accept->status = (int) $printer['status'];
                            $accept->link('service', $service);
                            $response = ['type' => 'alert-success', 'message' => TEXT_ACCEPTED_SUCCESSFULY];
                        } else {
                            $error = $google_printers->get_error();
                            $response[] = ['type' => 'alert-danger', 'message' => $error['errorMessage']];
                        }
                    } else {
                        $response = ['type' => 'alert-danger', 'message' => TEXT_ACCEPTED_ALREADY];
                    }
                } else {
                    $response = ['type' => 'alert-warning', 'message' => TEXT_ACCEPTED_ALREADY];
                }
            }
        }
        echo json_encode($response);
        exit;
    }
    public function action_unlink()
    {
        $printer_id = Yii::$app->request->post('id', 0);
        $response = [];
        if ($printer_id) {
            $printer = \common\models\Cloud_Printers::find_one(['id' => $printer_id]);
            if ($printer) {
                $printer->delete();
            }
        }
        echo json_encode($response);
        exit;
    }
    public function action_test()
    {
        $service_id = Yii::$app->request->post('sid');
        $printer_id = Yii::$app->request->post('pid');
        $message = Yii::$app->request->post('job');
        $response = [];
        if ($service_id && $printer_id) {
            $service = \common\models\Cloud_Services::find()->alias('s')->where(['s.id' => $service_id])->join_with(['printers p' => function ($query) use ($printer_id) {
                $query->where(['p.id' => $printer_id]);
            }])->one();
            if ($service) {
                $file = $this->config_dir . $service->key;
                $google_printers = new Google_Printers($file);
                if ($google_printers) {
                    $job = $google_printers->create_job($service->printers[0]->cloud_printer_id);
                    $job->set_content_type('text/html');
                    $processed = $google_printers->process_job($message);
                    if ($processed['message']) {
                        $response['message'] = $processed['message'];
                    }
                }
            }
        }
        echo json_encode($response);
        exit;
    }
    public function action_jobs()
    {
        $service_id = Yii::$app->request->get('sid');
        $printer_id = Yii::$app->request->get('pid');
        Yii::$app->response->format = \yii\web\Response::FORMAT_HTML;
        $echo = '';
        if ($service_id && $printer_id) {
            $service = \common\models\Cloud_Services::find()->alias('s')->where(['s.id' => $service_id])->join_with(['printers p' => function ($query) use ($printer_id) {
                $query->where(['p.id' => $printer_id]);
            }])->one();
            if ($service) {
                $file = $this->config_dir . $service->key;
                $google_printers = new Google_Printers($file);
                if ($google_printers) {
                    $jobs = $google_printers->get_jobs($service->printers[0]->cloud_printer_id);
                    if (is_array($jobs)) {
                        foreach ($jobs as $job) {
                            $echo .= $this->render_partial('job', ['job' => $job]);
                        }
                    } else {
                        $error = $google_printers->get_error();
                        $echo = $error['message'];
                    }
                }
            }
        }
        return Yii::$app->response->data = $echo;
        exit;
    }
    public function action_documents()
    {
        $service_id = Yii::$app->request->get('sid');
        $printer_id = Yii::$app->request->get('pid');
        $list = ['invoice' => 'Invoice', 'packingslip' => 'Packingslip', 'creditnote' => 'Credit Note', 'purchase' => 'Purchase orders'];
        //predefined documents
        $service = \common\models\Cloud_Services::find()->alias('s')->where(['s.id' => $service_id])->one();
        $theme_id = \common\models\Platforms_To_Themes::find_one($service->platform_id)->theme_id;
        $theme_name = \common\models\Themes::find_one(['id' => $theme_id])->theme_name;
        //manually added documents
        $docs = array_keys(\common\models\Themes_Settings::find()->select(['setting_value'])->where(['theme_name' => $theme_name, 'setting_group' => 'added_page', 'setting_name' => ['invoice', 'packingslip', 'purchase']])->index_by('setting_value')->as_array()->all());
        foreach ($docs as $name) {
            $key = \common\classes\design::page_name($name);
            $list[$key] = $name;
        }
        $assigned = array_keys(\common\models\Cloud_Printers_Documents::find()->where(['printer_id' => $printer_id])->index_by('document_name')->all());
        return $this->render_ajax('documents', ['list' => $list, 'assigned' => $assigned, 'printer_id' => $printer_id]);
    }
    public function action_save_documents()
    {
        $response = [];
        if (Yii::$app->request->is_post) {
            $printer_id = (int) Yii::$app->request->post('printer_id');
            if ($printer_id) {
                $docs = Yii::$app->request->post('documents');
                $remain = [];
                $response = ['type' => 'alert-success', 'message' => TEXT_MESSEAGE_SUCCESS];
                if (is_array($docs)) {
                    foreach ($docs as $value) {
                        $doc = \common\models\Cloud_Printers_Documents::create($printer_id, $value);
                        if ($doc->validate()) {
                            $doc->save();
                            array_push($remain, $doc->id);
                        } else {
                            $response = ['type' => 'alert-danger', 'message' => ERROR_INVALID_DOCUMENT_ASSIGNMENT];
                        }
                    }
                }
                \common\models\Cloud_Printers_Documents::delete_all(['and', ['not in', 'id', $remain], ['printer_id' => $printer_id]]);
            }
        }
        echo json_encode($response);
        exit;
    }
}