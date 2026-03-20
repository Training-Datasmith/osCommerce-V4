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

class Admin_Box
{
    public static function build_navigation($last_element = '')
    {
        $path = [];
        $query_response = \common\models\Admin_Boxes::find_one(['title' => $last_element]);
        if (is_object($query_response)) {
            $box_id = $query_response->box_id;
            do {
                $query_response = \common\models\Admin_Boxes::find_one(['box_id' => $box_id]);
                if (is_object($query_response)) {
                    $path[] = $query_response->title;
                    $box_id = $query_response->parent_id;
                } else {
                    $box_id = 0;
                }
            } while ($box_id > 0);
        }
        $path = array_reverse($path);
        return $path;
    }
    public static function get_data($id = '')
    {
        return \common\models\Admin::find()->select(['firstname' => 'admin_firstname', 'lastname' => 'admin_lastname', 'emai' => 'admin_email_address', 'avatar' => 'avatar'])->where(['admin_id' => (int) $id])->as_array()->one();
    }
}