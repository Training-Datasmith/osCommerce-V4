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
namespace common\components;

use common\classes\opc;
use common\helpers\Date as DateHelper;
use common\models;
use frontend\forms\registration\Customer_Registration;
use Yii;
class Customer extends \common\models\Customers implements \yii\web\Identity_Interface
{
    public const LOGIN_STANDALONE = 1;
    public const LOGIN_RECOVERY = 2;
    public const LOGIN_SOCIALS = 3;
    public const LOGIN_WITHOUT_CHECK = 4;
    private $login_type;
    private $is_multi = 0;
    private $data = [];
    private $temporary = [];
    protected $_customers_info = null;
    //protected $authKey;
    //protected $auth_key; there must be the field in DB => model\Customers
    public $remember_me = false;
    public function __construct($type = 0)
    {
        $this->set_login_type($type);
        $this->storage = Yii::$app->get('storage');
    }
    public function set_login_type($type)
    {
        $this->login_type = $type;
    }
    /**
     *
     * @param string $checkParam
     * @return boolean|0
     */
    public function validate_customer($check_param)
    {
        $success = true;
        $check_gdpr = true;
        switch ($this->login_type) {
            case static::LOGIN_STANDALONE:
                $success = \common\helpers\Password::validate_password($check_param, $this->customers_password, 'frontend');
                break;
            case static::LOGIN_RECOVERY:
                if (!$this->check_valid_token($this->customers_id, $check_param)) {
                    $success = false;
                }
                break;
            case static::LOGIN_SOCIALS:
                if ($check_param != Socials::HASHCODE) {
                    $success = false;
                }
                break;
            case static::LOGIN_WITHOUT_CHECK:
                if ($this->customers_id != $check_param) {
                    $success = false;
                }
                $check_gdpr = false;
                break;
            default:
                $success = false;
                break;
        }
        if ($success && $check_gdpr) {
            $this->check_gdpr();
        }
        return $success;
    }
    public function get_id()
    {
        return $this->customers_id;
    }
    public function login_customer_by_id($c_id)
    {
        if ($this->login_type != static::LOGIN_WITHOUT_CHECK) {
            return false;
        }
        $success = true;
        $this->is_multi = 0;
        $_user = $this->find_identity($c_id);
        if (!$_user) {
            return false;
        }
        return $success;
    }
    public function login_customer($email_address, $check_param)
    {
        if (!$this->login_type || !$email_address) {
            return false;
        }
        $success = true;
        if (!Yii::$app->user->is_guest) {
            return $success;
        }
        $this->is_multi = 0;
        /** @var self $_user */
        $_user = $this->find_identity_by_email($email_address);
        if (!$_user) {
            $_user = $this->find_by_multi_email($email_address);
            //findByEmail
            $this->is_multi = 1;
        }
        if (!$_user) {
            $this->is_multi = 0;
            \common\models\Fraud::register_address();
            return false;
        }
        $_user->login_type = $this->login_type;
        $_user->is_multi = $this->is_multi;
        $success = $_user->validate_customer($check_param);
        foreach (\common\helpers\Hooks::get_list('customers/login-customer/after-validate') as $filename) {
            include $filename;
        }
        if ($success) {
            if ($this->remember_me) {
                $duration = \Yii::$app->user->auto_login_duration;
                if (defined('RememberMe_EXTENSION_DURATION') && intval(Remember_Me_extension_duration) > 0) {
                    $duration = intval(Remember_Me_extension_duration);
                }
                \Yii::$app->user->login($_user, $duration);
            }
            $_user->_after_auth();
            \common\models\Fraud::clean_address();
        } elseif ($success === false) {
            \common\models\Fraud::register_address();
        }
        foreach (\common\helpers\Hooks::get_list('customers/login-customer') as $filename) {
            include $filename;
        }
        return $success;
    }
    private function _after_auth()
    {
        global $cart, $quote, $sample;
        if ($this->customers_id) {
            if (is_object($cart)) {
                $cart->before_restore();
            }
            if (is_object($cart)) {
                $cart->before_restore();
            }
            if (SESSION_RECREATE == 'True') {
                tep_session_recreate();
            }
            Yii::$app->storage->set_pointer(Yii::$app->storage->get_pointer());
            Yii::$app->user->login($this);
            $this->set_customers_data();
            if ($this->customers_id) {
                $address_book = $this->get_default_address()->one();
                if (\common\helpers\Acl::check_extension_allowed('SplitCustomerAddresses', 'allowed')) {
                    $shipping_address_book = $this->get_default_shipping_address()->one();
                } else {
                    $shipping_address_book = $address_book;
                }
                if (\common\helpers\Acl::check_extension_allowed('DealersMultiCustomers', 'allowed')) {
                    $multi_customer_id = $this->data['multi_customer_id'] ?? 0;
                    if ($multi_customer_id > 0) {
                        $user = \common\extensions\Dealers_Multi_Customers\models\Users::find()->where(['user_id' => $multi_customer_id])->one();
                        if ($user && $user->customers_shipto > 0) {
                            $shipping_address_book = models\Address_Book::find_one($user->customers_shipto);
                        }
                        unset($user);
                    }
                }
                Yii::$app->get('storage')->set('billto', $address_book->address_book_id ?? null);
                Yii::$app->get('storage')->set('sendto', $shipping_address_book->address_book_id ?? null);
            } else {
                Yii::$app->get('storage')->remove('billto');
                Yii::$app->get('storage')->remove('sendto');
            }
            //Yii::$app->get('storage')->remove('payment');
            Yii::$app->get('storage')->remove('comments');
            Yii::$app->get('storage')->remove('credit_covers');
            Yii::$app->get('storage')->remove('order_delivery_date');
            $this->convert_to_session();
            $this->update_access();
            // restore cart contents
            if (is_object($cart)) {
                $cart->restore_contents();
            }
            foreach (\common\helpers\Hooks::get_list('customers/after-auth') as $filename) {
                include $filename;
            }
        }
    }
    public function logoff_customer()
    {
        Yii::$app->user->logout(false);
        $this->clear_all_params();
        foreach (\common\helpers\Hooks::get_list('customers/logoff') as $filename) {
            include $filename;
        }
    }
    public function update_access()
    {
        if ($this->customers_id) {
            $info = models\Customers_Info::find_one(['customers_info_id' => $this->customers_id]);
            if ($info) {
                $info->customers_info_date_of_last_logon = date('Y-m-d');
                $info->customers_info_number_of_logons++;
                $info->update();
            }
        }
    }
    public function check_gdpr()
    {
        $gdpr = new Gdpr($this);
        return $gdpr->process_gdpr_checking();
    }
    public function get_address_books($to_array = false, $wo_drop_ship = false, $type = '')
    {
        $ab = parent::get_address_books();
        if ($wo_drop_ship) {
            $ab->and_where(['drop_ship' => 0]);
        }
        $allow_multi = false;
        if (\common\helpers\Acl::check_extension_allowed('SplitCustomerAddresses', 'allowed')) {
            if (!empty($type)) {
                switch ($type) {
                    case 'custom':
                        $ab->and_where(['entry_type' => \common\forms\Address_Form::CUSTOM_ADDRESS]);
                        // 1
                        break;
                    case 'shipping':
                        $ab->and_where(['entry_type' => \common\forms\Address_Form::SHIPPING_ADDRESS]);
                        // 2
                        $allow_multi = true;
                        break;
                    case 'billing':
                        $ab->and_where(['entry_type' => \common\forms\Address_Form::BILLING_ADDRESS]);
                        //3
                        break;
                    default:
                        break;
                }
            }
        }
        if ($allow_multi && \common\helpers\Acl::check_extension_allowed('DealersMultiCustomers', 'allowed')) {
            $multi_customer_id = \Yii::$app->get('storage')->get('multi_customer_id');
            if ($multi_customer_id > 0) {
                $shipto = [];
                $address_query = \common\extensions\Dealers_Multi_Customers\models\Users_To_Address_Book::find()->where(['user_id' => $multi_customer_id]);
                foreach ($address_query->each() as $address_row) {
                    $shipto[] = $address_row->address_book_id;
                }
                unset($address_query);
                $ab->and_where(['IN', 'address_book_id', $shipto]);
            }
        }
        if ($to_array) {
            $ab->as_array();
        }
        return $ab->all();
    }
    public function get_address_book($ab_id, $to_array = false)
    {
        if ($this->customers_id) {
            if ($to_array) {
                return parent::get_address_book($ab_id)->as_array()->one();
            } else {
                return parent::get_address_book($ab_id)->one();
            }
        }
        return null;
    }
    private function check_valid_token($cid, $token)
    {
        $query_token = tep_db_fetch_array(tep_db_query('select token from ' . TABLE_CUSTOMERS_INFO . " where customers_info_id = '" . (int) $cid . "'"));
        if ($query_token) {
            return $query_token['token'] == $token;
        }
        return false;
    }
    protected $storage;
    private function set_customers_data()
    {
        if ($this->customers_id) {
            $this->data['customer_id'] = $this->customers_id;
            $this->data['customer_default_address_id'] = $this->customers_default_address_id;
            $this->data['customer_first_name'] = $this->customers_firstname;
            $this->data['customer_last_name'] = $this->customers_lastname;
            $this->data['customer_email_address'] = $this->customers_email_address;
            $this->data['is_multi'] = $this->is_multi;
            $this->data['multi_customer_id'] = $this->multi_customer_id;
            $address_book = $this->get_default_address()->one();
            $this->data['customer_country_id'] = $address_book->entry_country_id ?? null;
            $this->data['customer_zone_id'] = $address_book->entry_zone_id ?? null;
            if (($address_book->entry_company_vat_status ?? null) > 1) {
                $this->data['customers_company_vat'] = $address_book->entry_company_vat;
                $this->data['customers_company_vat_status'] = $address_book->entry_company_vat_status;
                $this->data['customers_company_vat_date'] = $address_book->entry_company_vat_date;
            }
            if (($address_book->entry_customs_number_status ?? null) > 0) {
                $this->data['customers_customs_number'] = $address_book->entry_customs_number;
                $this->data['customers_customs_number_status'] = $address_book->entry_customs_number_status;
                $this->data['customers_customs_number_date'] = $address_book->entry_customs_number_date;
            }
            if (\common\helpers\Extensions::is_customer_groups_allowed()) {
                $this->data['customer_groups_id'] = $this->groups_id;
            } else {
                $this->data['customer_groups_id'] = 0;
            }
        }
    }
    public function load_customer($customer_id)
    {
        if (!$this->customers_id) {
            $_user = $this->find_identity($customer_id);
            if ($_user) {
                $this->set_attributes($_user->get_attributes(), false);
            }
        }
        $this->set_customers_data();
        $this->data['currency_id'] = \Yii::$app->settings->get('currency_id');
        $this->data['currency'] = \Yii::$app->settings->get('currency');
        return $this;
    }
    public function get($name)
    {
        $this->data[$name] = $this->data[$name] ?? $this->storage->get($name);
        return $this->data[$name];
    }
    public function get_all()
    {
        return $this->data;
    }
    public function set($name, $value, $enable_session = false)
    {
        $this->data[$name] = $value;
        if ($enable_session) {
            $this->storage->set($name, $value);
        }
    }
    public function clear_param($name)
    {
        unset($this->data[$name]);
        unset($this->temporary[$name]);
    }
    public function clear_all_params()
    {
        $this->data = [];
        $this->temporary = [];
        $this->storage->remove_all();
    }
    public function convert_to_session()
    {
        if (is_array($this->data) && count($this->data)) {
            foreach ($this->data as $key => $value) {
                $this->set($key, $value, true);
            }
        }
    }
    /*depricated*/
    public function convert_back_session()
    {
        /*
                if (is_array($this->temporary) && count($this->temporary)) {
                    $this->data = [];
                    $setUpCurrency = true;
                    foreach ($this->temporary as $key => $value) {
                        //global $$key;
                        if (tep_not_null($value)) {
                            if($key === 'currency' && !empty($value)){
                                $setUpCurrency = false;
                            }
                            $this->data[$key] = $value;
                            unset($GLOBALS[$key]);
                            $_SESSION[$key] = $value;
                            $GLOBALS[$key] = &$_SESSION[$key];
                        }
                    }
                    if($setUpCurrency){
                        $GLOBALS['currency'] = DEFAULT_CURRENCY;
                    }
                }*/
    }
    public function create_customer_qo($qo_model)
    {
        $firstname = $qo_model->firstname;
        $telephone = $qo_model->telephone;
        $email_address = $qo_model->email_address;
        $login = true;
        /*if($customer = static::findByEmail($email_address)){
              $customer->addPhone($telephone);
          } elseif (!empty($telephone) && $customer = static::findByPhone($telephone)){
              if($email_address){
                  $customer->addEmail($email_address);
              }
          } else {/**/
        $login = \common\helpers\Customer::check_need_login($qo_model->group);
        $customer = new self();
        $customer->set_attributes(['opc_temp_account' => 1, 'customers_firstname' => strval($firstname), 'customers_email_address' => strval($email_address), 'customers_telephone' => strval($telephone), 'groups_id' => $qo_model->group, 'customers_status' => $login ? 1 : 0, 'customers_password' => \common\helpers\Password::encrypt_password(\common\helpers\Password::create_random_value(ENTRY_PASSWORD_MIN_LENGTH), 'frontend'), 'platform_id' => \common\classes\platform::current_id()], false);
        if ($customer->save(false)) {
            if (!empty($telephone)) {
                (new models\Customers_Phones(['customers_phone' => $telephone]))->link('customer', $customer);
            }
            if (!empty($email_address)) {
                (new models\Customers_Emails(['customers_email' => $email_address]))->link('customer', $customer);
            }
            $customer->add_customers_info();
            $address = $customer->get_address_from_model($qo_model);
            $customer->add_default_address($address);
            if (\common\helpers\Acl::check_extension_allowed('SplitCustomerAddresses', 'allowed')) {
                $address['entry_type'] = \common\forms\Address_Form::SHIPPING_ADDRESS;
                $_address = $customer->add_address($address);
                if ($_address) {
                    $customer->customers_shipping_address_id = $_address->address_book_id;
                    $customer->save();
                }
            }
        }
        /*}/**/
        if ($login) {
            $customer->_after_auth();
        }
        return $customer;
    }
    public function increase_credit_amount(\common\models\Coupons $gv)
    {
        if ($this->customers_id) {
            $currencies = Yii::$container->get('currencies');
            $add_amount = $gv->coupon_amount * $currencies->get_market_price_rate($gv->coupon_currency, DEFAULT_CURRENCY);
            $this->credit_amount += (float) $add_amount;
            $this->save(false);
            $comment = 'Redeem ' . $gv->coupon_code;
            $this->save_credit_history($this->customers_id, $add_amount, '+', $gv->coupon_currency, $currencies->currencies[$gv->coupon_currency]['value'], $comment);
            return $this->credit_amount;
        }
        return false;
    }
    /*
     * type = 0 if credit amount, type = 1 if bonus amount
     */
    public function save_credit_history($customers_id, $amount, $prefix = '+', $currency = '', $currency_value = '', $comment = '', $type = 0, $customer_notified = 0)
    {
        if (!$customers_id) {
            $customers_id = $this->customers_id;
        }
        models\Customers_Credit_History::save_credit_history((int) $customers_id, $amount, $prefix, $currency, $currency_value, $comment, $type, $customer_notified);
        return $this;
    }
    /*return customers model*/
    public function get_user_by_token($token)
    {
        if ($token) {
            $c_info = models\Customers_Info::find()->where(['token' => $token])->one();
            if ($c_info) {
                return self::find_one($c_info->customers_info_id);
            }
        }
        return false;
    }
    public function update_user_token($customers_id = null)
    {
        $customers_id = $customers_id ? $customers_id : $this->customers_id;
        if ($customers_id) {
            $c_info = $this->get_customers_info($customers_id);
        }
        if ($c_info) {
            $c_info->update_token();
        }
        return false;
    }
    public function get_customers_info($customers_id = null)
    {
        $_cid = $this->customers_id ?? $customers_id;
        if ($_cid) {
            if (is_null($this->_customers_info)) {
                $this->_customers_info = models\Customers_Info::find_one(['customers_info_id' => $_cid]);
            }
        }
        return $this->_customers_info;
    }
    public function fill_customer_fields($model)
    {
        if (!empty($model->email_address)) {
            $this->customers_email_address = $model->email_address;
        }
        if (!empty($model->gender)) {
            $this->customers_gender = $model->gender;
        }
        if (!empty($model->firstname)) {
            $this->customers_firstname = $model->firstname;
        }
        if (!empty($model->lastname)) {
            $this->customers_lastname = $model->lastname;
        }
        if (!empty($model->dob)) {
            $this->customers_dob = Date_Helper::date_raw($model->dob);
        } else {
            $this->customers_dob = '0000-00-00';
        }
        if (!empty($model->gdpr)) {
            $this->dob_flag = $model->gdpr;
        }
        if (!empty($model->telephone)) {
            $this->customers_telephone = $model->telephone;
        }
        if (!empty($model->landline)) {
            $this->customers_landline = $model->landline;
        }
        $this->customers_company = '';
        //$this->customers_company_vat = '';
    }
    public function register_customer(Customer_Registration $model = null, $with_login = true, \common\forms\Address_Form $address_model = null)
    {
        if ($model) {
            $login = \common\helpers\Customer::check_need_login($model->group);
            $this->set_attributes([
                //'customers_email_address' => $model->email_address,
                'customers_newsletter' => $model->newsletter,
                'groups_id' => $model->group,
                'customers_status' => $login ? 1 : 0,
                'customers_password' => \common\helpers\Password::encrypt_password($model->password, 'frontend'),
                'opc_temp_account' => !is_null($model->opc_temp_account) ? $model->opc_temp_account : 0,
            ], false);
            $this->platform_id = !empty($model->platform_id) ? $model->platform_id : \common\classes\platform::current_id();
            try {
                $language_id = (int) \Yii::$app->settings->get('languages_id');
            } catch (\Exception $e) {
                $language_id = (int) \common\classes\language::default_id();
            }
            $this->language_id = $model->language_id ?? $language_id;
            if (!is_null($address_model)) {
                $this->fill_customer_fields($address_model);
            }
            $this->fill_customer_fields($model);
            if ($ext = \common\helpers\Acl::check_extension_allowed('CustomerCode', 'allowed')) {
                $ext::add_erp_fields($this, $model);
            }
            $this->insert(false);
            $this->add_customers_info();
            if (!is_null($address_model)) {
                $address = $this->get_address_from_model($address_model);
            } else {
                $address = $this->get_address_from_model($model);
            }
            $this->add_default_address($address);
            if (\common\helpers\Acl::check_extension_allowed('SplitCustomerAddresses', 'allowed')) {
                $address['entry_type'] = \common\forms\Address_Form::SHIPPING_ADDRESS;
                $_address = $this->add_address($address);
                if ($_address) {
                    $this->customers_shipping_address_id = $_address->address_book_id;
                    $this->save();
                }
            }
            if ($ext = \common\helpers\Acl::check_extension_allowed('PlatformRestrictLogin', 'enabled')) {
                $login_status = $ext::customer_register($this);
                if (is_bool($login_status) && $login_status === false) {
                    $with_login = false;
                }
            }
            if ($this->customers_status && $with_login) {
                $this->_after_auth();
            }
            foreach (\common\helpers\Hooks::get_list('customers/register') as $filename) {
                include $filename;
            }
            if (property_exists(Yii::$app->controller, 'promoActionsObs') && is_object(Yii::$app->controller->promo_actions_obs)) {
                Yii::$app->controller->promo_actions_obs->trigger_action('create_account');
                if ($model->newsletter && is_object(Yii::$app->controller->promo_actions_obs)) {
                    Yii::$app->controller->promo_actions_obs->trigger_action('signing_newsletter');
                }
            }
            if ($with_login) {
                $this->send_congratulation($login);
            }
            return $this;
        }
        return null;
    }
    public function register_guest_customer(Customer_Registration $customer_model, \common\forms\Address_Form $address_model)
    {
        $this->opc_temp_account = 1;
        foreach ($customer_model->get_attributes_by_scenario() as $name => $value) {
            if ($this->has_attribute('customers_' . $name)) {
                $this->{'customers_' . $name} = $value;
            }
            if ($this->has_attribute($name)) {
                $this->{$name} = $value;
            }
        }
        $this->groups_id = $customer_model->group;
        /*if (defined('ONE_PAGE_CREATE_ACCOUNT')){
              if (ONE_PAGE_CREATE_ACCOUNT == 'onebuy' && $customerModel->terms){ //terms used as create account prompt
                  $this->opc_temp_account = 0;
              }
          }*/
        if (empty($customer_model->password)) {
            $customer_model->password = \common\helpers\Password::create_random_value(ENTRY_PASSWORD_MIN_LENGTH);
        }
        $this->customers_password = \common\helpers\Password::encrypt_password($customer_model->password, 'frontend');
        $this->platform_id = \common\classes\platform::current_id();
        $this->fill_customer_fields($address_model);
        $this->save(false);
        $this->add_customers_info();
        $book = $this->get_address_from_model($address_model);
        if ($book) {
            $new_book = $this->add_default_address($book);
            if (\common\helpers\Acl::check_extension_allowed('SplitCustomerAddresses', 'allowed')) {
                $book['entry_type'] = \common\forms\Address_Form::SHIPPING_ADDRESS;
                $_address = $this->add_address($book);
                if ($_address) {
                    $this->customers_shipping_address_id = $_address->address_book_id;
                    $this->save();
                }
            }
        }
        $this->_after_auth();
    }
    public function remove_duplicate_guests_accounts()
    {
        if (!empty($this->customers_email_address) && $this->customers_id) {
            $guests = models\Customers::find()->where(['customers_email_address' => $this->customers_email_address, 'opc_temp_account' => 1])->and_where(['<>', 'customers_id', $this->customers_id])->all();
            if ($guests) {
                foreach ($guests as $guest) {
                    if (opc::is_temp_customer($guest->customers_id)) {
                        opc::remove_temp_customer($guest->customers_id, $this->customers_id);
                    }
                }
            }
        }
    }
    public function get_address_from_array(array $array_data)
    {
        //to do
    }
    public function update_customer($array_data)
    {
        //        if ($this->customers_id){
        if (is_array($array_data)) {
            foreach ($array_data as $key_field => $value) {
                if ($this->has_attribute($key_field)) {
                    $this->{$key_field} = $value;
                } elseif ($this->has_attribute('customers_' . $key_field)) {
                    $this->{'customers_' . $key_field} = $value;
                }
                if ($key_field == 'group') {
                    $this->groups_id = (int) $value;
                }
            }
            $this->save(false);
        }
        //        }
    }
    public function save_regular_offers(Customer_Registration $model)
    {
        if ($model->newsletter && $model->regular_offers) {
            $r_offer = new \common\models\Regular_Offers();
            $r_offer->set_attributes(['customers_id' => $this->customers_id, 'period' => $model->regular_offers, 'date_end' => date('Y-m-d', strtotime('+' . $model->regular_offers . ' months'))], false);
            $r_offer->save(false);
        }
    }
    public function get_address_from_model($model)
    {
        $address_details = [];
        if ($model) {
            $address_details = ['customers_id' => $this->customers_id, 'entry_country_id' => $model->country];
            if (!empty($model->gender)) {
                $address_details['entry_gender'] = $model->gender;
            }
            if (!empty($model->firstname)) {
                $address_details['entry_firstname'] = $model->firstname;
            }
            if (!empty($model->lastname)) {
                $address_details['entry_lastname'] = $model->lastname;
            }
            if (!empty($model->lastname)) {
                $address_details['entry_lastname'] = $model->lastname;
            }
            if (!empty($model->postcode)) {
                $address_details['entry_postcode'] = $model->postcode;
            }
            if (!empty($model->street_address)) {
                $address_details['entry_street_address'] = $model->street_address;
            }
            if (isset($model->suburb)) {
                $address_details['entry_suburb'] = $model->suburb;
            }
            if (!empty($model->city)) {
                $address_details['entry_city'] = $model->city;
            }
            if (isset($model->company)) {
                $address_details['entry_company'] = $model->company;
            }
            if (isset($model->company_vat)) {
                $address_details['entry_company_vat'] = $model->company_vat;
            }
            if (isset($model->customs_number)) {
                $address_details['entry_customs_number'] = $model->customs_number;
            }
            if ($model->zone_id) {
                $address_details['entry_zone_id'] = $model->zone_id;
                $address_details['entry_state'] = '';
            } else {
                $address_details['entry_zone_id'] = 0;
                $address_details['entry_state'] = $model->state ?? '';
            }
            if (isset($model->telephone)) {
                $address_details['entry_telephone'] = $model->telephone;
            }
            if (isset($model->email_address)) {
                $address_details['entry_email_address'] = $model->email_address;
            }
            if (isset($model->drop_ship)) {
                $address_details['drop_ship'] = $model->drop_ship && 1;
            }
            if (!empty($model->type)) {
                $address_details['entry_type'] = $model->type;
            }
        }
        return $address_details;
    }
    public function add_default_address($address)
    {
        if ($address) {
            if (!$this->customers_default_address_id) {
                $_address = $this->add_address($address);
                if ($_address) {
                    $this->customers_default_address_id = $_address->address_book_id;
                    $this->save();
                }
            } else {
                $_address = $this->get_default_address();
                if (!empty($_address) && is_object($_address) && $_address instanceof \yii\db\Active_Query) {
                    $_address = $_address->one();
                }
                if ($_address) {
                    $this->update_address($_address->address_book_id, $address);
                }
            }
        }
    }
    public function add_address($attributes)
    {
        $a_book = \common\models\Address_Book::create($attributes);
        if (!$a_book->customers_id) {
            $a_book->customers_id = $this->customers_id;
        }
        if ($a_book) {
            $a_book->save(false);
        }
        /** @var \common\extensions\VatOnOrder\VatOnOrder $VatOnOrder */
        if ($vat_on_order = \common\helpers\Acl::check_extension_allowed('VatOnOrder', 'allowed')) {
            try {
                $attributes['address_book_id'] = $a_book->address_book_id;
                $check = $vat_on_order::check_vat_status($attributes);
                // updates entry_company_vat_status
                if ($check > 1) {
                    $a_book->entry_company_vat_status = $check;
                }
            } catch (\Exception $ex) {
                \Yii::error($ex->get_message());
            }
        }
        return $a_book;
    }
    public function update_address($ab_id, $attributes)
    {
        $a_book = \common\models\Address_Book::find_one(['address_book_id' => $ab_id]);
        foreach (\common\helpers\Hooks::get_list('customers/update-address/before') as $filename) {
            $prevent_update = include $filename;
            if ($prevent_update === true) {
                return $a_book;
            }
        }
        if ($a_book) {
            $attributes['entry_company_vat_status'] = 0;
            $a_book->edit($attributes);
            $a_book->save(false);
            /** @var \common\extensions\VatOnOrder\VatOnOrder $VatOnOrder */
            if ($vat_on_order = \common\helpers\Acl::check_extension_allowed('VatOnOrder', 'allowed')) {
                try {
                    $attributes['address_book_id'] = $a_book->address_book_id;
                    $check = $vat_on_order::check_vat_status($attributes);
                    // updates entry_company_vat_status
                    if ($check > 1) {
                        $a_book->entry_company_vat_status = $check;
                    }
                } catch (\Exception $ex) {
                    \Yii::error($ex->get_message());
                }
            }
        } else {
            return $this->add_address($attributes);
        }
        return $a_book;
    }
    public function remove_address($ab_id)
    {
        if ($this->customers_id) {
            $a_book = \common\models\Address_Book::find_one(['address_book_id' => $ab_id, 'customers_id' => $this->customers_id]);
            if ($a_book) {
                $a_book->delete();
            }
        }
        return false;
    }
    public function add_customers_info()
    {
        $c_info = null;
        if ($this->customers_id) {
            $c_info = models\Customers_Info::find_one(['customers_info_id' => $this->customers_id]);
            if (!$c_info) {
                $c_info = new models\Customers_Info();
                $c_info->set_attributes(['customers_info_id' => $this->customers_id, 'customers_info_number_of_logons' => 0], false);
            }
            $c_info->save(false);
        }
        return $c_info;
    }
    public function send_congratulation($login = false)
    {
        if ($this->customers_id) {
            $name = $this->customers_firstname . ' ' . $this->customers_lastname;
            if ($this->customers_gender == 'm') {
                $user_greeting = sprintf(EMAIL_GREET_MR, $this->customers_lastname);
            } elseif ($this->customers_gender == 'f' || $this->customers_gender == 's') {
                $user_greeting = sprintf(EMAIL_GREET_MS, $this->customers_lastname);
            } else {
                $user_greeting = sprintf(EMAIL_GREET_NONE, $this->customers_firstname);
            }
            $email_params = [];
            $email_params['STORE_NAME'] = STORE_NAME;
            $email_params['USER_GREETING'] = trim($user_greeting);
            $email_params['CUSTOMER_FIRSTNAME'] = $this->customers_firstname;
            $email_params['CUSTOMER_LASTNAME'] = $this->customers_lastname;
            $email_params['CUSTOMER_EMAIL'] = $this->customers_email_address;
            $email_params['STORE_OWNER_EMAIL_ADDRESS'] = STORE_OWNER_EMAIL_ADDRESS;
            list($email_subject, $email_text) = \common\helpers\Mail::get_parsed_email_template('New Customer Confirmation', $email_params);
            \common\helpers\Mail::send($name, $this->customers_email_address, $email_subject, $email_text, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
            if (!$login) {
                $email_params['ADMIN_CUSTOMER_URL'] = Yii::$app->url_manager->create_absolute_url(['admin/customers/customeredit', 'customers_id' => $this->customers_id]);
                list($email_subject, $email_text) = \common\helpers\Mail::get_parsed_email_template('New Customer Query', $email_params);
                \common\helpers\Mail::send(STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS, $email_subject, $email_text, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
            }
        }
    }
}