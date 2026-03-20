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
namespace backend\design;

use yii\base\Widget;
class Image extends Widget
{
    public $name;
    //field name to save image
    public $value;
    // saved image name with path from "image" directory
    public $upload;
    //field name to upload new image
    public $delete = '';
    //field name to delete old image
    public $type = 'image';
    public $accepted_files = 'image/*';
    public $unlink = false;
    // show unlink button
    public $width = 0;
    // image width, not fixed, if it used with "height" image aspect ratio will be fixed
    public $height = 0;
    // image height, not fixed, if it used with "width" image aspect ratio will be fixed
    public $position_name = '';
    // position field name to save, only if you set width and height
    public $position_value = '';
    // value of position field
    public $fit_name = '';
    // fit field name to save, only if you set width and height
    public $fit_value = '';
    // value of fit field
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        \backend\design\Data::add_js_data(['tr' => \common\helpers\Translation::translations_for_js(['IMAGE_FIT', 'IMAGE_FIT_COVER', 'IMAGE_FIT_FILL', 'IMAGE_FIT_CONTAIN', 'IMAGE_FIT_NONE', 'IMAGE_FIT_SCALE_DOWN', 'IMAGE_POSITION', 'TEXT_MIDDLE_CENTER', 'TEXT_TOP_LEFT', 'TEXT_TOP_CENTER', 'TEXT_TOP_RIGHT', 'TEXT_MIDDLE_LEFT', 'TEXT_MIDDLE_RIGHT', 'TEXT_BOTTOM_LEFT', 'TEXT_BOTTOM_CENTER', 'TEXT_BOTTOM_RIGHT'], false)]);
        static $id = 0;
        if ($id == 0) {
            $id = rand(1, 10000);
        }
        $id++;
        $data = ['name' => $this->name, 'value' => $this->value, 'upload' => $this->upload, 'delete' => $this->delete, 'acceptedFiles' => $this->accepted_files, 'type' => $this->type, 'unlink' => $this->unlink];
        if ($this->width) {
            $data['width'] = $this->width;
        }
        if ($this->height) {
            $data['height'] = $this->height;
        }
        if ($this->position_name) {
            $data['positionName'] = $this->position_name;
        }
        if ($this->position_value) {
            $data['positionValue'] = $this->position_value;
        }
        if ($this->fit_name) {
            $data['fitName'] = $this->fit_name;
        }
        if ($this->fit_value) {
            $data['fitValue'] = $this->fit_value;
        }
        return $this->render('image.tpl', ['data' => addslashes(json_encode($data)), 'id' => $id]);
    }
}