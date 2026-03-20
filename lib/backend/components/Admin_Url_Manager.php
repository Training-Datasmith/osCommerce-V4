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
namespace app\components;

use yii\web\Url_Manager;
class Admin_Url_Manager extends Url_Manager
{
    public function create_absolute_url($params, $scheme = null, $front = false)
    {
        if ($front && !empty($params['platform_id'])) {
            // save current params
            $host_info = $this->get_host_info();
            $base_url = $this->get_base_url();
            $pc = new \common\classes\platform_config($params['platform_id']);
            $parsed = parse_url((string) $pc->get_catalog_base_url(true, false));
            $this->set_host_info($parsed['scheme'] . '://' . $parsed['host'] . (!empty($parsed['port']) && !in_array($parsed['port'], ['80', '443']) ? ':' . $parsed['port'] : ''));
            $this->set_base_url(rtrim($parsed['path']));
            // restore params
            $ret = parent::create_absolute_url($params, $scheme);
            $this->set_host_info($host_info);
            $this->set_base_url($base_url);
        } else {
            $ret = parent::create_absolute_url($params, $scheme);
        }
        return $ret;
    }
}