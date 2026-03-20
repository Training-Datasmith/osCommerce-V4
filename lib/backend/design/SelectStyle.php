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

use common\models\Themes_Styles_Groups;
use common\models\Themes_Styles_Main;
use yii\base\Widget;
class Select_Style extends Widget
{
    public $name;
    public $value;
    public $type;
    public $theme_name;
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        $styles = Themes_Styles_Main::find()->where(['theme_name' => $this->theme_name, 'type' => $this->type])->as_array()->all();
        $group_styles = Themes_Styles_Groups::find()->where(['theme_name' => $this->theme_name])->as_array()->all();
        $main_sub_styles = Style::main_styles($this->theme_name);
        return $this->render('select-style.tpl', ['name' => $this->name, 'value' => $this->value, 'type' => $this->type, 'styles' => $styles, 'groupStyles' => $group_styles, 'mainSubStyles' => $main_sub_styles, 'theme_name' => $this->theme_name]);
    }
}