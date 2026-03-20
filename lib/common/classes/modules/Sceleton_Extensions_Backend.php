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
namespace common\classes\modules;

abstract class Sceleton_Extensions_Backend extends \backend\controllers\Sceleton
{
    use Sceleton_Extensions_Trait;
    public function __construct($id, $module = null, $config = [])
    {
        $this->init_construct();
        parent::__construct($id, $module, $config);
    }
    public function before_action($action)
    {
        if ($action instanceof \yii\base\Action) {
            $action_acl = self::get_acl($action->id);
            if (!empty($action_acl)) {
                $this->acl = $action_acl;
                \common\helpers\Acl::check_access($this->acl);
            }
        }
        \common\helpers\Assert::is_not_empty($this->acl, 'Backend controller without acl');
        return parent::before_action($action);
    }
}