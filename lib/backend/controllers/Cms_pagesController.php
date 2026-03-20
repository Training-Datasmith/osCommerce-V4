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
class Cms_pages_Controller extends Sceleton
{
    public function action_index()
    {
        $this->selected_menu = ['cms', 'cms_pages'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('cms_pages/index'), 'title' => HEADING_TITLE];
        $this->view->heading_title = HEADING_TITLE;
        return $this->render('index');
    }
}