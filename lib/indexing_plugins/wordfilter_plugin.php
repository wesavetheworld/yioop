<?php
/**
 * SeekQuarry/Yioop --
 * Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 * Copyright (C) 2013 - 2014 Chris Pollett chris@pollett.org
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
 * @subpackage indexing_plugin
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
/** Loads processor used for */
require_once BASE_DIR."/lib/processors/text_processor.php";
/** Base indexing plugin class*/
require_once BASE_DIR."/lib/indexing_plugins/indexing_plugin.php";
/** Get the crawlHash function */
require_once BASE_DIR."/lib/utility.php";
/** Loads common constants for web crawling */
require_once BASE_DIR."/lib/crawl_constants.php";
/**
 * WordFilterPlugin is used to filter documents by terms during a crawl.
 *
 * When this plugin is in use, each document summary that is generated by a
 * TextProcessor or subclass during a crawl will be further processed by it
 * pageSummaryProcessing method. First a set of applicable
 * rules is computed base on the url of where the summary came from.
 * (see documentation in factory example for more info on how the
 * applicable rules are determined). Then as part of this
 * processing the summary's title and description are sent to
 * the method checkFilter. Here they are compared against the array of rules
 * $this->filter_rules which consists of a list of rules each of which
 * has a PRECONDITIONS and an ACTIONS field. Actions can either be directives
 * that might appear within a ROBOTS
 * meta tag of an HTML document: NOINDEX, NOFOLLOW, NOCACHE, NOARCHIVE, NOODP,
 * NOYDIR, NONE or can be the word NOPROCESS, JUSTFOLLOW, NOTCONTAINS.
 * The preconditions is checked in the function checkFilter. Details on
 * what constitutes are legal precondition are described in the
 * @see $filter_rules and @see $rules_string documentation.
 * Usually, if checkFilter returns true then pageSummaryProcessing adds the
 * meta words to the document summary and returns. If one of the actions
 * was NOTCONTAIN, then only if checkFilter returned false are the meta words
 * added. The crawl makes use of the meta word info when performing indexing.
 * In the case where the actions contain NOPROCESS the summary returned from
 * pageSummaryProcessing will be false this will prevent any indexing of this
 * document from occuring at all. In the case where the actions contain
 * JUSTFOLLOW, the document won't be stored in the index but links from it will
 * be followed. JUSTFOLLOW has a slightly different semantics than NOINDEX.
 * When NOINDEX is used the document is actually stored in the index
 * (unlike JUSTFOLLOW). If another document links to this document, it can be
 * detected. If at search time a NOINDEX document or a link to a NOINDEX
 * document is about to be returned, the NOINDEX is detected and the result
 * won't be returned. With JUSTFOLLOW since the data is not stored in the
 * index we can't tell if a link pointing to a JUSTFOLLOW page just hasn't been
 * crawled yet or if it is a link to a JUSTFOLLOW page, so links to JUSTFOLLOW
 * pages might appear in the index. One can see this effect by doing a search
 * on site:any. The link that found the p7.html page shows up.
 *
 * This plugin has been created with a dummy list of filter rules. By doing a
 * crawl on the test site contain in the archive
 *     tests/word-filter-test-crawl.zip
 * one can  test how it behaves on those terms. To make use of
 * this plugin on real web data one probably wants to alter the choice of words.
 * This can be done from Admin > Page Options > Crawl Time tab by clicking on
 * the Configure link next to the plugin. Alternatively, one could subclass this
 * plugin in WORK_DIRECTORY/app/lib/indexing_plugins where one has a different
 * array of filter_terms. To get a more sophisticated filtering process than a
 * precondition checker one would override checkFilter.
 * One can also directly modify the code below to achieve these effects, but
 * altering code under the BASE_DIR makes it slightly harder to newer versions
 * Yioop as they come out.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage indexing_plugin
 */
class WordfilterPlugin extends IndexingPlugin implements CrawlConstants
{
    /**
     * An array of rules. A rule is itself an array with two fields
     * PRECONDITIONS and ACTIONS. ACTIONS is an array with elements
     * from NOINDEX, NOFOLLOW, NOCACHE, NOARCHIVE, NOODP, NOYDIR, NONE,
     * NOTCONTAIN, JUSTFOLLOW, and NOPROCESS which are to be followed if the
     * PRECONDITIONS for the rule are met. PRECONDITIONS are an array
     * of pairs term => frequency. term is a term to check in the document
     * frequency indicates how often the term must appear for the condition
     * to hold. An integer frequency value greater or equal to 1 is treated as
     * raw count of occurrences that is required; a value between 0 and 1 is
     * treated a fraction of the document that must be made up of occurrence of
     * that term. The array in $this->filter rules is typically created by
     * calling $this->parseRules() which converts the string in
     * $this->rules_string into the format described above
     *
     * @var array
     */
    var $filter_rules = array();

