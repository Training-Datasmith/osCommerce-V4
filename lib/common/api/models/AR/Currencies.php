<?php

declare (strict_types=1);
/*
 * This file is part of osCommerce ecommerce platform.
 * osCommerce the ecommerce
 *
 * @link https://www.oscommerce.com
 * @copyright Copyright (c) 2005 Holbi Group Ltd
 *
 * Released under the GNU General Public License
 * For the full copyright and license information, please view the LICENSE.TXT file that was distributed with this source code.
 */
namespace common\api\models\AR;

class Currencies extends Ep_Map
{
    protected $hide_fields = [];
    protected $child_collections = [];
    protected $indexed_collections = [];
    public static function table_name()
    {
        return TABLE_CURRENCIES;
    }
    public static function primary_key()
    {
        return ['currencies_id'];
    }
    public function rules()
    {
        return array_merge(parent::rules(), []);
    }
}