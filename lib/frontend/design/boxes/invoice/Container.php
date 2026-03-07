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

namespace frontend\design\boxes\invoice;

use frontend\design\Block;
use yii\base\Widget;

class Container extends Widget
{
    public $settings;
    public $params;
    public $id;

    public function init()
    {
        parent::init();
    }

    public function run()
    {

        return Block::widget(['name' => 'block-' . $this->id, 'params' => ['params' => $this->params]]);

    }
}
