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

class Seo_Meta_Format_Array implements Seo_Meta_Format_Interface
{
    protected $keys = [];
    public function __construct()
    {
    }
    public function own_meta_title()
    {
        return isset($this->keys['META_TITLE']) ? $this->keys['META_TITLE'] : '';
    }
    public function own_meta_description()
    {
        return $this->get_meta_format_key('META_DESCRIPTION');
    }
    public function get_meta_format_key($key)
    {
        return isset($this->keys[$key]) ? $this->keys[$key] : '';
    }
}