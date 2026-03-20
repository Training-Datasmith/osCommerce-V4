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
namespace common\classes;

use common\models\Platforms_Settings;
class platform_settings
{
    protected $platform_settings;
    public function __construct($id)
    {
        $this->platform_settings = Platforms_Settings::find_one([$id]);
        if (!$this->platform_settings) {
            $this->platform_settings = Platforms_Settings::find_one([platform::default_id()]);
        }
    }
    public function get_platform_to_description()
    {
        if ($this->platform_settings->use_own_descriptions) {
            return $this->platform_settings->platform_id;
        } else if ($this->platform_settings->use_owner_descriptions) {
            return $this->platform_settings->use_owner_descriptions;
        } else {
            return platform::default_id();
        }
    }
}