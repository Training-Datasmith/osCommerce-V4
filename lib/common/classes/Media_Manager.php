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

use yii\base\Component;
class Media_Manager extends Component
{
    protected $group_by_type = [];
    protected $previous_alias_map = [];
    protected $disable_url_type_alias = [];
    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->load_platform_settings();
    }
    public function get_url_types()
    {
        return ['/' => 'Site folder', '/images' => 'Images folder', '/themes' => 'Themes folder'];
    }
    public function load_platform_settings($platform_id = null)
    {
        if (empty($platform_id)) {
            $platform_id = \Yii::$app->get('platform')->config()->get_id();
        }
        $platform_config = \Yii::$app->get('platform')->config();
        $this->group_by_type = ['themes' => [], 'images' => []];
        // Don't use when run in console (e.g. PdfCatalogGen)
        if (\Yii::$app instanceof \yii\console\Application) {
            return;
        }
        $cdn_server = $platform_config->get_images_cdn_url();
        if (!empty($cdn_server)) {
            \Yii::set_alias('@webCatalogImages', $cdn_server);
        }
        $is_secure_request = \Yii::$app->request->get_is_secure_connection();
        foreach ($platform_config->get_additional_urls() as $additional_url) {
            if ($is_secure_request && $additional_url['ssl_enabled'] == 0) {
                continue;
            }
            if ($is_secure_request || $additional_url['ssl_enabled'] == 2) {
                $schema = 'https';
            } else {
                $schema = 'http';
            }
            $url = $schema . '://' . rtrim($additional_url['url'], '/') . '/';
            if ($additional_url['url_type'] == '/') {
                $this->group_by_type['themes'][] = $url . 'themes/';
                $this->group_by_type['images'][] = $url . DIR_WS_IMAGES;
            } else if ($additional_url['url_type'] == '/themes') {
                $this->group_by_type['themes'][] = $url;
            } else {
                $this->group_by_type[trim($additional_url['url_type'], '/')][] = $url;
            }
        }
        $this->previous_alias_map = [];
    }
    public function allow_url_type_alias($type, $flag)
    {
        if ($flag) {
            unset($this->disable_url_type_alias[$type]);
        } else {
            $this->disable_url_type_alias[$type] = $type;
        }
    }
    public function get_alias($alias)
    {
        if (count($this->previous_alias_map) > 300) {
            array_shift($this->previous_alias_map);
        }
        if (!isset($this->disable_url_type_alias['images']) && count($this->group_by_type['images']) > 0 && strpos($alias, '@webCatalogImages/') !== false) {
            $url_to = current($this->group_by_type['images']);
            if (!next($this->group_by_type['images'])) {
                reset($this->group_by_type['images']);
            }
            if (!isset($this->previous_alias_map[$alias])) {
                $this->previous_alias_map[$alias] = str_replace('@webCatalogImages/', $url_to, $alias);
            }
            return $this->previous_alias_map[$alias];
        }
        if (!isset($this->disable_url_type_alias['images']) && count($this->group_by_type['themes']) > 0 && strpos($alias, '@webThemes/') !== false) {
            $url_to = current($this->group_by_type['themes']);
            if (!next($this->group_by_type['themes'])) {
                reset($this->group_by_type['themes']);
            }
            if (!isset($this->previous_alias_map[$alias])) {
                $this->previous_alias_map[$alias] = preg_replace('#@webThemes/+#', $url_to, $alias);
            }
            return $this->previous_alias_map[$alias];
        } elseif (strpos($alias, '@webThemes//') !== false) {
            $alias = str_replace('@webThemes//', '@webThemes/', $alias);
        }
        return \Yii::get_alias($alias);
    }
}