<?php

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
declare (strict_types=1);
if (getenv('HTTP_HOST')) {
    $bootstrap = ['log', 'common\components\SessionFlow'];
} else {
    $bootstrap = [];
}
try {
    $paths = [dirname(__FILE__, 2) . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR, dirname(__FILE__, 2) . DIRECTORY_SEPARATOR . 'extensions' . DIRECTORY_SEPARATOR, dirname(__FILE__, 2) . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR];
    foreach (get_bootstrap_iterator($paths) as $class) {
        $bootstrap[] = $class;
    }
} catch (\Exception $e) {
    \Yii::error($e->get_message());
}
return $bootstrap;
/**
 * @param string $className
 * @return bool
 * @throws ReflectionException
 */
function is_yii_bootstrap(string $class_name)
{
    $cl = new \ReflectionClass($class_name);
    return in_array('yii\base\BootstrapInterface', $cl->get_interface_names(), true);
}
/**
 * @param array $paths
 * @return Generator
 * @throws ReflectionException
 */
function get_bootstrap_iterator(array $paths)
{
    foreach ($paths as $path) {
        foreach (get_files_iterator($path, '/Bootstrap\.php$/', 2) as $file) {
            $class_name = get_class_from_path($file->get_path_name());
            if (is_yii_bootstrap($class_name)) {
                yield $class_name;
            }
        }
    }
}
/**
 * @param string $path
 * @param string $maskRegExp
 * @param int $depth
 * @return RegexIterator
 */
function get_files_iterator(string $path, string $mask_reg_exp = '/.*/', int $depth = -1)
{
    $dir = new Recursive_Iterator_Iterator(new Recursive_Directory_Iterator($path));
    $dir->set_max_depth($depth);
    return new Regex_Iterator($dir, $mask_reg_exp);
}
/**
 * @param string $path
 * @return string
 */
function get_class_from_path(string $path): string
{
    return str_replace([dirname(__FILE__, 3) . DIRECTORY_SEPARATOR, '/', '.php'], ['', '\\', ''], $path);
}