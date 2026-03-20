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
namespace common\helpers;

use common\models\Menu_Items;
use common\models\Menus;
use common\models\Menu_Titles;
use Yii;
class Menu_Helper
{
    public const MENU_CACHE_LIFETIME = 5;
    private static $count_disabled_menu_item = 0;
    public static function get_url_by_link_id($link_id, $link_type)
    {
        switch ($link_type) {
            case 'default':
                if ($link_id == '8888886') {
                    return tep_href_link('/');
                } elseif ($link_id == '8888887') {
                    if (!Yii::$app->user->is_guest) {
                        return tep_href_link('account/logoff', '', 'SSL');
                    } else {
                        return tep_href_link('account/login', '', 'SSL');
                    }
                } elseif ($link_id == '8888888') {
                    if (!Yii::$app->user->is_guest) {
                        return tep_href_link('account/index', '', 'SSL');
                    } else {
                        return tep_href_link('account/create', '', 'SSL');
                    }
                } elseif ($link_id == '8888884') {
                    return tep_href_link('checkout/index', '', 'SSL');
                } elseif ($link_id == '8888883') {
                    return tep_href_link('shopping-cart/index');
                } elseif ($link_id == '8888882') {
                    return tep_href_link('catalog/products-new');
                } elseif ($link_id == '8888881') {
                    return tep_href_link('catalog/featured-products');
                } elseif ($link_id == '8888880') {
                    return tep_href_link('catalog/sales');
                } elseif ($link_id == '8888879') {
                    return tep_href_link('catalog/gift-card');
                } elseif ($link_id == '8888878') {
                    return tep_href_link('catalog/all-products');
                } elseif ($link_id == '8888877') {
                    return tep_href_link('sitemap');
                } elseif ($link_id == '8888876') {
                    return tep_href_link('promotions');
                } elseif ($link_id == '8888875') {
                    return tep_href_link('wedding-registry');
                } elseif ($link_id == '8888874') {
                    return tep_href_link('wedding-registry/manage');
                } elseif ($link_id == '8888873') {
                    return tep_href_link('quick-order');
                }
                break;
            case 'custom':
                $link = tep_db_fetch_array(tep_db_query('select link from ' . TABLE_MENU_ITEMS . " where platform_id = '" . \common\classes\platform::current_id() . "' and link_id = '" . (int) $link_id . "'"));
                if ($link) {
                    return tep_href_link($link['link']);
                }
                break;
        }
        return false;
    }
    public static function get_all_custom_pages($platform_id)
    {
        $cusom_pages_query = tep_db_query('select ts.id, ts.setting_value from ' . TABLE_THEMES_SETTINGS . ' ts left join ' . TABLE_THEMES . ' t on ts.theme_name = t.theme_name inner join ' . TABLE_PLATFORMS_TO_THEMES . " pt on pt.is_default = 1 and pt.theme_id = t.id where pt.platform_id = '" . (int) $platform_id . "' and ts.setting_group = 'added_page' and ts.setting_name='custom' order by ts.setting_value");
        $custom_pages = [];
        if (tep_db_num_rows($cusom_pages_query)) {
            while ($custom = tep_db_fetch_array($cusom_pages_query)) {
                $custom_pages[$custom['id']] = $custom['setting_value'];
            }
        }
        return $custom_pages;
    }
    public static function get_brands_list()
    {
        static $brands;
        if (is_array($brands)) {
            return $brands;
        }
        /*
                $manufacturers_query = tep_db_query("select manufacturers_id, manufacturers_name, manufacturers_image from " . TABLE_MANUFACTURERS ." order by manufacturers_name asc");
        
                $brands = [];
                while ($item = tep_db_fetch_array($manufacturers_query)) {
                    $brands[$item['manufacturers_id']] = $item;
                }*/
        $manufacturers_query = \common\models\Manufacturers::find()->alias('m')->select('m.manufacturers_id, manufacturers_name, manufacturers_image')->order_by('manufacturers_name');
        foreach (\common\helpers\Hooks::get_list('menu-helper/get-brands-list') as $filename) {
            include $filename;
        }
        $brands = $manufacturers_query->index_by('manufacturers_id')->as_array()->all();
        return $brands;
    }
    public static function get_extensions_tree_items()
    {
        $path = \Yii::get_alias('@common') . DIRECTORY_SEPARATOR . 'extensions' . DIRECTORY_SEPARATOR;
        $ex_items = [];
        if ($dir = @dir($path)) {
            while ($file = $dir->read()) {
                if ($ext = \common\helpers\Acl::check_extension_installed($file, 'getAdminMenu')) {
                    $Items = $ext::get_admin_menu();
                    if (is_array($Items)) {
                        foreach ($Items as $item) {
                            $ex_items[] = $item;
                        }
                    }
                    unset($Items);
                }
            }
            $dir->close();
        }
        return $ex_items;
    }
    public static function prepare_admin_tree($data, $ex_items)
    {
        $response = [];
        foreach ($data->item as $item) {
            $row = ['sort_order' => (int) $item->sort_order, 'box_type' => (int) $item->box_type, 'acl_check' => (string) $item->acl_check, 'config_check' => (string) $item->config_check, 'path' => (string) $item->path, 'title' => (string) $item->title, 'filename' => (string) $item->filename];
            if ((int) $item->box_type == 1) {
                if (isset($item->child)) {
                    $row['child'] = self::prepare_admin_tree($item->child, $ex_items);
                }
                foreach ($ex_items as $key => $value) {
                    if ($value['parent'] == $row['title']) {
                        $row['child'][] = $value;
                        unset($ex_items[$key]);
                    }
                }
            }
            $response[] = $row;
        }
        return $response;
    }
    /**
     * Get menu chain from id
     * @param $menuId
     * @return null|array ['BOX_HEADING_CUSTOMERS', 'BOX_CUSTOMERS_CUSTOMERS']
     */
    private static function get_admin_menu_chain($menu_id)
    {
        $res = [];
        do {
            $menu = \common\models\Admin_Boxes::find_one($menu_id);
            if (empty($menu)) {
                \Yii::warning(__FUNCTION__ . " - cannot create menu chain for {$menu_id}");
                return;
            }
            $res[] = $menu->title;
            $menu_id = $menu->parent_id;
        } while ($menu_id != 0);
        return array_reverse($res);
    }
    private static function force_acl_for_menu($menu_id)
    {
        $menu_chain = self::get_admin_menu_chain($menu_id);
        if (empty($menu_chain)) {
            return;
        }
        $parent_id = 0;
        foreach ($menu_chain as $menu_title) {
            $acl = \common\models\Access_Control_List::find_one(['access_control_list_key' => $menu_title, 'parent_id' => $parent_id]);
            if (empty($acl)) {
                $acl = new \common\models\Access_Control_List();
                $acl->parent_id = $parent_id;
                $acl->access_control_list_key = $menu_title;
                $acl->sort_order = 10;
                $acl->save(false);
            }
            $parent_id = $acl->access_control_list_id;
        }
        return $acl;
    }
    /* extracted from below importAdminTree */
    private static function create_admin_menu_item_and_check_acl($item)
    {
        $parent = (int) ($item['parent_id'] ?? 0);
        if (!empty($parent)) {
            $row = \common\models\Admin_Boxes::find_one($parent);
            if ($row->box_type == 0) {
                $row->box_type = 1;
                $row->save(false);
            }
            unset($row);
        }
        $object = new \common\models\Admin_Boxes();
        $object->parent_id = $parent;
        $object->sort_order = (int) ($item['sort_order'] ?? null);
        $object->box_type = (int) ($item['box_type'] ?? null);
        $object->acl_check = (string) ($item['acl_check'] ?? null);
        $object->config_check = (string) ($item['config_check'] ?? null);
        $object->path = (string) ($item['path'] ?? null);
        // maybe null if box_type=1
        $object->title = (string) $item['title'];
        $object->filename = (string) ($item['filename'] ?? null);
        $object->save(false);
        //--- update acl
        $cnt = \common\models\Access_Control_List::find()->where(['access_control_list_key' => $object->title])->count();
        if ($cnt != 1) {
            $acl = self::force_acl_for_menu($object->box_id);
        } else {
            $acl = \common\models\Access_Control_List::find_one(['access_control_list_key' => $object->title]);
        }
        $acl->sort_order = $object->sort_order;
        $acl->save();
        return $object;
    }
    public static function import_admin_tree($data, $parent = 0)
    {
        foreach ($data as $item) {
            $item['parent_id'] = $parent;
            $object = self::create_admin_menu_item_and_check_acl($item);
            //--- update acl
            if (isset($item['child']) && is_array($item['child']) && (int) ($item['box_type'] ?? 0) == 1) {
                self::import_admin_tree($item['child'], $object->box_id);
            }
        }
    }
    public static function get_admin_menu_item_by_title($array_or_title)
    {
        if (is_array($array_or_title)) {
            $title = $array_or_title['title'] ?? null;
            $parent_id = $array_or_title['parent_id'] ?? null;
            if (is_null($parent_id) && isset($array_or_title['parent'])) {
                $parent_id = self::get_admin_menu_item_by_title($array_or_title['parent']);
            }
        } else {
            $title = (string) $array_or_title;
            $parent_id = null;
            $cnt = \common\models\Admin_Boxes::find()->where(['title' => $title])->count();
            if ($cnt > 1) {
                \Yii::warning(__FUNCTION__ . " - inconsistent search result for {$title}. More than one row: {$cnt}");
            }
        }
        if (!empty($title)) {
            return \common\models\Admin_Boxes::find()->where(['title' => $title])->and_filter_where(['parent_id' => $parent_id])->one();
        }
    }
    public static function get_admin_menu_item_last($parent_id = 0)
    {
        return \common\models\Admin_Boxes::find()->where(['parent_id' => $parent_id])->order_by('sort_order DESC')->limit(1)->one();
    }
    public static function get_admin_menu_child($array_or_title)
    {
        $row = self::get_admin_menu_item_by_title($array_or_title);
        if (!empty($row)) {
            return \common\models\Admin_Boxes::find()->where(['parent_id' => $row->box_id])->all();
        }
    }
    public static function remove_admin_menu_items($items_array)
    {
        if (is_array($items_array)) {
            foreach ($items_array as $item) {
                if (is_array($item)) {
                    self::remove_admin_menu_item($item);
                }
            }
        }
    }
    /**
     * @param type $array_or_title
     * @return null if success or error message
     */
    public static function remove_admin_menu_item($array_or_title)
    {
        if (is_array($array_or_title['child'] ?? null)) {
            self::remove_admin_menu_items($array_or_title['child']);
        }
        $row = self::get_admin_menu_item_by_title($array_or_title);
        if (empty($row)) {
            return 'removeAdminMenu: cant find item';
        }
        if (!empty($child = self::get_admin_menu_child($array_or_title))) {
            return 'removeAdminMenu: cant delete item because of child: ' . count($child);
        }
        $row->delete();
    }
    public static function resort_admin_menu($parent_id, $resort_after_id)
    {
        \common\models\Admin_Boxes::update_all(['sort_order' => new \yii\db\Expression('sort_order+1')], 'parent_id = :parent_id AND sort_order >= :shift_sort_order', ['parent_id' => $parent_id, 'shift_sort_order' => $resort_after_id++]);
    }
    public static function create_admin_menu_items($items_array, array $params = [])
    {
        if (is_array($items_array)) {
            foreach ($items_array as $item) {
                if (is_array($item)) {
                    self::create_admin_menu_item($item, $params);
                }
            }
        }
    }
    private static function array_to_xml($data, &$xml_data)
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (is_numeric($key)) {
                    $key = 'item';
                    //dealing with <0/>..<n/> issues
                }
                $subnode = $xml_data->add_child($key);
                self::array_to_xml($value, $subnode);
            } else {
                $xml_data->add_child("{$key}", htmlspecialchars("{$value}"));
            }
        }
    }
    public static function reset_admin_menu()
    {
        $path = \Yii::get_alias('@webroot');
        $filename = $path . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'default_menu.xml';
        $xmlfile = file_get_contents($filename);
        $ob = simplexml_load_string($xmlfile);
        if (isset($ob)) {
            $ex_items = \common\helpers\Menu_Helper::get_extensions_tree_items();
            // add first level menus like MarketPlaces
            foreach ($ex_items as $key => $value) {
                if (empty($value['parent'])) {
                    $already_exist = false;
                    foreach ($ob->item as $item) {
                        if ((string) $item->title == $value['title']) {
                            $already_exist = true;
                            if (isset($value['child'])) {
                                if (empty($item->child)) {
                                    $item->add_child('child');
                                }
                                $new_child = $item->child;
                                self::array_to_xml($value['child'], $new_child);
                            }
                        }
                    }
                    if (!$already_exist) {
                        $child = $ob->add_child('item');
                        self::array_to_xml($value, $child);
                    }
                    unset($ex_items[$key]);
                }
            }
            $ob_prepared = \common\helpers\Menu_Helper::prepare_admin_tree($ob, $ex_items);
            tep_db_query('TRUNCATE TABLE admin_boxes;');
            \common\helpers\Menu_Helper::import_admin_tree($ob_prepared);
        }
    }
    /**
     * @param array $array - admin menu item
     * @param array $params - ['removeIfExists' => true(default)/false, 'addAfterMenuTitle' => null(default)/'__last__'/'Title']
     */
    public static function create_admin_menu_item(array $array, array $params = [])
    {
        $array['sort_order'] = $array['sort_order'] ?? 0;
        $array['box_type'] = $array['box_type'] ?? 0;
        $array['acl_check'] = $array['acl_check'] ?? '';
        $array['config_check'] = $array['config_check'] ?? '';
        $array['filename'] = $array['filename'] ?? '';
        // find parent_id
        if (!empty($array['parent'])) {
            $parent = self::get_admin_menu_item_by_title($array['parent']);
            if (!empty($parent)) {
                $array['parent_id'] = $parent->box_id;
            }
        }
        $array['parent_id'] = $array['parent_id'] ?? 0;
        // if already exists
        if ($array['removeIfExists'] ?? true) {
            self::remove_admin_menu_item($array);
        }
        if (empty(self::get_admin_menu_item_by_title($array))) {
            // resort
            $add_after_title = $array['addAfterMenuTitle'] ?? null;
            if ($add_after_title == '__last__') {
                $menu = self::get_admin_menu_item_last($array['parent_id']);
            } elseif (!empty($add_after_title)) {
                $menu = self::get_admin_menu_item_by_title($add_after_title);
            }
            if (!empty($menu) && $menu->parent_id == $array['parent_id']) {
                $array['sort_order'] = $menu->sort_order + 1;
            }
            $array['sort_order'] = $array['sort_order'] ?? 100;
            self::resort_admin_menu($array['parent_id'], $array['sort_order']);
            // create
            $res = self::create_admin_menu_item_and_check_acl($array);
        }
        if (is_array($array['child'] ?? null)) {
            foreach ($array['child'] as $key => $child) {
                if (!empty($res) && $res->box_id > 0) {
                    $array['child'][$key]['parent_id'] = $res->box_id;
                } else {
                    // $res->box_id = 0 if parent was already exist
                    $array['child'][$key]['parent'] = $array['title'];
                }
            }
            self::create_admin_menu_items($array['child']);
        }
    }
    public static function categories_to_menu_message()
    {
        if (!\common\helpers\Acl::rule(['BOX_HEADING_DESIGN_CONTROLS', 'FILENAME_CMS_MENUS'])) {
            return '';
        }
        $last_modified_brands = \common\models\Manufacturers::find()->max('date_added');
        $platforms = \common\classes\platform::get_list(false);
        foreach ($platforms as $platform) {
            $last_modified_categories = \common\models\Categories::find()->alias('c')->left_join(['pc' => \common\models\Platforms_Categories::table_name()], 'c.categories_id = pc.categories_id')->where(['pc.platform_id' => $platform['id']])->max('date_added');
            $category = Menus::find()->alias('m')->select(['m.id', 'mi.platform_id', 'm.last_modified'])->left_join(['mi' => \common\models\Menu_Items::table_name()], 'm.id = mi.menu_id')->where(['mi.link_id' => '999999999', 'mi.platform_id' => $platform['id']])->and_where(['<', 'm.last_modified', $last_modified_categories])->as_array()->one();
            if ($category) {
                $message = CATEGORIES_TO_MENU;
            } else {
                $brand = Menus::find()->alias('m')->select(['m.id', 'mi.platform_id', 'm.last_modified'])->left_join(['mi' => \common\models\Menu_Items::table_name()], 'm.id = mi.menu_id')->where(['mi.link_id' => '999999998', 'mi.platform_id' => $platform['id']])->and_where(['<', 'm.last_modified', $last_modified_brands])->as_array()->one();
                if (!$brand) {
                    continue;
                }
                $message = BRANDS_TO_MENU;
            }
            $message = sprintf($message, Yii::$app->url_manager->create_url(['menus', 'menu' => $category['id'] ?? false, 'platform_id' => $platform['id'] ?? false]));
            \Yii::$container->get('message_stack')->add($message, 'alert', 'info menu-message', 'menu-message');
            return '';
        }
    }
    public static function create_menu($menu_name, $data = [], $platform_id = false)
    {
        if (Menus::find_one(['menu_name' => $menu_name])) {
            return '';
        }
        if ($platform_id === false) {
            $platform_id = \common\classes\platform::default_id();
        }
        $menu = new Menus();
        $menu->menu_name = $menu_name;
        $menu->last_modified = new \yii\db\Expression('NOW()');
        $menu->save();
        $menu_id = $menu->get_primary_key();
        self::create_menu_items($data, $menu_id, $platform_id);
        return $menu_id;
    }
    public static function create_menu_items($data, $menu_id, $platform_id, $parent_id = 0, $local = false)
    {
        if (!is_array($data)) {
            return false;
        }
        $language_id = Language::get_default_language_id();
        foreach ($data as $item) {
            $menu_item = new Menu_Items();
            $link_type = $item['link_type'];
            if ($local) {
                $link_id = $item['link_id_local'];
            } else {
                $link_id = $item['link_id'];
                if ($item['link_type'] == 'info') {
                    $link_id = \common\models\Information::find_one(['platform_id' => $platform_id, 'seo_page_name' => $item['link_id']])->information_id ?? null;
                    if (!$link_id) {
                        $link_id = \common\models\Information::find_one(['seo_page_name' => $item['link_id']])->information_id ?? null;
                    }
                }
                if ($item['link_type'] == 'categories' && $link_id != '999999999') {
                    $link_id = \common\models\Categories_Description::find_one(['categories_seo_page_name' => $link_id, 'language_id' => $language_id])->categories_id;
                }
                if ($item['link_type'] == 'brands' && $link_id != '999999998') {
                    $link_id = \common\models\Manufacturers::find_one(['manufacturers_name' => $link_id])->manufacturers_id;
                }
                if (!$link_id && in_array($item['link_type'], ['info', 'categories', 'brands'])) {
                    if (is_array($item['titles']) && count($item['titles']) || is_array($item['children']) && count($item['children'])) {
                        $link_type = 'custom';
                    } else {
                        continue;
                    }
                }
            }
            $menu_item->attributes = ['platform_id' => $platform_id, 'menu_id' => $menu_id, 'parent_id' => $parent_id, 'link' => $item['link'], 'link_id' => $link_id, 'link_type' => $link_type, 'target_blank' => $item['target_blank'], 'sub_categories' => $item['sub_categories'], 'custom_categories' => $item['custom_categories'], 'class' => $item['class'], 'sort_order' => $item['sort_order'], 'no_logged' => $item['no_logged']];
            $menu_item->save();
            $menu_item_id = $menu_item->get_primary_key();
            if (is_array($item['titles'])) {
                foreach ($item['titles'] as $langeage_key => $vals) {
                    $language = Language::get_language_id($langeage_key);
                    $menu_titles = new \common\models\Menu_Titles();
                    $menu_titles->attributes = ['language_id' => (int) $language['languages_id'], 'item_id' => (int) $menu_item_id, 'title' => $vals['title'], 'link' => $vals['link']];
                    $menu_titles->save();
                }
            }
            if ($item['children']) {
                self::create_menu_items($item['children'], $menu_id, $platform_id, $menu_item_id);
            }
        }
    }
    public static function menu_tree($menu_name, $platform_id = false)
    {
        if ($platform_id === false) {
            $platform_id = \common\classes\platform::default_id();
        }
        $languages = [];
        $menu_id = Menus::find_one(['menu_name' => $menu_name])->id;
        $menu_items = Menu_Items::find()->where(['menu_id' => $menu_id, 'platform_id' => $platform_id])->as_array()->all();
        foreach ($menu_items as $key => $menu_item) {
            $menu_titles = \common\models\Menu_Titles::find()->where(['item_id' => $menu_item['id']])->as_array()->all();
            foreach ($menu_titles as $menu_title) {
                if (!isset($languages[$menu_title['language_id']]) || !$languages[$menu_title['language_id']]) {
                    $lng = Language::get_language_code($menu_title['language_id']);
                    $languages[$menu_title['language_id']] = $lng['code'];
                }
                $menu_items[$key]['titles'][$languages[$menu_title['language_id']]] = ['title' => $menu_title['title'], 'link' => $menu_title['link']];
            }
        }
        return self::menu_tree_items($menu_items, $platform_id);
    }
    private static function menu_tree_items($menu_data, $platform_id, $parent_id = 0)
    {
        $language_id = Language::get_default_language_id();
        $tree = [];
        foreach ($menu_data as $item) {
            if ($item['parent_id'] != $parent_id) {
                continue;
            }
            $link_id = $item['link_id'];
            $link_id_local = $item['link_id'];
            if ($item['link_type'] == 'info') {
                $link_id = \common\models\Information::find_one(['platform_id' => $platform_id, 'information_id' => $item['link_id'], 'languages_id' => $language_id])->seo_page_name ?? null;
            }
            if ($item['link_type'] == 'categories') {
                $link_id = \common\models\Categories_Description::find_one(['categories_id' => $item['link_id'], 'language_id' => $language_id])->categories_seo_page_name ?? $link_id;
            }
            if ($item['link_type'] == 'brands') {
                $link_id = \common\models\Manufacturers::find_one($link_id)->manufacturers_name ?? $link_id;
            }
            $tree[] = ['link' => $item['link'], 'link_id' => $link_id, 'link_id_local' => $link_id_local, 'link_type' => $item['link_type'], 'target_blank' => $item['target_blank'], 'sub_categories' => $item['sub_categories'], 'custom_categories' => $item['custom_categories'], 'class' => $item['class'], 'sort_order' => $item['sort_order'], 'no_logged' => $item['no_logged'], 'titles' => $item['titles'] ?? null, 'children' => self::menu_tree_items($menu_data, $platform_id, $item['id'])];
        }
        return $tree;
    }
    public static function get_account_pages()
    {
        $pages = \common\models\Themes_Settings::find()->select(['setting_value'])->distinct()->where(['setting_group' => 'added_page', 'setting_name' => 'account'])->as_array()->cache(self::MENU_CACHE_LIFETIME)->all();
        $account_pages = [];
        foreach ($pages as $page) {
            $account_pages[] = ['type_id' => hexdec(substr(md5($page['setting_value']), 0, 7)), 'name' => $page['setting_value']];
        }
        return $account_pages;
    }
    public static function get_components($platform_id)
    {
        $theme = \common\models\Platforms_To_Themes::find_one(['platform_id' => $platform_id]);
        if (!$theme || !$theme->theme_id) {
            return [];
        }
        $theme_id = $theme->theme_id;
        $theme = \common\models\Themes::find_one(['id' => $theme_id]);
        if (!$theme || !$theme->theme_name) {
            return [];
        }
        $theme_name = $theme->theme_name;
        $pages = \common\models\Themes_Settings::find()->select(['setting_value'])->distinct()->where(['setting_group' => 'added_page', 'setting_name' => 'components', 'theme_name' => $theme_name])->as_array()->all();
        $components = [];
        foreach ($pages as $page) {
            $components[$page['setting_value']] = $page['setting_value'];
        }
        return $components;
    }
    public static function remove_menu($menu_id)
    {
        Menus::find_one($menu_id)->delete();
        self::remove_menu_items($menu_id);
    }
    public static function remove_menu_items($menu_id, $platform_id = false)
    {
        $conditions = ['menu_id' => $menu_id];
        if ($platform_id) {
            $conditions['platform_id'] = $platform_id;
        }
        $menus = Menu_Items::find()->select('id')->where($conditions)->as_array()->all();
        foreach ($menus as $menu) {
            Menu_Titles::delete_all(['item_id' => $menu['id']]);
            if ($ext = \common\helpers\Acl::check_extension_allowed('SeoRedirectsNamed', 'allowed')) {
                $ext::delete_menu_links((int) $menu['id']);
            }
        }
        Menu_Items::delete_all($conditions);
    }
    public static function get_extension_html_menu($code, $show_acl_link = false, $item_class = 'extension-menu-item')
    {
        $res = '';
        $extension = \common\helpers\Extensions::is_allowed($code);
        if (!empty($extension) && !empty($admin_menu = $extension::get_admin_menu())) {
            $extension::init_translation('install');
            $menu_items = self::build_hierarchy_html_array($admin_menu);
            $res = '<div class="mb-2">' . self::menu_items_to_html($menu_items, $item_class) . '</div>';
            if ($show_acl_link && \common\helpers\Acl::rule(['BOX_HEADING_ADMINISTRATOR', 'BOX_ADMINISTRATOR_BOXES']) && self::$count_disabled_menu_item > 0) {
                $res .= '<div class="extension-acl-link"><a target="_blank" class="btn btn-secondary btn-edit btn-block" href="' . \Yii::$app->url_manager->create_url(['adminfiles']) . '">' . TEXT_CHANGE_ACL . '</a></div>';
            }
        }
        return $res;
    }
    public static function menu_items_to_html($menu_items, $item_class = 'extension-menu-item')
    {
        $html_items = '';
        foreach (array_unique($menu_items, SORT_REGULAR) as $menu_item) {
            if (is_array($menu_item)) {
                $html_items .= self::menu_items_to_html($menu_item, $item_class);
            } else {
                $html_items .= "<div class = \"{$item_class}\">{$menu_item}</div>";
            }
        }
        return $html_items;
    }
    /**
     * @param $adminMenu array this is a result of {@see $module::getAdminMenu()}
     * @param $level integer hierarchy level depth. Default value is 0
     * @return array Extension menu in html format or empty string
     */
    private static function build_hierarchy_html_array($admin_menu, $level = 0, $ignore_root = false)
    {
        \common\helpers\Translation::init('admin/main');
        $indent = '&nbsp;&nbsp;&nbsp;&nbsp;';
        if (!is_array($admin_menu)) {
            // If empty $adminMenu then return nothing
            return [];
        }
        $res = [];
        foreach ($admin_menu as $item) {
            if (isset($item['parent']) && !$ignore_root) {
                $root_level = 0;
                foreach (array_reverse(self::build_root_menu($item['parent']) ?? []) as $root) {
                    $res[] = str_repeat($indent, $root_level) . $root;
                    // Adding a visual shift
                    $root_level++;
                }
                $level = $root_level;
                // End work with root menu items. Transferring the shift level to the extension menu items
            }
            //            $title = (defined($item['title'])) ? constant($item['title']) : $item['title'];  // Replacing the title with a translation
            $title = \common\helpers\Translation::get_value($item['title'], 'admin/main', $item['title']);
            // Replacing the title with a translation
            if (!isset($item['path']) || empty($item['path']) || is_array($item['child'] ?? null) || !self::is_menu_item_enabled($item['title']) || !self::is_menu_item_allowed($item['title'])) {
                $res[] = str_repeat($indent, $level) . '<button class="btn btn-disabled" style="background: dimgrey; border-color: dimgrey" href="#" disabled="disabled">' . $title . '</button>';
                if (!self::is_menu_item_enabled($item['title'])) {
                    self::$count_disabled_menu_item++;
                }
            } else {
                $res[] = str_repeat($indent, $level) . '<a class="btn btn-primary" href="' . \Yii::$app->url_manager->create_url([$item['path']]) . '">' . $title . '</a>';
            }
            if (isset($item['child'])) {
                $res[] = self::build_hierarchy_html_array($item['child'], $level + 1, true);
            }
        }
        return $res;
    }
    /**
     * This method builds the missing top menu levels if {@see $module::getAdminMenu()} method returns a menu with an incomplete hierarchy
     * @param $id_or_title string | integer
     * @param $level integer
     * @return array|null
     */
    private static function build_root_menu($id_or_title, $level = 0)
    {
        $root = [];
        $item = self::get_menu_item_record($id_or_title);
        if (is_object($item)) {
            $title = defined($item->title) ? constant($item->title) : $item->title;
            // Replacing the title with a translation
            $root[] = '<button class="btn btn-disabled" style="background: dimgrey; border-color: dimgrey" href="#" disabled="disabled">' . $title . '</button>';
            if ($item->parent_id > 0) {
                $root = array_merge($root, self::build_root_menu($item->parent_id, $level + 1));
            }
            return $root;
        }
    }
    /**
     * @param $item string  Menu item. Example: BOX_HEADING_CATALOG
     * @return bool
     */
    private static function is_menu_item_enabled($item)
    {
        $chain = self::get_menu_item_chain($item);
        if (!empty($chain)) {
            return \common\helpers\Acl::rule($chain);
        }
        return false;
    }
    /**
     * This method searches for menu items and checks the ACL
     *
     * @param $id_or_title string | integer
     * @return boolean
     *
     */
    private static function is_menu_item_allowed($id_or_title)
    {
        $item = self::get_menu_item_record($id_or_title);
        if (is_object($item)) {
            $acl = explode(',', $item->acl_check);
            $check = \common\helpers\Extensions::call_if_allowed($acl[0], $acl[1]);
            //            return empty($item->acl_check) ? true : \common\helpers\Extensions::callIfAllowed($acl[0], $acl[1]);
            return empty($item->acl_check) ? true : $check;
        }
        return false;
    }
    /**
     * Collect ACL chain from menu item
     * @param $menuItem string Menu item. Example: BOX_HEADING_CATALOG
     * @return array|void
     */
    private static function get_menu_item_chain($menu_item)
    {
        $item = self::get_menu_item_record($menu_item);
        if (is_object($item)) {
            return self::get_admin_menu_chain($item->box_id);
        }
    }
    private static function get_menu_item_record($menu_title_or_id)
    {
        if (is_string($menu_title_or_id)) {
            $item = self::get_admin_menu_item_by_title($menu_title_or_id);
        } elseif (is_int($menu_title_or_id)) {
            $item = \common\models\Admin_Boxes::find_one(['box_id' => $menu_title_or_id]);
        }
        return $item ?? null;
    }
}