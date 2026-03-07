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

use frontend\design\Info;
use yii\base\Widget;

class StorePhone extends Widget
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
        if ($this->settings[0]['pdf']) {
            $data = Info::platformData($this->params['platform_id']);
        } else {
            $data = Info::platformData();
        }
        return $data['telephone'];
    }
}
