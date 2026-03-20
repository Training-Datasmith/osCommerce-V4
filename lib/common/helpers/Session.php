<?php

declare (strict_types=1);
/*
 * This file is part of osCommerce ecommerce platform.
 * osCommerce the ecommerce
 *
 * @link https://www.oscommerce.com
 * @copyright Copyright (c) 2005 Holbi Group Ltd
 *
 * Released under the GNU General Public License
 * For the full copyright and license information, please view the LICENSE.TXT file that was distributed with this source code.
 */
namespace common\helpers;

use Yii;
/**
 * Description of Session
 *
 * @author vlad
 */
class Session
{
    public static function get($key)
    {
        return static::get_session()->get($key);
    }
    public static function get_session()
    {
        if (method_exists(Yii::$app, 'getSession')) {
            return Yii::$app->get_session();
        } else {
            //console workaround
            $storage = Yii::$app->get('storage');
            if (is_object($storage)) {
                return $storage;
            } else {
                return Yii::$app->session;
            }
        }
    }
    public static function delete_customer_sessions($customer_id, $exclude_sid = '')
    {
        $whos_rows = \common\models\Whos_Online::find()->where(['customer_id' => $customer_id])->as_array()->all();
        foreach ($whos_rows as $whos) {
            if ($exclude_sid == $whos['session_id']) {
                continue;
            }
            if (STORE_SESSIONS == 'mysql') {
                $session_row = \common\models\Sessions::find()->where(['sesskey' => $whos['session_id']])->one();
                if ($session_row instanceof \common\models\Sessions) {
                    $session_row->delete();
                }
            } else {
                $file = tep_session_save_path() . '/' . $whos['session_id'];
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
        \common\models\Whos_Online::delete_all(['customer_id' => $customer_id]);
    }
}