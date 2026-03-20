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

use common\models\Restriction;
use Yii;
/**
 * default controller to handle user requests.
 */
class Ip_Restriction_Controller extends Sceleton
{
    public $acl = ['TEXT_SETTINGS', 'BOX_HEADING_TOOLS', 'BOX_IP_RESTRICTION'];
    public function __construct($id, $module = null)
    {
        \common\helpers\Translation::init('admin/ip-restriction');
        parent::__construct($id, $module);
    }
    public function action_index()
    {
        global $language;
        $this->selected_menu = ['settings', 'tools', 'ip-restriction'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('ip-restriction/index'), 'title' => HEADING_TITLE];
        $this->top_buttons[] = '<a href="javascript:void(0)" class="btn btn-primary" onclick="return ipEdit(0)">' . IMAGE_NEW . '</a>';
        $this->view->heading_title = HEADING_TITLE;
        $this->view->row_id = (int) Yii::$app->request->get('row_id', 0);
        $this->view->ip_table = [['title' => TABLE_IP, 'not_important' => 0]];
        $messages = [];
        if (isset($_SESSION['messages'])) {
            $messages = $_SESSION['messages'];
            unset($_SESSION['messages']);
            if (!is_array($messages)) {
                $messages = [];
            }
        }
        return $this->render('index', ['messages' => $messages]);
    }
    public function action_list()
    {
        $draw = Yii::$app->request->get('draw', 1);
        $start = Yii::$app->request->get('start', 0);
        $length = Yii::$app->request->get('length', 10);
        parse_str(Yii::$app->request->get('filter', ''), $output);
        $response_list = [];
        if ($length == -1) {
            $length = 10000;
        }
        $records_total = 0;
        $forbidden = Restriction::find();
        if (isset($_GET['search']['value']) && tep_not_null($_GET['search']['value'])) {
            $forbidden->and_where(['like', 'forbidden_address', $_GET['search']['value']]);
        }
        if (isset($_GET['order'][0]['column']) && $_GET['order'][0]['dir']) {
            switch ($_GET['order'][0]['column']) {
                case 0:
                    $forbidden->order_by('forbidden_address ' . $_GET['order'][0]['dir']);
                    break;
                default:
                    $forbidden->order_by('forbidden_address ');
                    break;
            }
        } else {
            $forbidden->order_by('forbidden_address ');
        }
        $records_total = $forbidden->count();
        $forbidden->limit($length)->offset($start);
        $rows = $forbidden->all();
        if (is_array($rows)) {
            foreach ($rows as $key => $forbidden) {
                $response_list[] = [$forbidden->get_attribute('forbidden_address') . '<input class="cell_identify" type="hidden" value="' . $forbidden->get_attribute('forbidden_id') . '">'];
            }
        }
        $response = ['draw' => $draw, 'recordsTotal' => $records_total, 'recordsFiltered' => $records_total, 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_preview()
    {
        $forbidden_id = Yii::$app->request->post('forbidden_id', 0);
        $forbidden = Restriction::find()->where(['forbidden_id' => $forbidden_id])->one();
        $this->view->row_id = Yii::$app->request->post('row_id', 0);
        return $this->render_ajax('view', ['forbidden' => $forbidden]);
    }
    public function action_edit()
    {
        $forbidden_id = Yii::$app->request->post('forbidden_id', 0);
        if ($forbidden_id) {
            $forbidden = Restriction::find()->where(['forbidden_id' => $forbidden_id])->one();
        } else {
            $forbidden = new Restriction();
        }
        return $this->render_ajax('edit', ['forbidden' => $forbidden]);
    }
    public function action_save()
    {
        $forbidden_id = Yii::$app->request->post('forbidden_id', 0);
        $forbidden_address = Yii::$app->request->post('forbidden_address', '');
        if ($forbidden_id) {
            $forbidden = Restriction::find()->where(['forbidden_id' => $forbidden_id])->one();
        } else {
            $forbidden = new Restriction();
        }
        $forbidden->set_attribute('forbidden_address', $forbidden_address);
        if ($forbidden->validate()) {
            if (!$forbidden->has_errors()) {
                $forbidden->save();
            }
        }
        //echo '<pre>';print_r();die;
        echo json_encode($forbidden->get_errors());
        exit;
    }
    public function action_delete()
    {
        $forbidden_id = Yii::$app->request->post('forbidden_id', 0);
        if ($forbidden_id) {
            $forbidden = Restriction::find()->where(['forbidden_id' => $forbidden_id])->one();
            $forbidden->delete();
        }
        echo 'ok';
    }
}