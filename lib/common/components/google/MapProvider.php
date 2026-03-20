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
namespace common\components\google;

use common\models\repositories\Google_Settings_Repository;
class Map_Provider extends Providers implements Google_Provider_Interface
{
    private $gs_repository;
    private $code = 'mapskey';
    public function get_name()
    {
        return 'Map Key API';
    }
    public function get_code()
    {
        return $this->code;
    }
    public function get_description()
    {
        return 'This key is used to make requests with right access to Google Libraries like Maps JavaScript API. It can be obtained at <a href="https://console.developers.google.com/apis/credentials" target="_blank">Google Console</a>';
    }
    public function __construct(Google_Settings_Repository $gs_repository)
    {
        $this->gs_repository = $gs_repository;
    }
    public function get_setting()
    {
        return $this->gs_repository->get_setting($this->code, 0, 0);
    }
    public function update_setting($setting, $data)
    {
        if (is_array($data) && isset($data['key'])) {
            $key = $data['key'];
            return $this->gs_repository->update_setting($setting, [$this->gs_repository->get_config_holder() => (string) $key]);
        }
        return false;
    }
    public function create_setting($data)
    {
        if (is_array($data) && isset($data['key'])) {
            return $this->gs_repository->create_setting($this->get_code(), $this->get_name(), $data['key'], 0, 0);
        }
        return false;
    }
    public function get_config()
    {
        $setting = $this->get_setting();
        if ($setting) {
            $value = $setting->get_value();
            return $value ? $value : false;
        }
        return false;
    }
    public function get_maps_key()
    {
        return $this->get_config();
    }
    public function draw_config_template()
    {
        return widgets\Map_Widget::widget(['value' => $this->get_config(), 'owner' => $this->get_class_name(), 'description' => $this->get_description()]);
    }
    public function get_location_by_address(string $address)
    {
        if ($key = $this->get_maps_key()) {
            $query = http_build_query(['address' => $address, 'key' => $key]);
            $client = new \Guzzle_Http\Client(['base_uri' => 'https://maps.googleapis.com/']);
            try {
                $response = $client->get('maps/api/geocode/json?' . $query);
                if ($response) {
                    $content = json_decode($response->get_body()->get_contents());
                    if (is_object($response) && !empty($response->results) && $response->status == 'OK') {
                        $response = $response->results[0];
                        if (is_object($response) && property_exists($response, 'geometry')) {
                            $detail = $response->geometry;
                            if (property_exists($detail, 'location')) {
                                $detail = $detail->location;
                                if (property_exists($detail, 'lat') && property_exists($detail, 'lng')) {
                                    return ['lat' => (float) $detail->lat, 'lng' => (float) $detail->lng];
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $ex) {
                return false;
            }
        }
        return false;
    }
}