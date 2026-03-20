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

interface Seo_Meta_Format_Interface
{
    /**
     * Get page title tag, otherwise title will be calculated from meta const
     * db column overwrite_head_title_tag
     * @return string
     */
    public function own_meta_title();
    /**
     * Get page meta description tag, otherwise will be calculated from meta const.
     * db column overwrite_head_desc_tag
     * @return string
     */
    public function own_meta_description();
    /**
     * Get value of ##key## for meta const
     *
     * @param $key
     * @return mixed
     */
    public function get_meta_format_key($key);
}