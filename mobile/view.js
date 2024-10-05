/* eslint 'promise/no-native': "off" */
customElements.define(
    'plenum-motion-modal-page',
    class extends HTMLElement {
        connectedCallback() {
            const modalElement = document.querySelector('ion-modal');
            this.innerHTML = `
              <ion-header>
                <ion-toolbar>
                    <ion-title>${modalElement.componentProps.title}</ion-title>
                        <ion-buttons slot="primary">
                              <ion-button dismissModalButton>
                                      <ion-icon slot="icon-only" name="close"></ion-icon>
                              </ion-button>
                        </ion-buttons>
                </ion-toolbar>
              </ion-header>
              <ion-content class="ion-padding">
                  <div data-region="motion-view-content">${modalElement.componentProps.content}</div>
              </ion-content>`;
        }
    }
);

let modalElement;

const handleDismissButton = async(e) => {
    const button = e.target.closest('ion-button[dismissModalButton]');
    if (button) {
        e.stopPropagation();
        e.preventDefault();
        if (button.hasAttribute('confirmChange')) {
            const url = new URL(this.CoreSitesProvider.currentSite.siteUrl + '/webservice/rest/server.php'),
                data = url.searchParams;
            data.set('wstoken', this.CoreSitesProvider.currentSite.token);
            data.set('moodlewsrestformat', 'json');
            data.set('wsfunction', 'mod_plenum_get_fragment');
            data.set('fragment', 'confirm' + modalElement.componentProps.action);
            data.set('contextid', modalElement.componentProps.contextid);
            data.set('id', modalElement.componentProps.id);
            try {
                const response = await fetch(url);
                if (!response.ok) {
                    // eslint-disable-next-line no-console
                    console.log('Web service error');
                }
            } catch (e) {
                // eslint-disable-next-line no-console
                console.log(e);
            }
        }
        modalElement.dismiss();
        modalElement = null;
    }
};

const handleLink = async(e) => {
    const button = e.target.closest('ion-button[data-action="preview"]');

    if (!button) {
        return;
    }
    e.preventDefault();
    e.stopPropagation();
    const url = new URL(this.CoreSitesProvider.currentSite.siteUrl + '/webservice/rest/server.php'),
        data = url.searchParams;
    data.set('wstoken', this.CoreSitesProvider.currentSite.token);
    data.set('moodlewsrestformat', 'json');
    data.set('wsfunction', 'mod_plenum_get_fragment');
    data.set('fragment', 'motion');
    data.set('contextid', button.getAttribute('data-contextid'));
    data.set('id', button.getAttribute('data-motion'));
    try {
        const response = await fetch(url);
        if (!response.ok) {
            // eslint-disable-next-line no-console
            console.log('Web service error');
        }
        const {html} = await response.json();
        document.querySelectorAll('[data-region="motion-view-content"]').forEach(modalContent => {
            modalContent.innerHTML = html;
        });
    } catch (e) {
        // eslint-disable-next-line no-console
        console.log(e);
    }
};

document.body.addEventListener('click', handleLink.bind(this));
document.body.addEventListener('click', handleDismissButton.bind(this));

// eslint-disable-next-line no-undef
Plenum.viewMotion = (response, title) => {
    if (modalElement) {
        modalElement.dismiss();
    }
    // Create the modal with the `plenum-motion-modal-page` component.
    modalElement = document.createElement('ion-modal');
    modalElement.component = 'plenum-motion-modal-page';
    modalElement.componentProps = {
        title: title,
        content: response.html
    };
    modalElement.cssClass = 'plenum-motion-modal-page';

    // Present the modal.
    document.body.appendChild(modalElement);

    modalElement.present();
};

const templates = this.INIT_TEMPLATES;

customElements.define(
    'change-state-modal',
    class extends HTMLElement {
        connectedCallback() {
            this.innerHTML = templates.confirm;
        }
    }
);

const handleChangeState = async(e) => {
    const button = e.target.closest('[plenum-change-state]');

    if (!button) {
        return;
    }
    const action = button.getAttribute('data-action');
    e.preventDefault();
    e.stopPropagation();
    const url = new URL(this.CoreSitesProvider.currentSite.siteUrl + '/webservice/rest/server.php'),
        data = url.searchParams;
    data.set('wstoken', this.CoreSitesProvider.currentSite.token);
    data.set('moodlewsrestformat', 'json');
    data.set('wsfunction', 'mod_plenum_get_fragment');
    data.set('fragment', action);
    data.set('contextid', button.closest('[data-contextid]').getAttribute('data-contextid'));
    data.set('id', button.closest('[data-motion]').getAttribute('data-motion'));
    try {
        const response = await fetch(url);
        if (!response.ok) {
            // eslint-disable-next-line no-console
            console.log('Web service error');
        }
        const {html} = await response.json();
        // Create the modal with the `plenum-motion-modal-page` component.
        modalElement = document.createElement('ion-modal');
        modalElement.component = 'change-state-modal';
        modalElement.componentProps = {
            action: action,
            content: html,
            contextid: button.closest('[data-contextid]').getAttribute('data-contextid'),
            id: button.closest('[data-motion]').getAttribute('data-motion')
        };
        modalElement.cssClass = 'change-state-modal';

        // Present the modal.
        document.body.appendChild(modalElement);

        await modalElement.present();
        document.querySelector('[data-region="modal-confirm-content"]').innerHTML = html;
    } catch (e) {
        // eslint-disable-next-line no-console
        console.log(e);
    }
};

document.body.addEventListener('click', handleChangeState.bind(this));
