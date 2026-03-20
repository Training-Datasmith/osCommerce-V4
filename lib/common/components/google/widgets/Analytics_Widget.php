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
namespace common\components\google\widgets;

use Yii;
class Analytics_Widget extends \yii\base\Widget
{
    public $json_file;
    public $view_id;
    public $owner;
    public $description;
    public $platform_id;
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        Yii::$app->get_view()->register_js_file(Yii::$app->request->base_url . '/plugins/fileupload/jquery.fileupload.js');
        return $this->render('analytics-config', ['jsonFile' => $this->json_file, 'viewId' => $this->view_id, 'platformId' => $this->platform_id, 'owner' => $this->owner, 'description' => $this->description]);
    }
}