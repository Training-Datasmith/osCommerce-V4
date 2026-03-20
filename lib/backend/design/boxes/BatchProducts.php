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

use common\helpers\Translation;
use yii\base\Widget;
class Batch_Products extends Widget
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
        $batch_selected_widgets = \common\models\Design_Boxes_Tmp::find()->where(['widget_name' => 'BatchSelectedProducts', 'theme_name' => $this->settings['theme_name']])->as_array()->all();
        $product_sources = ['' => '', 'alsopurchased' => Translation::get_translation_value('TEXT_ALSO_PURCHASED', 'admin/main'), 'main_product' => TEXT_PRODUCT, 'xsell_0' => Translation::get_translation_value('FIELDSET_ASSIGNED_XSELL_PRODUCTS', 'admin/categories')];
        $extra_xsell_lists = [];
        if ($ext = \common\helpers\Acl::check_extension_allowed('UpSell')) {
            $extra_xsell_lists = $ext::get_xsell_type_list();
        }
        foreach ($extra_xsell_lists as $extra_xsell_list) {
            $product_sources['xsell_' . $extra_xsell_list['xsell_type_id']] = $extra_xsell_list['xsell_type_name'];
        }
        return $this->render('batch-products.tpl', ['id' => $this->id, 'params' => $this->params, 'settings' => $this->settings, 'visibility' => $this->visibility, 'sorting' => $sorting, 'batchSelectedWidgets' => $batch_selected_widgets, 'product_sources' => $product_sources]);
    }
}