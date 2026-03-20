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
namespace common\components\google\widgets;

use common\components\Google_Tools;
use Yii;
class Google_Verification
{
    public static function verify()
    {
        $module = Google_Tools::instance()->get_modules_provider()->get_active_by_code('verification', \common\classes\platform::current_id());
        if ($module) {
            Yii::$app->controller->get_view()->register_meta_tag(['name' => 'google-site-verification', 'content' => $module->config[$module->code]['fields'][0]['value']], 'google-site-verification');
        }
    }
}