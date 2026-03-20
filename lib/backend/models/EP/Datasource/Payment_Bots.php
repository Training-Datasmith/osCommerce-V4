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
namespace backend\models\EP\Datasource;

use backend\models\EP\Datasource_Base;
class Payment_Bots extends Datasource_Base
{
    public function get_name()
    {
        return 'Payment Bots';
    }
    public function prepare_config_for_view($config_array)
    {
        $config_array['platforms_list'] = \common\classes\platform::get_list(false);
        $manager = \common\services\Order_Manager::load_manager(new \common\classes\shopping_cart());
        $all_modules = [];
        $builder = new \common\classes\modules\Module_Builder($manager);
        foreach ($config_array['platforms_list'] as &$platform) {
            $installed = (new \yii\db\Query())->select('configuration_value')->from('platforms_configuration')->where(['configuration_key' => 'MODULE_PAYMENT_INSTALLED', 'platform_id' => $platform['id']])->one();
            $platform['modules'] = [];
            if ($installed) {
                $modules = explode(';', $installed['configuration_value']);
                foreach ($modules as $module) {
                    $class = pathinfo($module, PATHINFO_FILENAME);
                    if (!isset($all_modules[$class]) && class_exists("\\common\\modules\\orderPayment\\{$class}")) {
                        $all_modules[$class] = $builder(['class' => "\\common\\modules\\orderPayment\\{$class}"]);
                    }
                    if (is_object($all_modules[$class]) && $all_modules[$class] instanceof \common\classes\modules\Transaction_Search_Interface) {
                        $platform['modules'][$class] = ['class' => $class, 'title' => $all_modules[$class]->title, 'fields' => $all_modules[$class]->get_fields()];
                    }
                }
            }
        }
        return parent::prepare_config_for_view($config_array);
    }
    public function get_view_template()
    {
        return 'datasource/payments.tpl';
    }
}