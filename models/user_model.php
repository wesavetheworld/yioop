<?php
/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2014  Chris Pollett chris@pollett.org
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
 * @subpackage model
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/** Loads the base class */
require_once BASE_DIR."/models/model.php";

/** Used for the crawlHash function */
require_once BASE_DIR."/lib/utility.php";

/**
 * This class is used to handle
 * database statements related to User Administration
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage model
 */
class UserModel extends Model
{
    /**
     * @var string
     */
    var $from_tables = "USERS";

    /**
     *  @var string
     */
    var $where_callback = "userWhereCallback";
    /**
     *  @var string
     */
     var $select_callback = "userSelectCallback";
    /**
     *  Associations of the form
     *      name of field for web forms => database column names/abbreviations
     *  In this case, things will in general map to the USERS table in the
     *  Yioop data base
     *  var array
     */
    var $search_table_column_map = array("first"=>"FIRST_NAME",
        "last" => "LAST_NAME", "user" => "USER_NAME", "email"=>"EMAIL",
        "status"=>"STATUS");
    /**
     * @param mixed $args
     */
    function userWhereCallback($args = NULL)
    {
        return "USER_ID != '".PUBLIC_USER_ID."'";
    }

    /**
     * @param mixed $args
     */
    function userSelectCallback($args = NULL)
    {
        return "USER_ID, USER_NAME, FIRST_NAME, LAST_NAME, EMAIL, STATUS";
    }

    /**
     *  Get a list of admin activities that a user is allowed to perform.
     *  This includes their name and their associated method.
     *
     *  @param string $user_id  id of user to get activities fors
     */
    function getUserActivities($user_id)
    {
        $db = $this->db;
        $activities = array();
        $status = $this->getUserStatus($user_id);
        if(!$status || in_array($status, array(BANNED_STATUS,
            INACTIVE_STATUS))) {
            return array();
        }
        $locale_tag = getLocaleTag();
        $limit_offset = $db->limitOffset(1);
        $sql = "SELECT LOCALE_ID FROM LOCALE ".
            "WHERE LOCALE_TAG = ? $limit_offset";
        $result = $db->execute($sql, array($locale_tag));
        $row = $db->fetchArray($result);
        $locale_id = $row['LOCALE_ID'];

        $sql = "SELECT DISTINCT A.ACTIVITY_ID AS ACTIVITY_ID, ".
            "T.TRANSLATION_ID AS TRANSLATION_ID, A.METHOD_NAME AS METHOD_NAME,".
            " T.IDENTIFIER_STRING AS IDENTIFIER_STRING FROM ACTIVITY A, ".
            " USER_ROLE UR, ROLE_ACTIVITY RA, TRANSLATION T ".
            "WHERE UR.USER_ID = ? ".
            "AND UR.ROLE_ID=RA.ROLE_ID AND T.TRANSLATION_ID=A.TRANSLATION_ID ".
            "AND RA.ACTIVITY_ID = A.ACTIVITY_ID ORDER BY A.ACTIVITY_ID ASC";

        $result = $db->execute($sql, array($user_id));
        $i = 0;
        $sub_sql = "SELECT TRANSLATION AS ACTIVITY_NAME ".
            "FROM TRANSLATION_LOCALE ".
            "WHERE TRANSLATION_ID=? AND LOCALE_ID=? $limit_offset";
            // maybe do left join at some point
        while($activities[$i] = $this->db->fetchArray($result)) {
            $id = $activities[$i]['TRANSLATION_ID'];
            $result_sub =  $db->execute($sub_sql, array($id, $locale_id));
            $translate = $db->fetchArray($result_sub);
            if($translate) {
                $activities[$i]['ACTIVITY_NAME'] = $translate['ACTIVITY_NAME'];
            }
            if(!isset($activities[$i]['ACTIVITY_NAME']) ||
                $activities[$i]['ACTIVITY_NAME'] == "") {
                $activities[$i]['ACTIVITY_NAME'] = $this->translateDb(
                    $activities[$i]['IDENTIFIER_STRING'], DEFAULT_LOCALE);
            }
            $i++;
        }
        unset($activities[$i]); //last one will be null

        return $activities;
    }