    /**
     * Default rule string to be used if no other rules string is present
     * @var string
     */
    var $default_rules_string = <<< EOD
;
; Below is a set of dummy word filter rules to be used with the zip file
; tests/word-filter-test-crawl.zip . A description of how to carry out this
; test is given there in the readme.txt file in this zip file.
; You should not use these rules on an actual web crawl or you will get very
; minimal results.
;
; The general format of this word filter rule set is a sequence of rule blocks:
; [some_url_or_domain1]
; rule_1
; rule_2
; ...
; [some_url_or_domain2]
;
; Anything on a line after a ';' is also treated as a comment.
; A rule block begins with a url or domain that the rules that follow it are
; to a apply to. urls and domains follow the site format as used in crawl
; options. For example, http:/somesite.com/sub_dir/file or domain:somewhere.com.
; A minus in front of this url can be used to indicate anything but that domain.
; For example, -domain:ca would mean anything but the ca domain.
; The rules given before any [some_url_or_domain] line are applied to ALL
; summaries. When a summaries is being processed, the set of applicable rule
; blocks is determined based on matching the summaries url with
; the rule block urls, then all rules from these blocks are applied. Here is
; an example set of rules which is roughly suitable to return only pages with
; Canadian content on Wikipedia:
; [domain:wikipedia.org]
; -canada,-canadian:NOPROCESS
;
; A filter rule is specified in a single line that contains a ':'
; All lines in a rule file without a ':' are treated as comments.
;
; A rule has the format:
; PRECONDITIONS:ACTIONS
; For example,
; surfboard#2,bikini#0.02,wave:NOINDEX, NOFOLLOW
; As one can see PRECONDITIONS and ACTIONS are comma separated lists where
; whitespace is ignored. A PRECONDITIONS list is said to hold if all of
; its constituent conditions hold. In which case, Yioop's page processor
; will perform each of the actions in the ACTIONS list.
; The condition surfboard#2 requires that the term surfboard occurred
; at least twice in the document. If the value after the # is
; between 0 and 1, such as in bikini#0.02, then the condition holds
; if occurrences of that term (no stemming) make up 0.02 percent of
; the documents total length. Finally, if the condition doesn't
; have a # in it, then it is satisfied if that term appears at all.
; The first character of a precondition can be a sign + or -. The
; condition +surfboard#2 is the same as surfboard#2; however,
; -surfboard#2 means the negation of the condition surfboard#2. That is,
; that surfboard appear in the document less than two times.
; If you want to check for the occurrence of a term like -5degrees in
; a document you can use the precondition +-5degrees.
;
; If all the conditions in a precondition hold then the  WordfilterPlugin
; applies the list of ACTIONS. Possible actions are
; NOINDEX, NOFOLLOW, NOCACHE, NOARCHIVE, NOODP, NOYDIR, NONE,
; JUSTFOLLOW, and NOPROCESS. These say how the summary
; of whole page should be processed and  most of them correspond to robot
; meta tag directives. We indicate here the non standard directives. The crawl
; makes use of the meta word info when performing indexing. In the case
; where the actions contain NOPROCESS the summary returned from
; pageSummaryProcessing will be false, and this will prevent
; any indexing of this document from occuring at all. This is different from
; NOINDEX which says the document should not show up in the index as search
; time. With NOPROCESS a info about page can show up in the index, if there was
; a link to the page which was processed. NOINDEX on the other hand checks at
; search time to eliminate such links. In the case where the
; actions contain JUSTFOLLOW, the document won't be stored in the index but
: links from it will be followed.
;
-term0:JUSTFOLLOW
term1:NOPROCESS
+term2:NOFOLLOW,NOSNIPPET
EOD;
    /**
     * A string containing a parsable set of filter_rules to be used by
     * the WordFilterPlugin. The format of these rules is described in the
     * default value of this rule string below.
     *
     * @var string
     */
     var $rules_string = "";
    /**
     * This method adds robots metas to or removes entirely a summary
     * produced by a text page processor or its subsclasses depending on
     * whether the summary title and description satisfy various rules
     * in $this->filter_rules
     *
     * @param array& $summary the summary data produced by the relevant page
     *     processor's handle method; modified in-place.
     * @param string $url the url where the summary contents came from
     */
    function pageSummaryProcessing(&$summary, $url)
    {
        $sites = array_keys($this->filter_rules);
        $filter_rules = $this->filter_rules;
        $rules = ($filter_rules['default'])?$filter_rules['default'] : array();
        foreach($sites as $site) {
            if($site == "default") { continue; }
            $sign = ($site[0] == '-') ? false : true;
            if(!$sign || $site[0] == '+') {
                $check_url = substr($site, 1);
            } else {
                $check_url = $site;
            }
            if(($sign && UrlParser::urlMemberSiteArray($url, array($check_url),
                $url . $check_url)) || (!$sign &&
                !UrlParser::urlMemberSiteArray($url, array($check_url),
                $url . $check_url))) {
                $rules = array_merge($rules, $filter_rules[$site]);
            }
        }
        foreach ($rules as $rule) {
            $preconditions = $rule["PRECONDITIONS"];
            $actions = $rule["ACTIONS"];
            $filter_flag = $this->checkFilter($preconditions,
                $summary[self::TITLE], $summary[self::DESCRIPTION]);
            if($filter_flag) {
                if(in_array("NOPROCESS", $actions)) {
                    crawlLog("  Word filter plugin removed page.");
                    $summary = false;
                    break;
                } else {
                    if(!isset($summary[self::ROBOT_METAS])) {
                        $summary[self::ROBOT_METAS] = array();
                    }
                    $summary[self::ROBOT_METAS] += $actions;
                }
            }
        }
    }
    /**
     * Used to check if $precondition is met by the document
     * consisting of the concatenation of $title and $description
     *
     * @see $filter_terms to see what constitutes a valid precondition.
     *
     * @param string $preconditions the terms and their frequencies to search for
     * @param string $title of a web page summary
     * @param string $description of a web page summary
     * @return bool whether the summary should be filtered or not
     */
    function checkFilter($preconditions, $title, $description)
    {
        $title_description = mb_strtolower($title." ".$description);
        $len = strlen($title_description) - str_word_count($title_description);
        foreach($preconditions as $pre_term => $find_frequency) {
            $sign = $pre_term[0];
            $term = ($sign == '-' || $sign == '+') ?
                substr($pre_term, 1): $pre_term;
            $sign = ($sign == '-') ? false : true;
            $found_frequency = substr_count($title_description, $term);
            if($find_frequency < 1) {
                $found_frequency = ($found_frequency/$len);
            }
            if(( $sign && $found_frequency < $find_frequency) ||
               (!$sign && $found_frequency >= $find_frequency) ) {
                return false;
            }
        }
        return true;
    }
    /**
     * Saves to a file $this->rules_string, a field which contains the string
     * rules that are being used with this plugin
     */
    function saveConfiguration()
    {
        $config_file = WORK_DIRECTORY."/data/word_filter_plugin.txt";
        file_put_contents($config_file, $this->rules_string);
    }
    /**
     * Reads plugin configuration data from data/word_filter_plugin.txt
     * on the name server into $this->rule_string. Then parse this string
     * to $this->filter_rules, the format used by
     * $this->pageSummaryProcessing(&$summary)
     *
     * @return array configuration associative array
     */
    function loadConfiguration()
    {
        $config_file = WORK_DIRECTORY."/data/word_filter_plugin.txt";
        if(file_exists($config_file)) {
            $this->rules_string =  file_get_contents($config_file);
        }
        $configuration = $this->parseRules();
        return $configuration;
    }
    /**
     * Takes a configuration array of rules and sets them as the rules for
     * this instance of the plugin. Typically used on a
     * queue_server or on a fetcher. It first sets the value of
     * $this->filter_rules, then in case we later call saveConfiguration(),
     * it also call serializeRules to store the serial format in
     * $this->rules_string
     *
     * @param array $configuration
     */
    function setConfiguration($configuration)
    {
        $this->filter_rules = $configuration;
        $this->serializeRules();
    }
    /**
     * Behaves as a "controller" for the configuration page of the plugin.
     * It is called by the AdminController pageOptions activity method to
     * let the plugin handle any configuration $_REQUEST data sent by this
     * activity with regard to the plugin. This method sees if the $_REQUEST
     * has word filter plugin configuration data, and if so cleans and saves
     * it. It then modifies $data so that if the plugin's configuration view
     * is drawn it makes use of the current plugin configuration info.
     *
     * @param array& $data info to be used by the admin view to draw itself.
     */
    function configureHandler(&$data)
    {
        if(isset($_REQUEST['filter_rules'])) {
            $pre_filter_rules =  str_replace("&amp;", "&",
                $_REQUEST['filter_rules']);
            $pre_filter_rules = @htmlentities($pre_filter_rules,
                ENT_QUOTES, "UTF-8");
            $this->rules_string = $pre_filter_rules;
            $configuration = $this->parseRules();
            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                tl('wordfilter_plugin_settings_saved')."</h1>');";
        }
        if(!isset($configuration)) {
            $configuration = $this->loadConfiguration();
        }
        if(isset($_REQUEST['word_filter']) &&
            $_REQUEST['word_filter'] == "restore") {
            $configuration = $this->loadDefaultConfiguration();
            $data['SCRIPT'] .= "doMessage('<h1 class=\"red\" >".
                tl('wordfilter_plugin_defaults_restored')."</h1>');";
        }
        if(!is_array($configuration)) {
            $configuration = array();
        }
        $this->saveConfiguration();
        $data["filter_rules"] = $this->rules_string;
    }
    /**
     * Reads plugin configuration data from the default setting of this
     * plugin. Then parse this string to $this->filter_rules, the format used by
     * $this->pageSummaryProcessing(&$summary)
     *
     * @return array configuration associative array
     */
    function loadDefaultConfiguration()
    {
        $this->rules_string = $this->default_rules_string;
        $configuration = $this->parseRules();
        return $configuration;
    }
    /**
     * Parse rules into array format from the string $this->rules_string
     * into the array $this->filter_rules. $this->filter_rules is used
     * when  $this->pageSummaryProcessing(&$summary) is called.
     */
    function parseRules()
    {
        $rule_blocks_regex = "/\n*\s*\[(.*)\]\s*\n+/";
        $blocks = preg_split($rule_blocks_regex, $this->rules_string, -1,
            PREG_SPLIT_DELIM_CAPTURE);
        $num_blocks = count($blocks);
        $block_name = "default";
        $rule_blocks = array();
        for($i = 0; $i < $num_blocks; $i += 2) {
            $word_rules = explode("\n", $blocks[$i]);
            $rule_block = array();
            foreach($word_rules as $rule_string) {
                if(($pos = mb_stripos($rule_string, ";")) !== false) {
                    $rule_string = substr($rule_string, 0, $pos);
                }
                if(mb_stripos($rule_string, ":") > 0) {
                    $rule = array();
                    $rule_string = mb_ereg_replace("\s+", "",
                        $rule_string);
                    list($precondition_string, $actions_string,) =
                        explode(":", $rule_string);
                    $preconditions = explode(",", $precondition_string);
                    foreach($preconditions as $precondition) {
                        $pre_parts = explode("#", $precondition);
                        $pre_parts[1] = (isset($pre_parts[1])) ?
                            $pre_parts[1] : 1;
                        $rule["PRECONDITIONS"][
                            mb_strtolower($pre_parts[0])] = $pre_parts[1];
                    }
                    $actions = explode(",", $actions_string);
                    $rule["ACTIONS"] = $actions;
                    $rule_block[] = $rule;
                }
            }
            $rule_blocks[$block_name] = $rule_block;
            if(isset($blocks[$i + 1])) {
                $block_name = $blocks[$i + 1];
            }
        }
        $this->filter_rules = $rule_blocks;
        return $rule_blocks;
    }
    /**
     * This is used to convert the array in $this->filter_rules into a string
     * format in $this->rules_string which would be suitable for saving to
     * disk or displaying on the configuration page.
     */
    function serializeRules()
    {
        $rules_string = "";
        $configuration = $this->filter_rules;
        foreach($configuration as $url => $rules) {
            $rules_string .="[$url]\n";
            foreach($rules as $rule) {
                $comma = "";
                foreach($rule["PRECONDITIONS"] as $term => $frequency) {
                    if($frequency == 1) {
                        $rules_string .= "$comma$term";
                    } else {
                        $rules_string .= "$comma$term#$frequency";
                    }
                    $comma = ",";
                }
                $rules_string .= ":".
                    implode(",", $rule["ACTIONS"])."\n";
            }
        }
        $this->rules_string = $rules_string;
    }
    /**
     * Used to draw the HTML configure screen for the word filter plugin.
     *
     * @param array& $data contains configuration data to be used in drawing
     *     the view
     */
    function configureView(&$data)
    {
        ?>
        <h2 class="center"><?php e(tl('wordfilter_plugin_preferences')); ?>
        [<a href="?c=admin&amp;a=pageOptions&amp;option_type=crawl_time<?php
            e('&amp;'.CSRF_TOKEN.'='.$data[CSRF_TOKEN]);
            ?>&amp;word_filter=restore"><?php
            e(tl('wordfilter_plugin_factory_settings'));?></a>]</h2>
        <form  method="post" action='?'>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="a" value="pageOptions" />
        <input type="hidden" id='option-type' name="option_type"
            value="crawl_time" />
        <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
            e($data[CSRF_TOKEN]); ?>" />
        <textarea class="medium-text-area" name="filter_rules" ><?php
            e($data["filter_rules"])?></textarea>
        <div class="center slight-pad">
            <button class="button-box"
            type="submit"><?php
                e(tl('wordfilter_plugin_save'));
            ?></button>
        </div>
        </form>
        <?php
    }
    /**
     * Which mime type page processors this plugin should do additional
     * processing for
     *
     * @return array an array of page processors
     */
    static function getProcessors()
    {
        return array("TextProcessor"); //will apply to all subclasses
    }
}
?>