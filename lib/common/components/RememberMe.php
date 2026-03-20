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
namespace common\components;

/**
 * seems YII2 doesn't provide a way to change user's auth_key for autologin
 * (saved only once during customer registration .... https://yii2-framework.readthedocs.io/en/latest/guide/security-authentication/ )
 */
class Remember_Me extends \yii\web\User
{
    public $auto_login_duration = 0;
    protected function remove_identity_cookie()
    {
        parent::remove_identity_cookie();
        try {
            $user = $this->get_identity();
            if ($user) {
                $user->auth_key = \Yii::$app->security->generate_random_string();
                $user->save(false);
            }
        } catch (\Exception $ex) {
            \Yii::warning(' #### ' . print_r($ex->get_message(), true), 'TLDEBUG');
        }
    }
    public function logout($destroy_session = true)
    {
        //disable autologin (everywhere after log out
        try {
            $user = $this->get_identity();
        } catch (\Exception $ex) {
            \Yii::warning(' #### ' . print_r($ex->get_message(), true), 'TLDEBUG');
        }
        if (parent::logout($destroy_session)) {
            //parent::removeIdentityCookie();
            if ($user) {
                try {
                    $user->auth_key = \Yii::$app->security->generate_random_string();
                    $user->save(false);
                } catch (\Exception $ex) {
                    \Yii::warning(' #### ' . print_r($ex->get_message(), true), 'TLDEBUG');
                }
            }
        }
    }
}