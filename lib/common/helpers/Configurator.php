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
namespace common\helpers;

use common\extensions\Product_Designer\models as ProductDesignerORM;
class Configurator
{
    use Sql_Trait;
    // moved to extensions\ProductsConfigurator\helpers\Configurator::*
    //    public static function get_pctemplates() {
    //    public static function pctemplates_description($pctemplates_id, $language_id = '') {
    //    public static function getDetails($params, $attributes_details = array()) {
    //    public static function elements_name($elements_id, $language_id = '') {
    /**
     * build select options to product designer field
     * @return array
     */
    public static function get_product_designer_templates()
    {
        $pctemplates_array = [['id' => '0', 'text' => TEXT_NONE]];
        $a_product_designer_templates = Product_Designer_Orm\Product_Designer_Template::find()->all();
        foreach ($a_product_designer_templates as $a_product_designer_template) {
            $pctemplates_array[] = ['id' => $a_product_designer_template->id, 'text' => $a_product_designer_template->name];
        }
        return $pctemplates_array;
    }
    public static function get_products_price_configurator($products_id, $qty = 1)
    {
        $configurator_instance = \common\models\Product\Configurator_Price::get_instance($products_id);
        return $configurator_instance->get_configurator_price(['qty' => $qty]);
    }
}