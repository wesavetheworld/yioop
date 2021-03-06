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
 * @subpackage model
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */
if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}
/** Loads base model class if necessary*/
require_once BASE_DIR."/models/model.php";
/** For crawlHash function */
require_once BASE_DIR."/lib/utility.php";
/** Used to fetches web pages to get statuses of individual machines*/
require_once BASE_DIR."/lib/fetch_url.php";
/**
 * Used to remember the last time the web app ran periodic activities
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage model
 */
class CronModel extends Model
{
    /**
     * File name used to store the cron table associative array
     * @var string
     */
    var $cron_file;
    /**
     * An associative array of key_name => timestamps use to indicate
     * when various cron activities were last performed
     * @var array
     */
    var $cron_table;
    /**
     * {@inheritDoc}
     *
     * @param string $db_name the name of the database for the search engine
     * @param bool $connect whether to connect to the database by default
     *     after making the datasource class
     */
    function __construct($db_name = DB_NAME, $connect = true)
    {
        parent::__construct($db_name, $connect);
        $this->cron_table === NULL;
        $this->cron_file = WORK_DIRECTORY."/data/cron_time.txt";
    }
    /**
     * Returns the timestamp of last time the $key field was updated in
     * the cron_time.txt file associate array. Not using db as sqlite
     * seemed to have locking issues if the transaction takes a while
     *
     * @param $key field to find last update of
     * @return int a Unix timestamp
     */
    function getCronTime($key)
    {
        if($this->cron_table === NULL) {
            $this->loadCronTable();
        }
        if(!isset($this->cron_table[$key])) {
            $time = time();
        } else {
            $time = $this->cron_table[$key];
        }
        return $time;
    }
    /**
     * Loads into $this->cron_table the associative array of key =>timestamps
     * that is a cron table
     */
    function loadCronTable()
    {
        if(file_exists($this->cron_file)) {
            $this->cron_table = unserialize(file_get_contents(
                $this->cron_file));
        } else {
            $this->cron_table = array();
        }
    }
    /**
     * Updates the Cron timestamp to the current time.
     *
     * @param $key field to update time for
     * @param bool $transaction if you are doing a sequence of cron updates
     *     you can set this value to true so data is not immediately
     *     written to disk. Then you can write to disk using saveCronTable
     *     so only do one write.
     */
    function updateCronTime($key, $transaction = false)
    {
        if($this->cron_table === NULL) {
            $this->loadCronTable();
        }
        $this->cron_table[$key] = time();
        if(!$transaction) {
            file_put_contents($this->cron_file, serialize($this->cron_table));
            restore_error_handler();
            @chmod($this->cron_file, 0777);
            set_error_handler("yioop_error_handler");
        }
    }
    /**
     * Saerializes and save the current cron table to disk
     */
    function saveCronTable()
    {
        file_put_contents($this->cron_file, serialize($this->cron_table));
        restore_error_handler();
        @chmod($this->cron_file, 0777);
        set_error_handler("yioop_error_handler");
    }
}

 ?>
