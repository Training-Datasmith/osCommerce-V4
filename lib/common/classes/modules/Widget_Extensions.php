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

#[\Allow_Dynamic_Properties]
class Widget_Extensions extends \yii\base\Widget
{
    public $name;
    public $params;
    public $settings;
    public $id;
    public $ext_name = null;
    public $ext_dir = null;
    /**
     * @var \common\classes\modules\ModuleExtensions
     */
    public $ext_class = null;
    private static $ext = [];
    public function __construct($config = [])
    {
        parent::__construct($config);
        $ext = self::init_extension_class();
        $this->ext_name = $ext['name'];
        $this->ext_dir = $ext['dir'];
        $this->ext_class = $ext['class'];
        self::allowed();
        //init translations for settings
    }
    public function get_translation($key, $entity = '')
    {
        if (self::allowed()) {
            return $this->ext_class::get_translation_value($key, $entity);
        }
    }
    /**
     * For auto check into Acl::runExtensionWidget()
     * @return bool
     */
    public static function allowed()
    {
        return \common\helpers\Extensions::is_allowed(self::init_extension_class()['name']);
    }
    public function before_run()
    {
        return static::allowed() && parent::before_run();
    }
    private static function init_extension_class()
    {
        $called = get_called_class();
        if (!isset(self::$ext[$called])) {
            $ref = new \ReflectionClass($called);
            // find extension class
            $dir = dirname($ref->get_file_name());
            $count = 0;
            while (basename(dirname($dir)) != 'extensions') {
                $dir = dirname($dir);
                \common\helpers\Assert::assert(!empty($dir) && $dir != '/' && $count <= 4, "Can't find extension class. Current dir: {$dir}. Count: {$count}");
                \common\helpers\Assert::assert($count <= 4, "Can't find extension class. Directory nesting is too deeply. Current dir: {$dir}. Count: {$count}");
                $count++;
            }
            $ext_name = basename($dir);
            self::$ext[$called]['name'] = $ext_name;
            self::$ext[$called]['dir'] = $dir;
            self::$ext[$called]['class'] = "\\common\\extensions\\{$ext_name}\\{$ext_name}";
        }
        return self::$ext[$called];
    }
}