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
namespace common\extensions\Modules_Visibility;

class Setup extends \common\classes\modules\Setup_Extensions
{
    public static function get_description()
    {
        return 'This extension allows the administrator to manage order structure module visibility. ';
    }
    public static function get_version_history()
    {
        return ['1.0.0' => 'Changed as AppShop extension'];
    }
}