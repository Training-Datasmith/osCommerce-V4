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
namespace backend\controllers;

use backend\components\Information;
use common\helpers\Menu_Helper;
use common\models\Menu_Items;
use common\models\Menus;
use common\models\Menu_Source;
use common\models\Platforms;
use Yii;
ini_set('memory_limit', '-1');
/**
 * default controller to handle user requests.
 */
class Menus_Controller extends Sceleton
{
    public const MENU_CATEGORIES_COLLAPSED = true;
    public const MENU_CATEGORIES_MAX_LEVEL = -1;
    //-1 - no restriction, starts from 1
    public $acl = ['BOX_HEADING_DESIGN_CONTROLS', 'FILENAME_CMS_MENUS'];
    public function action_index()
    {
        global $languages;
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/design');
        $this->top_buttons[] = '<span class="btn btn-confirm btn-save">' . IMAGE_SAVE . '</span>';
        $this->top_buttons[] = '<span class="btn btn-primary btn-create-menu menu-ico">' . TEXT_CREATE_MENU . '</span>';
        $this->view->use_popup_mode = false;
        if (Yii::$app->request->is_ajax) {
            $this->layout = false;
            $this->view->use_popup_mode = true;
        }
        $this->selected_menu = ['design_controls', 'menus'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('menus/index'), 'title' => HEADING_TITLE];
        $this->view->heading_title = HEADING_TITLE;
        $selected_platform_id = \common\classes\platform::first_id();
        $try_set_platform = Yii::$app->request->get('platform_id', 0);
        if ($try_set_platform > 0) {
            foreach (\common\classes\platform::get_list(false) as $_platform) {
                if ((int) $try_set_platform == (int) $_platform['id']) {
                    $selected_platform_id = (int) $try_set_platform;
                }
            }
        }
        $menu_id = (int) Yii::$app->request->get('menu', 0);
        if ($menu_id == 0) {
            $sql = tep_db_fetch_array(tep_db_query('select id from ' . TABLE_MENUS . ' where 1 limit 1'));
            $menu_id = $sql['id'];
        }
        $sql = tep_db_query('SELECT information_id, info_title, page_title from ' . TABLE_INFORMATION . " WHERE visible='1' and languages_id =" . (int) $languages_id . " and platform_id='" . $selected_platform_id . "' and affiliate_id=0 " . (Information::show_hide_page() ? '' : ' and hide=0 ') . ' order by v_order');
        $info = [];
        while ($row = tep_db_fetch_array($sql)) {
            if ($row['info_title']) {
                $row['title'] = $row['info_title'];
            } elseif ($row['page_title']) {
                $row['title'] = $row['page_title'];
            }
            $info[] = $row;
        }
        $account_pages = \common\helpers\Menu_Helper::get_account_pages();
        $components = \common\helpers\Menu_Helper::get_components($selected_platform_id);
        $sql = tep_db_query('select c.categories_id, c.parent_id, if(length(cd1.categories_name), cd1.categories_name, cd.categories_name) as categories_name  ' . 'from ' . TABLE_CATEGORIES_DESCRIPTION . ' cd, ' . TABLE_CATEGORIES . ' c ' . ' inner join ' . TABLE_PLATFORMS_CATEGORIES . " pc on pc.categories_id=c.categories_id and pc.platform_id='" . $selected_platform_id . "' " . ' left join ' . TABLE_CATEGORIES_DESCRIPTION . " cd1 on cd1.categories_id = c.categories_id and cd1.language_id='" . (int) $languages_id . "' and cd1.affiliate_id = '" . (isset($_SESSION['affiliate_ref']) ? (int) $_SESSION['affiliate_ref'] : 0) . "' " . "where c.categories_id = cd.categories_id and cd.language_id = '" . (int) $languages_id . "' and cd.affiliate_id = 0 " . (self::MENU_CATEGORIES_MAX_LEVEL != -1 ? ' and c.categories_level<=' . (int) self::MENU_CATEGORIES_MAX_LEVEL . ' ' : '') . 'order by c.categories_level, c.categories_left, c.parent_id');
        $categories = [];
        while ($row = tep_db_fetch_array($sql)) {
            $parent_id = $row['parent_id'];
            $categories_id = $row['categories_id'];
            if ($parent_id != 0) {
                unset($row['parent_id']);
            }
            $categories[$categories_id] = $row;
            $categories[$parent_id]['children'][$categories_id] = [];
            if ($parent_id > 0) {
                $categories[$parent_id]['children'][$categories_id] = $row;
            }
        }
        //echo "<pre>" . print_r($categories, 1). "</pre>"; die;
        $brands = \common\helpers\Menu_Helper::get_brands_list();
        $groups = [];
        if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroups', 'allowed')) {
            $groups = $ext::get_groups_array();
        }
        $sql = tep_db_query("select mi.*, if(i.info_title='', i.page_title, ifnull(i.info_title,'')) as name, ifnull(mt.title, '') as shown " . ' from ' . TABLE_MENU_ITEMS . ' mi left join ' . TABLE_INFORMATION . " i on i.information_id=mi.link_id and i.visible='1' and i.languages_id =" . (int) $languages_id . " and i.platform_id='" . (int) $selected_platform_id . "' and i.affiliate_id=0 left join " . TABLE_MENU_TITLES . ' mt on mt.item_id=mi.id and mt.language_id = ' . (int) $languages_id . " where mi.platform_id='" . (int) $selected_platform_id . "' and mi.menu_id='" . $menu_id . "' order by sort_order");
        $new_categories = [];
        $new_brands = [];
        $menu = [];
        while ($row = tep_db_fetch_array($sql)) {
            if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroups', 'allowed')) {
                $menu_groups = [];
                $groups_arr = explode(',', $row['user_groups']);
                foreach ($groups_arr as $group) {
                    $menu_groups[] = trim($group, '#');
                }
                $row['groups'] = $menu_groups;
            }
            $row['name'] = 'item #' . $row['id'];
            if ($row['link_type'] == 'info') {
                $row1 = tep_db_fetch_array(tep_db_query('SELECT information_id, info_title, page_title from ' . TABLE_INFORMATION . " WHERE visible='1' and languages_id =" . (int) $languages_id . " and information_id='" . $row['link_id'] . "' and platform_id='" . $selected_platform_id . "' and affiliate_id=0"));
                if (!isset($row1) || !is_array($row1)) {
                    $row1 = tep_db_fetch_array(tep_db_query('SELECT information_id, info_title, page_title from ' . TABLE_INFORMATION . " WHERE visible='1' and languages_id =" . (int) $languages_id . " and information_id='" . $row['link_id'] . "' and affiliate_id=0"));
                }
                if ($row1['info_title'] ?? null) {
                    $row['name'] = $row1['info_title'];
                } elseif ($row1['page_title'] ?? null) {
                    $row['name'] = $row1['page_title'];
                }
                $sql1 = tep_db_query('select title from ' . TABLE_MENU_TITLES . ' where item_id = ' . (int) $row['id'] . ' and language_id = ' . $languages_id);
                if ($row1 = tep_db_fetch_array($sql1)) {
                    $row['shown'] = $row1['title'];
                }
            } elseif ($row['link_type'] == 'component') {
                $row['name'] = TEXT_COMPONENT . ': "' . $row['link'] . '"';
            } elseif ($row['link_type'] == 'categories') {
                if ($row['link_id'] == '999999999') {
                    $row['name'] = TEXT_ALL_CATEGORIES;
                    $query = tep_db_fetch_array(tep_db_query('select last_modified from ' . TABLE_MENUS . " where id = '" . $menu_id . "'"));
                    $sql3 = tep_db_query('select c.categories_id, c.parent_id, cd.categories_name ' . 'from ' . TABLE_CATEGORIES . ' c  ' . ' inner join ' . TABLE_PLATFORMS_CATEGORIES . " pc on pc.categories_id=c.categories_id and pc.platform_id='" . $selected_platform_id . "' " . ' left join ' . TABLE_CATEGORIES_DESCRIPTION . ' cd on c.categories_id = cd.categories_id  ' . "where c.date_added > '" . $query['last_modified'] . "' and cd.language_id = '" . $languages_id . "' and cd.affiliate_id=0");
                    if (tep_db_num_rows($sql3) > 0) {
                        while ($item = tep_db_fetch_array($sql3)) {
                            $new_categories[] = $item;
                        }
                    }
                } else {
                    $sql1 = tep_db_query('SELECT categories_name from ' . TABLE_CATEGORIES_DESCRIPTION . ' WHERE language_id =' . (int) $languages_id . " and categories_id='" . $row['link_id'] . "' and affiliate_id=0");
                    if ($row1 = tep_db_fetch_array($sql1)) {
                        $row['name'] = $row1['categories_name'];
                    }
                    /*
                                                          $sql1=tep_db_query("select title from " . TABLE_MENU_TITLES . " where item_id = " . (int)$row['id'] . " and language_id = " . $languages_id);
                                                          if ($row1=tep_db_fetch_array($sql1)){
                                                            $row['shown'] = $row1['title'];
                                                          }*/
                }
                if (count($new_categories) > 0) {
                    if ($row['link_id'] == 999999999) {
                        $current = 0;
                    } else {
                        $current = $row['link_id'];
                    }
                    foreach ($new_categories as $item) {
                        if ($item['parent_id'] == $current) {
                            $menu_item = ['parent_id' => $row['id'], 'link_type' => 'categories', 'name' => $item['categories_name'], 'link_id' => $item['categories_id'], 'new_category' => $item['categories_id']];
                            if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroups', 'allowed')) {
                                $menu_item['groups'] = [0];
                            }
                            $menu[] = $menu_item;
                        }
                    }
                }
            } elseif ($row['link_type'] == 'brands') {
                if ($row['link_id'] == '999999998') {
                    $row['name'] = 'All Brands';
                    $query = tep_db_fetch_array(tep_db_query('select last_modified from ' . TABLE_MENUS . " where id = '" . $menu_id . "'"));
                    $manufacturers_query = tep_db_query('select manufacturers_id, manufacturers_name, manufacturers_image from ' . TABLE_MANUFACTURERS . " where date_added > '" . $query['last_modified'] . "' order by manufacturers_name asc");
                    if (tep_db_num_rows($manufacturers_query) > 0) {
                        while ($item = tep_db_fetch_array($manufacturers_query)) {
                            $new_brands[] = $item;
                        }
                    }
                } else {
                    $row['name'] = $brands[$row['link_id']]['manufacturers_name'];
                    /*                    $sql1=tep_db_query("select title from " . TABLE_MENU_TITLES . " where item_id = " . (int)$row['id'] . " and language_id = " . $languages_id);
                                          if ($row1=tep_db_fetch_array($sql1)){
                                              $row['shown'] = $row1['title'];
                                          }*/
                }
                if (count($new_brands) > 0) {
                    if ($row['link_id'] == 999999998) {
                        $current = 0;
                    } else {
                        $current = $row['link_id'];
                    }
                    foreach ($new_brands as $item) {
                        if (isset($item['parent_id']) && $item['parent_id'] == $current) {
                            $menu[] = ['parent_id' => $row['id'], 'link_type' => 'brands', 'name' => $item['manufacturers_name'], 'link_id' => $item['manufacturers_id'], 'new_brand' => $item['manufacturers_id']];
                        }
                    }
                }
            } elseif ($row['link_type'] == 'custom') {
                $sql1 = tep_db_query('select title from ' . TABLE_MENU_TITLES . ' where item_id = ' . (int) $row['id'] . ' and language_id = ' . $languages_id);
                if ($row1 = tep_db_fetch_array($sql1)) {
                    $row['name'] = $row1['title'];
                }
            } elseif ($row['link_type'] == 'default') {
                if ($row['link_id'] == '8888886') {
                    $row['name'] = TEXT_HOME;
                } elseif ($row['link_id'] == '8888887') {
                    $row['name'] = TEXT_SIGN_IN . ' / ' . TEXT_HEADER_LOGOUT;
                } elseif ($row['link_id'] == '8888888') {
                    $row['name'] = TEXT_MY_ACCOUNT . ' / ' . TEXT_MY_ACCOUNT;
                } elseif ($row['link_id'] == '8888884') {
                    $row['name'] = TEXT_CHECKOUT;
                } elseif ($row['link_id'] == '8888883') {
                    $row['name'] = TEXT_SHOPPING_CART;
                } elseif ($row['link_id'] == '8888882') {
                    $row['name'] = IMAGE_NEW_PRODUCT;
                } elseif ($row['link_id'] == '8888881') {
                    $row['name'] = BOX_CATALOG_FEATURED;
                } elseif ($row['link_id'] == '8888880') {
                    $row['name'] = TEXT_SPECIALS_PRODUCTS;
                } elseif ($row['link_id'] == '8888879') {
                    $row['name'] = TEXT_GIFT_CARD;
                } elseif ($row['link_id'] == '8888878') {
                    $row['name'] = TEXT_ALL_PRODUCTS;
                } elseif ($row['link_id'] == '8888877') {
                    $row['name'] = TEXT_SITE_MAP;
                } elseif ($row['link_id'] == '8888876') {
                    $row['name'] = BOX_PROMOTIONS;
                } elseif ($row['link_id'] == '8888875') {
                    $row['name'] = TEXT_WEDDING_REGISTRY;
                } elseif ($row['link_id'] == '8888874') {
                    $row['name'] = MANAGE_YOUR_WEDDING_REGISTRY;
                } elseif ($row['link_id'] == '8888873') {
                    $row['name'] = TEXT_QUICK_ORDER;
                }
                //TODO: After added page add to Sceleton-bindActionParams exceptions for non logged users
                $sql1 = tep_db_query('select title from ' . TABLE_MENU_TITLES . ' where item_id = ' . (int) $row['id'] . ' and language_id = ' . $languages_id);
                if ($row1 = tep_db_fetch_array($sql1)) {
                    $row['shown'] = $row1['title'];
                }
            } elseif ($row['link_type'] == 'account') {
                foreach ($account_pages as $account_page) {
                    if ($row['link_id'] == $account_page['type_id']) {
                        $row['name'] = $account_page['name'];
                    }
                }
                $sql1 = tep_db_query('select title from ' . TABLE_MENU_TITLES . ' where item_id = ' . (int) $row['id'] . ' and language_id = ' . $languages_id);
                if ($row1 = tep_db_fetch_array($sql1)) {
                    $row['shown'] = $row1['title'];
                }
            }
            $titles = tep_db_query('select language_id, title from ' . TABLE_MENU_TITLES . ' where item_id = ' . (int) $row['id']);
            $row['titles'] = [];
            while ($item = tep_db_fetch_array($titles)) {
                $row['titles'][$item['language_id']] = $item['title'];
            }
            $row['customFilters'] = '';
            if (!empty($row['custom_categories'])) {
                if ($ext = \common\helpers\Acl::check_extension('ProductPropertiesFilters', 'inFilters')) {
                    if ($ext::allowed()) {
                        $output = [];
                        parse_str($row['custom_categories'], $output);
                        $row['customFilters'] = $ext::build_admin_menu_row($output);
                    }
                }
            }
            if ($row['settings'] ?? false) {
                $row['settings'] = json_decode($row['settings'], true);
            } else {
                $row['settings'] = [];
            }
            $sql1 = tep_db_fetch_array(tep_db_query('SELECT count(*) as total from ' . TABLE_CATEGORIES . " where categories_id='" . $row['link_id'] . "'"));
            $sql2 = tep_db_fetch_array(tep_db_query('SELECT count(*) as total from ' . TABLE_INFORMATION . " where information_id='" . $row['link_id'] . "'"));
            if ($row['link_type'] != 'categories' || $sql1['total'] > 0 || $row['link_id'] == '999999999') {
                if ($row['link_type'] != 'info' || $sql2['total'] > 0) {
                    $menu[] = $row;
                }
            }
        }
        $languages = \common\helpers\Language::get_languages();
        $lang = [];
        for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
            $languages[$i]['logo'] = $languages[$i]['image'];
            $lang[] = $languages[$i];
        }
        $menus = [];
        $sql = tep_db_query('select * from ' . TABLE_MENUS . ' where 1 order by id');
        while ($row = tep_db_fetch_array($sql)) {
            $menus[] = $row;
        }
        $current_menu = [];
        $sql = tep_db_query('select * from ' . TABLE_MENUS . ' where id = ' . (int) $menu_id);
        if ($row = tep_db_fetch_array($sql)) {
            $current_menu = $row;
        }
        $default_pages = [['type_id' => 8888886, 'name' => TEXT_HOME, 'opt_need_login' => false], ['type_id' => 8888888, 'name' => TEXT_MY_ACCOUNT . ' / ' . TEXT_MY_ACCOUNT, 'opt_need_login' => false], ['type_id' => 8888887, 'name' => TEXT_SIGN_IN . ' / ' . TEXT_HEADER_LOGOUT, 'opt_need_login' => false], ['type_id' => 8888884, 'name' => TEXT_CHECKOUT, 'opt_need_login' => false], ['type_id' => 8888883, 'name' => TEXT_SHOPPING_CART, 'opt_need_login' => false], ['type_id' => 8888882, 'name' => IMAGE_NEW_PRODUCT, 'opt_need_login' => true], ['type_id' => 8888881, 'name' => BOX_CATALOG_FEATURED, 'opt_need_login' => true], ['type_id' => 8888880, 'name' => TEXT_SPECIALS_PRODUCTS, 'opt_need_login' => true], ['type_id' => 8888879, 'name' => TEXT_GIFT_CARD, 'opt_need_login' => false], ['type_id' => 8888878, 'name' => TEXT_ALL_PRODUCTS, 'opt_need_login' => true], ['type_id' => 8888877, 'name' => TEXT_SITE_MAP, 'opt_need_login' => true], ['type_id' => 8888876, 'name' => BOX_PROMOTIONS, 'opt_need_login' => false], ['type_id' => 8888875, 'name' => TEXT_WEDDING_REGISTRY, 'opt_need_login' => false], ['type_id' => 8888874, 'name' => MANAGE_YOUR_WEDDING_REGISTRY, 'opt_need_login' => false], ['type_id' => 8888873, 'name' => TEXT_QUICK_ORDER, 'opt_need_login' => false]];
        $custom_pages = \common\helpers\Menu_Helper::get_all_custom_pages($selected_platform_id);
        $custom_filters = '';
        if ($ext = \common\helpers\Acl::check_extension('ProductPropertiesFilters', 'inFilters')) {
            if ($ext::allowed()) {
                $custom_filters = $ext::build_admin_menu_row([]);
            }
        }
        $source_platform = Yii::$app->request->get('source_platform', 0);
        if ($source_platform === 0) {
            $source_platform = Menu_Source::find_one(['id' => $menu_id, 'platform_id' => $selected_platform_id])->source_platform_id ?? null;
        }
        return $this->render('index', ['default_pages' => $default_pages, 'account_pages' => $account_pages, 'components' => $components, 'current_menu' => $current_menu, 'custom_pages' => $custom_pages, 'menus' => $menus, 'menu' => $menu, 'info' => $info, 'categories' => $categories, 'brands' => $brands, 'languages' => $lang, 'languages_id' => $languages_id, 'new_categories' => count($new_categories), 'new_brands' => count($new_brands), 'platforms' => array_map(function ($platform) {
            $platform['link'] = Yii::$app->url_manager->create_url(['menus/index', 'platform_id' => $platform['id']]);
            return $platform;
        }, \common\classes\platform::get_list(false)), 'isMultiPlatforms' => \common\classes\platform::is_multi(), 'selected_platform_id' => $selected_platform_id, 'action_url_select_menu' => Yii::$app->url_manager->create_url(['menus', 'platform_id' => $selected_platform_id]), 'action_url_save_menu' => Yii::$app->url_manager->create_url(['menus/save', 'platform_id' => $selected_platform_id]), 'customFilters' => $custom_filters, 'source_platform_id' => $source_platform, 'groups' => $groups]);
    }
    public function action_save()
    {
        \common\helpers\Translation::init('admin/design');
        $selected_platform_id = \common\classes\platform::first_id();
        $try_set_platform = Yii::$app->request->get('platform_id', 0);
        if ($try_set_platform > 0) {
            foreach (\common\classes\platform::get_list(false) as $_platform) {
                if ((int) $try_set_platform == (int) $_platform['id']) {
                    $selected_platform_id = (int) $try_set_platform;
                }
            }
        }
        $params = Yii::$app->request->post();
        if (MENU_DATA_LIKE_ONE_INPUT == 'True') {
            $list = \backend\design\Style::params_from_one_input($params['list']);
        } else {
            $list = $params['list'] ?? null;
        }
        $new_menu = false;
        $menu_id = tep_db_prepare_input($params['menu_id']);
        $sql_data_array = ['menu_name' => tep_db_prepare_input($params['menu_name'])];
        if ($params['menu_name']) {
            if ($menu_id == 0) {
                tep_db_perform(TABLE_MENUS, $sql_data_array);
                $sql = tep_db_query('select id from ' . TABLE_MENUS . " where menu_name = '" . tep_db_input(tep_db_prepare_input($params['menu_name'])) . "'");
                if ($row = tep_db_fetch_array($sql)) {
                    $menu_id = $row['id'];
                }
                $new_menu = true;
            } else {
                tep_db_perform(TABLE_MENUS, $sql_data_array, 'update', 'id = ' . (int) $menu_id);
            }
            $source_platform = Yii::$app->request->post('source_platform', 0);
            if ($source_platform) {
                $menu_source = Menu_Source::find_one(['id' => $menu_id, 'platform_id' => $selected_platform_id]);
                if (!$menu_source) {
                    $menu_source = new Menu_Source();
                    $menu_source->id = $menu_id;
                    $menu_source->platform_id = $selected_platform_id;
                }
                $menu_source->source_platform_id = $source_platform;
                $menu_source->save();
            } else {
                Menu_Source::delete_all(['id' => $menu_id, 'platform_id' => $selected_platform_id]);
            }
            if ($menu_id != 0 && !$new_menu) {
                $old = [];
                $new = [];
                $sql = tep_db_query('SELECT id from ' . TABLE_MENU_ITEMS . " WHERE platform_id='" . $selected_platform_id . "' and menu_id='" . $menu_id . "'");
                while ($row = tep_db_fetch_array($sql)) {
                    $old[] = $row['id'];
                }
                tep_db_perform(TABLE_MENUS, ['last_modified' => 'now()'], 'update', "id = '" . (int) $menu_id . "'");
                $order = 0;
                if (isset($list) && is_array($list)) {
                    foreach ($list as $item) {
                        $link_type = tep_db_prepare_input($item['type']);
                        $link = isset($item['link']) ? tep_db_prepare_input($item['link']) : '';
                        $link_id = tep_db_prepare_input($item['type_id'] ?? null);
                        $target_blank = tep_db_prepare_input($item['target_blank']);
                        $nofollow = tep_db_prepare_input($item['nofollow']);
                        $no_logged = tep_db_prepare_input($item['no_logged']);
                        $class = tep_db_prepare_input($item['class']);
                        $sub_categories = tep_db_prepare_input($item['sub_categories']);
                        $parent_link_type = isset($item['parent']['type']) ? tep_db_prepare_input($item['parent']['type']) : '';
                        $parent_link = isset($item['parent']['type_id']) ? tep_db_prepare_input($item['parent']['type_id']) : 0;
                        $custom_page = tep_db_prepare_input($item['custom_page']);
                        $custom_categories = tep_db_prepare_input($item['custom_categories']);
                        $settings = [];
                        if ($item['sorting_categories']) {
                            $settings['sorting'] = 'catalog';
                        }
                        if (isset($item['parent']['id'])) {
                            $parent_id = tep_db_prepare_input($item['parent']['id']);
                        } elseif (isset($item['parent']['type_id'])) {
                            $id = tep_db_fetch_array(tep_db_query('
                            select id from ' . TABLE_MENU_ITEMS . "\n                            where menu_id='" . (int) $menu_id . "' and link_type = '" . tep_db_input($parent_link_type) . "' and link_id = '" . (int) $parent_link . "'\n                              and platform_id='" . (int) $selected_platform_id . "'\n                            order by id desc"));
                            $parent_id = $id['id'];
                        } else {
                            $parent_id = 0;
                        }
                        $sql_data_array = ['menu_id' => $menu_id, 'parent_id' => $parent_id, 'platform_id' => $selected_platform_id, 'link' => $link, 'link_id' => $link_id, 'link_type' => $link_type, 'target_blank' => $target_blank, 'nofollow' => $nofollow, 'no_logged' => $no_logged, 'class' => $class, 'sub_categories' => $sub_categories, 'custom_categories' => $custom_categories, 'sort_order' => $order, 'theme_page_id' => (int) $custom_page, 'settings' => count($settings) ? json_encode($settings) : 'null'];
                        if (\common\helpers\Acl::check_extension_allowed('UserGroups', 'allowed')) {
                            $sql_data_array['user_groups'] = tep_db_prepare_input($item['user_groups']);
                        }
                        if (isset($item['id'])) {
                            tep_db_perform(TABLE_MENU_ITEMS, $sql_data_array, 'update', "id = '" . (int) $item['id'] . "'");
                            $new[(int) $item['id']] = $item['id'];
                            $id['id'] = $item['id'];
                        } else {
                            tep_db_perform(TABLE_MENU_ITEMS, $sql_data_array);
                            $id = ['id' => tep_db_insert_id()];
                            /*
                            $id = tep_db_fetch_array(tep_db_query("
                                        select id from " . TABLE_MENU_ITEMS . "
                                        where menu_id='" . $menu_id . "' and link_type = '" . $link_type . "' and link_id = '" . $link_id . "'
                                          and platform_id='".$selected_platform_id."'
                                        order by id desc"));
                            */
                            $new[(int) $id['id']] = $id['id'];
                        }
                        if ($ext = \common\helpers\Acl::check_extension_allowed('SeoRedirectsNamed', 'allowed')) {
                            $ext::save_menu_links($id['id'], isset($item['custom']) ? $item['custom'] : '');
                        }
                        if ($item['titles'] ?? null) {
                            foreach ($item['titles'] as $title) {
                                $sql_data_array = ['language_id' => $title['language_id'], 'item_id' => $id['id'], 'title' => $title['title']];
                                $sql = tep_db_query('
                            select id from ' . TABLE_MENU_TITLES . '
                            where language_id = ' . $title['language_id'] . ' and item_id = ' . $id['id']);
                                if (tep_db_num_rows($sql) > 0) {
                                    if ($title['title']) {
                                        tep_db_perform(TABLE_MENU_TITLES, $sql_data_array, 'update', "language_id = '" . (int) $title['language_id'] . "' and item_id = " . (int) $id['id']);
                                    } else {
                                        tep_db_query('delete from ' . TABLE_MENU_TITLES . " where language_id = '" . (int) $title['language_id'] . "' and item_id = " . (int) $id['id']);
                                    }
                                } else if ($title['title']) {
                                    tep_db_perform(TABLE_MENU_TITLES, $sql_data_array);
                                }
                            }
                        }
                        $order++;
                    }
                }
                foreach ($old as $id) {
                    if (!isset($new[(int) $id])) {
                        tep_db_query('delete from ' . TABLE_MENU_ITEMS . " where id = '" . (int) $id . "'");
                        tep_db_query('delete from ' . TABLE_MENU_TITLES . " where item_id = '" . (int) $id . "'");
                        if ($ext = \common\helpers\Acl::check_extension_allowed('SeoRedirectsNamed', 'allowed')) {
                            $ext::delete_menu_links($id);
                        }
                    }
                }
                $response = MESSAGE_SAVED;
            } else {
                $response = [MESSAGE_ADDED, $menu_id];
            }
        } else {
            tep_db_query('delete from ' . TABLE_MENUS . " where id = '" . (int) $params['menu_id'] . "'");
            $sql = tep_db_query('select id from ' . TABLE_MENU_ITEMS . ' where menu_id = ' . (int) $params['menu_id']);
            while ($row = tep_db_fetch_array($sql)) {
                tep_db_query('delete from ' . TABLE_MENU_TITLES . " where item_id = '" . (int) $row['id'] . "'");
                if ($ext = \common\helpers\Acl::check_extension_allowed('SeoRedirectsNamed', 'allowed')) {
                    $ext::delete_menu_links((int) $row['id']);
                }
            }
            tep_db_query('delete from ' . TABLE_MENU_ITEMS . " where menu_id = '" . (int) $params['menu_id'] . "'");
            $response = MESSAGE_DELETED;
        }
        return json_encode($response);
    }
    public function action_save_name()
    {
        $params = Yii::$app->request->get();
        $sql_data_array = ['menu_name' => $params['name']];
        tep_db_perform(TABLE_MENUS, $sql_data_array, 'update', 'id = ' . (int) $params['id']);
        return json_encode(['name' => $params['name']]);
    }
    public function action_export()
    {
        $menu_name = Yii::$app->request->get('name');
        $platform_id = Yii::$app->request->get('platform_id');
        $file_name = \common\classes\design::page_name($menu_name);
        header('Content-Type: application/json');
        header('Content-Transfer-Encoding: utf-8');
        header('Content-disposition: attachment; filename="' . $file_name . '.json"');
        return json_encode(\common\helpers\Menu_Helper::menu_tree($menu_name, $platform_id));
    }
    public function action_import()
    {
        $menu_name = Yii::$app->request->get('name');
        $platform_id = Yii::$app->request->get('platform_id');
        if ($_FILES['file']['error'] != UPLOAD_ERR_OK || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            return '';
        }
        $data = json_decode(file_get_contents($_FILES['file']['tmp_name']), true);
        if (!is_array($data) || !count($data)) {
            return '';
        }
        $menu_id = Menus::find_one(['menu_name' => $menu_name])->id;
        if ($menu_id) {
            \common\helpers\Menu_Helper::remove_menu_items($menu_id, $platform_id);
            Menu_Helper::create_menu_items($data, $menu_id, $platform_id);
        } else {
            $menu_id = \common\helpers\Menu_Helper::create_menu($menu_name, $data, $platform_id);
        }
        return $menu_id;
    }
    public function action_copy_from()
    {
        \common\helpers\Translation::init('admin/menus');
        $menu_id = Yii::$app->request->get('menu');
        $is_empty = Menu_Items::find()->where(['menu_id' => $menu_id])->count() ? false : true;
        $this->layout = false;
        return $this->render('copy-from', ['menu' => $menu_id, 'platform_id' => Yii::$app->request->get('platform_id'), 'menus' => Menus::find()->as_array()->all(), 'isMultiPlatforms' => \common\classes\platform::is_multi(), 'platforms' => \common\classes\platform::get_list(false), 'isEmpty' => $is_empty]);
    }
    public function action_copy_from_action()
    {
        $menu_id = Yii::$app->request->post('menu');
        $platform_id = Yii::$app->request->post('platform_id', false);
        $menu_from = Yii::$app->request->post('menu_from');
        $platform_id_from = Yii::$app->request->post('platform_id_from', false);
        $data = Menu_Helper::menu_tree($menu_from, $platform_id_from);
        Menu_Helper::remove_menu_items($menu_id, $platform_id);
        Menu_Helper::create_menu_items($data, $menu_id, $platform_id, 0, true);
        $menus = Menus::find_one($menu_id);
        $menus->last_modified = new \yii\db\Expression('NOW()');
        $menus->save();
    }
    public function action_swap()
    {
        \common\helpers\Translation::init('admin/menus');
        $menu_id = Yii::$app->request->get('menu_id');
        $platform_id = Yii::$app->request->get('platform_id', false);
        $menu = Menus::find_one($menu_id);
        $platform = Platforms::find_one(['platform_id' => $platform_id]);
        if (!$menu) {
            return json_encode(['error' => 'no menu']);
        }
        if (!$platform) {
            return json_encode(['error' => 'no platform']);
        }
        $this->layout = false;
        $html = $this->render('swap', ['menuId' => $menu_id, 'menuName' => $menu->menu_name, 'platformId' => $platform_id, 'platformName' => $platform->platform_name, 'menus' => Menus::find()->as_array()->all(), 'platforms' => \common\classes\platform::get_list(false), 'isMultiPlatforms' => \common\classes\platform::is_multi()]);
        return json_encode(['html' => $html]);
    }
    public function action_swap_action()
    {
        $menu_id = Yii::$app->request->post('menu_id');
        $platform_id = Yii::$app->request->post('platform_id', false);
        $menu_id2 = Yii::$app->request->post('menu_id_2');
        $platform_id2 = Yii::$app->request->post('platform_id_2', false);
        $tmp_menu_id = Menu_Items::find()->max('menu_id') + 1;
        Menu_Items::update_all(['menu_id' => $tmp_menu_id], ['menu_id' => $menu_id, 'platform_id' => $platform_id]);
        Menu_Items::update_all(['menu_id' => $menu_id, 'platform_id' => $platform_id], ['menu_id' => $menu_id2, 'platform_id' => $platform_id2]);
        Menu_Items::update_all(['menu_id' => $menu_id2, 'platform_id' => $platform_id2], ['menu_id' => $tmp_menu_id, 'platform_id' => $platform_id]);
    }
}