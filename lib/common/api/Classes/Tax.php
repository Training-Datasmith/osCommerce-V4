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
namespace common\api\Classes;

class Tax extends Abstract_Class
{
    public $class_id = null;
    public $class_record = [];
    public $zone_record_array = [];
    public function get_id()
    {
        return $this->class_id;
    }
    public function set_id($class_id)
    {
        $class_id = (int) $class_id;
        if ($class_id >= 0) {
            $this->class_id = $class_id;
            return true;
        }
        return $this;
    }
    public function load($class_id)
    {
        $this->clear();
        $class_id = (int) $class_id;
        $class_record = \common\models\Tax_Class::find()->where(['tax_class_id' => $class_id])->one();
        if ($class_record instanceof \common\models\Tax_Class) {
            $this->class_id = $class_id;
            $this->class_record = $class_record->to_array();
            unset($class_record);
            // ZONE
            foreach (\common\models\Tax_Rates::find()->where(['tax_class_id' => $this->class_id])->group_by('tax_zone_id')->as_array(true)->all() as $zone_array) {
                $zone_record = \common\models\Tax_Zones::find()->where(['geo_zone_id' => (int) $zone_array['tax_zone_id']])->as_array(true)->one();
                // RATE
                $zone_record['rateRecordArray'] = \common\models\Tax_Rates::find()->where(['tax_class_id' => $this->class_id, 'tax_zone_id' => (int) $zone_array['tax_zone_id']])->as_array(true)->all();
                // EOF RATE
                // ZONE TO GEO ZONE
                foreach (\common\models\Zones_To_Tax_Zones::find()->where(['geo_zone_id' => (int) $zone_record['geo_zone_id']])->as_array(true)->all() as $zone_to_geo_record) {
                    $zone_to_geo_record['zone_id'] = (int) $zone_to_geo_record['zone_id'];
                    $zone_to_geo_record['zone_country_id'] = (int) $zone_to_geo_record['zone_country_id'];
                    // GEO ZONE
                    $geo_zone_record = (array) \common\models\Zones::find()->where(['zone_country_id' => (int) $zone_to_geo_record['zone_country_id'], 'zone_id' => (int) $zone_to_geo_record['zone_id']])->as_array(true)->one();
                    $zone_to_geo_record['zone_code'] = trim(isset($geo_zone_record['zone_code']) ? $geo_zone_record['zone_code'] : '');
                    $zone_to_geo_record['zone_name'] = trim(isset($geo_zone_record['zone_name']) ? $geo_zone_record['zone_name'] : '');
                    unset($geo_zone_record);
                    // EOF GEO ZONE
                    // COUNTRY
                    $language_id = \common\classes\language::default_id();
                    $language_code = \common\classes\language::get_code($language_id, true);
                    $country_record = (array) \common\models\Countries::find()->where(['countries_id' => (int) $zone_to_geo_record['zone_country_id'], 'language_id' => $language_id])->as_array(true)->one();
                    $zone_to_geo_record['countries_name'] = trim(isset($country_record['countries_name']) ? $country_record['countries_name'] : '');
                    $zone_to_geo_record['countries_iso_code_2'] = trim(isset($country_record['countries_iso_code_2']) ? $country_record['countries_iso_code_2'] : '');
                    $zone_to_geo_record['countries_iso_code_3'] = trim(isset($country_record['countries_iso_code_3']) ? $country_record['countries_iso_code_3'] : '');
                    $zone_to_geo_record['address_format_id'] = trim(isset($country_record['address_format_id']) ? $country_record['address_format_id'] : '');
                    $zone_to_geo_record['language_id'] = trim(isset($country_record['language_id']) ? $country_record['language_id'] : '');
                    $zone_to_geo_record['language_code'] = trim(isset($country_record['language_id']) ? $language_code : '');
                    unset($country_record);
                    unset($language_code);
                    unset($language_id);
                    // EOF COUNTRY
                    $zone_record['zoneToGeoRecordArray'][] = $zone_to_geo_record;
                }
                unset($zone_to_geo_record);
                // EOF ZONE TO GEO ZONE
                $this->zone_record_array[] = $zone_record;
                unset($zone_record);
            }
            // EOF ZONE
            return true;
        }
        return false;
    }
    public function validate()
    {
        $this->class_id = (int) ((int) $this->class_id > 0 ? $this->class_id : 0);
        if (!is_array($this->class_record)) {
            return false;
        }
        if (!parent::validate()) {
            return false;
        }
        unset($this->class_record['tax_class_id']);
        $this->zone_record_array = is_array($this->zone_record_array) ? $this->zone_record_array : [];
        foreach ($this->zone_record_array as $key_z => &$zone_record) {
            $zone_record['rateRecordArray'] = (isset($zone_record['rateRecordArray']) and is_array($zone_record['rateRecordArray'])) ? $zone_record['rateRecordArray'] : [];
            foreach ($zone_record['rateRecordArray'] as $key_r => &$rate_record) {
                if (!isset($rate_record['tax_rate'])) {
                    unset($zone_record['rateRecordArray'][$key_r]);
                }
            }
            unset($rate_record);
            unset($key_r);
            if (count($zone_record['rateRecordArray']) > 0) {
                $zone_record['zoneToGeoRecordArray'] = (isset($zone_record['zoneToGeoRecordArray']) and is_array($zone_record['zoneToGeoRecordArray'])) ? $zone_record['zoneToGeoRecordArray'] : [];
                foreach ($zone_record['zoneToGeoRecordArray'] as &$zone_to_geo_record) {
                    unset($zone_to_geo_record['association_id']);
                }
                unset($zone_to_geo_record);
                continue;
            }
            unset($this->zone_record_array[$key_z]);
        }
        unset($zone_record);
        unset($key_z);
        if (count($this->zone_record_array) == 0) {
            return false;
        }
        return true;
    }
    public function create()
    {
        $this->class_id = 0;
        return $this->save();
    }
    public function save($is_replace = false)
    {
        $return = false;
        if (!$this->validate()) {
            return $return;
        }
        $class_class = \common\models\Tax_Class::find()->where(['tax_class_id' => $this->class_id])->one();
        /*if (!($classClass instanceof \common\models\TaxClass)) {
              SEARCH CLASS BY TITLE?
          }*/
        if (!$class_class instanceof \common\models\Tax_Class) {
            $class_class = new \common\models\Tax_Class();
            $class_class->load_default_values();
            $class_class->date_added = date('Y-m-d H:i:s');
            $class_class->last_modified = date('Y-m-d H:i:s');
            if ($this->class_id > 0) {
                $class_class->tax_class_id = $this->class_id;
            } else {
                $this->unrelate();
            }
        }
        $class_class->set_attributes($this->class_record, false);
        if ($class_class->save(false)) {
            $this->class_record = $class_class->to_array();
            $this->class_id = (int) $class_class->tax_class_id;
            // ZONE
            foreach ($this->zone_record_array as $key_z => &$zone_record) {
                $is_save_z = false;
                $zone_id = (int) (isset($zone_record['geo_zone_id']) ? $zone_record['geo_zone_id'] : 0);
                unset($zone_record['geo_zone_id']);
                try {
                    $zone_class = \common\models\Tax_Zones::find()->where(['geo_zone_id' => $zone_id])->one();
                    if (!$zone_class instanceof \common\models\Tax_Zones) {
                        $zone_name = trim(isset($zone_record['geo_zone_name']) ? $zone_record['geo_zone_name'] : '');
                        if ($zone_name != '') {
                            $zone_class = \common\models\Tax_Zones::find()->where(['geo_zone_name' => $zone_name])->all();
                            if (count($zone_class) > 1) {
                                unset($this->zone_record_array[$key_z]);
                                continue;
                            }
                            $zone_class = count($zone_class) == 1 ? $zone_class[0] : false;
                        }
                        unset($zone_name);
                    }
                    if (!$zone_class instanceof \common\models\Tax_Zones) {
                        $zone_class = new \common\models\Tax_Zones();
                        $zone_class->load_default_values();
                        $zone_class->date_added = date('Y-m-d H:i:s');
                        $zone_class->last_modified = date('Y-m-d H:i:s');
                        $zone_class->geo_zone_id = $zone_id;
                    }
                    $zone_class->set_attributes($zone_record, false);
                    if ($zone_class->save(false)) {
                        $is_save_z = true;
                        $zone_id = (int) $zone_class->geo_zone_id;
                        $zone_record = $zone_class->to_array() + $zone_record;
                        // RATE
                        foreach ($zone_record['rateRecordArray'] as $key_r => &$rate_record) {
                            $is_save_r = false;
                            $rate_id = (int) (isset($rate_record['tax_rates_id']) ? $rate_record['tax_rates_id'] : 0);
                            unset($rate_record['tax_rates_id']);
                            $rate_record['tax_class_id'] = $this->class_id;
                            $rate_record['tax_zone_id'] = $zone_id;
                            try {
                                $rate_class = \common\models\Tax_Rates::find()->where(['tax_rates_id' => $rate_id])->one();
                                if (!$rate_class instanceof \common\models\Tax_Rates) {
                                    $rate_description = trim(isset($rate_record['tax_description']) ? $rate_record['tax_description'] : '');
                                    $rate_class = \common\models\Tax_Rates::find()->where(['tax_class_id' => $this->class_id, 'tax_zone_id' => $zone_id, 'tax_description' => $rate_description])->all();
                                    if (count($rate_class) > 1) {
                                        unset($zone_record['rateRecordArray'][$key_r]);
                                        continue;
                                    }
                                    $rate_class = count($rate_class) == 1 ? $rate_class[0] : false;
                                    unset($rate_description);
                                }
                                if (!$rate_class instanceof \common\models\Tax_Rates) {
                                    $rate_class = new \common\models\Tax_Rates();
                                    $rate_class->load_default_values();
                                    $rate_class->date_added = date('Y-m-d H:i:s');
                                    $rate_class->last_modified = date('Y-m-d H:i:s');
                                    $rate_class->tax_rates_id = $rate_id;
                                }
                                $rate_class->set_attributes($rate_record, false);
                                if ($rate_class->save(false)) {
                                    $is_save_r = true;
                                    $rate_record = $rate_class->to_array();
                                } else {
                                    $this->message_add($rate_class->get_error_summary(true));
                                }
                            } catch (\Exception $exc) {
                                $this->message_add($exc->get_message());
                            }
                            unset($rate_class);
                            unset($rate_id);
                            if ($is_save_r != true) {
                                unset($zone_record['rateRecordArray'][$key_r]);
                            }
                            unset($is_save_r);
                        }
                        unset($rate_record);
                        unset($key_r);
                        // EOF RATE
                        // ZONE TO GEO ZONE
                        foreach ($zone_record['zoneToGeoRecordArray'] as $key_g => &$zone_to_geo_record) {
                            $is_save_g = false;
                            $country_id = (int) (isset($zone_to_geo_record['zone_country_id']) ? $zone_to_geo_record['zone_country_id'] : 0);
                            $geo_zone_id = (int) (isset($zone_to_geo_record['zone_id']) ? $zone_to_geo_record['zone_id'] : 0);
                            unset($zone_to_geo_record['zone_country_id']);
                            unset($zone_to_geo_record['geo_zone_id']);
                            unset($zone_to_geo_record['zone_id']);
                            if ($country_id <= 0) {
                                $country_iso2 = trim(isset($zone_to_geo_record['countries_iso_code_2']) ? $zone_to_geo_record['countries_iso_code_2'] : '');
                                if ($country_iso2 != '') {
                                    foreach (\common\models\Countries::find()->where(['countries_iso_code_2' => $country_iso2])->all() as $country_class) {
                                        $country_id = (int) $country_class->countries_id;
                                        break;
                                    }
                                    unset($country_class);
                                }
                                unset($country_iso2);
                            }
                            if ($geo_zone_id <= 0) {
                                $zone_code = trim(isset($zone_to_geo_record['zone_code']) ? $zone_to_geo_record['zone_code'] : '');
                                if ($zone_code != '') {
                                    $country_record = \common\models\Zones::find()->where(['zone_country_id' => $country_id, 'zone_code' => $zone_code])->all();
                                    if (count($country_record) > 1) {
                                        unset($zone_record['zoneToGeoRecordArray'][$key_g]);
                                        continue;
                                    }
                                    $geo_zone_id = (int) (count($country_record) == 1 ? $country_record[0]->zone_id : 0);
                                    unset($country_record);
                                }
                                unset($zone_code);
                            }
                            if ($country_id > 0) {
                                try {
                                    $zone_to_tax_zone_class = \common\models\Zones_To_Tax_Zones::find()->where(['geo_zone_id' => $zone_id, 'zone_country_id' => $country_id, 'zone_id' => $geo_zone_id])->one();
                                    if (!$zone_to_tax_zone_class instanceof \common\models\Zones_To_Tax_Zones) {
                                        $zone_to_tax_zone_class = new \common\models\Zones_To_Tax_Zones();
                                        $zone_to_tax_zone_class->load_default_values();
                                        $zone_to_tax_zone_class->geo_zone_id = $zone_id;
                                        $zone_to_tax_zone_class->zone_country_id = $country_id;
                                        $zone_to_tax_zone_class->zone_id = $geo_zone_id;
                                        $zone_to_tax_zone_class->date_added = date('Y-m-d H:i:s');
                                        $zone_to_tax_zone_class->last_modified = date('Y-m-d H:i:s');
                                    }
                                    $zone_to_tax_zone_class->set_attributes($zone_to_geo_record, false);
                                    if ($zone_to_tax_zone_class->save(false)) {
                                        $is_save_g = true;
                                        $zone_to_geo_record = $zone_to_tax_zone_class->to_array() + $zone_to_geo_record;
                                    } else {
                                        $this->message_add($zone_to_tax_zone_class->get_error_summary(true));
                                    }
                                } catch (\Exception $exc) {
                                    $this->message_add($exc->get_message());
                                }
                                unset($zone_to_tax_zone_class);
                                if ($is_save_g != true) {
                                    unset($zone_record['zoneToGeoRecordArray'][$key_g]);
                                }
                                unset($is_save_g);
                            }
                            unset($geo_zone_id);
                            unset($country_id);
                        }
                        unset($zone_to_geo_record);
                        unset($key_g);
                        // EOF ZONE TO GEO ZONE
                    } else {
                        $this->message_add($zone_class->get_error_summary(true));
                    }
                } catch (\Exception $exc) {
                    $this->message_add($exc->get_message());
                }
                unset($zone_class);
                unset($zone_id);
                if ($is_save_z != true) {
                    unset($this->zone_record_array[$key_z]);
                }
                unset($is_save_z);
            }
            unset($zone_record);
            unset($key_z);
            // EOF ZONE
            $return = $this->class_id;
        } else {
            $this->message_add($class_class->get_error_summary(true));
        }
        unset($class_class);
        unset($is_replace);
        return $return;
    }
}