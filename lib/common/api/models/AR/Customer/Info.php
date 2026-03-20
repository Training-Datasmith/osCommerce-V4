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
namespace common\api\models\AR\Customer;

use common\api\models\AR\Ep_Map;
use yii\db\Expression;
class Info extends Ep_Map
{
    protected $hide_fields = ['customers_info_id', 'time_long', 'token'];
    protected $parent_object;
    public static function table_name()
    {
        return TABLE_CUSTOMERS_INFO;
    }
    public static function primary_key()
    {
        return ['customers_info_id'];
    }
    public function parent_ep_map(Ep_Map $parent_object)
    {
        $this->customers_info_id = $parent_object->customers_id;
        //echo '<pre>'; var_dump($this->customers_info_id); echo '</pre>';
        parent::parent_ep_map($parent_object);
    }
    public function match_indexed_value(Ep_Map $imported_object)
    {
        $this->pending_removal = false;
        return true;
        //return parent::matchIndexedValue($importedObject);
    }
    public function import_array($data)
    {
        if (!isset($data['global_product_notifications'])) {
            $data['global_product_notifications'] = 1;
        }
        $import_result = parent::import_array($data);
        return $import_result;
    }
    public function before_save($insert)
    {
        if ($insert) {
            if (is_null($this->customers_info_date_account_created)) {
                $this->customers_info_date_account_created = new Expression('NOW()');
            }
        } else {
            $this->customers_info_date_account_last_modified = new Expression('NOW()');
        }
        return parent::before_save($insert);
    }
}