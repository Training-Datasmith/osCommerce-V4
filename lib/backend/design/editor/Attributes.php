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
namespace backend\design\editor;

use yii\base\Widget;
class Attributes extends Widget
{
    public $attributes;
    public $attr_text;
    public $settings;
    public $complex = false;
    public function init()
    {
        parent::init();
        if (!$this->settings) {
            $this->settings['onchange'] = 'getDetails(this)';
        }
    }
    public function run()
    {
        return $this->render('attributes', ['attributes' => $this->attributes, 'attrText' => $this->attr_text, 'settings' => $this->settings, 'complex' => $this->complex]);
    }
}