// add hasOwn
if (!Object.prototype.hasOwn) {
    Object.defineProperty(Object.prototype, 'hasOwn', { // eslint-disable-line
        value: function(property) {
            return Object.prototype.hasOwnProperty.call(this, property);
        },
        enumerable: false,
        configurable: true,
        writable: true,
    });
}
