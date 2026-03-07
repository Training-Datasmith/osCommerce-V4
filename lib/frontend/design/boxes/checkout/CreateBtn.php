<?php

declare(strict_types=1);
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

namespace frontend\design\boxes\checkout;

use frontend\design\IncludeTpl;
use Yii;
use yii\base\Widget;

class CreateBtn extends Widget
{
    public $file;
    public $params;
    public $settings;

    public function init()
    {
        parent::init();
    }

    public function run()
    {
        return IncludeTpl::widget(['file' => 'boxes/checkout/create-btn.tpl', 'params' => [
            'link' => Yii::$app->urlManager->createUrl([Yii::$app->controller->id, 'account' => 1]),
            'text' => TEXT_CREATE_ACCOUNT_DEFENETLY,
        ]]);
    }
}
