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
namespace backend\models\EP\Provider\Magento\helpers;

class Image_Source
{
    private $config;
    private static $_resource;
    private $dir;
    private $dir_import;
    private function __construct($config)
    {
        $this->config = $config['media'];
        $this->config['path'] = $config['client']['location'] . $this->config['path'];
        $this->dir = \common\classes\Images::get_fs_catalog_images_path();
        $this->dir_import = $this->dir . 'import' . DIRECTORY_SEPARATOR;
        if (!is_dir($this->dir_import && !is_writable($this->dir_import))) {
            $this->dir_import = $this->dir;
        }
    }
    public static function get_instance($config)
    {
        if (!self::$_resource instanceof self) {
            self::$_resource = new self($config);
        }
        return self::$_resource;
    }
    public function load_resource($source, $owner = 'product')
    {
        $remote_source = $source;
        $local_source = pathinfo($remote_source, PATHINFO_BASENAME);
        if ($owner == 'category') {
            if (!$this->source_exist($local_source)) {
                $image = file_get_contents($this->config['path'] . $remote_source);
                try {
                    file_put_contents($this->dir . $local_source, $image);
                } catch (\Exception $e) {
                    throw new \Exception('Error saving category media file');
                }
            }
            if (!$this->source_exist($local_source)) {
                return false;
            }
            return $local_source;
        } elseif ($owner == 'product') {
            if (!$this->source_exist($local_source)) {
                $image = file_get_contents($remote_source);
                try {
                    file_put_contents($this->dir_import . $local_source, $image);
                } catch (\Exception $e) {
                    throw new \Exception('Error saving product media file');
                }
            }
            if (!$this->source_exist($local_source)) {
                return false;
            }
            return $this->dir_import . $local_source;
        }
        return false;
    }
    public function source_exist($source)
    {
        if (file_exists($this->dir . $source) || file_exists($this->dir_import . $source)) {
            return true;
        }
        return false;
    }
    public function save_resource()
    {
    }
}