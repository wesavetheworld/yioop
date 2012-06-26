<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2012  Chris Pollett chris@pollett.org
 *
 *  LICENSE:
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *  END LICENSE
 *
 * @author Chris Pollett chris@pollett.org
 * @package seek_quarry
 * @subpackage bin
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(php_sapi_name() != 'cli') {echo "BAD REQUEST"; exit();}

/** Calculate base directory of script @ignore*/
define("BASE_DIR", substr(
    dirname(realpath($_SERVER['PHP_SELF'])), 0, 
    -strlen("/bin")));

ini_set("memory_limit","850M"); /* 
        reindex sometimes takes more than the default 128M, 850 to be safe */

/** This tool does not need logging*/
define("LOG_TO_FILES", false);

/** Load in global configuration settings */
require_once BASE_DIR.'/configs/config.php';
if(!PROFILE) {
    echo "Please configure the search engine instance by visiting" .
        "its web interface on localhost.\n";
    exit();
}

/** NO_CACHE means don't try to use memcache*/
define("NO_CACHE", true);

/** USE_CACHE false rules out file cache as well*/
define("USE_CACHE", false);

/** Load the class that maintains our URL queue */
require_once BASE_DIR."/lib/web_queue_bundle.php";

/** Load word->{array of docs with word} index class */
require_once BASE_DIR."/lib/index_archive_bundle.php";

/** Load the iterator classes for non-yioop archives*/
foreach(glob(BASE_DIR."/lib/archive_bundle_iterators/*_iterator.php")
    as $filename) {
    require_once $filename;
}

/** Used for manipulating urls*/
require_once BASE_DIR."/lib/url_parser.php";

/**  For crawlHash function */
require_once BASE_DIR."/lib/utility.php";

/** Get the database library based on the current database type */
require_once BASE_DIR."/models/datasources/".DBMS."_manager.php";

/** Load FetchUrl, used by the MediaWiki archive iterator */
require_once BASE_DIR."/lib/fetch_url.php";

/** Loads common constants for web crawling*/
require_once BASE_DIR."/lib/crawl_constants.php";

/*
 *  We'll set up multi-byte string handling to use UTF-8
 */
mb_internal_encoding("UTF-8");
mb_regex_encoding("UTF-8");

/**
 * Command line program that allows one to examine the content of
 * the WebArchiveBundles and IndexArchiveBundles of Yioop crawls.
 * For now it supports returning header information about bundles,
 * as well as pretty printing the page/summary contents of the bundle.
 *
 * The former can be gotten from a bundle by running arc_tool with a
 * command like:
 * php arc_tool.php info bundle_name
 *
 * The latter can be gotten from a bundle by running arc_tool with a 
 * command like:
 * php arc_tool.php list bundle_name start_doc_num num_results
 *
 * @author Chris Pollett (non-yioop archive code derived from earlier
 *      stuff by Shawn Tice)
 * @package seek_quarry
 */
class ArcTool implements CrawlConstants
{

    /** 
     * The maximum number of documents the arc_tool list function
     * will read into memory in one go.
     */
    const MAX_BUFFER_DOCS = 200;

    /**
     * Initializes the ArcTool, for now does nothing
     */
    function __construct() 
    {

    }

    /**
     * Runs the ArcTool on the supplied command line arguments
     */
    function start()
    {
        global $argv;

        if(!isset($argv[1]) || (!isset($argv[2]) && $argv[1] != "list")) {
            $this->usageMessageAndExit();
        }
        if($argv[1] != "list") {
            $path =  $bundle_name = UrlParser::getDocumentFilename($argv[2]);
            if($path == $argv[2] && !file_exists($path)) {
                $path = CRAWL_DIR."/cache/".$path;
                if(!file_exists($path)) {
                    $path = CRAWL_DIR."/cache/archives/".$argv[2];
                }
            }
        }

        switch($argv[1])
        {
            case "list":
                $this->outputArchiveList();
            break;

            case "info":
                $this->outputInfo($path);
            break;

            case "reindex":
                $this->reindexIndexArchive($path);
            break;

            case "mergetiers":
                if(!isset($argv[3])) {
                    $this->usageMessageAndExit();
                }
                $this->reindexIndexArchive($path, $argv[3]);
            break;

            case "show":
                if(!isset($argv[3])) {
                    $this->usageMessageAndExit();
                }
                if(!isset($argv[4])) {
                    $argv[4] = 1;
                }
                $this->outputShowPages($path, $argv[3], $argv[4]);
            break;

            default:
                $this->usageMessageAndExit();
        }

    }

