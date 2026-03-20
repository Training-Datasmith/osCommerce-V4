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
class Owner extends Widget
{
    public $manager;
    public $name;
    public $current_current;
    public $cancel;
    public $redirect;
    public $changes_list;
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        return $this->render('owner', ['manager' => $this->manager, 'currentCurrent' => $this->current_current, 'name' => $this->name, 'cancel' => $this->cancel, 'redirect' => $this->redirect, 'changesList' => $this->changes_list]);
    }
}