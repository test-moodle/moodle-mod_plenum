/*
 * Plenary meeting Basic media manager
 *
 * @package    plenumform_basic
 * @module     plenumform_basic/media_manager
 * @copyright  2023 Daniel Thies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from "core/ajax";
import Templates from "core/templates";
import Notification from "core/notification";

export default class MediaManager {
    /**
     * Initialize player plugin
     *
     * @param {int} contextid
     * @param {int} delay
     *
     * @returns {bool}
     */
    constructor(contextid, delay) {
        this.contextid = contextid;

        if (!delay) {
            return false;
        }

        setInterval(() => {
            this.updateMotions(contextid);
        }, delay);

        return true;
    }

    async updateMotions(contextid) {
        const selector = `[data-contextid="${contextid}"][data-region="plenum-motions"]`;
        const content = document.querySelector(selector);
        if (content) {
            const response = await Ajax.call([{
                args: {
                    contextid: contextid
                },
                contextid: contextid,
                fail: Notification.exception,
                methodname: 'mod_plenum_update_content'
            }])[0];
            if (response.motions) {
                Templates.replaceNodeContents(content, response.motions, response.javascript);
            }
        }
    }
}
