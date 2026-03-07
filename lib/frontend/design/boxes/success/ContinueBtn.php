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

namespace frontend\design\boxes\success;

use frontend\design\IncludeTpl;
use yii\base\Widget;

class ContinueBtn extends Widget
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
        return IncludeTpl::widget(['file' => 'boxes/success/continue-btn.tpl', 'params' => ['link' => tep_href_link('index')]]);
    }
}
