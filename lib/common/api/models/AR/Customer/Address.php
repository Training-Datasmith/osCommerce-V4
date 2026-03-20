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

use backend\models\EP\Tools;
use common\api\models\AR\Customer;
use common\api\models\AR\Ep_Map;
class Address extends Ep_Map
{
    public $is_default;
    public $save_lookup = false;
    public $is_shiping_address = false;
    public $is_billing_address = false;
    protected $hide_fields = ['customers_id', 'entry_company_vat_date'];
    protected $parent_object;
    public static function table_name()
    {
        return TABLE_ADDRESS_BOOK;
    }
    public static function primary_key()
    {
        return ['address_book_id'];
    }
    public function custom_fields()
    {
        return ['is_default'];
    }
    public function rules()
    {
        return array_merge(parent::rules(), [[['entry_firstname', 'entry_lastname', 'entry_street_address', 'entry_postcode', 'entry_city'], 'default', 'value' => '']]);
    }
    public function parent_ep_map(Ep_Map $parent_object)
    {
        $this->customers_id = $parent_object->customers_id;
        $this->parent_object = $parent_object;
        parent::parent_ep_map($parent_object);
    }
    public function match_indexed_value(Ep_Map $imported_object)
    {
        if (!is_null($imported_object->address_book_id) && !is_null($this->address_book_id) && $imported_object->address_book_id == $this->address_book_id) {
            $this->pending_removal = false;
            return true;
        }
        $compare_fields = [
            'entry_company',
            'entry_company_vat',
            'entry_customs_number',
            //VL???            'entry_gender',
            'entry_firstname',
            'entry_lastname',
            'entry_street_address',
            'entry_suburb',
            'entry_postcode',
            'entry_city',
            'entry_state',
            'entry_country_id',
            'entry_zone_id',
            'entry_telephone',
        ];
        $match = true;
        foreach ($compare_fields as $compare_field) {
            if (!$this->has_attribute($compare_field)) {
                continue;
            }
            if (in_array($compare_field, ['entry_country_id', 'entry_zone_id'])) {
                // integer fields
                if (intval($imported_object->{$compare_field}) !== intval($this->{$compare_field})) {
                    $match = false;
                    break;
                }
            } elseif (strval($imported_object->{$compare_field}) !== strval($this->{$compare_field})) {
                $match = false;
                break;
            }
        }
        if ($match) {
            $this->pending_removal = false;
        } else {
            //          echo "#### not match by \$compareField $compareField <PRE>"  . __FILE__ .':' . __LINE__ . ' ' . print_r($importedObject, 1) . ' this:' . print_r($this, 1) ."</PRE>";
        }
        return $match;
    }
    public function after_find()
    {
        parent::after_find();
        if (!is_null($this->is_default)) {
            $this->is_default = !!$this->is_default;
        }
    }
    public function export_array(array $fields = [])
    {
        $data = parent::export_array($fields);
        if (array_key_exists('entry_country_id', $data)) {
            $tools = Tools::get_instance();
            $country_info = $tools->get_country_info($data['entry_country_id']);
            $data['entry_country_iso2'] = $country_info['countries_iso_code_2'];
        }
        if (array_key_exists('entry_state', $data) && is_numeric($this->entry_zone_id)) {
            $data['entry_state'] = \common\helpers\Zones::get_zone_name($data['entry_country_id'], $this->entry_zone_id, $this->entry_state);
        }
        return $data;
    }
    public function import_array($data)
    {
        if (isset($data['entry_country_iso2'])) {
            $tools = Tools::get_instance();
            $data['entry_country_id'] = $tools->get_country_id($data['entry_country_iso2']);
        }
        if (isset($data['entry_state'])) {
            $data['entry_zone_id'] = \common\helpers\Zones::get_zone_id($data['entry_country_id'], $data['entry_state']);
            if ($data['entry_zone_id']) {
                $data['entry_state'] = '';
            }
        }
        $import_result = parent::import_array($data);
        if (array_key_exists('is_default', $data)) {
            $this->is_default = !!$data['is_default'];
        }
        if (array_key_exists('save_lookup', $data)) {
            $this->save_lookup = $data['save_lookup'];
        }
        return $import_result;
    }
    public function after_save($insert, $changed_attributes)
    {
        parent::after_save($insert, $changed_attributes);
        if ($this->is_default && $this->parent_object->customers_default_address_id !== $this->address_book_id) {
            if (!empty($this->entry_firstname) && !empty($this->entry_lastname)) {
                $this->get_db()->create_command()->update(Customer::table_name(), ['customers_default_address_id' => $this->address_book_id, 'customers_gender' => $this->entry_gender, 'customers_firstname' => $this->entry_firstname, 'customers_lastname' => $this->entry_lastname], ['customers_id' => (int) $this->customers_id])->execute();
            }
            if (is_object($this->parent_object)) {
                $this->parent_object->refresh();
            }
            /*
                        $this->parentObject->customers_default_address_id = $this->address_book_id;
                        $this->parentObject->customers_gender = $this->entry_gender;
                        $this->parentObject->customers_firstname = $this->entry_firstname;
                        $this->parentObject->customers_lastname = $this->entry_lastname;
                        $this->parentObject->save();*/
        }
    }
}