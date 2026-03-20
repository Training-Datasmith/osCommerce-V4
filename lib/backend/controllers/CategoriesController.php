<?php

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
namespace backend\controllers;

use backend\components\Sources_Search_Trait;
use backend\design\Uploads;
use backend\models\EP\Messages;
use backend\models\Product_Edit\View_Attributes;
use backend\models\Product_Edit\View_Import_Export;
use backend\models\Product_Edit\View_Price_Data;
use backend\models\Product_Edit\View_Stock_Info;
use backend\models\Product_Name_Decorator;
use common\classes\Images;
use common\helpers\Categories;
use common\helpers\Html;
use common\helpers\Manufacturers;
use common\helpers\Seo;
use common\models\Categories_Images;
use common\models\Image_Types;
use common\models\Product\Products_Notes;
use common\models\Suppliers;
use common\models\Suppliers_Products;
use common\services\Products_Documents_Service;
use common\services\Products_Notes_Service;
use Yii;
use yii\db\Expression;
use yii\helpers\File_Helper;
use yii\helpers\Url;
/**
 * default controller to handle user requests.
 */
class Categories_Controller extends Sceleton
{
    use Sources_Search_Trait;
    public $acl = ['BOX_HEADING_CATALOG', 'BOX_CATALOG_CATEGORIES_PRODUCTS'];
    public $default_collapsed = false;
    /**
     * @var \backend\models\ProductEdit\TabAccess
     */
    public $product_edit_tab_access;
    /** @var ProductsNotesService */
    private $products_notes_service;
    /** @var ProductsDocumentsService */
    private $products_documents_service;
    public function __construct($id, $module = null, Products_Notes_Service $products_notes_service = null, Products_Documents_Service $products_documents_service = null, array $config = [])
    {
        parent::__construct($id, $module, $config);
        $this->products_notes_service = $products_notes_service;
        $this->products_documents_service = $products_documents_service;
    }
    public function init()
    {
        parent::init();
        $this->product_edit_tab_access = new \backend\models\Product_Edit\Tab_Access();
    }
    private function get_category_tree($parent_id = '0', $platform_id = false)
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $filter_by_platform = [];
        if (is_array($platform_id)) {
            $filter_by_platform = $platform_id;
        } else {
            if (!$platform_param = Yii::$app->request->get('platform', false)) {
                $form_filter = Yii::$app->request->get('filter', '');
                $output = [];
                parse_str($form_filter, $output);
                if (isset($output['platform']) && is_array($output['platform'])) {
                    $platform_param = $output['platform'];
                }
            }
            if (isset($platform_param) && is_array($platform_param)) {
                foreach ($platform_param as $_platform_id) {
                    if ((int) $_platform_id > 0) {
                        $filter_by_platform[] = (int) $_platform_id;
                    }
                }
            }
        }
        $platform_filter_categories = '';
        if (count($filter_by_platform) > 0) {
            $platform_filter_categories .= ' and c.categories_id IN (SELECT categories_id FROM ' . TABLE_PLATFORMS_CATEGORIES . ' WHERE platform_id IN(\'' . implode("','", $filter_by_platform) . '\'))  ';
        }
        $filter_by_departments = [];
        $department_param = Yii::$app->request->get('departments', false);
        if (is_array($department_param)) {
            foreach ($department_param as $_department_id) {
                if ((int) $_department_id > 0) {
                    $filter_by_departments[] = (int) $_department_id;
                }
            }
        }
        if (count($filter_by_departments) > 0) {
            $platform_filter_categories .= ' and c.categories_id IN (SELECT categories_id FROM ' . TABLE_DEPARTMENTS_CATEGORIES . ' WHERE departments_id IN(\'' . implode("','", $filter_by_departments) . '\'))  ';
        }
        $categories_query = tep_db_query('select c.categories_level, c.categories_id as id, cd.categories_name as text, c.parent_id, c.categories_status from ' . TABLE_CATEGORIES . ' c, ' . TABLE_CATEGORIES . ' c1, ' . TABLE_CATEGORIES_DESCRIPTION . " cd where c.categories_id = cd.categories_id and cd.language_id = '" . (int) $languages_id . "' and c1.parent_id = '" . (int) $parent_id . "' and (c.categories_left >= c1.categories_left and c.categories_right <= c1.categories_right) and affiliate_id = 0 {$platform_filter_categories} order by c.categories_left, c.sort_order, cd.categories_name");
        $categories_by_level = [];
        while ($categories = tep_db_fetch_array($categories_query)) {
            $categories['child'] = [];
            $categories_by_level[$categories['categories_level']][$categories['id']] = $categories;
        }
        $categories_tree = self::build_tree($categories_by_level);
        return $categories_tree;
    }
    //transform plain array to tree
    private static function build_tree(array &$categories_by_level)
    {
        $categories_tree = [];
        if (count($categories_by_level)) {
            $levels = array_keys($categories_by_level);
            $top_level = min($levels);
            for ($level = max($levels); $level >= $top_level; $level--) {
                foreach ($categories_by_level[$level] as $id => $cat_info) {
                    if ($level == $top_level) {
                        $categories_tree[] = $cat_info;
                    } else {
                        $to_parent_id = $cat_info['parent_id'];
                        $categories_by_level[$level - 1][$to_parent_id]['child'][] = $cat_info;
                    }
                }
            }
        }
        return $categories_tree;
    }
    private function get_brands_list($platform_id = false)
    {
        $brands_list = [];
        $filter_by_platform = [];
        if (is_array($platform_id)) {
            $filter_by_platform = $platform_id;
        } else {
            if (!$platform_param = Yii::$app->request->get('platform', false)) {
                $form_filter = Yii::$app->request->get('filter', '');
                $output = [];
                parse_str($form_filter, $output);
                if (isset($output['platform']) && is_array($output['platform'])) {
                    $platform_param = $output['platform'];
                }
            }
            if (isset($platform_param) && is_array($platform_param)) {
                foreach ($platform_param as $_platform_id) {
                    if ((int) $_platform_id > 0) {
                        $filter_by_platform[] = (int) $_platform_id;
                    }
                }
            }
        }
        $platform_filter_products = '';
        //         if ( count($filter_by_platform)>0 ) {
        //             $platform_filter_products .= ' and m.manufacturers_id IN (SELECT distinct p.manufacturers_id FROM '.TABLE_PRODUCTS.' p inner join '.TABLE_PLATFORMS_PRODUCTS.' pp WHERE pp.products_id=p.products_id and pp.platform_id IN(\''.implode("','",$filter_by_platform).'\'))  ';
        //         }
        if (count($filter_by_platform) > 0) {
            $platform_filter_products .= ' inner join ' . TABLE_PRODUCTS . ' p on m.manufacturers_id = p.manufacturers_id inner join ' . TABLE_PLATFORMS_PRODUCTS . ' pp on pp.products_id=p.products_id and pp.platform_id IN(\'' . implode("','", $filter_by_platform) . '\')  ';
        }
        $manufacturers_query_raw = 'select m.manufacturers_id, m.manufacturers_name, m.manufacturers_image, m.date_added, m.last_modified from ' . TABLE_MANUFACTURERS . " m {$platform_filter_products} where 1  group by m.manufacturers_id order by m.sort_order, m.manufacturers_name";
        $manufacturers_query = tep_db_query($manufacturers_query_raw);
        while ($manufacturers = tep_db_fetch_array($manufacturers_query)) {
            $brands_list[] = ['id' => $manufacturers['manufacturers_id'], 'text' => $manufacturers['manufacturers_name']];
        }
        return $brands_list;
    }
    /**
     * Index action is the default action in a controller.
     */
    public function action_index()
    {
        global $login_id;
        $this->selected_menu = ['catalog', 'categories'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('categories/index'), 'title' => HEADING_TITLE];
        if (true === \common\helpers\Acl::rule(['TABLE_HEADING_PRODUCTS', 'IMAGE_EDIT'])) {
            if (\common\helpers\Acl::check_extension_allowed('ProductBundles')) {
                $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url(['categories/productedit', 'bundle' => '1']) . '" class="js_create_new_product btn btn-primary addprbtn create_bundle" title="Create bundle"><i class="icon-cubes"></i>' . TEXT_CREATE_NEW_BUNDLE . '</a>';
            }
            $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url('categories/productedit') . '" class="js_create_new_product btn btn-primary addprbtn create_product" title="Create product"><i class="icon-cubes"></i>' . TEXT_CREATE_NEW_PRODUCT . '</a>';
        }
        if (true === \common\helpers\Acl::rule(['TEXT_CATEGORIES', 'IMAGE_EDIT'])) {
            $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url('categories/categoryedit') . '" class="js_create_new_category btn btn-primary addprbtn create_category" title="Create category"><i class="icon-folder-close-alt"></i>' . TEXT_CREATE_NEW_CATEGORY . '</a>';
        }
        if (true === \common\helpers\Acl::rule(['TEXT_LABEL_BRAND', 'IMAGE_EDIT'])) {
            $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url('categories/brandedit') . '" class="btn btn-primary addprbtn create_brand" title="Create brand"><i class="icon-tag"></i>' . TEXT_CREATE_NEW_BRANDS . '</a>';
        }
        $demo_products_counter = \common\models\Products::find()->where(['is_demo' => 1])->count();
        if ($demo_products_counter > 0) {
            $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url(['categories/demo-cleanup']) . '" onclick="return confirm(\'' . TEXT_DEMO_PRODUCT_CLEAN_NOTICE . '\');" class="btn btn-primary remove_product" title="Remove demo products"><i></i>' . TEXT_DEMO_PRODUCT_CLEAN . '</a>';
        }
        $this->view->heading_title = HEADING_TITLE;
        $this->view->catalog_table = [['title' => '<div class="checker"><input class="uniform js-cat-batch js-cat-batch-master" type="checkbox"></div>', 'not_important' => 2], ['title' => TABLE_HEADING_CATEGORIES_PRODUCTS, 'not_important' => 0], ['title' => TABLE_HEADING_STATUS, 'not_important' => 0]];
        $filter_by_platform = false;
        if (false === \common\helpers\Acl::rule(['SUPERUSER'])) {
            $filter_by_platform = [];
            $platforms = \common\models\Admin_Platforms::find()->where(['admin_id' => $login_id])->as_array()->all();
            foreach ($platforms as $platform) {
                $filter_by_platform[] = $platform['platform_id'];
            }
            //$filter_by_platform[] = 0;
        }
        $this->view->categories_tree = $this->get_category_tree('0', $filter_by_platform);
        $this->view->brands_list = $this->get_brands_list();
        $this->view->filters = new \stdClass();
        $by = [['name' => TEXT_ANY, 'value' => '', 'selected' => ''], ['name' => TEXT_PRODUCT_NAME, 'value' => 'name', 'selected' => ''], ['name' => TEXT_IN_DESCRIPTION, 'value' => 'description', 'selected' => ''], ['name' => TEXT_CATEGORY_NAME, 'value' => 'cname', 'selected' => ''], ['name' => TEXT_IN_CATEGORY_DESCRIPTION, 'value' => 'cdescription', 'selected' => ''], ['name' => TEXT_PRODUCT_PAGE_TITLE, 'value' => 'title', 'selected' => ''], ['name' => TEXT_PRODUCT_HEADER_DESC, 'value' => 'header', 'selected' => ''], ['name' => TEXT_PRODUCT_KEYWORDS, 'value' => 'keywords', 'selected' => ''], ['name' => TEXT_SEARCH_BY_MODEL, 'value' => 'model', 'selected' => ''], ['name' => rtrim(TEXT_UPC, ' :'), 'value' => 'upc', 'selected' => ''], ['name' => TEXT_SEARCH_BY_EAN, 'value' => 'ean', 'selected' => ''], ['name' => TEXT_SEARCH_BY_ASIN, 'value' => 'asin', 'selected' => ''], ['name' => TEXT_SEARCH_BY_ISBN, 'value' => 'isbn', 'selected' => ''], ['name' => TEXT_SEARCH_BY_FILE, 'value' => 'file', 'selected' => ''], ['name' => TEXT_IMAGE_NAME, 'value' => 'image', 'selected' => ''], ['name' => TEXT_SEARCH_BY_SEO_NAME, 'value' => 'seo', 'selected' => '']];
        foreach ($by as $key => $value) {
            if (isset($_GET['by']) && $value['value'] == $_GET['by']) {
                $by[$key]['selected'] = 'selected';
            }
        }
        $this->view->filters->by = $by;
        $search = '';
        if (isset($_GET['search'])) {
            $search = $_GET['search'];
        }
        $this->view->filters->search = $search;
        $auto_edit = Yii::$app->request->get('autoEdit', 0);
        if ($auto_edit && !empty($search)) {
            $p = \common\models\Products::find()->alias('p')->join_with('productsDescriptions pd')->select(new \yii\db\Expression('distinct p.products_id'))->and_where(['or', ['like', 'pd.products_name', $search], ['like', 'pd.products_seo_page_name', $search], ['like', 'p.products_model', $search], ['like', 'p.products_ean', $search]]);
            if ($p->count('distinct p.products_id') == 1) {
                $this->redirect(\Yii::$app->url_manager->create_url(['categories/productedit', 'pID' => $p->scalar()]));
            }
            //2do check " for extra escapeing  echo $p->createCommand()->rawSql; die;
        }
        $brand = '';
        if (isset($_GET['brand'])) {
            $brand = $_GET['brand'];
        }
        $this->view->filters->brand = $brand;
        $supplier = '';
        if (isset($_GET['supplier'])) {
            $supplier = $_GET['supplier'];
        }
        $this->view->filters->supplier = $supplier;
        $source = '';
        if (isset($_GET['source'])) {
            $source = $_GET['source'];
        }
        $this->view->filters->source = $source;
        $stock = [['name' => TEXT_ALL, 'value' => '', 'selected' => ''], ['name' => TEXT_PRODUCT_AVAILABLE, 'value' => 'y', 'selected' => ''], ['name' => TEXT_PRODUCT_NOT_AVAILABLE, 'value' => 'n', 'selected' => '']];
        foreach ($stock as $key => $value) {
            if (isset($_GET['stock']) && $value['value'] == $_GET['stock']) {
                $stock[$key]['selected'] = 'selected';
            }
        }
        $this->view->filters->stock = $stock;
        $status = [['name' => TEXT_ALL, 'value' => '', 'selected' => ''], ['name' => TEXT_ACTIVE, 'value' => 'y', 'selected' => ''], ['name' => TEXT_INACTIVE, 'value' => 'n', 'selected' => '']];
        $gstatus = \Yii::$app->request->get('status', '');
        if (!empty($gstatus)) {
            foreach ($status as $key => $value) {
                if ($value['value'] == $gstatus) {
                    $status[$key]['selected'] = 'selected';
                }
            }
        }
        $this->view->filters->status = $status;
        $price_from = '';
        if (isset($_GET['price_from'])) {
            $price_from = $_GET['price_from'];
        }
        $this->view->filters->price_from = $price_from;
        $price_to = '';
        if (isset($_GET['price_to'])) {
            $price_to = $_GET['price_to'];
        }
        $this->view->filters->price_to = $price_to;
        if (isset($_GET['weight_value']) && $_GET['weight_value'] == 'lbs') {
            $this->view->filters->weight_kg = false;
            $this->view->filters->weight_lbs = true;
        } else {
            $this->view->filters->weight_kg = true;
            $this->view->filters->weight_lbs = false;
        }
        $weight_from = '';
        if (isset($_GET['weight_from'])) {
            $weight_from = $_GET['weight_from'];
        }
        $this->view->filters->weight_from = $weight_from;
        $weight_to = '';
        if (isset($_GET['weight_to'])) {
            $weight_to = $_GET['weight_to'];
        }
        $this->view->filters->weight_to = $weight_to;
        $this->view->filters->prod_attr = (int) Yii::$app->request->get('prod_attr', 0);
        $this->view->filters->low_stock = (int) Yii::$app->request->get('low_stock', 0);
        $this->view->filters->featured = (int) Yii::$app->request->get('featured', 0);
        $this->view->filters->gift = (int) Yii::$app->request->get('gift', 0);
        $this->view->filters->virtual = (int) Yii::$app->request->get('virtual', 0);
        $this->view->filters->all_bundles = (int) Yii::$app->request->get('all_bundles', 0);
        $this->view->filters->type_listing = (int) Yii::$app->request->get('type_listing', 0);
        $this->view->filters->type_not_listing = (int) Yii::$app->request->get('type_not_listing', 0);
        $this->view->filters->sub_children = (int) Yii::$app->request->get('sub_children', 0);
        $this->view->filters->sale = (int) Yii::$app->request->get('sale', 0);
        $this->view->filters->wo_images = (int) Yii::$app->request->get('wo_images', 0);
        $this->view->filters->platform = [];
        if (isset($_GET['platform']) && is_array($_GET['platform'])) {
            foreach ($_GET['platform'] as $_platform_id) {
                if ((int) $_platform_id > 0) {
                    $this->view->filters->platform[] = (int) $_platform_id;
                }
            }
        }
        $this->view->filters->row = (int) Yii::$app->request->get('row', 0);
        $listing_type = 'category';
        if (isset($_GET['listing_type'])) {
            $listing_type = $_GET['listing_type'];
        }
        $this->view->filters->listing_type = $listing_type;
        $this->view->filters->category_id = (int) Yii::$app->request->get('category_id', 0);
        $this->view->filters->brand_id = (int) Yii::$app->request->get('brand_id', 0);
        $this->view->categories_opened_tree = \common\helpers\Categories::get_category_parents_ids($this->view->filters->category_id);
        $this->view->categories_closed_tree = array_diff(array_map('intval', explode('|', \Yii::$app->session->get('closed_data', ''))), $this->view->categories_opened_tree);
        if (is_dir(DIR_FS_CATALOG_IMAGES)) {
            if (!is_writeable(DIR_FS_CATALOG_IMAGES)) {
                $this->view->error_message = sprintf(ERROR_CATALOG_IMAGE_DIRECTORY_NOT_WRITEABLE, DIR_FS_CATALOG_IMAGES);
                $this->view->error_message_type = 'danger';
            }
        } else {
            $this->view->error_message = sprintf(ERROR_CATALOG_IMAGE_DIRECTORY_DOES_NOT_EXIST, DIR_FS_CATALOG_IMAGES);
            $this->view->error_message_type = 'danger';
        }
        $departments = false;
        if (defined('SUPERADMIN_ENABLED') && SUPERADMIN_ENABLED == true) {
            $this->view->filters->departments = [];
            if (isset($_GET['departments']) && is_array($_GET['departments'])) {
                foreach ($_GET['departments'] as $_department_id) {
                    if ((int) $_department_id > 0) {
                        $this->view->filters->departments[] = (int) $_department_id;
                    }
                }
            }
            $departments = \common\classes\department::get_list(false);
        }
        return $this->render('index', ['platforms' => \common\classes\platform::get_list(), 'isMultiPlatforms' => \common\classes\platform::is_multi(), 'collapsed' => $this->default_collapsed, 'departments' => $departments]);
    }
    public function action_list()
    {
        \common\helpers\Translation::init('admin/categories');
        if (defined('SUPERADMIN_ENABLED') && SUPERADMIN_ENABLED == true) {
            $departments = [];
            $departments_list = \common\classes\department::get_list();
            foreach ($departments_list as $department) {
                $departments[$department['departments_id']] = $department['departments_store_name'];
            }
        }
        global $login_id;
        $languages_id = \Yii::$app->settings->get('languages_id');
        $draw = Yii::$app->request->get('draw', 1);
        $start = Yii::$app->request->get('start', 0);
        $length = Yii::$app->request->get('length', 10);
        $current_category_id = Yii::$app->request->get('id', 0);
        if ($length == -1) {
            $length = 10000;
        }
        $form_filter = Yii::$app->request->get('filter');
        parse_str($form_filter, $output);
        $categories_qty = 0;
        $products_qty = 0;
        $current_page_number = $start / $length + 1;
        $response_list = [];
        $_session = Yii::$app->session;
        $_session->remove('products_query_raw');
        $list_bread_crumb = '';
        $search_filter = '';
        if (isset($_GET['search']['value']) && tep_not_null($_GET['search']['value'])) {
            $keywords = tep_db_input(tep_db_prepare_input($_GET['search']['value']));
            $search_condition = " where cd.categories_name like '%" . $keywords . "%' ";
            if (!empty($output['listing_type']) && $output['listing_type'] == 'brand') {
                $search_fields = ['pd.products_name', 'pd.products_seo_page_name', 'p.products_model'];
            } else {
                $search_fields = ['pd.products_name', 'pd.products_seo_page_name', 'p.products_model'];
            }
            $search_filter = '( ' . implode(" like '%" . tep_db_input($keywords) . "%' or ", $search_fields) . " like '%" . tep_db_input($keywords) . "%')";
        } else {
            $search_condition = ' where 1 ';
        }
        $search_condition .= " and c.parent_id='" . (int) $current_category_id . "'";
        //--- Apply filter start
        $only_categories = false;
        $only_products = false;
        $filter_cat = '';
        $filter_prod = '';
        $use_iventory = false;
        $platform_filter_categories = '';
        $platform_filter_products = '';
        if (defined('SUPERADMIN_ENABLED') && SUPERADMIN_ENABLED == true) {
            $filter_by_departments = [];
            if (isset($output['departments']) && is_array($output['departments'])) {
                foreach ($output['departments'] as $_department_id) {
                    if ((int) $_department_id > 0) {
                        $filter_by_departments[] = (int) $_department_id;
                    }
                }
            }
            if (count($filter_by_departments) > 0) {
                $platform_filter_categories .= ' and c.categories_id IN (SELECT categories_id FROM ' . TABLE_DEPARTMENTS_CATEGORIES . ' WHERE departments_id IN(\'' . implode("','", $filter_by_departments) . '\'))  ';
                $platform_filter_products .= ' and p.products_id IN (SELECT products_id FROM ' . TABLE_DEPARTMENTS_PRODUCTS . ' WHERE departments_id IN(\'' . implode("','", $filter_by_departments) . '\'))  ';
            }
        }
        $filter_by_platform = [];
        if (isset($output['platform']) && is_array($output['platform'])) {
            foreach ($output['platform'] as $_platform_id) {
                if ((int) $_platform_id > 0) {
                    $filter_by_platform[] = (int) $_platform_id;
                }
            }
        } elseif (false === \common\helpers\Acl::rule(['SUPERUSER'])) {
            $platforms = \common\models\Admin_Platforms::find()->where(['admin_id' => $login_id])->as_array()->all();
            foreach ($platforms as $platform) {
                $filter_by_platform[] = $platform['platform_id'];
            }
            if (count($filter_by_platform) == 0) {
                $filter_by_platform[] = 0;
            }
        }
        if (count($filter_by_platform) > 0) {
            //            $filter_cat .= ' and c.categories_id IN (SELECT categories_id FROM '.TABLE_PLATFORMS_CATEGORIES.' WHERE platform_id IN(\''.implode("','",$filter_by_platform).'\'))  ';
            //            $filter_prod .= ' and p.products_id IN (SELECT products_id FROM '.TABLE_PLATFORMS_PRODUCTS.' WHERE platform_id IN(\''.implode("','",$filter_by_platform).'\'))  ';
            $platform_filter_categories .= ' and c.categories_id IN (SELECT categories_id FROM ' . TABLE_PLATFORMS_CATEGORIES . ' WHERE platform_id IN(\'' . implode("','", $filter_by_platform) . '\'))  ';
            $platform_filter_products .= ' and p.products_id IN (SELECT products_id FROM ' . TABLE_PLATFORMS_PRODUCTS . ' WHERE platform_id IN(\'' . implode("','", $filter_by_platform) . '\'))  ';
        }
        if (tep_not_null($output['search'])) {
            $search = tep_db_prepare_input($output['search']);
            switch ($output['by']) {
                case 'name':
                    $filter_prod .= " and (pd.products_name like '%" . tep_db_input($search) . "%' or pdd.products_name like '%" . tep_db_input($search) . "%') ";
                    $only_products = true;
                    break;
                case 'internal_name':
                    $filter_prod .= " and (pd.products_internal_name like '%" . tep_db_input($search) . "%' or pdd.products_internal_name like '%" . tep_db_input($search) . "%') ";
                    $only_products = true;
                    break;
                case 'description':
                    $filter_prod .= " and pd.products_description like '%" . tep_db_input($search) . "%' ";
                    $only_products = true;
                    break;
                case 'cname':
                default:
                    $filter_cat .= " and (cd.categories_name like '%" . tep_db_input($search) . "%' or cdd.categories_name like '%" . tep_db_input($search) . "%') ";
                    $only_categories = true;
                    break;
                case 'cdescription':
                    $filter_cat .= " and cd.categories_description like '%" . tep_db_input($search) . "%' ";
                    $only_categories = true;
                    break;
                case 'title':
                    $filter_prod .= " and pd.products_head_title_tag like '%" . tep_db_input($search) . "%' ";
                    $only_products = true;
                    break;
                case 'header':
                    $filter_prod .= " and pd.products_head_desc_tag like '%" . tep_db_input($search) . "%' ";
                    $only_products = true;
                    break;
                case 'keywords':
                    $filter_prod .= " and pd.products_head_keywords_tag like '%" . tep_db_input($search) . "%' ";
                    $only_products = true;
                    break;
                case 'model':
                    $filter_prod .= " and p.products_model like '%" . tep_db_input($search) . "%' ";
                    $only_products = true;
                    break;
                case 'ean':
                    $filter_prod .= " and p.products_ean like '%" . tep_db_input($search) . "%' ";
                    $only_products = true;
                    break;
                case 'upc':
                    $filter_prod .= " and p.products_upc like '%" . tep_db_input($search) . "%' ";
                    $only_products = true;
                    break;
                case 'asin':
                    $filter_prod .= " and p.products_asin like '%" . tep_db_input($search) . "%' ";
                    $only_products = true;
                    break;
                case 'isbn':
                    $filter_prod .= " and p.products_isbn like '%" . tep_db_input($search) . "%' ";
                    $only_products = true;
                    break;
                case 'file':
                    $filter_prod .= " and p.products_file like '%" . tep_db_input($search) . "%' ";
                    $only_products = true;
                    break;
                case 'image':
                    $filter_prod .= " and p.products_image like '%" . tep_db_input($search) . "%' ";
                    $filter_cat .= " and c.categories_image like '%" . tep_db_input($search) . "%' ";
                    break;
                case 'seo':
                    $filter_prod .= " and (p.products_seo_page_name like '%" . tep_db_input($search) . "%' ";
                    $filter_prod .= " or pd.products_seo_page_name like '%" . tep_db_input($search) . "%' )";
                    $filter_cat .= " and c.categories_seo_page_name like '%" . tep_db_input($search) . "%' ";
                    break;
                case '':
                case 'any':
                    /** @var \common\extensions\PlainProductsDescription\PlainProductsDescription  $ext  */
                    $ext = \common\helpers\Acl::check_extension_allowed('PlainProductsDescription', 'allowed');
                    if ($ext && $ext::is_enabled() && (!method_exists($ext, 'optionUseInBackend') || $ext::option_use_in_backend())) {
                        $search_builder = new \common\components\Search_Builder('simple');
                        $search_builder->set_search_in_desc(SEARCH_IN_DESCRIPTION == 'True');
                        $search_builder->set_search_internal(true);
                        $search_builder->search_in_property = false;
                        $search_builder->search_in_attributes = false;
                        $search_builder->parse_keywords(\common\helpers\Product::cleanup_search($search));
                        $products_query = \common\models\Products::find()->distinct()->alias('p');
                        $search_builder->add_products_restriction($products_query);
                        $products_query->select('p.products_id')->order_by('p.products_id');
                        $filter_prod .= ' and (';
                        $filter_prod .= "p.products_id in ('" . implode("','", $products_query->as_array()->column()) . "') ";
                        $filter_prod .= ') ';
                    } else {
                        $filter_prod .= ' and (';
                        $filter_prod .= " pd.products_name like '%" . tep_db_input($search) . "%' ";
                        $filter_prod .= " or pdd.products_name like '%" . tep_db_input($search) . "%' ";
                        $filter_prod .= " or pd.products_internal_name like '%" . tep_db_input($search) . "%' ";
                        $filter_prod .= " or pdd.products_internal_name like '%" . tep_db_input($search) . "%' ";
                        $filter_prod .= " or pd.products_description like '%" . tep_db_input($search) . "%' ";
                        $filter_prod .= " or pd.products_head_title_tag like '%" . tep_db_input($search) . "%' ";
                        $filter_prod .= " or pd.products_head_desc_tag like '%" . tep_db_input($search) . "%' ";
                        $filter_prod .= " or pd.products_head_keywords_tag like '%" . tep_db_input($search) . "%' ";
                        $filter_prod .= " or p.products_model like '%" . tep_db_input($search) . "%' ";
                        /** @var \common\extensions\Inventory\Inventory $inv */
                        if ($inv = \common\helpers\Extensions::is_allowed('Inventory')) {
                            $use_iventory = true;
                            $filter_prod .= " or i.products_model like '%" . tep_db_input($search) . "%' ";
                            // add search in suppliers
                            $filter_prod .= " or suppp.suppliers_product_name like '%" . tep_db_input($search) . "%' ";
                            $filter_prod .= " or suppp.suppliers_model like '%" . tep_db_input($search) . "%' ";
                            $filter_prod .= " or suppp.suppliers_upc like '%" . tep_db_input($search) . "%' ";
                        }
                        $filter_prod .= " or p.products_ean like '%" . tep_db_input($search) . "%' ";
                        $filter_prod .= " or p.products_upc like '%" . tep_db_input($search) . "%' ";
                        $filter_prod .= " or p.products_asin like '%" . tep_db_input($search) . "%' ";
                        $filter_prod .= " or p.products_isbn like '%" . tep_db_input($search) . "%' ";
                        $filter_prod .= " or p.products_file like '%" . tep_db_input($search) . "%' ";
                        $filter_prod .= " or p.products_image like '%" . tep_db_input($search) . "%' ";
                        $filter_prod .= " or p.products_seo_page_name like '%" . tep_db_input($search) . "%' ";
                        $filter_prod .= " or pd.products_seo_page_name like '%" . tep_db_input($search) . "%' ";
                        $filter_prod .= ') ';
                    }
                    $filter_cat .= ' and (';
                    $filter_cat .= " cd.categories_name like '%" . tep_db_input($search) . "%' ";
                    $filter_cat .= " or cdd.categories_name like '%" . tep_db_input($search) . "%' ";
                    $filter_cat .= " or cd.categories_description like '%" . tep_db_input($search) . "%' ";
                    $filter_cat .= " or c.categories_image like '%" . tep_db_input($search) . "%' ";
                    $filter_cat .= " or c.categories_seo_page_name like '%" . tep_db_input($search) . "%' ";
                    $filter_cat .= ') ';
                    break;
            }
        }
        if (tep_not_null($output['brand'])) {
            $only_products = true;
            //$filter_prod .= " and m.manufacturers_name like '%" . tep_db_input($output['brand']) . "%'";
            $_matched_manufacturers = \yii\helpers\Array_Helper::map(\common\models\Manufacturers::find()->where(['LIKE', 'manufacturers_name', $output['brand']])->select(['id' => 'manufacturers_id', 'exact_match' => new \yii\db\Expression('IF(manufacturers_name=:brand_name,1,0)', [':brand_name' => $output['brand']])])->as_array()->all(), 'id', 'id', 'exact_match');
            if (isset($_matched_manufacturers[1]) && count($_matched_manufacturers[1]) > 0) {
                $filter_prod .= " and p.manufacturers_id in('" . implode("','", $_matched_manufacturers[1]) . "')";
            } elseif (isset($_matched_manufacturers[0]) && count($_matched_manufacturers[0]) > 0) {
                $filter_prod .= " and p.manufacturers_id in('" . implode("','", $_matched_manufacturers[0]) . "')";
            } else {
                $filter_prod .= ' and 1=0 /*brand filter*/ ';
            }
        }
        if (tep_not_null($output['supplier']) || tep_not_null($output['source'])) {
            $only_products = true;
            $check_products_query = tep_db_query('SELECT distinct(sp.products_id) FROM ' . TABLE_SUPPLIERS_PRODUCTS . ' as sp LEFT JOIN ' . TABLE_SUPPLIERS . " as s on (sp.suppliers_id=s.suppliers_id) WHERE s.suppliers_name like '%" . tep_db_input($output['supplier']) . "%' AND sp.source like '%" . tep_db_input($output['source']) . "%'");
            if (tep_db_num_rows($check_products_query) > 0) {
                $featured_ids = [];
                while ($check_products = tep_db_fetch_array($check_products_query)) {
                    $featured_ids[] = $check_products['products_id'];
                }
                $_supplier_products_filter = 'p.products_id IN (' . implode(', ', $featured_ids) . ')';
            } else {
                $_supplier_products_filter = 'p.products_id = -1';
            }
            if (tep_not_null($output['source'])) {
                $filter_prod .= " and ({$_supplier_products_filter} or p.source like '%" . tep_db_input($output['source']) . "%')";
            } else {
                $filter_prod .= " and {$_supplier_products_filter}";
            }
        }
        if (tep_not_null($output['stock'])) {
            switch ($output['stock']) {
                case 'y':
                    $only_products = true;
                    $filter_prod .= ' and p.products_quantity > 0 and p.products_id_stock=p.products_id';
                    break;
                case 'n':
                    $only_products = true;
                    $filter_prod .= ' and p.products_quantity <= 0 and p.products_id_stock=p.products_id';
                    break;
                default:
                    break;
            }
        }
        if (tep_not_null($output['status'])) {
            switch ($output['status']) {
                case 'y':
                    $only_products = true;
                    $filter_prod .= " and p.products_status = '1' ";
                    break;
                case 'n':
                    $only_products = true;
                    $filter_prod .= " and p.products_status = '0' ";
                    break;
                default:
                    break;
            }
        }
        if (isset($output['price_from']) && !empty($output['price_from'])) {
            $only_products = true;
            $filter_prod .= " and p.products_price >= '" . tep_db_input($output['price_from']) . "' ";
        }
        if (isset($output['price_to']) && !empty($output['price_to'])) {
            $only_products = true;
            $filter_prod .= " and p.products_price <= '" . tep_db_input($output['price_to']) . "' ";
        }
        if (isset($output['weight_from']) && !empty($output['weight_from'])) {
            $only_products = true;
            if ($output['weight_value'] == 'lbs') {
                $filter_prod .= " and p.weight_in >= '" . tep_db_input($output['weight_from']) . "' ";
            } else {
                $filter_prod .= " and p.weight_cm >= '" . tep_db_input($output['weight_from']) . "' ";
            }
        }
        if (isset($output['weight_to']) && !empty($output['weight_to'])) {
            $only_products = true;
            if ($output['weight_value'] == 'lbs') {
                $filter_prod .= " and p.weight_in <= '" . tep_db_input($output['weight_to']) . "' ";
            } else {
                $filter_prod .= " and p.weight_cm <= '" . tep_db_input($output['weight_to']) . "' ";
            }
        }
        if (isset($output['prod_attr'])) {
            $only_products = true;
            $check_products_query = tep_db_query('SELECT distinct(products_id) FROM ' . TABLE_PRODUCTS_ATTRIBUTES . ' WHERE 1');
            if (tep_db_num_rows($check_products_query) > 0) {
                $featured_ids = [];
                while ($check_products = tep_db_fetch_array($check_products_query)) {
                    $featured_ids[] = $check_products['products_id'];
                }
                $filter_prod .= ' and p.products_id IN (' . implode(', ', $featured_ids) . ')';
            } else {
                $filter_prod .= ' and p.products_id = -1';
            }
        }
        if (isset($output['low_stock'])) {
            $only_products = true;
            $filter_prod .= " and p.products_quantity < '" . STOCK_REORDER_LEVEL . "' ";
        }
        if (isset($output['featured'])) {
            $only_products = true;
            $check_products_query = tep_db_query('SELECT distinct(products_id) FROM ' . TABLE_FEATURED . ' WHERE 1');
            if (tep_db_num_rows($check_products_query) > 0) {
                $featured_ids = [];
                while ($check_products = tep_db_fetch_array($check_products_query)) {
                    $featured_ids[] = $check_products['products_id'];
                }
                $filter_prod .= ' and p.products_id IN (' . implode(', ', $featured_ids) . ')';
            } else {
                $filter_prod .= ' and p.products_id = -1';
            }
        }
        if (isset($output['gift'])) {
            $only_products = true;
            $check_products_query = tep_db_query('SELECT distinct(products_id) FROM ' . TABLE_GIFT_WRAP_PRODUCTS . ' WHERE 1');
            if (tep_db_num_rows($check_products_query) > 0) {
                $featured_ids = [];
                while ($check_products = tep_db_fetch_array($check_products_query)) {
                    $featured_ids[] = $check_products['products_id'];
                }
                $filter_prod .= ' and p.products_id IN (' . implode(', ', $featured_ids) . ')';
            } else {
                $filter_prod .= ' and p.products_id = -1';
            }
        }
        if (isset($output['virtual'])) {
            $only_products = true;
            $filter_prod .= " and p.is_virtual = '1' ";
        }
        if (isset($output['type_listing'])) {
            $only_products = true;
            $filter_prod .= " and p.is_listing_product = '1' ";
        }
        if (isset($output['type_not_listing'])) {
            $only_products = true;
            $filter_prod .= " and p.is_listing_product = '0' ";
        }
        if (isset($output['sub_children'])) {
            $only_products = true;
            $filter_prod .= ' and p.parent_products_id != 0 ';
        }
        if (isset($output['all_bundles'])) {
            $only_products = true;
            $filter_prod .= ' and p.is_bundle = 1';
        }
        if (isset($output['sale'])) {
            $only_products = true;
            $sale_ids = \common\models\Specials::find()->select('products_id')->expired(false)->distinct()->as_array()->column();
            if (!empty($sale_ids)) {
                $filter_prod .= ' and p.products_id IN (' . implode(', ', $sale_ids) . ')';
            } else {
                $filter_prod .= ' and p.products_id = -1';
            }
            /*
            $check_products_query = tep_db_query("SELECT distinct(products_id) FROM " . TABLE_SPECIALS . " WHERE 1");
            if (tep_db_num_rows($check_products_query) > 0) {
                $saleIds = [];
                while ($check_products = tep_db_fetch_array($check_products_query)) {
                    $saleIds[] = $check_products['products_id'];
                }
                $filter_prod .= " and p.products_id IN (" . implode(", ", $saleIds) . ")";
            } else {
                $filter_prod .= " and p.products_id = -1";
            }
            */
        }
        if (isset($output['wo_images'])) {
            $_wo_images_pids = Yii::$app->get_db()->create_command('SELECT p.products_id ' . 'FROM ' . TABLE_PRODUCTS . ' p ' . ' LEFT JOIN ' . TABLE_PRODUCTS_IMAGES . ' pi ON (p.products_id = pi.products_id) ' . 'WHERE pi.products_id IS NULL')->query_column();
            if (count($_wo_images_pids) > 0) {
                $platform_filter_products .= ' and p.products_id IN (' . implode(', ', $_wo_images_pids) . ')';
                $_wo_images_categories = Yii::$app->get_db()->create_command('SELECT DISTINCT p2c.categories_id ' . 'FROM ' . TABLE_PRODUCTS_TO_CATEGORIES . ' p2c ' . 'WHERE p2c.products_id IN (' . implode(', ', $_wo_images_pids) . ')')->query_column();
                foreach ($_wo_images_categories as $_cat_id) {
                    \common\helpers\Categories::get_parent_categories($_wo_images_categories, $_cat_id, false);
                }
                $platform_filter_categories = ' AND c.categories_id IN (' . implode(', ', $_wo_images_categories) . ')';
            } else {
                $platform_filter_products .= ' AND 1=0 /*wo images empty*/ ';
                $platform_filter_categories .= ' AND 1=0 /*wo images empty*/ ';
            }
        }
        if (false === \common\helpers\Acl::rule(['TEXT_CATEGORIES', 'IMAGE_EDIT'])) {
            $disable_category_item = true;
        } else {
            $disable_category_item = false;
        }
        if (false === \common\helpers\Acl::rule(['TABLE_HEADING_PRODUCTS', 'IMAGE_EDIT'])) {
            $disable_product_item = true;
        } else {
            $disable_product_item = false;
        }
        if (!empty($filter_prod) || !empty($filter_cat)) {
            // SEARCH
            $list_bread_crumb = '';
            $rows_counter = 0;
            if (!$only_products) {
                //categories
                $order_by_category = 'c.sort_order, cd.categories_name';
                $categories_query_raw = 'select distinct(c.categories_id), if(length(cd.categories_name) > 0, cd.categories_name, cdd.categories_name) as categories_name, c.categories_status, c.manual_control_status from ' . TABLE_CATEGORIES . ' c left join ' . TABLE_CATEGORIES_DESCRIPTION . ' cd on c.categories_id=cd.categories_id left join ' . TABLE_CATEGORIES_DESCRIPTION . ' cdd on c.categories_id=cdd.categories_id where 1 ' . $filter_cat . " and cd.language_id = '" . (int) $languages_id . "' and cdd.language_id = '" . \common\helpers\Language::get_default_language_id() . "' and cd.affiliate_id = 0 " . $platform_filter_categories . ' order by ' . $order_by_category;
                $remind_page_number = $current_page_number;
                $categories_split = new \Split_Page_Results($current_page_number, $length, $categories_query_raw, $categories_query_numrows, 'c.categories_id');
                $categories_query = tep_db_query($categories_query_raw);
                $categories_qty = $categories_query_numrows;
                if ($remind_page_number == $current_page_number) {
                    // all categories showed, now show only products
                    while ($categories = tep_db_fetch_array($categories_query)) {
                        $response_list[] = ['<input type="checkbox"' . ($disable_category_item ? ' disabled' : '') . ' class="' . ($categories_qty < CATALOG_SPEED_UP_DESIGN ? 'uniform' : '') . ' js-cat-batch" name="batch[]" value="c_' . $categories['categories_id'] . '">', '<div class="handle_cat_list state-disabled"><span class="handle"><i class="icon-hand-paper-o"></i></span><div class="cat_name"><b>' . $categories['categories_name'] . '</b><input class="cell_identify" type="hidden" value="' . $categories['categories_id'] . '"><input class="cell_type" type="hidden" value="category"></div></div>', $categories['categories_status'] == 1 ? '<input type="checkbox" value="' . $categories['categories_id'] . '" name="categories_status" class="' . ($categories_qty < CATALOG_SPEED_UP_DESIGN ? 'check_on_off' : 'check_on_off_check') . '" checked="checked"' . ($disable_category_item ? ' readonly' : '') . '>' : '<input type="checkbox" value="' . $categories['categories_id'] . '" name="categories_status" class="' . ($categories_qty < CATALOG_SPEED_UP_DESIGN ? 'check_on_off' : 'check_on_off_check') . '"' . ($disable_category_item ? ' readonly' : '') . '>'];
                        if ($ext = \common\helpers\Acl::check_extension('AutomaticallyStatus', 'allowed')) {
                            if ($ext::allowed() && !$categories['manual_control_status']) {
                                $response_list[count($response_list) - 1]['DT_RowClass'] = 'check_status_auto';
                            }
                        }
                        $rows_counter++;
                    }
                }
            }
            if (!$only_categories) {
                //products
                $order_by_product = 'p2c.sort_order, pd.products_name';
                $products_query_raw = 'select p.products_id, p.is_listing_product, p.sub_product_children_count, p.parent_products_id, p.products_groups_id, p.products_model, ' . Product_Name_Decorator::instance()->listing_query_expression('pd', 'pdd') . ' as products_name, p.products_status, p.manual_control_status, p.products_image, p.products_quantity from ' . TABLE_PRODUCTS . ' p LEFT JOIN ' . TABLE_PRODUCTS_DESCRIPTION . ' as pd on p.products_id = pd.products_id LEFT JOIN ' . TABLE_PRODUCTS_DESCRIPTION . ' as pdd on p.products_id = pdd.products_id LEFT JOIN ' . TABLE_PRODUCTS_TO_CATEGORIES . ' as p2c on p.products_id = p2c.products_id LEFT JOIN ' . TABLE_MANUFACTURERS . ' as m on p.manufacturers_id=m.manufacturers_id ' . ($use_iventory ? 'LEFT JOIN ' . TABLE_INVENTORY . ' i on i.prid = p.products_id LEFT JOIN ' . TABLE_SUPPLIERS_PRODUCTS . ' as suppp on i.products_id = suppp.uprid' : '') . " where pd.language_id = '" . (int) $languages_id . "' and pdd.language_id = '" . \common\helpers\Language::get_default_language_id() . "' and pd.platform_id = '" . intval(\common\classes\platform::default_id()) . "' and pdd.platform_id = '" . intval(\common\classes\platform::default_id()) . "' and pd.department_id=0 and pdd.department_id=0 " . (empty($search_filter) ? '' : "and {$search_filter} ") . $filter_prod . " {$platform_filter_products} group by p.products_id order by " . $order_by_product;
                $products_query = tep_db_query($products_query_raw);
                $products_query_numrows = tep_db_num_rows($products_query);
                $categories_query_numrows = $categories_query_numrows ?? 0;
                $offset = $start - $categories_query_numrows;
                $products_query_raw .= ' limit ' . max($offset, 0) . ', ' . $length;
                $products_query = tep_db_query($products_query_raw);
                $products_qty = $products_query_numrows;
                $categories_query_numrows += $products_query_numrows;
                if ($rows_counter < $length) {
                    $products_query = tep_db_query($products_query_raw);
                    while ($products = tep_db_fetch_array($products_query)) {
                        if (empty($products['products_name'])) {
                            $products['products_name'] = \common\helpers\Product::get_products_name($products['products_id']);
                        }
                        // (file_exists(DIR_FS_CATALOG_IMAGES . $products['products_image']) ? '<span class="prodImgC">' . \common\helpers\Image::info_image($products['products_image'], $products['products_name'], 50, 50) . '</span>' : '<span class="cubic"></span>')
                        $image = \common\classes\Images::get_image($products['products_id']);
                        //(!empty($image) ? '<span class="prodImgC">' . \common\helpers\Image::info_image($image, $products['products_name'], 50, 50) . '</span>' : '<span class="cubic"></span>');
                        $product_categories_string = '';
                        if (true) {
                            $product_categories = \common\helpers\Categories::generate_category_path($products['products_id'], 'product');
                            $product_categories_string .= '';
                            for ($i = 0, $n = sizeof($product_categories); $i < $n; $i++) {
                                $category_path = '';
                                for ($j = 0, $k = sizeof($product_categories[$i]); $j < $k; $j++) {
                                    $category_path .= '<span class="category_path__location">' . $product_categories[$i][$j]['text'] . '</span>&nbsp;&gt;&nbsp;';
                                }
                                $category_path = substr($category_path, 0, -16);
                                $product_categories_string .= '<li class="category_path">' . $category_path . '</li>';
                            }
                            $product_categories_string = '<span class="category_path" style="display:block">' . TEXT_LIST_PRODUCT_PLACED_IN . '</span> <ul class="category_path_list">' . $product_categories_string . '</ul>';
                        }
                        $product_markers = '';
                        if (defined('LISTING_SUB_PRODUCT') && LISTING_SUB_PRODUCT == 'True') {
                            if ($products['is_listing_product']) {
                                $product_markers .= '<i class="product_list_marker product_list_marker__listing">' . TEXT_LISTING_PRODUCT . '</i> ';
                            } else {
                                $product_markers .= '<i class="product_list_marker product_list_marker__master">' . TEXT_MASTER_PRODUCT . '</i> ';
                            }
                            if ($products['parent_products_id']) {
                                $products['products_quantity'] = \common\helpers\Product::get_products_info($products['parent_products_id'], 'products_quantity');
                                $__link_content = \common\helpers\Product::get_products_info($products['parent_products_id'], 'products_model');
                                if ($__link_content) {
                                    $__link_content = "{$__link_content} ";
                                }
                                $__link_content .= \common\helpers\Product::get_backend_products_name($products['parent_products_id']);
                                $parent_product_name = TEXT_PARENT_PRODUCT . ' ' . Html::a($__link_content, Url::to(['categories/productedit', 'pID' => $products['parent_products_id']]));
                                $product_markers .= '<i class="product_list_marker product_list_marker__child_of">' . TEXT_CHILD_PRODUCT . '<div class="product_list_marker__pophover">' . $parent_product_name . '</div></i> ';
                            } elseif ($products['sub_product_children_count'] > 0) {
                                $children_products = \yii\helpers\Array_Helper::map(\common\models\Products::find()->where(['parent_products_id' => $products['products_id']])->select(['products_id', 'products_model'])->as_array()->all(), 'products_id', 'products_model');
                                foreach ($children_products as $children_product_id => $children_product_model) {
                                    $children_products[$children_product_id] = '<div>' . TEXT_CHILD_PRODUCT . ' ' . Html::a(($children_product_model ? "{$children_product_model} " : '') . \common\helpers\Product::get_backend_products_name($children_product_id), Url::to(['categories/productedit', 'pID' => $children_product_id])) . '</div>';
                                }
                                $product_markers .= '<i class="product_list_marker product_list_marker__parent_of">' . TEXT_PARENT_PRODUCT . '<div class="product_list_marker__pophover">' . implode('', $children_products) . '</div></i> ';
                            }
                        }
                        if ($products['products_groups_id']) {
                            $product_categories_string = '<i class="next-row category_path product_list__products_group">' . \common\helpers\Product::products_groups_name($products['products_groups_id']) . '</i>' . $product_categories_string;
                        }
                        $response_list[] = ['<input type="checkbox"' . ($disable_product_item ? ' disabled' : '') . ' class="' . ($products_qty < CATALOG_SPEED_UP_DESIGN ? 'uniform' : '') . ' js-cat-batch" name="batch[]" value="p_' . $products['products_id'] . '">', '<div class="handle_cat_list state-disabled' . ($products['products_status'] == 1 ? '' : ' dis_prod') . '">' . '<span class="handle"><i class="icon-hand-paper-o"></i></span>' . '<div class="prod_name prod_name_double" data-click-double="' . tep_href_link(FILENAME_CATEGORIES . '/productedit', 'pID=' . $products['products_id']) . '">' . (!empty($image) ? '<span class="prodImgC">' . $image . '</span>' : '<span class="cubic"></span>') . '<table class="wrapper"><tr><td><span class="prodNameC">' . $products['products_name'] . $product_categories_string . '</span></td></tr></table>' . '<span class="prodIDsC"><span title="' . \common\helpers\Output::output_string($products['products_model']) . '">' . ($products['products_model'] ? TEXT_SKU . ': ' . $products['products_model'] . '<br>' : '') . TEXT_PRODUCTS_QUANTITY_INFO . ': ' . \common\helpers\Product::get_virtual_item_quantity($products['products_id'], $products['products_quantity']) . '<br>' . TABLE_HEADING_ID . ': ' . $products['products_id'] . '</span>' . $product_markers . '</span>' . '<input class="cell_identify" type="hidden" value="' . $products['products_id'] . '"><input class="cell_type" type="hidden" value="product">' . '</div>' . '</div>', $products['products_status'] == 1 ? '<input type="checkbox" value="' . $products['products_id'] . '" name="products_status" class="check_on_off" checked="checked"' . ($disable_product_item ? ' readonly' : '') . '>' : '<input type="checkbox" value="' . $products['products_id'] . '" name="products_status" class="' . ($products_qty < CATALOG_SPEED_UP_DESIGN ? 'check_on_off' : 'check_on_off_check') . '"' . ($disable_product_item ? ' readonly' : '') . '>'];
                        if ($ext = \common\helpers\Acl::check_extension('AutomaticallyStatus', 'allowed')) {
                            if ($ext::allowed() && !$products['manual_control_status']) {
                                $response_list[count($response_list) - 1]['DT_RowClass'] = 'check_status_auto';
                            }
                        }
                        //$categories_query_numrows++;
                        $rows_counter++;
                        if ($rows_counter >= $length) {
                            break;
                        }
                    }
                }
            }
            //--- Apply filter end
        } elseif ($output['listing_type'] == 'category') {
            $list_bread_crumb = TEXT_CATALOG_LIST_BREADCRUMB . ' ';
            $list_bread_crumb .= ' &gt; ' . \common\helpers\Categories::output_generated_category_path($current_category_id, 'category', '<span class="category_path__location clickable_element js-category-navigate" data-id="%1$s">%2$s</span>');
            $order_by_category = 'c.sort_order, cd.categories_name';
            $order_by_product = 'p2c.sort_order, pd.products_name';
            $rows_counter = 0;
            $categories_query_raw = 'select distinct(c.categories_id), if(length(cd.categories_name) > 0, cd.categories_name, cdd.categories_name) as categories_name, c.categories_status, c.manual_control_status, c.categories_image from ' . TABLE_CATEGORIES . ' c left join ' . TABLE_CATEGORIES_DESCRIPTION . ' cd on c.categories_id=cd.categories_id left join ' . TABLE_CATEGORIES_DESCRIPTION . ' cdd on c.categories_id=cdd.categories_id ' . $search_condition . " and cd.language_id = '" . (int) $languages_id . "' and cdd.language_id = '" . \common\helpers\Language::get_default_language_id() . "' and cd.affiliate_id = 0 " . $platform_filter_categories . ' order by ' . $order_by_category;
            $remind_page_number = $current_page_number;
            $categories_split = new \Split_Page_Results($current_page_number, $length, $categories_query_raw, $categories_query_numrows, 'c.categories_id');
            $categories_query = tep_db_query($categories_query_raw);
            if ($current_category_id > 0) {
                $parrent_query = tep_db_query('select parent_id, categories_status from ' . TABLE_CATEGORIES . " where categories_id = '" . (int) $current_category_id . "'");
                if ($parrent = tep_db_fetch_array($parrent_query)) {
                    $response_list[] = ['', '<span class="parent_cats"><i class="icon-circle"></i><i class="icon-circle"></i><i class="icon-circle"></i></span><input class="cell_identify" type="hidden" value="' . $parrent['parent_id'] . '"><input class="cell_type" type="hidden" value="parent">', ''];
                }
            }
            $categories_qty = $categories_query_numrows;
            if ($remind_page_number == $current_page_number) {
                // all categories showed, now show only products
                while ($categories = tep_db_fetch_array($categories_query)) {
                    $image_path = DIR_WS_CATALOG_IMAGES . $categories['categories_image'];
                    $response_list[] = ['<input type="checkbox"' . ($disable_category_item ? ' disabled' : '') . ' class="' . ($categories_qty < CATALOG_SPEED_UP_DESIGN ? 'uniform' : '') . ' js-cat-batch" name="batch[]" value="c_' . $categories['categories_id'] . '">', '<div class="handle_cat_list' . ($categories['categories_status'] == 1 ? '' : ' dis_prod') . '"><span class="handle"><i class="icon-hand-paper-o"></i></span><div class="cat_name' . ($categories['categories_image'] ? ' catNameImg' : '') . '">' . ($categories['categories_image'] ? '<span class="prodCatImg"><img src="' . $image_path . '"></span>' : '') . '<b>' . $categories['categories_name'] . '</b><input class="cell_identify" type="hidden" value="' . $categories['categories_id'] . '"><input class="cell_type" type="hidden" value="category"></div></div>', $categories['categories_status'] == 1 ? '<input type="checkbox" value="' . $categories['categories_id'] . '" name="categories_status" class="' . ($categories_qty < CATALOG_SPEED_UP_DESIGN ? 'check_on_off' : 'check_on_off_check') . '" checked="checked"' . ($disable_category_item ? ' readonly' : '') . '>' : '<input type="checkbox" value="' . $categories['categories_id'] . '" name="categories_status" class="' . ($categories_qty < CATALOG_SPEED_UP_DESIGN ? 'check_on_off' : 'check_on_off_check') . '"' . ($disable_category_item ? ' readonly' : '') . '>'];
                    if ($ext = \common\helpers\Acl::check_extension('AutomaticallyStatus', 'allowed')) {
                        if ($ext::allowed() && !$categories['manual_control_status']) {
                            $response_list[count($response_list) - 1]['DT_RowClass'] = 'check_status_auto';
                        }
                    }
                    $rows_counter++;
                }
            }
            /**
             * Recalc products offset
             */
            $offset = $start - $categories_query_numrows;
            $products_in_category = \common\models\Products::find()->alias('p')->select('p.products_id, p.is_listing_product, p.sub_product_children_count, p.parent_products_id, p.products_groups_id, p.products_model, p.products_status, p.manual_control_status, p.products_image, p.products_quantity')->add_select(['products_name' => new \yii\db\Expression(Product_Name_Decorator::instance()->listing_query_expression('pd', 'pdd'))])->w_description('pd')->w_description('pdd', \common\helpers\Language::get_default_language_id())->inner_join_with(['categoriesList p2c' => function ($query) use ($current_category_id) {
                $query->and_on_condition(['p2c.categories_id' => (int) $current_category_id]);
            }]);
            if (!empty($search_filter)) {
                $products_in_category->and_where($search_filter);
            }
            $products_in_category->and_where('1 ' . $platform_filter_products);
            $products_qty = $products_query_numrows = $products_in_category->count();
            $categories_query_numrows += $products_query_numrows;
            if ($rows_counter < $length) {
                $products_in_category = $products_in_category->order_by($order_by_product)->offset(max($offset, 0))->limit($length);
                $products_query_raw = $products_in_category->create_command()->get_raw_sql();
                // backward compatibility
                $products_all = $products_in_category->as_array()->all();
                foreach ($products_all as $products) {
                    if (empty($products['products_name'])) {
                        $products['products_name'] = \common\helpers\Product::get_products_name($products['products_id']);
                    }
                    // (file_exists(DIR_FS_CATALOG_IMAGES . $products['products_image']) ? '<span class="prodImgC">' . \common\helpers\Image::info_image($products['products_image'], $products['products_name'], 50, 50) . '</span>' : '<span class="cubic"></span>')
                    $image = \common\classes\Images::get_image($products['products_id']);
                    $product_categories_string = '';
                    if (true) {
                        $product_categories = \common\helpers\Categories::generate_category_path($products['products_id'], 'product');
                        if (count($product_categories) > 1) {
                            $product_categories_string .= '';
                            for ($i = 0, $n = sizeof($product_categories); $i < $n; $i++) {
                                $category_path = '';
                                if (intval($product_categories[$i][count($product_categories[$i]) - 1]['id']) == (int) $current_category_id) {
                                    continue;
                                }
                                for ($j = 0, $k = sizeof($product_categories[$i]); $j < $k; $j++) {
                                    $category_path .= '<span class="category_path__location">' . $product_categories[$i][$j]['text'] . '</span>&nbsp;&gt;&nbsp;';
                                }
                                $category_path = substr($category_path, 0, -16);
                                $product_categories_string .= '<li class="category_path">' . $category_path . '</li>';
                            }
                            $product_categories_string = '<span class="category_path" style="display:block">' . TEXT_LIST_PRODUCT_ALSO_PLACED_IN . '</span> <ul class="category_path_list">' . $product_categories_string . '</ul>';
                        }
                    }
                    $product_markers = '';
                    if (defined('LISTING_SUB_PRODUCT') && LISTING_SUB_PRODUCT == 'True') {
                        if ($products['is_listing_product']) {
                            $product_markers .= '<i class="product_list_marker product_list_marker__listing">' . TEXT_LISTING_PRODUCT . '</i> ';
                        } else {
                            $product_markers .= '<i class="product_list_marker product_list_marker__master">' . TEXT_MASTER_PRODUCT . '</i> ';
                        }
                        if ($products['parent_products_id']) {
                            $products['products_quantity'] = \common\helpers\Product::get_products_info($products['parent_products_id'], 'products_quantity');
                            $__link_content = \common\helpers\Product::get_products_info($products['parent_products_id'], 'products_model');
                            if ($__link_content) {
                                $__link_content = "{$__link_content} ";
                            }
                            $__link_content .= \common\helpers\Product::get_backend_products_name($products['parent_products_id']);
                            $parent_product_name = TEXT_PARENT_PRODUCT . ' ' . Html::a($__link_content, Url::to(['categories/productedit', 'pID' => $products['parent_products_id']]));
                            $product_markers .= '<i class="product_list_marker product_list_marker__child_of">' . TEXT_CHILD_PRODUCT . '<div class="product_list_marker__pophover">' . $parent_product_name . '</div></i> ';
                        } elseif ($products['sub_product_children_count'] > 0) {
                            $children_products = \yii\helpers\Array_Helper::map(\common\models\Products::find()->where(['parent_products_id' => $products['products_id']])->select(['products_id', 'products_model'])->as_array()->all(), 'products_id', 'products_model');
                            foreach ($children_products as $children_product_id => $children_product_model) {
                                $children_products[$children_product_id] = '<div>' . TEXT_CHILD_PRODUCT . ' ' . Html::a(($children_product_model ? "{$children_product_model} " : '') . \common\helpers\Product::get_backend_products_name($children_product_id), Url::to(['categories/productedit', 'pID' => $children_product_id])) . '</div>';
                            }
                            $product_markers .= '<i class="product_list_marker product_list_marker__parent_of">' . TEXT_PARENT_PRODUCT . '<div class="product_list_marker__pophover">' . implode('', $children_products) . '</div></i> ';
                        }
                    }
                    if ($products['products_groups_id']) {
                        $product_categories_string = '<i class="next-row category_path product_list__products_group">' . \common\helpers\Product::products_groups_name($products['products_groups_id']) . '</i>' . $product_categories_string;
                    }
                    $response_list[] = ['<input type="checkbox"' . ($disable_product_item ? ' disabled' : '') . ' class="' . ($products_qty < CATALOG_SPEED_UP_DESIGN ? 'uniform' : '') . ' js-cat-batch" name="batch[]" value="p_' . $products['products_id'] . '">', '<div class="handle_cat_list prod_handle' . ($products['products_status'] == 1 ? '' : ' dis_prod') . '">' . '<span class="handle"><i class="icon-hand-paper-o"></i></span>' . '<div class="prod_name prod_name_double" data-click-double="' . tep_href_link(FILENAME_CATEGORIES . '/productedit', 'pID=' . $products['products_id']) . '">' . (!empty($image) ? '<span class="prodImgC">' . $image . '</span>' : '<span class="cubic"></span>') . '<table class="wrapper"><tr><td><span class="prodNameC">' . $products['products_name'] . $product_categories_string . '</span></td></tr></table>' . '<span class="prodIDsC"><span title="' . \common\helpers\Output::output_string($products['products_model']) . '">' . ($products['products_model'] ? TEXT_SKU . ': ' . $products['products_model'] . '<br>' : '') . TEXT_PRODUCTS_QUANTITY_INFO . ': ' . \common\helpers\Product::get_virtual_item_quantity($products['products_id'], $products['products_quantity']) . '<br>' . TABLE_HEADING_ID . ': ' . $products['products_id'] . '</span>' . $product_markers . '</span>' . '<input class="cell_identify" type="hidden" value="' . $products['products_id'] . '">' . '<input class="cell_type" type="hidden" value="product">' . '</div>' . '</div>', $products['products_status'] == 1 ? '<input type="checkbox" value="' . $products['products_id'] . '" name="products_status" class="' . ($products_qty < CATALOG_SPEED_UP_DESIGN ? 'check_on_off' : 'check_on_off_check') . '" checked="checked"' . ($disable_product_item ? ' readonly' : '') . '>' : '<input type="checkbox" value="' . $products['products_id'] . '" name="products_status" class="' . ($products_qty < CATALOG_SPEED_UP_DESIGN ? 'check_on_off' : 'check_on_off_check') . '"' . ($disable_product_item ? ' readonly' : '') . '>'];
                    if ($ext = \common\helpers\Acl::check_extension('AutomaticallyStatus', 'allowed')) {
                        if ($ext::allowed() && !$products['manual_control_status']) {
                            $response_list[count($response_list) - 1]['DT_RowClass'] = 'check_status_auto';
                        }
                    }
                    //$categories_query_numrows++;
                    $rows_counter++;
                    if ($rows_counter >= $length) {
                        break;
                    }
                }
            }
        } else {
            // BRAND listing
            $list_bread_crumb = '';
            $ff = empty($search_filter) ? '' : ' and ' . $search_filter . ' ';
            $order = 'p.sort_order, pd.products_name';
            $products_query_raw = 'select *, ' . Product_Name_Decorator::instance()->listing_query_expression('pd', '') . ' as products_name, p.products_groups_id from ' . TABLE_PRODUCTS . ' p ' . (intval($output['brand_id']) == -1 ? ' left join ' . TABLE_MANUFACTURERS . ' m ON m.manufacturers_id=p.manufacturers_id ' : '') . ' left join ' . TABLE_PRODUCTS_DESCRIPTION . " pd on (p.products_id = pd.products_id and pd.language_id='" . intval($languages_id) . "') where pd.platform_id = '" . intval(\common\classes\platform::default_id()) . "' " . (intval($output['brand_id']) > 0 ? " and manufacturers_id = '" . intval($output['brand_id']) . "' " : (intval($output['brand_id']) == -1 ? ' and m.manufacturers_id IS NULL' : '')) . $ff . " {$platform_filter_products} group by p.products_id ORDER BY " . $order;
            $products_split = new \Split_Page_Results($current_page_number, $length, $products_query_raw, $categories_query_numrows, 'p.products_id');
            $products_query = tep_db_query($products_query_raw);
            $products_qty = $categories_query_numrows;
            while ($products = tep_db_fetch_array($products_query)) {
                if (empty($products['products_name'])) {
                    $products['products_name'] = \common\helpers\Product::get_products_name($products['products_id']);
                }
                $image = \common\classes\Images::get_image($products['products_id']);
                $product_categories_string = '';
                if (true) {
                    $product_categories = \common\helpers\Categories::generate_category_path($products['products_id'], 'product');
                    $product_categories_string .= '';
                    for ($i = 0, $n = sizeof($product_categories); $i < $n; $i++) {
                        $category_path = '';
                        for ($j = 0, $k = sizeof($product_categories[$i]); $j < $k; $j++) {
                            $category_path .= '<span class="category_path__location">' . $product_categories[$i][$j]['text'] . '</span>&nbsp;&gt;&nbsp;';
                        }
                        $category_path = substr($category_path, 0, -16);
                        $product_categories_string .= '<li class="category_path">' . $category_path . '</li>';
                    }
                    $product_categories_string = '<span class="category_path" style="display:block">' . TEXT_LIST_PRODUCT_PLACED_IN . '</span> <ul class="category_path_list">' . $product_categories_string . '</ul>';
                }
                $product_markers = '';
                if (defined('LISTING_SUB_PRODUCT') && LISTING_SUB_PRODUCT == 'True') {
                    if ($products['is_listing_product']) {
                        $product_markers .= '<i class="product_list_marker product_list_marker__listing">' . TEXT_LISTING_PRODUCT . '</i> ';
                    } else {
                        $product_markers .= '<i class="product_list_marker product_list_marker__master">' . TEXT_MASTER_PRODUCT . '</i> ';
                    }
                    if ($products['parent_products_id']) {
                        $products['products_quantity'] = \common\helpers\Product::get_products_info($products['parent_products_id'], 'products_quantity');
                        $__link_content = \common\helpers\Product::get_products_info($products['parent_products_id'], 'products_model');
                        if ($__link_content) {
                            $__link_content = "{$__link_content} ";
                        }
                        $__link_content .= \common\helpers\Product::get_backend_products_name($products['parent_products_id']);
                        $parent_product_name = TEXT_PARENT_PRODUCT . ' ' . Html::a($__link_content, Url::to(['categories/productedit', 'pID' => $products['parent_products_id']]));
                        $product_markers .= '<i class="product_list_marker product_list_marker__child_of">' . TEXT_CHILD_PRODUCT . '<div class="product_list_marker__pophover">' . $parent_product_name . '</div></i> ';
                    } elseif ($products['sub_product_children_count'] > 0) {
                        $children_products = \yii\helpers\Array_Helper::map(\common\models\Products::find()->where(['parent_products_id' => $products['products_id']])->select(['products_id', 'products_model'])->as_array()->all(), 'products_id', 'products_model');
                        foreach ($children_products as $children_product_id => $children_product_model) {
                            $children_products[$children_product_id] = '<div>' . TEXT_CHILD_PRODUCT . ' ' . Html::a(($children_product_model ? "{$children_product_model} " : '') . \common\helpers\Product::get_backend_products_name($children_product_id), Url::to(['categories/productedit', 'pID' => $children_product_id])) . '</div>';
                        }
                        $product_markers .= '<i class="product_list_marker product_list_marker__parent_of">' . TEXT_PARENT_PRODUCT . '<div class="product_list_marker__pophover">' . implode('', $children_products) . '</div></i> ';
                    }
                }
                if ($products['products_groups_id']) {
                    $product_categories_string = '<i class="next-row category_path product_list__products_group">' . \common\helpers\Product::products_groups_name($products['products_groups_id']) . '</i>' . $product_categories_string;
                }
                $response_list[] = [
                    '<input type="checkbox" class="' . ($products_qty < CATALOG_SPEED_UP_DESIGN ? 'uniform' : '') . ' js-cat-batch" name="batch[]" value="p_' . $products['products_id'] . '">',
                    '<div class="handle_cat_list' . ($products['products_status'] == 1 ? '' : ' dis_prod') . '">' . '<span class="handle"><i class="icon-hand-paper-o"></i></span>' . '<div class="prod_name prod_name_double" data-click-double="' . tep_href_link(FILENAME_CATEGORIES . '/productedit', 'pID=' . $products['products_id']) . '">' . (!empty($image) ? '<span class="prodImgC">' . $image . '</span>' : '<span class="cubic"></span>') . '<table class="wrapper"><tr><td><span class="prodNameC">' . $products['products_name'] . $product_categories_string . '</span></td></tr></table>' . '<span class="prodIDsC"><span title="' . \common\helpers\Output::output_string($products['products_model']) . '">' . ($products['products_model'] ? TEXT_SKU . ': ' . $products['products_model'] . '<br>' : '') . TEXT_PRODUCTS_QUANTITY_INFO . ': ' . \common\helpers\Product::get_virtual_item_quantity($products['products_id'], $products['products_quantity']) . '<br>' . TABLE_HEADING_ID . ': ' . $products['products_id'] . '</span>' . $product_markers . '</span>' . '<input class="cell_identify" type="hidden" value="' . $products['products_id'] . '">' . '<input class="cell_type" type="hidden" value="product" data-id="products-' . $products['products_id'] . '">' . '</div>' . '</div>',
                    //$products['products_status']
                    $products['products_status'] == 1 ? '<input type="checkbox" value="' . $products['products_id'] . '" name="products_status" class="' . ($products_qty < CATALOG_SPEED_UP_DESIGN ? 'check_on_off' : 'check_on_off_check') . '" checked="checked">' : '<input type="checkbox" value="' . $products['products_id'] . '" name="products_status" class="' . ($products_qty < CATALOG_SPEED_UP_DESIGN ? 'check_on_off' : 'check_on_off_check') . '">',
                ];
                if ($ext = \common\helpers\Acl::check_extension('AutomaticallyStatus', 'allowed')) {
                    if ($ext::allowed() && !$products['manual_control_status']) {
                        $response_list[count($response_list) - 1]['DT_RowClass'] = 'check_status_auto';
                    }
                }
                //$categories_query_numrows++;
            }
        }
        if (tep_not_null($products_query_raw ?? null)) {
            $_session->set('products_query_raw', $products_query_raw);
        }
        $response = ['draw' => $draw, 'recordsTotal' => $categories_query_numrows, 'recordsFiltered' => $categories_query_numrows, 'data' => $response_list, 'categories' => $categories_qty, 'products' => $products_qty, 'breadcrumb' => $list_bread_crumb];
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $response;
    }
    public function action_categoryactions()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/categories');
        global $navigation;
        if (is_object($navigation) && method_exists($navigation, 'set_snapshot')) {
            $navigation->set_snapshot(['page' => 'categories', 'get' => Yii::$app->request->post('get')], true);
        }
        $this->layout = false;
        $categories_id = Yii::$app->request->post('categories_id', 0);
        if ($categories_id > 0) {
            $categories_query = tep_db_query('select c.categories_id, cd.categories_name, c.categories_image, c.parent_id, c.sort_order, c.date_added, c.last_modified, c.categories_status, c.last_xml_export from ' . TABLE_CATEGORIES . ' c, ' . TABLE_CATEGORIES_DESCRIPTION . " cd where c.categories_id = '" . (int) $categories_id . "' and c.categories_id = cd.categories_id and cd.affiliate_id = 0 and cd.language_id = '" . (int) $languages_id . "'");
            $categories = tep_db_fetch_array($categories_query);
            $category_childs = ['childs_count' => \common\helpers\Categories::childs_in_category_count($categories['categories_id'])];
            $category_products = ['products_count' => \common\helpers\Categories::products_in_category_count($categories['categories_id'])];
            $c_info_array = array_merge($categories, $category_childs, $category_products);
            $c_info = new \Object_Info($c_info_array);
            $c_info->has_groupped_products = Categories::has_groupped_products($categories_id, true);
            $c_info->event_info = null;
            if ($es = \common\helpers\Extensions::is_allowed('EventSystem')) {
                $c_info->event_info = $es::event()->exec('getEventInformation', [$categories_id]);
            }
            return $this->render('categoryactions.tpl', ['cInfo' => $c_info]);
        }
    }
    public function action_productactions()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/categories');
        $currencies = Yii::$container->get('currencies');
        global $navigation;
        if (is_object($navigation) && method_exists($navigation, 'set_snapshot')) {
            $navigation->set_snapshot(['page' => 'categories', 'get' => Yii::$app->request->post('get')], true);
        }
        $this->layout = false;
        $categories_id = intval(Yii::$app->request->post('categories_id', 0));
        $products_id = Yii::$app->request->post('products_id');
        $p = \common\models\Products::find()->and_where(['products_id' => (int) $products_id])->with('description')->with('platforms')->with('localRating');
        if (tep_session_is_registered('login_vendor')) {
            global $login_id;
            $p->and_where(['vendor_id' => $login_id]);
        }
        $p_info = $p->one();
        if ($p_info->parent_products_id) {
            $p_info->products_quantity = \common\helpers\Product::get_products_info($p_info->parent_products_id, 'products_quantity');
        }
        $image = \common\classes\Images::get_image($p_info->products_id, 'Small');
        echo '<div class="prod_box_img">' . $image . '</div>';
        echo '<div class="or_box_head prod_head_box">' . $p_info->description->products_name . '</div>';
        echo '<div class="row_or_wrapp">';
        echo '<div class="row_or">
                    <div>' . TEXT_MODEL_SKU . '</div>
                    <div>' . \common\helpers\Output::output_string($p_info->products_model) . '</div>
             </div>';
        echo '<div class="row_or">
                    <div>' . TEXT_DATE_ADDED . '</div>
                    <div>' . \common\helpers\Date::date_short($p_info->products_date_added) . '</div>
             </div>';
        if (tep_not_null($p_info->products_last_modified)) {
            echo '<div class="row_or">
                <div>' . TEXT_LAST_MODIFIED . '</div>
                <div>' . \common\helpers\Date::date_short($p_info->products_last_modified) . '</div>
         </div>';
        }
        if (date('Y-m-d') < $p_info->products_date_available) {
            echo '<div class="row_or">
                <div>' . TEXT_DATE_AVAILABLE . '</div>
                <div>' . \common\helpers\Date::date_short($p_info->products_date_available) . '</div>
         </div>';
        }
        if (USE_MARKET_PRICES == 'True') {
            echo '<div class="row_or">
                    <div>' . TEXT_PRODUCTS_PRICE_INFO . '</div>
                    <div>' . $currencies->format(\common\helpers\Product::get_products_price($p_info->products_id, 1, 0, $currencies->currencies[DEFAULT_CURRENCY]['id'])) . '</div>
             </div>';
            echo '<div class="row_or">
                   <div>' . TEXT_PRODUCTS_QUANTITY_INFO . '</div>
                   <div>' . \common\helpers\Product::get_virtual_item_quantity($p_info->products_id, $p_info->products_quantity) . '</div>
            </div>';
        } else {
            echo '<div class="row_or">
                    <div>' . TEXT_PRODUCTS_PRICE_INFO . '</div>
                    <div>' . $currencies->format($p_info->products_price) . '</div>
             </div>';
            echo '<div class="row_or">
                    <div>' . TEXT_PRODUCTS_QUANTITY_INFO . '</div>
                    <div>' . \common\helpers\Product::get_virtual_item_quantity($p_info->products_id, $p_info->products_quantity) . '</div>
             </div>';
        }
        echo '<div class="row_or">
                    <div>' . TEXT_PRODUCTS_AVERAGE_RATING . '</div>
                    <div>' . number_format($p_info->local_rating[0]->average_rating ?? 0, 2) . '%</div>
             </div>';
        echo '<div class="row_or">
                    <div>' . TEXT_SORT_ORDER . '</div>
                    <div>' . $p_info->sort_order . '</div>
             </div>';
        echo '</div>';
        echo '<div class="btn-toolbar btn-toolbar-order">';
        if (\common\helpers\Acl::rule(['TABLE_HEADING_PRODUCTS', 'IMAGE_EDIT'])) {
            echo '<a class="btn btn-primary btn-process-order btn-edit" href="' . tep_href_link(FILENAME_CATEGORIES . '/productedit', 'pID=' . $p_info->products_id) . '">' . IMAGE_EDIT . '</a>';
        }
        if (defined('LISTING_SUB_PRODUCT') && LISTING_SUB_PRODUCT == 'True') {
            if (!$p_info->parent_products_id && \common\helpers\Acl::rule(['TABLE_HEADING_PRODUCTS', 'IMAGE_EDIT'])) {
                echo '<a class="btn btn-primary btn-process-order btn-new" href="' . Yii::$app->url_manager->create_url(['categories/productedit', 'category_id' => $categories_id, 'parentID' => $p_info->products_id]) . '">' . BUTTON_CREATE_LISTING_PRODUCT . '</a>';
                if ($p_info->sub_product_children_count == 0) {
                    echo '<a class="btn btn-primary btn-process-order btn-new actionPopup" href="' . Yii::$app->url_manager->create_url(['categories/listing-attach', 'product_id' => $p_info->products_id]) . '">' . BUTTON_ATTACH_TO_PARENT_LISTING_PRODUCT . '</a>';
                }
            } elseif ($p_info->parent_products_id && \common\helpers\Acl::rule(['TABLE_HEADING_PRODUCTS', 'IMAGE_EDIT'])) {
                echo '<a class="btn btn-primary btn-process-order btn-new actionPopup" href="' . Yii::$app->url_manager->create_url(['categories/listing-detach', 'product_id' => $p_info->products_id]) . '">' . BUTTON_DETACH_LISTING_PRODUCT . '</a>';
            }
        }
        if (\common\helpers\Acl::rule(['TABLE_HEADING_PRODUCTS', 'IMAGE_DELETE'])) {
            echo '<button class="btn btn-delete btn-no-margin" onclick="confirmDeleteProduct(' . $p_info->products_id . ')">' . IMAGE_DELETE . '</button>';
        }
        if (\common\helpers\Acl::rule(['TABLE_HEADING_PRODUCTS', 'IMAGE_MOVE'])) {
            echo '<button class="btn btn-move" onclick="confirmMoveProduct(' . $p_info->products_id . ')">' . IMAGE_MOVE . '</button>';
        }
        if (\common\helpers\Acl::rule(['TABLE_HEADING_PRODUCTS', 'IMAGE_COPY_TO'])) {
            echo '<button class="btn btn-copy btn-no-margin" onclick="confirmCopyProduct(' . $p_info->products_id . ')">' . IMAGE_COPY_TO . '</button>';
        }
        if (!tep_session_is_registered('login_vendor')) {
            if (\common\helpers\Acl::rule(['TABLE_HEADING_PRODUCTS', 'IMAGE_COPY_ATTRIBUTES'])) {
                echo '<button class="btn" onclick="confirmCopyProductAttr(' . $p_info->products_id . ')">' . IMAGE_COPY_ATTRIBUTES . '</button>';
            }
            /* if ($pID) {
               echo '<div>' . ATTRIBUTES_NAMES_HELPER . '</div>';
               } */
        }
        /*EP Sync now*/
        $ns_block = '';
        if (\common\helpers\Acl::check_extension_allowed('NetSuite') && \common\extensions\Net_Suite\helpers\Net_Suite_Helper::any_configured()) {
            $r = tep_db_query('select local_products_id, remote_products_id, ld.directory_id, ld.directory  ' . " from ep_directories ld left join ep_holbi_soap_link_products lp on ld.directory_id=lp.ep_directory_id and local_products_id='" . (int) $p_info->products_id . "'" . " where ld.directory_config like '%NetSuiteLink%'  and ld.directory_type='datasource' " . ' ');
            while ($d = tep_db_fetch_array($r)) {
                $ns_block = '<div class="ep-sync ep-sync-ns"> <div class="ns-info">' . $d['directory'] . ' ' . ((int) $d['remote_products_id'] > 0 ? '  <a class="sync" target="_blank" href="https://system.netsuite.com/app/common/item/item.nl?id=' . $d['remote_products_id'] . '">' . TEXT_VIEW_NS . '</a>' : '') . '</div><div class="ns-buttons"><button class="btn btn-sync btn-no-margin" onclick="linkNS(\'' . $d['remote_products_id'] . '\',' . (int) $p_info->products_id . ',' . (int) $d['directory_id'] . ')">' . TEXT_UPDATE_EXTERNAL_ID . '</button>' . ((int) $d['remote_products_id'] > 0 ? ' <button class="btn btn-sync btn-no-margin" onclick="confirmSyncNow(' . $d['remote_products_id'] . ',' . (int) $p_info->products_id . ',' . $d['directory_id'] . ')">' . TEXT_SYNC_NOW . '</button>' : '') . '</div></div>';
            }
            if (\common\helpers\Acl::rule(['BOX_HEADING_CATALOG', 'BOX_CATALOG_EASYPOPULATE'])) {
                echo $ns_block;
            }
        }
        /*EP Sync now*/
        /* @var $ext \common\extensions\ProductEasyView\ProductEasyView */
        if ($ext = \common\helpers\Acl::check_extension_allowed('ProductEasyView', 'allowed')) {
            $ext::admin_adction_product($p_info->products_id);
        } else {
            echo '<a class="btn btn-primary btn-no-margin btn-easy-view dis_module" disabled href="javascript:void(0)">' . IMAGE_EASY_VIEW . '</a>';
        }
        $choose_platform_popup = '';
        $single_platform_link = '';
        $platforms_assigned = 0;
        foreach (\common\classes\platform::get_list(false) as $frontend) {
            if ($p_info->platforms && isset($p_info->platforms[$frontend['id']])) {
                $platforms_assigned++;
                $seo_url = \common\helpers\Product::get_seo_name((int) $p_info->products_id, (int) $languages_id, $frontend['id']);
                if ($seo_url) {
                    $single_platform_link = 'http://' . $frontend['platform_url'] . '/' . $seo_url;
                    $choose_platform_popup .= '<p><a href="http://' . $frontend['platform_url'] . '/' . $seo_url . '" target="_blank">' . $frontend['text'] . '</a></p>';
                } else {
                    $single_platform_link = 'http://' . $frontend['platform_url'] . '/catalog/product?products_id=' . $p_info->products_id;
                    $choose_platform_popup .= '<p><a href="http://' . $frontend['platform_url'] . '/catalog/product?products_id=' . $p_info->products_id . '" target="_blank">' . $frontend['text'] . '</a></p>';
                }
            }
        }
        if ($single_platform_link != '' && $platforms_assigned > 1) {
            echo '<a href="#choose-frontend" class="btn btn-primary btn-choose-frontend">' . TEXT_PREVIEW_ON_SITE . '</a>';
            echo '<div id="choose-frontend" style="display: none">
            <div class="popup-heading">Choose frontend</div>
            <div class="popup-content frontend-links">
          ' . $choose_platform_popup . '
            </div>
            <div class="noti-btn">
              <div><button class="btn btn-cancel">Cancel</button></div>
            </div>
            <script type="text/javascript">
              (function($){
                $(function(){
                  $(\'.popup-box-wrap .frontend-links a\').on(\'click\', function(){
                    $(\'.popup-box-wrap\').remove()
                  });
                  $(\'.btn-choose-frontend\').popUp();
                })
              })(jQuery)
            </script>
          </div>';
        } elseif ($single_platform_link != '' && $p_info->platforms && $platforms_assigned == 1) {
            echo '<a href="' . $single_platform_link . '" target="_blank" class="btn btn-primary">' . TEXT_PREVIEW_ON_SITE . '</a>';
        }
        echo '<a class="btn btn-primary btn-process-order btn-new actionPopup" href="#print-product-label">Product label</a>';
        echo '<div id="print-product-label" style="display: none">
            <div class="popup-heading">Print product label</div>
            <form method="get" target="_blank" action="' . Yii::$app->url_manager->create_url(['categories/product-label']) . '">
            <div class="popup-content">
            Print ' . \common\helpers\Html::text_input('count', 1, ['id' => 'countLabelCopies', 'style' => 'width: 60px;display: inline-block;vertical-align: middle;']) . ' copies "' . $p_info->products_model . '".
            ' . \common\helpers\Html::hidden_input('model', $p_info->products_model) . '
            </div>
            <div class="noti-btn">
              <div><button class="btn btn-cancel" type="button">Cancel</button></div>
              <div><button class="btn btn-primary" type="submit" onclick="setTimeout(function(){closePopup();},10);">Print</button></div>
            </div>
            </form>

          </div>';
        echo '</div>';
    }
    public function action_sort_products()
    {
        \common\helpers\Translation::init('admin/categories');
        $ret = [];
        $this->layout = false;
        $categories_id = (int) Yii::$app->request->post('categories_id', 0);
        $recursively = (int) Yii::$app->request->post('recursively', 0);
        if ($categories_id > 0) {
            if ($recursively) {
                $cats = \common\models\Categories::find_one($categories_id)->get_descendants(null, true)->select('categories_id')->order_by([])->as_array()->column();
            } else {
                $cats = [$categories_id];
            }
            if (is_array($cats)) {
                foreach ($cats as $cat) {
                    \common\helpers\Product::in_category_sort_reindex_groupped($cat);
                }
            }
            $ret = ['status' => 'OK'];
        }
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        Yii::$app->response->data = $ret;
    }
    public function action_confirm_category_move()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/categories');
        $this->layout = false;
        $categories_id = Yii::$app->request->post('categories_id');
        $categories_query = tep_db_query('select c.categories_id, cd.categories_name, c.categories_image, c.parent_id, c.sort_order, c.date_added, c.last_modified, c.categories_status, c.last_xml_export from ' . TABLE_CATEGORIES . ' c, ' . TABLE_CATEGORIES_DESCRIPTION . " cd where c.categories_id = '" . (int) $categories_id . "' and c.categories_id = cd.categories_id and cd.affiliate_id = 0 and cd.language_id = '" . (int) $languages_id . "'");
        $categories = tep_db_fetch_array($categories_query);
        $category_childs = ['childs_count' => \common\helpers\Categories::childs_in_category_count($categories['categories_id'])];
        $category_products = ['products_count' => \common\helpers\Categories::products_in_category_count($categories['categories_id'])];
        $c_info_array = array_merge($categories, $category_childs, $category_products);
        $c_info = new \Object_Info($c_info_array);
        $category_tree = \common\helpers\Categories::get_category_tree(0, '', $c_info->categories_id);
        return $this->render('confirmcategorymove.tpl', ['cInfo' => $c_info, 'categoryTree' => $category_tree]);
    }
    public function action_category_move()
    {
        $this->layout = false;
        if (\common\helpers\Acl::rule(['TEXT_CATEGORIES', 'IMAGE_MOVE'])) {
            $categories_id = Yii::$app->request->post('categories_id');
            $parent_id = Yii::$app->request->post('move_to_category_id');
            if ($categories_id != $parent_id && !in_array($categories_id, \common\helpers\Categories::get_category_parents_ids($parent_id))) {
                tep_db_query('update ' . TABLE_CATEGORIES . " set parent_id = '" . (int) $parent_id . "' where categories_id = '" . (int) $categories_id . "'");
            }
            \common\helpers\Categories::update_categories();
        }
        $this->view->categories_tree = $this->get_category_tree();
        if ($categories_id > 0) {
            $this->view->categories_opened_tree = \common\helpers\Categories::get_category_parents_ids($categories_id);
            \common\components\Categories_Cache::get_cpc()::invalidate_categories($categories_id);
        } else {
            $this->view->categories_opened_tree = [];
        }
        $this->view->categories_closed_tree = array_diff(array_map('intval', explode('|', \Yii::$app->session->get('closed_data'))), $this->view->categories_opened_tree);
        $collapsed = $this->default_collapsed;
        return $this->render('cat_main_box', ['directOutput' => true, 'collapsed' => $collapsed]);
    }
    public function action_confirmcategorydelete()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/categories');
        $this->layout = false;
        if (Yii::$app->request->is_post) {
            $categories_id = Yii::$app->request->post('categories_id');
        } else {
            $categories_id = Yii::$app->request->get('categories_id');
        }
        $categories_query = tep_db_query('select c.categories_id, cd.categories_name, c.categories_image, c.parent_id, c.sort_order, c.date_added, c.last_modified, c.categories_status, c.last_xml_export from ' . TABLE_CATEGORIES . ' c, ' . TABLE_CATEGORIES_DESCRIPTION . " cd where c.categories_id = '" . (int) $categories_id . "' and c.categories_id = cd.categories_id and cd.affiliate_id = 0 and cd.language_id = '" . (int) $languages_id . "'");
        $categories = tep_db_fetch_array($categories_query);
        $category_childs = ['childs_count' => \common\helpers\Categories::childs_in_category_count($categories['categories_id'])];
        $category_products = ['products_count' => \common\helpers\Categories::products_in_category_count($categories['categories_id'])];
        $c_info_array = array_merge($categories, $category_childs, $category_products);
        $c_info = new \Object_Info($c_info_array);
        echo '<div class="or_box_head">' . TEXT_INFO_HEADING_DELETE_CATEGORY . '</div>';
        echo tep_draw_form('categories', FILENAME_CATEGORIES, \common\helpers\Output::get_all_get_params(['action']) . 'action=delete_category_confirm', 'post', 'id="categories_edit" onSubmit="return deleteCategory();"');
        echo '<div class="col_title">' . TEXT_DELETE_CATEGORY_INTRO . '</div>';
        echo '<div class="col_desc">' . $c_info->categories_name . '</div>';
        if ($c_info->childs_count > 0) {
            echo '<div class="col_desc">' . sprintf(TEXT_DELETE_WARNING_CHILDS, $c_info->childs_count) . '</div>';
        }
        if ($c_info->products_count > 0) {
            echo '<div class="col_desc">' . sprintf(TEXT_DELETE_WARNING_PRODUCTS, $c_info->products_count) . '</div>';
        }
        ?>
        <div class="btn-toolbar btn-toolbar-order">
            <button class="btn btn-delete btn-no-margin"><?php 
        echo IMAGE_DELETE;
        ?></button><button class="btn btn-cancel" onClick="return resetStatement()"><?php 
        echo IMAGE_CANCEL;
        ?></button>
            <?php 
        /* echo '<input type="submit" class="btn btn-primary" value="' . IMAGE_DELETE . '" >';
           echo '<input type="button" class="btn btn-cancel" value="' . IMAGE_CANCEL . '" onClick="return resetStatement()">'; */
        echo tep_draw_hidden_field('categories_id', $c_info->categories_id);
        ?>
        </div>
        </form>
        <?php 
    }
    public function action_confirm_product_move()
    {
        global $login_id;
        \common\helpers\Translation::init('admin/categories');
        $this->layout = false;
        $languages_id = \Yii::$app->settings->get('languages_id');
        $products_id = Yii::$app->request->post('products_id');
        $products_query = tep_db_query('select p.products_id, ' . Product_Name_Decorator::instance()->listing_query_expression('pd', '') . ' AS products_name, p.products_quantity, p.products_image, p.products_price, p.products_date_added, p.products_last_modified, p.products_date_available, p.products_status, p.products_model, p.sort_order, p.last_xml_export from ' . TABLE_PRODUCTS . ' p, ' . TABLE_PRODUCTS_DESCRIPTION . ' pd where p.products_id = pd.products_id ' . (tep_session_is_registered('login_vendor') ? " and p.vendor_id = '" . $login_id . "'" : '') . " and pd.language_id = '" . (int) $languages_id . "'  and pd.platform_id = '" . intval(\common\classes\platform::default_id()) . "' and p.products_id = '" . (int) $products_id . "'");
        $products = tep_db_fetch_array($products_query);
        $reviews_query = tep_db_query('select (avg(reviews_rating) / 5 * 100) as average_rating from ' . TABLE_REVIEWS . " where products_id = '" . (int) $products['products_id'] . "'");
        $reviews = tep_db_fetch_array($reviews_query);
        $p_info_array = array_merge($products, $reviews);
        $p_info = new \Object_Info($p_info_array);
        $p_info->categories_id = Yii::$app->request->post('categories_id');
        // fix for brands tab
        $o_relation = \common\models\Products2Categories::find()->where(['products_id' => $p_info->products_id])->limit(1)->one();
        if (isset($o_relation->categories_id) && $o_relation->categories_id != 0 && $p_info->categories_id == 0) {
            $p_info->categories_id = $o_relation->categories_id;
        }
        $category_tree = \common\helpers\Categories::get_category_tree();
        $categories = \common\models\Products2Categories::find()->where(['products_id' => $products['products_id']])->as_array()->all();
        $c_i_ds = [];
        foreach ($categories as $item) {
            $c_i_ds[] = $item['categories_id'];
        }
        return $this->render('confirmproductmove.tpl', ['pInfo' => $p_info, 'categoryTree' => $category_tree, 'cIDs' => $c_i_ds]);
    }
    public function action_product_move()
    {
        if (\common\helpers\Acl::rule(['TABLE_HEADING_PRODUCTS', 'IMAGE_MOVE'])) {
            // move_to_category_id products_id categories_id
            $products_id = Yii::$app->request->post('products_id');
            $new_parent_id = Yii::$app->request->post('move_to_category_id');
            $current_category_id = Yii::$app->request->post('categories_id');
            $duplicate_check_query = tep_db_query('select count(*) as total from ' . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id = '" . (int) $products_id . "' and categories_id = '" . (int) $new_parent_id . "'");
            $duplicate_check = tep_db_fetch_array($duplicate_check_query);
            if ($duplicate_check['total'] < 1) {
                tep_db_query('update ' . TABLE_PRODUCTS_TO_CATEGORIES . " set categories_id = '" . (int) $new_parent_id . "' where products_id = '" . (int) $products_id . "' and categories_id = '" . (int) $current_category_id . "'");
            }
            \common\components\Categories_Cache::get_cpc()::invalidate_categories([(int) $new_parent_id, (int) $current_category_id]);
            if (USE_CACHE == 'true') {
                \common\helpers\System::reset_cache_block('categories');
                \common\helpers\System::reset_cache_block('also_purchased');
            }
        }
    }
    public function action_confirm_product_attr_copy()
    {
        global $login_id;
        \common\helpers\Translation::init('admin/categories');
        $this->layout = false;
        $languages_id = \Yii::$app->settings->get('languages_id');
        $products_id = Yii::$app->request->post('products_id');
        $products_query = tep_db_query('select p.products_id, ' . Product_Name_Decorator::instance()->listing_query_expression('pd', '') . ' AS products_name, p.products_quantity, p.products_image, p.products_price, p.products_date_added, p.products_last_modified, p.products_date_available, p.products_status, p.products_model, p.sort_order, p.last_xml_export from ' . TABLE_PRODUCTS . ' p, ' . TABLE_PRODUCTS_DESCRIPTION . ' pd where p.products_id = pd.products_id ' . (tep_session_is_registered('login_vendor') ? " and p.vendor_id = '" . $login_id . "'" : '') . " and pd.language_id = '" . (int) $languages_id . "'  and pd.platform_id = '" . intval(\common\classes\platform::default_id()) . "' and p.products_id = '" . (int) $products_id . "'");
        $products = tep_db_fetch_array($products_query);
        $reviews_query = tep_db_query('select (avg(reviews_rating) / 5 * 100) as average_rating from ' . TABLE_REVIEWS . " where products_id = '" . (int) $products['products_id'] . "'");
        $reviews = tep_db_fetch_array($reviews_query);
        $p_info_array = array_merge($products, $reviews);
        $p_info = new \Object_Info($p_info_array);
        return $this->render('confirmproductattrcopy.tpl', ['pInfo' => $p_info]);
    }
    public function action_product_attr_copy()
    {
        $ret = ['ok' => 1];
        if (\common\helpers\Acl::rule(['TABLE_HEADING_PRODUCTS', 'IMAGE_COPY_ATTRIBUTES'])) {
            $products_id = (int) Yii::$app->request->post('products_id', 0);
            $copy_to_products_id = (int) Yii::$app->request->post('copy_to_products_id', 0);
            if (empty($copy_to_products_id)) {
                //allow id in the search field
                $copy_to_products_id = (int) Yii::$app->request->post('products_name', 0);
            }
            $skip_duplicates = (bool) Yii::$app->request->post('copy_attributes_duplicates_skipped', false);
            $delete_first = (bool) Yii::$app->request->post('copy_attributes_delete_first', false);
            //\common\helpers\Attributes::copy_products_attributes($products_id, $copy_to_products_id);
            try {
                \common\helpers\Attributes::copy_products_attributes($products_id, $copy_to_products_id, $delete_first, $skip_duplicates);
            } catch (\Exception $ex) {
                \Yii::warning(' #### ' . print_r($ex->get_message(), true), 'TLDEBUG');
                $ret = ['message' => $ex->get_message()];
            }
        } else {
            $ret = ['message' => TEXT_ERROR_INSUFFICIENT_PERMISSIONS];
        }
        $this->layout = false;
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $ret;
    }
    public function action_ns_sync()
    {
        if (\common\helpers\Acl::rule(['TABLE_HEADING_PRODUCTS', 'IMAGE_COPY_ATTRIBUTES'])) {
            $products_id = Yii::$app->request->post('r_id');
            $directory_id = Yii::$app->request->post('d_id');
            $test = tep_db_query("select remote_id from ep_holbi_soap_remote_products_queue where ep_directory_id='" . (int) $directory_id . "' and remote_id='" . (int) $products_id . "' limit 2");
            if (tep_db_num_rows($test) == 0) {
                $sql_data = ['ep_directory_id' => $directory_id, 'remote_id' => $products_id];
                tep_db_perform('ep_holbi_soap_remote_products_queue', $sql_data);
                ob_start();
                $ep_directory = \backend\models\EP\Directory::load_by_id($directory_id);
                $provider_name = $ep_directory->directory_config[0]['file_format'];
                $job_id = $ep_directory->touch_import_job($provider_name . '_DownloadProducts_' . date('YmdHis'), 'configured', $provider_name . '\DownloadProducts');
                $export_order_job = \backend\models\EP\Job::load_by_id($job_id);
                if ($export_order_job) {
                    if (!is_array($export_order_job->job_configure)) {
                        $export_order_job->job_configure = [];
                    }
                    $export_order_job->job_configure['oneTimeJob'] = true;
                    $export_order_job->save_configure_state();
                    $export_order_job->set_job_start_time(time());
                    $messages = new Messages(['job_id' => $job_id, 'output' => 'db']);
                    ob_start();
                    try {
                        $messages->info('Run import manually');
                        $export_order_job->run($messages);
                        $ret['status'] = 'OK';
                        $ret['messages'] = $messages->get_messages();
                    } catch (\Exception $ex) {
                        $ret['messages'][] = $ex->get_message();
                    }
                    ob_end_flush();
                    $export_order_job->job_finished();
                }
                ob_get_clean();
                //$ret = ['status'=>"OK"];
            } else {
                $ret = ['status' => 'OK', 'inqueue' => 1];
            }
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            Yii::$app->response->data = $ret;
        }
    }
    public function action_ns_sync_update_id()
    {
        if (\common\helpers\Acl::rule(['TABLE_HEADING_PRODUCTS', 'IMAGE_COPY_ATTRIBUTES'])) {
            $products_id = Yii::$app->request->post('r_id', 0);
            $l_id = Yii::$app->request->post('l_id', 0);
            $n_id = Yii::$app->request->post('n_id', 0);
            $directory_id = Yii::$app->request->post('d_id', 0);
            if ($products_id > 0) {
                tep_db_query("delete from ep_holbi_soap_link_products where ep_directory_id='" . (int) $directory_id . "' and remote_products_id='" . (int) $products_id . "' and local_products_id='" . (int) $l_id . "' ");
            }
            if ($n_id > 0) {
                $sql_data = ['ep_directory_id' => $directory_id, 'local_products_id' => $l_id, 'remote_products_id' => $n_id];
                tep_db_perform('ep_holbi_soap_link_products', $sql_data);
            }
            $ret = ['status' => 'OK'];
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            Yii::$app->response->data = $ret;
        }
    }
    public function action_confirm_product_copy()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/categories');
        $this->layout = false;
        $products_id = Yii::$app->request->post('products_id');
        $products_query = tep_db_query('select p.products_id, ' . Product_Name_Decorator::instance()->listing_query_expression('pd', '') . ' AS products_name, p.products_quantity, p.products_image, p.products_price, p.products_date_added, p.products_last_modified, p.products_date_available, p.products_status, p.products_model, p.sort_order, p.last_xml_export from ' . TABLE_PRODUCTS . ' p, ' . TABLE_PRODUCTS_DESCRIPTION . ' pd where p.products_id = pd.products_id ' . (tep_session_is_registered('login_vendor') ? " and p.vendor_id = '" . $login_id . "'" : '') . " and pd.language_id = '" . (int) $languages_id . "'  and pd.platform_id = '" . intval(\common\classes\platform::default_id()) . "' and p.products_id = '" . (int) $products_id . "'");
        $products = tep_db_fetch_array($products_query);
        $reviews_query = tep_db_query('select (avg(reviews_rating) / 5 * 100) as average_rating from ' . TABLE_REVIEWS . " where products_id = '" . (int) $products['products_id'] . "'");
        $reviews = tep_db_fetch_array($reviews_query);
        $p_info_array = array_merge($products, $reviews);
        $p_info = new \Object_Info($p_info_array);
        $p_info->categories_id = Yii::$app->request->post('categories_id');
        $categories = \common\models\Products2Categories::find()->where(['products_id' => $products['products_id']])->as_array()->all();
        $c_i_ds = [];
        foreach ($categories as $item) {
            $c_i_ds[] = $item['categories_id'];
        }
        return $this->render('confirmproductcopy.tpl', ['pInfo' => $p_info, 'cIDs' => $c_i_ds]);
    }
    public function action_product_copy()
    {
        $message_stack = \Yii::$container->get('message_stack');
        if (\common\helpers\Acl::rule(['TABLE_HEADING_PRODUCTS', 'IMAGE_COPY_TO'])) {
            if ($_POST['copy_as'] == 'duplicate' && !isset($_POST['categories_id'])) {
                // duplicate to top category if new category was not selected
                $_POST['categories_id'] = ['0'];
            }
            if (isset($_POST['products_id']) && isset($_POST['categories_id'])) {
                $products_id = tep_db_prepare_input($_POST['products_id']);
                $c_i_ds = tep_db_prepare_input($_POST['categories_id']);
                if ($c_i_ds && !is_array($c_i_ds)) {
                    $c_i_ds = [$c_i_ds];
                }
                foreach ($c_i_ds as $categories_id) {
                    if ($_POST['copy_as'] == 'link') {
                        $check_query = tep_db_query('select count(*) as total from ' . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id = '" . (int) $products_id . "' and categories_id = '" . (int) $categories_id . "'");
                        $check = tep_db_fetch_array($check_query);
                        if ($check['total'] < '1') {
                            tep_db_query('insert into ' . TABLE_PRODUCTS_TO_CATEGORIES . " (products_id, categories_id) values ('" . (int) $products_id . "', '" . (int) $categories_id . "')");
                        } else {
                            $message_stack->add(ERROR_CANNOT_LINK_TO_SAME_CATEGORY);
                        }
                    } elseif ($_POST['copy_as'] == 'duplicate') {
                        $copy_categories = (int) \Yii::$app->request->post('copy_categories', 0);
                        $copy_attributes = (bool) \Yii::$app->request->post('copy_attributes', false);
                        \common\helpers\Product::duplicate($products_id, $categories_id, $copy_attributes, $copy_categories);
                    }
                }
                if (defined('USE_CACHE') && USE_CACHE == 'true') {
                    \common\helpers\System::reset_cache_block('categories');
                    \common\helpers\System::reset_cache_block('also_purchased');
                }
            }
        }
    }
    public function action_confirmproductdelete()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/categories');
        $this->layout = false;
        $products_id = Yii::$app->request->post('products_id');
        $products_query = tep_db_query('select p.products_id, ' . Product_Name_Decorator::instance()->listing_query_expression('pd', '') . ' AS products_name, p.products_quantity, p.products_image, p.products_price, p.products_date_added, p.products_last_modified, p.products_date_available, p.products_status, p.products_model, p.sort_order, p.last_xml_export from ' . TABLE_PRODUCTS . ' p, ' . TABLE_PRODUCTS_DESCRIPTION . ' pd where p.products_id = pd.products_id ' . (tep_session_is_registered('login_vendor') ? " and p.vendor_id = '" . $login_id . "'" : '') . " and pd.language_id = '" . (int) $languages_id . "'  and pd.platform_id = '" . intval(\common\classes\platform::default_id()) . "' and p.products_id = '" . (int) $products_id . "'");
        $products = tep_db_fetch_array($products_query);
        $reviews_query = tep_db_query('select (avg(reviews_rating) / 5 * 100) as average_rating from ' . TABLE_REVIEWS . " where products_id = '" . (int) $products['products_id'] . "'");
        $reviews = tep_db_fetch_array($reviews_query);
        $p_info_array = array_merge($products, $reviews);
        $p_info = new \Object_Info($p_info_array);
        echo '<div class="or_box_head">' . TEXT_INFO_HEADING_DELETE_PRODUCT . '</div>';
        echo tep_draw_form('products', FILENAME_CATEGORIES, \common\helpers\Output::get_all_get_params(['action']) . 'action=delete_product_confirm', 'post', 'id="products_edit" onSubmit="return deleteProduct();"');
        echo '<div class="col_title">' . TEXT_DELETE_PRODUCT_INTRO . '</div>';
        echo '<div class="col_desc"><b>' . $p_info->products_name . '</b></div>';
        $product_categories_string = '';
        $product_categories = \common\helpers\Categories::generate_category_path($p_info->products_id, 'product');
        for ($i = 0, $n = sizeof($product_categories); $i < $n; $i++) {
            $category_path = '';
            for ($j = 0, $k = sizeof($product_categories[$i]); $j < $k; $j++) {
                $category_path .= $product_categories[$i][$j]['text'] . '&nbsp;&gt;&nbsp;';
            }
            $category_path = substr($category_path, 0, -16);
            $product_categories_string .= tep_draw_checkbox_field('product_categories[]', $product_categories[$i][sizeof($product_categories[$i]) - 1]['id'], true) . '&nbsp;' . $category_path . '<br>';
        }
        $product_categories_string = substr($product_categories_string, 0, -4);
        echo '<div class="col_desc">' . $product_categories_string . '</div>';
        ?>
        <p class="btn-toolbar btn-toolbar-order">
            <?php 
        echo '<button class="btn btn-delete btn-no-margin"><span>' . IMAGE_DELETE . '</span></button>';
        echo '<input type="button" class="btn btn-cancel" value="' . IMAGE_CANCEL . '" onClick="return resetStatement()">';
        echo tep_draw_hidden_field('products_id', $p_info->products_id);
        ?>
        </p>
        </form>
        <?php 
    }
    /*
     * remove category with all subcategories and products. O_O - tooo danger, no warning.
     */
    public function action_categorydelete()
    {
        $this->layout = false;
        if (\common\helpers\Acl::rule(['TEXT_CATEGORIES', 'IMAGE_DELETE'])) {
            if (isset($_POST['categories_id']) && $_POST['categories_id'] > 0) {
                $categories_id = tep_db_prepare_input($_POST['categories_id']);
                $cat_list = \common\helpers\Categories::get_category_parents_ids($categories_id);
                if (\common\helpers\Acl::check_extension_allowed('ReportChangesHistory')) {
                    $logger = new \common\extensions\Report_Changes_History\classes\Logger();
                    $before_object = new \common\api\Classes\Category();
                    $before_object->load($categories_id);
                    $logger->set_before_object($before_object);
                    unset($before_object);
                }
                //2do if _left and _right is updated everywhere - replace wth 2 queries.
                $categories = \common\helpers\Categories::get_category_tree($categories_id, '', '0', '', true);
                $products = [];
                $products_delete = [];
                for ($i = 0, $n = sizeof($categories); $i < $n; $i++) {
                    $product_ids_query = tep_db_query('select products_id from ' . TABLE_PRODUCTS_TO_CATEGORIES . " where categories_id = '" . (int) $categories[$i]['id'] . "'");
                    while ($product_ids = tep_db_fetch_array($product_ids_query)) {
                        $products[$product_ids['products_id']]['categories'][] = $categories[$i]['id'];
                    }
                }
                foreach ($products as $key => $value) {
                    $category_ids = '';
                    for ($i = 0, $n = sizeof($value['categories']); $i < $n; $i++) {
                        $category_ids .= "'" . (int) $value['categories'][$i] . "', ";
                    }
                    $category_ids = substr($category_ids, 0, -2);
                    $check_query = tep_db_query('select count(*) as total from ' . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id = '" . (int) $key . "' and categories_id not in (" . $category_ids . ')');
                    $check = tep_db_fetch_array($check_query);
                    if ($check['total'] < '1') {
                        $products_delete[$key] = $key;
                    }
                }
                // removing categories can be a lengthy process
                set_time_limit(0);
                $sdn = \common\helpers\Acl::check_extension_allowed('SeoRedirectsNamed', 'allowed');
                for ($i = 0, $n = sizeof($categories); $i < $n; $i++) {
                    \common\helpers\Categories::remove_category($categories[$i]['id'], false);
                    if ($sdn) {
                        $sdn::delete_category_links($categories[$i]['id']);
                    }
                }
                foreach ($products_delete as $key) {
                    \common\helpers\Product::remove_product($key);
                    if ($sdn) {
                        $sdn::delete_product_links($key);
                    }
                }
                \common\components\Categories_Cache::get_cpc()::invalidate_categories($cat_list);
            }
            if (USE_CACHE == 'true') {
                \common\helpers\System::reset_cache_block('categories');
                \common\helpers\System::reset_cache_block('also_purchased');
            }
            //It's not required as branch is deleted completely. Left, right are not concequent, but correct. It's very slow operation.
            //\common\helpers\Categories::update_categories();
            //
        }
        if (isset($logger) && \common\helpers\Acl::check_extension_allowed('ReportChangesHistory')) {
            $after_object = new \common\api\Classes\Category();
            $after_object->load(0);
            $logger->set_after_object($after_object);
            unset($after_object);
            $logger->run();
        }
        $this->view->categories_tree = $this->get_category_tree();
        $this->view->categories_opened_tree = [];
        $this->view->categories_closed_tree = array_map('intval', explode('|', \Yii::$app->session->get('closed_data')));
        $collapsed = $this->default_collapsed;
        return $this->render('cat_main_box', ['directOutput' => true, 'collapsed' => $collapsed]);
    }
    public function action_productdelete()
    {
        $this->layout = false;
        if (\common\helpers\Acl::rule(['TABLE_HEADING_PRODUCTS', 'IMAGE_DELETE'])) {
            $product_id = Yii::$app->request->post('products_id');
            $list_cat_id = \common\helpers\Product::get_categories_id_list_with_parents($product_id);
            if (\common\helpers\Acl::check_extension_allowed('ReportChangesHistory')) {
                $logger = new \common\extensions\Report_Changes_History\classes\Logger();
                $before_object = new \common\api\Classes\Product();
                $before_object->load($product_id);
                $logger->set_before_object($before_object);
                unset($before_object);
            }
            $product_categories_query = tep_db_query('select count(*) as total from ' . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id = '" . (int) $product_id . "'");
            $count_product_categories = tep_db_fetch_array($product_categories_query);
            $remove_complete = true;
            if (isset($_POST['product_categories']) && is_array($_POST['product_categories'])) {
                $product_categories = $_POST['product_categories'];
                if ($count_product_categories['total'] != count($product_categories)) {
                    for ($i = 0, $n = sizeof($product_categories); $i < $n; $i++) {
                        tep_db_query('delete from ' . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id = '" . (int) $product_id . "' and categories_id = '" . (int) $product_categories[$i] . "'");
                    }
                    $remove_complete = false;
                }
            }
            $product_categories_query = tep_db_query('select count(*) as total from ' . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id = '" . (int) $product_id . "'");
            $product_categories = tep_db_fetch_array($product_categories_query);
            if ($remove_complete || $product_categories['total'] == '0') {
                \common\helpers\Product::remove_product($product_id);
                if ($ext = \common\helpers\Acl::check_extension_allowed('SeoRedirectsNamed', 'allowed')) {
                    $ext::delete_product_links($product_id);
                }
            }
            if (defined('USE_CACHE') && USE_CACHE == 'true') {
                \common\helpers\System::reset_cache_block('categories');
                \common\helpers\System::reset_cache_block('also_purchased');
            }
            if (isset($logger) && \common\helpers\Acl::check_extension_allowed('ReportChangesHistory')) {
                $after_object = new \common\api\Classes\Product();
                $after_object->load(0);
                $logger->set_after_object($after_object);
                unset($after_object);
                $logger->run();
            }
            \common\components\Categories_Cache::get_cpc()::invalidate_categories($list_cat_id);
        }
    }
    public function action_productedit()
    {
        if (false === \common\helpers\Acl::rule(['TABLE_HEADING_PRODUCTS', 'IMAGE_EDIT'])) {
            $this->redirect(\yii\helpers\Url::to_route('categories/'));
        }
        $languages_id = \Yii::$app->settings->get('languages_id');
        if (defined('SUPERADMIN_ENABLED') && SUPERADMIN_ENABLED == true) {
            $selected_department_id = (int) Yii::$app->request->get('department_id', 0);
        } else {
            $selected_department_id = 0;
        }
        $_session = Yii::$app->session;
        $service = new \common\services\Supplier_Service();
        Yii::configure($service, ['allow_change_status' => true, 'allow_change_default' => true, 'allow_change_surcharge' => true, 'allow_change_margin' => true, 'allow_change_price_formula' => true, 'allow_change_auth' => true]);
        \common\helpers\Translation::init('admin/categories');
        // search in top
        $this->top_buttons[] = '<span class="btn btn-confirm" onclick="$(\'#save_product_form\').trigger(\'submit\')">' . IMAGE_SAVE . '</span>';
        $this->top_buttons[] = '<div class="btn-quick-search" style="float: right;">' . \common\helpers\Html::begin_form(\Yii::$app->url_manager->create_url('categories'), 'get') . \common\helpers\Html::hidden_input('autoEdit', 1) . \common\helpers\Html::begin_tag('div', ['class' => 'box-head-search']) . \common\helpers\Html::text_input('search') . \common\helpers\Html::button('', ['class' => 'edit-product-quick-search', 'onclick' => 'this.form.submit();']) . \common\helpers\Html::end_tag('div') . \common\helpers\Html::end_form() . '</div>';
        if (\common\helpers\Acl::check_extension_allowed('ProductBundles') && \common\helpers\Acl::rule(['TABLE_HEADING_PRODUCTS', 'IMAGE_EDIT', 'TAB_BUNDLES'])) {
            $edit_product_bundle_switcher = true;
        } else {
            $edit_product_bundle_switcher = false;
        }
        $currencies = Yii::$container->get('currencies');
        $products_id = (int) Yii::$app->request->get('pID', 0);
        //products_id
        $languages = \common\helpers\Language::get_languages();
        $in_category_id = intval(Yii::$app->request->get('category_id', 0));
        $is_bundle = false;
        if ($products_id > 0) {
            $product_record = \common\helpers\Product::get_record($products_id, true);
            $is_bundle = count(\common\helpers\Product::get_child_array($product_record)) > 0;
            unset($product_record);
            $product_query = tep_db_query('select p.*, ' . Product_Name_Decorator::instance()->listing_query_expression('pd', '') . ' AS products_name, pd.products_viewed, m.manufacturers_name, pf.status as featured_status, pf.expires_date as featured_expires_date, p.products_sets_discount from ' . TABLE_PRODUCTS . ' p left join ' . TABLE_PRODUCTS_DESCRIPTION . " pd on p.products_id = pd.products_id and pd.language_id = '" . (int) $languages_id . "' and pd.platform_id='" . intval(\common\classes\platform::default_id()) . "' left join " . TABLE_MANUFACTURERS . ' m on p.manufacturers_id=m.manufacturers_id left join ' . TABLE_FEATURED . " pf on p.products_id = pf.products_id where p.products_id = '" . (int) $products_id . "' ");
            $product = tep_db_fetch_array($product_query);
            if (empty($product)) {
                $products_id = 0;
            } else {
                if (empty($product['products_name'])) {
                    $product['products_name'] = \common\helpers\Product::get_products_name($product['products_id']);
                }
                if ($product['is_bundle'] > 0) {
                    // allow to unbundle even if ProductsBundles is not installed
                    $edit_product_bundle_switcher = true;
                }
            }
            $p_info = new \Object_Info($product);
        }
        //else {
        if ($products_id <= 0) {
            $parent_id = intval(Yii::$app->request->get('parentID', 0));
            if ($parent_id && $parent_product_model = \common\models\Products::find_one($parent_id)) {
                $parent_data = $parent_product_model->get_attributes(\common\helpers\Product::sub_product_main_attributes_share());
                $parent_data['manufacturers_name'] = $parent_product_model->manufacturer->manufacturers_name;
            } else {
                $parent_id = 0;
                $parent_data = false;
            }
            if (!is_array($parent_data)) {
                // new common product
                $default_values_obj = new \common\models\Products();
                $default_values_obj->load_default_values();
                $parent_data = $default_values_obj->get_attributes();
                unset($default_values_obj);
            }
            $p_info = new \Object_Info(array_merge($parent_data, ['products_id' => 0, 'parent_products_id' => $parent_id, 'products_id_stock' => $parent_id, 'products_id_price' => $parent_id, 'products_popularity' => 0, 'popularity_simple' => 0, 'popularity_bestseller' => 0, 'use_sets_discount' => 0, 'is_bundle' => Yii::$app->request->get('bundle', 0), 'without_inventory' => (int) !\common\helpers\Extensions::is_allowed('Inventory'), 'products_tax_class_id' => \common\helpers\Tax::get_default_tax_class_id_for_products(), 'products_sets_discount' => 0, 'products_name' => '']));
        }
        $info_sub_products = '';
        \common\helpers\Php8::null_props($p_info, ['parent_products_id', 'products_model', 'products_name', 'products_id', 'products_quantity', 'allocated_stock_quantity', 'temporary_stock_quantity', 'warehouse_stock_quantity', 'ordered_stock_quantity', 'suppliers_stock_quantity', 'stock_reorder_level', 'stock_reorder_quantity', 'stock_limit']);
        if ($p_info->parent_products_id) {
            $this->product_edit_tab_access->set_product($p_info);
            $edit_product_bundle_switcher = false;
            $info_sub_products = '<b>' . TEXT_CHILD_PRODUCT . '</b> ' . TEXT_SUB_PRODUCT_CONNECTED_TO_PARENT . ' <i class="product_list_marker">' . Html::a(\common\helpers\Product::get_backend_products_name($p_info->parent_products_id), Url::to(['categories/productedit', 'pID' => $p_info->parent_products_id])) . '</i>';
        } elseif ($p_info->products_id) {
            $children_ids = \common\helpers\Sub_Product::get_children_ids($p_info->products_id);
            if (count($children_ids) > 0) {
                foreach ($children_ids as $child_id) {
                    if (empty($info_sub_products)) {
                        $info_sub_products = '<b>' . TEXT_PARENT_PRODUCT . '</b> ' . TEXT_SUB_PRODUCT_CONNECTED_CHILDREN . '<i class="product_list_marker">' . Html::a(\common\helpers\Product::get_backend_products_name($child_id), Url::to(['categories/productedit', 'pID' => $child_id])) . '</i>';
                        if (count($children_ids) > 1) {
                            $info_sub_products .= '<i class="product_list_marker" style="position: relative;"><b>' . TEXT_SUB_PRODUCT_SEE_ALL_CHILDREN . '</b><div class="product_list_marker__pophover">';
                        }
                    } else {
                        $info_sub_products .= ' <i class="product_list_marker">' . Html::a(\common\helpers\Product::get_backend_products_name($child_id), Url::to(['categories/productedit', 'pID' => $child_id])) . '</i>';
                    }
                }
                if (count($children_ids) > 1) {
                    $info_sub_products .= '</div></i>';
                }
            }
        }
        $p_info->stock_info = new View_Stock_Info($p_info);
        if (!empty($p_info->products_date_available)) {
            $p_info->products_date_available = \common\helpers\Date::date_short($p_info->products_date_available);
        }
        if (!empty($p_info->products_new_until)) {
            $p_info->products_new_until = \common\helpers\Date::date_short($p_info->products_new_until);
        }
        $p_info->settings = \common\helpers\Product::get_settings($p_info->products_id);
        $this->selected_menu = ['catalog', 'categories'];
        $str_full = strlen($p_info->products_model ?? '');
        if ($str_full > 20) {
            $st_full_name = mb_substr($p_info->products_model, 0, 20);
            $st_full_name .= '...';
            $st_full_model_view = '<span title="' . $p_info->products_model . '">' . $st_full_name . '</span>';
        } else {
            $st_full_model_view = $p_info->products_model;
        }
        $str_full = strlen($p_info->products_name);
        if ($str_full > 35) {
            $st_full_name = mb_substr($p_info->products_name, 0, 35);
            $st_full_name .= '...';
            $st_full_name_view = '<span title="' . $p_info->products_name . '">' . $st_full_name . '</span>';
        } else {
            $st_full_name_view = $p_info->products_name;
        }
        $text_new_or_edit = $products_id == 0 ? TEXT_NEW_PRODUCT : T_EDIT_PROD . ' ' . $st_full_model_view . ' "' . $st_full_name_view . '"';
        if ($products_id == 0) {
            $edit_product_in_path = (defined('TEXT_PRODUCT_CREATE_IN') ? TEXT_PRODUCT_CREATE_IN : '') . ' ' . '<ul class="category_path_list top_bead-items"><li class="category_path">' . \common\helpers\Categories::output_generated_category_path($in_category_id, 'category', '<span class="category_path__location">%2$s</span>', '</li><li class="category_path onemore">') . '</li></ul>';
        } else {
            $edit_product_in_path = (defined('TEXT_PRODUCT_PLACED_IN') ? TEXT_PRODUCT_PLACED_IN : '') . ' ' . '<ul class="category_path_list top_bead-items"><li class="category_path">' . \common\helpers\Categories::output_generated_category_path($products_id, 'product', '<a class="category_path__location" href="categories?category_id=%1$s">%2$s</a>', '</li><li class="category_path onemore">') . '</li></ul>';
        }
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('orders/productedit'), 'title' => $text_new_or_edit];
        //// extensions
        $this->view->groups = [];
        /** @var \common\extensions\UserGroups\UserGroups $ext */
        if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroups', 'allowed')) {
            $ext::get_groups();
        }
        $bundles_products = [];
        if ($ext = \common\helpers\Acl::check_extension_allowed('ProductBundles', 'allowed')) {
            $bundles_products = $ext::get_products($p_info);
        }
        $this->view->bundles_products = $bundles_products;
        $documents = [];
        /** @var \common\extensions\ProductDocuments\ProductDocuments $ext */
        if ($ext = \common\helpers\Extensions::is_allowed('ProductDocuments')) {
            $documents = $ext::get_documents($products_id);
        }
        $this->view->documents = $documents;
        /** @var \common\extensions\Inventory\Inventory $ext */
        if ($ext = \common\helpers\Extensions::is_allowed('Inventory')) {
            $ext::get_inventory($products_id, $languages_id);
        } else {
            $this->view->show_inventory = false;
        }
        $this->view->templates = ['list' => \common\classes\platform::get_list(false), 'show_block' => 1];
        /** @var \common\extensions\ProductTemplates\ProductTemplates $ext */
        if ($ext = \common\helpers\Extensions::is_allowed('ProductTemplates')) {
            $this->view->templates = $ext::productedit($products_id);
        }
        if ($this->product_edit_tab_access->tab_view('TAB_IMPORT_EXPORT')) {
            $this->view->import_export = new View_Import_Export($p_info);
        }
        ///////////// other lists (for both new and edit product - attributes, properties, x-sell)
        $this->view->tax_classes = ['0' => TEXT_NONE];
        $tax_class_query = tep_db_query('select tax_class_id, tax_class_title from ' . TABLE_TAX_CLASS . ' order by tax_class_title');
        while ($tax_class = tep_db_fetch_array($tax_class_query)) {
            $this->view->tax_classes[$tax_class['tax_class_id']] = $tax_class['tax_class_title'];
        }
        $attribute_templates = ['label' => BOX_CATALOG_CATEGORIES_OPTIONS_TEMPLATES, 'options' => array_map(function ($data) {
            return ['value' => $data['options_templates_id'], 'name' => htmlspecialchars($data['options_templates_name'])];
        }, \common\models\Options_Templates::find()->order_by(['options_templates_name' => SORT_ASC])->as_array()->all())];
        $this->view->attribute_templates = $attribute_templates;
        $attributes = [];
        //improve - 1 query
        $options_query = tep_db_query('select products_options_id, products_options_name, is_virtual from ' . TABLE_PRODUCTS_OPTIONS . " where language_id = '" . $languages_id . "' order by products_options_sort_order, products_options_name");
        while ($options = tep_db_fetch_array($options_query)) {
            $values_query = tep_db_query('select pov.products_options_values_id, pov.products_options_values_name from ' . TABLE_PRODUCTS_OPTIONS_VALUES . ' pov, ' . TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS . " p2p where pov.products_options_values_id = p2p.products_options_values_id and p2p.products_options_id = '" . $options['products_options_id'] . "' and pov.language_id = '" . $languages_id . "' order by products_options_values_sort_order, products_options_values_name");
            $option = [];
            while ($values = tep_db_fetch_array($values_query)) {
                $option[] = ['value' => $values['products_options_values_id'], 'name' => htmlspecialchars($values['products_options_values_name'])];
            }
            $attributes[] = ['id' => $options['products_options_id'], 'label' => htmlspecialchars($options['products_options_name']), 'is_virtual' => !!$options['is_virtual'], 'disable' => !$options['is_virtual'] && $p_info->parent_products_id, 'options' => $option];
        }
        $this->view->attributes = $attributes;
        $this->view->default_currency = $currencies->currencies[DEFAULT_CURRENCY]['id'];
        $this->view->use_market_prices = USE_MARKET_PRICES == 'True';
        $this->view->give_away = 0;
        $this->view->shopping_cart_price = '';
        $this->view->buy_qty = '';
        $this->view->products_qty = '';
        $this->view->use_in_qty_discount = 0;
        $this->view->featured = 0;
        $this->view->featured_expires_date = '';
        $upload_path = \Yii::get_alias('@web');
        $upload_path .= '/uploads/';
        $this->view->upload_path = $upload_path;
        $p_description = [];
        $_p_q = \common\models\Platforms::get_platforms_by_type('non-virtual')->order_by('is_marketplace, sort_order');
        if (!(isset($this->view->sph) && $this->view->sph)) {
            $_p_q->and_where(['status' => 1]);
        }
        $admin_available_platform_ids = \yii\helpers\Array_Helper::get_column(\common\classes\platform::get_list(), 'id');
        $_p_q->and_where(['IN', 'platform_id', $admin_available_platform_ids]);
        $platforms = $_p_q->all();
        $def_platform_id = \common\classes\platform::default_id();
        if (!in_array($def_platform_id, $admin_available_platform_ids)) {
            $def_platform_id = reset($admin_available_platform_ids);
        }
        $platform_configs = [];
        $this->view->platform_languages = [];
        ///Description
        if (isset($_GET['shp'])) {
            $_session->set('shp', (int) $_GET['shp']);
        }
        $this->view->sph = $_session->has('shp') ? $_session->get('shp') : 0;
        if (isset($_GET['shpl'])) {
            $_session->set('shpl', $_GET['shpl']);
        }
        $this->view->sphl = $_session->has('shpl') ? $_session->get('shpl') : 0;
        $description_products_id = $products_id;
        // {{ create sub product
        if ($p_info->products_id == 0 && $p_info->parent_products_id) {
            $description_products_id = $p_info->parent_products_id;
        }
        // }} create sub product
        if (count($platforms) > 1) {
            foreach ($platforms as $platform) {
                if (empty($this->view->sphl[$platform->platform_id]) && !$platform->is_marketplace) {
                    $_p_lans = Yii::$app->get('platform')->get_config($platform->platform_id)->get_allowed_languages();
                    if ($_p_lans) {
                        for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
                            if (in_array($languages[$i]['code'], $_p_lans)) {
                                $p_description_object = \common\models\Products_Description::find()->where(['products_id' => $description_products_id, 'language_id' => $languages[$i]['id'], 'platform_id' => $platform->platform_id, 'department_id' => 0])->one();
                                if ($selected_department_id > 0) {
                                    $p_description_override_object = \common\models\Products_Description::find()->where(['products_id' => $description_products_id, 'language_id' => $languages[$i]['id'], 'platform_id' => $platform->platform_id, 'department_id' => $selected_department_id])->one();
                                    if (is_object($p_description_override_object)) {
                                        if (!empty($p_description_override_object->products_name)) {
                                            $p_description_object->products_name = $p_description_override_object->products_name;
                                        }
                                        if (!empty($p_description_override_object->products_internal_name)) {
                                            $p_description_object->products_internal_name = $p_description_override_object->products_internal_name;
                                        }
                                        if (!empty($p_description_override_object->products_description_short)) {
                                            $p_description_object->products_description_short = $p_description_override_object->products_description_short;
                                        }
                                        if (!empty($p_description_override_object->products_description)) {
                                            $p_description_object->products_description = $p_description_override_object->products_description;
                                        }
                                        /*if (!empty($pDescriptionOverrideObject->products_seo_page_name)) {
                                              $pDescriptionObject->products_seo_page_name = $pDescriptionOverrideObject->products_seo_page_name;
                                          }*/
                                    }
                                }
                                $p_description[$platform->platform_id][] = $p_description_object;
                                $this->view->platform_languages[$platform->platform_id][] = $languages[$i];
                            }
                        }
                    }
                } else {
                    for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
                        $p_description_object = \common\models\Products_Description::find()->where(['products_id' => $description_products_id, 'language_id' => $languages[$i]['id'], 'platform_id' => $platform->platform_id, 'department_id' => 0])->one();
                        if ($selected_department_id > 0) {
                            $p_description_override_object = \common\models\Products_Description::find()->where(['products_id' => $description_products_id, 'language_id' => $languages[$i]['id'], 'platform_id' => $platform->platform_id, 'department_id' => $selected_department_id])->one();
                            if (is_object($p_description_override_object)) {
                                if (!empty($p_description_override_object->products_name)) {
                                    $p_description_object->products_name = $p_description_override_object->products_name;
                                }
                                if (!empty($p_description_override_object->products_internal_name)) {
                                    $p_description_object->products_internal_name = $p_description_override_object->products_internal_name;
                                }
                                if (!empty($p_description_override_object->products_description_short)) {
                                    $p_description_object->products_description_short = $p_description_override_object->products_description_short;
                                }
                                if (!empty($p_description_override_object->products_description)) {
                                    $p_description_object->products_description = $p_description_override_object->products_description;
                                }
                            }
                        }
                        $p_description[$platform->platform_id][$i] = $p_description_object;
                    }
                    $this->view->platform_languages[$platform->platform_id] = $languages;
                }
            }
        } else if (!(isset($this->view->sphl[$def_platform_id]) && $this->view->sphl[$def_platform_id])) {
            $_p_lans = Yii::$app->get('platform')->get_config($def_platform_id)->get_allowed_languages();
            if ($_p_lans) {
                for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
                    if (in_array($languages[$i]['code'], $_p_lans)) {
                        $p_description_object = \common\models\Products_Description::find()->where(['products_id' => $description_products_id, 'language_id' => $languages[$i]['id'], 'platform_id' => $def_platform_id, 'department_id' => 0])->one();
                        if ($selected_department_id > 0) {
                            $p_description_override_object = \common\models\Products_Description::find()->where(['products_id' => $description_products_id, 'language_id' => $languages[$i]['id'], 'platform_id' => $def_platform_id, 'department_id' => $selected_department_id])->one();
                            if (is_object($p_description_override_object)) {
                                if (!empty($p_description_override_object->products_name)) {
                                    $p_description_object->products_name = $p_description_override_object->products_name;
                                }
                                if (!empty($p_description_override_object->products_internal_name)) {
                                    $p_description_object->products_internal_name = $p_description_override_object->products_internal_name;
                                }
                                if (!empty($p_description_override_object->products_description_short)) {
                                    $p_description_object->products_description_short = $p_description_override_object->products_description_short;
                                }
                                if (!empty($p_description_override_object->products_description)) {
                                    $p_description_object->products_description = $p_description_override_object->products_description;
                                }
                            }
                        }
                        $p_description[$def_platform_id][] = $p_description_object;
                        $this->view->platform_languages[$def_platform_id][] = $languages[$i];
                    }
                }
            }
        } else {
            for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
                $p_description_object = \common\models\Products_Description::find()->where(['products_id' => $description_products_id, 'language_id' => $languages[$i]['id'], 'platform_id' => \common\classes\platform::default_id(), 'department_id' => 0])->one();
                if ($selected_department_id > 0) {
                    $p_description_override_object = \common\models\Products_Description::find()->where(['products_id' => $description_products_id, 'language_id' => $languages[$i]['id'], 'platform_id' => \common\classes\platform::default_id(), 'department_id' => $selected_department_id])->one();
                    if (is_object($p_description_override_object)) {
                        if (!empty($p_description_override_object->products_name)) {
                            $p_description_object->products_name = $p_description_override_object->products_name;
                        }
                        if (!empty($p_description_override_object->products_internal_name)) {
                            $p_description_object->products_internal_name = $p_description_override_object->products_internal_name;
                        }
                        if (!empty($p_description_override_object->products_description_short)) {
                            $p_description_object->products_description_short = $p_description_override_object->products_description_short;
                        }
                        if (!empty($p_description_override_object->products_description)) {
                            $p_description_object->products_description = $p_description_override_object->products_description;
                        }
                    }
                }
                $p_description[$def_platform_id][$i] = $p_description_object;
            }
            $this->view->platform_languages[$def_platform_id] = $languages;
        }
        // create sub product
        if ($p_info->products_id == 0 && $p_info->parent_products_id) {
            foreach ($p_description as $__nest_desc) {
                foreach ($__nest_desc as $__desc_model) {
                    if (!is_object($__desc_model)) {
                        continue;
                    }
                    $__new_data = array_fill_keys(array_keys($__desc_model->get_attributes()), '');
                    $__new_data = array_merge($__new_data, $__desc_model->get_attributes(['products_name', 'products_internal_name', 'products_description_short', 'products_description']));
                    $__desc_model->set_attributes($__new_data, false);
                }
            }
        }
        $this->view->platform_activate_categories = [];
        $this->view->department_activate_categories = [];
        $this->view->price_tabs_data = null;
        $this->view->gaw = [];
        $this->view->properties_tree = [];
        $this->view->properties_tree = \common\helpers\Properties::get_properties_tree('0', '&nbsp;&nbsp;&nbsp;&nbsp;', '', false);
        $this->view->properties_hiddens = '';
        $this->view->properties_array = [];
        $this->view->values_array = [];
        $this->view->extra_values = [];
        $this->view->properties_tree_array = [];
        /// default view values
        if ($products_id == 0) {
            $this->view->platform_assigned = [];
            if ($in_category_id > 0) {
                $get_assigned_platforms_r = tep_db_query('SELECT platform_id FROM ' . TABLE_PLATFORMS_CATEGORIES . " WHERE categories_id = '" . intval($in_category_id) . "' ");
                if (tep_db_num_rows($get_assigned_platforms_r) > 0) {
                    while ($_assigned_platform = tep_db_fetch_array($get_assigned_platforms_r)) {
                        $this->view->platform_assigned[(int) $_assigned_platform['platform_id']] = (int) $_assigned_platform['platform_id'];
                    }
                }
            } else {
                foreach (\common\classes\platform::get_products_assign_list() as $___data) {
                    $this->view->platform_assigned[intval($___data['id'])] = intval($___data['id']);
                }
            }
            $this->view->show_statistic = false;
            $this->view->images_qty = 0;
            $this->view->suppliers = [];
            $d_supplier = Suppliers::find_one(['is_default' => 1]);
            if ($d_supplier) {
                $s_product = new \common\models\Suppliers_Products();
                $s_product->load_default_values();
                $s_product->load_supplier_values($d_supplier->suppliers_id);
                $service->get('\common\models\SuppliersProducts', 'sProduct');
                $this->view->suppliers[$d_supplier->suppliers_id] = $s_product;
            }
            if ($p_info->parent_products_id) {
                $price_view_obj = new View_Price_Data(\common\models\Products::find_one($p_info->products_id_price));
                $price_view_obj->populate_view($this->view, $currencies);
                if (count($this->view->attributes) > 0) {
                    // attributes added to system
                    $parent_product_model = \common\models\Products::find_one($p_info->parent_products_id);
                    $attributes = new View_Attributes($parent_product_model, true);
                    $attributes->populate_view($this->view);
                }
            }
        } else {
            /// product exists - edit
            /// statistics
            $this->view->show_statistic = true;
            $this->view->statistic = new \stdClass();
            $this->view->statistic->price = $currencies->format(\common\helpers\Product::get_products_price($p_info->products_id));
            $this->view->statistic->products_date_added = \common\helpers\Date::datetime_short($p_info->products_date_added);
            $this->view->statistic->products_last_modified = \common\helpers\Date::datetime_short($p_info->products_last_modified);
            $this->view->statistic->products_viewed = $p_info->products_viewed;
            if ($this->view->show_inventory) {
                $inventory_listing = [];
                $inventory_query = tep_db_query('select * from ' . TABLE_INVENTORY . " where prid = '" . (int) $p_info->products_id . "'");
                while ($inventory_data = tep_db_fetch_array($inventory_query)) {
                    $arr = preg_split('/[{}]/', $inventory_data['products_id']);
                    $label = '';
                    for ($i = 1, $n = sizeof($arr); $i < $n; $i = $i + 2) {
                        $options_name_data = tep_db_fetch_array(tep_db_query('select products_options_name as name from ' . TABLE_PRODUCTS_OPTIONS . " where products_options_id = '" . $arr[$i] . "' and language_id  = '" . (int) $languages_id . "'"));
                        $options_values_name_data = tep_db_fetch_array(tep_db_query('select products_options_values_name as name from ' . TABLE_PRODUCTS_OPTIONS_VALUES . " where products_options_values_id  = '" . $arr[$i + 1] . "' and language_id  = '" . (int) $languages_id . "'"));
                        if ($label == '') {
                            $label = $options_name_data['name'] . ' : ' . $options_values_name_data['name'];
                        } else {
                            $label .= ', ' . $options_name_data['name'] . ' : ' . $options_values_name_data['name'];
                        }
                    }
                    $inventory_listing[] = ['label' => $label, 'price' => $currencies->format(\common\helpers\Product::get_products_price($p_info->products_id))];
                }
                $this->view->statistic->inventory = $inventory_listing;
            }
            $orders_data_array = ['ordered' => [], 'price' => []];
            $date_from = date('Y-m-d H:i:s', mktime(0, 0, 0, date('m') + 1, 1, date('Y') - 1));
            $orders_query = tep_db_query('select year(o.date_purchased) as date_year, month(o.date_purchased) as date_month, count(*) as total_orders, avg(op.products_price) as price, sum(op.products_quantity) as total from ' . TABLE_ORDERS . ' o inner join ' . TABLE_ORDERS_PRODUCTS . " op on (o.orders_id = op.orders_id and op.products_id = '" . $p_info->products_id . "') where o.date_purchased >= '" . tep_db_input($date_from) . "' group by year(o.date_purchased), month(o.date_purchased) order by year(o.date_purchased), month(o.date_purchased)");
            while ($orders = tep_db_fetch_array($orders_query)) {
                $orders_data_array['ordered'][] = '[' . mktime(0, 0, 0, $orders['date_month'], 1, $orders['date_year']) . '000,' . $orders['total'] . ']';
                $orders_data_array['price'][] = '[' . mktime(0, 0, 0, $orders['date_month'], 1, $orders['date_year']) . '000,' . $orders['price'] . ']';
            }
            $this->view->statistic->ordered_grid = implode(' , ', $orders_data_array['ordered']);
            $this->view->statistic->price_grid = implode(' , ', $orders_data_array['price']);
            /// price and cost
            if ($p_info->products_id_price && $p_info->products_id != $p_info->products_id_price) {
                $price_view_obj = new View_Price_Data(\common\models\Products::find_one($p_info->products_id_price));
            } else {
                $price_view_obj = new View_Price_Data($p_info);
            }
            $price_view_obj->populate_view($this->view, $currencies);
            /// assigned attributes
            if (count($this->view->attributes) > 0) {
                // attributes added to system
                $attributes = new View_Attributes($p_info, $p_info->parent_products_id > 0);
                $attributes->populate_view($this->view);
            }
            if (tep_not_null($p_info->featured_status)) {
                $this->view->featured = $p_info->featured_status;
                $this->view->featured_expires_date = \common\helpers\Date::date_short($p_info->featured_expires_date);
            }
            $product_file = '';
            if ($p_info->products_file != '') {
                $product_file .= '<a href="' . tep_href_link(FILENAME_DOWNLOAD, 'filename=' . $p_info->products_file) . '">' . $p_info->products_file . '</a><br>';
                $product_file .= tep_draw_hidden_field('products_previous_file', $p_info->products_file) . '<input type="checkbox" name="delete_products_file" value="yes">' . TEXT_PRODUCTS_IMAGE_REMOVE_SHORT;
            }
            $this->view->product_file = $product_file;
            $this->view->platform_assigned = [];
            $get_assigned_platforms_r = tep_db_query('SELECT platform_id FROM ' . TABLE_PLATFORMS_PRODUCTS . " WHERE products_id = '" . intval($p_info->products_id) . "' ");
            if (tep_db_num_rows($get_assigned_platforms_r) > 0) {
                while ($_assigned_platform = tep_db_fetch_array($get_assigned_platforms_r)) {
                    $this->view->platform_assigned[(int) $_assigned_platform['platform_id']] = (int) $_assigned_platform['platform_id'];
                }
            }
            $this->view->platform_activate_categories = [];
            foreach (\common\classes\platform::get_categories_assign_list() as $__category_platform) {
                if (isset($this->view->platform_assigned[$__category_platform['id']])) {
                    continue;
                }
                $get_notactive_categories_r = tep_db_query('SELECT p2c.categories_id, plc.platform_id ' . 'FROM ' . TABLE_PRODUCTS_TO_CATEGORIES . ' p2c ' . '  LEFT JOIN ' . TABLE_PLATFORMS_CATEGORIES . " plc ON plc.categories_id=p2c.categories_id and plc.platform_id='" . $__category_platform['id'] . "'  " . "WHERE p2c.products_id='" . intval($p_info->products_id) . "' " . '  /*AND plc.platform_id IS NULL*/');
                while ($_notactive_category = tep_db_fetch_array($get_notactive_categories_r)) {
                    foreach (\common\helpers\Categories::generate_category_path($_notactive_category['categories_id']) as $_category_path_array) {
                        if (!isset($this->view->platform_activate_categories[$__category_platform['id']])) {
                            $this->view->platform_activate_categories[$__category_platform['id']] = [];
                        }
                        $this->view->platform_activate_categories[$__category_platform['id']][$_category_path_array[0]['id']] = ['label' => implode(' &gt; ', array_reverse(array_map(function ($_in) {
                            return $_in['text'];
                        }, $_category_path_array))), 'selected' => !is_null($_notactive_category['platform_id'])];
                    }
                }
            }
            $this->view->department_activate_categories = [];
            $this->view->department_assigned = [];
            if (defined('SUPERADMIN_ENABLED') && SUPERADMIN_ENABLED == true) {
                // {{ department tab
                if (isset($p_info->products_id) && intval($p_info->products_id) > 0) {
                    $get_assigned_department_r = tep_db_query('SELECT departments_id FROM ' . TABLE_DEPARTMENTS_PRODUCTS . " WHERE products_id = '" . intval($p_info->products_id) . "' ");
                    if (tep_db_num_rows($get_assigned_department_r) > 0) {
                        while ($_assigned_department = tep_db_fetch_array($get_assigned_department_r)) {
                            $this->view->department_assigned[(int) $_assigned_department['departments_id']] = (int) $_assigned_department['departments_id'];
                        }
                    }
                } elseif ($in_category_id > 0) {
                    $get_assigned_department_r = tep_db_query('SELECT departments_id FROM ' . TABLE_DEPARTMENTS_CATEGORIES . " WHERE categories_id = '" . intval($in_category_id) . "' ");
                    if (tep_db_num_rows($get_assigned_department_r) > 0) {
                        while ($_assigned_department = tep_db_fetch_array($get_assigned_department_r)) {
                            $this->view->department_assigned[(int) $_assigned_department['departments_id']] = (int) $_assigned_department['departments_id'];
                        }
                    }
                } else {
                    foreach (\common\classes\department::get_catalog_assign_list() as $___data) {
                        $this->view->department_assigned[intval($___data['id'])] = intval($___data['id']);
                    }
                }
                foreach (\common\classes\department::get_catalog_assign_list() as $__category_department) {
                    if (isset($this->view->department_assigned[$__category_department['id']])) {
                        continue;
                    }
                    $get_notactive_categories_r = tep_db_query('SELECT p2c.categories_id, plc.departments_id ' . 'FROM ' . TABLE_PRODUCTS_TO_CATEGORIES . ' p2c ' . '  LEFT JOIN ' . TABLE_DEPARTMENTS_CATEGORIES . " plc ON plc.categories_id=p2c.categories_id and plc.departments_id='" . $__category_department['id'] . "'  " . "WHERE p2c.products_id='" . intval($p_info->products_id) . "' " . '  /*AND plc.departments_id IS NULL*/');
                    while ($_notactive_category = tep_db_fetch_array($get_notactive_categories_r)) {
                        foreach (\common\helpers\Categories::generate_category_path($_notactive_category['categories_id']) as $_category_path_array) {
                            if (!isset($this->view->department_activate_categories[$__category_platform['id']])) {
                                $this->view->department_activate_categories[$__category_department['id']] = [];
                            }
                            $this->view->department_activate_categories[$__category_department['id']][$_category_path_array[count($_category_path_array) - 1]['id']] = ['label' => implode(' &gt; ', array_reverse(array_map(function ($_in) {
                                return $_in['text'];
                            }, $_category_path_array))), 'selected' => !is_null($_notactive_category['departments_id'])];
                        }
                    }
                }
                // }} department tab
            }
            $this->view->suppliers = [];
            $service->get('\common\models\SuppliersProducts', 'sProduct');
            if (!\common\helpers\Attributes::has_product_attributes($p_info->products_id) || $p_info->without_inventory || !\common\helpers\Extensions::is_allowed('Inventory')) {
                //$sProducts = \common\models\SuppliersProducts::getSupplierProducts((int)$pInfo->products_id)->all();
                $s_products = \common\models\Suppliers_Products::find()->alias('sp')->join_with('supplier s')->where(['sp.products_id' => (int) $p_info->products_id])->order_by(new \yii\db\Expression('if(sp.sort_order is null, s.sort_order, sp.sort_order)'))->all();
                $p_info->supplier_default_sort = 1;
                if (!$s_products) {
                    $s_product = (new \common\models\Suppliers_Products())->save_default_supplier_product(['products_id' => (int) $p_info->products_id]);
                    if ($s_product) {
                        $s_products = [$s_product];
                    }
                } else if (count($s_products) > 1) {
                    foreach ($s_products as $s_product) {
                        if (!is_null($s_product->sort_order)) {
                            $p_info->supplier_default_sort = 0;
                            break;
                        }
                    }
                }
            }
            if (!empty($s_products)) {
                foreach ($s_products as $s_product) {
                    $this->view->suppliers[$s_product->suppliers_id] = $s_product;
                }
            }
            $this->view->properties_hiddens = '';
            $this->view->properties_array = [];
            $this->view->values_array = [];
            $this->view->extra_values = [];
            $properties_query = tep_db_query('select properties_id, if(values_id > 0, values_id, values_flag) as values_id, extra_value from ' . TABLE_PROPERTIES_TO_PRODUCTS . " where products_id = '" . (int) $p_info->products_id . "'");
            while ($properties = tep_db_fetch_array($properties_query)) {
                if (!in_array($properties['properties_id'], $this->view->properties_array)) {
                    $this->view->properties_array[] = $properties['properties_id'];
                    $this->view->properties_hiddens .= tep_draw_hidden_field('prop_ids[]', $properties['properties_id']);
                }
                $this->view->values_array[$properties['properties_id']][] = $properties['values_id'];
                $this->view->extra_values[$properties['properties_id']][] = $properties['extra_value'];
                $this->view->properties_hiddens .= tep_draw_hidden_field('val_ids[' . $properties['properties_id'] . '][]', $properties['values_id']);
                $this->view->properties_hiddens .= tep_draw_hidden_field('val_extra[' . $properties['properties_id'] . '][]', $properties['extra_value']);
            }
            $this->view->properties_tree_array = \common\helpers\Properties::generate_properties_tree(0, $this->view->properties_array, $this->view->values_array, '', '', $this->view->extra_values);
            $videos = [];
            $products_images = \common\models\Products_Videos::find()->where(['products_id' => $p_info->products_id])->as_array()->all();
            foreach ($products_images as $products_image) {
                if ($products_image['type'] == 1) {
                    $products_image['src'] = '..' . DIRECTORY_SEPARATOR . DIR_WS_IMAGES . 'products' . DIRECTORY_SEPARATOR . $p_info->products_id . DIRECTORY_SEPARATOR . 'videos' . DIRECTORY_SEPARATOR . $products_image['video'];
                }
                $videos[$products_image['language_id']][] = $products_image;
            }
            $this->view->videos = $videos;
            if (Yii::$app->request->is_post) {
                $this->layout = false;
            }
            \common\helpers\Thumb::set_product_pagination($_session, $this->view, (int) $p_info->products_id);
            //improve
            $frontends = [];
            foreach (\common\classes\platform::get_list(false) as $frontend) {
                if (isset($this->view->platform_assigned[$frontend['id']])) {
                    $seo_url = tep_db_fetch_array(tep_db_query('select products_seo_page_name from ' . TABLE_PRODUCTS_DESCRIPTION . " where products_id = '" . $products_id . "' and language_id = '" . (int) \common\helpers\Language::get_default_language_id() . "' and platform_id = '" . (int) $frontend['id'] . "'"));
                    if ($seo_url['products_seo_page_name'] ?? null) {
                        $this->view->preview_link[] = ['link' => 'http://' . $frontend['platform_url'] . '/' . $seo_url['products_seo_page_name'], 'name' => $frontend['text']];
                    } else {
                        $this->view->preview_link[] = ['link' => 'http://' . $frontend['platform_url'] . '/catalog/product?products_id=' . $p_info->products_id, 'name' => $frontend['text']];
                    }
                    $frontends[] = $frontend;
                }
            }
            \common\helpers\Gifts::prepare_gwa($this->view, $p_info->products_id);
        }
        //{{ insert and update
        if (!empty($p_info->products_id) || !empty($p_info->parent_products_id)) {
            $image_edit_obj = new \backend\models\Product_Edit\View_Images($p_info);
            $image_edit_obj->populate_view($this->view);
        }
        //}} insert and update
        $this->view->platforms = $platforms;
        $this->view->def_platform_id = $def_platform_id;
        /// re-arrange data arrays for design templates
        // init price tabs
        $this->view->price_tabs = $this->view->price_tabparams = [];
        ////currencies tabs and params
        $this->view->currencies_tabs = [];
        if ($this->view->use_market_prices) {
            foreach ($currencies->currencies as $value) {
                $value['def_data'] = ['currencies_id' => $value['id']];
                $value['title'] = $value['symbol_left'] . ' ' . $value['code'] . ' ' . $value['symbol_right'];
                $this->view->currencies_tabs[] = $value;
            }
            $this->view->price_tabs[] = $this->view->currencies_tabs;
            $this->view->price_tabparams[] = ['cssClass' => 'tabs-currencies', 'tabs_type' => 'hTab'];
        }
        $this->view->currencies_formats = [];
        // used to format currency in js
        foreach ($currencies->currencies as $value) {
            $value['def_data'] = ['currencies_id' => $value['id']];
            $value['title'] = $value['symbol_left'] . ' ' . $value['code'] . ' ' . $value['symbol_right'];
            $this->view->currencies_formats[] = $value;
        }
        //// groups tabs and params
        if (\common\helpers\Extensions::is_customer_groups_allowed()) {
            $this->view->groups_m = array_merge([['groups_id' => 0, 'groups_name' => TEXT_MAIN]], array_filter($this->view->groups, function ($e) {
                return $e['per_product_price'];
            }));
            $tmp = [];
            foreach ($this->view->groups_m as $value) {
                $value['id'] = $value['groups_id'];
                $value['title'] = $value['groups_name'];
                $value['def_data'] = ['groups_id' => $value['id']];
                unset($value['groups_name']);
                unset($value['groups_id']);
                $tmp[] = $value;
            }
            $this->view->price_tabs[] = $tmp;
            unset($tmp);
            $this->view->price_tabparams[] = [
                'cssClass' => 'tabs-groups',
                // add to tabs and tab-pane
                //'callback' => 'productPriceBlock', // smarty function which will be called before children tabs , data passed as params params
                'callback_bottom' => '',
                'tabs_type' => 'lTab',
                'aboveTabs' => count($this->view->groups_m) < 1 + count($this->view->groups) ? 'productedit/edit-price-link.tpl' : '',
                'all_hidden' => count($this->view->groups_m) == 1,
                'maxHeight' => '400px',
            ];
        }
        foreach (\common\helpers\Hooks::get_list('categories/productedit/before-render') as $filename) {
            include $filename;
        }
        if ($pd_ext = \common\helpers\Acl::check_extension_allowed('ProductDesigner', 'allowed')) {
            $pd_ext::product_edit($p_info, $this->view);
        }
        $p_info->current_assigned_categories = [];
        if ($p_info->products_id) {
            foreach (\common\models\Products::find_one($p_info->products_id)->categories_list as $_cat_tmp) {
                $p_info->current_assigned_categories[] = $_cat_tmp->categories_id;
            }
            $p_info->current_assigned_categories = array_map('intval', $p_info->current_assigned_categories);
        } else {
            $p_info->current_assigned_categories[] = (int) $in_category_id;
        }
        $departments = false;
        if (defined('SUPERADMIN_ENABLED') && SUPERADMIN_ENABLED == true) {
            $departments = \common\classes\department::get_list(false);
        }
        $product_group_variants = [];
        $product_group_name = '';
        if ($p_info->products_groups_id) {
            $product_group_variants = ['items' => [], 'selected' => Url::to(['categories/productedit', 'pID' => $p_info->products_id])];
            $product_group_name = \common\helpers\Product::products_groups_name($p_info->products_groups_id);
            foreach (\common\models\Products::find()->where(['products_groups_id' => $p_info->products_groups_id])->select(['products_id'])->as_array()->all() as $_prod) {
                $product_group_variants['items'][Url::to(['categories/productedit', 'pID' => $_prod['products_id']])] = \common\helpers\Product::get_products_name($_prod['products_id']);
            }
        }
        $products_notes = $this->products_notes_service->find_by_product_id((int) $p_info->products_id);
        $language_names = [];
        $language_names[0] = '';
        foreach ($languages as $language) {
            $language_names[$language['id']] = $language['name'];
        }
        $video = [];
        $video[0] = '';
        if ($p_info->products_id > 0) {
            $products_videos = \common\models\Products_Videos::find()->where(['products_id' => $p_info->products_id])->as_array()->all();
            foreach ($products_videos as $products_video) {
                $video[$language_names[$products_video['language_id']]][$products_video['video_id']] = 'Video#' . $products_video['video_id'];
            }
        }
        $hidden_admin_language = \common\helpers\Language::get_admin_hidden_languages();
        if (!empty($hidden_admin_language)) {
            foreach ($this->view->platform_languages as $_pl => $_lngs) {
                $show_hidden_admin_language = false;
                if (!empty($this->view->sphl) && is_array($this->view->sphl) && !empty($this->view->sphl[$_pl])) {
                    continue;
                }
                if (is_array($_lngs)) {
                    $this->view->platform_languages[$_pl] = array_values(array_filter($_lngs, function ($el) use ($hidden_admin_language) {
                        return !in_array($el['id'], $hidden_admin_language);
                    }));
                }
            }
        }
        global $navigation;
        if (sizeof($navigation->snapshot) > 0) {
            $back_url = Yii::$app->url_manager->create_url(array_merge([$navigation->snapshot['page']], $navigation->snapshot['get']));
        } else {
            $category_id = \common\models\Products2Categories::find_one(['products_id' => $p_info->products_id])->categories_id ?? null;
            $back_url = Yii::$app->url_manager->create_url(['category', 'category_id' => $category_id]);
        }
        return $this->render('productedit.tpl', [
            'infoBreadCrumb' => $edit_product_in_path,
            'infoSubProducts' => $info_sub_products,
            'editProductBundleSwitcher' => $edit_product_bundle_switcher,
            // allowed ProductBundles ext or product->is_bundle
            'default_currency' => $currencies->currencies[DEFAULT_CURRENCY],
            'currencies' => $currencies,
            'languages' => $languages,
            'languages_id' => $languages_id,
            'pInfo' => $p_info,
            'pDescription' => $p_description,
            'categories_id' => $in_category_id,
            'json_platform_activate_categories' => json_encode($this->view->platform_activate_categories),
            'json_department_activate_categories' => json_encode($this->view->department_activate_categories),
            'departments' => $departments,
            'selected_department_id' => $selected_department_id,
            'service' => $service,
            'productGroupName' => $product_group_name,
            'productGroupVariants' => $product_group_variants,
            'TabAccess' => $this->product_edit_tab_access,
            'isBundle' => $is_bundle,
            'productsNotes' => $products_notes,
            'video' => $video,
            'popup' => false,
            'hideSuppliersPart' => false,
            'hidden_admin_language' => $hidden_admin_language,
            'backUrl' => $back_url,
        ]);
    }
    public function action_property_values()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/categories');
        $this->layout = false;
        $products_id = (int) Yii::$app->request->post('products_id');
        $properties_id = (int) Yii::$app->request->post('properties_id');
        $values = [];
        $property = tep_db_fetch_array(tep_db_query('select properties_id, properties_type, multi_choice, multi_line, decimals, extra_values from ' . TABLE_PROPERTIES . " where properties_id = '" . (int) $properties_id . "'"));
        $property['properties_name'] = \common\helpers\Properties::get_properties_name($property['properties_id'], $languages_id);
        if ($property['properties_type'] == 'flag') {
            $values = [];
            $values[] = ['values_id' => '1', 'values' => TEXT_PROP_FLAG_YES];
            $values[] = ['values_id' => '0', 'values' => TEXT_PROP_FLAG_NO];
        } else {
            $properties_values_query = tep_db_query('select values_id, values_text, values_number, values_number_upto, values_alt, values_prefix, values_postfix from ' . TABLE_PROPERTIES_VALUES . " where properties_id = '" . (int) $properties_id . "' and language_id = '" . (int) $languages_id . "' order by sort_order, " . ($property['properties_type'] == 'number' || $property['properties_type'] == 'interval' ? 'values_number' : 'values_text'));
            $thousands_separator = '';
            $decimal_separator = '.';
            while ($properties_values = tep_db_fetch_array($properties_values_query)) {
                if ($property['properties_type'] == 'interval') {
                    $properties_values['values'] = number_format($properties_values['values_number'], $property['decimals'], $decimal_separator, $thousands_separator) . ' - ' . number_format($properties_values['values_number_upto'], $property['decimals'], $decimal_separator, $thousands_separator);
                } elseif ($property['properties_type'] == 'number') {
                    $properties_values['values'] = number_format($properties_values['values_number'], $property['decimals'], $decimal_separator, $thousands_separator);
                } else {
                    $properties_values['values'] = $properties_values['values_text'];
                }
                $values[$properties_values['values_id']] = $properties_values;
            }
        }
        // extra_values for extra fields
        // values_prefix values_postfix
        return $this->render('property-values.tpl', ['property' => $property, 'values' => $values]);
    }
    public function action_update_property_values()
    {
        $properties_array = Yii::$app->request->post('properties_array', []);
        $values_array = Yii::$app->request->post('values_array', []);
        $extra_values = Yii::$app->request->post('extra_values', []);
        $values_ids = [];
        $val_extra = [];
        $properties_hiddens = '';
        foreach ($properties_array as $key => $properties_id) {
            if ($properties_id > 0) {
                $properties_hiddens .= tep_draw_hidden_field('prop_ids[]', $properties_id);
                foreach ($values_array[$key] as $values_key => $values_id) {
                    $properties_id;
                    $property = \common\models\Properties::find_one($properties_id);
                    if ($values_id > 0 || $property->properties_type == 'flag') {
                        $properties_hiddens .= tep_draw_hidden_field('val_ids[' . $properties_id . '][]', $values_id);
                        $properties_hiddens .= tep_draw_hidden_field('val_extra[' . $properties_id . '][]', $extra_values[$key][$values_key] ?? null);
                        $values_ids[$properties_id][] = $values_id;
                        $val_extra[$properties_id][] = $extra_values[$key][$values_key] ?? null;
                    }
                    unset($property);
                }
            }
        }
        $this->layout = false;
        return $this->render('property-values-selected.tpl', ['properties_hiddens' => $properties_hiddens, 'properties_tree_array' => \common\helpers\Properties::generate_properties_tree(0, $properties_array, $values_ids, '', '', $val_extra)]);
    }
    public function action_product_new_option()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/categories');
        $this->layout = false;
        $products_id = (int) Yii::$app->request->post('products_id');
        $inventory_disable = Yii::$app->request->post('without_inventory', 0) == 1;
        /*
                if (count(\common\helpers\Product::getChildArray($products_id)) > 0) {
                    return json_encode([]);
                }
        */
        $products_options_ids = array_unique(explode(',', Yii::$app->request->post('products_options_id')));
        $products_options_values_ids = array_unique(explode(',', Yii::$app->request->post('products_options_values_id')));
        foreach ($products_options_ids as $k => $v) {
            if (intval($v) == 0) {
                unset($products_options_ids[$k]);
            } else {
                $products_options_ids[$k] = intval($v);
            }
        }
        foreach ($products_options_values_ids as $k => $v) {
            if (intval($v) == 0) {
                unset($products_options_values_ids[$k]);
            } else {
                $products_options_values_ids[$k] = intval($v);
            }
        }
        $this->view->groups = [];
        if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroups', 'allowed')) {
            $ext::get_groups();
            //fills in $this->view->groups
        }
        $this->view->images = \common\helpers\Product::get_product_images($products_id);
        $ret = [];
        $attributes = [];
        $products_options_id = false;
        $currencies = Yii::$container->get('currencies');
        /// re-arrange data arrays for design templates
        // init price tabs
        $this->view->use_market_prices = USE_MARKET_PRICES == 'True';
        $this->view->price_tabs = $this->view->price_tabparams = [];
        ////currencies tabs and params
        if ($this->view->use_market_prices) {
            $this->view->currencies_tabs = [];
            foreach ($currencies->currencies as $value) {
                $value['def_data'] = ['currencies_id' => $value['id']];
                $value['title'] = $value['symbol_left'] . ' ' . $value['code'] . ' ' . $value['symbol_right'];
                $this->view->currencies_tabs[] = $value;
            }
            $this->view->price_tabs[] = $this->view->currencies_tabs;
            $this->view->price_tabparams[] = ['cssClass' => 'tabs-currencies', 'tabs_type' => 'hTab'];
        }
        //// groups tabs and params
        if (\common\helpers\Extensions::is_customer_groups_allowed() && count($this->view->groups) > 0) {
            $this->view->groups_m = array_merge([['groups_id' => 0, 'groups_name' => TEXT_MAIN]], $this->view->groups);
            $tmp = [];
            foreach ($this->view->groups_m as $value) {
                $value['id'] = $value['groups_id'];
                $value['title'] = $value['groups_name'];
                $value['def_data'] = ['groups_id' => $value['id']];
                unset($value['groups_name']);
                unset($value['groups_id']);
                $tmp[] = $value;
            }
            $this->view->price_tabs[] = $tmp;
            unset($tmp);
            $this->view->price_tabparams[] = [
                'cssClass' => 'tabs-groups',
                // add to tabs and tab-pane
                //'callback' => 'productPriceBlock', // smarty function which will be called before children tabs , data passed as params params
                'callback_bottom' => '',
                'tabs_type' => 'lTab',
                'aboveTabs' => count($this->view->groups_m) < 1 + count($this->view->groups) ? 'productedit/edit-price-link.tpl' : '',
                'all_hidden' => count($this->view->groups_m) == 1,
                'maxHeight' => '400px',
            ];
        }
        $values_query = tep_db_query('select po.products_options_id, po.products_options_name, pov.products_options_values_id, pov.products_options_values_name from ' . TABLE_PRODUCTS_OPTIONS_VALUES . ' pov, ' . TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS . ' p2p, ' . TABLE_PRODUCTS_OPTIONS . " po where po.language_id = '" . $languages_id . "' and po.products_options_id in ('" . implode("', '", $products_options_ids) . "') and pov.products_options_values_id = p2p.products_options_values_id and p2p.products_options_id = po.products_options_id and  pov.products_options_values_id in ('" . implode("','", $products_options_values_ids) . "') and pov.language_id = '" . $languages_id . "' order by po.products_options_name, po.products_options_id, pov.products_options_values_sort_order, pov.products_options_values_name, pov.products_options_values_id ");
        while ($values = tep_db_fetch_array($values_query)) {
            if ($products_options_id != $values['products_options_id']) {
                if ($products_options_id) {
                    $is_virtual_option = \common\helpers\Attributes::is_virtual_option($products_options_id);
                    /** @var \common\extensions\Inventory\Inventory $ext */
                    if (!$inventory_disable && ($ext = \common\helpers\Extensions::is_allowed('Inventory')) && !$is_virtual_option) {
                        $ret[] = ['data' => $ext::get_product_new_option($products_id, $attributes), 'is_virtual_option' => $is_virtual_option, 'products_options_id' => $attributes[0]['products_options_id'], 'products_options_values_id' => $attributes[0]['values'][0]['products_options_values_id']];
                    } else {
                        $ret[] = ['data' => $this->render('product-new-option.tpl', ['products_id' => $products_id, 'default_currency' => $currencies->currencies[DEFAULT_CURRENCY], 'currencies' => $currencies, 'attributes' => $attributes]), 'is_virtual_option' => $is_virtual_option, 'products_options_id' => $attributes[0]['products_options_id'], 'products_options_values_id' => $attributes[0]['values'][0]['products_options_values_id']];
                    }
                    $attributes = [];
                }
                $products_options_id = $values['products_options_id'];
                $attributes[0] = ['is_virtual_option' => \common\helpers\Attributes::is_virtual_option($products_options_id), 'products_options_id' => $values['products_options_id'], 'net_price_formatted' => $currencies->display_price(0, 0, 1, false), 'gross_price_formatted' => $currencies->display_price(0, 0, 1, false), 'products_options_name' => htmlspecialchars($values['products_options_name']), 'values' => []];
            }
            $attributes[0]['values'][] = ['products_options_values_id' => $values['products_options_values_id'], 'net_price_formatted' => $currencies->display_price(0, 0, 1, false), 'gross_price_formatted' => $currencies->display_price(0, 0, 1, false), 'products_options_values_name' => htmlspecialchars($values['products_options_values_name'])];
        }
        if ($products_options_id) {
            $is_virtual_option = \common\helpers\Attributes::is_virtual_option($products_options_id);
            /** @var \common\extensions\Inventory\Inventory $ext */
            if (!$inventory_disable && ($ext = \common\helpers\Extensions::is_allowed('Inventory')) && !$is_virtual_option) {
                $ret[] = ['data' => $ext::get_product_new_option($products_id, $attributes), 'is_virtual_option' => $is_virtual_option, 'products_options_id' => $attributes[0]['products_options_id'], 'products_options_values_id' => $attributes[0]['values'][0]['products_options_values_id']];
            } else {
                $ret[] = ['data' => $this->render('product-new-option.tpl', ['products_id' => $products_id, 'default_currency' => $currencies->currencies[DEFAULT_CURRENCY], 'currencies' => $currencies, 'attributes' => $attributes]), 'is_virtual_option' => $is_virtual_option, 'products_options_id' => $attributes[0]['products_options_id'], 'products_options_values_id' => $attributes[0]['values'][0]['products_options_values_id']];
            }
        }
        return json_encode($ret);
    }
    public function action_product_inventory_box()
    {
        \common\helpers\Translation::init('admin/categories');
        $this->layout = false;
        $this->view->tax_classes = ['0' => TEXT_NONE];
        $tax_class_query = tep_db_query('select tax_class_id, tax_class_title from ' . TABLE_TAX_CLASS . ' order by tax_class_title');
        while ($tax_class = tep_db_fetch_array($tax_class_query)) {
            $this->view->tax_classes[$tax_class['tax_class_id']] = $tax_class['tax_class_title'];
        }
        /* @var $ext  \common\extensions\Inventory\Inventory */
        if ($ext = \common\helpers\Extensions::is_allowed('Inventory')) {
            return $ext::product_inventory_box();
        }
    }
    public function action_selected_attributes()
    {
        \common\helpers\Translation::init('admin/categories');
        $this->layout = false;
        $products_id = (int) Yii::$app->request->post('products_id');
        $inventory_disable = Yii::$app->request->post('without_inventory', 0) == 1;
        $p_info = \common\models\Products::find_one($products_id);
        if (!$p_info) {
            $p_info = new \common\models\Products();
            $p_info->load_default_values();
            $p_info->without_inventory = $inventory_disable ? 1 : 0;
        }
        $p_info = new \Object_Info(array_merge($p_info->get_attributes(), ['options_templates_id' => (int) Yii::$app->request->post('options_templates_id', 0), 'products_tax_class_id' => (int) Yii::$app->request->post('products_tax_class_id', 0)]));
        $this->view->groups = [];
        /** @var \common\extensions\UserGroups\UserGroups $ext */
        if ($ext = \common\helpers\Extensions::is_allowed('UserGroups')) {
            $ext::get_groups();
        }
        $this->view->images = \common\helpers\Product::get_product_images($products_id);
        $ret = [];
        $currencies = Yii::$container->get('currencies');
        $this->view->default_currency = $currencies->currencies[DEFAULT_CURRENCY]['id'];
        $this->view->use_market_prices = USE_MARKET_PRICES == 'True';
        $this->view->price_tabs = $this->view->price_tabparams = [];
        ////currencies tabs and params
        if ($this->view->use_market_prices) {
            $this->view->currencies_tabs = [];
            foreach ($currencies->currencies as $value) {
                $value['def_data'] = ['currencies_id' => $value['id']];
                $value['title'] = $value['symbol_left'] . ' ' . $value['code'] . ' ' . $value['symbol_right'];
                $this->view->currencies_tabs[] = $value;
            }
            $this->view->price_tabs[] = $this->view->currencies_tabs;
            $this->view->price_tabparams[] = ['cssClass' => 'tabs-currencies', 'tabs_type' => 'hTab'];
        }
        //// groups tabs and params
        if (\common\helpers\Extensions::is_customer_groups_allowed() && count($this->view->groups) > 0) {
            $this->view->groups_m = array_merge([['groups_id' => 0, 'groups_name' => TEXT_MAIN]], $this->view->groups);
            $tmp = [];
            foreach ($this->view->groups_m as $value) {
                $value['id'] = $value['groups_id'];
                $value['title'] = $value['groups_name'];
                $value['def_data'] = ['groups_id' => $value['id']];
                unset($value['groups_name']);
                unset($value['groups_id']);
                $tmp[] = $value;
            }
            $this->view->price_tabs[] = $tmp;
            unset($tmp);
            $this->view->price_tabparams[] = [
                'cssClass' => 'tabs-groups',
                // add to tabs and tab-pane
                //'callback' => 'productPriceBlock', // smarty function which will be called before children tabs , data passed as params params
                'callback_bottom' => '',
                'tabs_type' => 'lTab',
                'aboveTabs' => count($this->view->groups_m) < 1 + count($this->view->groups) ? 'productedit/edit-price-link.tpl' : '',
                'all_hidden' => count($this->view->groups_m) == 1,
                'maxHeight' => '400px',
            ];
        }
        $attributes = new View_Attributes($p_info);
        $attributes->populate_view($this->view);
        /** @var \common\extensions\Inventory\Inventory $ext */
        if (!$inventory_disable && $ext = \common\helpers\Extensions::is_allowed('Inventory')) {
            return $ext::product_attributes_box($p_info);
        } else {
            return $this->render('product-new-option.tpl', ['attributes' => $this->view->selected_attributes, 'products_id' => $p_info->products_id, 'currencies' => $currencies]);
        }
    }
    public function action_product_new_attribute()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/categories');
        $this->layout = false;
        $products_id = (int) Yii::$app->request->post('products_id');
        $inventory_disable = Yii::$app->request->post('without_inventory', 0) == 1;
        /*
                if (count(\common\helpers\Product::getChildArray($products_id)) > 0) {
                    return json_encode([]);
                }
        */
        /*arrays of new options & values */
        $products_options_ids = array_unique(explode(',', Yii::$app->request->post('products_options_id')));
        $products_options_values_ids = array_unique(explode(',', Yii::$app->request->post('products_options_values_id')));
        foreach ($products_options_ids as $k => $v) {
            if (intval($v) == 0) {
                unset($products_options_ids[$k]);
            } else {
                $products_options_ids[$k] = intval($v);
            }
        }
        foreach ($products_options_values_ids as $k => $v) {
            if (intval($v) == 0) {
                unset($products_options_values_ids[$k]);
            } else {
                $products_options_values_ids[$k] = intval($v);
            }
        }
        $this->view->groups = [];
        if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroups', 'allowed')) {
            $ext::get_groups();
        }
        $this->view->images = \common\helpers\Product::get_product_images($products_id);
        $ret = [];
        $currencies = Yii::$container->get('currencies');
        /// re-arrange data arrays for design templates
        // init price tabs
        $this->view->use_market_prices = USE_MARKET_PRICES == 'True';
        $this->view->price_tabs = $this->view->price_tabparams = [];
        ////currencies tabs and params
        if ($this->view->use_market_prices) {
            $this->view->currencies_tabs = [];
            foreach ($currencies->currencies as $value) {
                $value['def_data'] = ['currencies_id' => $value['id']];
                $value['title'] = $value['symbol_left'] . ' ' . $value['code'] . ' ' . $value['symbol_right'];
                $this->view->currencies_tabs[] = $value;
            }
            $this->view->price_tabs[] = $this->view->currencies_tabs;
            $this->view->price_tabparams[] = ['cssClass' => 'tabs-currencies', 'tabs_type' => 'hTab'];
        }
        //// groups tabs and params
        if (\common\helpers\Extensions::is_customer_groups_allowed() && count($this->view->groups) > 0) {
            $this->view->groups_m = array_merge([['groups_id' => 0, 'groups_name' => TEXT_MAIN]], $this->view->groups);
            $tmp = [];
            foreach ($this->view->groups_m as $value) {
                $value['id'] = $value['groups_id'];
                $value['title'] = $value['groups_name'];
                $value['def_data'] = ['groups_id' => $value['id']];
                unset($value['groups_name']);
                unset($value['groups_id']);
                $tmp[] = $value;
            }
            $this->view->price_tabs[] = $tmp;
            unset($tmp);
            $this->view->price_tabparams[] = [
                'cssClass' => 'tabs-groups',
                // add to tabs and tab-pane
                //'callback' => 'productPriceBlock', // smarty function which will be called before children tabs , data passed as params params
                'callback_bottom' => '',
                'tabs_type' => 'lTab',
                'aboveTabs' => count($this->view->groups_m) < 1 + count($this->view->groups) ? 'productedit/edit-price-link.tpl' : '',
                'all_hidden' => count($this->view->groups_m) == 1,
                'maxHeight' => '400px',
            ];
        }
        $values_query = tep_db_query('select p2p.products_options_id, pov.products_options_values_id, pov.products_options_values_name from ' . TABLE_PRODUCTS_OPTIONS_VALUES . ' pov, ' . TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS . " p2p where pov.products_options_values_id = p2p.products_options_values_id and p2p.products_options_id in ('" . implode("','", $products_options_ids) . "') and  pov.products_options_values_id in ('" . implode("','", $products_options_values_ids) . "') and pov.language_id = '" . $languages_id . "' order by pov.products_options_values_sort_order, pov.products_options_values_name ");
        while ($values = tep_db_fetch_array($values_query)) {
            $values['net_price_formatted'] = $currencies->display_price(0, 0, 1, false);
            $values['gross_price_formatted'] = $currencies->display_price(0, 0, 1, false);
            $option[0] = $values;
            $is_virtual_option = \common\helpers\Attributes::is_virtual_option($values['products_options_id']);
            /** @var \common\extensions\Inventory\Inventory */
            if (!$inventory_disable && ($ext = \common\helpers\Extensions::is_allowed('Inventory')) && !$is_virtual_option) {
                $ret[] = ['data' => $ext::get_product_new_attribute($products_id, $option, $values['products_options_id']), 'is_virtual_option' => $is_virtual_option, 'products_options_values_id' => $values['products_options_values_id'], 'products_options_id' => $values['products_options_id']];
            } else {
                $ret[] = ['data' => $this->render('product-new-attribute.tpl', ['options' => $option, 'products_id' => $products_id, 'default_currency' => $currencies->currencies[DEFAULT_CURRENCY], 'currencies' => $currencies, 'products_options_id' => $values['products_options_id']]), 'is_virtual_option' => $is_virtual_option, 'products_options_values_id' => $values['products_options_values_id'], 'products_options_id' => $values['products_options_id']];
            }
        }
        return json_encode($ret);
    }
    public function action_product_new_image($id)
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $pid = (int) Yii::$app->request->get('pid');
        \common\helpers\Translation::init('admin/categories');
        $this->layout = false;
        $languages = \common\helpers\Language::get_languages();
        $language_names = [];
        $language_names[0] = '';
        foreach ($languages as $language) {
            $language_names[$language['id']] = $language['name'];
        }
        $video = [];
        $video[0] = '';
        if ($pid > 0) {
            $products_videos = \common\models\Products_Videos::find()->where(['products_id' => $pid])->as_array()->all();
            foreach ($products_videos as $products_video) {
                $video[$language_names[$products_video['language_id']]][$products_video['video_id']] = 'Video#' . $products_video['video_id'];
            }
        }
        $attributes = [];
        $options_query = tep_db_query('select products_options_id, products_options_name from ' . TABLE_PRODUCTS_OPTIONS . " where language_id = '" . $languages_id . "' order by products_options_sort_order, products_options_name");
        if (tep_db_num_rows($options_query)) {
            $options_query = tep_db_query('select products_options_id, products_options_name from ' . TABLE_PRODUCTS_OPTIONS . " where language_id = '" . $languages_id . "' order by products_options_sort_order, products_options_name");
            while ($options = tep_db_fetch_array($options_query)) {
                $values_query = tep_db_query('select pov.products_options_values_id, pov.products_options_values_name from ' . TABLE_PRODUCTS_OPTIONS_VALUES . ' pov, ' . TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS . " p2p where pov.products_options_values_id = p2p.products_options_values_id and p2p.products_options_id = '" . $options['products_options_id'] . "' and pov.language_id = '" . $languages_id . "' order by products_options_values_sort_order, products_options_values_name");
                $option = [];
                while ($values = tep_db_fetch_array($values_query)) {
                    $option[] = ['value' => $values['products_options_values_id'], 'name' => htmlspecialchars($values['products_options_values_name'])];
                }
                $attributes[] = ['id' => $options['products_options_id'], 'label' => htmlspecialchars($options['products_options_name']), 'options' => $option];
            }
        }
        $this->view->attributes = $attributes;
        $image_path = \Yii::get_alias('@web');
        $image_path .= DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
        $file_name = Yii::$app->request->get('name');
        // {{
        $use_external_images = false;
        // !!Yii::$app->request->get('external',0);
        $_ext_images_array = [];
        foreach (Images::get_image_types() as $image_type) {
            $_ext_images_array[$image_type['image_types_id']] = ['image_types_id' => $image_type['image_types_id'], 'image_types_name' => $image_type['image_types_name'], 'image_size' => $image_type['image_types_x'] . 'x' . $image_type['image_types_y'], 'image_url' => ''];
        }
        // }}
        $Item = [
            'products_images_id' => 0,
            'default_image' => 0,
            'image_status' => 1,
            'image_name' => empty($file_name) ? '' : $image_path . $file_name,
            // for language_id = 0
            'image_title' => '',
            'image_alt' => '',
            'orig_file_name' => $file_name,
            'use_origin_image_name' => 0,
            'hash_file_name' => '',
            'file_name' => '',
            'alt_file_name' => '',
            'no_watermark' => 0,
            'use_external_images' => $use_external_images,
            'external_image_original' => '',
            'external_images' => $_ext_images_array,
        ];
        $description = [];
        for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
            $description[$i] = ['key' => $i + 1, 'id' => $languages[$i]['id'], 'code' => $languages[$i]['code'], 'name' => $languages[$i]['name'], 'logo' => $languages[$i]['image'], 'image_title' => '', 'image_alt' => '', 'orig_file_name' => '', 'use_origin_image_name' => 0, 'hash_file_name' => '', 'file_name' => '', 'alt_file_name' => '', 'no_watermark' => 0, 'image_name' => '', 'use_external_images' => $use_external_images, 'external_image_original' => '', 'external_images' => $_ext_images_array];
        }
        return $this->render('product-new-image.tpl', ['Item' => $Item, 'description' => $description, 'Key' => $id, 'video' => $video]);
    }
    /**
     * check & returns data from marketing tabs if any
     * @field ['db' => 'products_price_discount_pack_unit', db field name --- not required at all :(
     * 'postreindex' => 'discount_qty_pack_unit', POST - change array keys to
     * 'post' => 'discount_price_pack_unit', POST key. Required!
     * 'flag' => 'qty_discount_status_pack_unit', POST switcher flag (1 - on!!! someone use yes o_O )
     * 'f' => ['self', 'formatDiscountString']] - validator - callback
     */
    private static function get_from_post_arrays($field, $curr_id, $group_id = 0)
    {
        return \backend\models\Product_Edit\Post_Array_Helper::get_from_post_arrays($field, $curr_id, $group_id);
    }
    public function action_product_submit()
    {
        if (false === \common\helpers\Acl::rule(['TABLE_HEADING_PRODUCTS', 'IMAGE_EDIT'])) {
            die;
        }
        $languages_id = \Yii::$app->settings->get('languages_id');
        $is_new_product = false;
        \common\helpers\Translation::init('admin/categories');
        $currencies = Yii::$container->get('currencies');
        $path = \Yii::get_alias('@webroot');
        $path .= DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
        $selected_department_id = (int) Yii::$app->request->post('department_id', 0);
        $old_products_id = $products_id = (int) Yii::$app->request->post('products_id');
        if (\common\helpers\Acl::check_extension_allowed('ReportChangesHistory')) {
            $logger = new \common\extensions\Report_Changes_History\classes\Logger();
            $before_object = new \common\api\Classes\Product();
            $before_object->load($products_id);
            $logger->set_before_object($before_object);
            unset($before_object);
        }
        if ((int) $products_id > 0) {
            $action = 'update_product';
        } else {
            $action = 'insert_product';
        }
        $tab_access = $this->product_edit_tab_access;
        $currencies_ids = $groups = $groups_price = [];
        if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroups', 'allowed')) {
            $groups = $ext::get_groups_array();
            if (!isset($groups['0'])) {
                $groups['0'] = ['groups_id' => 0, 'per_product_price' => 1];
            }
            $groups_price = array_filter($groups, function ($e) {
                return $e['per_product_price'];
            });
            if ($groups_price == $groups) {
                $groups_price = null;
                //php8: was unset($groups_price);
            }
        }
        $_def_curr_id = $currencies->currencies[DEFAULT_CURRENCY]['id'];
        if (USE_MARKET_PRICES == 'True') {
            foreach ($currencies->currencies as $key => $value) {
                $currencies_ids[$currencies->currencies[$key]['id']] = $currencies->currencies[$key]['id'];
            }
        } else {
            $currencies_ids[$_def_curr_id] = '0';
            /// here is the post and db currencies_id are different.
        }
        if ($action == 'update_product') {
            $product_model = \common\models\Products::find_one((int) $products_id);
        } else {
            $product_model = new \common\models\Products();
            $product_model->load_default_values();
            $product_model->parent_products_id = intval(Yii::$app->request->post('parent_products_id', 0));
            //['products_model','products_price','products_price_rrp','products_weight'];
            //$productModel->
        }
        $_products_id_price = intval(Yii::$app->request->post('products_id_price', -1));
        if ($_products_id_price >= 0) {
            $product_model->products_id_price = $_products_id_price;
        }
        $tab_access->set_product($product_model);
        /**
         * Main details
         */
        $sql_data_array = [];
        if ($tab_access->tab_data_save('TEXT_MAIN_DETAILS')) {
            if ($product_model->parent_products_id) {
                $main_details = new \backend\models\Product_Edit\Save_Sub_Product_Main_Details($product_model);
            } else {
                $main_details = new \backend\models\Product_Edit\Save_Main_Details($product_model);
            }
            $main_details->prepare_save();
        }
        /**
         * Size and Packaging
         */
        if ($tab_access->tab_data_save('TEXT_SIZE_PACKAGING')) {
            $packaging = new \backend\models\Product_Edit\Save_Size_And_Packaging($product_model);
            $packaging->prepare_save();
        }
        if ($tab_access->tab_data_save('TAB_BUNDLES')) {
            if (\common\helpers\Acl::check_extension_allowed('ProductBundles')) {
                $sql_data_array['is_bundle'] = tep_db_prepare_input(Yii::$app->request->post('is_bundle'));
                //$sql_data_array['products_sets_price'] = tep_db_prepare_input(Yii::$app->request->post('products_sets_price');
                $sql_data_array['use_sets_discount'] = tep_db_prepare_input(Yii::$app->request->post('use_sets_discount'));
                $sql_data_array['products_sets_discount'] = tep_db_prepare_input(Yii::$app->request->post('products_sets_discount'));
                $sql_data_array['products_sets_price_formula'] = tep_db_prepare_input(Yii::$app->request->post('products_sets_price_formula'));
            } elseif (Yii::$app->request->post('is_bundle') == 0) {
                // allow to reset bundle
                $sql_data_array['is_bundle'] = tep_db_prepare_input(Yii::$app->request->post('is_bundle'));
            }
        }
        $categories_id = (int) Yii::$app->request->post('categories_id');
        if ($action == 'insert_product') {
            $sql_data_array['products_date_added'] = new Expression('NOW()');
            //tep_db_perform(TABLE_PRODUCTS, $sql_data_array);
            $product_model->set_attributes($sql_data_array, false);
            $product_model->save(false);
            $product_model->refresh();
            if ($product_model->parent_products_id) {
                $product_model->products_id_stock = $product_model->parent_products_id;
                $product_model->products_id_price = $product_model->parent_products_id;
            } else {
                $product_model->products_id_stock = $product_model->products_id;
                $product_model->products_id_price = $product_model->products_id;
            }
            //$products_id = tep_db_insert_id();
            $products_id = $product_model->products_id;
            $is_new_product = true;
            tep_db_query('insert into ' . TABLE_PRODUCTS_TO_CATEGORIES . " (products_id, categories_id) values ('" . (int) $products_id . "', '" . (int) $categories_id . "')");
            // {{ mark_parent_as_master
            if (Yii::$app->request->post('mark_parent_as_master', 0) && $product_model->parent_products_id) {
                if ($parent_model = \common\models\Products::find_one($product_model->parent_products_id)) {
                    $parent_model->is_listing_product = 0;
                    $parent_model->save(false);
                }
            }
            // }}
        } elseif ($action == 'update_product') {
            $sql_data_array['products_last_modified'] = new Expression('NOW()');
            //tep_db_perform(TABLE_PRODUCTS, $sql_data_array, 'update', "products_id = '" . (int) $products_id . "'");
            $product_model->set_attributes($sql_data_array, false);
            $product_model->save(false);
            $product_model->refresh();
            $check_query = tep_db_query('select count(*) as total from ' . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id = '" . (int) $products_id . "'");
            $check = tep_db_fetch_array($check_query);
            if ($check['total'] < '1') {
                tep_db_query('insert into ' . TABLE_PRODUCTS_TO_CATEGORIES . " (products_id, categories_id) values ('" . (int) $products_id . "', '" . (int) $categories_id . "')");
            }
        }
        if ($tab_access->tab_data_save('TAB_NOTES')) {
            $products_notes = $this->products_notes_service->find_by_product_id($product_model->products_id);
            if ($products_notes) {
                $products_note_form = [$this->products_notes_service->new_products_note_by_array()];
                $products_note_save = [];
                foreach (Yii::$app->request->post($products_note_form[0]->form_name(), []) as $i => $data) {
                    /** @var ProductsNotes $productsNoteSave[$i] */
                    $products_note_save[$i] = $this->products_notes_service->get_by_id($i);
                    if ($products_note_save[$i]->load($data, '') && $products_note_save[$i]->validate()) {
                        $this->products_notes_service->save($products_note_save[$i]);
                    } else {
                        unset($products_note_save[$i]);
                    }
                }
                $delete_notes = array_diff(array_keys($products_notes), array_keys($products_note_save));
                foreach ($delete_notes as $note) {
                    $this->products_notes_service->remove($products_notes[$note]);
                }
            }
            $new_note_post_form = [$this->products_notes_service->new_products_note_form_by_array()];
            foreach (Yii::$app->request->post($new_note_post_form[0]->form_name(), []) as $data) {
                $new_note_form = $this->products_notes_service->new_products_note_form_by_array();
                if ($new_note_form->load($data, '') && $new_note_form->validate()) {
                    $new_product_note = $this->products_notes_service->new_product_note($product_model->products_id, $new_note_form->note);
                    $this->products_notes_service->save($new_product_note);
                }
            }
        }
        if ($tab_access->tab_data_save('TEXT_SEO')) {
            if ($ext = \common\helpers\Acl::check_extension_allowed('SeoRedirectsNamed', 'allowed')) {
                $ext::save_product_links($products_id, $_POST);
            }
        }
        // Update stock quantity
        /*if (\common\helpers\Acl::rule(['TABLE_HEADING_PRODUCTS', 'IMAGE_EDIT', 'TEXT_MAIN_DETAILS'])) {
              $products_quantity_update = (int) Yii::$app->request->post('products_quantity_update');
              $products_quantity_update_prefix = (Yii::$app->request->post('products_quantity_update_prefix') == '-' ? '-' : '+');
              if ($products_quantity_update > 0) {
                  global $login_id;
                  $warehouse_id = (int) Yii::$app->request->post('warehouse_id');
                  $w_suppliers_id = (int)Yii::$app->request->post('w_suppliers_id', 0);
                  $stock_comments = Yii::$app->request->post('stock_comments');
                  $location_id = 0;
                  $locationIds = Yii::$app->request->post('box_location');
                  if (is_array($locationIds)) {
                      foreach ($locationIds as $lid) {
                          if ($lid > 0) {
                              $location_id = $lid;
                          }
                      }
                  }
                  //\common\helpers\Product::log_stock_history_before_update($products_id, $products_quantity_update, $products_quantity_update_prefix, ['warehouse_id' => $warehouse_id, 'comments' => TEXT_MANUALL_STOCK_UPDATE . (trim($stock_comments) != '' ? ': ' . $stock_comments : ''), 'admin_id' => $login_id]);
                  if ($warehouse_id > 0) {
                      $parameters = [
                          'admin_id' => $login_id,
                          'comments' => (TEXT_MANUALL_STOCK_UPDATE . (trim($stock_comments) != '' ? ': ' . $stock_comments : ''))
                      ];
                      \common\helpers\Warehouses::update_products_quantity($products_id, $warehouse_id, $products_quantity_update, $products_quantity_update_prefix, $w_suppliers_id, $location_id, $parameters);
                      \common\helpers\Warehouses::get_allocated_stock_quantity($products_id);
                      \common\helpers\Warehouses::get_temporary_stock_quantity($products_id);
                  } else {
                      tep_db_query("update " . TABLE_PRODUCTS . " set products_quantity = products_quantity " . $products_quantity_update_prefix . $products_quantity_update . " where products_id = '" . (int) $products_id . "'");
                  }
              }
          }*/
        //Give away
        if ($tab_access->tab_data_save('TEXT_MARKETING')) {
            $marketing_data = new \backend\models\Product_Edit\Save_Marketing_Data($product_model);
            $marketing_data->prepare_save();
        }
        //Gift wrap
        if ($tab_access->tab_data_save('TEXT_MAIN_DETAILS')) {
            if ($old_products_id > 0) {
                if ($groups_price) {
                    \common\models\Gift_Wrap_Products::delete_all(['products_id' => (int) $old_products_id, 'groups_id' => array_keys($groups_price)]);
                } else {
                    tep_db_query('delete from ' . TABLE_GIFT_WRAP_PRODUCTS . " where products_id = '" . (int) $old_products_id . "'");
                }
            }
            $gift_wrap = Yii::$app->request->post('gift_wrap', 0);
            if (is_array($gift_wrap) || $gift_wrap > 0) {
                if (is_array($gift_wrap) && (USE_MARKET_PRICES == 'True' || \common\helpers\Extensions::is_customer_groups_allowed())) {
                    foreach ($currencies_ids as $post_currencies_id => $currencies_id) {
                        foreach ($groups_price ? $groups_price : $groups as $groups_id => $non) {
                            $sql_data_array = ['products_id' => (int) $products_id, 'groups_id' => (int) $groups_id, 'currencies_id' => (int) $currencies_id];
                            $field = ['db' => 'gift_wrap_price', 'dbdef' => 0, 'post' => 'gift_wrap_price', 'flag' => 'gift_wrap'];
                            if (self::get_from_post_arrays(['post' => 'gift_wrap'], (int) $post_currencies_id, (int) $groups_id) == 1) {
                                $sql_data_array[$field['db']] = self::get_from_post_arrays($field, (int) $post_currencies_id, (int) $groups_id);
                                tep_db_perform(TABLE_GIFT_WRAP_PRODUCTS, $sql_data_array);
                            }
                        }
                    }
                } else {
                    $sql_data_array = ['products_id' => (int) $products_id, 'groups_id' => 0, 'currencies_id' => 0];
                    $field = ['db' => 'gift_wrap_price', 'dbdef' => 0, 'post' => 'gift_wrap_price', 'flag' => 'gift_wrap'];
                    if (self::get_from_post_arrays(['post' => 'gift_wrap'], 0) == 1) {
                        $sql_data_array[$field['db']] = self::get_from_post_arrays($field, 0);
                        tep_db_perform(TABLE_GIFT_WRAP_PRODUCTS, $sql_data_array);
                    }
                }
            }
        }
        // Featured
        if ($tab_access->tab_data_save('TEXT_MAIN_DETAILS')) {
            $featured = (int) Yii::$app->request->post('featured');
            $featured_expires_date = Yii::$app->request->post('featured_expires_date');
            if ($featured == 0) {
                tep_db_query('delete from ' . TABLE_FEATURED . " where products_id = '" . (int) $products_id . "'");
            } else {
                if (!empty($featured_expires_date)) {
                    $featured_expires_date = \common\helpers\Date::prepare_input_date($featured_expires_date);
                }
                $check_data = tep_db_query('select * from ' . TABLE_FEATURED . " where products_id ='" . (int) $products_id . "'");
                if (tep_db_num_rows($check_data) > 0) {
                    $check = tep_db_fetch_array($check_data);
                    tep_db_query('update ' . TABLE_FEATURED . " set featured_last_modified = now(), status = '1', expires_date = '" . tep_db_input($featured_expires_date) . "' where featured_id = '" . (int) $check['featured_id'] . "'");
                } else {
                    tep_db_query('insert into ' . TABLE_FEATURED . " (products_id, featured_date_added, expires_date, status, affiliate_id) values ('" . (int) $products_id . "', now(), '" . tep_db_input($featured_expires_date) . "', '1', '0')");
                }
            }
        }
        /**
         * Price and Cost
         */
        if ($tab_access->tab_data_save('TEXT_PRICE_COST_W')) {
            $product_model->disable_discount = intval(Yii::$app->request->post('disable_discount', 0));
            ///1 nya in product table: shipping_surcharge_price etc
            $sql_data_array = [];
            $fields = [['db' => 'products_price', 'dbdef' => 0, 'post' => 'products_group_price'], ['db' => 'products_price_full', 'dbdef' => 0, 'post' => 'products_price_full'], ['db' => 'products_price_rrp', 'dbdef' => 0, 'post' => 'products_price_rrp'], ['db' => 'products_tax_class_id', 'dbdef' => 0, 'post' => 'products_tax_class_id'], ['db' => 'products_price_pack_unit', 'dbdef' => -2, 'post' => 'products_group_price_pack_unit', 'f' => ['self', 'defGroupPrice']], ['db' => 'products_price_packaging', 'dbdef' => -2, 'post' => 'products_group_price_packaging', 'f' => ['self', 'defGroupPrice']], ['db' => 'supplier_price_manual', 'dbdef' => 'null', 'post' => 'supplier_auto_price'], ['db' => 'shipping_surcharge_price', 'dbdef' => 0, 'post' => 'shipping_surcharge_price', 'flag' => 'shipping_surcharge'], ['db' => 'bonus_points_price', 'dbdef' => 0, 'post' => 'bonus_points_price', 'flag' => 'bonus_points_status'], ['db' => 'bonus_points_cost', 'dbdef' => 0, 'post' => 'bonus_points_cost', 'flag' => 'bonus_points_status'], ['db' => 'products_price_discount', 'dbdef' => '', 'postreindex' => 'discount_qty', 'post' => 'discount_price', 'flag' => 'qty_discount_status', 'f' => ['self', 'formatDiscountString']], ['db' => 'products_price_discount_pack_unit', 'dbdef' => '', 'postreindex' => 'discount_qty_pack_unit', 'post' => 'discount_price_pack_unit', 'flag' => 'qty_discount_status_pack_unit', 'f' => ['self', 'formatDiscountString']], ['db' => 'products_price_discount_packaging', 'dbdef' => '', 'postreindex' => 'discount_qty_packaging', 'post' => 'discount_price_packaging', 'flag' => 'qty_discount_status_packaging', 'f' => ['self', 'formatDiscountString']]];
            //products_weight - saved above
            ///????products_sets_price
            foreach ($fields as $field) {
                $sql_data_array[$field['db']] = self::get_from_post_arrays($field, $_def_curr_id, 0);
            }
            $sql_data_array['supplier_price_manual'] = $sql_data_array['supplier_price_manual'] == '1' ? 0 : 1;
            // reset matched with current config
            if ($sql_data_array['supplier_price_manual'] == 1 && SUPPLIER_UPDATE_PRICE_MODE == 'Manual' || $sql_data_array['supplier_price_manual'] == 0 && SUPPLIER_UPDATE_PRICE_MODE == 'Auto') {
                $sql_data_array['supplier_price_manual'] = 'null';
            }
            tep_db_perform(TABLE_PRODUCTS, $sql_data_array, 'update', "products_id = '" . (int) $products_id . "'");
            //2 group prices specials. etc
            if (USE_MARKET_PRICES == 'True' || \common\helpers\Extensions::is_customer_groups_allowed()) {
                if ($groups_price ?? null) {
                    \common\models\Products_Prices::delete_all(['products_id' => (int) $old_products_id, 'groups_id' => array_keys($groups_price)]);
                } else {
                    tep_db_query('delete from ' . TABLE_PRODUCTS_PRICES . " where products_id = '" . (int) $products_id . "'");
                }
                foreach ($currencies_ids as $post_currencies_id => $currencies_id) {
                    foreach ($groups_price ? $groups_price : $groups as $groups_id => $non) {
                        $sql_data_array = ['products_id' => (int) $products_id, 'groups_id' => (int) $groups_id, 'currencies_id' => (int) $currencies_id];
                        $fields = [['db' => 'products_sets_discount', 'dbdef' => 0, 'post' => 'products_group_sets_discount'], ['db' => 'products_group_price', 'dbdef' => $groups_id == 0 ? 0 : -2, 'post' => 'products_group_price'], ['db' => 'bonus_points_price', 'dbdef' => 0, 'post' => 'bonus_points_price', 'flag' => 'bonus_points_status'], ['db' => 'bonus_points_cost', 'dbdef' => 0, 'post' => 'bonus_points_cost', 'flag' => 'bonus_points_status'], ['db' => 'products_group_price_pack_unit', 'dbdef' => -2, 'post' => 'products_group_price_pack_unit', 'f' => ['self', 'defGroupPrice']], ['db' => 'products_group_price_packaging', 'dbdef' => -2, 'post' => 'products_group_price_packaging', 'f' => ['self', 'defGroupPrice']], ['db' => 'supplier_price_manual', 'dbdef' => 'null', 'post' => 'supplier_auto_price'], ['db' => 'shipping_surcharge_price', 'dbdef' => 0, 'post' => 'shipping_surcharge_price', 'flag' => 'shipping_surcharge'], ['db' => 'products_group_discount_price', 'dbdef' => '', 'postreindex' => 'discount_qty', 'post' => 'discount_price', 'flag' => 'qty_discount_status', 'f' => ['self', 'formatDiscountString']], ['db' => 'products_group_discount_price_pack_unit', 'dbdef' => '', 'postreindex' => 'discount_qty_pack_unit', 'post' => 'discount_price_pack_unit', 'flag' => 'qty_discount_status_pack_unit', 'f' => ['self', 'formatDiscountString']], ['db' => 'products_group_discount_price_packaging', 'dbdef' => '', 'postreindex' => 'discount_qty_packaging', 'post' => 'discount_price_packaging', 'flag' => 'qty_discount_status_packaging', 'f' => ['self', 'formatDiscountString']]];
                        //2do products_price_configurator
                        foreach ($fields as $field) {
                            $sql_data_array[$field['db']] = self::get_from_post_arrays($field, (int) $post_currencies_id, (int) $groups_id);
                        }
                        if ($groups_id == 0) {
                            // posted auto, make manual
                            $sql_data_array['supplier_price_manual'] = $sql_data_array['supplier_price_manual'] == '1' ? 0 : 1;
                            // reset matched with current config
                            if ($sql_data_array['supplier_price_manual'] == 1 && SUPPLIER_UPDATE_PRICE_MODE == 'Manual' || $sql_data_array['supplier_price_manual'] == 0 && SUPPLIER_UPDATE_PRICE_MODE == 'Auto') {
                                unset($sql_data_array['supplier_price_manual']);
                            }
                        } else {
                            unset($sql_data_array['supplier_price_manual']);
                        }
                        tep_db_perform(TABLE_PRODUCTS_PRICES, $sql_data_array);
                    }
                }
            }
            \common\helpers\Specials::save_from_post($products_id, 1);
            if ($ext = \common\helpers\Acl::check_extension_allowed('DeliveryOptions', 'allowed')) {
                $ext::save_product($products_id);
            }
        }
        if (true) {
            ////////////////////////////////////
            $_platform_list = \common\classes\platform::get_products_assign_list();
            $admin_available_platform_ids = \yii\helpers\Array_Helper::map($_platform_list, 'id', 'id');
            $all_platform_ids = \yii\helpers\Array_Helper::map(\common\models\Platforms::get_platforms_by_type('non-virtual')->select('platform_id')->as_array()->all(), 'platform_id', 'platform_id');
            $assign_platform = [];
            if (count($_platform_list) == 1) {
                $assign_platform[] = (int) $_platform_list[0]['id'];
            } else {
                $assign_platform = array_map('intval', Yii::$app->request->post('platform', []));
            }
            $db_assigned_platforms = \common\models\Platforms_Products::find()->where(['products_id' => (int) $products_id])->index_by('platform_id')->all();
            foreach (array_keys($db_assigned_platforms) as $_platform_id) {
                if (isset($all_platform_ids[$_platform_id]) && !isset($admin_available_platform_ids[$_platform_id])) {
                    unset($db_assigned_platforms[$_platform_id]);
                }
            }
            foreach ($assign_platform as $_platform_id) {
                if (isset($db_assigned_platforms[$_platform_id])) {
                    unset($db_assigned_platforms[$_platform_id]);
                } else {
                    $new_assign_to_platform = new \common\models\Platforms_Products(['products_id' => (int) $products_id, 'platform_id' => (int) $_platform_id]);
                    $new_assign_to_platform->load_default_values();
                    $new_assign_to_platform->save(false);
                }
            }
            foreach ($db_assigned_platforms as $not_updated_link_model) {
                $not_updated_link_model->delete();
            }
            $activate_parent_categories = Yii::$app->request->post('activate_parent_categories', []);
            $__assigned_platform_check = array_flip($assign_platform);
            foreach (\common\classes\platform::get_categories_assign_list() as $__category_platform) {
                if (!isset($activate_parent_categories[$__category_platform['id']]) || empty($activate_parent_categories[$__category_platform['id']])) {
                    continue;
                }
                if (!isset($__assigned_platform_check[$__category_platform['id']])) {
                    continue;
                }
                foreach (explode(',', $activate_parent_categories[$__category_platform['id']]) as $activate_category_id) {
                    do {
                        tep_db_query('REPLACE INTO ' . TABLE_PLATFORMS_CATEGORIES . " (categories_id, platform_id) VALUES('" . (int) $activate_category_id . "','" . (int) $__category_platform['id'] . "')");
                        $_move_upp = tep_db_fetch_array(tep_db_query('SELECT parent_id FROM ' . TABLE_CATEGORIES . " WHERE categories_id='" . (int) $activate_category_id . "' "));
                        $activate_category_id = is_array($_move_upp) ? (int) $_move_upp['parent_id'] : 0;
                    } while ($activate_category_id);
                }
            }
            if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroupsRestrictions', 'allowed')) {
                $ext::save_product((int) $products_id);
            }
        }
        if (defined('SUPERADMIN_ENABLED') && SUPERADMIN_ENABLED == true) {
            // {{ departments assign
            if (Yii::$app->request->post('department_assign_present', 0)) {
                $_departments_list = \common\classes\department::get_catalog_assign_list();
                $assign_departments = [];
                if (count($_departments_list) == 1) {
                    $assign_departments[] = (int) $_departments_list[0]['id'];
                } else {
                    $assign_departments = array_map('intval', Yii::$app->request->post('departments', []));
                }
                if (count($assign_departments) > 0) {
                    tep_db_query('DELETE FROM ' . TABLE_DEPARTMENTS_PRODUCTS . " WHERE products_id='" . (int) $products_id . "' AND departments_id NOT IN('" . implode("','", $assign_departments) . "') ");
                } else {
                    tep_db_query('DELETE FROM ' . TABLE_DEPARTMENTS_PRODUCTS . " WHERE products_id='" . (int) $products_id . "'");
                }
                foreach ($assign_departments as $assign_department_id) {
                    $_check = tep_db_fetch_array(tep_db_query('SELECT COUNT(*) AS c FROM ' . TABLE_DEPARTMENTS_PRODUCTS . " WHERE products_id='" . (int) $products_id . "' AND departments_id='" . $assign_department_id . "' "));
                    if ($_check['c'] == 0) {
                        tep_db_perform(TABLE_DEPARTMENTS_PRODUCTS, ['products_id' => (int) $products_id, 'departments_id' => $assign_department_id]);
                    }
                }
                $department_activate_parent_categories = Yii::$app->request->post('department_activate_parent_categories', []);
                $__assigned_department_check = array_flip($assign_departments);
                foreach ($_departments_list as $__category_department_info) {
                    if (!isset($department_activate_parent_categories[$__category_department_info['id']]) || empty($department_activate_parent_categories[$__category_department_info['id']])) {
                        continue;
                    }
                    if (!isset($__assigned_department_check[$__category_department_info['id']])) {
                        continue;
                    }
                    foreach (explode(',', $department_activate_parent_categories[$__category_department_info['id']]) as $activate_category_id) {
                        do {
                            tep_db_query('REPLACE INTO ' . TABLE_DEPARTMENTS_CATEGORIES . " (categories_id, departments_id) VALUES('" . (int) $activate_category_id . "','" . (int) $__category_department_info['id'] . "')");
                            $_move_upp = tep_db_fetch_array(tep_db_query('SELECT parent_id FROM ' . TABLE_CATEGORIES . " WHERE categories_id='" . (int) $activate_category_id . "' "));
                            $activate_category_id = is_array($_move_upp) ? (int) $_move_upp['parent_id'] : 0;
                        } while ($activate_category_id);
                    }
                }
            } else {
                // only one active, or ACL hide tab ????
                $_departments_list = \common\classes\department::get_catalog_assign_list();
                if (count($_departments_list) == 1) {
                    tep_db_query('INSERT IGNORE INTO ' . TABLE_DEPARTMENTS_PRODUCTS . " (products_id, departments_id) VALUES('" . (int) $products_id . "','" . (int) $_departments_list[0]['id'] . "')");
                }
            }
            // }} departments assign
        }
        /*if  ($TabAccess->tabDataSave('TEXT_PRICE_COST_W') && $TabAccess->allowSuppliersData()) {
              $suppliers_id = Yii::$app->request->post('suppliers_id', array());
              $suppliers_model = Yii::$app->request->post('suppliers_model', array());
              $suppliers_quantity = Yii::$app->request->post('suppliers_quantity', array());
              $suppliers_price = Yii::$app->request->post('suppliers_price', array());
              $supplier_discount = Yii::$app->request->post('supplier_discount', array());
              $suppliers_surcharge_amount = Yii::$app->request->post('suppliers_surcharge_amount', array());
              $suppliers_margin_percentage = Yii::$app->request->post('suppliers_margin_percentage', array());
              $suppliers_data_query = tep_db_query("select * from " . TABLE_SUPPLIERS . " order by suppliers_id");
              while ($suppliers_data = tep_db_fetch_array($suppliers_data_query)) {
                  if ($suppliers_id[$suppliers_data['suppliers_id']]) {
                      $sql_data_array = [];
                      $sql_data_array['source'] = $suppliers_model[$suppliers_data['source']];
                      $sql_data_array['suppliers_model'] = $suppliers_model[$suppliers_data['suppliers_id']];
                      $sql_data_array['suppliers_price'] = $suppliers_price[$suppliers_data['suppliers_id']];
                      $sql_data_array['suppliers_quantity'] = $suppliers_quantity[$suppliers_data['suppliers_id']];
                      $sql_data_array['supplier_discount'] = $supplier_discount[$suppliers_data['suppliers_id']];
                      $sql_data_array['suppliers_surcharge_amount'] = $suppliers_surcharge_amount[$suppliers_data['suppliers_id']];
                      $sql_data_array['suppliers_margin_percentage'] = $suppliers_margin_percentage[$suppliers_data['suppliers_id']];
                      $check = tep_db_fetch_array(tep_db_query("select count(*) as suppliers_product_exists from " . TABLE_SUPPLIERS_PRODUCTS . " where products_id = '" . (int) $products_id . "' and uprid = '" . (int) $products_id . "' and suppliers_id = '" . (int) $suppliers_data['suppliers_id'] . "'"));
                      if ($check['suppliers_product_exists']) {
                          $sql_data_array['last_modified'] = 'now()';
                          tep_db_perform(TABLE_SUPPLIERS_PRODUCTS, $sql_data_array, 'update', "products_id = '" . (int) $products_id . "' and uprid = '" . (int) $products_id . "' and suppliers_id = '" . (int) $suppliers_data['suppliers_id'] . "'");
                      } else {
                          $sql_data_array['date_added'] = 'now()';
                          $sql_data_array['products_id'] = $products_id;
                          $sql_data_array['uprid'] = $products_id;
                          $sql_data_array['suppliers_id'] = $suppliers_data['suppliers_id'];
                          tep_db_perform(TABLE_SUPPLIERS_PRODUCTS, $sql_data_array);
                      }
                  } else {
                      tep_db_query("delete from " . TABLE_SUPPLIERS_PRODUCTS . " where products_id = '" . (int) $products_id . "' and uprid = '" . (int) $products_id . "' and suppliers_id = '" . (int) $suppliers_data['suppliers_id'] . "'");
                  }
              }
          }*/
        /**
         * Split by languages
         */
        $languages = \common\helpers\Language::get_languages();
        if ($tab_access->tab_data_save('TEXT_NAME_DESCRIPTION') || $tab_access->tab_data_save('TEXT_SEO')) {
            $description_save = new \backend\models\Product_Edit\Save_Description($product_model, $selected_department_id);
            $description_save->save();
        }
        /**
         * Attributes and inventory (variations)
         */
        $all_inventory_uprids_array = [];
        if ($tab_access->tab_data_save('TEXT_ATTR_INVENTORY')) {
            $attributes_and_inventory_save = new \backend\models\Product_Edit\Save_Attributes_And_Inventory($product_model);
            $all_inventory_uprids_array = $attributes_and_inventory_save->save();
        }
        ////////////////////////////////
        //suppliers
        if ($tab_access->tab_data_save('TEXT_PRICE_COST_W') && $tab_access->allow_suppliers_data()) {
            $suppliers_data = Yii::$app->request->post('suppliers_data', []);
            $suppliers_discount = Yii::$app->request->post('suppliers_discount', []);
            foreach (\common\models\Suppliers_Products::find()->where(['products_id' => (int) $products_id])->and_where(['NOT IN', 'uprid', array_map('strval', array_unique(array_merge(array_keys($suppliers_data), $all_inventory_uprids_array)))])->all() as $sp_record) {
                $sp_record->delete();
            }
            unset($sp_record);
            if (!\common\helpers\Attributes::has_product_attributes($products_id) || $product_model->without_inventory) {
                \common\helpers\Suppliers::remove_uprids($products_id);
                $s_products = \yii\helpers\Array_Helper::index(Suppliers_Products::get_supplier_products($products_id)->all(), 'suppliers_id');
                if (is_array($suppliers_data) && count($suppliers_data)) {
                    foreach ($suppliers_data as $supplier_uprid => $unused) {
                        break;
                    }
                    if (isset($suppliers_data[0]) && !isset($suppliers_data[$products_id])) {
                        $suppliers_data[$products_id] = $suppliers_data[0];
                        unset($suppliers_data[0]);
                    } elseif (strpos($supplier_uprid, '{') !== false && \common\helpers\Inventory::get_prid($supplier_uprid) == $products_id) {
                        $suppliers_data[$products_id] = $suppliers_data[$supplier_uprid];
                        unset($suppliers_data[$supplier_uprid]);
                    }
                    $sort_order = \Yii::$app->request->post('suppliers-default-sort', 0) ? null : 0;
                    foreach ($suppliers_data[$products_id] as $suppliers_id => $data) {
                        if (isset($s_products[$suppliers_id])) {
                            $s_product = $s_products[$suppliers_id];
                            unset($s_products[$suppliers_id]);
                        } else {
                            $s_product = new Suppliers_Products();
                            $s_product->load_default_values();
                            $data['suppliers_id'] = $suppliers_id;
                            $data['products_id'] = $products_id;
                        }
                        $data['suppliers_price_discount'] = \common\helpers\Suppliers::get_discount_values_table($suppliers_discount[$products_id][$suppliers_id] ?? null);
                        $s_product->load($data, null);
                        $s_product->sort_order = is_null($sort_order) ? null : $sort_order++;
                        $s_product->save_supplier_product($data);
                        if ($ext = \common\helpers\Acl::check_extension_allowed('SupplierPurchase', 'allowed')) {
                            if (method_exists($ext, 'checkWSProductStock')) {
                                $ext::check_ws_product_stock($products_id, \common\helpers\Warehouses::get_default_warehouse(), $suppliers_id);
                            }
                        }
                    }
                    foreach ($s_products as $s_product) {
                        $s_product->delete();
                    }
                } else {
                    foreach ($s_products as $s_product) {
                        $s_product->delete();
                    }
                    (new Suppliers_Products())->save_default_supplier_product(['products_id' => $products_id]);
                }
            } else if (\common\helpers\Extensions::is_allowed('Inventory')) {
                //{{delete single sup_prid
                foreach (Suppliers_Products::get_supplier_products($products_id)->all() as $s_p) {
                    $s_p->delete();
                }
                //}}end
                $inventories = \common\models\Inventory::find_all(['prid' => $products_id]);
                if ($inventories) {
                    foreach ($inventories as $inventory) {
                        if (!isset($suppliers_data[$inventory->products_id])) {
                            //new product, prid undefined
                            $reg = preg_replace('/^' . $inventory->prid . "\\{/", '0{', $inventory->products_id);
                            if (isset($suppliers_data[$reg])) {
                                $suppliers_data[$inventory->products_id] = $suppliers_data[$reg];
                            }
                        }
                        if (isset($suppliers_data[$inventory->products_id])) {
                            $s_products = \yii\helpers\Array_Helper::index(Suppliers_Products::get_supplier_uprid_products($inventory->products_id)->all(), 'suppliers_id');
                            foreach ($suppliers_data[$inventory->products_id] as $suppliers_id => $data) {
                                $s_product = null;
                                if (isset($s_products[$suppliers_id])) {
                                    $s_product = $s_products[$suppliers_id];
                                    unset($s_products[$suppliers_id]);
                                }
                                if (!$s_product) {
                                    $s_product = new Suppliers_Products();
                                    $s_product->load_default_values();
                                    $data['suppliers_id'] = $suppliers_id;
                                    $data['products_id'] = $products_id;
                                    $data['uprid'] = $inventory->products_id;
                                }
                                $data['suppliers_price_discount'] = \common\helpers\Suppliers::get_discount_values_table($suppliers_discount[$inventory->products_id][$suppliers_id] ?? null);
                                $s_product->load($data, null);
                                $s_product->save_supplier_product($data);
                                /** @var \common\extensions\SupplierPurchase\SupplierPurchase $ext */
                                if ($ext = \common\helpers\Extensions::is_allowed('SupplierPurchase')) {
                                    if (method_exists($ext, 'checkWSProductStock')) {
                                        $ext::check_ws_product_stock($inventory->products_id, \common\helpers\Warehouses::get_default_warehouse(), $suppliers_id);
                                    }
                                }
                            }
                            foreach ($s_products as $s_product) {
                                $s_product->delete();
                            }
                        } else {
                            if (Yii::$app->request->post('products_id') == 0) {
                                $post_uprid = preg_replace('/^' . $inventory->prid . "\\{/", '0{', $inventory->products_id);
                            } else {
                                $post_uprid = $inventory->products_id;
                            }
                            if (Yii::$app->request->post('inventoryexistent_' . $post_uprid) === null && Yii::$app->request->post('inventorymodel_' . $post_uprid) === null) {
                                // If inventory data is not set - skip it
                                continue;
                            }
                            foreach (Suppliers_Products::get_supplier_uprid_products($inventory->products_id)->all() as $s_product) {
                                $s_product->delete();
                            }
                            (new Suppliers_Products())->save_default_supplier_product(['products_id' => $products_id, 'uprid' => $inventory->products_id]);
                        }
                    }
                }
            }
            if (!empty(\common\helpers\Price_Formula::get_product_model_for_auto_update($products_id))) {
                \common\helpers\Price_Formula::apply_db($products_id);
            }
        }
        /**
         * Images
         */
        if ($tab_access->tab_data_save('TAB_IMAGES')) {
            $product_images = new \backend\models\Product_Edit\Save_Product_Images($product_model, $path);
            $product_images->save();
        }
        /**
         * Properties
         */
        if ($tab_access->tab_data_save('TAB_PROPERTIES')) {
            $product_properties = new \backend\models\Product_Edit\Save_Product_Properties($product_model);
            $product_properties->save();
        }
        /**
         * Videos
         */
        if ($tab_access->tab_data_save('TEXT_VIDEO')) {
            $product_videos = new \backend\models\Product_Edit\Save_Product_Videos($product_model, $path);
            $product_videos->save();
        }
        if ($tab_access->tab_view('TAB_IMPORT_EXPORT')) {
            $import_export_data = new \backend\models\Product_Edit\Save_Import_Export($product_model);
            $import_export_data->save();
        }
        foreach (\common\helpers\Hooks::get_list('categories/productedit-beforesave') as $filename) {
            include $filename;
        }
        $product_model->save(false);
        foreach (\common\helpers\Hooks::get_list('categories/productedit') as $filename) {
            include $filename;
        }
        if ($tab_access->tab_data_save('TEXT_PRODUCT_SOAP_CONFIG') && class_exists('\backend\models\EP\Datasource\HolbiSoap')) {
            \backend\models\EP\Datasource\Holbi_Soap::product_update($products_id, Yii::$app->request->post('soap_config', []));
        }
        $product_model->save(false);
        \common\helpers\Product::fill_global_sort(0, $products_id);
        \common\helpers\Sub_Product::after_product_save($product_model);
        \common\components\Popularity::calculate_popularity($product_model->products_id);
        \common\components\Categories_Cache::get_cpc()::invalidate_products((int) $products_id);
        $message = TEXT_PRODUCT_UPDATED_NOTICE;
        $message_type = 'success';
        ?>
        <div class="popup-box-wrap pop-mess">
            <div class="around-pop-up"></div>
            <div class="popup-box">
                <div class="pop-up-close pop-up-close-alert"></div>
                <div class="pop-up-content">
                    <div class="popup-heading"><?php 
        echo TEXT_NOTIFIC;
        ?></div>
                    <div class="popup-content pop-mess-cont pop-mess-cont-<?php 
        echo $message_type;
        ?>">
        <?php 
        echo $message;
        ?>
                    </div>
                </div>
                <div class="noti-btn">
                    <div></div>
                    <div><span class="btn btn-primary"><?php 
        echo TEXT_BTN_OK;
        ?></span></div>
                </div>
            </div>
            <script>
                $('body').scrollTop(0);
                $('.pop-mess .pop-up-close-alert, .noti-btn .btn').click(function () {
                    $(this).parents('.pop-mess').remove();
                });
            </script>
        </div>


        <?php 
        if ($is_new_product) {
            echo '<script> var url= "' . Yii::$app->url_manager->create_url(['categories/productedit', 'pID' => $products_id]) . '"+window.location.hash; window.location.href=url;</script>';
        } else {
            echo '<script>window.location.reload();</script>';
        }
        \common\helpers\Product::do_allocate_automatic($products_id, true);
        //return $this->redirect(Yii::$app->urlManager->createUrl(['categories/productedit', 'pID' => $products_id]));
        //        if ($action == 'update_product') {
        if (isset($logger) && \common\helpers\Acl::check_extension_allowed('ReportChangesHistory')) {
            $after_object = new \common\api\Classes\Product();
            $after_object->load($products_id);
            $logger->set_after_object($after_object);
            unset($after_object);
            $logger->run();
        }
    }
    public function action_product_search()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $q = Yii::$app->request->get('q');
        $products_id = (int) Yii::$app->request->get('not');
        $bundle_skip = (int) Yii::$app->request->get('bundle_skip');
        $linked_skip = (int) Yii::$app->request->get('linked_skip', 0);
        $with_images = (int) Yii::$app->request->get('with_images', 0);
        $bycustomer = (int) Yii::$app->request->get('bycustomer', 0);
        $child_skip = (int) Yii::$app->request->get('child_skip', 0);
        $products_string = '';
        if ($this->default_collapsed) {
            $p_q = (new \yii\db\Query())->select('p.products_id, p.products_status ')->add_select(['products_name' => new Expression(Product_Name_Decorator::instance()->listing_query_expression('pd', ''))])->from(['p' => TABLE_PRODUCTS])->left_join(TABLE_PRODUCTS_DESCRIPTION . ' pd', 'p.products_id = pd.products_id and pd.language_id =:lid and pd.platform_id = :pid', [':lid' => (int) $languages_id, ':pid' => intval(\common\classes\platform::default_id())])->and_where('p.products_id != :prid', [':prid' => (int) $products_id])->distinct()->order_by('p.sort_order ')->add_order_by(new Expression(Product_Name_Decorator::instance()->listing_query_expression('pd', '')))->limit(500);
            if (!empty($q)) {
                $p_q->and_where(['or', ['like', 'p.products_model', tep_db_input($q)], ['like', 'pd.products_name', tep_db_input($q)], ['like', 'pd.products_internal_name', tep_db_input($q)]]);
            }
            $filter_by_platform = \common\helpers\Admin::limited_platform_list();
            if (is_array($filter_by_platform) && count($filter_by_platform) > 0) {
                $p_q->and_where(['EXISTS', (new \yii\db\Query())->from(\common\models\Platforms_Products::table_name() . ' p2pl')->and_where('p2pl.products_id=p.products_id')->and_where(['IN', 'p2pl.platform_id', $filter_by_platform])])->distinct();
            }
            if ($bundle_skip > 0) {
                $p_q->and_where(' p.is_bundle = 0');
            }
            if ($linked_skip > 0) {
                $p_q->left_join('products_linked_parent lp', 'lp.product_id=p.products_id ')->and_where(' lp.product_id IS NULL');
            }
            if ($child_skip > 0) {
                $p_q->and_where(' p.parent_products_id = 0');
            }
            if ($bycustomer > 0) {
                /** @var \common\extensions\CustomerProducts\CustomerProducts $ext  */
                if ($ext = \common\helpers\Acl::check_extension('CustomerProducts', 'allowed')) {
                    if ($ext::allowed()) {
                        $p_q->and_where(['p.products_id' => \common\extensions\Customer_Products\models\Customer_Products::find()->where(['customer_id' => $bycustomer])->select('product_id')]);
                    }
                } else {
                    $bycustomer = 0;
                }
            }
            $products_all = $p_q->all();
            if (is_array($products_all) && !empty($products_all)) {
                foreach ($products_all as $products) {
                    if (empty($products['products_name'])) {
                        $products['products_name'] = \common\helpers\Product::get_products_name($products['products_id']);
                    }
                    $option_attributes = $products['products_status'] == 0 ? ' class="dis_prod"' : '';
                    if ($with_images) {
                        $option_attributes .= ' data-image-src="' . \common\classes\Images::get_image_url($products['products_id']) . '"';
                    }
                    if ($bycustomer > 0) {
                        $option_attributes .= ' selected ';
                    }
                    $products_string .= '<option value="' . $products['products_id'] . '" ' . $option_attributes . '>' . $products['products_name'] . '</option>';
                }
            }
        } else {
            $categories = \common\helpers\Categories::get_category_tree(0, '', '0', '', true);
            $categories_idx = \yii\helpers\Array_Helper::index($categories, 'id');
            $p_q = (new \yii\db\Query())->select('p.products_id, p.products_status ')->add_select(['products_name' => new Expression(Product_Name_Decorator::instance()->listing_query_expression('pd', ''))])->from(['p' => TABLE_PRODUCTS])->join('left join', \common\models\Products2Categories::table_name() . ' p2c', 'p2c.products_id=p.products_id')->join('left join', \common\models\Categories::table_name() . ' c', 'c.categories_id=p2c.categories_id')->add_select(['p2c.categories_id'])->left_join(TABLE_PRODUCTS_DESCRIPTION . ' pd', 'p.products_id = pd.products_id and pd.language_id =:lid and pd.platform_id = :pid', [':lid' => (int) $languages_id, ':pid' => intval(\common\classes\platform::default_id())])->and_where('p.products_id != :prid', [':prid' => (int) $products_id])->distinct()->order_by([new \yii\db\Expression('IFNULL(c.categories_left,10000000)'), 'p2c.sort_order' => SORT_ASC])->add_order_by(new Expression(Product_Name_Decorator::instance()->listing_query_expression('pd', '')))->limit(500);
            if (!empty($q)) {
                $p_q->and_where(['or', ['like', 'p.products_model', tep_db_input($q)], ['like', 'pd.products_name', tep_db_input($q)], ['like', 'pd.products_internal_name', tep_db_input($q)]]);
            } else {
                //$pQ->andWhere('/*empty search term*/1=0');
            }
            if ($bundle_skip > 0) {
                $p_q->and_where(' p.is_bundle = 0');
            }
            $filter_by_platform = \common\helpers\Admin::limited_platform_list();
            if (is_array($filter_by_platform) && count($filter_by_platform) > 0) {
                $p_q->and_where(['EXISTS', (new \yii\db\Query())->from(\common\models\Platforms_Products::table_name() . ' p2pl')->and_where('p2pl.products_id=p.products_id')->and_where(['IN', 'p2pl.platform_id', $filter_by_platform])])->and_where(['EXISTS', (new \yii\db\Query())->from(\common\models\Platforms_Categories::table_name() . ' c2pl')->and_where('c2pl.categories_id=c.categories_id')->and_where(['IN', 'c2pl.platform_id', $filter_by_platform])])->distinct();
            }
            if ($linked_skip > 0) {
                $p_q->left_join('products_linked_parent lp', 'lp.product_id=p.products_id ')->and_where(' lp.product_id IS NULL');
            }
            if ($child_skip > 0) {
                $p_q->and_where(' p.parent_products_id = 0');
            }
            if ($bycustomer > 0) {
                /** @var \common\extensions\CustomerProducts\CustomerProducts $ext  */
                if ($ext = \common\helpers\Acl::check_extension('CustomerProducts', 'allowed')) {
                    if ($ext::allowed()) {
                        $p_q->and_where(['p.products_id' => \common\extensions\Customer_Products\models\Customer_Products::find()->where(['customer_id' => $bycustomer])->select('product_id')]);
                    }
                } else {
                    $bycustomer = 0;
                }
            }
            $products_all = $p_q->all();
            if (is_array($products_all) && !empty($products_all)) {
                $group_cat_id = -1;
                foreach ($products_all as $_idx => $products) {
                    if (empty($products['products_name'])) {
                        $products['products_name'] = \common\helpers\Product::get_products_name($products['products_id']);
                    }
                    if ((int) $products['categories_id'] != $group_cat_id) {
                        $group_cat_id = (int) $products['categories_id'];
                        $products_string .= '<optgroup label="' . ($categories_idx[$group_cat_id]['text'] ?? null) . '">';
                    }
                    $option_attributes = $products['products_status'] == 0 ? ' class="dis_prod"' : '';
                    if ($with_images) {
                        $option_attributes .= ' data-image-src="' . \common\classes\Images::get_image_url($products['products_id']) . '"';
                    }
                    if ($bycustomer > 0) {
                        $option_attributes .= ' selected ';
                    }
                    $products_string .= '<option value="' . $products['products_id'] . '" ' . $option_attributes . '>' . $products['products_name'] . '</option>';
                    if ($_idx + 1 >= count($products_all) || (int) $products_all[$_idx + 1]['categories_id'] != $group_cat_id) {
                        $products_string .= '</optgroup>';
                    }
                }
            }
        }
        echo $products_string;
    }
    private static function get_products_details($products_id)
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        if ($products_id > 0) {
            //probably random platform
            $query = tep_db_query('select p.products_id, p.products_quantity, p.products_model, p.products_status_bundle, ' . Product_Name_Decorator::instance()->listing_query_expression('pd', '') . ' AS products_name, p.products_status from ' . TABLE_PRODUCTS_DESCRIPTION . ' pd,' . TABLE_PRODUCTS . " p where language_id = '" . $languages_id . "' and platform_id = '" . intval(\common\classes\platform::default_id()) . "' and  p.products_id = '" . $products_id . "' and pd.products_id = '" . $products_id . "' limit 1");
            if (tep_db_num_rows($query) > 0) {
                $ret = tep_db_fetch_array($query);
                if (empty($ret['products_name'])) {
                    $ret['products_name'] = \common\helpers\Product::get_products_name($ret['products_id']);
                }
            } else {
                $ret = [];
            }
        } else {
            $ret = [];
        }
        return $ret;
    }
    public function action_product_new_bundles()
    {
        $currencies = \Yii::$container->get('currencies');
        $this->layout = false;
        $products_id = (int) Yii::$app->request->post('products_id');
        $data = self::get_products_details($products_id);
        if (count($data) > 0) {
            $bundles_products = ['bundles_id' => $data['products_id'], 'products_name' => $data['products_name'], 'num_product' => '1', 'price' => '0.00', 'discount' => '', 'image' => \common\classes\Images::get_image($data['products_id'], 'Small'), 'status_class' => $data['products_status'] == 0 ? 'dis_prod' : '', 'products_status_bundle' => (int) $data['products_status_bundle'], 'products_quantity' => (int) $data['products_quantity'], 'products_model' => $data['products_model'], 'products_qty' => \common\helpers\Product::get_products_stock($data['products_id']), 'products_price' => $currencies->format(\common\helpers\Product::get_products_price($data['products_id']))];
            return $this->render('product-new-bundles.tpl', ['bundles' => $bundles_products]);
        }
    }
    //    public function actionProductNewXsell() {
    //
    //        $this->layout = false;
    //
    //        $currencies = Yii::$container->get('currencies');
    //
    //        $products_id = (int) Yii::$app->request->post('products_id');
    //        $xsell_type_id = (int) Yii::$app->request->post('xsell_type');
    //        $data = self::getProductsDetails($products_id);
    //
    //        if (count($data) > 0) {
    //            $backlink = 0;
    //            if ($parent_products_id = (int) Yii::$app->request->post('parent_products_id')) {
    //                $backlink = \common\models\ProductsXsell::find()
    //                    ->where(['xsell_type_id' => $xsell_type_id, 'xsell_id' => $parent_products_id, 'products_id' => $data['products_id']])
    //                    ->select(['xsell_id'])->scalar();
    //            }
    //
    //            $xsellProduct = [
    //                'xsell_id' => $data['products_id'],
    //                'xsell_type_id' => $xsell_type_id,
    //                'products_name' => $data['products_name'],
    //                'image' => \common\classes\Images::getImage($data['products_id'], 'Small'),
    //                'price' => $currencies->format(\common\helpers\Product::get_products_price($data['products_id'])),
    //                'status_class' => ($data['products_status'] == 0 ? 'dis_prod' : ''),
    //                'backlink' => $backlink,
    //            ];
    //
    //            return $this->render('product-new-xsell.tpl', [
    //                'xsell_type_id' => $xsell_type_id,
    //                'xsell' => $xsellProduct,
    //            ]);
    //        }
    //    }
    //    public function actionProductNewUpsell() {
    //
    //        $this->layout = false;
    //
    //        $currencies = Yii::$container->get('currencies');
    //
    //        $products_id = (int) Yii::$app->request->post('products_id');
    //
    //        $data = self::getProductsDetails($products_id);
    //
    //        if (count($data) > 0) {
    //            $upsellProduct = [
    //                'upsell_id' => $data['products_id'],
    //                'products_name' => $data['products_name'],
    //                'image' => \common\classes\Images::getImage($data['products_id'], 'Small'),
    //                'price' => $currencies->format(\common\helpers\Product::get_products_price($data['products_id'])),
    //                'status_class' => ($data['products_status'] == 0 ? 'dis_prod' : ''),
    //            ];
    //
    //            return $this->render('product-new-upsell.tpl', [
    //                        'upsell' => $upsellProduct,
    //            ]);
    //        }
    //    }
    public function action_product_image_generator()
    {
        // product-image-generator
        $Images = new \common\classes\Images();
        $path = DIR_FS_DOCUMENT_ROOT . DIR_WS_CATALOG_IMAGES;
        //$languages = \common\helpers\Language::get_languages();
        //TRUNCATE TABLE `products_images`
        //TRUNCATE TABLE `products_images_description`
        $check_product_query = tep_db_query('SELECT products_id, products_image, products_image_lrg, products_image_xl_1, products_image_xl_2, products_image_xl_3, products_image_xl_4, products_image_xl_5, products_image_xl_6, products_seo_page_name FROM ' . TABLE_PRODUCTS . ' WHERE 1');
        if (tep_db_num_rows($check_product_query) > 0) {
            while ($product = tep_db_fetch_array($check_product_query)) {
                $orig_file = $product['products_image_lrg'];
                $check = tep_db_fetch_array(tep_db_query('select pi.products_images_id from ' . TABLE_PRODUCTS_IMAGES . ' pi, ' . TABLE_PRODUCTS_IMAGES_DESCRIPTION . " pid where pi.products_id = '" . (int) $product['products_id'] . "' and pi.products_images_id = pid.products_images_id and pid.language_id = '0' and pid.orig_file_name like '%" . tep_db_input($orig_file) . "'"));
                $tmp_name = $path . $orig_file;
                if (!empty($orig_file) && file_exists($tmp_name) && !($check['products_images_id'] > 0)) {
                    $image_location = DIR_FS_DOCUMENT_ROOT . DIR_WS_CATALOG_IMAGES . 'products' . DIRECTORY_SEPARATOR . $product['products_id'] . DIRECTORY_SEPARATOR;
                    if (!file_exists($image_location)) {
                        mkdir($image_location, 0777, true);
                    }
                    $sql_data_array = [];
                    $sql_data_array['default_image'] = 1;
                    $sql_data_array['image_status'] = 1;
                    $sql_data_array['products_id'] = (int) $product['products_id'];
                    tep_db_perform(TABLE_PRODUCTS_IMAGES, $sql_data_array);
                    $image_id = tep_db_insert_id();
                    $image_location .= $image_id . DIRECTORY_SEPARATOR;
                    if (!file_exists($image_location)) {
                        mkdir($image_location, 0777, true);
                    }
                    $sql_data_array = [];
                    $sql_data_array['language_id'] = 0;
                    $file_name = $product['products_seo_page_name'] ? $product['products_seo_page_name'] : Seo::make_slug(\common\helpers\Product::get_products_name($product['products_id']));
                    $upload_extension = strtolower(pathinfo($tmp_name, PATHINFO_EXTENSION));
                    $file_name .= '.' . $upload_extension;
                    $sql_data_array['file_name'] = $file_name;
                    $hash_name = md5($orig_file . '_' . date('dmYHis') . '_' . microtime(true));
                    $new_name = $image_location . $hash_name;
                    copy($tmp_name, $new_name);
                    $sql_data_array['hash_file_name'] = $hash_name;
                    $sql_data_array['orig_file_name'] = $orig_file;
                    $sql_data_array['image_title'] = '';
                    $sql_data_array['image_alt'] = '';
                    $lang = '';
                    $Images->create_images($product['products_id'], $image_id, $hash_name, $file_name, $lang);
                    //$orig_file
                    $sql_data_array['products_images_id'] = (int) $image_id;
                    $sql_data_array['language_id'] = (int) $language_id;
                    tep_db_perform(TABLE_PRODUCTS_IMAGES_DESCRIPTION, $sql_data_array);
                    /* for( $i = 0, $n = sizeof( $languages ); $i < $n; $i++ ) {
                    
                    
                    
                                          } */
                }
                for ($im = 1; $im <= 6; $im++) {
                    $orig_file = $product['products_image_xl_' . $im];
                    $check = tep_db_fetch_array(tep_db_query('select pi.products_images_id from ' . TABLE_PRODUCTS_IMAGES . ' pi, ' . TABLE_PRODUCTS_IMAGES_DESCRIPTION . " pid where pi.products_id = '" . (int) $product['products_id'] . "' and pi.products_images_id = pid.products_images_id and pid.language_id = '0' and pid.orig_file_name like '%" . tep_db_input($orig_file) . "'"));
                    $tmp_name = $path . $orig_file;
                    if (!empty($orig_file) && file_exists($tmp_name) && !($check['products_images_id'] > 0)) {
                        $image_location = DIR_FS_DOCUMENT_ROOT . DIR_WS_CATALOG_IMAGES . 'products' . DIRECTORY_SEPARATOR . $product['products_id'] . DIRECTORY_SEPARATOR;
                        if (!file_exists($image_location)) {
                            mkdir($image_location, 0777, true);
                        }
                        $sql_data_array = [];
                        $sql_data_array['default_image'] = 0;
                        $sql_data_array['image_status'] = 1;
                        $sql_data_array['sort_order'] = $im;
                        $sql_data_array['products_id'] = (int) $product['products_id'];
                        tep_db_perform(TABLE_PRODUCTS_IMAGES, $sql_data_array);
                        $image_id = tep_db_insert_id();
                        $image_location .= $image_id . DIRECTORY_SEPARATOR;
                        if (!file_exists($image_location)) {
                            mkdir($image_location, 0777, true);
                        }
                        $sql_data_array = [];
                        $sql_data_array['language_id'] = 0;
                        $file_name = $product['products_seo_page_name'] ? $product['products_seo_page_name'] : Seo::make_slug(\common\helpers\Product::get_products_name($product['products_id']));
                        $upload_extension = strtolower(pathinfo($tmp_name, PATHINFO_EXTENSION));
                        $file_name .= '.' . $upload_extension;
                        $sql_data_array['file_name'] = $file_name;
                        $hash_name = md5($orig_file . '_' . date('dmYHis') . '_' . microtime(true));
                        $new_name = $image_location . $hash_name;
                        copy($tmp_name, $new_name);
                        $sql_data_array['hash_file_name'] = $hash_name;
                        $sql_data_array['orig_file_name'] = $orig_file;
                        $sql_data_array['image_title'] = '';
                        $sql_data_array['image_alt'] = '';
                        $lang = '';
                        $Images->create_images($product['products_id'], $image_id, $hash_name, $file_name, $lang);
                        //$orig_file
                        $sql_data_array['products_images_id'] = (int) $image_id;
                        $sql_data_array['language_id'] = (int) $language_id;
                        tep_db_perform(TABLE_PRODUCTS_IMAGES_DESCRIPTION, $sql_data_array);
                        /* for( $i = 0, $n = sizeof( $languages ); $i < $n; $i++ ) {
                        
                        
                        
                                                  } */
                    }
                }
            }
        }
    }
    public function action_categoryedit()
    {
        if (false === \common\helpers\Acl::rule(['TEXT_CATEGORIES', 'IMAGE_EDIT'])) {
            $this->redirect(\yii\helpers\Url::to_route('categories/'));
        }
        $affiliate_id = \Yii::$app->settings->get('affiliate_id');
        $languages_id = \Yii::$app->settings->get('languages_id');
        $this->top_buttons[] = '<span class="btn btn-confirm" onclick="return saveCategory()">' . IMAGE_SAVE . '</span>';
        $this->view->use_popup_mode = false;
        if (Yii::$app->request->is_ajax) {
            $this->layout = false;
            $this->view->use_popup_mode = true;
        }
        \common\helpers\Translation::init('admin/categories');
        $popup = 0;
        if (Yii::$app->request->is_post) {
            $categories_id = (int) Yii::$app->request->get_body_param('categories_id');
            $popup = (int) Yii::$app->request->post('popup');
            if ($popup == 0) {
                $this->view->use_popup_mode = false;
            }
        } else {
            $categories_id = (int) Yii::$app->request->get('categories_id');
        }
        $this->view->content_already_loaded = $popup;
        $category = [];
        if ($categories_id > 0) {
            $categories_query = tep_db_query('select c.*, cd.categories_name, cd.categories_heading_title, cd.categories_description, cd.categories_head_title_tag, cd.categories_head_desc_tag, cd.categories_head_keywords_tag, cd.categories_h1_tag, cd.categories_h2_tag, cd.categories_h3_tag, cd.categories_image_alt_tag_mask, cd.categories_image_title_tag_mask, c.categories_image,  c.categories_image_2, categories_image_3, categories_image_4, c.show_on_home, c.parent_id, c.categories_seo_page_name, c.sort_order, c.date_added, c.last_modified, c.categories_status, c.categories_old_seo_page_name, c.maps_id, c.banners_group from ' . TABLE_CATEGORIES . ' c, ' . TABLE_CATEGORIES_DESCRIPTION . " cd where c.categories_id = '" . $categories_id . "' and c.categories_id = cd.categories_id and cd.language_id = '" . $languages_id . "' and cd.affiliate_id = 0 order by c.sort_order, cd.categories_name");
            $category = tep_db_fetch_array($categories_query);
        } else {
            $category['parent_id'] = (int) Yii::$app->request->get('category_id', 0);
            $category['manual_control_status'] = 1;
            $category['maps_id'] = 0;
            $category['categories_image'] = '';
            $category['categories_image_2'] = '';
            $category['categories_image_3'] = '';
            $category['categories_image_4'] = '';
            $category['categories_name'] = '';
        }
        $c_info = new \Object_Info($category);
        if (!isset($c_info->default_sort_order)) {
            $c_info->default_sort_order = 0;
        }
        $map_image = null;
        $map_title = '';
        /**
         * @var $imageMaps \common\extensions\ImageMaps\models\ImageMaps
         */
        if ($image_maps = \common\helpers\Extensions::get_model('ImageMaps', 'ImageMaps')) {
            if (!isset($c_info->maps_id)) {
                $c_info->maps_id = 0;
            }
            if ($c_info->maps_id > 0 && !empty($image_maps)) {
                $map = $image_maps::find_one((int) $c_info->maps_id);
                if ($map) {
                    $map_image = $map->image;
                    $map_title = $map->get_title($languages_id);
                }
            }
        }
        $p_settings = \common\models\Categories_Platform_Settings::find()->and_where(['categories_id' => $categories_id])->index_by('platform_id')->as_array()->all();
        if (!$p_settings) {
            $p_settings = [];
        }
        $p_settings[0] = ['platform_id' => 0, 'categories_image' => isset($c_info->categories_image) ? $c_info->categories_image : '', 'categories_image_2' => isset($c_info->categories_image_2) ? $c_info->categories_image_2 : '', 'categories_image_3' => isset($c_info->categories_image_3) ? $c_info->categories_image_3 : '', 'categories_image_4' => isset($c_info->categories_image_4) ? $c_info->categories_image_4 : '', 'show_on_home' => isset($c_info->show_on_home) ? $c_info->show_on_home : 0, 'maps_id' => $c_info->maps_id, 'imageMap' => ['image' => $map_image], 'imageMapTitle' => ['title' => $map_title]];
        $tmp = [];
        foreach (array_merge([['id' => 0, 'text' => TEXT_MAIN]], \common\classes\platform::get_list(false)) as $__platform) {
            $__platform['title'] = $__platform['text'];
            if (isset($p_settings[$__platform['id']])) {
                $__platform['cssClass'] = ' changed';
            }
            $__platform['def_data'] = ['platform_id' => $__platform['id']];
            unset($__platform['need_login']);
            $tmp[] = $__platform;
        }
        $this->view->platformsettings_tabs[] = $tmp;
        $this->view->platformsettings_tabs_data = $p_settings;
        $this->view->platformsettings_tabparams[] = [
            'cssClass' => 'tabs-platforms-settings',
            // add to tabs and tab-pane
            //'callback' => 'productPriceBlock', // smarty function which will be called before children tabs , data passed as params params
            'callback_bottom' => '',
            'tabs_type' => 'hTab',
        ];
        unset($tmp);
        $c_description = [];
        $languages = \common\helpers\Language::get_languages();
        for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
            $languages[$i]['logo'] = $languages[$i]['image'];
            $c_description[$i]['code'] = $languages[$i]['code'];
            $c_description[$i]['languageId'] = $languages[$i]['id'];
            $category_description_query = tep_db_query('select * from ' . TABLE_CATEGORIES_DESCRIPTION . " where categories_id = '" . $categories_id . "' and language_id = '" . (int) $languages[$i]['id'] . "' and affiliate_id = '" . (int) $affiliate_id . "'");
            $category_description = tep_db_fetch_array($category_description_query);
            $category_description = new \Object_Info($category_description);
            $c_description[$i]['categories_name'] = tep_draw_input_field('categories_name[' . $languages[$i]['id'] . ']', isset($category_description->categories_name) ? $category_description->categories_name : '', 'class="form-control"');
            $c_description[$i]['categories_description'] = \common\helpers\Html::textarea('categories_description[' . $languages[$i]['id'] . ']', $category_description->categories_description ?? '', ['wrap' => 'soft', 'cols' => '70', 'rows' => '15', 'class' => 'form-control ckeditor', 'id' => 'txt_category_description_' . $languages[$i]['id']]);
            $c_description[$i]['categories_seo_page_name'] = tep_draw_input_field('categories_seo_page_name[' . $languages[$i]['id'] . ']', isset($category_description->categories_seo_page_name) ? $category_description->categories_seo_page_name : '', 'class="form-control"');
            $c_description[$i]['noindex_option'] = tep_draw_checkbox_field('noindex_option[' . $languages[$i]['id'] . ']', '1', isset($category_description->noindex_option) && $category_description->noindex_option == 1, '', 'class="check_on_off"');
            $c_description[$i]['nofollow_option'] = tep_draw_checkbox_field('nofollow_option[' . $languages[$i]['id'] . ']', '1', isset($category_description->nofollow_option) && $category_description->nofollow_option == 1, '', 'class="check_on_off"');
            $c_description[$i]['rel_canonical'] = tep_draw_input_field('rel_canonical[' . $languages[$i]['id'] . ']', isset($category_description->rel_canonical) ? $category_description->rel_canonical : '', 'class="form-control form-control-small"');
            $c_description[$i]['categories_head_title_tag'] = tep_draw_input_field('categories_head_title_tag[' . $languages[$i]['id'] . ']', isset($category_description->categories_head_title_tag) ? $category_description->categories_head_title_tag : '', 'class="form-control"');
            $c_description[$i]['categories_head_desc_tag'] = tep_draw_textarea_field('categories_head_desc_tag[' . $languages[$i]['id'] . ']', 'soft', '70', '5', isset($category_description->categories_head_desc_tag) ? $category_description->categories_head_desc_tag : '', 'class="form-control"');
            $c_description[$i]['categories_head_keywords_tag'] = tep_draw_textarea_field('categories_head_keywords_tag[' . $languages[$i]['id'] . ']', 'soft', '70', '5', isset($category_description->categories_head_keywords_tag) ? $category_description->categories_head_keywords_tag : '', 'class="form-control"');
            $c_description[$i]['categories_h1_tag'] = tep_draw_input_field('categories_h1_tag[' . $languages[$i]['id'] . ']', isset($category_description->categories_h1_tag) ? $category_description->categories_h1_tag : '', 'class="form-control"');
            $c_description[$i]['categories_h2_tag'] = isset($category_description->categories_h2_tag) ? $category_description->categories_h2_tag : '';
            $c_description[$i]['categories_h3_tag'] = isset($category_description->categories_h3_tag) ? $category_description->categories_h3_tag : '';
            $c_description[$i]['categories_image_alt_tag_mask'] = tep_draw_input_field('categories_image_alt_tag_mask[' . $languages[$i]['id'] . ']', isset($category_description->categories_image_alt_tag_mask) ? $category_description->categories_image_alt_tag_mask : '', 'class="form-control"');
            $c_description[$i]['categories_image_title_tag_mask'] = tep_draw_input_field('categories_image_title_tag_mask[' . $languages[$i]['id'] . ']', isset($category_description->categories_image_title_tag_mask) ? $category_description->categories_image_title_tag_mask : '', 'class="form-control"');
        }
        $this->view->platform_assigned = [];
        $this->view->platform_switch_notice = [];
        if (isset($c_info->categories_id) && intval($c_info->categories_id) > 0) {
            $get_assigned_platforms_r = tep_db_query('SELECT platform_id FROM ' . TABLE_PLATFORMS_CATEGORIES . " WHERE categories_id = '" . intval($c_info->categories_id) . "' ");
            if (tep_db_num_rows($get_assigned_platforms_r) > 0) {
                while ($_assigned_platform = tep_db_fetch_array($get_assigned_platforms_r)) {
                    $this->view->platform_assigned[(int) $_assigned_platform['platform_id']] = (int) $_assigned_platform['platform_id'];
                }
            }
            foreach (\common\classes\platform::get_list() as $__platform) {
                $this->view->platform_switch_notice[strval($__platform['id'])] = ['categories' => [0, 0], 'products' => [0, 0], 'original_state' => isset($this->view->platform_assigned[(int) $__platform['id']])];
            }
            $sub_categories = [];
            \common\helpers\Categories::get_subcategories($sub_categories, $c_info->categories_id, true);
            if (count($sub_categories) > 0) {
                foreach (\common\classes\platform::get_categories_assign_list() as $_check_notice_platform) {
                    //category assigned, can switch OFF - check assigned subcategories
                    $__check = tep_db_fetch_array(tep_db_query('SELECT COUNT(*) AS c ' . 'FROM ' . TABLE_PLATFORMS_CATEGORIES . ' ' . "WHERE platform_id='" . $_check_notice_platform['id'] . "' AND categories_id IN('" . implode("','", $sub_categories) . "') "));
                    if ($__check['c'] > 0) {
                        $this->view->platform_switch_notice[$_check_notice_platform['id']]['categories'][1] = $__check['c'];
                    }
                    //category not assigned, can switch ON - check not assigned subcategories
                    $__check = tep_db_fetch_array(tep_db_query('SELECT COUNT(*) AS c ' . 'FROM ' . TABLE_CATEGORIES . ' c ' . ' LEFT JOIN ' . TABLE_PLATFORMS_CATEGORIES . " pc ON pc.categories_id=c.categories_id AND pc.platform_id='" . $_check_notice_platform['id'] . "' " . "WHERE c.categories_id IN('" . implode("','", $sub_categories) . "') AND pc.categories_id IS NULL "));
                    if ($__check['c'] > 0) {
                        $this->view->platform_switch_notice[$_check_notice_platform['id']]['categories'][0] = $__check['c'];
                    }
                }
            }
            $sub_categories[] = $c_info->categories_id;
            foreach (\common\classes\platform::get_products_assign_list() as $_check_notice_platform) {
                //category assigned, can switch OFF - check assigned products
                $__check = tep_db_fetch_array(tep_db_query('SELECT COUNT(*) AS c ' . 'FROM ' . TABLE_PRODUCTS . ' p, ' . TABLE_PRODUCTS_TO_CATEGORIES . ' p2c, ' . TABLE_PLATFORMS_PRODUCTS . ' plp ' . "WHERE p2c.products_id=p.products_id AND p2c.categories_id IN('" . implode("','", $sub_categories) . "') " . "  AND plp.platform_id='" . $_check_notice_platform['id'] . "' AND plp.products_id=p.products_id "));
                if ($__check['c'] > 0) {
                    $this->view->platform_switch_notice[$_check_notice_platform['id']]['products'][1] = $__check['c'];
                }
                //category not assigned, can switch ON - check not assigned products
                $__check = tep_db_fetch_array(tep_db_query('SELECT COUNT(*) AS c ' . 'FROM ' . TABLE_PRODUCTS_TO_CATEGORIES . ' p2c, ' . TABLE_PRODUCTS . ' p ' . '  LEFT JOIN ' . TABLE_PLATFORMS_PRODUCTS . " plp ON plp.platform_id='" . $_check_notice_platform['id'] . "' AND plp.products_id=p.products_id " . "WHERE p2c.products_id=p.products_id AND p2c.categories_id IN('" . implode("','", $sub_categories) . "') " . '  AND plp.products_id IS NULL '));
                if ($__check['c'] > 0) {
                    $this->view->platform_switch_notice[$_check_notice_platform['id']]['products'][0] = $__check['c'];
                }
            }
        } elseif (isset($c_info->parent_id) && !empty($c_info->parent_id)) {
            $get_assigned_platforms_r = tep_db_query('SELECT platform_id FROM ' . TABLE_PLATFORMS_CATEGORIES . " WHERE categories_id = '" . intval($c_info->parent_id) . "' ");
            if (tep_db_num_rows($get_assigned_platforms_r) > 0) {
                while ($_assigned_platform = tep_db_fetch_array($get_assigned_platforms_r)) {
                    $this->view->platform_assigned[(int) $_assigned_platform['platform_id']] = (int) $_assigned_platform['platform_id'];
                }
            }
        } else {
            foreach (\common\classes\platform::get_categories_assign_list() as $___data) {
                $this->view->platform_assigned[intval($___data['id'])] = intval($___data['id']);
            }
        }
        $departments = false;
        $this->view->department_assigned = [];
        $this->view->department_switch_notice = [];
        if (defined('SUPERADMIN_ENABLED') && SUPERADMIN_ENABLED == true) {
            $departments = true;
            // {{ departments assign
            if (isset($c_info->categories_id) && intval($c_info->categories_id) > 0) {
                $get_assigned_departments_r = tep_db_query('SELECT departments_id FROM ' . TABLE_DEPARTMENTS_CATEGORIES . " WHERE categories_id = '" . intval($c_info->categories_id) . "' ");
                if (tep_db_num_rows($get_assigned_departments_r) > 0) {
                    while ($_assigned_department = tep_db_fetch_array($get_assigned_departments_r)) {
                        $this->view->department_assigned[(int) $_assigned_department['departments_id']] = (int) $_assigned_department['departments_id'];
                    }
                }
                foreach (\common\classes\department::get_catalog_assign_list() as $__department) {
                    $this->view->department_switch_notice[strval($__department['id'])] = ['categories' => [0, 0], 'products' => [0, 0], 'original_state' => isset($this->view->department_assigned[(int) $__department['id']])];
                }
                $sub_categories = [];
                \common\helpers\Categories::get_subcategories($sub_categories, $c_info->categories_id, true);
                if (count($sub_categories) > 0) {
                    foreach (\common\classes\department::get_catalog_assign_list() as $_check_notice_department) {
                        //category assigned, can switch OFF - check assigned subcategories
                        $__check = tep_db_fetch_array(tep_db_query('SELECT COUNT(*) AS c ' . 'FROM ' . TABLE_DEPARTMENTS_CATEGORIES . ' ' . "WHERE departments_id='" . $_check_notice_department['id'] . "' AND categories_id IN('" . implode("','", $sub_categories) . "') "));
                        if ($__check['c'] > 0) {
                            $this->view->department_switch_notice[$_check_notice_department['id']]['categories'][1] = $__check['c'];
                        }
                        //category not assigned, can switch ON - check not assigned subcategories
                        $__check = tep_db_fetch_array(tep_db_query('SELECT COUNT(*) AS c ' . 'FROM ' . TABLE_CATEGORIES . ' c ' . ' LEFT JOIN ' . TABLE_DEPARTMENTS_CATEGORIES . " pc ON pc.categories_id=c.categories_id AND pc.departments_id='" . $_check_notice_department['id'] . "' " . "WHERE c.categories_id IN('" . implode("','", $sub_categories) . "') AND pc.categories_id IS NULL "));
                        if ($__check['c'] > 0) {
                            $this->view->department_switch_notice[$_check_notice_department['id']]['categories'][0] = $__check['c'];
                        }
                    }
                }
                $sub_categories[] = $c_info->categories_id;
                foreach (\common\classes\department::get_catalog_assign_list() as $_check_notice_platform) {
                    //category assigned, can switch OFF - check assigned products
                    $__check = tep_db_fetch_array(tep_db_query('SELECT COUNT(*) AS c ' . 'FROM ' . TABLE_PRODUCTS . ' p, ' . TABLE_PRODUCTS_TO_CATEGORIES . ' p2c, ' . TABLE_DEPARTMENTS_PRODUCTS . ' plp ' . "WHERE p2c.products_id=p.products_id AND p2c.categories_id IN('" . implode("','", $sub_categories) . "') " . "  AND plp.departments_id='" . $_check_notice_platform['id'] . "' AND plp.products_id=p.products_id "));
                    if ($__check['c'] > 0) {
                        $this->view->department_switch_notice[$_check_notice_platform['id']]['products'][1] = $__check['c'];
                    }
                    //category not assigned, can switch ON - check not assigned products
                    $__check = tep_db_fetch_array(tep_db_query('SELECT COUNT(*) AS c ' . 'FROM ' . TABLE_PRODUCTS_TO_CATEGORIES . ' p2c, ' . TABLE_PRODUCTS . ' p ' . '  LEFT JOIN ' . TABLE_DEPARTMENTS_PRODUCTS . " plp ON plp.departments_id='" . $_check_notice_platform['id'] . "' AND plp.products_id=p.products_id " . "WHERE p2c.products_id=p.products_id AND p2c.categories_id IN('" . implode("','", $sub_categories) . "') " . '  AND plp.products_id IS NULL '));
                    if ($__check['c'] > 0) {
                        $this->view->department_switch_notice[$_check_notice_platform['id']]['products'][0] = $__check['c'];
                    }
                }
            } elseif (isset($c_info->parent_id) && !empty($c_info->parent_id)) {
                $get_assigned_departments_r = tep_db_query('SELECT departments_id FROM ' . TABLE_DEPARTMENTS_CATEGORIES . " WHERE categories_id = '" . intval($c_info->parent_id) . "' ");
                if (tep_db_num_rows($get_assigned_departments_r) > 0) {
                    while ($_assigned_department = tep_db_fetch_array($get_assigned_departments_r)) {
                        $this->view->department_assigned[(int) $_assigned_department['departments_id']] = (int) $_assigned_department['departments_id'];
                    }
                }
            } else {
                foreach (\common\classes\department::get_catalog_assign_list() as $___data) {
                    $this->view->department_assigned[intval($___data['id'])] = intval($___data['id']);
                }
            }
            // }} departments assign
            $c_info->department_category_price = [];
            foreach (\common\classes\department::get_catalog_assign_list() as $_dep_data) {
                $department_id = $_dep_data['id'];
                $_price_formula = tep_db_fetch_array(tep_db_query('SELECT ' . ' api_outgoing_price_formula as formula, ' . ' api_outgoing_price_discount as discount, ' . ' api_outgoing_price_surcharge as surcharge, ' . ' api_outgoing_price_margin as margin ' . 'FROM ' . TABLE_DEPARTMENTS . " WHERE departments_id='" . (int) $department_id . "' "));
                if (!is_array($_price_formula)) {
                    $_price_formula = [];
                }
                if (isset($c_info->categories_id) && intval($c_info->categories_id) > 0) {
                    $_category_formula = \common\classes\Api_Department::get_category_formula_data((int) $department_id, $c_info->categories_id ? intval($c_info->categories_id) : intval($c_info->parent_id));
                    if (is_array($_category_formula)) {
                        $_price_formula = $_category_formula;
                    }
                }
                $_price_formula['formula_text'] = '';
                if (!empty($_price_formula['formula'])) {
                    $_price_formula_arr = json_decode($_price_formula['formula'], true);
                    if (is_array($_price_formula_arr)) {
                        $_price_formula['formula_text'] = $_price_formula_arr['text'];
                    }
                }
                $c_info->department_category_price[$_dep_data['id']] = $_price_formula;
            }
        }
        // {{ ep soap
        if ($categories_id) {
            $get_linked_r = tep_db_query('SELECT c.ep_holbi_soap_disable_update ' . 'FROM ' . TABLE_CATEGORIES . ' c ' . ' INNER JOIN ep_holbi_soap_link_categories lc ON lc.local_category_id=c.categories_id ' . "WHERE c.categories_id='" . (int) $categories_id . "' " . 'LIMIT 1 ');
            if (tep_db_num_rows($get_linked_r) > 0) {
                $c_info->ep_holbi_soap_present = 1;
                $get_linked = tep_db_fetch_array($get_linked_r);
                $c_info->ep_holbi_soap_disable_update = (int) $get_linked['ep_holbi_soap_disable_update'];
            }
        }
        if (class_exists('\backend\models\EP\Datasource\HolbiSoap')) {
            \backend\models\EP\Datasource\Holbi_Soap::category_edit($c_info);
        }
        // }} ep soap
        $this->selected_menu = ['catalog', 'categories'];
        $text_new_or_edit = $categories_id == 0 ? TEXT_INFO_HEADING_NEW_CATEGORY : TEXT_INFO_HEADING_EDIT_CATEGORY . (empty($c_info->categories_name) ? '' : ' &quot;' . $c_info->categories_name . '&quot;');
        if ($categories_id == 0) {
            $edit_category_in_path = (defined('TEXT_CATEGORY_CREATE_IN') ? TEXT_CATEGORY_CREATE_IN : '') . ' ' . '<ul class="category_path_list top_bead-items"><li class="category_path">' . \common\helpers\Categories::output_generated_category_path($c_info->parent_id, 'category', '<a href="' . Yii::$app->url_manager->create_url('categories') . '?category_id=%s" class="category_path__location">%2$s</a>', '</li><li class="category_path onemore">') . '</li></ul>';
        } else {
            $edit_category_in_path = (defined('TEXT_CATEGORY_PLACED_IN') ? TEXT_CATEGORY_PLACED_IN : '') . ' ' . '<ul class="category_path_list top_bead-items"><li class="category_path">' . \common\helpers\Categories::output_generated_category_path($c_info->parent_id, 'category', '<a href="' . Yii::$app->url_manager->create_url('categories') . '?category_id=%s" class="category_path__location">%2$s</a>', '</li><li class="category_path onemore">') . '</li></ul>';
        }
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('categories/index'), 'title' => sprintf($text_new_or_edit, \common\helpers\Categories::output_generated_category_path($categories_id))];
        $seo_url = tep_db_fetch_array(tep_db_query('select categories_seo_page_name from ' . TABLE_CATEGORIES_DESCRIPTION . " where categories_id = '" . $categories_id . "' and language_id = '" . (int) \common\helpers\Language::get_default_language_id() . "'"));
        foreach (\common\classes\platform::get_list(false) as $frontend) {
            if ($this->view->platform_assigned[$frontend['id']] ?? null) {
                if (isset($seo_url['categories_seo_page_name']) && !empty($seo_url['categories_seo_page_name'])) {
                    $this->view->preview_link[] = ['link' => '//' . $frontend['platform_url'] . '/' . $seo_url['categories_seo_page_name'], 'name' => $frontend['text']];
                } else {
                    $this->view->preview_link[] = ['link' => '//' . $frontend['platform_url'] . '/catalog/index?cPath=' . $categories_id, 'name' => $frontend['text']];
                }
            }
        }
        if (isset($this->view->preview_link) && count($this->view->preview_link) > 1) {
            $this->top_buttons[] = '<a href="#choose-frontend" class="btn btn-primary btn-choose-frontend">' . TEXT_PREVIEW_ON_SITE . '</a>';
        } else {
            $this->top_buttons[] = '<a href="' . ($this->view->preview_link[0]['link'] ?? null) . '" target="_blank" class="btn btn-primary">' . TEXT_PREVIEW_ON_SITE . '</a>';
        }
        // {{ suppliers
        $supplier_rules = new \backend\models\Suppliers_Rules();
        if ($categories_id) {
            $category_obj = \common\models\Categories::find_one(['categories_id' => $categories_id]);
        } else {
            $category_obj = new \common\models\Categories();
        }
        $supplier_rules->get_category_data($category_obj, $c_info);
        // }} suppliers
        if (isset($c_info->stock_limit) && $c_info->stock_limit >= 0) {
            $c_info->stock_limit_on = true;
        } else {
            $c_info->stock_limit_on = false;
            $c_info->stock_limit = (int) ADDITIONAL_STOCK_LIMIT;
        }
        $banner_groups[] = '';
        $banners = \common\models\Banners_Groups::find()->as_array()->all();
        foreach ($banners as $banner) {
            $banner_groups[$banner['id']] = $banner['banners_group'];
        }
        //        $xsellProducts = [0=>[]];
        //        $this->view->xsellTypes = [];
        //        $get_xsell_types_r = tep_db_query("SELECT xsell_type_id, xsell_type_name FROM ".TABLE_PRODUCTS_XSELL_TYPE." WHERE language_id='".$languages_id."' ORDER BY xsell_type_name");
        //        if ( tep_db_num_rows($get_xsell_types_r)>0 ) {
        //            while ( $_xsell_type = tep_db_fetch_array($get_xsell_types_r) ) {
        //                $this->view->xsellTypes[$_xsell_type['xsell_type_id']] = $_xsell_type['xsell_type_name'];
        //                $xsellProducts[$_xsell_type['xsell_type_id']] = [];
        //            }
        //        }
        //        $this->view->xsellProducts = $xsellProducts;
        //
        //        $currencies = Yii::$container->get('currencies');
        //        $query = tep_db_query("select cpxs.xsell_products_id as xsell_id, cpxs.xsell_type_id, cpxs.sort_order, ".ProductNameDecorator::instance()->listingQueryExpression('pd','')." AS products_name, p.products_status from  " . TABLE_CATS_PRODUCTS_XSELL . " cpxs, " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd where cpxs.xsell_products_id = p.products_id and cpxs.xsell_products_id = pd.products_id and pd.language_id = '" . $languages_id . "' and pd.platform_id = '".intval(\common\classes\platform::defaultId())."' and cpxs.categories_id = '" . (int) $categories_id . "' order by cpxs.xsell_type_id, cpxs.sort_order");
        //        while ($data = tep_db_fetch_array($query)) {
        //            if ( !isset($xsellProducts[$data['xsell_type_id']]) ) continue;
        //            if (empty($data['products_name'])) {
        //                $data['products_name'] = \common\helpers\Product::get_products_name($data['xsell_id']);
        //            }
        //            $xsellProducts[$data['xsell_type_id']][] = [
        //                'xsell_id' => $data['xsell_id'],
        //                'id' => $data['xsell_id'],
        //                'products_name' => $data['products_name'],
        //                'name' => $data['products_name'],
        //                'image' => \common\classes\Images::getImage($data['xsell_id'], 'Small'),
        //                'price' => $currencies->format(\common\helpers\Product::get_products_price($data['xsell_id'])),
        //                'status_class' => ($data['products_status'] == 0 ? 'dis_prod' : ''),
        //            ];
        //        }
        //        $this->view->xsellProducts = $xsellProducts;
        foreach (\common\helpers\Hooks::get_list('categories/categoryedit/before-render') as $filename) {
            include $filename;
        }
        global $navigation;
        if (sizeof($navigation->snapshot) > 0) {
            $back_url = Yii::$app->url_manager->create_url(array_merge([$navigation->snapshot['page']], $navigation->snapshot['get']));
        } else {
            $back_url = Yii::$app->url_manager->create_url(['category', 'category_id' => $c_info->parent_id]);
        }
        $hero_images = Image_Types::find()->where(['image_types_name' => 'Category hero'])->and_where(['not', ['parent_id' => 0]])->as_array()->all();
        if (is_array($hero_images)) {
            foreach ($hero_images as $key => $size) {
                $categories_images = Categories_Images::find()->where(['categories_id' => $categories_id, 'image_types_id' => $size['image_types_id']])->as_array()->all();
                if (is_array($categories_images)) {
                    foreach ($categories_images as $categories_image) {
                        $hero_images[$key]['images'][$categories_image['image_types_id']][$categories_image['platform_id']] = $categories_image;
                    }
                }
            }
        }
        return $this->render('categoryedit', ['infoBreadCrumb' => $edit_category_in_path, 'categories_id' => $categories_id, 'cInfo' => $c_info, 'languages' => $languages, 'cDescription' => $c_description, 'js_platform_switch_notice' => json_encode($this->view->platform_switch_notice), 'departments' => $departments, 'js_department_switch_notice' => json_encode($this->view->department_switch_notice), 'templates' => \backend\design\Category_Template::categoryedit($categories_id), 'upload_path' => \Yii::get_alias('@web') . '/uploads/', 'images' => \common\helpers\Image::get_categories_additional_images($categories_id), 'bannerGroups' => $banner_groups, 'backUrl' => $back_url, 'heroImages' => $hero_images]);
    }
    public function action_category_submit()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/categories');
        $message_stack = \Yii::$container->get('message_stack');
        $this->view->error_message_type = 'success';
        $this->view->error_message = '';
        $this->layout = false;
        $current_category_id = (int) Yii::$app->request->post('parent_category_id', 0);
        //can change current category
        $popup = (int) Yii::$app->request->post('popup');
        $categories_id = (int) Yii::$app->request->post('categories_id');
        if ($categories_id > 0) {
            $action = 'update_category';
            $cat_info = \common\models\Categories::find_one($categories_id);
            $category = $cat_info;
            if (!$cat_info) {
                $categories_id = null;
                $action = 'insert_category';
            }
        } else {
            $action = 'insert_category';
        }
        //if ($action == 'update_category') {
        if (\common\helpers\Acl::check_extension_allowed('ReportChangesHistory')) {
            $logger = new \common\extensions\Report_Changes_History\classes\Logger();
            $before_object = new \common\api\Classes\Category();
            $before_object->load($categories_id);
            $logger->set_before_object($before_object);
            unset($before_object);
        }
        //}
        $categories_status = (int) tep_db_prepare_input(Yii::$app->request->post('categories_status', ''));
        $default_sort_order = tep_db_prepare_input(Yii::$app->request->post('default_sort_order', ''));
        $post = Yii::$app->request->post();
        if ($categories_id > 0) {
            $p_settings = \common\models\Categories_Platform_Settings::find()->and_where(['categories_id' => $categories_id])->index_by('platform_id')->all();
            if ($p_settings) {
                foreach ($p_settings as $platform_id => $ps) {
                    if (!isset($post['plaformsettings'][$platform_id])) {
                        foreach (['', '_2', '_3', '_4'] as $mod) {
                            $image_name = 'categories_image' . $mod;
                            if (!empty($ps->{$image_name})) {
                                $image_location = DIR_FS_DOCUMENT_ROOT . DIR_WS_CATALOG_IMAGES . $ps->{$image_name};
                                if (file_exists($image_location)) {
                                    @unlink($image_location);
                                }
                                Images::remove_resize_images($ps->{$image_name});
                                Images::remove_webp($ps->{$image_name});
                            }
                        }
                        $ps->delete();
                    } else {
                        $post['plaformsettings'][$platform_id] = $ps;
                    }
                }
                unset($p_settings);
            }
        }
        \common\helpers\Image::save_categories_additional_images(Yii::$app->request->post('additional_categories'), $categories_id);
        if ($action == 'insert_category') {
            $sql_data_array = ['parent_id' => $current_category_id, 'date_added' => 'now()'];
            tep_db_perform(TABLE_CATEGORIES, $sql_data_array);
            $categories_id = tep_db_insert_id();
            /** @var \common\extensions\UserGroupsRestrictions\UserGroupsRestrictions $ext */
            if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroupsRestrictions', 'allowed')) {
                if ($group_service = $ext::get_groups_service()) {
                    $group_service->add_category_to_all_groups($categories_id);
                }
            }
            Yii::$app->request->set_body_params(array_merge(Yii::$app->request->get_body_params(), ['categories_id' => $categories_id, 'popup' => $popup]));
        }
        $hero_image_main = [];
        $hero_image_main_update = [];
        /* platform settings main tab*/
        if (!empty($post['plaformsettings']) && is_array($post['plaformsettings'])) {
            foreach ($post['plaformsettings'] as $platform_id => $ps) {
                $maps_id = (int) tep_db_prepare_input($_POST['maps_id'][$platform_id]);
                foreach (['gallery' => '', 'hero' => '_2', 'homepage' => '_3', 'menu' => '_4'] as $image_type => $mod) {
                    $image_name = 'categories_image' . $mod;
                    $old_image = $platform_id == 0 ? $category->{$image_name} ?? '' : $ps->{$image_name} ?? '';
                    $delete_image = (bool) ($post['delete_image' . $mod][$platform_id] ?? false);
                    $sql_data_array[$image_name] = \common\helpers\Image::prepare_saving_image($old_image, $post[$image_name][$platform_id], $post['categories_image_loaded' . $mod][$platform_id], 'categories' . DIRECTORY_SEPARATOR . $categories_id . DIRECTORY_SEPARATOR . $image_type, $delete_image);
                    if ($image_type == 'hero') {
                        $hero_image_main[$platform_id] = $sql_data_array[$image_name];
                        if ($sql_data_array[$image_name] == $old_image) {
                            $hero_image_main_update[$platform_id] = false;
                        } else {
                            $hero_image_main_update[$platform_id] = true;
                        }
                    }
                    if ($delete_image && $old_image) {
                        Images::remove_resize_images($old_image);
                    }
                    Images::create_resize_images($sql_data_array[$image_name], 'Category ' . $image_type);
                }
                $sql_data_array['maps_id'] = $maps_id;
                $sql_data_array['show_on_home'] = !empty($post['show_on_home'][$platform_id]) ? 1 : 0;
                if ($platform_id == 0) {
                    $_cat_data = $sql_data_array;
                } else {
                    try {
                        if (!is_object($ps)) {
                            $ps = new \common\models\Categories_Platform_Settings();
                        }
                        if ($ps) {
                            $sql_data_array['platform_id'] = $platform_id;
                            if ($ps->load($sql_data_array, '')) {
                                $ps->categories_id = $categories_id;
                                $ps->save();
                                unset($ps);
                            } else {
                                Yii::warning(print_r($ps->get_errors(), 1), 'CATEGORYPLATFORMSETTINGS');
                            }
                        }
                    } catch (\Exception $e) {
                        Yii::warning(print_r($e, 1), 'CATEGORYPLATFORMSETTINGS');
                    }
                }
            }
            if ($_cat_data) {
                $sql_data_array = $_cat_data;
            } else {
                $sql_data_array = [];
            }
            unset($post['plaformsettings'][$platform_id]);
        }
        $sql_data_array['categories_status'] = $categories_status;
        $sql_data_array['default_sort_order'] = $default_sort_order;
        $sql_data_array['banners_group'] = tep_db_prepare_input(Yii::$app->request->post('banners_group', ''));
        $sql_data_array['stock_limit'] = (int) Yii::$app->request->post('stock_limit', -1);
        if ($ext = \common\helpers\Acl::check_extension_allowed('AutomaticallyStatus', 'allowed')) {
            $_sql_data = $ext::on_category_save();
            if (is_array($_sql_data)) {
                $sql_data_array = array_merge($sql_data_array, $_sql_data);
            }
        }
        // Moved to SeoRedirectsNamed
        // $sql_data_array['categories_old_seo_page_name'] = tep_db_prepare_input($_POST['categories_old_seo_page_name']);
        $update_sql_data = ['last_modified' => 'now()'];
        \common\helpers\Categories::set_categories_status($categories_id, $categories_status);
        $sql_data_array = array_merge($sql_data_array, $update_sql_data);
        tep_db_perform(TABLE_CATEGORIES, $sql_data_array, 'update', "categories_id = '" . (int) $categories_id . "'");
        $this->view->error_message = TEXT_INFO_UPDATED;
        if ($ext = \common\helpers\Acl::check_extension_allowed('SeoRedirectsNamed', 'allowed')) {
            $ext::save_category_links($categories_id, $_POST);
        }
        $categories_name = Yii::$app->request->post('categories_name');
        $categories_description = Yii::$app->request->post('categories_description');
        $categories_seo_page_name = Yii::$app->request->post('categories_seo_page_name');
        $noindex_option = Yii::$app->request->post('noindex_option');
        $nofollow_option = Yii::$app->request->post('nofollow_option');
        $rel_canonical = Yii::$app->request->post('rel_canonical');
        $categories_head_title_tag = Yii::$app->request->post('categories_head_title_tag');
        $categories_head_desc_tag = Yii::$app->request->post('categories_head_desc_tag');
        $categories_head_keywords_tag = Yii::$app->request->post('categories_head_keywords_tag');
        $categories_h1_tag = Yii::$app->request->post('categories_h1_tag');
        $categories_h2_tag = Yii::$app->request->post('categories_h2_tag');
        $categories_h3_tag = Yii::$app->request->post('categories_h3_tag');
        $categories_image_alt_tag_mask = Yii::$app->request->post('categories_image_alt_tag_mask');
        $categories_image_title_tag_mask = Yii::$app->request->post('categories_image_title_tag_mask');
        $languages = \common\helpers\Language::get_languages();
        for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
            $language_id = $languages[$i]['id'];
            $sql_data_array = ['categories_name' => tep_db_prepare_input($categories_name[$language_id]), 'categories_description' => tep_db_prepare_input($categories_description[$language_id]), 'categories_seo_page_name' => tep_db_prepare_input($categories_seo_page_name[$language_id]), 'noindex_option' => isset($noindex_option[$language_id]) ? (int) $noindex_option[$language_id] : 0, 'nofollow_option' => isset($nofollow_option[$language_id]) ? (int) $nofollow_option[$language_id] : 0, 'rel_canonical' => tep_db_prepare_input($rel_canonical[$language_id]), 'categories_head_title_tag' => tep_db_prepare_input($categories_head_title_tag[$language_id]), 'categories_head_desc_tag' => tep_db_prepare_input($categories_head_desc_tag[$language_id]), 'categories_head_keywords_tag' => tep_db_prepare_input($categories_head_keywords_tag[$language_id]), 'categories_h1_tag' => tep_db_prepare_input($categories_h1_tag[$language_id]), 'categories_h2_tag' => tep_db_prepare_input(is_array($categories_h2_tag[$language_id]) ? implode("\n", $categories_h2_tag[$language_id]) : $categories_h2_tag[$language_id]), 'categories_h3_tag' => tep_db_prepare_input(is_array($categories_h3_tag[$language_id]) ? implode("\n", $categories_h3_tag[$language_id]) : $categories_h3_tag[$language_id]), 'categories_image_alt_tag_mask' => isset($categories_image_alt_tag_mask[$language_id]) ? tep_db_prepare_input($categories_image_alt_tag_mask[$language_id]) : '', 'categories_image_title_tag_mask' => isset($categories_image_title_tag_mask[$language_id]) ? tep_db_prepare_input($categories_image_title_tag_mask[$language_id]) : ''];
            if (empty($sql_data_array['categories_seo_page_name'])) {
                $sql_data_array['categories_seo_page_name'] = Seo::make_slug(tep_db_prepare_input($_POST['categories_name'][$languages_id]));
                $cur_seo = $sql_data_array['categories_seo_page_name'];
                if (\common\models\Categories_Description::find()->where(['categories_seo_page_name' => $cur_seo])->and_where(['not', ['categories_id' => (int) $categories_id]])->exists()) {
                    $seo_max_count = (int) \common\models\Categories_Description::find()->where("categories_seo_page_name LIKE '{$cur_seo}-%'")->and_where(['not', ['categories_id' => (int) $categories_id]])->max('CAST(SUBSTR(categories_seo_page_name, ' . (strlen($cur_seo) + 2) . ') AS UNSIGNED)');
                    $sql_data_array['categories_seo_page_name'] .= '-' . ++$seo_max_count;
                }
            }
            $check_category = tep_db_query('select * from ' . TABLE_CATEGORIES_DESCRIPTION . " where categories_id = '" . $categories_id . "' and language_id = '" . $languages[$i]['id'] . "' and affiliate_id = 0");
            if ($action == 'insert_category' || !tep_db_num_rows($check_category)) {
                $insert_sql_data = ['categories_id' => $categories_id, 'language_id' => $languages[$i]['id']];
                $sql_data_array = array_merge($sql_data_array, $insert_sql_data);
                tep_db_perform(TABLE_CATEGORIES_DESCRIPTION, $sql_data_array);
            } elseif ($action == 'update_category') {
                $check_category_data = tep_db_fetch_array($check_category);
                tep_db_perform(TABLE_CATEGORIES_DESCRIPTION, $sql_data_array, 'update', "categories_id = '" . (int) $categories_id . "' and language_id = '" . (int) $languages[$i]['id'] . "' and affiliate_id = 0");
                if ($ext = \common\helpers\Acl::check_extension_allowed('SeoRedirectsNamed', 'allowed')) {
                    $ext::track_category_links($categories_id, $language_id, null, $sql_data_array, $check_category_data);
                }
            }
        }
        $_platform_list = \common\classes\platform::get_categories_assign_list();
        $assign_platform = [];
        if (count($_platform_list) == 1) {
            $assign_platform[] = (int) $_platform_list[0]['id'];
        } else {
            $assign_platform = array_map('intval', Yii::$app->request->post('platform', []));
        }
        $category_product_assign = Yii::$app->request->post('category_product_assign', []);
        $sub_categories = [(int) $categories_id];
        \common\helpers\Categories::get_subcategories($sub_categories, (int) $categories_id);
        $removed_mapping_pool = [];
        if (count($assign_platform) > 0) {
            $get_removed_r = tep_db_query('SELECT DISTINCT platform_id FROM ' . TABLE_PLATFORMS_CATEGORIES . ' ' . "WHERE categories_id IN('" . implode("','", $sub_categories) . "') AND platform_id NOT IN('" . implode("','", $assign_platform) . "') ");
            while ($_removed = tep_db_fetch_array($get_removed_r)) {
                $removed_mapping_pool[] = $_removed;
            }
            tep_db_query('DELETE FROM ' . TABLE_PLATFORMS_CATEGORIES . " WHERE categories_id IN('" . implode("','", $sub_categories) . "') AND platform_id NOT IN('" . implode("','", $assign_platform) . "') ");
        } else {
            $get_removed_r = tep_db_query('SELECT DISTINCT platform_id FROM ' . TABLE_PLATFORMS_CATEGORIES . ' ' . "WHERE categories_id IN('" . implode("','", $sub_categories) . "') ");
            while ($_removed = tep_db_fetch_array($get_removed_r)) {
                $removed_mapping_pool[] = $_removed;
            }
            tep_db_query('DELETE FROM ' . TABLE_PLATFORMS_CATEGORIES . " WHERE categories_id IN('" . implode("','", $sub_categories) . "')");
        }
        if (count($removed_mapping_pool) > 0) {
            foreach ($removed_mapping_pool as $removed_mapping) {
                $__remove_ids = [];
                $get_cleanup_ids_r = tep_db_query('  SELECT /*count(*) as ttl,*/ plp.products_id/*,  max(IF(plc.categories_id is null , if(p2c.categories_id=0,0,-1), plc.categories_id)) AS plc_categories_id*/ ' . '  FROM ' . TABLE_PLATFORMS_PRODUCTS . ' plp ' . '    INNER JOIN ' . TABLE_PRODUCTS_TO_CATEGORIES . ' p2c on p2c.products_id=plp.products_id ' . '    LEFT JOIN ' . TABLE_PLATFORMS_CATEGORIES . ' plc on plc.categories_id=p2c.categories_id AND plc.platform_id=plp.platform_id ' . "  WHERE plp.platform_id='{$removed_mapping['platform_id']}' " . '  GROUP BY plp.products_id HAVING MAX(IF(plc.categories_id IS NULL, IF(p2c.categories_id=0,0,-1), plc.categories_id))=-1 ');
                while ($_cleanup_ids = tep_db_fetch_array($get_cleanup_ids_r)) {
                    $__remove_ids[] = $_cleanup_ids['products_id'];
                    if (count($__remove_ids) > 99) {
                        tep_db_query('DELETE FROM ' . TABLE_PLATFORMS_PRODUCTS . ' ' . "WHERE platform_id='{$removed_mapping['platform_id']}' AND products_id IN(" . implode(',', $__remove_ids) . ') ');
                        $__remove_ids = [];
                    }
                }
                if (count($__remove_ids) > 0) {
                    tep_db_query('DELETE FROM ' . TABLE_PLATFORMS_PRODUCTS . ' ' . "WHERE platform_id='{$removed_mapping['platform_id']}' AND products_id IN(" . implode(',', $__remove_ids) . ') ');
                    $__remove_ids = [];
                }
            }
        }
        foreach ($assign_platform as $assign_platform_id) {
            $_check = tep_db_fetch_array(tep_db_query('SELECT COUNT(*) AS c FROM ' . TABLE_PLATFORMS_CATEGORIES . " WHERE categories_id='" . (int) $categories_id . "' AND platform_id='" . $assign_platform_id . "' "));
            if ($_check['c'] == 0) {
                tep_db_perform(TABLE_PLATFORMS_CATEGORIES, ['categories_id' => (int) $categories_id, 'platform_id' => $assign_platform_id]);
            }
            if (isset($category_product_assign[$assign_platform_id]) && $category_product_assign[$assign_platform_id] == 'yes') {
                tep_db_query('REPLACE INTO ' . TABLE_PLATFORMS_PRODUCTS . " (products_id, platform_id) SELECT p2c.products_id, '" . $assign_platform_id . "' FROM " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c WHERE p2c.categories_id='" . (int) $categories_id . "' ");
                foreach ($sub_categories as $__sub_category_id) {
                    if ((int) $__sub_category_id == (int) $categories_id) {
                        continue;
                    }
                    tep_db_query('REPLACE INTO ' . TABLE_PLATFORMS_CATEGORIES . " (categories_id, platform_id) VALUES('" . (int) $__sub_category_id . "','" . $assign_platform_id . "') ");
                    tep_db_query('REPLACE INTO ' . TABLE_PLATFORMS_PRODUCTS . " (products_id, platform_id) SELECT p2c.products_id, '" . $assign_platform_id . "' FROM " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c WHERE p2c.categories_id='" . (int) $__sub_category_id . "' ");
                }
            }
        }
        if (defined('SUPERADMIN_ENABLED') && SUPERADMIN_ENABLED == true) {
            // {{ departments assign
            $_department_list = \common\classes\department::get_catalog_assign_list();
            $assign_department = [];
            if (count($_department_list) == 1) {
                $assign_department[] = (int) $_department_list[0]['id'];
            } else {
                $assign_department = array_map('intval', Yii::$app->request->post('departments', []));
            }
            $department_category_product_assign = Yii::$app->request->post('department_category_product_assign', []);
            $sub_categories = [(int) $categories_id];
            \common\helpers\Categories::get_subcategories($sub_categories, (int) $categories_id);
            $removed_mapping_pool = [];
            if (count($assign_department) > 0) {
                $get_removed_r = tep_db_query('SELECT DISTINCT departments_id FROM ' . TABLE_DEPARTMENTS_CATEGORIES . ' ' . "WHERE categories_id IN('" . implode("','", $sub_categories) . "') AND departments_id NOT IN('" . implode("','", $assign_department) . "') ");
                while ($_removed = tep_db_fetch_array($get_removed_r)) {
                    $removed_mapping_pool[] = $_removed;
                }
                tep_db_query('DELETE FROM ' . TABLE_DEPARTMENTS_CATEGORIES . " WHERE categories_id!=0 AND categories_id IN('" . implode("','", $sub_categories) . "') AND departments_id NOT IN('" . implode("','", $assign_department) . "') ");
            } else {
                $get_removed_r = tep_db_query('SELECT DISTINCT departments_id FROM ' . TABLE_DEPARTMENTS_CATEGORIES . ' ' . "WHERE categories_id IN('" . implode("','", $sub_categories) . "') ");
                while ($_removed = tep_db_fetch_array($get_removed_r)) {
                    $removed_mapping_pool[] = $_removed;
                }
                tep_db_query('DELETE FROM ' . TABLE_DEPARTMENTS_CATEGORIES . " WHERE categories_id!=0 AND categories_id IN('" . implode("','", $sub_categories) . "')");
            }
            if (count($removed_mapping_pool) > 0) {
                foreach ($removed_mapping_pool as $removed_mapping) {
                    $__remove_ids = [];
                    $get_cleanup_ids_r = tep_db_query('  SELECT /*count(*) as ttl,*/ plp.products_id/*,  max(IF(plc.categories_id is null , if(p2c.categories_id=0,0,-1), plc.categories_id)) AS plc_categories_id*/ ' . '  FROM ' . TABLE_DEPARTMENTS_PRODUCTS . ' plp ' . '    INNER JOIN ' . TABLE_PRODUCTS_TO_CATEGORIES . ' p2c on p2c.products_id=plp.products_id ' . '    LEFT JOIN ' . TABLE_DEPARTMENTS_CATEGORIES . ' plc on plc.categories_id=p2c.categories_id AND plc.departments_id=plp.departments_id ' . "  WHERE plp.departments_id='{$removed_mapping['departments_id']}' " . '  GROUP BY plp.products_id HAVING MAX(IF(plc.categories_id IS NULL, IF(p2c.categories_id=0,0,-1), plc.categories_id))=-1 ');
                    while ($_cleanup_ids = tep_db_fetch_array($get_cleanup_ids_r)) {
                        $__remove_ids[] = $_cleanup_ids['products_id'];
                        if (count($__remove_ids) > 99) {
                            tep_db_query('DELETE FROM ' . TABLE_DEPARTMENTS_PRODUCTS . ' ' . "WHERE departments_id='{$removed_mapping['departments_id']}' AND products_id IN(" . implode(',', $__remove_ids) . ') ');
                            $__remove_ids = [];
                        }
                    }
                    if (count($__remove_ids) > 0) {
                        tep_db_query('DELETE FROM ' . TABLE_DEPARTMENTS_PRODUCTS . ' ' . "WHERE departments_id='{$removed_mapping['departments_id']}' AND products_id IN(" . implode(',', $__remove_ids) . ') ');
                        $__remove_ids = [];
                    }
                }
            }
            foreach ($assign_department as $assign_department_id) {
                $_check = tep_db_fetch_array(tep_db_query('SELECT COUNT(*) AS c FROM ' . TABLE_DEPARTMENTS_CATEGORIES . " WHERE categories_id='" . (int) $categories_id . "' AND departments_id='" . $assign_department_id . "' "));
                if ($_check['c'] == 0) {
                    tep_db_perform(TABLE_DEPARTMENTS_CATEGORIES, ['categories_id' => (int) $categories_id, 'departments_id' => $assign_department_id]);
                }
                if (isset($department_category_product_assign[$assign_department_id]) && $department_category_product_assign[$assign_department_id] == 'yes') {
                    tep_db_query('REPLACE INTO ' . TABLE_PLATFORMS_PRODUCTS . " (products_id, departments_id) SELECT p2c.products_id, '" . $assign_department_id . "' FROM " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c WHERE p2c.categories_id='" . (int) $categories_id . "' ");
                    for ($_sub_category_idx = 1; $i < count($sub_categories) - 1; $_sub_category_idx++) {
                        $sub_categories[$_sub_category_idx];
                        tep_db_query('REPLACE INTO ' . TABLE_DEPARTMENTS_CATEGORIES . " (categories_id, departments_id) VALUES('" . (int) $sub_categories[$_sub_category_idx] . "','" . $assign_department_id . "') ");
                        tep_db_query('REPLACE INTO ' . TABLE_PLATFORMS_PRODUCTS . " (products_id, departments_id) SELECT p2c.products_id, '" . $assign_department_id . "' FROM " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c WHERE p2c.categories_id='" . (int) $sub_categories[$_sub_category_idx] . "' ");
                    }
                }
            }
            // }} departments assign
            // {{ departments_categories_price_formula
            $department_category_price = Yii::$app->request->post('department_category_price');
            if (is_array($department_category_price)) {
                $department_category_price = tep_db_prepare_input($department_category_price);
                foreach ($department_category_price as $department_id => $price_config) {
                    $parent_formula_data = tep_db_fetch_array(tep_db_query('SELECT ' . ' api_outgoing_price_formula as formula, ' . ' api_outgoing_price_discount as discount, ' . ' api_outgoing_price_surcharge as surcharge, ' . ' api_outgoing_price_margin as margin ' . 'FROM ' . TABLE_DEPARTMENTS . " WHERE departments_id='" . (int) $department_id . "' "));
                    if (!is_array($parent_formula_data)) {
                        continue;
                    }
                    $_category_formula = \common\classes\Api_Department::get_category_formula_data((int) $department_id, intval($categories_id), true);
                    if (is_array($_category_formula)) {
                        $parent_formula_data['formula'] = $_category_formula['formula'];
                        $parent_formula_data['discount'] = $_category_formula['discount'];
                        $parent_formula_data['surcharge'] = $_category_formula['surcharge'];
                        $parent_formula_data['margin'] = $_category_formula['margin'];
                    }
                    $_extracted_price_config = json_decode($price_config['formula'], true);
                    if (!is_array($_extracted_price_config) || empty($_extracted_price_config['formula']) || isset($_extracted_price_config['formula'][0]) && empty($_extracted_price_config['formula'][0])) {
                        $price_config['formula'] = '';
                    }
                    $department_category_price_formula = ['formula' => $price_config['formula'], 'discount' => number_format(floatval($price_config['discount']), 2, '.', ''), 'surcharge' => number_format(floatval($price_config['surcharge']), 2, '.', ''), 'margin' => number_format(floatval($price_config['margin']), 2, '.', '')];
                    tep_db_query("DELETE FROM departments_categories_price_formula WHERE departments_id='" . (int) $department_id . "' AND categories_id='" . (int) $categories_id . "'");
                    if ($department_category_price_formula['formula'] == '') {
                        continue;
                    }
                    if ($parent_formula_data != $department_category_price_formula) {
                        tep_db_perform('departments_categories_price_formula', array_merge(['departments_id' => (int) $department_id, 'categories_id' => (int) $categories_id], $department_category_price_formula));
                    }
                }
            }
            // }} departments_categories_price_formula
        }
        /* if (SUPPLEMENT_STATUS == 'True') {
                  tep_db_query("delete from " . TABLE_CATS_PRODUCTS_XSELL . " where categories_id = '" . (int)$categories_id . "'");
                  if (is_array($_POST['xsell_product_id'])){
                  foreach ($_POST['xsell_product_id'] as $key => $value){
                  tep_db_query("insert into " . TABLE_CATS_PRODUCTS_XSELL . " (categories_id, xsell_products_id, sort_order) values ('" . tep_db_input($categories_id) . "', '" . tep_db_input($value) . "', '" . tep_db_input($_POST['xsell_products_sort_order'][$key]). "')");
                  }
                  }
                  tep_db_query("delete from " . TABLE_CATS_PRODUCTS_UPSELL . " where categories_id = '" . (int)$categories_id . "'");
                  if (is_array($_POST['upsell_product_id'])){
                  foreach ($_POST['upsell_product_id'] as $key => $value){
                  tep_db_query("insert into " . TABLE_CATS_PRODUCTS_UPSELL . " (categories_id, upsell_products_id, sort_order) values ('" . tep_db_input($categories_id) . "', '" . tep_db_input($value) . "', '" . tep_db_input($_POST['upsell_products_sort_order'][$key]). "')");
                  }
                  }
        
                  tep_db_query("delete from " . TABLE_CATEGORIES_UPSELL . " where categories_id = '" . (int)$categories_id . "'");
                  if (is_array($_POST['upsell_category_id'])){
                  foreach ($_POST['upsell_category_id'] as $key => $value){
                  tep_db_query("insert into " . TABLE_CATEGORIES_UPSELL . " (categories_id, upsell_id, sort_order) values ('" . tep_db_input($categories_id) . "', '" . tep_db_input($value) . "', '" . tep_db_input($_POST['upsell_category_sort_order'][$key]). "')");
                  }
                  }
        
                  } */
        foreach (\common\helpers\Hooks::get_list('categories/categoryedit') as $filename) {
            include $filename;
        }
        \backend\design\Category_Template::category_submit($categories_id);
        if (Yii::$app->request->post('supplier_price_rule_present', 0)) {
            $category_obj = \common\models\Categories::find_one(['categories_id' => $categories_id]);
            $supplier_rules = new \backend\models\Suppliers_Rules();
            $supplier_rules->save_category_data($category_obj, Yii::$app->request->post('suppliers_data', []));
        }
        if (USE_CACHE == 'true') {
            \common\helpers\System::reset_cache_block('categories');
            \common\helpers\System::reset_cache_block('also_purchased');
        }
        if ($popup != 1) {
            \common\helpers\Categories::update_categories();
        }
        //if ($action == 'update_category') {
        if (isset($logger) && \common\helpers\Acl::check_extension_allowed('ReportChangesHistory')) {
            $after_object = new \common\api\Classes\Category();
            $after_object->load($categories_id);
            $logger->set_after_object($after_object);
            unset($after_object);
            $logger->run();
        }
        $hero_images = Yii::$app->request->post('heroImage', []);
        $hero_images_loaded = Yii::$app->request->post('heroImage_loaded', []);
        $hero_images_delete = Yii::$app->request->post('heroImage_delete', []);
        $hero_images_position = Yii::$app->request->post('heroImage_position', []);
        $hero_images_fit = Yii::$app->request->post('heroImage_fit', []);
        if (count($hero_images)) {
            foreach ($hero_images as $type_id => $hero_image_platforms) {
                $image_types = Image_Types::find()->where(['image_types_id' => $type_id])->as_array()->one();
                foreach ($hero_image_platforms as $platform_id => $hero_image) {
                    $categories_images = Categories_Images::find_one(['categories_id' => $categories_id, 'platform_id' => $platform_id, 'image_types_id' => $type_id]);
                    if (($hero_image_main_update[$platform_id] ?? false) && !($hero_images_loaded[$type_id][$platform_id] ?? false) || !$hero_image && !($hero_images_loaded[$type_id][$platform_id] ?? false) && ($hero_image_main[$platform_id] ?? false)) {
                        $hero_images_loaded[$type_id][$platform_id] = DIR_WS_IMAGES . $hero_image_main[$platform_id];
                    }
                    $image = \common\helpers\Image::prepare_saving_image($categories_images->image ?? '', $hero_image, $hero_images_loaded[$type_id][$platform_id], 'categories' . DIRECTORY_SEPARATOR . $categories_id . DIRECTORY_SEPARATOR . 'hero', $hero_images_delete[$type_id][$platform_id], false, ['width' => $image_types['image_types_x'], 'height' => $image_types['image_types_y'], 'fit' => $hero_images_fit[$type_id][$platform_id]]);
                    if (!$categories_images && $image) {
                        $categories_images = new Categories_Images();
                        $categories_images->categories_id = $categories_id;
                        $categories_images->image = $image;
                        $categories_images->platform_id = $platform_id;
                        $categories_images->image_types_id = $type_id;
                        $categories_images->position = $hero_images_position[$type_id][$platform_id];
                        $categories_images->fit = $hero_images_fit[$type_id][$platform_id];
                        $categories_images->save();
                    } elseif ($categories_images && !$image) {
                        $categories_images->delete();
                    } elseif ($categories_images && $image) {
                        $categories_images->image = $image;
                        $categories_images->position = $hero_images_position[$type_id][$platform_id];
                        $categories_images->fit = $hero_images_fit[$type_id][$platform_id];
                        $categories_images->save();
                    }
                }
            }
        }
        \common\components\Categories_Cache::get_cpc()::invalidate_categories((int) $categories_id);
        if ($popup == 1) {
            $this->view->categories_tree = $this->get_category_tree();
            if ($categories_id > 0) {
                $this->view->categories_opened_tree = \common\helpers\Categories::get_category_parents_ids($categories_id);
            } else {
                $this->view->categories_opened_tree = [];
            }
            $this->view->categories_closed_tree = array_map('intval', explode('|', \Yii::$app->session->get('closed_data')));
            $collapsed = $this->default_collapsed;
            return $this->render('cat_main_box', ['directOutput' => true, 'collapsed' => $collapsed]);
        }
        if ($message_stack->size() > 0) {
            $this->view->error_message = $message_stack->output(true);
            $this->view->error_message_type = $message_stack->message_type;
        }
        echo $this->render('error');
        echo '<script> window.location.href="' . Yii::$app->url_manager->create_url(['categories/categoryedit', 'categories_id' => $categories_id]) . '";</script>';
        //die();
        //return $this->actionCategoryedit();
    }
    public function action_bundle_search()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $q = Yii::$app->request->get_param('q');
        $prid = Yii::$app->request->get_param('prid', 0);
        $products_string = '';
        $products_query = tep_db_query('select distinct p.products_id, ' . Product_Name_Decorator::instance()->listing_query_expression('pd', '') . ' AS products_name, count(sp.sets_id) is_bundle_set from ' . TABLE_PRODUCTS . ' p left join ' . TABLE_SETS_PRODUCTS . ' sp on sp.sets_id = p.products_id, ' . TABLE_PRODUCTS_DESCRIPTION . " pd where p.products_id = pd.products_id and pd.language_id = '" . (int) $languages_id . "' and pd.platform_id = '" . intval(\common\classes\platform::default_id()) . "' and (p.products_model like '%" . tep_db_input($q) . "%' or pd.products_name like '%" . tep_db_input($q) . "%') and p.products_id <> '" . (int) $prid . "' group by p.products_id having is_bundle_set = 0 order by p.sort_order, pd.products_name");
        while ($products = tep_db_fetch_array($products_query)) {
            if (empty($products['products_name'])) {
                $products['products_name'] = \common\helpers\Product::get_products_name($products['products_id']);
            }
            $products_string .= '<option id="' . $products['products_id'] . '" value="prod_' . $products['products_id'] . '" style="COLOR:#555555">' . $products['products_name'] . '</option>';
        }
        echo json_encode(['tf' => '<select name="sets_select" size="16" style="width:100%">' . $products_string . '</select>']);
    }
    /* public function actionEditcategorypopup() {
    
          \common\helpers\Translation::init('admin/categories');
    
          $this->layout = false;
          return $this->render('editcategorypopup');
          } */
    public function action_delete_batch()
    {
        $this->layout = false;
        $current_categories_id = (int) Yii::$app->request->post('categories_id', 0);
        $items = Yii::$app->request->post('batch', []);
        if (is_array($items) && count($items) > 0) {
            foreach ($items as $item) {
                list($what, $id) = explode('_', $item, 2);
                if ($what == 'p' && $id > 0) {
                    if (\common\helpers\Acl::rule(['TABLE_HEADING_PRODUCTS', 'IMAGE_DELETE'])) {
                        $product_id = $id;
                        if (\common\helpers\Acl::check_extension_allowed('ReportChangesHistory')) {
                            $logger = new \common\extensions\Report_Changes_History\classes\Logger();
                            $before_object = new \common\api\Classes\Product();
                            $before_object->load($product_id);
                            $logger->set_before_object($before_object);
                            unset($before_object);
                        }
                        tep_db_query('delete from ' . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id = '" . (int) $product_id . "' and categories_id = '" . (int) $current_categories_id . "'");
                        $product_categories_query = tep_db_query('select count(*) as total from ' . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id = '" . (int) $product_id . "'");
                        $product_categories = tep_db_fetch_array($product_categories_query);
                        if ($product_categories['total'] == '0') {
                            \common\helpers\Product::remove_product($product_id);
                            if ($ext = \common\helpers\Acl::check_extension_allowed('SeoRedirectsNamed', 'allowed')) {
                                $ext::delete_product_links($product_id);
                            }
                        }
                        if (defined('USE_CACHE') && USE_CACHE == 'true') {
                            \common\helpers\System::reset_cache_block('categories');
                            \common\helpers\System::reset_cache_block('also_purchased');
                        }
                        if (isset($logger) && \common\helpers\Acl::check_extension_allowed('ReportChangesHistory')) {
                            $after_object = new \common\api\Classes\Product();
                            $after_object->load(0);
                            $logger->set_after_object($after_object);
                            unset($after_object);
                            $logger->run();
                        }
                    }
                } elseif ($what == 'c' && $id > 0) {
                    if (\common\helpers\Acl::rule(['TEXT_CATEGORIES', 'IMAGE_DELETE'])) {
                        $categories_id = $id;
                        if (\common\helpers\Acl::check_extension_allowed('ReportChangesHistory')) {
                            $logger = new \common\extensions\Report_Changes_History\classes\Logger();
                            $before_object = new \common\api\Classes\Category();
                            $before_object->load($categories_id);
                            $logger->set_before_object($before_object);
                            unset($before_object);
                        }
                        $categories = \common\helpers\Categories::get_category_tree($categories_id, '', '0', '', true);
                        $products = [];
                        $products_delete = [];
                        for ($i = 0, $n = sizeof($categories); $i < $n; $i++) {
                            $product_ids_query = tep_db_query('select products_id from ' . TABLE_PRODUCTS_TO_CATEGORIES . " where categories_id = '" . (int) $categories[$i]['id'] . "'");
                            while ($product_ids = tep_db_fetch_array($product_ids_query)) {
                                $products[$product_ids['products_id']]['categories'][] = $categories[$i]['id'];
                            }
                        }
                        foreach ($products as $key => $value) {
                            $category_ids = '';
                            for ($i = 0, $n = sizeof($value['categories']); $i < $n; $i++) {
                                $category_ids .= "'" . (int) $value['categories'][$i] . "', ";
                            }
                            $category_ids = substr($category_ids, 0, -2);
                            $check_query = tep_db_query('select count(*) as total from ' . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id = '" . (int) $key . "' and categories_id not in (" . $category_ids . ')');
                            $check = tep_db_fetch_array($check_query);
                            if ($check['total'] < '1') {
                                $products_delete[$key] = $key;
                            }
                        }
                        set_time_limit(0);
                        $sdn = \common\helpers\Acl::check_extension_allowed('SeoRedirectsNamed', 'allowed');
                        for ($i = 0, $n = sizeof($categories); $i < $n; $i++) {
                            \common\helpers\Categories::remove_category($categories[$i]['id'], false);
                            if ($sdn) {
                                $sdn::delete_category_links($categories[$i]['id']);
                            }
                        }
                        foreach ($products_delete as $key) {
                            \common\helpers\Product::remove_product($key);
                            if ($sdn) {
                                $sdn::delete_product_links($key);
                            }
                        }
                        if (USE_CACHE == 'true') {
                            \common\helpers\System::reset_cache_block('categories');
                            \common\helpers\System::reset_cache_block('also_purchased');
                        }
                        //It's not required as branch is deleted completely. Left, right are not concequent, but correct. It's very slow operation.
                        //\common\helpers\Categories::update_categories();
                    }
                }
            }
        }
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        Yii::$app->response->data = ['status' => 'ok'];
    }
    public function action_switch_status_batch()
    {
        $this->layout = false;
        $status = Yii::$app->request->post('state', 0);
        $status = $status ? 'true' : 'false';
        $items = Yii::$app->request->post('batch', []);
        if (is_array($items) && count($items) > 0) {
            foreach ($items as $item) {
                list($what, $id) = explode('_', $item, 2);
                if ($what == 'p') {
                    \common\helpers\Product::set_status((int) $id, $status == 'true' ? 1 : 0);
                } elseif ($what == 'c') {
                    \common\helpers\Categories::set_categories_status((int) $id, $status == 'true' ? 1 : 0);
                }
            }
        }
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        Yii::$app->response->data = ['status' => 'ok'];
    }
    public function action_switch_status()
    {
        $type = Yii::$app->request->post('type');
        $id = Yii::$app->request->post('id');
        $status = Yii::$app->request->post('status');
        switch ($type) {
            case 'products_status':
                \common\helpers\Product::set_status((int) $id, $status == 'true' ? 1 : 0);
                break;
            case 'categories_status':
                \common\helpers\Categories::set_categories_status((int) $id, $status == 'true' ? 1 : 0);
                break;
            default:
                break;
        }
        if (USE_CACHE == 'true') {
            \common\helpers\System::reset_cache_block('categories');
            \common\helpers\System::reset_cache_block('also_purchased');
        }
    }
    private function change_category_tree($categories = [], $parent_id = 0)
    {
        if (is_array($categories)) {
            foreach ($categories as $sort_order => $category) {
                if (isset($category['id'])) {
                    tep_db_query('update ' . TABLE_CATEGORIES . " set sort_order = '" . (int) $sort_order . "', parent_id = '" . (int) $parent_id . "' where categories_id = '" . (int) $category['id'] . "'");
                    if (isset($category['children'])) {
                        $this->change_category_tree($category['children'], $category['id']);
                    }
                }
            }
        }
    }
    /*
     * sort sub-categories, brands (manufacturers), products, re-arrange category tree.
     */
    public function action_sort_order()
    {
        global $login_id;
        $languages_id = \Yii::$app->settings->get('languages_id');
        $this->layout = false;
        //sort brands (manufacturers)
        if (isset($_POST['brands'])) {
            $brands = Yii::$app->request->post('brands');
            foreach ($brands as $key => $value) {
                tep_db_query('update ' . TABLE_MANUFACTURERS . " set sort_order = '" . $key . "' where manufacturers_id = '" . (int) $value . "'");
            }
        }
        //re-arrange category tree
        if (isset($_POST['categories'])) {
            $categories = Yii::$app->request->post('categories');
            $categories = stripslashes($categories);
            $categories = json_decode($categories, true);
            $this->change_category_tree($categories);
            if (USE_CACHE == 'true') {
                \common\helpers\System::reset_cache_block('categories');
                \common\helpers\System::reset_cache_block('also_purchased');
            }
            \common\helpers\Categories::update_categories();
        }
        if (isset($_GET['listing_type']) && $_GET['listing_type'] == 'category') {
            //sort sub-categories
            $parent_id = (int) Yii::$app->request->get('category_id', 0);
            $categories = Yii::$app->request->post('category');
            if (is_array($categories)) {
                $order_by_category = 'c.sort_order, cd.categories_name';
                $search_condition = ' where 1 ';
                $search_condition .= " and c.parent_id='" . $parent_id . "'";
                $categories_query_raw = 'select distinct(c.categories_id), cd.categories_name, c.categories_status from ' . TABLE_CATEGORIES . ' c left join ' . TABLE_CATEGORIES_DESCRIPTION . ' cd on c.categories_id=cd.categories_id ' . $search_condition . " and cd.language_id = '" . (int) $languages_id . "' and cd.affiliate_id = 0 " . ' order by ' . $order_by_category;
                $categories_query = tep_db_query($categories_query_raw);
                $sort_order = 0;
                $offsets = array_flip($categories);
                $grid_offset = 0;
                while ($category = tep_db_fetch_array($categories_query)) {
                    $category_id = $category['categories_id'];
                    if (isset($offsets[$category_id])) {
                        tep_db_query('update ' . TABLE_CATEGORIES . " set sort_order = '" . (int) ($sort_order + $offsets[$category_id]) . "' where parent_id = '" . $parent_id . "'  and categories_id = '" . (int) $category_id . "'");
                        $grid_offset++;
                    } else {
                        $sort_order += $grid_offset;
                        $grid_offset = 0;
                        tep_db_query('update ' . TABLE_CATEGORIES . " set sort_order = '" . (int) $sort_order . "' where parent_id = '" . $parent_id . "'  and categories_id = '" . (int) $category_id . "'");
                        $sort_order++;
                    }
                }
                /// its impossible to link category to another branch now.
                /// update all tree if it's changed.
                \common\helpers\Categories::update_categories($parent_id);
            }
            //sort products within category
            $products = Yii::$app->request->post('product');
            if (is_array($products)) {
                $order_by_product = 'p2c.sort_order, pd.products_name';
                $products_query_raw = 'select p.products_id from ' . TABLE_PRODUCTS . ' p, ' . TABLE_PRODUCTS_DESCRIPTION . ' pd, ' . TABLE_PRODUCTS_TO_CATEGORIES . " p2c where p.products_id = pd.products_id and pd.language_id = '" . (int) $languages_id . "' and p.products_id = p2c.products_id " . (tep_session_is_registered('login_vendor') ? " and p.vendor_id = '" . $login_id . "'" : '') . " and pd.platform_id = '" . intval(\common\classes\platform::default_id()) . "' and p2c.categories_id = '" . (int) $parent_id . "' order by " . $order_by_product;
                $products_query = tep_db_query($products_query_raw);
                $sort_order = 0;
                $offsets = array_flip($products);
                $grid_offset = 0;
                while ($product = tep_db_fetch_array($products_query)) {
                    $product_id = $product['products_id'];
                    if (isset($offsets[$product_id])) {
                        tep_db_query('update ' . TABLE_PRODUCTS_TO_CATEGORIES . " set sort_order = '" . (int) ($sort_order + $offsets[$product_id]) . "' where categories_id = '" . (int) $parent_id . "'  and products_id = '" . (int) $product_id . "'");
                        $grid_offset++;
                    } else {
                        $sort_order += $grid_offset;
                        $grid_offset = 0;
                        tep_db_query('update ' . TABLE_PRODUCTS_TO_CATEGORIES . " set sort_order = '" . (int) $sort_order . "' where categories_id = '" . (int) $parent_id . "'  and products_id = '" . (int) $product_id . "'");
                        $sort_order++;
                    }
                }
            }
            $this->view->categories_tree = $this->get_category_tree();
            if ($parent_id > 0) {
                $this->view->categories_opened_tree = \common\helpers\Categories::get_category_parents_ids($parent_id);
            } else {
                $this->view->categories_opened_tree = [];
            }
            $this->view->categories_closed_tree = array_diff(array_map('intval', explode('|', \Yii::$app->session->get('closed_data'))), $this->view->categories_opened_tree);
            $collapsed = $this->default_collapsed;
            return $this->render('cat_main_box', ['directOutput' => true, 'collapsed' => $collapsed]);
        }
        if (isset($_GET['listing_type']) && $_GET['listing_type'] == 'brand') {
            //sort products within brand
            $brand_id = Yii::$app->request->get('brand_id');
            $products = Yii::$app->request->post('product');
            if (is_array($products)) {
                $ff = '';
                $order = 'p.sort_order, pd.products_name';
                $products_query_raw = 'select p.products_id from ' . TABLE_PRODUCTS . ' p ' . (intval($brand_id) == -1 ? ' left join ' . TABLE_MANUFACTURERS . ' m ON m.manufacturers_id=p.manufacturers_id ' : '') . ' left join ' . TABLE_PRODUCTS_DESCRIPTION . " pd on (p.products_id = pd.products_id and pd.language_id='" . intval($languages_id) . "') where pd.platform_id = '" . intval(\common\classes\platform::default_id()) . "' " . (intval($brand_id) > 0 ? " and manufacturers_id = '" . intval($brand_id) . "' " : (intval($brand_id) == -1 ? ' and m.manufacturers_id IS NULL' : '')) . $ff . ' group by p.products_id ORDER BY ' . $order;
                $products_query = tep_db_query($products_query_raw);
                $sort_order = 0;
                $offsets = array_flip($products);
                $grid_offset = 0;
                while ($product = tep_db_fetch_array($products_query)) {
                    $product_id = $product['products_id'];
                    if (isset($offsets[$product_id])) {
                        tep_db_query('update ' . TABLE_PRODUCTS . " set sort_order = '" . (int) ($sort_order + $offsets[$product_id]) . "' where products_id = '" . (int) $product_id . "'");
                        $grid_offset++;
                    } else {
                        $sort_order += $grid_offset;
                        $grid_offset = 0;
                        tep_db_query('update ' . TABLE_PRODUCTS . " set sort_order = '" . (int) $sort_order . "' where products_id = '" . (int) $product_id . "'");
                        $sort_order++;
                    }
                }
            }
        }
    }
    public function action_copy_move()
    {
        $this->layout = false;
        $type = Yii::$app->request->post('type');
        $cat_updated = false;
        switch ($type) {
            case 'mixed':
                $items = Yii::$app->request->post('batch', []);
                if (is_array($items) && count($items) > 0) {
                    $current_category_id = Yii::$app->request->post('current_category_id');
                    //где мы
                    $c_i_ds = Yii::$app->request->post('categories_id');
                    //куда
                    $copy_to = Yii::$app->request->post('copy_to');
                    $copy_attributes = Yii::$app->request->post('copy_attributes');
                    if ($c_i_ds && !is_array($c_i_ds)) {
                        $c_i_ds = [$c_i_ds];
                    }
                    foreach ($c_i_ds as $categories_id) {
                        foreach ($items as $item) {
                            list($what, $id) = explode('_', $item, 2);
                            if ($what == 'p') {
                                switch ($copy_to) {
                                    case 'move':
                                        $in_p2c = \common\models\Products2Categories::find()->where(['products_id' => (int) $id])->select(['categories_id'])->column();
                                        $p2c = \common\models\Products2Categories::find_one(['products_id' => (int) $id, 'categories_id' => (int) $categories_id]);
                                        if ($current_category_id > 0 && !$p2c && $current_category_id != (int) $categories_id) {
                                            tep_db_query('update ' . TABLE_PRODUCTS_TO_CATEGORIES . " set categories_id = '" . (int) $categories_id . "' where products_id = '" . (int) $id . "' and categories_id = '" . (int) $current_category_id . "'");
                                        } else if (count($in_p2c) == 1) {
                                            tep_db_query('update ' . TABLE_PRODUCTS_TO_CATEGORIES . " set categories_id = '" . (int) $categories_id . "' where products_id = '" . (int) $id . "' and categories_id = '" . (int) $in_p2c[0] . "'");
                                        } else {
                                            try {
                                                $p2c = new \common\models\Products2Categories();
                                                $p2c->categories_id = (int) $categories_id;
                                                $p2c->products_id = (int) $id;
                                                $p2c->sort_order = 0;
                                                $p2c->save();
                                            } catch (\Exception $e) {
                                                \Yii::warning($e->get_message());
                                            }
                                        }
                                        break;
                                    case 'link':
                                        $check_query = tep_db_query('select count(*) as total from ' . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id = '" . (int) $id . "' and categories_id = '" . (int) $categories_id . "'");
                                        $check = tep_db_fetch_array($check_query);
                                        if ($check['total'] < '1') {
                                            tep_db_query('insert into ' . TABLE_PRODUCTS_TO_CATEGORIES . " (products_id, categories_id) values ('" . (int) $id . "', '" . (int) $categories_id . "')");
                                        }
                                        break;
                                    case 'dublicate':
                                        \common\helpers\Product::duplicate($id, $categories_id, $copy_attributes);
                                        break;
                                }
                            } elseif ($what == 'c') {
                                if ($id != $categories_id) {
                                    $cat = \common\models\Categories::find_one((int) $id);
                                    if ($categories_id > 0) {
                                        $cat_to = \common\models\Categories::find_one((int) $categories_id);
                                    }
                                    if ($cat && ($categories_id == 0 || $cat_to)) {
                                        $cat->parent_id = $categories_id;
                                        try {
                                            if ($categories_id > 0) {
                                                $cat->append_to($cat_to);
                                            }
                                            $cat->save();
                                            $cat_updated = true;
                                        } catch (\Exception $ex) {
                                            \Yii::warning("{$id} => {$categories_id} " . $ex->get_message());
                                        }
                                    }
                                }
                            }
                        }
                    }
                    if ($cat_updated) {
                        \common\helpers\Categories::update_categories(0);
                    }
                    if (USE_CACHE == 'true') {
                        \common\helpers\System::reset_cache_block('categories');
                        \common\helpers\System::reset_cache_block('also_purchased');
                    }
                    $this->view->categories_tree = $this->get_category_tree();
                    $this->view->categories_opened_tree = [];
                    $this->view->categories_closed_tree = array_diff(array_map('intval', explode('|', \Yii::$app->session->get('closed_data'))), $this->view->categories_opened_tree);
                    return $this->render('cat_main_box', ['directOutput' => true, 'collapsed' => $this->default_collapsed]);
                }
                break;
            case 'product':
                $copy_to = Yii::$app->request->post('copy_to');
                $products_id = Yii::$app->request->post('products_id');
                $c_i_ds = Yii::$app->request->post('categories_id');
                if ($c_i_ds && !is_array($c_i_ds)) {
                    $c_i_ds = [$c_i_ds];
                }
                foreach ($c_i_ds as $categories_id) {
                    switch ($copy_to) {
                        case 'move':
                            $current_category_id = Yii::$app->request->post('current_category_id');
                            if (!\common\models\Products2Categories::find_one(['products_id' => (int) $products_id, 'categories_id' => (int) $categories_id])) {
                                if ('0' === $current_category_id || $current_category_id > 0) {
                                    tep_db_query('update ' . TABLE_PRODUCTS_TO_CATEGORIES . " set categories_id = '" . (int) $categories_id . "' where products_id = '" . (int) $products_id . "' and categories_id = '" . (int) $current_category_id . "'");
                                } else {
                                    // extra link from search
                                    try {
                                        $p2c = new \common\models\Products2Categories();
                                        $p2c->categories_id = (int) $categories_id;
                                        $p2c->products_id = (int) $products_id;
                                        $p2c->sort_order = 0;
                                        $p2c->save();
                                    } catch (\Exception $e) {
                                        \Yii::warning($e->get_message());
                                    }
                                }
                            }
                            break;
                        case 'link':
                            $check_query = tep_db_query('select count(*) as total from ' . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id = '" . (int) $products_id . "' and categories_id = '" . (int) $categories_id . "'");
                            $check = tep_db_fetch_array($check_query);
                            if ($check['total'] < '1') {
                                tep_db_query('insert into ' . TABLE_PRODUCTS_TO_CATEGORIES . " (products_id, categories_id) values ('" . (int) $products_id . "', '" . (int) $categories_id . "')");
                            }
                            break;
                        case 'dublicate':
                            $copy_attributes = Yii::$app->request->post('copy_attributes');
                            \common\helpers\Product::duplicate($products_id, $categories_id, $copy_attributes);
                            break;
                    }
                }
                if (USE_CACHE == 'true') {
                    \common\helpers\System::reset_cache_block('categories');
                    \common\helpers\System::reset_cache_block('also_purchased');
                }
                break;
            case 'category':
                $c_i_ds = Yii::$app->request->post('categories_id');
                $parent_id = Yii::$app->request->post('parent_id');
                if ($c_i_ds && !is_array($c_i_ds)) {
                    $c_i_ds = [$c_i_ds];
                }
                foreach ($c_i_ds as $categories_id) {
                    if ($categories_id != $parent_id) {
                        //    tep_db_query("update " . TABLE_CATEGORIES . " set parent_id = '" . (int) $parent_id . "' where categories_id = '" . (int) $categories_id . "'");
                        $cat = \common\models\Categories::find_one((int) $categories_id);
                        if ($parent_id > 0) {
                            $cat_to = \common\models\Categories::find_one((int) $parent_id);
                        }
                        if ($cat && ($parent_id == 0 || $cat_to)) {
                            $cat->parent_id = $parent_id;
                            try {
                                if ($parent_id > 0) {
                                    $cat->append_to($cat_to);
                                }
                                $cat->save();
                            } catch (\Exception $ex) {
                                \Yii::warning("{$categories_id} => {$parent_id} " . $ex->get_message());
                            }
                        }
                    }
                }
                if ($parent_id == 0) {
                    \common\helpers\Categories::update_categories(0);
                }
                $this->view->categories_tree = $this->get_category_tree();
                if ($c_i_ds[0] > 0) {
                    $this->view->categories_opened_tree = \common\helpers\Categories::get_category_parents_ids($categories_id);
                } else {
                    $this->view->categories_opened_tree = [];
                }
                $this->view->categories_closed_tree = array_diff(array_map('intval', explode('|', \Yii::$app->session->get('closed_data'))), $this->view->categories_opened_tree);
                $collapsed = $this->default_collapsed;
                return $this->render('cat_main_box', ['directOutput' => true, 'collapsed' => $collapsed]);
                break;
            case 'brand':
                // products_id brand_id
                $brand_id = Yii::$app->request->post('brand_id');
                $product_id = Yii::$app->request->post('products_id');
                if ($brand_id >= 0) {
                    tep_db_query('update ' . TABLE_PRODUCTS . " set manufacturers_id = '" . (int) $brand_id . "' where products_id \t = '" . (int) $product_id . "'");
                } else {
                    tep_db_query('update ' . TABLE_PRODUCTS . " set manufacturers_id = NULL where products_id \t = '" . (int) $product_id . "'");
                }
                break;
            default:
                break;
        }
    }
    /**
     * Autocomplette
     */
    public function action_brands()
    {
        $term = tep_db_prepare_input(Yii::$app->request->get('term'));
        $with = Yii::$app->request->get('with', '');
        $search = '1';
        if (!empty($term)) {
            $search = "manufacturers_name like '%" . tep_db_input($term) . "%'";
        }
        $brands = [];
        if (!empty($with)) {
            $brands_query = tep_db_query('select manufacturers_id as id, manufacturers_name as `value` from ' . TABLE_MANUFACTURERS . ' where ' . $search . ' order by manufacturers_name');
            while ($response = tep_db_fetch_array($brands_query)) {
                $response['text'] = $response['value'];
                $brands[] = $response;
            }
        } else {
            $brands_query = tep_db_query('select manufacturers_name  from ' . TABLE_MANUFACTURERS . ' where ' . $search . ' group by manufacturers_name order by manufacturers_name');
            while ($response = tep_db_fetch_array($brands_query)) {
                $brands[] = $response['manufacturers_name'];
            }
        }
        echo json_encode($brands);
    }
    public function action_suppliers()
    {
        $term = tep_db_prepare_input(Yii::$app->request->get('term'));
        $search = '1';
        if (!empty($term)) {
            $search = "suppliers_name like '%" . tep_db_input($term) . "%'";
        }
        $suppliers = [];
        $suppliers_query = tep_db_query('select suppliers_name  from ' . TABLE_SUPPLIERS . ' where ' . $search . ' group by suppliers_name order by suppliers_name');
        while ($response = tep_db_fetch_array($suppliers_query)) {
            $suppliers[] = $response['suppliers_name'];
        }
        echo json_encode($suppliers);
    }
    public function action_brandedit()
    {
        if (false === \common\helpers\Acl::rule(['TEXT_LABEL_BRAND', 'IMAGE_EDIT'])) {
            $this->redirect(\yii\helpers\Url::to_route('categories/'));
        }
        $languages_id = \Yii::$app->settings->get('languages_id');
        $this->view->use_popup_mode = false;
        if (Yii::$app->request->is_ajax) {
            $this->layout = false;
            $this->view->use_popup_mode = true;
        }
        \common\helpers\Translation::init('admin/categories');
        $popup = 0;
        if (Yii::$app->request->is_post) {
            $manufacturers_id = (int) Yii::$app->request->get_body_param('manufacturers_id');
            $popup = (int) Yii::$app->request->post('popup');
            if ($popup == 0) {
                $this->view->use_popup_mode = false;
            }
        } else {
            $manufacturers_id = (int) Yii::$app->request->get('manufacturers_id');
        }
        $this->view->content_already_loaded = $popup;
        $manufacturers = [];
        if ($manufacturers_id > 0) {
            $manufacturers_query_raw = 'select * from ' . TABLE_MANUFACTURERS . "  where manufacturers_id = '" . $manufacturers_id . "'";
            $manufacturers_query = tep_db_query($manufacturers_query_raw);
            $manufacturers = tep_db_fetch_array($manufacturers_query);
        }
        \common\helpers\Php8::null_arr_props($manufacturers, ['manufacturers_id', 'maps_id', 'manufacturers_name', 'manufacturers_image', 'manufacturers_image_2', 'stock_limit', 'mapsId', 'mapsImage', 'mapsTitle', 'brand_id']);
        /**
         * @var $imageMaps \common\extensions\ImageMaps\models\ImageMaps
         */
        if ($image_maps = \common\helpers\Extensions::get_model('ImageMaps', 'ImageMaps')) {
            if ($manufacturers['maps_id'] && !empty($image_maps)) {
                if ($map = $image_maps::find_one($manufacturers['maps_id'])) {
                    $manufacturers['mapsId'] = $manufacturers['maps_id'];
                    $manufacturers['mapsImage'] = $map->image;
                    $manufacturers['mapsTitle'] = $map->get_title($languages_id);
                }
            }
        }
        $m_info = new \Object_Info($manufacturers);
        if ($m_info->manufacturers_image) {
            $image_path = DIR_WS_CATALOG_IMAGES . $m_info->manufacturers_image;
        }
        if ($m_info->manufacturers_image_2) {
            $image_path = DIR_WS_CATALOG_IMAGES . $m_info->manufacturers_image_2;
        }
        $m_description = [];
        $languages = \common\helpers\Language::get_languages();
        for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
            $languages[$i]['logo'] = $languages[$i]['image'];
            $m_description[$i]['code'] = $languages[$i]['code'];
            $m_description[$i]['languageId'] = $languages[$i]['id'];
            $m_description[$i]['manufacturers_url'] = tep_draw_input_field('manufacturers_url[' . $languages[$i]['id'] . ']', \common\helpers\Manufacturers::get_manufacturer_url($m_info->manufacturers_id, $languages[$i]['id']), 'class="form-control"');
            $m_description[$i]['manufacturers_seo_name'] = tep_draw_input_field('manufacturers_seo_name[' . $languages[$i]['id'] . ']', \common\helpers\Manufacturers::get_manufacturer_seo_name($m_info->manufacturers_id, $languages[$i]['id']), 'class="form-control"');
            $m_description[$i]['manufacturers_meta_description'] = tep_draw_textarea_field('manufacturers_meta_description[' . $languages[$i]['id'] . ']', 'soft', '25', '7', \common\helpers\Manufacturers::get_manufacturer_meta_descr($m_info->manufacturers_id, $languages[$i]['id']), 'class="form-control"');
            //            $mDescription[$i]['manufacturers_description'] = tep_draw_textarea_field('manufacturers_description[' . $languages[$i]['id'] . ']', 'soft', '25', '7', \common\helpers\Manufacturers::getManufacturerDescription($mInfo->manufacturers_id, $languages[$i]['id']), 'class="form-control ckeditor text-dox-02" id="txt_brand_description_'.$languages[$i]['id'].'"');
            $m_description[$i]['manufacturers_description'] = \common\helpers\Html::textarea('manufacturers_description[' . $languages[$i]['id'] . ']', \common\helpers\Manufacturers::get_manufacturer_description($m_info->manufacturers_id, $languages[$i]['id']), ['wrap' => 'soft', 'cols' => '25', 'rows' => '7', 'class' => 'form-control ckeditor text-dox-02', 'id' => 'txt_brand_description_' . $languages[$i]['id']]);
            $m_description[$i]['manufacturers_meta_key'] = tep_draw_textarea_field('manufacturers_meta_key[' . $languages[$i]['id'] . ']', 'soft', '25', '7', \common\helpers\Manufacturers::get_manufacturer_meta_key($m_info->manufacturers_id, $languages[$i]['id']), 'class="form-control"');
            $m_description[$i]['manufacturers_meta_title'] = tep_draw_input_field('manufacturers_meta_title[' . $languages[$i]['id'] . ']', \common\helpers\Manufacturers::get_manufacturer_meta_title($m_info->manufacturers_id, $languages[$i]['id']), 'class="form-control"');
            $m_description[$i]['manufacturers_h1_tag'] = tep_draw_input_field('manufacturers_h1_tag[' . $languages[$i]['id'] . ']', \common\helpers\Manufacturers::get_manufacturers_h1_tag($m_info->manufacturers_id, $languages[$i]['id']), 'class="form-control"');
            $m_description[$i]['manufacturers_h2_tag'] = \common\helpers\Manufacturers::get_manufacturers_h2_tag($m_info->manufacturers_id, $languages[$i]['id']);
            //tep_draw_input_field('manufacturers_h2_tag[' . $languages[$i]['id'] . ']', \common\helpers\Manufacturers::get_manufacturers_h2_tag($mInfo->manufacturers_id, $languages[$i]['id']), 'class="form-control"');
            $m_description[$i]['manufacturers_h3_tag'] = \common\helpers\Manufacturers::get_manufacturers_h3_tag($m_info->manufacturers_id, $languages[$i]['id']);
            //tep_draw_input_field('manufacturers_h3_tag[' . $languages[$i]['id'] . ']', \common\helpers\Manufacturers::get_manufacturers_h3_tag($mInfo->manufacturers_id, $languages[$i]['id']), 'class="form-control"');
        }
        // {{ suppliers
        $supplier_rules = new \backend\models\Suppliers_Rules();
        if ($manufacturers_id) {
            $brand_obj = \common\models\Manufacturers::find_one(['manufacturers_id' => $manufacturers_id]);
        } else {
            $brand_obj = new \common\models\Manufacturers();
        }
        $supplier_rules->get_manufacturer_data($brand_obj, $m_info);
        // }} suppliers
        $this->selected_menu = ['catalog', 'categories'];
        $text_new_or_edit = $manufacturers_id == 0 ? TEXT_INFO_HEADING_NEW_BRAND : TEXT_INFO_HEADING_EDIT_BRAND;
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('categories/index'), 'title' => $text_new_or_edit];
        if ((int) $m_info->stock_limit < 0) {
            $m_info->stock_limit = (int) ADDITIONAL_STOCK_LIMIT;
        } else {
            $m_info->stock_limit_on = true;
        }
        return $this->render('brandedit', ['manufacturers_id' => $manufacturers_id, 'mInfo' => $m_info, 'languages' => $languages, 'mDescription' => $m_description]);
    }
    public function action_brand_submit()
    {
        \common\helpers\Translation::init('admin/manufacturers');
        $this->layout = false;
        $error = false;
        $message = '';
        $script = '';
        $message_type = 'success';
        $popup = (int) Yii::$app->request->post('popup');
        $manufacturers_id = (int) Yii::$app->request->post('manufacturers_id');
        $maps_id = (int) Yii::$app->request->post('maps_id', 0);
        $manufacturers_name = tep_db_prepare_input(Yii::$app->request->post('manufacturers_name'));
        $manufacturers_url = Yii::$app->request->post('manufacturers_url');
        $manufacturers_old_seo_page_name = Yii::$app->request->post('manufacturers_old_seo_page_name');
        $manufacturers_meta_title = Yii::$app->request->post('manufacturers_meta_title');
        $manufacturers_meta_description = Yii::$app->request->post('manufacturers_meta_description');
        $manufacturers_description = Yii::$app->request->post('manufacturers_description');
        $manufacturers_meta_key = Yii::$app->request->post('manufacturers_meta_key');
        $manufacturers_h1_tag = Yii::$app->request->post('manufacturers_h1_tag');
        $manufacturers_h2_tag = Yii::$app->request->post('manufacturers_h2_tag');
        $manufacturers_h3_tag = Yii::$app->request->post('manufacturers_h3_tag');
        $manufacturers_seo_name = Yii::$app->request->post('manufacturers_seo_name');
        $sql_data_array = ['manufacturers_name' => $manufacturers_name];
        // Moved to SeoRedirectsNamed
        // $sql_data_array['manufacturers_old_seo_page_name'] = $manufacturers_old_seo_page_name;
        $sql_data_array['maps_id'] = $maps_id;
        $sql_data_array['stock_limit'] = (int) Yii::$app->request->post('stock_limit', -1);
        $action = '';
        if ($error === false) {
            if ($manufacturers_id > 0) {
                // Update
                $action = 'update';
                $update_sql_data = ['last_modified' => 'now()'];
                $sql_data_array = array_merge($sql_data_array, $update_sql_data);
                tep_db_perform(TABLE_MANUFACTURERS, $sql_data_array, 'update', "manufacturers_id = '" . (int) $manufacturers_id . "'");
                $message = TEXT_INFO_UPDATED;
            } else {
                // Insert
                $action = 'insert';
                $insert_sql_data = ['date_added' => 'now()'];
                $sql_data_array = array_merge($sql_data_array, $insert_sql_data);
                tep_db_perform(TABLE_MANUFACTURERS, $sql_data_array);
                $manufacturers_id = (int) tep_db_insert_id();
                Yii::$app->request->set_body_params(array_merge(Yii::$app->request->get_body_params(), ['manufacturers_id' => $manufacturers_id, 'popup' => $popup]));
                if ($manufacturers_id > 0) {
                    $script = '
                     <script type="text/javascript">
                        setTimeout(function(data){
                            $("form[name=save_manufacturer_form] input[name=manufacturers_id]").val(' . $manufacturers_id . ');
                        }, 500);
                     </script>
                    ';
                }
                $message = TEXT_INFO_SAVED;
            }
            if ($ext = \common\helpers\Acl::check_extension_allowed('SeoRedirectsNamed', 'allowed')) {
                $ext::save_brand_links($manufacturers_id, $_POST);
            }
        }
        $languages = \common\helpers\Language::get_languages();
        for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
            $manufacturers_url_array = $manufacturers_url;
            $language_id = $languages[$i]['id'];
            if (!tep_not_null($manufacturers_seo_name[$language_id])) {
                $manufacturers_seo_name[$language_id] = Seo::make_slug($manufacturers_name);
            }
            $check_seo = tep_db_fetch_array(tep_db_query('SELECT count(*) AS c FROM ' . TABLE_MANUFACTURERS_INFO . " WHERE manufacturers_id != '" . (int) $manufacturers_id . "' AND manufacturers_seo_name='" . tep_db_input($manufacturers_seo_name[$language_id]) . "'"));
            if ($check_seo['c'] > 0) {
                $manufacturers_seo_name[$language_id] = trim($manufacturers_seo_name[$language_id] . '-' . $manufacturers_id, '-');
            }
            $sql_data_array = ['manufacturers_url' => tep_db_prepare_input($manufacturers_url_array[$language_id]), 'manufacturers_meta_description' => tep_db_prepare_input($manufacturers_meta_description[$language_id]), 'manufacturers_description' => tep_db_prepare_input($manufacturers_description[$language_id]), 'manufacturers_meta_key' => tep_db_prepare_input($manufacturers_meta_key[$language_id]), 'manufacturers_meta_title' => tep_db_prepare_input($manufacturers_meta_title[$language_id]), 'manufacturers_h1_tag' => tep_db_prepare_input($manufacturers_h1_tag[$language_id]), 'manufacturers_h2_tag' => tep_db_prepare_input(is_array($manufacturers_h2_tag[$language_id]) ? implode("\n", $manufacturers_h2_tag[$language_id]) : $manufacturers_h2_tag[$language_id]), 'manufacturers_h3_tag' => tep_db_prepare_input(is_array($manufacturers_h3_tag[$language_id]) ? implode("\n", $manufacturers_h3_tag[$language_id]) : $manufacturers_h3_tag[$language_id]), 'manufacturers_seo_name' => tep_db_prepare_input($manufacturers_seo_name[$language_id])];
            $_check_info_exists_r = tep_db_query('SELECT * ' . 'FROM ' . TABLE_MANUFACTURERS_INFO . ' ' . "WHERE manufacturers_id = '" . (int) $manufacturers_id . "' and languages_id = '" . (int) $language_id . "'");
            if (tep_db_num_rows($_check_info_exists_r) == 0) {
                $insert_sql_data = ['manufacturers_id' => $manufacturers_id, 'languages_id' => $language_id];
                $sql_data_array = array_merge($sql_data_array, $insert_sql_data);
                tep_db_perform(TABLE_MANUFACTURERS_INFO, $sql_data_array);
            } else {
                $_info_exist = tep_db_fetch_array($_check_info_exists_r);
                tep_db_perform(TABLE_MANUFACTURERS_INFO, $sql_data_array, 'update', "manufacturers_id = '" . (int) $manufacturers_id . "' and languages_id = '" . (int) $language_id . "'");
                if ($ext = \common\helpers\Acl::check_extension_allowed('SeoRedirectsNamed', 'allowed')) {
                    $ext::track_brand_links($manufacturers_id, $language_id, null, $sql_data_array, $_info_exist);
                }
            }
        }
        Manufacturers::save_image($manufacturers_id, '', 'gallery');
        Manufacturers::save_image($manufacturers_id, '_2', 'hero');
        foreach (\common\helpers\Hooks::get_list('categories/brandedit') as $filename) {
            include $filename;
        }
        $supplier_rules = new \backend\models\Suppliers_Rules();
        $manufacturer_object = \common\models\Manufacturers::find_one($manufacturers_id);
        $supplier_rules->save_manufacturers_data($manufacturer_object, Yii::$app->request->post('suppliers_data', []));
        if (defined('USE_CACHE') && USE_CACHE == 'true') {
            \common\helpers\System::reset_cache_block('manufacturers');
        }
        if ($error === true) {
            $message_type = 'warning';
            if ($message == '') {
                $message = WARN_UNKNOWN_ERROR;
            }
        }
        if ($popup == 1) {
            $this->view->brands_list = $this->get_brands_list();
            return $this->render('brand_box');
        }
        ?>
        <div class="popup-box-wrap pop-mess">
            <div class="around-pop-up"></div>
            <div class="popup-box">
                <div class="pop-up-close pop-up-close-alert"></div>
                <div class="pop-up-content">
                    <div class="popup-heading"><?php 
        echo TEXT_NOTIFIC;
        ?></div>
                    <div class="popup-content pop-mess-cont pop-mess-cont-<?php 
        echo $message_type;
        ?>">
        <?php 
        echo $message;
        ?>
        <?php 
        echo $script;
        ?>
                    </div>
                </div>
                <div class="noti-btn">
                    <div></div>
                    <div><a href="javascript:void(0)" class="btn btn-primary" onClick="return backStatement();"><?php 
        echo TEXT_BTN_OK;
        ?></a></div>
                </div>
            </div>
            <script>
                $('body').scrollTop(0);
                /* $('.pop-mess .pop-up-close-alert, .noti-btn .btn').click(function(){
                 $(this).parents('.pop-mess').remove();
                 }); */
            </script>
        </div>

        <?php 
        return $this->action_brandedit();
    }
    public function action_confirm_manufacturer_delete()
    {
        \common\helpers\Translation::init('admin/manufacturers');
        \common\helpers\Translation::init('admin/faqdesk');
        $this->layout = false;
        $manufacturers_id = Yii::$app->request->get('manufacturers_id');
        $message = '';
        $manufacturers_query_raw = 'select manufacturers_id, manufacturers_name, manufacturers_image, date_added, last_modified from ' . TABLE_MANUFACTURERS . " where  manufacturers_id = '{$manufacturers_id}' ";
        $manufacturers_query = tep_db_query($manufacturers_query_raw);
        while ($manufacturers = tep_db_fetch_array($manufacturers_query)) {
            $manufacturer_products_query = tep_db_query('select count(*) as products_count from ' . TABLE_PRODUCTS . " where manufacturers_id = '" . (int) $manufacturers['manufacturers_id'] . "'");
            $manufacturer_products = tep_db_fetch_array($manufacturer_products_query);
            $m_info_array = array_merge($manufacturers, $manufacturer_products);
            $m_info = new \Object_Info($m_info_array);
        }
        if ($m_info->products_count > 0) {
            $message_type = 'warning';
            $message = sprintf(TEXT_DELETE_WARNING_PRODUCTS, $m_info->products_count);
            ?>
            <div class="popup-box-wrap pop-mess">
                <div class="around-pop-up"></div>
                <div class="popup-box">
                    <div class="pop-up-close pop-up-close-alert"></div>
                    <div class="pop-up-content">
                        <div class="popup-heading"><?php 
            echo TEXT_NOTIFIC;
            ?></div>
                        <div class="popup-content pop-mess-cont pop-mess-cont-<?php 
            echo $message_type;
            ?>">
            <?php 
            echo $message;
            ?>
                        </div>
                    </div>
                    <div class="noti-btn">
                        <div></div>
                        <div><span class="btn btn-primary"><?php 
            echo TEXT_BTN_OK;
            ?></span></div>
                    </div>
                </div>
                <script>
                    $('body').scrollTop(0);
                    $('.pop-mess .pop-up-close-alert, .noti-btn .btn').click(function () {
                        $(this).parents('.pop-mess').remove();
                    });
                </script>
            </div>

            <?php 
        }
        echo '<div class="brand_pad">';
        echo tep_draw_form('manufacturer_delete', FILENAME_MANUFACTURERS, \common\helpers\Output::get_all_get_params(['action']) . 'action=update', 'post', 'id="manufacturer_delete" onSubmit="return deleteManufacturer();"');
        echo '<div class="or_box_head">' . TEXT_HEADING_DELETE_MANUFACTURER . '</div>';
        echo '<div class="col_desc">' . TEXT_DELETE_MANUFACTURER . ' <b>' . $m_info->manufacturers_name . '</b></div>';
        //echo '<div class="check_linear">' . tep_draw_checkbox_field('delete_image', '', TRUE) . ' <span>' . TEXT_DELETE_IMAGE . '</span></div>';
        ?>
        <div class="btn-toolbar btn-toolbar-order">
        <?php 
        echo '<button class="btn btn-delete btn-no-margin">' . IMAGE_DELETE . '</button>';
        echo '<input type="button" class="btn btn-cancel" value="' . IMAGE_CANCEL . '" onClick="return closePopup()">';
        echo tep_draw_hidden_field('manufacturers_id', $manufacturers_id);
        ?>
        </div>
        </form>
        </div>
        <?php 
    }
    //manufacturer-delete
    public function action_manufacturer_delete()
    {
        \common\helpers\Translation::init('admin/manufacturers');
        $this->layout = false;
        $manufacturers_id = (int) Yii::$app->request->post('manufacturers_id');
        $delete_products = Yii::$app->request->post('delete_products');
        $brand_folder = DIR_FS_CATALOG_IMAGES . 'brands' . DIRECTORY_SEPARATOR . $manufacturers_id;
        \yii\helpers\File_Helper::remove_directory($brand_folder);
        tep_db_query('delete from ' . TABLE_MANUFACTURERS . " where manufacturers_id = '" . (int) $manufacturers_id . "'");
        tep_db_query('delete from ' . TABLE_MANUFACTURERS_INFO . " where manufacturers_id = '" . (int) $manufacturers_id . "'");
        if ((int) $manufacturers_id > 0) {
            tep_db_query('delete from ' . TABLE_FILTERS . " where manufacturers_id = '" . (int) $manufacturers_id . "'");
        }
        if ($ext = \common\helpers\Acl::check_extension_allowed('SeoRedirectsNamed', 'allowed')) {
            $ext::delete_brand_links($manufacturers_id);
        }
        if (isset($delete_products) && $delete_products == 'on') {
            $products_query = tep_db_query('select products_id from ' . TABLE_PRODUCTS . " where manufacturers_id = '" . (int) $manufacturers_id . "'");
            while ($products = tep_db_fetch_array($products_query)) {
                \common\helpers\Product::remove_product($products['products_id']);
            }
        } else {
            tep_db_query('update ' . TABLE_PRODUCTS . " set manufacturers_id = '' where manufacturers_id = '" . (int) $manufacturers_id . "'");
        }
        if (USE_CACHE == 'true') {
            \common\helpers\System::reset_cache_block('manufacturers');
        }
        $this->view->brands_list = $this->get_brands_list();
        return $this->render('brand_box');
    }
    public function action_temporary_upload()
    {
        $path = \Yii::get_alias('@webroot');
        $path .= DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
        $filename = '';
        $status = 0;
        if (isset($_FILES['filedrop_files']['name'])) {
            if ((int) $_FILES['filedrop_files']['error'] === 0) {
                $tmp_name = $_FILES['filedrop_files']['tmp_name'];
                //$image_location = DIR_FS_DOCUMENT_ROOT . DIR_WS_CATALOG_IMAGES;
                $new_name = $path . $_FILES['filedrop_files']['name'];
                copy($tmp_name, $new_name);
                $filename = $_FILES['filedrop_files']['name'];
                $status = 1;
            }
        }
        $response = ['status' => $status, 'filename' => $filename];
        echo json_encode($response);
    }
    public function action_supplier_select()
    {
        \common\helpers\Translation::init('admin/categories');
        \common\helpers\Translation::init('admin/suppliers');
        $this->layout = false;
        $mode = Yii::$app->request->get('mode', 'product');
        $except = Yii::$app->request->get('except', '');
        if (!empty($except)) {
            $except = preg_split(',', $except, -1, PREG_SPLIT_NO_EMPTY);
        } else {
            $except = [];
        }
        $this->view->suppliers = ['0' => TEXT_NEW_SUPPLIER];
        $this->view->suppliers_js = "arSurcharge = []; arMargin = [];\n";
        $suppliers = \common\helpers\Suppliers::get_suppliers();
        if ($suppliers) {
            foreach ($suppliers as $supplier) {
                if (in_array($supplier->suppliers_id, $except)) {
                    continue;
                }
                $this->view->suppliers[$supplier->suppliers_id] = $supplier->suppliers_name;
                $this->view->suppliers_js .= "arSurcharge[{$supplier->suppliers_id}] = '{$supplier->suppliers_surcharge_amount}'; arMargin[{$supplier->suppliers_id}] = '{$supplier->suppliers_margin_percentage}';\n";
            }
        }
        return $this->render('supplierselect', ['endpointUrl' => Yii::$app->url_manager->create_url(['categories/supplier-add', 'mode' => $mode]), 'mode' => $mode, 'uprid' => \Yii::$app->request->get('uprid')]);
    }
    public function action_calculate_supplier_price()
    {
        $this->layout = false;
        $data = Yii::$app->request->post('queue');
        foreach ($data as $_supplier_id => $calculate_data) {
            $params = ['products_id' => isset($calculate_data['products_id']) ? intval($calculate_data['products_id']) : 0, 'categories_id' => isset($calculate_data['categories_id']) && is_array($calculate_data['categories_id']) ? array_map('intval', $calculate_data['categories_id']) : [], 'manufacturers_id' => isset($calculate_data['manufacturers_id']) ? intval($calculate_data['manufacturers_id']) : 0, 'currencies_id' => isset($calculate_data['currencies_id']) ? intval($calculate_data['currencies_id']) : 0, 'PRICE' => isset($calculate_data['PRICE']) ? floatval($calculate_data['PRICE']) : 0, 'MARGIN' => !empty($calculate_data['MARGIN']) ? $calculate_data['MARGIN'] : null, 'SURCHARGE' => !empty($calculate_data['SURCHARGE']) ? $calculate_data['SURCHARGE'] : null, 'DISCOUNT' => !empty($calculate_data['DISCOUNT']) ? $calculate_data['DISCOUNT'] : null, 'tax_rate' => isset($calculate_data['tax_rate']) ? $calculate_data['tax_rate'] : null, 'price_with_tax' => isset($calculate_data['price_with_tax']) ? $calculate_data['price_with_tax'] : null];
            if ($params['PRICE'] >= 0) {
                $data[$_supplier_id]['result'] = \common\helpers\Price_Formula::apply_rules($params, $_supplier_id);
                $data[$_supplier_id]['result']['SUPPLIER_COST'] = \common\helpers\Price_Formula::correct_supplier_value_by_currency_risks($_supplier_id, $data[$_supplier_id]['currencies_id'], $data[$_supplier_id]['PRICE'] ?? 0);
                $data[$_supplier_id]['result']['LANDED_PRICE'] = \common\helpers\Price_Formula::correct_supplier_value_by_currency_risks($_supplier_id, $data[$_supplier_id]['currencies_id'], $data[$_supplier_id]['LANDED_PRICE'] ?? 0);
                if ($data[$_supplier_id]['result'] === false) {
                    $data[$_supplier_id]['error'] = 'No applicable rule found';
                }
            } else {
                $data[$_supplier_id]['result'] = false;
                $data[$_supplier_id]['error'] = '';
            }
        }
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        Yii::$app->response->data = ['data' => $data];
    }
    public function action_supplier_price()
    {
        \common\helpers\Translation::init('admin/categories');
        \common\helpers\Translation::init('admin/suppliers');
        $currencies = Yii::$container->get('currencies');
        $this->layout = false;
        $this->view->suppliers = [];
        $target_id = Yii::$app->request->get('tID', 0);
        $products_tax_class_id = Yii::$app->request->post('products_tax_class_id', 'products_group_price');
        $manufacturers_id = Yii::$app->request->post('manufacturers_id', 0);
        $products_id = Yii::$app->request->post('products_id', 0);
        $suppliers_load = Yii::$app->request->post('suppliers_data', []);
        $inventory_uprid = Yii::$app->request->get('inventoryUprid', '');
        if (!empty($inventory_uprid)) {
            $suppliers_load = $suppliers_load[$inventory_uprid];
        }
        if (isset($suppliers_load[$products_id])) {
            $suppliers_load = $suppliers_load[$products_id];
        }
        $suppliers_id = Yii::$app->request->post('suppliers_id', []);
        $calculated_prices = [];
        $suppliers_data_query = tep_db_query('select * from ' . TABLE_SUPPLIERS . ' order by is_default DESC, sort_order, suppliers_name');
        while ($suppliers_data = tep_db_fetch_array($suppliers_data_query)) {
            if (!isset($suppliers_load[$suppliers_data['suppliers_id']]['suppliers_price']) || $suppliers_load[$suppliers_data['suppliers_id']]['suppliers_price'] < 0) {
                continue;
            }
            $calculate_data = $suppliers_load[$suppliers_data['suppliers_id']];
            if (!isset($calculate_data['status'])) {
                continue;
            }
            $this->view->suppliers[$suppliers_data['suppliers_id']] = $calculate_data;
            //$suppliers_load[$suppliers_data['suppliers_id']]['suppliers_price'] *= $currencies->get_market_price_rate(\common\helpers\Currencies::getCurrencyCode($suppliers_load[$suppliers_data['suppliers_id']]['currencies_id']), DEFAULT_CURRENCY);
            $params = ['products_id' => 0, 'categories_id' => isset($calculate_data['categories_id']) && is_array($calculate_data['categories_id']) ? array_map('intval', $calculate_data['categories_id']) : [], 'manufacturers_id' => $manufacturers_id, 'currencies_id' => isset($calculate_data['currencies_id']) ? intval($calculate_data['currencies_id']) : 0, 'PRICE' => isset($calculate_data['suppliers_price']) ? (float) $calculate_data['suppliers_price'] : 0, 'MARGIN' => isset($calculate_data['suppliers_margin_percentage']) ? (float) $calculate_data['suppliers_margin_percentage'] : null, 'SURCHARGE' => isset($calculate_data['suppliers_surcharge_amount']) ? (float) $calculate_data['suppliers_surcharge_amount'] : null, 'DISCOUNT' => isset($calculate_data['supplier_discount']) ? (float) $calculate_data['supplier_discount'] : null, 'tax_rate' => isset($calculate_data['tax_rate']) ? (float) $calculate_data['tax_rate'] : null, 'price_with_tax' => isset($calculate_data['price_with_tax']) ? $calculate_data['price_with_tax'] : null];
            $result = \common\helpers\Price_Formula::apply_rules($params, $suppliers_data['suppliers_id']);
            if ($result === false) {
                continue;
            }
            $result['product'] = ['suppliers_id' => $suppliers_data['suppliers_id'], 'qty' => $calculate_data['suppliers_quantity'], 'status' => $calculate_data['status'], 'is_default' => false];
            $calculated_prices[$suppliers_data['suppliers_id']] = $result;
            $suppliers_calculated_price = $result['resultPrice'];
            $suppliers_sale_price = $result['applyParams']['PRICE'] * (1 - $result['applyParams']['DISCOUNT'] / 100);
            $this->view->suppliers[$suppliers_data['suppliers_id']]['target_id'] = $target_id;
            $this->view->suppliers[$suppliers_data['suppliers_id']]['suppliers_name'] = $suppliers_data['suppliers_name'];
            $this->view->suppliers[$suppliers_data['suppliers_id']]['suppliers_calculated_price_net'] = $currencies->display_price($suppliers_calculated_price, 0);
            $this->view->suppliers[$suppliers_data['suppliers_id']]['suppliers_calculated_price_gross'] = $currencies->display_price($suppliers_calculated_price, \common\helpers\Tax::get_tax_rate_value($products_tax_class_id));
            $this->view->suppliers[$suppliers_data['suppliers_id']]['suppliers_calculated_profit'] = $currencies->format($suppliers_calculated_price - $suppliers_sale_price);
        }
        foreach (\common\helpers\Hooks::get_list('categories/supplier-price') as $filename) {
            include $filename;
        }
        return $this->render('supplierprice');
    }
    public function action_auto_supplier_price()
    {
        \common\helpers\Translation::init('admin/categories');
        \common\helpers\Translation::init('admin/suppliers');
        $this->layout = false;
        $this->view->suppliers = [];
        $target_id = Yii::$app->request->get('tID', 0);
        $products_tax_class_id = Yii::$app->request->post('products_tax_class_id', 'products_group_price');
        $manufacturers_id = Yii::$app->request->post('manufacturers_id', 0);
        $products_id = Yii::$app->request->post('products_id', 0);
        $suppliers_load = Yii::$app->request->post('suppliers_data', []);
        $inventory_uprid = Yii::$app->request->get('inventoryUprid', '');
        if (!empty($inventory_uprid)) {
            $suppliers_load = $suppliers_load[$inventory_uprid];
        }
        if (isset($suppliers_load[$products_id])) {
            $suppliers_load = $suppliers_load[$products_id];
        }
        $calculated_prices = [];
        $suppliers_data_query = tep_db_query('select * from ' . TABLE_SUPPLIERS . ' order by is_default DESC, sort_order, suppliers_name');
        while ($suppliers_data = tep_db_fetch_array($suppliers_data_query)) {
            if (!isset($suppliers_load[$suppliers_data['suppliers_id']]['suppliers_price']) || $suppliers_load[$suppliers_data['suppliers_id']]['suppliers_price'] <= 0) {
                continue;
            }
            $calculate_data = $suppliers_load[$suppliers_data['suppliers_id']];
            if (!isset($calculate_data['status'])) {
                continue;
            }
            $this->view->suppliers[$suppliers_data['suppliers_id']] = $calculate_data;
            $params = ['products_id' => 0, 'categories_id' => isset($calculate_data['categories_id']) && is_array($calculate_data['categories_id']) ? array_map('intval', $calculate_data['categories_id']) : [], 'manufacturers_id' => $manufacturers_id, 'currencies_id' => isset($calculate_data['currencies_id']) ? intval($calculate_data['currencies_id']) : 0, 'PRICE' => isset($calculate_data['suppliers_price']) ? floatval($calculate_data['suppliers_price']) : 0, 'MARGIN' => isset($calculate_data['suppliers_margin_percentage']) ? $calculate_data['suppliers_margin_percentage'] : null, 'SURCHARGE' => isset($calculate_data['suppliers_surcharge_amount']) ? $calculate_data['suppliers_surcharge_amount'] : null, 'DISCOUNT' => isset($calculate_data['supplier_discount']) ? $calculate_data['supplier_discount'] : null, 'tax_rate' => isset($calculate_data['tax_rate']) ? $calculate_data['tax_rate'] : null, 'price_with_tax' => isset($calculate_data['price_with_tax']) ? $calculate_data['price_with_tax'] : null];
            $result = \common\helpers\Price_Formula::apply_rules($params, $suppliers_data['suppliers_id']);
            if ($result === false) {
                continue;
            }
            $result['product'] = ['suppliers_id' => $suppliers_data['suppliers_id'], 'qty' => $calculate_data['suppliers_quantity'], 'status' => $calculate_data['status'], 'is_default' => false];
            $calculated_prices[$suppliers_data['suppliers_id']] = $result;
        }
        $selected_supplier_id = 0;
        foreach (\common\helpers\Hooks::get_list('categories/auto-supplier-price') as $filename) {
            include $filename;
        }
        $response = ['id' => $selected_supplier_id];
        echo json_encode($response);
    }
    public function action_supplier_add()
    {
        \common\helpers\Translation::init('admin/categories');
        \common\helpers\Translation::init('admin/suppliers');
        $currencies = Yii::$container->get('currencies');
        $suppliers_id = Yii::$app->request->post('suppliers_id', 0);
        $mode = Yii::$app->request->get('mode', 'product');
        $mode = Yii::$app->request->post('mode', $mode);
        if (!$suppliers_id) {
            $suppliers_data = Yii::$app->request->post('suppliers_data', []);
            $supplier = new Suppliers();
            if ($supplier->load($suppliers_data, '') && $supplier->validate()) {
                if ($supplier->save_supplier($suppliers_data)) {
                    $suppliers_id = $supplier->suppliers_id;
                }
            }
        }
        if ($suppliers_id > 0) {
            $service = new \common\services\Supplier_Service();
            Yii::configure($service, ['allow_change_status' => true, 'allow_change_default' => true, 'allow_change_surcharge' => true, 'allow_change_margin' => true, 'allow_change_price_formula' => true, 'allow_change_auth' => true]);
            $this->layout = false;
            if ($mode == 'category') {
                $rules_model = new \backend\models\Suppliers_Rules();
                $s_info = new \stdClass();
                $rules_model->get_suppliers_data(\common\models\Suppliers::find_one(['suppliers_id' => $suppliers_id]), $s_info);
                return $this->render('category-supplier-block.tpl', ['sInfo' => $s_info->supplier_data[$suppliers_id], 'mayEditCost' => true]);
            } else {
                $service->get('\common\models\SuppliersProducts', 'sProduct');
                $s_info = new Suppliers_Products();
                $s_info->load_default_values();
                $s_info->load_supplier_values($suppliers_id);
                $s_info->status = 1;
                $s_info->products_id = (int) $_POST['uprid'];
                $s_info->uprid = $_POST['uprid'];
                $this->layout = false;
                if (strpos($_POST['uprid'], '{') !== false) {
                    return $this->render('supplierinventory', ['sInfo' => $s_info, 'uprid' => $_POST['uprid'], 'currencies' => $currencies, 'service' => $service]);
                } else {
                    return $this->render('supplierproduct', ['sInfo' => $s_info, 'uprid' => (int) $_POST['uprid'], 'currencies' => $currencies, 'cMap' => \yii\helpers\Array_Helper::map($currencies->currencies, 'id', 'title'), 'service' => $service]);
                }
            }
        }
    }
    public function action_filter_tab_list()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/categories');
        $draw = Yii::$app->request->get('draw', 1);
        $categories_id = Yii::$app->request->get('cID', 0);
        $categories_array = [$categories_id => $categories_id];
        \common\helpers\Categories::get_subcategories($categories_array, $categories_id);
        $response_list = [];
        $filters_query = tep_db_query("\r\n\r\n(select 0 as id, '" . tep_db_input(TEXT_PRODUCT . ': ' . TEXT_KEYWORDS) . "' as name, '' as values_array, 'keywords' as type, f.status as status, f.sort_order as sort_order from " . TABLE_PRODUCTS . ' p, ' . TABLE_PRODUCTS_TO_CATEGORIES . ' p2c left join ' . TABLE_FILTERS . " f on f.filters_type = 'keywords' and f.categories_id = '" . (int) $categories_id . "' and f.filters_of = 'category' where p.products_id = p2c.products_id and p2c.categories_id in ('" . implode("','", $categories_array) . "') group by id)\r\n\r\nunion\r\n\r\n(select 0 as id, '" . tep_db_input(TEXT_PRODUCT . ': ' . TEXT_PRICE) . "' as name, group_concat(distinct round(p.products_price, 2) order by p.products_price asc separator ',') as values_array, 'price' as type, f.status as status, f.sort_order as sort_order from " . TABLE_PRODUCTS . ' p, ' . TABLE_PRODUCTS_TO_CATEGORIES . ' p2c left join ' . TABLE_FILTERS . " f on f.filters_type = 'price' and f.categories_id = '" . (int) $categories_id . "' and f.filters_of = 'category' where p.products_price > 0 and p.products_id = p2c.products_id and p2c.categories_id in ('" . implode("','", $categories_array) . "') group by id)\r\n\r\nunion\r\n\r\n(select 0 as id, '" . tep_db_input(TEXT_PRODUCT . ': ' . TEXT_CATEGORY) . "' as name, group_concat(distinct c.categories_id order by c.categories_id asc separator ',') as values_array, 'category' as type, f.status as status, f.sort_order as sort_order from " . TABLE_PRODUCTS . ' p left join ' . TABLE_PRODUCTS_TO_CATEGORIES . ' p2c2 on p.products_id = p2c2.products_id left join ' . TABLE_CATEGORIES . " c on p2c2.categories_id = c.categories_id and c.parent_id = '" . (int) $categories_id . "', " . TABLE_PRODUCTS_TO_CATEGORIES . ' p2c left join ' . TABLE_FILTERS . " f on f.filters_type = 'category' and f.categories_id = '" . (int) $categories_id . "' and f.filters_of = 'category' where c.categories_id > 0 and p.products_id = p2c.products_id and p2c.categories_id in ('" . implode("','", $categories_array) . "') group by id)\r\n\r\nunion\r\n\r\n(select 0 as id, '" . tep_db_input(TEXT_PRODUCT . ': ' . TEXT_MANUFACTURER) . "' as name, group_concat(distinct p.manufacturers_id order by p.manufacturers_id asc separator ',') as values_array, 'brand' as type, f.status as status, f.sort_order as sort_order from " . TABLE_PRODUCTS . ' p, ' . TABLE_PRODUCTS_TO_CATEGORIES . ' p2c left join ' . TABLE_FILTERS . " f on f.filters_type = 'brand' and f.categories_id = '" . (int) $categories_id . "' and f.filters_of = 'category' where p.manufacturers_id > 0 and p.products_id = p2c.products_id and p2c.categories_id in ('" . implode("','", $categories_array) . "') group by id)\r\n\r\nunion\r\n\r\n(select po.products_options_id as id, concat('" . tep_db_input(TEXT_ATTRIBUTE . ': ') . "', po.products_options_name) as name, group_concat(distinct pa.options_values_id order by pa.options_values_id asc separator ',') as values_array, 'attribute' as type, f.status as status, f.sort_order as sort_order from " . TABLE_PRODUCTS_OPTIONS . ' po left join ' . TABLE_FILTERS . " f on f.options_id = po.products_options_id and f.filters_type = 'attribute' and f.categories_id = '" . (int) $categories_id . "' and f.filters_of = 'category', " . TABLE_PRODUCTS_ATTRIBUTES . ' pa, ' . TABLE_PRODUCTS . ' p, ' . TABLE_PRODUCTS_TO_CATEGORIES . " p2c where po.products_options_id = pa.options_id and po.display_filter = '1' and po.language_id = '" . (int) $languages_id . "' and pa.products_id = p.products_id and p.products_id = p2c.products_id and p2c.categories_id in ('" . implode("','", $categories_array) . "') group by po.products_options_id order by f.status desc, f.sort_order, po.products_options_sort_order, po.products_options_name)\r\n\r\nunion\r\n\r\n(select pr.properties_id as id, concat('" . tep_db_input(TEXT_PROPERTY . ': ') . "', if(length(prd.properties_name_alt) > 0, prd.properties_name_alt, prd.properties_name)) as name, group_concat(distinct pr2p.values_id order by pr2p.values_id asc separator ',') as values_array, 'property' as type, f.status as status, f.sort_order as sort_order from " . TABLE_PROPERTIES . ' pr left join ' . TABLE_FILTERS . " f on f.properties_id = pr.properties_id and f.filters_type = 'property' and f.categories_id = '" . (int) $categories_id . "' and f.filters_of = 'category', " . TABLE_PROPERTIES_DESCRIPTION . ' prd, ' . TABLE_PROPERTIES_TO_PRODUCTS . ' pr2p, ' . TABLE_PRODUCTS . ' p, ' . TABLE_PRODUCTS_TO_CATEGORIES . " p2c where pr.properties_id = pr2p.properties_id and pr.display_filter = '1' and pr.properties_id = prd.properties_id and prd.language_id = '" . (int) $languages_id . "' and pr2p.products_id = p.products_id and p.products_id = p2c.products_id and p2c.categories_id in ('" . implode("','", $categories_array) . "') group by pr.properties_id order by f.status desc, f.sort_order, pr.sort_order, prd.properties_name)\r\n\r\norder by status desc, sort_order\r\n\r\n");
        while ($filters = tep_db_fetch_array($filters_query)) {
            if ($ext = \common\helpers\Acl::check_extension_allowed('ProductPropertiesFilters', 'allowed')) {
                $response_list[] = $ext::get_row_data($filters);
            } else {
                $response_list[] = ['<div class="handle_cat_list dis_module"><span class="handle"><i class="icon-hand-paper-o"></i></span><div class="module_title">' . $filters['name'] . '</div></div>', '<div class="count_block dis_module">' . (tep_not_null($filters['values_array']) ? '<span class="count_values">' . count(explode(',', $filters['values_array'])) . '</span><a href="javascript:void(0)" class="view_filter_values">' . TEXT_VIEW_VALUES . '</a>' : '&nbsp;') . '</div>', '<input type="checkbox" value="1" class="check_on_off_filters" disabled>'];
            }
        }
        $response = ['draw' => $draw, 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_filter_brand_tab_list()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/categories');
        $draw = Yii::$app->request->get('draw', 1);
        $manufacturers_id = Yii::$app->request->get('mID', 0);
        $response_list = [];
        $filters_query = tep_db_query("\r\n\r\n(select 0 as id, '" . tep_db_input(TEXT_PRODUCT . ': ' . TEXT_KEYWORDS) . "' as name, '' as values_array, 'keywords' as type, f.status as status, f.sort_order as sort_order from " . TABLE_PRODUCTS . ' p left join ' . TABLE_FILTERS . " f on f.filters_type = 'keywords' and f.manufacturers_id = '" . (int) $manufacturers_id . "' and f.filters_of = 'brand' where 1 " . ($manufacturers_id > 0 ? " and p.manufacturers_id = '" . (int) $manufacturers_id . "'" : '') . " group by id)\r\n\r\nunion\r\n\r\n(select 0 as id, '" . tep_db_input(TEXT_PRODUCT . ': ' . TEXT_PRICE) . "' as name, group_concat(distinct round(p.products_price, 2) order by p.products_price asc separator ',') as values_array, 'price' as type, f.status as status, f.sort_order as sort_order from " . TABLE_PRODUCTS . ' p left join ' . TABLE_FILTERS . " f on f.filters_type = 'price' and f.manufacturers_id = '" . (int) $manufacturers_id . "' and f.filters_of = 'brand' where p.products_price > 0 " . ($manufacturers_id > 0 ? " and p.manufacturers_id = '" . (int) $manufacturers_id . "'" : '') . " group by id)\r\n\r\nunion\r\n\r\n(select 0 as id, '" . tep_db_input(TEXT_PRODUCT . ': ' . TEXT_CATEGORY) . "' as name, group_concat(distinct c.categories_id order by c.categories_id asc separator ',') as values_array, 'category' as type, f.status as status, f.sort_order as sort_order from " . TABLE_PRODUCTS . ' p left join ' . TABLE_PRODUCTS_TO_CATEGORIES . ' p2c2 on p.products_id = p2c2.products_id left join ' . TABLE_CATEGORIES . ' c on p2c2.categories_id = c.categories_id left join ' . TABLE_FILTERS . " f on f.filters_type = 'category' and f.manufacturers_id = '" . (int) $manufacturers_id . "' and f.filters_of = 'brand' where c.categories_id > 0 " . ($manufacturers_id > 0 ? " and p.manufacturers_id = '" . (int) $manufacturers_id . "'" : '') . " group by id)\r\n\r\nunion\r\n\r\n(select po.products_options_id as id, concat('" . tep_db_input(TEXT_ATTRIBUTE . ': ') . "', po.products_options_name) as name, group_concat(distinct pa.options_values_id order by pa.options_values_id asc separator ',') as values_array, 'attribute' as type, f.status as status, f.sort_order as sort_order from " . TABLE_PRODUCTS_OPTIONS . ' po left join ' . TABLE_FILTERS . " f on f.options_id = po.products_options_id and f.filters_type = 'attribute' and f.manufacturers_id = '" . (int) $manufacturers_id . "' and f.filters_of = 'brand', " . TABLE_PRODUCTS_ATTRIBUTES . ' pa, ' . TABLE_PRODUCTS . " p where po.products_options_id = pa.options_id and po.display_filter = '1' and po.language_id = '" . (int) $languages_id . "' and pa.products_id = p.products_id " . ($manufacturers_id > 0 ? " and p.manufacturers_id = '" . (int) $manufacturers_id . "'" : '') . " group by po.products_options_id order by f.status desc, f.sort_order, po.products_options_sort_order, po.products_options_name)\r\n\r\nunion\r\n\r\n(select pr.properties_id as id, concat('" . tep_db_input(TEXT_PROPERTY . ': ') . "', if(length(prd.properties_name_alt) > 0, prd.properties_name_alt, prd.properties_name)) as name, group_concat(distinct pr2p.values_id order by pr2p.values_id asc separator ',') as values_array, 'property' as type, f.status as status, f.sort_order as sort_order from " . TABLE_PROPERTIES . ' pr left join ' . TABLE_FILTERS . " f on f.properties_id = pr.properties_id and f.filters_type = 'property' and f.manufacturers_id = '" . (int) $manufacturers_id . "' and f.filters_of = 'brand', " . TABLE_PROPERTIES_DESCRIPTION . ' prd, ' . TABLE_PROPERTIES_TO_PRODUCTS . ' pr2p, ' . TABLE_PRODUCTS . " p where pr.properties_id = pr2p.properties_id and pr.display_filter = '1' and pr.properties_id = prd.properties_id and prd.language_id = '" . (int) $languages_id . "' and pr2p.products_id = p.products_id " . ($manufacturers_id > 0 ? " and p.manufacturers_id = '" . (int) $manufacturers_id . "'" : '') . ' group by pr.properties_id order by f.status desc, f.sort_order, pr.sort_order, prd.properties_name)

order by status desc, sort_order

');
        while ($filters = tep_db_fetch_array($filters_query)) {
            if ($ext = \common\helpers\Acl::check_extension_allowed('ProductPropertiesFilters', 'allowed')) {
                $response_list[] = $ext::get_row_data($filters);
            } else {
                $response_list[] = ['<div class="handle_cat_list dis_module"><span class="handle"><i class="icon-hand-paper-o"></i></span><div class="module_title">' . $filters['name'] . '</div></div>', '<div class="count_block dis_module">' . (tep_not_null($filters['values_array']) ? '<span class="count_values">' . count(explode(',', $filters['values_array'])) . '</span><a href="javascript:void(0)" class="view_filter_values">' . TEXT_VIEW_VALUES . '</a>' : '&nbsp;') . '</div>', '<input type="checkbox" value="1" class="check_on_off_filters" disabled>'];
            }
        }
        $response = ['draw' => $draw, 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_viewvalues()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $this->layout = false;
        $this->view->use_popup_mode = true;
        $type = Yii::$app->request->get('type');
        $id = Yii::$app->request->get('id');
        $values = Yii::$app->request->get('values', []);
        $values_html = '';
        switch ($type) {
            case 'price':
                $currencies = Yii::$container->get('currencies');
                foreach (explode(',', $values) as $price) {
                    $values_html .= '<div>' . $currencies->format($price) . '</div>';
                }
                break;
            case 'category':
                $values_query = tep_db_query('select categories_name from ' . TABLE_CATEGORIES_DESCRIPTION . " where categories_id in ('" . implode("','", explode(',', $values)) . "') and language_id = '" . (int) $languages_id . "' order by categories_name");
                while ($values = tep_db_fetch_array($values_query)) {
                    $values_html .= '<div>' . $values['categories_name'] . '</div>';
                }
                break;
            case 'brand':
                $values_query = tep_db_query('select manufacturers_name from ' . TABLE_MANUFACTURERS . " where manufacturers_id in ('" . implode("','", explode(',', $values)) . "') order by manufacturers_name");
                while ($values = tep_db_fetch_array($values_query)) {
                    $values_html .= '<div>' . $values['manufacturers_name'] . '</div>';
                }
                break;
            case 'attribute':
                $values_query = tep_db_query('select pov.products_options_values_name from ' . TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS . ' pov2po, ' . TABLE_PRODUCTS_OPTIONS_VALUES . " pov where pov2po.products_options_values_id = pov.products_options_values_id and pov2po.products_options_id = '" . (int) $id . "' and pov.products_options_values_id in ('" . implode("','", explode(',', $values)) . "') and pov.language_id = '" . (int) $languages_id . "' order by pov.products_options_values_name");
                while ($values = tep_db_fetch_array($values_query)) {
                    $values_html .= '<div>' . $values['products_options_values_name'] . '</div>';
                }
                break;
            case 'property':
                $values_query = tep_db_query('select p.properties_type, p.decimals, pv.values_text, pv.values_number, pv.values_number_upto, pv.values_alt from ' . TABLE_PROPERTIES . ' p, ' . TABLE_PROPERTIES_VALUES . " pv where p.properties_id = pv.properties_id and pv.properties_id = '" . (int) $id . "' and pv.values_id in ('" . implode("','", explode(',', $values)) . "') and pv.language_id = '" . (int) $languages_id . "' order by pv.values_number, pv.values_text");
                while ($values = tep_db_fetch_array($values_query)) {
                    if ($values['properties_type'] == 'number' || $values['properties_type'] == 'interval') {
                        $values_html .= '<div>' . (float) number_format($values['values_number'], $values['decimals']) . '</div>';
                    } elseif ($values['properties_type'] == 'interval') {
                        $values_html .= '<div>' . (float) number_format($values['values_number'], $values['decimals']) . ' - ' . (float) number_format($values['values_number_upto'], $values['decimals']) . '</div>';
                    } else {
                        $values_html .= '<div>' . $values['values_text'] . '</div>';
                    }
                }
                break;
        }
        $html = '<div class="viewContent">' . $values_html . '</div>';
        return $html;
    }
    public function action_file_manager()
    {
        $this->layout = false;
        unset($_SESSION['uploaded_file_name']);
        $fs_path = DIR_FS_CATALOG . 'documents/';
        $ws_path = DIR_WS_CATALOG . 'documents/';
        $file_list = [];
        $download_list = array_diff(scandir($fs_path), ['..', '.']);
        foreach ($download_list as $download_file) {
            if (is_file($fs_path . '/' . $download_file)) {
                $file_list[] = $download_file;
            }
        }
        return $this->render('file-manager', ['fileList' => $file_list]);
    }
    public function action_file_manager_upload()
    {
        $response = ['status' => 'error'];
        if (isset($_FILES['files'])) {
            $path = DIR_FS_CATALOG . 'documents/';
            $uploadfile = $path . \common\helpers\Output::mb_basename($_FILES['files']['name']);
            if (move_uploaded_file($_FILES['files']['tmp_name'], $uploadfile)) {
                $text = '';
                $_SESSION['uploaded_file_name'][] = $_FILES['files']['name'];
                $response = ['status' => 'ok', 'text' => $text, 'file_name' => $_FILES['files']['name']];
            }
        }
        echo json_encode($response);
    }
    public function action_file_manager_listing()
    {
        $this->layout = false;
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/categories');
        $fs_path = DIR_FS_CATALOG . 'documents/';
        $ws_path = DIR_WS_CATALOG . 'documents/';
        $draw = Yii::$app->request->get('draw', 1);
        $start = Yii::$app->request->get('start', 0);
        $length = Yii::$app->request->get('length', 10);
        $current_category_id = Yii::$app->request->get('id', 0);
        $search = Yii::$app->request->get('search');
        if ($length == -1) {
            $length = 10000;
        }
        $documents = [];
        $documents[] = ['id' => '', 'text' => 'Please choose group to link'];
        $documents_data_query = tep_db_query('select * from ' . TABLE_DOCUMENT_TYPES . " where language_id='" . $languages_id . "' order by document_types_name");
        while ($documents_data = tep_db_fetch_array($documents_data_query)) {
            $documents[] = ['id' => $documents_data['document_types_id'], 'text' => $documents_data['document_types_name']];
        }
        $form_filter = Yii::$app->request->get('filter');
        parse_str($form_filter, $output);
        $files_arr = [];
        if (is_array($output['filename'])) {
            foreach ($output['filename'] as $item1) {
                foreach ($item1 as $item2) {
                    $files_arr[] = $item2;
                }
            }
        }
        /**
         * products_documents_id
         * document_types_id
         * filename
         * title
         */
        $products_id = (int) $output['global_id'];
        $file_list = [];
        try {
            $download_list = array_map('basename', File_Helper::find_files($fs_path, []));
            // array_diff(scandir($fsPath), array('..', '.'));
        } catch (\Exception $ex) {
            $download_list = [];
        }
        $uploaded_file_names = $_SESSION['uploaded_file_name'];
        if ($uploaded_file_names) {
            $download_list = array_unique(array_merge($uploaded_file_names, $download_list));
            $new_files = count($uploaded_file_names);
        } else {
            $uploaded_file_names = [];
            $new_files = 0;
        }
        $counter = 0;
        foreach ($download_list as $download_file) {
            if ($search['value']) {
                if (strpos(strtolower($download_file), strtolower($search['value'])) === false) {
                    continue;
                }
            }
            if ($counter > $new_files && in_array($download_file, $uploaded_file_names)) {
                continue;
            }
            if (is_file($fs_path . '/' . $download_file)) {
                //file not used?
                $docs_data_query = tep_db_query('select count(*) as total from ' . TABLE_PRODUCTS_DOCUMENTS . " where filename='" . tep_db_input($download_file) . "'");
                $docs_data = tep_db_fetch_array($docs_data_query);
                $actions = '';
                $delete = '';
                if ($docs_data['total'] == 0 && !in_array($download_file, $files_arr)) {
                    $delete = '<span class="file-remove" onclick="deleteFile(\'' . $download_file . '\')" title="' . IMAGE_DELETE . '"></span>';
                }
                /* $docs_data_query = tep_db_query("select count(*) as total from " . TABLE_PRODUCTS_DOCUMENTS . " where filename='" . tep_db_input($downloadFile) . "' and products_id=" . (int)$products_id);
                   $docs_data = tep_db_fetch_array($docs_data_query); */
                if (!in_array($download_file, $files_arr)) {
                    $actions .= tep_draw_pull_down_menu('doc_type_' . $counter, $documents, '', 'class="form-control"') . '<span onclick="addFile(\'' . addslashes($download_file) . '\', \'' . $products_id . '\', \'' . 'doc_type_' . $counter . '\')" class="btn">' . TEXT_ADD . '</span>';
                } else {
                    $actions .= '&nbsp;<span onclick="removeFile(\'' . addslashes($download_file) . '\', \'' . $products_id . '\')" class="unlink">' . UNLINK_FROM_PRODUCT . '</span>';
                }
                $download_file = '<span onclick="renameFile(\'' . $download_file . '\')" class="btn-edit-file" title="' . EDIT_FILE_NAME . '"></span><span class="file-name" data-name="' . $download_file . '">' . $download_file . '</span>';
                if ($counter == $new_files - 1) {
                    $download_file .= '
<script type="text/javascript">
  $("#document_list tbody tr").each(function(i){
    if (i < ' . $new_files . ') $(this).addClass("new-file")
  })
</script>';
                }
                $file_list[] = [$download_file, $actions, $delete];
                $counter++;
            }
        }
        $response = ['draw' => $draw, 'recordsTotal' => $counter, 'recordsFiltered' => $counter, 'data' => $file_list];
        echo json_encode($response);
    }
    public function action_file_manager_delete()
    {
        $this->layout = false;
        $fs_path = DIR_FS_CATALOG . 'documents/';
        $download_file = Yii::$app->request->get('name');
        $download_file = \common\helpers\Output::mb_basename($download_file);
        if (is_file($fs_path . '/' . $download_file)) {
            @unlink($fs_path . '/' . $download_file);
        }
    }
    public function action_file_manager_remove()
    {
        $this->layout = false;
        $products_id = (int) Yii::$app->request->post('id');
        $download_file = tep_db_prepare_input(Yii::$app->request->post('name'));
        $query = tep_db_query('select products_documents_id from ' . TABLE_PRODUCTS_DOCUMENTS . " where products_id  = '" . (int) $products_id . "'");
        while ($item = tep_db_fetch_array($query)) {
            tep_db_query('delete from ' . TABLE_PRODUCTS_DOCUMENTS_TITLES . " where products_documents_id  = '" . (int) $item['products_documents_id'] . "'");
        }
        tep_db_query('delete from ' . TABLE_PRODUCTS_DOCUMENTS . " where products_id = '" . $products_id . "' and filename = '" . tep_db_input($download_file) . "'");
    }
    public function action_file_manager_add()
    {
        $this->layout = false;
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/categories');
        $form_filter = Yii::$app->request->post('filter');
        parse_str($form_filter, $output);
        $products_id = (int) $output['global_id'];
        $name = Yii::$app->request->post('name');
        $type = (int) Yii::$app->request->post('type');
        $is_link = (int) Yii::$app->request->post('isLink');
        /**
         * products_documents_id
         * document_types_id
         * filename
         * title
         */
        $products_documents_id = $output['products_documents_id'];
        $document_types_id = $output['document_types_id'];
        $filename = $output['filename'];
        $is_link = $output['is_link'];
        $title = $output['title'];
        $sort_order = $output['sort_order'];
        $languages = \common\helpers\Language::get_languages();
        $this->view->documents = [];
        $documents_data_query = tep_db_query('select * from ' . TABLE_DOCUMENT_TYPES . " where language_id='" . $languages_id . "' order by document_types_name");
        while ($documents_data = tep_db_fetch_array($documents_data_query)) {
            $docs = [];
            if (isset($products_documents_id[$documents_data['document_types_id']]) && is_array($products_documents_id[$documents_data['document_types_id']])) {
                foreach ($products_documents_id[$documents_data['document_types_id']] as $key => $value) {
                    $doc_title = [];
                    foreach ($languages as $language) {
                        $doc_title[$language['id']] = $title[$language['id']][$documents_data['document_types_id']][$key];
                    }
                    $docs[] = ['products_documents_id' => $value, 'document_types_id' => $document_types_id[$documents_data['document_types_id']][$key], 'filename' => $filename[$documents_data['document_types_id']][$key], 'is_link' => $is_link[$documents_data['document_types_id']][$key], 'title' => $doc_title, 'sort_order' => $sort_order[$documents_data['document_types_id']][$key]];
                }
            }
            if ($documents_data['document_types_id'] == $type) {
                $docs[] = ['products_documents_id' => '', 'document_types_id' => $type, 'filename' => $name, 'is_link' => $is_link, 'title' => ''];
            }
            /* $docs_data_query = tep_db_query("select * from " . TABLE_PRODUCTS_DOCUMENTS . " where document_types_id=" . $documents_data['document_types_id'] . " and products_id=" . (int)$products_id);
               while ($docs_data = tep_db_fetch_array($docs_data_query)) {
               $docs[] = $docs_data;
               } */
            $this->view->documents[$documents_data['document_types_id']] = ['id' => $documents_data['document_types_id'], 'title' => $documents_data['document_types_name'], 'docs' => $docs];
        }
        return $this->render('file-manager-add', ['global_id' => $products_id, 'languages' => $languages, 'languages_id' => $languages_id]);
    }
    public function action_file_add_external_link()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/categories');
        $this->layout = false;
        if (Yii::$app->request->is_post) {
        }
        $languages = \common\helpers\Language::get_languages();
        $document_type_variants = [];
        $documents_data_query = tep_db_query('select * from ' . TABLE_DOCUMENT_TYPES . " where language_id='" . $languages_id . "' order by document_types_name");
        while ($documents_data = tep_db_fetch_array($documents_data_query)) {
            $document_type_variants[$documents_data['document_types_id']] = $documents_data['document_types_name'];
        }
        return $this->render('file-add-external-url.tpl', ['form_action_href' => Yii::$app->url_manager->create_url('categories/file-add-external-link'), 'documentTypeVariants' => $document_type_variants]);
    }
    public function action_file_groups()
    {
        \common\helpers\Translation::init('admin/categories');
        $languages_id = \Yii::$app->settings->get('languages_id');
        $this->layout = false;
        $languages = \common\helpers\Language::get_languages();
        $types = [];
        $types_list = [];
        $documents_query = tep_db_query('select * from ' . TABLE_DOCUMENT_TYPES . ' order by document_types_name');
        while ($documents = tep_db_fetch_array($documents_query)) {
            $types[$documents['language_id']][$documents['document_types_id']] = $documents;
            $types_list[$documents['document_types_id']] = $documents['document_types_id'];
        }
        return $this->render('file-groups', ['languages' => $languages, 'languages_id' => $languages_id, 'types' => $types, 'types_list' => $types_list]);
    }
    public function action_file_groups_save()
    {
        $this->layout = false;
        $languages = \common\helpers\Language::get_languages();
        $types = Yii::$app->request->post('type');
        foreach ($languages as $language) {
            foreach ($types[$language['id']] as $id => $type) {
                $types_icon = '';
                if ($type['document_types_icon']) {
                    $icon = tep_db_fetch_array(tep_db_query('select document_types_icon from ' . TABLE_DOCUMENT_TYPES . " where document_types_id = '" . (int) $id . "' and language_id = '" . $language['id'] . "'"));
                    if ($icon['document_types_icon'] == $type['document_types_icon']) {
                        $types_icon = $type['document_types_icon'];
                    } else {
                        $path = \Yii::get_alias('@webroot') . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
                        if (!is_file($path . $type['document_types_icon'])) {
                            $types_icon = 'images/' . Uploads::move($type['document_types_icon']);
                        } else {
                            $types_icon = $type['document_types_icon'];
                        }
                    }
                }
                $document_types = tep_db_query('select document_types_icon from ' . TABLE_DOCUMENT_TYPES . " where document_types_id = '" . (int) $id . "' and language_id = '" . $language['id'] . "'");
                if (tep_db_num_rows($document_types) > 0) {
                    $sql_data_array = ['document_types_name' => $type['document_types_name'], 'document_types_icon' => $types_icon];
                    tep_db_perform(TABLE_DOCUMENT_TYPES, $sql_data_array, 'update', "document_types_id = '" . (int) $id . "' and language_id = '" . $language['id'] . "'");
                } else {
                    $sql_data_array = ['document_types_id' => $id, 'language_id' => $language['id'], 'document_types_name' => $type['document_types_name'], 'document_types_icon' => $types_icon];
                    tep_db_perform(TABLE_DOCUMENT_TYPES, $sql_data_array);
                }
            }
        }
        $types = [];
        $types_list = [];
        $documents_query = tep_db_query('select * from ' . TABLE_DOCUMENT_TYPES . ' order by document_types_name');
        while ($documents = tep_db_fetch_array($documents_query)) {
            $types[$documents['language_id']][$documents['document_types_id']] = $documents;
            $types_list[$documents['document_types_id']] = $documents['document_types_id'];
        }
        return '';
    }
    public function action_file_groups_add()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $this->layout = false;
        $languages = \common\helpers\Language::get_languages();
        $documents = tep_db_fetch_array(tep_db_query('select max(document_types_id) as id from ' . TABLE_DOCUMENT_TYPES . ' '));
        $sql_data_array = ['document_types_id' => $documents['id'] + 1, 'language_id' => $languages_id, 'document_types_name' => '', 'document_types_icon' => ''];
        tep_db_perform(TABLE_DOCUMENT_TYPES, $sql_data_array);
        $types = [];
        $types_list = [];
        $documents_query = tep_db_query('select * from ' . TABLE_DOCUMENT_TYPES . ' order by document_types_name');
        while ($documents = tep_db_fetch_array($documents_query)) {
            $types[$documents['language_id']][$documents['document_types_id']] = $documents;
            $types_list[$documents['document_types_id']] = $documents['document_types_id'];
        }
        return $this->render('file-groups', ['languages' => $languages, 'languages_id' => $languages_id, 'types' => $types, 'types_list' => $types_list]);
    }
    public function action_file_groups_remove()
    {
        $this->layout = false;
        $document_types_id = Yii::$app->request->get('document_types_id');
        $query = tep_db_query('select products_documents_id from ' . TABLE_PRODUCTS_DOCUMENTS . " where document_types_id  = '" . (int) $document_types_id . "'");
        while ($item = tep_db_fetch_array($query)) {
            tep_db_query('delete from ' . TABLE_PRODUCTS_DOCUMENTS_TITLES . " where products_documents_id  = '" . (int) $item['products_documents_id'] . "'");
        }
        tep_db_query('delete from ' . TABLE_DOCUMENT_TYPES . " where document_types_id = '" . (int) $document_types_id . "'");
        return json_encode('ok');
    }
    public function action_file_manager_rename()
    {
        $this->layout = false;
        $name = Yii::$app->request->get('name');
        $new_name = Yii::$app->request->get('new_name');
        $new_name = \common\helpers\Output::mb_basename($new_name);
        $sql_data_array = ['filename' => $new_name];
        tep_db_perform(TABLE_PRODUCTS_DOCUMENTS, $sql_data_array, 'update', "filename='" . $name . "'");
        $fs_path = DIR_FS_CATALOG . 'documents/';
        if (is_file($fs_path . '/' . $name)) {
            rename($fs_path . '/' . $name, $fs_path . '/' . $new_name);
        }
        return $new_name;
    }
    public function action_stock_history()
    {
        $this->layout = false;
        if ($ext = \common\helpers\Acl::check_extension_allowed('ProductStockHistory', 'allowed')) {
            return $ext::action_stock_history();
        }
    }
    public function action_stock_info()
    {
        $this->layout = false;
        $prid = Yii::$app->request->get('prid');
        $warehouse_names = \yii\helpers\Array_Helper::map(\common\helpers\Warehouses::get_warehouses(true), 'id', 'text');
        $blocks = \common\models\Location_Blocks::find()->as_array()->all();
        $blocks_list = [];
        foreach ($blocks as $value) {
            $blocks_list[$value['block_id']] = $value['block_name'];
        }
        $exist_stock = \common\models\Warehouses_Products::find()->select(['warehouse_id', 'suppliers_id', 'location_id', 'layers_id', 'batch_id', 'products_quantity'])->where(['products_id' => $prid])->order_by(['warehouse_id' => SORT_ASC, 'suppliers_id' => SORT_ASC, 'location_id' => SORT_ASC, 'layers_id' => SORT_ASC, 'batch_id' => SORT_ASC])->as_array()->all();
        $stock_list = [];
        foreach ($exist_stock as $stock) {
            if ($stock['products_quantity'] <= 0) {
                continue;
            }
            $location = \common\helpers\Warehouses::get_location_path($stock['location_id'], $stock['warehouse_id'], $blocks_list);
            if (empty($location)) {
                $location = 'N/A';
            }
            $layer = 'N/A';
            if ($stock['layers_id']) {
                $layer = \common\helpers\Date::date_short(\common\helpers\Warehouses::get_expiry_date_by_layers_id($stock['layers_id']));
            }
            $batch = 'N/A';
            if ($stock['batch_id']) {
                $batch = \common\helpers\Warehouses::get_batch_name_by_batch_id($stock['batch_id']);
            }
            $stock_list[] = ['id' => $stock['location_id'] . '_' . $stock['layers_id'] . '_' . $stock['batch_id'], 'warehouse' => isset($warehouse_names[$stock['warehouse_id']]) ? $warehouse_names[$stock['warehouse_id']] : '', 'supplier' => \common\helpers\Suppliers::get_supplier_name($stock['suppliers_id']), 'location' => $location, 'layer' => $layer, 'batch' => $batch, 'qty' => $stock['products_quantity']];
        }
        return $this->render_ajax('stock-info', ['stockList' => $stock_list]);
    }
    public function action_product_quantity_update()
    {
        \common\helpers\Translation::init('admin/categories');
        $box_location = Yii::$app->request->post('box_location');
        $is_autoallocate = (int) Yii::$app->request->post('is_autoallocate');
        $location_ids = explode(',', $box_location);
        $location_id = 0;
        if (is_array($location_ids)) {
            foreach ($location_ids as $id) {
                if ($id > 0) {
                    $location_id = $id;
                }
            }
        }
        $expiry_date = Yii::$app->request->post('expiry_date', '');
        $expiry_date = \common\helpers\Date::prepare_input_date($expiry_date);
        $layers_id = \common\helpers\Warehouses::get_warehouses_products_layers_i_dby_expiry_date($expiry_date);
        $batch_name = Yii::$app->request->post('batch_name', '');
        $batch_id = \common\helpers\Warehouses::get_warehouses_products_batch_i_dby_batch_name($batch_name);
        $warehouse_id = (int) $_POST['warehouse_id'];
        $w_suppliers_id = (int) Yii::$app->request->post('w_suppliers_id', 0);
        $stock_comments = $_POST['stock_comments'];
        if (count(\common\helpers\Product::get_child_array($_POST['uprid'])) > 0) {
            return json_encode([]);
        }
        $response = [];
        $update_data = [];
        if (strpos($_POST['uprid'], '{') !== false && \common\helpers\Inventory::get_prid($_POST['uprid']) > 0) {
            $inventory_quantity_update = (int) $_POST['inventoryqtyupdate_' . $_POST['uprid']];
            $inventory_quantity_update_prefix = $_POST['inventoryqtyupdateprefix_' . $_POST['uprid']] == '-' ? '-' : '+';
            if ($inventory_quantity_update_prefix == '-') {
                $exist_locations = \common\models\Warehouses_Products::find()->select(['location_id', 'layers_id', 'batch_id', 'warehouse_stock_quantity'])->where(['warehouse_id' => $warehouse_id, 'suppliers_id' => $w_suppliers_id, 'products_id' => $_POST['uprid']])->order_by(['location_id' => SORT_ASC, 'layers_id' => SORT_ASC, 'batch_id' => SORT_ASC])->as_array()->all();
                foreach ($exist_locations as $location) {
                    if (isset($_POST['stock_minus_qty_' . $location['location_id'] . '_' . $location['layers_id'] . '_' . $location['batch_id']]) && $_POST['stock_minus_qty_' . $location['location_id'] . '_' . $location['layers_id'] . '_' . $location['batch_id']] > 0) {
                        $update_data[] = ['quantity' => (int) $_POST['stock_minus_qty_' . $location['location_id'] . '_' . $location['layers_id'] . '_' . $location['batch_id']], 'prefix' => $inventory_quantity_update_prefix, 'location' => $location['location_id'], 'layers_id' => $location['layers_id'], 'batch_id' => $location['batch_id']];
                    }
                }
            } else {
                $update_data[] = ['quantity' => $inventory_quantity_update, 'prefix' => $inventory_quantity_update_prefix, 'location' => $location_id, 'layers_id' => $layers_id, 'batch_id' => $batch_id];
            }
            foreach ($update_data as $update_item) {
                if ($update_item['quantity'] > 0) {
                    $check_data = tep_db_fetch_array(tep_db_query('select products_quantity, ordered_stock_quantity, suppliers_stock_quantity from ' . TABLE_INVENTORY . " where products_id = '" . tep_db_input($_POST['uprid']) . "'"));
                    if (!$check_data) {
                        tep_db_query('insert into ' . TABLE_INVENTORY . " set inventory_id = '', products_id = '" . tep_db_input($_POST['uprid']) . "', prid = '" . (int) \common\helpers\Inventory::get_prid($_POST['uprid']) . "'");
                        $check_data['products_quantity'] = 0;
                    }
                    global $login_id;
                    //\common\helpers\Product::log_stock_history_before_update($_POST['uprid'], $updateItem['quantity'], $updateItem['prefix'], ['warehouse_id' => $warehouse_id, 'comments' => TEXT_MANUALL_STOCK_UPDATE . (trim($stock_comments) != '' ? ': ' . $stock_comments : ''), 'admin_id' => $login_id]);
                    if ($warehouse_id > 0) {
                        $parameters = ['layers_id' => $update_item['layers_id'], 'batch_id' => $update_item['batch_id'], 'admin_id' => $login_id, 'comments' => TEXT_MANUALL_STOCK_UPDATE . (trim($stock_comments) != '' ? ': ' . $stock_comments : '')];
                        $check_data['warehouse_quantity'] = \common\helpers\Warehouses::update_products_quantity($_POST['uprid'], $warehouse_id, $update_item['quantity'], $update_item['prefix'], $w_suppliers_id, $update_item['location'], $parameters);
                        if ($is_autoallocate) {
                            \common\helpers\Product::do_allocate_automatic($_POST['uprid'], true);
                        }
                        $check_data['allocated_quantity'] = \common\helpers\Product::get_allocated($_POST['uprid']);
                        $check_data['temporary_quantity'] = \common\helpers\Product::get_allocated_temporary($_POST['uprid']);
                        $check_data['allocated_temporary_quantity'] = \common\helpers\Product::get_allocated_temporary($_POST['uprid'], true);
                        $check_data['deficit_quantity'] = \common\helpers\Product::get_stock_deficit($_POST['uprid']);
                    } else {
                        tep_db_query('update ' . TABLE_INVENTORY . ' set products_quantity = products_quantity ' . $update_item['prefix'] . $update_item['quantity'] . " where products_id = '" . tep_db_input($_POST['uprid']) . "'");
                        if ($update_item['prefix'] == '-') {
                            $check_data['warehouse_quantity'] -= $update_item['quantity'];
                        } else {
                            $check_data['warehouse_quantity'] += $update_item['quantity'];
                        }
                        if ($is_autoallocate) {
                            \common\helpers\Product::do_allocate_automatic($_POST['uprid'], true);
                        }
                        $check_data['allocated_quantity'] = \common\helpers\Product::get_allocated($_POST['uprid']);
                        $check_data['temporary_quantity'] = \common\helpers\Product::get_allocated_temporary($_POST['uprid']);
                        $check_data['allocated_temporary_quantity'] = \common\helpers\Product::get_allocated_temporary($_POST['uprid'], true);
                        $check_data['deficit_quantity'] = \common\helpers\Product::get_stock_deficit($_POST['uprid']);
                    }
                    $check_data['products_quantity'] = $check_data['warehouse_quantity'] - ($check_data['allocated_quantity'] + $check_data['temporary_quantity']);
                    $check_data['ordered_quantity'] = $check_data['ordered_stock_quantity'];
                    $check_data['suppliers_quantity'] = $check_data['suppliers_stock_quantity'];
                    $response = $check_data;
                }
            }
        } elseif ($_POST['uprid'] > 0) {
            $products_quantity_update = (int) $_POST['products_quantity_update'];
            $products_quantity_update_prefix = $_POST['products_quantity_update_prefix'] == '-' ? '-' : '+';
            if ($products_quantity_update_prefix == '-') {
                $exist_locations = \common\models\Warehouses_Products::find()->select(['location_id', 'layers_id', 'batch_id', 'warehouse_stock_quantity'])->where(['warehouse_id' => $warehouse_id, 'suppliers_id' => $w_suppliers_id, 'products_id' => $_POST['uprid']])->order_by(['location_id' => SORT_ASC, 'layers_id' => SORT_ASC, 'batch_id' => SORT_ASC])->as_array()->all();
                foreach ($exist_locations as $location) {
                    if (isset($_POST['stock_minus_qty_' . $location['location_id'] . '_' . $location['layers_id'] . '_' . $location['batch_id']]) && $_POST['stock_minus_qty_' . $location['location_id'] . '_' . $location['layers_id'] . '_' . $location['batch_id']] > 0) {
                        $update_data[] = ['quantity' => (int) $_POST['stock_minus_qty_' . $location['location_id'] . '_' . $location['layers_id'] . '_' . $location['batch_id']], 'prefix' => $products_quantity_update_prefix, 'location' => $location['location_id'], 'layers_id' => $location['layers_id'], 'batch_id' => $location['batch_id']];
                    }
                }
            } else {
                $update_data[] = ['quantity' => $products_quantity_update, 'prefix' => $products_quantity_update_prefix, 'location' => $location_id, 'layers_id' => $layers_id, 'batch_id' => $batch_id];
            }
            foreach ($update_data as $update_item) {
                if ($update_item['quantity'] > 0) {
                    $check_data = tep_db_fetch_array(tep_db_query('select products_quantity, ordered_stock_quantity, suppliers_stock_quantity from ' . TABLE_PRODUCTS . " where products_id = '" . (int) $_POST['uprid'] . "'"));
                    global $login_id;
                    //\common\helpers\Product::log_stock_history_before_update($_POST['uprid'], $updateItem['quantity'], $updateItem['prefix'], ['warehouse_id' => $warehouse_id, 'comments' => TEXT_MANUALL_STOCK_UPDATE . (trim($stock_comments) != '' ? ': ' . $stock_comments : ''), 'admin_id' => $login_id]);
                    if ($warehouse_id > 0) {
                        $parameters = ['layers_id' => $update_item['layers_id'], 'batch_id' => $update_item['batch_id'], 'admin_id' => $login_id, 'comments' => TEXT_MANUALL_STOCK_UPDATE . (trim($stock_comments) != '' ? ': ' . $stock_comments : '')];
                        $check_data['warehouse_quantity'] = \common\helpers\Warehouses::update_products_quantity($_POST['uprid'], $warehouse_id, $update_item['quantity'], $update_item['prefix'], $w_suppliers_id, $update_item['location'], $parameters);
                        if ($is_autoallocate) {
                            \common\helpers\Product::do_allocate_automatic($_POST['uprid'], true);
                        }
                        $check_data['allocated_quantity'] = \common\helpers\Product::get_allocated($_POST['uprid']);
                        $check_data['temporary_quantity'] = \common\helpers\Product::get_allocated_temporary($_POST['uprid']);
                        $check_data['allocated_temporary_quantity'] = \common\helpers\Product::get_allocated_temporary($_POST['uprid'], true);
                        $check_data['deficit_quantity'] = \common\helpers\Product::get_stock_deficit($_POST['uprid']);
                    } else {
                        tep_db_query('update ' . TABLE_PRODUCTS . ' set products_quantity = products_quantity ' . $update_item['prefix'] . $update_item['quantity'] . " where products_id = '" . (int) $_POST['uprid'] . "'");
                        if ($update_item['prefix'] == '-') {
                            $check_data['warehouse_quantity'] -= $update_item['quantity'];
                        } else {
                            $check_data['warehouse_quantity'] += $update_item['quantity'];
                        }
                        if ($is_autoallocate) {
                            \common\helpers\Product::do_allocate_automatic($_POST['uprid'], true);
                        }
                        $check_data['allocated_quantity'] = \common\helpers\Product::get_allocated($_POST['uprid']);
                        $check_data['temporary_quantity'] = \common\helpers\Product::get_allocated_temporary($_POST['uprid']);
                        $check_data['allocated_temporary_quantity'] = \common\helpers\Product::get_allocated_temporary($_POST['uprid'], true);
                        $check_data['deficit_quantity'] = \common\helpers\Product::get_stock_deficit($_POST['uprid']);
                    }
                    $check_data['products_quantity'] = $check_data['warehouse_quantity'] - ($check_data['allocated_quantity'] + $check_data['temporary_quantity']);
                    $check_data['ordered_quantity'] = $check_data['ordered_stock_quantity'];
                    $check_data['suppliers_quantity'] = $check_data['suppliers_stock_quantity'];
                    $response = $check_data;
                }
            }
        }
        if ($response) {
            if ($ext = \common\helpers\Acl::check_extension_allowed('ProductAssets', 'allowed')) {
                if ($ext::get_control_instance($_POST['uprid'])->need_stock_control()) {
                    $response['warehouse_quantity'] = $ext::check_stock($_POST['uprid']);
                    $response['products_quantity'] = $response['warehouse_quantity'] - ($response['allocated_quantity'] + $response['temporary_quantity']);
                }
            }
        }
        if (isset($response['allocated_quantity'])) {
            $response['allocated_quantity'] -= $response['allocated_temporary_quantity'];
        }
        foreach ($response as &$value) {
            $value = \common\helpers\Product::get_virtual_item_quantity($_POST['uprid'], $value);
        }
        unset($value);
        echo json_encode($response);
    }
    public function action_product_stock_details()
    {
        $uprid = Yii::$app->request->post('uprid');
        /** @var \common\extensions\Inventory\Inventory $invAllowed */
        $inv_allowed = \common\helpers\Extensions::is_allowed('Inventory');
        if ($inv_allowed) {
            $uprid = \common\helpers\Inventory::normalize_inventory_id($uprid);
        }
        $p_info = \common\models\Products::find_one((int) $uprid)->get_attributes();
        if (($p_info->parent_products_id ?? null) && ($p_info->products_id_stock ?? null)) {
            $prid = $p_info->products_id_stock ?? null;
            $p_info = \common\models\Products::find_one($prid)->get_attributes();
            if ($inv_allowed) {
                $uprid = $prid . substr($uprid, strpos($uprid, '{'));
            } else {
                $uprid = $prid;
            }
        }
        if ($inv_allowed && strpos($uprid, '{') !== false && \common\helpers\Inventory::get_prid($uprid) > 0) {
            $_data = tep_db_fetch_array(tep_db_query('select products_quantity, ordered_stock_quantity, suppliers_stock_quantity from ' . TABLE_INVENTORY . " where products_id = '" . tep_db_input($uprid) . "'"));
            $check_data['warehouse_quantity'] = \common\helpers\Product::get_quantity($uprid);
        } else {
            $_data = (array) $p_info;
            $check_data['warehouse_quantity'] = $_data['warehouse_stock_quantity'];
        }
        $check_data['ordered_quantity'] = $_data['ordered_stock_quantity'] ?? 0;
        $check_data['suppliers_quantity'] = $_data['suppliers_stock_quantity'] ?? 0;
        $check_data['allocated_quantity'] = \common\helpers\Product::get_allocated($uprid);
        $check_data['temporary_quantity'] = \common\helpers\Product::get_allocated_temporary($uprid);
        $check_data['allocated_temporary_quantity'] = \common\helpers\Product::get_allocated_temporary($uprid, true);
        $check_data['deficit_quantity'] = \common\helpers\Product::get_stock_deficit($uprid);
        $check_data['products_quantity'] = $check_data['warehouse_quantity'] - ($check_data['allocated_quantity'] + $check_data['temporary_quantity']);
        $response = $check_data;
        if (!empty($response)) {
            if ($ext = \common\helpers\Acl::check_extension_allowed('ProductAssets', 'allowed')) {
                if ($ext::get_control_instance($uprid)->need_stock_control()) {
                    $response['warehouse_quantity'] = $ext::check_stock($uprid);
                    $response['products_quantity'] = $response['warehouse_quantity'] - ($response['allocated_quantity'] + $response['temporary_quantity']);
                }
            }
        }
        if (isset($response['allocated_quantity'])) {
            $response['allocated_quantity'] -= $response['allocated_temporary_quantity'];
        }
        foreach ($response as &$value) {
            $value = \common\helpers\Product::get_virtual_item_quantity($uprid, $value);
        }
        unset($value);
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $response;
    }
    public function action_stock()
    {
        \common\helpers\Translation::init('admin/categories');
        $prid = Yii::$app->request->get('prid');
        $suppliers_id = (int) Yii::$app->request->get('suppliers_id', 0);
        $warehouse_id = (int) Yii::$app->request->get('warehouse_id', 0);
        return $this->render_ajax('stock', ['prid' => $prid, 'suppliers_id' => $suppliers_id, 'warehouse_id' => $warehouse_id]);
    }
    public function action_warehouses_stock()
    {
        \common\helpers\Translation::init('admin/categories');
        if (Yii::$app->request->is_post) {
            $prid = Yii::$app->request->post('prid');
            $suppliers_id = (int) Yii::$app->request->post('suppliers_id', 0);
            $empty_row = (int) Yii::$app->request->post('empty_row');
            if (count(\common\helpers\Product::get_child_array($prid)) == 0) {
                $quantity_update = Yii::$app->request->post('quantity_update', []);
                $quantity_prefix = Yii::$app->request->post('quantity_prefix', []);
                $stock_comments = Yii::$app->request->post('stock_comments', []);
                foreach ($quantity_update as $warehouse_id => $quantity) {
                    if ($quantity > 0) {
                        $prefix = $quantity_prefix[$warehouse_id] == '-' ? '-' : '+';
                        $comments = $stock_comments[$warehouse_id];
                        if (strpos($prid, '{') !== false) {
                            $check_data = tep_db_fetch_array(tep_db_query('select products_quantity from ' . TABLE_INVENTORY . " where products_id = '" . tep_db_input($prid) . "'"));
                            if (!$check_data) {
                                tep_db_query('insert into ' . TABLE_INVENTORY . " set inventory_id = '', products_id = '" . tep_db_input($prid) . "', prid = '" . (int) \common\helpers\Inventory::get_prid($prid) . "'");
                            }
                        }
                        global $login_id;
                        //\common\helpers\Product::log_stock_history_before_update($prid, $quantity, $prefix, ['warehouse_id' => $warehouse_id, 'suppliers_id' => $suppliers_id, 'comments' => TEXT_MANUALL_STOCK_UPDATE . (trim($comments) != '' ? ': ' . $comments : ''), 'admin_id' => $login_id]);
                        $parameters = ['admin_id' => $login_id, 'comments' => TEXT_MANUALL_STOCK_UPDATE . (trim($comments) != '' ? ': ' . $comments : '')];
                        \common\helpers\Warehouses::update_products_quantity($prid, $warehouse_id, $quantity, $prefix, $suppliers_id, 0, $parameters);
                    }
                }
            }
        } else {
            $prid = Yii::$app->request->get('prid');
            $suppliers_id = (int) Yii::$app->request->get('suppliers_id', 0);
            $empty_row = (int) Yii::$app->request->get('empty_row');
        }
        $product_allocated_array = [];
        foreach (\common\helpers\Product::get_allocated_array($prid) as $product_allocated_record) {
            $product_allocated_array[$product_allocated_record['warehouse_id']][$product_allocated_record['suppliers_id']] += $product_allocated_record['allocate_received'] - $product_allocated_record['allocate_dispatched'];
            if ($suppliers_id == 0) {
                $product_allocated_array[$product_allocated_record['warehouse_id']][$suppliers_id] += $product_allocated_record['allocate_received'] - $product_allocated_record['allocate_dispatched'];
            }
        }
        $product_allocated_temporary_array = [];
        foreach (\common\helpers\Product::get_allocated_temporary_array($prid) as $product_allocated_temporary_record) {
            $product_allocated_temporary_array[$product_allocated_temporary_record['warehouse_id']][$product_allocated_temporary_record['suppliers_id']] += $product_allocated_temporary_record['temporary_stock_quantity'];
            if ($suppliers_id == 0) {
                $product_allocated_temporary_array[$product_allocated_temporary_record['warehouse_id']][$suppliers_id] += $product_allocated_temporary_record['temporary_stock_quantity'];
            }
        }
        $supplier = \common\helpers\Suppliers::get_suppliers_list($prid);
        if ($suppliers_id == 0 && count($supplier) > 1) {
            $master = 1;
        } else {
            $master = 0;
        }
        $warehouses = [];
        $products_quantity = $allocated_quantity = $temporary_quantity = $warehouse_quantity = $ordered_quantity = 0;
        $spq = \common\models\Suppliers_Products::find()->add_select('status as sp_status, suppliers_id');
        if (strpos($prid, '{') !== false) {
            $spq->and_where(['uprid' => tep_db_input($prid), 'products_id' => (int) $prid]);
        } else {
            $spq->and_where(['products_id' => (int) $prid]);
        }
        $spdata = $spq->index_by('suppliers_id')->as_array()->all();
        $warehouses_query = \common\models\Warehouses::find()->select(['warehouse_id', 'warehouse_name', 'sort_order', 'status'])->order_by(['sort_order' => SORT_ASC, 'warehouse_name' => SORT_ASC])->as_array()->all();
        foreach ($warehouses_query as $warehouses_record) {
            $warehouses_stock_query = \common\models\Warehouses_Products::find()->select(['sum(warehouse_stock_quantity) as warehouse_stock_quantity', 'sum(ordered_stock_quantity) as ordered_stock_quantity'])->where(['warehouse_id' => $warehouses_record['warehouse_id']]);
            if ($suppliers_id > 0) {
                $warehouses_stock_query->and_where(['suppliers_id' => (int) $suppliers_id]);
            }
            if (strpos($prid, '{') !== false) {
                $warehouses_stock_query->and_where(['products_id' => tep_db_input($prid)]);
            } else {
                $warehouses_stock_query->and_where(['products_id' => (int) $prid]);
            }
            $warehouses_stock = $warehouses_stock_query->as_array()->one();
            $warehouses_item = ['id' => $warehouses_record['warehouse_id'], 'name' => $warehouses_record['warehouse_name'], 'sort_order' => $warehouses_record['sort_order'], 'allocated_quantity' => isset($product_allocated_array[$warehouses_record['warehouse_id']][$suppliers_id]) ? $product_allocated_array[$warehouses_record['warehouse_id']][$suppliers_id] : 0, 'temporary_quantity' => isset($product_allocated_temporary_array[$warehouses_record['warehouse_id']][$suppliers_id]) ? $product_allocated_temporary_array[$warehouses_record['warehouse_id']][$suppliers_id] : 0, 'warehouse_quantity' => (int) $warehouses_stock['warehouse_stock_quantity'], 'ordered_quantity' => (int) $warehouses_stock['ordered_stock_quantity'], 'master' => $master, 'actions' => '', 'warehouse_disabled' => !$warehouses_record['status']];
            $warehouses_item['products_quantity'] = $warehouses_item['warehouse_quantity'] - ($warehouses_item['allocated_quantity'] + $warehouses_item['temporary_quantity']);
            if ($warehouses_record['status']) {
                $products_quantity += $warehouses_item['products_quantity'];
                $allocated_quantity += $warehouses_item['allocated_quantity'];
                $temporary_quantity += $warehouses_item['temporary_quantity'];
                $warehouse_quantity += $warehouses_item['warehouse_quantity'];
                $ordered_quantity += $warehouses_item['ordered_quantity'];
            }
            $show_row = false;
            if ($empty_row == 1) {
                $show_row = true;
            } elseif ($warehouses_item['products_quantity'] > 0 || $warehouses_item['allocated_quantity'] > 0 || $warehouses_item['temporary_quantity'] > 0 || $warehouses_item['warehouse_quantity'] > 0 || $warehouses_item['ordered_quantity'] > 0) {
                $show_row = true;
            }
            if ($show_row) {
                $warehouses[] = $warehouses_item;
                if ($master == 1) {
                    foreach ($supplier as $s_id => $s_name) {
                        $warehouses_stock_query = \common\models\Warehouses_Products::find()->select(['sum(warehouse_stock_quantity) as warehouse_stock_quantity', 'sum(ordered_stock_quantity) as ordered_stock_quantity'])->where(['warehouse_id' => $warehouses_record['warehouse_id']]);
                        $warehouses_stock_query->and_where(['suppliers_id' => (int) $s_id]);
                        if (strpos($prid, '{') !== false) {
                            $warehouses_stock_query->and_where(['products_id' => tep_db_input($prid)]);
                        } else {
                            $warehouses_stock_query->and_where(['products_id' => (int) $prid]);
                        }
                        $warehouses_stock = $warehouses_stock_query->as_array()->one();
                        if (!empty($spdata[$s_id]['sp_status'])) {
                            $s_name = '&nbsp;&nbsp;' . $s_name;
                        } else {
                            $s_name = '<div class="dis_module">' . '&nbsp;&nbsp;' . $s_name . '</div>';
                        }
                        $warehouses_item = ['id' => $warehouses_record['warehouse_id'], 'name' => $s_name, 'sort_order' => $warehouses_record['sort_order'], 'allocated_quantity' => isset($product_allocated_array[$warehouses_record['warehouse_id']][$s_id]) ? $product_allocated_array[$warehouses_record['warehouse_id']][$s_id] : 0, 'temporary_quantity' => isset($product_allocated_temporary_array[$warehouses_record['warehouse_id']][$s_id]) ? $product_allocated_temporary_array[$warehouses_record['warehouse_id']][$s_id] : 0, 'warehouse_quantity' => (int) $warehouses_stock['warehouse_stock_quantity'], 'ordered_quantity' => (int) $warehouses_stock['ordered_stock_quantity'], 'master' => 0, 'actions' => ' <a href="' . Yii::$app->url_manager->create_url(['categories/update-stock', 'products_id' => $prid, 'suppliers_id' => $s_id, 'warehouse_id' => $warehouses_record['warehouse_id']]) . '" class="right-link" data-class="update-stock-popup">' . TEXT_UPDATE_STOCK . '</a>'];
                        $warehouses_item['products_quantity'] = $warehouses_item['warehouse_quantity'] - ($warehouses_item['allocated_quantity'] + $warehouses_item['temporary_quantity']);
                        if ($empty_row == 1) {
                            $warehouses[] = $warehouses_item;
                        } elseif ($warehouses_item['products_quantity'] > 0 || $warehouses_item['allocated_quantity'] > 0 || $warehouses_item['temporary_quantity'] > 0 || $warehouses_item['warehouse_quantity'] > 0 || $warehouses_item['ordered_quantity'] > 0) {
                            $warehouses[] = $warehouses_item;
                        }
                    }
                }
            }
        }
        // Total
        $qrap_start = '<b>';
        $qrap_end = '</b>';
        $warehouses[] = ['id' => 0, 'name' => $qrap_start . TEXT_TOTAL . $qrap_end, 'sort_order' => 777777777, 'products_quantity' => $qrap_start . (int) $products_quantity . $qrap_end, 'allocated_quantity' => $qrap_start . (int) $allocated_quantity . $qrap_end, 'temporary_quantity' => $qrap_start . (int) $temporary_quantity . $qrap_end, 'warehouse_quantity' => $qrap_start . (int) $warehouse_quantity . $qrap_end, 'ordered_quantity' => $qrap_start . (int) $ordered_quantity . $qrap_end, 'master' => $master, 'actions' => ''];
        if ($suppliers_id == 0 && count($supplier) <= 1) {
            if (count($supplier) == 1) {
                $suppliers_id = key($supplier);
            } else {
                $suppliers_id = \common\helpers\Suppliers::get_default_supplier_id();
            }
        }
        return $this->render_ajax('warehouses-stock', ['warehouses' => $warehouses, 'prid' => $prid, 'suppliers_id' => $suppliers_id, 'empty_row' => $empty_row, 'master' => $master]);
    }
    public function action_warehouses_relocate()
    {
        \common\helpers\Translation::init('admin/categories');
        if (Yii::$app->request->is_post) {
            $prid = Yii::$app->request->post('prid');
            $suppliers_id = (int) Yii::$app->request->post('suppliers_id', 0);
            if (count(\common\helpers\Product::get_child_array($prid)) == 0) {
                $from_warehouse = (int) Yii::$app->request->post('from_warehouse');
                $to_warehouse = (int) Yii::$app->request->post('to_warehouse');
                $quantity_update = 0;
                $update_data = [];
                $exist_locations = \common\models\Warehouses_Products::find()->select(['location_id', 'layers_id', 'batch_id', 'warehouse_stock_quantity'])->where(['warehouse_id' => $from_warehouse, 'suppliers_id' => $suppliers_id, 'products_id' => $prid])->order_by(['location_id' => SORT_ASC])->as_array()->all();
                foreach ($exist_locations as $location) {
                    if (isset($_POST['stock_minus_qty_' . $location['location_id'] . '_' . $location['layers_id'] . '_' . $location['batch_id']]) && $_POST['stock_minus_qty_' . $location['location_id'] . '_' . $location['layers_id'] . '_' . $location['batch_id']] > 0) {
                        $update_data[] = ['quantity' => (int) $_POST['stock_minus_qty_' . $location['location_id'] . '_' . $location['layers_id'] . '_' . $location['batch_id']], 'prefix' => '-', 'location' => $location['location_id'], 'layer' => $location['layers_id'], 'batch' => $location['batch_id'], 'warehouse_id' => $from_warehouse];
                        $quantity_update += (int) $_POST['stock_minus_qty_' . $location['location_id'] . '_' . $location['layers_id'] . '_' . $location['batch_id']];
                    }
                }
                if (strpos($prid, '{') !== false) {
                    $check_data = tep_db_fetch_array(tep_db_query('select products_quantity from ' . TABLE_INVENTORY . " where products_id = '" . tep_db_input($prid) . "'"));
                    if (!$check_data) {
                        tep_db_query('insert into ' . TABLE_INVENTORY . " set inventory_id = '', products_id = '" . tep_db_input($prid) . "', prid = '" . (int) \common\helpers\Inventory::get_prid($prid) . "'");
                    }
                }
                if ($quantity_update > 0) {
                    $location_id = 0;
                    $location_ids = Yii::$app->request->post('box_location');
                    if (is_array($location_ids)) {
                        foreach ($location_ids as $id) {
                            if ($id > 0) {
                                $location_id = $id;
                            }
                        }
                    }
                    $expiry_date = Yii::$app->request->post('expiry_date', '');
                    $expiry_date = \common\helpers\Date::prepare_input_date($expiry_date);
                    $layers_id = \common\helpers\Warehouses::get_warehouses_products_layers_i_dby_expiry_date($expiry_date);
                    $batch_name = Yii::$app->request->post('batch_name', '');
                    $batch_id = \common\helpers\Warehouses::get_warehouses_products_batch_i_dby_batch_name($batch_name);
                    $update_data[] = ['quantity' => $quantity_update, 'prefix' => '+', 'location' => $location_id, 'layer' => $layers_id, 'batch' => $batch_id, 'warehouse_id' => $to_warehouse];
                    //$quantity_update = min($quantity_update, \common\helpers\Warehouses::get_products_quantity($prid, $from_warehouse, $suppliers_id));
                    global $login_id;
                    $comments = sprintf(TEXT_MANUAL_STOCK_RELOCATE, \common\helpers\Warehouses::get_warehouse_name($from_warehouse), \common\helpers\Warehouses::get_warehouse_name($to_warehouse));
                    $parameters = ['admin_id' => $login_id, 'comments' => $comments];
                    foreach ($update_data as $update_item) {
                        $parameters['layers_id'] = $update_item['layer'];
                        $parameters['batch_id'] = $update_item['batch'];
                        \common\helpers\Warehouses::update_products_quantity($prid, $update_item['warehouse_id'], $update_item['quantity'], $update_item['prefix'], $suppliers_id, $update_item['location'], $parameters);
                    }
                }
            }
        } else {
            $prid = Yii::$app->request->get('prid');
            $suppliers_id = (int) Yii::$app->request->get('suppliers_id', 0);
        }
        $product_allocated_array = [];
        foreach (\common\helpers\Product::get_allocated_array($prid) as $product_allocated_record) {
            $tmp_supplier_id = $suppliers_id > 0 ? $product_allocated_record['suppliers_id'] : 0;
            if (!isset($product_allocated_array[$product_allocated_record['warehouse_id']][$tmp_supplier_id])) {
                $product_allocated_array[$product_allocated_record['warehouse_id']][$tmp_supplier_id] = 0;
            }
            $product_allocated_array[$product_allocated_record['warehouse_id']][$tmp_supplier_id] += $product_allocated_record['allocate_received'] - $product_allocated_record['allocate_dispatched'];
        }
        $product_allocated_temporary_array = [];
        foreach (\common\helpers\Product::get_allocated_temporary_array($prid) as $product_allocated_temporary_record) {
            $tmp_supplier_id = $suppliers_id > 0 ? $product_allocated_temporary_record['suppliers_id'] : 0;
            if (!isset($product_allocated_temporary_array[$product_allocated_temporary_record['warehouse_id']][$tmp_supplier_id])) {
                $product_allocated_temporary_array[$product_allocated_temporary_record['warehouse_id']][$tmp_supplier_id] = 0;
            }
            $product_allocated_temporary_array[$product_allocated_temporary_record['warehouse_id']][$tmp_supplier_id] += $product_allocated_temporary_record['temporary_stock_quantity'];
        }
        $warehouses = [];
        $products_quantity = $allocated_quantity = $temporary_quantity = $warehouse_quantity = $ordered_quantity = 0;
        $warehouses_query = \common\models\Warehouses::find()->select(['warehouse_id', 'warehouse_name', 'sort_order'])->where(['status' => 1])->order_by(['sort_order' => SORT_ASC, 'warehouse_name' => SORT_ASC])->as_array()->all();
        foreach ($warehouses_query as $warehouses_record) {
            $warehouses_stock_query = \common\models\Warehouses_Products::find()->select(['sum(warehouse_stock_quantity) as warehouse_stock_quantity', 'sum(ordered_stock_quantity) as ordered_stock_quantity'])->where(['warehouse_id' => $warehouses_record['warehouse_id']]);
            if ($suppliers_id > 0) {
                $warehouses_stock_query->and_where(['suppliers_id' => (int) $suppliers_id]);
            }
            if (strpos($prid, '{') !== false) {
                $warehouses_stock_query->and_where(['products_id' => tep_db_input($prid)]);
            } else {
                $warehouses_stock_query->and_where(['products_id' => (int) $prid]);
            }
            $warehouses_stock = $warehouses_stock_query->as_array()->one();
            $warehouses_item = ['id' => $warehouses_record['warehouse_id'], 'name' => $warehouses_record['warehouse_name'], 'sort_order' => $warehouses_record['sort_order'], 'allocated_quantity' => isset($product_allocated_array[$warehouses_record['warehouse_id']][$suppliers_id]) ? $product_allocated_array[$warehouses_record['warehouse_id']][$suppliers_id] : 0, 'temporary_quantity' => isset($product_allocated_temporary_array[$warehouses_record['warehouse_id']][$suppliers_id]) ? $product_allocated_temporary_array[$warehouses_record['warehouse_id']][$suppliers_id] : 0, 'warehouse_quantity' => (int) $warehouses_stock['warehouse_stock_quantity'], 'ordered_quantity' => (int) $warehouses_stock['ordered_stock_quantity']];
            $warehouses_item['products_quantity'] = $warehouses_item['warehouse_quantity'] - ($warehouses_item['allocated_quantity'] + $warehouses_item['temporary_quantity']);
            $products_quantity += $warehouses_item['products_quantity'];
            $allocated_quantity += $warehouses_item['allocated_quantity'];
            $temporary_quantity += $warehouses_item['temporary_quantity'];
            $warehouse_quantity += $warehouses_item['warehouse_quantity'];
            $ordered_quantity += $warehouses_item['ordered_quantity'];
            $warehouses[] = $warehouses_item;
        }
        // Total
        $warehouses[] = ['id' => 0, 'name' => TEXT_TOTAL, 'sort_order' => 777777777, 'products_quantity' => (int) $products_quantity, 'allocated_quantity' => (int) $allocated_quantity, 'temporary_quantity' => (int) $temporary_quantity, 'warehouse_quantity' => (int) $warehouse_quantity, 'ordered_quantity' => (int) $ordered_quantity];
        $supp_id = $suppliers_id;
        if ($supp_id == 0) {
            $sp = \common\helpers\Suppliers::get_suppliers_to_uprid($prid);
            if ($sp) {
                foreach ($sp as $_sp) {
                    $supp_id = $_sp->suppliers_id;
                    break;
                }
            }
            unset($sp);
        }
        return $this->render_ajax('warehouses-relocate', ['warehouses' => $warehouses, 'prid' => $prid, 'suppliers_id' => $suppliers_id, 'supp_id' => $supp_id]);
    }
    public function action_suppliers_stock()
    {
        \common\helpers\Translation::init('admin/categories');
        if (Yii::$app->request->is_post) {
            $prid = Yii::$app->request->post('prid');
            $warehouse_id = (int) Yii::$app->request->post('warehouse_id', 0);
            $empty_row = (int) Yii::$app->request->post('empty_row');
            if (count(\common\helpers\Product::get_child_array($prid)) == 0) {
                $quantity_update = Yii::$app->request->post('quantity_update', []);
                $quantity_prefix = Yii::$app->request->post('quantity_prefix', []);
                $stock_comments = Yii::$app->request->post('stock_comments', []);
                foreach ($quantity_update as $suppliers_id => $quantity) {
                    if ($quantity > 0) {
                        $prefix = $quantity_prefix[$suppliers_id] == '-' ? '-' : '+';
                        $comments = $stock_comments[$suppliers_id];
                        if (strpos($prid, '{') !== false) {
                            $check_data = tep_db_fetch_array(tep_db_query('select products_quantity from ' . TABLE_INVENTORY . " where products_id = '" . tep_db_input($prid) . "'"));
                            if (!$check_data) {
                                tep_db_query('insert into ' . TABLE_INVENTORY . " set inventory_id = '', products_id = '" . tep_db_input($prid) . "', prid = '" . (int) \common\helpers\Inventory::get_prid($prid) . "'");
                            }
                        }
                        global $login_id;
                        //\common\helpers\Product::log_stock_history_before_update($prid, $quantity, $prefix, ['warehouse_id' => $warehouse_id, 'suppliers_id' => $suppliers_id, 'comments' => TEXT_MANUALL_STOCK_UPDATE . (trim($comments) != '' ? ': ' . $comments : ''), 'admin_id' => $login_id]);
                        $parameters = ['admin_id' => $login_id, 'comments' => TEXT_MANUALL_STOCK_UPDATE . (trim($comments) != '' ? ': ' . $comments : '')];
                        \common\helpers\Warehouses::update_products_quantity($prid, $warehouse_id, $quantity, $prefix, $suppliers_id, 0, $parameters);
                    }
                }
            }
        } else {
            $prid = Yii::$app->request->get('prid');
            $warehouse_id = (int) Yii::$app->request->get('warehouse_id', 0);
            $empty_row = (int) Yii::$app->request->get('empty_row');
        }
        $product_allocated_array = [];
        foreach (\common\helpers\Product::get_allocated_array($prid) as $product_allocated_record) {
            $product_allocated_array[$product_allocated_record['suppliers_id']][$product_allocated_record['warehouse_id']] += $product_allocated_record['allocate_received'] - $product_allocated_record['allocate_dispatched'];
            if ($warehouse_id == 0) {
                $product_allocated_array[$product_allocated_record['suppliers_id']][$warehouse_id] += $product_allocated_record['allocate_received'] - $product_allocated_record['allocate_dispatched'];
            }
        }
        $product_allocated_temporary_array = [];
        foreach (\common\helpers\Product::get_allocated_temporary_array($prid) as $product_allocated_temporary_record) {
            $product_allocated_temporary_array[$product_allocated_temporary_record['suppliers_id']][$product_allocated_temporary_record['warehouse_id']] += $product_allocated_temporary_record['temporary_stock_quantity'];
            if ($warehouse_id == 0) {
                $product_allocated_temporary_array[$product_allocated_temporary_record['suppliers_id']][$warehouse_id] += $product_allocated_temporary_record['temporary_stock_quantity'];
            }
        }
        if ($warehouse_id == 0 && \common\helpers\Warehouses::get_warehouses_count() > 1) {
            $master = 1;
        } else {
            $master = 0;
        }
        $suppliers = [];
        $products_quantity = $allocated_quantity = $temporary_quantity = $warehouse_quantity = $ordered_quantity = 0;
        $spq = \common\models\Suppliers_Products::find()->add_select('status as sp_status, suppliers_id');
        if (strpos($prid, '{') !== false) {
            $spq->and_where(['uprid' => tep_db_input($prid), 'products_id' => (int) $prid]);
        } else {
            $spq->and_where(['products_id' => (int) $prid]);
        }
        $spdata = $spq->index_by('suppliers_id')->as_array()->all();
        $suppliers_query = \common\models\Suppliers::find()->select(['suppliers_id', 'suppliers_name', 'sort_order'])->where(['status' => 1])->order_by(['sort_order' => SORT_ASC, 'suppliers_name' => SORT_ASC])->as_array()->all();
        foreach ($suppliers_query as $suppliers_record) {
            $suppliers_stock_query = \common\models\Warehouses_Products::find()->select(['sum(warehouse_stock_quantity) as warehouse_stock_quantity', 'sum(ordered_stock_quantity) as ordered_stock_quantity'])->where(['suppliers_id' => $suppliers_record['suppliers_id']]);
            if ($warehouse_id > 0) {
                $suppliers_stock_query->and_where(['warehouse_id' => (int) $warehouse_id]);
            }
            if (strpos($prid, '{') !== false) {
                $suppliers_stock_query->and_where(['products_id' => tep_db_input($prid)]);
            } else {
                $suppliers_stock_query->and_where(['products_id' => (int) $prid]);
            }
            $suppliers_stock = $suppliers_stock_query->as_array()->one();
            if (!empty($spdata[$suppliers_record['suppliers_id']]['sp_status'])) {
                $tmp_name = $suppliers_record['suppliers_name'];
            } else {
                $tmp_name = '<div class="dis_module">' . $suppliers_record['suppliers_name'] . '</div>';
            }
            $suppliers_item = ['id' => $suppliers_record['suppliers_id'], 'name' => $tmp_name, 'sort_order' => $suppliers_record['sort_order'], 'allocated_quantity' => isset($product_allocated_array[$suppliers_record['suppliers_id']][$warehouse_id]) ? $product_allocated_array[$suppliers_record['suppliers_id']][$warehouse_id] : 0, 'temporary_quantity' => isset($product_allocated_temporary_array[$suppliers_record['suppliers_id']][$warehouse_id]) ? $product_allocated_temporary_array[$suppliers_record['suppliers_id']][$warehouse_id] : 0, 'warehouse_quantity' => (int) $suppliers_stock['warehouse_stock_quantity'], 'ordered_quantity' => (int) $suppliers_stock['ordered_stock_quantity'], 'master' => $master, 'actions' => ''];
            $suppliers_item['products_quantity'] = $suppliers_item['warehouse_quantity'] - ($suppliers_item['allocated_quantity'] + $suppliers_item['temporary_quantity']);
            $products_quantity += $suppliers_item['products_quantity'];
            $allocated_quantity += $suppliers_item['allocated_quantity'];
            $temporary_quantity += $suppliers_item['temporary_quantity'];
            $warehouse_quantity += $suppliers_item['warehouse_quantity'];
            $ordered_quantity += $suppliers_item['ordered_quantity'];
            $show_row = false;
            if ($empty_row == 1) {
                $show_row = true;
            } elseif ($suppliers_item['products_quantity'] > 0 || $suppliers_item['allocated_quantity'] > 0 || $suppliers_item['temporary_quantity'] || $suppliers_item['warehouse_quantity'] || $suppliers_item['ordered_quantity']) {
                $show_row = true;
            }
            if ($show_row) {
                $suppliers[] = $suppliers_item;
                if ($master == 1) {
                    $warehouses = \common\helpers\Warehouses::get_warehouses();
                    foreach ($warehouses as $wh_item) {
                        $w_id = $wh_item['id'];
                        $w_name = $wh_item['text'];
                        $suppliers_stock_query = \common\models\Warehouses_Products::find()->select(['sum(warehouse_stock_quantity) as warehouse_stock_quantity', 'sum(ordered_stock_quantity) as ordered_stock_quantity'])->where(['suppliers_id' => $suppliers_record['suppliers_id']]);
                        $suppliers_stock_query->and_where(['warehouse_id' => (int) $w_id]);
                        if (strpos($prid, '{') !== false) {
                            $suppliers_stock_query->and_where(['products_id' => tep_db_input($prid)]);
                        } else {
                            $suppliers_stock_query->and_where(['products_id' => (int) $prid]);
                        }
                        $suppliers_stock = $suppliers_stock_query->as_array()->one();
                        $suppliers_item = ['id' => $suppliers_record['suppliers_id'], 'name' => '&nbsp;&nbsp;' . $w_name, 'sort_order' => $suppliers_record['sort_order'], 'allocated_quantity' => isset($product_allocated_array[$suppliers_record['suppliers_id']][$warehouse_id]) ? $product_allocated_array[$suppliers_record['suppliers_id']][$warehouse_id] : 0, 'temporary_quantity' => isset($product_allocated_temporary_array[$suppliers_record['suppliers_id']][$warehouse_id]) ? $product_allocated_temporary_array[$suppliers_record['suppliers_id']][$warehouse_id] : 0, 'warehouse_quantity' => (int) $suppliers_stock['warehouse_stock_quantity'], 'ordered_quantity' => (int) $suppliers_stock['ordered_stock_quantity'], 'master' => 0, 'actions' => ' <a href="' . Yii::$app->url_manager->create_url(['categories/update-stock', 'products_id' => $prid, 'suppliers_id' => $suppliers_record['suppliers_id'], 'warehouse_id' => $w_id]) . '" class="right-link" data-class="update-stock-popup">' . TEXT_UPDATE_STOCK . '</a>'];
                        $suppliers_item['products_quantity'] = $suppliers_item['warehouse_quantity'] - ($suppliers_item['allocated_quantity'] + $suppliers_item['temporary_quantity']);
                        if ($empty_row == 1) {
                            $suppliers[] = $suppliers_item;
                        } elseif ($suppliers_item['products_quantity'] > 0 || $suppliers_item['allocated_quantity'] > 0 || $suppliers_item['temporary_quantity'] || $suppliers_item['warehouse_quantity'] || $suppliers_item['ordered_quantity']) {
                            $suppliers[] = $suppliers_item;
                        }
                    }
                }
            }
        }
        // Total
        $qrap_start = '<b>';
        $qrap_end = '</b>';
        $suppliers[] = ['id' => 0, 'name' => $qrap_start . TEXT_TOTAL . $qrap_end, 'sort_order' => 777777777, 'products_quantity' => $qrap_start . (int) $products_quantity . $qrap_end, 'allocated_quantity' => $qrap_start . (int) $allocated_quantity . $qrap_end, 'temporary_quantity' => $qrap_start . (int) $temporary_quantity . $qrap_end, 'warehouse_quantity' => $qrap_start . (int) $warehouse_quantity . $qrap_end, 'ordered_quantity' => $qrap_start . (int) $ordered_quantity . $qrap_end, 'master' => $master, 'actions' => ''];
        return $this->render_ajax('suppliers-stock', ['suppliers' => $suppliers, 'prid' => $prid, 'warehouse_id' => $warehouse_id, 'empty_row' => $empty_row, 'master' => $master]);
    }
    public function action_product_assets()
    {
        \common\helpers\Translation::init('admin/categories');
        if ($ext = \common\helpers\Acl::check_extension_allowed('ProductAssets', 'allowed')) {
            return $ext::admin_product_popup();
        }
    }
    private function search_category_tree($search_term, $platform_id = false)
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $filter_by_platform = [];
        if (is_array($platform_id)) {
            $filter_by_platform = $platform_id;
        } else {
            if (!$platform_param = Yii::$app->request->get('platform', false)) {
                $form_filter = Yii::$app->request->get('filter', '');
                $output = [];
                parse_str($form_filter, $output);
                if (isset($output['platform']) && is_array($output['platform'])) {
                    $platform_param = $output['platform'];
                }
            }
            if (isset($platform_param) && is_array($platform_param)) {
                foreach ($platform_param as $_platform_id) {
                    if ((int) $_platform_id > 0) {
                        $filter_by_platform[] = (int) $_platform_id;
                    }
                }
            }
        }
        $platform_filter_categories = '';
        if (count($filter_by_platform) > 0) {
            $platform_filter_categories .= ' and c.categories_id IN (SELECT categories_id FROM ' . TABLE_PLATFORMS_CATEGORIES . ' WHERE platform_id IN(\'' . implode("','", $filter_by_platform) . '\'))  ';
        }
        $categories_query = tep_db_query('select distinct c.categories_level, c.categories_id as id, c.parent_id, c.categories_left,  c.categories_status, cd.categories_name  as text from  ' . TABLE_CATEGORIES . ' c1 join ' . TABLE_CATEGORIES_DESCRIPTION . " cd1 on c1.categories_id=cd1.categories_id and cd1.language_id='" . (int) $languages_id . "' join " . TABLE_CATEGORIES . '  c on c.categories_left<=c1.categories_left and c.categories_right>=c1.categories_right join ' . TABLE_CATEGORIES_DESCRIPTION . " cd on c.categories_id=cd.categories_id and  cd.language_id='" . (int) $languages_id . "' and cd1.categories_name like '%" . $search_term . "%' {$platform_filter_categories} order by c.categories_left, c.sort_order, cd.categories_name");
        $categories_by_level = [];
        while ($categories = tep_db_fetch_array($categories_query)) {
            $categories['child'] = [];
            $categories_by_level[$categories['categories_level']][$categories['id']] = $categories;
        }
        $categories_tree = self::build_tree($categories_by_level);
        return $categories_tree;
    }
    public function action_categoryfilter()
    {
        $this->layout = false;
        $categorysearch = trim(tep_db_input(tep_db_prepare_input(Yii::$app->request->post('categorysearch', ''))));
        $collapsed = (bool) Yii::$app->request->post('collapsed', $this->default_collapsed);
        if ($categorysearch == '') {
            $this->view->categories_tree = $this->get_category_tree();
            $collapsed = $this->default_collapsed;
            $this->view->categories_closed_tree = array_map('intval', explode('|', \Yii::$app->session->get('closed_data')));
        } else {
            $this->view->categories_tree = $this->search_category_tree($categorysearch);
            $this->view->categories_closed_tree = [];
            $collapsed = false;
            // always expanded - as unclear what's search result
        }
        $categories_id = (int) Yii::$app->request->get('category_id', 0);
        if ($categories_id > 0) {
            $this->view->categories_opened_tree = \common\helpers\Categories::get_category_parents_ids($categories_id);
        } else {
            $this->view->categories_opened_tree = [];
        }
        return $this->render('cat_main_box', ['directOutput' => true, 'collapsed' => $collapsed]);
    }
    public function action_easy_view()
    {
        $this->layout = false;
        if ($ext = \common\helpers\Acl::check_extension_allowed('ProductEasyView', 'allowed')) {
            return $ext::admin_action_easy_view();
        }
    }
    public function action_listing_attach()
    {
        \common\helpers\Translation::init('admin/categories');
        $this->layout = false;
        $this->view->use_popup_mode = true;
        $product_id = Yii::$app->request->get('product_id', 0);
        $product_model = \common\models\Products::find_one($product_id);
        if ($product_model && !$product_model->parent_products_id) {
            if (Yii::$app->request->is_post) {
                $parent_product_id = intval(Yii::$app->request->post('parent_product_id', 0));
                \common\helpers\Product::child_attach($product_id, $parent_product_id);
                if (Yii::$app->request->post('mark_parent_as_master', 0)) {
                    if ($parent_model = \common\models\Products::find_one($parent_product_id)) {
                        $parent_model->is_listing_product = 0;
                        $parent_model->save(false);
                    }
                }
                return 'ok';
            }
            return $this->render('popup-listing-attach.tpl', ['product_id' => $product_id, 'product_name' => \common\helpers\Product::get_backend_products_name($product_id)]);
        }
    }
    public function action_listing_detach()
    {
        \common\helpers\Translation::init('admin/categories');
        $this->layout = false;
        $this->view->use_popup_mode = true;
        $product_id = Yii::$app->request->get('product_id', 0);
        $product_model = \common\models\Products::find_one($product_id);
        if ($product_model && $product_model->parent_products_id) {
            if (Yii::$app->request->is_post) {
                \common\helpers\Product::child_detach($product_id);
                return 'ok';
            }
            return $this->render('popup-listing-detach.tpl', ['product_id' => $product_id, 'product_name' => \common\helpers\Product::get_backend_products_name($product_id), 'parent_product_name' => \common\helpers\Product::get_backend_products_name($product_model->parent_products_id)]);
        }
    }
    public function action_sold()
    {
        \common\helpers\Translation::init('admin/categories');
        $prid = Yii::$app->request->get('pID');
        $sold = [];
        $op = [];
        $product = null;
        if ($prid) {
            $product = (new \yii\db\Query())->select('products_date_added')->from(TABLE_PRODUCTS)->where('products_id=:prid', [':prid' => (int) $prid])->one();
            $op = (new \yii\db\Query())->select('products_name')->from(TABLE_ORDERS_PRODUCTS)->where('uprid=:prid', [':prid' => $prid])->limit(1)->one();
            $sold[] = \backend\models\Product_Sold::from_period_sold($this, 'sold', $prid, 'DATE_SUB(CURDATE(), INTERVAL 7 DAY)', 'CURDATE()', TEXT_LAST_WEEK);
            $sold[] = \backend\models\Product_Sold::from_period_sold($this, 'sold', $prid, 'DATE_SUB(CURDATE(), INTERVAL 14 DAY)', 'DATE_SUB(CURDATE(), INTERVAL 7 day)', TEXT_WEEK_BEFORE);
            //$sold[] = '&nbsp;';
            $sold[] = \backend\models\Product_Sold::from_period_sold($this, 'sold', $prid, 'DATE_SUB(CURDATE(), INTERVAL 1 MONTH)', 'CURDATE()', TEXT_LAST_MONTH);
            for ($i = 2; $i < 7; $i++) {
                $j = $i - 1;
                $sold[] = \backend\models\Product_Sold::from_period_sold($this, 'sold', $prid, "DATE_SUB(CURDATE(), INTERVAL {$i} MONTH)", "DATE_SUB(CURDATE(), INTERVAL {$j} MONTH)", TEXT_MONTH_BEFORE);
            }
        }
        return $this->render_ajax('sold-view', ['sold' => $sold, 'name' => $op['products_name'], 'date_added' => $product['products_date_added']]);
    }
    public function action_easy_save()
    {
        if ($ext = \common\helpers\Acl::check_extension_allowed('ProductEasyView', 'allowed')) {
            return $ext::admin_action_easy_save();
        }
    }
    public function action_all_list()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $categories = \common\helpers\Categories::get_category_tree(0, '', '', '', false, false, 0, false, false, $languages_id);
        $categories_arr = [];
        foreach ($categories as $category) {
            $category['text'] = trim(str_replace('&nbsp;', ' ', $category['text']));
            unset($category['products']);
            $categories_arr[] = $category;
        }
        return json_encode($categories_arr);
    }
    public function action_brands_list()
    {
        $manufacturers = \common\models\Manufacturers::find()->select(['id' => 'manufacturers_id', 'text' => 'manufacturers_name'])->as_array()->all();
        return json_encode($manufacturers);
    }
    public function action_warehouse_location()
    {
        $this->layout = false;
        $blocks = \common\models\Location_Blocks::find()->as_array()->all();
        $blocks_list = [];
        foreach ($blocks as $value) {
            $blocks_list[$value['block_id']] = $value['block_name'];
        }
        $warehouse_id = (int) Yii::$app->request->post('warehouse_id');
        $suppliers_id = (int) Yii::$app->request->post('suppliers_id');
        $products_id = (string) Yii::$app->request->post('products_id');
        $prefix = Yii::$app->request->post('prefix');
        if ($prefix == '-') {
            $exist_locations = \common\models\Warehouses_Products::find()->select(['location_id', 'layers_id', 'batch_id', 'products_quantity'])->where(['warehouse_id' => $warehouse_id, 'suppliers_id' => $suppliers_id, 'products_id' => $products_id])->order_by(['location_id' => SORT_ASC, 'layers_id' => SORT_ASC, 'batch_id' => SORT_ASC])->as_array()->all();
            $location_list = [];
            foreach ($exist_locations as $location) {
                if ($location['products_quantity'] <= 0) {
                    continue;
                }
                $name = \common\helpers\Warehouses::get_location_path($location['location_id'], $warehouse_id, $blocks_list);
                if (empty($name)) {
                    $name = 'N/A';
                }
                if ($location['layers_id']) {
                    $name .= ', ' . \common\helpers\Translation::get_translation_value('TEXT_EXPIRY_DATE', 'admin/categories') . ' ' . \common\helpers\Date::date_short(\common\helpers\Warehouses::get_expiry_date_by_layers_id($location['layers_id']));
                }
                if ($location['batch_id']) {
                    $name .= ', ' . TEXT_WAREHOUSES_PRODUCTS_BATCH_NAME . ' ' . \common\helpers\Warehouses::get_batch_name_by_batch_id($location['batch_id']);
                }
                $location_list[] = ['id' => $location['location_id'] . '_' . $location['layers_id'] . '_' . $location['batch_id'], 'name' => $name, 'qty' => $location['products_quantity']];
            }
            return $this->render('warehouse-location-minus', ['locationList' => $location_list]);
        }
        $selected_location_id = '';
        $sublocation = [];
        $exist_locations = \common\models\Warehouses_Products::find()->select('location_id')->where(['warehouse_id' => $warehouse_id, 'suppliers_id' => $suppliers_id, 'products_id' => $products_id])->order_by(['location_id' => SORT_DESC])->as_array()->one();
        if (isset($exist_locations['location_id']) && $exist_locations['location_id'] > 0) {
            $selected_location_id = $exist_locations['location_id'];
            //build back tree
            $back_build = [];
            $loc = \common\models\Locations::find()->where(['warehouse_id' => $warehouse_id, 'location_id' => $exist_locations['location_id']])->as_array()->one();
            if (is_array($loc)) {
                $back_build[] = $loc;
                while (isset($loc['parrent_id']) && $loc['parrent_id'] > 0) {
                    $loc = \common\models\Locations::find()->where(['warehouse_id' => $warehouse_id, 'location_id' => $loc['parrent_id']])->as_array()->one();
                    if (is_array($loc)) {
                        $back_build[] = $loc;
                        $selected_location_id = $loc['location_id'];
                    }
                }
            }
            $back_build = array_reverse($back_build);
            if (count($back_build) > 1) {
                unset($back_build[0]);
                foreach ($back_build as $back_key => $back_item) {
                    $location_id = '';
                    $location_list = [];
                    $locations = \common\models\Locations::find()->where(['warehouse_id' => $warehouse_id, 'parrent_id' => $back_item['parrent_id']])->order_by('sort_order')->as_array()->all();
                    if (is_array($locations) && count($locations) > 0) {
                        $location_list[''] = PULL_DOWN_DEFAULT;
                        foreach ($locations as $location) {
                            $location_list[$location['location_id']] = $blocks_list[$location['block_id']] . ': ' . $location['location_name'];
                        }
                        $location_id = $back_item['location_id'];
                    }
                    $sublocation[] = ['locationList' => $location_list, 'location_id' => $location_id];
                }
            }
        }
        $locations = \common\models\Locations::find()->where(['warehouse_id' => $warehouse_id, 'parrent_id' => 0])->order_by('sort_order')->as_array()->all();
        if (is_array($locations) && count($locations) > 0) {
            $location_list = [];
            $location_list[''] = PULL_DOWN_DEFAULT;
            foreach ($locations as $location) {
                $location_list[$location['location_id']] = $blocks_list[$location['block_id']] . ': ' . $location['location_name'];
            }
            return $this->render('warehouse-location', ['locationList' => $location_list, 'location_id' => $selected_location_id, 'sublocation' => $sublocation, 'warehouse_id' => $warehouse_id]);
        }
    }
    public function action_warehouse_location_child()
    {
        $this->layout = false;
        $warehouse_id = (int) Yii::$app->request->post('warehouse_id');
        $location_id = (int) Yii::$app->request->post('location_id');
        if ($location_id == 0) {
            return '';
        }
        $locations = \common\models\Locations::find()->where(['warehouse_id' => $warehouse_id, 'parrent_id' => $location_id])->order_by('sort_order')->as_array()->all();
        if (is_array($locations) && count($locations) > 0) {
            $blocks = \common\models\Location_Blocks::find()->as_array()->all();
            $blocks_list = [];
            foreach ($blocks as $value) {
                $blocks_list[$value['block_id']] = $value['block_name'];
            }
            $location_list = [];
            $location_list[''] = PULL_DOWN_DEFAULT;
            foreach ($locations as $location) {
                $location_list[$location['location_id']] = $blocks_list[$location['block_id']] . ': ' . $location['location_name'];
            }
            return $this->render('warehouse-location-child', ['locationList' => $location_list, 'warehouse_id' => $warehouse_id]);
        }
    }
    public function action_update_stock()
    {
        $this->layout = false;
        \common\helpers\Translation::init('admin/categories');
        $products_id = (string) Yii::$app->request->get('products_id');
        $suppliers_id = (int) Yii::$app->request->get('suppliers_id', 0);
        $warehouse_id = (int) Yii::$app->request->get('warehouse_id', 0);
        if (strpos($products_id, '{') !== false) {
            $action = 'inventory_quantity_update';
        } else {
            $action = 'products_quantity_update';
        }
        if ($suppliers_id == 0) {
            $supplier = \common\helpers\Suppliers::get_suppliers_list($products_id);
            if (count($supplier) == 1) {
                $suppliers_id = key($supplier);
            } else {
                $suppliers_id = \common\helpers\Suppliers::get_default_supplier_id();
            }
        }
        if ($warehouse_id == 0) {
            $warehouse_id = \common\helpers\Warehouses::get_default_warehouse();
        }
        return $this->render('update-product-stock', ['products_id' => $products_id, 'warehouse_id' => $warehouse_id, 'suppliers_id' => $suppliers_id, 'action' => $action]);
    }
    public function action_order_reallocate()
    {
        \common\helpers\Translation::init('admin/categories');
        $u_product_id = \common\helpers\Inventory::normalize_id_excl_virtual(Yii::$app->request->get('prid'));
        if (Yii::$app->request->is_post) {
            $u_product_id = \common\helpers\Inventory::normalize_id_excl_virtual(Yii::$app->request->post('prid'));
        }
        \common\helpers\Product::is_valid_allocated($u_product_id);
        $warehouse_name_list = [];
        foreach (\common\models\Warehouses::find()->as_array(true)->all() as $warehouse_record) {
            $warehouse_name_list[$warehouse_record['warehouse_id']] = $warehouse_record['warehouse_name'];
        }
        unset($warehouse_record);
        $supplier_name_list = [];
        foreach (\common\models\Suppliers::find()->as_array(true)->all() as $supplier_record) {
            $supplier_name_list[$supplier_record['suppliers_id']] = $supplier_record['suppliers_name'];
        }
        unset($supplier_record);
        $location_block_list = [];
        foreach (\common\models\Location_Blocks::find()->as_array(true)->all() as $location_block_record) {
            $location_block_list[$location_block_record['block_id']] = $location_block_record['block_name'];
        }
        unset($location_block_record);
        $product_allocated_temporary_array = [];
        foreach (\common\helpers\Product::get_allocated_temporary_array($u_product_id) as $product_allocated_temporary_record) {
            $product_allocated_temporary_array[$product_allocated_temporary_record['warehouse_id']][$product_allocated_temporary_record['suppliers_id']][$product_allocated_temporary_record['location_id']][$product_allocated_temporary_record['layers_id']][$product_allocated_temporary_record['batch_id']][] = $product_allocated_temporary_record;
        }
        unset($product_allocated_temporary_record);
        $warehouse_product_array = [];
        foreach (\common\helpers\Warehouses::get_product_array($u_product_id) as $warehouse_product_record) {
            $warehouse_id = $warehouse_product_record['warehouse_id'];
            $supplier_id = $warehouse_product_record['suppliers_id'];
            $location_id = $warehouse_product_record['location_id'];
            $layers_id = $warehouse_product_record['layers_id'];
            $batch_id = $warehouse_product_record['batch_id'];
            $warehouse_available = $warehouse_product_record['warehouse_stock_quantity'];
            if (isset($product_allocated_temporary_array[$warehouse_id][$supplier_id][$location_id][$layers_id][$batch_id])) {
                foreach ($product_allocated_temporary_array[$warehouse_id][$supplier_id][$location_id][$layers_id][$batch_id] as $product_allocated_temporary_record) {
                    $warehouse_available -= $product_allocated_temporary_record['temporary_stock_quantity'];
                }
                unset($product_allocated_temporary_array[$warehouse_id][$supplier_id][$location_id][$layers_id][$batch_id]);
                unset($product_allocated_temporary_record);
            }
            $warehouse_product_array[$warehouse_id][$supplier_id][$location_id][$layers_id][$batch_id] = ['quantity' => $warehouse_available, 'allocated_real' => 0, 'allocated_update' => 0];
            unset($warehouse_available);
            unset($warehouse_id);
            unset($supplier_id);
            unset($location_id);
            unset($layers_id);
            unset($batch_id);
        }
        unset($product_allocated_temporary_array);
        unset($warehouse_product_record);
        $order_product_allocated_array = [];
        foreach ($warehouse_product_array as $warehouse_id => $supplier_array) {
            foreach ($supplier_array as $supplier_id => $location_array) {
                foreach ($location_array as $location_id => $layers_array) {
                    foreach ($layers_array as $layers_id => $batch_array) {
                        foreach ($batch_array as $batch_id => $warehouse_product_record) {
                            $location_name = trim(\common\helpers\Warehouses::get_location_path($location_id, $warehouse_id, $location_block_list));
                            if ($layers_id) {
                                $location_name .= ', ' . \common\helpers\Translation::get_translation_value('TEXT_EXPIRY_DATE', 'admin/categories') . ' ' . \common\helpers\Date::date_short(\common\helpers\Warehouses::get_expiry_date_by_layers_id($layers_id));
                            }
                            if ($batch_id) {
                                $location_name .= ', ' . TEXT_WAREHOUSES_PRODUCTS_BATCH_NAME . ' ' . \common\helpers\Warehouses::get_batch_name_by_batch_id($batch_id);
                            }
                            $order_product_allocated_array[$warehouse_id][$supplier_id][$location_id][$layers_id][$batch_id] = ['allocated_real' => 0, 'allocated_update' => 0, 'warehouseName' => isset($warehouse_name_list[$warehouse_id]) ? $warehouse_name_list[$warehouse_id] : 'N/A', 'supplierName' => isset($supplier_name_list[$supplier_id]) ? $supplier_name_list[$supplier_id] : 'N/A', 'locationName' => $location_name != '' ? $location_name : 'N/A'];
                            unset($location_name);
                        }
                    }
                }
                unset($warehouse_product_record);
                unset($location_id);
            }
            unset($location_array);
            unset($supplier_id);
        }
        unset($supplier_array);
        unset($warehouse_id);
        $order_product_id_array = [];
        foreach (\common\helpers\Product::get_allocated_array($u_product_id) as $order_product_allocate_record) {
            $order_product_id_array[$order_product_allocate_record['orders_products_id']] = $order_product_allocate_record['orders_products_id'];
        }
        unset($order_product_allocate_record);
        if (($ext = \common\helpers\Acl::check_extension_allowed('ReportFreezeStock')) && $ext::is_freezed()) {
            //skip?
        } else {
            foreach (\common\models\Orders_Products::find()->select(['orders_products_id', 'uprid'])->and_where(['products_id' => (int) $u_product_id])->and_where(['IN', 'orders_products_status', [\common\helpers\Order_Product::OPS_QUOTED, \common\helpers\Order_Product::OPS_STOCK_DEFICIT, \common\helpers\Order_Product::OPS_STOCK_ORDERED, \common\helpers\Order_Product::OPS_RECEIVED]])->as_array(true)->all() as $order_product_data) {
                if (\common\helpers\Inventory::get_inventory_id($u_product_id) === \common\helpers\Inventory::get_inventory_id($order_product_data['uprid'])) {
                    $order_product_id_array[$order_product_data['orders_products_id']] = $order_product_data['orders_products_id'];
                }
            }
            unset($order_product_data);
        }
        $order_product_array = [];
        foreach ($order_product_id_array as $order_product_id) {
            $order_product_record = \common\helpers\Order_Product::get_record($order_product_id);
            if (!$order_product_record instanceof \common\models\Orders_Products) {
                continue;
            }
            $order_record = \common\helpers\Order::get_record($order_product_record->orders_id);
            if (!$order_record instanceof \common\models\Orders) {
                continue;
            }
            $order_product_array[$order_product_id] = ['orderId' => $order_record->orders_id, 'datePurchased' => $order_record->date_purchased, 'platformId' => $order_record->platform_id, 'model' => $order_product_record->products_model, 'quantity' => \common\helpers\Order_Product::get_quantity_real($order_product_record), 'allocated_real' => 0, 'allocated_update' => 0, 'allocated_parent' => \common\helpers\Order_Product::get_quantity_real($order_product_record) - (int) $order_product_record->qty_rcvd, 'allocatedArray' => $order_product_allocated_array];
            foreach (\common\helpers\Order_Product::get_allocated_array($order_product_record) as $order_product_allocate_record) {
                $product_allocated = $order_product_allocate_record['allocate_received'] - $order_product_allocate_record['allocate_dispatched'];
                $order_product_array[$order_product_id]['quantity'] -= $order_product_allocate_record['allocate_dispatched'];
                if ($product_allocated == 0) {
                    continue;
                }
                $order_product_array[$order_product_id]['allocated_real'] += $product_allocated;
                $order_product_array[$order_product_id]['allocated_update'] = $order_product_array[$order_product_id]['allocated_real'];
                $warehouse_id = $order_product_allocate_record['warehouse_id'];
                $supplier_id = $order_product_allocate_record['suppliers_id'];
                $location_id = $order_product_allocate_record['location_id'];
                $layers_id = $order_product_allocate_record['layers_id'];
                $batch_id = $order_product_allocate_record['batch_id'];
                $location_name = trim(\common\helpers\Warehouses::get_location_path($location_id, $warehouse_id, $location_block_list));
                if ($layers_id) {
                    $location_name .= ', ' . \common\helpers\Translation::get_translation_value('TEXT_EXPIRY_DATE', 'admin/categories') . ' ' . \common\helpers\Date::date_short(\common\helpers\Warehouses::get_expiry_date_by_layers_id($layers_id));
                }
                if ($batch_id) {
                    $location_name .= ', ' . TEXT_WAREHOUSES_PRODUCTS_BATCH_NAME . ' ' . \common\helpers\Warehouses::get_batch_name_by_batch_id($batch_id);
                }
                $order_product_array[$order_product_id]['allocatedArray'][$warehouse_id][$supplier_id][$location_id][$layers_id][$batch_id] = ['allocated_real' => $product_allocated, 'allocated_update' => $product_allocated, 'warehouseName' => isset($warehouse_name_list[$warehouse_id]) ? $warehouse_name_list[$warehouse_id] : 'N/A', 'supplierName' => isset($supplier_name_list[$supplier_id]) ? $supplier_name_list[$supplier_id] : 'N/A', 'locationName' => $location_name != '' ? $location_name : 'N/A'];
                unset($location_name);
                if (!isset($warehouse_product_array[$warehouse_id][$supplier_id][$location_id][$layers_id][$batch_id])) {
                    $warehouse_product_array[$warehouse_id][$supplier_id][$location_id][$layers_id][$batch_id] = ['quantity' => 0, 'allocated_real' => 0, 'allocated_update' => 0];
                }
                $warehouse_product_array[$warehouse_id][$supplier_id][$location_id][$layers_id][$batch_id]['allocated_real'] += $product_allocated;
                $warehouse_product_array[$warehouse_id][$supplier_id][$location_id][$layers_id][$batch_id]['allocated_update'] += $product_allocated;
                unset($product_allocated);
                unset($warehouse_id);
                unset($supplier_id);
                unset($location_id);
                unset($layers_id);
                unset($batch_id);
            }
            unset($order_product_allocate_record);
            unset($order_product_record);
            unset($order_record);
        }
        unset($order_product_allocated_array);
        unset($order_product_id_array);
        unset($location_block_list);
        unset($supplier_name_list);
        unset($order_product_id);
        if (Yii::$app->request->is_post) {
            $allocated_update_array = Yii::$app->request->post('allocated_update', []);
            $allocated_update_array = is_array($allocated_update_array) ? $allocated_update_array : [];
            foreach ($allocated_update_array as $order_product_id => $warehouse_array) {
                if (isset($order_product_array[$order_product_id])) {
                    $order_product_array[$order_product_id]['allocated_update'] = 0;
                } else {
                    continue;
                }
                foreach ($warehouse_array as $warehouse_id => $supplier_array) {
                    foreach ($supplier_array as $supplier_id => $location_array) {
                        foreach ($location_array as $location_id => $layers_array) {
                            foreach ($layers_array as $layers_id => $batch_array) {
                                foreach ($batch_array as $batch_id => $allocated_update) {
                                    if (isset($order_product_array[$order_product_id]['allocatedArray'][$warehouse_id][$supplier_id][$location_id][$layers_id][$batch_id])) {
                                        $order_product_array[$order_product_id]['allocated_update'] += (int) $allocated_update;
                                        $order_product_array[$order_product_id]['allocatedArray'][$warehouse_id][$supplier_id][$location_id][$layers_id][$batch_id]['allocated_update'] = (int) $allocated_update;
                                    }
                                }
                            }
                        }
                        unset($allocated_update);
                        unset($location_id);
                    }
                    unset($location_array);
                    unset($supplier_id);
                }
                unset($supplier_array);
                unset($warehouse_id);
            }
            unset($allocated_update_array);
            unset($warehouse_array);
            unset($order_product_id);
            foreach ($warehouse_product_array as $warehouse_id => $supplier_array) {
                foreach ($supplier_array as $supplier_id => $location_array) {
                    foreach ($location_array as $location_id => $layers_array) {
                        foreach ($layers_array as $layers_id => $batch_array) {
                            foreach ($batch_array as $batch_id => $warehouse_product_record) {
                                $warehouse_product_array[$warehouse_id][$supplier_id][$location_id][$layers_id][$batch_id]['allocated_update'] = 0;
                                foreach ($order_product_array as $order_product_id => $order_product_data) {
                                    if (isset($order_product_data['allocatedArray'][$warehouse_id][$supplier_id][$location_id][$layers_id][$batch_id])) {
                                        $warehouse_product_array[$warehouse_id][$supplier_id][$location_id][$layers_id][$batch_id]['allocated_update'] += $order_product_data['allocatedArray'][$warehouse_id][$supplier_id][$location_id][$layers_id][$batch_id]['allocated_update'];
                                    }
                                }
                                unset($order_product_data);
                                unset($order_product_id);
                            }
                        }
                    }
                    unset($warehouse_product_record);
                    unset($location_id);
                }
                unset($location_array);
                unset($supplier_id);
            }
            unset($supplier_array);
            unset($warehouse_id);
            $return = ['status' => 'ok', 'message' => ''];
            foreach ($order_product_array as $order_product_id => $order_product_data) {
                if ($order_product_data['quantity'] < $order_product_data['allocated_update']) {
                    $return = ['status' => 'error', 'message' => TEXT_OPR_ERROR_INVALID];
                    break;
                }
            }
            unset($order_product_data);
            unset($order_product_id);
            if ($return['status'] == 'ok') {
                foreach ($warehouse_product_array as $warehouse_id => $supplier_array) {
                    foreach ($supplier_array as $supplier_id => $location_array) {
                        foreach ($location_array as $location_id => $layers_array) {
                            foreach ($layers_array as $layers_id => $batch_array) {
                                foreach ($batch_array as $batch_id => $warehouse_product_record) {
                                    if ($warehouse_product_record['allocated_update'] > 0 and $warehouse_product_record['quantity'] < $warehouse_product_record['allocated_update']) {
                                        foreach ($order_product_array as $order_product_id => $order_product_data) {
                                            if (isset($order_product_data['allocatedArray'][$warehouse_id][$supplier_id][$location_id][$layers_id][$batch_id])) {
                                                if ($order_product_data['allocatedArray'][$warehouse_id][$supplier_id][$location_id][$layers_id][$batch_id]['allocated_update'] > $order_product_data['allocatedArray'][$warehouse_id][$supplier_id][$location_id][$layers_id][$batch_id]['allocated_real']) {
                                                    $return = ['status' => 'error', 'message' => TEXT_OPR_ERROR_INVALID];
                                                    break 6;
                                                }
                                            }
                                        }
                                        unset($order_product_data);
                                        unset($order_product_id);
                                    }
                                }
                            }
                        }
                        unset($warehouse_product_record);
                        unset($location_id);
                    }
                    unset($location_array);
                    unset($supplier_id);
                }
                unset($supplier_array);
                unset($warehouse_id);
            }
            unset($warehouse_product_array);
            if ($return['status'] == 'ok') {
                foreach ($order_product_array as $order_product_id => $order_product_data) {
                    foreach ($order_product_data['allocatedArray'] as $warehouse_id => $supplier_array) {
                        foreach ($supplier_array as $supplier_id => $location_array) {
                            foreach ($location_array as $location_id => $layers_array) {
                                foreach ($layers_array as $layers_id => $batch_array) {
                                    foreach ($batch_array as $batch_id => $allocated_update) {
                                        $order_product_allocate_record = \common\models\Orders_Products_Allocate::find()->where(['orders_products_id' => $order_product_id])->and_where(['warehouse_id' => $warehouse_id])->and_where(['suppliers_id' => $supplier_id])->and_where(['location_id' => $location_id])->and_where(['layers_id' => $layers_id])->and_where(['batch_id' => $batch_id])->one();
                                        if ($allocated_update['allocated_update'] <= 0) {
                                            if ($order_product_allocate_record instanceof \common\models\Orders_Products_Allocate) {
                                                try {
                                                    if ($order_product_allocate_record->allocate_dispatched > 0) {
                                                        $order_product_allocate_record->allocate_received = $order_product_allocate_record->allocate_dispatched;
                                                        $order_product_allocate_record->save();
                                                    } else {
                                                        $order_product_allocate_record->delete();
                                                    }
                                                } catch (\Exception $exc) {
                                                }
                                            }
                                        } else {
                                            if (!$order_product_allocate_record instanceof \common\models\Orders_Products_Allocate) {
                                                $order_product_allocate_record = new \common\models\Orders_Products_Allocate();
                                                $order_product_allocate_record->orders_products_id = $order_product_id;
                                                $order_product_allocate_record->warehouse_id = $warehouse_id;
                                                $order_product_allocate_record->suppliers_id = $supplier_id;
                                                $order_product_allocate_record->location_id = $location_id;
                                                $order_product_allocate_record->layers_id = $layers_id;
                                                $order_product_allocate_record->batch_id = $batch_id;
                                                $order_product_allocate_record->platform_id = $order_product_data['platformId'];
                                                $order_product_allocate_record->orders_id = $order_product_data['orderId'];
                                                $order_product_allocate_record->prid = (int) $u_product_id;
                                                $order_product_allocate_record->products_id = $u_product_id;
                                                $order_product_allocate_record->suppliers_price = \common\models\Suppliers_Products::get_suppliers_price($u_product_id, $supplier_id);
                                                $order_product_allocate_record->is_temporary = \common\helpers\Order::is_allocate_temporary($order_product_data['orderId']);
                                                $order_product_allocate_record->datetime = date('Y-m-d H:i:s');
                                            }
                                            $order_product_allocate_record->allocate_received = $order_product_allocate_record->allocate_dispatched + $allocated_update['allocated_update'];
                                            try {
                                                $order_product_allocate_record->save();
                                            } catch (\Exception $exc) {
                                            }
                                        }
                                        unset($order_product_allocate_record);
                                    }
                                }
                            }
                            unset($allocated_update);
                            unset($location_id);
                        }
                        unset($location_array);
                        unset($supplier_id);
                    }
                    unset($order_product_record);
                    unset($supplier_array);
                    unset($warehouse_id);
                    \common\helpers\Order_Product::evaluate($order_product_id);
                    \common\helpers\Order::evaluate($order_product_data['orderId']);
                }
                unset($order_product_data);
                unset($order_product_id);
                \common\helpers\Product::is_valid_allocated($u_product_id);
                $product_record = \common\helpers\Product::get_record($u_product_id, true);
                if ($product_record instanceof \common\models\Products) {
                    $return['allocated_temporary'] = \common\helpers\Product::get_allocated_temporary($u_product_id, true);
                    $return['deficit'] = \common\helpers\Product::get_virtual_item_quantity($u_product_id, \common\helpers\Product::get_stock_deficit($u_product_id));
                    $return['available'] = \common\helpers\Product::get_virtual_item_quantity($u_product_id, $product_record->products_quantity);
                    $return['allocated'] = \common\helpers\Product::get_virtual_item_quantity($u_product_id, $product_record->allocated_stock_quantity - $return['allocated_temporary']);
                    $return['allocated_temporary'] = \common\helpers\Product::get_virtual_item_quantity($u_product_id, $return['allocated_temporary']);
                }
                unset($product_record);
            }
            unset($order_product_array);
            return json_encode($return);
        }
        //numeric index :( replaced with 0,1,2....      \yii\helpers\ArrayHelper::multisort($orderProductArray, ['datePurchased', 'orderId']);
        uasort($order_product_array, function ($a, $b) {
            return strnatcmp($a['datePurchased'] . $a['orderId'], $b['datePurchased'] . $b['orderId']);
        });
        return $this->render_ajax('order-reallocate', ['orderProductArray' => $order_product_array, 'warehouseProductArray' => $warehouse_product_array, 'warehouseNameList' => $warehouse_name_list, 'prid' => $u_product_id, 'isParent' => count(\common\helpers\Product::get_child_array($u_product_id)) > 0]);
    }
    public function action_temporary_stock()
    {
        $this->layout = false;
        if (Yii::$app->request->post('action', '') == 'delete') {
            $temporary_stock_id = (int) Yii::$app->request->post('temporary_stock_id', 0);
            $op_temporary_record = \common\models\Orders_Products_Temporary_Stock::find_one(['temporary_stock_id' => $temporary_stock_id]);
            $temporary_stock_id = 0;
            if ($op_temporary_record instanceof \common\models\Orders_Products_Temporary_Stock) {
                try {
                    $op_temporary_record->delete();
                    $temporary_stock_id = (int) $op_temporary_record->temporary_stock_id;
                } catch (\Exception $exc) {
                }
            }
            echo json_encode(['id' => $temporary_stock_id]);
            die;
        }
        \common\helpers\Translation::init('admin/categories');
        $u_product_id = \common\helpers\Inventory::normalize_id_excl_virtual(Yii::$app->request->get('prid'));
        $warehouse_name_list = [];
        foreach (\common\models\Warehouses::find()->as_array(true)->all() as $warehouse_record) {
            $warehouse_name_list[$warehouse_record['warehouse_id']] = $warehouse_record['warehouse_name'];
        }
        unset($warehouse_record);
        $temporary_array = [];
        foreach (\common\helpers\Product::get_allocated_temporary_array($u_product_id) as $op_temporary_record) {
            if ((int) $op_temporary_record['customers_id'] > 0) {
                $customer_record = \common\helpers\Customer::get_customer_data($op_temporary_record['customers_id']);
                if (is_array($customer_record) and isset($customer_record['customers_lastname'])) {
                    $op_temporary_record['customer_name'] = '<a href="' . tep_href_link('customers/customeredit', 'customers_id=' . $customer_record['customers_id']) . '" target="_blank">' . trim(trim($customer_record['customers_firstname']) . ' ' . trim($customer_record['customers_lastname'])) . '</a>';
                }
                unset($customer_record);
            }
            $op_temporary_record['warehouse_name'] = isset($warehouse_name_list[$op_temporary_record['warehouse_id']]) ? $warehouse_name_list[$op_temporary_record['warehouse_id']] : '';
            $temporary_array[] = $op_temporary_record;
        }
        unset($op_temporary_record);
        unset($warehouse_name_list);
        return $this->render('temporary-stock', ['temporaryArray' => $temporary_array]);
    }
    public function action_orders_products_stock()
    {
        $this->layout = false;
        \common\helpers\Translation::init('admin/categories');
        $u_product_id = \common\helpers\Inventory::normalize_id_excl_virtual(Yii::$app->request->get('prid'));
        $warehouse_name_list = [];
        foreach (\common\models\Warehouses::find()->as_array(true)->all() as $warehouse_record) {
            $warehouse_name_list[$warehouse_record['warehouse_id']] = $warehouse_record['warehouse_name'];
        }
        unset($warehouse_record);
        $supplier_name_list = [];
        foreach (\common\models\Suppliers::find()->as_array(true)->all() as $supplier_record) {
            $supplier_name_list[$supplier_record['suppliers_id']] = $supplier_record['suppliers_name'];
        }
        unset($supplier_record);
        $location_block_list = [];
        foreach (\common\models\Location_Blocks::find()->as_array(true)->all() as $location_block_record) {
            $location_block_list[$location_block_record['block_id']] = $location_block_record['block_name'];
        }
        unset($location_block_record);
        $allocation_array = [];
        foreach (\common\helpers\Product::get_allocated_array($u_product_id, true, true) as $opa_record) {
            if ((int) $opa_record['is_temporary'] > 0) {
                continue;
            }
            $opa_record['order_link'] = '<a target="_blank" href="' . tep_href_link('orders/process-order', 'orders_id=' . $opa_record['orders_id']) . '">' . $opa_record['orders_id'] . '</a>';
            $order_record = \common\models\Orders::find()->where(['orders_id' => $opa_record['orders_id']])->as_array(true)->one();
            if (is_array($order_record) and isset($order_record['customers_lastname'])) {
                $opa_record['customer_name'] = '<a target="_blank" href="' . tep_href_link('customers/customeredit', 'customers_id=' . $order_record['customers_id']) . '">' . trim(trim($order_record['customers_firstname']) . ' ' . trim($order_record['customers_lastname'])) . '</a>';
            }
            unset($order_record);
            $opa_record['warehouse_name'] = $warehouse_name_list[$opa_record['warehouse_id']] ?? '';
            $opa_record['supplier_name'] = $supplier_name_list[$opa_record['suppliers_id']] ?? '';
            $opa_record['location_name'] = trim(\common\helpers\Warehouses::get_location_path($opa_record['location_id'], $opa_record['warehouse_id'], $location_block_list));
            $allocation_array[] = $opa_record;
        }
        unset($location_block_list);
        unset($warehouse_name_list);
        unset($supplier_name_list);
        unset($opa_record);
        return $this->render('orders-products-stock', ['allocationArray' => $allocation_array]);
    }
    public function action_orders_products_deficit()
    {
        $this->layout = false;
        \common\helpers\Translation::init('admin/categories');
        $u_product_id = \common\helpers\Inventory::normalize_id_excl_virtual(Yii::$app->request->get('prid'));
        $deficit_array = [];
        foreach (\common\models\Orders_Products::find()->select(['*', '(products_quantity - (qty_cnld + qty_rcvd)) AS deficit'])->where(['uprid' => $u_product_id])->and_where(['>', '(products_quantity - (qty_cnld + qty_rcvd))', 0])->and_where(['NOT IN', 'orders_products_status', [\common\helpers\Order_Product::OPS_QUOTED]])->as_array(true)->all() as $op_record) {
            $op_record['order_link'] = '<a target="_blank" href="' . tep_href_link('orders/process-order', 'orders_id=' . $op_record['orders_id']) . '">' . $op_record['orders_id'] . '</a>';
            $order_record = \common\models\Orders::find()->where(['orders_id' => $op_record['orders_id']])->as_array(true)->one();
            if (is_array($order_record) and isset($order_record['customers_lastname'])) {
                $op_record['datetime'] = $order_record['date_purchased'];
                $op_record['customer_name'] = '<a target="_blank" href="' . tep_href_link('customers/customeredit', 'customers_id=' . $order_record['customers_id']) . '">' . trim(trim($order_record['customers_firstname']) . ' ' . trim($order_record['customers_lastname'])) . '</a>';
            }
            unset($order_record);
            $deficit_array[] = $op_record;
        }
        unset($op_record);
        return $this->render('orders-products-deficit', ['deficitArray' => $deficit_array]);
    }
    public function action_orders_products_temporary_stock()
    {
        $this->layout = false;
        if (Yii::$app->request->post('action', '') == 'delete') {
            try {
                $allocation_id = trim(Yii::$app->request->post('allocation_id', ''));
                $search_array = explode('_', $allocation_id);
                $op_allocate_record = \common\models\Orders_Products_Allocate::find()->where(['is_temporary' => 1])->and_where(['allocate_dispatched' => 0])->and_where(['orders_products_id' => $search_array[0] ?? -1])->and_where(['warehouse_id' => $search_array[1] ?? -1])->and_where(['suppliers_id' => $search_array[2] ?? -1])->and_where(['location_id' => $search_array[3] ?? -1])->as_array(false)->one();
                unset($search_array);
                if ($op_allocate_record instanceof \common\models\Orders_Products_Allocate) {
                    $orders_products_id = $op_allocate_record->orders_products_id;
                    $op_allocate_record->delete();
                    \common\helpers\Order_Product::evaluate($orders_products_id);
                    unset($orders_products_id);
                    echo json_encode(['id' => $allocation_id]);
                }
                unset($op_allocate_record);
            } catch (\Exception $exc) {
                \Yii::warning($exc->get_message() . ' ' . $exc->get_trace_as_string(), 'Error.Backend.Controller.Categories.actionOrdersProductsTemporaryStock.delete');
            }
            die;
        }
        \common\helpers\Translation::init('admin/categories');
        $u_product_id = \common\helpers\Inventory::normalize_id_excl_virtual(Yii::$app->request->get('prid'));
        $warehouse_name_list = [];
        foreach (\common\models\Warehouses::find()->as_array(true)->all() as $warehouse_record) {
            $warehouse_name_list[$warehouse_record['warehouse_id']] = $warehouse_record['warehouse_name'];
        }
        unset($warehouse_record);
        $supplier_name_list = [];
        foreach (\common\models\Suppliers::find()->as_array(true)->all() as $supplier_record) {
            $supplier_name_list[$supplier_record['suppliers_id']] = $supplier_record['suppliers_name'];
        }
        unset($supplier_record);
        $location_block_list = [];
        foreach (\common\models\Location_Blocks::find()->as_array(true)->all() as $location_block_record) {
            $location_block_list[$location_block_record['block_id']] = $location_block_record['block_name'];
        }
        unset($location_block_record);
        $order_status_expired_duration_hours = (int) \common\helpers\Configuration::get_configuration_key_value('ORDER_STATUS_TEMPORARY_ALLOCATION_EXPIRED_DURATION');
        if ($order_status_expired_duration_hours < 1) {
            $order_status_expired_duration_hours = 1;
        }
        $temporary_array = [];
        foreach (\common\helpers\Product::get_allocated_temporary_array($u_product_id, true, true) as $op_temporary_record) {
            $op_temporary_record['allocation_id'] = "{$op_temporary_record['orders_products_id']}_{$op_temporary_record['warehouse_id']}_{$op_temporary_record['suppliers_id']}_{$op_temporary_record['location_id']}";
            $op_temporary_record['orders_link'] = '<a href="' . tep_href_link('orders/process-order', 'orders_id=' . $op_temporary_record['orders_id']) . '" target="_blank">' . $op_temporary_record['orders_id'] . '</a>';
            $order_record = \common\models\Orders::find()->where(['orders_id' => $op_temporary_record['orders_id']])->as_array()->one();
            if (is_array($order_record) && isset($order_record['customers_lastname'])) {
                $op_temporary_record['customer_name'] = '<a href="' . tep_href_link('customers/customeredit', 'customers_id=' . $order_record['customers_id']) . '" target="_blank">' . trim($order_record['customers_firstname']) . ' ' . trim($order_record['customers_lastname']) . '</a>';
            }
            unset($order_record);
            $op_temporary_record['warehouse_name'] = $warehouse_name_list[$op_temporary_record['warehouse_id']] ?? '';
            $op_temporary_record['supplier_name'] = $supplier_name_list[$op_temporary_record['suppliers_id']] ?? '';
            $op_temporary_record['location_name'] = trim(\common\helpers\Warehouses::get_location_path($op_temporary_record['location_id'], $op_temporary_record['warehouse_id'], $location_block_list));
            $time_expire = strtotime($op_temporary_record['datetime']) + $order_status_expired_duration_hours * 60 * 60;
            $time_expire = $time_expire - time() < 0 ? ' style="color: red;"' : '';
            $op_temporary_record['allocate_time'] = '<span' . $time_expire . '>' . \common\helpers\Date::time_humanize($op_temporary_record['datetime']) . '</span>';
            unset($time_expire);
            $temporary_array[] = $op_temporary_record;
        }
        unset($order_status_expired_duration_hours);
        unset($op_temporary_record);
        unset($location_block_list);
        unset($warehouse_name_list);
        unset($supplier_name_list);
        return $this->render('orders-products-temporary-stock', ['temporaryArray' => $temporary_array]);
    }
    public function action_product_in_bundle_status()
    {
        $this->layout = false;
        $return = ['status' => 'error'];
        $product_record = \common\helpers\Product::get_record((int) Yii::$app->request->post('pID'));
        if ($product_record instanceof \common\models\Products) {
            $product_record->products_status_bundle = (int) Yii::$app->request->post('status');
            try {
                $product_record->save();
                $return = ['status' => 'ok'];
            } catch (\Exception $exc) {
            }
        }
        unset($product_record);
        echo json_encode($return);
        die;
    }
    public function action_product_label()
    {
        $model = trim(Yii::$app->request->get('model', ''));
        if (strlen($model) == 0) {
            $model = '-';
        }
        $count = Yii::$app->request->get('count', 1);
        $label_data = \common\helpers\Product_Label::label($model, max(1, (int) $count));
        $this->layout = false;
        Yii::$app->response->send_content_as_file($label_data, preg_replace('/[^\da-z-_]+/i', '_', $model) . '.pdf', ['mimeType' => 'application/pdf', 'inline' => true]);
    }
    public function action_check_supplier_delete()
    {
        $this->layout = false;
        $return = ['status' => 'error'];
        $p_id = \common\helpers\Inventory::normalize_id_excl_virtual(Yii::$app->request->post('pId'));
        $product_record = \common\helpers\Product::get_record($p_id);
        if ($product_record instanceof \common\models\Products) {
            $return = ['status' => 'ok'];
            $s_id = (int) Yii::$app->request->post('sId');
            foreach (\common\models\Warehouses_Products::find()->and_where(['products_id' => $p_id])->and_where(['prid' => (int) $p_id])->and_where(['suppliers_id' => $s_id])->as_array(true)->all() as $wp_record) {
                $return = ['status' => 'error', 'message' => MESSAGE_ERROR_SUPPLIER_DELETE_STOCK];
                break;
            }
        }
        echo json_encode($return);
        die;
    }
    public function action_file_filter_form()
    {
        $editor_id = \Yii::$app->request->get('editorId');
        $host = tep_catalog_href_link('', '', 'SSL', \common\classes\platform::default_id());
        // $items = [['link'=>'link', 'text'=> 'text']];
        return $this->render_partial('productedit/notes/document-links.tpl', [
            //'items' => $items,
            'editorId' => $editor_id,
            'host' => $host,
            'suggest' => true,
        ]);
    }
    public function action_file_filter()
    {
        $language_id = \Yii::$app->settings->get('languages_id');
        $editor_id = \Yii::$app->settings->get('editorId');
        if (!$language_id) {
            $language_id = (int) \common\classes\language::default_id();
        }
        $keywords = \Yii::$app->request->get('keywords', '');
        \common\helpers\Translation::init('admin/categories');
        \common\helpers\Translation::init('admin/design');
        $fs_path = DIR_FS_CATALOG . 'documents/';
        $link = DIR_WS_CATALOG;
        if (mb_strpos($link, '/') === 0) {
            $link = substr($link, 1);
        }
        $ws_path = $link . 'documents/';
        $documents = $this->products_documents_service->find_by_file_name($keywords, $language_id, true, 10, true);
        $result_documents = [];
        $document_names = [];
        if ($documents) {
            foreach ($documents as $id => $document) {
                $documents[$id]['exist'] = true;
                $documents[$id]['name'] = $document['title']['title'] ?: $document['filename'];
                $documents[$id]['download'] = $document['filename'];
                if (!$document['is_link']) {
                    $documents[$id]['download'] = $ws_path . $document['filename'];
                    $documents[$id]['exist'] = false;
                    if (is_file($fs_path . $document['filename'])) {
                        $documents[$id]['exist'] = true;
                    }
                }
                if (in_array($documents[$id]['name'], $document_names, true)) {
                    continue;
                }
                $document_names[] = $documents[$id]['name'];
                $result_documents[] = $documents[$id];
            }
        }
        $disk_documents = array_map('basename', File_Helper::find_files($fs_path, ['recursive' => false, 'only' => ['pattern' => "*{$keywords}*"]]));
        if ($disk_documents) {
            $disk_documents = array_slice($disk_documents, 0, 10);
            foreach ($disk_documents as $document) {
                if (in_array($document, $document_names, true)) {
                    continue;
                }
                $document_names[] = $document;
                $result_documents[] = ['exist' => true, 'name' => $document, 'filename' => $document, 'download' => $ws_path . $document];
            }
        }
        return $this->render_partial('productedit/notes/document-search.tpl', ['documents' => $result_documents, 'editorId' => $editor_id]);
    }
    public function action_load_tree()
    {
        \common\helpers\Translation::init('admin/platforms');
        $this->layout = false;
        $post = Yii::$app->request->post();
        $catalog = new \backend\components\Products_Catalog();
        $catalog->settings['add_sku'] = false;
        return $catalog->make($post);
    }
    public function action_seacrh_product()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $seacrh = Yii::$app->request->get('search', null);
        if (!empty($seacrh)) {
            $catalog = new \backend\components\Products_Catalog();
            //$catalog->post['suggest'] = 1;
            if (!($catalog->post['suggest'] ?? null)) {
                $catalog->post['suggest'] = Yii::$app->request->get('suggest');
            }
            return $catalog->search($seacrh);
        }
    }
    public function action_demo_cleanup()
    {
        set_time_limit(0);
        $sdn = \common\helpers\Acl::check_extension_allowed('SeoRedirectsNamed', 'allowed');
        $demo_products = \common\models\Products::find()->select(['products_id'])->where(['is_demo' => 1])->as_array()->all();
        foreach ($demo_products as $product) {
            $key = $product['products_id'];
            \common\helpers\Product::remove_product($key);
            if ($sdn) {
                $sdn::delete_product_links($key);
            }
        }
        return $this->redirect(Yii::$app->url_manager->create_url(['categories/']));
    }
    /**
     * works only with customers groups.
     */
    public function action_product_price_edit()
    {
        if (!\common\helpers\Extensions::is_customer_groups_allowed()) {
            return;
        }
        \common\helpers\Translation::init('admin/categories');
        \common\helpers\Translation::init('admin/categories/productedit');
        $currencies = Yii::$container->get('currencies');
        $this->layout = false;
        $currencies_id = \Yii::$app->request->post('currencies_id', \Yii::$app->request->get('currencies_id', 0));
        $products_id = \Yii::$app->request->post('products_id', \Yii::$app->request->get('products_id', 0));
        $group_id = \Yii::$app->request->post('group_id', 0);
        $only_price = \Yii::$app->request->post('only_price', 0);
        $no_price = true;
        if ($group_id > 0) {
            $no_price = false;
        }
        ////currencies tabs and params
        $this->view->price_tabs = $this->view->price_tabparams = [];
        $this->view->currencies_tabs = [];
        /*
               if ($this->view->useMarketPrices) {
                 foreach ($currencies->currencies as $value) {
                   $value['def_data'] = ['currencies_id' => $value['id']];
                   $value['title'] = $value['symbol_left'] . ' ' . $value['code'] . ' ' . $value['symbol_right'];
                   $this->view->currenciesTabs[] = $value;
                 }
                 $this->view->price_tabs[] = $this->view->currenciesTabs;
                 $this->view->price_tabparams[] =  [
                     'cssClass' => 'tabs-currencies',
                     'tabs_type' => 'hTab',
                     //'maxWidth' => '520px',
                     //'include' => 'test/test.tpl',
                 ];
               }
        */
        //// groups tabs and params
        $this->view->groups = [];
        /** @var \common\extensions\UserGroups\UserGroups $ext */
        if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroups', 'allowed')) {
            $ext::get_groups();
        }
        $this->view->groups_m = $this->view->groups;
        $tabdata = $groups = $tmp = [];
        foreach ($this->view->groups_m as $value) {
            $value['id'] = $value['groups_id'];
            $value['title'] = $value['groups_name'];
            $value['def_data'] = ['groups_id' => $value['id']];
            unset($value['groups_name']);
            unset($value['groups_id']);
            $tmp[] = $value;
            if ($group_id == $value['id']) {
                $tabdata = $value;
            }
            if ($value['per_product_price'] == 0) {
                $groups[$value['id']] = $value['title'];
            }
        }
        //$this->view->price_tabs[] = $tmp;
        $this->view->price_tabs = $tabdata;
        unset($tmp);
        $this->view->price_tabparams[] = [
            'cssClass' => 'tabs-groups',
            // add to tabs and tab-pane
            //'callback' => 'productPriceBlock', // smarty function which will be called before children tabs , data passed as params params
            'callback_bottom' => '',
            'tabs_type' => 'lTab',
        ];
        $this->view->use_market_prices = USE_MARKET_PRICES == 'True';
        $groups = [0 => TEXT_CHOOSE_GROUP] + $groups;
        $this->view->tax_classes = ['0' => TEXT_NONE];
        $tax_class_query = tep_db_query('select tax_class_id, tax_class_title from ' . TABLE_TAX_CLASS . ' order by tax_class_title');
        while ($tax_class = tep_db_fetch_array($tax_class_query)) {
            $this->view->tax_classes[$tax_class['tax_class_id']] = $tax_class['tax_class_title'];
        }
        $p = \common\models\Products::find()->and_where(['products_id' => (int) $products_id]);
        if (tep_session_is_registered('login_vendor')) {
            global $login_id;
            $p->and_where(['vendor_id' => $login_id]);
        }
        $p_info = $p->one();
        $this->product_edit_tab_access->set_product($p_info);
        if ($only_price) {
            if ($p_info->products_id_price && $p_info->products_id != $p_info->products_id_price) {
                $price_view_obj = new View_Price_Data(\common\models\Products::find_one($p_info->products_id_price));
            } else {
                $price_view_obj = new View_Price_Data($p_info);
            }
            $price_view_obj->populate_view($this->view);
            if ($this->view->use_market_prices) {
                $data = $this->view->price_tabs_data[$currencies_id][$group_id];
            } else {
                $data = $this->view->price_tabs_data[$group_id] ?? null;
            }
            $data['tabdata'] = $tabdata;
            $data['groups_id'] = $group_id;
            unset($this->view->price_tabs);
            unset($this->view->price_tabs_data);
            $this->view->price_tabs_data = $data;
            $ret = $this->render('productedit/price', ['currencies' => $currencies, 'pInfo' => $p_info, 'TabAccess' => $this->product_edit_tab_access, 'idSuffix' => '_' . ($this->view->use_market_prices ? $currencies_id . '_' : '') . $group_id, 'fieldSuffix' => ($this->view->use_market_prices ? '[' . $currencies_id . ']' : '') . '[' . $group_id . ']', 'default_currency' => $currencies->currencies[DEFAULT_CURRENCY], 'hideSuppliersPart' => 1, 'popup' => 1]);
        } else {
            $ret = $this->render('productedit/edit-price-popup', ['currencies' => $currencies, 'currencies_id' => $currencies_id, 'products_id' => $products_id, 'pInfo' => $p_info, 'groups' => $groups]);
        }
        return $ret;
    }
    public function action_group_price_submit()
    {
        if (!\common\helpers\Extensions::is_customer_groups_allowed()) {
            return;
        }
        $res = ['result' => 0, 'message' => 'error'];
        \common\helpers\Translation::init('admin/categories');
        $currencies = Yii::$container->get('currencies');
        $tab_access = $this->product_edit_tab_access;
        $this->layout = false;
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        //$currencies_id = \Yii::$app->request->post('currencies_id', \Yii::$app->request->get('currencies_id', 0));
        $old_products_id = $products_id = \Yii::$app->request->post('products_id', 0);
        $group_id = \Yii::$app->request->post('group_id', 0);
        $_def_curr_id = $currencies->currencies[DEFAULT_CURRENCY]['id'];
        if (USE_MARKET_PRICES == 'True') {
            foreach ($currencies->currencies as $key => $value) {
                $currencies_ids[$currencies->currencies[$key]['id']] = $currencies->currencies[$key]['id'];
            }
        } else {
            $currencies_ids[$_def_curr_id] = '0';
            /// here is the post and db currencies_id are different.
        }
        $product_model = \common\models\Products::find_one((int) $products_id);
        //        $_products_id_price = intval(Yii::$app->request->post('products_id_price',-1));
        //       if ( $_products_id_price>=0 ) { $productModel->products_id_price = $_products_id_price; }
        $tab_access->set_product($product_model);
        $groups_price = $groups = [$group_id => 'dummy'];
        try {
            //Gift wrap
            if ($tab_access->tab_data_save('TEXT_MAIN_DETAILS')) {
                if ($old_products_id > 0) {
                    if ($groups_price) {
                        \common\models\Gift_Wrap_Products::delete_all(['products_id' => (int) $old_products_id, 'groups_id' => array_keys($groups_price)]);
                    } else {
                        tep_db_query('delete from ' . TABLE_GIFT_WRAP_PRODUCTS . " where products_id = '" . (int) $old_products_id . "'");
                    }
                }
                $gift_wrap = Yii::$app->request->post('gift_wrap', 0);
                if (is_array($gift_wrap) || $gift_wrap > 0) {
                    if (is_array($gift_wrap) && (USE_MARKET_PRICES == 'True' || \common\helpers\Extensions::is_customer_groups_allowed())) {
                        foreach ($currencies_ids as $post_currencies_id => $currencies_id) {
                            foreach ($groups_price ? $groups_price : $groups as $groups_id => $non) {
                                $sql_data_array = ['products_id' => (int) $products_id, 'groups_id' => (int) $groups_id, 'currencies_id' => (int) $currencies_id];
                                $field = ['db' => 'gift_wrap_price', 'dbdef' => 0, 'post' => 'gift_wrap_price', 'flag' => 'gift_wrap'];
                                if (self::get_from_post_arrays(['post' => 'gift_wrap'], (int) $post_currencies_id, (int) $groups_id) == 1) {
                                    $sql_data_array[$field['db']] = self::get_from_post_arrays($field, (int) $post_currencies_id, (int) $groups_id);
                                    tep_db_perform(TABLE_GIFT_WRAP_PRODUCTS, $sql_data_array);
                                }
                            }
                        }
                    } else {
                        $sql_data_array = ['products_id' => (int) $products_id, 'groups_id' => 0, 'currencies_id' => 0];
                        $field = ['db' => 'gift_wrap_price', 'dbdef' => 0, 'post' => 'gift_wrap_price', 'flag' => 'gift_wrap'];
                        if (self::get_from_post_arrays(['post' => 'gift_wrap'], 0) == 1) {
                            $sql_data_array[$field['db']] = self::get_from_post_arrays($field, 0);
                            tep_db_perform(TABLE_GIFT_WRAP_PRODUCTS, $sql_data_array);
                        }
                    }
                }
            }
            if ($tab_access->tab_data_save('TEXT_PRICE_COST_W')) {
                $product_model->disable_discount = intval(Yii::$app->request->post('disable_discount', 0));
                //2 group prices specials. etc
                if (USE_MARKET_PRICES == 'True' || \common\helpers\Extensions::is_customer_groups_allowed()) {
                    if ($groups_price ?? null) {
                        \common\models\Products_Prices::delete_all(['products_id' => (int) $old_products_id, 'groups_id' => array_keys($groups_price)]);
                    } else {
                        tep_db_query('delete from ' . TABLE_PRODUCTS_PRICES . " where products_id = '" . (int) $products_id . "'");
                    }
                    foreach ($currencies_ids as $post_currencies_id => $currencies_id) {
                        foreach ($groups_price ? $groups_price : $groups as $groups_id => $non) {
                            $sql_data_array = ['products_id' => (int) $products_id, 'groups_id' => (int) $groups_id, 'currencies_id' => (int) $currencies_id];
                            $fields = [['db' => 'products_sets_discount', 'dbdef' => 0, 'post' => 'products_group_sets_discount'], ['db' => 'products_group_price', 'dbdef' => $groups_id == 0 ? 0 : -2, 'post' => 'products_group_price'], ['db' => 'bonus_points_price', 'dbdef' => 0, 'post' => 'bonus_points_price', 'flag' => 'bonus_points_status'], ['db' => 'bonus_points_cost', 'dbdef' => 0, 'post' => 'bonus_points_cost', 'flag' => 'bonus_points_status'], ['db' => 'products_group_price_pack_unit', 'dbdef' => -2, 'post' => 'products_group_price_pack_unit', 'f' => ['self', 'defGroupPrice']], ['db' => 'products_group_price_packaging', 'dbdef' => -2, 'post' => 'products_group_price_packaging', 'f' => ['self', 'defGroupPrice']], ['db' => 'supplier_price_manual', 'dbdef' => 'null', 'post' => 'supplier_auto_price'], ['db' => 'shipping_surcharge_price', 'dbdef' => 0, 'post' => 'shipping_surcharge_price', 'flag' => 'shipping_surcharge'], ['db' => 'products_group_discount_price', 'dbdef' => '', 'postreindex' => 'discount_qty', 'post' => 'discount_price', 'flag' => 'qty_discount_status', 'f' => ['self', 'formatDiscountString']], ['db' => 'products_group_discount_price_pack_unit', 'dbdef' => '', 'postreindex' => 'discount_qty_pack_unit', 'post' => 'discount_price_pack_unit', 'flag' => 'qty_discount_status_pack_unit', 'f' => ['self', 'formatDiscountString']], ['db' => 'products_group_discount_price_packaging', 'dbdef' => '', 'postreindex' => 'discount_qty_packaging', 'post' => 'discount_price_packaging', 'flag' => 'qty_discount_status_packaging', 'f' => ['self', 'formatDiscountString']]];
                            //2do products_price_configurator
                            foreach ($fields as $field) {
                                $sql_data_array[$field['db']] = self::get_from_post_arrays($field, (int) $post_currencies_id, (int) $groups_id);
                            }
                            if ($groups_id == 0) {
                                // posted auto, make manual
                                $sql_data_array['supplier_price_manual'] = $sql_data_array['supplier_price_manual'] == '1' ? 0 : 1;
                                // reset matched with current config
                                if ($sql_data_array['supplier_price_manual'] == 1 && SUPPLIER_UPDATE_PRICE_MODE == 'Manual' || $sql_data_array['supplier_price_manual'] == 0 && SUPPLIER_UPDATE_PRICE_MODE == 'Auto') {
                                    unset($sql_data_array['supplier_price_manual']);
                                }
                            } else {
                                unset($sql_data_array['supplier_price_manual']);
                            }
                            tep_db_perform(TABLE_PRODUCTS_PRICES, $sql_data_array);
                        }
                    }
                }
                /*
                                  if ($ext = \common\helpers\Acl::checkExtensionAllowed('DeliveryOptions', 'allowed')) {
                                  $ext::saveProduct($products_id);
                                  } */
            }
            $res['result'] = 1;
        } catch (\Exception $e) {
            \Yii::warning(' #### ' . print_r($e, true), 'TLDEBUG');
            $res['message'] = $e->get_message();
        }
        return $res;
    }
    public function action_set_suppliers_stock()
    {
        $ret = [];
        ///suppliers_data[362][9][suppliers_quantity]
        $suppliers_data = \Yii::$app->request->post('suppliers_data', []);
        $cnt = 0;
        $qty = 0;
        if (!empty($suppliers_data) && is_array($suppliers_data)) {
            foreach ($suppliers_data as $products_id => $suppliers) {
                if (!empty($suppliers) && is_array($suppliers)) {
                    foreach ($suppliers as $supplier_id => $data) {
                        try {
                            $qty = intval($data['suppliers_quantity']);
                            $products_id = \common\helpers\Inventory::normalize_id_excl_virtual($products_id);
                            $m = Suppliers_Products::find_one(['suppliers_id' => (int) $supplier_id, 'products_id' => (int) $products_id, 'uprid' => $products_id]);
                            if (empty($m)) {
                                $m = new Suppliers_Products(['suppliers_id' => (int) $supplier_id, 'products_id' => (int) $products_id, 'uprid' => $products_id]);
                                $m->load_default_values();
                            }
                            $m->suppliers_quantity = $qty;
                            $m->save(false);
                            if (!isset($ret[$products_id])) {
                                $ret[$products_id] = [];
                            }
                            $ret[$products_id][$supplier_id] = ['value' => $qty];
                        } catch (\Exception $e) {
                            \Yii::warning(' #### ' . print_r($e->get_message() . $e->get_trace_as_string(), true), 'TLDEBUG');
                        }
                        $cnt++;
                    }
                }
                \common\helpers\Product::do_cache($products_id);
            }
        }
        //only 1 qty updated = simple response.
        if ($cnt == 1) {
            $ret = ['value' => $qty];
        }
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $ret;
    }
    public function action_save_supplier_fields()
    {
        $product_id = \Yii::$app->request->post('save_products_id');
        $uprid = \Yii::$app->request->post('uprid', $product_id);
        $supplier_id = \Yii::$app->request->post('save_suppliers_id');
        $supplier_data = \Yii::$app->request->post('suppliers_data');
        if (empty($product_id) || empty($supplier_id) || empty($supplier_data)) {
            return;
        }
        $supplier = \common\models\Suppliers_Products::find_one(['products_id' => $product_id, 'uprid' => $uprid, 'suppliers_id' => $supplier_id]);
        if (empty($supplier)) {
            return;
        }
        foreach (['supplier_discount', 'suppliers_surcharge_amount', 'suppliers_margin_percentage'] as $field) {
            $supplier->{$field} = empty($supplier_data[$product_id][$supplier_id][$field]) ? null : $supplier_data[$product_id][$supplier_id][$field];
        }
        $supplier->save(false);
    }
}