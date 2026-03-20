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
namespace common\components\google\widgets;

class Captcha_Widget extends \yii\base\Widget
{
    public $public_key;
    public $private_key;
    public $version;
    public $owner;
    public $description;
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        return $this->render('captcha-config', ['publicKey' => $this->public_key, 'privateKey' => $this->private_key, 'owner' => $this->owner, 'version' => $this->version, 'description' => $this->description]);
    }
}