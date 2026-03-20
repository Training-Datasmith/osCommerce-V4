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

use backend\models\Product_Name_Decorator;
use common\models\Products;
use Yii;
use yii\base\Widget;
class Products_Box extends Widget
{
    public $manager;
    public $cart;
    public $post = [];
    public function init()
    {
        parent::init();
    }
    public function search($search_text)
    {
        //$searchText = urldecode($searchText);
        $_languages = \Yii::$app->settings->get('languages_id');
        $search_builder = new \common\components\Search_Builder('simple');
        $search_builder->set_search_in_desc(SEARCH_IN_DESCRIPTION == 'True');
        $search_builder->set_search_internal(true);
        if (defined('BACKEND_SEARCH_ON_ALL_LANGUAGES') && BACKEND_SEARCH_ON_ALL_LANGUAGES == 'True') {
            $_languages = \common\helpers\Language::get_languages();
            $_languages = \yii\helpers\Array_Helper::get_column($_languages, 'id');
            \Yii::$container->set('_languages', (object) $_languages);
        }
        $search_builder->search_in_property = false;
        $search_builder->search_in_attributes = false;
        $search_builder->parse_keywords($search_text);
        /*
                $searchBuilder->prepareRequest($searchText);
        
                $filters_where = $searchBuilder->getProductsArray(false);
                /**/
        $manager = $this->manager;
        $search_builder->relevance_order = true;
        $products_query = Products::find()->distinct()->alias('p')->select(['p.products_id', 'p.products_model'])->where(['p.products_status' => 1]);
        $ext = \common\helpers\Acl::check_extension_allowed('PlainProductsDescription', 'allowed');
        if ($ext && $ext::is_enabled()) {
            $products_query->with(['productsDescriptions' => function ($query) use ($manager, $_languages) {
                $_pl = array_unique([intval(\Yii::$app->get('platform')->config($manager->get_platform_id())->get_platform_to_description()), intval(\common\classes\platform::default_id())]);
                $query->on_condition(['language_id' => is_array($_languages) ? $_languages : (int) $_languages, 'platform_id' => $_pl])->add_select('products_id, language_id, platform_id, products_internal_name, products_name')->add_select(['main' => new \yii\db\Expression('platform_id=1')])->add_select('products_description, products_url, products_head_title_tag, products_description_short, products_seo_page_name, products_h1_tag, products_h2_tag, products_h3_tag, products_internal_name, platform_id, products_id, language_id, products_image_alt_tag_mask, products_head_desc_tag, products_image_title_tag_mask');
                if (count($_pl) > 1) {
                    $query->add_order_by(new \yii\db\Expression("FIELD(pd1.platform_id, {$manager->get_platform_id()}) desc"));
                }
            }]);
        } else {
            $products_query->join_with('manufacturer m', false)->join_with(['productsDescriptions pd' => function ($query) use ($manager, $_languages) {
                $query->on_condition(['pd.language_id' => is_array($_languages) ? $_languages : (int) $_languages, 'pd.platform_id' => [\common\classes\platform::default_id()]]);
            }])->join_with(['productsDescriptions pd1' => function ($query) use ($manager, $_languages) {
                $_pl = array_unique([intval(\Yii::$app->get('platform')->config($manager->get_platform_id())->get_platform_to_description()), intval(\common\classes\platform::default_id())]);
                $query->on_condition(['pd1.language_id' => is_array($_languages) ? $_languages : (int) $_languages, 'pd1.platform_id' => $_pl]);
                if (count($_pl) > 1) {
                    $query->add_order_by(new \yii\db\Expression("FIELD(pd1.platform_id, {$manager->get_platform_id()}) desc"));
                }
            }])->add_select(['pd1.products_name', Product_Name_Decorator::instance()->listing_query_expression('pd', 'pd1') . ' as products_name']);
        }
        if (empty($this->post['suggest']) && \common\helpers\Settings::is_backend_search_aggregate_product_type()) {
            $products_query->add_select('p.is_bundle, p.products_pctemplates_id, p.without_inventory')->add_select(new \yii\db\Expression('EXISTS (SELECT 1 FROM products_attributes pa WHERE pa.products_id = p.products_id) as attr_exists'));
        }
        $products_query->sql_products_model_to_platform($this->manager->get_platform_id());
        $search_builder->add_products_restriction($products_query);
        //        $productsQuery->andWhere($filters_where);
        //        \Yii::warning(" #### " .print_r($productsQuery->createCommand()->rawSql, true), 'TLDEBUG');
        $products = $products_query->all();
        $tree = [];
        if (empty($this->post['suggest'])) {
            //search for tree
            if ($products) {
                $pathes = [];
                if (BACKEND_SEARCH_AGREGATE_PRODUCT_DATA != 'Standard') {
                    $tree = $this->build_plain($this->manager->get_platform_id(), $products, $search_builder);
                } else {
                    foreach ($products as $product) {
                        $path = \common\helpers\Product::get_product_path($product->products_id);
                        $_path_array = explode('_', $path);
                        foreach ($_path_array as $_path_id) {
                            $priorities[$_path_id] = ($priorities[$_path_id] ?? 0) + 1;
                        }
                    }
                    $products = \yii\helpers\Array_Helper::get_column($products, 'products_id');
                    if (!isset($priorities[0])) {
                        $priorities[0] = 0;
                    }
                    arsort($priorities);
                    //put $pathes  in priority order
                    foreach ($priorities as $_key => $_val) {
                        $pathes[$_key] = $_key;
                    }
                    $tree = $this->get_children(0, $pathes, $products);
                }
            }
        } else {
            //search for suggest
            $currencies = Yii::$container->get('currencies');
            foreach ($products_query->limit(20)->all() as $product) {
                $ins = \common\models\Product\Price::get_instance($product->products_id);
                $tree[] = ['id' => $product->products_id, 'text' => $product->products_descriptions[0]->get_backend_listing_name(), 'price' => $currencies->display_price($ins->get_product_price(['qty' => 1]), 0, 1)];
            }
        }
        return json_encode($tree);
    }
    public function get_children($top, $pathes, $products)
    {
        if (!$pathes) {
            return;
        }
        $level = $this->build_tree($this->manager->get_platform_id(), $top, $products);
        //children for $path
        $trees = $this->skip($level, $pathes, $products, $top);
        //clear level for n
        foreach ($trees as &$tree) {
            if (!empty($tree['folder'])) {
                $children = $this->get_children(substr($tree['key'], 1), $pathes, $products);
                if ($children) {
                    $tree['children'] = $children;
                }
            }
        }
        return $trees;
    }
    public function skip($branch, $only, $products, $cid = null)
    {
        $new_branch = $branch;
        if (is_array($branch)) {
            foreach ($branch as $key => $item) {
                $branch[$key]['selected'] = 0;
                if (!empty($item['folder'])) {
                    $cid = str_replace('c', '', $item['key']);
                    if (!in_array($cid, $only) || !\common\helpers\Categories::products_in_category_count($cid)) {
                        unset($branch[$key]);
                    } else {
                        $branch[$key]['expanded'] = 1;
                        $branch[$key]['lazy'] = 0;
                    }
                } else {
                    $pid = preg_replace("/^p(\\d+)_(.*)/", '$1', $item['key']);
                    if (!in_array($pid, $products)) {
                        unset($branch[$key]);
                    } else {
                        unset($branch[$key]['children']);
                    }
                }
            }
            //revert position of categories in result array as it path(only) priorities, if products in list exists , no changes
            $new_branch = $branch;
            if (!isset($pid) && count($branch) > 1 && is_array($only) && count($only) > 0) {
                unset($new_branch);
                foreach ($only as $key) {
                    $_in_branch_pos = -1;
                    foreach ($branch as $_idx => $_category) {
                        if ($_category['key'] == 'c' . $key) {
                            $_in_branch_pos = $_idx;
                            break;
                        }
                    }
                    if ($_in_branch_pos > -1) {
                        $new_branch[] = $branch[$_in_branch_pos];
                    }
                }
            }
        }
        return array_values($new_branch);
    }
    public function build_plain($platform_id, $products = [], $search_builder = null)
    {
        $_init_data = [];
        $product_ids = \yii\helpers\Array_Helper::get_column($products, 'products_id');
        $manager = $this->manager;
        $_assigned_categories = \yii\helpers\Array_Helper::map((new yii\db\Query())->select('p2c.products_id,p2c.categories_id')->from(\common\models\Products2Categories::table_name() . ' p2c ')->inner_join(\common\models\Platforms_Categories::table_name() . ' pc ', ' (pc.categories_id=p2c.categories_id and pc.platform_id in (' . join(',', [intval(\Yii::$app->get('platform')->config($manager->get_platform_id())->get_platform_to_description()), intval(\common\classes\platform::default_id())]) . ')) ')->where(['products_id' => $product_ids])->all(), 'categories_id', 'categories_id', 'products_id');
        $p_all = Products::find()->where(['products_id' => $product_ids])->as_array()->index_by('products_id')->all();
        $container = Yii::$container->get('products');
        $_current_langv_id = \Yii::$app->settings->get('languages_id');
        foreach ($products as $product) {
            $categories = [];
            if (isset($_assigned_categories[$product->products_id])) {
                $categories = $_assigned_categories[$product->products_id];
            }
            if (count($product->products_descriptions) > 1 && !is_array($_current_langv_id) && (int) $_current_langv_id > 0) {
                foreach ($product->products_descriptions as $_product_description) {
                    if ($_product_description->language_id == (int) $_current_langv_id) {
                        $description = !empty($_product_description->products_internal_name) ? $_product_description->products_internal_name : $_product_description->products_name ?? '';
                        $tmp_desc = $_product_description->attributes;
                        break;
                    }
                }
                if (empty($description)) {
                    $k = 'products_internal_name';
                    $_pda = json_decode(json_encode($product->products_descriptions), true);
                    $tmp_name = array_values(array_filter($_pda, function ($el) {
                        return !empty($el['products_internal_name']);
                    }));
                    if (empty($tmp_name)) {
                        $k = 'products_name';
                        $tmp_name = array_values(array_filter($_pda, function ($el) {
                            return !empty($el['products_name']);
                        }));
                    }
                    if (!empty($tmp_name)) {
                        $description = $tmp_name[0][$k];
                        $tmp_desc = $tmp_name[0];
                    }
                }
            } else {
                $description = !empty($product->products_descriptions[0]->products_internal_name) ? $product->products_descriptions[0]->products_internal_name : $product->products_descriptions[0]->products_name;
                $tmp_desc = $product->products_descriptions[0]->attributes;
            }
            if (!empty($tmp_desc)) {
                $p_info = $p_all[$product->products_id] + $tmp_desc;
            } else {
                $p_info = $p_all[$product->products_id];
            }
            $container->load_products($p_info);
            unset($p_all[$product->products_id]);
            unset($p_info);
            $products_model = $product->products_model;
            if (!empty($search_builder) && !empty($search_builder->get_parsed_keywords())) {
                $description = \common\helpers\Output::highlight_text($description, $search_builder->get_parsed_keywords());
                $products_model = \common\helpers\Output::highlight_text($product->products_model, $search_builder->get_parsed_keywords());
            }
            $_product = ['key' => 'p' . $product->products_id . (count($categories) > 0 ? '_' . key($categories) : ''), 'products_id' => $product->products_id, 'model' => $products_model, 'title' => $description];
            $_product = \common\helpers\Categories::set_product_data($_product);
            $_init_data[] = $_product;
        }
        return $_init_data;
    }
    public function build_tree($platform_id, $top = 0, $products = [])
    {
        return \common\helpers\Categories::load_tree_slice($platform_id, $top, true, '', true, true, true);
    }
    public function tree()
    {
        $do = $this->post['do'];
        $platform_id = $this->post['platform_id'];
        $response_data = [];
        if ($do == 'missing_lazy') {
            $category_id = $this->post['id'];
            $selected = $this->post['selected'];
            $req_selected_data = $this->post['selected_data'] ?? '';
            $selected_data = json_decode($req_selected_data, true);
            $products_id = 0;
            if (!is_array($selected_data)) {
                $selected_data = json_decode($selected_data, true);
            }
            if (is_array($selected_data)) {
                $products_id = (int) $selected_data[0];
            }
            if (substr($category_id, 0, 1) == 'c') {
                $category_id = intval(substr($category_id, 1));
            }
            $response_data['tree_data'] = $this->build_tree($platform_id, $category_id);
            foreach ($response_data['tree_data'] as $_idx => $_data) {
                $response_data['tree_data'][$_idx]['selected'] = preg_match("/^p{$products_id}_*/", $_data['key']);
            }
            $response_data = $response_data['tree_data'];
        }
        if ($do == 'update_selected') {
            $id = $this->post['id'];
            $selected = $this->post['selected'];
            $select_children = $this->post['select_children'];
            $req_selected_data = $this->post['selected_data'];
            $selected_data = json_decode($req_selected_data, true);
            if (!is_array($selected_data)) {
                $selected_data = json_decode($selected_data, true);
            }
            if (substr($id, 0, 1) == 'p') {
                list($ppid, $cat_id) = explode('_', $id, 2);
                if ($selected) {
                    // check parent categories
                    $parent_ids = [(int) $cat_id];
                    \common\helpers\Categories::get_parent_categories($parent_ids, $parent_ids[0], false);
                    foreach ($parent_ids as $parent_id) {
                        if (!isset($selected_data['c' . (int) $parent_id])) {
                            $response_data['update_selection']['c' . (int) $parent_id] = true;
                            $selected_data['c' . (int) $parent_id] = 'c' . (int) $parent_id;
                        }
                    }
                    if (!isset($selected_data[$id])) {
                        $response_data['update_selection'][$id] = true;
                        $selected_data[$id] = $id;
                    }
                } else if (isset($selected_data[$id])) {
                    $response_data['update_selection'][$id] = false;
                    unset($selected_data[$id]);
                }
            } elseif (substr($id, 0, 1) == 'c') {
                $cat_id = (int) substr($id, 1);
                if ($selected) {
                    $parent_ids = [(int) $cat_id];
                    \common\helpers\Categories::get_parent_categories($parent_ids, $parent_ids[0], false);
                    foreach ($parent_ids as $parent_id) {
                        if (!isset($selected_data['c' . (int) $parent_id])) {
                            $response_data['update_selection']['c' . (int) $parent_id] = true;
                            $selected_data['c' . (int) $parent_id] = 'c' . (int) $parent_id;
                        }
                    }
                    if ($select_children) {
                        $children = [];
                        $this->tep_get_category_children($children, $platform_id, $cat_id);
                        foreach ($children as $child_key) {
                            if (!isset($selected_data[$child_key])) {
                                $response_data['update_selection'][$child_key] = true;
                                $selected_data[$child_key] = $child_key;
                            }
                        }
                    }
                    if (!isset($selected_data[$id])) {
                        $response_data['update_selection'][$id] = true;
                        $selected_data[$id] = $id;
                    }
                } else {
                    $children = [];
                    $this->tep_get_category_children($children, $platform_id, $cat_id);
                    foreach ($children as $child_key) {
                        if (isset($selected_data[$child_key])) {
                            $response_data['update_selection'][$child_key] = false;
                            unset($selected_data[$child_key]);
                        }
                    }
                    if (isset($selected_data[$id])) {
                        $response_data['update_selection'][$id] = false;
                        unset($selected_data[$id]);
                    }
                }
            }
            $response_data['selected_data'] = $selected_data;
        }
        return json_encode($response_data);
    }
    private function tep_get_category_children(&$children, $platform_id, $categories_id)
    {
        if (!is_array($children)) {
            $children = [];
        }
        foreach ($this->load_tree_slice($platform_id, $categories_id) as $item) {
            $key = $item['key'];
            $children[] = $key;
            if ($item['folder']) {
                $this->tep_get_category_children($children, $platform_id, intval(substr($item['key'], 1)));
            }
        }
    }
    public function run()
    {
        if (isset($this->post['do'])) {
            return $this->tree();
        } elseif (isset($this->post['search']) && !empty($this->post['search'])) {
            return $this->search($this->post['search']);
        }
        $params['searchsuggest'] = \common\models\Products::find()->inner_join_with('platform')->where(['platform_id' => $this->manager->get_platform_id(), 'products_status' => 1])->count() > 5000;
        if (!$params['searchsuggest']) {
            $category_tree_array = $this->build_tree($this->manager->get_platform_id(), 0);
        }
        $params['rates'] = $this->manager->get_order_tax_rates();
        $params['category_tree_array'] = $category_tree_array;
        $params['queryParams'] = array_merge(['editor/show-basket'], Yii::$app->request->get_query_params());
        $params['tree_server_url'] = array_merge(['editor/load-tree', 'platform_id' => $this->manager->get_platform_id()], Yii::$app->request->get_query_params());
        $params['product_display_entities'] = json_encode(defined('BACKEND_SEARCH_SHOW_DATA') ? array_fill_keys(array_map('trim', explode(',', BACKEND_SEARCH_SHOW_DATA)), true) : []);
        $params['product_display_format'] = defined('BACKEND_SEARCH_AGREGATE_PRODUCT_DATA') ? BACKEND_SEARCH_AGREGATE_PRODUCT_DATA : 'Standard';
        $params['min_search_text_lenght'] = defined('BACKEND_MSEARCH_WORD_LENGTH') && (int) BACKEND_MSEARCH_WORD_LENGTH > 0 ? (int) BACKEND_MSEARCH_WORD_LENGTH - 1 : 2;
        $totals = [];
        foreach ($this->manager->get_total_output(false) as $total) {
            $totals[$total['code']] = $total;
        }
        $params['totals'] = $totals;
        return $this->render('products-box', $params);
    }
}