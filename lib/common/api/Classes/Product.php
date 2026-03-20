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

class Product extends Abstract_Class
{
    public $product_id = null;
    public $product_record = [];
    public $description_record_array = [];
    public $products_prices_record_array = [];
    public $products_attributes_record_array = [];
    public $products_to_categories_record_array = [];
    public $platforms_products_record_array = [];
    public $departments_products_record_array = [];
    public $products_images_record_array = [];
    public $inventory_record_array = [];
    public $give_away_products_record_array = [];
    public $featured_record_array = [];
    public $products_xsell_record_array = [];
    public $products_upsell_record_array = [];
    public $properties_to_propducts_record_array = [];
    public $products_videos_record_array = [];
    public $gift_wrap_products_record_array = [];
    public $products_notes_record_array = [];
    public $suppliers_products_record_array = [];
    public $warehouses_products_record_array = [];
    public $platform_stock_control_record_array = [];
    public $warehouse_stock_control_record_array = [];
    public $old_seo_redirect_array = [];
    // ...
    public $new_products_images_array = [];
    public $specials_array = [];
    //public $specialPricesArray = []; not done yet
    public function get_id()
    {
        return $this->product_id;
    }
    public function set_id($product_id)
    {
        $product_id = (int) $product_id;
        if ($product_id >= 0) {
            $this->product_id = $product_id;
            return true;
        }
        return $this;
    }
    public function load($product_id)
    {
        $this->clear();
        $product_id = (int) $product_id;
        $product_record = \common\models\Products::find()->where(['products_id' => $product_id])->one();
        if ($product_record instanceof \common\models\Products) {
            $this->product_id = $product_id;
            $this->product_record = $product_record->to_array();
            unset($product_record);
            /**
             * Description
             */
            $this->description_record_array = \common\models\Products_Description::find()->where(['products_id' => $product_id])->as_array(true)->all();
            /**
             * Prices
             */
            $this->products_prices_record_array = \common\models\Products_Prices::find()->where(['products_id' => $product_id])->as_array(true)->all();
            /**
             * Attributes
             */
            foreach (\common\models\Products_Attributes::find()->where(['products_id' => $product_id])->as_array(true)->all() as $products_attributes_record) {
                $products_attributes_record['productsAttributesPricesRecordArray'] = \common\models\Products_Attributes_Prices::find()->where(['products_attributes_id' => $products_attributes_record['products_attributes_id']])->as_array(true)->all();
                $products_attributes_record['productsAttributesDownloadRecord'] = \common\models\Products_Attributes_Download::find()->where(['products_attributes_id' => $products_attributes_record['products_attributes_id']])->one();
                $this->products_attributes_record_array[] = $products_attributes_record;
            }
            unset($products_attributes_record);
            /**
             * Categories
             */
            $this->products_to_categories_record_array = \common\models\Products2Categories::find()->where(['products_id' => $product_id])->as_array(true)->all();
            /**
             * Platforms
             */
            $this->platforms_products_record_array = \common\models\Platforms_Products::find()->where(['products_id' => $product_id])->as_array(true)->all();
            /**
             * Departments
             */
            $this->departments_products_record_array = \common\models\Departments_Products::find()->where(['products_id' => $product_id])->as_array(true)->all();
            /**
             * Images
             */
            foreach (\common\models\Products_Images::find()->where(['products_id' => $product_id])->as_array(true)->all() as $products_images_record) {
                $products_images_record['productsImagesDescriptionRecordArray'] = \common\models\Products_Images_Description::find()->where(['products_images_id' => $products_images_record['products_images_id']])->as_array(true)->all();
                $products_images_record['productsImagesAttributesRecordArray'] = \common\models\Products_Images_Attributes::find()->where(['products_images_id' => $products_images_record['products_images_id']])->as_array(true)->all();
                $products_images_record['productsImagesExternalUrlRecordArray'] = \common\models\Products_Images_External_Url::find()->where(['products_images_id' => $products_images_record['products_images_id']])->as_array(true)->all();
                $products_images_record['productsImagesInventoryRecordArray'] = \common\models\Products_Images_Inventory::find()->where(['products_images_id' => $products_images_record['products_images_id']])->as_array(true)->all();
                $this->products_images_record_array[] = $products_images_record;
            }
            unset($products_images_record);
            $this->new_products_images_array = [];
            /**
             * Inventory
             */
            foreach (\common\models\Inventory::find()->where(['prid' => $product_id])->as_array(true)->all() as $inventory_record) {
                $inventory_record['inventoryPricesRecordArray'] = \common\models\Inventory_Prices::find()->where(['inventory_id' => $inventory_record['inventory_id']])->as_array(true)->all();
                if ($ext_scl = \common\helpers\Acl::check_extension_allowed('StockControl', 'allowed')) {
                    $ext_scl::update_api_product_inventory_load($inventory_record);
                }
                $this->inventory_record_array[] = $inventory_record;
            }
            unset($inventory_record);
            /**
             * Give Away
             */
            $this->give_away_products_record_array = \common\models\Give_Away_Products::find()->where(['products_id' => $product_id])->as_array(true)->all();
            /**
             * Featured
             */
            $this->featured_record_array = \common\models\Featured::find()->where(['products_id' => $product_id])->as_array(true)->all();
            /**
             * Cross Sell
             */
            $xsell_model = \common\helpers\Extensions::get_model('UpSell', 'ProductsXsell');
            $this->products_xsell_record_array = !empty($xsell_model) ? $xsell_model::find()->where(['products_id' => $product_id])->as_array(true)->all() : [];
            $upsell_model = \common\helpers\Extensions::get_model('UpSell', 'ProductsUpsell');
            $this->products_upsell_record_array = !empty($upsell_model) ? $upsell_model::find()->where(['products_id' => $product_id])->as_array(true)->all() : [];
            /**
             * Properties To Propducts
             */
            $this->properties_to_propducts_record_array = \common\models\Properties2Propducts::find()->where(['products_id' => $product_id])->as_array(true)->all();
            /**
             * Videos
             */
            $this->products_videos_record_array = \common\models\Products_Videos::find()->where(['products_id' => $product_id])->as_array(true)->all();
            /**
             * Gift Wrap
             */
            $this->gift_wrap_products_record_array = \common\models\Gift_Wrap_Products::find()->where(['products_id' => $product_id])->as_array(true)->all();
            /**
             * Notes
             */
            $this->products_notes_record_array = \common\models\Product\Products_Notes::find()->where(['products_id' => $product_id])->as_array(true)->all();
            /**
             * Suppliers Products
             */
            $this->suppliers_products_record_array = \common\models\Suppliers_Products::find()->where(['products_id' => $product_id])->as_array(true)->all();
            /**
             * Warehouses Products
             */
            $this->warehouses_products_record_array = \common\models\Warehouses_Products::find()->where(['prid' => $product_id])->as_array(true)->all();
            /**
             * Specials
             */
            $this->specials_array = \common\models\Specials::find()->where(['products_id' => $product_id])->as_array(true)->all();
            if ($ext_scl = \common\helpers\Acl::check_extension_allowed('StockControl', 'allowed')) {
                $ext_scl::update_api_product_load($this);
            }
            // ...
            return true;
        }
        return false;
    }
    public function unrelate()
    {
        if (is_array($this->products_attributes_record_array)) {
            foreach ($this->products_attributes_record_array as &$products_attributes_record) {
                unset($products_attributes_record['products_attributes_id']);
                unset($products_attributes_record['productsAttributesDownloadRecord']['products_attributes_id']);
                if (is_array($products_attributes_record['productsAttributesPricesRecordArray'])) {
                    foreach ($products_attributes_record['productsAttributesPricesRecordArray'] as &$products_attributes_prices_record) {
                        unset($products_attributes_prices_record['products_attributes_id']);
                    }
                    unset($products_attributes_prices_record);
                }
            }
            unset($products_attributes_record);
        }
        if (is_array($this->products_images_record_array)) {
            foreach ($this->products_images_record_array as &$products_images_record) {
                unset($products_images_record['products_images_id']);
                if (is_array($products_images_record['productsImagesDescriptionRecordArray'])) {
                    foreach ($products_images_record['productsImagesDescriptionRecordArray'] as &$products_images_description_record) {
                        unset($products_images_description_record['products_images_id']);
                    }
                    unset($products_images_description_record);
                }
                if (is_array($products_images_record['productsImagesAttributesRecordArray'])) {
                    foreach ($products_images_record['productsImagesAttributesRecordArray'] as &$products_images_attributes_record) {
                        unset($products_images_attributes_record['products_images_id']);
                    }
                    unset($products_images_attributes_record);
                }
                if (is_array($products_images_record['productsImagesExternalUrlRecordArray'])) {
                    foreach ($products_images_record['productsImagesExternalUrlRecordArray'] as &$products_images_external_url_record) {
                        unset($products_images_external_url_record['products_images_id']);
                    }
                    unset($products_images_external_url_record);
                }
                if (is_array($products_images_record['productsImagesInventoryRecordArray'])) {
                    foreach ($products_images_record['productsImagesInventoryRecordArray'] as &$products_images_inventory_record) {
                        unset($products_images_inventory_record['products_images_id']);
                    }
                    unset($products_images_inventory_record);
                }
            }
            unset($products_images_record);
        }
        if (is_array($this->inventory_record_array)) {
            foreach ($this->inventory_record_array as &$inventory_record) {
                unset($inventory_record['inventory_id']);
                if (is_array($inventory_record['inventoryPricesRecordArray'])) {
                    foreach ($inventory_record['inventoryPricesRecordArray'] as &$inventory_prices_record) {
                        unset($inventory_prices_record['inventory_id']);
                    }
                    unset($inventory_prices_record);
                }
            }
            unset($inventory_record);
        }
        if (is_array($this->give_away_products_record_array)) {
            foreach ($this->give_away_products_record_array as &$give_away_products_record) {
                unset($give_away_products_record['gap_id']);
            }
            unset($give_away_products_record);
        }
        if (is_array($this->featured_record_array)) {
            foreach ($this->featured_record_array as &$featured_record) {
                unset($featured_record['featured_id']);
            }
            unset($featured_record);
        }
        if (is_array($this->products_xsell_record_array)) {
            foreach ($this->products_xsell_record_array as &$products_xsell_record) {
                unset($products_xsell_record['ID']);
            }
            unset($products_xsell_record);
        }
        if (is_array($this->products_upsell_record_array)) {
            foreach ($this->products_upsell_record_array as &$products_upsell_record) {
                unset($products_upsell_record['ID']);
            }
            unset($products_upsell_record);
        }
        // ...
        return parent::unrelate();
    }
    public function validate()
    {
        $this->product_id = (int) ((int) $this->product_id > 0 ? $this->product_id : 0);
        if (!is_array($this->product_record)) {
            return false;
        }
        if (!parent::validate()) {
            return false;
        }
        unset($this->product_record['products_id']);
        $this->description_record_array = is_array($this->description_record_array) ? $this->description_record_array : [];
        $this->products_prices_record_array = is_array($this->products_prices_record_array) ? $this->products_prices_record_array : [];
        $this->products_attributes_record_array = is_array($this->products_attributes_record_array) ? $this->products_attributes_record_array : [];
        foreach ($this->products_attributes_record_array as $key => $products_attributes_record) {
            $this->products_attributes_record_array[$key]['productsAttributesPricesRecordArray'] = is_array($products_attributes_record['productsAttributesPricesRecordArray']) ? $products_attributes_record['productsAttributesPricesRecordArray'] : [];
            $this->products_attributes_record_array[$key]['productsAttributesDownloadRecord'] = is_array($products_attributes_record['productsAttributesDownloadRecord']) ? $products_attributes_record['productsAttributesDownloadRecord'] : null;
        }
        unset($products_attributes_record);
        unset($key);
        $this->products_to_categories_record_array = is_array($this->products_to_categories_record_array) ? $this->products_to_categories_record_array : [];
        $this->platforms_products_record_array = is_array($this->platforms_products_record_array) ? $this->platforms_products_record_array : [];
        $this->departments_products_record_array = is_array($this->departments_products_record_array) ? $this->departments_products_record_array : [];
        $this->products_images_record_array = is_array($this->products_images_record_array) ? $this->products_images_record_array : [];
        foreach ($this->products_images_record_array as $key => $products_images_record) {
            $this->products_images_record_array[$key]['productsImagesDescriptionRecordArray'] = is_array($products_images_record['productsImagesDescriptionRecordArray']) ? $products_images_record['productsImagesDescriptionRecordArray'] : [];
            $this->products_images_record_array[$key]['productsImagesAttributesRecordArray'] = is_array($products_images_record['productsImagesAttributesRecordArray']) ? $products_images_record['productsImagesAttributesRecordArray'] : [];
            $this->products_images_record_array[$key]['productsImagesExternalUrlRecordArray'] = is_array($products_images_record['productsImagesExternalUrlRecordArray']) ? $products_images_record['productsImagesExternalUrlRecordArray'] : [];
            $this->products_images_record_array[$key]['productsImagesInventoryRecordArray'] = is_array($products_images_record['productsImagesInventoryRecordArray']) ? $products_images_record['productsImagesInventoryRecordArray'] : [];
        }
        unset($products_images_record);
        unset($key);
        $this->new_products_images_array = is_array($this->new_products_images_array) ? $this->new_products_images_array : [];
        $this->inventory_record_array = is_array($this->inventory_record_array) ? $this->inventory_record_array : [];
        foreach ($this->inventory_record_array as $key => $inventory_record) {
            $this->inventory_record_array[$key]['inventoryPricesRecordArray'] = is_array($inventory_record['inventoryPricesRecordArray']) ? $inventory_record['inventoryPricesRecordArray'] : [];
            $this->inventory_record_array[$key]['platformInventoryControlRecordArray'] = is_array($inventory_record['platformInventoryControlRecordArray']) ? $inventory_record['platformInventoryControlRecordArray'] : [];
            $this->inventory_record_array[$key]['warehouseInventoryControlRecordArray'] = is_array($inventory_record['warehouseInventoryControlRecordArray']) ? $inventory_record['warehouseInventoryControlRecordArray'] : [];
        }
        unset($inventory_record);
        unset($key);
        $this->give_away_products_record_array = is_array($this->give_away_products_record_array) ? $this->give_away_products_record_array : [];
        $this->featured_record_array = is_array($this->featured_record_array) ? $this->featured_record_array : [];
        $this->products_xsell_record_array = is_array($this->products_xsell_record_array) ? $this->products_xsell_record_array : [];
        $this->products_upsell_record_array = is_array($this->products_upsell_record_array) ? $this->products_upsell_record_array : [];
        $this->properties_to_propducts_record_array = is_array($this->properties_to_propducts_record_array) ? $this->properties_to_propducts_record_array : [];
        $this->products_videos_record_array = is_array($this->products_videos_record_array) ? $this->products_videos_record_array : [];
        $this->gift_wrap_products_record_array = is_array($this->gift_wrap_products_record_array) ? $this->gift_wrap_products_record_array : [];
        $this->products_notes_record_array = is_array($this->products_notes_record_array) ? $this->products_notes_record_array : [];
        $this->suppliers_products_record_array = is_array($this->suppliers_products_record_array) ? $this->suppliers_products_record_array : [];
        $this->warehouses_products_record_array = is_array($this->warehouses_products_record_array) ? $this->warehouses_products_record_array : [];
        $this->platform_stock_control_record_array = is_array($this->platform_stock_control_record_array) ? $this->platform_stock_control_record_array : [];
        $this->warehouse_stock_control_record_array = is_array($this->warehouse_stock_control_record_array) ? $this->warehouse_stock_control_record_array : [];
        $this->old_seo_redirect_array = is_array($this->old_seo_redirect_array) ? $this->old_seo_redirect_array : [];
        $this->specials_array = is_array($this->specials_array) ? $this->specials_array : [];
        // ...
        return true;
    }
    public function create()
    {
        $this->product_id = 0;
        return $this->save();
    }
    public function save($is_replace = false)
    {
        $return = false;
        if (!$this->validate()) {
            return $return;
        }
        $product_class = \common\models\Products::find()->where(['products_id' => $this->product_id])->one();
        if (!$product_class instanceof \common\models\Products) {
            $product_class = new \common\models\Products();
            $product_class->load_default_values();
            if ($this->product_id > 0) {
                $product_class->products_id = $this->product_id;
            } else {
                $this->unrelate();
            }
        }
        $product_class->set_attributes($this->product_record, false);
        $product_class->detach_behavior('nestedSets');
        if ($product_class->save(false)) {
            $this->product_record = $product_class->to_array();
            $this->product_id = (int) $product_class->products_id;
            /**
             * Description
             */
            foreach ($this->description_record_array as $description_record) {
                $language_id = (int) (isset($description_record['language_id']) ? $description_record['language_id'] : 0);
                $platform_id = (int) (isset($description_record['platform_id']) ? $description_record['platform_id'] : -1);
                $department_id = (int) (isset($description_record['department_id']) ? $description_record['department_id'] : 0);
                unset($description_record['products_id']);
                unset($description_record['language_id']);
                unset($description_record['platform_id']);
                unset($description_record['department_id']);
                if ($language_id > 0 and $platform_id >= 0) {
                    $description_class = \common\models\Products_Description::find()->where(['products_id' => $this->product_id, 'language_id' => $language_id, 'platform_id' => $platform_id, 'department_id' => $department_id])->one();
                    if (!$description_class instanceof \common\models\Products_Description) {
                        $description_class = new \common\models\Products_Description();
                        $description_class->load_default_values();
                        $description_class->products_id = $this->product_id;
                        $description_class->language_id = $language_id;
                        $description_class->platform_id = $platform_id;
                        $description_class->department_id = $department_id;
                    }
                    $description_class->set_attributes($description_record, false);
                    if ($description_class->save(false)) {
                    } else {
                        $this->message_add($description_class->get_error_summary(true));
                    }
                    unset($description_class);
                }
                unset($department_id);
                unset($platform_id);
                unset($language_id);
            }
            unset($description_record);
            /**
             * Prices
             */
            foreach ($this->products_prices_record_array as $price_record) {
                $currencies_id = (int) (isset($price_record['currencies_id']) ? $price_record['currencies_id'] : -1);
                $groups_id = (int) (isset($price_record['groups_id']) ? $price_record['groups_id'] : -1);
                unset($price_record['products_id']);
                unset($price_record['currencies_id']);
                unset($price_record['groups_id']);
                if ($currencies_id >= 0 and $groups_id >= 0) {
                    $price_class = \common\models\Products_Prices::find()->where(['products_id' => $this->product_id, 'groups_id' => $groups_id, 'currencies_id' => $currencies_id])->one();
                    if (!$price_class instanceof \common\models\Products_Prices) {
                        $price_class = new \common\models\Products_Prices();
                        $price_class->load_default_values();
                        $price_class->products_id = $this->product_id;
                        $price_class->groups_id = $groups_id;
                        $price_class->currencies_id = $currencies_id;
                    }
                    $price_class->set_attributes($price_record, false);
                    if (is_null($price_class->products_group_price)) {
                        $price_class->products_group_price = 0;
                    }
                    if ($price_class->save(false)) {
                    } else {
                        $this->message_add($price_class->get_error_summary(true));
                    }
                    unset($price_class);
                }
                unset($groups_id);
                unset($currencies_id);
            }
            unset($price_record);
            /**
             * Attributes
             */
            foreach ($this->products_attributes_record_array as $key => $products_attributes_record) {
                $is_save = false;
                $products_attributes_id = (int) (isset($products_attributes_record['products_attributes_id']) ? $products_attributes_record['products_attributes_id'] : 0);
                $options_id = (int) (isset($products_attributes_record['options_id']) ? $products_attributes_record['options_id'] : 0);
                $options_values_id = (int) (isset($products_attributes_record['options_values_id']) ? $products_attributes_record['options_values_id'] : 0);
                unset($products_attributes_record['products_id']);
                unset($products_attributes_record['products_attributes_id']);
                unset($products_attributes_record['options_id']);
                unset($products_attributes_record['options_values_id']);
                try {
                    if ($options_id > 0 && $options_values_id > 0) {
                        $atribute_class = \common\models\Products_Attributes::find()->where(['products_id' => $this->product_id, 'options_id' => $options_id, 'options_values_id' => $options_values_id])->one();
                        if (!$atribute_class instanceof \common\models\Products_Attributes) {
                            $atribute_class = new \common\models\Products_Attributes();
                            $atribute_class->load_default_values();
                            $atribute_class->products_id = $this->product_id;
                            $atribute_class->options_id = $options_id;
                            $atribute_class->options_values_id = $options_values_id;
                            /*if ($productsAttributesId > 0) {
                                  $atributeClass->products_attributes_id = $productsAttributesId;
                              }*/
                        }
                        if ($atribute_class->save(false)) {
                            $is_save = true;
                            $this->products_attributes_record_array[$key] = $products_attributes_record + $atribute_class->to_array();
                            $products_attributes_id = $atribute_class->products_attributes_id;
                        } else {
                            $this->message_add($atribute_class->get_error_summary(true));
                        }
                    }
                } catch (\Exception $exc) {
                }
                unset($atribute_class);
                if ($is_save != true) {
                    unset($this->products_attributes_record_array[$key]);
                }
                unset($is_save);
                if ($products_attributes_id > 0 and count($products_attributes_record['productsAttributesPricesRecordArray']) > 0) {
                    foreach ($products_attributes_record['productsAttributesPricesRecordArray'] as $products_attributes_prices_record) {
                        $currencies_id = (int) (isset($products_attributes_prices_record['currencies_id']) ? $products_attributes_prices_record['currencies_id'] : -1);
                        $groups_id = (int) (isset($products_attributes_prices_record['groups_id']) ? $products_attributes_prices_record['groups_id'] : -1);
                        unset($products_attributes_prices_record['products_attributes_id']);
                        unset($products_attributes_prices_record['currencies_id']);
                        unset($products_attributes_prices_record['groups_id']);
                        if ($currencies_id >= 0 and $groups_id >= 0) {
                            $products_attributes_class = \common\models\Products_Attributes_Prices::find()->where(['products_attributes_id' => $products_attributes_id, 'groups_id' => $groups_id, 'currencies_id' => $currencies_id])->one();
                            if (!$products_attributes_class instanceof \common\models\Products_Attributes_Prices) {
                                $products_attributes_class = new \common\models\Products_Attributes_Prices();
                                $products_attributes_class->load_default_values();
                                $products_attributes_class->products_attributes_id = $products_attributes_id;
                                $products_attributes_class->groups_id = $groups_id;
                                $products_attributes_class->currencies_id = $currencies_id;
                            }
                            $products_attributes_class->set_attributes($products_attributes_prices_record, false);
                            if ($products_attributes_class->save(false)) {
                                $this->message_add($products_attributes_class->get_error_summary(true));
                            }
                            unset($products_attributes_class);
                        }
                        unset($groups_id);
                        unset($currencies_id);
                    }
                    unset($products_attributes_prices_record);
                }
                if ($products_attributes_id > 0 and is_array($products_attributes_record['productsAttributesDownloadRecord'])) {
                    $products_attributes_download_record = $products_attributes_record['productsAttributesDownloadRecord'];
                    unset($products_attributes_download_record['products_attributes_id']);
                    $products_attributes_download_class = \common\models\Products_Attributes_Download::find()->where(['products_attributes_id' => $products_attributes_id])->one();
                    if (!$products_attributes_download_class instanceof \common\models\Products_Attributes_Download) {
                        $products_attributes_download_class = new \common\models\Products_Attributes_Download();
                        $products_attributes_download_class->load_default_values();
                        $products_attributes_download_class->products_attributes_id = $products_attributes_id;
                    }
                    $products_attributes_download_class->set_attributes($products_attributes_download_record, false);
                    if ($products_attributes_download_class->save(false)) {
                        $this->message_add($products_attributes_download_class->get_error_summary(true));
                    }
                    unset($products_attributes_download_class);
                    unset($products_attributes_download_record);
                }
                unset($products_attributes_id);
            }
            unset($key);
            unset($products_attributes_record);
            /**
             * Categories
             */
            foreach ($this->products_to_categories_record_array as $products_categories_record) {
                $category_id = (int) (isset($products_categories_record['categories_id']) ? $products_categories_record['categories_id'] : -1);
                unset($products_categories_record['products_id']);
                unset($products_categories_record['categories_id']);
                if ($category_id >= 0) {
                    $category_class = \common\models\Products2Categories::find()->where(['products_id' => $this->product_id, 'categories_id' => $category_id])->one();
                    if (!$category_class instanceof \common\models\Products2Categories) {
                        $category_class = new \common\models\Products2Categories();
                        $category_class->load_default_values();
                        $category_class->products_id = $this->product_id;
                        $category_class->categories_id = $category_id;
                    }
                    $category_class->set_attributes($products_categories_record, false);
                    if ($category_class->save(false)) {
                    } else {
                        $this->message_add($category_class->get_error_summary(true));
                    }
                    unset($category_class);
                }
                unset($category_id);
            }
            unset($products_categories_record);
            /**
             * Platforms
             */
            foreach ($this->platforms_products_record_array as $platforms_products_record) {
                $platform_id = (int) (isset($platforms_products_record['platform_id']) ? $platforms_products_record['platform_id'] : 0);
                unset($platforms_products_record['products_id']);
                unset($platforms_products_record['platform_id']);
                if ($platform_id > 0) {
                    $platform_class = \common\models\Platforms_Products::find()->where(['products_id' => $this->product_id, 'platform_id' => $platform_id])->one();
                    if (!$platform_class instanceof \common\models\Platforms_Products) {
                        $platform_class = new \common\models\Platforms_Products();
                        $platform_class->load_default_values();
                        $platform_class->products_id = $this->product_id;
                        $platform_class->platform_id = $platform_id;
                    }
                    $platform_class->set_attributes($platforms_products_record, false);
                    if ($platform_class->save(false)) {
                    } else {
                        $this->message_add($platform_class->get_error_summary(true));
                    }
                    unset($platform_class);
                }
                unset($platform_id);
            }
            unset($platforms_products_record);
            /**
             * Departments
             */
            foreach ($this->departments_products_record_array as $departments_products_record) {
                $department_id = (int) (isset($departments_products_record['departments_id']) ? $departments_products_record['departments_id'] : 0);
                unset($departments_products_record['products_id']);
                unset($departments_products_record['platform_id']);
                if ($department_id > 0) {
                    $department_class = \common\models\Departments_Products::find()->where(['products_id' => $this->product_id, 'platform_id' => $platform_id])->one();
                    if (!$department_class instanceof \common\models\Departments_Products) {
                        $department_class = new \common\models\Departments_Products();
                        $department_class->load_default_values();
                        $department_class->products_id = $this->product_id;
                        $department_class->departments_id = $department_id;
                    }
                    $department_class->set_attributes($departments_products_record, false);
                    if ($department_class->save(false)) {
                    } else {
                        $this->message_add($department_class->get_error_summary(true));
                    }
                    unset($department_class);
                }
                unset($department_id);
            }
            unset($departments_products_record);
            /**
             * Images
             */
            foreach ($this->products_images_record_array as $key => $products_images_record) {
                $is_save = false;
                $product_image_id = (int) (isset($products_images_record['products_images_id']) ? $products_images_record['products_images_id'] : 0);
                unset($products_images_record['products_id']);
                unset($products_images_record['products_images_id']);
                try {
                    $image_class = \common\models\Products_Images::find()->where(['products_id' => $this->product_id, 'products_images_id' => $product_image_id])->one();
                    if (!$image_class instanceof \common\models\Products_Images) {
                        $image_class = new \common\models\Products_Images();
                        $image_class->load_default_values();
                        $image_class->products_id = $this->product_id;
                        if ($product_image_id > 0) {
                            $image_class->products_images_id = $product_image_id;
                        }
                    }
                    $image_class->set_attributes($products_images_record, false);
                    if ($image_class->save(false)) {
                        $is_save = true;
                        $this->products_images_record_array[$key] = $products_images_record + $image_class->to_array();
                        $product_image_id = $image_class->products_images_id;
                    } else {
                        $this->message_add($image_class->get_error_summary(true));
                    }
                } catch (\Exception $exc) {
                }
                unset($image_class);
                if ($is_save != true) {
                    $product_image_id = 0;
                    unset($this->products_images_record_array[$key]);
                }
                unset($is_save);
                if ($product_image_id > 0 and count($products_images_record['productsImagesDescriptionRecordArray']) > 0) {
                    foreach ($products_images_record['productsImagesDescriptionRecordArray'] as $products_images_description_record) {
                        $language_id = (int) (isset($products_images_description_record['language_id']) ? $products_images_description_record['language_id'] : -1);
                        unset($products_images_description_record['products_images_id']);
                        unset($products_images_description_record['language_id']);
                        if ($language_id >= 0) {
                            $description_class = \common\models\Products_Images_Description::find()->where(['products_images_id' => $product_image_id, 'language_id' => $language_id])->one();
                            if (!$description_class instanceof \common\models\Products_Images_Description) {
                                $description_class = new \common\models\Products_Images_Description();
                                $description_class->load_default_values();
                                $description_class->products_images_id = $product_image_id;
                                $description_class->language_id = $language_id;
                            }
                            $description_class->set_attributes($products_images_description_record, false);
                            if ($description_class->save(false)) {
                            } else {
                                $this->message_add($description_class->get_error_summary(true));
                            }
                            unset($description_class);
                        }
                        unset($language_id);
                    }
                    unset($products_images_description_record);
                }
                if ($product_image_id > 0 and count($products_images_record['productsImagesAttributesRecordArray']) > 0) {
                    foreach ($products_images_record['productsImagesAttributesRecordArray'] as $products_images_attributes_record) {
                        $products_options_id = (int) (isset($products_images_attributes_record['products_options_id']) ? $products_images_attributes_record['products_options_id'] : 0);
                        $products_options_values_id = (int) (isset($products_images_attributes_record['products_options_values_id']) ? $products_images_attributes_record['products_options_values_id'] : 0);
                        unset($products_images_attributes_record['products_images_id']);
                        unset($products_images_attributes_record['products_options_id']);
                        unset($products_images_attributes_record['products_options_values_id']);
                        if ($products_options_id > 0 and $products_options_values_id >= 0) {
                            $images_attributes_class = \common\models\Products_Images_Attributes::find()->where(['products_images_id' => $product_image_id, 'products_options_id' => $products_options_id, 'products_options_values_id' => $products_options_values_id])->one();
                            if (!$images_attributes_class instanceof \common\models\Products_Images_Attributes) {
                                $images_attributes_class = new \common\models\Products_Images_Attributes();
                                $images_attributes_class->load_default_values();
                                $images_attributes_class->products_images_id = $product_image_id;
                                $images_attributes_class->products_options_id = $products_options_id;
                                $images_attributes_class->products_options_values_id = $products_options_values_id;
                            }
                            $images_attributes_class->set_attributes($products_images_attributes_record, false);
                            if ($images_attributes_class->save(false)) {
                            } else {
                                $this->message_add($images_attributes_class->get_error_summary(true));
                            }
                            unset($images_attributes_class);
                        }
                        unset($products_options_id);
                        unset($products_options_values_id);
                    }
                    unset($products_images_attributes_record);
                }
                if ($product_image_id > 0 and count($products_images_record['productsImagesExternalUrlRecordArray']) > 0) {
                    foreach ($products_images_record['productsImagesExternalUrlRecordArray'] as $products_images_external_url_record) {
                        $language_id = (int) (isset($products_images_external_url_record['language_id']) ? $products_images_external_url_record['language_id'] : -1);
                        $image_types_id = (int) (isset($products_images_external_url_record['image_types_id']) ? $products_images_external_url_record['image_types_id'] : 0);
                        unset($products_images_external_url_record['products_images_id']);
                        unset($products_images_external_url_record['image_types_id']);
                        unset($products_images_external_url_record['language_id']);
                        if ($language_id >= 0 and $image_types_id >= 0) {
                            $images_external_url_class = \common\models\Products_Images_External_Url::find()->where(['products_images_id' => $product_image_id, 'image_types_id' => $image_types_id, 'language_id' => $language_id])->one();
                            if (!$images_external_url_class instanceof \common\models\Products_Images_External_Url) {
                                $images_external_url_class = new \common\models\Products_Images_External_Url();
                                $images_external_url_class->load_default_values();
                                $images_external_url_class->products_images_id = $product_image_id;
                                $images_external_url_class->image_types_id = $image_types_id;
                                $images_external_url_class->language_id = $language_id;
                            }
                            $images_external_url_class->set_attributes($products_images_external_url_record, false);
                            if ($images_external_url_class->save(false)) {
                            } else {
                                $this->message_add($images_external_url_class->get_error_summary(true));
                            }
                            unset($images_external_url_class);
                        }
                        unset($image_types_id);
                        unset($language_id);
                    }
                    unset($products_images_external_url_record);
                }
                if ($product_image_id > 0 and count($products_images_record['productsImagesInventoryRecordArray']) > 0) {
                    foreach ($products_images_record['productsImagesInventoryRecordArray'] as $products_images_inventory_record) {
                        $inventory_id = (int) (isset($products_images_inventory_record['inventory_id']) ? $products_images_inventory_record['inventory_id'] : 0);
                        unset($products_images_inventory_record['products_images_id']);
                        unset($products_images_inventory_record['inventory_id']);
                        if ($inventory_id > 0) {
                            $images_inventory_class = \common\models\Products_Images_Inventory::find()->where(['products_images_id' => $product_image_id, 'inventory_id' => $inventory_id])->one();
                            if (!$images_inventory_class instanceof \common\models\Products_Images_Inventory) {
                                $images_inventory_class = new \common\models\Products_Images_Inventory();
                                $images_inventory_class->load_default_values();
                                $images_inventory_class->products_images_id = $product_image_id;
                                $images_inventory_class->inventory_id = $inventory_id;
                            }
                            $images_inventory_class->set_attributes($products_images_inventory_record, false);
                            if ($images_inventory_class->save(false)) {
                            } else {
                                $this->message_add($images_inventory_class->get_error_summary(true));
                            }
                            unset($images_inventory_class);
                        }
                        unset($inventory_id);
                    }
                    unset($products_images_inventory_record);
                }
                unset($product_image_id);
            }
            unset($key);
            unset($products_images_record);
            foreach ($this->new_products_images_array as $new_products_images) {
                $this->attach_new_image($new_products_images);
            }
            unset($new_products_images);
            $is_default = false;
            foreach (\common\models\Products_Images::find()->where(['products_id' => $this->product_id])->order_by(['default_image' => SORT_DESC, 'products_images_id' => SORT_ASC])->as_array(false)->all() as $product_image_record) {
                if ($is_default == false) {
                    $product_image_record->default_image = 1;
                    $is_default = true;
                } else {
                    $product_image_record->default_image = 0;
                }
                try {
                    $product_image_record->save();
                } catch (\Exception $exc) {
                }
            }
            unset($product_image_record);
            unset($is_default);
            /**
             * Inventory
             */
            foreach ($this->inventory_record_array as $key => $inventory_record) {
                $is_save = false;
                $inventory_id = (int) (isset($inventory_record['inventory_id']) ? $inventory_record['inventory_id'] : 0);
                $uprid = isset($inventory_record['products_id']) ? $inventory_record['products_id'] : $this->product_id;
                unset($inventory_record['inventory_id']);
                unset($inventory_record['products_id']);
                unset($inventory_record['prid']);
                $uprid = preg_replace('/^\d*(\{.+)$/', $this->product_id . '$1', $uprid);
                try {
                    $inventory_class = \common\models\Inventory::find()->where(['prid' => $this->product_id, 'products_id' => $uprid])->one();
                    if (!$inventory_class instanceof \common\models\Inventory) {
                        $inventory_class = new \common\models\Inventory();
                        $inventory_class->load_default_values();
                        $inventory_class->products_id = $uprid;
                        $inventory_class->prid = $this->product_id;
                        if ($inventory_id > 0) {
                            $inventory_class->inventory_id = $inventory_id;
                        }
                    }
                    $inventory_class->set_attributes($inventory_record, false);
                    if ($inventory_class->save(false)) {
                        $is_save = true;
                        $this->inventory_record_array[$key] = $inventory_record + $inventory_class->to_array();
                        $inventory_id = $inventory_class->inventory_id;
                    } else {
                        $this->message_add($inventory_class->get_error_summary(true));
                    }
                } catch (\Exception $exc) {
                }
                unset($inventory_class);
                if ($is_save != true) {
                    unset($this->inventory_record_array[$key]);
                }
                unset($is_save);
                // TODO
                if ($inventory_id > 0 and count($inventory_record['inventoryPricesRecordArray']) > 0) {
                    foreach ($inventory_record['inventoryPricesRecordArray'] as $inventory_prices_record) {
                        $currencies_id = (int) (isset($inventory_prices_record['currencies_id']) ? $inventory_prices_record['currencies_id'] : -1);
                        $groups_id = (int) (isset($inventory_prices_record['groups_id']) ? $inventory_prices_record['groups_id'] : -1);
                        unset($inventory_prices_record['inventory_id']);
                        unset($inventory_prices_record['groups_id']);
                        unset($inventory_prices_record['currencies_id']);
                        if ($currencies_id >= 0 and $groups_id >= 0) {
                            $inventory_class = \common\models\Inventory_Prices::find()->where(['prid' => $this->product_id, 'products_id' => $uprid, 'groups_id' => $groups_id, 'currencies_id' => $currencies_id])->one();
                            if (!$inventory_class instanceof \common\models\Inventory_Prices) {
                                $inventory_class = new \common\models\Inventory_Prices();
                                $inventory_class->load_default_values();
                                $inventory_class->inventory_id = $inventory_id;
                                $inventory_class->groups_id = $groups_id;
                                $inventory_class->currencies_id = $currencies_id;
                                $inventory_class->products_id = $uprid;
                                $inventory_class->prid = $this->product_id;
                            }
                            $inventory_class->set_attributes($inventory_prices_record, false);
                            if ($inventory_class->save(false)) {
                            } else {
                                $this->message_add($inventory_class->get_error_summary(true));
                            }
                            unset($inventory_class);
                        }
                        unset($groups_id);
                        unset($currencies_id);
                    }
                    unset($inventory_prices_record);
                }
                if ($inventory_id > 0) {
                    if (isset($inventory_record['inventoryImagesArray'])) {
                        foreach (is_array($inventory_record['inventoryImagesArray']) ? $inventory_record['inventoryImagesArray'] : [] as $inventory_image) {
                            $product_image_id = $this->find_image_id($inventory_image);
                            if ($product_image_id > 0) {
                                $images_inventory_class = \common\models\Products_Images_Inventory::find()->where(['products_images_id' => $product_image_id, 'inventory_id' => $inventory_id])->one();
                                if (!$images_inventory_class instanceof \common\models\Products_Images_Inventory) {
                                    $images_inventory_class = new \common\models\Products_Images_Inventory();
                                    $images_inventory_class->load_default_values();
                                    $images_inventory_class->products_images_id = $product_image_id;
                                    $images_inventory_class->inventory_id = $inventory_id;
                                }
                                $images_inventory_class->set_attributes($products_images_inventory_record, false);
                                if ($images_inventory_class->save(false)) {
                                } else {
                                    $this->message_add($images_inventory_class->get_error_summary(true));
                                }
                                unset($images_inventory_class);
                            }
                            unset($product_image_id);
                        }
                        unset($inventory_image);
                        unset($inventory_record['inventoryImagesArray']);
                    }
                }
                if ($ext_scl = \common\helpers\Acl::check_extension_allowed('StockControl', 'allowed')) {
                    $ext_scl::update_api_product_inventory_save($this, $inventory_record, $inventory_id, $uprid);
                }
            }
            unset($key);
            unset($inventory_record);
            /**
             * Give Away
             */
            foreach ($this->give_away_products_record_array as $give_away_products_record) {
                $currencies_id = (int) (isset($give_away_products_record['currencies_id']) ? $give_away_products_record['currencies_id'] : -1);
                $groups_id = (int) (isset($give_away_products_record['groups_id']) ? $give_away_products_record['groups_id'] : -1);
                unset($give_away_products_record['products_id']);
                unset($give_away_products_record['currencies_id']);
                unset($give_away_products_record['groups_id']);
                if ($currencies_id >= 0 and $groups_id >= 0) {
                    $give_away_class = \common\models\Give_Away_Products::find()->where(['products_id' => $this->product_id, 'groups_id' => $groups_id, 'currencies_id' => $currencies_id])->one();
                    if (!$give_away_class instanceof \common\models\Give_Away_Products) {
                        $give_away_class = new \common\models\Products_Prices();
                        $give_away_class->load_default_values();
                        $give_away_class->products_id = $this->product_id;
                        $give_away_class->groups_id = $groups_id;
                        $give_away_class->currencies_id = $currencies_id;
                    }
                    $give_away_class->set_attributes($give_away_products_record, false);
                    if ($give_away_class->save(false)) {
                    } else {
                        $this->message_add($give_away_class->get_error_summary(true));
                    }
                    unset($give_away_class);
                }
                unset($groups_id);
                unset($currencies_id);
            }
            unset($give_away_products_record);
            /**
             * Featured
             */
            foreach ($this->featured_record_array as $featured_record_array) {
                $affiliate_id = (int) (isset($featured_record_array['affiliate_id']) ? $featured_record_array['affiliate_id'] : 0);
                unset($featured_record_array['products_id']);
                unset($featured_record_array['affiliate_id']);
                $featured_class = \common\models\Featured::find()->where(['products_id' => $this->product_id, 'affiliate_id' => $affiliate_id])->one();
                if (!$featured_class instanceof \common\models\Featured) {
                    $featured_class = new \common\models\Featured();
                    $featured_class->load_default_values();
                    $featured_class->products_id = $this->product_id;
                    $featured_class->affiliate_id = $affiliate_id;
                }
                $featured_class->set_attributes($featured_record_array, false);
                if ($featured_class->save(false)) {
                } else {
                    $this->message_add($featured_class->get_error_summary(true));
                }
                unset($featured_class);
                unset($affiliate_id);
            }
            unset($featured_record_array);
            /**
             * Cross Sell
             */
            $xsell_model = \common\helpers\Extensions::get_model('UpSell', 'ProductsXsell');
            if (!empty($xsell_model)) {
                foreach ($this->products_xsell_record_array as $products_xsell_record) {
                    $xsell_id = (int) (isset($products_xsell_record['xsell_id']) ? $products_xsell_record['xsell_id'] : 0);
                    unset($products_xsell_record['products_id']);
                    unset($products_xsell_record['xsell_id']);
                    if ($xsell_id > 0) {
                        $xsell_class = $xsell_model::find()->where(['products_id' => $this->product_id, 'xsell_id' => $xsell_id])->one();
                        if (!$xsell_class instanceof $xsell_model) {
                            $xsell_class = new $xsell_model();
                            $xsell_class->load_default_values();
                            $xsell_class->products_id = $this->product_id;
                            $xsell_class->xsell_id = $xsell_id;
                        }
                        $xsell_class->set_attributes($products_xsell_record, false);
                        if ($xsell_class->save(false)) {
                        } else {
                            $this->message_add($xsell_class->get_error_summary(true));
                        }
                        unset($xsell_class);
                    }
                    unset($xsell_id);
                }
                unset($products_xsell_record);
            }
            /**
             * Up Sell
             */
            $upsell_model = \common\helpers\Extensions::get_model('UpSell', 'ProductsUpsell');
            if (!empty($upsell_model)) {
                foreach ($this->products_upsell_record_array as $products_upsell_record) {
                    $upsell_id = (int) (isset($products_upsell_record['upsell_id']) ? $products_upsell_record['upsell_id'] : 0);
                    unset($products_upsell_record['products_id']);
                    unset($products_upsell_record['upsell_id']);
                    if ($upsell_id > 0) {
                        $upsell_class = $upsell_model::find()->where(['products_id' => $this->product_id, 'upsell_id' => $upsell_id])->one();
                        if (!$upsell_class instanceof $upsell_model) {
                            $upsell_class = new $upsell_model();
                            $upsell_class->load_default_values();
                            $upsell_class->products_id = $this->product_id;
                            $upsell_class->upsell_id = $upsell_id;
                        }
                        $upsell_class->set_attributes($products_upsell_record, false);
                        if ($upsell_class->save(false)) {
                        } else {
                            $this->message_add($upsell_class->get_error_summary(true));
                        }
                        unset($upsell_class);
                    }
                    unset($upsell_id);
                }
                unset($products_upsell_record);
            }
            /**
             * Properties To Propducts
             */
            foreach ($this->properties_to_propducts_record_array as $products_properties_record) {
                $property_id = (int) (isset($products_properties_record['properties_id']) ? $products_properties_record['properties_id'] : 0);
                $value_id = (int) (isset($products_properties_record['values_id']) ? $products_properties_record['values_id'] : 0);
                unset($products_properties_record['products_id']);
                unset($products_properties_record['properties_id']);
                unset($products_properties_record['values_id']);
                if ($property_id > 0 && $value_id > 0) {
                    $product_propery_class = \common\models\Properties2Propducts::find()->where(['products_id' => $this->product_id, 'properties_id' => $property_id, 'values_id' => $value_id])->one();
                    if (!$product_propery_class instanceof \common\models\Properties2Propducts) {
                        $product_propery_class = new \common\models\Properties2Propducts();
                        $product_propery_class->load_default_values();
                        $product_propery_class->products_id = $this->product_id;
                        $product_propery_class->properties_id = $property_id;
                        $product_propery_class->values_id = $value_id;
                    }
                    $product_propery_class->set_attributes($products_properties_record, false);
                    if ($product_propery_class->save(false)) {
                    } else {
                        $this->message_add($product_propery_class->get_error_summary(true));
                    }
                    unset($product_propery_class);
                }
                unset($value_id);
                unset($property_id);
            }
            unset($products_properties_record);
            /**
             * Videos
             */
            foreach ($this->products_videos_record_array as $key => $products_videos_record) {
                $language_id = (int) (isset($products_videos_record['language_id']) ? $products_videos_record['language_id'] : -1);
                $video_id = (int) (isset($products_properties_record['video_id']) ? $products_properties_record['video_id'] : 0);
                unset($products_videos_record['products_id']);
                unset($products_videos_record['language_id']);
                unset($products_properties_record['video_id']);
                if ($language_id >= 0) {
                    $product_video_class = \common\models\Products_Videos::find()->where(['products_id' => $this->product_id, 'video_id' => $video_id, 'language_id' => $language_id])->one();
                    if (!$product_video_class instanceof \common\models\Products_Videos) {
                        $product_video_class = new \common\models\Products_Videos();
                        $product_video_class->load_default_values();
                        $product_video_class->products_id = $this->product_id;
                        $product_video_class->language_id = $language_id;
                    }
                    $product_video_class->set_attributes($products_videos_record, false);
                    if ($product_video_class->save(false)) {
                        $this->products_videos_record_array[$key] = $product_video_class->to_array();
                    } else {
                        $this->message_add($product_video_class->get_error_summary(true));
                    }
                    unset($product_video_class);
                }
                unset($video_id);
                unset($language_id);
            }
            unset($products_videos_record);
            /**
             * Gift Wrap
             */
            foreach ($this->gift_wrap_products_record_array as $gift_wrap_products_record) {
                $currencies_id = (int) (isset($gift_wrap_products_record['currencies_id']) ? $gift_wrap_products_record['currencies_id'] : -1);
                $groups_id = (int) (isset($gift_wrap_products_record['groups_id']) ? $gift_wrap_products_record['groups_id'] : -1);
                unset($gift_wrap_products_record['products_id']);
                unset($gift_wrap_products_record['currencies_id']);
                unset($gift_wrap_products_record['groups_id']);
                if ($currencies_id >= 0 and $groups_id >= 0) {
                    $gift_wrap_class = \common\models\Gift_Wrap_Products::find()->where(['products_id' => $this->product_id, 'groups_id' => $groups_id, 'currencies_id' => $currencies_id])->one();
                    if (!$gift_wrap_class instanceof \common\models\Gift_Wrap_Products) {
                        $gift_wrap_class = new \common\models\Gift_Wrap_Products();
                        $gift_wrap_class->load_default_values();
                        $gift_wrap_class->products_id = $this->product_id;
                        $gift_wrap_class->groups_id = $groups_id;
                        $gift_wrap_class->currencies_id = $currencies_id;
                    }
                    $gift_wrap_class->set_attributes($gift_wrap_products_record, false);
                    if ($gift_wrap_class->save(false)) {
                    } else {
                        $this->message_add($gift_wrap_class->get_error_summary(true));
                    }
                    unset($gift_wrap_class);
                }
                unset($groups_id);
                unset($currencies_id);
            }
            unset($gift_wrap_products_record);
            /**
             * Notes
             */
            foreach ($this->products_notes_record_array as $key => $products_notes_record) {
                $note_id = (int) (isset($products_notes_record['products_notes_id']) ? $products_notes_record['products_notes_id'] : 0);
                unset($products_videos_record['products_id']);
                unset($products_videos_record['products_notes_id']);
                $note_class = \common\models\Products_Notes::find()->where(['products_id' => $this->product_id, 'products_notes_id' => $note_id])->one();
                if (!$note_class instanceof \common\models\Products_Notes) {
                    $note_class = new \common\models\Products_Notes();
                    $note_class->load_default_values();
                    $note_class->products_id = $this->product_id;
                }
                $note_class->set_attributes($products_notes_record, false);
                if ($note_class->save(false)) {
                    $this->products_notes_record_array[$key] = $note_class->to_array();
                } else {
                    $this->message_add($note_class->get_error_summary(true));
                }
                unset($note_class);
                unset($note_id);
            }
            unset($products_notes_record);
            /**
             * Suppliers Products
             */
            foreach ($this->suppliers_products_record_array as $suppliers_products_record) {
                $supplier_id = (int) (isset($suppliers_products_record['suppliers_id']) ? $suppliers_products_record['suppliers_id'] : 0);
                $uprid = isset($suppliers_products_record['uprid']) ? $suppliers_products_record['uprid'] : 0;
                unset($suppliers_products_record['products_id']);
                unset($suppliers_products_record['suppliers_id']);
                if ($supplier_id > 0 && !empty($uprid)) {
                    $suppliers_product_class = \common\models\Suppliers_Products::find()->where(['products_id' => $this->product_id, 'uprid' => $uprid, 'suppliers_id' => $supplier_id])->one();
                    if (!$suppliers_product_class instanceof \common\models\Suppliers_Products) {
                        $suppliers_product_class = new \common\models\Suppliers_Products();
                        $suppliers_product_class->load_default_values();
                        $suppliers_product_class->products_id = $this->product_id;
                        $suppliers_product_class->uprid = $uprid;
                        $suppliers_product_class->suppliers_id = $supplier_id;
                    }
                    $suppliers_product_class->set_attributes($suppliers_products_record, false);
                    if ($suppliers_product_class->save(false)) {
                    } else {
                        $this->message_add($suppliers_product_class->get_error_summary(true));
                    }
                    unset($suppliers_product_class);
                }
                unset($uprid);
                unset($supplier_id);
            }
            unset($suppliers_products_record);
            /**
             * Warehouses Products
             */
            foreach ($this->warehouses_products_record_array as $warehouses_products_record) {
                $warehouse_id = (int) (isset($warehouses_products_record['warehouse_id']) ? $warehouses_products_record['warehouse_id'] : 0);
                $supplier_id = (int) (isset($warehouses_products_record['suppliers_id']) ? $warehouses_products_record['suppliers_id'] : 0);
                $location_id = (int) (isset($warehouses_products_record['location_id']) ? $warehouses_products_record['location_id'] : 0);
                $uprid = isset($warehouses_products_record['products_id']) ? $warehouses_products_record['products_id'] : $this->product_id;
                unset($warehouses_products_record['products_id']);
                unset($warehouses_products_record['warehouse_id']);
                unset($warehouses_products_record['suppliers_id']);
                unset($warehouses_products_record['location_id']);
                unset($warehouses_products_record['prid']);
                if ($warehouse_id > 0 && $supplier_id > 0 && !empty($uprid)) {
                    $warehouse_product_class = \common\models\Warehouses_Products::find()->where(['products_id' => $uprid, 'suppliers_id' => $supplier_id, 'warehouse_id' => $warehouse_id, 'location_id' => $location_id])->one();
                    if (!$warehouse_product_class instanceof \common\models\Warehouses_Products) {
                        $warehouse_product_class = new \common\models\Warehouses_Products();
                        $warehouse_product_class->load_default_values();
                        $warehouse_product_class->products_id = $uprid;
                        $warehouse_product_class->suppliers_id = $supplier_id;
                        $warehouse_product_class->warehouse_id = $warehouse_id;
                        $warehouse_product_class->location_id = $location_id;
                        $warehouse_product_class->prid = $this->product_id;
                    }
                    $warehouse_product_class->set_attributes($warehouses_products_record, false);
                    if ($warehouse_product_class->save(false)) {
                    } else {
                        $this->message_add($warehouse_product_class->get_error_summary(true));
                    }
                    unset($warehouse_product_class);
                }
                unset($uprid);
                unset($location_id);
                unset($supplier_id);
                unset($warehouse_id);
            }
            unset($warehouses_products_record);
            if ($ext_scl = \common\helpers\Acl::check_extension_allowed('StockControl', 'allowed')) {
                $ext_scl::update_api_product_save($this);
            }
            // ...
            // OLD SEO REDIRECT
            $seo_model = \common\helpers\Extensions::get_model('SeoRedirectsNamed', 'SeoRedirectsNamed');
            if (!empty($seo_model)) {
                foreach ($this->old_seo_redirect_array as $seo_redirect_array) {
                    try {
                        $platform_id = (int) (isset($seo_redirect_array['platform_id']) ? $seo_redirect_array['platform_id'] : 0);
                        if ($platform_id > 0) {
                            $language_id = (int) (isset($seo_redirect_array['language_id']) ? $seo_redirect_array['language_id'] : 0);
                            if (isset($seo_redirect_array['language_code'])) {
                                $language_id = $this->get_language_id_by_code($seo_redirect_array['language_code'], $language_id);
                            }
                            $search_array = ['platform_id' => $platform_id, 'language_id' => $language_id, 'redirects_type' => 'product', 'owner_id' => $this->product_id, 'old_seo_page_name' => $seo_redirect_array['old_seo_page_name']];
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
            // SPECIALS
            foreach ($this->specials_array as $special) {
                try {
                    $special_id = $special['specials_id'] ?? null;
                    unset($special['products_id']);
                    $search_array = ['products_id' => $this->product_id];
                    $special_record = null;
                    if ($special_id > 0) {
                        $search_array['specials_id'] = $special_id;
                        $special_record = \common\models\Specials::find_one($search_array);
                    }
                    if (empty($special_record)) {
                        $special_record = new \common\models\Specials();
                        $special_record->load_default_values();
                        $special_record->set_attributes($search_array);
                    }
                    $special_record->set_attributes($special);
                    $special_record->save();
                    $search_prices_array = ['specials_id' => $special_record->specials_id, 'groups_id' => 0, 'currencies_id' => 0];
                    $special_prices_record = \common\models\Specials_Prices::find_one($search_prices_array);
                    if (empty($special_prices_record)) {
                        $special_prices_record = new \common\models\Specials_Prices();
                        $special_prices_record->load_default_values();
                        $special_prices_record->set_attributes($search_prices_array);
                    }
                    $special_prices_record->specials_new_products_price = $special_record->specials_new_products_price;
                    $special_prices_record->save();
                } catch (\Throwable $exc) {
                    \Yii::warning($exc->get_message() . "\n" . $exc->get_trace_as_string());
                }
                unset($special_record);
                unset($search_array);
            }
            // END OF SPECIALS
            $return = $this->product_id;
        } else {
            $this->message_add($product_class->get_error_summary(true));
        }
        unset($product_class);
        unset($is_replace);
        return $return;
    }
    private function find_image_id($orig_filename)
    {
        $check = \common\models\Products_Images::find()->select(['pi.products_images_id'])->from(\common\models\Products_Images::table_name() . ' pi')->left_join(\common\models\Products_Images_Description::table_name() . ' pid', 'pi.products_images_id = pid.products_images_id')->where(['pi.products_id' => $this->product_id, 'pid.language_id' => 0, 'pid.orig_file_name' => $orig_filename])->as_array()->one();
        return $check['products_images_id'] ?? null;
    }
    private function attach_new_image($new_products_images)
    {
        if ($this->product_id == 0) {
            return false;
        }
        $orig_filename = (string) $new_products_images['file_name'];
        $products_images_id = $this->find_image_id($orig_filename);
        if ($products_images_id > 0) {
            return false;
        }
        try {
            $image_location = DIR_FS_CATALOG . 'images' . DIRECTORY_SEPARATOR . 'products' . DIRECTORY_SEPARATOR . $this->product_id . DIRECTORY_SEPARATOR;
            if (!file_exists($image_location)) {
                mkdir($image_location, 0777, true);
            }
            $imgdata = file_get_contents($new_products_images['file_url']);
            if ($imgdata !== false) {
                $language_id = (int) \Yii::$app->settings->get('languages_id');
                $platform_id = \common\classes\platform::default_id();
                $hash_name = md5($orig_filename . '_' . date('dmYHis') . '_' . microtime(true));
                $products_images_class = new \common\models\Products_Images();
                $products_images_class->load_default_values();
                $products_images_class->default_image = 1;
                $products_images_class->image_status = 1;
                $products_images_class->products_id = $this->product_id;
                if (!$products_images_class->save(false)) {
                    $this->message_add($products_images_class->get_error_summary(true));
                    return false;
                }
                $image_id = $products_images_class->products_images_id;
                unset($products_images_class);
                $image_location .= $image_id . DIRECTORY_SEPARATOR;
                if (!file_exists($image_location)) {
                    mkdir($image_location, 0777, true);
                }
                $new_name = $image_location . $hash_name;
                $fp = fopen($new_name, 'w+');
                fwrite($fp, $imgdata);
                fclose($fp);
                unset($new_name);
                $filename = \common\helpers\Product::get_seo_name($this->product_id, $language_id, $platform_id);
                $upload_extension = strtolower(pathinfo($orig_filename, PATHINFO_EXTENSION));
                $filename .= '.' . $upload_extension;
                $product_name = \common\helpers\Product::get_products_name($this->product_id);
                $Images = new \common\classes\Images();
                $Images->create_images($this->product_id, $image_id, $hash_name, $filename, '');
                unset($Images);
                $products_images_description_class = new \common\models\Products_Images_Description();
                $products_images_description_class->load_default_values();
                $products_images_description_class->language_id = 0;
                $products_images_description_class->file_name = $filename;
                $products_images_description_class->hash_file_name = $hash_name;
                $products_images_description_class->orig_file_name = $orig_filename;
                $products_images_description_class->image_title = $product_name;
                $products_images_description_class->image_alt = $product_name;
                $products_images_description_class->products_images_id = (int) $image_id;
                if (!$products_images_description_class->save(false)) {
                    $this->message_add($products_images_description_class->get_error_summary(true));
                    return false;
                }
                unset($products_images_description_class);
                unset($product_name);
                unset($filename);
                unset($image_id);
                unset($hash_name);
                unset($platform_id);
                unset($language_id);
                return true;
            }
            unset($imgdata);
            unset($image_location);
        } catch (\Exception $e) {
            $this->message_add($e->get_message());
        }
        unset($orig_filename);
        return false;
    }
}