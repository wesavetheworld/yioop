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
 * Used to draw the admin screen on which admin users can add/delete
 * and manage machines which might act as fetchers or queue_servers.
 * The managing protion of this element is actually done via an ajax
 * call of the MachinestatusView
 *
 * @author Chris Pollett
 * @package seek_quarry
 * @subpackage element
 */
class ManagemachinesElement extends Element
{
    /**
     * Draws the ManageMachines element to the output buffer
     *
     * @param array $data  contains antiCSRF token, as well as data for
     * the select fetcher number element.
     */
    function render($data)
    {?>
        <div class="current-activity">
        <h2><?php e(tl('managemachines_element_add_machine') . "&nbsp;");
            e($this->view->helper("helpbutton")->render(
                "Manage Machines", $data[CSRF_TOKEN]));
            ?></h2>
        <form id="addMachineForm" method="post">
        <input type="hidden" name="c" value="admin" />
        <input type="hidden" name="<?php e(CSRF_TOKEN); ?>" value="<?php
            e($data[CSRF_TOKEN]); ?>" />
        <input type="hidden" name="a" value="manageMachines" />
        <input type="hidden" name="arg" value="addmachine" />
        <table class="name-table">
        <tr><th><label for="machine-name"><?php
            e(tl('managemachines_element_machine_name'))?></label></th>
            <td><input type="text" id="machine-name" name="name"
                maxlength="<?php e(NAME_LEN); ?>" class="wide-field" /></td>
        </tr>
        <tr><th><label for="machine-url"><?php
            e(tl('managemachines_element_machineurl'))?></label></th>
            <td><input type="text" id="machine-url" name="url"
                maxlength="<?php e(MAX_URL_LEN);
                ?>" class="wide-field" /></td></tr>
        <tr><th><label for="is-replica-box"><?php
            e(tl('managemachines_element_is_mirror'))?></label></th>
            <td><input type="checkbox" id="is-replica-box"
                name="is_replica" value="true"
                onclick="toggleReplica(this.checked)" /></td></tr>
         <tr id="m1"><th><label for="parent-machine-name"><?php
            e(tl('managemachines_element_parent_name'))?></label></th>
            <td><input type="text" name="parent" id="parent-machine-name"
                 maxlength="<?php e(NAME_LEN); ?>" class="wide-field" />
            </td>
        </tr>
        <tr id="m2"><th><label for="queue-box"><?php
            e(tl('managemachines_element_has_queueserver'))?></label></th>
            <td><input type="checkbox" id="queue-box"
                name="has_queue_server" value="true" /></td></tr>
        <tr id="m3"><th><label for="fetcher-number"><?php
            e(tl('managemachines_element_num_fetchers'))?></label></th><td>
            <?php $this->view->helper("options")->render("fetcher-number",
            "num_fetchers", $data['FETCHER_NUMBERS'],$data['FETCHER_NUMBER']);
            ?></td></tr>
        <tr><th></th><td><button class="button-box" type="submit"><?php
                e(tl('managemachines_element_submit')); ?></button></td>
        </tr>
        </table>
        </form>

        <h2><?php e(tl('managemachines_element_machine_info'). "&nbsp;");
            e($this->view->helper("helpbutton")->render(
                "Machine Information", $data[CSRF_TOKEN]));
            ?></h2>
        <div id="machinestatus" >
        <p class="red"><?php
            e(tl('managemachines_element_awaiting_status'))?></p>
        </div>
        <script type="text/javascript" >
        var updateId;
        function machineStatusUpdate()
        {
            var startUrl = "?c=admin&<?php
                e(CSRF_TOKEN."=".$data[CSRF_TOKEN]); ?>&a=machineStatus<?php
                e('&num_show='.$data['NUM_SHOW']."&start_row=".
                    $data['START_ROW']."&end_row=".$data['END_ROW']); ?>";
            var machineTag = elt('machinestatus');
            getPage(machineTag, startUrl);
        }

        function clearUpdate()
        {
             clearInterval(updateId );
             var machineTag = elt('machinestatus');
             machineTag.innerHTML= "<h2 class='red'><?php
                e(tl('managemachines_element_no_longer_update'))?></h2>";
        }
        function doUpdate()
        {
             var sec = 1000;
             var minute = 60 * sec;
             machineStatusUpdate();
             updateId = setInterval("machineStatusUpdate()", 30*sec);
             setTimeout("clearUpdate()", 20 * minute + sec);
        }
        function toggleReplica(is_replica)
        {
            if(is_replica) {
                m1_value = "table-row";
                m2_value = "none";
                m3_value = "none";
            } else {
                m1_value = "none";
                m2_value = "table-row";
                m3_value = "table-row";
            }
            setDisplay('m1', m1_value);
            setDisplay('m2', m2_value);
            setDisplay('m3', m3_value);
        }
        </script>
        </div>
    <?php
    }
}
?>
