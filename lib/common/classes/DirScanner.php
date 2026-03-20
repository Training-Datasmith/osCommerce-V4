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
namespace common\classes;

class Dir_Scanner
{
    private $target_dir;
    private $checksum_list;
    public function __construct($Dir)
    {
        $Dir = str_replace(DIRECTORY_SEPARATOR, '/', $Dir);
        // for win
        $this->target_dir = $Dir;
    }
    private function start($dir)
    {
        $full_array = glob($dir . '/*');
        foreach ($full_array as $item) {
            $item = str_replace(DIRECTORY_SEPARATOR, '/', $item);
            // for win
            if (is_dir($item)) {
                $path = str_replace([$this->target_dir . '/', '/'], ['', '|'], $item);
                $this->checksum_list[$path] = '';
                $this->start($item);
            } elseif (is_file($item)) {
                $crc = crc32(file_get_contents($item));
                $path = str_replace([$this->target_dir . '/', '/'], ['', '|'], $item);
                $this->checksum_list[$path] = $crc;
            }
        }
    }
    public function run()
    {
        $this->checksum_list = [];
        if (is_dir($this->target_dir)) {
            $this->start($this->target_dir);
        }
        return $this->checksum_list;
    }
}