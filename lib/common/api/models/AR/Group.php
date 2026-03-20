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

use yii;
use yii\db\Expression;
use yii\db\Query;
class Group extends Ep_Map
{
    protected $hide_fields = ['image_active', 'image_inactive'];
    protected $child_collections = [];
    protected $indexed_collections = [];
    public static function table_name()
    {
        return TABLE_GROUPS;
    }
    public static function primary_key()
    {
        return ['groups_id'];
    }
    public function before_save($insert)
    {
        if ($insert) {
            //            Yii::$app->getDb()->createCommand("alter table " . self::tableName() . " change groups_id groups_id int(11)")->query();
            if (empty($this->date_added)) {
                $this->date_added = new Expression('NOW()');
            }
        }
        return parent::before_save($insert);
    }
    public function after_save($insert, $changed_attributes)
    {
        //      Yii::$app->getDb()->createCommand("alter table " . self::tableName() . " change groups_id groups_id int(11) auto_increment")->query();
        parent::after_save($insert, $changed_attributes);
    }
}