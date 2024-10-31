function initializeNS8CSP() {

    NS8CSP.sendMessage = function(params) {
        var iframe = document.getElementById('ns8-app-iframe');
        iframe.contentWindow.postMessage(
            params,
            "*"
        );
    };

    var myEventMethod =
        window.addEventListener ? "addEventListener" : "attachEvent";

    var myEventListener = window[myEventMethod];

    // browser compatibility: attach event uses onmessage
    var myEventMessage =
        myEventMethod === "attachEvent" ? "onmessage" : "message";

    // register callback function on incoming message
    myEventListener(myEventMessage, function (e) {
        switch (e.data.type) {
            case 'frameLoad':
                window.scrollTo(0, 0);
                document.getElementById('ns8-app-iframe').height = e.data.height + "px";
                break;
            case 'frameResize':
                document.getElementById('ns8-app-iframe').height = e.data.height + "px";
                break;
            case 'syncOrder':
                NS8CSP.syncOrder(e.data.order_id, function(err, response) {});
                break;
        }
    }, false);
}

var NS8CSP = {

    post: function(data, callback) {
        jQuery.ajax({
            type:"POST",
            url: NS8CSP_Local.ajaxurl,
            data: data,
            success: function(response) {
                if (typeof callback == 'function')
                    callback(null, response);
            },
            error: function(err) {
                if (typeof callback == 'function')
                    callback(err);
            }
        });
    },

    handleResponse: function(err, response, callback) {
        if (err || response)
            alert(err || response);

        if (typeof callback == 'function')
            callback(err, response);
    },

    syncOrder: function(order_id, callback) {
        var data = {
            'action': 'ns8csp_sync_order',
            'order_id': order_id,
            'security': NS8CSP_Local.security
        };

        NS8CSP.post(data, function(err, response) {
        });
    }
};

jQuery(document).ready(function () {
    initializeNS8CSP();
});
