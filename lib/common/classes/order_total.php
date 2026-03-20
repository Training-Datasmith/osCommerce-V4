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

class order_total extends modules\Module_Collection
{
    public $modules;
    public $readonly = ['ot_tax', 'ot_total', 'ot_subtotal', 'ot_due', 'ot_paid', 'ot_subtax', 'ot_refund'];
    protected $include_modules = [];
    private $manager;
    // class constructor
    public function __construct($reconfig, \common\services\Order_Manager $manager)
    {
        global $language;
        if (defined('MODULE_ORDER_TOTAL_INSTALLED') && tep_not_null(MODULE_ORDER_TOTAL_INSTALLED)) {
            $this->modules = explode(';', MODULE_ORDER_TOTAL_INSTALLED);
            \common\helpers\Translation::init('ordertotal');
            $this->manager = $manager;
            $builder = new \common\classes\modules\Module_Builder($manager);
            if (is_array($this->modules)) {
                foreach ($this->modules as $value) {
                    //$value = basename(str_replace('\\', '/', $value));
                    $class = substr($value, 0, strrpos($value, '.'));
                    $module = "\\common\\modules\\orderTotal\\{$class}";
                    if (!class_exists($module)) {
                        continue;
                    }
                    if (\frontend\design\Info::is_totally_admin()) {
                        defined('ONE_PAGE_CHECKOUT') or define('ONE_PAGE_CHECKOUT', 'True');
                        defined('ONE_PAGE_SHOW_TOTALS') or define('ONE_PAGE_SHOW_TOTALS', 'true');
                    }
                    $this->include_modules[$class] = $builder(['class' => $module]);
                    if (method_exists($this->include_modules[$class], 'config')) {
                        $config_array = array_merge(['ONE_PAGE_CHECKOUT' => defined('ONE_PAGE_CHECKOUT') ? ONE_PAGE_CHECKOUT : 'False', 'ONE_PAGE_SHOW_TOTALS' => defined('ONE_PAGE_SHOW_TOTALS') ? ONE_PAGE_SHOW_TOTALS : 'false'], is_array($reconfig) ? $reconfig : []);
                        $this->include_modules[$class]->config($config_array);
                    }
                }
            }
        }
    }
    public function get_custom_value($module)
    {
        $replacing_value = -1;
        if (in_array('admin', $this->manager->get_modules_visibility())) {
            $currencies = \Yii::$container->get('currencies');
            $cart = $this->manager->get_cart();
            $currency = $this->manager->get('currency');
            if (array_key_exists($module->code, $cart->totals) && tep_not_null($cart->totals[$module->code]) && (!in_array($module->code, $this->readonly) || $module->code == 'ot_tax' || $module->code == 'ot_paid')) {
                if ($cart->totals[$module->code]['value'] != '&nbsp;') {
                    if (is_array($cart->totals[$module->code]['value'])) {
                        $replacing_value = ['in' => (float) $cart->totals[$module->code]['value']['in'], 'ex' => (float) $cart->totals[$module->code]['value']['ex']];
                    }
                } else {
                    $replacing_value = 0;
                }
            }
        }
        return $replacing_value;
    }
    private $order_total_array = null;
    /*$processAgain = array['ot_due', 'ot_paid']*/
    public function process($re_process = [])
    {
        //static $order_total_array = null;
        if (is_null($this->order_total_array) || !empty($re_process)) {
            $re_process_on = false;
            if (is_null($this->order_total_array)) {
                $this->order_total_array = [];
            }
            $processin_modules = $this->get_enabled_modules();
            if (is_array($re_process) && count($re_process)) {
                $processin_modules = $this->overwrite_modules($re_process);
                $re_process_on = true;
            }
            $processing_order = array_flip(array_keys($processin_modules));
            foreach ($processin_modules as $module) {
                $module->set_processing_order($processing_order);
                if ($this->manager->has_cart() && $this->manager->get_cart()->exist_hidden_module($module->code)) {
                    continue;
                }
                //shoul work only for manual edited modules
                // {{
                $groups_id = 0;
                if ($groups_id == 0 && !\Yii::$app->user->is_guest) {
                    $groups_id = \Yii::$app->user->get_identity()->groups_id;
                } elseif (empty($groups_id) && \Yii::$app->user->is_guest && defined('DEFAULT_USER_GROUP')) {
                    $groups_id = (int) DEFAULT_USER_GROUP;
                }
                if (!$module->get_group_visibily(\common\classes\platform::current_id(), $groups_id)) {
                    continue;
                }
                // }}
                if ($module->get_visibily($module->manager->get_platform_id(), $module->manager->get_modules_visibility())) {
                    $replacing_value = $this->get_custom_value($module);
                    $module->process($replacing_value, is_array($replacing_value) ? true : false);
                    if ($re_process_on) {
                        $this->unset_total($module->code);
                    }
                    for ($i = 0, $n = sizeof($module->output); $i < $n; $i++) {
                        if (tep_not_null($module->output[$i]['title']) && tep_not_null($module->output[$i]['text'])) {
                            $sort = $module->output[$i]['sort_order'] ?? $module->sort_order;
                            while (isset($this->order_total_array[$sort])) {
                                $sort++;
                            }
                            $this->order_total_array[$sort] = ['code' => $module->code, 'title' => $module->output[$i]['title'], 'text' => $module->output[$i]['text'], 'value' => $module->output[$i]['value'] ?? null, 'sort_order' => $sort, 'text_exc_tax' => $module->output[$i]['text_exc_tax'] ?? null, 'text_inc_tax' => $module->output[$i]['text_inc_tax'] ?? null, 'tax_class_id' => $module->output[$i]['tax_class_id'] ?? null, 'value_exc_vat' => $module->output[$i]['value_exc_vat'] ?? null, 'value_inc_tax' => $module->output[$i]['value_inc_tax'] ?? null];
                        }
                    }
                }
            }
        }
        return $this->order_total_array;
    }
    private function unset_total(string $module_code)
    {
        if (!empty($module_code) && is_array($this->order_total_array) && count($this->order_total_array)) {
            foreach ($this->order_total_array as $k => $v) {
                if ($v['code'] == $module_code) {
                    unset($this->order_total_array[$k]);
                }
            }
        }
    }
    public function clear_total_cache()
    {
        $this->order_total_array = null;
    }
    private function overwrite_modules($re_process)
    {
        $processin_modules = [];
        if ($re_process && is_array($re_process)) {
            foreach ($re_process as $mod) {
                $module = $this->get($mod);
                if ($module) {
                    $processin_modules[] = $module;
                }
            }
        }
        return $processin_modules;
    }
    private $enabled = null;
    public function get_enabled_modules()
    {
        //static $enabled = null;
        if (is_null($this->enabled)) {
            $this->enabled = [];
            foreach ($this->include_modules as $class => $module) {
                if (is_object($module) && $module->enabled) {
                    $this->enabled[$class] = $module;
                }
            }
        }
        return $this->enabled;
    }
    public function get($class, $all = false)
    {
        $enabled = $all ? $this->include_modules : $this->get_enabled_modules();
        return $enabled[$class] ?? null;
    }
    public function output()
    {
        $output_string = '';
        foreach ($this->get_enabled_modules() as $module) {
            $size = sizeof($module->output);
            for ($i = 0; $i < $size; $i++) {
                $output_string .= '              <div class="row">' . "\n" . '                <strong>' . $module->output[$i]['title'] . '</strong>&nbsp;' . "\n" . '                <span>' . $module->output[$i]['text'] . '</span>' . "\n" . '              </div>';
            }
        }
        return $output_string;
    }
    // update_credit_account is called in checkout process on a per product basis. It's purpose
    // is to decide whether each product in the cart should add something to a credit account.
    // e.g. for the Gift Voucher it checks whether the product is a Gift voucher and then adds the amount
    // to the Gift Voucher account.
    // Another use would be to check if the product would give reward points and add these to the points/reward account.
    //
    public function update_credit_account($i)
    {
        if (MODULE_ORDER_TOTAL_INSTALLED) {
            foreach ($this->get_enabled_modules() as $module) {
                if (($module->enabled ?? false) && ($module->credit_class ?? false)) {
                    $module->update_credit_account($i);
                }
            }
        }
    }
    public function get_credit_classes()
    {
        $modules = [];
        foreach ($this->get_enabled_modules() as $code => $module) {
            if (isset($module->credit_class) && $module->credit_class) {
                if ($module->get_visibily($module->manager->get_platform_id(), $module->manager->get_modules_visibility())) {
                    $modules[$code] = true;
                }
            }
        }
        return $modules;
    }
    // This function is called in checkout confirmation.
    // It's main use is for credit classes that use the credit_selection() method. This is usually for
    // entering redeem codes(Gift Vouchers/Discount Coupons). This function is used to validate these codes.
    // If they are valid then the necessary actions are taken, if not valid we are returned to checkout payment
    // with an error
    //
    public function collect_posts($limit_class = '', $post_data = [])
    {
        $result = [];
        if (defined('MODULE_ORDER_TOTAL_INSTALLED') && MODULE_ORDER_TOTAL_INSTALLED) {
            foreach ($this->get_enabled_modules() as $class => $module) {
                if (($module->credit_class ?? false) || method_exists($module, 'collect_posts')) {
                    $post_var = 'c' . $module->code;
                    if (isset($post_data[$post_var])) {
                        if ($module->manager) {
                            $module->manager->set($post_var, $post_data[$post_var]);
                        }
                    }
                    if (!empty($limit_class) && $limit_class != $class) {
                        continue;
                    }
                    $response = $module->collect_posts($post_data);
                    if ($response) {
                        $result[$module->code] = $response;
                    }
                }
            }
        }
        return $result;
    }
    // pre_confirmation_check is called on checkout confirmation. It's function is to decide whether the
    // credits available are greater than the order total. If they are then a variable (credit_covers) is set to
    // true. This is used to bypass the payment method. In other words if the Gift Voucher is more than the order
    // total, we don't want to go to paypal etc.
    //
    public function pre_confirmation_check($order)
    {
        if (MODULE_ORDER_TOTAL_INSTALLED) {
            if (number_format($order->info['total_inc_tax'], 6) <= 0) {
                $this->manager->set('credit_covers', true);
            } else {
                $this->manager->remove('credit_covers');
            }
        }
    }
    // this function is called in checkout process. it tests whether a decision was made at checkout payment to use
    // the credit amount be applied aginst the order. If so some action is taken. E.g. for a Gift voucher the account
    // is reduced the order total amount.
    //
    public function apply_credit()
    {
        if (MODULE_ORDER_TOTAL_INSTALLED) {
            foreach ($this->get_enabled_modules() as $module) {
                if ($module->credit_class ?? false) {
                    $module->apply_credit();
                }
            }
        }
    }
    // Called in checkout process to clear session variables created by each credit class module.
    //
    public function clear_posts()
    {
        if (MODULE_ORDER_TOTAL_INSTALLED) {
            foreach ($this->get_enabled_modules() as $module) {
                if ($module->credit_class ?? false) {
                    $post_var = 'c' . $module->code;
                    $module->manager->remove($post_var);
                }
            }
        }
    }
    // Called at various times. This function calulates the total value of the order that the
    // credit will be appled aginst. This varies depending on whether the credit class applies
    // to shipping & tax
    //
    public function get_order_total_main($class, $order_total)
    {
        //global $credit, $order;
        //      if ($GLOBALS[$class]->include_tax == 'false') $order_total=$order_total-$order->info['tax'];
        //      if ($GLOBALS[$class]->include_shipping == 'false') $order_total=$order_total-$order->info['shipping_cost'];
        return $order_total;
    }
    // ICW ORDER TOTAL CREDIT CLASS/GV SYSTEM - END ADDITION
    public function get_all_totals_list()
    {
        if (MODULE_ORDER_TOTAL_INSTALLED) {
        }
    }
    public function get_pos_totals_list()
    {
        $output = [];
        if (MODULE_ORDER_TOTAL_INSTALLED) {
            foreach ($this->get_enabled_modules() as $module) {
                if (is_array($module->output) && count($module->output)) {
                    for ($i = 0; $i < count($module->output); $i++) {
                        $i = 0;
                        if (in_array($module->code, ['ot_subtotal', 'ot_shipping', 'ot_coupon', 'ot_total'])) {
                            if ($module->code == 'ot_shipping' && $module->output[$i]['value_inc_tax'] == 0) {
                                continue;
                            }
                            $output[] = ['title' => $this->get_short_text($module->output[$i]['title'], 15), 'exc' => $module->output[$i]['text_exc_tax'], 'exc' => $module->output[$i]['text_exc_tax'], 'inc' => $module->output[$i]['text_inc_tax'], 'value' => $module->output[$i]['value'], 'type' => $module->code == 'ot_total' ? 1 : 0];
                        }
                    }
                }
            }
        }
        return $output;
    }
    public function get_short_text($str = '', $count = 0, $pattern = '..')
    {
        $text = strip_tags($str);
        if (mb_strlen($text, 'UTF-8') > $count) {
            $text_cut = mb_substr($text, 0, $count, 'UTF-8');
            $text_explode = explode(' ', $text_cut);
            unset($text_explode[count($text_explode) - 1]);
            $text_implode = implode(' ', $text_explode);
            return $text_implode . $pattern;
        }
        return $text;
    }
}