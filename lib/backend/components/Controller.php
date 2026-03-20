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
/**
 * Controller is the customized base controller class.
 * All controller classes for this application should extend from this base class.
 */
class Controller extends C_Controller
{
    /**
     * @var array the breadcrumbs of the current page.
     */
    public $navigation = [];
    /**
     * @var stdClass the variables for smarty.
     */
    public $view;
    /**
     * Selected items in menu
     * @var array
     */
    public $selected_menu = [];
    public function __construct($id, $module = null)
    {
        $this->view = new stdClass();
        parent::__construct($id, $module);
    }
}