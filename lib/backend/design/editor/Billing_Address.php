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
class Billing_Address extends Widget
{
    public $manager;
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        return $this->render('billing-address', ['manager' => $this->manager, 'urlCheckout' => Yii::$app->url_manager->create_absolute_url(['editor/checkout', 'action' => 'get_address_list', 'type' => 'billing', 'currentCart' => Yii::$app->request->get('currentCart')])]);
    }
}