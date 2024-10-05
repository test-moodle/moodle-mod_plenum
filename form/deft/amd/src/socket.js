/*
 * Plenary meeting Deft socket
 *
 * @package    plenumform_deft
 * @module     plenumform_deft/socket
 * @copyright  2023 Daniel Thies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from "core/ajax";
import Log from "core/log";
import Notification from "core/notification";
import SocketBase from "block_deft/socket";

export default class Socket extends SocketBase {
    /**
     * Renew token
     *
     * @param {int} contextid Context id of block
     */
    renewToken(contextid) {
        Ajax.call([{
            methodname: 'plenumform_deft_renew_token',
            args: {contextid: contextid},
            done: (replacement) => {
                Log.debug('Reconnecting');
                this.connect(contextid, replacement.token);
            },
            fail: Notification.exception
        }]);
    }
}
