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
namespace common\forms;

use common\helpers\Country;
use yii\base\Model;
class Address_Form extends Model
{
    public const CUSTOM_ADDRESS = 1;
    public const SHIPPING_ADDRESS = 2;
    public const BILLING_ADDRESS = 3;
    private $_addresses_types = [];
    public $address_type = null;
    private $form_name = null;
    private $reflector;
    protected $_prefix;
    public function __construct($config = [])
    {
        $this->reflector = new \ReflectionClass($this);
        $this->load_address_types();
        if (!in_array($config['scenario'], $this->_addresses_types)) {
            throw new \Exception('Undefined address type');
        }
        $this->address_type = $config['scenario'];
        $this->set_form_name();
        $this->define_prefix();
        parent::__construct($config);
    }
    protected function set_form_name()
    {
        if ($this->address_type) {
            $const = array_flip($this->reflector->get_constants());
            $this->form_name = \yii\helpers\Inflector::id2camel(strtolower($const[$this->address_type]));
        }
    }
    public function form_name()
    {
        return $this->form_name;
    }
    public function before_validate()
    {
        foreach ($this->attributes as $attribute_name => $attribute_value) {
            if (is_string($attribute_value)) {
                $this->{$attribute_name} = \yii\helpers\Html_Purifier::process($attribute_value);
                $this->{$attribute_name} = str_replace('&amp;', '&', $this->{$attribute_name});
            }
        }
        return parent::before_validate();
    }
    private function load_address_types()
    {
        $this->_addresses_types = $this->_get_defined_scenarios();
    }
    public function rules()
    {
        return [[['address_book_id', 'company', 'company_vat', 'customs_number', 'gender', 'firstname', 'lastname', 'telephone', 'email_address', 'postcode', 'street_address', 'suburb', 'city', 'state', 'country', 'zone_id', 'drop_ship', 'type'], 'stronglyRequired', 'skipOnEmpty' => false], [['country', 'zone_id'], 'defaultGeoValues', 'skipOnEmpty' => false], ['as_preferred', 'default', 'value' => 0]];
    }
    private function get_entry_label($label)
    {
        $label = strtoupper($label);
        return $this->address_type == static::SHIPPING_ADDRESS && defined('SHIP_' . $label) ? constant('SHIP_' . $label) : constant('ENTRY_' . $label);
    }
    private $is_light_check = false;
    public function set_light_check(bool $value)
    {
        $this->is_light_check = $value;
    }
    public function is_light_check()
    {
        return $this->is_light_check;
    }
    private function check_spam_hack($val, $capitals = false)
    {
        return strpos($val, '<') !== false || strpos($val, 'https://') !== false || strpos($val, 'http://') !== false || strip_tags($val) != $val;
    }
    public function strongly_required($attribute, $params)
    {
        if (!$this->is_light_check && $this->has($attribute, false)) {
            switch ($attribute) {
                case 'gender':
                    if (!in_array($this->{$attribute}, array_keys($this->get_genders_list()))) {
                        $this->add_error($attribute, ENTRY_GENDER_ERROR);
                    }
                    break;
                case 'firstname':
                    if ($this->check_spam_hack($this->{$attribute}, true) || strlen($this->{$attribute}) < ENTRY_FIRST_NAME_MIN_LENGTH) {
                        $this->add_error($attribute, sprintf($this->get_entry_label('FIRST_NAME_ERROR'), ENTRY_FIRST_NAME_MIN_LENGTH));
                    }
                    break;
                case 'lastname':
                    if ($this->check_spam_hack($this->{$attribute}, true) || strlen($this->{$attribute}) < ENTRY_LAST_NAME_MIN_LENGTH) {
                        $this->add_error($attribute, sprintf($this->get_entry_label('LAST_NAME_ERROR'), ENTRY_LAST_NAME_MIN_LENGTH));
                    }
                    break;
                case 'company':
                    if ($this->check_spam_hack($this->{$attribute}) || empty($this->{$attribute})) {
                        $this->add_error($attribute, ENTRY_COMPANY_ERROR);
                    }
                    break;
                case 'company_vat':
                    if ($this->check_spam_hack($this->{$attribute}) || (empty($this->{$attribute}) || !\common\helpers\Validations::check_vat($this->{$attribute}))) {
                        $this->add_error($attribute, ENTRY_VAT_ID_ERROR);
                    }
                    break;
                case 'customs_number':
                    $cfg = $this->up('CUSTOMS_NUMBER');
                    if ($cfg && empty($this->{$attribute}) && (in_array($cfg, ['required', 'required_register']) || in_array($cfg, ['required_company']) && !empty($this->company))) {
                        $this->add_error($attribute, TEXT_CUSTOMS_NUMBER_ERROR);
                    }
                    break;
                case 'postcode':
                    if ($this->check_spam_hack($this->{$attribute}) || strlen($this->{$attribute}) < ENTRY_POSTCODE_MIN_LENGTH) {
                        $this->add_error($attribute, sprintf($this->get_entry_label('POST_CODE_ERROR'), ENTRY_POSTCODE_MIN_LENGTH));
                    }
                    break;
                case 'street_address':
                    if ($this->check_spam_hack($this->{$attribute}) || strlen($this->{$attribute}) < ENTRY_STREET_ADDRESS_MIN_LENGTH) {
                        $this->add_error($attribute, sprintf($this->get_entry_label('STREET_ADDRESS_ERROR'), ENTRY_STREET_ADDRESS_MIN_LENGTH));
                    }
                    break;
                case 'suburb':
                    if ($this->check_spam_hack($this->{$attribute}) || empty($this->{$attribute})) {
                        $this->add_error($attribute, $this->get_entry_label('SUBURB_ERROR'));
                    }
                    break;
                case 'city':
                    if ($this->check_spam_hack($this->{$attribute}) || strlen($this->{$attribute}) < ENTRY_CITY_MIN_LENGTH) {
                        $this->add_error($attribute, sprintf($this->get_entry_label('CITY_ERROR'), ENTRY_STREET_ADDRESS_MIN_LENGTH));
                    }
                    break;
                case 'country':
                    if (!is_numeric($this->{$attribute})) {
                        $this->add_error($attribute, ENTRY_COUNTRY_ERROR);
                    }
                    break;
            }
        }
        if ($attribute == 'state') {
            $this->zone_id = 0;
            $q_zones = \common\models\Zones::find()->where(['zone_country_id' => $this->country]);
            if ($q_zones->count() > 0) {
                $q_zones = \common\models\Zones::find()->where(['zone_country_id' => $this->country, 'zone_name' => $this->{$attribute}])->all();
                if (count($q_zones) == 1) {
                    $this->zone_id = $q_zones[0]->zone_id;
                } else if ($this->has($attribute, $this->is_light_check)) {
                    $this->add_error($attribute, ENTRY_STATE_ERROR_SELECT);
                }
            } else if (strlen($this->{$attribute}) < ENTRY_STATE_MIN_LENGTH && $this->has($attribute, $this->is_light_check)) {
                $this->add_error($attribute, sprintf(ENTRY_STATE_ERROR, ENTRY_STATE_MIN_LENGTH));
            }
        }
    }
    public function default_geo_values()
    {
        if (is_null($this->country)) {
            $this->country = (int) STORE_COUNTRY;
        }
        if (is_null($this->zone_id)) {
            //$this->zone_id = (int) STORE_ZONE;
        }
    }
    private function _get_defined_scenarios()
    {
        return [static::CUSTOM_ADDRESS, static::SHIPPING_ADDRESS, static::BILLING_ADDRESS];
    }
    public function scenarios()
    {
        $_sc = [];
        foreach ($this->_get_defined_scenarios() as $scena) {
            $_sc[$scena] = $this->collect_fields();
        }
        return $_sc;
    }
    public $address_book_id;
    public $company;
    public $company_vat;
    public $company_vat_date;
    public $company_vat_status;
    public $customs_number;
    public $customs_number_date;
    public $customs_number_status;
    public $gender;
    public $firstname;
    public $lastname;
    public $telephone;
    public $email_address;
    public $postcode;
    public $street_address;
    public $suburb;
    public $city;
    public $state;
    public $country;
    public $zone_id;
    public $drop_ship;
    public $type;
    public $as_preferred;
    public function get_active_attributes()
    {
        return $this->get_attributes(null, ['addressType', 'as_preferred']);
    }
    public function collect_configurable_fields($include_visible = true)
    {
        if ($include_visible) {
            $fields = ['telephone', 'email_address', 'drop_ship'];
            if (\common\helpers\Acl::check_extension_allowed('SplitCustomerAddresses', 'allowed')) {
                $fields[] = 'type';
            }
        } else {
            $fields = [];
        }
        $public_fields = $this->reflector->get_properties(\ReflectionProperty::IS_PUBLIC);
        if (is_array($public_fields)) {
            foreach ($public_fields as $_field) {
                if ($this->has($_field->name, $include_visible)) {
                    $fields[] = $_field->name;
                }
            }
        }
        return $fields;
    }
    public function collect_fields()
    {
        $fields = ['address_book_id', 'as_preferred', 'telephone', 'email_address', 'drop_ship'];
        if (\common\helpers\Acl::check_extension_allowed('SplitCustomerAddresses', 'allowed')) {
            $fields[] = 'type';
        }
        $fields = array_merge($fields, $this->collect_configurable_fields(true));
        return $fields;
    }
    public function define_prefix()
    {
        switch ($this->address_type) {
            case static::SHIPPING_ADDRESS:
                $this->_prefix = 'SHIPPING_';
                break;
            case static::BILLING_ADDRESS:
                $this->_prefix = 'BILLING_';
                break;
            default:
                $this->_prefix = 'ACCOUNT_';
                break;
        }
    }
    public function get_prefix()
    {
        return $this->_prefix;
    }
    public function up($postfix)
    {
        return defined($this->_prefix . strtoupper($postfix)) ? constant($this->_prefix . strtoupper($postfix)) : false;
    }
    public function get($postfix)
    {
        return $this->_prefix . $postfix;
    }
    public function has($postfix, $include_visible = true)
    {
        if ($include_visible) {
            if ($_c = $this->up($postfix)) {
                return in_array($_c, ['required', 'required_register', 'visible', 'visible_register', 'required_company']);
            }
        } else if ($_c = $this->up($postfix)) {
            return in_array($_c, ['required', 'required_register', 'required_company']);
        }
        return false;
    }
    public function get_genders_list()
    {
        return \common\helpers\Address::get_genders_list();
    }
    public function get_allowed_countries()
    {
        $_countries = Country::get_countries('', false, '', strtolower(substr($this->_prefix, 0, 4)));
        $_countries = \yii\helpers\Array_Helper::map($_countries, 'countries_id', 'text');
        return $_countries;
    }
    public function get_allowed_countries_iso($iso = 'iso_code_2')
    {
        $_countries = Country::get_countries('', false, '', strtolower(substr($this->_prefix, 0, 4)));
        $_countries = \yii\helpers\Array_Helper::map($_countries, 'countries_id', 'countries_' . $iso);
        return $_countries;
    }
    public function preload($data = [])
    {
        if (is_array($data) || is_object($data)) {
            foreach ($data as $name => $value) {
                if (is_array($data[$name])) {
                    $this->preload($value);
                } else {
                    try {
                        if ($this->has_property($name)) {
                            $this->{$name} = $value;
                        } elseif (strlen(substr($name, 6)) > 0 && $this->has_property(substr($name, 6))) {
                            $this->{substr($name, 6)} = $value;
                        }
                        if ($name == 'country_id' || substr($name, 6) == 'country_id') {
                            $this->country = $value;
                        }
                    } catch (\Exception $ex) {
                        //var_dump($ex->getMessage(), $name, $value);
                        \Yii::error($ex->get_message() . ' $name ' . $name . ' $value ' . $value);
                    }
                }
            }
            $this->obtain_state();
        }
        $this->preload_default();
    }
    public function preload_default()
    {
        if (empty($this->gender)) {
            $this->gender = 'm';
        }
        if (!is_numeric($this->country)) {
            $this->country = (int) STORE_COUNTRY;
        }
    }
    public function obtain_state()
    {
        if ($this->zone_id) {
            $q_zones = \common\models\Zones::find()->where(['zone_country_id' => $this->country]);
            if ($q_zones->count() > 0) {
                $q_zones = \common\models\Zones::find()->where(['zone_country_id' => $this->country, 'zone_id' => $this->zone_id])->one();
                if ($q_zones) {
                    $this->state = $q_zones->zone_name;
                }
            }
        }
    }
    public function not_empty($with_country = false)
    {
        return !empty($this->company) || !empty($this->firstname) || !empty($this->lastname) || !empty($this->postcode) || !empty($this->street_address) || !empty($this->city) || !empty($this->state) || $with_country && !empty($this->country);
    }
    public function customer_address_is_ready()
    {
        $ready = true;
        foreach ($this->collect_configurable_fields(false) as $field) {
            if (empty($this->{$field})) {
                $ready = false;
            }
        }
        return $ready;
    }
}