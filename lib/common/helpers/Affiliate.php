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

class Affiliate
{
    public static function is_logged()
    {
        return tep_session_is_registered('login_affiliate') && \common\helpers\Acl::check_extension_allowed('Affiliate');
    }
    public static function id()
    {
        return isset($_SESSION['affiliate_ref']) && \common\helpers\Acl::check_extension_allowed('Affiliate') ? (int) $_SESSION['affiliate_ref'] : 0;
    }
    public static function where($alias_table = '', $insert_str_before = ' ')
    {
        if (!empty($alias)) {
            $alias .= '.';
        }
        return $insert_str_before . $alias_table . 'affiliate_id = ' . self::id();
    }
    public static function where_if_exists($alias_table = '', $insert_str_before = ' ')
    {
        return self::is_logged() ? self::where($alias_table, $insert_str_before) : '';
    }
}