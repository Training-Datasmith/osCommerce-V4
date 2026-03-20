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
namespace backend\models\Product_Edit;

class Tab_Access
{
    protected $sub_product = false;
    protected $supplier_data_allowed = true;
    public function set_product($product)
    {
        if (is_object($product) && $product->parent_products_id) {
            $this->sub_product = true;
            $this->supplier_data_allowed = $product->parent_products_id != $product->products_id_stock;
        }
    }
    public function check_sub_product_tabs($tab_code)
    {
        $allowed_for_sub_products = ['TEXT_NAME_DESCRIPTION', 'TEXT_MAIN_DETAILS', 'TAB_PROPERTIES', 'TAB_IMAGES', 'TEXT_VIDEO', 'TEXT_SEO', 'TEXT_MARKETING', 'TAB_DOCUMENTS', 'TAB_IMPORT_EXPORT', 'TAB_NOTES'];
        if (true) {
            $allowed_for_sub_products[] = 'TEXT_PRICE_COST_W';
            $allowed_for_sub_products[] = 'TEXT_ATTR_INVENTORY';
        }
        return in_array($tab_code, $allowed_for_sub_products);
    }
    public function is_sub_product()
    {
        return $this->sub_product;
    }
    public function allow_suppliers_data()
    {
        return $this->supplier_data_allowed;
    }
    public function tab_data_save($tab_code)
    {
        if ($this->sub_product && !$this->check_sub_product_tabs($tab_code)) {
            return false;
        }
        return \common\helpers\Acl::rule(['TABLE_HEADING_PRODUCTS', 'IMAGE_EDIT', $tab_code]);
    }
    public function tab_view($tab_code)
    {
        if ($this->sub_product && !$this->check_sub_product_tabs($tab_code)) {
            return false;
        }
        return \common\helpers\Acl::rule(['TABLE_HEADING_PRODUCTS', 'IMAGE_EDIT', $tab_code]);
    }
}