    /**
     *  Checks if a user is allowed to perform the activity given by
     *  method name
     *
     *  @param string $user_id  id of user to check
     *  @param string $method_name to see if user allowed to do
     *  @return bool whether or not the user is allowed
     */
    function isAllowedUserActivity($user_id, $method_name)
    {
        $db = $this->db;
        $sql = "SELECT COUNT(*) AS ALLOWED FROM ACTIVITY A, ".
            "USER_ROLE UR, ROLE_ACTIVITY RA WHERE UR.USER_ID = ? ".
            "AND UR.ROLE_ID=RA.ROLE_ID AND A.METHOD_NAME = ? ".
            "AND RA.ACTIVITY_ID = A.ACTIVITY_ID";
        $result = $db->execute($sql, array($user_id, $method_name));
        if($result) {
            $row = $db->fetchArray($result);
            if(isset($row["ALLOWED"]) && $row["ALLOWED"] > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns $_SESSION variable of given user from the last time
     * logged in.
     *
     * @param int $user_id id of user to get session for
     * @return array user's session data
     */
    function getUserSession($user_id)
    {
        $db = $this->db;
        $sql = "SELECT SESSION FROM USER_SESSION ".
            "WHERE USER_ID = ? " . $db->limitOffset(1);
        $result = $db->execute($sql, array($user_id));
        $row = $db->fetchArray($result);
        if(isset($row["SESSION"])) {
            return unserialize($row["SESSION"]);
        }
        return NULL;
    }

    /**
     * Stores into DB the $session associative array of given user
     *
     * @param int $user_id id of user to store session for
     * @param array $session session data for the given user
     */
    function setUserSession($user_id, $session)
    {
        $sql = "DELETE FROM USER_SESSION ".
            "WHERE USER_ID = ?";
        $this->db->execute($sql, array($user_id));
        $session_string = serialize($session);
        $sql = "INSERT INTO USER_SESSION ".
            "VALUES (?, ?)";
        $this->db->execute($sql, array($user_id, $session_string));
    }

    /**
     * Get a username by user_id
     *
     * @param string $user_id id of the user
     * @return string
     */
    function getUsername($user_id)
    {
        $db = $this->db;
        $sql = "SELECT USER_NAME FROM USERS WHERE USER_ID=?";
        $result = $db->execute($sql, array($user_id));
        $row = $db->fetchArray($result);
        return $row['USER_NAME'];
    }

    /**
     * Get the status of user by user_id
     *
     * @param string $user_id id of the user
     * @return array
     */
    function getUserStatus($user_id)
    {
        $db = $this->db;
        $sql = "SELECT STATUS FROM USERS WHERE USER_ID=?";
        $result = $db->execute($sql, array($user_id));
        $row = $db->fetchArray($result);
        return $row['STATUS'];
    }

    /**
     * Returns a row from the USERS table based on a username (case-insensitive)
     *
     * @param string $username user login to be used for look up
     * @return array corresponds to the row of that user in the USERS table
     */
    function getUser($username)
    {
        $db = $this->db;
        $sql = "SELECT * FROM USERS WHERE UPPER(USER_NAME) = UPPER(?) " .
            $db->limitOffset(1);
        $result = $db->execute($sql, array($username));
        if(!$result) {
            return false;
        }
        $row = $db->fetchArray($result);
        return $row;
    }

    /**
     *  Looks up a USERS row based on their $email (potentially not unique)
     *  and the time at which their account was create in microseconds
     *  @param string $email of user to lookup
     *  @param string $creation_time when the user's account was created in
     *      the current epoch
     *  @return array row from USERS table
     */
    function getUserByEmailTime($email, $creation_time)
    {
        $db = $this->db;
        $sql = "SELECT * FROM USERS WHERE UPPER(EMAIL) = UPPER(?)
            AND CREATION_TIME=? " . $db->limitOffset(1);
        $result = $db->execute($sql, array($email, $creation_time));
        if(!$result) {
            return false;
        }
        $row = $db->fetchArray($result);
        return $row;
    }

    /**
     * Get a status of user by user_id
     *
     * @param string $user_id id of the user
     */
    function updateUserStatus($user_id, $status)
    {
        $db = $this->db;
        if(!in_array($status, array(ACTIVE_STATUS, INACTIVE_STATUS,
            BANNED_STATUS))) {
            return;
        }
        $sql = "UPDATE USERS SET STATUS=? WHERE USER_ID=?";
        $db->execute($sql, array($status, $user_id));
    }

    /**
     * Add a user with a given username and password to the list of users
     * that can login to the admin panel
     *
     * @param string $username  the username of the user to be added
     * @param string $password  the password of the user to be added
     * @param string $firstname the firstname of the user to be added
     * @param string $lastname the lastname of the user to be added
     * @param string $email the email of the user to be added
     * @return mixed false if operation not successful, user_id otherwise
     */
    function addUser($username, $password, $firstname='', $lastname='',
        $email='', $status = ACTIVE_STATUS)
    {
        $creation_time = microTimestamp();
        $db = $this->db;
        $sql = "INSERT INTO USERS(FIRST_NAME, LAST_NAME,
            USER_NAME, EMAIL, PASSWORD, STATUS, HASH, CREATION_TIME) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?)";
        $result = $db->execute($sql, array($firstname, $lastname,
            $username, $email, crawlCrypt($password), $status,
            crawlCrypt($username.AUTH_KEY.$creation_time), $creation_time));
        if(!$user_id = $this->getUserId($username)) {
            return false;
        }
        $now = time();
        $user_id = $db->escapeString($user_id);
        $sql = "INSERT INTO USER_GROUP (USER_ID, GROUP_ID, STATUS,
            JOIN_DATE) VALUES(?, ?, ?, ?)";
        $result = $db->execute($sql, array($user_id, PUBLIC_GROUP_ID,
            ACTIVE_STATUS, $now));
        $sql = "INSERT INTO USER_ROLE  VALUES (?, ?) ";
        $result_id = $db->execute($sql, array($user_id, USER_ROLE));
        return $user_id;
    }



