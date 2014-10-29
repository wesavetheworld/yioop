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
 * This script can be used to set up the database and filesystem for the
 * seekquarry database system. The SeekQuarry system is deployed with a
 * minimal sqlite database so this script is not strictly needed.
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage configs
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(isset($_SERVER['DOCUMENT_ROOT']) && strlen($_SERVER['DOCUMENT_ROOT']) > 0) {
    echo "BAD REQUEST";
    exit();
}
/**
 * Calculate base directory of script
 * @ignore
 */
define("BASE_DIR", substr(
    dirname(realpath($_SERVER['PHP_SELF'])), 0,
    -strlen("/configs")));
require_once BASE_DIR.'/configs/config.php';
/* get the database library */
require_once BASE_DIR."/models/datasources/".DBMS."_manager.php";
/** Get base class for profile_model.php*/
require_once BASE_DIR."/models/model.php";
/** For ProfileModel::createDatabaseTables method*/
require_once BASE_DIR."/models/profile_model.php";
/** For GroupModel::setPageName method*/
require_once BASE_DIR."/models/group_model.php";
/** For crawlHash function */
require_once BASE_DIR."/lib/utility.php";
$profile_model = new ProfileModel(DB_NAME, false);
$db_class = ucfirst(DBMS)."Manager";
$dbinfo = array("DBMS" => DBMS, "DB_HOST" => DB_HOST, "DB_USER" => DB_USER,
    "DB_PASSWORD" => DB_PASSWORD, "DB_NAME" => DB_NAME);
if(!in_array(DBMS, array('sqlite', 'sqlite3'))) {
    $db = new $db_class();
    $db->connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);
    /*  postgres doesn't let you drop a database while connected to it so drop
        tables instead first
     */
    $profile_model->initializeSql($db, $dbinfo);
    $database_tables = array_keys($profile_model->create_statements);
    foreach($database_tables as $table) {
        $db->execute("DROP TABLE ".$table);
    }
    $db->execute("DROP DATABASE IF EXISTS ".DB_NAME);
    $db->execute("CREATE DATABASE ".DB_NAME);
    $db->disconnect();

    $db->connect(); // default connection goes to actual DB
} else {
    @unlink(CRAWL_DIR."/data/".DB_NAME.".db");
    $db = new $db_class();
    $db->connect();
}
if(!$profile_model->createDatabaseTables($db, $dbinfo)) {
    echo "\n\nCouldn't create database tables!!!\n\n";
    exit();
}
$db->execute("INSERT INTO VERSION VALUES (".YIOOP_VERSION.")");
$creation_time = microTimestamp();
//numerical value of the blank password
$profile = $profile_model->getProfile(WORK_DIRECTORY);
$new_profile = $profile;
$new_profile['FIAT_SHAMIR_MODULUS'] = generateFiatShamirModulus();
$profile_model->updateProfile(WORK_DIRECTORY, $new_profile, $profile);
$sha1_of_blank_string =  bchexdec(sha1(''));
//calculating V  = S ^ 2 mod N
$temp = bcpow($sha1_of_blank_string . '', '2');
$zkp_password = bcmod($temp, $new_profile['FIAT_SHAMIR_MODULUS']);
//default account is root without a password
$sql ="INSERT INTO USERS VALUES (".ROOT_ID.", 'admin', 'admin','root',
        'root@dev.null', '".crawlCrypt('')."', '".ACTIVE_STATUS.
        "', '".crawlCrypt('root'.AUTH_KEY.$creation_time)."','$creation_time',
        0, 0, '$zkp_password')";
$db->execute($sql);
/* public account is an inactive account for used for public permissions
   default account is root without a password
 */
$sql ="INSERT INTO USERS VALUES (".PUBLIC_USER_ID.", 'all', 'all','public',
        'public@dev.null', '".crawlCrypt('')."', '".INACTIVE_STATUS.
        "', '".crawlCrypt('public' . AUTH_KEY . $creation_time)."',
        '$creation_time', 0, 0, '$zkp_password')";
