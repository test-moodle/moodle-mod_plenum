/* eslint 'promise/no-native': "off" */
var ws = new WebSocket('wss://deftly.us/ws'),
    token = this.CONTENT_OTHERDATA.token;

ws.onopen = function() {
    ws.send(token);
};

ws.onclose = () => {
    var id = setInterval(() => {
        if (navigator.onLine) {
            clearInterval(id);
            this.refreshContent(false);
        }
    }, 5000);
};

ws.addEventListener('message', () => {
    setTimeout(async() => {
        if (navigator.onLine && !document.querySelector('textarea:focus')) {
            this.refreshContent(false);
        }
    });
});
