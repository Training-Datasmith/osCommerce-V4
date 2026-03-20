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
namespace common\api\models\AR;

use common\api\models\AR\Customer\Address;
use common\api\models\AR\Customer\Info;
class Customer extends Ep_Map
{
    public $credit_amount_delta;
    public $customers_bonus_points_delta;
    protected $hide_fields = ['customers_password', 'last_xml_import', 'last_xml_export', 'affiliate_id', 'customers_alt_email_address', 'customers_alt_telephone', 'customers_cell', 'customers_owc_member', 'customers_type_id', 'customers_selected_template', 'customers_fax', 'dnu_customers_company_vat', 'customers_credit_avail'];
    protected $child_collections = ['addresses' => false, 'info' => false];
    protected $indexed_collections = ['addresses' => 'common\api\models\AR\Customer\Address', 'info' => 'common\api\models\AR\Customer\Info'];
    public static function table_name()
    {
        return TABLE_CUSTOMERS;
    }
    public static function primary_key()
    {
        return ['customers_id'];
    }
    public static function max_addresses()
    {
        $def = 5;
        if (defined('MAX_ADDRESS_BOOK_ENTRIES') && intval(MAX_ADDRESS_BOOK_ENTRIES) > 0) {
            $def = intval(MAX_ADDRESS_BOOK_ENTRIES);
        }
        return min(20, $def);
    }
    public function custom_fields()
    {
        $fields = parent::custom_fields();
        $fields[] = 'credit_amount_delta';
        $fields[] = 'customers_bonus_points_delta';
        return $fields;
    }
    public function get_possible_keys()
    {
        $keys = parent::get_possible_keys();
        $keys = array_values($keys);
        $credit_idx = array_search('credit_amount', $keys);
        $credit_delta_idx = array_search('credit_amount_delta', $keys);
        if ($credit_idx !== false && $credit_delta_idx !== false) {
            unset($keys[$credit_delta_idx]);
            array_splice($keys, $credit_idx + 1, 0, ['credit_amount_delta']);
        }
        $bonus_points_idx = array_search('customers_bonus_points', $keys);
        $bonus_points_delta_idx = array_search('customers_bonus_points_delta', $keys);
        if ($bonus_points_idx !== false && $bonus_points_delta_idx !== false) {
            unset($keys[$bonus_points_delta_idx]);
            array_splice($keys, $bonus_points_idx + 1, 0, ['customers_bonus_points_delta']);
        }
        return $keys;
    }
    public function rules()
    {
        return array_merge(parent::rules(), [['customers_dob', 'default', 'value' => '0000-00-00 00:00:00']]);
    }
    public function init_collection_by_lookup_key_addresses($lookup_keys)
    {
        if (!is_array($this->child_collections['addresses'])) {
            $this->child_collections['addresses'] = [];
            if ($this->customers_id) {
                $this->child_collections['addresses'] = Address::find()->add_select(['*', 'IF(address_book_id=' . intval($this->customers_default_address_id) . ',1,NULL) As is_default'])->where(['customers_id' => $this->customers_id])->order_by([new \yii\db\Expression('address_book_id=' . intval($this->customers_default_address_id) . ' DESC'), 'address_book_id' => SORT_ASC])->all();
            }
        }
        return $this->child_collections['addresses'];
    }
    public function init_collection_by_lookup_key_info($lookup_keys)
    {
        if (!is_array($this->child_collections['info'])) {
            $this->child_collections['info'] = [];
            if ($this->customers_id) {
                $info = Info::find_one(['customers_info_id' => $this->customers_id]);
                if ($info) {
                    $this->child_collections['info'][] = $info;
                }
            }
        }
        return $this->child_collections['info'];
    }
    public function export_array(array $fields = [])
    {
        $export = parent::export_array($fields);
        if (array_key_exists('customers_currency_id', $export) || in_array('customers_currency', $fields)) {
            $export['customers_currency'] = \common\helpers\Currencies::get_currency_code($this->customers_currency_id);
        }
        if (!defined('ALLOW_CUSTOMER_CREDIT_AMOUNT') || ALLOW_CUSTOMER_CREDIT_AMOUNT == 'false') {
            unset($export['credit_amount']);
        } else if (count($fields) == 0 || array_key_exists('credit_amount_delta', $fields)) {
            $export['credit_amount_delta'] = '';
        }
        if (!\common\helpers\Acl::check_extension_allowed('BonusActions')) {
            unset($export['customers_bonus_points']);
        } else if (count($fields) == 0 || array_key_exists('customers_bonus_points_delta', $fields)) {
            $export['customers_bonus_points_delta'] = '';
        }
        return $export;
    }
    public function import_array($data)
    {
        if (array_key_exists('customers_currency', $data)) {
            $data['customers_currency_id'] = \common\helpers\Currencies::get_currency_id($data['customers_currency']);
        }
        if (!defined('ALLOW_CUSTOMER_CREDIT_AMOUNT') || ALLOW_CUSTOMER_CREDIT_AMOUNT == 'false') {
            unset($data['credit_amount']);
            unset($data['credit_amount_delta']);
        } else if (!empty($this->model_flags['credit_amount_delta'])) {
            unset($data['credit_amount']);
            if (array_key_exists('credit_amount_delta', $data) && !empty($data['credit_amount_delta'])) {
                $data['credit_amount'] = (float) $this->credit_amount + (float) $data['credit_amount_delta'];
            }
        }
        if (!\common\helpers\Acl::check_extension_allowed('BonusActions')) {
            unset($data['customers_bonus_points']);
            unset($data['customers_bonus_points_delta']);
        } else if (!empty($this->model_flags['customers_bonus_points_delta'])) {
            unset($data['customers_bonus_points']);
            if (array_key_exists('customers_bonus_points_delta', $data) && !empty($data['customers_bonus_points_delta'])) {
                $data['customers_bonus_points'] = (float) $this->customers_bonus_points + (float) $data['customers_bonus_points_delta'];
            }
        }
        $import_result = parent::import_array($data);
        return $import_result;
    }
    public function before_save($insert)
    {
        if ($insert && (!is_array($this->child_collections['info']) || count($this->child_collections['info']) == 0)) {
            $this->child_collections['info'] = [];
            $this->child_collections['info'][] = new Info();
        }
        return parent::before_save($insert);
    }
    public function after_save($insert, $changed_attributes)
    {
        parent::after_save($insert, $changed_attributes);
        if (array_key_exists('credit_amount', $changed_attributes) || array_key_exists('customers_bonus_points', $changed_attributes)) {
            static $customer;
            if (!is_object($customer)) {
                $customer = new \common\components\Customer();
            }
            if (array_key_exists('credit_amount', $changed_attributes)) {
                $diff = $this->get_old_attribute('credit_amount') - $changed_attributes['credit_amount'];
                if ((float) $diff != 0) {
                    $customer->save_credit_history($this->customers_id, abs($diff), $diff > 0 ? '+' : '-', DEFAULT_CURRENCY, 1, 'Import update', 0, 0);
                }
            }
            if (array_key_exists('customers_bonus_points', $changed_attributes)) {
                $diff = $this->get_old_attribute('customers_bonus_points') - $changed_attributes['customers_bonus_points'];
                if ((float) $diff != 0) {
                    $customer->save_credit_history($this->customers_id, abs($diff), $diff > 0 ? '+' : '-', '', 1, 'Import update', 1, 0);
                }
            }
        }
    }
}