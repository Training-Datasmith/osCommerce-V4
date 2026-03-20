<?php

declare (strict_types=1);
namespace common\api\models\AR;

use backend\models\EP\Tools;
use common\api\models\AR\Products\Assigned_Categories;
use common\api\models\AR\Products\Assigned_Customer_Groups as ProductAssignedCustomerGroups;
use common\api\models\AR\Products\Assigned_Departments;
use common\api\models\AR\Products\Assigned_Platforms as ProductAssignedPlatforms;
use common\api\models\AR\Products\Attributes;
use common\api\models\AR\Products\Description;
use common\api\models\AR\Products\Documents;
use common\api\models\AR\Products\Featured;
use common\api\models\AR\Products\Gift_Wrap;
use common\api\models\AR\Products\Images;
use common\api\models\AR\Products\Inventory;
use common\api\models\AR\Products\Prices;
use common\api\models\AR\Products\Properties;
use common\api\models\AR\Products\Set_Products;
use common\api\models\AR\Products\Special;
use common\api\models\AR\Products\Supplier_Product;
use common\api\models\AR\Products\Suppliers_Data;
use common\api\models\AR\Products\Warehouses_Products;
use common\api\models\AR\Products\Xsell;
use yii\db\Expression;
class Products extends Ep_Map
{
    protected $hide_fields = [
        'products_image',
        'products_image_med',
        'products_image_lrg',
        'products_image_sm_1',
        'products_image_xl_1',
        'products_image_sm_2',
        'products_image_xl_2',
        'products_image_sm_3',
        'products_image_xl_3',
        'products_image_sm_4',
        'products_image_xl_4',
        'products_image_sm_5',
        'products_image_xl_5',
        'products_image_sm_6',
        'products_image_xl_6',
        'products_image_sm_7',
        'products_image_xl_7',
        'products_image_alt_1',
        'products_image_alt_2',
        'products_image_alt_3',
        'products_image_alt_4',
        'products_image_alt_5',
        'products_image_alt_6',
        //'products_date_added',
        //'products_last_modified',
        'products_seo_page_name',
        'last_xml_import',
        'last_xml_export',
        'previous_status',
        'vendor_id',
    ];
    protected $child_collections = ['descriptions' => [], 'prices' => [], 'gift_wrap' => false, 'featured' => false, 'special' => false, 'assigned_categories' => false, 'assigned_platforms' => false, 'assigned_customer_groups' => false, 'attributes' => false, 'inventory' => false, 'suppliers_data' => false, 'images' => false, 'properties' => false, 'xsell' => false, 'documents' => false, 'suppliers_product' => false, 'set_products' => false, 'warehouses_products' => []];
    protected $indexed_collections = ['assigned_categories' => 'common\api\models\AR\Products\AssignedCategories', 'assigned_platforms' => 'common\api\models\AR\Products\AssignedPlatforms', 'assigned_customer_groups' => 'common\api\models\AR\Products\AssignedCustomerGroups', 'attributes' => 'common\api\models\AR\Products\Attributes', 'inventory' => 'common\api\models\AR\Products\Inventory', 'suppliers_data' => 'common\api\models\AR\Products\SuppliersData', 'images' => 'common\api\models\AR\Products\Images', 'properties' => 'common\api\models\AR\Products\Properties', 'xsell' => 'common\api\models\AR\Products\Xsell', 'documents' => 'common\api\models\AR\Products\Documents', 'suppliers_product' => 'common\api\models\AR\Products\SupplierProduct', 'set_products' => 'common\api\models\AR\Products\SetProducts', 'gift_wrap' => 'common\api\models\AR\Products\GiftWrap', 'featured' => 'common\api\models\AR\Products\Featured', 'special' => 'common\api\models\AR\Products\Special', 'warehouses_products' => 'common\api\models\AR\Products\WarehousesProducts'];
    protected $auto_status = null;
    private $inventory_present = false;
    private $virtual_fields = [];
    public function __construct(array $config = [])
    {
        $this->inventory_present = \common\helpers\Extensions::is_allowed('Inventory');
        if (!$this->inventory_present) {
            unset($this->child_collections['inventory']);
            unset($this->indexed_collections['inventory']);
        }
        if (defined('TABLE_DEPARTMENTS_PRODUCTS')) {
            $this->child_collections['assigned_departments'] = false;
            $this->indexed_collections['assigned_departments'] = 'common\api\models\AR\Products\AssignedDepartments';
        }
        $market_present = defined('USE_MARKET_PRICES') && USE_MARKET_PRICES == 'True';
        $groups_present = \common\helpers\Extensions::is_customer_groups_allowed();
        if (!$market_present && !$groups_present) {
            unset($this->child_collections['prices']);
        }
        $this->after_save_hooks['Product::doCache'] = 'reCalculateStock';
        $this->after_save_hooks['Product::SpecialClean'] = 'removeInvalidSpecials';
        if (!$ext = \common\helpers\Acl::check_extension_allowed('UserGroupsRestrictions', 'allowed')) {
            if (array_key_exists('assigned_customer_groups', $this->child_collections)) {
                unset($this->child_collections['assigned_customer_groups']);
            }
            if (array_key_exists('assigned_customer_groups', $this->indexed_collections)) {
                unset($this->indexed_collections['assigned_customer_groups']);
            }
        }
        parent::__construct($config);
    }
    public static function table_name()
    {
        return TABLE_PRODUCTS;
    }
    public static function primary_key()
    {
        return ['products_id'];
    }
    public function set_auto_status($value)
    {
        $this->auto_status = $value;
    }
    public function rules()
    {
        return array_merge(parent::rules(), [['products_quantity', 'default', 'value' => 0]]);
    }
    // {{ XTrader
    public function set_suppliers_id($value)
    {
        $this->virtual_fields['suppliers_id'] = $value;
    }
    public function get_suppliers_id()
    {
        return isset($this->virtual_fields['suppliers_id']) ? $this->virtual_fields['suppliers_id'] : false;
    }
    // }} XTrader
    public function init_collection_by_lookup_key_descriptions($lookup_keys)
    {
        $load_all = in_array('*', $lookup_keys);
        if (!is_null($this->products_id)) {
            $db_map_collect = [];
            foreach (Description::find_all(['products_id' => $this->products_id]) as $obj) {
                $code = \common\classes\language::get_code($obj->language_id, true);
                if ($code == false) {
                    continue;
                }
                $db_map_collect[$code . '_' . $obj->platform_id] = $obj;
            }
            foreach (Description::get_all_key_codes() as $key_code => $lookup_pk) {
                if ($load_all || in_array($key_code, $lookup_keys)) {
                    if (isset($db_map_collect[$key_code])) {
                        $this->child_collections['descriptions'][$key_code] = $db_map_collect[$key_code];
                    } else {
                        $lookup_pk['products_id'] = $this->products_id;
                        $this->child_collections['descriptions'][$key_code] = new Description($lookup_pk);
                    }
                }
            }
        } else {
            foreach (Description::get_all_key_codes() as $key_code => $lookup_pk) {
                $this->child_collections['descriptions'][$key_code] = new Description($lookup_pk);
            }
        }
        /*
                foreach(Description::getAllKeyCodes() as $keyCode=>$lookupPK){
                    $this->childCollections['descriptions'][$keyCode] = null;
                    if ( is_null($this->products_id) ) {
                        $this->childCollections['descriptions'][$keyCode] = new Description($lookupPK);
                    }elseif( $loadAll || in_array($keyCode,$lookupKeys) ) {
                        if (!isset($this->childCollections['descriptions'][$keyCode])) {
                            $lookupPK['products_id'] = $this->products_id;
                            $this->childCollections['descriptions'][$keyCode] = Description::findOne($lookupPK);
                            if (!is_object($this->childCollections['descriptions'][$keyCode])) {
                                $this->childCollections['descriptions'][$keyCode] = new Description($lookupPK);
                            }
                        }
                    }
                }
        */
        return $this->child_collections['descriptions'];
    }
    public function init_collection_by_lookup_key_prices($lookup_keys)
    {
        $load_all = in_array('*', $lookup_keys);
        if (true) {
            if (!is_null($this->products_id)) {
                $db_map_collect = [];
                foreach (Prices::find_all(['products_id' => $this->products_id]) as $obj) {
                    $key_code = $obj->currencies_id . '_' . $obj->groups_id;
                    $db_map_collect[$key_code] = $obj;
                }
                foreach (Prices::get_all_key_codes() as $key_code => $lookup_pk) {
                    if ($load_all || in_array($key_code, $lookup_keys)) {
                        $db_key_code = $lookup_pk['currencies_id'] . '_' . $lookup_pk['groups_id'];
                        if (isset($db_map_collect[$db_key_code])) {
                            $this->child_collections['prices'][$key_code] = $db_map_collect[$db_key_code];
                        } else {
                            $lookup_pk['products_id'] = $this->products_id;
                            $this->child_collections['prices'][$key_code] = new Prices($lookup_pk);
                        }
                    }
                }
                unset($db_map_collect);
            } else {
                foreach (Prices::get_all_key_codes() as $key_code => $lookup_pk) {
                    $this->child_collections['prices'][$key_code] = new Prices($lookup_pk);
                }
            }
        } else {
            foreach (Prices::get_all_key_codes() as $key_code => $lookup_pk) {
                $this->child_collections['prices'][$key_code] = null;
                if (is_null($this->products_id)) {
                    $this->child_collections['prices'][$key_code] = new Prices($lookup_pk);
                } elseif ($load_all || in_array($key_code, $lookup_keys)) {
                    if (!isset($this->child_collections['prices'][$key_code])) {
                        $lookup_pk['products_id'] = $this->products_id;
                        $this->child_collections['prices'][$key_code] = Prices::find_one($lookup_pk);
                        if (!is_object($this->child_collections['prices'][$key_code])) {
                            $this->child_collections['prices'][$key_code] = new Prices($lookup_pk);
                        }
                    }
                }
            }
        }
        return $this->child_collections['prices'];
    }
    public function init_collection_by_lookup_key_gift_wrap($lookup_keys)
    {
        if (!is_array($this->child_collections['gift_wrap'])) {
            $this->child_collections['gift_wrap'] = [];
            if ($this->products_id) {
                $this->child_collections['gift_wrap'] = Gift_Wrap::find()->where(['products_id' => $this->products_id])->all();
            }
        }
        return $this->child_collections['gift_wrap'];
    }
    public function init_collection_by_lookup_key_featured($lookup_keys)
    {
        if (!is_array($this->child_collections['featured'])) {
            $this->child_collections['featured'] = [];
            if ($this->products_id) {
                $this->child_collections['featured'] = Featured::find()->where(['products_id' => $this->products_id])->all();
            }
        }
        return $this->child_collections['featured'];
    }
    public function init_collection_by_lookup_key_special($lookup_keys)
    {
        if (!is_array($this->child_collections['special'])) {
            $this->child_collections['special'] = [];
            if ($this->products_id) {
                $this->child_collections['special'] = Special::find()->where(['products_id' => $this->products_id])->and_where(['OR', ['status' => 1], ['>=', 'start_date', new Expression('NOW()')]])->order_by(['status' => SORT_DESC, 'start_date' => SORT_ASC])->all();
            }
        }
        return $this->child_collections['special'];
    }
    public function init_collection_by_lookup_key_assigned_categories($lookup_keys)
    {
        if (!is_array($this->child_collections['assigned_categories'])) {
            $this->child_collections['assigned_categories'] = [];
            if ($this->products_id) {
                $this->child_collections['assigned_categories'] = Assigned_Categories::find()->where(['products_id' => $this->products_id])->order_by(['sort_order' => SORT_ASC, 'categories_id' => SORT_ASC])->all();
            }
        }
        return $this->child_collections['assigned_categories'];
    }
    public function init_collection_by_lookup_key_set_products($lookup_keys)
    {
        if (!is_array($this->child_collections['set_products'])) {
            $this->child_collections['set_products'] = [];
            if ($this->products_id) {
                $this->child_collections['set_products'] = Set_Products::find()->where(['sets_id' => $this->products_id])->all();
            }
        }
        return $this->child_collections['set_products'];
    }
    public function init_collection_by_lookup_key_warehouses_products($lookup_keys)
    {
        $this->child_collections['warehouses_products'] = [];
        if (!$this->has_assigned_product_attributes()) {
            $load_all = in_array('*', $lookup_keys);
            if (false) {
                if (!is_null($this->products_id)) {
                    $db_map_collect = [];
                    foreach (Warehouses_Products::find_all([new Expression('CONCAT(\'\',:products_id)', ['products_id' => (int) $this->products_id])]) as $obj) {
                        $key_code = $obj->warehouse_id . '_' . $obj->suppliers_id;
                        $db_map_collect[$key_code] = $obj;
                    }
                    foreach (Warehouses_Products::get_all_key_codes() as $key_code => $lookup_pk) {
                        $lookup_pk['products_id'] = (int) $this->products_id;
                        if ($load_all || in_array($key_code, $lookup_keys)) {
                            if (isset($db_map_collect[$key_code])) {
                                $this->child_collections['warehouses_products'][$key_code] = $db_map_collect[$key_code];
                            } else {
                                $this->child_collections['warehouses_products'][$key_code] = new Warehouses_Products($lookup_pk);
                            }
                        }
                    }
                } else {
                    foreach (Warehouses_Products::get_all_key_codes() as $key_code => $lookup_pk) {
                        $this->child_collections['warehouses_products'][$key_code] = new Warehouses_Products($lookup_pk);
                    }
                }
            } else if (!is_null($this->products_id)) {
                $db_map_collect = [];
                foreach (Warehouses_Products::find_all([new Expression('CONCAT(\'\',:products_id)', ['products_id' => (int) $this->products_id])]) as $obj) {
                    $key_code = $obj->warehouse_id . '_' . $obj->suppliers_id;
                    if (!empty($obj->location_id)) {
                        $key_code .= '_' . $obj->location_id;
                    }
                    $db_map_collect[$key_code] = $obj;
                }
                foreach (Warehouses_Products::get_all_key_codes() as $key_code => $lookup_pk) {
                    if ($load_all || in_array($key_code, $lookup_keys)) {
                        if (isset($db_map_collect[$key_code])) {
                            $this->child_collections['warehouses_products'][$key_code] = $db_map_collect[$key_code];
                        }
                    }
                }
            }
        }
        return $this->child_collections['warehouses_products'];
    }
    public function init_collection_by_lookup_key_assigned_departments($lookup_keys)
    {
        if (!is_array($this->child_collections['assigned_departments'])) {
            $this->child_collections['assigned_departments'] = [];
            if ($this->products_id) {
                $this->child_collections['assigned_departments'] = Assigned_Departments::find()->where(['products_id' => $this->products_id])->order_by(['departments_id' => SORT_ASC])->all();
            }
        }
        return $this->child_collections['assigned_departments'];
    }
    public function init_collection_by_lookup_key_assigned_platforms($lookup_keys)
    {
        if (!is_array($this->child_collections['assigned_platforms'])) {
            $this->child_collections['assigned_platforms'] = [];
            if ($this->products_id) {
                $this->child_collections['assigned_platforms'] = Product_Assigned_Platforms::find()->where(['products_id' => $this->products_id])->order_by(['platform_id' => SORT_ASC])->all();
            }
        }
        return $this->child_collections['assigned_platforms'];
    }
    public function init_collection_by_lookup_key_assigned_customer_groups($lookup_keys)
    {
        if (!is_array($this->child_collections['assigned_customer_groups'])) {
            $this->child_collections['assigned_customer_groups'] = [];
            if ($this->products_id) {
                $this->child_collections['assigned_customer_groups'] = Product_Assigned_Customer_Groups::find()->where(['products_id' => $this->products_id])->order_by(['groups_id' => SORT_ASC])->all();
            }
        }
        return $this->child_collections['assigned_customer_groups'];
    }
    public function init_collection_by_lookup_key_attributes($lookup_keys)
    {
        if (!is_array($this->child_collections['attributes'])) {
            $this->child_collections['attributes'] = [];
            if ($this->products_id) {
                $this->child_collections['attributes'] = Attributes::find()->where(['products_id' => $this->products_id])->order_by(['options_id' => SORT_ASC, 'options_values_id' => SORT_ASC])->all();
            }
        }
        return $this->child_collections['attributes'];
    }
    public function get_collection_product_name()
    {
        if (isset($this->child_collections['descriptions'][DEFAULT_LANGUAGE]) && is_object($this->child_collections['descriptions'][DEFAULT_LANGUAGE])) {
            return $this->child_collections['descriptions'][DEFAULT_LANGUAGE]->products_name;
        }
        return null;
    }
    public function get_assigned_attribute_ids($exclude_virtual = false)
    {
        if (!is_array($this->child_collections['attributes'])) {
            $this->init_collection_by_lookup_key_attributes([]);
        }
        $ids = [];
        foreach ($this->child_collections['attributes'] as $attr_ar) {
            if ($attr_ar->pending_removal) {
                continue;
            }
            if ($exclude_virtual && Tools::get_instance()->is_option_virtual($attr_ar->options_id)) {
                continue;
            }
            if (!is_array($ids[$attr_ar->options_id])) {
                $ids[$attr_ar->options_id] = [];
            }
            $ids[$attr_ar->options_id][] = $attr_ar->options_values_id;
        }
        return $ids;
    }
    public function has_assigned_product_attributes()
    {
        if (is_array($this->child_collections['attributes'])) {
            $_check = $this->get_assigned_attribute_ids();
            return count($_check) > 0;
        }
        if ($this->products_id) {
            $in_database_count = \common\api\models\AR\Products\Attributes::find()->where(['products_id' => $this->products_id])->count();
            return $in_database_count > 0;
        }
        return false;
    }
    public function init_collection_by_lookup_key_inventory($lookup_keys)
    {
        if (!is_array($this->child_collections['inventory'])) {
            $this->child_collections['inventory'] = [];
            if ($this->products_id) {
                $this->child_collections['inventory'] = Inventory::find()->where(['prid' => $this->products_id])->order_by(['products_id' => SORT_ASC])->all();
            }
        }
        return $this->child_collections['inventory'];
    }
    public function init_collection_by_lookup_key_images($lookup_keys)
    {
        if (!is_array($this->child_collections['images'])) {
            $this->child_collections['images'] = [];
            if ($this->products_id) {
                $this->child_collections['images'] = Images::find()->where(['products_id' => $this->products_id])->order_by(['sort_order' => SORT_ASC])->all();
            }
        }
        return $this->child_collections['images'];
    }
    public function init_collection_by_lookup_key_properties($lookup_keys)
    {
        if (!is_array($this->child_collections['properties'])) {
            $this->child_collections['properties'] = [];
            if ($this->products_id) {
                $this->child_collections['properties'] = Properties::find()->where(['products_id' => $this->products_id])->all();
            }
        }
        return $this->child_collections['properties'];
    }
    public function init_collection_by_lookup_key_xsell($lookup_keys)
    {
        if (!is_array($this->child_collections['xsell'])) {
            $this->child_collections['xsell'] = [];
            if ($this->products_id) {
                $this->child_collections['xsell'] = Xsell::find()->where(['products_id' => $this->products_id])->order_by(['sort_order' => SORT_ASC])->all();
            }
        }
        return $this->child_collections['xsell'];
    }
    public function init_collection_by_lookup_key_documents($lookup_keys)
    {
        if (!is_array($this->child_collections['documents'])) {
            $this->child_collections['documents'] = [];
            if ($this->products_id) {
                $this->child_collections['documents'] = Documents::find()->where(['products_id' => $this->products_id])->order_by(['sort_order' => SORT_ASC])->all();
            }
        }
        return $this->child_collections['documents'];
    }
    public function init_collection_by_lookup_key_suppliers_data($lookup_keys)
    {
        if (!is_array($this->child_collections['suppliers_data'])) {
            $this->child_collections['suppliers_data'] = [];
            if ($this->products_id) {
                $this->child_collections['suppliers_data'] = Suppliers_Data::find()->where(['products_id' => $this->products_id])->all();
            }
        }
        return $this->child_collections['suppliers_data'];
    }
    // {{ XTrader
    public function init_collection_by_lookup_key_suppliers_product($lookup_keys)
    {
        if (!is_array($this->child_collections['suppliers_product'])) {
            $this->child_collections['suppliers_product'] = [];
            if ($this->products_id && $this->suppliers_id) {
                $this->child_collections['suppliers_product'] = Supplier_Product::find()->where(['products_id' => $this->products_id])->and_where(['suppliers_id' => $this->suppliers_id])->all();
            }
        }
        return $this->child_collections['suppliers_product'];
    }
    // }} XTrader
    /**
     * @return \yii\db\ActiveQuery
     */
    public function get_descriptions()
    {
        return $this->has_many(Description::class_name(), ['products_id' => 'products_id']);
    }
    /**
     * @return \yii\db\ActiveQuery
     */
    public function get_inventory()
    {
        return $this->has_many(Inventory::class_name(), ['prid' => 'products_id']);
    }
    public function get_featured()
    {
        return $this->has_one(Featured::class_name(), ['products_id' => 'products_id'])->where(['affiliate_id' => 0]);
    }
    /*public function extraFields()
      {
          return ['descriptions'=>'descriptions'];
      }*/
    public function export_array(array $fields = [])
    {
        $tools = \backend\models\EP\Tools::get_instance();
        $export = parent::export_array($fields);
        if (array_key_exists('stock_delivery_terms_id', $export) || in_array('stock_delivery_terms_text', $fields)) {
            $export['stock_delivery_terms_text'] = $tools->get_stock_delivery_terms($this->stock_delivery_terms_id);
        }
        if (array_key_exists('stock_indication_id', $export) || in_array('stock_indication_text', $fields)) {
            $export['stock_indication_text'] = $tools->get_stock_indication($this->stock_indication_id);
        }
        if (array_key_exists('manufacturers_id', $export) || in_array('manufacturers_name', $fields)) {
            $export['manufacturers_name'] = \common\helpers\Manufacturers::get_manufacturer_info('manufacturers_name', $this->manufacturers_id);
        }
        return $export;
    }
    public function import_array($data)
    {
        $tools = \backend\models\EP\Tools::get_instance();
        if (array_key_exists('stock_delivery_terms_text', $data)) {
            $data['stock_delivery_terms_id'] = $tools->lookup_stock_delivery_term_id($data['stock_delivery_terms_text']);
        }
        if (array_key_exists('stock_indication_text', $data)) {
            $data['stock_indication_id'] = $tools->lookup_stock_indication_id($data['stock_indication_text']);
        }
        if (array_key_exists('manufacturers_name', $data)) {
            $data['manufacturers_id'] = $tools->get_brand_by_name($data['manufacturers_name']);
            if ($data['manufacturers_id'] === 'null') {
                $data['manufacturers_id'] = null;
            }
        }
        if (isset($data['warehouses_products']) && is_array($data['warehouses_products'])) {
            unset($data['products_quantity']);
        }
        $import_result = parent::import_array($data);
        if (array_key_exists('attributes', $data)) {
            $this->check_inventory();
        }
        if (isset($data['AutoStatus'])) {
            $this->auto_status = $data['AutoStatus'];
        }
        return $import_result;
    }
    public function re_calculate_stock()
    {
        \common\helpers\Product::do_cache($this->products_id);
    }
    public function remove_invalid_specials()
    {
        foreach (Special::find()->where(['products_id' => $this->products_id, 'status' => 0, 'start_date' => null, 'expires_date' => null])->all() as $remove_inactive) {
            $remove_inactive->delete();
        }
    }
    public function check_inventory()
    {
        if (!$this->inventory_present) {
            return;
        }
        $attr = $this->get_assigned_attribute_ids(true);
        $options = $attr;
        ksort($options);
        reset($options);
        $i = 0;
        $idx = 0;
        foreach ($options as $key => $value) {
            if ($i == 0) {
                $idx = $key;
                $i = 1;
            }
            asort($options[$key]);
        }
        $inventory_options = [];
        if (count($options) > 0) {
            $inventory_options = \common\helpers\Inventory::get_inventory_uprid($options, $idx);
        }
        if (!is_array($this->child_collections['inventory'])) {
            $this->init_collection_by_lookup_key_inventory([]);
        }
        foreach ($this->child_collections['inventory'] as $idx => $inventory_obj) {
            $partial_uprid = preg_replace('/^\d+/', '', $inventory_obj->products_id);
            $have_valid_idx = array_search($partial_uprid, $inventory_options);
            if ($have_valid_idx !== false) {
                // valid inventory uprid
                unset($inventory_options[$have_valid_idx]);
                $inventory_obj->pending_removal = false;
            } else {
                $inventory_obj->pending_removal = true;
            }
        }
        // not checked need add
        foreach ($inventory_options as $partial_uprid) {
            $new_inventory = new Inventory();
            $new_inventory->products_id = strval($this->products_id) . $partial_uprid;
            $new_inventory->fill_option_value_list();
            $new_inventory->parent_ep_map($this);
            $this->child_collections['inventory'][] = $new_inventory;
        }
    }
    public function before_save($insert)
    {
        if ($ext = \common\helpers\Acl::check_extension_allowed('AutomaticallyStatus', 'allowed') && isset($this->auto_status)) {
            unset($this->products_status);
        }
        if ($this->inventory_present && $this->has_assigned_product_attributes()) {
            $this->child_collections['warehouses_products'] = [];
        } else if ($this->get_dirty_attributes(['products_quantity'])) {
            $default_warehouse_id = intval(\common\helpers\Warehouses::get_default_warehouse());
            $default_wh = $default_warehouse_id . '_' . \common\helpers\Suppliers::get_default_supplier_id();
            if (count($this->child_collections['warehouses_products']) == 0) {
                $this->init_collection_by_lookup_key_warehouses_products(['*']);
            }
            if (!isset($this->child_collections['warehouses_products'][$default_wh])) {
                $this->child_collections['warehouses_products'][$default_wh] = new Warehouses_Products([]);
                $this->child_collections['warehouses_products'][$default_wh]->warehouse_id = $default_warehouse_id;
                $this->child_collections['warehouses_products'][$default_wh]->suppliers_id = \common\helpers\Suppliers::get_default_supplier_id();
                $this->child_collections['warehouses_products'][$default_wh]->parent_ep_map($this);
            }
            $this->child_collections['warehouses_products'][$default_wh]->warehouse_stock_quantity = $this->products_quantity;
            $this->initiate_after_save('Product::doCache');
            unset($this->products_quantity);
        }
        if ($insert) {
            if (empty($this->products_date_added)) {
                $this->products_date_added = new Expression('NOW()');
            }
            if (defined('NEW_MARK_UNTIL_DAYS') && intval(constant('NEW_MARK_UNTIL_DAYS')) > 0 && empty($this->products_new_until)) {
                $this->products_new_until = date(\common\helpers\Date::DATABASE_DATE_FORMAT, strtotime('+' . intval(constant('NEW_MARK_UNTIL_DAYS')) . ' day'));
            }
        } else if ($this->is_modified()) {
            $this->products_last_modified = new Expression('NOW()');
        }
        if ($insert) {
            if ($this->parent_products_id) {
                $this->products_id_stock = $this->parent_products_id;
                $this->products_id_price = $this->parent_products_id;
            }
        }
        return parent::before_save($insert);
    }
    public function after_save($insert, $changed_attributes)
    {
        parent::after_save($insert, $changed_attributes);
        if ($insert) {
            if (!$this->parent_products_id) {
                // parented product handled in before save
                static::update_all(['products_id_stock' => $this->parent_products_id ? intval($this->parent_products_id) : intval($this->products_id), 'products_id_price' => $this->parent_products_id ? intval($this->parent_products_id) : intval($this->products_id)], ['products_id' => intval($this->products_id)]);
            } else {
                $child_count = static::find()->where(['parent_products_id' => intval($this->parent_products_id)])->count();
                static::update_all(['sub_product_children_count' => (int) $child_count], ['products_id' => intval($this->parent_products_id)]);
            }
        }
        static::update_all(['products_price_full' => $this->products_price_full], ['parent_products_id' => $this->products_id, 'products_id_price' => $this->products_id]);
        if ($insert && !is_array($this->child_collections['assigned_customer_groups'] ?? null)) {
            if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroupsRestrictions', 'allowed')) {
                if ($ext::select()) {
                    /** @var \backend\services\GroupsService $groupService */
                    try {
                        $group_service = \Yii::create_object(\backend\services\Groups_Service::class);
                        $group_service->add_product_to_all_groups($this->products_id);
                        unset($group_service);
                    } catch (\Exception $ex) {
                        \common\helpers\Php::log_error($ex);
                    }
                }
            }
        }
        if (isset($this->auto_status) && $ext = \common\helpers\Acl::check_extension_allowed('AutomaticallyStatus')) {
            $ext::set_auto_status_product($this->products_id, $this->auto_status, true);
            unset($this->auto_status);
        }
        $used_suppliers_products_ids = [];
        $get_used_ids_r = tep_db_query('SELECT DISTINCT suppliers_id ' . 'FROM ' . TABLE_SUPPLIERS_PRODUCTS . ' ' . "WHERE products_id='" . $this->products_id . "'");
        if (tep_db_num_rows($get_used_ids_r) > 0) {
            while ($get_used_id = tep_db_fetch_array($get_used_ids_r)) {
                $used_suppliers_products_ids[(int) $get_used_id['suppliers_id']] = (int) $get_used_id['suppliers_id'];
            }
        }
        if (count($used_suppliers_products_ids) == 0) {
            $used_suppliers_products_ids[intval(\common\helpers\Suppliers::get_default_supplier_id())] = intval(\common\helpers\Suppliers::get_default_supplier_id());
        }
        $get_wh_del = Warehouses_Products::find()->where(['prid' => $this->products_id]);
        if (count($used_suppliers_products_ids) > 0) {
            $get_wh_del->and_where(['NOT IN', 'suppliers_id', array_values($used_suppliers_products_ids)]);
        }
        if ($get_wh_del->count() > 0) {
            foreach ($get_wh_del->all() as $delete_warehouse_product) {
                $delete_warehouse_product->delete();
            }
            \common\helpers\Warehouses::update_products_quantity($this->products_id, \common\helpers\Warehouses::get_default_warehouse(), 0, '+');
        }
        /* @var $ext \common\extensions\PlainProductsDescription\PlainProductsDescription */
        $ext = \common\helpers\Acl::check_extension_allowed('PlainProductsDescription', 'allowed');
        if ($ext && $ext::is_enabled()) {
            $ext::reindex((int) $this->products_id);
        }
        if (array_key_exists('products_groups_id', $changed_attributes)) {
            \common\helpers\Products_Group_Sort_Cache::update($this->products_id);
        }
    }
    public function after_delete()
    {
        parent::after_delete();
        if ($this->parent_products_id) {
            $child_count = static::find()->where(['parent_products_id' => intval($this->parent_products_id)])->count();
            static::update_all(['sub_product_children_count' => (int) $child_count], ['products_id' => intval($this->parent_products_id)]);
        } else {
            foreach (static::find()->where(['parent_products_id' => $this->products_id])->select(['products_id'])->as_array()->all() as $child_product) {
                \common\helpers\Product::remove_product($child_product['products_id']);
            }
        }
    }
}