    /**
     * Lists the Web or IndexArchives in the crawl directory
     */
     function outputArchiveList()
     {
        $yioop_pattern = CRAWL_DIR."/cache/*{".self::archive_base_name.",".
            self::index_data_base_name."}*";

        $archives = glob($yioop_pattern, GLOB_BRACE);
        $archives_found = false;
        if(is_array($archives) && count($archives) > 0) {
            $archives_found = true;
            echo "\nFound Yioop Archives:\n";
            echo "=====================\n";
            foreach($archives as $archive_path) {
                echo $this->getArchiveName($archive_path)."\n";
            }
        }

        $nonyioop_pattern = CRAWL_DIR."/cache/archives/*/arc_description.ini";
        $archives = glob($nonyioop_pattern);
        if(is_array($archives) && count($archives) > 0 ) {
            $archives_found = true;
            echo "\nFound Non-Yioop Archives:\n";
            echo "=========================\n";
            foreach($archives as $archive_path) {
                $len = strlen("/arc_description.ini");
                $path = substr($archive_path, 0, -$len);
                echo $this->getArchiveName($path)."\n";
            }
        }

        if(!$archives_found) {
            echo "No archives currently in crawl directory \n";
        }
        echo "\n";
     }

    /**
     * Determines whether the supplied path is a WebArchiveBundle or
     * an IndexArchiveBundle or non-Yioop Archive. Then outputs
     * to stdout header information about the
     * bundle by calling the appropriate sub-function.
     *
     * @param string $archive_path the oath of a directory that holds 
     *      WebArchiveBundle,IndexArchiveBundle, or non-Yioop archive data
     */
    function outputInfo($archive_path)
    {
        $bundle_name = $this->getArchiveName($archive_path);
        echo "Bundle Name: ".$bundle_name."\n";
        $archive_type = $this->getArchiveKind($archive_path);
        echo "Bundle Type: ".$archive_type."\n";
        if($archive_type === false) {
            $this->badFormatMessageAndExit($archive_path);
        }
        if(in_array($archive_type, array("IndexArchiveBundle", 
            "WebArchiveBundle"))) {
            $call = "outputInfo".$archive_type;
            $info = $archive_type::getArchiveInfo($archive_path);
            $this->$call($info, $archive_path);
        }
    }

    /**
     * Given a complete path to an archive returns its filename
     *
     * @param string $archive_path a path to a yioop or non-yioop archive
     * @return string its filename
     */
    function getArchiveName($archive_path)
    {
        $start = CRAWL_DIR."/cache/archives/";
        if(strstr($archive_path, $start)) {
            $start_len = strlen($start);
            $name = substr($archive_path, $start_len);
        } else {
            $name = UrlParser::getDocumentFilename($archive_path);
        }
        return $name;
    }

