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

use yii\base\Widget;
class Specials_Products extends Widget
{
    public $id;
    public $params;
    public $settings;
    public $visibility;
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $specials_types_arr = \common\models\Specials_Types::find()->where(['language_id' => $languages_id])->select('specials_type_name, specials_type_id')->as_array()->index_by('specials_type_id')->column();
        if (!is_array($specials_types_arr)) {
            $specials_types_arr = [];
        }
        $specials_types_arr[0] = '';
        ksort($specials_types_arr);
        return $this->render('specials-products.tpl', ['id' => $this->id, 'params' => $this->params, 'settings' => $this->settings, 'visibility' => $this->visibility, 'types' => $specials_types_arr]);
    }
}