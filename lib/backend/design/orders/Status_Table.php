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
class Status_Table extends Widget
{
    public $enquire;
    public $order;
    public $manager;
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        $this->enquire = $this->order->get_status_history_ar_model()->join_with('group')->where(['orders_id' => $this->order->order_id])->as_array()->all();
        return $this->render('status-table', ['manager' => $this->manager, 'enquire' => $this->enquire]);
    }
}