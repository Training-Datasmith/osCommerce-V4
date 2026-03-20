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
namespace common\classes;

use common\models\Platforms_Address_Book;
class platform
{
    private $config_pool = [];
    private $active_config_id = false;
    public static function name($platform_id)
    {
        static $_map = false;
        if (!is_array($_map)) {
            $_map = [];
            foreach (self::get_list(true, true) as $_platform) {
                $_map[$_platform['id']] = $_platform['text'];
            }
        }
        return isset($_map[$platform_id]) ? $_map[$platform_id] : '-';
    }
    public static function get_list($with_marketplace = true, $with_virtual = false)
    {
        static $db_list = [];
        $cache_key = (!!$with_marketplace ? 'T' : 'F') . (!!$with_virtual ? 'V' : 'L');
        if (!isset($db_list[$cache_key])) {
            $db_list[$cache_key] = [];
            $platform_query = \common\models\Platforms::find()->where(['status' => 1]);
            $filter = ['or', ['is_virtual' => 0, 'is_marketplace' => 0]];
            $order = ['if(is_default=1,0,1)' => SORT_ASC, 'is_marketplace' => SORT_ASC, 'is_virtual' => SORT_ASC];
            if ($with_marketplace) {
                $filter[] = ['and', ['is_virtual' => 0, 'is_marketplace' => 1]];
            }
            if ($with_virtual) {
                $filter[] = ['and', ['is_virtual' => 1, 'is_marketplace' => 0]];
            }
            global $login_id;
            if (defined('TABLE_ADMIN') && false === \common\helpers\Acl::rule(['SUPERUSER']) && $login_id > 0) {
                $ids = [];
                $platforms = \common\models\Admin_Platforms::find()->where(['admin_id' => $login_id])->as_array()->all();
                foreach ($platforms as $platform) {
                    $ids[] = $platform['platform_id'];
                }
                $ids[] = 0;
                $platform_query->and_where(['in', 'platform_id', $ids]);
            }
            $platform_query->order_by(array_merge($order, ['sort_order' => SORT_ASC, 'platform_name' => SORT_ASC]));
            $get_list_r = $platform_query->and_filter_where($filter)->all();
            if ($get_list_r) {
                $is_default_in_list = false;
                foreach ($get_list_r as $_list) {
                    $db_list[$cache_key][] = ['id' => $_list['platform_id'], 'text' => $_list['platform_name'], 'is_virtual' => $_list['is_virtual'], 'is_marketplace' => $_list['is_marketplace'], 'is_default' => !!$_list['is_default'], 'need_login' => $_list['need_login'], 'platform_url' => $_list['platform_url']];
                    if (!!$_list['is_default']) {
                        $is_default_in_list = true;
                    }
                }
                if ($is_default_in_list === false) {
                    foreach ($db_list[$cache_key] as $key => $value) {
                        $db_list[$cache_key][$key]['is_default'] = true;
                        break;
                    }
                }
            }
        }
        return $db_list[$cache_key];
    }
    /**
     * @return platform_config
     */
    public function get_config($id)
    {
        if (!isset($this->config_pool[(int) $id])) {
            $this->config_pool[(int) $id] = new platform_config((int) $id);
            if (!$this->config_pool[(int) $id]->get_id()) {
                $this->config_pool[(int) $id] = new platform_config(platform::default_id());
            }
        }
        return $this->config_pool[(int) $id];
    }
    /**
     * @return platform_config
     */
    public function config($id = null)
    {
        if (is_numeric($id)) {
            $this->active_config_id = false;
            foreach (platform::get_list(true, true) as $check_id) {
                if ($check_id['id'] == $id) {
                    $this->active_config_id = (int) $id;
                    break;
                }
            }
        }
        if (!is_numeric($this->active_config_id)) {
            $this->active_config_id = platform::current_id();
        }
        if (!isset($this->config_pool[$this->active_config_id])) {
            $this->config_pool[$this->active_config_id] = new platform_config($this->active_config_id);
        }
        return $this->config_pool[$this->active_config_id];
    }
    public static function get_products_assign_list()
    {
        return self::get_list();
    }
    public static function get_categories_assign_list()
    {
        return self::get_list();
    }
    public static function real_default_id()
    {
        $default_platform = \common\models\Platforms::find_one(['is_default' => 1]);
        return $default_platform->platform_id ?? 0;
    }
    public static function default_id()
    {
        $default_id = 0;
        $platforms = self::get_list();
        foreach ($platforms as $platform) {
            if ($platform['is_default']) {
                $default_id = (int) $platform['id'];
                break;
            }
        }
        return $default_id;
    }
    public static function active_id()
    {
        $platforms = self::get_list();
        return count($platforms) > 1 && defined('PLATFORM_ID') && PLATFORM_ID > 0 ? (int) PLATFORM_ID : 0;
    }
    public static function current_id()
    {
        return defined('PLATFORM_ID') && PLATFORM_ID > 0 ? (int) PLATFORM_ID : self::first_id();
    }
    public static function is_multi($with_marketplace = true, $with_virtual = false)
    {
        $platforms = self::get_list($with_marketplace, $with_virtual);
        return count($platforms) > 1;
    }
    public static function first_id()
    {
        $platforms = self::get_list(false);
        return count($platforms) > 0 ? $platforms[0]['id'] : 0;
    }
    public static function valid_id($id)
    {
        $is_valid = false;
        $platforms = self::get_list(false);
        foreach ($platforms as $platform) {
            if ($id == $platform['id']) {
                $is_valid = true;
            }
        }
        if ($is_valid) {
            return $id;
        } else {
            return self::current_id();
        }
    }
    public static function country($id)
    {
        $platform_address = Platforms_Address_Book::find()->where(['platform_id' => $id])->with('country')->one();
        if ($platform_address instanceof Platforms_Address_Book) {
            return $platform_address->country;
        }
    }
}