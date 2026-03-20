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
namespace common\classes\modules;

use common\services\Payment_Transaction_Manager;
interface Transactional_Interface
{
    /**
     * @param string $transaction_id transaction id from payment system
     * @param PaymentTransactionManager $tManager default null
     */
    public function get_transaction_details($transaction_id, Payment_Transaction_Manager $t_manager = null);
    public function can_refund($transaction_id);
    public function refund($transaction_id, $amount = 0);
    public function can_void($transaction_id);
    public function void($transaction_id);
    /**
     * can capture/release/authenticate - module should call correct method according transaction details/state
     * @param string $transaction_id transaction id from payment system
     * @return int|false  1 - auth, 2 - deferred, false - not allowed
     */
    public function can_capture($transaction_id);
    public function capture($transaction_id, $amount = 0);
    public function can_reauthorize($transaction_id);
    public function reauthorize($transaction_id, $amount = 0);
}