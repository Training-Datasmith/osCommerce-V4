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

class Category extends Abstract_Class
{
    public $category_id = null;
    public $category_record = [];
    public $description_record_array = [];
    public $affiliate_record_array = [];
    public $platform_record_array = [];
    public $platform_setting_record_array = [];
    public $template_record_array = [];
    public $group_record_array = [];
    public $supplier_discount_record_array = [];
    public $supplier_price_rule_record_array = [];
    public $filter_record_array = [];
    public $product_record_array = [];
    public $category_image_new_array = [];
    public $old_seo_redirect_array = [];
    public function get_id()
    {
        return $this->category_id;
    }
    public function set_id($category_id)
    {
        $category_id = (int) $category_id;
        if ($category_id >= 0) {
            $this->category_id = $category_id;
            return true;
        }
        return $this;
    }
    public function load($category_id)
    {
        $this->clear();
        $category_id = (int) $category_id;
        $category_record = \common\models\Categories::find()->where(['categories_id' => $category_id])->one();
        if ($category_record instanceof \common\models\Categories) {
            $this->category_id = $category_id;
            $this->category_record = $category_record->to_array();
            unset($category_record);
            // DESCRIPTION
            $this->description_record_array = \common\models\Categories_Description::find()->where(['categories_id' => $category_id])->as_array(true)->all();
            // EOF DESCRIPTION
            // AFFILIATE
            $this->affiliate_record_array = !\common\helpers\Acl::check_extension_allowed('Affiliate') ? [] : \common\extensions\Affiliate\models\Categories_To_Affiliates::find()->where(['categories_id' => $category_id])->as_array(true)->all();
            // EOF AFFILIATE*/
            // PLATFORM
            $this->platform_record_array = \common\models\Platforms_Categories::find()->where(['categories_id' => $category_id])->as_array(true)->all();
            // EOF PLATFORM
            // PLATFORM SETTING
            $this->platform_setting_record_array = \common\models\Categories_Platform_Settings::find()->where(['categories_id' => $category_id])->as_array(true)->all();
            // EOF PLATFORM SETTING
            // TEMPLATE
            $this->template_record_array = \common\models\Categories_To_Template::find()->where(['categories_id' => $category_id])->as_array(true)->all();
            // EOF TEMPLATE
            // GROUP
            if ($model = \common\helpers\Acl::check_extension_table_exist('UserGroupsRestrictions', 'GroupsCategories')) {
                $this->group_record_array = $model::find()->where(['categories_id' => $category_id])->as_array(true)->all();
            }
            // EOF GROUP
            // SUPPLIER DISCOUNT
            $this->supplier_discount_record_array = \common\models\Suppliers_Catalog_Discount::find()->where(['category_id' => $category_id])->as_array(true)->all();
            // EOF SUPPLIER DISCOUNT
            // SUPPLIER PRICE RULE
            $this->supplier_price_rule_record_array = \common\models\Suppliers_Catalog_Price_Rules::find()->where(['category_id' => $category_id])->as_array(true)->all();
            // EOF SUPPLIER PRICE RULE
            // FILTER
            $this->filter_record_array = \common\models\Filters::find()->where(['categories_id' => $category_id])->as_array(true)->all();
            // EOF FILTER
            // PRODUCT
            $this->product_record_array = \common\models\Products2Categories::find()->alias('ptc')->left_join(\common\models\Products::table_name() . ' AS p', 'p.products_id = ptc.products_id')->select(['ptc.*', 'p.products_model'])->where(['categories_id' => $category_id])->as_array(true)->all();
            // EOF PRODUCT
            return true;
        }
        return false;
    }
    public function validate()
    {
        $this->category_id = (int) ((int) $this->category_id > 0 ? $this->category_id : 0);
        if (!is_array($this->category_record)) {
            return false;
        }
        if (!parent::validate()) {
            return false;
        }
        unset($this->category_record['categories_id']);
        $this->description_record_array = is_array($this->description_record_array) ? $this->description_record_array : [];
        $this->affiliate_record_array = is_array($this->affiliate_record_array) ? $this->affiliate_record_array : [];
        $this->platform_record_array = is_array($this->platform_record_array) ? $this->platform_record_array : [];
        $this->platform_setting_record_array = is_array($this->platform_setting_record_array) ? $this->platform_setting_record_array : [];
        $this->template_record_array = is_array($this->template_record_array) ? $this->template_record_array : [];
        $this->group_record_array = is_array($this->group_record_array) ? $this->group_record_array : [];
        $this->supplier_discount_record_array = is_array($this->supplier_discount_record_array) ? $this->supplier_discount_record_array : [];
        $this->supplier_price_rule_record_array = is_array($this->supplier_price_rule_record_array) ? $this->supplier_price_rule_record_array : [];
        $this->filter_record_array = is_array($this->filter_record_array) ? $this->filter_record_array : [];
        $this->product_record_array = is_array($this->product_record_array) ? $this->product_record_array : [];
        $this->category_image_new_array = is_array($this->category_image_new_array) ? $this->category_image_new_array : [];
        $this->old_seo_redirect_array = is_array($this->old_seo_redirect_array) ? $this->old_seo_redirect_array : [];
        return true;
    }
    public function create()
    {
        $this->category_id = 0;
        return $this->save();
    }
    public function save($is_replace = false)
    {
        $return = false;
        if (!$this->validate()) {
            return $return;
        }
        $category_class = \common\models\Categories::find()->where(['categories_id' => $this->category_id])->one();
        if (!$category_class instanceof \common\models\Categories) {
            $category_class = new \common\models\Categories();
            $category_class->load_default_values();
            if ($this->category_id > 0) {
                $category_class->categories_id = $this->category_id;
            } else {
                $this->unrelate();
            }
        }
        $category_class->set_attributes($this->category_record, false);
        $category_class->detach_behavior('nestedSets');
        if ($category_class->save(false)) {
            $this->category_record = $category_class->to_array();
            $this->category_id = (int) $category_class->categories_id;
            // DESCRIPTION
            foreach ($this->description_record_array as $key => &$description_record) {
                $is_save = false;
                $language_id = (int) (isset($description_record['language_id']) ? $description_record['language_id'] : 0);
                if (isset($description_record['language_code'])) {
                    $language_id = $this->get_language_id_by_code($description_record['language_code'], $language_id);
                }
                $affiliate_id = (int) (isset($description_record['affiliate_id']) ? $description_record['affiliate_id'] : -1);
                unset($description_record['categories_id']);
                unset($description_record['affiliate_id']);
                unset($description_record['language_id']);
                if ($language_id > 0 and $affiliate_id >= 0) {
                    try {
                        $description_class = \common\models\Categories_Description::find()->where(['categories_id' => $this->category_id, 'language_id' => $language_id, 'affiliate_id' => $affiliate_id])->one();
                        if (!$description_class instanceof \common\models\Categories_Description) {
                            $description_class = new \common\models\Categories_Description();
                            $description_class->load_default_values();
                            $description_class->categories_id = $this->category_id;
                            $description_class->affiliate_id = $affiliate_id;
                            $description_class->language_id = $language_id;
                        }
                        $description_class->set_attributes($description_record, false);
                        if ($description_class->save(false)) {
                            $is_save = true;
                            $description_record = $description_class->to_array();
                        } else {
                            $this->message_add($description_class->get_error_summary(true));
                        }
                    } catch (\Exception $exc) {
                        $this->message_add($exc->get_message());
                    }
                    unset($description_class);
                }
                unset($affiliate_id);
                unset($language_id);
                if ($is_save != true) {
                    unset($this->description_record_array[$key]);
                }
                unset($is_save);
            }
            unset($description_record);
            unset($key);
            // EOF DESCRIPTION
            // AFFILIATE
            if (\common\helpers\Acl::check_extension_allowed('Affiliate')) {
                foreach ($this->affiliate_record_array as $key => &$affiliate_record) {
                    $is_save = false;
                    $affiliate_id = (int) (isset($affiliate_record['affiliate_id']) ? $affiliate_record['affiliate_id'] : -1);
                    unset($affiliate_record['categories_id']);
                    unset($affiliate_record['affiliate_id']);
                    if ($affiliate_id >= 0) {
                        try {
                            $affiliate_class = \common\extensions\Affiliate\models\Categories_To_Affiliates::find()->where(['categories_id' => $this->category_id, 'affiliate_id' => $affiliate_id])->one();
                            if (!$affiliate_class instanceof \common\models\Categories_To_Affiliates) {
                                $affiliate_class = new \common\extensions\Affiliate\models\Categories_To_Affiliates();
                                $affiliate_class->load_default_values();
                                $affiliate_class->categories_id = $this->category_id;
                                $affiliate_class->affiliate_id = $affiliate_id;
                            }
                            $affiliate_class->set_attributes($affiliate_record, false);
                            if ($affiliate_class->save(false)) {
                                $is_save = true;
                                $affiliate_record = $affiliate_class->to_array();
                            } else {
                                $this->message_add($affiliate_class->get_error_summary(true));
                            }
                        } catch (\Exception $exc) {
                            $this->message_add($exc->get_message());
                        }
                        unset($affiliate_class);
                    }
                    unset($affiliate_id);
                    if ($is_save != true) {
                        unset($this->affiliate_record_array[$key]);
                    }
                    unset($is_save);
                }
                unset($affiliate_record);
                unset($key);
            }
            // EOF AFFILIATE
            // PLATFORM
            foreach ($this->platform_record_array as $key => &$platform_record) {
                $is_save = false;
                $platform_id = (int) (isset($platform_record['platform_id']) ? $platform_record['platform_id'] : 0);
                unset($platform_record['categories_id']);
                unset($platform_record['platform_id']);
                if ($platform_id > 0) {
                    try {
                        $platform_class = \common\models\Platforms_Categories::find()->where(['categories_id' => $this->category_id, 'platform_id' => $platform_id])->one();
                        if (!$platform_class instanceof \common\models\Platforms_Categories) {
                            $platform_class = new \common\models\Platforms_Categories();
                            $platform_class->load_default_values();
                            $platform_class->categories_id = $this->category_id;
                            $platform_class->platform_id = $platform_id;
                        }
                        $platform_class->set_attributes($platform_record, false);
                        if ($platform_class->save(false)) {
                            $is_save = true;
                            $platform_record = $platform_class->to_array();
                        } else {
                            $this->message_add($platform_class->get_error_summary(true));
                        }
                    } catch (\Exception $exc) {
                        $this->message_add($exc->get_message());
                    }
                    unset($platform_class);
                }
                unset($platform_id);
                if ($is_save != true) {
                    unset($this->platform_record_array[$key]);
                }
                unset($is_save);
            }
            unset($platform_record);
            unset($key);
            // EOF PLATFORM
            // PLATFORM SETTING
            foreach ($this->platform_setting_record_array as $key => &$platform_setting_record) {
                $is_save = false;
                $platform_id = (int) (isset($platform_setting_record['platform_id']) ? $platform_setting_record['platform_id'] : 0);
                unset($platform_setting_record['categories_id']);
                unset($platform_setting_record['platform_id']);
                if ($platform_id > 0) {
                    try {
                        $platform_setting_class = \common\models\Categories_Platform_Settings::find()->where(['categories_id' => $this->category_id, 'platform_id' => $platform_id])->one();
                        if (!$platform_setting_class instanceof \common\models\Categories_Platform_Settings) {
                            $platform_setting_class = new \common\models\Categories_Platform_Settings();
                            $platform_setting_class->load_default_values();
                            $platform_setting_class->categories_id = $this->category_id;
                            $platform_setting_class->platform_id = $platform_id;
                        }
                        $platform_setting_class->set_attributes($platform_setting_record, false);
                        if ($platform_setting_class->save(false)) {
                            $is_save = true;
                            $platform_setting_record = $platform_setting_class->to_array();
                        } else {
                            $this->message_add($platform_setting_class->get_error_summary(true));
                        }
                    } catch (\Exception $exc) {
                        $this->message_add($exc->get_message());
                    }
                    unset($platform_setting_class);
                }
                unset($platform_id);
                if ($is_save != true) {
                    unset($this->platform_setting_record_array[$key]);
                }
                unset($is_save);
            }
            unset($platform_setting_record);
            unset($key);
            // EOF PLATFORM SETTING
            // TEMPLATE
            foreach ($this->template_record_array as $key => &$template_record) {
                $is_save = false;
                $platform_id = (int) (isset($template_record['platform_id']) ? $template_record['platform_id'] : 0);
                unset($template_record['categories_id']);
                unset($template_record['platform_id']);
                unset($template_record['id']);
                if ($platform_id > 0) {
                    try {
                        $template_class = \common\models\Categories_To_Template::find()->where(['categories_id' => $this->category_id, 'platform_id' => $platform_id])->one();
                        if (!$template_class instanceof \common\models\Categories_To_Template) {
                            $template_class = new \common\models\Categories_To_Template();
                            $template_class->load_default_values();
                            $template_class->categories_id = $this->category_id;
                            $template_class->platform_id = $platform_id;
                        }
                        $template_class->set_attributes($template_record, false);
                        if ($template_class->save(false)) {
                            $is_save = true;
                            $template_record = $template_class->to_array();
                        } else {
                            $this->message_add($template_class->get_error_summary(true));
                        }
                    } catch (\Exception $exc) {
                        $this->message_add($exc->get_message());
                    }
                    unset($template_class);
                }
                unset($platform_id);
                if ($is_save != true) {
                    unset($this->template_record_array[$key]);
                }
                unset($is_save);
            }
            unset($template_record);
            unset($key);
            // EOF TEMPLATE
            // GROUP
            $is_rewrite_group = false;
            if ($group_categories = \common\helpers\Acl::check_extension_table_exist('UserGroupsRestrictions', 'GroupsCategories')) {
                foreach ($this->group_record_array as $key => &$group_record) {
                    $is_save = false;
                    if ($is_rewrite_group == false) {
                        $is_rewrite_group = true;
                        $group_categories::delete_all(['categories_id' => $this->category_id]);
                    }
                    $group_id = (int) (isset($group_record['groups_id']) ? $group_record['groups_id'] : 0);
                    unset($group_record['categories_id']);
                    unset($group_record['groups_id']);
                    if ($group_id > 0) {
                        try {
                            $group_class = $group_categories::find()->where(['categories_id' => $this->category_id, 'groups_id' => $group_id])->one();
                            if (empty($group_class)) {
                                $group_class = new $group_categories();
                                $group_class->load_default_values();
                                $group_class->categories_id = $this->category_id;
                                $group_class->groups_id = $group_id;
                            }
                            $group_class->set_attributes($group_record, false);
                            if ($group_class->save(false)) {
                                $is_save = true;
                                $group_record = $group_class->to_array();
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
                        unset($this->group_record_array[$key]);
                    }
                    unset($is_save);
                }
                unset($is_rewrite_group);
                unset($group_record);
                unset($key);
            }
            // EOF GROUP
            // SUPPLIER DISCOUNT
            foreach ($this->supplier_discount_record_array as $key => &$supplier_discount_record) {
                $is_save = false;
                $manufacturer_id = (int) (isset($supplier_discount_record['manufacturer_id']) ? $supplier_discount_record['manufacturer_id'] : 0);
                $supplier_id = (int) (isset($supplier_discount_record['suppliers_id']) ? $supplier_discount_record['suppliers_id'] : 0);
                unset($supplier_discount_record['catalog_discount_id']);
                unset($supplier_discount_record['manufacturer_id']);
                unset($supplier_discount_record['suppliers_id']);
                unset($supplier_discount_record['category_id']);
                if ($supplier_id > 0 and $manufacturer_id > 0) {
                    try {
                        $supplier_discount_class = \common\models\Suppliers_Catalog_Discount::find()->where(['category_id' => $this->category_id, 'suppliers_id' => $supplier_id, 'manufacturer_id' => $manufacturer_id])->one();
                        if (!$supplier_discount_class instanceof \common\models\Suppliers_Catalog_Discount) {
                            $supplier_discount_class = new \common\models\Suppliers_Catalog_Discount();
                            $supplier_discount_class->load_default_values();
                            $supplier_discount_class->category_id = $this->category_id;
                            $supplier_discount_class->manufacturer_id = $manufacturer_id;
                            $supplier_discount_class->suppliers_id = $supplier_id;
                        }
                        $supplier_discount_class->set_attributes($supplier_discount_record, false);
                        if ($supplier_discount_class->save(false)) {
                            $is_save = true;
                            $supplier_discount_record = $supplier_discount_class->to_array();
                        } else {
                            $this->message_add($supplier_discount_class->get_error_summary(true));
                        }
                    } catch (\Exception $exc) {
                        $this->message_add($exc->get_message());
                    }
                    unset($supplier_discount_class);
                }
                unset($manufacturer_id);
                unset($supplier_id);
                if ($is_save != true) {
                    unset($this->supplier_discount_record_array[$key]);
                }
                unset($is_save);
            }
            unset($supplier_discount_record);
            unset($key);
            // EOF SUPPLIER DISCOUNT
            // SUPPLIER PRICE RULE
            foreach ($this->supplier_price_rule_record_array as $key => &$supplier_price_rule_record) {
                $is_save = false;
                $manufacturer_id = (int) (isset($supplier_price_rule_record['manufacturer_id']) ? $supplier_price_rule_record['manufacturer_id'] : 0);
                $currency_id = (int) (isset($supplier_price_rule_record['currencies_id']) ? $supplier_price_rule_record['currencies_id'] : -1);
                $supplier_id = (int) (isset($supplier_price_rule_record['suppliers_id']) ? $supplier_price_rule_record['suppliers_id'] : 0);
                unset($supplier_price_rule_record['manufacturer_id']);
                unset($supplier_price_rule_record['currencies_id']);
                unset($supplier_price_rule_record['suppliers_id']);
                unset($supplier_price_rule_record['category_id']);
                unset($supplier_price_rule_record['rule_id']);
                if ($supplier_id > 0 and $manufacturer_id > 0 and $currency_id >= 0) {
                    try {
                        $supplier_price_rule_class = \common\models\Suppliers_Catalog_Price_Rules::find()->where(['category_id' => $this->category_id, 'suppliers_id' => $supplier_id, 'manufacturer_id' => $manufacturer_id, 'currencies_id' => $currency_id])->one();
                        if (!$supplier_price_rule_class instanceof \common\models\Suppliers_Catalog_Price_Rules) {
                            $supplier_price_rule_class = new \common\models\Suppliers_Catalog_Price_Rules();
                            $supplier_price_rule_class->load_default_values();
                            $supplier_price_rule_class->category_id = $this->category_id;
                            $supplier_price_rule_class->manufacturer_id = $manufacturer_id;
                            $supplier_price_rule_class->currencies_id = $currency_id;
                            $supplier_price_rule_class->suppliers_id = $supplier_id;
                        }
                        $supplier_price_rule_class->set_attributes($supplier_price_rule_record, false);
                        if ($supplier_price_rule_class->save(false)) {
                            $is_save = true;
                            $supplier_price_rule_record = $supplier_price_rule_class->to_array();
                        } else {
                            $this->message_add($supplier_price_rule_class->get_error_summary(true));
                        }
                    } catch (\Exception $exc) {
                        $this->message_add($exc->get_message());
                    }
                    unset($supplier_price_rule_class);
                }
                unset($manufacturer_id);
                unset($currency_id);
                unset($supplier_id);
                if ($is_save != true) {
                    unset($this->supplier_price_rule_record_array[$key]);
                }
                unset($is_save);
            }
            unset($supplier_price_rule_record);
            unset($key);
            // EOF SUPPLIER PRICE RULE
            // FILTER
            foreach ($this->filter_record_array as $key => &$filter_record) {
                $is_save = false;
                $manufacturer_id = (int) (isset($filter_record['manufacturers_id']) ? $filter_record['manufacturers_id'] : 0);
                $property_id = (int) (isset($filter_record['properties_id']) ? $filter_record['properties_id'] : 0);
                $filter_type = trim(isset($filter_record['filters_type']) ? $filter_record['filters_type'] : '');
                $option_id = (int) (isset($filter_record['options_id']) ? $filter_record['options_id'] : 0);
                unset($filter_record['manufacturers_id']);
                unset($filter_record['categories_id']);
                unset($filter_record['properties_id']);
                unset($filter_record['filters_type']);
                unset($filter_record['options_id']);
                unset($filter_record['filters_id']);
                if ($filter_type != '') {
                    try {
                        $filter_class = \common\models\Filters::find()->where(['categories_id' => $this->category_id, 'manufacturers_id' => $manufacturer_id, 'filters_type' => $filter_type, 'options_id' => $option_id, 'properties_id' => $property_id])->one();
                        if (!$filter_class instanceof \common\models\Filters) {
                            $filter_class = new \common\models\Filters();
                            $filter_class->load_default_values();
                            $filter_class->categories_id = $this->category_id;
                            $filter_class->manufacturers_id = $manufacturer_id;
                            $filter_class->properties_id = $property_id;
                            $filter_class->filters_type = $filter_type;
                            $filter_class->options_id = $option_id;
                        }
                        $filter_class->set_attributes($filter_record, false);
                        if ($filter_class->save(false)) {
                            $is_save = true;
                            $filter_record = $filter_class->to_array();
                        } else {
                            $this->message_add($filter_class->get_error_summary(true));
                        }
                    } catch (\Exception $exc) {
                        $this->message_add($exc->get_message());
                    }
                    unset($filter_class);
                }
                unset($manufacturer_id);
                unset($property_id);
                unset($filter_type);
                unset($option_id);
                if ($is_save != true) {
                    unset($this->filter_record_array[$key]);
                }
                unset($is_save);
            }
            unset($filter_record);
            unset($key);
            // EOF FILTER
            // PRODUCT
            foreach ($this->product_record_array as $key => &$product_record) {
                $is_save = false;
                $product_id = (int) (isset($product_record['products_id']) ? $product_record['products_id'] : 0);
                $product_model = trim(isset($product_record['products_model']) ? $product_record['products_model'] : '');
                unset($product_record['products_model']);
                unset($product_record['categories_id']);
                unset($product_record['products_id']);
                if ($product_model != '') {
                    $product_id_check = $product_id;
                    $product_id = 0;
                    foreach (\common\models\Products::find_all(['products_model' => $product_model]) as $count => $product_search_record) {
                        $product_id = 0;
                        if ($count == 0) {
                            $product_id = (int) $product_search_record->products_id;
                        }
                        if ($product_id_check == (int) $product_search_record->products_id) {
                            $product_id = $product_id_check;
                            break;
                        }
                    }
                    unset($product_search_record);
                    unset($product_id_check);
                    unset($count);
                }
                if ($product_id > 0) {
                    try {
                        $product_class = \common\models\Products2Categories::find()->where(['categories_id' => $this->category_id, 'products_id' => $product_id])->one();
                        if (!$product_class instanceof \common\models\Products2Categories) {
                            $product_class = new \common\models\Products2Categories();
                            $product_class->load_default_values();
                            $product_class->categories_id = $this->category_id;
                            $product_class->products_id = $product_id;
                        }
                        $product_class->set_attributes($product_record, false);
                        if ($product_class->save(false)) {
                            $is_save = true;
                            $product_record = $product_class->to_array();
                            if ($product_model != '') {
                                $product_record['products_model'] = $product_model;
                            }
                        } else {
                            $this->message_add($product_class->get_error_summary(true));
                        }
                    } catch (\Exception $exc) {
                        $this->message_add($exc->get_message());
                    }
                    unset($product_class);
                }
                unset($product_model);
                unset($product_id);
                if ($is_save != true) {
                    unset($this->product_record_array[$key]);
                }
                unset($is_save);
            }
            unset($product_record);
            unset($key);
            // EOF PRODUCT
            // OLD SEO REDIRECT
            $seo_model = \common\helpers\Extensions::get_model('SeoRedirectsNamed', 'SeoRedirectsNamed');
            if (!empty($seo_model)) {
                foreach ($this->old_seo_redirect_array as $seo_redirect_array) {
                    try {
                        $platform_id = (int) (isset($seo_redirect_array['platform_id']) ? $seo_redirect_array['platform_id'] : 0);
                        if ($platform_id >= 0) {
                            $language_id = (int) (isset($seo_redirect_array['language_id']) ? $seo_redirect_array['language_id'] : 0);
                            if (isset($seo_redirect_array['language_code'])) {
                                $language_id = $this->get_language_id_by_code($seo_redirect_array['language_code'], $language_id);
                            }
                            $search_array = ['platform_id' => $platform_id, 'language_id' => $language_id, 'redirects_type' => 'category', 'owner_id' => $this->category_id, 'old_seo_page_name' => $seo_redirect_array['old_seo_page_name']];
                            $seo_redirect_record = $seo_model::find_one($search_array);
                            if (!$seo_redirect_record instanceof $seo_model) {
                                $seo_redirect_record = new $seo_model();
                                $seo_redirect_record->load_default_values();
                                $seo_redirect_record->set_attributes($search_array);
                                $seo_redirect_record->save();
                            }
                        }
                    } catch (\Exception $exc) {
                        \Yii::warning($exc->get_message() . ' ' . $exc->get_trace_as_string(), 'SeoRedirectNammed');
                    }
                    unset($seo_redirect_record);
                    unset($search_array);
                    unset($language_id);
                    unset($platform_id);
                }
            }
            unset($seo_redirect_array);
            // EOF OLD SEO REDIRECT
            // IMAGES
            if (count($this->category_image_new_array) > 0) {
                $category_directory = 'categories' . DIRECTORY_SEPARATOR . $this->category_id . DIRECTORY_SEPARATOR;
                foreach (['gallery' => '', 'hero' => '_2', 'homepage' => '_3'] as $image_type => $image_field) {
                    if (isset($this->category_image_new_array[$image_type]) and trim($this->category_image_new_array[$image_type]) != '') {
                        try {
                            $image_src = trim($this->category_image_new_array[$image_type]);
                            $image_body = file_get_contents($image_src);
                            if ($image_body != false) {
                                $image_name = $this->category_id . '_' . md5($image_src) . '.' . strtolower(pathinfo($image_src, PATHINFO_EXTENSION));
                                $image_directory = DIR_FS_CATALOG . DIR_WS_IMAGES . $category_directory;
                                if (!is_dir($image_directory)) {
                                    @mkdir($image_directory, 0777, true);
                                }
                                $image_file = @fopen($image_directory . $image_name, 'w+');
                                unset($image_directory);
                                if ($image_file) {
                                    $is_create = @fwrite($image_file, $image_body) > 0;
                                    @fclose($image_file);
                                    if ($is_create == true) {
                                        $category_class->{'categories_image' . $image_field} = \common\classes\Images::move_image($category_directory . $image_name, $category_directory . $image_type);
                                        $category_class->save(false);
                                        \common\classes\Images::create_webp($category_class->{'categories_image' . $image_field});
                                        \common\classes\Images::create_resize_images($category_class->{'categories_image' . $image_field}, 'Category ' . $image_type);
                                    }
                                    unset($is_create);
                                }
                                unset($image_file);
                                unset($image_name);
                            }
                            unset($image_body);
                            unset($image_src);
                        } catch (\Exception $exc) {
                            \Yii::warning("Error while import image '{$image_src}' for category({$this->category_id}) : " . $exc->get_message());
                        }
                    }
                }
                unset($category_directory);
                unset($image_field);
                unset($image_type);
                $category_class->save(false);
            }
            // EOF IMAGES
            $return = $this->category_id;
        } else {
            $this->message_add($category_class->get_error_summary(true));
        }
        unset($category_class);
        unset($is_replace);
        return $return;
    }
}