$db->execute($sql);

//default public group with group id 1
$creation_time = microTimestamp();
$sql = "INSERT INTO GROUPS VALUES(".PUBLIC_GROUP_ID.",'Public','".
    $creation_time."','".ROOT_ID."', '".PUBLIC_JOIN."', '".GROUP_READ.
    "', ".NON_VOTING_GROUP.", " . FOREVER . ")";
$db->execute($sql);
$now = time();
$db->execute("INSERT INTO ROLE VALUES (".ADMIN_ROLE.", 'Admin' )");
$db->execute("INSERT INTO ROLE VALUES (".USER_ROLE.", 'User' )");
$db->execute("INSERT INTO USER_ROLE VALUES (".ROOT_ID.", ".ADMIN_ROLE.")");
$db->execute("INSERT INTO USER_GROUP VALUES (".ROOT_ID.", ".
    PUBLIC_GROUP_ID.", ".ACTIVE_STATUS.", $now)");
$db->execute("INSERT INTO USER_GROUP VALUES (".PUBLIC_USER_ID.", ".
    PUBLIC_GROUP_ID.", ".ACTIVE_STATUS.", $now)");
//Create a Group for Wiki HELP.
$sql = "INSERT INTO GROUPS VALUES(" . HELP_GROUP_ID . ",'Help','" .
    $creation_time . "','" . ROOT_ID . "',
    '" . PUBLIC_BROWSE_REQUEST_JOIN . "', '" . GROUP_READ_WIKI .
    "', " . UP_DOWN_VOTING_GROUP . ", " . FOREVER . ")";
$db->execute($sql);
$now = time();
$db->execute("INSERT INTO USER_GROUP VALUES (" . ROOT_ID . ", " .
    HELP_GROUP_ID . ", " . ACTIVE_STATUS . ", $now)");
$db->execute("INSERT INTO USER_GROUP VALUES (" . PUBLIC_USER_ID . ", " .
    HELP_GROUP_ID . ", " . ACTIVE_STATUS . ", $now)");
$public_pages = array();
$public_pages["404"] = <<<EOD
title=Page Not Found
description=The page you requested cannot be found on our server
END_HEAD_VARS
==The page you requested cannot be found.==
EOD;
$public_pages["409"] = <<<EOD
title=Conflict

description=Your request would result in an edit conflict.
END_HEAD_VARS
==Your request would result in an edit conflict, so will not be processed.==
EOD;
$public_pages["captcha_time_out"] = <<<EOD
title=Captcha/Recover Time Out
END_HEAD_VARS
==Account Timeout==

A large number of captcha refreshes or recover password requests
have been made from this IP address. Please wait until
%s to try again.
EOD;
$public_pages["bot"] = <<<EOD
title=Bot

description=Describes the web crawler used with this
web site
END_HEAD_VARS
==My Web Crawler==

Please Describe Your Robot
EOD;
$public_pages["privacy"] = <<<EOD
title=Privacy Policy

description=Describes what information this site collects and retains about
users and how it uses that information
END_HEAD_VARS
==We are concerned with your privacy==
EOD;
$public_pages["register_time_out"] = <<<EOD
title=Create/Recover Account

END_HEAD_VARS

==Account Timeout==

A number of incorrect captcha responses or recover password requests
have been made from this IP address. Please wait until
%s to access this site.
EOD;
$public_pages["suggest_day_exceeded"] = <<<EOD
EOD;
$public_pages["terms"] = <<<EOD
=Terms of Service=

Please write the terms for the services provided by this website.
EOD;
$public_pages["Syntax"] = <<<EOD
=Yioop Wiki Syntax=

Wiki syntax is a lightweight way to markup a text document so that
it can be formatted and drawn nicely by Yioop.
This page briefly describes the wiki syntax supported by Yioop.

