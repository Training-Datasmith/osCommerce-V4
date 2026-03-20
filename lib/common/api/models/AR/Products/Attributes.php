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
namespace common\api\models\AR\Products;

use common\api\models\AR\Ep_Map;
use common\api\models\AR\Products;
use common\api\models\AR\Products\Attributes\Prices as Attributes_Prices;
class Attributes extends Ep_Map
{
    protected $hide_fields = ['products_attributes_id', 'products_id', 'product_attributes_one_time', 'products_attributes_filename', 'products_attributes_maxdays', 'products_attributes_maxcount'];
    protected $child_collections = ['prices' => []];
    /**
     * @var Products
     */
    protected $parent_object;
    public function __construct(array $config = [])
    {
        $market_present = defined('USE_MARKET_PRICES') && USE_MARKET_PRICES == 'True';
        $groups_present = \common\helpers\Extensions::is_customer_groups_allowed();
        if (!$market_present && !$groups_present) {
            unset($this->child_collections['prices']);
        }
        parent::__construct($config);
    }
    /**
     * @inheritdoc
     */
    public static function table_name()
    {
        return TABLE_PRODUCTS_ATTRIBUTES;
    }
    /**
     * @inheritdoc
     */
    public static function primary_key()
    {
        return ['products_attributes_id'];
    }
    public function parent_ep_map(Ep_Map $parent_object)
    {
        $this->products_id = $parent_object->products_id;
        $this->parent_object = $parent_object;
        parent::parent_ep_map($parent_object);
    }
    public function match_indexed_value(Ep_Map $imported_object)
    {
        if (!is_null($imported_object->options_id) && !is_null($this->options_id) && $imported_object->options_id == $this->options_id && !is_null($imported_object->options_values_id) && !is_null($this->options_values_id) && $imported_object->options_values_id == $this->options_values_id) {
            $this->pending_removal = false;
            return true;
        }
        return false;
    }
    public function import_array($data)
    {
        $tools = new \backend\models\EP\Tools();
        if (isset($data['options_name'])) {
            $data['options_id'] = $tools->get_option_by_name($data['options_name']);
        }
        if (isset($data['options_values_name'])) {
            $data['options_values_id'] = $tools->get_option_value_by_name($data['options_id'], $data['options_values_name']);
        }
        return parent::import_array($data);
    }
    public function export_array(array $fields = [])
    {
        $data = parent::export_array($fields);
        if (count($fields) == 0 || in_array('options_name', $fields) || in_array('options_values_name', $fields) || in_array('is_virtual', $fields)) {
            $tools = \backend\models\EP\Tools::get_instance();
            if (count($fields) == 0 || in_array('options_name', $fields)) {
                $data['options_name'] = $tools->get_option_name($this->options_id, \common\classes\language::default_id());
            }
            if (count($fields) == 0 || in_array('options_values_name', $fields)) {
                $data['options_values_name'] = $tools->get_option_value_name($this->options_values_id, \common\classes\language::default_id());
            }
            if (count($fields) == 0 || in_array('is_virtual', $fields)) {
                $data['is_virtual'] = $tools->is_option_virtual($this->options_id);
            }
        }
        return $data;
    }
    public function init_collection_by_lookup_key_prices($lookup_keys)
    {
        $load_all = in_array('*', $lookup_keys);
        foreach (Attributes_Prices::get_all_key_codes() as $key_code => $lookup_pk) {
            $this->child_collections['prices'][$key_code] = null;
            if (is_null($this->products_attributes_id)) {
                $this->child_collections['prices'][$key_code] = new Attributes_Prices($lookup_pk);
            } elseif ($load_all || in_array($key_code, $lookup_keys)) {
                if (!isset($this->child_collections['prices'][$key_code])) {
                    $lookup_pk['products_attributes_id'] = $this->products_attributes_id;
                    $this->child_collections['prices'][$key_code] = Attributes_Prices::find_one($lookup_pk);
                    if (!is_object($this->child_collections['prices'][$key_code])) {
                        $this->child_collections['prices'][$key_code] = new Attributes_Prices($lookup_pk);
                    }
                }
            }
        }
        return $this->child_collections['prices'];
    }
}