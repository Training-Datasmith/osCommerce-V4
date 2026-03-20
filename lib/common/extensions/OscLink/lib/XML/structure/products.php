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
return ['Header' => 'site/products', 'dependsOn' => ['site/languages', 'site/groups', 'site/currencies'], 'Data' => ['common\models\Products' => [
    //'where' => ['products_id'=>340],
    'xmlCollection' => 'Products>Product',
    'properties' => ['products_tax_class_id' => ['class' => 'IOMap', 'table' => 'tax_class', 'attribute' => 'tax_class_id'], 'manufacturers_id' => ['class' => 'IOMap', 'table' => 'manufacturers', 'attribute' => 'manufacturers_id'], 'products_image' => ['class' => 'IOAttachment', 'location' => '@images'], 'products_image_lrg' => ['class' => 'IOAttachment', 'location' => '@images'], 'products_image_sm_1' => ['class' => 'IOAttachment', 'location' => '@images'], 'products_image_xl_1' => ['class' => 'IOAttachment', 'location' => '@images'], 'products_image_sm_2' => ['class' => 'IOAttachment', 'location' => '@images'], 'products_image_xl_2' => ['class' => 'IOAttachment', 'location' => '@images'], 'products_image_sm_3' => ['class' => 'IOAttachment', 'location' => '@images'], 'products_image_xl_3' => ['class' => 'IOAttachment', 'location' => '@images'], 'products_image_sm_4' => ['class' => 'IOAttachment', 'location' => '@images'], 'products_image_xl_4' => ['class' => 'IOAttachment', 'location' => '@images'], 'products_image_sm_5' => ['class' => 'IOAttachment', 'location' => '@images'], 'products_image_xl_5' => ['class' => 'IOAttachment', 'location' => '@images'], 'products_image_sm_6' => ['class' => 'IOAttachment', 'location' => '@images'], 'products_image_xl_6' => ['class' => 'IOAttachment', 'location' => '@images']],
    'hideProperties' => ['vendor_id', 'products_image_med', 'last_xml_import', 'last_xml_export'],
    'withRelated' => [
        'productsDescriptions' => ['xmlCollection' => 'Descriptions>Description', 'properties' => ['language_id' => ['class' => 'IOLanguageMap'], 'products_name_soundex' => false, 'products_description_soundex' => false]],
        /*
                        'platform' => [
                            'xmlCollection' => 'AssignedPlatforms>AssignedPlatform',
                        ],
        */
        'categoriesList' => ['xmlCollection' => 'AssignedCategories>AssignedCategory', 'properties' => ['categories_id' => ['class' => 'IOPK', 'table' => 'categories', 'attribute' => 'categories_id']]],
        'productsPrices' => ['xmlCollection' => 'Prices>Price', 'properties' => ['groups_id' => ['class' => 'IOMap', 'table' => 'groups', 'attribute' => 'groups_id'], 'currencies_id' => ['class' => 'IOCurrencyMap']]],
        'productsAttributes' => ['xmlCollection' => 'Attributes>Attribute', 'properties' => ['options_id' => ['class' => 'IOPK', 'table' => 'products_options', 'attribute' => 'products_options_id'], 'options_values_id' => ['class' => 'IOPK', 'table' => 'products_options_values', 'attribute' => 'products_options_values_id']], 'beforeImportSave' => function ($model, $data) {
            if ($model instanceof \common\models\Products_Attributes && !$data->skipped) {
                if (isset($data->data['options_weight']) && is_numeric($data->data['options_weight'])) {
                    // osc2 mod
                    $model->products_attributes_weight = $data->data['options_weight'];
                }
            }
        }],
        'inventories' => ['xmlCollection' => 'Inventories>Inventory'],
        'productImagesList' => ['xmlCollection' => 'ImagesList>Image', 'properties' => []],
        /*
                        'productImagesList' => [
                            'xmlCollection' => 'ImagesList>Image',
                            'properties' => [
                                //'products_images_id' => false,
                            ],
                            'withRelated' => [
                                'description' => [
                                    'xmlCollection' => 'ImageDescriptions>ImageDescription',
                                    'properties' => [
                                        'imageUri' => [
                                            'class'=>'IOGalleryAttachment',
                                            'location'=>'@images',
                                            'options'=>['importVia'=>'File', 'noValue'=>true,'mergeLimitOptions'=>['url', 'checksum_sha1', 'file_info',]],
                                            'record' => true,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        'giftWrap' => [
                            'xmlCollection' => 'GiftWrap>GiftWrapPrice',
                            'properties' => [
                                'gw_id' => false,
                                'groups_id' => ['class'=>'IOMap', 'table'=>'groups','attribute'=>'groups_id'],
                                'currencies_id' => ['class'=>'IOCurrencyMap'],
                            ],
                        ],
        */
        'productsFeatured' => ['xmlCollection' => 'Featured>FeaturedInfo', 'where' => ['AND', ['affiliate_id' => 0], ['status' => '1']], 'properties' => ['featured_id' => false, 'featured_last_modified' => false, 'date_status_change' => false, 'status' => false, 'affiliate_id' => false]],
    ],
    'beforeDelete' => function ($model, $id) {
        \common\helpers\Product::remove_product($id);
        return 'deleted';
    },
    'beforeImportSave' => function ($model, $data) {
        if ($model instanceof \common\models\Products && !$data->skipped) {
            // seo
            if (empty($model->products_seo_page_name)) {
                $products_name_other_lang = $products_seo_other_lang = '';
                if (is_array($data->data['productsDescriptions']->data ?? null)) {
                    foreach ($data->data['productsDescriptions']->data as $io_description) {
                        if ($io_description->data['language_id']->language == 'en') {
                            $description_data['products_name'] = $io_description->data['products_name'] ?? '';
                            $description_data['products_seo_page_name'] = $io_description->data['products_seo_page_name'] ?? '';
                        } else {
                            if (!empty($io_description->data['products_name'])) {
                                $products_name_other_lang = $io_description->data['products_name'];
                            }
                            if (!empty($io_description->data['products_seo_page_name'])) {
                                $products_seo_other_lang = $io_description->data['products_seo_page_name'];
                            }
                        }
                    }
                }
                if (empty($description_data['products_name'])) {
                    $description_data['products_name'] = $products_name_other_lang;
                }
                if (empty($description_data['products_seo_page_name'])) {
                    $description_data['products_seo_page_name'] = $products_seo_other_lang;
                }
                $model->products_seo_page_name = \common\helpers\Seo::make_product_slug($description_data, $model);
            }
            $metric = \common\extensions\Osc_Link\Osc_Link::get_configuration_array('api_measurement') == 'metric';
            // inch, cm
            if (!isset($data->data['weight_cm'])) {
                $model->weight_cm = $model->products_weight / ($metric ? 1 : 2.20462);
            }
            if (!isset($data->data['weight_in'])) {
                $model->weight_in = $model->products_weight * ($metric ? 2.20462 : 1);
            }
            // inventory
            $model->without_inventory = !\common\helpers\Acl::check_extension_allowed('Inventory');
        }
    },
    'afterImport' => function ($model, $data) {
        if (isset($data->data['products_id']) && is_object($data->data['products_id'])) {
            $products_id = $data->data['products_id']->to_import_model();
            $log_prefix = sprintf('products(%s)', $products_id);
            $groups = [];
            if ($ext = \common\helpers\Acl::check_extension('UserGroups', 'getGroupsArray')) {
                $groups = $ext::get_groups_array();
            }
            $images_path = DIR_FS_CATALOG . DIR_WS_IMAGES;
            $additional_images = '';
            for ($i = 1; $i < 7; $i++) {
                $additional_images .= ", products_image_sm_{$i}, products_image_xl_{$i}";
            }
            $check_product_query = tep_db_query("SELECT products_id, products_model, products_image, products_image_med, products_image_lrg {$additional_images}, products_weight, products_seo_page_name FROM " . TABLE_PRODUCTS . " WHERE products_id = '" . (int) $products_id . "'");
            if ($product = tep_db_fetch_array($check_product_query)) {
                // {{
                $product_name = \common\helpers\Product::get_products_description($products_id);
                $product_import = [];
                $local_product = \common\api\models\AR\Products::find()->where(['products_id' => $products_id])->one();
                if ($local_product) {
                    $product_import['descriptions'] = ['*' => ['products_seo_page_name' => (string) $product['products_seo_page_name']]];
                }
                $inventory_allowed = \common\helpers\Acl::check_extension_allowed('Inventory');
                if (!$inventory_allowed) {
                    \common\models\Suppliers_Products::delete_all(['products_id' => $products_id]);
                    $supplier = new \common\models\Suppliers_Products();
                    $supplier->suppliers_model = $model->products_model ?? '';
                    $supplier->suppliers_id = \common\helpers\Suppliers::get_default_supplier_id();
                    $supplier->products_id = $products_id;
                    $supplier->uprid = $products_id;
                    $supplier->save();
                    \Osc_Link\Logger::get()->log_record($model, 'supplier changed to default because inventory extension is not installed');
                }
                // copypaste(
                $total_comb = 1;
                $count_values = [];
                $products_options_array = [];
                $products_options_query = tep_db_query('select distinct patrib.options_id as products_options_id from ' . TABLE_PRODUCTS_ATTRIBUTES . ' patrib where patrib.products_id = ' . (int) $products_id);
                while ($products_options = tep_db_fetch_array($products_options_query)) {
                    $options_id = $products_options['products_options_id'];
                    $products_options_array[$options_id] = [];
                    $count_values[$options_id] = 0;
                    $products_options_values_query = tep_db_query('select pa.products_attributes_id, pa.options_values_id as products_options_values_id, pa.options_values_price, pa.price_prefix from ' . TABLE_PRODUCTS_ATTRIBUTES . ' pa where pa.products_id = ' . (int) $products_id . " and pa.options_id = '" . (int) $options_id . "' order by pa.products_options_sort_order");
                    while ($products_options_values = tep_db_fetch_array($products_options_values_query)) {
                        $products_options_values['options_values_price'] = \common\helpers\Attributes::get_attributes_price($products_options_values['products_attributes_id'], 0, 0, $products_options_values['options_values_price']);
                        $products_options_array[$options_id][] = ['id' => $products_options_values['products_options_values_id']];
                        $products_options_array[$options_id][sizeof($products_options_array[$options_id]) - 1]['prefix'] = $products_options_values['price_prefix'];
                        $products_options_array[$options_id][sizeof($products_options_array[$options_id]) - 1]['price'][0] = $products_options_values['options_values_price'];
                        tep_db_query('insert ignore into ' . TABLE_PRODUCTS_ATTRIBUTES_PRICES . " set products_attributes_id = '" . (int) $products_options_values['products_attributes_id'] . "', groups_id = '0', currencies_id = '0', attributes_group_price = '" . tep_db_input($products_options_values['options_values_price']) . "'");
                        if (is_array($groups)) {
                            foreach ($groups as $group) {
                                $options_values_price = \common\helpers\Attributes::get_attributes_price($products_options_values['products_attributes_id'], 0, $group['groups_id'], $products_options_values['options_values_price']);
                                tep_db_query('insert ignore into ' . TABLE_PRODUCTS_ATTRIBUTES_PRICES . " set products_attributes_id = '" . (int) $products_options_values['products_attributes_id'] . "', groups_id = '" . (int) $group['groups_id'] . "', currencies_id = '0', attributes_group_price = '" . tep_db_input($options_values_price) . "'");
                                if ($options_values_price == -2) {
                                    $options_values_price = $products_options_values['options_values_price'];
                                }
                                $products_options_array[$options_id][sizeof($products_options_array[$options_id]) - 1]['price'][$group['groups_id']] = $options_values_price;
                            }
                        }
                        $count_values[$options_id]++;
                    }
                    $total_comb *= $count_values[$options_id];
                }
                if ($inventory_allowed && $total_comb < 5000) {
                    $products_price = $model->products_price;
                    for ($i = 0; $i < $total_comb; $i++) {
                        $num = $i;
                        $total_attributes_price = [];
                        $attributes_array = [];
                        $prefix = '+';
                        foreach ($products_options_array as $id => $array) {
                            $k = $num % $count_values[$id];
                            $attributes_array[$id] = $array[$k]['id'];
                            $total_attributes_price[0] = $total_attributes_price[0] ?? 0;
                            if ($total_attributes_price[0] != -1) {
                                $total_attributes_price[0] += $array[$k]['prefix'] != '-' ? $array[$k]['price'][0] : -$array[$k]['price'][0];
                            }
                            if ($total_attributes_price[0] == 0 && $array[$k]['prefix'] == '-') {
                                $prefix = '-';
                            }
                            foreach ($groups as $group) {
                                $total_attributes_price[$group['groups_id']] = $total_attributes_price[$group['groups_id']] ?? null;
                                if ($total_attributes_price[$group['groups_id']] != -1) {
                                    $total_attributes_price[$group['groups_id']] += $array[$k]['prefix'] != '-' ? $array[$k]['price'][$group['groups_id']] : -$array[$k]['price'][$group['groups_id']];
                                }
                            }
                            $num = (int) ($num / $count_values[$id]);
                        }
                        $uprid = \common\helpers\Inventory::normalize_id(\common\helpers\Inventory::get_uprid($products_id, $attributes_array));
                        $inventory = tep_db_fetch_array(tep_db_query('select inventory_id from ' . TABLE_INVENTORY . " where prid = '" . (int) $products_id . "' and products_id = '" . tep_db_input($uprid) . "'"));
                        if (!(($inventory['inventory_id'] ?? null) > 0)) {
                            tep_db_query('insert into ' . TABLE_INVENTORY . " set prid = '" . (int) $products_id . "', products_id = '" . tep_db_input($uprid) . "'");
                            $inventory = tep_db_fetch_array(tep_db_query('select inventory_id from ' . TABLE_INVENTORY . " where prid = '" . (int) $products_id . "' and products_id = '" . tep_db_input($uprid) . "'"));
                        }
                        $total_attributes_price[0] = $total_attributes_price[0] ?? 0;
                        if ($products_price == -1 || $total_attributes_price[0] == -1) {
                            tep_db_query('update ' . TABLE_INVENTORY . " set inventory_price = '-1', price_prefix = '+', inventory_full_price = '-1' where prid = '" . (int) $products_id . "' and products_id = '" . tep_db_input($uprid) . "'");
                        } else {
                            tep_db_query('update ' . TABLE_INVENTORY . " set inventory_price = '" . (float) abs($total_attributes_price[0]) . "', price_prefix = '" . ($total_attributes_price[0] < 0 ? '-' : $prefix) . "', inventory_full_price = '" . (float) ($products_price + $total_attributes_price[0]) . "' where prid = '" . (int) $products_id . "' and products_id = '" . tep_db_input($uprid) . "'");
                        }
                        foreach ($groups as $group) {
                            $products_price = \common\helpers\Product::get_products_price_for_edit($products_id, 0, $group['groups_id'], $products_price);
                            if ($products_price == -2) {
                                $products_price = $products_price;
                            }
                            tep_db_query('delete from ' . TABLE_INVENTORY_PRICES . " where prid = '" . (int) $products_id . "' and products_id = '" . tep_db_input($uprid) . "' and groups_id = '" . (int) $group['groups_id'] . "' and currencies_id = '0' and inventory_id = '" . (int) $inventory['inventory_id'] . "'");
                            $total_attributes_price[$group['groups_id']] = $total_attributes_price[$group['groups_id']] ?? null;
                            if ($products_price == -1 || $total_attributes_price[$group['groups_id']] == -1) {
                                tep_db_query('insert into ' . TABLE_INVENTORY_PRICES . " set inventory_id = '" . (int) $inventory['inventory_id'] . "', inventory_group_price  = '-1', price_prefix = '+', inventory_full_price = '-1', prid = '" . (int) $products_id . "', products_id = '" . tep_db_input($uprid) . "', groups_id = '" . (int) $group['groups_id'] . "', currencies_id = '0'");
                            } else {
                                tep_db_query('insert into ' . TABLE_INVENTORY_PRICES . " set inventory_id = '" . (int) $inventory['inventory_id'] . "', inventory_group_price  = '" . (float) abs($total_attributes_price[$group['groups_id']]) . "', price_prefix = '" . ($total_attributes_price[$group['groups_id']] < 0 ? '-' : '+') . "', inventory_full_price = '" . (float) ($products_price + $total_attributes_price[$group['groups_id']]) . "', prid = '" . (int) $products_id . "', products_id = '" . tep_db_input($uprid) . "', groups_id = '" . (int) $group['groups_id'] . "', currencies_id = '0'");
                            }
                        }
                    }
                }
                tep_db_query('UPDATE ' . TABLE_INVENTORY . " SET warehouse_stock_quantity = products_quantity where prid = '" . (int) $products_id . "' and products_quantity > 0");
                tep_db_query('INSERT IGNORE INTO ' . TABLE_WAREHOUSES_PRODUCTS . " (warehouse_id, products_id, prid, products_model, products_quantity, allocated_stock_quantity, temporary_stock_quantity, warehouse_stock_quantity, ordered_stock_quantity, suppliers_id) SELECT '" . (int) \common\helpers\Warehouses::get_default_warehouse() . "', products_id, prid, products_model, products_quantity, allocated_stock_quantity, temporary_stock_quantity, warehouse_stock_quantity, ordered_stock_quantity, '" . (int) \common\helpers\Suppliers::get_default_supplier_id() . "' FROM " . TABLE_INVENTORY . " where prid = '" . (int) $products_id . "'");
                tep_db_query('UPDATE ' . TABLE_PRODUCTS . " SET warehouse_stock_quantity = products_quantity where products_id = '" . (int) $products_id . "' and products_quantity > 0");
                tep_db_query('INSERT IGNORE INTO ' . TABLE_WAREHOUSES_PRODUCTS . " (warehouse_id, products_id, prid, products_model, products_quantity, allocated_stock_quantity, temporary_stock_quantity, warehouse_stock_quantity, ordered_stock_quantity, suppliers_id) SELECT '" . (int) \common\helpers\Warehouses::get_default_warehouse() . "', products_id, products_id as prid, products_model, products_quantity, allocated_stock_quantity, temporary_stock_quantity, warehouse_stock_quantity, ordered_stock_quantity, '" . (int) \common\helpers\Suppliers::get_default_supplier_id() . "' FROM " . TABLE_PRODUCTS . " where products_id = '" . (int) $products_id . "'");
                // }}
                $remove_images = [];
                $image_location = $images_path . 'products' . DIRECTORY_SEPARATOR . $product['products_id'] . DIRECTORY_SEPARATOR;
                if (!empty($product['products_image_lrg']) && is_file($images_path . $product['products_image_lrg'])) {
                    $orig_file = $product['products_image_lrg'];
                } elseif (!empty($product['products_image_med']) && is_file($images_path . $product['products_image_med'])) {
                    $orig_file = $product['products_image_med'];
                } else {
                    $orig_file = $product['products_image'];
                }
                //$check = tep_db_fetch_array(tep_db_query("select pi.products_images_id from " . TABLE_PRODUCTS_IMAGES . " pi, " . TABLE_PRODUCTS_IMAGES_DESCRIPTION . " pid where pi.products_id = '" . (int) $product['products_id'] . "' and pi.products_images_id = pid.products_images_id and pid.language_id = '0' and pid.orig_file_name like '%" . tep_db_input($orig_file) . "'"));
                $tmp_name = $images_path . $orig_file;
                if (!empty($orig_file) && !is_file($tmp_name)) {
                    \Osc_Link\Logger::printf('products(%s): image missed %s', $product['products_id'], $orig_file);
                    file_put_contents($images_path . 'products/missing_img.txt', 'pid:' . $product['products_id'] . ":{$orig_file}\n", FILE_APPEND);
                }
                if (!empty($orig_file) && is_file($tmp_name)) {
                    if ($local_product) {
                        $remove_images[] = $tmp_name;
                        $file_name = pathinfo($tmp_name, PATHINFO_BASENAME);
                        \Osc_Link\Logger::printf('products(%s): add default image %s', $product['products_id'], $orig_file);
                        $product_import['images'][] = ['default_image' => 1, 'image_status' => 1, 'sort_order' => 0, 'image_description' => ['00' => ['image_source_url' => $tmp_name, 'orig_file_name' => $file_name, 'file_name' => $file_name, 'alt_file_name' => $file_name, 'image_title' => (string) $product_name]]];
                    }
                }
                for ($i = 1; $i < 7; $i++) {
                    if (!empty($product['products_image_xl_' . $i]) && is_file($images_path . $product['products_image_xl_' . $i])) {
                        $orig_file = $product['products_image_xl_' . $i];
                    } else {
                        $orig_file = $product['products_image_sm_' . $i];
                    }
                    if (!empty($orig_file) && !is_file($tmp_name)) {
                        \Osc_Link\Logger::printf('products(%s): image missed %s', $product['products_id'], $orig_file);
                        file_put_contents($images_path . 'products/missing_img.txt', 'pid:' . $product['products_id'] . ":{$orig_file}\n", FILE_APPEND);
                    }
                    //$check = tep_db_fetch_array(tep_db_query("select pi.products_images_id from " . TABLE_PRODUCTS_IMAGES . " pi, " . TABLE_PRODUCTS_IMAGES_DESCRIPTION . " pid where pi.products_id = '" . (int) $product['products_id'] . "' and pi.products_images_id = pid.products_images_id and pid.language_id = '0' and pid.orig_file_name like '%" . tep_db_input($orig_file) . "'"));
                    $tmp_name = $images_path . $orig_file;
                    if (!empty($orig_file) && is_file($tmp_name)) {
                        if ($local_product) {
                            $remove_images[] = $tmp_name;
                            $file_name = pathinfo($tmp_name, PATHINFO_BASENAME);
                            $product_import['images'][] = ['default_image' => 0, 'image_status' => 1, 'sort_order' => $i, 'image_description' => ['00' => ['image_source_url' => $tmp_name, 'orig_file_name' => $file_name, 'file_name' => $file_name, 'alt_file_name' => $file_name, 'image_title' => (string) $product_name]]];
                        }
                    }
                }
                // load products_images
                if ($local_product && isset($data->data['productImagesList']->data) && is_array($data->data['productImagesList']->data)) {
                    $sort_order = 8;
                    foreach ($data->data['productImagesList']->data as $obj) {
                        if (isset($obj->data['image']) && is_object($obj->data['image']) && $obj->data['image'] instanceof \Osc_Link\XML\Io_Attachment) {
                            $url = $obj->data['image']->url;
                            $original_file = $obj->data['image']->value;
                            $description = $obj->data['htmlcontent'];
                            $local_file = \Osc_Link\XML\Io_Core::get()->get_local_location('@images/' . $original_file);
                            if (file_exists($local_file)) {
                                $local_file .= '_' . microtime();
                                // uniq file name
                            }
                            $success_download = \Osc_Link\XML\Io_Core::get()->download($url, $local_file, $log_prefix);
                            if ($success_download) {
                                $remove_images[] = $local_file;
                                $file_name = pathinfo($original_file, PATHINFO_BASENAME);
                                $product_import['images'][] = ['default_image' => 0, 'image_status' => 1, 'sort_order' => $sort_order++, 'image_description' => ['00' => ['image_source_url' => $local_file, 'orig_file_name' => $file_name, 'file_name' => $file_name, 'alt_file_name' => $file_name, 'image_title' => $description ?? (string) $product_name]]];
                                \Osc_Link\Logger::printf('%s: additional products_image(%s) was added', $log_prefix, $original_file);
                            }
                        }
                    }
                }
                if ($local_product) {
                    $local_product->import_array($product_import);
                    try {
                        $local_product->save(false);
                    } catch (\Exception $e) {
                        \Osc_Link\Logger::printf('%s: Error while saving ER model: %s', $log_prefix, $e->get_message());
                    }
                    if (is_array($remove_images ?? null)) {
                        foreach ($remove_images as $fn) {
                            if (file_exists($fn)) {
                                \Osc_Link\Logger::printf('File removed: %s', $fn);
                                unlink($fn);
                            }
                        }
                    }
                }
                unset($local_product);
            }
        }
    },
]], 'covered_tables' => [
    'products',
    'products_description',
    'platforms_products',
    'products_to_categories',
    'products_images',
    'products_images_description',
    'products_prices',
    'products_attributes',
    'products_attributes_prices',
    'inventory',
    'inventory_prices',
    'suppliers_products',
    'warehouses_products',
    //'gift_wrap_products',
    'featured',
]];