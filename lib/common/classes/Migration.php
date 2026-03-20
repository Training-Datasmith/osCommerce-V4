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

use common\helpers\System;
class Migration extends \yii\db\Migration
{
    /**
     * drop index with same name if exists and create it again
     * @inheritdoc
     */
    public function create_index($name, $table, $columns, $unique = false)
    {
        $check_exists = $this->db->create_command('show indexes from ' . $table . ' WHERE Key_name like :indexName ', ['indexName' => $name])->query_one();
        if (is_array($check_exists)) {
            // drop old one
            $this->drop_index($name, $table);
        }
        parent::create_index($name, $table, $columns, $unique);
    }
    /**
     * @inheritdoc
     */
    public function drop_index($name, $table)
    {
        $check_exists = $this->db->create_command('show indexes from ' . $table . ' WHERE Key_name like :indexName ', ['indexName' => $name])->query_one();
        if (is_array($check_exists)) {
            parent::drop_index($name, $table);
        }
    }
    public function is_index_exist($name, $table)
    {
        $check_exists = $this->db->create_command('show indexes from ' . $table . ' WHERE Key_name like :indexName ', ['indexName' => $name])->query_one();
        return is_array($check_exists);
    }
    public function add_primary_key_force($name, $table, $columns)
    {
        $this->drop_primary_key($name, $table);
        $this->add_primary_key($name, $table, $columns);
    }
    /**
     * @inheritdoc
     */
    public function add_foreign_key($name, $table, $columns, $ref_table, $ref_columns, $delete = null, $update = null)
    {
        $ts = $this->get_db()->get_table_schema($table, true);
        if (isset($ts->foreign_keys[$name])) {
            $this->drop_foreign_key($name, $table);
        }
        parent::add_foreign_key($name, $table, $columns, $ref_table, $ref_columns, $delete, $update);
    }
    /**
     * @inheritdoc
     */
    public function drop_foreign_key($name, $table)
    {
        $ts = $this->get_db()->get_table_schema($table);
        if (isset($ts->foreign_keys[$name])) {
            parent::drop_foreign_key($name, $table);
        }
    }
    /**
     * Creates table and indexes if table not exists yet
     * @param string       $table_name
     * @param array        $struct      table structure array like ['column' => $migrate->integer(10), etc.]
     * @param string|array $primary     info for primary key:
     *                                  string -> comma separated string of columns that the primary key will consist of. The index name will be $table_name + '_pk'
     *                                  array  -> the key is a name of primary key, the value is comma separated string of columns
     *                                  Samples:
     *                                      'products_id,platforms_id'
     *                                      ['products_id', 'platforms_id']
     *                                      [ 'primary_key' => 'products_id, platforms_id']
     *                                      [ 'primary_key' => ['products_id', 'platforms_id']]
     * @param string|array $indexes     info for foreign key
     *                                  string -> comma separated string of columns that the foreign key will consist of. The index name will be generated automatically
     *                                  array  -> each item
     *                                            string -> comma separated string of columns that the foreign key will consist of. The index name will be generated automatically
     *                                            array  -> the key is a name of primary key (prefix 'unique:' allowed), the value is comma separated string of columns
     *                                  Samples:
     *                                      'products_id,platforms_id'
     *                                      ['products_id,platforms_id', 'products_id,customers_id']
     *                                      [ 'unique:products_platforms' => 'products_id,platforms_id', 'products_customers' => 'products_id,customers_id']
     *                                      [ 'unique:products_platforms' => ['products_id', 'platforms_id'], 'products_customers' => 'products_id,customers_id']
     *
     * @return boolean True if table did not exist and has just been created.
     */
    public function create_table_if_not_exists(
        string $table_name,
        array $struct,
        /* array|string */
        $primary = null,
        /* array|string */
        $indexes = null
    )
    {
        if (!$this->is_table_exists($table_name)) {
            try {
                $this->create_table($table_name, $struct);
                // create primary key
                if (!is_null($primary)) {
                    $index_name = null;
                    if (is_string($primary)) {
                        $index_name = $table_name . '_pk';
                        $columns = $primary;
                    }
                    if (is_array($primary)) {
                        switch (count($primary)) {
                            case 0:
                                break;
                            case 1:
                                // key as index_name
                                foreach ($primary as $index_name => $columns) {
                                    break;
                                }
                                break;
                            default:
                                // array of columns
                                $index_name = $table_name . '_pk';
                                $columns = $primary;
                                break;
                        }
                    }
                    if (!is_null($index_name)) {
                        $this->add_primary_key($index_name, $table_name, $columns);
                    }
                }
                // create indexes
                if (!is_null($indexes)) {
                    if (!is_array($indexes)) {
                        $indexes = [$indexes];
                    }
                    foreach ($indexes as $index_name => $columns) {
                        $unique = false;
                        if (is_string($index_name) && strpos($index_name, 'unique:') !== false) {
                            $unique = true;
                            $index_name = str_replace('unique:', '', $index_name);
                        }
                        if (is_int($index_name) or empty($index_name)) {
                            $index_name = str_replace(',', '_', $columns);
                        }
                        $this->create_index($index_name, $table_name, $columns, $unique);
                    }
                }
                return true;
            } catch (\Throwable $e) {
                \Yii::warning($e->get_message() . "\n" . $e->get_trace_as_string());
                $this->drop_table_if_exists($table_name);
                throw $e;
            }
        }
    }
    public function drop_table_if_exists($table)
    {
        if ($this->is_table_exists($table)) {
            $this->drop_table($table);
        }
    }
    /**
     * Drops multiple tables.
     * @param array|string $tables array of table names to be dropped.
     */
    public function drop_tables($tables)
    {
        if (is_string($tables)) {
            $tables = explode(',', $tables);
        }
        if (is_array($tables)) {
            foreach ($tables as $table) {
                $this->drop_table_if_exists($table);
            }
        }
    }
    /**
     * @inheritdoc
     */
    public function is_table_exists($table_name)
    {
        return $this->db->get_table_schema($table_name, true) !== null;
    }
    /**
     * @inheritdoc
     */
    public function is_field_exists($field, $table)
    {
        $fields = $this->db->create_command("show FIELDS from {$table}")->query_column();
        return in_array($field, $fields);
    }
    /**
     * Check column in table
     *
     * @param $tableName
     * @param $columnName
     * @return bool
     */
    public function is_missing_column($table_name, $column_name)
    {
        return $this->db->get_table_schema($table_name, true)->get_column($column_name) === null;
    }
    /**
     * Wrap around addColumn with check for simple migration scenario
     *
     * Builds and executes a SQL statement for adding a new DB column.
     * @param string $table the table that the new column will be added to. The table name will be properly quoted by the method.
     * @param string $column the name of the new column. The name will be properly quoted by the method.
     * @param string $type the column type. The [[QueryBuilder::getColumnType()]] method will be invoked to convert abstract column type (if any)
     * into the physical one. Anything that is not recognized as abstract type will be kept in the generated SQL.
     * For example, 'string' will be turned into 'varchar(255)', while 'string not null' will become 'varchar(255) not null'.
     */
    public function add_column_if_missing($table, $column, $type)
    {
        if ($this->is_missing_column($table, $column)) {
            $this->add_column($table, $column, $type);
        }
    }
    public function drop_column_if_exists($table, $column)
    {
        if (!$this->is_missing_column($table, $column)) {
            $this->drop_column($table, $column);
        }
    }
    /**
     * @inheritdoc
     */
    public function create_table($table, $columns, $options = null)
    {
        if ($options === null && $this->db->driver_name === 'mysql') {
            // http://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
            //$options = 'CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE=InnoDB';
            $options = 'ENGINE=InnoDB CHARSET=utf8';
        }
        parent::create_table($table, $columns, $options);
    }
    public function batch_insert_safe($table, $columns, $rows)
    {
        try {
            parent::batch_insert($table, $columns, $rows);
            return true;
        } catch (\Exception $e) {
            \Yii::warning('Error in batchInsert (will be added row-by-row): ' . $e->get_message());
        }
        $error_cnt = 0;
        $error_msg = '';
        foreach ($rows as $row) {
            $insert = [];
            foreach ($columns as $index => $col) {
                $insert[$col] = $row[$index];
            }
            try {
                \Yii::$app->db->create_command()->upsert($table, $insert, false)->execute();
            } catch (\Exception $e) {
                $error_cnt++;
                $error_msg .= sprintf("Error adding row %s: %s\n", var_export($row, true), $e->get_message());
            }
        }
        if ($error_cnt) {
            \Yii::warning("Error in row-by-row inserting ({$error_cnt}): " . $error_msg);
        }
        return $error_cnt;
    }
    /**
     *
     * @staticvar boolean $language_map
     * @param string  $entity
     * @param array $keys [$key=>$value], $value = string | array per language ['en' => 'english', 'fr' => 'French']
     */
    public function add_translation($entity, $keys, $replace_existing = false)
    {
        static $language_map = false;
        if ($language_map === false) {
            $language_map = \yii\helpers\Array_Helper::map(\common\models\Languages::find()->select('languages_id, code')->as_array()->all(), 'code', 'languages_id');
        }
        foreach ($keys as $key => $value) {
            $hash = md5($key . '-' . $entity);
            if (is_array($value)) {
                // per language $value -- 'en' => 'english', 'fr' => 'French'
                foreach ($language_map as $language_code => $language_id) {
                    $checked = $translated = false;
                    if (isset($value[$language_code])) {
                        $language_value = $value[$language_code];
                        $checked = $translated = true;
                    } elseif (isset($value[\common\helpers\Language::system_language_code()])) {
                        $language_value = $value[\common\helpers\Language::system_language_code()];
                    } else {
                        $language_value = reset($value);
                    }
                    if ($replace_existing) {
                        $existing = \common\models\Translation::find_one(['language_id' => $language_id, 'translation_entity' => $entity, 'translation_key' => $key]);
                        if ($existing && $existing->translation_value != $language_value) {
                            \common\models\Translation::delete_all(['language_id' => $language_id, 'translation_entity' => $entity, 'translation_key' => $key]);
                        }
                    }
                    $this->db->create_command('INSERT IGNORE INTO `translation` ' . '  (language_id, translation_key, translation_entity, translation_value, checked, translated, hash) ' . '  VALUES (:languages_id, :text_key, :entity, :text_value, :checked, :translated, :hash)', ['languages_id' => (int) $language_id, 'entity' => $entity, 'text_key' => $key, 'text_value' => $language_value, 'checked' => $checked, 'translated' => $translated, 'hash' => $hash])->execute();
                }
            } else {
                if ($replace_existing) {
                    $existing = \common\models\Translation::find_one(['language_id' => 1, 'translation_entity' => $entity, 'translation_key' => $key]);
                    if ($existing && $existing->translation_value != $value) {
                        \common\models\Translation::delete_all(['translation_entity' => $entity, 'translation_key' => $key]);
                    }
                }
                $this->db->create_command('INSERT IGNORE INTO `translation` ' . '  (language_id, translation_key, translation_entity, translation_value, checked, translated, hash) ' . '  VALUES (1, :text_key, :entity, :text_value, 1, 1, :hash)', ['entity' => $entity, 'text_key' => $key, 'text_value' => $value, 'hash' => $hash])->execute();
                $this->db->create_command('INSERT IGNORE INTO `translation` ' . '  (language_id, translation_key, translation_entity, translation_value, hash) ' . '  SELECT languages_id, :text_key, :entity, :text_value, :hash FROM languages', ['entity' => $entity, 'text_key' => $key, 'text_value' => $value, 'hash' => $hash])->execute();
            }
        }
        \yii\caching\Tag_Dependency::invalidate(\Yii::$app->get_cache(), 'translation');
    }
    /**
     *
     * @param string $entity
     * @param array $keys
     */
    public function remove_translation($entity, $keys = null)
    {
        if (!empty($keys)) {
            $this->print("Remove translations for enity {$entity}\n");
            if (!is_array($keys)) {
                $keys = [$keys];
            }
            foreach ($keys as $key) {
                $this->db->create_command('DELETE FROM translation ' . 'WHERE translation_entity=:entity AND translation_key=:translate_key ', ['entity' => $entity, 'translate_key' => $key])->execute();
            }
        } elseif (is_null($keys)) {
            $this->db->create_command('DELETE FROM translation WHERE translation_entity=:entity', ['entity' => $entity])->execute();
        }
        \yii\caching\Tag_Dependency::invalidate(\Yii::$app->get_cache(), 'translation');
    }
    /**
     * @param $key_name
     * @param $type_to_data
     * @example addEmailTemplate('Test email', [ 'html'=>['subject'=>'email subject', 'body'=>'email body'], [ 'text'=>['subject'=>'email subject', 'body'=>'email body'] ])
     */
    public function add_email_template($key_name, $type_to_data)
    {
        $data = ['html' => ['email_templates_subject' => isset($type_to_data['html']['subject']) ? $type_to_data['html']['subject'] : (isset($type_to_data['text']['subject']) ? $type_to_data['text']['subject'] : ''), 'email_templates_body' => isset($type_to_data['html']['body']) ? $type_to_data['html']['body'] : (isset($type_to_data['text']['body']) ? $type_to_data['text']['body'] : '')], 'plaintext' => ['email_templates_subject' => isset($type_to_data['text']['subject']) ? $type_to_data['text']['subject'] : (isset($type_to_data['html']['subject']) ? $type_to_data['html']['subject'] : ''), 'email_templates_body' => isset($type_to_data['text']['body']) ? $type_to_data['text']['body'] : (isset($type_to_data['html']['body']) ? $type_to_data['html']['body'] : '')]];
        foreach ($data as $email_template_type => $email_template_data) {
            $existing_email_templates_id = $this->db->create_command('SELECT email_templates_id ' . 'FROM email_templates ' . 'WHERE email_templates_key = :templates_key and email_template_type=:email_type ', ['templates_key' => $key_name, 'email_type' => $email_template_type])->query_scalar();
            if (!$existing_email_templates_id) {
                $this->insert('email_templates', ['email_templates_key' => $key_name, 'email_template_type' => $email_template_type]);
                $email_template_id = $this->db->get_last_insert_id();
                $insert_data_query = new \yii\db\Query();
                foreach ($insert_data_query->select(['email_templates_id' => new \yii\db\Expression($email_template_id), 'platform_id' => 'p.platform_id', 'language_id' => 'l.languages_id', 'affiliate_id' => new \yii\db\Expression(0)])->from(['p' => 'platforms', 'l' => 'languages'])->where('p.is_virtual=0')->all() as $row) {
                    $row['email_templates_subject'] = $email_template_data['email_templates_subject'];
                    $row['email_templates_body'] = $email_template_data['email_templates_body'];
                    $this->insert('email_templates_texts', $row);
                }
            }
        }
    }
    public function remove_email_template($key_name)
    {
        $this->db->create_command('DELETE ett FROM email_templates_texts ett ' . ' INNER JOIN email_templates et ON ett.email_templates_id=et.email_templates_id ' . 'WHERE email_templates_key = :templates_key ', ['templates_key' => $key_name])->execute();
        $this->db->create_command('DELETE et FROM email_templates et ' . 'WHERE email_templates_key = :templates_key ', ['templates_key' => $key_name])->execute();
    }
    public function append_acl($acl_chain, $assign_to_access_levels = 1)
    {
        $PARENT_ID = 0;
        $ACL_INSERTED_ID = false;
        foreach ($acl_chain as $assign_box) {
            $check_on_level = $this->db->create_command('SELECT access_control_list_id AS id ' . 'FROM access_control_list ' . 'WHERE parent_id=:parent_id AND access_control_list_key=:box_name ', ['parent_id' => (int) $PARENT_ID, 'box_name' => $assign_box])->query_one();
            if (is_array($check_on_level)) {
                $PARENT_ID = $check_on_level['id'];
            } else {
                $get_so = $this->db->create_command('SELECT MAX(sort_order) AS max_so ' . 'FROM access_control_list ' . "WHERE parent_id='" . (int) $PARENT_ID . "'")->query_one();
                $SORT_ORDER = (int) $get_so['max_so'] + 1;
                $this->insert('access_control_list', ['parent_id' => $PARENT_ID, 'access_control_list_key' => $assign_box, 'sort_order' => $SORT_ORDER]);
                $ACL_INSERTED_ID = $this->db->get_last_insert_id();
                $PARENT_ID = $ACL_INSERTED_ID;
                if (!is_array($assign_to_access_levels)) {
                    $assign_to_access_levels = [$assign_to_access_levels];
                }
                $this->db->create_command('UPDATE access_levels ' . "SET access_levels_persmissions = CONCAT(access_levels_persmissions,',','" . (int) $ACL_INSERTED_ID . "') " . "WHERE access_levels_id IN ('" . implode("','", array_map('intval', $assign_to_access_levels)) . "')")->execute();
            }
        }
        if ($ACL_INSERTED_ID === false) {
            $ACL_INSERTED_ID = $PARENT_ID;
        }
        return $ACL_INSERTED_ID;
    }
    public function remove_acl($acl_chain)
    {
        $this->drop_acl($acl_chain);
    }
    private function check_parent($id, $acl_reversed)
    {
        array_shift($acl_reversed);
        foreach ($acl_reversed as $acl) {
            if (empty($row = \common\models\Access_Control_List::find_one(['access_control_list_key' => $acl, 'access_control_list_id' => $id]))) {
                return false;
            }
            $id = $row->parent_id;
        }
        return true;
    }
    public function drop_acl($acl_chain)
    {
        $acl_chain = array_reverse($acl_chain);
        $ids = [];
        foreach ($acl_chain as $assign_box) {
            foreach (\common\models\Access_Control_List::find()->where(['access_control_list_key' => $assign_box])->all() as $acl) {
                if (!$this->check_parent($acl->parent_id, $acl_chain)) {
                    continue;
                }
                $count = \common\models\Access_Control_List::find()->where(['parent_id' => $acl->access_control_list_id])->count();
                if ($count == 0) {
                    $ids[] = $acl->access_control_list_id;
                    $acl->delete();
                }
            }
        }
        if (count($ids) > 0) {
            foreach (\common\models\Access_Levels::find()->all() as $acl) {
                $persmissions = explode(',', $acl->access_levels_persmissions);
                foreach ($persmissions as $key => $value) {
                    if (in_array($value, $ids)) {
                        unset($persmissions[$key]);
                    }
                }
                $acl->access_levels_persmissions = implode(',', $persmissions);
                $acl->save(false);
            }
        }
    }
    public function add_admin_menu_after($menu_data, $after_box_title)
    {
        if (is_array($menu_data) && !empty($menu_data['title'])) {
            $check_box = $this->db->create_command('SELECT box_id ' . 'FROM admin_boxes ' . 'WHERE title=:box_title', ['box_title' => $menu_data['title']])->query_one();
            if (is_array($check_box)) {
                return (int) $check_box['box_id'];
            }
        } else {
            return false;
        }
        $get_box = $this->db->create_command('SELECT parent_id, box_id, sort_order ' . 'FROM admin_boxes ' . 'WHERE title=:box_title', ['box_title' => $after_box_title])->query_one();
        if (is_array($get_box)) {
            //$getBox['box_id'];
            $new_sort_order = $get_box['sort_order'] + 1;
            $this->db->create_command('UPDATE admin_boxes SET sort_order=sort_order+1 ' . 'WHERE parent_id=:parent_id AND sort_order>=:shift_sort_order', ['parent_id' => (int) $get_box['parent_id'], 'shift_sort_order' => (int) $new_sort_order])->execute();
            $default_data = ['parent_id' => $get_box['parent_id'], 'sort_order' => $new_sort_order, 'acl_check' => '', 'config_check' => '', 'box_type' => 0, 'path' => '', 'title' => '', 'filename' => ''];
            $data = array_merge($default_data, $menu_data);
            $this->insert('admin_boxes', $data);
            return $this->db->get_last_insert_id();
            //$this->updateMenuXmlAfter(\Yii::getAlias('@site_root/admin/includes/default_menu.xml'), $data, $afterBoxTitle); die;
        }
        return false;
    }
    public function add_admin_menu(array $menu_array)
    {
        \common\helpers\Menu_Helper::create_admin_menu_item($menu_array);
    }
    public function remove_admin_menu($array_or_title)
    {
        $this->drop_admin_menu($array_or_title);
    }
    public function drop_admin_menu($array_or_title)
    {
        \common\helpers\Menu_Helper::remove_admin_menu_item($array_or_title);
    }
    protected function update_menu_xml_after($filename, $node_data, $after_box_title)
    {
        $after_box_title = 'BOX_REPORTS_COMPARE';
        $simple_menu = \simplexml_load_file($filename);
        $check_new_exist = $simple_menu->xpath('//title[text()=\'' . $node_data['title'] . '\']');
        if (count($check_new_exist) > 0) {
            return false;
        }
        $xpath = $simple_menu->xpath('//title[text()=\'' . $after_box_title . '\']/..');
        if (count($xpath) == 0) {
            return false;
        }
        $insert_after = $xpath[0];
        $new_node_sort_order = intval($insert_after->sort_order) + 1;
        unset($node_data['parent_id']);
        $node_data['sort_order'] = $new_node_sort_order;
        $xml_formatter = new \common\api\Xml\Xml_Formatter();
        $xml_formatter->root_tag = 'item';
        $insert_node = \simplexml_load_string($xml_formatter->format($node_data));
        /**
         * @var $modifyParent \SimpleXMLElement
         */
        $modify_parent = reset($insert_after->xpath('..'));
        foreach ($modify_parent->children() as $child_node) {
            if (strval($child_node->title) == $after_box_title) {
                $target_dom = \dom_import_simplexml($child_node);
                $insert_dom = $target_dom->owner_document->import_node(\dom_import_simplexml($insert_node), true);
                if ($target_dom->next_sibling) {
                    $target_dom->parent_node->insert_before($insert_dom, $target_dom->next_sibling);
                } else {
                    $target_dom->parent_node->append_child($insert_dom);
                }
            }
            if (intval($child_node->sort_order) >= $new_node_sort_order) {
                $child_node->sort_order = intval($child_node->sort_order) + 1;
            }
        }
        //$xslt = new \XSLTProcessor();
        //$xslt->importStyleSheet($xsl);
        $xsl = \simplexml_load_string('<?xml version="1.0" encoding="utf-8"?>
<xsl:stylesheet xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns="http://www.w3.org/1999/xhtml" version="1.0">

    <xsl:output encoding="utf-8" method="text" indent="no" media-type="text/xml"/>

    <xsl:template match="/">
        <xsl:text>&lt;?xml version="1.0" encoding="UTF-8"?&gt;
</xsl:text>
        <xsl:apply-templates select="node()">
            <xsl:with-param name="indent" select="\'\'"/>
        </xsl:apply-templates>
    </xsl:template>

    <xsl:template match="node()">
        <xsl:param name="indent"/>

        <xsl:value-of select="$indent"/>

        <xsl:text>&lt;</xsl:text><xsl:value-of select="name(.)"/><xsl:apply-templates select="@*"/>

            <!--xsl:if test="not(node())"><xsl:text> /</xsl:text></xsl:if-->
            <xsl:if test="not(node())"><xsl:text>&gt;&lt;/</xsl:text><xsl:value-of select="name(.)"/></xsl:if>
        <xsl:text>&gt;</xsl:text>

        <xsl:if test="node()">

            <xsl:if test="node()[node()]">
<xsl:text>
</xsl:text>
            </xsl:if>

            <xsl:apply-templates>
                <xsl:with-param name="indent" select="concat($indent, \'    \')"/>
            </xsl:apply-templates>


            <xsl:if test="node()[node()]">
                <xsl:value-of select="$indent"/>
            </xsl:if>

            <xsl:text>&lt;/</xsl:text><xsl:value-of select="name(.)"/><xsl:text>&gt;</xsl:text>
        </xsl:if>

<xsl:text>
</xsl:text>
    </xsl:template>

    <xsl:template match="@*">
        <xsl:text> </xsl:text>
        <xsl:value-of select="name(.)"/>
        <xsl:text>=</xsl:text>
        <xsl:value-of select="concat(\'&quot;\', ., \'&quot;\')"/>
    </xsl:template>

    <xsl:template match="text()">
        <xsl:value-of select="normalize-space(.)"/>
    </xsl:template>

    <xsl:template match="comment()">
        <xsl:text>&lt;--</xsl:text><xsl:value-of select="."/><xsl:text>--&gt;</xsl:text>
    </xsl:template>

    <xsl:template match="processing-instruction()">
        <xsl:text>&lt;?</xsl:text><xsl:value-of select="name(.)"/><xsl:text> </xsl:text><xsl:value-of select="."/><xsl:text>?&gt;</xsl:text>
<xsl:text>
</xsl:text>
    </xsl:template>
</xsl:stylesheet>
');
        $xslt = new \Xslt_Processor();
        $xslt->import_style_sheet($xsl);
        file_put_contents($filename . '.new.xml', $xslt->transform_to_xml($simple_menu));
        die;
        $xml_formatter = new \common\api\Xml\Xml_Formatter();
        $unformated_xml = $xml_formatter->format($simple_menu);
        $domxml = new \Dom_Document('1.0');
        //$domxml->preserveWhiteSpace = false;
        $domxml->format_output = true;
        /* @var $xml SimpleXMLElement */
        $domxml->load_xml($unformated_xml);
        $domxml->save($filename . '.new.xml', LIBXML_NOEMPTYTAG);
        //file_put_contents($filename.'.new.xml',);
        //echo '<pre>'; var_dump($ob); echo '</pre>';
    }
    private static function var_to_str($str_or_array)
    {
        return is_array($str_or_array) ? implode(',', $str_or_array) : $str_or_array;
    }
    public function add_configuration_key($attr_array, $change_if_exists = false)
    {
        if (!isset($attr_array['configuration_key'])) {
            if (System::is_development()) {
                throw new \Exception("Param 'configuration_key' is not set");
            }
            return false;
        }
        unset($attr_array['date_added']);
        $model = \common\models\Configuration::find_one(['configuration_key' => $attr_array['configuration_key']]);
        if (empty($model)) {
            $model = new \common\models\Configuration();
            $model->load_default_values();
            $model->date_added = new \yii\db\Expression('now()');
        } else if (!$change_if_exists) {
            return false;
        }
        $model->set_attributes($attr_array);
        $model->last_modified = new \yii\db\Expression('now()');
        $model->save(false);
        return true;
    }
    public function remove_configuration_keys($key_or_array)
    {
        $this->print('Remove configuration keys: ' . self::var_to_str($key_or_array) . "\n");
        $this->delete(TABLE_CONFIGURATION, ['configuration_key' => $key_or_array]);
    }
    public function remove_configuration_keys_in_group($group_or_array)
    {
        $this->print('Remove configuration keys in group: ' . self::var_to_str($group_or_array) . "\n");
        $this->delete(TABLE_CONFIGURATION, ['configuration_group_id' => $group_or_array]);
    }
    public function remove_platform_configuration_keys($key_or_array, $platform_id = null)
    {
        $this->print('Remove platform configuration keys: ' . self::var_to_str($key_or_array) . "\n");
        $conditions['configuration_key'] = $key_or_array;
        if (!is_null($platform_id)) {
            $conditions['platform_id'] = $platform_id;
        }
        $this->delete(TABLE_PLATFORMS_CONFIGURATION, $conditions);
    }
    public function remove_platform_configuration_keys_in_group($group_or_array, $platform_id = null)
    {
        $this->print('Remove platform configuration keys in group: ' . self::var_to_str($group_or_array) . "\n");
        $conditions['configuration_key'] = $group_or_array;
        if (!is_null($platform_id)) {
            $conditions['platform_id'] = $platform_id;
        }
        $this->delete(TABLE_PLATFORMS_CONFIGURATION, $conditions);
    }
    public function is_widget_exist($widget_name_or_array, $theme_name = null)
    {
        $rec = \common\models\Design_Boxes::find()->where(['widget_name' => $widget_name_or_array]);
        if (!empty($theme_name)) {
            $rec->and_where(['OR', 'theme_name= :theme_name', 'theme_name=:theme_mobile'], ['theme_name' => $theme_name, 'theme_mobile' => $theme_name . '-mobile']);
        }
        return !empty($rec->one());
    }
    /**
     * @param string $newWidgetName - new widget name like 'Extension\widgets\WidgetName'. For zipped widget 'Extension\widgets\WidgetName=>lib/common/extensions/ExtName/widget/WidgetName.zip'
     * @param string|array $toPlaceholders - placeholder to put new widget (may be null if need rename only)
     * @param $oldWidgetName - old widget name if exists
     * @param $renameWidgetStylesArray - array to rename old widget styles ['oldStyleName' => 'newStyleName']
     * @param string $position position for installed widget in placeholder
     * @see Migration::addWidget()
     * @return void
     */
    public function add_or_rename_widget(string $new_widget_name, $to_placeholders, $old_widget_name = null, $rename_widget_styles_array = null, $position = 'end')
    {
        $tmp = explode('=>', $new_widget_name);
        if (count($tmp) > 1) {
            list($new_widget_name, $new_widget_name_or_zip_file) = $tmp;
        } else {
            $new_widget_name_or_zip_file = $new_widget_name;
        }
        $themes = \common\models\Themes::find()->as_array()->all();
        foreach ($themes as $theme_arr) {
            $theme = $theme_arr['theme_name'];
            $old_widget_exists = !empty($old_widget_name) && $this->is_widget_exist($old_widget_name, $theme);
            $new_widget_exists = $this->is_widget_exist($new_widget_name, $theme);
            if (!$new_widget_exists && !$old_widget_exists) {
                if (!empty($to_placeholders)) {
                    $err_msg = $this->add_widget($to_placeholders, $new_widget_name_or_zip_file, null, $theme, $position);
                    if (!empty($err_msg)) {
                        \Yii::warning("AddWidget error for widget '{$new_widget_name}' in theme '{$theme}': {$err_msg}");
                    }
                }
            } else {
                \Yii::warning("Widget '{$new_widget_name}' is not added to theme '{$theme}' because:" . ($new_widget_exists ? ' widget already exists' : '') . ($old_widget_exists ? ' old widget already exists - trying to rename' : ''));
                if ($old_widget_exists) {
                    $this->rename_widget_and_styles($old_widget_name, $new_widget_name, $rename_widget_styles_array ?? [], $theme);
                }
            }
        }
    }
    /**
     * @param string|array $placeholder it can be page_name or placeholder from widget_params
     * @param string $widget widget name or path to widget archive
     * @param string $ifNoWidget  $widget can be added to $placeholder only if $placeholder dont have $ifNoWidget
     * @param string $themeName
     * @param string|array $position string: 'start', 'middle', 'end'; array(widget settings of previous widget): ['setting_name' => 'setting_value']
     * @return string
     */
    public function add_widget($placeholder, $widget, $if_no_widget = '', $theme_name = '', $position = 'end')
    {
        try {
            if (is_string($placeholder)) {
                $placeholders = [$placeholder];
            } else {
                $placeholders = $placeholder;
            }
            if (is_file(DIR_FS_CATALOG . $widget)) {
                $widget_name = '';
                $widget_location = DIR_FS_CATALOG . $widget;
            } else {
                $main_widgets_path = DIR_FS_CATALOG . implode(DIRECTORY_SEPARATOR, ['lib', 'frontend', 'design', 'boxes']) . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $widget);
                $widget_arr = explode('\\', $widget);
                $extensions_path = DIR_FS_CATALOG . implode(DIRECTORY_SEPARATOR, ['lib', 'common', 'extensions']) . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $widget) . DIRECTORY_SEPARATOR . end($widget_arr);
                if (is_file($main_widgets_path . '.php') || is_file($extensions_path . '.php')) {
                    $widget_name = $widget;
                } else {
                    $this->print("\nError: {$extensions_path} don't have php file \n\n");
                    return "\"{$extensions_path}\" don't have php file";
                }
                if (is_file($main_widgets_path . '.zip')) {
                    $widget_location = $main_widgets_path . '.zip';
                } elseif (is_file($extensions_path . '.zip')) {
                    $widget_location = $extensions_path . '.zip';
                }
            }
            $theme_error = '';
            $_theme_name = $theme_name;
            if ($theme_name) {
                $_theme_name = str_replace('-mobile', '', $theme_name);
            }
            if ($theme_name && \common\models\Themes::find_one(['theme_name' => $_theme_name])) {
                $themes = [['theme_name' => $theme_name]];
            } else {
                $themes = \common\models\Themes::find()->as_array()->all();
                $themes_mobile = [];
                foreach ($themes as $theme) {
                    if (\common\models\Design_Boxes_Tmp::find_one(['theme_name' => $theme['theme_name'] . '-mobile'])) {
                        $themes_mobile[] = ['theme_name' => $theme['theme_name'] . '-mobile'];
                    }
                }
                $themes = array_merge($themes, $themes_mobile);
            }
            foreach ($themes as $theme) {
                $params = [];
                $block_names = [];
                foreach ($placeholders as $block_name) {
                    if (!str_contains($block_name, '-')) {
                        $block_names = [$block_name];
                        break;
                    }
                    $boxes = \common\models\Design_Boxes_Tmp::find()->where(['widget_params' => $block_name, 'theme_name' => $theme['theme_name']])->as_array()->all();
                    if ($boxes && is_array($boxes)) {
                        foreach ($boxes as $box) {
                            $block_names[] = 'block-' . $box['id'];
                        }
                        break;
                    }
                }
                if (!count($block_names)) {
                    if (str_contains(end($placeholders), '-')) {
                        $message = 'Placeholder "' . end($placeholders) . '" not found in theme "' . $theme['theme_name'] . '", widget "' . $widget . '" could not be installed in the theme' . "\n";
                        $this->print($message);
                        \Yii::warning($message);
                    }
                    $block_names = [end($placeholders)];
                }
                $params['theme_name'] = $theme['theme_name'];
                foreach ($block_names as $block_name) {
                    $params['block_name'] = $block_name;
                    if ($if_no_widget) {
                        $widgets = \backend\design\Theme::get_widgets_in_placeholder($block_name, $theme['theme_name']);
                        foreach ($widgets as $_widget) {
                            if ($_widget['widget_name'] == $if_no_widget) {
                                continue 2;
                            }
                        }
                    }
                    if (is_array($position)) {
                        $parent_box = \common\models\Design_Boxes_Tmp::find()->where(['widget_params' => $placeholder, 'theme_name' => $theme['theme_name']])->as_array()->one();
                        $db = \common\models\Design_Boxes_Tmp::find()->alias('db')->select(['db.sort_order'])->left_join(\common\models\Design_Boxes_Settings_Tmp::table_name() . ' dbs', 'db.id = dbs.box_id')->and_where(['db.theme_name' => $theme['theme_name']])->and_where(['or', ['db.block_name' => $placeholder], ['db.block_name' => 'block-' . ($parent_box['id'] ?? '')]]);
                        foreach ($position as $setting_name => $setting_value) {
                            $db->and_where(['dbs.setting_name' => $setting_name, 'dbs.setting_value' => $setting_value]);
                        }
                        $db_arr = $db->as_array()->one();
                        $params['sort_order'] = ($db_arr['sort_order'] ?? 0) + 1;
                        $boxes = \common\models\Design_Boxes_Tmp::find()->where(['block_name' => $block_name, 'theme_name' => $theme['theme_name']])->order_by('sort_order')->all();
                        foreach ($boxes as $box) {
                            if ($box->sort_order >= $params['sort_order']) {
                                $box->sort_order = $box->sort_order + 1;
                                $box->save();
                            }
                        }
                    } elseif ($position == 'end') {
                        $max = \common\models\Design_Boxes_Tmp::find()->where(['block_name' => $block_name, 'theme_name' => $theme['theme_name']])->max('sort_order');
                        $params['sort_order'] = $max + 1;
                    } else {
                        $boxes = \common\models\Design_Boxes_Tmp::find()->where(['block_name' => $block_name, 'theme_name' => $theme['theme_name']])->order_by('sort_order')->all();
                        if ($position == 'middle') {
                            $params['sort_order'] = round(count($boxes) / 2) + 1;
                            $sort_order = 1;
                            foreach ($boxes as $box) {
                                if ($sort_order == $params['sort_order']) {
                                    $sort_order++;
                                }
                                $box->sort_order = $sort_order;
                                $box->save();
                                $sort_order++;
                            }
                        } elseif ($position == 'start') {
                            $params['sort_order'] = 1;
                            $sort_order = 2;
                            foreach ($boxes as $box) {
                                $box->sort_order = $sort_order;
                                $box->save();
                                $sort_order++;
                            }
                        }
                    }
                    if ($widget_location ?? null) {
                        $import_block = \backend\design\Theme::import_block($widget_location, $params);
                        if (!is_array($import_block)) {
                            $this->print("\n" . $import_block . "\n");
                            $theme_error .= $theme['theme_name'] . ': error in ' . $import_block . "\n";
                        }
                    } elseif ($widget_name ?? null) {
                        $design_boxes = new \common\models\Design_Boxes_Tmp();
                        $design_boxes->microtime = microtime(true);
                        $design_boxes->theme_name = $theme['theme_name'];
                        $design_boxes->block_name = $block_name;
                        $design_boxes->widget_name = $widget_name;
                        $design_boxes->sort_order = $params['sort_order'];
                        $design_boxes->save();
                        if ($design_boxes->errors) {
                            $theme_error .= $theme['theme_name'] . ': sql error ' . "\n";
                        }
                    }
                }
                \backend\design\Theme::elements_save($params['theme_name']);
                \common\models\Design_Boxes_Cache::delete_all(['theme_name' => $params['theme_name']]);
            }
            return $theme_error;
        } catch (\Throwable $e) {
            \Yii::warning($e->get_message() . "\n" . $e->get_trace_as_string());
            return $e->get_message();
        }
    }
    public function remove_widget($widget_name)
    {
        $boxes = \common\models\Design_Boxes_Tmp::find()->where(['widget_name' => $widget_name])->as_array()->all();
        foreach ($boxes as $box) {
            \backend\design\Theme::delete_block($box['id'], true);
            \common\models\Design_Boxes_Settings::delete_all(['box_id' => $box['id']]);
            \common\models\Design_Boxes_Settings_Tmp::delete_all(['box_id' => $box['id']]);
            \common\models\Design_Boxes::delete_all(['id' => $box['id']]);
            \common\models\Design_Boxes_Tmp::delete_all(['id' => $box['id']]);
        }
    }
    /**
     * @param string $pageName block_name in design_boxes_tmp table
     * @param string $themeName
     * @return void
     */
    public function remove_page(string $page_name, string $theme_name = '')
    {
        $design_boxes = \common\models\Design_Boxes_Tmp::find()->where(['block_name' => $page_name]);
        if ($theme_name) {
            $design_boxes->and_where(['theme_name' => $theme_name]);
        }
        $boxes = $design_boxes->as_array()->all();
        foreach ($boxes as $box) {
            \backend\design\Theme::delete_block($box['id'], true);
            \common\models\Design_Boxes_Settings::delete_all(['box_id' => $box['id']]);
            \common\models\Design_Boxes_Settings_Tmp::delete_all(['box_id' => $box['id']]);
            \common\models\Design_Boxes::delete_all(['id' => $box['id']]);
            \common\models\Design_Boxes_Tmp::delete_all(['id' => $box['id']]);
        }
        \common\models\Themes_Settings::delete_all(['setting_group' => 'added_page', 'setting_value' => $page_name]);
    }
    /**
     * @param string $placeholder widget_params in design_boxes_tmp table
     * @return void
     */
    public function remove_block(string $placeholder)
    {
        $boxes = \common\models\Design_Boxes_Tmp::find()->where(['widget_params' => $placeholder])->as_array()->all();
        foreach ($boxes as $box) {
            \backend\design\Theme::delete_block($box['id'], true);
            \common\models\Design_Boxes_Settings::delete_all(['box_id' => $box['id']]);
            \common\models\Design_Boxes_Settings_Tmp::delete_all(['box_id' => $box['id']]);
            \common\models\Design_Boxes::delete_all(['id' => $box['id']]);
            \common\models\Design_Boxes_Tmp::delete_all(['id' => $box['id']]);
        }
    }
    /**
     * @param string $oldName existing name into design_boxes_tmp table. Looks like 'promotions\PromoList'
     * @param string $newName like 'Promotions\widgets\PromoList'
     * @param string $themeName null - for all themes
     * @return void
     */
    public function rename_widget(string $old_name, string $new_name, $theme_name = null)
    {
        $params = ['old_name' => $old_name];
        $theme_cond = '';
        if (!empty($theme_name)) {
            $theme_cond = ' AND (theme_name = :theme_name OR theme_name = :theme_mobile)';
            $params['theme_name'] = $theme_name;
            $params['theme_mobile'] = $theme_name . '-mobile';
        }
        $count = \common\models\Design_Boxes::update_all(['widget_name' => $new_name], 'widget_name = :old_name' . $theme_cond, $params);
        $this->print(sprintf("Widget renamed from %s to %s into design_boxes for %s theme(s): %d records\n", $old_name, $new_name, $theme_name ?? 'all', $count));
        $count = \common\models\Design_Boxes_Tmp::update_all(['widget_name' => $new_name], 'widget_name = :old_name' . $theme_cond, $params);
        $this->print(sprintf("Widget renamed from %s to %s into design_boxes_tmp for %s theme(s): %d records\n", $old_name, $new_name, $theme_name ?? 'all', $count));
        \common\models\Design_Boxes_Cache::delete_all();
    }
    public function rename_widget_and_styles(string $old_name, string $new_name, array $rename_widget_styles_array = [], $theme_name = null)
    {
        if (!$this->is_widget_exist($old_name, $theme_name)) {
            return;
        }
        $this->rename_widget($old_name, $new_name, $theme_name);
        if (is_array($rename_widget_styles_array)) {
            foreach ($rename_widget_styles_array as $old_style_name => $new_style_name) {
                $this->rename_widget_style($old_style_name, $new_style_name, $theme_name);
            }
        }
    }
    /**
     * @param string $oldName like '.w-product-promotions'
     * @param string $newName like '.w-promotions-widgets-promotions'
     * @return void
     */
    public function rename_widget_style(string $old_name, string $new_name, $theme_name = null)
    {
        $params = ['old_name' => $old_name];
        $theme_cond = '';
        if (!empty($theme_name)) {
            $theme_cond = ' AND (theme_name = :theme_name OR theme_name = :theme_mobile)';
            $params['theme_name'] = $theme_name;
            $params['theme_mobile'] = $theme_name . '-mobile';
        }
        $count = \common\models\Themes_Styles::update_all(['selector' => new \yii\db\Expression("REPLACE(selector, '{$old_name}', '{$new_name}')"), 'accessibility' => $new_name], 'accessibility = :old_name' . $theme_cond, $params);
        $this->print(sprintf("Widget style renamed from %s to %s: %d records\n", $old_name, $new_name, $count));
        if ($count) {
            \backend\design\Style::invalidate_cache();
        }
    }
    public function update_theme($theme_name, $migration_path)
    {
        $this->print("\nMigration for " . $theme_name . " theme \n");
        if (!\common\models\Design_Boxes::find(['theme_name' => $theme_name])) {
            $this->print($theme_name . " theme not found \n");
            return '';
        }
        $file_path = rtrim(DIR_FS_CATALOG, '/\\') . DIRECTORY_SEPARATOR . trim($migration_path, DIRECTORY_SEPARATOR);
        if (!is_file($file_path)) {
            $this->print('Migration file not found: ' . $file_path . " \n");
            \Yii::warning('Migration file not found: ' . $file_path);
            return '';
        }
        $migration = json_decode(file_get_contents($file_path), true);
        if ($result = \backend\design\Steps::apply_migration($theme_name, $migration)) {
            \backend\design\Theme::elements_save($theme_name);
            \common\models\Design_Boxes_Cache::delete_all(['theme_name' => $theme_name]);
            \backend\design\Theme::save_theme_version($theme_name);
            $this->print($result . "\n");
            return '';
        }
        $this->print("Migration not applied \n");
    }
    /**
     * @param $code string class name of extension
     */
    public function install_ext(string $code)
    {
        $res = \common\helpers\Extensions::install_safe($code);
        if (!is_null($res)) {
            $this->print("Error while installing {$code}: {$res}\n");
        } else {
            $this->print("Extension {$code} was installed successfully\n");
        }
    }
    public function uninstall_ext(string $code)
    {
        $res = \common\helpers\Extensions::uninstall_safe($code);
        if (!is_null($res)) {
            $this->print("Error while uninstalling {$code}: {$res}\n");
        } else {
            $this->print("Extension {$code} was uninstalled successfully\n");
        }
    }
    public function reinstall_ext_translation(string $code)
    {
        if ($ext = \common\helpers\Extensions::is_allowed($code)) {
            $ext::reinstall_translation($this);
            $this->print("Translations were reinstalled for extension {$code}\n");
        } else {
            $this->print("Translations were not reinstalled for extension {$code}: it is not enabled");
        }
    }
    public function is_old_project()
    {
        $res = defined('OLD_PROJECT') && OLD_PROJECT == true;
        if ($res) {
            $this->print("Changes is not applied due OLD_PROJECT constant\n");
        }
        return $res;
    }
    public function is_old_extension($code)
    {
        $res = $this->is_old_project();
        if (!$res) {
            $res = defined("OLD_{$code}") && constant("OLD_{$code}") == true || class_exists("\\common\\extensions\\{$code}\\{$code}") && !class_exists("\\common\\extensions\\{$code}\\Setup");
            if ($res) {
                $this->print("Changes is not applied due OLD_{$code} constant\n");
            }
        }
        return $res;
    }
    /**
     * Add page for all themes
     * @param string $pageName
     * @param string $pageGroup
     * @return void
     */
    public function add_theme_page(string $page_name, string $page_group = 'info')
    {
        $arr = [
            'setting_group' => 'added_page',
            // special sign to add page
            'setting_name' => $page_group,
            'setting_value' => $page_name,
        ];
        foreach (\common\models\Themes::find()->all() as $theme) {
            $arr['theme_name'] = $theme->theme_name;
            if (empty(\common\models\Themes_Settings::find_one($arr))) {
                $settings = new \common\models\Themes_Settings();
                $settings->set_attributes($arr);
                $settings->save(false);
            }
            $arr['theme_name'] = $theme->theme_name . '-mobile';
            if (empty(\common\models\Themes_Settings::find_one($arr))) {
                $settings = new \common\models\Themes_Settings();
                $settings->set_attributes($arr);
                $settings->save(false);
            }
        }
    }
    public function remove_theme_page(string $page_name)
    {
        $this->remove_page($page_name);
    }
    /**
     * @param array $attrOrigin
     * @param $makePublicAndActive
     * @return void
     * @example
     *   $migrate->addInfoPage([
     *     'info_title' => 'Support',
     *     'page_title' => 'Support',
     *     'seo_page_name' => 'support',
     *     'template_name' => 'ext-support-system',
     *   ]);
     */
    public function add_info_page(array $attr_origin, $make_public_and_active = true)
    {
        static $information_id = null;
        $defaults = ['visible' => 1, 'type' => 1, 'date_added' => new \yii\db\Expression('NOW()'), 'last_modified' => new \yii\db\Expression('NOW()')];
        $attr = array_merge($defaults, $attr_origin);
        $languages = \common\helpers\Language::get_languages();
        $platforms = \common\classes\platform::get_list(false);
        foreach ($languages as $language) {
            $attr_origin['languages_id'] = $language['id'];
            $attr['languages_id'] = $language['id'];
            foreach ($platforms as $platform) {
                $attr_origin['platform_id'] = $platform['id'];
                $attr['platform_id'] = $platform['id'];
                if (!empty(\common\models\Information::find_one($attr_origin))) {
                    continue;
                }
                $model = new \common\models\Information();
                $model->load_default_values();
                $model->set_attributes($attr);
                if (!is_null($information_id)) {
                    $model->information_id = $information_id;
                }
                try {
                    $model->save(false);
                    $information_id = $model->information_id;
                } catch (\Throwable $e) {
                    \Yii::warning($e->get_message() . ' ' . $e->get_trace_as_string());
                }
            }
        }
        if ($make_public_and_active) {
            \common\helpers\Page_Status::save_scheduled_statuses('information', $information_id, ['action' => ['public'], 'period' => ['once'], 'day' => [''], 'date' => ['']]);
            $status = \common\models\Page_Status::find_one(['page_id' => $information_id]);
            if ($status) {
                $status->status = 'public';
                $status->save(false);
            }
        }
        $information_id = null;
    }
    public function remove_info_page(array $attr_array, bool $more_than_one = false)
    {
        $ids = \common\models\Information::find()->select('information_id')->where($attr_array)->distinct()->column();
        if (!$more_than_one && count($ids) > 1) {
            \Yii::warning('More than one information pages found: deletion failed');
            return false;
        }
        \common\models\Page_Status::delete_all(['page_id' => $ids]);
        \common\models\Information::delete_all(['information_id' => $ids]);
        return true;
    }
    public function print($msg)
    {
        if (!$this->compact) {
            echo $msg;
        }
    }
}