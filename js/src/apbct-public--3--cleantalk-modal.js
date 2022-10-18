/* Cleantalk Modal object */
let cleantalkModal = {

    // Flags
    loaded: false,
    loading: false,
    opened: false,
    opening: false,

    // Methods
    load: function( action ) {
        if( ! this.loaded ) {
            this.loading = true;
            callback = function( result, data, params, obj ) {
                cleantalkModal.loading = false;
                cleantalkModal.loaded = result;
                document.dispatchEvent(
                    new CustomEvent( "cleantalkModalContentLoaded", {
                        bubbles: true,
                    } )
                );
            };
            if( typeof apbct_admin_sendAJAX === "function" ) {
                apbct_admin_sendAJAX( { 'action' : action }, { 'callback': callback, 'notJson': true } );
            } else {
                apbct_public_sendAJAX( { 'action' : action }, { 'callback': callback, 'notJson': true } );
            }

        }
    },

    open: function () {
        /* Cleantalk Modal CSS start */
        var renderCss = function () {
            var cssStr = '';
            let key;
            for ( key in this.styles ) {
                cssStr += key + ':' + this.styles[key] + ';';
            }
            return cssStr;
        };
        var overlayCss = {
            styles: {
                "z-index": "9999",
                "position": "fixed",
                "top": "0",
                "left": "0",
                "width": "100%",
                "height": "100%",
                "background": "rgba(0,0,0,0.5)",
                "display": "flex",
                "justify-content" : "center",
                "align-items" : "center",
            },
            toString: renderCss
        };
        var innerCss = {
            styles: {
                "position" : "relative",
                "padding" : "30px",
                "background" : "#FFF",
                "border" : "1px solid rgba(0,0,0,0.75)",
                "border-radius" : "4px",
                "box-shadow" : "7px 7px 5px 0px rgba(50,50,50,0.75)",
            },
            toString: renderCss
        };
        var closeCss = {
            styles: {
                "position" : "absolute",
                "background" : "#FFF",
                "width" : "20px",
                "height" : "20px",
                "border" : "2px solid rgba(0,0,0,0.75)",
                "border-radius" : "15px",
                "cursor" : "pointer",
                "top" : "-8px",
                "right" : "-8px",
                "box-sizing" : "content-box",
            },
            toString: renderCss
        };
        var closeCssBefore = {
            styles: {
                "content" : "\"\"",
                "display" : "block",
                "position" : "absolute",
                "background" : "#000",
                "border-radius" : "1px",
                "width" : "2px",
                "height" : "16px",
                "top" : "2px",
                "left" : "9px",
                "transform" : "rotate(45deg)",
            },
            toString: renderCss
        };
        var closeCssAfter = {
            styles: {
                "content" : "\"\"",
                "display" : "block",
                "position" : "absolute",
                "background" : "#000",
                "border-radius" : "1px",
                "width" : "2px",
                "height" : "16px",
                "top" : "2px",
                "left" : "9px",
                "transform" : "rotate(-45deg)",
            },
            toString: renderCss
        };
        var bodyCss = {
            styles: {
                "overflow" : "hidden",
            },
            toString: renderCss
        };
        var cleantalkModalStyle = document.createElement( 'style' );
        cleantalkModalStyle.setAttribute( 'id', 'cleantalk-modal-styles' );
        cleantalkModalStyle.innerHTML = 'body.cleantalk-modal-opened{' + bodyCss + '}';
        cleantalkModalStyle.innerHTML += '#cleantalk-modal-overlay{' + overlayCss + '}';
        cleantalkModalStyle.innerHTML += '#cleantalk-modal-close{' + closeCss + '}';
        cleantalkModalStyle.innerHTML += '#cleantalk-modal-close:before{' + closeCssBefore + '}';
        cleantalkModalStyle.innerHTML += '#cleantalk-modal-close:after{' + closeCssAfter + '}';
        document.body.append( cleantalkModalStyle );
        /* Cleantalk Modal CSS end */

        var overlay = document.createElement( 'div' );
        overlay.setAttribute( 'id', 'cleantalk-modal-overlay' );
        document.body.append( overlay );

        document.body.classList.add( 'cleantalk-modal-opened' );

        var inner = document.createElement( 'div' );
        inner.setAttribute( 'id', 'cleantalk-modal-inner' );
        inner.setAttribute( 'style', innerCss );
        overlay.append( inner );

        var close = document.createElement( 'div' );
        close.setAttribute( 'id', 'cleantalk-modal-close' );
        inner.append( close );

        var content = document.createElement( 'div' );
        if ( this.loaded ) {
            content.innerHTML = this.loaded;
        } else {
            content.innerHTML = 'Loading...';
            // @ToDo Here is hardcoded parameter. Have to get this from a 'data-' attribute.
            this.load( 'get_options_template' );
        }
        content.setAttribute( 'id', 'cleantalk-modal-content' );
        inner.append( content );

        this.opened = true;
    },

    close: function () {
        document.body.classList.remove( 'cleantalk-modal-opened' );
        document.getElementById( 'cleantalk-modal-overlay' ).remove();
        document.getElementById( 'cleantalk-modal-styles' ).remove();
        document.dispatchEvent(
            new CustomEvent( "cleantalkModalClosed", {
                bubbles: true,
            } )
        );
    }

};

/* Cleantalk Modal helpers */
document.addEventListener('click',function( e ){
    if( e.target && e.target.id === 'cleantalk-modal-overlay' || e.target.id === 'cleantalk-modal-close' ){
        cleantalkModal.close();
    }
});
document.addEventListener("cleantalkModalContentLoaded", function( e ) {
    if( cleantalkModal.opened && cleantalkModal.loaded ) {
        document.getElementById( 'cleantalk-modal-content' ).innerHTML = cleantalkModal.loaded;
    }
});