    /**
     * Used to recompute the dictionary of an index archive -- either from
     * scratch using the index shard data or just using the current dictionary
     * but merging the tiers into one tier
     *
     * @param string $path file path to dictionary of an IndexArchiveBundle
     * @param int $max_tier tier up to which the dicitionary tiers should be
     *      merge (typically a value greater than the max_tier of the
     *      dictionary)
     */
    function reindexIndexArchive($path, $max_tier = -1)
    {
        if($this->getArchiveKind($path) != "IndexArchiveBundle") {
            echo "\n$path ...\n".
                "  is not an IndexArchiveBundle so cannot be re-indexed\n\n";
            exit();
        }
        $shards = glob($path."/posting_doc_shards/index*");
        if(is_array($shards)) {
            if($max_tier == -1) {
                $dbms_manager = DBMS."Manager";
                $db = new $dbms_manager();
                $db->unlinkRecursive($path."/dictionary", false);
                IndexDictionary::makePrefixLetters($path."/dictionary");
            }
            $dictionary = new IndexDictionary($path."/dictionary");

            if($max_tier == -1) {
                $max_generation = 0;
                foreach($shards as $shard_name) {
                    $file_name = UrlParser::getDocumentFilename($shard_name);
                    $generation = (int)substr($file_name, strlen("index"));
                    $max_generation = max($max_generation, $generation);
                }
                for($i = 0; $i < $max_generation + 1; $i++) {
                    $shard_name = $path."/posting_doc_shards/index$i";
                    echo "\nShard $i\n";
                    $shard = new IndexShard($shard_name, $i,
                        NUM_DOCS_PER_GENERATION, true);
                    $dictionary->addShardDictionary($shard);
                }
                $max_tier = $dictionary->max_tier;
            }
            echo "\nFinal Merge Tiers\n";
            $dictionary->mergeAllTiers(NULL, $max_tier);
            $db->setWorldPermissionsRecursive($path."/dictionary");
            echo "\nReindex complete!!\n";
        } else {
            echo "\n$path ...\n".
                "  does not contain posting shards so cannot be re-indexed\n\n";

        }
    }

    /**
     * Outputs to stdout header information for a IndexArchiveBundle
     * bundle.
     *
     * @param array $info header info that has already been read from
     *      the description.txt file
     * @param string $archive_path file path of the folder containing the bundle
     */
    function outputInfoIndexArchiveBundle($info, $archive_path)
    {
        $more_info = unserialize($info['DESCRIPTION']);
        unset($info['DESCRIPTION']);
        $info = array_merge($info, $more_info);
        echo "Description: ".$info['DESCRIPTION']."\n";
        $generation_info = unserialize(
            file_get_contents("$archive_path/generation.txt"));
        $num_generations = $generation_info['ACTIVE']+1;
        echo "Number of generations: ".$num_generations."\n";
        echo "Number of stored links and documents: ".$info['COUNT']."\n";
        echo "Number of stored documents: ".$info['VISITED_URLS_COUNT']."\n";
        $crawl_order = ($info[self::CRAWL_ORDER] == self::BREADTH_FIRST) ?
            "Bread First" : "Page Importance";
        echo "Crawl order was: $crawl_order\n";
        echo "Seed sites:\n";
        foreach($info[self::TO_CRAWL] as $seed) {
            echo "   $seed\n";
        }
        if($info[self::RESTRICT_SITES_BY_URL]) {
            echo "Sites allowed to crawl:\n";
            foreach($info[self::ALLOWED_SITES] as $site) {
                echo "   $site\n";
            }
        }
        echo "Sites not allowed to be crawled:\n";
        if(is_array($info[self::DISALLOWED_SITES])) {
            foreach($info[self::DISALLOWED_SITES] as $site) {
                echo "   $site\n";
            }
        }
        echo "Meta Words:\n";
        foreach($info[self::META_WORDS] as $word) {
            echo "   $word\n";
        }
        echo "\n";
    }

    /**
     * Outputs to stdout header information for a WebArchiveBundle
     * bundle.
     *
     * @param array $info header info that has already been read from
     *      the description.txt file
     * @param string $archive_path file path of the folder containing the bundle

     */
    function outputInfoWebArchiveBundle($info, $archive_path)
    {
        echo "Description: ".$info['DESCRIPTION']."\n";
        echo "Number of stored documents: ".$info['COUNT']."\n";
        echo "Maximum Number of documents per partition: ".
            $info['NUM_DOCS_PER_PARTITION']."\n";
        echo "Number of partitions: ".
            ($info['WRITE_PARTITION']+1)."\n";
        echo "\n";
    }

