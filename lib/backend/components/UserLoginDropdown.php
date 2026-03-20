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
namespace backend\components;

use yii\base\Widget;
class User_Login_Dropdown extends Widget
{
    public $admin_fullname = '';
    public $admin_avatar = '';
    public function run()
    {
        $check_admin_query = tep_db_query('select * from ' . TABLE_ADMIN . ' where admin_id = ' . (int) tep_session_var('login_id') . '');
        $check_admin = tep_db_fetch_array($check_admin_query);
        $this->admin_fullname = $check_admin['admin_firstname'] . ' ' . $check_admin['admin_lastname'];
        $this->admin_avatar = '<span class="avatar' . (@get_image_size(DIR_FS_CATALOG_IMAGES . $check_admin['avatar']) > 0 ? '' : ' avatar_noimg') . '">' . (@get_image_size(DIR_FS_CATALOG_IMAGES . $check_admin['avatar']) > 0 ? tep_image(DIR_WS_CATALOG_IMAGES . $check_admin['avatar'], $check_admin['admin_firstname'] . ' ' . $check_admin['admin_lastname']) : '<i class="icon-user"></i>') . '</span>';
        return $this->render('UserLoginDropdown', ['context' => $this]);
    }
}