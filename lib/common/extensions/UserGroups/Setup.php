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
namespace common\extensions\User_Groups;

class Setup extends \common\classes\modules\Setup_Extensions
{
    public static function get_admin_hooks()
    {
        return [['page_name' => 'banner_manager/banneredit'], ['page_name' => 'banner_manager/banneredit', 'page_area' => 'platform-table-heading-cell'], ['page_name' => 'banner_manager/banneredit', 'page_area' => 'platform-table-cell'], ['page_name' => 'banner_manager/submit'], ['page_name' => 'box/banner'], ['page_name' => 'box/block/hide-widget'], ['page_name' => 'design/box-edit', 'page_area' => 'hide-widget']];
    }
    public static function get_translation_array()
    {
        return ['extensions/user-groups' => ['USER_GROUPS' => 'User Groups']];
    }
    public static function get_version_history()
    {
        return ['1.0.1' => 'Added user groups to widgets', '1.0.1' => 'Added user groups to banner', '1.0.0' => 'User groups'];
    }
    public static function install($platform_id, $migrate)
    {
        if ($migrate->is_table_exists('banners_to_platform') && !$migrate->is_field_exists('user_groups', 'banners_to_platform')) {
            $migrate->add_column('banners_to_platform', 'user_groups', $migrate->string(255)->not_null()->default_value('#0#'));
        }
    }
    public static function remove($platform_id, $migrate, $drop = false)
    {
        if ($drop && $migrate->is_table_exists('banners_to_platform') && $migrate->is_field_exists('user_groups', 'banners_to_platform')) {
            $migrate->drop_column('banners_to_platform', 'user_groups');
        }
    }
    public static function get_drop_databases_array()
    {
        return [];
    }
    public static function get_configure_keys()
    {
        return ['USER_GROUPS_WITH_BANNERS' => ['title' => 'Use User Groups on banners', 'description' => '', 'value' => 'false', 'set_function' => 'tep_cfg_select_option(array(\'true\', \'false\'), ']];
    }
}