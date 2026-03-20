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
namespace backend\design\editor;

use yii\base\Widget;
class Addresses_List extends Widget
{
    public $file;
    public $params;
    public $settings;
    public $manager;
    public $type;
    //shipping or billing
    public $mode;
    public $ab_id;
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        if (!is_object($this->manager)) {
            throw new \Exception('order manager should be defined');
        }
        if (!in_array($this->mode, ['single', 'select', 'edit'])) {
            throw new \Exception('mode type should be defined');
        }
        $this->params['manager'] = $this->manager;
        $this->params['type'] = $this->type;
        $this->params['mode'] = $this->mode;
        if ($this->ab_id) {
            $_selected_a_bid = $this->ab_id;
        } elseif ($this->type == 'shipping') {
            $_selected_a_bid = $this->manager->get_sendto();
        } else {
            $_selected_a_bid = $this->manager->get_billto();
        }
        $this->params['selected_ab_id'] = $_selected_a_bid;
        if ($this->mode == 'single') {
            $this->params['address'] = $this->manager->get_customers_address($_selected_a_bid, true, true);
            $this->_define_form();
            if (is_null($this->params['address']) || !$this->params['model']->customer_address_is_ready() || $this->params['model']->has_errors()) {
                $this->params['error'] = true;
            }
        } elseif ($this->mode == 'select') {
            $this->ab_id = $_selected_a_bid;
            $this->_define_form();
            if (!$this->params['model']->customer_address_is_ready()) {
                $this->params['error'] = true;
            }
            $this->params['addresses'] = $this->manager->get_customers_addresses(true, true, $this->type);
            if (!count($this->params['addresses'])) {
                $this->params['mode'] = 'edit';
            }
        } else {
            $this->_define_form();
        }
        if ($this->params['mode'] == 'edit') {
            $this->params['postcoder'] = ($ext = \common\helpers\Acl::check_extension_allowed('AddressLookup')) ? $ext::get_tool() : null;
        }
        return $this->render('addresses-list', $this->params);
    }
    private function _define_form()
    {
        if ($this->type == 'shipping') {
            $this->params['model'] = $this->manager->get_shipping_form($this->ab_id);
        } else {
            $this->params['model'] = $this->manager->get_billing_form($this->ab_id);
        }
    }
}