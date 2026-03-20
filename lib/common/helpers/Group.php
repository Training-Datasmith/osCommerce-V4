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

class Group
{
    /**
     *
     * @staticvar array $groups
     * @staticvar array $e_groups
     * @param int $code
     * @return array
     */
    public static function get_customer_groups($code = '')
    {
        static $groups = false;
        static $e_groups = false;
        /** @var \common\extensions\UserGroups\UserGroups $ext */
        $ext = \common\helpers\Acl::check_extension_allowed('UserGroups', 'allowed');
        if (!$ext) {
            if (!is_array($groups)) {
                $groups = \common\models\Groups::find()->order_by('groups_name')->index_by('groups_id')->as_array()->all();
                if (!is_array($groups)) {
                    $groups = [];
                }
            }
            $ret = $groups;
        } else {
            if (!isset($e_groups[$code]) || !is_array($e_groups[$code])) {
                $e_groups = [];
                //php8
                $e_groups[$code] = $ext::get_groups_array($code);
                if (!is_array($e_groups[$code])) {
                    $e_groups[$code] = [];
                }
            }
            $ret = $e_groups[$code];
        }
        return $ret;
    }
    /**
     * uses cached get_customer_groups
     * @param int $code (type id - extra groups extension)
     * @return array (id=>name)
     */
    public static function get_customer_groups_list($code = '', $empty_string = false)
    {
        $response = [];
        if ($empty_string) {
            $response[0] = TEXT_MAIN;
        }
        return $response + \yii\helpers\Array_Helper::map(self::get_customer_groups($code), 'groups_id', 'groups_name');
    }
    /**
     * get group name by id
     * @param type $id
     * @return string
     */
    public static function get_user_group_name($id)
    {
        if ($id == 0) {
            $ret = TEXT_MAIN;
        } else {
            $ret = '';
            $group = \common\models\Groups::find_one($id);
            if ($group) {
                $ret = $group->groups_name;
            }
        }
        return $ret;
    }
    public static function is_tax_applicable($group_id = null)
    {
        if (!\common\helpers\Extensions::is_allowed('BusinessToBusiness')) {
            return true;
        }
        if (is_null($group_id)) {
            $group_id = (int) \Yii::$app->storage->get('customer_groups_id');
        }
        if ($group_id == 0) {
            return true;
        }
        return \common\helpers\Customer::check_customer_groups($group_id, 'groups_is_tax_applicable');
    }
}