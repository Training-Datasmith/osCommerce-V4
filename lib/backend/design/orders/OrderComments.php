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

use common\models\Admin;
use yii\base\Widget;
class Order_Comments extends Widget
{
    public $manager;
    public $order;
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        global $login_id;
        $admin = Admin::find_one(['admin_id' => $login_id]);
        $groups = \common\models\Access_Levels::find()->select(['access_levels_id', 'access_levels_name'])->with(['admins' => function (\yii\db\Active_Query $query) {
            return $query->order_by('admin_firstname, admin_lastname');
        }])->as_array()->all();
        $comments = \common\models\Orders_Comments::find()->where(['orders_id' => $this->order->order_id, 'for_invoice' => 0])->order_by('date_added desc')->with('admin')->all();
        if ($comments) {
            foreach ($comments as $_key => $comment) {
                if ((int) $comment->admin_id == (int) $login_id) {
                    continue;
                }
                $data = json_decode($comment->visible, true);
                if ($data) {
                    if (key($data) == 'm') {
                        if ($data['m'] != $login_id) {
                            unset($comments[$_key]);
                        }
                    } elseif (key($data) == 'g') {
                        if ($admin) {
                            $al = $admin->get_accesslevel()->one();
                            if ($al && $al->access_levels_id != $data['g']) {
                                unset($comments[$_key]);
                            }
                        }
                    }
                }
            }
        }
        ksort($comments);
        return $this->render('order-comments', ['order' => $this->order, 'manager' => $this->manager, 'comments' => $comments, 'groups' => $groups]);
    }
}