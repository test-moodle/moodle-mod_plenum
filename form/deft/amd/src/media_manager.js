/*
 * Plenary meeting Deft integration media manager
 *
 * @package    plenumform_deft
 * @module     plenumform_deft/media_manager
 * @copyright  2023 Daniel Thies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from "core/ajax";
import Templates from "core/templates";
import Janus from 'block_deft/janus-gateway';
import Log from "core/log";
import Notification from "core/notification";
import PublishBase from "block_deft/publish";
import SubscribeBase from "block_deft/subscribe";
import Socket from "plenumform_deft/socket";

var room;

export default class MediaManager {
    /**
     * Initialize player plugin
     *
     * @param {int} contextid
     * @param {string} token Deft token
     *
     * @returns {bool}
     */
    constructor(contextid, token) {
        this.remoteFeeds = {};
        this.contextid = contextid;
        const socket = new Socket(contextid, token);

        socket.subscribe(() => {
            this.updateMotions(contextid);
        });

        this.initializeRoom(socket, contextid);
    }

    async initializeRoom(socket, contextid) {
        try {
            const response = await this.getRoom();

            this.iceservers = JSON.parse(response.iceservers);

            room = {
                contextid: contextid,
                roomid: response.roomid,
                server: response.server,
                autogaincontrol: response.autogaincontrol,
                echocancellation: response.echocancellation,
                noisesuppression: response.noisesuppression,
                iceServers: JSON.parse(response.iceservers)
            };
            this.roomid = response.roomid;
            this.server = response.server;
            document.querySelectorAll('[data-contextid="' + this.contextid + '"] .plenum-control').forEach(control => {
                control.classList.remove('hidden');
            });

            this.addListeners();

            return response;
        } catch (e) {
            Notification.exception(e);
        }

        return false;
    }

    /**
     * Update content when motions change
     *
     * @param {int} contextid
     */
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
                methodname: 'plenumform_deft_update_content'
            }])[0];
            if (response.motions) {
                Templates.replaceNodeContents(content, response.motions, response.javascript);
            }
            if (response.controls) {
                const selector = `[data-contextid="${contextid}"][data-region="plenum-deft-controls"]`;
                Templates.replaceNodeContents(selector, response.controls, '');
            }
            response.userinfo.forEach(speaker => {
                document.querySelectorAll(`[data-region="slot-${ speaker.slot }"] .card-header`).forEach(function(h) {
                    h.innerHTML = speaker.name;
                });
                document.querySelectorAll(`[data-region="slot-${ speaker.slot }"] img`).forEach(function(img) {
                    img.src = speaker.pictureurl;
                });
                setTimeout(() => {
                    this.subscribeTo(speaker.id, speaker.slot);
                    document.querySelectorAll(`[data-region="slot-${ speaker.slot }"] audio`).forEach(audio => {
                        audio.setAttribute('data-speakerid', speaker.id);
                        audio.volume = (!room.localFeed || Number(room.localFeed.feed) != Number(speaker.id)) ? 1 : 0;
                    });
                }, 1000);
            });
        }
    }

    /**
     * Fetch room info
     *
     * @returns {Promise}
     */
    getRoom() {
        return Ajax.call([{
            methodname: 'plenumform_deft_get_room',
            args: {contextid: this.contextid},
            fail: Notification.exception
        }])[0];
    }

    /**
     * Register player events to respond to user interaction and play progress.
     */
    addListeners() {
        document.querySelector('body').removeEventListener('click', handleClick);
        document.querySelector('body').addEventListener('click', handleClick);

        document.body.removeEventListener('click', this.muteAudio);
        document.body.addEventListener('click', this.muteAudio);
    }

    /**
     * Update existing subscription
     *
     * @param {int} source Feed to subscribe
     * @param {string} slot Identifier used to place stream
     * @param {object} remoteFeed Subscription
     */
    updateSubscription(source, slot, remoteFeed) {
        const update = {
            request: 'update',
            subscribe: [{
                feed: Number(source)
            }],
            unsubscribe: [{
                feed: Number(remoteFeed.current)
            }]
        };

        if (!source && remoteFeed.current) {
            delete update.subscribe;
        } else if (source && !remoteFeed.current) {
            delete update.unsubscribe;
        }

        if (remoteFeed.current != source) {
            remoteFeed.muteAudio = !!room.localFeed && (room.localFeed.feed == source);
            remoteFeed.videoroom.send({message: update});
            if (room.localFeed && (remoteFeed.current == Number(room.localFeed.feed))) {
                room.localFeed.handleClose();
                room.localFeed = null;
            }
            if (remoteFeed.audioTrack) {
                remoteFeed.audioTrack.enabled = !remoteFeed.muteAudio;
            }

            if (room.publish && (remoteFeed.current == room.publish.feed)) {
                room.publish.handleClose();
                room.publish = null;
            }
            remoteFeed.current = source;
            if (!source && remoteFeed) {
                remoteFeed.handleClose();
                this.remoteFeeds[slot] = null;
                document.querySelector(`[data-region="slot-${ slot }"] video`).srcObject = null;
            }
            if (Number(source)) {
                document.querySelectorAll(
                    `[data-contextid="${this.contextid}"] [data-region="slot-${slot}"] img.card-img-top`
                ).forEach(img => {
                    img.classList.add('hidden');
                });
                document.querySelectorAll(
                    `[data-contextid="${this.contextid}"] [data-region="slot-${slot}"] video`
                ).forEach(video => {
                    video.classList.remove('hidden');
                });
            } else {
                document.querySelectorAll(
                    `[data-contextid="${this.contextid}"] [data-region="slot-${slot}"] img.card-img-top`
                ).forEach(img => {
                    img.classList.remove('hidden');
                });
                document.querySelectorAll(
                    `[data-contextid="${this.contextid}"] [data-region="slot-${slot}"] video`
                ).forEach(video => {
                    video.classList.add('hidden');
                });
            }
        }
    }

    /**
     * Subscribe to feed
     *
     * @param {int} source Feed to subscribe
     * @param {string} slot Identifier used to place stream
     */
    subscribeTo(source, slot) {
        const remoteFeed = this.remoteFeeds[slot];
        if (remoteFeed && !remoteFeed.creatingSubscription && !remoteFeed.restart) {
            this.updateSubscription(source, slot, remoteFeed);
        } else if (remoteFeed && remoteFeed.restart) {
            if (remoteFeed.current != source) {
                this.remoteFeeds[slot] = null;
                this.subscribeTo(source, slot);
            }
        } else if (remoteFeed) {
            setTimeout(() => {
                this.subscribeTo(source, slot);
            }, 500);
        } else if (source) {
            const remoteFeed = new Subscribe(this.contextid, this.iceservers, this.roomid, this.server, this.contextid);
            remoteFeed.remoteVideo = document.querySelector(
                `[data-contextid="${this.contextid}"] [data-region="slot-${slot}"] video`
            );
            remoteFeed.remoteAudio = remoteFeed.remoteVideo.parentNode.querySelector('audio');
            remoteFeed.muteAudio = !!room.localFeed && (room.localFeed.feed == source);
            remoteFeed.startConnection(source);
            this.remoteFeeds[slot] = remoteFeed;
        }
    }

    muteAudio(e) {
        const input = e.target.closest('[data-region="audio-control"] input');
        if (!input) {
            return;
        }
        setTimeout(() => {
            if (input.checked) {
                document.querySelectorAll('[data-region="plenum-deft-media"] audio').forEach(audio => {
                    const speakerid = Number(audio.getAttribute('data-speakerid'));
                    audio.muted = '';
                    audio.setAttribute('data-active', 'true');
                    audio.volume = (!room.localFeed || Number(room.localFeed.feed) != speakerid) ? 1 : 0;
                    audio.play();
                });
            } else {
                document.querySelectorAll('audio').forEach(audio => {
                    audio.muted = true;
                    audio.removeAttribute('data-active');
                });
            }
        });
    }
}

