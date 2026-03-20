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

class Customer extends Abstract_Class
{
    public $customer_id = 0;
    public $customer_record = [];
    public $address_record_array = [];
    public $email_record_array = [];
    public $phone_record_array = [];
    public $extra_group_record_array = [];
    public function get_id()
    {
        return $this->customer_id;
    }
    public function set_id($customer_id)
    {
        $customer_id = (int) $customer_id;
        if ($customer_id >= 0) {
            $this->customer_id = $customer_id;
            return true;
        }
        return false;
    }
    public function load($customer_id)
    {
        $this->clear();
        $customer_id = (int) $customer_id;
        $customer_record = \common\models\Customers::find()->alias('c')->select('*')->left_join(\common\models\Customers_Info::table_name() . ' ci', 'ci.customers_info_id = c.customers_id')->where(['c.customers_id' => $customer_id])->as_array(true)->one();
        if (is_array($customer_record) and count($customer_record) > 0) {
            $this->customer_id = $customer_id;
            $this->customer_record = $customer_record;
            unset($customer_record);
            // ADDRESS
            $this->address_record_array = \common\models\Address_Book::find()->where(['customers_id' => $customer_id])->as_array(true)->all();
            // EOF ADDRESS
            // EMAIL
            $this->email_record_array = \common\models\Customers_Emails::find()->where(['customers_id' => $customer_id])->as_array(true)->all();
            // EOF EMAIL
            // PHONE
            $this->phone_record_array = \common\models\Customers_Phones::find()->where(['customers_id' => $customer_id])->as_array(true)->all();
            // EOF PHONE*/
            // EXTRA GROUP
            if (\common\helpers\Acl::check_extension_allowed('ExtraGroups', 'allowed')) {
                $model = \common\helpers\Extensions::get_model('ExtraGroups', 'CustomerExtraGroups');
                $this->extra_group_record_array = !empty($model) ? $model::find()->where(['customer_id' => $customer_id])->as_array(true)->all() : [];
            }
            // EOF EXTRA GROUP
            return true;
        }
        return false;
    }
    public function unrelate()
    {
        if (is_array($this->customer_record)) {
            unset($this->customer_record['customers_default_address_id']);
        }
        if (is_array($this->address_record_array)) {
            foreach ($this->address_record_array as &$address_record) {
                unset($address_record['address_book_id']);
            }
            unset($address_record);
        }
        return parent::unrelate();
    }
    public function validate()
    {
        $this->customer_id = (int) ((int) $this->customer_id > 0 ? $this->customer_id : 0);
        if (!is_array($this->customer_record) or count($this->customer_record) < 5) {
            return false;
        }
        if (!parent::validate()) {
            return false;
        }
        unset($this->customer_record['customers_id']);
        unset($this->customer_record['customers_info_id']);
        $this->address_record_array = is_array($this->address_record_array) ? $this->address_record_array : [];
        $this->email_record_array = is_array($this->email_record_array) ? $this->email_record_array : [];
        $this->phone_record_array = is_array($this->phone_record_array) ? $this->phone_record_array : [];
        $this->extra_group_record_array = is_array($this->extra_group_record_array) ? $this->extra_group_record_array : [];
        return true;
    }
    public function create()
    {
        $this->customer_id = 0;
        return $this->save();
    }
    public function save($is_replace = false)
    {
        $return = false;
        if (!$this->validate()) {
            return $return;
        }
        $customer_class = \common\models\Customers::find()->where(['customers_id' => $this->customer_id])->one();
        if (!$customer_class instanceof \common\models\Customers) {
            $customer_class = new \common\models\Customers();
            $customer_class->load_default_values();
            if ($this->customer_id > 0) {
                $customer_class->customers_id = $this->customer_id;
            } else {
                $this->unrelate();
            }
        }
        $customer_class->set_attributes($this->customer_record, false);
        if ($customer_class->save(false)) {
            $customer_info_record = $this->customer_record;
            $this->customer_record = $customer_class->to_array();
            $this->customer_id = (int) $customer_class->customers_id;
            // INFORMATION
            try {
                $customer_info_class = \common\models\Customers_Info::find()->where(['customers_info_id' => $this->customer_id])->one();
                if (!$customer_info_class instanceof \common\models\Customers_Info) {
                    $customer_info_class = new \common\models\Customers_Info();
                    $customer_info_class->load_default_values();
                    $customer_info_class->customers_info_id = $this->customer_id;
                }
                $customer_info_class->set_attributes($customer_info_record, false);
                $customer_info_class->detach_behavior('timestampBehavior');
                if ($customer_info_class->save(false)) {
                    $this->customer_record = $this->customer_record + $customer_info_class->to_array();
                } else {
                    $this->message_add($customer_info_class->get_error_summary(true));
                }
            } catch (\Exception $exc) {
                $this->message_add($exc->get_message());
            }
            unset($customer_info_record);
            unset($customer_info_class);
            // EOF INFORMATION
            // ADDRESS
            $address_record_array =& $this->address_record_array;
            foreach ($address_record_array as $key => &$address_record) {
                $is_save = false;
                $address_id = (int) (isset($address_record['address_book_id']) ? $address_record['address_book_id'] : 0);
                unset($address_record['customers_id']);
                unset($address_record['address_book_id']);
                try {
                    $address_class = \common\models\Address_Book::find()->where(['customers_id' => $this->customer_id, 'address_book_id' => $address_id])->one();
                    if (!$address_class instanceof \common\models\Address_Book) {
                        $address_class = new \common\models\Address_Book();
                        $address_class->load_default_values();
                        $address_class->customers_id = $this->customer_id;
                        if ($address_id > 0) {
                            $address_class->address_book_id = $address_id;
                        }
                    }
                    $address_class->set_attributes($address_record, false);
                    if ($address_class->save(false)) {
                        $is_save = true;
                        $address_record = $address_class->to_array() + $address_record;
                    } else {
                        $this->message_add($address_class->get_error_summary(true));
                    }
                } catch (\Exception $exc) {
                    $this->message_add($exc->get_message());
                }
                unset($address_class);
                unset($address_id);
                if ($is_save != true) {
                    unset($address_record_array[$key]);
                }
                unset($is_save);
            }
            unset($address_record_array);
            unset($address_record);
            unset($key);
            // EOF ADDRESS
            // EMAIL
            $email_record_array =& $this->email_record_array;
            foreach ($email_record_array as $key => &$email_record) {
                $is_save = false;
                $email = trim(isset($email_record['customers_email']) ? $email_record['customers_email'] : '');
                unset($email_record['customers_id']);
                unset($email_record['customers_email']);
                if ($email != '') {
                    try {
                        $email_class = \common\models\Customers_Emails::find()->where(['customers_id' => $this->customer_id, 'customers_email' => $email])->one();
                        if (!$email_class instanceof \common\models\Customers_Emails) {
                            $email_class = new \common\models\Customers_Emails();
                            $email_class->load_default_values();
                            $email_class->customers_id = $this->customer_id;
                            $email_class->customers_email = $email;
                        }
                        $email_class->set_attributes($email_record, false);
                        if ($email_class->save(false)) {
                            $is_save = true;
                            $email_record = $email_class->to_array();
                        } else {
                            $this->message_add($email_class->get_error_summary(true));
                        }
                    } catch (\Exception $exc) {
                        $this->message_add($exc->get_message());
                    }
                    unset($email_class);
                }
                unset($email);
                if ($is_save != true) {
                    unset($email_record_array[$key]);
                }
                unset($is_save);
            }
            unset($email_record_array);
            unset($email_record);
            unset($key);
            // EOF EMAIL
            // PHONE
            $phone_record_array =& $this->phone_record_array;
            foreach ($phone_record_array as $key => &$phone_record) {
                $is_save = false;
                $phone = trim(isset($phone_record['customers_phone']) ? $phone_record['customers_phone'] : '');
                unset($phone_record['customers_id']);
                unset($phone_record['customers_phone']);
                if ($phone != '') {
                    try {
                        $phone_class = \common\models\Customers_Phones::find()->where(['customers_id' => $this->customer_id, 'customers_phone' => $phone])->one();
                        if (!$phone_class instanceof \common\models\Customers_Phones) {
                            $phone_class = new \common\models\Customers_Phones();
                            $phone_class->load_default_values();
                            $phone_class->customers_id = $this->customer_id;
                            $phone_class->customers_phone = $phone;
                        }
                        $phone_class->set_attributes($phone_record, false);
                        if ($phone_class->save(false)) {
                            $is_save = true;
                            $phone_record = $phone_class->to_array();
                        } else {
                            $this->message_add($phone_class->get_error_summary(true));
                        }
                    } catch (\Exception $exc) {
                        $this->message_add($exc->get_message());
                    }
                    unset($phone_class);
                }
                unset($phone);
                if ($is_save != true) {
                    unset($phone_record_array[$key]);
                }
                unset($is_save);
            }
            unset($phone_record_array);
            unset($phone_record);
            unset($key);
            // EOF PHONE
            // EXTRA GROUP
            $model = \common\helpers\Extensions::get_model('ExtraGroups', 'CustomerExtraGroups');
            if (!empty($model)) {
                $extra_group_record_array =& $this->extra_group_record_array;
                foreach ($extra_group_record_array as $key => &$extra_group_record) {
                    $is_save = false;
                    $group_id = (int) (isset($extra_group_record['group_id']) ? $extra_group_record['group_id'] : '');
                    unset($extra_group_record['customer_id']);
                    unset($extra_group_record['group_id']);
                    if ($group_id > 0) {
                        try {
                            $group_class = $model::find()->where(['customer_id' => $this->customer_id, 'group_id' => $group_id])->one();
                            if (!$group_class instanceof $model) {
                                $group_class = new $model();
                                $group_class->load_default_values();
                                $group_class->customer_id = $this->customer_id;
                                $group_class->group_id = $group_id;
                            }
                            $group_class->set_attributes($extra_group_record, false);
                            if ($group_class->save(false)) {
                                $is_save = true;
                                $extra_group_record = $group_class->to_array();
                            } else {
                                $this->message_add($group_class->get_error_summary(true));
                            }
                        } catch (\Exception $exc) {
                            $this->message_add($exc->get_message());
                        }
                        unset($group_class);
                    }
                    unset($group_id);
                    if ($is_save != true) {
                        unset($extra_group_record_array[$key]);
                    }
                    unset($is_save);
                }
                unset($extra_group_record_array);
                unset($extra_group_record);
                unset($key);
            }
            // EOF EXTRA GROUP
            $return = $this->customer_id;
        } else {
            $this->message_add($customer_class->get_error_summary(true));
        }
        unset($customer_class);
        unset($is_replace);
        return $return;
    }
}