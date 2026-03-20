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

abstract class Module_Collection
{
    protected function get_all_modules()
    {
        return $this->include_modules;
    }
    public function get_module($class)
    {
        $modules = $this->get_all_modules();
        return is_object($modules[$class] ?? null) ? $modules[$class] : false;
    }
}