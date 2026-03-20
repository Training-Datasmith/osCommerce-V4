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
namespace common\components\behaviors;

use yii\base\Behavior;
use yii\db\Active_Record;
class Reset_Cache_Behavior extends Behavior
{
    public $cache_id;
    public function events()
    {
        return [Active_Record::EVENT_AFTER_INSERT => 'deleteCache', Active_Record::EVENT_AFTER_UPDATE => 'deleteCache', Active_Record::EVENT_AFTER_DELETE => 'deleteCache'];
    }
    public function delete_cache()
    {
        foreach ($this->cache_id as $id) {
            \Yii::$app->cache->delete($id);
        }
    }
}