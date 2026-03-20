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

use common\api\models\AR\Warehouses\Address;
use common\api\models\AR\Warehouses\Info;
use yii\db\Expression;
class Warehouses extends Ep_Map
{
    protected $hide_fields = [];
    protected $child_collections = ['addresses' => false, 'info' => false];
    protected $indexed_collections = ['addresses' => 'common\api\models\AR\Warehouses\Address', 'info' => 'common\api\models\AR\Warehouses\Info'];
    public static function table_name()
    {
        return TABLE_WAREHOUSES;
    }
    public static function primary_key()
    {
        return ['warehouse_id'];
    }
    public function rules()
    {
        return array_merge(parent::rules(), []);
    }
    public function init_collection_by_lookup_key_addresses($lookup_keys)
    {
        if (!is_array($this->child_collections['addresses'])) {
            $this->child_collections['addresses'] = [];
            if ($this->warehouse_id) {
                $this->child_collections['addresses'] = Address::find()->add_select(['*'])->where(['warehouse_id' => $this->warehouse_id])->all();
            }
        }
        return $this->child_collections['addresses'];
    }
    public function init_collection_by_lookup_key_info($lookup_keys)
    {
        if (!is_array($this->child_collections['info'])) {
            $this->child_collections['info'] = [];
            if ($this->warehouse_id) {
                $this->child_collections['info'][] = Info::find_one(['warehouse_id' => $this->warehouse_id]);
            }
        }
        return $this->child_collections['info'];
    }
    public function export_array(array $fields = [])
    {
        $export = parent::export_array($fields);
        return $export;
    }
    public function import_array($data)
    {
        /*        if ( array_key_exists('customers_currency', $data) ) {
                            $data['customers_currency_id'] = \common\helpers\Currencies::getCurrencyId($data['customers_currency']);
                        }
                */
        $import_result = parent::import_array($data);
        return $import_result;
    }
    public function before_save($insert)
    {
        if ($insert && (!is_array($this->child_collections['info']) || count($this->child_collections['info']) == 0)) {
            $this->child_collections['info'] = [];
            $this->child_collections['info'][] = new Info();
        }
        if ($insert) {
            if (is_null($this->date_added)) {
                $this->date_added = new Expression('NOW()');
            }
        } else {
            $this->last_modified = new Expression('NOW()');
        }
        /*
        if ( !$insert ) {
            $creditChanged = $this->getDirtyAttributes(['credit_amount']);
            if ( count($creditChanged)>0 ) {
                $old_credit_amount = $this->getOldAttribute('credit_amount');
                if ( number_format($old_credit_amount,4,'.','')!=number_format($old_credit_amount,4,'.','') ) {
                    tep_db_perform(TABLE_CUSTOMERS_CREDIT_HISTORY,[
        | warehouse_id                | int(11)       | NO     |       |    <null> |                |
        | credit_prefix               | varchar(1)    | NO     |       |    <null> |                |
        | credit_amount               | decimal(11,2) | NO     |       |    <null> |                |
        | currency                    | char(3)       | NO     |       |    <null> |                |
        | currency_value              | decimal(14,6) | NO     |       |    <null> |                |
        | customer_notified           | tinyint(1)    | NO     |       |    <null> |                |
        | comments                    | mediumtext    | NO     |       |    <null> |                |
        | date_added                  | datetime      | NO     |       |    <null> |                |
        | admin_id
                    ]);
                }
            }
        }
        */
        return parent::before_save($insert);
    }
}