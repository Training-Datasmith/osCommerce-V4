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
namespace backend\components;

use yii\base\Widget;
class Message_Popup extends Widget
{
    public const MESSAGE_TYPE_SUCCESS = 'success';
    public const MESSAGE_TYPE_WARNING = 'warning';
    public $message_type = 'success';
    public $heading = '';
    public $message = '';
    public $click_js = '';
    public function run()
    {
        return $this->render('MessagePopup', ['messageType' => $this->message_type, 'message' => $this->message, 'heading' => $this->heading, 'clickJs' => $this->click_js]);
    }
}