    /**
     * Used to list out the pages/summaries stored in a bundle at
     * $archive_path. It lists to stdout $num many documents starting at $start.
     *
     * @param string $archive_path path to bundle to list documents for
     * @param int $start first document to list
     * @param int $num number of documents to list
     */
    function outputShowPages($archive_path, $start, $num)
    {
        $fields_to_print = array(
            self::URL => "URL",
            self::IP_ADDRESSES => "IP ADDRESSES",
            self::TIMESTAMP => "DATE",
            self::HTTP_CODE => "HTTP RESPONSE CODE",
            self::TYPE => "MIMETYPE",
            self::ENCODING => "CHARACTER ENCODING",
            self::DESCRIPTION => "DESCRIPTION",
            self::PAGE => "PAGE DATA");
        $archive_type = $this->getArchiveKind($archive_path);
        if($archive_type === false) {
            $this->badFormatMessageAndExit($archive_path);
        }

        $nonyioop = false;
        //for yioop archives we set up a dummy iterator
        $iterator =  (object) array();
        $iterator->end_of_iterator = false;
        if($archive_type == "IndexArchiveBundle") {
            $info = $archive_type::getArchiveInfo($archive_path);
            $num = min($num, $info["COUNT"] - $start);
            $generation_info = unserialize(
                file_get_contents("$archive_path/generation.txt"));
            $num_generations = $generation_info['ACTIVE']+1;
            $archive = new WebArchiveBundle($archive_path."/summaries");
        } else if ($archive_type == "WebArchiveBundle") {
            $info = $archive_type::getArchiveInfo($archive_path);
            $num = min($num, $info["COUNT"] - $start);
            $num_generations = $info["WRITE_PARTITION"]+1;
            $archive = new WebArchiveBundle($archive_path);
        } else {
            $nonyioop = true;
            $num_generations = 1;
            //for non-yioop archives we set up a real iterator
            $iterator=$this->instantiateIterator($archive_path, $archive_type);
            if($iterator === false) {
                $this->badFormatMessageAndExit($archive_path);
            }
        }
        if(!$nonyioop) {
            if(isset($this->tmp_results)) unset($this->tmp_results);
        }
        $num = max($num, 0);
        $total = $start + $num;
        $seen = 0;
        $generation = 0;
        while(!$iterator->end_of_iterator && 
            $seen < $total && $generation < $num_generations) {
            if($nonyioop) {
                $partition = (object) array();
                $partition->count = 1;
            } else {
                $partition = $archive->getPartition($generation, false);
                if($partition->count < $start && $seen < $start) {
                    $generation++;
                    $seen += $partition->count;
                    continue;
                }
            }
            $seen_generation = 0;
            while($seen < $total && $seen_generation < $partition->count) {
                if($nonyioop) {
                    $num_to_get = min(self::MAX_BUFFER_DOCS, $total - $seen);
                    $objects = $iterator->nextPages($num_to_get);
                    $seen += count($objects);
                } else {
                    $num_to_get = min($total - $seen,  
                        $partition->count - $seen_generation, 
                        self::MAX_BUFFER_DOCS);
                    $objects = $partition->nextObjects($num_to_get);
                    $seen += $num_to_get;
                    $seen_generation += $num_to_get;
                }
                if($seen >= $start) {
                    $num_to_show = min($seen - $start, $num_to_get);
                    $cnt = 0;
                    $first = $num_to_get - $num_to_show;
                    foreach($objects as $pre_object) {
                        if($cnt >= $first) {
                            $out = "";
                            if($nonyioop) {
                                $object = $pre_object;
                            } else {
                                if(!isset($pre_object[1])) continue;
                                $object = $pre_object[1];
                            }
                            if(isset($object[self::TIMESTAMP])) {
                                $object[self::TIMESTAMP] = 
                                    date("r", $object[self::TIMESTAMP]);
                            }
                            foreach($fields_to_print as $key => $name) {
                                if(isset($object[$key])) {
                                    $out .= "[$name]\n";
                                    if($key != self::IP_ADDRESSES) {
                                        $out .= $object[$key]."\n";
                                    } else {
                                        foreach($object[$key] as $address) {
                                            $out .= $address."\n";
                                        }
                                    }
                                }
                            }
                            $out .= "==========\n\n";
                            echo "BEGIN ITEM, LENGTH:".strlen($out)."\n";
                            echo $out;
                        }
                        $cnt++;
                    }
                }
            }
            $generation++;
        }
        if(isset($this->tmp_results)) {
            //garbage collect savepoint folder for non-yioop archives
            $dbms_manager = DBMS."Manager";
            $db = new $dbms_manager();
            $db->unlinkRecursive($this->tmp_results);
        }
    }

