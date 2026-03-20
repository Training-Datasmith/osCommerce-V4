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
namespace backend\assets;

use yii\web\Asset_Bundle;
use yii\web\View;
class Select_Products_Asset extends Asset_Bundle
{
    public $base_path = '@webroot';
    public $base_url = '@web';
    public $css = ['themes/basic/css/select-products.css'];
    public $js = ['plugins/dragselect.js', 'themes/basic/js/select-products.js'];
    public $js_options = ['position' => View::POS_HEAD];
}