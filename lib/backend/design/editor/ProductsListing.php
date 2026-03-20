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
class Products_Listing extends Widget
{
    public $manager;
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        $response = \common\helpers\Gifts::get_give_aways_query();
        $giveaway_count = $response['giveaway_query']->count();
        return $this->render('product-listing', ['manager' => $this->manager, 'giftWrapExist' => $this->manager->get_cart()->cart_allow_giftwrap(), 'queryParams' => array_merge(['editor/show-basket'], Yii::$app->request->get_query_params()), 'giveaway_count' => $giveaway_count]);
    }
}