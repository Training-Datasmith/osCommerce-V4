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
namespace common\api\models\XML;

class Io_Currency_Map extends Complex
{
    protected $name = '@currency';
    public $currency;
    public function serialize_to(\Simple_Xml_Element $parent)
    {
        if (!empty($this->value)) {
            static $currency_codes;
            if (!is_array($currency_codes)) {
                $currency_codes = [];
                $curr = new \common\classes\currencies();
                foreach ($curr->currencies as $curr_info) {
                    $currency_codes[$curr_info['id']] = $curr_info['code'];
                }
            }
            $parent->add_attribute('currency', $currency_codes[$this->value]);
            $parent->add_attribute('internalId', $this->value);
            $external_id = Io_Core::get()->get_attribute_mapper()->external_id($this);
            if (is_numeric($external_id)) {
                $parent->add_attribute('externalId', $external_id);
            }
        }
    }
    public function to_import_model()
    {
        echo '<pre>';
        var_dump($this);
        echo '</pre>';
        die;
        $parent_result = parent::to_import_model();
        if (!$parent_result && !empty($this->currency) && !Io_Core::get()->is_local_project()) {
            $new_id = 0;
            $curr = new \common\classes\currencies();
            foreach ($curr->currencies as $curr_info) {
                if ($this->currency == $curr_info['code']) {
                    $new_id = $curr_info['id'];
                    break;
                }
            }
            echo '<pre>';
            var_dump($this->currency, $new_id, $curr->currencies);
            echo '</pre>';
            if ($new_id) {
                $this->internal_id = $new_id;
                $this->value = $new_id;
                $parent_result = $new_id;
                Io_Core::get()->get_attribute_mapper()->map_ids($this, $this->internal_id, $this->external_id);
            }
        }
        return $parent_result;
    }
}