const handleClick = function(e) {
    const button = e.target.closest(
        '[data-region="plenum-motions"] [data-action], [data-region="plenum-deft-controls"] [data-action]'
    );

    if (!button) {
        return;
    } else if (button.getAttribute('data-action') == 'publish') {
        if (room.localFeed) {
            room.localFeed.janus.destroy();
        }
        room.localFeed = new Publish(room.contextid, room.iceServers, room.roomid, room.server, room.contextid);
        window.onbeforeunload = room.localFeed.handleClose.bind(room.localFeed);
        room.localFeed.videoInput = room.localFeed.shareCamera(room);
        room.localFeed.startConnection();
    } else if (button.getAttribute('data-action') == 'unpublish') {
        if (room.localFeed) {
            room.localFeed.handleClose();
            room.localFeed = null;
        } else {
            Ajax.call([{
                args: {
                    id: 0,
                    publish: false,
                    room: room.roomid
                },
                contextid: room.contextid,
                fail: Notification.exception,
                methodname: 'plenumform_deft_publish_feed'
            }]);
        }
    }
    return;
};

class Subscribe extends SubscribeBase {
    /**
     * Register the room
     *
     * @param {object} pluginHandle
     * @return {Promise}
     */
    register(pluginHandle) {
        // Try a registration
        return Ajax.call([{
            args: {
                handle: pluginHandle.getId(),
                id: Number(this.contextid),
                plugin: pluginHandle.plugin,
                room: this.roomid,
                ptype: false,
                feed: this.feed,
                session: pluginHandle.session.getSessionId()
            },
            contextid: this.contextid,
            fail: Notification.exception,
            methodname: 'plenumform_deft_join_room'
        }])[0];
    }