    /**
     * Used to create an archive_bundle_iterator for a non-yioop archive
     * As these iterators sometimes make use of a folder to store savepoints
     * We create a temporary folder for this purpose in the current directory
     * This should be garbage collected elsewhere.
     *
     * @param string $archive_path path to non-yioop archive
     * @param string $iterator_type name of archive_bundle_iterator used to
     *      iterate over archive.
     * @param return an ArchiveBundleIterator of the correct type using
     *      a temporary folder to store savepoints
     */
    function instantiateIterator($archive_path, $iterator_type)
    {
        $iterate_timestamp = filectime($archive_path);
        $result_timestamp = strval(time());
        $this->tmp_results = 'TmpArchiveExtract'.$iterate_timestamp;
        if(!file_exists($this->tmp_results)) {
            mkdir($this->tmp_results);
        } else {
            $dbms_manager = DBMS."Manager";
            $db = new $dbms_manager();
            $db->unlinkRecursive($this->tmp_results);
        }
        $iterator_class = "{$iterator_type}Iterator";
        $iterator = new $iterator_class($iterate_timestamp, $archive_path,
            $result_timestamp, $this->tmp_results);
        return $iterator;
    }


    /**
     * Given a folder name, determines the kind of bundle (if any) it holds.
     * It does this based on the expected location of the description.txt file,
     * or arc_description.ini (in the case of a non-yioop archive)
     *
     * @param string $archive_path the path to archive folder
     * @return string the archive bundle type, either: WebArchiveBundle or
     *      IndexArchiveBundle
     */
    function getArchiveKind($archive_path)
    {
        if(file_exists("$archive_path/description.txt")) {
            return "WebArchiveBundle";
        }
        if(file_exists("$archive_path/summaries/description.txt")) {
            return "IndexArchiveBundle";
        }
        $desc_path = "$archive_path/arc_description.ini";
        if(file_exists($desc_path)) {
            $desc = parse_ini_file($desc_path);
            if(!isset($desc['arc_type'])) {
                return false;
            }
            return $desc['arc_type'];
        }
        return false;
    }

    /**
     * Outputs the "hey, this isn't a known bundle message" and then exit()'s.
     * @param string $archive_name name or path to what was supposed to be
     *      an archive
     */
    function badFormatMessageAndExit($archive_name) 
    {
        echo "$archive_name does not appear to be a web or index ".
        "archive bundle\n";
        exit();
    }

    /**
     * Outputs the "how to use this tool message" and then exit()'s.
     */
    function usageMessageAndExit() 
    {
        echo "\narc_tool is used to look at the contents of\n";
        echo "WebArchiveBundles and IndexArchiveBundles.\n";
        echo "It will look for these using the path provided or \n";
        echo "will check in the Yioop! crawl directory as a fall back\n\n";
        echo "The available commands for arc_tool are:\n\n";
        echo "php arc_tool.php info bundle_name //return info about\n".
            "//documents stored in archive.\n\n";
        echo "php arc_tool.php list //returns a list \n".
            "//of all the archives in the Yioop! crawl directory, including\n".
            "//non-Yioop! archives in the cache/archives sub-folder.\n\n";
        echo "php arc_tool.php mergetiers bundle_name max_tier\n".
            "//merges tiers of word dictionary into one tier up to max_tier\n";
        echo "\nphp arc_tool.php reindex bundle_name \n".
            "//reindex the word dictionary in bundle_name\n\n";
        echo "php arc_tool.php show bundle_name start num //outputs\n".
            "//items start through num from bundle_name\n".
            "//or name of non-Yioop archive crawl folder.\n\n";
        exit();
    }
}

$arc_tool =  new ArcTool();
$arc_tool->start();
?>
