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
namespace backend\design\orders;

use yii\base\Widget;
class Product_Assets extends Widget
{
    public $product;
    public $manager;
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        if ($ext = \common\helpers\Acl::check_extension_allowed('ProductAssets', 'allowed')) {
            if ($this->manager->is_instance()) {
                return $ext::render_order_product_asset($this->product['orders_products_id']);
            }
        }
    }
}