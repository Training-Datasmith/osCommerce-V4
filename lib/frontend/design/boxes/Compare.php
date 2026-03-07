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

namespace frontend\design\boxes;

use frontend\design\Info;
use Yii;
use yii\base\Widget;

class Compare extends Widget
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
        Info::addJsData(['productListings' => [
            'compare' => [
                'compareUrl' => Yii::$app->urlManager->createUrl('catalog/compare'),
                //'byCategory' => $_SESSION['compare'],
            ],
        ]]);
        Info::addJsData(['tr' => [
            'TEXT_CLEANING_ALL' => TEXT_CLEANING_ALL,
            'BOX_HEADING_COMPARE_LIST' => BOX_HEADING_COMPARE_LIST,
        ]]);

        return '<div class="compare-list"></div><script>tl(function(){$(".main-content").trigger("updateContent")})</script>';
    }
}
