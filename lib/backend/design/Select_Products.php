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
class Select_Products extends Widget
{
    public $name;
    public $select_title;
    public $selected_name;
    public $selected_products;
    public $selected_prefix;
    public $selected_sort_name;
    public $selected_back_link;
    public $selected_back_link_c;
    public $only_include_js = false;
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        $tr = \common\helpers\Translation::translations_for_js(['TEXT_ROOT', 'TEXT_ADD', 'IMAGE_BACK', 'TABLE_HEADING_PRICE_EXCLUDING_TAX', 'TABLE_HEADING_PRICE_INCLUDING_TAX', 'TEXT_STOCK_QTY', 'TEXT_MODEL', 'TEXT_TYPE_CHOOSE_PRODUCT', 'TEXT_PRODUCT_NOT_SELECTED', 'TEXT_IMG', 'TEXT_LABEL_NAME', 'TEXT_PRICE', 'FIELDSET_ASSIGNED_PRODUCTS', 'SEARCH_BY_ATTR', 'TEXT_MODEL', 'TEXT_BACKLINK', 'BATCH_BACK_LINK_TOOLTIP_TITLE', 'ADD_SELECTED_PRODUCTS', 'TEXT_ADDED'], false);
        \backend\design\Data::add_js_data(['tr' => $tr]);
        $selected_products = [];
        if (is_array($this->selected_products)) {
            foreach ($this->selected_products as $product) {
                $selected_products[] = $product;
            }
        }
        return $this->render('select-products.tpl', ['name' => $this->name, 'selectTitle' => $this->select_title, 'selectedName' => $this->selected_name, 'selectedProducts' => addslashes(json_encode($selected_products)), 'selectedPrefix' => $this->selected_prefix, 'selectedSortName' => $this->selected_sort_name, 'selectedBackLink' => $this->selected_back_link, 'selectedBackLink_c' => $this->selected_back_link_c, 'onlyIncludeJs' => $this->only_include_js]);
    }
}