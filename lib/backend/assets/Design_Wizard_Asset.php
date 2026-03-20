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
class Design_Wizard_Asset extends Asset_Bundle
{
    public $base_path = '@webroot';
    public $base_url = '@web/themes/basic';
    public $css = ['css/design-wizard.css'];
    public $js = [];
}