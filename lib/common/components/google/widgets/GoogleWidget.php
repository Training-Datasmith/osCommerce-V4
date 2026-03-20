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

use common\classes\platform;
use common\components\Google_Tools;
use frontend\design\Info;
class Google_Widget extends \yii\base\Widget
{
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        if (Info::is_admin()) {
            return;
        }
        $provider = Google_Tools::instance()->get_modules_provider();
        $to_work = [];
        $priority = [];
        foreach ($provider->get_installed_modules(platform::current_id()) as $module) {
            $_pages = $module->get_available_pages();
            if (in_array('checkout', $_pages) && strtolower(str_replace('-', '', \Yii::$app->controller->id)) == 'checkout') {
                if (\Yii::$app->controller->action->id == 'success') {
                    $priority[$module->code] = $module->get_priority();
                    $to_work[$module->code] = $module;
                }
            } elseif (in_array(strtolower(str_replace('-', '', \Yii::$app->controller->id)), $_pages) || in_array('all', $_pages)) {
                $priority[$module->code] = $module->get_priority();
                $to_work[$module->code] = $module;
            }
        }
        if (is_array($priority)) {
            asort($priority);
            foreach ($priority as $module => $value) {
                echo $to_work[$module]->render_widget();
            }
        }
    }
}