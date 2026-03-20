<?php

use yii\helpers\Html;
use yii\helpers\Url;
/* @var $this yii\web\View */
/* @var $user common\models\User */
$reset_link = Yii::$app->url_manager->create_absolute_url(['site/reset-password', 'token' => $user->password_reset_token]);
?>
<div class="password-reset">
<p style="background: #39a5e7; padding: 5px 0px; text-align: center;"><img src="<?php 
echo Url::to('@web/images/logo-2.png', 'http');
?>" alt=""></p>
    <p style="text-align: center; font-family: tahoma; font-size: 30px; color: #424242">Hello <?php 
echo Html::encode($user->username);
?>,</p>

    <p>Follow the link below to reset your password:</p>

    <p><?php 
echo Html::a(Html::encode($reset_link), $reset_link);
?></p>
	Best regards, <br />The MyPropertyPoral team.<p style="text-align: center; background: #051930; height: 3px; padding: 0;">&nbsp;</p>
</div>
