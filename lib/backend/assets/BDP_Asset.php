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
class Bdp_Asset extends Asset_Bundle
{
    public $base_path = '@webroot';
    public $base_url = '@web';
    public $css = ['plugins/bootstrap-datepicker/css/bootstrap-datepicker.min.css', 'plugins/multiple-select/multiple-select.css'];
    public $js = ['plugins/bootstrap-datepicker/js/bootstrap-datepicker.min.js', 'plugins/multiple-select/multiple-select.js'];
    public $js_options = ['position' => View::POS_HEAD];
}