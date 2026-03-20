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
namespace common\classes;

class payment extends modules\Module_Collection
{
    public $modules;
    public $selected_module;
    public $include_modules = [];
    private $manager;
    // class constructor
    public function __construct($module, \common\services\Order_Manager $manager)
    {
        global $cart;
        // EOF: WebMakers.com Added: Downloads Controller
        $module_key = 'MODULE_PAYMENT_INSTALLED';
        $this->manager = $manager;
        if (defined($module_key) && tep_not_null(constant($module_key))) {
            $this->modules = explode(';', constant($module_key));
            /*foreach ($this->modules as &$class) {
                  $class = basename(str_replace('\\', '/', $class));
              }
              unset($class);*/
            $include_modules = [];
            if (tep_not_null($module) && in_array($module . '.php', $this->modules)) {
                $this->selected_module = $module;
                $include_modules[] = ['class' => $module, 'file' => $module . '.php'];
            } elseif (tep_not_null($module) && in_array(substr($module, 0, strpos($module, '_')) . '.php', $this->modules)) {
                $this->selected_module = substr($module, 0, strpos($module, '_'));
                $include_modules[] = ['class' => substr($module, 0, strpos($module, '_')), 'file' => substr($module, 0, strpos($module, '_')) . '.php'];
            } elseif (tep_not_null($module) && in_array(substr($module, 0, strrpos($module, '_')) . '.php', $this->modules)) {
                $this->selected_module = substr($module, 0, strrpos($module, '_'));
                $include_modules[] = ['class' => substr($module, 0, strrpos($module, '_')), 'file' => substr($module, 0, strrpos($module, '_')) . '.php'];
            } else if (defined('MODULE_PAYMENT_FREECHARGER_STATUS') && MODULE_PAYMENT_FREECHARGER_STATUS == 1 && ($cart->show_total() == 0 and $cart->show_weight == 0)) {
                $this->selected_module = $module;
                $include_modules[] = ['class' => 'freecharger', 'file' => 'freecharger.php'];
            } else {
                // All Other Payment Modules
                if (is_array($this->modules)) {
                    foreach ($this->modules as $value) {
                        $class = substr($value, 0, strrpos($value, '.'));
                        // Don't show Free Payment Module
                        if ($class != 'freecharger') {
                            $include_modules[] = ['class' => $class, 'file' => $value];
                        }
                    }
                }
                // EOF: WebMakers.com Added: Downloads Controller
            }
            \common\helpers\Translation::init('payment');
            $builder = new \common\classes\modules\Module_Builder($manager);
            foreach ($include_modules as $include_module) {
                $class = $include_module['class'];
                $module = "\\common\\modules\\orderPayment\\{$class}";
                if (!class_exists($module)) {
                    continue;
                }
                $this->include_modules[$class] = $builder(['class' => $module]);
                foreach (\common\helpers\Hooks::get_list('payment/check-ignored') as $filename) {
                    include $filename;
                }
            }
        }
    }
    // class methods
    /* The following method is needed in the checkout_confirmation.php page
        due to a chicken and egg problem with the payment class and order class.
        The payment modules needs the order destination data for the dynamic status
        feature, and the order class needs the payment module title.
        The following method is a work-around to implementing the method in all
        payment modules available which would break the modules in the contributions
        section. This should be looked into again post 2.2.
       */
    public function update_status()
    {
        if (is_array($this->include_modules)) {
            if (is_object($this->include_modules[$this->selected_module] ?? null)) {
                if (function_exists('method_exists')) {
                    if (method_exists($this->include_modules[$this->selected_module], 'update_status')) {
                        $this->include_modules[$this->selected_module]->update_status();
                    }
                }
            }
        }
    }
    public function is_payment_selected()
    {
        //2do add allow per customer modules now online IPN will fail \common\extensions\CustomerModules\CustomerModules
        return isset($this->include_modules[$this->selected_module]) && is_object($this->include_modules[$this->selected_module]) && $this->include_modules[$this->selected_module]->enabled;
    }
    public function get_selected_payment()
    {
        if ($this->is_payment_selected()) {
            return $this->include_modules[$this->selected_module];
        }
        return false;
    }
    public function is_payment_enabled($payment_class)
    {
        return isset($this->get_enabled_modules()[$payment_class]) && $this->get_enabled_modules()[$payment_class]->enabled;
    }
    public function get_payment_url()
    {
        if ($_selected = $this->get_selected_payment()) {
            if (property_exists($_selected, 'form_action_url')) {
                return $_selected->form_action_url;
            }
        }
        return null;
    }
    public function javascript_validation()
    {
        $js = '';
        if (is_array($this->include_modules)) {
            $modules = $this->get_enabled_modules();
            foreach ($modules as $_payment) {
                $js .= $_payment->javascript_validation();
            }
            if (count($modules)) {
                $js .= "\n" . '  if (payment_value == null && submitter != 1) {' . "\n" . '    error_message = error_message + ' . json_encode(JS_ERROR_NO_PAYMENT_MODULE_SELECTED, JSON_PARTIAL_OUTPUT_ON_ERROR) . ';' . "\n" . '    error = 1;' . "\n" . '  }' . "\n\n";
            } else {
                //$js .= "$(window).trigger('disable-checkout-button', { name: 'no_payment_method', value: true} );\n";
                $js .= "\n" . '    error_message = error_message + "\n" + ' . json_encode(JS_ERROR_NO_PAYMENT_MODULE_SELECTED, JSON_PARTIAL_OUTPUT_ON_ERROR) . ";\n" . "    error = 2;\n";
            }
            if (defined('GERMAN_SITE') && GERMAN_SITE == 'True' && !defined('ONE_PAGE_POST_PAYMENT')) {
                $js .= ' if (!document.one_page_checkout.conditions.checked){' . "\n" . '   error = 1;' . "\n" . '   error_message = error_message + ' . json_encode(ERROR_JS_CONDITIONS_NOT_ACCEPTED, JSON_PARTIAL_OUTPUT_ON_ERROR) . ';' . "\n" . ' }' . "\n";
            }
            $JS_ERROR = JS_ERROR;
            return <<<EOD
                        <script language="javascript">
            function check_form() {
                var error = 0;
                var error_message = "{$JS_ERROR}";
                var payment_value = null;
                if (document.one_page_checkout.payment) {
                if (document.one_page_checkout.payment.length) {
                    for (var i=0; i<document.one_page_checkout.payment.length; i++) {
                        if (document.one_page_checkout.payment[i].checked) {
                            payment_value = document.one_page_checkout.payment[i].value;
                        }
                    }
                } else if (document.one_page_checkout.payment.checked) {
                    payment_value = document.one_page_checkout.payment.value;
                } else if (document.one_page_checkout.payment.value) {
                    payment_value = document.one_page_checkout.payment.value;
                }
                }
                {$js}
                if (error >= 1 && submitter != 1) {
                    alert(error_message);
                    return false;
                } else {
                    return true;
                }
            }
            
            </script>
            EOD;
        }
        return $js;
    }
    public function get_enabled_modules($inc_disabled_by_zone = false)
    {
        static $cached = [];
        if (!isset($cached[(int) $inc_disabled_by_zone])) {
            /** @var \common\extensions\CustomerModules\CustomerModules $CustomerModules */
            //$CustomerModules = \common\helpers\Acl::checkExtensionAllowed('CustomerModules', 'allowed');
            $enabled = [];
            foreach ($this->include_modules as $class => $module) {
                /*$forceCustomer = false;
                  if ($CustomerModules && !\Yii::$app->user->isGuest) {
                    if ($CustomerModules::checkForceAllowed(\common\classes\platform::currentId(), \Yii::$app->user->getId(), $class)) {
                      $forceCustomer = true;
                    }
                  }*/
                if (is_object($module) && (($module->enabled || $inc_disabled_by_zone && $module->get_status_before_update()) && $module->get_visibily($this->manager->get_platform_id(), $this->manager->get_modules_visibility()))) {
                    $enabled[$class] = $module;
                }
            }
            $cached[(int) $inc_disabled_by_zone] = $enabled;
        }
        return $cached[(int) $inc_disabled_by_zone];
    }
    private $selection_mode = false;
    /**
     * @param string $customerDetails - 'exist'/'optional'/'absent'
     */
    public function selection($opc = false, $only_online = false, $visibility = ['shop_order', 'shop_quote', 'shop_sample', 'admin', 'pos'], $groups_id = 0, $customer_details = 'exist')
    {
        $visibility = \common\helpers\Extensions::get_visibility_variants($visibility);
        $selection_array = [];
        if (!is_array($visibility)) {
            $visibility = [$visibility];
        }
        if (is_array($this->include_modules)) {
            $have_subscription = $this->manager->get_order_instance()->have_subscription();
            /** @var \common\extensions\CustomerModules\CustomerModules $CustomerModules */
            $customer_modules = \common\helpers\Acl::check_extension_allowed('CustomerModules', 'allowed');
            /** @var \common\classes\modules\ModulePayment $_payment */
            foreach ($this->include_modules as $class => $_payment) {
                $force_customer = false;
                if ($customer_modules && !\Yii::$app->user->is_guest) {
                    if ($customer_modules::check_force_allowed(\common\classes\platform::current_id(), \Yii::$app->user->get_id(), $class)) {
                        $force_customer = true;
                    }
                }
                if (is_object($_payment) && ($force_customer || $_payment->enabled && $_payment->get_visibily(\common\classes\platform::current_id(), $visibility))) {
                    //only enabled
                    if (!$force_customer && !$_payment->get_group_visibily(\common\classes\platform::current_id(), $groups_id)) {
                        continue;
                    }
                    if ($customer_modules && !\Yii::$app->user->is_guest) {
                        if (!$customer_modules::check_available(\common\classes\platform::current_id(), \Yii::$app->user->get_id(), $class)) {
                            continue;
                        }
                    }
                    if ($this->manager->get('credit_covers') && is_object($this->include_modules['covered_by_coupon']) && $this->include_modules['covered_by_coupon']->update_status()) {
                        if ($class != 'covered_by_coupon') {
                            continue;
                        }
                    }
                    if ($have_subscription) {
                        if (!$_payment->have_subscription()) {
                            continue;
                        }
                    }
                    if ($only_online && !$_payment->is_online()) {
                        continue;
                    }
                    switch ($customer_details) {
                        case 'absent':
                            if ($_payment->customer_details_required()) {
                                continue 2;
                            }
                        // no break
                        case 'optional':
                            if (!$_payment->customer_details_optional()) {
                                continue 2;
                            }
                        // no break
                        case 'exist':
                    }
                    if ($opc) {
                        $selection = $_payment->selection();
                        if (is_array($selection)) {
                            $selection['module_status'] = $_payment->enabled;
                            $selection_array[] = $selection;
                        }
                    } else {
                        $selection = $_payment->selection();
                        if (is_array($selection)) {
                            $selection_array[] = $selection;
                        }
                    }
                }
            }
            $this->selection_mode = true;
        }
        $this->register_callbacks();
        return $selection_array;
    }
    private $callbacks = [];
    /**
     * Register JSCallback of payments
     */
    public function register_callbacks()
    {
        if ($this->has_callbacks()) {
            \Yii::$app->get_view()->register_js_file(\frontend\design\Info::theme_file('/js/payment.js'));
            \Yii::$app->get_view()->register_js($this->add_callbacks_to_checkout());
        }
    }
    public function has_callback($code)
    {
        return in_array($code, array_keys($this->callbacks));
    }
    /*
     * Register JSCallback function name of particular payment
     */
    public function register_callback($code, $callback)
    {
        $this->callbacks[$code] = $callback;
    }
    public function get_callbacks()
    {
        return $this->callbacks;
    }
    public function has_callbacks()
    {
        return count($this->callbacks);
    }
    protected function make_replacement($callback, &$js_data)
    {
        if (empty($callback)) {
            return;
        }
        if (is_array($js_data)) {
            foreach ($js_data as $_key => &$data) {
                $this->make_replacement($callback, $data);
            }
        } elseif (is_string($js_data)) {
            $has_params = strpos($callback, '(');
            $params = '';
            if ($has_params !== false) {
                $params = substr($callback, $has_params);
                $callback = substr($callback, 0, $has_params);
                $params = preg_replace("/\\(.*\\)/", '', $params);
            }
            $js_data = preg_replace("/function[\\s]{1,3}{$callback}/", "window.{$callback} = function" . $params, $js_data);
        }
    }
    protected function globalise_callback(string $callback)
    {
        $view = \Yii::$app->get_view();
        if (property_exists($view, 'js') && is_array($view->js)) {
            foreach ($view->js as &$js_data) {
                $this->make_replacement($callback, $js_data);
            }
        }
    }
    protected function add_callbacks_to_checkout(): string
    {
        $js = '';
        if ($this->has_callbacks()) {
            $callbacks = $this->get_callbacks();
            foreach ($callbacks as $p_code => $c_value) {
                if (is_array($c_value)) {
                    //to do
                } elseif (is_string($c_value)) {
                    $this->globalise_callback($c_value);
                }
            }
            $callbacks = json_encode($callbacks);
            $form_name = !$this->selection_mode ? 'frmCheckoutConfirm' : 'frmCheckout';
            $js .= <<<EOD
            paymentCollection.init(document.getElementById('{$form_name}'));
            paymentCollection.setCallbacks({$callbacks});
            EOD;
            if (!$this->selection_mode) {
                $js .= <<<EOD
                paymentCollection.setNeedConfirmation(false);
                EOD;
            }
        }
        return $js;
    }
    protected function is_without_confirmation(): bool
    {
        return defined('SKIP_CHECKOUT') && SKIP_CHECKOUT === 'True';
    }
    //ICW CREDIT CLASS Gift Voucher System
    // check credit covers was setup to test whether credit covers is set in other parts of the code
    public function check_credit_covers()
    {
        return $this->manager->get('credit_covers');
    }
    public function pre_confirmation_check()
    {
        if ($this->is_payment_selected()) {
            if ($this->manager->get('credit_covers')) {
                //  ICW CREDIT CLASS Gift Voucher System
                $this->include_modules[$this->selected_module]->enabled = false;
                //ICW CREDIT CLASS Gift Voucher System
                $this->include_modules[$this->selected_module] = null;
                //ICW CREDIT CLASS Gift Voucher System
                $payment_modules = '';
                //ICW CREDIT CLASS Gift Voucher System
            } else {
                //ICW CREDIT CLASS Gift Voucher System
                $this->include_modules[$this->selected_module]->pre_confirmation_check();
            }
        }
    }
    //ICW CREDIT CLASS Gift Voucher System
    public function confirmation()
    {
        if ($this->is_payment_selected()) {
            $confirmation = $this->include_modules[$this->selected_module]->confirmation();
            $this->register_callbacks();
            return $confirmation;
        }
    }
    public function is_online()
    {
        if ($this->is_payment_selected()) {
            return $this->include_modules[$this->selected_module]->is_online();
        }
    }
    public function process_button()
    {
        if ($this->is_payment_selected()) {
            return $this->include_modules[$this->selected_module]->process_button();
        }
    }
    public function before_process()
    {
        if ($this->is_payment_selected()) {
            return $this->include_modules[$this->selected_module]->before_process();
        }
    }
    public function before_subscription($id)
    {
        if ($this->is_payment_selected()) {
            return $this->include_modules[$this->selected_module]->before_subscription($id);
        }
    }
    public function get_subscription_info($id)
    {
        if ($this->is_payment_selected()) {
            return $this->include_modules[$this->selected_module]->get_subscription_info($id);
        }
    }
    public function after_process()
    {
        if ($this->is_payment_selected()) {
            return $this->include_modules[$this->selected_module]->after_process();
        }
    }
    public function track_credits()
    {
        if ($this->is_payment_selected()) {
            return $this->include_modules[$this->selected_module]->track_credits();
        }
    }
    public function checkout_initialization_method($visibility = ['shop_order', 'shop_quote', 'shop_sample', 'admin', 'pos'], $groups_id = 0)
    {
        $visibility = \common\helpers\Extensions::get_visibility_variants($visibility);
        $initialize_array = [];
        if (!is_array($visibility)) {
            $visibility = [$visibility];
        }
        if ($groups_id == 0 && !\Yii::$app->user->is_guest) {
            $groups_id = \Yii::$app->user->get_identity()->groups_id;
        } elseif (empty($groups_id) && \Yii::$app->user->is_guest && defined('DEFAULT_USER_GROUP')) {
            $groups_id = (int) DEFAULT_USER_GROUP;
        }
        /** @var \common\extensions\CustomerModules\CustomerModules $CustomerModules */
        $customer_modules = \common\helpers\Acl::check_extension_allowed('CustomerModules', 'allowed');
        foreach ($this->get_enabled_modules(true) as $tmpname => $_payment) {
            if (method_exists($_payment, 'checkout_initialization_method')) {
                $force_customer = false;
                if ($customer_modules && !\Yii::$app->user->is_guest) {
                    if ($customer_modules::check_force_allowed(\common\classes\platform::current_id(), \Yii::$app->user->get_id(), $_payment->code)) {
                        $force_customer = true;
                    }
                }
                if (is_object($_payment) && ($force_customer || ($_payment->enabled || $_payment->get_status_before_update()) && $_payment->get_visibily(\common\classes\platform::current_id(), $visibility))) {
                    //only enabled
                    if (!$force_customer && !$_payment->get_group_visibily(\common\classes\platform::current_id(), $groups_id)) {
                        continue;
                    }
                    if ($customer_modules && !\Yii::$app->user->is_guest) {
                        if (!$customer_modules::check_available(\common\classes\platform::current_id(), \Yii::$app->user->get_id(), $_payment->code)) {
                            continue;
                        }
                    }
                }
                $sort_order = (int) $_payment->sort_order;
                if (isset($initialize_array[$sort_order])) {
                    if ($sort_order == 0) {
                        $inc = 1000;
                        // put not ordered to the end
                    } else {
                        $inc = 1;
                    }
                    $sort_order = max(array_keys($initialize_array)) + $inc;
                }
                $tmp = $_payment->checkout_initialization_method();
                if (!empty($tmp)) {
                    $initialize_array[$sort_order] = $tmp;
                }
            }
        }
        ksort($initialize_array, SORT_NATURAL);
        //NUMERIC
        return $initialize_array;
    }
    public function show_paynow_button($type = 0)
    {
        $initialize_array = [];
        foreach ($this->get_enabled_modules() as $_payment) {
            if (method_exists($_payment, 'checkButtonOnProduct') && $_payment->check_button_on_product() && method_exists($_payment, 'checkout_initialization_method')) {
                $sort_order = (int) $_payment->sort_order;
                if (isset($initialize_array[$sort_order])) {
                    if ($sort_order == 0) {
                        $inc = 1000;
                        // put not ordered to the end
                    } else {
                        $inc = 1;
                    }
                    $sort_order = max(array_keys($initialize_array)) + $inc;
                }
                $initialize_array[$sort_order] = $_payment->checkout_initialization_method(1, $type);
            }
        }
        ksort($initialize_array, SORT_NATURAL);
        //NUMERIC
        return $initialize_array;
    }
    /**
     * get payment modules which support express checkout button at product form.
     * @return array of payment module codes
     */
    public function get_express_payments()
    {
        $initialize_array = [];
        foreach ($this->get_enabled_modules() as $class => $_payment) {
            if (method_exists($_payment, 'checkButtonOnProduct') && $_payment->check_button_on_product() && method_exists($_payment, 'checkout_initialization_method')) {
                $initialize_array[] = $class;
            }
        }
        return $initialize_array;
    }
    public function get($class, $all = false)
    {
        return $all ? $this->include_modules[$class] ?? false : $this->get_enabled_modules()[$class] ?? false;
    }
    public function get_error()
    {
        if ($this->is_payment_selected()) {
            $message_stack = \Yii::$container->get('message_stack');
            $_error = $this->include_modules[$this->selected_module]->get_error();
            if (is_object($message_stack) && method_exists($message_stack, 'save_to_base')) {
                if (isset($_error['title']) && isset($_error['error'])) {
                    $message_stack->save_to_base('payment', $_error['error'], 'error', $_error['title']);
                }
            }
            return $_error;
        }
    }
    public static function module($module, $front = false)
    {
        $file = $front ? DIR_WS_MODULES . 'shipping/' . $module . '.php' : DIR_FS_DOCUMENT_ROOT . '/includes/modules/shipping/' . $module . '.php';
        if (!is_null($module) && file_exists($file)) {
            include_once $file;
            if (class_exists($module)) {
                return new $module();
            }
        }
        return null;
    }
    public function get_confirmation_title()
    {
        if ($module = $this->get_selected_payment()) {
            if (method_exists($module, 'getTitle')) {
                return $module->get_title($this->manager->get_payment());
            }
        }
        return '';
    }
    public function get_transactional_modules()
    {
        static $transactional = null;
        if (is_null($transactional)) {
            $transactional = [];
            foreach ($this->include_modules as $class => $module) {
                if (is_object($module) && $module instanceof \common\classes\modules\Transactional_Interface) {
                    $transactional[$class] = $module;
                }
            }
        }
        return $transactional;
    }
    public function get_transaction_search_modules()
    {
        static $transaction_search = null;
        if (is_null($transaction_search)) {
            $transaction_search = [];
            foreach ($this->include_modules as $class => $module) {
                if (is_object($module) && $module instanceof \common\classes\modules\Transaction_Search_Interface) {
                    $transaction_search[$class] = $module;
                }
            }
        }
        return $transaction_search;
    }
    public function confirmation_curl_allowed()
    {
        if ($this->is_payment_selected()) {
            return $this->include_modules[$this->selected_module]->confirmation_curl_allowed();
        }
    }
    public function confirmation_autosubmit()
    {
        if ($this->is_payment_selected() && method_exists($this->include_modules[$this->selected_module], 'confirmationAutosubmit')) {
            return $this->include_modules[$this->selected_module]->confirmation_autosubmit();
        }
    }
    public function pop_up_mode()
    {
        if ($this->is_payment_selected()) {
            return $this->include_modules[$this->selected_module]->pop_up_mode();
        }
    }
    public function direct_payment()
    {
        if ($this->is_payment_selected()) {
            return $this->include_modules[$this->selected_module]->direct_payment();
        }
    }
    public function process_button()
    {
        if ($this->is_payment_selected() && method_exists($this->include_modules[$this->selected_module], 'processButton')) {
            return $this->include_modules[$this->selected_module]->process_button();
        }
        return false;
    }
}