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
class Order_Statuses extends Widget
{
    public $manager;
    public $admin;
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        $cart = $this->manager->get_cart();
        if ($cart->order_id) {
            return $this->render('order-statuses', ['url' => Yii::$app->url_manager->create_absolute_url(array_merge(['editor/checkout', 'action' => 'show_statuses'], Yii::$app->request->get_query_params()))]);
        }
    }
}