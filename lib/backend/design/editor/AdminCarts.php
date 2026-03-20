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

use yii\base\Widget;
class Admin_Carts extends Widget
{
    public $manager;
    public $admin;
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        if (!\common\helpers\Acl::rule(['ACL_ORDER', 'TEXT_UNSAVED_CARTS'])) {
            return '';
        }
        $unsaved_carts = $this->admin->get_virtual_cart_i_ds();
        if (is_array($unsaved_carts) && count($unsaved_carts)) {
            $_carts = $this->admin->get_carts();
            foreach ($unsaved_carts as $_ids) {
                $_customer_id = $_carts[$_ids]['customers_id'] ?? 0;
                $admin_choice[] = $this->render('mini', ['cart' => $_ids, 'basketId' => $_carts[$_ids]['basket_id'], 'orders_id' => $_carts[$_ids]['order_id'], 'customer' => $_customer_id ? \common\helpers\Customer::get_customer_data($_customer_id) : '', 'opened' => $_ids == $this->admin->get_current_cart_id()]);
            }
            return $this->render('admin-carts', ['admin_choice' => $admin_choice, 'saved' => $this->admin->is_cart_saved($this->admin->get_current_cart_id())]);
        }
    }
}