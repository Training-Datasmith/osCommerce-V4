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
class Sitemap_Controller extends Sceleton
{
    public $acl = ['BOX_HEADING_SEO', 'BOX_MARKETING_SITEMAP'];
    public function action_index()
    {
        $this->selected_menu = ['seo_cms', 'sitemap'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('sitemap/index'), 'title' => HEADING_TITLE];
        $this->view->heading_title = HEADING_TITLE;
        $this->view->site_map_table = [
            ['title' => TABLE_HEADING_SITEMAP_FILE_URL, 'not_important' => 0],
            /*array(
                'title' => TABLE_HEADING_FILE_SIZE,
                'not_important' => 0,
              ),*/
            ['title' => TABLE_HEADING_STATUS, 'not_important' => 0],
            ['title' => TABLE_HEADING_CREATED_DATE, 'not_important' => 0],
            ['title' => '&nbsp;', 'not_important' => 0],
        ];
        return $this->render('index');
    }
    public function action_list()
    {
        \common\helpers\Translation::init('admin/sitemap');
        $draw = Yii::$app->request->get('draw', 1);
        $start = Yii::$app->request->get('start', 0);
        $length = Yii::$app->request->get('length', 10);
        $current_page_number = $start / $length + 1;
        $response_list = [];
        $platform_list = \common\classes\platform::get_list(false, false);
        $_active_platform_id = Yii::$app->get('platform')->config()->get_id();
        foreach ($platform_list as $platform) {
            Yii::$app->get('platform')->config($platform['id']);
            $index_feed = tep_catalog_href_link('sitemap.xml', '', 'SSL');
            Yii::$app->get('platform')->config($_active_platform_id);
            if (empty($platform['platform_url'])) {
                $content = '';
            } else {
                $content = @file_get_contents($index_feed, false, stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true], 'http' => ['timeout' => 6]]));
            }
            if ($content && preg_match_all('#<sitemap>(.*?)</sitemap>#ims', $content, $feeds_match)) {
                $response_list[] = [
                    $index_feed,
                    //round(strlen($content)/1024,2).' kB',
                    TEXT_FILE_STATUS_GOOD,
                    \common\helpers\Date::date_short(date('Y-m-d')),
                    '<a href="' . tep_href_link('sitemap/download', 'file=index&store=' . $platform['id']) . '">' . TEXT_DOWNLOAD_FILE . '</a>',
                ];
                foreach ($feeds_match[1] as $feed_tags) {
                    if (preg_match('#<loc>(.*)</loc>#ims', $feed_tags, $location)) {
                        preg_match('#<lastmod>(.*)</lastmod>#ims', $feed_tags, $lastmod);
                        list($dummy_or_not, $action) = explode('xmlsitemap/', $location[1], 2);
                        $response_list[] = [
                            $location[1],
                            //round(strlen(@file_get_contents($location[1]))/1024,2).' kB',
                            TEXT_FILE_STATUS_GOOD,
                            \common\helpers\Date::date_short($lastmod[1]),
                            '<a href="' . tep_href_link('sitemap/download', 'file=' . $action) . '">' . TEXT_DOWNLOAD_FILE . '</a>',
                        ];
                    }
                }
            } else {
                $response_list[] = [
                    $index_feed,
                    //'0'.' kB',
                    TEXT_FILE_STATUS_FAIL,
                    '',
                    '',
                ];
            }
        }
        $records_total = count($response_list);
        if (isset($_GET['search']['value']) && tep_not_null($_GET['search']['value'])) {
            $keywords = tep_db_input(tep_db_prepare_input($_GET['search']['value']));
            foreach ($response_list as $idx => $_tmp) {
                if (stripos(implode("\n", $_tmp), $keywords) === false) {
                    unset($response_list[$idx]);
                }
            }
            $response_list = array_values($response_list);
        }
        if ($length > 0) {
            $response_list = array_values(array_slice($response_list, max(0, $current_page_number - 1) * $length, $length));
        }
        $response = ['draw' => $draw, 'recordsTotal' => $records_total, 'recordsFiltered' => count($response_list), 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_download()
    {
        $platform_id = \Yii::$app->request->get('store', \common\classes\platform::default_id());
        $action = \Yii::$app->request->get('file', 'index');
        $action = basename($action);
        $_active_platform_id = Yii::$app->get('platform')->config()->get_id();
        Yii::$app->get('platform')->config($platform_id);
        $_feed_url = tep_catalog_href_link('xmlsitemap/' . $action);
        Yii::$app->get('platform')->config($_active_platform_id);
        header('Content-Type: ' . 'text/xml');
        header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Content-Disposition: attachment; filename="' . $action . '.xml"');
        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            $HTTP_USER_AGENT = $_SERVER['HTTP_USER_AGENT'];
        } elseif (!isset($HTTP_USER_AGENT)) {
            $HTTP_USER_AGENT = '';
        }
        if (preg_match('@MSIE ([0-9].[0-9]{1,2})@', $HTTP_USER_AGENT, $log_version)) {
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
        } else {
            header('Pragma: no-cache');
        }
        readfile($_feed_url);
        die;
    }
}