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
class Featured_Products extends Widget
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
        $languages_id = (int) \Yii::$app->settings->get('languages_id');
        $featured_types = \common\models\Featured_Types::find()->where(['language_id' => $languages_id])->as_array()->all();
        $featured_types_arr = [];
        $featured_types_arr[0] = BOX_CATALOG_FEATURED;
        foreach ($featured_types as $featured_type) {
            $featured_types_arr[$featured_type['featured_type_id']] = $featured_type['featured_type_name'];
        }
        $sorting_options = \common\helpers\Sorting::get_possible_sort_options();
        $sorting_options[''] = TEXT_RANDOM;
        $sorting = \common\helpers\Html::drop_down_list('setting[0][sort_order]', $this->settings[0]['sort_order'], $sorting_options, ['class' => 'form-control']);
        return $this->render('featured-products.tpl', ['id' => $this->id, 'params' => $this->params, 'settings' => $this->settings, 'visibility' => $this->visibility, 'featuredTypes' => $featured_types_arr, 'sorting' => $sorting]);
    }
}