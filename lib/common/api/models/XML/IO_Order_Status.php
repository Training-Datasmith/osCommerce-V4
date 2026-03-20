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

class Io_Order_Status extends Io_Map
{
    protected $named = '@order_status';
    public $name;
    public function serialize_to(\Simple_Xml_Element $parent)
    {
        parent::serialize_to($parent);
        static $statuses = [];
        if ($this->value && !isset($statuses[$this->value])) {
            $statuses[$this->value] = \common\helpers\Order::get_order_status_name($this->value, \common\helpers\Language::get_default_language_id());
        }
        if (isset($statuses[$this->value])) {
            $parent->add_attribute('name', $statuses[$this->value]);
        }
    }
    public function to_import_model()
    {
        $parent_result = parent::to_import_model();
        if (!$parent_result && !empty($this->name) && !Io_Core::get()->is_local_project()) {
            // unknown import status id
            $new_status_id = Io_Core::get()->get_lookup_tool()->lookup_order_status($this->name, true);
            if ($new_status_id) {
                $this->internal_id = $new_status_id;
                $this->value = $new_status_id;
                $parent_result = $new_status_id;
                Io_Core::get()->get_attribute_mapper()->map_ids($this, $this->internal_id, $this->external_id);
            }
        }
        return $parent_result;
    }
}