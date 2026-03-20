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
class Breadcrumbs extends Widget
{
    public $navigation = [];
    public $top_buttons = [];
    public function run()
    {
        if (isset(\Yii::$app->controller->navigation)) {
            $this->navigation = \Yii::$app->controller->navigation;
        }
        if (isset(\Yii::$app->controller->top_buttons)) {
            $this->top_buttons = \Yii::$app->controller->top_buttons;
        }
        foreach (\common\helpers\Hooks::get_list('components/breadcrumbs/before-render') as $filename) {
            include $filename;
        }
        return $this->render('Breadcrumbs', ['context' => $this]);
    }
}