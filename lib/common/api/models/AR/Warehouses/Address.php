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
namespace common\api\models\AR\Warehouses;

use backend\models\EP\Tools;
use common\api\models\AR\Ep_Map;
class Address extends Ep_Map
{
    public $is_default;
    public $save_lookup = false;
    protected $hide_fields = ['warehouse_id'];
    protected $parent_object;
    public static function table_name()
    {
        return TABLE_WAREHOUSES_ADDRESS_BOOK;
    }
    public static function primary_key()
    {
        return ['warehouses_address_book_id'];
    }
    /*public function customFields()
      {
          return ['is_default'];
      }*/
    public function rules()
    {
        return array_merge(parent::rules(), [[['entry_firstname', 'entry_lastname', 'entry_street_address', 'entry_postcode', 'entry_city'], 'default', 'value' => '']]);
    }
    public function parent_ep_map(Ep_Map $parent_object)
    {
        $this->warehouse_id = $parent_object->warehouse_id;
        $this->parent_object = $parent_object;
        parent::parent_ep_map($parent_object);
    }
    public function match_indexed_value(Ep_Map $imported_object)
    {
        if (!is_null($imported_object->warehouses_address_book_id) && !is_null($this->warehouses_address_book_id) && $imported_object->warehouses_address_book_id == $this->warehouses_address_book_id) {
            $this->pending_removal = false;
            return true;
        }
        $compare_fields = ['is_default', 'entry_company', 'entry_company_vat', 'entry_company_reg_number', 'entry_street_address', 'entry_suburb', 'entry_postcode', 'entry_city', 'entry_state', 'entry_country_id', 'entry_zone_id'];
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
        }
        return $match;
    }
    public function export_array(array $fields = [])
    {
        $data = parent::export_array($fields);
        if (array_key_exists('entry_country_id', $data)) {
            $tools = new Tools();
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
            $tools = new Tools();
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
}