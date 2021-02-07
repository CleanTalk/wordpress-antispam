/* Cleantalk Modal object */
cleantalkModal = {

    open: function ( elementIdToShow ) {
        /* Cleantalk Modal CSS start */
        var renderCss = function () {
            var cssStr = '';
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
                "text-align" : "center",
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
        cleantalkModalStyle.innerHTML += '#cleantalk-modal{' + overlayCss + '}';
        cleantalkModalStyle.innerHTML += '#cleantalk-modal-close{' + closeCss + '}';
        cleantalkModalStyle.innerHTML += '#cleantalk-modal-close:before{' + closeCssBefore + '}';
        cleantalkModalStyle.innerHTML += '#cleantalk-modal-close:after{' + closeCssAfter + '}';
        document.body.append( cleantalkModalStyle );
        /* Cleantalk Modal CSS end */

        var overlay = document.createElement( 'div' );
        overlay.setAttribute( 'id', 'cleantalk-modal' );
        document.body.append( overlay );

        document.body.classList.add( 'cleantalk-modal-opened' );

        var inner = document.getElementById( elementIdToShow ).cloneNode( true );
        inner.removeAttribute( 'id' );
        inner.removeAttribute( 'class' );
        inner.removeAttribute( 'style' );
        inner.setAttribute( 'style', innerCss );
        overlay.append( inner );

        var close = document.createElement( 'div' );
        close.setAttribute( 'id', 'cleantalk-modal-close' );
        inner.append( close );
    },

    close: function () {
        document.body.classList.remove( 'cleantalk-modal-opened' );
        document.getElementById( 'cleantalk-modal' ).remove();
        document.getElementById( 'cleantalk-modal-styles' ).remove();
    }

};

/* Cleantalk Modal helpers */
document.addEventListener('click',function( e ){
    if( e.target && e.target.id === 'cleantalk-modal' || e.target.id === 'cleantalk-modal-close' ){
        cleantalkModal.close();
    }
});