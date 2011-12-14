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
 * @subpackage test
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2012
 * @filesource
 */

if(!defined('BASE_DIR')) {echo "BAD REQUEST"; exit();}

/**
 *  Load the url parser library we'll be testing
 */
require_once BASE_DIR."/lib/url_parser.php"; 

/**
 *  Used to test that the UrlParser class. For now, want to see that the
 *  method canonicalLink is working correctly
 *
 *  @author Chris Pollett
 *  @package seek_quarry
 *  @subpackage test
 */
class UrlParserTest extends UnitTest
{
    /**
     * UrlParser uses static methods so doesn't do anything right now
     */
    public function setUp()
    {
    }

    /**
     * UrlParser uses static methods so doesn't do anything right now
     */
    public function tearDown()
    {
    }

    /**
     * Check if can go from a relative link, base link to a complete link
     * in various different ways
     */
    public function canonicalLinkTestCase()
    {
        $test_links = array(
            array("/bob.html", "http://www.example.com/", 
                "http://www.example.com/bob.html", "root dir1"),
            array("bob.html", "http://www.example.com/", 
                "http://www.example.com/bob.html", "root dir2"),
            array("bob", "http://www.example.com/", 
                "http://www.example.com/bob", "root dir3"),
            array("bob", "http://www.example.com", 
                "http://www.example.com/bob", "root dir4"),
            array("http://print.bob.com/bob", "http://www.example.com", 
                "http://print.bob.com/bob", "root dir5"),
            array("bob", "http://www.example.com/a", 
                "http://www.example.com/a/bob", "sub dir1"),
            array("bob.html?a=1", "http://www.example.com/a", 
                "http://www.example.com/a/bob.html?a=1", "query 1"),
            array("bob?a=1&b=2", "http://www.example.com/a", 
                "http://www.example.com/a/bob?a=1&b=2", "query 2"),
            array("/?a=1&b=2", "http://www.example.com/a", 
                "http://www.example.com/?a=1&b=2", "query 3"),
            array("?a=1&b=2", "http://www.example.com/a", 
                "http://www.example.com/a/?a=1&b=2", "query 4"),
            array("b/b.html?a=1&b=2", "http://www.example.com/a/c", 
                "http://www.example.com/a/c/b/b.html?a=1&b=2", "query 5"),
            array("b/b.html?a=1&b=2?c=4", "http://www.example.com/a/c", 
                "http://www.example.com/a/c/b/b.html?a=1&b=2?c=4", "query 6"),
            array("b#1", "http://www.example.com/", 
                "http://www.example.com/b#1", "fragment 1"),
            array("b?a=1#1", "http://www.example.com/", 
                "http://www.example.com/b?a=1#1", "fragment 2"),
            array("b?a=1#1#2", "http://www.example.com/", 
                "http://www.example.com/b?a=1#1#2", "fragment 3"),

        );

        foreach($test_links as $test_link) {
            $result = UrlParser::canonicalLink($test_link[0], $test_link[1]);
            $this->assertEqual($result, $test_link[2], $test_link[3]);
        }
    }

}
?>