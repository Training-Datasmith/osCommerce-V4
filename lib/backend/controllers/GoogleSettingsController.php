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
class Google_Settings_Controller extends Sceleton
{
    public $acl = ['TEXT_SETTINGS', 'BOX_GOOGLE_SETTINGS', 'BOX_GOOGLE_MAIN_SETTINGS'];
    public function __construct($id, $module)
    {
        parent::__construct($id, $module);
    }
    public function action_index()
    {
        $this->selected_menu = ['settings', 'google-settings', 'google-settings'];
        $this->navigation[] = ['link' => \Yii::$app->url_manager->create_url('google-settings/index'), 'title' => BOX_GOOGLE_MAIN_SETTINGS];
        $this->view->heading_title = BOX_GOOGLE_MAIN_SETTINGS;
        $google_tools = new \common\components\Google_Tools();
        $selected = null;
        $messages = [];
        if (Yii::$app->request->is_post) {
            $provider = $google_tools->get_provider(Yii::$app->request->post('provider'));
            if ($provider) {
                $platform_id = (int) Yii::$app->request->post('platform_id');
                if ($google_tools->update_provider_config($provider, Yii::$app->request->post($provider->get_class_name()), $platform_id)) {
                    $messages[] = ['type' => 'alert-success', 'info' => $provider->get_name() . ' saved successfully'];
                } else {
                    $messages[] = ['type' => 'alert-danger', 'info' => $provider->get_name() . ' error'];
                }
            }
        }
        $platforms = [];
        $platforms[] = ['id' => 0, 'text' => defined('TEXT_DEFAULT') ? TEXT_DEFAULT : 'Default'];
        $platforms = array_merge($platforms, \common\classes\platform::get_list(false, true));
        $_provider = $google_tools->get_captcha_provider();
        $_provider->platforms = $platforms;
        $providers = ['independed' => [$google_tools->get_map_provider()], 'platformed' => [$_provider], 'selected' => $selected];
        unset($platforms[0]);
        foreach ([$google_tools->get_analytics_provider()] as $_provider) {
            $_provider->platforms = $platforms;
            $providers['platformed'][] = $_provider;
        }
        return $this->render('index', ['providers' => $providers, 'messages' => $messages, 'platforms' => $platforms]);
    }
}