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
class J_Query_Query_Builder_Asset extends Asset_Bundle
{
    ///https://querybuilder.js.org/
    public $base_path = '@webroot';
    public $base_url = '@web';
    public $css = ['plugins/jQuery-QueryBuilder/css/query-builder.default.min.css'];
    public $js = [
        'plugins/jQuery-QueryBuilder/js/query-builder.standalone.js',
        //query-builder.min.js',
        'plugins/jQuery-QueryBuilder/js/sql-parser.js',
    ];
}