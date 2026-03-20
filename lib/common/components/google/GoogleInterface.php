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
namespace common\components\google;

interface Google_Interface
{
    public function get_params();
    //config
    public function render();
    // render in admin
    public function loaded();
    //loaded post config before save
    public function render_widget();
    // render at frontend
}