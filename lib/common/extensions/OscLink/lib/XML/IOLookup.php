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
namespace Osc_Link\XML;

use backend\models\EP\Tools;
class Io_Lookup
{
    protected $tools;
    public function __construct()
    {
        $this->tools = new Tools();
    }
    public function lookup_order_status($status_name, $create_missing = false)
    {
        return $this->tools->lookup_order_status($status_name, true);
    }
}