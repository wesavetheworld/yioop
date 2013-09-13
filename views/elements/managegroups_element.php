<?php
/**
 *  SeekQuarry/Yioop --
 *  Open Source Pure PHP Search Engine, Crawler, and Indexer
 *
 *  Copyright (C) 2009 - 2013  Chris Pollett chris@pollett.org
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
 * @author Mallika Perepa
 * @package seek_quarry
 * @subpackage element
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2013
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/*
 * Used to draw the admin screen on which admin users can create groups, delete
 * groups and add and delete users and roles to a group 
 *
 * @author Mallika Perepa
 * @package seek_quarry
 * @subpackage element
 */
 
class ManagegroupsElement extends Element
{
/**
* renders the screen in which groups can be created, deleted, and added or
* deleted 
*
* @param array $data  contains antiCSRF token, as well as data on
*      available groups or which user is in what group
*/
function render($data)
    {?>
    <div id="div1" class="divOverlay">
    <div style="margin:100px auto;"><?php
       foreach($data['GROUP_USERS'] as $group_user) {?>
       <form id ="groupform" method = "post" action ='#'>
       <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
            e($data[CSRF_TOKEN]); ?>" />
       <input type="hidden" name="arg" value="updategroup" />
       <input type="radio" name="selectuser" value=
           "<?php e($group_user['USER_ID'])?>">
        <?php e($group_user['USER_NAME']); ?></input><br/>
    <?php } ?>
    <hr/>
       <input type="submit" value="submit" 
       onclick=
     "return confirm('<?php e(tl('managegroups_element_transfer_admin'))?>');"/>
       <input type="button" value="cancel" onclick="closeoverlay();"/>
       </form>
    </div>
    </div>
        <div class="current-activity">
        <h2><?php e(tl('managegroups_element_add_group'))?></h2>
        <form id="addGroupForm" method="post" action='#'>
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
            e($data[CSRF_TOKEN]); ?>" />
        <input type="hidden" name="a" value="manageGroups" />
        <input type="hidden" name="arg" value="addgroup" />

        <table class="name-table">
        <tr><td><label for="group-name"><?php
            e(tl('managegroups_element_groupname'))?></label></td>
            <td><input type="text" id="group-name" name="groupname"
                maxlength="80" class="narrow-field" /></td><td
                class="center"> <button class="button-box" type="submit"><?php
                e(tl('managegroups_element_submit')); ?></button></td>
        </tr>
        </table>
        </form>
        <h2><?php e(tl('managegroups_element_delete_group'))?></h2>
        <form id="deleteGroupForm" method="post" action='#' 
        onsubmit=
    "return confirm('<?php e(tl('managegroups_element_delete_groupname'))?>');">
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
            e($data[CSRF_TOKEN]); ?>" />
        <input type="hidden" name="a" value="manageGroups" />
        <input type="hidden" name="arg" value="deletegroup" />

        <table class="name-table">
         <tr><td><label for="delete-groupname"><?php
            e(tl('manageusers_element_delete_groupname'))?></label></td>
          <td><?php $this->view->optionsHelper->render(
          "delete-groupname", "selectgroup", $data['DELETE_GROUP_NAMES'], "-1");
                ?></td><td><button class="button-box" type="submit"><?php
                e(tl('managegroups_element_submit')); ?></button></td>
        </tr>
        </table>
        </form>
        <h2><?php e(tl('managegroups_element_view_groups'))?></h2>
        <form id="viewGroupForm" method="get" action='#' >
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
            e($data[CSRF_TOKEN]); ?>" />
        <input type="hidden" name="a" value="manageGroups" />
        <input type="hidden" name="arg" value="viewgroups" />
        <table class="name-table">
        <tr><td><label for="select-groups"><?php
            e(tl('managegroups_element_select_group'))?></label></td>
            <td><?php $this->view->optionsHelper->render("select-group",
                "selectgroup", $data['GROUP_NAMES'], $data['SELECT_GROUP']);
                ?></td></tr>
        </table>
        </form>
        <?php
                 if(isset($data['USER_NAMES'])) {
                if(count($data['USER_NAMES']) > 0) { ?>
               <form id="adduserForm" method="get" action='#' >
               <input type="hidden" name="c" value="admin" />
               <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
                          e($data[CSRF_TOKEN]); ?>" />
                 <input type="hidden" name="a" value="manageGroups" />
                 <input type="hidden" name="arg" value="adduser" />
                 <input type="hidden" name="selectgroup" value="<?php
                         e($data['SELECT_GROUP']); ?>"/>
                    <table class="name-table">
                <tr><td><label for="add-user"><?php
                    e(tl('managegroups_element_add_user'))?></label></td>
                <td><?php $this->view->optionsHelper->render("select-user",
                    "selectuser", $data['USER_NAMES'],
                    $data['SELECT_USER']); ?></td>
                <td><button class="button-box" type="submit"><?php
                    e(tl('managegroups_element_submit')); ?></button></td></tr>
                </table>
                </form>
                <?php
                 }
                ?>
                <table class="role-table"><?php
             foreach($data['GROUP_USERS'] as $group_user) {
                  if($_SESSION['USER_ID'] != $group_user['USER_ID']
                  && $_SESSION['USER_ID'] != $group_user['CREATER_ID']){ 
                      e("<tr><td>".$group_user['USER_NAME'].
                    "</td>");
                     }else{
                      if ($group_user['USER_ID'] != $group_user['CREATER_ID']){
                       e("<tr><td>".$group_user['USER_NAME'].
                          "</td><td><a href='?c=admin&amp;a=manageGroups".
                             "&amp;arg=deleteuser&amp;selectgroup=".
                              $group_user['GROUP_ID'].
                                  "&amp;selectuser=".$group_user['USER_ID'].
                               "&amp;".CSRF_TOKEN."=".$data[CSRF_TOKEN].
                        "' onclick='return confirm("); ?>&#039;<?php 
                        e(tl('managegroups_element_delete_groupuser'))?>&#039;
                        <?php e(");'>Delete</a></td>");
                        }else{
                            e("<tr><td>".$group_user['USER_NAME'].
                            "</td><td><a href='#".
                                "' onclick='return showOverlay()".
                                  ";'>Transfer</a></td>");
                           }
                       }
                     }
                   ?>
                   </table>
        <?php
        if(isset($data['GROUP_ROLES'])) {
             if(count($data['ROLE_NAMES']) > 0  &&
                $data['ROLE_NAMES'] != -1) { ?>
                <form id="addGroupRoleForm" method="get" action='#' >
                <input type="hidden" name="c" value="admin" />
                <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
                    e($data[CSRF_TOKEN]); ?>" />
                <input type="hidden" name="a" value="manageGroups" />
                <input type="hidden" name="arg" value="addrole" />
                <input type="hidden" name="selectgroup" value="<?php
                    e($data['SELECT_GROUP']);?>" />
                <table class="name-table">
                 <tr><td><label for="add-role"><?php
                    e(tl('managegroups_element_add_group_role'))?></label></td>
                    <td><?php $this->view->optionsHelper->render("add-role",
                        "selectrole", $data['ROLE_NAMES'],
                        $data['SELECT_ROLE']); ?></td>
                    <td><button class="button-box" type="submit"><?php
                    e(tl('managegroups_element_submit')); ?></button></td></tr>
                 </table>
                 </form>
                 <?php
            }
                ?>
            <?php
        if(isset($data['GROUP_ROLES'])) {
             if(count($data['GROUP_ROLES']) > 0  &&
                $data['GROUP_ROLES'] != -1) { ?>
            <table class="role-table"><?php
             foreach($data['GROUP_ROLES'] as $group_role) {
             e("<tr><td>".$group_role['ROLE_NAME'].
                    "</td><td><a href='?c=admin&amp;a=manageGroups".
                    "&amp;arg=deleterole&amp;selectgroup=".
                    $group_role['GROUP_ID'].
                    "&amp;selectrole=".$group_role['ROLE_ID'].
                    "&amp;".CSRF_TOKEN."=".$data[CSRF_TOKEN].
                    "'>Delete</a></td>");
             }
          }   
             ?>
             </table>
            <?php
              } } }
            ?>
        <script type="text/javascript">
        function submitViewGroups()
        {
            elt('viewGroupForm').submit();
        }
        function showOverlay() {
                var divObject = document.getElementById('div1');
                  divObject.style.visibility = "visible";
           }
        function closeoverlay(){
          var divObject = document.getElementById('div1');
           divObject.style.visibility = "hidden";
        }
        </script>
           </div>
        <?php
                }
                    }
           ?>
