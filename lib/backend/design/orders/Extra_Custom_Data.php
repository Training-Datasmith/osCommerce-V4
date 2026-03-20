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

use common\helpers\Acl;
use yii\base\Widget;
class Extra_Custom_Data extends Widget
{
    public $order;
    public $manager;
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        $show_extra = false;
        if ($rf = Acl::check_extension_allowed('ReferFriend', 'allowed')) {
            $rf_block = $rf::get_admin_order_view($this->order->order_id);
            if ($rf_block) {
                $show_extra = true;
            }
        }
        return $this->render('extra-custom-data', ['manager' => $this->manager, 'order' => $this->order, 'showExtra' => $show_extra, 'rfBlock' => $rf_block ?? null]);
    }
}