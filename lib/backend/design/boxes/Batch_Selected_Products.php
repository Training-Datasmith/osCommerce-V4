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
class Batch_Selected_Products extends Widget
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
        $sorting_options = \common\helpers\Sorting::get_possible_sort_options();
        $sorting_options[''] = TEXT_RANDOM;
        $sorting = \common\helpers\Html::drop_down_list('setting[0][sort_order]', $this->settings[0]['sort_order'], $sorting_options, ['class' => 'form-control']);
        return $this->render('batch-selected-products.tpl', ['id' => $this->id, 'params' => $this->params, 'settings' => $this->settings, 'visibility' => $this->visibility, 'sorting' => $sorting]);
    }
}