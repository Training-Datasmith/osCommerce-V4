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

use common\helpers\Address;
use Yii;
class Address_Formats_Controller extends Sceleton
{
    public $acl = ['BOX_HEADING_CONFIGURATION', 'BOX_ADDRESS_FORMATS'];
    public function __construct($id, $module = null)
    {
        \common\helpers\Translation::init('admin/address-formats');
        $this->navigation[] = ['link' => \Yii::$app->url_manager->create_url('address-formats/index'), 'title' => BOX_ADDRESS_FORMATS];
        parent::__construct($id, $module);
    }
    public function action_index()
    {
        $this->top_buttons[] = '<span class="btn btn-confirm" onclick="$(\'#frmMain\').trigger(\'submit\')">' . TEXT_APPLY . '</span>';
        $formats = Address::get_formats();
        if (Yii::$app->request->is_post) {
            $formats = Yii::$app->request->post('formats');
            if (is_array($formats)) {
                $message = TEXT_MESSEAGE_SUCCESS;
                $status = 'ok';
                $curren_ids = [];
                $_titles = Yii::$app->request->post('formats_titles');
                foreach ($formats as $format_id => $format) {
                    if ($f_m = Address::save_address_format($format_id, $format)) {
                        $curren_ids[] = $format_id;
                        $f_m->address_format_title = $_titles[$format_id];
                        $f_m->save();
                    }
                }
                if ($curren_ids) {
                    $_to_delete = \common\models\Address_Format::find()->where(['not in', 'address_format_id', $curren_ids])->all();
                    if ($_to_delete) {
                        foreach ($_to_delete as $fid) {
                            if (!Address::check_buisy_address_formats([$fid->address_format_id])) {
                                $fid->delete();
                            } else {
                                $message = ERROR_FORMAT_IS_BUSY;
                                $status = 'bad';
                            }
                        }
                    }
                }
            }
            if (Yii::$app->request->is_ajax) {
                Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
                Yii::$app->response->data = ['status' => $status, 'message' => $message];
                return;
            } else {
                return $this->redirect(['address-formats/index']);
            }
        }
        return $this->render('index', ['formats' => $formats]);
    }
    public function action_new()
    {
        $format = new \common\models\Address_Format();
        $format->address_format_title = 'Untitled Format';
        $format->save();
        return $this->render_ajax('format', ['format' => $format]);
    }
}