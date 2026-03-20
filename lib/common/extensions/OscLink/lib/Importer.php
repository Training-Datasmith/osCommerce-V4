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
namespace Osc_Link;

use common\extensions\Osc_Link\models\Configuration;
use common\extensions\Osc_Link\models\Entity;
use common\extensions\Osc_Link\models\Mapping;
#[\Allow_Dynamic_Properties]
class Importer implements \Osc_Link\XML\Import_Tuning_Interface
{
    private $downloader;
    private $platform_id = null;
    private $platform_id_def = null;
    private $feed_cur;
    private $batch_count;
    private $batch_offset;
    private $count_in_batch;
    // fat entities have small batch count
    public const BATCH_COUNT = ['categories' => 50, 'products' => 20, 'orders' => 20, 'products_options' => 50];
    public function __construct($conf)
    {
        $this->downloader = new \Osc_Link\Downloader($conf);
        $this->platform_id_def = \common\classes\platform::default_id();
        $this->platform_id = $conf['api_platform']['cmc_value'];
        if (empty($this->platform_id)) {
            $this->platform_id = $this->platform_id_def;
        }
    }
    public function before_import_save($update_object, $data)
    {
        if ($update_object instanceof \yii\db\Active_Record) {
            // platform_id
            if ($update_object instanceof \common\models\Products_Description) {
                \Osc_Link\Logger::get()->log_record($update_object, "platform_id {$update_object->platform_id} changed to {$this->platform_id_def}");
                $update_object->platform_id = $this->platform_id_def;
            }
            if ($update_object instanceof \common\models\Products) {
                // only for import from TL
                $update_object->products_id_price = 0;
                $update_object->products_id_stock = 0;
            }
        }
    }
    public function after_import($update_object, $data, $is_new_record)
    {
        \Osc_Link\Logger::get()->log(\Osc_Link\Helper::get_ident_ar($update_object) . (!$is_new_record ? ' was added' : ' was updated'));
        if ($update_object instanceof \common\models\Products) {
            \Yii::$app->db->create_command()->upsert(\common\models\Platforms_Products::tablename(), ['platform_id' => $this->platform_id, 'products_id' => $update_object->products_id], false)->execute();
        }
        if ($update_object instanceof \common\models\Categories) {
            $pc = \common\models\Platforms_Categories::find_one(['categories_id' => $update_object->categories_id, 'platform_id' => $this->platform_id]);
            if (empty($pc)) {
                $pc = new \common\models\Platforms_Categories();
                $pc->categories_id = $update_object->categories_id;
                $pc->platform_id = $this->platform_id;
                $pc->save(false);
            }
        }
    }
    public function after_import_entity($update_object, $data, $res)
    {
        $this->count_in_batch++;
        $p = (int) (100 * ($this->batch_offset + $this->count_in_batch) / $this->count_all);
        $p = $p > 100 ? 100 : $p;
        \Osc_Link\Progress::Percent($p, "{$p}%");
        Configuration::throw_if_canceled();
    }
    public function finished_import()
    {
        switch ($this->feed_cur) {
            case 'categories':
                \Yii::$app->get_db()->create_command('UPDATE menus SET last_modified = (SELECT MIN(date_added) - INTERVAL 1 DAY FROM categories)')->execute();
                \common\helpers\Categories::update_categories();
                break;
        }
    }
    public function after_clean($model, $id, $res)
    {
        if ($model instanceof \common\models\Categories) {
            $pc = \common\models\Platforms_Categories::delete_all(['categories_id' => $id]);
        }
    }
    public function after_clean_entity($model, $id, $res)
    {
        Configuration::throw_if_canceled();
    }
    public function Import($feeds)
    {
        set_time_limit(0);
        if (!is_array($feeds)) {
            $feeds = [$feeds];
        }
        $this->downloader->check_version();
        foreach ($feeds as $feed) {
            $this->feed_cur = $feed;
            $feed_name = \Osc_Link\Helper::get_feed_name($feed);
            $this->count_all = $this->downloader->get_count($feed, $error_msg);
            if ($this->count_all < 0) {
                \Osc_Link\Progress::Log("Can't import for {$feed_name}: {$error_msg}");
                continue;
            } elseif ($this->count_all == 0) {
                \Osc_Link\Progress::Log("Nothing import for {$feed_name}: records not found");
                continue;
            }
            $offset_start = 0;
            $this->batch_count = self::BATCH_COUNT[$feed] ?? 50;
            $imported_sum = [];
            \Osc_Link\Progress::Percent(0);
            \Osc_Link\Progress::Log("Start import for {$feed_name}... Expecting: {$this->count_all}");
            $structure = \Osc_Link\XML\Io_Core::get_export_structure($feed);
            $mirror_ids = self::is_feed_use_mirror_ids($feed);
            \Osc_Link\Logger::print($mirror_ids ? "The same ids for {$feed}" : "Mapping ids for {$feed}");
            \Osc_Link\XML\Io_Core::get()->set_tablenames_with_mirror_ids($mirror_ids ? $feed : []);
            for ($this->batch_offset = $offset_start; $this->batch_offset < $this->count_all; $this->batch_offset += $this->batch_count) {
                $this->count_in_batch = 0;
                $fn = $this->downloader->get_feed($feed, $this->batch_offset, $this->batch_count);
                $project = new \Osc_Link\XML\Project($fn);
                $project->set_structure($structure, $this);
                $imported = $project->import();
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
                \Osc_Link\Helper::sum_cols($imported_sum, $imported);
            }
            $this->finished_import();
            if ($mirror_ids) {
                self::update_auto_inc_value($feed);
            }
            $finish_msg = "Finished import for {$feed_name}! " . \Osc_Link\Helper::format_arr("Entities downloaded: {$this->count_all}, added: {new}, updated: {updated}, skipped: {skipped}, error: {error}", $imported_sum);
            \Osc_Link\Logger::print($finish_msg);
            \Osc_Link\Progress::Log($finish_msg);
            if ($imported_sum['skipped'] > 0 || $imported_sum['error'] > 0) {
                \Osc_Link\Progress::show_log_file();
            }
        }
        \Osc_Link\Progress::Done(true);
    }
    public function Clean(array $feeds)
    {
        set_time_limit(0);
        \Osc_Link\XML\Io_Core::get();
        // init Yii::$container
        $imported_sum = [];
        $error_sum = 0;
        \Osc_Link\Progress::$percent_prev_stage = 0;
        \Osc_Link\Progress::$percent_in_cur_stage = intval(1 / count($feeds) * 100);
        foreach ($feeds as $feed) {
            $this->feed_cur = $feed;
            $feed_name = \Osc_Link\Helper::get_feed_name($feed);
            \Osc_Link\Progress::Log("Start cleaning for {$feed_name}...");
            $project = new \Osc_Link\XML\Project();
            $structure = \Osc_Link\XML\Io_Core::get_export_structure($feed);
            $project->set_structure($structure, $this);
            $imported = $project->clean();
            \Osc_Link\Progress::$percent_prev_stage += \Osc_Link\Progress::$percent_in_cur_stage;
            \Osc_Link\Progress::Log("Finished cleaning for {$feed_name}! " . \Osc_Link\Helper::format_arr('Entities found: {mapped}, deleted now: {deleted}, deleted before: {not_found}, deleted related: {deleted_related}, error: {error}', $imported));
            if ($imported['error'] > 0) {
                \Osc_Link\Progress::show_log_file();
                $error_sum += $imported['error'];
            }
        }
        \Osc_Link\Progress::Done(true);
        return $error_sum;
    }
    /**
     * Returns true if main feed table does not contains records except imported ones
     * @param string $feed feed name
     * @return bool
     */
    public static function is_feed_use_mirror_ids($feed)
    {
        if (in_array($feed, ['products', 'orders', 'customers', 'categories'])) {
            $primary_col = "{$feed}_id";
            $entity_name = "{$feed}.{$primary_col}";
            $entity_id = Entity::find_one(['project_id' => 1, 'entity_name' => $entity_name]);
            if (empty($entity_id)) {
                return (new \yii\db\Query())->from($feed)->count() == 0;
            } else {
                return (new \yii\db\Query())->from("{$feed} f")->left_join(Mapping::table_name() . ' m', "m.internal_id = f.{$primary_col} AND entity_id = :entityId", ['entityId' => $entity_id->id ?? null])->where('m.internal_id IS NULL')->count() == 0;
            }
        }
        return false;
    }
    public static function update_auto_inc_value($feed)
    {
        $primary_col = "{$feed}_id";
        $max_id = (new \yii\db\Query())->from($feed)->max($primary_col);
        \Yii::$app->db->create_command()->execute_reset_sequence($feed, round($max_id, -2) + 1000);
    }
}