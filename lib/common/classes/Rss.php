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

use Dom_Document;
class Rss
{
    private $channel;
    private $platform;
    private $item;
    private $item_count = 0;
    private $chanel_xml;
    public function __construct($lang = 'en', $link = '/')
    {
        $this->platform = \Yii::$app->get('platform')->config()->get_id();
        $this->channel = new \stdClass();
        $this->get_header($lang, $link);
    }
    private function get_header($lang = 'en', $link = '/')
    {
        $platform = \common\models\Platforms::find_one((int) $this->platform);
        $this->channel->title = $platform->platform_name;
        $this->channel->link = $platform->ssl_enabled == 0 ? 'http://' . $platform->platform_url : 'https://' . $platform->platform_url;
        $this->channel->atom = $this->channel->link . $link . '?lang=' . $lang;
        $this->channel->generator = $this->channel->link . '/v=1.0';
        $this->channel->description = $platform->platform_name;
        $this->channel->last_build_date = date(DATE_RSS);
        $this->channel->update_period = 'hourly';
        $this->channel->update_frequency = 1;
        $locale = \common\models\Languages::find()->where(['code' => $lang])->one();
        $this->channel->language = strtolower(str_replace('_', '-', $locale->locale));
        $this->channel->creator = $platform->platform_owner;
    }
    public function set_item($array)
    {
        if (count($array) > 0) {
            $data = new \stdClass();
            foreach ($array as $key => $value) {
                $data->{$key} = $value;
            }
            $this->item($data);
        }
        $this->channel();
    }
    public function set_items($items)
    {
        if (count($items) > 0) {
            foreach ($items as $item) {
                $this->set_item($item);
            }
        }
    }
    private function channel()
    {
        $this->chanel_xml = new \Simple_Xml_Element('<?xml version="1.0" encoding="UTF-8" ?><channel></channel>', LIBXML_NOERROR | LIBXML_ERR_NONE | LIBXML_ERR_FATAL);
        $this->chanel_xml->add_child('title', $this->channel->title);
        $atom = $this->chanel_xml->add_child('atom:link', '', 'http://www.w3.org/2005/Atom');
        $atom->add_attribute('href', $this->channel->atom);
        $atom->add_attribute('type', 'application/rss+xml');
        $atom->add_attribute('rel', 'self');
        $this->chanel_xml->add_child('link', $this->channel->link);
        $this->chanel_xml->add_child('description', $this->channel->description);
        $this->chanel_xml->add_child('lastBuildDate', $this->channel->last_build_date);
        $this->chanel_xml->add_child('language', $this->channel->language);
        $this->chanel_xml->add_child('sy:updatePeriod', $this->channel->update_period, 'http://purl.org/rss/1.0/modules/syndication/');
        $this->chanel_xml->add_child('sy:updateFrequency', $this->channel->update_frequency, 'http://purl.org/rss/1.0/modules/syndication/');
        $this->chanel_xml->add_child('generator', $this->channel->generator);
        if (isset($this->item) && $this->item !== null) {
            foreach ($this->item as $item) {
                $to_dom = dom_import_simplexml($this->chanel_xml);
                $from_dom = dom_import_simplexml($item);
                $to_dom->append_child($to_dom->owner_document->import_node($from_dom, true));
            }
        }
        return $this->chanel_xml;
    }
    private function item($data)
    {
        $this->item[$this->item_count] = new \Simple_Xml_Element('<?xml version="1.0" encoding="UTF-8" ?><item></item>', LIBXML_NOERROR | LIBXML_ERR_NONE | LIBXML_ERR_FATAL);
        if (isset($data->title)) {
            $this->item[$this->item_count]->add_child('title', htmlspecialchars($data->title));
        }
        if (isset($data->link)) {
            $this->item[$this->item_count]->add_child('link', $data->link);
        }
        if ($this->channel->creator) {
            $this->add_cdata_child($this->item[$this->item_count], 'dc:creator', $this->channel->creator, 'http://purl.org/dc/elements/1.1/');
        }
        if (isset($data->pub_date)) {
            $this->item[$this->item_count]->add_child('pubDate', \DateTime::create_from_format('Y-m-d H:i:s', $data->pub_date)->format(DATE_RSS));
        }
        if (isset($data->categories)) {
            foreach ($data->categories as $category) {
                if (strlen($category['name']) > 0) {
                    $this->add_cdata_child($this->item[$this->item_count], 'category', $category['name']);
                }
            }
        }
        if (isset($data->guid)) {
            $guid = $this->item[$this->item_count]->add_child('guid', $data->guid);
            $guid->add_attribute('isPermaLink', empty($data->item_permalink) ? 'false' : 'true');
        }
        if (isset($data->description)) {
            $this->add_cdata_child($this->item[$this->item_count], 'description', $data->description);
        }
        if (isset($data->enclosure_url) && isset($data->enclosure_length) && isset($data->enclosure_type)) {
            $enclosure = $this->item[$this->item_count]->add_child('enclosure');
            $enclosure->add_attribute('url', $data->enclosure_url);
            $enclosure->add_attribute('length', $data->enclosure_length);
            $enclosure->add_attribute('type', $data->enclosure_type);
        }
        if (isset($data->content)) {
            $this->add_cdata_child($this->item[$this->item_count], 'content:encoded', $data->content, 'http://purl.org/rss/1.0/modules/content/');
        }
        $this->item_count = $this->item_count + 1;
    }
    public function build()
    {
        $xml = new \Simple_Xml_Element('<?xml version="1.0" encoding="UTF-8" ?><rss version="2.0" 
            xmlns:content="http://purl.org/rss/1.0/modules/content/"
            xmlns:wfw="http://wellformedweb.org/CommentAPI/"
            xmlns:dc="http://purl.org/dc/elements/1.1/"
            xmlns:atom="http://www.w3.org/2005/Atom"
            xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
            xmlns:slash="http://purl.org/rss/1.0/modules/slash/"
            />', LIBXML_NOERROR | LIBXML_ERR_NONE | LIBXML_ERR_FATAL);
        $to_dom = dom_import_simplexml($xml);
        $from_dom = dom_import_simplexml($this->channel());
        $to_dom->append_child($to_dom->owner_document->import_node($from_dom, true));
        $dom = new Dom_Document('1.0', 'UTF-8');
        $dom->append_child($dom->import_node(dom_import_simplexml($xml), true));
        $dom->format_output = true;
        return $dom->save_xml();
    }
    private function add_cdata_child($xml, $name, $value = null, $namespace = null)
    {
        $element = $xml->add_child($name, null, $namespace);
        $dom = dom_import_simplexml($element);
        $element_owner = $dom->owner_document;
        $dom->append_child($element_owner->create_cdata_section($value));
        return $element;
    }
}