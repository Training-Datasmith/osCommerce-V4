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
namespace common\api\models\XML;

class Attribute_Mapper
{
    protected $known = ['@language' => ['languages', 'languages_id'], '@currency' => ['currencies', 'currencies_id'], '@customer_address_book' => ['address_book', 'address_book_id'], '@order_status' => ['orders_status', 'orders_status_id']];
    protected $project_id = 0;
    protected $is_local_project = false;
    protected $cache = [];
    public function set_project_id($project_id)
    {
        $get_project_info_r = tep_db_query("SELECT * FROM io_project WHERE project_id='" . intval($project_id) . "'");
        if (tep_db_num_rows($get_project_info_r)) {
            $get_project_info = tep_db_fetch_array($get_project_info_r);
            $this->project_id = $project_id;
            $this->is_local_project = !!$get_project_info['is_local'];
        } else {
            throw new \Exception('Wrong project id');
        }
        $this->cache = [];
    }
    public function external_id(Complex $ref)
    {
        $external_id = null;
        if (!$this->is_local_project) {
            $entity_id = $this->get_entity_id($ref);
            if ($entity_id) {
                $get_reference_r = tep_db_query('SELECT external_id ' . 'FROM io_entity_mapping ' . "WHERE entity_id='" . (int) $entity_id . "' AND internal_id='" . intval($ref->value) . "'");
                if (tep_db_num_rows($get_reference_r) > 0) {
                    $get_reference = tep_db_fetch_array($get_reference_r);
                    $external_id = $get_reference['external_id'];
                }
            }
        }
        return $external_id;
    }
    public function internal_id(Complex $ref)
    {
        $internal_id = null;
        if (!$this->is_local_project) {
            $entity_id = $this->get_entity_id($ref);
            if ($entity_id) {
                $map_name = $ref->get_map_name();
                if (isset($this->known[$map_name])) {
                    if (isset($this->cache[(int) $entity_id . $map_name]) && !empty($this->cache[(int) $entity_id . $map_name][intval($ref->external_id)])) {
                        return $this->cache[(int) $entity_id . $map_name][intval($ref->external_id)];
                    }
                }
                $get_reference_r = tep_db_query('SELECT internal_id ' . 'FROM io_entity_mapping ' . "WHERE entity_id='" . (int) $entity_id . "' AND external_id='" . intval($ref->external_id) . "'");
                if (tep_db_num_rows($get_reference_r) > 0) {
                    $get_reference = tep_db_fetch_array($get_reference_r);
                    $internal_id = $get_reference['internal_id'];
                }
                if (isset($this->known[$map_name])) {
                    $this->cache[(int) $entity_id . $map_name][intval($ref->external_id)] = $internal_id;
                }
            }
        }
        return $internal_id;
    }
    public function map_ids(Complex $ref, $internal_id, $external_id)
    {
        $entity_id = $this->get_entity_id($ref);
        tep_db_query('INSERT IGNORE INTO io_entity_mapping ' . ' (entity_id, internal_id, external_id) ' . 'VALUES ' . "('" . (int) $entity_id . "', '" . intval($internal_id) . "', '" . intval($external_id) . "')");
    }
    protected function get_entity_id(Complex $ref)
    {
        $entity_id = 0;
        static $cached_ids = [];
        $key = intval($this->project_id) . '^' . $ref->get_map_name();
        if (!isset($cached_ids[$key])) {
            $get_id_r = tep_db_query('SELECT id ' . 'FROM io_entity ' . "WHERE entity_name='" . tep_db_input($ref->get_map_name()) . "' AND project_id='" . intval($this->project_id) . "'");
            if (tep_db_num_rows($get_id_r) > 0) {
                $get_id = tep_db_fetch_array($get_id_r);
                $entity_id = $get_id['id'];
            } else {
                tep_db_perform('io_entity', ['entity_name' => $ref->get_map_name(), 'project_id' => intval($this->project_id)]);
                $entity_id = tep_db_insert_id();
            }
            $cached_ids[$key] = $entity_id;
        } else {
            $entity_id = $cached_ids[$key];
        }
        return $entity_id;
    }
}