    /**
     * Deletes a user by username from the list of users that can login to
     * the admin panel
     *
     * @param string $username  the login name of the user to delete
     */
    function deleteUser($user_name)
    {
        $db = $this->db;
        $user_id = $this->getUserId($user_name);
        if($user_id) {
            $sql = "DELETE FROM USER_ROLE WHERE USER_ID=?";
            $result = $db->execute($sql, array($user_id));
            $sql = "DELETE FROM USER_GROUP WHERE USER_ID=?";
            $result = $db->execute($sql, array($user_id));
            $sql = "DELETE FROM USER_SESSION WHERE USER_ID=?";
            $result = $db->execute($sql, array($user_id));
        }
        $sql = "DELETE FROM USERS WHERE USER_ID=?";
        $result = $db->execute($sql, array($user_id));
    }

    /**
     *  Used to update the fields stored in a USERS row according to
     *  an array holding new values
     *
     *  @param array $user updated values for a USERS row
     */
    function updateUser($user)
    {
        $user_id = $user['USER_ID'];
        unset($user['USER_ID']);
        unset($user['USER_NAME']);
        $sql = "UPDATE USERS SET ";
        $comma ="";
        $params = array();
        foreach($user as $field => $value) {
            $sql .= "$comma $field=? ";
            $comma = ",";
            $params[] = $value;
        }
        $sql .= " WHERE USER_ID=?";
        $params[] = $user_id;
        $this->db->execute($sql, $params);
    }
}
?>
