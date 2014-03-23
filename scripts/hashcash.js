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
 * @subpackage javascript
 * @license http://www.gnu.org/licenses/ GPL3
 * @link http://www.seekquarry.com/
 * @copyright 2009 - 2014
 * @filesource
 */

/*
 *  To find the nonce for the input parameters given by the server.
 *
 *  @param Object nonce_for_string a DOM element to put the value of a nonce
 *  @param Object random_string a DOM element to get the value of random_string
 *  @param Object time a DOM element to get the value of the time
 *  @param Object level a DOM element to get the value of a level
 */
function findNonce(nonce_for_string, random_string, time, level)
{
    if(elt(random_string).value) {
        var random_string = elt(random_string).value;
        var level = elt(level).value;
        var time = elt(time).value;
        var nonce = hashStamp(random_string, time, level);
        var input = elt(nonce_for_string);
        input.setAttribute('value', nonce);
        input.setAttribute('type', 'hidden');
    }
}

/*
 *  This function calculates the sha1 on the strings until
 *  numeber of a leading zeroes in the sha1 value matches with a level
 *  parameter.
 *
 *  @param String random_string a string sent by the server
 *  @param String time the time sent by the server
 *  @param String level define number of leading zeroes
 *  @return int nonce for which the sha1 of a string
        produces the level number of a zeroes
 */
function hashStamp(random_string, time, level)
{
    var nonce = -1;
    var input_string = '';
    var pattern = new RegExp("^0{"+level+"}");
    while (!generateSha1(input_string).match(pattern)) {
        nonce = nonce + 1;
        input_string = random_string +':'+time + ':' + nonce;
    }
    return nonce;
}

/*
 *  To calculate the sha1 on the given String.
 *
 *  @param String input string for the function
 *  @return String sha1 of the input string
 */
function generateSha1(str)
{
    var n = ((str.toString().length + 8) >> 6) + 1;
    var x = new Array();

    for (var i = 0; i < n * 16; i++) {
        x[i] = 0;
    }

    for (i = 0; i < str.toString().length; i++) {
        x[i >> 2] +=
            (str.toString().substr(i, 1)).charCodeAt(0) << (24 - (i % 4) * 8);
    }

    x[i >> 2] |= 0x80 << (24 - (i % 4) * 8);
    x[n * 16 - 1] = str.toString().length * 8;

    var a = 1732584193;
    var b = -271733879;
    var c = -1732584194;
    var d = 271733878;
    var e = -1009589776;

    var w = new Array();

    for (i = 0; i < x.length; i += 16) {
        var olda = a;
        var oldb = b;
        var oldc = c;
        var oldd = d;
        var olde = e;

        for (var j = 0; j < 80; j++) {
            if (j < 16) {
                w[j] = x[i + j];
            } else {
                w[j] = rotate(w[j - 3]^w[j - 8]^w[j - 14]^w[j - 16], 1);
            }
            var t = safeAdd(safeAdd(rotate(a, 5),
                    hashOperationByIteration(j, b, c, d)),
                    safeAdd(safeAdd(e, w[j]), constantForIteration(j)));
            e = d;
            d = c;
            c = rotate(b, 30);
            b = a;
            a = t;
        }

        a = safeAdd(a, olda);
        b = safeAdd(b, oldb);
        c = safeAdd(c, oldc);
        d = safeAdd(d, oldd);
        e = safeAdd(e, olde);
    }
    return bin2hex(Array(a, b, c, d, e));
}

/*
 *  To conver a binary array into a hexadecimal value
 *
 *  @param array binarray array of a 5 binary values
 *  @return String hexadecimal
 */
function bin2hex(binarray)
{
    var hex_case = 0;
    var hex_tab = hex_case ? "0123456789ABCDEF" : "0123456789abcdef";
    var str = "";
    for (var i = 0; i < binarray.length * 4; i++) {
        str += hex_tab.charAt((binarray[i >> 2] >> ((3 - i % 4) * 8 + 4))
        & 0xF)+hex_tab.charAt((binarray[i >> 2] >> ((3 - i % 4) * 8))
        & 0xF);
    }
    return str;
}

/*
 *  Calculate the one of the operand of a safe_add function
 *  based on the iteration value
 *
 *  @param int t value of iteration
 *  @param int b constant value
 *  @param int c constant value
 *  @param int d constant value
 *  @return int result of a xor value on constants
 */
function hashOperationByIteration(t, b, c, d)
{
    if (t < 20) {
        return (b & c) | ((~b) & d);
    }
    if (t < 40) {
        return b^c^d;
    }
    if (t < 60) {
        return (b & c) | (b & d) | (c & d);
    }
    return b^c^d;
}

/*
 *  To find a constant based on the iteration
 *
 *  @param int t value of iteration
 *  @return int constant value
 */
function constantForIteration(t)
{
    if (t < 20) {
        return 1518500249;
    } else if (t < 40) {
        return 1859775393;
    } else if (t < 60) {
        return -1894007588;
    } else {
        return -899497514;
    }
}

/*
 *  To add integers, wrapping at 2^32
 *
 *  @param int x first operand of the add operation
 *  @param int y second operand of the add operation
 *  @return int result of the add operation
 */
function safeAdd(x, y)
{
    var lsw = (x & 0xFFFF) + (y & 0xFFFF);
    var msw = (x >> 16) + (y >> 16) + (lsw >> 16);
    return (msw << 16) | (lsw & 0xFFFF);
}

/*
 *  Bitwise rotate a 32-bit number
 *
 *  @param int num number on which rotation operation is performed
 *  @param int count define a number of times the shift opeation should perform
 *  @return int result of the rotate operation
 */
function rotate(num, cnt)
{
    return (num << cnt) | zeroFill(num, 32 - cnt);
}

/*
 *  Use for a zero padding to the input number if a number is not 32 bit
 *
 *  @param int a input number
 *  @param int b to define how many leading zero should be added
 *  @return int 32 bit number
 */
function zeroFill(a, b)
{
    var bin = dec2bin(a);

    if (bin.toString().length < b) {
        bin = 0;
    } else {
        var length = bin.toString().length;
        bin = bin.toString().substr(0, length - b);
    }

    for (var i = 0; i < b; i++) {
        bin = "0" + bin;
    }
    return bin2dec(bin);
}

/*
 *  Convert decimal number to binary string
 *
 *  @param int number input number
 *  @return string binary number
 */
function dec2bin(number)
{
    if (number < 0) {
        number = 0xFFFFFFFF + number + 1;
    }
    return parseInt(number, 10).toString(2);
}

/*
 *  Convert binary string to decimal number
 *
 *  @param string binary string input string
 *  @return int decimal number
 */
function bin2dec(binary_string)
{
    binary_string = (binary_string + '').replace(/[^01]/gi, '');
    return parseInt(binary_string, 2);
}