<?php

declare (strict_types=1);
/*
 * This file is part of osCommerce ecommerce platform.
 * osCommerce the ecommerce
 *
 * @link https://www.oscommerce.com
 * @copyright Copyright (c) 2005 Holbi Group Ltd
 *
 * Released under the GNU General Public License
 * For the full copyright and license information, please view the LICENSE.TXT file that was distributed with this source code.
 */
namespace common\components;

use common\classes\platform;
use yii\db\Query;
use yii\helpers\Array_Helper;
/**
 * ProductsQuery uber class... :,(
 * creates product query for all pages/modules according parameters
 * do something with filters
 * indextables (description and price) are for search and sort only (incorrect filter is not a big problem - availability should be correct)
 *
 * @author vlad
 */
#[\Allow_Dynamic_Properties]
class Products_Query
{
    public const PRESELECT_PRODUCST_IDS = true;
    public const PRESELECT_PRODUCST_PRICE_IDS = true;
    public const PRESELECT_PRODUCST_SEARCH_IDS = true;
    public const PRESELECT_PRODUCST_CATEGORIES_IDS = true;
    protected static $allowed_filter_types = ['boxes', 'radio', 'pulldown'];
    protected $params = [
        'page' => 'catalog/all-products',
        'filters' => [],
        'currentPlatform' => true,
        'currentCategory' => true,
        'currentCustomerGroup' => true,
        'active' => true,
        'forceInStock' => false,
        'outOfStock' => true,
        'onlyWithImages' => false,
        'hasSubcategories' => false,
        'customAndWhere' => '',
        'get' => [],
        // get params to add filter and sort order
        'orderBy' => [],
        'limit' => 0,
        'anyExists' => false,
        'countAllSubcategories' => false,
        'featuredTypeId' => false,
        'groupProductGroups' => false,
        'specialsTypeId' => false,
        'salesOnly' => false,
        'withInventory' => false,
        'skipInTop' => null,
        'skipInTopOnly' => null,
    ];
    /* @var \common\models\queries\ProductsQuery $query */
    private $query = null;
    private $count = [];
    private $list_product_ids = [];
    private $list_product_price_ids = [];
    private $list_product_keywords_ids = [];
    private $hidden_stock_indication_ids = null;
    private $skip_top = false;
    private $backorder_stock_indication_ids = null;
    /**
     * set allowed parameters and apply default restrictions
     *  'page' => 'catalog/all-products',
     *
     *   'filters' => [
     *  'keywords' => ''
     * 'manufacturers' => N,[N]
     * 'categories' => N,[N]
     * 'price' => ['from => dd.dd,  'to' => dd.dd]
     *  'currentPlatform' => true,
     * 'currentCategory' => true,
     *  'currentCustomerGroup' => true,
     *  //'active' => true,
     *  'outOfStock' => true,
     *  'onlyWithImages' => false,
     *  'customAndWhere' => false,
     *
     *  'get' => [
     * 'keywords'
     *
     * brand|manufacturers_id => [id]
     *
     * pfrom, pto  => dd.dd- price range
     *
     * cat => [id]
     *
     * pr(\d+)from => dd.dd,  pr(\d+)to => dd.dd, ^pr(\d+)$ => []
     *
     * ^at(\d+)$  => []
     *
     * ],
     *
     *  'orderBy' => [
     *
     * 'products_name' | 'bestsellers' | 'products_model' | 'products_date_added' | 'products_popularity' | 'products_weight' | 'manufacturers_name' | 'products_price' | 'products_quantity' | 'rand()
     *  => dir | 'FIELD (products_id, 5) DESC'
     * or
     * ['fake' => 1]
     * ],
     *
     *  'limit' => 0
     *  'offset' => 0
     * @param array $params
     */
    public function __construct($params = [])
    {
        if (\Yii::$app->id != 'app-console' && \frontend\design\Info::theme_setting('group_product_by_product_group')) {
            $this->params['groupProductGroups'] = true;
        }
        foreach ($params as $key => $value) {
            if (array_key_exists($key, $this->params) && (!is_array($this->params[$key]) || is_array($this->params[$key]) && is_array($value) && !empty($value))) {
                $this->params[$key] = $value;
            } elseif (!empty($value)) {
                \Yii::warning('Products query builder incorrect type: ' . $key . ' ' . gettype($this->params[$key]) . ' != ' . gettype($value));
            }
        }
        if (!empty($this->params['get'])) {
            $this->filters_from_get();
        }
        if (is_null($this->params['skipInTopOnly']) && is_null($this->params['skipInTop']) && defined('HIDE_PRODUCTS_IN_TOP_CATEGORY')) {
            //from  store config
            if (HIDE_PRODUCTS_IN_TOP_CATEGORY == 'All') {
                $this->skip_top = 'all';
            } elseif (HIDE_PRODUCTS_IN_TOP_CATEGORY == 'Only') {
                $this->skip_top = 'only';
            } elseif (HIDE_PRODUCTS_IN_TOP_CATEGORY == 'None') {
                $this->skip_top = false;
            }
        }
        $this->init();
    }
    public static function count_products_in_categories_query($category_id = 0, $include_inactive = false)
    {
        $q = new \common\components\Products_Query([
            'filters' => ['categories' => [$category_id]],
            //'anyExists' => 1,
            'countAllSubcategories' => true,
            'currentCategory' => false,
            'orderBy' => ['fake' => 1],
            'active' => !$include_inactive,
        ]);
        return $q;
    }
    public function init()
    {
        $this->query = \common\models\Products::find();
        $this->query->alias('p')->select('p.products_id');
        // hidden if -1 in customer group price
        if (false && \common\helpers\Extensions::is_customer_groups_allowed()) {
            //USE_MARKET_PRICES == 'True' || no disable by currency, it's by group only
            $customer_groups_id = (int) \Yii::$app->storage->get('customer_groups_id');
            $currency_id = \Yii::$app->settings->get('currency_id');
            if ($customer_groups_id > 0) {
                $this->query->left_join(['pp' => TABLE_PRODUCTS_PRICES], ['pp.products_id' => new \yii\db\Expression('p.products_id'), 'pp.groups_id' => (int) $customer_groups_id, 'pp.currencies_id' => USE_MARKET_PRICES == 'True' ? (int) $currency_id : 0])->and_where(['<>', new \yii\db\Expression('COALESCE(pp.products_group_price, 0)'), -1]);
                // products_group_price <> -1 (null safe)
            }
            // new way to restrict in separate method
        }
        if (!empty($this->skip_top)) {
            if ($this->skip_top == 'all') {
                // LEFTJOIN instead of NOT EXISTS SELECT => 2 tmp_tables less
                $this->query->left_join(['p2cskip' => TABLE_PRODUCTS_TO_CATEGORIES], ['p2cskip.products_id' => new \yii\db\Expression('p.products_id'), 'p2cskip.categories_id' => 0])->and_where(['<>', new \yii\db\Expression('COALESCE(p2cskip.categories_id, 1)'), 0]);
                // p2cskip.categories_id <> 0 (null safe)
            } elseif ($this->skip_top == 'only') {
                $this->query->and_filter_where($this->exclude_top_category_products());
            }
        }
        // hidden by stock indication flag
        if (is_null($this->hidden_stock_indication_ids)) {
            $this->hidden_stock_indication_ids = \common\classes\Stock_Indication::get_hidden_ids();
        }
        if (!is_null($this->hidden_stock_indication_ids)) {
            $this->query->and_where(['not in', 'p.stock_indication_id', $this->hidden_stock_indication_ids]);
        }
        if (defined('LISTING_SUB_PRODUCT') && LISTING_SUB_PRODUCT == 'True' && !\frontend\design\Info::is_totally_admin()) {
            $this->query->and_where('p.is_listing_product=1');
        }
        /** @var \common\extensions\CustomerProducts\CustomerProducts $ext  */
        if ($ext = \common\helpers\Acl::check_extension('CustomerProducts', 'allowed')) {
            if ($ext::allowed() && !\Yii::$app->user->is_guest) {
                if (\Yii::$app->user->identity->restrict_products) {
                    $this->query->and_where(['p.products_id' => \common\extensions\Customer_Products\models\Customer_Products::find()->where(['customer_id' => \Yii::$app->user->get_id()])->select('product_id')->as_array()->column()]);
                }
            }
        }
        foreach (\common\helpers\Hooks::get_list('products-query/init') as $filename) {
            include $filename;
        }
        /**
         * @var $ga \common\extensions\GoogleAnalyticsTools\GoogleAnalyticsTools
         */
        if (isset($this->params['filters']['keywords'])) {
            if (($ga = \common\helpers\Extensions::is_allowed('GoogleAnalyticsTools')) && $ga::option_use_in_search()) {
                $ga_query = $ga::get_products_for_products_query($this->params['filters']['keywords']);
                if ($ga_query) {
                    $this->query->and_where(['p.products_id' => $ga_query]);
                }
            }
        }
    }
    protected function exclude_top_category_products()
    {
        static $top_product_ids;
        if (!is_array($top_product_ids)) {
            $top_product_ids = \common\models\Products2Categories::find()->where(['categories_id' => 0])->select(['products_id'])->column();
        }
        if (count($top_product_ids) < 1000) {
            return ['NOT IN', 'p.products_id', $top_product_ids];
        }
        return ['exists', (new Query())->from(['p2cskip' => TABLE_PRODUCTS_TO_CATEGORIES])->where('p.products_id = p2cskip.products_id')->and_where('p2cskip.categories_id <> 0')];
    }
    /**
     * parse get param and put them into filter
     */
    protected function filters_from_get()
    {
        if (!empty($this->params['get']) && is_array($this->params['get'])) {
            foreach ($this->params['get'] as $key => $values) {
                $arr = [];
                if ($key == 'sort') {
                    $values = trim($values);
                    $this->params['orderBy'] = \common\helpers\Sorting::get_order_by_array($values);
                } elseif ($key == 'keywords') {
                    if (\Yii::$app->request->get('onlyFilter')) {
                        $this->params['filters']['keywords'] = tep_db_input(tep_db_prepare_input(strip_tags($values)));
                    } else {
                        $this->params['filters']['keywords'] = tep_db_prepare_input(strip_tags($values));
                    }
                } elseif ($key == 'products_id' && !empty($values)) {
                    if (is_string($values)) {
                        $values = preg_split('#,#', $values, -1, PREG_SPLIT_NO_EMPTY);
                    }
                    $this->params['filters']['limitedProducts'] = array_map('intval', $values);
                } elseif ($key == 'brand' || $key == 'manufacturers_id') {
                    //brand
                    if (!is_array($values)) {
                        $values = [$values];
                    }
                    if (!(is_array($values) && count($values) == 1 && key_exists(0, $values) && empty($values[0]))) {
                        // skip empty value for &brand[] then $values = [0=>'']
                        $this->params['filters']['manufacturers'] = array_map('intval', $values);
                    }
                } elseif ($key == 'cat') {
                    // categories
                    if (!is_array($values)) {
                        $values = [$values];
                    }
                    $this->params['filters']['categories'] = array_map('intval', $values);
                } elseif ($key == 'pfrom' && (!defined('GROUPS_IS_SHOW_PRICE') || GROUPS_IS_SHOW_PRICE == true)) {
                    // Price from
                    $values = str_replace(',', '.', preg_replace('/[^\d\.\,]/', '', $values));
                    $this->params['filters']['price']['from'] = (float) $values;
                } elseif ($key == 'pto' && (!defined('GROUPS_IS_SHOW_PRICE') || GROUPS_IS_SHOW_PRICE == true)) {
                    //interval to
                    $values = str_replace(',', '.', preg_replace('/[^\d\.\,]/', '', $values));
                    $this->params['filters']['price']['to'] = (float) $values;
                } elseif (preg_match("/^pr(\\d+)from\$/", $key, $arr)) {
                    // Properties
                    //interval from
                    $prop_id = (int) $arr[1];
                    if ($prop_id > 0) {
                        $this->params['filters']['properties'][$prop_id]['from'] = (float) $values;
                        if ($this->params['filters']['properties'][$prop_id]['to'] && $this->params['filters']['properties'][$prop_id]['to'] < (float) $values) {
                            $this->params['filters']['properties'][$prop_id]['from'] = $this->params['filters']['properties'][$prop_id]['to'];
                            $this->params['filters']['properties'][$prop_id]['to'] = (float) $values;
                        }
                        $this->params['filters']['properties'][$prop_id]['field'] = 'values_number';
                    }
                } elseif (preg_match("/^pr(\\d+)to\$/", $key, $arr)) {
                    //interval to
                    $prop_id = (int) $arr[1];
                    if ($prop_id > 0) {
                        $this->params['filters']['properties'][$prop_id]['to'] = (float) $values;
                        if ($this->params['filters']['properties'][$prop_id]['from'] && $this->params['filters']['properties'][$prop_id]['from'] > (float) $values) {
                            $this->params['filters']['properties'][$prop_id]['to'] = $this->params['filters']['properties'][$prop_id]['from'];
                            $this->params['filters']['properties'][$prop_id]['from'] = (float) $values;
                        }
                        $this->params['filters']['properties'][$prop_id]['field'] = 'values_number';
                    }
                } elseif (preg_match("/^vpr(\\d+)from(\\d+)\$/", $key, $arr)) {
                    $prop_id = (int) $arr[1];
                    $val_id = (int) $arr[2];
                    if ($prop_id > 0 && $val_id > 0 && !empty($values)) {
                        $this->params['filters']['extra'][$prop_id]['field'] = 'extra_value';
                        $this->params['filters']['extra'][$prop_id]['from'][$val_id] = (float) $values;
                    }
                } elseif (preg_match("/^vpr(\\d+)to(\\d+)\$/", $key, $arr)) {
                    $prop_id = (int) $arr[1];
                    $val_id = (int) $arr[2];
                    if ($prop_id > 0 && $val_id > 0 && !empty($values)) {
                        $this->params['filters']['extra'][$prop_id]['field'] = 'extra_value';
                        $this->params['filters']['extra'][$prop_id]['to'][$val_id] = (float) $values;
                    }
                } elseif (preg_match("/^pr(\\d+)\$/", $key, $arr)) {
                    //flags Y|N && ids
                    $prop_id = (int) $arr[1];
                    if ($prop_id > 0) {
                        $is_flag = false;
                        if (is_array($values)) {
                            $tmp = current($values);
                            if (!is_array($tmp)) {
                                $is_flag = in_array(strtoupper($tmp), ['Y', 'N']);
                            }
                        } else {
                            $is_flag = in_array(strtoupper($values), ['Y', 'N']);
                        }
                        if ($is_flag) {
                            if (!is_array($values)) {
                                $values = [$values];
                            }
                            $this->params['filters']['properties'][$prop_id] = ['field' => 'values_flag', 'values' => array_map(function ($v) {
                                return strtoupper($v) == 'Y' ? 1 : 0;
                            }, $values)];
                        } else {
                            $v_list = [];
                            if (is_array($values)) {
                                foreach ($values as $_ix => $value) {
                                    if (preg_match("/[\\d]+\\,/", $value)) {
                                        $v_list = array_merge($v_list, array_map('intval', explode(',', $value)));
                                    } else {
                                        $v_list = $values;
                                    }
                                }
                            }
                            if (!isset($this->params['filters']['properties'][$prop_id])) {
                                $this->params['filters']['properties'][$prop_id] = ['field' => 'values_id', 'values' => array_filter(array_map('intval', $v_list))];
                            } else {
                                $this->params['filters']['properties'][$prop_id]['values'] = array_merge($this->params['filters']['properties'][$prop_id]['values'], array_map('intval', $v_list));
                            }
                        }
                    }
                } elseif (preg_match("/^at(\\d+)\$/", $key, $arr)) {
                    // Attributes selected
                    $attr_id = (int) $arr[1];
                    if ($attr_id > 0) {
                        $this->params['filters']['attributes'][$attr_id] = ['values' => array_map('intval', $values)];
                    }
                } elseif ($key == 'salesOnly') {
                    // Sales Only Filter
                    $this->params['salesOnly'] = boolval($values);
                }
            }
            /// filter steps (unset filtered/child if current/parent is reset)
            if (!empty($this->params['filters']['properties']) && is_array($this->params['filters']['properties'])) {
                $propids = array_keys($this->params['filters']['properties']);
                $lst = \common\models\Properties::find()->and_where('filter_by_property>0')->and_where(['properties_id' => $propids, 'filter_steps' => 1])->select('properties_id, filter_by_property')->as_array()->all();
                if (!empty($lst)) {
                    foreach ($lst as $child) {
                        if (empty($this->params['filters']['properties'][$child['filter_by_property']]) || $this->params['filters']['properties'][$child['filter_by_property']]['field'] == 'values_id' && empty($this->params['filters']['properties'][$child['filter_by_property']]['values'])) {
                            //$this->params['filters']['properties'][$child['properties_id']]['values'] = [];
                            unset($this->params['filters']['properties'][$child['properties_id']]);
                        }
                    }
                }
            }
            /// filter steps eof
        }
        unset($this->params['get']);
    }
    /**
     * build Query object with few (2) columns: products_id and columns from sort_order (mysql restriction)
     * and all restrictions from params
     * @return self
     */
    public function build_query($params = null)
    {
        if ($params === $this->params) {
            return $this;
        }
        if (!is_array($params)) {
            $params = $this->params;
        }
        if (empty($params['orderBy']) && empty($params['get']['sort'])) {
            if (!empty($params['currentCategory']) && (int) $this->get_current_category_id() > 0) {
                $cat = $this->get_current_category_id();
            } else {
                $cat = 0;
            }
            if (empty($this->params['filters']['keywords']) || empty($this->relevance_order)) {
                $def_sort = \common\helpers\Sorting::get_default_sort_order($cat);
            }
            if (!empty($def_sort)) {
                $_tmp = $this->params;
                //probably not required and it's ok to add orderby to current instance.....
                $this->params['get']['sort'] = $def_sort;
                $this->filters_from_get();
                if (!empty($this->params['orderBy'])) {
                    $params['orderBy'] = $this->params['orderBy'];
                }
                $this->params = $_tmp;
                //VL WTF  at least check keywords
                //      } else {
                //          $params['orderBy'] = ['fake' => false];
            }
        }
        $this->init();
        if (is_array($params)) {
            foreach ($params as $k => $v) {
                $method = 'add' . ucfirst($k);
                if ($v !== false && method_exists($this, $method)) {
                    $this->{$method}($v);
                } elseif ($v !== false) {
                    \Yii::warning("Build Query not exists {$method} ");
                }
            }
        }
        return $this;
    }
    public function rebuild_by_group()
    {
        if (\frontend\design\Info::theme_setting('group_product_by_product_group') && $this->has_group_product_groups()) {
            $group_check_query = clone $this->get_query();
            $not_members = [];
            $xxx = $group_check_query->select(['p.products_groups_id', 'group_members' => new \yii\db\Expression('GROUP_CONCAT(p.products_id ORDER BY products_groups_sort)')])->having(['>', new \yii\db\Expression('COUNT(*)'), 1])->as_array()->all();
            foreach ($xxx as $xx) {
                if ($xx['products_groups_id'] == 0) {
                    continue;
                }
                $group_members = explode(',', $xx['group_members']);
                $unset_index = 0;
                // {{ sort group selection by instock color
                $products_instock = \common\models\Products::find()->where(['products_groups_id' => $xx['products_groups_id']])->and_where(['products_status' => 1])->and_where(['>', 'products_quantity', 0])->select(['products_id'])->as_array()->all();
                $products_instock = \yii\helpers\Array_Helper::map($products_instock, 'products_id', 'products_id');
                if (count($products_instock) > 0) {
                    foreach ($group_members as $__idx => $group_members_id) {
                        if (isset($products_instock[$group_members_id])) {
                            $unset_index = $__idx;
                            break;
                        }
                    }
                }
                // }} sort group selection by instock color
                unset($group_members[$unset_index]);
                $not_members = array_merge($not_members, $group_members);
            }
            $this->get_query()->and_where(['NOT IN', 'p.products_id', $not_members]);
            return $this;
        } else {
            return $this;
        }
    }
    public function add_current_customer_group()
    {
        if (!empty($this->params['currentCustomerGroup']) && $this->params['currentCustomerGroup']) {
            $customer_groups_id = (int) \Yii::$app->storage->get('customer_groups_id');
            if ($groups_products = \common\helpers\Acl::check_extension_table_exist('UserGroupsRestrictions', 'GroupsProducts', 'isAllowed')) {
                $this->query->and_where(['exists', $groups_products::find()->where('p.products_id = products_id')->and_where(['groups_id' => (int) $customer_groups_id])]);
                if (\common\helpers\Extensions::is_allowed('Inventory') && !$this->params['withInventory']) {
                    if ($groups_inventory = \common\helpers\Acl::check_extension_table_exist('UserGroupsRestrictions', 'GroupsInventory', 'isAllowed')) {
                        $this->query->and_where(['or', ['not exists', \common\models\Inventory::find()->where('p.products_id = prid')], ['and', ['exists', \common\models\Inventory::find()->where('p.products_id = prid')], ['exists', $groups_inventory::find()->where('p.products_id = prid')->and_where(['groups_id' => (int) $customer_groups_id])]]]);
                    }
                }
            }
        }
    }
    public function add_any_exists()
    {
        //do nothing - only to skip caching
    }
    public function add_get()
    {
        //do nothing
    }
    public function add_featured_type_id()
    {
        //do nothing
    }
    public function add_specials_type_id()
    {
        //do nothing
    }
    public function add_skip_in_top()
    {
        //do nothing
    }
    public function add_skip_in_top_only()
    {
        //do nothing
    }
    public function add_active()
    {
        if (!empty($this->params['active']) && $this->params['active']) {
            /* @var $ext \common\extensions\ShowInactive\ShowInactive */
            if ($ext = \common\helpers\Extensions::is_allowed('ShowInactive')) {
                $this->query->and_where($ext::get_state(false));
            } else {
                $this->query->and_where(' p.products_status = 1 ');
            }
        }
    }
    public function add_force_in_stock()
    {
        if (is_null($this->backorder_stock_indication_ids)) {
            $this->backorder_stock_indication_ids = \common\models\Products_Stock_Indication::find()->where(['allow_out_of_stock_checkout' => 1, 'allow_out_of_stock_add_to_cart' => 1])->select('stock_indication_id')->as_array()->column();
        }
        $q = 'p.stock_indication_id = 0 and p.products_quantity > 0';
        if (!is_null($this->backorder_stock_indication_ids)) {
            $q = ['or', ['p.stock_indication_id' => $this->backorder_stock_indication_ids], $q];
        }
        $this->query->and_where($q);
    }
    public function add_out_of_stock()
    {
        if (defined('SHOW_OUT_OF_STOCK') && !SHOW_OUT_OF_STOCK) {
            if (is_null($this->backorder_stock_indication_ids)) {
                $this->backorder_stock_indication_ids = \common\models\Products_Stock_Indication::find()->where(['allow_out_of_stock_checkout' => 1, 'allow_out_of_stock_add_to_cart' => 1])->select('stock_indication_id')->as_array()->column();
            }
            $q = 'p.stock_indication_id = 0 and p.products_quantity > 0';
            if (!is_null($this->backorder_stock_indication_ids)) {
                $q = ['or', ['p.stock_indication_id' => $this->backorder_stock_indication_ids], $q];
            }
            $this->query->and_where($q);
        }
    }
    public function add_has_subcategories()
    {
    }
    public function has_group_product_groups()
    {
        $group_by = $this->get_query()->group_by;
        if (!is_array($group_by)) {
            $group_by = [];
        }
        $has_grouping = array_search('IF(p.products_groups_id>0,  CONCAT("G_", p.products_groups_id),  CONCAT("P_", p.products_id))', $group_by);
        return $has_grouping !== false;
    }
    public function add_group_product_groups()
    {
        $group_by = $this->get_query()->group_by;
        if (!is_array($group_by)) {
            $group_by = [];
        }
        $has_grouping = array_search('IF(p.products_groups_id>0,  CONCAT("G_", p.products_groups_id),  CONCAT("P_", p.products_id))', $group_by);
        if ($has_grouping === false) {
            $group_by[] = 'IF(p.products_groups_id>0,  CONCAT("G_", p.products_groups_id),  CONCAT("P_", p.products_id))';
        }
        $this->get_query()->group_by($group_by);
        $this->get_query()->add_select('p.products_groups_id');
        return $this;
    }
    public function remove_group_product_groups()
    {
        $group_by = $this->get_query()->group_by;
        if (!is_array($group_by)) {
            $group_by = [];
        }
        $has_grouping = array_search('IF(p.products_groups_id>0,  CONCAT("G_", p.products_groups_id),  CONCAT("P_", p.products_id))', $group_by);
        if ($has_grouping === false) {
            return false;
        } else {
            unset($group_by[$has_grouping]);
            $this->get_query()->group_by($group_by);
            return true;
        }
        return $this;
    }
    /*vl2do */
    public function add_only_with_images()
    {
        $this->query->and_where(['exists', \common\models\Products_Images::find()->where('p.products_id = products_id')->and_where('image_status=1 and default_image=1')]);
    }
    public function add_limit($limit = null)
    {
        if (!empty($limit) && (int) $limit > 0 || !empty($this->params['limit']) && (int) $this->params['limit'] > 0) {
            $this->query->limit((int) $limit > 0 ? (int) $limit : $this->params['limit']);
        } else {
            //$this->query->limit(100000);
        }
        return $this;
    }
    public function add_offset($offset = null)
    {
        if (!empty($offset) && (int) $offset > 0 || !empty($this->params['offset']) && (int) $this->params['offset'] > 0) {
            $this->query->offset((int) $offset > 0 ? (int) $offset : $this->params['offset']);
        }
        return $this;
    }
    public function add_custom_and_where($params = null)
    {
        if (!$params) {
            $params = $this->params['customAndWhere'];
        }
        if (is_string($params)) {
            $params = trim($params);
        }
        if (!empty($params)) {
            if (is_array($params)) {
                $this->query->and_where($params);
            } else {
                $this->query->and_where($params);
            }
        }
    }
    public function add_sales_only($type_id = 0)
    {
        if (!empty($this->params['salesOnly']) && $this->params['salesOnly']) {
            $this->sales_restriction($type_id);
        }
    }
    private function sales_restriction($type_id = 0)
    {
        $eq = \common\models\Specials::find()->alias('sp')->and_where(['status' => 1, 'specials_type_id' => $type_id]);
        if (USE_MARKET_PRICES == 'True' || \common\helpers\Extensions::is_customer_groups_allowed()) {
            $currency_id = (int) \Yii::$app->settings->get('currency_id');
            $customer_groups_id = 0;
            if (\Yii::$app->storage->has('customer_groups_id')) {
                $customer_groups_id = (int) \Yii::$app->storage->get('customer_groups_id');
                if (($ext = \common\helpers\Acl::check_extension_allowed('UserGroups')) && !$ext::group_has_product_price($customer_groups_id)) {
                    $customer_groups_id = 0;
                }
            }
            if ($customer_groups_id === 0) {
                $eq->and_where('sp.specials_new_products_price > 0.0001');
            } else {
                $eq->join_with('prices spp', false, 'INNER JOIN')->and_where(['spp.groups_id' => $customer_groups_id, 'spp.currencies_id' => USE_MARKET_PRICES == 'True' ? $currency_id : 0])->and_where(['<>', 'spp.specials_new_products_price', -1])->and_where('spp.specials_new_products_price>0 or sp.specials_new_products_price>0');
            }
        }
        // doesn't work with caps - have to query all sales;
        //$this->query->andWhere(['exists', $eq]);
        $key = md5($eq->create_command()->raw_sql);
        static $cache = [];
        if (!isset($cache[$key])) {
            $sp = $eq->as_array()->all();
            $pids = [];
            if (!empty($sp) && is_array($sp)) {
                foreach ($sp as $special) {
                    if (in_array($special['products_id'], $pids)) {
                        continue;
                    } elseif ($special['total_qty'] == 0) {
                        $pids[] = $special['products_id'];
                    } else if (!\common\helpers\Specials::check_sold_out($special)) {
                        $pids[] = $special['products_id'];
                    }
                }
            }
            $cache[$key] = $pids;
        }
        if (empty($cache[$key])) {
            $this->query->and_where(0);
        } else {
            $this->query->and_where(['p.products_id' => $cache[$key]]);
        }
    }
    public function add_with_inventory()
    {
        if (!empty($this->params['withInventory']) && $this->params['withInventory'] && \common\helpers\Extensions::is_allowed('Inventory')) {
            $this->query->left_join(['i' => TABLE_INVENTORY], 'p.products_id = i.prid and i.non_existent = 0');
            $this->query->select(new \yii\db\Expression('ifnull(i.products_id, p.products_id) as products_id'));
            /** @var \common\extensions\UserGroupsRestrictions\models\GroupsInventory $groupsInventory $groupsInventory */
            $groups_inventory = \common\helpers\Extensions::get_model('UserGroupsRestrictions', 'GroupsInventory');
            if (!empty($groups_inventory)) {
                $customer_groups_id = (int) \Yii::$app->storage->get('customer_groups_id');
                $this->query->and_where(['or', ['not exists', \common\models\Inventory::find()->where('p.products_id = prid')], ['and', ['exists', \common\models\Inventory::find()->where('p.products_id = prid')], ['exists', $groups_inventory::find()->where('i.products_id = products_id and i.prid = prid')->and_where(['groups_id' => (int) $customer_groups_id])]]]);
            }
        }
    }
    public function add_order_by($params = null)
    {
        // {{
        $skip_add_order_by = false;
        foreach (\common\helpers\Hooks::get_list('products-query/add-order-by') as $filename) {
            $skip_add_order_by = include $filename;
            if ($skip_add_order_by === true) {
                return $this;
            }
        }
        // }}
        if (empty($params)) {
            $params = $this->params['orderBy'];
        }
        if (!empty($params)) {
            //transform string to arrray
            if (!is_array($params) && !empty(trim($params))) {
                $params = explode(',', $params);
                if (is_array($params)) {
                    $ar = [];
                    foreach ($params as $v) {
                        $v = trim($v);
                        $offset = strpos($v, ' ');
                        if ($offset) {
                            $dir = strtolower(trim(substr($v, $offset + 1)));
                            $ar[substr($v, 0, $offset)] = $dir == 'desc' ? SORT_DESC : SORT_ASC;
                        } else {
                            $ar[$v] = SORT_ASC;
                        }
                    }
                    $params = $ar;
                }
            }
            if (!empty($params)) {
                foreach ($params as $field => $direction) {
                    $known_table = false;
                    switch ($field) {
                        case 'products_name':
                            $this->query->join_with('listingName');
                            /**
                             * @var $extModel \common\extensions\PlainProductsDescription\models\PlainProductsNameSearch
                             */
                            $ext_model = \common\helpers\Extensions::get_model('PlainProductsDescription', 'PlainProductsNameSearch');
                            if (!empty($ext_model) && !$this->params['withInventory']) {
                                $known_table = $ext_model::table_name() . '.' . $field;
                            } else {
                                $known_table = \common\models\Products_Description::table_name() . '.' . $field;
                            }
                            break;
                        case 'bestsellers':
                            $known_table = '(p.products_ordered+p.popularity_bestseller)';
                            break;
                        case 'products_popularity':
                            $known_table = '(p.products_popularity+p.popularity_simple)';
                            break;
                        case 'products_model':
                        case 'products_date_added':
                        case 'products_weight':
                            //2do (attributes)
                            $known_table = 'p.' . $field;
                            break;
                        case 'manufacturers_name':
                            $this->query->join_with('manufacturer');
                            $known_table = $field;
                            break;
                        case 'products_price':
                            if (!defined('GROUPS_IS_SHOW_PRICE') || GROUPS_IS_SHOW_PRICE == true) {
                                if (!isset($this->params['filters']['price']) || self::PRESELECT_PRODUCST_PRICE_IDS) {
                                    $this->query->join_with('listingPrice');
                                }
                                //2do if $groups_id>0 - get discount
                                if (DISPLAY_PRICE_WITH_TAX == 'true') {
                                    $tax_rates = \common\models\Tax_Rates::find()->as_array()->all();
                                } else {
                                    $tax_rates = false;
                                }
                                $tax_rate_m = '';
                                if ($tax_rates) {
                                    foreach ($tax_rates as $v) {
                                        $tax_rate_m .= ' WHEN ' . (int) $v['tax_class_id'] . ' THEN 1+' . (float) \common\helpers\Tax::get_tax_rate($v['tax_class_id']) / 100;
                                    }
                                }
                                /**
                                 * @var $ppiModel \common\extensions\ProductPriceIndex\models\ProductPriceIndex
                                 */
                                $ppi_model = \common\helpers\Extensions::get_model('ProductPriceIndex', 'ProductPriceIndex');
                                if (!empty($ppi_model)) {
                                    $known_table = ['products_final_price' => new \yii\db\Expression('round(if(products_special_price_min>0, products_special_price_min, products_price_min) ' . ($tax_rates ? ' * (case ifnull(' . $ppi_model::table_name() . '.products_tax_class_id,0) ' . $tax_rate_m . ' else 1 end)' : '') . ', 2)')];
                                } else {
                                    $known_table = ['products_final_price' => new \yii\db\Expression(
                                        //'if(products_special_price>0, products_special_price, products_price) '
                                        'products_price' . ($tax_rates ? ' * (case products_tax_class_id ' . $tax_rate_m . ' else 1 end)' : '')
                                    )];
                                }
                            }
                            break;
                        case 'products_quantity':
                            // most probably slow ... 2do dependant on per warehoouse availability
                            $this->query->join_with('deliveryTerm products_listing_delivery_term');
                            $known_table = ['sort_by_qty' => new \yii\db\Expression('if(p.products_quantity>0, p.products_quantity, if(p.stock_delivery_terms_id=0,"z",products_listing_delivery_term.sort_order))')];
                            break;
                        case 'rand()':
                            $known_table = 'rand()';
                            break;
                        case 'gso':
                        case 'mark':
                            if ($field == 'mark' && $this->params['featuredTypeId']) {
                                $this->query->add_order_by('{{%featured}}.sort_order');
                            } elseif ($field == 'mark' && $this->params['currentCategory'] && $this->get_current_category_id() > 0) {
                                if ($this->params['limit'] < 1000) {
                                    $use_index = ' USE INDEX (categories_id)';
                                } else {
                                    $use_index = '';
                                }
                                if ($this->params['hasSubcategories']) {
                                    $all_sub_categories = [(int) $this->get_current_category_id()];
                                    \common\helpers\Categories::get_subcategories($all_sub_categories, $all_sub_categories[0]);
                                    $this->query->inner_join('{{%products_to_categories}} ' . $use_index, '({{%products_to_categories}}.products_id=p.products_id) ')->inner_join('{{%categories}} ', '({{%products_to_categories}}.categories_id={{%categories}}.categories_id) ')->distinct()->and_where(['IN', '{{%products_to_categories}}.categories_id', $all_sub_categories])->add_order_by('{{%categories}}.categories_left, {{%products_to_categories}}.sort_order, {{%products_to_categories}}.products_id desc');
                                    //->addSelect('{{%products_to_categories}}.sort_order, {{%products_to_categories}}.products_id ');
                                } else {
                                    $this->query->inner_join('{{%products_to_categories}} ' . $use_index, '({{%products_to_categories}}.products_id=p.products_id) ')->and_where(['{{%products_to_categories}}.categories_id' => $this->get_current_category_id()])->add_order_by('{{%products_to_categories}}.sort_order, {{%products_to_categories}}.products_id desc');
                                    // ->addSelect('{{%products_to_categories}}.sort_order, {{%products_to_categories}}.products_id ');
                                }
                            } else {
                                //$this->query->joinWith('listingGlobalSort', false, 'inner join')
                                $this->query->join_with('listingGlobalSort')->add_order_by('{{%products_global_sort}}.sort_order desc');
                                //->addSelect('{{%products_global_sort}}.sort_order ');
                            }
                            break;
                        default:
                            if (strtoupper(substr(trim($field), 0, 6)) == 'FIELD ') {
                                $this->query->add_order_by([new \yii\db\Expression($field)]);
                            }
                            break;
                    }
                    if ($known_table) {
                        if (is_array($known_table)) {
                            $f = (string) array_shift($known_table);
                        } else {
                            $f = $known_table;
                        }
                        $this->query->add_order_by([$f => $direction]);
                        $this->query->add_select($known_table);
                    }
                    /*else {
                                $this->query->joinWith('listingGlobalSort')
                                    ->addOrderBy('{{%products_global_sort}}.sort_order desc')
                                    ->addSelect('{{%products_global_sort}}.sort_order ');
                    
                                if ($this->params['currentCategory'] && $this->getCurrentCategoryId() > 0) {
                                  $this->query->joinWith('getCategoriesList')
                                    ->andWhere(['{{%products_to_categories}}.categories_id' => $this->getCurrentCategoryId()])
                                    ->addOrderBy('{{%products_to_categories}}.sort_order ')
                                    ->addSelect('{{%products_global_sort}}.sort_order ');
                                }
                    
                                $this->query->addOrderBy('products_date_added desc')
                                    ->addSelect('products_date_added ');
                              }*/
                }
            }
        } else if (isset($this->params['filters']['keywords']) && $this->params['filters']['keywords'] && !empty($this->relevance_order)) {
            //2do relevance
            $this->query->join_with('listingName');
            $this->query->add_order_by(new \yii\db\Expression($this->relevance_order));
        } else {
            if (isset($this->params['currentCategory']) && $this->params['currentCategory'] && $this->get_current_category_id() > 0 && !$this->params['hasSubcategories']) {
                if ($this->params['limit'] < 1000) {
                    $use_index = ' USE INDEX (categories_id)';
                } else {
                    $use_index = '';
                }
                $this->query->inner_join('{{%products_to_categories}} ' . $use_index, '({{%products_to_categories}}.products_id=p.products_id) ')->and_where(['{{%products_to_categories}}.categories_id' => $this->get_current_category_id()])->add_order_by('{{%products_to_categories}}.sort_order, {{%products_to_categories}}.products_id desc')->add_select('{{%products_to_categories}}.sort_order, {{%products_to_categories}}.products_id ');
            } else {
                //$this->query->joinWith('listingGlobalSort', false, 'inner join')
                $this->query->join_with('listingGlobalSort')->add_order_by('{{%products_global_sort}}.sort_order desc')->add_select('{{%products_global_sort}}.sort_order ');
            }
            /*
                                        $this->query->addOrderBy('products_date_added desc')
                                             ->addSelect('products_date_added');*/
        }
        //SB custom - TL??
        //$this->query->addOrderBy('p.products_groups_id');
        //SB custom - TL?? eof
        return $this;
    }
    protected function add_attributes($params = null)
    {
        if (empty($params)) {
            $params = $this->params['filters']['attributes'];
        }
        if (!empty($params)) {
            $at_filters = $params;
            foreach ($at_filters as $o_id => $v_ids) {
                $cnt_q = \common\models\Products_Attributes::find()->alias('pat');
                $condition = ['and', ['pat.options_id' => $o_id]];
                if (!empty($v_ids['values'])) {
                    $condition[] = ['pat.options_values_id' => $v_ids['values']];
                    $cnt_q->and_where(['and', 'p.products_id=pat.products_id', $condition]);
                    $this->query->and_where(['exists', $cnt_q]);
                }
                if (\common\helpers\Extensions::is_allowed('Inventory') && defined('SHOW_OUT_OF_STOCK') && !SHOW_OUT_OF_STOCK) {
                    // Hide Out of Stock products by inventory
                    if (is_null($this->backorder_stock_indication_ids)) {
                        $this->backorder_stock_indication_ids = \common\models\Products_Stock_Indication::find()->where(['allow_out_of_stock_checkout' => 1, 'allow_out_of_stock_add_to_cart' => 1])->select('stock_indication_id')->as_array()->column();
                    }
                    $q = 'stock_indication_id = 0 and products_quantity > 0';
                    if (!is_null($this->backorder_stock_indication_ids)) {
                        $q = ['or', ['stock_indication_id' => $this->backorder_stock_indication_ids], $q];
                    }
                    $regexp = '\\\\{' . intval($o_id) . '\\\\}';
                    if (!empty($v_ids['values'])) {
                        if (is_array($v_ids['values'])) {
                            $regexp .= '(' . implode('|', array_map('intval', $v_ids['values'])) . ')';
                        } else {
                            $regexp .= intval($v_ids['values']);
                        }
                        $regexp .= '(\\\\{|$)';
                    }
                    $this->query->and_where(['exists', \common\models\Inventory::find()->where('p.products_id = prid')->and_where(new \yii\db\Expression(sprintf("products_id REGEXP '%s'", $regexp)))->and_where($q)]);
                }
            }
            //echo $this->query->createCommand()->rawSql . ' addProperties <br>';
        }
    }
    protected function add_extra($params = null)
    {
        if (empty($params)) {
            $params = $this->params['filters']['extra'];
        }
        if (is_array($params)) {
            foreach ($params as $p_id => $p_v) {
                if (!isset($this->params['filters']['properties'][$p_id])) {
                    continue;
                }
                $cnt_q = \common\models\Properties2Propducts::find()->alias('pr2p');
                $condition = ['and', ['pr2p.properties_id' => $p_id]];
                if (isset($p_v['from']) && is_array($p_v['from'])) {
                    foreach ($p_v['from'] as $v_id => $from) {
                        if ($from > 0 && !empty($p_v['field'])) {
                            $condition[] = ['and', ['pr2p.values_id' => $v_id], ['>=', $p_v['field'], $from]];
                        }
                    }
                }
                if (isset($p_v['to']) && is_array($p_v['to'])) {
                    foreach ($p_v['to'] as $v_id => $to) {
                        if ($to > 0 && !empty($p_v['field'])) {
                            $condition[] = ['and', ['pr2p.values_id' => $v_id], ['<=', $p_v['field'], $to]];
                        }
                    }
                }
                $cnt_q->and_where(['and', 'p.products_id=pr2p.products_id', $condition]);
                $this->query->and_where(['exists', $cnt_q]);
            }
        }
    }
    protected function add_properties($params = null)
    {
        if (empty($params)) {
            $params = $this->params['filters']['properties'];
        }
        if (!empty($params)) {
            $pr_filters = $params;
            foreach ($pr_filters as $o_id => $v_ids) {
                $val_ok = $val_required = false;
                $cnt_q = \common\models\Properties2Propducts::find()->alias('pr2p');
                if (!empty($v_ids['field']) && in_array($v_ids['field'], ['values_number', 'values_number_upto', 'values_alt'])) {
                    // for range the values table is required
                    $val_required = true;
                }
                $condition = ['and', ['pr2p.properties_id' => $o_id]];
                if (!empty($v_ids['from']) && abs($v_ids['from']) > 0 && !empty($v_ids['field'])) {
                    $condition[] = ['>=', $v_ids['field'], $v_ids['from']];
                    $val_ok = true;
                }
                if (!empty($v_ids['to']) && abs($v_ids['to']) > 0 && !empty($v_ids['field'])) {
                    $condition[] = ['<=', $v_ids['field'], $v_ids['to']];
                    $val_ok = true;
                }
                if (!empty($v_ids['values']) && !empty($v_ids['field'])) {
                    $condition[] = ['pr2p.' . $v_ids['field'] => $v_ids['values']];
                    $val_ok = true;
                }
                if ($val_ok) {
                    if ($val_required) {
                        $cnt_q->join_with(['propertiesValue'], false);
                    }
                    $cnt_q->and_where(['and', 'p.products_id=pr2p.products_id', $condition]);
                    $this->query->and_where(['exists', $cnt_q]);
                }
            }
            //echo $this->query->createCommand()->rawSql . ' addProperties <br>';
        }
    }
    public function add_price($params = null)
    {
        if (defined('GROUPS_IS_SHOW_PRICE') && GROUPS_IS_SHOW_PRICE == false) {
            return $this;
        }
        if (empty($params)) {
            $params = $this->params['filters']['price'];
        }
        if (Array_Helper::get_value($params, 'from') > 0 || Array_Helper::get_value($params, 'to') > 0) {
            if (self::PRESELECT_PRODUCST_PRICE_IDS) {
                $params = $this->params;
                unset($params['filters']);
                unset($params['orderBy']);
                $params['filters']['price'] = $this->params['filters']['price'];
            }
            self::cleanup_params($params);
            $pk = serialize($params);
            // already have products_id list in cache for these parameters
            if (self::PRESELECT_PRODUCST_PRICE_IDS && is_array($this->list_product_ids[$pk] ?? null)) {
                $this->query->and_where(['p.products_id' => $this->list_product_ids[$pk]]);
                return $this;
            }
            if (self::PRESELECT_PRODUCST_PRICE_IDS) {
                // need to save ids in cache - clone query params and build/execute/cache result of new query
                unset($params['filters']);
                $q = clone $this;
                $q = $q->build_query($params);
                $q->query->join_with('listingPrice', false, ' inner join ');
                $q = $q->get_query();
                $params = $this->params['filters']['price'];
            } else {
                // join price tables to currenct query
                $q = $this->get_query();
            }
            /* @var $q \common\models\queries\ProductsQuery  */
            if (USE_MARKET_PRICES == 'True') {
                $currencies = \Yii::$container->get('currencies');
                $currency = \Yii::$app->settings->get('currency');
                $currencies_id = (int) $currencies->currencies[$currency]['id'];
            } else {
                $currencies_id = 0;
            }
            $groups_id = (int) \Yii::$app->storage->get('customer_groups_id');
            /** @var $ppiModel \common\extensions\ProductPriceIndex\models\ProductPriceIndex */
            $ppi_model = \common\helpers\Extensions::get_model('ProductPriceIndex', 'ProductPriceIndex');
            if (!empty($ppi_model)) {
                $tmp = [];
                if (DISPLAY_PRICE_WITH_TAX == 'true') {
                    $tax_rates = $ppi_model::find()->and_where(['currencies_id' => $currencies_id, 'groups_id' => $groups_id, 'products_status' => 1])->select('products_tax_class_id')->distinct()->as_array()->column();
                    if ($tax_rates) {
                        foreach ($tax_rates as $v) {
                            $r = 1 + (float) \common\helpers\Tax::get_tax_rate($v) / 100;
                            if (!isset($tmp[$r])) {
                                $tmp["{$r}"] = [];
                            }
                            $tmp["{$r}"][] = $v;
                        }
                    }
                }
                if (count($tmp) == 0) {
                    $tmp['0'][] = 0;
                }
                if (count($tmp) > 1) {
                    $cond = [];
                    foreach ($tmp as $rate => $ids) {
                        $each = [];
                        if ($params['from'] > 0) {
                            $each[] = ['and', ['>=', 'if(products_special_price_min>0, products_special_price_min, products_price_min)', $params['from'] / ($rate > 0 ? $rate : 1)], ['=', $ppi_model::table_name() . '.products_tax_class_id', $ids]];
                        }
                        if ($params['to'] > 0) {
                            $each[] = ['and', ['<=', 'if(products_special_price_min>0, products_special_price_min, products_price_min)', $params['to'] / ($rate > 0 ? $rate : 1)], ['=', $ppi_model::table_name() . '.products_tax_class_id', $ids]];
                        }
                        if (count($each) == 2) {
                            $cond[] = ['and', $each[0], $each[1]];
                            //            $cond[] = ['and', $each];
                        } else {
                            $cond[] = $each[0];
                        }
                    }
                    array_unshift($cond, 'or');
                    //echo "#### <PRE>" .print_r($cond, 1) ."</PRE>"; die;
                    $q->and_where($cond);
                } else {
                    $rate = array_keys($tmp);
                    if ($params['from'] > 0) {
                        $q->and_where(['>=', 'if(products_special_price_min>0, products_special_price_min, products_price_min)', $params['from'] / ($rate[0] > 0 ? $rate['0'] : 1)]);
                    }
                    if ($params['to'] > 0) {
                        $q->and_where(['<=', 'if(products_special_price_min>0, products_special_price_min, products_price_min)', $params['to'] / ($rate[0] > 0 ? $rate['0'] : 1)]);
                    }
                }
                //echo "<BR> price" . $q->createCommand()->rawSql;
            } else {
                //no flat price table - joined product_prices table (only, ignore attributes and inventory)
                //2do if $groups_id>0 - get discount
                $tax_rates = \common\models\Products::find()->and_where(['products_status' => 1])->select('products_tax_class_id')->distinct()->as_array()->column();
                $tmp = [];
                if (DISPLAY_PRICE_WITH_TAX == 'true' && $tax_rates) {
                    foreach ($tax_rates as $v) {
                        $r = 1 + (float) \common\helpers\Tax::get_tax_rate($v) / 100;
                        if (!isset($tmp[$r])) {
                            $tmp["{$r}"] = [];
                        }
                        $tmp["{$r}"][] = $v;
                    }
                }
                if (count($tmp) > 1) {
                    $cond = [];
                    foreach ($tmp as $rate => $ids) {
                        $each = [];
                        if ($params['from'] > 0) {
                            $each[] = ['and', ['>=', new \yii\db\Expression('if(products_group_price>0, products_group_price,products_price)'), $params['from'] / ($rate > 0 ? $rate : 1)], ['=', 'p.products_tax_class_id', $ids]];
                        }
                        if ($params['to'] > 0) {
                            $each[] = ['and', ['<=', new \yii\db\Expression('if(products_group_price>0, products_group_price,products_price)'), $params['to'] / ($rate > 0 ? $rate : 1)], ['=', 'p.products_tax_class_id', $ids]];
                        }
                        if (count($each) == 2) {
                            $cond[] = ['and', $each[0], $each[1]];
                        } else {
                            $cond[] = $each[0];
                        }
                    }
                    array_unshift($cond, 'or');
                    $q->and_where($cond);
                } elseif (count($tmp) > 0) {
                    $rate = array_keys($tmp);
                    if ($params['from'] > 0) {
                        $q->and_where(['>=', new \yii\db\Expression('if(products_group_price>0, products_group_price,products_price)'), $params['from'] / ($rate[0] > 0 ? $rate['0'] : 1)]);
                    }
                    if ($params['to'] > 0) {
                        $q->and_where(['<=', new \yii\db\Expression('if(products_group_price>0, products_group_price,products_price)'), $params['to'] / ($rate[0] > 0 ? $rate['0'] : 1)]);
                    }
                }
            }
            if (self::PRESELECT_PRODUCST_PRICE_IDS) {
                //echo " <BR> $pk <br>" . $q->offset(null)->limit(null)->orderBy(null)->select("p.products_id")->createCommand()->rawSql;
                //temp kostyl for php 72
                $s = \Yii::$app->db->create_command($q->offset(null)->limit(null)->order_by(null)->select('p.products_id')->create_command()->raw_sql)->query_column();
                //if ($q->offset(null)->limit(null)->orderBy(null)->select("p.products_id")->count() < 100000) {//
                if (count($s) < 100000) {
                    //
                    //$this->listProductIds[$pk] = $q->offset(null)->limit(null)->orderBy(null)->select("p.products_id")->asArray()->column();
                    $this->list_product_ids[$pk] = $s;
                    $this->query->and_where(['p.products_id' => $this->list_product_ids[$pk]]);
                    //echo " <BR><BR>" . $this->query->createCommand()->rawSql;
                    //echo " count " . count($this->listProductIds[$pk]);
                    //unset($this->listProductIds[$pk]);
                } else {
                    $this->query->and_where(['p.products_id' => $q->offset(null)->limit(null)->order_by(null)->select('p.products_id')]);
                }
            }
        }
        return $this;
    }
    public function add_keywords($params = null)
    {
        if (empty($params)) {
            $params = $this->params['filters']['keywords'];
        }
        if (!empty($params)) {
            //echo 'bfore <br>' . $this->query->createCommand()->rawSql . '<BR>';
            if (isset($this->params['filters']['keywords'])) {
                $this->params['filters']['keywords'] = (string) $this->params['filters']['keywords'];
            }
            // {{
            $skip_add_keywords = false;
            foreach (\common\helpers\Hooks::get_list('products-query/add-keywords') as $filename) {
                $skip_add_keywords = include $filename;
                if ($skip_add_keywords === true) {
                    return $this;
                }
            }
            // }}
            if (self::PRESELECT_PRODUCST_SEARCH_IDS && !$this->params['withInventory']) {
                $params = $this->params;
                unset($params['filters']);
                unset($params['orderBy']);
                $params['filters']['keywords'] = $this->params['filters']['keywords'];
            }
            self::cleanup_params($params);
            $pk = serialize($params);
            if (self::PRESELECT_PRODUCST_SEARCH_IDS && !$this->params['withInventory'] && is_array($this->list_product_ids[$pk] ?? null)) {
                $this->query->and_where(['p.products_id' => $this->list_product_ids[$pk]]);
                return $this;
            }
            $search_builder = new \common\components\Search_Builder('simple');
            $search_builder->parse_keywords($this->params['filters']['keywords']);
            $kws = $search_builder->get_parsed_keywords();
            /// plain
            /* @var $ext \common\extensions\PlainProductsDescription\PlainProductsDescription */
            $ext = \common\helpers\Acl::check_extension_allowed('PlainProductsDescription', 'allowed');
            if ($ext && $ext::is_enabled() && !$this->params['withInventory']) {
                if (defined('MSEARCH_ENABLE') && strtolower(MSEARCH_ENABLE) == 'fulltext') {
                    $kws = \common\extensions\Plain_Products_Description\Plain_Products_Description::validate_keywords($kws, true);
                } else {
                    $kws = \common\extensions\Plain_Products_Description\Plain_Products_Description::validate_keywords($kws);
                }
                if (!is_array($kws)) {
                    // all keywords too short or common
                    return;
                }
            }
            if (self::PRESELECT_PRODUCST_SEARCH_IDS && !$this->params['withInventory']) {
                unset($params['filters']);
                $q = clone $this;
                $q = $q->build_query($params);
                $q->remove_group_product_groups();
                $q->query->join_with('listingName', false);
                $q = $q->get_query();
            } else {
                $q = $this->get_query();
                if ($this->params['withInventory']) {
                    $languages_id = \Yii::$app->settings->get('languages_id');
                    $platform_id = \common\classes\platform::current_id();
                    $platform_id = (new \common\classes\platform_settings($platform_id))->get_platform_to_description();
                    $q->inner_join(\common\models\Products_Description::table_name(), new \yii\db\Expression('p.products_id = ' . \common\models\Products_Description::table_name() . '.products_id'))->and_where([\common\models\Products_Description::table_name() . '.language_id' => (int) $languages_id, \common\models\Products_Description::table_name() . '.platform_id' => $platform_id]);
                } else {
                    $q->join_with('listingName', false);
                }
            }
            $params = $kws;
            /* @var $q \common\models\queries\ProductsQuery  */
            /// plain
            if ($ext && $ext::is_enabled() && !$this->params['withInventory']) {
                if (defined('MSEARCH_ENABLE') && strtolower(MSEARCH_ENABLE) == 'fulltext') {
                    if (is_array($params)) {
                        $q->and_where('match( {{%plain_products_name_search}}.search_details ) against(:kw)', [':kw' => implode(' ', $params)]);
                        $this->relevance_order = \Yii::$app->db->create_command('match( {{%plain_products_name_search}}.search_details ) against(:kw)', [':kw' => implode(' ', $params)])->raw_sql;
                    }
                } else {
                    //always like by "search" field
                    $params = \common\extensions\Plain_Products_Description\Plain_Products_Description::validate_keywords($params);
                    if (is_array($params)) {
                        $f = ['like', '{{%plain_products_name_search}}.search_details', $params];
                        //highest/extra relevance by name (all keywords in the name)
                        $relevance_f = ['like', '{{%plain_products_name_search}}.products_name', $params];
                        $tmp = $tmp_f = [];
                        foreach ($params as $param) {
                            $tmp[] = \Yii::$app->db->create_command('-100/if(LOCATE( :kw , ' . $f[1] . ')>0, LOCATE( :kw , ' . $f[1] . '), -100)', [':kw' => $param])->raw_sql;
                            $tmp_f[] = \Yii::$app->db->create_command($relevance_f[1] . ' ' . $relevance_f[0] . ' :kw', [':kw' => '%' . $param . '%'])->raw_sql;
                        }
                        if (defined('MSEARCH_ENABLE') && (strtolower(MSEARCH_ENABLE) == 'true' || strtolower(MSEARCH_ENABLE) == 'soundex')) {
                            //+ or like by soundex field
                            $fs = ['like', '{{%plain_products_name_search}}.search_soundex', $params];
                            $tmps = \common\extensions\Plain_Products_Description\Plain_Products_Description::get_soundex(implode(' ', $params), false);
                            if (is_array($tmps)) {
                                $tmps = array_map(function ($el) {
                                    return ',' . $el . ',';
                                }, $tmps);
                                $f = ['or', $f, ['like', '{{%plain_products_name_search}}.search_soundex', $tmps]];
                                foreach ($tmps as $param) {
                                    $tmp[] = \Yii::$app->db->create_command('-10/if(LOCATE( :kw , ' . $fs[1] . ')>0, LOCATE( :kw , ' . $fs[1] . '), -10)', [':kw' => $param])->raw_sql;
                                }
                            }
                        }
                        $this->relevance_order = '(' . implode(' and ', $tmp_f) . ') desc, (' . implode(' + ', $tmp) . ')';
                        foreach (\common\helpers\Hooks::get_list('products-search/alter-force-search') as $filename) {
                            include $filename;
                        }
                        $q->and_where($f);
                    }
                }
            } else if (defined('MSEARCH_ENABLE') && strtolower(MSEARCH_ENABLE) == 'fulltext') {
                $params = explode(' ', reset($params));
                foreach ($params as $key => $param) {
                    $q->and_where("match( p.products_model ) against(:kw{$key}) or match( {{%products_description}}.products_name ) against(:kw{$key}) or match( {{%products_description}}.products_description) against(:kw{$key})", [":kw{$key}" => $param]);
                }
            } else {
                //like by model, name and description in the description table
                if ($this->params['withInventory']) {
                    $f = ['like', 'concat(ifnull(i.products_model, p.products_model), {{%products_description}}.products_name, {{%products_description}}.products_description)', $params];
                } else {
                    $f = ['like', 'concat(p.products_model, ifnull({{%products_description}}.products_name, ""), ifnull({{%products_description}}.products_description, ""))', $params];
                }
                $q->and_where($f);
                if (is_array($params)) {
                    $f = ['like', '{{%products_description}}.products_description', $params];
                    $relevance_f = ['like', '{{%products_description}}.products_name', $params];
                    $tmp = $tmp_f = [];
                    foreach ($params as $param) {
                        $tmp[] = \Yii::$app->db->create_command('LOCATE( :kw , ' . $f[1] . ')', [':kw' => $param])->raw_sql;
                        $tmp_f[] = \Yii::$app->db->create_command($relevance_f[1] . ' ' . $relevance_f[0] . ' :kw', [':kw' => '%' . $param . '%'])->raw_sql;
                    }
                    $this->relevance_order = '(' . implode(' and ', $tmp_f) . ') desc, (' . implode(' + ', $tmp) . ')';
                }
            }
            //echo " <BR><BR>" . $q->createCommand()->rawSql . ' ' . ($this->relevanceOrder??null);
            if (self::PRESELECT_PRODUCST_SEARCH_IDS && !$this->params['withInventory']) {
                //echo " <BR><BR> $pk <br>" . $q->offset(null)->limit(null)->orderBy(null)->select("p.products_id")->createCommand()->rawSql;
                if ($q->offset(null)->limit(null)->order_by(null)->select('p.products_id')->count() < 100000) {
                    $this->list_product_ids[$pk] = $q->offset(null)->limit(null)->order_by(null)->select('p.products_id')->as_array()->column();
                    if (!empty($this->list_product_ids[$pk])) {
                        $this->query->and_where(['p.products_id' => $this->list_product_ids[$pk]]);
                    } else {
                        $this->query->and_where('0=1');
                    }
                    //echo " <BR><BR>" . $this->query->createCommand()->rawSql;
                    //echo " count " . count($this->listProductIds[$pk]);
                    //unset($this->listProductIds[$pk]);
                } else {
                    $this->query->and_where(['p.products_id' => $q->offset(null)->limit(null)->order_by(null)->select('p.products_id')]);
                }
                return $this;
            }
        }
    }
    public function add_limited_products($params = null)
    {
        $limit_ids = [];
        if (!empty($params) && is_array($params)) {
            $limit_ids = $params;
        } else if (isset($this->params['filters']['limitedProducts']) && is_array($this->params['filters']['limitedProducts'])) {
            $limit_ids = $this->params['filters']['limitedProducts'];
        }
        if (is_array($limit_ids) && \count($limit_ids) > 0) {
            $this->query->and_where(['IN', 'p.products_id', $limit_ids]);
        }
    }
    public function add_filters($params = null)
    {
        if (empty($params)) {
            $params = $this->params['filters'];
        }
        if (!empty($params)) {
            foreach ($params as $k => $v) {
                $method = 'add' . ucfirst($k);
                if ($v !== false && method_exists($this, 'add' . ucfirst($k))) {
                    $this->{$method}($v);
                } else {
                    \Yii::warning('AddFilter not exists ' . 'add' . ucfirst($k));
                }
            }
        }
    }
    public function add_current_platform($params = null)
    {
        if (empty($params)) {
            $params = $this->params['currentPlatform'];
        }
        if ($params) {
            $this->query->and_where(['exists', (new Query())->from(['plp' => TABLE_PLATFORMS_PRODUCTS])->where('p.products_id = plp.products_id')->and_where(['plp.platform_id' => platform::current_id()])]);
        }
    }
    /**
     * Sub-query: selected categories or incl. their sub-categories (according theme settings)
     * @param array|int $current_category_id
     * @return QueryBuilder
     */
    private function children_categories_query($current_category_id = [])
    {
        if (!is_array($current_category_id)) {
            $current_category_id = [$current_category_id];
        }
        if ($this->params['countAllSubcategories']) {
            $current_category_id = reset($current_category_id);
            $this->get_query()->select(['products_count' => new \yii\db\Expression('COUNT(p.products_id)'), 'c1.categories_id'])->join('inner join', \common\models\Products2Categories::table_name() . ' p2c', 'p2c.products_id=p.products_id')->join('inner join', \common\models\Categories::table_name() . ' c1', 'c1.categories_id=p2c.categories_id and c1.categories_status=1')->group_by('c1.categories_id');
            if ($current_category_id > 0) {
                $this->get_query()->join('inner join', \common\models\Categories::table_name() . ' cfrom', "cfrom.categories_id='" . (int) $current_category_id . "' AND c1.categories_left>=cfrom.categories_left and c1.categories_right<=cfrom.categories_right");
                //->andWhere(['AND', ['>=', 'c1.categories_left','cfrom.categories_left'],['<=', 'c1.categories_right', 'cfrom.categories_right']]);
            }
            return $this;
        }
        sort($current_category_id);
        $pk = implode(',', $current_category_id);
        if (self::PRESELECT_PRODUCST_CATEGORIES_IDS && isset($this->list_product_ids[$pk]) && is_array($this->list_product_ids[$pk])) {
            $this->query->and_where(['p.products_id' => $this->list_product_ids[$pk]]);
            return $this;
        }
        $p2c_query = (new Query())->from(['p2c_cur' => TABLE_PRODUCTS_TO_CATEGORIES])->inner_join(['c1' => TABLE_CATEGORIES], 'c1.categories_id=p2c_cur.categories_id and c1.categories_status=1');
        if (\frontend\design\Info::theme_setting('show_products_from_subcategories')) {
            $p2c_query->inner_join(['c2' => TABLE_CATEGORIES], 'c1.categories_left>=c2.categories_left and c1.categories_right<=c2.categories_right')->and_where(['c2.categories_id' => $current_category_id])->and_where(['c2.categories_status' => 1]);
        } else {
            $p2c_query->and_where(['c1.categories_id' => $current_category_id]);
            $p2c_query->and_where(['c1.categories_status' => 1]);
        }
        if (!$this->params['anyExists'] && self::PRESELECT_PRODUCST_CATEGORIES_IDS && $this->list_product_ids[$pk] = $p2c_query->select('p2c_cur.products_id')->distinct()->count() < 100000) {
            $this->list_product_ids[$pk] = $p2c_query->select('p2c_cur.products_id')->distinct()->column();
            $this->query->and_where(['p.products_id' => $this->list_product_ids[$pk]]);
        } else {
            $p2c_query->and_where('p.products_id = p2c_cur.products_id');
            $this->query->and_where(['exists', $p2c_query]);
        }
        return $this;
    }
    public function add_current_category($params = null)
    {
        if (empty($params)) {
            $params = $this->params['currentCategory'];
        }
        if ($params && $this->get_current_category_id() > 0) {
            $this->children_categories_query($this->get_current_category_id());
        }
    }
    public function add_categories($categories_ids = [])
    {
        if (is_array($categories_ids) && count($categories_ids) > 0) {
            $categories_ids = array_map('intval', $categories_ids);
            $this->children_categories_query($categories_ids);
        }
    }
    public function add_manufacturers($manufacturers_ids = [])
    {
        if (!empty($manufacturers_ids)) {
            if (!is_array($manufacturers_ids)) {
                $manufacturers_ids = [$manufacturers_ids];
            }
            $manufacturers_ids = array_map('intval', $manufacturers_ids);
            $this->query->and_where(['in', 'p.manufacturers_id', $manufacturers_ids]);
        }
    }
    public function add_page($page = null)
    {
        if (empty($page)) {
            $page = $this->params['page'];
        }
        switch ($page) {
            case 'catalog/all-products':
            default:
                break;
            case 'catalog/sales':
            case 'sales':
                if (!empty($this->params['specialsTypeId'])) {
                    if (is_array($this->params['specialsTypeId'])) {
                        $type_id = array_map('intval', $this->params['specialsTypeId']);
                    } else {
                        $type_id = intval($this->params['specialsTypeId']);
                    }
                } else {
                    $type_id = 0;
                }
                $this->sales_restriction($type_id);
                break;
            /*VL2do */
            case 'catalog/featured-products':
            case 'featured-products':
            case 'featured':
                /*2do */
                if (tep_session_is_registered('affiliate_ref') && (isset($_SESSION['affiliate_ref']) ? (int) $_SESSION['affiliate_ref'] : 0) > 0) {
                    $ids = [(int) $_SESSION['affiliate_ref'], 0];
                } else {
                    $ids = 0;
                }
                if (!empty($this->params['featuredTypeId'])) {
                    if (is_array($this->params['featuredTypeId'])) {
                        $type_id = array_map('intval', $this->params['featuredTypeId']);
                    } else {
                        $type_id = intval($this->params['featuredTypeId']);
                    }
                } else {
                    $type_id = 0;
                }
                $this->query->inner_join_with('productsFeatured featured', false)->and_where(['featured.status' => 1, 'featured.affiliate_id' => $ids, 'featured.featured_type_id' => $type_id]);
                //          ->andWhere(['exists', \common\models\Featured::find()
                //                                            ->alias('feat_'.(int)$typeId)
                //                                            ->where('p.products_id = feat_'.(int)$typeId.'.products_id')
                //                                            ->andWhere([
                //                                                    'status' => 1,
                //                                                    'affiliate_id' => $ids,
                //                                                    'featured_type_id' => $typeId
                //                                                  ])
                //                                            ->joinWith('productsFeatured featured', false)
                //            ]);
                break;
        }
    }
    /**
     * @return ActiveQuery
     */
    public function get_query()
    {
        return $this->query;
    }
    public function get_params()
    {
        return $this->params;
    }
    /**
     *
     * @param type $db
     * @return array [0 => pid1, 1 => pid2, ...]
     */
    public function all_ids($db = null)
    {
        $r = $this->get_query()->column($db);
        return $r;
    }
    /**
     *
     * @param type $db
     * @return array [0 => [products_id => pid1], ...]
     */
    public function all($db = null)
    {
        $r = $this->get_query()->all($db);
        return $r;
    }
    /**
     *
     * @param string $q
     * @param DBConnection $db nice to have - now could be problem
     * @return int
     */
    public function count($q = '*', $db = null)
    {
        $key = $q;
        if (empty($this->count[$key])) {
            $this->count[$key] = $this->get_query()->count($q, $db);
        }
        return $this->count[$key];
    }
    /**
     * remove keys with empty value from params array
     * @param type $params
     */
    protected static function cleanup_params(&$params)
    {
        if (is_array($params)) {
            foreach ($params as $key => $value) {
                if (is_array($value)) {
                    self::cleanup_params($params[$key]);
                }
            }
            $params = array_filter($params);
        }
    }
    /**
     *
     */
    private function filter_products_ids($params = null)
    {
        if (!$params) {
            $params = $this->params;
        }
        self::cleanup_params($params);
        $pk = serialize($params);
        if (self::PRESELECT_PRODUCST_IDS && is_array($this->list_product_ids[$pk] ?? null)) {
            return $this->list_product_ids[$pk];
        }
        $r = $this->build_query($params)->get_query()->offset(null)->limit(null)->order_by(null)->select('p.products_id');
        if (\frontend\design\Info::theme_setting('group_product_by_product_group')) {
            $r->group_by(null);
        }
        if (!self::PRESELECT_PRODUCST_IDS) {
            return $r;
        } else if ($r->count() > 100000) {
            return $r;
        } else {
            $this->list_product_ids[$pk] = $r->column();
            return $this->list_product_ids[$pk];
        }
    }
    /////////   FILTERS /////////
    /**
     * filter product on listing: Attributes (called from getFiltersArray)
     * @param array $setting
     * @return array
     */
    protected function get_attribute_filters_array($setting)
    {
        $name = $setting['get'];
        $ids = $setting['ids'];
        $params = $this->params;
        unset($params['limit']);
        unset($params['offset']);
        unset($params['orderBy']);
        if (isset($params['filters']['attributes'])) {
            $attr_filters = $params['filters']['attributes'];
            //unset($params['filters']['attributes']);
        } else {
            $attr_filters = [];
        }
        $q = \common\models\Products_Attributes::find()->alias('pa')->join_with(['productsOptions', 'productsOptionsValues'], false)->and_where(\common\models\Products_Options::table_name() . '.display_filter=1')->and_where(['pa.options_id' => $ids])->select('pa.options_id, pa.options_values_id, products_options_name, products_options_values_name')->add_select('{{%products_options}}.products_options_sort_order, products_options_values_sort_order')->add_select(['count' => new \yii\db\Expression('count(distinct pa.products_id)')])->order_by('{{%products_options}}.products_options_sort_order, products_options_name, products_options_values_sort_order, products_options_values_name')->group_by('pa.options_values_id, products_options_values_name, products_options_name, products_options_values_sort_order')->index_by(function ($row) {
            return $row['options_id'] . '_' . $row['options_values_id'];
        });
        if (count($attr_filters) > 0) {
            $cnt_q = clone $q;
        }
        $q->and_where(['pa.products_id' => $this->filter_products_ids($params)]);
        //echo $q->createCommand()->rawSql . '<br>';
        $r = $q->as_array()->all();
        unset($q);
        //echo "#### <PRE>" .print_r($r, 1) ."</PRE>";
        if (count($attr_filters) > 0) {
            //update q-ty
            foreach ($attr_filters as $option_id => $value_ids) {
                $other = $attr_filters;
                unset($other[$option_id]);
                if (is_array($other)) {
                    $q = clone $cnt_q;
                    $params['filters']['attributes'] = $other;
                    $q->and_where(['pa.products_id' => $this->filter_products_ids($params), 'pa.options_id' => $option_id]);
                    //echo "<br>$optionId sub " .  $q->createCommand()->rawSql . '<br><br>';
                    $r_q = $q->as_array()->all();
                    if ($r_q && is_array($r_q)) {
                        foreach ($r_q as $key => $value) {
                            if (isset($r[$key])) {
                                $r[$key]['count'] = $value['count'];
                            }
                        }
                    }
                    unset($r_q);
                }
            }
        }
        $ret = [];
        foreach ($r as $v) {
            if (!isset($ret[$v['options_id']])) {
                $ret[$v['options_id']] = ['title' => $v['products_options_name'], 'name' => $name . $v['options_id'], 'type' => defined('ATTRIBUTES_FILTER_DISPLAY_MODE') && in_array(ATTRIBUTES_FILTER_DISPLAY_MODE, self::$allowed_filter_types) ? ATTRIBUTES_FILTER_DISPLAY_MODE : 'boxes', 'params' => isset($attr_filters[$v['options_id']]['values']) ? $attr_filters[$v['options_id']]['values'] : []];
            }
            $ret[$v['options_id']]['values'][$v['options_values_id']] = ['id' => $v['options_values_id'], 'text' => $v['products_options_values_name'], 'count' => $v['count'], 'selected' => is_array($attr_filters[$v['options_id']]['values'] ?? null) ? in_array($v['options_values_id'], $attr_filters[$v['options_id']]['values']) : false];
        }
        unset($r);
        $s_order = array_flip($ids);
        $ret_sorted = [];
        foreach ($ret as $k => $v) {
            if (is_array($v['values'])) {
                //((!defined('DISPLAY_ONE_VALUE_FILTER') || DISPLAY_ONE_VALUE_FILTER == 'True' || count($values_array) > 1 || $any_selected) && count($values_array) > 0 && $products_count > 0) {
                uasort($v['values'], ['self', 'cmpFilterValues']);
                //          if ((!defined('DISPLAY_ONE_VALUE_FILTER') || DISPLAY_ONE_VALUE_FILTER == 'True' || count($v['values']) > 1 || !empty($v['params'])) ) {
                if (self::is_display_one_value_filter() || count($v['values']) > 1 || !empty($v['params'])) {
                    $ret_sorted[$s_order[$k]] = $v;
                }
            }
        }
        return $ret_sorted;
    }
    /**
     *
     * @param type $setting
     * @return array indexed by sort order
     */
    protected function get_property_filters_array($setting)
    {
        $name = $setting['get'];
        $ids = $setting['ids'];
        if (empty($ids)) {
            return [];
        }
        $params = $this->params;
        //echo "<PRE style='position:absolute;left:65%;top:0;z-index:100; width:35%'>" . __FILE__ .":". __LINE__ . print_r($params, 1) . "</PRE>";
        unset($params['limit']);
        unset($params['offset']);
        unset($params['orderBy']);
        if (!empty($params['filters']['properties'])) {
            $pr_filters = $params['filters']['properties'];
            //unset($params['filters']['properties']);
        } else {
            $pr_filters = [];
        }
        $q = \common\models\Properties2Propducts::find()->alias('pp')->join_with(['properties', 'propertiesDescription', 'propertiesValue'], false)->and_where('{{%properties}}.display_filter=1')->and_where(['pp.properties_id' => $ids])->select('pp.properties_id, pp.values_id, min(pp.extra_value) as extra_value_min, max(pp.extra_value) as extra_value_max, display_filter_as, range_select, extra_values, properties_type, properties_units_title, decimals, pp.values_flag, properties_description, properties_color, properties_image, values_number, values_number_upto, values_color, filter_by_property, filter_steps')->add_select(['properties_name' => new \yii\db\Expression('if(length(properties_name_alt) > 0, properties_name_alt, properties_name)')])->add_select(['filter_values_text' => new \yii\db\Expression('values_text')])->add_select(['values_text' => new \yii\db\Expression('if(length(values_alt) > 0, values_alt, values_text)')])->add_select('{{%properties}}.sort_order, properties_values.sort_order')->add_select(['count' => new \yii\db\Expression('count(pp.products_id)')])->group_by('pp.properties_id, pp.values_id, properties_type, properties_units_title, decimals, pp.values_flag, properties_description, properties_color, properties_image, values_number, values_number_upto, values_color')->add_group_by(['properties_name' => new \yii\db\Expression('if(length(properties_name_alt) > 0, properties_name_alt, properties_name)')])->add_group_by('{{%properties}}.sort_order, properties_values.sort_order')->add_group_by(['values_text' => new \yii\db\Expression('if(length(values_alt) > 0, values_alt, values_text)')])->add_group_by(['filter_values_text' => new \yii\db\Expression('values_text')])->index_by(function ($row) {
            return $row['properties_id'] . '_' . ($row['values_id'] > 0 ? $row['values_id'] : $row['values_flag']);
        });
        if (count($pr_filters) > 0) {
            $cnt_q = clone $q;
        }
        $q->and_where(['pp.products_id' => $this->filter_products_ids($params)]);
        //echo $q->createCommand()->rawSql . '<br>';
        $r = $q->as_array()->all();
        unset($q);
        if (count($pr_filters) > 0) {
            //update q-ty
            foreach ($pr_filters as $option_id => $value_ids) {
                $other = $pr_filters;
                unset($other[$option_id]);
                if (is_array($other) && !empty($pr_filters[$option_id]['values'])) {
                    $q = clone $cnt_q;
                    $params['filters']['properties'] = $other;
                    $q->and_where(['pp.products_id' => $this->filter_products_ids($params)])->and_where(['pp.properties_id' => $option_id]);
                    //echo $q->createCommand()->rawSql . '<BR><BR>' . $optionId . "<BR>";
                    $rr = $q->as_array()->all();
                    //some values could be unavailable with other properties selected. (probably need to select that selected values separately)
                    ///VL2do add all alt ids (separate AR method)...
                    $vids = \yii\helpers\Array_Helper::get_column($rr, 'values_id');
                    if (!empty(array_diff($pr_filters[$option_id]['values'], $vids))) {
                        $q = clone $cnt_q;
                        $params['filters']['properties'] = $other;
                        $q->and_where(['pp.values_id' => array_diff($pr_filters[$option_id]['values'], $vids)])->add_select(['count' => new \yii\db\Expression('0')])->and_where(['pp.properties_id' => $option_id]);
                        $rrr = $q->as_array()->all();
                    } else {
                        $rrr = [];
                    }
                    $r = array_merge($r, $rr, $rrr);
                    unset($rr);
                    unset($rrr);
                }
            }
        }
        /// filter_by_property
        $tmp = array_filter(\yii\helpers\Array_Helper::get_column($r, 'filter_by_property'));
        if (is_array($tmp)) {
            foreach ($tmp as $k => $pid) {
                if (isset($pr_filters[$pid]) && $pr_filters[$pid]['field'] == 'values_id' && is_array($pr_filters[$pid]['values'])) {
                    $match_any = false;
                    $tmp = array_filter($pr_filters[$pid]['values']);
                    if (empty($tmp)) {
                        unset($r[$k]);
                        unset($pr_filters[$pid]['values']);
                    } else {
                        foreach ($pr_filters[$pid]['values'] as $svid) {
                            if (!empty($r[$pid . '_' . $svid]['values_text'])) {
                                $txt = $r[$pid . '_' . $svid]['values_text'];
                                //if (!preg_match('/'. preg_quote($txt) . '/i', $r[$k]['filter_values_text'])) {
                                if (0 === stripos($r[$k]['filter_values_text'], $txt) || 0 === stripos($r[$k]['values_text'], $txt)) {
                                    //ok starts with filter value
                                    $match_any = true;
                                    break;
                                }
                            }
                        }
                        if (!$match_any) {
                            unset($r[$k]);
                        }
                    }
                } elseif ($r[$k]['filter_steps'] == 1) {
                    /// hide all values if filter (parent) property is not selected yet
                    unset($r[$k]);
                }
            }
        }
        /// filter_by_property eof
        //echo __LINE__ . "#### CNT <PRE STYle='position:absolute; width:40%; top:0; left:0; z-index:100'>" .print_r($r, 1) ."</PRE>";
        $uniq_texts = $ret = [];
        foreach ($r as $v) {
            if (!isset($ret[$v['properties_id']])) {
                $ret[$v['properties_id']] = ['title' => $v['properties_name'] . (tep_not_null($v['properties_units_title']) ? '<span class="units-title"> <span class="units-title-text">' . $v['properties_units_title'] . '</span></span>' : ''), 'name' => $name . $v['properties_id'], 'color' => $v['properties_color'], 'image' => $v['properties_image'], 'type' => $v['range_select'] == 1 && $v['extra_values'] == 1 ? 'extra' : (in_array($v['display_filter_as'], self::$allowed_filter_types) ? $v['display_filter_as'] : 'boxes'), 'params' => isset($pr_filters[$v['properties_id']]['values']) ? $pr_filters[$v['properties_id']]['values'] : []];
                //if (in_array($v['properties_type'], ['number', 'interval'])) {          }
                if ($v['properties_type'] == 'number') {
                    $ret[$v['properties_id']]['type'] = 'slider';
                    $ret[$v['properties_id']]['step'] = (float) number_format(pow(10, -$v['decimals']), $v['decimals']);
                    $ret[$v['properties_id']]['paramfrom'] = $pr_filters[$v['properties_id']]['from'] != 0 ? $pr_filters[$v['properties_id']]['from'] : '';
                    $ret[$v['properties_id']]['paramto'] = $pr_filters[$v['properties_id']]['to'] != 0 ? $pr_filters[$v['properties_id']]['to'] : '';
                    $ret[$v['properties_id']]['min'] = 0;
                    $ret[$v['properties_id']]['max'] = 0;
                }
            }
            if (in_array($v['properties_type'], ['number', 'interval'])) {
                $v['values_text'] = (float) number_format($v['values_number'], $v['decimals']);
                if ($ret[$v['properties_id']]['min'] == 0 || $ret[$v['properties_id']]['min'] > $v['values_text']) {
                    $ret[$v['properties_id']]['min'] = $v['values_text'];
                }
                if ($ret[$v['properties_id']]['max'] == 0 || $ret[$v['properties_id']]['max'] < $v['values_text']) {
                    $ret[$v['properties_id']]['max'] = $v['values_text'];
                }
                if ($ret[$v['properties_id']]['paramfrom'] > 0 && (float) $ret[$v['properties_id']]['paramfrom'] < $ret[$v['properties_id']]['min']) {
                    //'paramfrom' => (float) ($_GET[$name . 'from'] > 0 && $_GET[$name . 'from'] > $min_value ? $_GET[$name . 'from'] : ''),
                    $ret[$v['properties_id']]['paramfrom'] = $ret[$v['properties_id']]['min'];
                }
                if ($ret[$v['properties_id']]['paramto'] > 0 && (float) $ret[$v['properties_id']]['paramto'] > $ret[$v['properties_id']]['max']) {
                    //'paramto' => (float) ($_GET[$name . 'to'] > 0 && $_GET[$name . 'to'] < $max_value ? $_GET[$name . 'to'] : ''),
                    $ret[$v['properties_id']]['paramto'] = $ret[$v['properties_id']]['max'];
                }
            } elseif (in_array($v['properties_type'], ['flag']) && $v['values_id'] == 0) {
                if (is_array($pr_filters[$v['properties_id']]['values'])) {
                    if (in_array(1, $pr_filters[$v['properties_id']]['values'])) {
                        $pr_filters[$v['properties_id']]['values'][] = 'Y';
                        $pr_filters[$v['properties_id']]['values'][] = 'y';
                    }
                    if (in_array(0, $pr_filters[$v['properties_id']]['values'])) {
                        $pr_filters[$v['properties_id']]['values'][] = 'N';
                        $pr_filters[$v['properties_id']]['values'][] = 'n';
                    }
                }
                if ($v['values_flag']) {
                    $v['values_id'] = 'Y';
                    $v['values_text'] = TEXT_YES;
                } else {
                    $v['values_id'] = 'N';
                    $v['values_text'] = TEXT_NO;
                }
            }
            if ($v['properties_type'] != 'number') {
                if (!isset($uniq_texts[$v['properties_id'] . '_' . $v['values_text']])) {
                    $uniq_texts[$v['properties_id'] . '_' . $v['values_text']] = $v['values_id'];
                    $ret[$v['properties_id']]['values'][$v['values_id']] = ['id' => $v['values_id'], 'text' => $v['values_text'], 'color' => $v['values_color'], 'count' => $v['count'], 'selected' => is_array($pr_filters[$v['properties_id']]['values'] ?? null) ? in_array($v['values_id'], $pr_filters[$v['properties_id']]['values']) : '', 'paramfrom' => isset($params['filters']['extra'][$v['properties_id']]['from'][$v['values_id']]) ? $params['filters']['extra'][$v['properties_id']]['from'][$v['values_id']] : '', 'paramto' => isset($params['filters']['extra'][$v['properties_id']]['to'][$v['values_id']]) ? $params['filters']['extra'][$v['properties_id']]['to'][$v['values_id']] : '', 'min' => $v['extra_value_min'], 'max' => $v['extra_value_max'], 'sort_order' => $v['sort_order']];
                } else {
                    $tmp = $uniq_texts[$v['properties_id'] . '_' . $v['values_text']];
                    $ret[$v['properties_id']]['values'][$tmp]['id'] .= ',' . $v['values_id'];
                    $ret[$v['properties_id']]['values'][$tmp]['count'] += $v['count'];
                    if (empty($ret[$v['properties_id']]['values'][$tmp]['selected'])) {
                        $ret[$v['properties_id']]['values'][$tmp]['selected'] = is_array($pr_filters[$v['properties_id']]['values']) ? in_array($v['values_id'], $pr_filters[$v['properties_id']]['values']) : '';
                    }
                }
            }
        }
        unset($r);
        $s_order = array_flip($ids);
        $ret_sorted = [];
        foreach ($ret as $k => $v) {
            if (is_array($v['values'])) {
                //((!defined('DISPLAY_ONE_VALUE_FILTER') || DISPLAY_ONE_VALUE_FILTER == 'True' || count($values_array) > 1 || $any_selected) && count($values_array) > 0 && $products_count > 0) {
                uasort($v['values'], ['self', 'cmpFilterValues']);
                //          if ((!defined('DISPLAY_ONE_VALUE_FILTER') || DISPLAY_ONE_VALUE_FILTER == 'True' || count($v['values']) > 1 || !empty($v['params'])) ) {
                if (self::is_display_one_value_filter() || count($v['values']) > 1 || !empty($v['params'])) {
                    $ret_sorted[$s_order[$k]] = $v;
                }
            } else {
                $ret_sorted[$s_order[$k]] = $v;
            }
        }
        return $ret_sorted;
    }
    private static function is_display_one_value_filter()
    {
        if ($ext = \common\helpers\Acl::check_extension_allowed('ProductPropertiesFilters')) {
            return method_exists($ext, 'optionDisplayOneValueFilter') ? $ext::option_display_one_value_filter() : !defined('DISPLAY_ONE_VALUE_FILTER') || DISPLAY_ONE_VALUE_FILTER == 'True';
        }
        return false;
    }
    /**
     *
     * @param array $setting
     * @return array indexed by sort order
     */
    protected function get_brand_filters_array($setting)
    {
        $name = $setting['get'];
        $pos = $setting['position'];
        $params = $this->params;
        if (!empty($params['filters']['manufacturers'])) {
            $pr_filters = $params['filters']['manufacturers'];
            unset($params['filters']['manufacturers']);
            unset($params['orderBy']);
        } else {
            $pr_filters = [];
        }
        $q = clone $this->build_query($params)->get_query();
        $q->offset(null)->limit(null)->order_by(null)->inner_join(['m' => TABLE_MANUFACTURERS], 'p.manufacturers_id = m.manufacturers_id')->select('m.manufacturers_id, m.manufacturers_name ')->add_select(['products_count' => new \yii\db\Expression('count(distinct p.products_id)')])->group_by('m.manufacturers_id, m.manufacturers_name ');
        $r = $q->as_array()->all();
        $manufacturers_array = [];
        if (is_array($r)) {
            foreach ($r as $manufacturers) {
                $manufacturers_array[$manufacturers['manufacturers_id']] = ['id' => $manufacturers['manufacturers_id'], 'text' => $manufacturers['manufacturers_name'], 'count' => (int) $manufacturers['products_count'], 'selected' => is_array($pr_filters) ? in_array($manufacturers['manufacturers_id'], $pr_filters) : ''];
            }
        }
        if (count($pr_filters) > count($manufacturers_array)) {
            $missed = array_diff($pr_filters, array_keys($manufacturers_array));
            $q = (new \yii\db\Query())->from(['m' => TABLE_MANUFACTURERS])->select('m.manufacturers_id, m.manufacturers_name ')->and_where(['m.manufacturers_id' => $missed]);
            $r = $q->all();
            if ($r) {
                foreach ($r as $manufacturers) {
                    $manufacturers_array[$manufacturers['manufacturers_id']] = ['id' => $manufacturers['manufacturers_id'], 'text' => $manufacturers['manufacturers_name'], 'count' => 0, 'selected' => is_array($pr_filters) ? in_array($manufacturers['manufacturers_id'], $pr_filters) : ''];
                }
            }
        }
        uasort($manufacturers_array, ['self', 'cmpFilterValues']);
        $ret_sorted = [];
        if (!empty($manufacturers_array)) {
            $ret_sorted[$pos] = ['title' => TEXT_BRAND, 'name' => $name, 'type' => defined('BRANDS_FILTER_DISPLAY_MODE') && in_array(BRANDS_FILTER_DISPLAY_MODE, self::$allowed_filter_types) ? BRANDS_FILTER_DISPLAY_MODE : 'boxes', 'not_selected' => empty(array_filter($manufacturers_array, function ($el) {
                return $el['selected'];
            })), 'values' => $manufacturers_array, 'params' => $pr_filters];
        }
        return $ret_sorted;
    }
    /**
     *
     * @param array $setting
     * @return array indexed by sort order
     */
    protected function get_category_filters_array($setting)
    {
        $name = $setting['get'];
        $pos = $setting['position'];
        $params = $this->params;
        if (!empty($params['filters']['categories'])) {
            $pr_filters = $params['filters']['categories'];
            unset($params['filters']['categories']);
            unset($params['orderBy']);
        } else {
            $pr_filters = [];
        }
        //echo "<PRE style='position:absolute;left:65%;top:0;z-index:100; width:35%'>" . __FILE__ .":". __LINE__ . print_r($prFilters, 1) . "</PRE>";
        // {{ remove category filter on leaf category
        if ($this->get_current_category_id()) {
            $active_subcategories = [];
            \common\helpers\Categories::get_subcategories($active_subcategories, $this->get_current_category_id());
            if (count($active_subcategories) == 0) {
                return [];
            }
        }
        // }} remove category filter on leaf category
        $q = \common\models\Categories::find()->with_product_ids()->active()->with_list_description()->and_where(['products_id' => $this->filter_products_ids($params)])->select('{{%categories}}.categories_id, categories_name, {{%categories}}.categories_left as sort_order  ')->add_select(['products_count' => new \yii\db\Expression('count(distinct products_id)')])->group_by('{{%categories}}.categories_id, categories_name  ')->order_by(['{{%categories}}.categories_left' => SORT_ASC]);
        //echo '<BR>' . $q->createCommand()->rawSql;
        $r = $q->as_array()->all();
        $ret = [];
        if ($r) {
            foreach ($r as $cats) {
                $ret[$cats['categories_id']] = ['id' => $cats['categories_id'], 'text' => $cats['categories_name'], 'count' => (int) $cats['products_count'], 'sort_order' => (int) $cats['sort_order'], 'selected' => is_array($pr_filters) ? in_array($cats['categories_id'], $pr_filters) : ''];
            }
        }
        if (count($pr_filters) > count($ret)) {
            $missed = array_diff($pr_filters, array_keys($ret));
            $q = \common\models\Categories::find()->active()->with_list_description()->select('{{%categories}}.categories_id, categories_name, {{%categories}}.categories_left as sort_order  ')->and_where(['{{%categories}}.categories_id' => $missed]);
            $r = $q->as_array()->all();
            if ($r) {
                foreach ($r as $cats) {
                    $ret[$cats['categories_id']] = ['id' => $cats['categories_id'], 'text' => $cats['categories_name'], 'count' => 0, 'sort_order' => (int) $cats['sort_order'], 'selected' => is_array($pr_filters) ? in_array($cats['categories_id'], $pr_filters) : ''];
                }
            }
        }
        uasort($ret, ['self', 'cmpFilterValues']);
        //echo "<PRE STYle='position:absolute; width:40%; top:200; left:60%; z-index:100'>" . __FILE__ .':' . __LINE__ . " #### " .print_r($ret, 1) ."</PRE>";
        $ret_sorted = [];
        if (!empty($ret)) {
            $ret_sorted[$pos] = ['title' => TEXT_CATEGORY, 'name' => $name, 'type' => defined('CATEGORIES_FILTER_DISPLAY_MODE') && in_array(CATEGORIES_FILTER_DISPLAY_MODE, self::$allowed_filter_types) ? CATEGORIES_FILTER_DISPLAY_MODE : 'boxes', 'values' => $ret, 'params' => $pr_filters];
        }
        return $ret_sorted;
    }
    /**
     *
     * @param array $setting
     * @return array indexed by sort order
     */
    protected function get_keywords_filters_array($setting)
    {
        $name = $setting['get'];
        $pos = $setting['position'];
        $ret_sorted = [];
        $ret_sorted[$pos] = ['title' => TEXT_KEYWORDS, 'name' => $name, 'type' => 'input', 'params' => !empty($this->params['filters'][$name]) ? tep_db_prepare_input($this->params['filters'][$name]) : ''];
        return $ret_sorted;
    }
    /**
     *
     * @param array $setting
     * @return array indexed by sort order
     */
    protected function get_price_filters_array($setting)
    {
        if (defined('GROUPS_IS_SHOW_PRICE') && GROUPS_IS_SHOW_PRICE == false) {
            return [];
        }
        $name = $setting['get'];
        $pos = $setting['position'];
        $currencies = \Yii::$container->get('currencies');
        $min_price = $max_price = 0;
        $container = \Yii::$container->get('products');
        $params = $this->params;
        unset($params['filters']['price']);
        unset($params['orderBy']);
        /**
         * @var $ext \common\extensions\ProductPriceIndex\ProductPriceIndex
         * @var $extModel \common\extensions\ProductPriceIndex\models\ProductPriceIndex
         */
        if (($ext = \common\helpers\Extensions::is_allowed('ProductPriceIndex')) && !empty($ext_model = $ext::get_model('ProductPriceIndex'))) {
            $ext::check_update_status();
            $ranges = $ext_model::get_range(['products' => $this->filter_products_ids($params)]);
            //echo "<PRE STYle='position:absolute; width:40%; top:0; left:60%; z-index:100'>" . __FILE__ .':' . __LINE__ . " #### " .print_r( /*$ranges*/$this->filterProductsIds($params), 1) ."</PRE>";
            foreach ($ranges as $products) {
                if ($products['min_special'] > 0 && $products['min_special'] < $products['min']) {
                    $price = $products['min_special'];
                } else {
                    $price = $products['min'];
                }
                $price = $currencies->display_price_clear($price, \common\helpers\Tax::get_tax_rate($products['products_tax_class_id']));
                if ($min_price == 0 || $price < $min_price) {
                    $min_price = (float) $price;
                }
                if ($products['max_special'] > 0 && $products['max_special'] > $products['max']) {
                    $price = $products['max_special'];
                } else {
                    $price = $products['max'];
                }
                $price = $currencies->display_price_clear($price, \common\helpers\Tax::get_tax_rate($products['products_tax_class_id']));
                if ($max_price == 0 || $price > $max_price) {
                    $max_price = (float) $price;
                }
            }
        } else {
            $r = $this->build_query($params)->get_query()->offset(null)->limit(null)->order_by(null)->select('p.products_id, p.products_tax_class_id, p.products_price ')->as_array()->all();
            foreach ($r as $products) {
                $container->load_products($products);
                $special_price = \common\helpers\Product::get_products_special_price($products['products_id']);
                $price = \common\helpers\Product::get_products_price($products['products_id'], 1, $products['products_price']);
                if ($special_price) {
                    $price = $special_price;
                }
                $price = $currencies->display_price_clear($price, \common\helpers\Tax::get_tax_rate($products['products_tax_class_id']));
                if ($min_price == 0 || $price < $min_price) {
                    $min_price = (float) $price;
                }
                if ($max_price == 0 || $price > $max_price) {
                    $max_price = (float) $price;
                }
            }
        }
        $ret_sorted = [];
        $from = $this->params['filters']['price']['from'] ?? null;
        $to = $this->params['filters']['price']['to'] ?? null;
        if ($max_price > $min_price) {
            $ret_sorted[$pos] = ['title' => TEXT_PRICE, 'name' => $name, 'type' => 'slider', 'step' => 1, 'min' => (int) max(0, floor($min_price)), 'max' => (int) max(0, ceil($max_price)), 'min_price' => $min_price, 'max_price' => $max_price, 'paramfrom' => $from > 0 && $from > $min_price ? $from : '', 'paramto' => $to > 0 && $to < $max_price ? $to : ''];
        } else {
            $ret_sorted[$pos] = ['name' => 'price_data', 'min_price' => $min_price, 'max_price' => $max_price];
        }
        return $ret_sorted;
    }
    public function get_filters_array($settings)
    {
        $ret = $exclude = [];
        if (is_array($settings)) {
            $to_process = [];
            foreach ($settings as $n => $f) {
                switch ($f['filters_type']) {
                    case 'keywords':
                        $to_process['keywords'] = ['position' => $n, 'get' => 'keywords'];
                        $exclude[] = 'keywords';
                        break;
                    case 'price':
                        $to_process['price'] = ['position' => $n, 'get' => 'p'];
                        $exclude[] = 'pfrom';
                        $exclude[] = 'pto';
                        break;
                    case 'category':
                        $to_process['category'] = ['position' => $n, 'get' => 'cat'];
                        $exclude[] = 'cat';
                        break;
                    case 'brand':
                        $to_process['brand'] = ['position' => $n, 'get' => 'brand'];
                        $exclude[] = 'brand';
                        break;
                    case 'attribute':
                        $to_process['attribute']['get'] = 'at';
                        $to_process['attribute']['ids'][$n] = $f['options_id'];
                        $exclude[] = 'at' . $f['options_id'];
                        break;
                    case 'property':
                        $to_process['property']['get'] = 'pr';
                        $to_process['property']['ids'][$n] = $f['properties_id'];
                        $exclude[] = 'pr' . $f['properties_id'];
                        $exclude[] = 'pr' . $f['properties_id'] . 'from';
                        $exclude[] = 'pr' . $f['properties_id'] . 'to';
                        /*$pvq = \common\models\PropertiesValues::find()
                                  ->select(['values_id', 'properties_id'])
                                  //->where(['properties_id' => $f['properties_id']])
                                  ->groupBy('values_id');
                          foreach ($pvq->asArray()->all() as $pvr) {
                              $exclude[] = 'vpr' . $pvr['properties_id'] . 'from' . $pvr['values_id'];
                              $exclude[] = 'vpr' . $pvr['properties_id'] . 'to' . $pvr['values_id'];
                          }*/
                        //$exclude[] = 'vpr' . $f['properties_id'] . 'from';
                        //$exclude[] = 'vpr' . $f['properties_id'] . 'to';
                        break;
                }
            }
            $pvq = \common\models\Properties_Values::find()->select(['values_id', 'properties_id'])->group_by('values_id');
            foreach ($pvq->as_array()->all() as $pvr) {
                $exclude[] = 'vpr' . $pvr['properties_id'] . 'from' . $pvr['values_id'];
                $exclude[] = 'vpr' . $pvr['properties_id'] . 'to' . $pvr['values_id'];
            }
            foreach ($to_process as $method => $params) {
                if (method_exists($this, 'get' . ucfirst($method) . 'FiltersArray')) {
                    $f = $this->{'get' . ucfirst($method) . 'FiltersArray'}($params);
                    if (!empty($f)) {
                        //            $ret = array_merge($ret, $f);
                        $ret += $f;
                    }
                } else {
                    \Yii::warning('method get' . ucfirst($method) . 'FiltersArray' . ' does not exist ');
                }
            }
            // echo "<PRE style='position:absolute;left:65%;top:0;z-index:100; width:35%'>" . __FILE__ .":". __LINE__ . print_r($ret, 1) . "</PRE>";
            ksort($ret, SORT_NUMERIC);
        }
        return ['exclude' => $exclude, 'filters' => $ret];
    }
    /**
     * order by: selected, with count>0, sort_order, text
     * @param array $a
     * @param array $b
     * @return int
     */
    public static function cmp_filter_values($a, $b)
    {
        //selected, count reverse order; text, sort order - usual
        $soa = !empty($a['sort_order']) ? $a['sort_order'] : 0;
        $sob = !empty($b['sort_order']) ? $b['sort_order'] : 0;
        $ret = 0;
        if ($a['selected'] == $b['selected']) {
            $a['count'] = $a['count'] > 0;
            $b['count'] = $b['count'] > 0;
            if ($a['count'] == $b['count']) {
                if ($soa == $sob) {
                    if ($a['text'] == $b['text']) {
                        //already $ret=0
                    } elseif ($a['text'] < $b['text']) {
                        $ret = -1;
                    } else {
                        $ret = 1;
                    }
                } elseif ($soa < $sob) {
                    $ret = -1;
                } else {
                    $ret = 1;
                }
            } elseif ($a['count'] > $b['count']) {
                $ret = -1;
            } else {
                $ret = 1;
            }
        } elseif ((int) $a['selected'] > (int) $b['selected']) {
            $ret = -1;
        } else {
            $ret = 1;
        }
        return $ret;
    }
    public function get_count($params = false)
    {
        $save = $this->params;
        if (is_array($params)) {
            $this->params = array_merge($this->params, $params);
        }
        $this->params['orderBy'] = ['fake' => 1];
        $cnt = $this->build_query()->get_query()->count();
        $this->params = $save;
        return $cnt;
    }
    private function get_current_category_id()
    {
        global $current_category_id;
        return intval($current_category_id);
    }
}