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
namespace common\api\Json;

class Json_Parser
{
    public function parse($json_string, $as_array = true)
    {
        $json_string = json_decode($json_string, $as_array);
        return (is_array($json_string) or is_object($json_string)) ? $json_string : false;
    }
}