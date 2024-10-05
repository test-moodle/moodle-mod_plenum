/* eslint 'promise/no-native': "off" */
var Janus;
class Subscribe {
    constructor(janus, slot, roomid, contextid, CoreSitesProvider) {
        this.janus = janus;
        this.CoreSitesProvider = CoreSitesProvider;
        this.slot = slot;
        this.contextid = contextid;
        this.roomid = roomid;
    }

    /**
     * Handle message from video room plugin
     *
     * @param {object} msg message
     * @param {jsep} jsep
     */
    onMessage(msg, jsep) {
        const event = msg.videoroom,
            pluginHandle = this.videoroom;
        Janus.debug(' ::: Got a message :::', msg);
        Janus.debug('Event: ' + event);
        switch (event) {
            case 'destroyed':
                // The room has been destroyed.
                Janus.warn('The room has been destroyed!');
                break;
            case 'attached':
                this.creatingSubscription = false;
                break;
            case 'event':
                if (msg.error) {
                    if (msg.error_code === 485) {
                        // This is a 'no such room' error: give a more meaningful description
                        // eslint-disable-next-line no-alert
                        alert(
                            '<p>Apparently room <code>' + this.roomid + '</code> is not configured</p>'
                        );
                    } else if (msg.error_code === 428) {
                        // eslint-disable-next-line no-console
                        console.log(msg.error);
                    } else {
                        // eslint-disable-next-line no-alert
                        alert(msg.error_code + ' ' + msg.error);
                    }
                    return;
                }
                break;
        }
        if (jsep) {
            Janus.debug('Handling SDP as well...', jsep);
            // Answer and attach
            pluginHandle.createAnswer(
                {
                    jsep: jsep,
                    tracks: [
                        {type: 'data'}
                    ],
                    success: function(jsep) {
                        Janus.debug('Got SDP!');
                        Janus.debug(jsep);
                        let body = {request: 'start', room: this.roomid};
                        pluginHandle.send({message: body, jsep: jsep});
                    },
                    error: function(error) {
                        Janus.error('WebRTC error:', error);
                    }
                }
            );
        }
    }

    /**
     * Register plugin when attached
     *
     * @param {object} pluginHandle Janus plugin handl
     * @returns {Promise}
     */
    async register(pluginHandle) {
        const args = {
            handle: pluginHandle.getId(),
            id: Number(this.contextid),
            plugin: pluginHandle.plugin,
            room: this.roomid,
            session: pluginHandle.session.getSessionId()
        };

        if (pluginHandle.plugin === 'janus.plugin.videoroom') {
            args.ptype = false;
            args.feed = this.currentFeed;
        }

        try {
            const site = await this.CoreSitesProvider.getSite(this.CoreSitesProvider.currentSite.id);
            const response = await site.read('plenumform_deft_join_room', args, {
                getFromCache: false,
                saveToCache: false,
                reusePending: false
            });
            this.creatingSubscription = false;
            return response;
        } catch (e) {
            // eslint-disable-next-line no-console
            console.log(e);
            return e;
        }
    }