==Headings==
In wiki syntax headings of documents and sections are written as follows:

<nowiki>
=Level1=
==Level2==
===Level3===
====Level4====
=====Level5=====
======Level6======
</nowiki>

and would look like:

=Level1=
==Level2==
===Level3===
====Level4====
=====Level5=====
======Level6======

==Paragraphs==
In Yioop two new lines indicates a new paragraph. You can control
the indent of a paragraph by putting colons followed by a space in front of it:

<nowiki>
: some indent

:: a little more

::: even more

:::: that's sorta crazy
</nowiki>

which looks like:

: some indent

:: a little more

::: even more

:::: that's sorta crazy

==Horizontal Rule==
Sometimes it is convenient to separate paragraphs or sections with a horizontal
rule. This can be done by placing four hyphens on a line by themselves:
<nowiki>
----
</nowiki>
This results in a line that looks like:
----

==Text Formatting Within Paragraphs==
Within a paragraph it is often convenient to make some text bold, italics,
underlined, etc. Below is a quick summary of how to do this:
===Wiki Markup===
{|
|<nowiki>''italic''</nowiki>|''italic''
|-
|<nowiki>'''bold'''</nowiki>|'''bold'''
|-
|<nowiki>'''''bold and italic'''''</nowiki>|'''''bold and italic'''''
|}

===HTML Tags===
Yioop also supports several html tags such as:
{|
|<nowiki><del>delete</del></nowiki>|<del>delete</del>
|-
|<nowiki><ins>insert</ins></nowiki>|<ins>insert</ins>
|-
|<nowiki><s>strike through</s> or
<strike>strike through</strike> </nowiki>|<s>strike through</s>
|-
|<nowiki><sup>superscript</sup> and
<sub>subscript</sub></nowiki>|<sup>superscript</sup> and
<sub>subscript</sub>
|-
|<nowiki><tt>typewriter</tt></nowiki>|<tt>typewriter</tt>
|-
|<nowiki><u>underline</u></nowiki>|<u>underline</u>
|}

===Spacing within Paragraphs===
The HTML entity
<nowiki>&nbsp;</nowiki>
can be used to create a non-breaking space. The tag
<nowiki><br></nowiki>
can be used to produce a line break.

==Preformatted Text and Unformatted Text==
You can force text to be formatted as you typed it rather
than using the layout mechanism of the browser using the
<nowiki><pre>preformatted text tag.</pre></nowiki>
Alternatively, a sequence of lines all beginning with a
space character will also be treated as preformatted.

Wiki markup within pre tags is still parsed by Yioop.
If you would like to add text that is not parsed, enclosed
it in `<`nowiki> `<`/nowiki> tags.

==Styling Text Paragraphs==
Yioop wiki syntax offers a number of templates for
control the styles, and alignment of text for
a paragraph or group of paragraphs:<br />
`{{`left| some text`}}`,<br /> `{{`right| some text`}}`,<br />
and<br />
`{{`center| some text`}}`<br /> can be used to left-justify,
right-justify, and center a block of text. For example,
the last command, would produce:
{{center|
some text
}}
If you know cascading style sheets (CSS), you can set
a class or id selector for a block of text using:<br />
`{{`class="my-class-selector" some text`}}`<br />and<br />
`{{`id="my-id-selector" some text`}}`.<br />
You can also apply inline styles to a block of text
using the syntax:<br />
`{{`style="inline styles" some text`}}`.<br />
For example, `{{`style="color:red" some text`}}` looks
like {{style="color:red" some text}}.

==Lists==
The Yioop Wiki Syntax supported of ways of listing items:
bulleted/unordered list, numbered/ordered lists, and
definition lists. Below are some examples:

===Unordered Lists===
<nowiki>
* Item1
** SubItem1
** SubItem2
*** SubSubItem1
* Item 2
* Item 3
</nowiki>
would be drawn as:
* Item1
** SubItem1
** SubItem2
*** SubSubItem1
* Item 2
* Item 3

