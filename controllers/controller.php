<?php
/** 
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009, 2010, 2011  Chris Pollett chris@pollett.org
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
 * @subpackage controller
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009, 2010, 2011
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 * Load crawlHash function
 */
require_once BASE_DIR."/lib/utility.php"; 

/**
 * Base controller class for all controllers on
 * the SeekQuarry site.
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage controller
 */

abstract class Controller 
{
    /**
     * Array of the model classes used by this controller 
     * (contructor loads these)
     * @var array
     */
    var $models = array();
    /**
     * Array of the view classes used by this controller 
     * (contructor loads these)
     * @var array
     */
    var $views = array();

    /**
     *  Loads all of the models listed in the models field array.
     *  Loads each view the controlle might use listed in the views field array
     */
	 var $components = array();

    public function __construct() 
    {
        require_once BASE_DIR."/models/model.php";

        foreach($this->models as $model) {
            require_once BASE_DIR."/models/".$model."_model.php";
             
            $model_name = ucfirst($model)."Model";
            $model_instance_name = lcfirst($model_name);

            $this->$model_instance_name = new $model_name();
        }

        require_once BASE_DIR."/views/view.php";

        foreach($this->views as $view) {
            require_once BASE_DIR."/views/".$view."_view.php";
            $view_name = ucfirst($view)."View";
            $view_instance_name = lcfirst($view_name);

            $this->$view_instance_name = new $view_name();
        }
		
		foreach($this->components as $component) 
		{
            require_once BASE_DIR."/lib/components/".$component."_component.php";
			$component_name = ucfirst($component)."Component";
			$component_instance_name = lcfirst($component_name);
			$this->$component_instance_name = new $component_name();
		}
		
	}

    /**
     *  This function should be overriden to web handle requests
     */
    public abstract function processRequest();

    /**
     *  Send the provided view to output, drawing it with the given
     *  data variable, using the current locale for translation, and
     *  writing mode
     *
     *  @param string $view   the name of the view to draw
     *  @param array $data   an array of values to use in drawing the view
     */
    public function displayView($view, $data) 
    {
        $view_name = ucfirst($view)."View";
        $view_instance_name = lcfirst($view_name);
        $data['LOCALE_TAG'] = getLocaleTag();
        $data['LOCALE_DIR'] = getLocaleDirection();
        $data['BLOCK_PROGRESSION'] = getBlockProgression();
        $data['WRITING_MODE'] = getWritingMode();
        if(QUERY_STATISTICS) {
            $data['QUERY_STATISTICS'] = array();
            $data['TOTAL_ELAPSED_TIME'] = 0;
            foreach($this->models as $model) {
                $model_name = ucfirst($model)."Model";
                $model_instance_name = lcfirst($model_name);
                $data['QUERY_STATISTICS'] = array_merge(
                    $data['QUERY_STATISTICS'], 
                    $this->$model_instance_name->db->query_log);
                $data['TOTAL_ELAPSED_TIME'] += 
                    $this->$model_instance_name->db->total_time;
            }
        }
        $this->$view_instance_name->render($data); 
    }

    /**
     * Generates a cross site request forgery preventing token based on the 
     * provided user name, the current time and the hidden AUTH_KEY
     *
     * @param string $user   username to use to generate token
     * @return string   a csrf token
     */
    public function generateCSRFToken($user)
    {
        $time = time();
        return crawlHash($user.$time.AUTH_KEY)."|$time";
    }

    /**
     * Checks if the form CSRF (cross-site request forgery preventing) token
     * matches the given user and has not expired (1 hour till expires)
     *
     * @param string $token_name attribute of $_REQUEST containing CSRFToken
     * @param string $user  username
     * @return bool  whether the CSRF token was valid
     */
    public function checkCSRFToken($token_name, $user)
    {
        $token_okay = false;
        if(isset($_REQUEST[$token_name]) && 
            strlen($_REQUEST[$token_name]) == 22) {
            $token_parts = explode("|", $_REQUEST[$token_name]);

            if($token_parts[1] + 3600 > time() && 
                crawlHash($user.$token_parts[1].AUTH_KEY) == $token_parts[0]) {
                $token_okay = true;
            }
        }

        return $token_okay;
    }

    /**
     * Used to clean strings that might be tainted as originate from the user
     *
     * @param mixed $value tainted data
     * @param string $type type of data in value: one of int, hash, or string
     * @param mixed $default if $value is not set default value is returned, 
     *      this isn't used much since if the error_reporting is E_ALL 
     *      or -1 you would still get a Notice.
     * @return string the clean input matching the type provided
     */
    public function clean($value, $type, $default = NULL) 
    {
        $clean_value = NULL;
        switch($type) {
        case "int":
            if(isset($value)) {
                $clean_value = intval($value);
            } else if ($default != NULL) {
                $clean_value = $default;
            } else {
                $clean_value = 0;
            }
        break;

        case "float":
            if(isset($value)) {
                $clean_value = floatval($value);
            } else if ($default != NULL) {
                $clean_value = $default;
            } else {
                $clean_value = 0;
            }
        break;

        case "hash";
            if(isset($value)) {
                if(strlen($value) == strlen(crawlHash("A")) && 
                    base64_decode($value)) {
                    $clean_value = $value;
                }
            } else {
                $clean_value = $default;
            }
        break;

        case "string":
            if(isset($value)) {
                $clean_value = htmlentities($value, ENT_QUOTES, "UTF-8");
            } else {
                $clean_value = $default;
            }
            break;

        }

        return $clean_value;
    }

    /**
     * Checks the request if a request is for a valid activity and if it uses 
     * the correct authorization key 
     *
     * @return bool whether the request was valid or not
     */
    function checkRequest()
    {
        if(!isset($_REQUEST['time']) || 
            !isset($_REQUEST['session']) || 
            !in_array($_REQUEST['a'], $this->activities)) { return; }

        $time = $_REQUEST['time']; 
            // request must be within an hour of this machine's clock

        if(abs(time() - $time) > 3600) { return false;}

        $session = $_REQUEST['session'];

        if(md5($time . AUTH_KEY) != $session) { return false; }

        return true;
    }
}
?>
