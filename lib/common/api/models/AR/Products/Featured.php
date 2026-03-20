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
namespace common\api\models\AR\Products;

use common\api\models\AR\Ep_Map;
class Featured extends Ep_Map
{
    public static function table_name()
    {
        return 'featured';
    }
    public static function primary_key()
    {
        return ['featured_id'];
    }
    public function parent_ep_map(Ep_Map $parent_object)
    {
        $this->products_id = $parent_object->products_id;
        parent::parent_ep_map($parent_object);
    }
    public function match_indexed_value(Ep_Map $imported_object)
    {
        $this->pending_removal = false;
        return true;
    }
    public function before_save($insert)
    {
        if ($insert && empty($this->featured_date_added)) {
            $this->featured_date_added = new \yii\db\Expression('NOW()');
        }
        return parent::before_save($insert);
    }
}