===Ordered Lists===
<nowiki>
# Item1
## SubItem1
## SubItem2
### SubSubItem1
# Item 2
# Item 3
</nowiki>
# Item1
## SubItem1
## SubItem2
### SubSubItem1
# Item 2
# Item 3

===Mixed Lists===
<nowiki>
# Item1
#* SubItem1
#* SubItem2
#*# SubSubItem1
# Item 2
# Item 3
</nowiki>
# Item1
#* SubItem1
#* SubItem2
#*# SubSubItem1
# Item 2
# Item 3

===Definition Lists===
<nowiki>
;Term 1: Definition of Term 1
;Term 2: Definition of Term 2
</nowiki>
;Term 1: Definition of Term 1
;Term 2: Definition of Term 2

==Tables==
A table begins with {`|`  and ends with `|`}. Cells are separated with | and
rows are separated with |- as can be seen in the following
example:
<nowiki>
{|
|a||b
|-
|c||d
|}
</nowiki>
{|
|a||b
|-
|c||d
|}
Headings for columns and rows can be made by using an exclamation point, !,
rather than a vertical bar |. For example,
<nowiki>
{|
!a!!b
|-
|c|d
|}
</nowiki>
{|
!a!!b
|-
|c|d
|}
Captions can be added using the + symbol:
<nowiki>
{|
|+ My Caption
!a!!b
|-
|c|d
|}
</nowiki>
{|
|+ My Caption
!a!!b
|-
|c|d
|}
Finally, you can put a CSS class or style attributes (or both) on the first line
of the table to further control how it looks:
<nowiki>
{| class="wikitable"
|+ My Caption
!a!!b
|-
|c|d
|}
</nowiki>
{| class="wikitable"
|+ My Caption
!a!!b
|-
|c|d
|}
Within a cell attributes like align, valign, styles, and class can be used. For
example,
<nowiki>
{|
| style="text-align:right;"| a| b
|-
| lalala | lalala
|}
</nowiki>
{|
| style="text-align:right;"| a| b
|-
| lalala | lalala
|}

==Math==

Math can be included into a wiki document by either using the math tag:
<nowiki>
<math>
\sum_{i=1}^{n} i = frac{(n+1)(n)}{2}
</math>
</nowiki>

<math>
\sum_{i=1}^{n} i = frac{(n+1)(n)}{2}
</math>

==Adding Resources to a Page==

Yioop wiki syntax supports adding search bars, audio, images, and video to a
page. The magnifying class edit tool icon can be used to add a search bar via
the GUI. This can also be added by hand with the syntax:
<nowiki>
{{search:default|size:small|placeholder:Search Placeholder Text}}
</nowiki>
This syntax is split into three parts each separated by a vertical bar |. The
first part search:default means results from searches should come from the
default search index. You can replace default with the timestamp of a specific
index or mix if you do not want to use the default. The second group size:small
indicates the size of the search bar to be drawn. Choices of size are small,
medium, and large. Finally, placeholder:Search Placeholder Text indicates the
grayed out background text in the search input before typing is done should
read: Search Placeholder Text. Here is what the above code outputs:

{{search:default|size:small|placeholder:Search Placeholder Text}}

In a given pages edit mode at the bottom of the page there is a form that allows
one to upload resources such as audio, images, or video for that page. After
uploading an item it will appear underneath this form with a link to preview
what the item looks like followed by a button to add the resource to the page,
followed by a link [X] to delete the item. Clinking on the add button will
insert into the wiki page a line like:

<pre>
{ {resource:myphoto.jpg|Resource Description}}
</pre>

Here  myphoto.jpg is the resource that will be inserted and Resource Description
is the alternative text to use in case the viewing browser can't display jpg
files.

==Page Settings, Page Type==

