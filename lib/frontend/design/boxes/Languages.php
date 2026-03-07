<?php

declare(strict_types=1);
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

namespace frontend\design\boxes;

use frontend\design\IncludeTpl;
use Yii;
use yii\base\Widget;
use yii\helpers\Html;

class Languages extends Widget
{
    public $params;
    public $settings;

    public function init()
    {
        parent::init();
    }

    public function run()
    {
        global $lng, $request_type, $languages_id;

        $languages = [];

        if (is_array($lng->catalog_languages)) {
            foreach ($lng->catalog_languages as $key => $value) {
                if (!in_array($value['code'], $lng->paltform_languages)) {
                    continue;
                }

                $link = tep_href_link(Yii::$app->controller->getRoute(), \common\helpers\Output::get_all_get_params(['language', 'currency']) . 'language=' . $key, $request_type);

                $languages[] = [
                    'image' => Html::img(DIR_WS_ICONS . $value['image'], ['width' => 24, 'height' => 16, 'class' => 'language-icon' , 'alt' => $value['name'], 'title' => $value['name']]),
                    'name' => $value['name'],
                    'link' => $link,
                    'id' => $value['id'],
                    'key' => $key,
                ];
            }
        }

        return IncludeTpl::widget(['file' => 'boxes/languages.tpl', 'params' => [
            'languages' => $languages,
            'languages_id' => $languages_id,
            'settings' => $this->settings,
        ]]);
    }
}
