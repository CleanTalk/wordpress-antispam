/* Cleantalk Modal object */
var cleantalkModal = cleantalkModal || { // eslint-disable-line no-var

    // Flags
    loaded: false,
    loading: false,
    opened: false,
    opening: false,
    ignoreURLConvert: false,

    // Methods
    load: function( action ) {
        if ( ! this.loaded ) {
            this.loading = true;
            let callback = function( result, data, params, obj ) {
                cleantalkModal.loading = false;
                cleantalkModal.loaded = result;
                document.dispatchEvent(
                    new CustomEvent( 'cleantalkModalContentLoaded', {
                        bubbles: true,
                    } ),
                );
            };
            // eslint-disable-next-line camelcase
            if ( typeof apbct_admin_sendAJAX === 'function' ) {
                apbct_admin_sendAJAX( {'action': action}, {'callback': callback, 'notJson': true} );
            } else {
                apbct_public_sendAJAX( {'action': action}, {'callback': callback, 'notJson': true} );
            }
        }
    },

    /**
     * Open modal
     * @param {boolean|string} actionCallbackName
     */
    open: function(actionCallbackName = 'get_options_template') {
        /* Cleantalk Modal CSS start */
        let renderCss = function() {
            let cssStr = '';
            // eslint-disable-next-line guard-for-in
            for ( const key in this.styles ) {
                cssStr += key + ':' + this.styles[key] + ';';
            }
            return cssStr;
        };
        let overlayCss = {
            styles: {
                'z-index': '9999999999',
                'position': 'fixed',
                'top': '0',
                'left': '0',
                'width': '100%',
                'height': '100%',
                'background': 'rgba(0,0,0,0.5)',
                'display': 'flex',
                'justify-content': 'center',
                'align-items': 'center',
            },
            toString: renderCss,
        };
        let innerCss = {
            styles: {
                'position': 'relative',
                'padding': '30px',
                'background': '#FFF',
                'border': '1px solid rgba(0,0,0,0.75)',
                'border-radius': '4px',
                'box-shadow': '7px 7px 5px 0px rgba(50,50,50,0.75)',
            },
            toString: renderCss,
        };
        let closeCss = {
            styles: {
                'position': 'absolute',
                'background': '#FFF',
                'width': '20px',
                'height': '20px',
                'border': '2px solid rgba(0,0,0,0.75)',
                'border-radius': '15px',
                'cursor': 'pointer',
                'top': '-8px',
                'right': '-8px',
                'box-sizing': 'content-box',
            },
            toString: renderCss,
        };
        let closeCssBefore = {
            styles: {
                'content': '""',
                'display': 'block',
                'position': 'absolute',
                'background': '#000',
                'border-radius': '1px',
                'width': '2px',
                'height': '16px',
                'top': '2px',
                'left': '9px',
                'transform': 'rotate(45deg)',
            },
            toString: renderCss,
        };
        let closeCssAfter = {
            styles: {
                'content': '""',
                'display': 'block',
                'position': 'absolute',
                'background': '#000',
                'border-radius': '1px',
                'width': '2px',
                'height': '16px',
                'top': '2px',
                'left': '9px',
                'transform': 'rotate(-45deg)',
            },
            toString: renderCss,
        };
        let bodyCss = {
            styles: {
                'overflow': 'hidden',
            },
            toString: renderCss,
        };
        let cleantalkModalStyle = document.createElement( 'style' );
        cleantalkModalStyle.setAttribute( 'id', 'cleantalk-modal-styles' );
        cleantalkModalStyle.innerHTML = 'body.cleantalk-modal-opened{' + bodyCss + '}';
        cleantalkModalStyle.innerHTML += '#cleantalk-modal-overlay{' + overlayCss + '}';
        cleantalkModalStyle.innerHTML += '#cleantalk-modal-close{' + closeCss + '}';
        cleantalkModalStyle.innerHTML += '#cleantalk-modal-close:before{' + closeCssBefore + '}';
        cleantalkModalStyle.innerHTML += '#cleantalk-modal-close:after{' + closeCssAfter + '}';
        document.body.append( cleantalkModalStyle );
        /* Cleantalk Modal CSS end */

        let overlay = document.createElement( 'div' );
        overlay.setAttribute( 'id', 'cleantalk-modal-overlay' );
        document.body.append( overlay );

        document.body.classList.add( 'cleantalk-modal-opened' );

        let inner = document.createElement( 'div' );
        inner.setAttribute( 'id', 'cleantalk-modal-inner' );
        inner.setAttribute( 'style', innerCss );
        overlay.append( inner );

        let close = document.createElement( 'div' );
        close.setAttribute( 'id', 'cleantalk-modal-close' );
        inner.append( close );

        let content = document.createElement( 'div' );
        if ( this.loaded ) {
            const urlRegex = /(https?:\/\/[^\s]+)/g;
            const serviceContentRegex = /.*\/inc/g;
            if (serviceContentRegex.test(this.loaded) || this.ignoreURLConvert) {
                content.innerHTML = this.sanitizeHtml(this.loaded);
            } else {
                content.innerHTML = this.sanitizeHtml(
                    this.loaded.replace(urlRegex, '<a href="$1" target="_blank">$1</a>'),
                );
            }
        } else {
            content.innerHTML = 'Loading...';
            // @ToDo Here is hardcoded parameter. Have to get this from a 'data-' attribute.
            if (actionCallbackName) {
                this.load( actionCallbackName );
            }
        }
        content.setAttribute( 'id', 'cleantalk-modal-content' );
        inner.append( content );

        this.opened = true;
    },

    confirm: function(header, text = '', filePath = '', callback, yesButtonText = 'Yes', noButtonText = 'No') {
        cleantalkModal.loading = false;
        let contentBlock = document.getElementById('cleantalk-modal-content');
        if (contentBlock) {
            contentBlock.innerHTML = '';

            const headerBlock = document.createElement('div');
            headerBlock.className = 'cleantalk-confirm-modal_header';
            headerBlock.textContent = header;
            contentBlock.append(headerBlock);

            // Create text block
            const textBlock = document.createElement('div');
            textBlock.className = 'cleantalk-confirm-modal_text-block';
            contentBlock.append(textBlock);

            if (filePath && filePath.length > 60) {
                filePath = '...' + filePath.slice(filePath.length - 60);
            }

            const textElem = document.createElement('div');
            textElem.className = 'cleantalk-confirm-modal_text';
            textElem.textContent = text;
            textBlock.append(textElem);

            // Create buttons block
            const buttonsBlock = document.createElement('div');
            buttonsBlock.className = 'cleantalk-confirm-modal_buttons-block';
            contentBlock.append(buttonsBlock);

            const yesButton = document.createElement('button');
            yesButton.className = 'cleantalk_link cleantalk_link-auto';
            yesButton.textContent = yesButtonText;
            yesButton.onclick = function() {
                callback(true);
                cleantalkModal.close();
            };
            buttonsBlock.append(yesButton);

            const noButton = document.createElement('button');
            noButton.className = 'cleantalk_link cleantalk_link-auto';
            noButton.textContent = noButtonText;
            noButton.onclick = function() {
                cleantalkModal.close();
            };
            buttonsBlock.append(noButton);
        }
        document.dispatchEvent(
            new CustomEvent( 'cleantalkModalContentLoaded', {
                bubbles: true,
            } ),
        );
    },

    /**
     * Allowlist of HTML tags that may appear in the modal content.
     * Tag names are compared as they are reported by the parser, so only HTML elements
     * (always uppercase) can match here - SVG/MathML elements never do.
     */
    allowedTags: [
        'A', 'B', 'BLOCKQUOTE', 'BR', 'BUTTON', 'CODE', 'DD', 'DIV', 'DL', 'DT', 'EM', 'FIELDSET',
        'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'HR', 'I', 'IMG', 'INPUT', 'LABEL', 'LEGEND', 'LI',
        'OL', 'OPTGROUP', 'OPTION', 'P', 'PRE', 'SELECT', 'SMALL', 'SPAN', 'STRONG', 'SUB', 'SUP',
        'TABLE', 'TBODY', 'TD', 'TEXTAREA', 'TFOOT', 'TH', 'THEAD', 'TR', 'U', 'UL',
    ],

    /**
     * Allowlist of attributes. Everything else (including every on* handler,
     * form/formaction, srcset, namespaced attributes) is dropped.
     * Attributes prefixed with 'data-' are allowed too, see isAttributeAllowed().
     */
    allowedAttributes: [
        'alt', 'checked', 'class', 'cols', 'disabled', 'for', 'height', 'href', 'id', 'maxlength',
        'multiple', 'name', 'placeholder', 'readonly', 'rel', 'required', 'rows', 'selected',
        'size', 'src', 'style', 'target', 'title', 'type', 'value', 'width',
    ],

    // Attributes holding an URL - their value is additionally checked by isSafeUrl().
    allowedUrlAttributes: ['href', 'src'],

    allowedUrlSchemes: ['http', 'https', 'mailto'],

    /**
     * Sanitize an untrusted HTML string with a strict allowlist.
     *
     * @param {string} dirty Untrusted HTML.
     * @return {string} Sanitized HTML.
     */
    sanitizeHtml: function( dirty ) {
        if ( typeof dirty !== 'string' || dirty === '' ) {
            return '';
        }

        let template = document.createElement( 'template' );
        template.innerHTML = dirty;

        // Comments are never needed here and are a known mXSS vector.
        let walker = document.createTreeWalker( template.content, NodeFilter.SHOW_COMMENT );
        let comments = [];
        while ( walker.nextNode() ) {
            comments.push( walker.currentNode );
        }
        comments.forEach( function( comment ) {
            comment.remove();
        } );

        let self = this;
        template.content.querySelectorAll( '*' ).forEach( function( el ) {
            // Tag names are compared as the parser reports them - do not normalize the case here.
            // HTML elements are always uppercase, while foreign content (SVG, MathML) keeps its
            // lowercase names, so it never matches the allowlist and is dropped as a whole
            // together with its own script vectors: <svg><script>, <math><annotation-xml>, xlink:href.
            if ( self.allowedTags.indexOf( el.tagName ) === -1 ) {
                el.remove();
                return;
            }

            Array.prototype.slice.call( el.attributes ).forEach( function( attr ) {
                if ( ! self.isAttributeAllowed( attr ) ) {
                    el.removeAttribute( attr.name );
                }
            } );
        } );

        return template.innerHTML;
    },

    /**
     * Check an attribute against the allowlist.
     *
     * @param {Attr} attr Attribute to check.
     * @return {boolean} True if the attribute may be kept.
     */
    isAttributeAllowed: function( attr ) {
        if ( attr.namespaceURI !== null ) {
            return false;
        }

        let name = attr.name.toLowerCase();

        if ( name.indexOf( 'data-' ) === 0 ) {
            return true;
        }

        if ( this.allowedAttributes.indexOf( name ) === -1 ) {
            return false;
        }

        if ( this.allowedUrlAttributes.indexOf( name ) !== -1 ) {
            return this.isSafeUrl( attr.value );
        }

        if ( name === 'style' ) {
            return this.isSafeCss( attr.value );
        }

        return true;
    },

    /**
     * Check that an URL has no scheme or one of the allowed schemes.
     *
     * @param {string} value Attribute value.
     * @return {boolean} True if the URL may be kept.
     */
    isSafeUrl: function( value ) {
        let normalized = String( value ).replace( /[\u0000-\u0020\u007F-\u00A0]+/g, '' ).toLowerCase();
        let scheme = normalized.match( /^([a-z][a-z0-9+.\-]*):/ );

        if ( scheme === null ) {
            return true;
        }

        return this.allowedUrlSchemes.indexOf( scheme[1] ) !== -1;
    },

    /**
     * Check an inline style value for the constructs able to execute code or load remote content.
     *
     * @param {string} value Attribute value.
     * @return {boolean} True if the style may be kept.
     */
    isSafeCss: function( value ) {
        let normalized = String( value ).replace( /[\u0000-\u0020\u007F]+/g, '' ).toLowerCase();

        return ! /(expression\(|javascript:|vbscript:|url\(|@import|-moz-binding|behavior:)/.test( normalized );
    },

    close: function() {
        document.body.classList.remove( 'cleantalk-modal-opened' );
        const overlay = document.getElementById( 'cleantalk-modal-overlay' );
        const styles = document.getElementById( 'cleantalk-modal-styles' );
        overlay !== null && overlay.remove();
        styles !== null && styles.remove();
        document.dispatchEvent(
            new CustomEvent( 'cleantalkModalClosed', {
                bubbles: true,
            } ),
        );
    },

};

/* Cleantalk Modal helpers */
document.addEventListener('click', function( e ) {
    if ( e.target && (e.target.id === 'cleantalk-modal-overlay' || e.target.id === 'cleantalk-modal-close') ) {
        cleantalkModal.close();
    }
});
document.addEventListener('cleantalkModalContentLoaded', function( e ) {
    if ( cleantalkModal.opened && cleantalkModal.loaded ) {
        document.getElementById( 'cleantalk-modal-content' ).innerHTML =
            cleantalkModal.sanitizeHtml( cleantalkModal.loaded );
    }
});
