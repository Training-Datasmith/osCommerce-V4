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
namespace backend\controllers;

use common\helpers\Affiliate;
use Yii;
/**
 * default controller to handle user requests.
 */
class Email_Controller extends Sceleton
{
    public $acl = ['BOX_HEADING_DESIGN_CONTROLS', 'BOX_TRANSLATION_EMAIL_TEMPLATES'];
    public function action_index()
    {
        global $language;
        \common\helpers\Translation::init('admin/email/templates');
        $this->view->heading_title = HEADING_TITLE;
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('email/'), 'title' => HEADING_TITLE];
        $this->selected_menu = ['design_controls', 'email/templates'];
        $customers = [];
        $customers[] = ['id' => '', 'text' => TEXT_SELECT_CUSTOMER];
        $customers[] = ['id' => '***', 'text' => TEXT_ALL_CUSTOMERS];
        /** @var \common\extensions\Subscribers\Subscribers $subscr  */
        if ($subscr = \common\helpers\Acl::check_extension_allowed('Subscribers', 'allowed')) {
            $customers[] = ['id' => '**D', 'text' => TEXT_NEWSLETTER_CUSTOMERS];
        }
        $mail_query = tep_db_query('select customers_email_address, customers_firstname, customers_lastname from ' . TABLE_CUSTOMERS . ' ' . Affiliate::where_if_exists('', 'where ') . ' order by customers_lastname');
        while ($customers_values = tep_db_fetch_array($mail_query)) {
            $customers[] = ['id' => $customers_values['customers_email_address'], 'text' => $customers_values['customers_lastname'] . ', ' . $customers_values['customers_firstname'] . ' (' . $customers_values['customers_email_address'] . ')'];
        }
        if (Yii::$app->request->is_ajax) {
            $this->layout = false;
        }
        return $this->render('index', ['customers' => $customers]);
    }
    public function action_templates()
    {
        \common\helpers\Acl::check_access(['MANAGE_EMAIL_TEMPLATES']);
        global $language;
        $this->selected_menu = ['design_controls', 'email/templates'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('email/templates'), 'title' => HEADING_TITLE];
        $this->view->heading_title = HEADING_TITLE;
        $this->view->groups_table = [['title' => TABLE_HEADING_EMAIL_TEMPLATES, 'not_important' => 1]];
        $this->view->filters = new \stdClass();
        $this->view->filters->row = (int) Yii::$app->request->get('row', 0);
        $this->view->insert_template = \common\helpers\Acl::rule(['MANAGE_EMAIL_TEMPLATES', 'INSERT_EMAIL_TEMPLATES']);
        if ($this->view->insert_template == true) {
            $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url('email/template-edit') . '" class="btn btn-primary">' . IMAGE_INSERT . '</a>';
        }
        $type_id = (int) Yii::$app->request->get('type_id', 0);
        $messages = [];
        if (isset($_SESSION['messages'])) {
            $messages = $_SESSION['messages'];
            unset($_SESSION['messages']);
        }
        if (!is_array($messages)) {
            $messages = [];
        }
        return $this->render('templates', ['messages' => $messages, 'type_id' => $type_id, 'types' => \common\helpers\Mail::get_type_list(true)]);
    }
    public function action_templates_list()
    {
        \common\helpers\Translation::init('admin/email/templates');
        $draw = Yii::$app->request->get('draw', 1);
        $start = Yii::$app->request->get('start', 0);
        $length = Yii::$app->request->get('length', 10);
        $response_list = [];
        if ($length == -1) {
            $length = 10000;
        }
        $query_numrows = 0;
        //TODO search
        $search_condition = '';
        if (isset($_GET['search']['value']) && tep_not_null($_GET['search']['value'])) {
            $keywords = tep_db_input(tep_db_prepare_input($_GET['search']['value']));
            $search_condition = "AND email_templates_key like '%" . $keywords . "%' ";
        }
        if (isset($_GET['order'][0]['column']) && $_GET['order'][0]['dir']) {
            switch ($_GET['order'][0]['column']) {
                case 0:
                    $order_by = 'email_templates_key ' . tep_db_input(tep_db_prepare_input($_GET['order'][0]['dir']));
                    break;
                case 1:
                    $order_by = 'email_template_type ' . tep_db_input(tep_db_prepare_input($_GET['order'][0]['dir']));
                    break;
                default:
                    $order_by = 'email_templates_key, email_template_type';
                    break;
            }
        } else {
            $order_by = 'email_templates_key, email_template_type';
        }
        $form_filter = Yii::$app->request->get('filter');
        parse_str($form_filter, $filter);
        if ($filter['type_id'] > 0) {
            $search_condition .= " and type_id = '" . (int) $filter['type_id'] . "'";
        }
        $groups_query_raw = 'select email_templates_id, email_templates_key, email_template_type from ' . TABLE_EMAIL_TEMPLATES . " where 1 {$search_condition} group by email_templates_key order by {$order_by}";
        $current_page_number = $start / $length + 1;
        $_split = new \Split_Page_Results($current_page_number, $length, $groups_query_raw, $query_numrows, 'email_templates_key');
        $groups_query = tep_db_query($groups_query_raw);
        while ($email_templates = tep_db_fetch_array($groups_query)) {
            $name_key = 'TEXT_EMAIL_' . str_replace(' ', '_', strtoupper($email_templates['email_templates_key']));
            $email_templates['email_templates_key'] = defined($name_key) ? constant($name_key) : $email_templates['email_templates_key'];
            $response_list[] = ['<div class="click_double" data-click-double="' . \Yii::$app->url_manager->create_url(['email/template-edit', 'tpl_id' => $email_templates['email_templates_id']]) . '">' . $email_templates['email_templates_key'] . '<input class="cell_identify" type="hidden" value="' . $email_templates['email_templates_id'] . '"></div>'];
        }
        $response = ['draw' => $draw, 'recordsTotal' => $query_numrows, 'recordsFiltered' => $query_numrows, 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_templatepreedit($item_id = null)
    {
        $this->layout = false;
        \common\helpers\Translation::init('admin/email/templates');
        if ($item_id === null) {
            $item_id = (int) Yii::$app->request->post('item_id');
        }
        $get_template_r = tep_db_query('select email_templates_id, email_templates_key, email_template_type from ' . TABLE_EMAIL_TEMPLATES . " where email_templates_id='" . (int) $item_id . "'");
        if (tep_db_num_rows($get_template_r) > 0) {
            $et_info = new \Object_Info(tep_db_fetch_array($get_template_r));
            $item_id = intval($et_info->email_templates_id);
            ?>
        <div class="or_box_head or_box_head_no_margin"><?php 
            $name_key = 'TEXT_EMAIL_' . str_replace(' ', '_', strtoupper($et_info->email_templates_key));
            echo defined($name_key) ? constant($name_key) : $et_info->email_templates_key;
            ?></div>
        <div class="row_or_wrapp">
        </div>
        <div class="btn-toolbar btn-toolbar-order">
          <a class="btn btn-process-order btn-edit btn-primary" href="<?php 
            echo \Yii::$app->url_manager->create_url(['email/template-edit', 'tpl_id' => $et_info->email_templates_id]);
            ?>"><?php 
            echo IMAGE_EDIT;
            ?></a>
<?php 
            if (\common\helpers\Acl::rule(['MANAGE_EMAIL_TEMPLATES', 'DELETE_EMAIL_TEMPLATES'])) {
                ?>
          <button onclick="return deleteItemConfirm(<?php 
                echo $item_id;
                ?>)" class="btn btn-delete btn-no-margin btn-process-order "><?php 
                echo IMAGE_DELETE;
                ?></button>
<?php 
            }
            ?>
          <a class="btn btn-process-order btn-edit " href="<?php 
            echo \Yii::$app->url_manager->create_url(['email/template-edit', 'from_tpl_id' => $et_info->email_templates_id]);
            ?>"><?php 
            echo IMAGE_COPY;
            ?></a>
        </div>
        <?php 
            //<button class="btn btn-delete" onclick="return previewItem( <_?php echo $item_id; ?_>)"><_?=IMAGE_PREVIEW?_><!--</button>-->
        }
    }
    public function action_confirmitemdelete()
    {
        \common\helpers\Translation::init('admin/email/templates');
        $this->layout = false;
        $item_id = (int) Yii::$app->request->post('item_id');
        $get_template_r = tep_db_query('select email_templates_id, email_templates_key, email_template_type from ' . TABLE_EMAIL_TEMPLATES . " where email_templates_id='" . (int) $item_id . "'");
        if (tep_db_num_rows($get_template_r) > 0) {
            $et_info = new \Object_Info(tep_db_fetch_array($get_template_r));
            $item_id = intval($et_info->email_templates_id);
            echo '<div class="or_box_head">' . TEXT_HEADING_DELETE . '</div>';
            echo tep_draw_form('groups', 'email/templates', '', 'post', 'id="item_delete" onsubmit="return deleteItem();"');
            echo '<div class="row_fields">' . TEXT_DELETE_INTRO . '</div>';
            //echo '<div class="row_fields"><b>' . $etInfo->groups_name . '</b></div>';
            echo '<div class="btn-toolbar btn-toolbar-order"><button class="btn btn-delete btn-no-margin">' . IMAGE_DELETE . '</button><input type="button" class="btn btn-cancel" value="' . IMAGE_CANCEL . '" onClick="return resetStatement()"></div>';
            echo tep_draw_hidden_field('item_id', $item_id);
            echo '</form>';
        }
    }
    public function action_itemdelete()
    {
        $this->layout = false;
        $template_id = (int) Yii::$app->request->post('item_id');
        $html_id = false;
        $text_id = false;
        $info = tep_db_fetch_array(tep_db_query('select email_templates_id, email_templates_key, email_template_type from ' . TABLE_EMAIL_TEMPLATES . " where email_templates_id='" . (int) $template_id . "'"));
        $html_id = (int) $info['email_templates_id'];
        $info2 = tep_db_fetch_array(tep_db_query('select email_templates_id, email_templates_key, email_template_type from ' . TABLE_EMAIL_TEMPLATES . ' ' . "where email_templates_key='" . tep_db_input($info['email_templates_key']) . "' AND email_template_type='" . ($info['email_template_type'] == 'html' ? 'plaintext' : 'html') . "' "));
        $text_id = (int) $info2['email_templates_id'];
        tep_db_query('delete from ' . TABLE_EMAIL_TEMPLATES . " where email_templates_id = '" . (int) $html_id . "'");
        tep_db_query('delete from ' . TABLE_EMAIL_TEMPLATES . " where email_templates_id = '" . (int) $text_id . "'");
        tep_db_query('delete from ' . TABLE_EMAIL_TEMPLATES_TEXTS . " where email_templates_id = '" . (int) $html_id . "'");
        tep_db_query('delete from ' . TABLE_EMAIL_TEMPLATES_TEXTS . " where email_templates_id = '" . (int) $text_id . "'");
        \common\models\Email_Templates_To_Design_Template::delete_all(['email_templates_id' => (int) $html_id]);
    }
    public function action_template_edit($item_id = null)
    {
        $this->selected_menu = ['design_controls', 'email/templates'];
        \common\helpers\Translation::init('admin/email/templates');
        $template_id = (int) Yii::$app->request->get('tpl_id', $item_id);
        $this->top_buttons[] = '<span class="btn btn-confirm" onclick="$(\'#save_email_form\').trigger(\'submit\')">' . IMAGE_SAVE . '</span>';
        $_copy = false;
        if ($template_id == 0) {
            $template_id = (int) Yii::$app->request->get('from_tpl_id', 0);
            if ($template_id > 0) {
                $_copy = true;
            }
        }
        $html_id = false;
        $text_id = false;
        $info = tep_db_fetch_array(tep_db_query('select email_templates_id, email_templates_key, email_template_type, type_id from ' . TABLE_EMAIL_TEMPLATES . " where email_templates_id='" . (int) $template_id . "'"));
        $info = $this->init_info_array_if_empty($info);
        if ($info['email_template_type'] == 'html') {
            $html_id = (int) $info['email_templates_id'];
        } else {
            $text_id = (int) $info['email_templates_id'];
        }
        $info2 = tep_db_fetch_array(tep_db_query('select email_templates_id, email_templates_key, email_template_type from ' . TABLE_EMAIL_TEMPLATES . ' ' . "where email_templates_key='" . tep_db_input($info['email_templates_key']) . "' AND email_template_type='" . ($info['email_template_type'] == 'html' ? 'plaintext' : 'html') . "' "));
        $info2 = $this->init_info_array_if_empty($info2);
        if ($info2['email_template_type'] == 'html') {
            $html_id = (int) $info2['email_templates_id'];
        } else {
            $text_id = (int) $info2['email_templates_id'];
        }
        $c_description_html = [];
        $c_description_text = [];
        $design_templates = [];
        $platforms = \common\classes\platform::get_list(false);
        $languages = \common\helpers\Language::get_languages();
        foreach ($platforms as $platform) {
            $design_templates[$platform['id']]['design_templates'] = \common\helpers\Mail::get_email_design_templates((int) $html_id, $platform['id']);
            for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
                $languages[$i]['logo'] = $languages[$i]['image'];
                $c_description_html[$platform['id']][$i] = [];
                $c_description_html[$platform['id']][$i]['code'] = $languages[$i]['code'];
                if ($html_id) {
                    $c_description_html[$platform['id']][$i]['email_templates_subject'] = tep_draw_input_field('email_templates_subject[' . $platform['id'] . '][html][' . $languages[$i]['id'] . ']', \common\helpers\Mail::get_email_templates_subject((int) $html_id, $languages[$i]['id'], $platform['id']), 'class="form-control"');
                    $c_description_html[$platform['id']][$i]['email_templates_body'] = \common\helpers\Html::textarea('email_templates_body[' . $platform['id'] . '][html][' . $languages[$i]['id'] . ']', \common\helpers\Mail::get_email_templates_body((int) $html_id, $languages[$i]['id'], $platform['id']), ['wrap' => 'soft', 'cols' => '70', 'rows' => '15', 'class' => 'form-control' . ($info['email_template_type'] == 'html' ? ' ckeditor' : ''), 'id' => 'htmldesc' . $platform['id'] . '_' . $languages[$i]['id']]);
                    $c_description_html[$platform['id']][$i]['c_link'] = 'htmldesc' . $platform['id'] . '_' . $languages[$i]['id'];
                } else {
                    $c_description_html[$platform['id']][$i]['email_templates_subject'] = tep_draw_input_field('email_templates_subject[' . $platform['id'] . '][html][' . $languages[$i]['id'] . ']', '', 'class="form-control"');
                    $c_description_html[$platform['id']][$i]['email_templates_body'] = tep_draw_textarea_field('email_templates_body[' . $platform['id'] . '][html][' . $languages[$i]['id'] . ']', 'soft', '70', '15', '', 'class="ckeditor form-control" id="htmldesc' . $platform['id'] . '_' . $languages[$i]['id'] . '"');
                    $c_description_html[$platform['id']][$i]['c_link'] = 'htmldesc' . $platform['id'] . '_' . $languages[$i]['id'];
                }
                $c_description_text[$platform['id']][$i] = [];
                $c_description_text[$platform['id']][$i]['code'] = $languages[$i]['code'];
                if ($text_id) {
                    $c_description_text[$platform['id']][$i]['email_templates_subject'] = tep_draw_input_field('email_templates_subject[' . $platform['id'] . '][plaintext][' . $languages[$i]['id'] . ']', \common\helpers\Mail::get_email_templates_subject((int) $text_id, $languages[$i]['id'], $platform['id']), 'class="form-control"');
                    $c_description_text[$platform['id']][$i]['email_templates_body'] = tep_draw_textarea_field('email_templates_body[' . $platform['id'] . '][plaintext][' . $languages[$i]['id'] . ']', 'soft', '70', '15', \common\helpers\Mail::get_email_templates_body((int) $text_id, $languages[$i]['id'], $platform['id']), 'class="form-control" id="textdesc' . $platform['id'] . '_' . $languages[$i]['id'] . '"');
                    $c_description_text[$platform['id']][$i]['c_link'] = 'textdesc' . $platform['id'] . '_' . $languages[$i]['id'];
                } else {
                    $c_description_text[$platform['id']][$i]['email_templates_subject'] = tep_draw_input_field('email_templates_subject[' . $platform['id'] . '][plaintext][' . $languages[$i]['id'] . ']', '', 'class="form-control"');
                    $c_description_text[$platform['id']][$i]['email_templates_body'] = tep_draw_textarea_field('email_templates_body[' . $platform['id'] . '][plaintext][' . $languages[$i]['id'] . ']', 'soft', '70', '15', '', 'class="form-control" id="textdesc' . $platform['id'] . '_' . $languages[$i]['id'] . '"');
                    $c_description_text[$platform['id']][$i]['c_link'] = 'textdesc' . $platform['id'] . '_' . $languages[$i]['id'];
                }
            }
        }
        $this->view->heading_title = $info['email_templates_key'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('email/templates'), 'title' => HEADING_TITLE];
        $name_key = 'TEXT_EMAIL_' . str_replace(' ', '_', strtoupper($info['email_templates_key']));
        $info['email_templates_key'] = defined($name_key) ? constant($name_key) : $info['email_templates_key'];
        if ($template_id == 0 || $_copy) {
            $info['email_templates_key'] = tep_draw_input_field('email_templates_key', '', 'required class="form-control" placeholder="' . TEXT_EMAIL_TEMPLATE_KEY . '"');
        } else {
            $info['email_templates_key'] .= tep_draw_hidden_field('email_templates_key', $info['email_templates_key']);
        }
        return $this->render('templates-edit', ['email_templates_key' => $info['email_templates_key'], 'languages' => $languages, 'designTemplates' => $design_templates, 'cDescriptionHtml' => $c_description_html, 'cDescriptionText' => $c_description_text, 'email_templates_id' => $_copy ? 0 : (int) $template_id, 'platforms' => $platforms, 'isMultiPlatforms' => \common\classes\platform::is_multi(), 'default_platform_id' => \common\classes\platform::default_id(), 'types' => \common\helpers\Mail::get_type_list(true), 'type_id' => $info['type_id']]);
    }
    public function action_templates_keys()
    {
        \common\helpers\Translation::init('keys');
        $this->layout = false;
        $keys_list = [];
        $keys_list[0] = ['text' => BOX_CONFIGURATION_MYSTORE, 'child' => ['##STORE_NAME##', '##HTTP_HOST##', '##STORE_OWNER_EMAIL_ADDRESS##', '##SECURITY_KEY##']];
        $keys_list[1] = ['text' => BOX_CUSTOMERS_CUSTOMERS, 'child' => ['##CUSTOMER_EMAIL##', '##CUSTOMER_FIRSTNAME##', '##CUSTOMER_LASTNAME##', '##NEW_PASSWORD##', '##USER_GREETING##']];
        $keys_list[2] = ['text' => BOX_CUSTOMERS_ORDERS, 'child' => ['##ORDER_NUMBER##', '##ORDER_DATE_LONG##', '##ORDER_DATE_SHORT##', '##BILLING_ADDRESS##', '##DELIVERY_ADDRESS##', '##PAYMENT_METHOD##', '##ORDER_COMMENTS##', '##NEW_ORDER_STATUS##', '##ORDER_TOTALS##', '##PRODUCTS_ORDERED##', '##ORDER_INVOICE_URL##', '##TRACKING_NUMBER##', '##TRACKING_NUMBER_URL##']];
        $keys_list[3] = ['text' => BOX_HEADING_GV_ADMIN, 'child' => ['##COUPON_AMOUNT##', '##COUPON_NAME##', '##COUPON_DESCRIPTION##', '##COUPON_CODE##']];
        if (\Yii::$app->request->get('email_templates_key') == 'Wedding invitation') {
            $keys_list = [];
            $keys_list[0] = ['text' => 'Wedding invitation', 'child' => ['##STORE_NAME##', '##INVITED_EMAIL##', '##INVITED_NAME##', '##FROM_FIRSTNAME##', '##FROM_LASTNAME##', '##FROM_EMAIL_ADDRESS##', '##SHARE_LINK##']];
        }
        foreach (\common\helpers\Hooks::get_list('email/template-keys') as $filename) {
            include $filename;
        }
        if (\common\helpers\Extensions::is_allowed('Testimonials')) {
            $keys_list[0]['child'][] = '##STORE_TESTIMONIALS_URL##';
        }
        if (\common\helpers\Extensions::is_allowed('MailSurvay')) {
            $keys_list[2]['child'][] = '##PRODUCTS_ORDERED_REVIEW##';
        }
        //        $keysList[] = ['id' => 1,'type' => 'item','text' => '&nbsp;&nbsp;Firstname'];
        //        $keysList[] = ['id' => 2,'type' => 'item','text' => '&nbsp;&nbsp;Lastname'];
        //        $keysList[] = ['id' => 3,'type' => 'group','text' => BOX_CUSTOMERS_ORDERS];
        echo '<div class="pageLinksWrapper">';
        echo '<select name="key" class="form-control">';
        foreach ($keys_list as $keys) {
            echo '<optgroup label="' . htmlspecialchars($keys['text']) . '">' . "\n";
            foreach ($keys['child'] as $key => $value) {
                echo '<option value="' . $value . '">' . (defined($value) ? constant($value) : $value) . '</option>';
            }
            echo '</optgroup>';
        }
        echo '</select>';
        //'<div class="pageLinksWrapper">'.tep_draw_pull_down_menu('category_id', $keysList, '', 'class="form-control"') .
        echo '</div>';
        ?>

            <div class="pageLinksButton">
                <button class="btn btn-no-margin"><?php 
        echo IMAGE_INSERT;
        ?></button>
            </div>
<script type="text/javascript">
  (function($){
    $(function(){
      var oEditor = CKEDITOR.instances.<?php 
        echo $_GET['id_ckeditor'];
        ?>;
      if (oEditor != undefined) {
      if(oEditor.mode == 'wysiwyg') {
      $('.pageLinksButton .btn').click(function(){
        if($('select[name="key"]').val() != ''){
            oEditor.focus();
            if(oEditor.getSelection().getRanges()[0].collapsed == false){
                var fragment = oEditor.getSelection().getRanges()[0].extractContents();
                var container = CKEDITOR.dom.element.createFromHtml($('select[name="key"]').val(), oEditor.document);
                //fragment.appendTo(container);
                //oEditor.insertElement(container);
                var html = $('select[name="key"]').val();
                oEditor.insertHtml(html);
            } else {

                var html = $('select[name="key"]').val();
                oEditor.insertHtml(html);
                //var newElement = CKEDITOR.dom.element.createFromHtml( html, oEditor.document );
                //oEditor.insertElement( newElement );
            }
        }
        $(this).parents('.popup-box-wrap').remove();
      })
        } else {
            $('.pageLinksWrapper').html('<?php 
        echo TEXT_PLEASE_TURN;
        ?>');
            $('.pageLinksButton').hide();
        }
      } else {
        $('.pageLinksButton .btn').click(function(){
            if($('select[name="key"]').val() != ''){
                var html = $('select[name="key"]').val();
                insertAtCaret('<?php 
        echo $_GET['id_ckeditor'];
        ?>', html)
            }
            $(this).parents('.popup-box-wrap').remove();
        })
      }
    })
  })(jQuery)
</script>
        <?php 
    }
    public function action_templates_save()
    {
        $this->layout = false;
        \common\helpers\Translation::init('admin/email/templates');
        $template_id = (int) Yii::$app->request->post('email_templates_id');
        $html_id = false;
        $text_id = false;
        $info = tep_db_fetch_array(tep_db_query('select email_templates_id, email_templates_key, email_template_type from ' . TABLE_EMAIL_TEMPLATES . " where email_templates_id='" . (int) $template_id . "'"));
        $info = $this->init_info_array_if_empty($info);
        if ($info['email_template_type'] == 'html') {
            $html_id = (int) $info['email_templates_id'];
        } else {
            $text_id = (int) $info['email_templates_id'];
        }
        $info2 = tep_db_fetch_array(tep_db_query('select email_templates_id, email_templates_key, email_template_type from ' . TABLE_EMAIL_TEMPLATES . ' ' . "where email_templates_key='" . $info['email_templates_key'] . "' AND email_template_type='" . ($info['email_template_type'] == 'html' ? 'plaintext' : 'html') . "' "));
        $info2 = $this->init_info_array_if_empty($info2);
        if ($info2['email_template_type'] == 'html') {
            $html_id = (int) $info2['email_templates_id'];
        } else {
            $text_id = (int) $info2['email_templates_id'];
        }
        if ($html_id == 0) {
            tep_db_perform(TABLE_EMAIL_TEMPLATES, ['email_templates_key' => Yii::$app->request->post('email_templates_key'), 'email_template_type' => 'html']);
            $template_id = $html_id = tep_db_insert_id();
        }
        if ($text_id == 0) {
            tep_db_perform(TABLE_EMAIL_TEMPLATES, ['email_templates_key' => Yii::$app->request->post('email_templates_key'), 'email_template_type' => 'plaintext']);
            $text_id = tep_db_insert_id();
            if ($html_id == 0) {
                $template_id = $text_id;
            }
        }
        tep_db_perform(TABLE_EMAIL_TEMPLATES, ['type_id' => (int) Yii::$app->request->post('type_id')], 'update', "email_templates_id = '" . (int) $text_id . "'");
        tep_db_perform(TABLE_EMAIL_TEMPLATES, ['type_id' => (int) Yii::$app->request->post('type_id')], 'update', "email_templates_id = '" . (int) $html_id . "'");
        $platforms = \common\classes\platform::get_list(false);
        $design_template = Yii::$app->request->post('design_template', '');
        $languages = \common\helpers\Language::get_languages();
        foreach ($platforms as $platform) {
            $template = \common\models\Email_Templates_To_Design_Template::find_one(['email_templates_id' => $template_id, 'platform_id' => $platform['id']]);
            if ($design_template[$platform['id']]) {
                if (!$template) {
                    $template = new \common\models\Email_Templates_To_Design_Template();
                }
                $template->attributes = ['email_templates_id' => $template_id, 'platform_id' => $platform['id'], 'email_design_template' => $design_template[$platform['id']]];
                $template->save();
            } elseif ($template) {
                $template->delete();
            }
            for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
                if ($html_id && isset($_POST['email_templates_subject'][$platform['id']]['html'])) {
                    $update_template_id = $html_id;
                    $email_templates_subject = tep_db_prepare_input($_POST['email_templates_subject'][$platform['id']]['html'][$languages[$i]['id']]);
                    $email_templates_body = tep_db_prepare_input($_POST['email_templates_body'][$platform['id']]['html'][$languages[$i]['id']]);
                    $check = tep_db_fetch_array(tep_db_query('SELECT COUNT(*) AS echeck FROM ' . TABLE_EMAIL_TEMPLATES_TEXTS . ' ' . "WHERE email_templates_id='" . (int) $update_template_id . "' AND language_id='" . (int) $languages[$i]['id'] . "' AND affiliate_id=0 and platform_id = '" . $platform['id'] . "'"));
                    if ($check['echeck'] > 0) {
                        tep_db_perform(TABLE_EMAIL_TEMPLATES_TEXTS, ['email_templates_subject' => $email_templates_subject, 'email_templates_body' => $email_templates_body], 'update', "email_templates_id='" . (int) $update_template_id . "' AND language_id='" . (int) $languages[$i]['id'] . "' AND affiliate_id=0 and platform_id = '" . $platform['id'] . "'");
                    } else {
                        tep_db_perform(TABLE_EMAIL_TEMPLATES_TEXTS, ['email_templates_id' => (int) $update_template_id, 'language_id' => (int) $languages[$i]['id'], 'affiliate_id' => 0, 'email_templates_subject' => $email_templates_subject, 'email_templates_body' => $email_templates_body, 'platform_id' => $platform['id']]);
                    }
                }
                if ($text_id && isset($_POST['email_templates_subject'][$platform['id']]['plaintext'])) {
                    $update_template_id = $text_id;
                    $email_templates_subject = tep_db_prepare_input($_POST['email_templates_subject'][$platform['id']]['plaintext'][$languages[$i]['id']]);
                    $email_templates_body = tep_db_prepare_input($_POST['email_templates_body'][$platform['id']]['plaintext'][$languages[$i]['id']]);
                    $check = tep_db_fetch_array(tep_db_query('SELECT COUNT(*) AS echeck FROM ' . TABLE_EMAIL_TEMPLATES_TEXTS . ' ' . "WHERE email_templates_id='" . (int) $update_template_id . "' AND language_id='" . (int) $languages[$i]['id'] . "' AND affiliate_id=0  and platform_id = '" . $platform['id'] . "'"));
                    if ($check['echeck'] > 0) {
                        tep_db_perform(TABLE_EMAIL_TEMPLATES_TEXTS, ['email_templates_subject' => $email_templates_subject, 'email_templates_body' => $email_templates_body], 'update', "email_templates_id='" . (int) $update_template_id . "' AND language_id='" . (int) $languages[$i]['id'] . "' AND affiliate_id=0 and platform_id = '" . $platform['id'] . "'");
                    } else {
                        tep_db_perform(TABLE_EMAIL_TEMPLATES_TEXTS, ['email_templates_id' => (int) $update_template_id, 'language_id' => (int) $languages[$i]['id'], 'affiliate_id' => 0, 'email_templates_subject' => $email_templates_subject, 'email_templates_body' => $email_templates_body, 'platform_id' => $platform['id']]);
                    }
                }
            }
        }
        echo '<script> window.location.replace("' . Yii::$app->url_manager->create_url(['email/template-edit', 'tpl_id' => $template_id]) . '");</script>';
        //return $this->actionTemplateEdit( (int)$template_id );
    }
    public function action_template_preview($item_id = null)
    {
        $this->layout = false;
        \common\helpers\Translation::init('admin/email/templates');
        $languages_id = \Yii::$app->settings->get('languages_id');
        $template_id = \Yii::$app->request->post('item_id', $item_id);
        $info = tep_db_fetch_array(tep_db_query('select email_templates_id, email_templates_key, email_template_type from ' . TABLE_EMAIL_TEMPLATES . " where email_templates_id='" . (int) $template_id . "'"));
        ?>
        <?php 
        echo \common\helpers\Mail::get_email_templates_subject((int) $template_id, $languages_id);
        ?>
              <hr>
        <?php 
        if ($info['email_template_type'] == 'html') {
            echo \common\helpers\Mail::get_email_templates_body((int) $template_id, $languages_id);
        } else {
            echo nl2br(\common\helpers\Mail::get_email_templates_body((int) $template_id, $languages_id));
        }
        ?>
        <?php 
    }
    public function action_sms_templates()
    {
        $this->acl = ['BOX_HEADING_DESIGN_CONTROLS', 'BOX_SMS_TEMPLATES'];
        \common\helpers\Acl::check_access(['BOX_HEADING_DESIGN_CONTROLS', 'BOX_SMS_TEMPLATES']);
        $this->selected_menu = ['design_controls', 'email/sms-templates'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('email/sms-templates'), 'title' => HEADING_TITLE];
        $this->view->heading_title = HEADING_TITLE;
        $this->view->groups_table = [['title' => TABLE_HEADING_SMS_TEMPLATES, 'not_important' => 1]];
        $this->view->filters = new \stdClass();
        $this->view->filters->row = (int) \Yii::$app->request->get('row');
        $this->view->insert_template = \common\helpers\Acl::rule(['BOX_HEADING_DESIGN_CONTROLS', 'BOX_SMS_TEMPLATES', 'INSERT_SMS_TEMPLATES']);
        if ($this->view->insert_template == true) {
            $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url('email/sms-templates-edit') . '" class="btn btn-primary">' . IMAGE_INSERT . '</a>';
        }
        $type_id = (int) Yii::$app->request->get('type_id', 0);
        $messages = \Yii::$app->session->get('messages');
        unset($_SESSION['messages']);
        if (!is_array($messages)) {
            $messages = [];
        }
        return $this->render('sms-templates', ['messages' => $messages, 'type_id' => $type_id, 'types' => \common\helpers\Mail::get_sms_type_list(true)]);
    }
    public function action_sms_templates_list()
    {
        $this->acl = ['BOX_HEADING_DESIGN_CONTROLS', 'BOX_SMS_TEMPLATES'];
        $draw = Yii::$app->request->get('draw', 1);
        $start = Yii::$app->request->get('start', 0);
        $length = Yii::$app->request->get('length', 10);
        $response_list = [];
        if ($length == -1) {
            $length = 9999;
        }
        $sms_template_query = \common\models\Sms_Templates::find()->as_array(true);
        if (isset($_GET['search']['value']) && tep_not_null($_GET['search']['value'])) {
            $sms_template_query->where(['sms_templates_key' => trim($_GET['search']['value'])]);
        }
        if (isset($_GET['order'][0]['column']) && $_GET['order'][0]['dir']) {
            $sort = strtolower(trim($_GET['order'][0]['dir'])) == 'desc' ? SORT_DESC : SORT_ASC;
            switch ($_GET['order'][0]['column']) {
                case 0:
                    $sms_template_query->order_by(['sms_templates_key' => $sort]);
                    break;
                case 1:
                    $sms_template_query->order_by(['sms_template_type_id' => $sort]);
                    break;
                default:
                    $sms_template_query->order_by(['sms_templates_key' => SORT_ASC, 'sms_template_type_id' => SORT_ASC]);
                    break;
            }
        } else {
            $sms_template_query->order_by(['sms_templates_key' => SORT_ASC, 'sms_template_type_id' => SORT_ASC]);
        }
        $form_filter = Yii::$app->request->get('filter');
        parse_str($form_filter, $filter);
        if ($filter['type_id'] > 0) {
            $sms_template_query->and_where(['sms_template_type_id' => (int) $filter['type_id']]);
        }
        $query_numrows = $sms_template_query->count();
        $sms_template_query->offset($start)->limit($length);
        foreach ($sms_template_query->all() as $sms_templates) {
            $name_key = 'TEXT_EMAIL_' . str_replace(' ', '_', strtoupper($sms_templates['sms_templates_key']));
            $sms_templates['sms_templates_key'] = defined($name_key) ? constant($name_key) : $sms_templates['sms_templates_key'];
            $response_list[] = ['<div class="click_double" data-click-double="' . \Yii::$app->url_manager->create_url(['email/sms-templates-edit', 'tpl_id' => $sms_templates['sms_templates_id']]) . '">' . $sms_templates['sms_templates_key'] . '<input class="cell_identify" type="hidden" value="' . $sms_templates['sms_templates_id'] . '"></div>'];
        }
        $response = ['draw' => $draw, 'recordsTotal' => $query_numrows, 'recordsFiltered' => $query_numrows, 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_sms_templates_edit($sms_templates_id = null)
    {
        $this->acl = ['BOX_HEADING_DESIGN_CONTROLS', 'BOX_SMS_TEMPLATES'];
        $this->selected_menu = ['design_controls', 'email/sms-templates'];
        \common\helpers\Translation::init('admin/email/sms-templates');
        \common\helpers\Translation::init('admin/email/template-edit');
        \common\helpers\Translation::init('admin/email/templates');
        $template_id = (int) Yii::$app->request->get('tpl_id', $sms_templates_id);
        $sms_templates_record = \common\models\Sms_Templates::find()->where(['sms_templates_id' => $template_id])->as_array(true)->one();
        $this->view->heading_title = trim($sms_templates_record['sms_templates_key']);
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('email/sms-templates'), 'title' => HEADING_TITLE];
        $c_description_text = [];
        $platforms = \common\classes\platform::get_list(false);
        $languages = \common\helpers\Language::get_languages();
        foreach ($platforms as $platform) {
            for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
                $languages[$i]['logo'] = $languages[$i]['image'];
                $c_description_text[$platform['id']][$i] = [];
                $c_description_text[$platform['id']][$i]['code'] = $languages[$i]['code'];
                $c_description_text[$platform['id']][$i]['sms_templates_body'] = tep_draw_textarea_field('sms_templates_body[' . $platform['id'] . '][plaintext][' . $languages[$i]['id'] . ']', 'soft', '70', '15', \common\helpers\Mail::get_sms_templates_body((int) $template_id, $languages[$i]['id'], $platform['id']), 'class="form-control" id="textdesc' . $platform['id'] . '_' . $languages[$i]['id'] . '"');
                $c_description_text[$platform['id']][$i]['c_link'] = 'textdesc' . $platform['id'] . '_' . $languages[$i]['id'];
            }
        }
        $name_key = 'TEXT_SMS_' . str_replace(' ', '_', strtoupper($sms_templates_record['sms_templates_key']));
        $sms_templates_record['sms_templates_key'] = defined($name_key) ? constant($name_key) : $sms_templates_record['sms_templates_key'];
        if ($template_id == 0) {
            $sms_templates_record['sms_templates_key'] = tep_draw_input_field('sms_templates_key', '', 'required class="form-control" placeholder="' . TEXT_SMS_TEMPLATE_KEY . '"');
        }
        return $this->render('sms-templates-edit', ['sms_templates_key' => $sms_templates_record['sms_templates_key'], 'languages' => $languages, 'cDescriptionText' => $c_description_text, 'sms_templates_id' => $template_id, 'platforms' => $platforms, 'isMultiPlatforms' => \common\classes\platform::is_multi(), 'default_platform_id' => \common\classes\platform::default_id(), 'types' => \common\helpers\Mail::get_sms_type_list(true), 'sms_templates_type_id' => $sms_templates_record['sms_templates_type_id']]);
    }
    public function action_sms_templates_save()
    {
        $this->layout = false;
        $sms_templates_id = (int) Yii::$app->request->post('sms_templates_id', 0);
        $sms_templates_record = \common\models\Sms_Templates::find()->where(['sms_templates_id' => (int) $sms_templates_id])->one();
        if (!is_object($sms_templates_record)) {
            $sms_templates_record = new \common\models\Sms_Templates();
            $sms_templates_record->sms_templates_key = trim(Yii::$app->request->post('sms_templates_key', ''));
            $sms_templates_record->sms_templates_type_id = (int) Yii::$app->request->post('sms_templates_type_id', 0);
            if ($sms_templates_record->sms_templates_key != '') {
                $sms_templates_record->save();
                $sms_templates_id = (int) $sms_templates_record->sms_templates_id;
            }
        }
        if ($sms_templates_id > 0) {
            $platforms = \common\classes\platform::get_list(false);
            $languages = \common\helpers\Language::get_languages();
            foreach ($platforms as $platform) {
                for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
                    if (isset($_POST['sms_templates_body'][$platform['id']]['plaintext'])) {
                        $sms_templates_texts_record = \common\models\Sms_Templates_Texts::find()->where(['sms_templates_id' => (int) $sms_templates_id])->and_where(['language_id' => (int) $languages[$i]['id']])->and_where(['platform_id' => (int) $platform['id']])->one();
                        if (!is_object($sms_templates_texts_record)) {
                            $sms_templates_texts_record = new \common\models\Sms_Templates_Texts();
                            $sms_templates_texts_record->sms_templates_id = (int) $sms_templates_id;
                            $sms_templates_texts_record->language_id = (int) $languages[$i]['id'];
                            $sms_templates_texts_record->platform_id = (int) $platform['id'];
                            $sms_templates_texts_record->affiliate_id = 0;
                        }
                        $sms_templates_texts_record->sms_templates_body = trim($_POST['sms_templates_body'][$platform['id']]['plaintext'][$languages[$i]['id']]);
                        $sms_templates_texts_record->save();
                    }
                }
            }
        }
        echo '<script> window.location.replace("' . Yii::$app->url_manager->create_url(['email/sms-templates-edit', 'tpl_id' => $sms_templates_id]) . '");</script>';
    }
    public function action_sms_templates_view($template_id = null)
    {
        $this->layout = false;
        \common\helpers\Translation::init('admin/email/sms-templates');
        \common\helpers\Translation::init('admin/email/template-edit');
        \common\helpers\Translation::init('admin/email/templates');
        if (is_null($template_id)) {
            $template_id = (int) Yii::$app->request->post('item_id', 0);
        }
        $st_info = new \Object_Info(\common\models\Sms_Templates::find()->where(['sms_templates_id' => (int) $template_id])->as_array(true)->one());
        $template_id = (int) $st_info->sms_templates_id;
        if ($template_id > 0) {
            ?>
            <div class="or_box_head or_box_head_no_margin">
            <?php 
            $name_key = 'TEXT_SMS_' . str_replace(' ', '_', strtoupper($st_info->sms_templates_key));
            echo defined($name_key) ? constant($name_key) : $st_info->sms_templates_key;
            ?>
            </div>
            <div class="row_or_wrapp"></div>
            <div class="btn-toolbar btn-toolbar-order">
                <a class="btn btn-process-order btn-edit btn-primary" href="<?php 
            echo \Yii::$app->url_manager->create_url(['email/sms-templates-edit', 'tpl_id' => $st_info->sms_templates_id]);
            ?>"><?php 
            echo IMAGE_EDIT;
            ?></a>
                <?php 
            if (\common\helpers\Acl::rule(['BOX_HEADING_DESIGN_CONTROLS', 'BOX_SMS_TEMPLATES', 'DELETE_SMS_TEMPLATES'])) {
                ?>
                    <button onclick="return deleteItemConfirm(<?php 
                echo $template_id;
                ?>)" class="btn btn-delete btn-no-margin btn-process-order "><?php 
                echo IMAGE_DELETE;
                ?></button>
                <?php 
            }
            ?>
            </div>
            <?php 
        }
    }
    public function action_sms_templates_delete_confirm()
    {
        \common\helpers\Translation::init('admin/email/sms-templates');
        \common\helpers\Translation::init('admin/email/template-edit');
        \common\helpers\Translation::init('admin/email/templates');
        $this->layout = false;
        $template_id = (int) Yii::$app->request->post('item_id');
        $sms_templates_record = \common\models\Sms_Templates::find()->where(['sms_templates_id' => $template_id])->one();
        if (is_object($sms_templates_record)) {
            echo '<div class="or_box_head">' . TEXT_HEADING_DELETE . '</div>';
            echo tep_draw_form('groups', 'email/sms-templates', '', 'post', 'id="item_delete" onsubmit="return deleteItem();"');
            echo '<div class="row_fields">' . TEXT_SMS_TEMPLATE_DELETE . '</div>';
            //echo '<div class="row_fields"><b>' . $etInfo->groups_name . '</b></div>';
            echo '<div class="btn-toolbar btn-toolbar-order"><button class="btn btn-delete btn-no-margin">' . IMAGE_DELETE . '</button><input type="button" class="btn btn-cancel" value="' . IMAGE_CANCEL . '" onClick="return resetStatement();"></div>';
            echo tep_draw_hidden_field('item_id', $sms_templates_record->sms_templates_id);
            echo '</form>';
        }
    }
    public function action_sms_templates_delete()
    {
        $this->layout = false;
        $template_id = (int) Yii::$app->request->post('item_id');
        if (\common\helpers\Acl::rule(['BOX_HEADING_DESIGN_CONTROLS', 'BOX_SMS_TEMPLATES', 'DELETE_SMS_TEMPLATES'])) {
            \common\models\Sms_Templates::delete_all(['sms_templates_id' => $template_id]);
            \common\models\Sms_Templates_Texts::delete_all(['sms_templates_id' => $template_id]);
        }
    }
    private const NULL_INFO = ['email_template_type' => null, 'email_templates_id' => null, 'email_templates_key' => null, 'type_id' => null];
    private function init_info_array_if_empty($info)
    {
        return !empty($info) ? $info : self::NULL_INFO;
    }
}