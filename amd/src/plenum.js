/*
 * Plenary meeting main page js
 *
 * @package    mod_plenum
 * @module     mod_plenum/plenum
 * @copyright  2023 Daniel Thies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Fragment from "core/fragment";
import Templates from "core/templates";
import ModalForm from "core_form/modalform";
import Notification from "core/notification";
import {get_string as getString} from "core/str";

export default class Plenum {
    /**
     * Initialize player plugin
     *
     * @param {int} contextid
     *
     * @returns {bool}
     */
    constructor(contextid) {
        this.contextid = contextid;

        this.addListeners();

        return true;
    }

    /**
     * Register player events to respond to user interaction and play progress.
     */
    addListeners() {
        document.querySelector('body').removeEventListener('click', handleClick);
        document.querySelector('body').addEventListener('click', handleClick);
    }
}

const handleClick = function(e) {
    const button = e.target.closest(
        '[data-region="plenum-motions"][data-contextid] [data-action], .modal-body [data-contextid] [data-action]'
    );
    if (button) {
        const action = button.getAttribute('data-action'),
            contextid = button.closest('[data-contextid]').getAttribute('data-contextid');
        e.stopPropagation();
        e.preventDefault();

        if (action == 'move') {
            const type = button.getAttribute('data-type');
            const modalForm = new ModalForm({
                args: {
                    contextid: contextid,
                    type: button.getAttribute('data-type')
                },
                formClass: `plenumtype_${type}\\form\\edit_motion`,
                modalConfig: {title: getString('editingmotiontype', `plenumtype_${type}`)}
            });
            modalForm.show();
        } else if (['adopt', 'allow', 'decline', 'deny'].includes(action)) {
            const id = e.target.closest('[data-motion]').getAttribute('data-motion');
            const modalForm = new ModalForm({
                args: {
                    contextid: contextid,
                    id: id,
                    state: action
                },
                formClass: 'mod_plenum\\form\\change_state',
                modalConfig: {title: getString('confirm')}
            });
            modalForm.show();
        } else if (action == 'close') {
            const id = e.target.closest('[data-motion]').getAttribute('data-motion');
            const modalForm = new ModalForm({
                formClass: 'mod_plenum\\form\\close_motion',
                args: {contextid: contextid, id: id, state: action}
            });
            modalForm.show();
        } else if (action == 'preview') {
            const id = e.target.closest('[data-motion]').getAttribute('data-motion');
            Fragment.loadFragment(
                'mod_plenum',
                'motion',
                contextid,
                {id: id}
            ).done((html) => {
                if (button.closest('.modal-body') && !button.closest('[data-region="plenum-activity-report"]')) {
                    Templates.replaceNodeContents(button.closest('.modal-body'), '<div>' + html + '</div>');
                } else {
                    Notification.alert(getString('viewmotion', 'mod_plenum'), html);
                }
            }).fail(Notification.exeption);
        }
    }
};
