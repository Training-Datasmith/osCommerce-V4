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
namespace backend\controllers;

use Yii;
class Popups_Controller extends Sceleton
{
    public function action_editor()
    {
        $this->layout = 'popup.tpl';
        return $this->render('editor.tpl', []);
    }
    public function action_price_formula_editor()
    {
        $formula_input = Yii::$app->request->get('formula_input', '');
        $formula_input = Yii::$app->request->post('formula_input', $formula_input);
        $allowed_params = Yii::$app->request->get('allowed_params', '');
        $allowed_params = Yii::$app->request->post('allowed_params', $allowed_params);
        $allow_params = [
            //CODE => 'label',
            'PRICE' => 'PRICE',
            'DISCOUNT' => 'DISCOUNT',
            'SURCHARGE' => 'SURCHARGE',
        ];
        if (!empty($allowed_params)) {
            $allowed_params_array = explode(',', $allowed_params);
            foreach (array_keys($allow_params) as $key) {
                if (!in_array($key, $allowed_params_array)) {
                    unset($allow_params[$key]);
                }
            }
        }
        $this->layout = 'popup.tpl';
        return $this->render('price_formula.tpl', ['formula_input' => $formula_input, 'allowParams' => $allow_params]);
    }
}