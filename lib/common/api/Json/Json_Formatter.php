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
namespace common\api\Json;

use Yii;
use yii\base\Base_Object;
use yii\httpclient\Formatter_Interface;
class Json_Formatter extends Base_Object implements Formatter_Interface
{
    public $charset = null;
    public $content_type = 'application/json';
    public function __construct($config = [])
    {
        $this->charset = trim((!isset($config['charset']) or !is_scalar($config['charset']) or trim($config['charset']) == '') ? Yii::$app->charset : $config['charset']);
        parent::__construct($config);
    }
    /**
     * @inheritdoc
     */
    public function format($json_array, $options = 0)
    {
        return json_encode($json_array, $options);
    }
}