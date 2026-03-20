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
namespace common\api\models\AR\Manufacturer;

use common\api\models\AR\Ep_Map;
use common\helpers\Seo;
class Info extends Ep_Map
{
    protected $hide_fields = ['manufacturers_id', 'languages_id'];
    /**
     * @var EPMap
     */
    public $parent_object;
    public static function table_name()
    {
        return TABLE_MANUFACTURERS_INFO;
    }
    public static function primary_key()
    {
        return ['manufacturers_id', 'languages_id'];
    }
    public function parent_ep_map(Ep_Map $parent_object)
    {
        $this->parent_object = $parent_object;
        $this->manufacturers_id = $parent_object->manufacturers_id;
    }
    public static function get_all_key_codes()
    {
        $key_codes = [];
        foreach (\common\classes\language::get_all() as $lang) {
            $key_code = $lang['code'];
            $key_codes[$key_code] = ['manufacturers_id' => null, 'languages_id' => $lang['id']];
        }
        return $key_codes;
    }
    public function before_save($insert)
    {
        if (empty($this->manufacturers_seo_name) && is_object($this->parent_object)) {
            $this->manufacturers_seo_name = Seo::make_slug($this->parent_object->manufacturers_name);
        }
        return parent::before_save($insert);
    }
}