    /**
     * Attach audio stream to media element
     *
     * @param {HTMLMediaElement} audioStream Stream to attach
     */
    attachAudio(audioStream) {
        Janus.attachMediaStream(
            this.remoteVideo.parentNode.querySelector('audio'),
            audioStream
        );
        audioStream.getTracks().forEach(track => {
            this.audioTrack = track;
            track.enabled = !this.muteAudio;
        });
    }

    /**
     * Attach video stream to media element
     *
     * @param {HTMLMediaElement} videoStream Stream to attach
     */
    attachVideo(videoStream) {
        this.remoteVideo.closest('[data-region]').querySelectorAll('img.card-img-top').forEach(img => {
            img.classList.add('hidden');
        });
        this.remoteVideo.classList.remove('hidden');
        Janus.attachMediaStream(
            this.remoteVideo,
            videoStream
        );
    }
}

class Publish extends PublishBase {
    /**
     * Register the room
     *
     * @param {object} pluginHandle
     * @return {Promise}
     */
    async register(pluginHandle) {
        // Try a registration
        try {
            const response = await Ajax.call([{
                args: {
                    handle: pluginHandle.getId(),
                    id: Number(this.contextid),
                    plugin: pluginHandle.plugin,
                    room: this.roomid,
                    ptype: this.ptype == 'publish',
                    session: pluginHandle.session.getSessionId()
                },
                contextid: this.contextid,
                fail: Notification.exception,
                methodname: 'plenumform_deft_join_room'
            }])[0];

            this.feed = response.id;

            return response;
        } catch (e) {
            Notification.exception(e);
        }

        return false;
    }

    /**
     * Publish current video feed
     *
     * @returns {Promise}
     */
    publishFeed() {
        return Ajax.call([{
            args: {
                id: Number(this.feed),
                room: this.roomid,
            },
            contextid: this.contextid,
            fail: Notification.exception,
            methodname: 'plenumform_deft_publish_feed'
        }])[0];
    }

