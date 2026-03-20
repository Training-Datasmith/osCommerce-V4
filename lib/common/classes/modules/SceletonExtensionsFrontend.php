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
namespace common\classes\modules;

abstract class Sceleton_Extensions_Frontend extends \frontend\controllers\Sceleton
{
    use Sceleton_Extensions_Trait;
    public function __construct($id, $module = null)
    {
        $this->init_construct();
        parent::__construct($id, $module);
    }
}