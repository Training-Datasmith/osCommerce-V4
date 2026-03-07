<?php

declare(strict_types=1);
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

class StatusTable extends Widget
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
        $this->enquire = $this->order->getStatusHistoryARModel()
                ->joinWith('group')->where(['orders_id' => $this->order->order_id])
                ->asArray()->all();

        return $this->render('status-table', [
            'manager' => $this->manager,
            'enquire' => $this->enquire,
        ]);
    }
}