    onLocalTrack() {
        return;
    }

    /**
     * Set video source to user camera
     *
     * @param {object} room Room configuration
     */
    async shareCamera(room) {
        if (this.videoInput) {
            try {
                const videoStream = await this.videoInput;
                if (videoStream) {
                    return videoStream;
                }
            } catch (e) {
                Log.debug(e);
            }
        }

        try {
            const videoStream = await navigator.mediaDevices.getUserMedia({
                video: {
                    aspectRatio: 1,
                    width: {max: 160}
                },
                audio: {
                    autoGainControl: room.autogaincontrol,
                    echoCancellation: room.echocancellation,
                    noiseSuppression: room.noisesuppression
                }
            });

            this.tracks = this.tracks || {};
            videoStream.getTracks().forEach(track => {
                this.tracks[track.id] = 'camera';
            });

            return videoStream;
        } catch (e) {
            Log.debug(e);
        }
        return null;
    }

    /**
     * Process tracks from current video stream and adjust publicatioin
     *
     * @param {array} tracks Additional tracks to add
     */
    async processStream(tracks) {
        try {
            const videoStream = await this.videoInput;
            this.tracks = this.tracks || {};
            if (videoStream) {
                const audiotransceiver = this.getTransceiver('audio'),
                    videotransceiver = this.getTransceiver('video');
                videoStream.getVideoTracks().forEach(track => {
                    track.addEventListener('ended', () => {
                        if (this.selectedTrack.id == track.id) {
                            this.unpublish();
                        } else {
                            document
                                .getElementById('video-controls-' + this.tracks[track.id])
                                .parentNode
                                .classList
                                .add('hidden');
                        }
                    });
                    this.selectedTrack = track;
                    if (videotransceiver) {
                        this.videoroom.replaceTracks({
                            tracks: [{
                                type: 'video',
                                mid: videotransceiver.mid,
                                capture: track
                            }],
                            error: Notification.exception
                        });

                        return;
                    }
                    tracks.push({
                        type: 'video',
                        capture: track,
                        recv: false
                    });
                });
                videoStream.getAudioTracks().forEach(track => {
                    if (
                        document.querySelector('.hidden[data-action="mute"][data-contextid="' + this.contextid + '"][data-type="'
                        + this.tracks[this.selectedTrack.id] + '"]'
                    )) {
                        track.enabled = false;
                    }

                    if (audiotransceiver) {
                        this.videoroom.replaceTracks({
                            tracks: [{
                                type: 'audio',
                                mid: audiotransceiver.mid,
                                capture: track
                            }],
                            error: Notification.exception
                        });

                        return;
                    }
                    tracks.push({
                        type: 'audio',
                        capture: track,
                        recv: false
                    });
                });
                if (!tracks.length) {
                    return videoStream;
                }
                this.videoroom.createOffer({
                    tracks: tracks,
                    success: (jsep) => {
                        const publish = {
                            request: "configure",
                            video: true,
                            audio: true
                        };
                        this.videoroom.send({
                            message: publish,
                            jsep: jsep
                        });
                    },
                    error: function(error) {
                        Notification.alert("WebRTC error... ", error.message);
                    }
                });
            }

            return videoStream;
        } catch (e) {
            Notification.exception(e);
        }

        return null;
    }

    /**
     * Handle close of windoww
     */
    async handleClose() {

        this.janus.destroy();

        Ajax.call([{
            args: {
                id: Number(this.feed),
                publish: false,
                room: this.roomid
            },
            contextid: this.contextid,
            fail: Notification.exception,
            methodname: 'plenumform_deft_publish_feed'
        }]);

        if (this.videoInput) {
            try {
                const videoStream = await this.videoInput;
                if (videoStream) {
                    videoStream.getTracks().forEach(track => {
                        track.stop();
                    });
                }
            } catch (e) {
                Notification.exception(e);
            }
        }

        window.onbeforeunload = null;
    }
}
