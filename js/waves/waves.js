var rSciUrl = "";
function setAjaxSendCartUrl(ajax_send_cart_url){
    rSciUrl = ajax_send_cart_url;
}

function retention_science_send_cart(){
    if (rSciUrl != "") {
        new Ajax.Request(rSciUrl, {
            onSuccess: function(transport) {
                var json = transport.responseText.evalJSON();

                if (json.customerId != "") {
                    _rsq.push(['_setUserId', json.customerId]);
                }

                items = json.items.evalJSON();
                items.each(function(item){
                    _rsq.push(['_addItem', {id: item.id, name: item.name, price: item.price}]);
                });

                _rsq.push(['_setAction', 'shopping_cart']);
                _rsq.push(['_track']);
            },
            method: "get"
        });
    }
}

(function() {
    if('Ajax' in window && Ajax && 'Responders' in Ajax) {
        Ajax.Responders.register({
            onComplete: function(ajax) {
                var url = ajax.url;
                var cartAdd = false;
                // Track Amasty
                if(url.match(/amcart\/ajax\/index/)) {
                    cartAdd = true;
                }
                // Track AheadWorks
                if(url.match(/checkout\/cart\/add/)) {
                    cartAdd = true;
                }
                // Track CmsIdeas
                if(url.match(/ajaxcart/)) {
                    cartAdd = true;
                }
                if(cartAdd) {
                    retention_science_send_cart();
                }
            }
        });
    }
}) ();