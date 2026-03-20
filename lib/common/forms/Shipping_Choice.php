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
namespace common\forms;

use yii\base\Model;
class Shipping_Choice extends Model
{
    public $choice = 1;
    protected $_manager;
    public function __construct(\common\services\Order_Manager $manager, $config = [])
    {
        $this->_manager = $manager;
        if (!$this->_manager->get_pickup_shipping_quotes()) {
            $this->choice = 1;
        }
        parent::__construct($config);
    }
    public function rules()
    {
        return ['choice'];
    }
    public function show_customer_choices()
    {
        return [1 => SHIP_TO_ADDRESS, 0 => COLLECT_FROM_POINT];
    }
    public function set_choice($choice)
    {
        $this->choice = (int) $choice;
    }
    public function get_choice()
    {
        return $this->choice;
    }
}