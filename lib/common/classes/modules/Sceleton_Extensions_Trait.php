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

trait Sceleton_Extensions_Trait
{
    private $extension_dir;
    private $extension_class;
    private $view_dir;
    private $controller_short_class;
    protected $ext;
    protected function init_extension_class()
    {
        $ref = new \ReflectionClass(get_class($this));
        $this->controller_short_class = $ref->get_short_name();
        $base_dir = dirname($ref->get_file_name(), 2);
        // backend or frontend
        $this->extension_dir = dirname($base_dir);
        \common\helpers\Assert::assert(basename(dirname($this->extension_dir)) == 'extensions', 'Unexpected controller path');
        $this->view_dir = $base_dir . '/views/';
        $this->extension_class = basename($this->extension_dir);
    }
    public function get_view_path()
    {
        return $this->view_dir . DIRECTORY_SEPARATOR . $this->id;
    }
    private function _get_acl(string $action_name, string $controller, bool $default)
    {
        $res = $this->ext::get_acl($action_name, $controller, false);
        if (empty($res)) {
            if ($pos = strpos($action_name, '-')) {
                $res = $this->ext::get_acl(substr($action_name, 0, $pos), $controller, $default);
            } elseif ($default) {
                $res = $this->ext::get_acl($action_name, $controller, true);
            }
        }
        return $res;
    }
    protected function get_acl(string $action_name = '')
    {
        $res = $this->_get_acl($action_name, $this->id, false);
        if (empty($res)) {
            $res = $this->_get_acl($action_name, $this->controller_short_class, true);
            //alternative controller name
        }
        return $res;
    }
    private function init_construct()
    {
        $this->init_extension_class();
        $this->ext = \common\helpers\Acl::check_extension_allowed($this->extension_class);
        if (!$this->ext) {
            throw new \yii\web\Not_Found_Http_Exception("{$this->extension_class} extension is not allowed.");
        }
        $this->ext::init_translation('init_controller');
    }
}