    /**
     * Subscribe to video feed
     *
     * @param {int} source Peer id of source feed
     */
    subscribeTo(source) {
        if (!this.janus || this.creatingSubscription) {
            return;
        }
        if (source === this.currentFeed) {
            if (this.remoteVideoStream) {
                this.attachVideo(this.remoteVideoStream);
            }
            if (this.remoteAudioStream) {
                this.attachAudio(this.remoteAudioStream);
            }
            return;
        }
        this.resumeFeed = source;

        if (this.remoteVideoStream) {
            this.remoteVideoStreams = {};
            this.remoteVideoStream = null;
            this.creatingSubscription = false;
            this.currentFeed = null;
            this.videoroom.detach();
            this.videoroom = null;
        }

        if (!source) {
            const slot = document.getElementById(`[data-region="slot-${this.slot}"]`);
            if (slot) {
                slot.querySelector('video').style.display = 'none';
                slot.querySelector('img').style.display = 'block';
            }
            this.creatingSubscription = false;
            return;
        }

        if (this.paused) {
            return;
        }

        this.creatingSubscription = true;

        this.currentFeed = source;

        this.janus.attach({
            plugin: 'janus.plugin.videoroom',
            opaqueId: 'videoroom-' + Janus.randomString(12),
            success: pluginHandle => {
                this.videoroom = pluginHandle;
                this.register(pluginHandle);
            },
            error: (error) => {
                this.creatingSubscription = false;
                Janus.error('videoroom: ', error);
            },
            onmessage: this.onMessage.bind(this),
            onremotetrack: (track, mid, on, metadata) => {
                Janus.debug(
                    'Remote track (mid=' + mid + ') ' +
                    (on ? 'added' : 'removed') +
                    (metadata ? ' (' + metadata.reason + ') ' : '') + ':', track
                );
                if (!on) {
                    // Track removed, get rid of the stream and the rendering
                    delete this.remoteVideoStreams[mid];
                    return;
                }
                this.remoteVideoStreams = this.remoteVideoStreams || {};
                if (!this.remoteVideoStreams.hasOwnProperty(mid) && track.kind === 'video') {
                    this.remoteVideoStreams[mid] = track;
                    if (this.remoteVideoStream) {
                        return;
                    }
                    this.remoteVideoStream = new MediaStream([track]);
                    this.remoteVideoStream.mid = mid;
                    this.attachVideo(this.remoteVideoStream);
                }
                this.remoteAudioStreams = this.remoteAudioStreams || {};
                if (track.kind === 'audio') {
                    this.remoteAudioStreams[mid] = track;
                    if (this.remoteAudioStream) {
                        return;
                    }
                    this.remoteAudioStream = new MediaStream([track]);
                    this.remoteAudioStream.mid = mid;
                    this.attachAudio(this.remoteAudioStream);
                }
            }
        });
    }

    /**
     * Attach video stream to ui
     *
     * @param {MediaStream} videoStream
     */
    attachVideo(videoStream) {
        const slot = document.querySelector(`[data-region="slot-${this.slot}"]`);
        slot.querySelector('video').style.display = 'block';
        slot.querySelector('img').style.display = 'none';
        Janus.attachMediaStream(
            document.querySelector(`[data-region="slot-${this.slot}"] video`),
            videoStream
        );
    }

    /**
     * Attach video stream to ui
     *
     * @param {MediaStream} videoStream
     */
    attachAudio(audioStream) {
        Janus.attachMediaStream(
            document.querySelector(`[data-region="slot-${this.slot}"] audio`),
            audioStream
        );
    }
}

const PlenumDeft = {
    subscribeTo: function(sources) {
        this.sources = sources;
        this.subscriptions = this.subscriptions || {};
        for (const slot in sources) {
            if (this.subscriptions[slot] && (this.subscriptions[slot].roomid != this.roomid)) {
                if (this.subscriptions[slot].videoroom) {
                    this.subscriptions[slot].videoroom.detach();
                }
                this.subscriptions[slot] = null;
            }
            this.subscriptions[slot] = this.subscriptions[slot]
                || new Subscribe(this.janus, slot, this.roomid, this.contextid, this.CoreSitesProvider);
            this.subscriptions[slot].subscribeTo(sources[slot]);
        }
    },

    viewVideo: async function() {
        if (this.videoModal) {
            this.videoModal.dismiss();
        }
        // Create the modal with the `plenum-motion-modal-page` component.
        this.videoModal = document.createElement('ion-modal');
        this.videoModal.component = 'plenum-video-modal-page';
        this.videoModal.componentProps = {
        };
        this.videoModal.cssClass = 'plenum-motion-modal-page';

        // Present the modal.
        document.body.appendChild(this.videoModal);

        await this.videoModal.present();
        this.subscribeTo(this.sources);
    },

    CoreSitesProvider: this.CoreSitesProvider,

    Device: this.Device
};

// eslint-disable-next-line no-undef
Plenum.Deft = PlenumDeft;

customElements.define(
    'plenum-video-modal-page',
    class extends HTMLElement {
        connectedCallback() {
            this.innerHTML = `
              <div class="plenum-deft-video">
                    <div data-region="slot-chair" class="col col-md-6">
                        <img src="">
                        <video class="w-100"
                            autoplay
                            muted
                        >
                        </video>
                        <audio
                            autoplay
                        >
                        </audio>
                    </div>
                    <div data-region="slot-floor" class="col col-md-6">
                        <img src="">
                        <video class="w-100"
                            autoplay
                            muted
                        >
                        </video>
                        <audio
                            autoplay
                        >
                        </audio>
                    </div>
              </div>`;
        }
    }
);
