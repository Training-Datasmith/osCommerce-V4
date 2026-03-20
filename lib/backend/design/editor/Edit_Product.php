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
namespace backend\design\editor;

use Yii;
use yii\base\Widget;
class Edit_Product extends Widget
{
    public $manager;
    public $uprid;
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        $insulator = new \backend\services\Product_Insulator_Service($this->uprid, $this->manager);
        $insulator->edit = true;
        $product_details = $insulator->get_product_main_details();
        return $this->render('edit-product', ['manager' => $this->manager, 'product' => $product_details, 'rates' => $this->manager->get_order_tax_rates(), 'queryParams' => array_merge(['editor/show-basket'], Yii::$app->request->get_query_params()), 'currentUrl' => Yii::$app->request->url]);
    }
}