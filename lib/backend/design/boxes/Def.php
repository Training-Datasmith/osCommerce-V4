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
namespace backend\design\boxes;

use backend\models\Admin;
use yii\base\Widget;
class Def extends Widget
{
    public $id;
    public $params;
    public $settings;
    public $block_type;
    public $visibility;
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        $admin = new Admin();
        $designer_mode = $admin->get_additional_data('designer_mode');
        return $this->render('default.tpl', ['id' => $this->id, 'params' => $this->params, 'settings' => $this->settings, 'block_type' => $this->block_type, 'visibility' => $this->visibility, 'designer_mode' => $designer_mode]);
    }
}