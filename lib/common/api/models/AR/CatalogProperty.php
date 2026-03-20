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
namespace common\api\models\AR;

use common\api\models\AR\Catalog_Property\Property_Description;
use yii\db\Expression;
class Catalog_Property extends Ep_Map
{
    protected $child_collections = ['descriptions' => []];
    public static function table_name()
    {
        return TABLE_PROPERTIES;
    }
    public static function primary_key()
    {
        return ['properties_id'];
    }
    public function init_collection_by_lookup_key_descriptions($lookup_keys)
    {
        $load_all = in_array('*', $lookup_keys);
        foreach (Property_Description::get_all_key_codes() as $key_code => $lookup_pk) {
            $this->child_collections['descriptions'][$key_code] = null;
            if (is_null($this->properties_id)) {
                $this->child_collections['descriptions'][$key_code] = new Property_Description($lookup_pk);
            } elseif ($load_all || in_array($key_code, $lookup_keys)) {
                if (!isset($this->child_collections['descriptions'][$key_code])) {
                    $lookup_pk['properties_id'] = $this->properties_id;
                    $this->child_collections['descriptions'][$key_code] = Property_Description::find_one($lookup_pk);
                    if (!is_object($this->child_collections['descriptions'][$key_code])) {
                        $this->child_collections['descriptions'][$key_code] = new Property_Description($lookup_pk);
                    }
                }
            }
        }
        return $this->child_collections['descriptions'];
    }
    public function before_save($insert)
    {
        if (!parent::before_save($insert)) {
            return false;
        }
        if ($insert) {
            if (empty($this->date_added)) {
                $this->date_added = new Expression('NOW()');
            }
        } else if ($this->is_modified()) {
            $this->last_modified = new Expression('NOW()');
        }
        return true;
    }
}