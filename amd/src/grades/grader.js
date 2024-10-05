// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This module will tie together all of the different calls the gradable module will make.
 *
 * @module     mod_plenum/grades/grader
 * @copyright  2024 Daniel Thies <dethies@google.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
import {get_string as getString} from 'core/str';
import ModalForm from 'core_form/modalform';
import Notification from 'core/notification';
import * as Selectors from './grader/selectors';
import {serialize} from 'core_form/util';

/**
 * Find top of gradable node
 *
 * @param {HTMLElement} node Target node
 * @return {HTMLElement}
 */
const findGradableNode = node => {
    const gradableItem = node.closest(Selectors.gradableItem);
    return gradableItem;
};

var modalForm;

/**
 * Launch the Grader.
 *
 * @param {HTMLElement} rootNode the root HTML element describing what is to be graded
 * @param {object} param
 * @param {bool} [param.focusOnClose=null]
 */
const launchGrading = async(rootNode, {
    focusOnClose = null,
} = {}) => {
    const data = rootNode.dataset;

    modalForm = new ModalForm({
        formClass: "mod_plenum\\form\\grader",
        args: {contextid: data.contextid},
        large: true,
        modalConfig: {title: getString('gradeusers', 'forum')},
        // DOM element that should get the focus after the modal dialogue is closed:
        returnFocus: focusOnClose,
    });
    modalForm.show();
};

/**
 * Launch the Grader.
 *
 * @param {HTMLElement} rootNode the root HTML element describing what is to be graded
 * @param {object} param
 * @param {bool} [param.focusOnClose=null]
 */
const launchViewGrading = async(rootNode, {
    focusOnClose = null,
} = {}) => {
    const data = rootNode.dataset;

    modalForm = new ModalForm({
        formClass: "mod_plenum\\form\\grade_view",
        args: {contextid: data.contextid},
        large: true,
        modalConfig: {title: getString('viewgrades', 'forum')},
        // DOM element that should get the focus after the modal dialogue is closed:
        returnFocus: focusOnClose,
    });
    modalForm.show();
};

/**
 * Register listeners to launch the grading panel.
 */
export const registerLaunchListeners = () => {
    document.addEventListener('click', async(e) => {
        const rootNode = findGradableNode(e.target);

        if (!rootNode) {
            return;
        }

        if (e.target.matches(Selectors.launch)) {

            e.preventDefault();
            try {
                await launchGrading(rootNode, {
                    focusOnClose: e.target,
                });
            } catch (error) {
                Notification.exception(error);
            }
        } else if (e.target.matches(Selectors.viewGrade)) {

            e.preventDefault();
            try {
                await launchViewGrading(rootNode, {
                    focusOnClose: e.target,
                });
            } catch (error) {
                Notification.exception(error);
            }
        }
    });
    document.addEventListener('change', e => {
        const select = e.target.closest(Selectors.userid);

        if (select) {
            const form = select.closest('form');
            const data = new FormData(form);
            const formParams = serialize({contextid: data.get('contextid'), userid: data.get('userid')});
            const bodyContent = modalForm.getBody(formParams);
            modalForm.modal.setBodyContent(bodyContent);
        }
    });
};
