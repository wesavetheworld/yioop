<?php
/**
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 * Copyright (C) 2009 - 2014  Chris Pollett chris@pollett.org
 *
 * LICENSE:
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * END LICENSE
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage element
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
/**
 * Contains the forms for managing search sources for video, news, etc.
 * Also, contains form for managing subsearches which appear in SearchView
 *
 * @author Chris Pollett
 *
 * @package seek_quarry
 * @subpackage element
 */
class SearchsourcesElement extends Element
{
    /**
     * Renders search source and subsearch forms
     *
     * @param array $data available Search sources  and subsearches
     */
    function render($data)
    {
        $pre_base_url = "?".CSRF_TOKEN."=".$data[CSRF_TOKEN]."&amp;c=admin";
        $base_url = $pre_base_url . "&amp;a=searchSources";
        $localize_url = $pre_base_url . "&amp;a=manageLocales".
            "&amp;arg=editstrings&amp;selectlocale=".$data['LOCALE_TAG'].
            "&amp;previous_activity=searchSources";
        ?>
        <div class="current-activity">
        <?php if($data["SOURCE_FORM_TYPE"] == "editsource") {
            ?>
            <div class='float-opposite'><a href='<?php e($base_url); ?>'><?php
                e(tl('searchsources_element_addsource_form')); ?></a></div>
            <h2><?php e(tl('searchsources_element_edit_media_source'));?></h2>
            <?php
        } else {
            ?>
            <h2><?php e(tl('searchsources_element_add_media_source'));?></h2>
            <?php
        }
        ?>
        <form id="addSearchSourceForm" method="post">
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
            e($data[CSRF_TOKEN]); ?>" />
        <input type="hidden" name="a" value="searchSources" />
        <input type="hidden" name="arg" value="<?php
            e($data['SOURCE_FORM_TYPE'])?>" />
        <?php
        if($data['SOURCE_FORM_TYPE'] == "editsource") {
            ?>
            <input type="hidden" name="ts" value="<?php
                e($data['ts'])?>" />
            <?php
        }
        ?>
        <table class="name-table">
        <tr><td><label for="source-type"><b><?php
            e(tl('searchsources_element_sourcetype'))?></b></label></td><td>
            <?php $this->view->helper("options")->render("source-type",
            "type", $data['SOURCE_TYPES'],
                $data['CURRENT_SOURCE']['type']); ?></td></tr>
        <tr><td><label for="source-name"><b><?php
            e(tl('searchsources_element_sourcename'))?></b></label></td><td>
            <input type="text" id="source-name" name="name"
                value="<?php e($data['CURRENT_SOURCE']['name']); ?>"
                maxlength="<?php e(LONG_NAME_LEN); ?>"
                class="wide-field" /></td></tr>
        <tr><td><label for="source-url"><b><?php
            e(tl('searchsources_element_url'))?></b></label></td><td>
            <input type="text" id="source-url" name="source_url"
                value="<?php e($data['CURRENT_SOURCE']['source_url']); ?>"
                maxlength="<?php e(MAX_URL_LEN); ?>"
                class="wide-field" /></td></tr>
        <tr><td><label for="source-locale-tag"><b id="locale-text"><?php
            e(tl('searchsources_element_locale_tag'))?></b></label></td><td>
            <?php $this->view->helper("options")->render("source-locale-tag",
                "language", $data['LANGUAGES'],
                 $data['CURRENT_SOURCE']['language']); ?></td></tr>
        <tr><td><label for="source-thumbnail"><b id="thumb-text"><?php
            e(tl('searchsources_element_thumbnail'));
            $aux_info_len = MAX_URL_LEN;
            $num_sub_aux_fields = 5;
            $sub_aux_len = floor(MAX_URL_LEN/$num_sub_aux_fields);
            ?></b></label></td><td>
            <input type="text" id="source-thumbnail" name="aux_info"
                value="<?php e($data['CURRENT_SOURCE']['aux_info']); ?>"
                maxlength="<?php e($aux_info_len);
                ?>" class="wide-field" /></td></tr>
        <tr><td colspan="2"><span id='instruct'><?php
            e(tl('searchsources_element_feed_instruct'));
            ?></span></td></tr>
        <tr><td><label for="item-path"><b id="channel-text"><?php
            e(tl('searchsources_element_channelpath')); ?></b></label></td><td>
            <input type="text" id="channel-path" name="channel_path"
                value="<?php e($data['CURRENT_SOURCE']['channel_path']); ?>"
                maxlength="<?php e($sub_aux_len); ?>"
                class="wide-field" /></td></tr>
        <tr><td><label for="item-path"><b id="item-text"><?php
            e(tl('searchsources_element_itempath')); ?></b></label></td><td>
            <input type="text" id="item-path" name="item_path"
                value="<?php e($data['CURRENT_SOURCE']['item_path']); ?>"
                maxlength="<?php e($sub_aux_len); ?>"
                class="wide-field" /></td></tr>
        <tr><td><label for="title-path"><b id="title-text"><?php
            e(tl('searchsources_element_titlepath'))?></b></label></td><td>
            <input type="text" id="title-path" name="title_path"
                value="<?php e($data['CURRENT_SOURCE']['title_path']); ?>"
                maxlength="<?php e($sub_aux_len); ?>"
                class="wide-field" /></td></tr>
        <tr><td><label for="description-path"><b id="description-text"><?php
            e(tl('searchsources_element_descpath'))?></b></label></td><td>
            <input type="text" id="description-path" name="description_path"
                value="<?php e($data['CURRENT_SOURCE']['description_path']); ?>"
                maxlength="<?php e($sub_aux_len); ?>"
                class="wide-field" /></td></tr>
        <tr><td><label for="date-path"><b id="link-text"><?php
            e(tl('searchsources_element_linkpath'))?></b></label></td><td>
            <input type="text" id="link-path" name="link_path"
                value="<?php e($data['CURRENT_SOURCE']['link_path']); ?>"
                maxlength="<?php e($sub_aux_len); ?>"
                class="wide-field" /></td></tr>
        <tr><td></td><td class="center"><button class="button-box"
            type="submit"><?php e(tl('searchsources_element_submit'));
            ?></button></td></tr>
        </table>
        </form>
        <?php
        $data['FORM_TYPE'] = "";
        $data['TABLE_TITLE'] = tl('searchsources_element_media_sources');
        $data['NO_FLOAT_TABLE'] = true;
        $data["NO_FORM_TAG"] = true;
        $data['ACTIVITY'] = 'searchSources';
        $data['VIEW'] = $this->view;
        $data['NO_SEARCH'] = true;
        $paging_items = array('SUBstart_row', 'SUBend_row', 'SUBnum_show');
        $paging1 = "";
        foreach($paging_items as $item) {
            if(isset($data[strtoupper($item)])) {
                $paging1 .= "&amp;".$item."=".$data[strtoupper($item)];
            }
        }
        $paging2 = "";
        $paging_items = array('start_row', 'end_row', 'num_show');
        foreach($paging_items as $item) {
            if(isset($data[strtoupper($item)])) {
                $paging2 .= "&amp;".$item."=".$data[strtoupper($item)];
            }
        }
        $data['PAGING'] = $paging1;
        $this->view->helper("pagingtable")->render($data);
        ?>
        <table class="search-sources-table">
        <tr><th><?php e(tl('searchsources_element_medianame'));?></th>
            <th><?php e(tl('searchsources_element_mediatype'));?></th><?php
            if(!MOBILE) {
                e("<th>".tl('searchsources_element_mediaurls')."</th>");
            }
            ?>
            <th colspan="2"><?php e(tl('searchsources_element_action'));
                ?></th></tr><?php
        foreach($data['MEDIA_SOURCES'] as $source) {
        ?>
        <tr><td><b><?php e($source['NAME']); ?></b></td>
            <td><?php e($data['SOURCE_TYPES'][$source['TYPE']]); ?></td>
            <?php
            if(!MOBILE) {
                ?>
                <td><?php e($source['SOURCE_URL']."<br />".
                    $source['AUX_INFO']); ?></td>
                <?php
            }
            ?>
            <td><a href="<?php e($base_url."&amp;arg=editsource&amp;ts=".
                $source['TIMESTAMP'].$paging1.$paging2); ?>"><?php
                e(tl('searchsources_element_editmedia'));
            ?></a></td>
            <td><a onclick='javascript:return confirm("<?php
                e(tl('confirm_delete_operation')); ?>");' href="<?php
                e($base_url."&amp;arg=deletesource&amp;ts=".
                $source['TIMESTAMP'].$paging1.$paging2); ?>"><?php
                e(tl('searchsources_element_deletemedia'));
            ?></a></td></tr>
        <?php
        } ?>
        </table>
        <?php if($data["SEARCH_FORM_TYPE"] == "editsubsearch") {
            ?>
            <div id='subsearch-section'
                class='float-opposite'><a href='<?php e($base_url); ?>'><?php
                e(tl('searchsources_element_addsearch_form')); ?></a></div>
            <h2 id="subsearch-head"><?php
                e(tl('searchsources_element_edit_subsearch'));?></h2>
            <?php
        } else {
            ?>
            <h2 id="subsearch-head"><?php
                e(tl('searchsources_element_add_subsearch'));?></h2>
            <?php
        }
        ?>
        <form id="SearchSourceForm" method="post" action='#subsearch-head'>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
            e($data[CSRF_TOKEN]); ?>" />
        <input type="hidden" name="a" value="searchSources" />
        <input type="hidden" name="arg" value="<?php
            e($data['SEARCH_FORM_TYPE']); ?>" />
        <table class="name-table">
        <tr><td><label for="subsearch-folder-name"><b><?php
            e(tl('searchsources_element_foldername'))?></b></label></td><td>
            <input type="text" id="subsearch-folder-name" name="folder_name"
                value="<?php e($data['CURRENT_SUBSEARCH']['folder_name']); ?>"
                <?php
                if($data['SEARCH_FORM_TYPE'] == 'editsubsearch') {
                    e("disabled='disabled'");
                }
                ?>
                maxlength="80" class="wide-field" /></td></tr>
        <tr><td><label for="index-source"><b><?php
            e(tl('searchsources_element_indexsource'))?></b></label></td><td>
            <?php $this->view->helper("options")->render("index-source",
            "index_identifier", $data['SEARCH_LISTS'],
                $data['CURRENT_SUBSEARCH']['index_identifier']); ?></td></tr>
        <tr>
        <td class="table-label"><label for="per-page"><b><?php
            e(tl('searchsources_element_per_page')); ?></b></label></td>
            <td><?php
            $this->view->helper("options")->render("per-page", "per_page",
                $data['PER_PAGE'], $data['CURRENT_SUBSEARCH']['per_page']); ?>
        </td></tr>
        <tr><td></td><td class="center"><button class="button-box"
            type="submit"><?php e(tl('searchsources_element_submit'));
            ?></button></td></tr>
        </table>
        </form>
        <?php
        $data['SUBFORM_TYPE'] = "";
        $data['TABLE_TITLE'] = tl('searchsources_element_subsearches');
        $data['TARGET_FRAGMENT'] = "subsearch-head";
        $data['NO_FLOAT_TABLE'] = true;
        $data['ACTIVITY'] = 'searchSources';
        $data['VIEW'] = $this->view;
        $data['VAR_PREFIX'] = "SUB";
        $data['PAGING'] = $paging2;
        $this->view->helper("pagingtable")->render($data);
        ?>
        <table class="search-sources-table">
        <tr><th><?php e(tl('searchsources_element_dirname'));?></th>
            <th><?php
            e(tl('searchsources_element_index')); ?></th>
            <?php
            if(!MOBILE) { ?>
                <th><?php e(tl('searchsources_element_localestring'));
                ?></th>
                <th><?php e(tl('searchsources_element_perpage'));
                ?></th>
                <?php
            }
            ?>
            <th colspan="3"><?php
                e(tl('searchsources_element_actions'));?></th></tr>
        <?php foreach($data['SUBSEARCHES'] as $search) {
        ?>
        <tr><td><b><?php e($search['FOLDER_NAME']); ?></b></td>
            <td><?php
                e("<b>".$data["SEARCH_LISTS"][trim($search['INDEX_IDENTIFIER'])].
                "</b><br />".$search['INDEX_IDENTIFIER']); ?></td><?php
            if(!MOBILE) {
                ?>
                <td><?php e($search['LOCALE_STRING']);?></td>
                <td><?php e($search['PER_PAGE']);?></td><?php
            }
            ?>
            <td><a href="<?php e($base_url."&amp;arg=editsubsearch&amp;fn=".
                $search['FOLDER_NAME'].$paging1.$paging2.
                "#subsearch-section"); ?>"><?php
                e(tl('searchsources_element_editsource'));
            ?></a></td>
            <td><?php
            if($data['CAN_LOCALIZE']) { ?>
                <a href='<?php e($localize_url."&amp;filter=".
                    $search['LOCALE_STRING']);
                ?>' ><?php
                e(tl('searchsources_element_localize'));
                ?></a><?php
            } else { ?>
                <span class="gray"><?php
                e(tl('searchsources_element_localize')); ?></span>
                <?php
            }
            ?>
            </td>
            <td><a href="<?php e($base_url.'&amp;arg=deletesubsearch&amp;fn='.
                $search['FOLDER_NAME'].$paging1.$paging2); ?>"><?php
                e(tl('searchsources_element_deletesubsearch'));
            ?></a></td></tr>
        <?php
        } ?>
        </table>
        </div>
        <script type= "text/javascript">
        function switchSourceType()
        {
            var stype = elt("source-type");
            stype = stype.options[stype.selectedIndex].value;
            if(stype == "video") {
                setDisplay("thumb-text", true);
                setDisplay("source-thumbnail", true);
                setDisplay("instruct", false);
                setDisplay("channel-text", false);
                setDisplay("channel-path", false);
                setDisplay("item-text", false);
                setDisplay("item-path", false);
                setDisplay("title-text", false);
                setDisplay("title-path", false);
                setDisplay("description-text", false);
                setDisplay("description-path", false);
                setDisplay("link-text", false);
                setDisplay("link-path", false);
                setDisplay("locale-text", false);
                setDisplay("source-locale-tag", false);
            } else if(stype == "html") {
                setDisplay("thumb-text", false);
                setDisplay("source-thumbnail", false);
                setDisplay("instruct", true);
                setDisplay("channel-text", true);
                setDisplay("channel-path", true);
                setDisplay("item-text", true);
                setDisplay("item-path", true);
                setDisplay("title-text", true);
                setDisplay("title-path", true);
                setDisplay("description-text", true);
                setDisplay("description-path", true);
                setDisplay("link-text", true);
                setDisplay("link-path", true);
                setDisplay("locale-text", true);
                setDisplay("source-locale-tag", true);
            } else {
                setDisplay("thumb-text", false);
                setDisplay("source-thumbnail", false);
                setDisplay("instruct", false);
                setDisplay("channel-text", false);
                setDisplay("channel-path", false);
                setDisplay("item-text", false);
                setDisplay("item-path", false);
                setDisplay("title-text", false);
                setDisplay("title-path", false);
                setDisplay("description-text", false);
                setDisplay("description-path", false);
                setDisplay("link-text", false);
                setDisplay("link-path", false);
                setDisplay("locale-text", true);
                setDisplay("source-locale-tag", true);
            }
        }
        </script>
    <?php
    }
}
?>