In edit mode for a wiki page, next to the page name, is a link [Settings].
Clicking this link expands a form which can be used to control global settings
for a wiki page. This form contains a drop down for the page type and then text
fields or areas for the page title, author, meta robots, and page description.
Page title is displayed in the browser title base when the wiki page is accessed
with the  Activity Panel collapsed or when not logged. Similar, in this mode,
if one looks as the HTML page source for the page, in the head of document,
<meta> tags for author, robots, and description are set according to these
fields. The robots meta tag. Wikipedia has more information on
[[https://en.wikipedia.org/wiki/Meta_element|Meta Elements]].

The default page
type is Standard which means treat the page as a usual wiki page.
The type Media List means that the page when read should just display the
resources in the page and the links for the resources go to a separate page
used to display this resource. This kind of page is useful for a gallery of
images or a collection of audio or video files. Presentation
mode is for a wiki page whose purpose is a slide presentation. In this mode,
....
on a line by itself is used to separate one slide. When the Activity panel is
not collapse and you are reading a presentation, it just displays as a single
page with all slides visible. Collapsing the Activity panel presents the slides
as a typical slide presentation using the
[[www.w3.org/Talks/Tools/Slidy2/Overview.html|Slidy]] javascript.
EOD;
$group_model = new GroupModel(DB_NAME, false);
$group_model->db = $db;
foreach($public_pages as $page_name => $page_content) {
    $page_content = str_replace("&amp;", "&", $page_content);
    $page_content = @htmlentities($page_content, ENT_QUOTES, "UTF-8");
    $group_model->setPageName(ROOT_ID, PUBLIC_USER_ID, $page_name,
        $page_content, "en-US", "Creating Default Pages",
        "$page_name Wiki Page Created!", "Discuss the page in this thread!");
}
//Insert Help pages
$help_pages = getWikiHelpPages();
foreach($help_pages as $page_name => $page_content) {
    $page_content = str_replace("&amp;", "&", $page_content);
    $page_content = @htmlentities($page_content, ENT_QUOTES, "UTF-8");
    $group_model->setPageName(ROOT_ID, HELP_GROUP_ID, $page_name,
        $page_content, "en-US", "Creating Default Pages",
        "$page_name Help Page Created!", "Discuss the page in this thread!");
}
function getWikiHelpPages()
{
    $help_pages = array();
    require_once(BASE_DIR . "/models/datasources/sqlite3_manager.php");
    $default_dbm = new Sqlite3Manager();
    $default_dbm->connect("", "", "", BASE_DIR . "/data/default_help.db");
    if(!$default_dbm) {
        return false;
    }
    $group_model = new GroupModel(DB_NAME, true);
    $group_model->db = $default_dbm;
    $page_list = $group_model->getPageList(HELP_GROUP_ID, "en-US", '', 0, 200);
    foreach($page_list[1] as $page) {
        if(isset($page['TITLE'])) {
            $page_info = $group_model->getPageInfoByName(
                HELP_GROUP_ID, $page['TITLE'], "en-US", "api");
            $page_content = str_replace("&amp;", "&", $page_info['PAGE']);
            $page_content = html_entity_decode($page_content, ENT_QUOTES,
                "UTF-8");
            $help_pages[$page['TITLE']] = $page_content;
        }
    }
    return $help_pages;
}

/* End Help content insertion. */
/* we insert 1 by 1 rather than comma separate as sqlite
   does not support comma separated inserts
 */
$locales = array(
    array('en-US', 'English', 'lr-tb'),
    array('ar', 'العربية', 'rl-tb'),
    array('de', 'Deutsch', 'lr-tb'),
    array('es', 'Español', 'lr-tb'),
    array('fr-FR', 'Français', 'lr-tb'),
    array('he', 'עברית', 'rl-tb'),
    array('in-ID', 'Bahasa', 'lr-tb'),
    array('it', 'Italiano', 'lr-tb'),
    array('ja', '日本語', 'lr-tb'),
    array('ko', '한국어', 'lr-tb'),
    array('pl', 'Polski', 'lr-tb'),
    array('pt', 'Português', 'lr-tb'),
    array('ru', 'Русский', 'lr-tb'),
    array('th', 'ไทย', 'lr-tb'),
    array('vi-VN', 'Tiếng Việt', 'lr-tb'),
    array('zh-CN', '中文', 'lr-tb'),
    array('kn', 'ಕನ್ನಡ', 'lr-tb'),
    array('hi', 'हिन्दी', 'lr-tb'),
    array('tr', 'Türkçe', 'lr-tb'),
    array('fa', 'فارسی', 'rl-tb'),
    array('te', 'తెలుగు', 'lr-tb'),
);
$i = 1;
foreach($locales as $locale) {
    $db->execute("INSERT INTO LOCALE VALUES ($i, '{$locale[0]}',
        '{$locale[1]}', '{$locale[2]}', '1')");
    $locale_index[$locale[0]] = $i;
    $i++;
}
$activities = array(
    "manageAccount" => array('db_activity_manage_account',
        array(
            "en-US" => 'Manage Account',
            "fa" => 'مدیریت حساب',
            "fr-FR" => 'Modifier votre compte',
            "ja" => 'アカウント管理',
            "ko" => '사용자 계정 관리',
            "vi-VN" => 'Quản lý tài khoản',
            "zh-CN" => '管理帳號',
        )),
    "manageUsers" => array('db_activity_manage_users',
        array(
            "en-US" => 'Manage Users',
            "fa" => 'مدیریت کاربران',
            "fr-FR" => 'Modifier les utilisateurs',
            "ja" => 'ユーザー管理',
            "ko" => '사용자 관리',
            "vi-VN" => 'Quản lý tên sử dụng',
            "zh-CN" => '管理使用者',
        )),
    "manageRoles" => array('db_activity_manage_roles',
        array(
            "en-US" => 'Manage Roles',
            "fa" => 'مدیریت نقش‌ها',
            "fr-FR" => 'Modifier les rôles',
            "ja" => '役割管理',
            "ko" => '사용자 권한 관리',
            "vi-VN" => 'Quản lý chức vụ',
        )),
    "manageGroups" => array('db_activity_manage_groups',
        array(
            "en-US" => 'Manage Groups',
            "fr-FR" => 'Modifier les groupes',
        )),
    "manageCrawls" => array('db_activity_manage_crawl',
        array(
            "en-US" => 'Manage Crawl',
            "fa" => 'مدیریت خزش‌ها',
            "fr-FR" => 'Modifier les indexes',
            "ja" => '検索管理',
            "ko" => '크롤 관리',
            "vi-VN" => 'Quản lý sự bò',
        )),
    "groupFeeds" => array('db_activity_group_feeds',
        array(
            "en-US" => 'Feeds and Wikis',
        )),
    "mixCrawls" => array('db_activity_mix_crawls',
        array(
            "en-US" => 'Mix Crawls',
            "fa" => 'ترکیب‌های خزش‌ها',
            "fr-FR" => 'Mélanger les indexes',
        )),
    "manageClassifiers" => array('db_activity_manage_classifiers',
        array(
            "en-US" => 'Manage Classifiers',
            "fa" => '',
            "fr-FR" => 'Classificateurs',
        )),
    "pageOptions" => array('db_activity_file_options',
        array(
            "en-US" => 'Page Options',
            "fa" => 'تنظیمات صفحه',
            "fr-FR" => 'Options de fichier',
        )),
    "resultsEditor" => array('db_activity_results_editor',
        array(
            "en-US" => 'Results Editor',
            "fa" => 'ویرایشگر نتایج',
            "fr-FR" => 'Éditeur de résultats',
        )),
    "searchSources" => array('db_activity_search_services',
        array(
            "en-US" => 'Search Sources',
            "fa" => 'منابع جستجو',
            "fr-FR" => 'Sources de recherche',
        )),
    "manageMachines" => array('db_activity_manage_machines',
        array(
            "en-US" => 'Manage Machines',
            "fa" => 'مدیریت دستگاه‌ها',
            "fr-FR" => 'Modifier les ordinateurs',
        )),
    "manageLocales" => array('db_activity_manage_locales',
        array(
            "en-US" => 'Manage Locales',
            "fa" => 'مدیریت زبان‌ها',
            "fr-FR" => 'Modifier les lieux',
            "ja" => 'ローケル管理',
            "ko" => '로케일 관리',
            "vi-VN" => 'Quản lý miền địa phương',
        )),
    "serverSettings" => array('db_activity_server_settings',
        array(
            "en-US" => 'Server Settings',
            "fr-FR" => 'Serveurs',
        )),
    "security" => array('db_activity_security',
        array(
            "en-US" => 'Security',
            "fr-FR" => 'Sécurité',
        )),

    "configure" => array('db_activity_configure',
        array(
            "en-US" => 'Configure',
            "fa" => 'پیکربندی',
            "fr-FR" => 'Configurer',
            "ja" => '設定',
            "ko" => '구성',
            "vi-VN" => 'Sắp xếp hoạt động dựa theo hoạch định',
        ))

);
$i = 1;
foreach($activities as $activity => $translation_info) {
    // set-up activity
    $db->execute("INSERT INTO ACTIVITY VALUES ($i, $i, '$activity')");
    //give admin role the ability to have that activity
    $db->execute("INSERT INTO ROLE_ACTIVITY VALUES (".ADMIN_ROLE.", $i)");
    $db->execute("INSERT INTO TRANSLATION
            VALUES($i, '{$translation_info[0]}')");
    foreach($translation_info[1] as $locale_tag => $translation) {
        $index = $locale_index[$locale_tag];
        $db->execute("INSERT INTO TRANSLATION_LOCALE VALUES ($i, $index,
            '$translation')");
    }
    $i++;
}
$new_user_activities = array(
    "manageAccount",
    "manageGroups",
    "mixCrawls",
    "groupFeeds"
);
foreach($new_user_activities as $new_activity) {
    $i = 1;
    foreach($activities as $key => $value) {
        if($new_activity == $key){
        //give new user role the ability to have that activity
            $db->execute("INSERT INTO ROLE_ACTIVITY VALUES (".
                USER_ROLE.", $i)");
        }
        $i++;
    }
}
$db->execute("INSERT INTO MEDIA_SOURCE VALUES ('1342634195',
    'YouTube', 'video', 'http://www.youtube.com/watch?v={}&',
    'http://i1.ytimg.com/vi/{}/default.jpg', '')");
$db->execute("INSERT INTO MEDIA_SOURCE VALUES ('1342634196',
    'MetaCafe', 'video', 'http://www.metacafe.com/watch/{}/',
    'http://www.metacafe.com/thumb/{}.jpg', '')");
$db->execute("INSERT INTO MEDIA_SOURCE VALUES ('1342634197',
    'DailyMotion', 'video', 'http://www.dailymotion.com/video/{}',
    'http://www.dailymotion.com/thumbnail/video/{}', '')");
$db->execute("INSERT INTO MEDIA_SOURCE VALUES ('1342634198',
    'Vimeo', 'video', 'http://player.vimeo.com/video/{}',
    'http://www.yioop.com/resources/blank.png?{}', '')");
$db->execute("INSERT INTO MEDIA_SOURCE VALUES ('1342634199',
    'Break.com', 'video', 'http://www.break.com/index/{}',
    'http://www.yioop.com/resources/blank.png?{}', '')");
$db->execute("INSERT INTO MEDIA_SOURCE VALUES ('1342634200',
    'Yahoo News', 'rss', 'http://news.yahoo.com/rss/',
    '', 'en')");
$db->execute("INSERT INTO CRAWL_MIXES VALUES (2, 'images', ".ROOT_ID.", -1)");
$db->execute("INSERT INTO MIX_FRAGMENTS VALUES(2, 0, 1)");
$db->execute("INSERT INTO MIX_COMPONENTS VALUES(
    2, 0, 1, 1, 'media:image site:doc')");
$db->execute("INSERT INTO CRAWL_MIXES VALUES (3, 'videos', ".ROOT_ID.", -1)");
$db->execute("INSERT INTO MIX_FRAGMENTS VALUES(3, 0, 1)");
$db->execute("INSERT INTO MIX_COMPONENTS VALUES(
    3, 0, 1, 1, 'media:video site:doc')");
$db->execute("INSERT INTO CRAWL_MIXES VALUES (4, 'news', ".ROOT_ID.", -1)");
$db->execute("INSERT INTO MIX_FRAGMENTS VALUES(4, 0, 1)");
$db->execute("INSERT INTO MIX_COMPONENTS VALUES(4, 0, 1, 1,
    'media:news')");

$db->execute("INSERT INTO SUBSEARCH VALUES('db_subsearch_images',
    'images','m:2', 50)");
$db->execute("INSERT INTO TRANSLATION VALUES (1002,'db_subsearch_images')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1002, 1, 'Images' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1002, 2, 'الصور' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1002, 5, 'Images' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1002, 15, 'Hình' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1002, 16, '图象' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1002, 20, 'تصاویر' )");

$db->execute("INSERT INTO SUBSEARCH VALUES ('db_subsearch_videos',
    'videos','m:3', 10)");
$db->execute("INSERT INTO TRANSLATION VALUES (1003,'db_subsearch_videos')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1003, 1, 'Videos' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1003, 2, 'فيديو' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1003, 5, 'Vidéos' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1003, 15, 'Thâu hình' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1003, 16, '录影' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1003, 20, 'ویدیوها' )");

$db->execute("INSERT INTO SUBSEARCH VALUES ('db_subsearch_news',
    'news','m:4',20)");
$db->execute("INSERT INTO TRANSLATION VALUES (1004,'db_subsearch_news')");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1004, 1, 'News' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1004, 2, 'أخبار' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1004, 5, 'Actualités' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1004, 15, 'Tin tức' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1004, 16, '新闻' )");
$db->execute("INSERT INTO TRANSLATION_LOCALE VALUES
        (1004, 20, 'اخبار' )");

if(stristr(DB_HOST, "pgsql") !== false) {
    /* For postgres count initial values of SERIAL sequences
       will be screwed up unless do
     */
    $auto_tables = array("ACTIVITY" =>"ACTIVITY_ID",
        "GROUP_ITEM" =>"GROUP_ITEM_ID", "GROUP_PAGE" => "GROUP_PAGE_ID",
        "GROUPS" => "GROUP_ID", "LOCALE"=> "LOCALE_ID", "ROLE" => "ROLE_ID",
        "TRANSLATION" => "TRANSLATION_ID", "USERS" => "USER_ID");
    foreach($auto_tables as $table => $auto_column) {
        $sql = "SELECT MAX($auto_column) AS NUM FROM $table";
        $result = $db->execute($sql);
        $row = $db->fetchArray($result);
        $next = $row['NUM'];
        $sequence = strtolower("{$table}_{$auto_column}_seq");
        $sql = "SELECT setval('$sequence', $next)";
        $db->execute($sql);
        $sql = "SELECT nextval('$sequence')";
        $db->execute($sql);
    }
}

$db->disconnect();
if(in_array(DBMS, array('sqlite','sqlite3' ))){
    chmod(CRAWL_DIR."/data/".DB_NAME.".db", 0666);
}
echo "Create DB succeeded\n";
?>