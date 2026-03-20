<?php

declare (strict_types=1);
namespace common\helpers;

use common\models\Categories_Description;
class Categories_Description_Helper
{
    /**
     * @param int $currentCategoryId
     * @param int $languagesId
     * @param int $session
     * @param int|bool $customerGroupsId
     * @param bool $groupJoin
     * @param bool $groupWhere
     *
     * @return array|null
     */
    public static function get_categories_description_list($current_category_id, $languages_id, $session, $customer_groups_id = false, $group_join = false, $group_where = false)
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $category = Categories_Description::find()->alias('cd')->select(['c.categories_id AS id', "(CASE WHEN cd1.categories_name = '' THEN cd.categories_name ELSE cd1.categories_name END) AS categories_name", "(CASE WHEN cd1.categories_heading_title = '' THEN cd.categories_heading_title ELSE cd1.categories_heading_title END) AS categories_heading_title", "(CASE WHEN cd1.categories_description = '' THEN cd.categories_description ELSE cd1.categories_description END) AS categories_description", 'c.categories_image', 'c.banners_group', 'cd.noindex_option', 'cd.nofollow_option', 'cd.rel_canonical', 'cd.categories_h1_tag']);
        $category->inner_join_with(['categories c'], false);
        $category->left_join('categories_description cd1', 'cd1.categories_id = c.categories_id');
        if ($group_join && $model = \common\helpers\Acl::check_extension_table_exist('UserGroupsRestrictions', 'GroupsCategories')) {
            $category->inner_join($model::table_name() . ' gc', 'gc.categories_id=c.categories_id');
        }
        $category->where(['c.categories_id' => $current_category_id])->and_where(['cd1.language_id' => $languages_id, 'cd1.affiliate_id' => $session, 'cd.categories_id' => $current_category_id, 'cd.language_id' => $languages_id, 'c.categories_status' => 1]);
        if ($group_where && \common\helpers\Acl::check_extension_table_exist('UserGroupsRestrictions', 'GroupsCategories')) {
            $category->and_where(['gc.groups_id' => $customer_groups_id]);
        }
        return $category->as_array()->one();
    }
}