export const apbctLocalStorage = {
    get: function(key, property) {
        if ( typeof property === 'undefined' ) {
            property = 'value';
        }
        const storageValue = localStorage.getItem(key);
        if ( storageValue !== null ) {
            try {
                const json = JSON.parse(storageValue);
                if ( json.hasOwnProperty(property) ) {
                    try {
                        const parsed = JSON.parse( json[property] );
                        if ( parsed !== null && typeof parsed === 'object' ) {
                            return json[property].toString();
                        }
                        return parsed;
                    } catch (e) {
                        return json[property].toString();
                    }
                } else {
                    return json;
                }
            } catch (e) {
                return storageValue;
            }
        }
        return false;
    },
    set: function(key, value, isJson = true) {
        if (isJson) {
            let objToSave = {'value': JSON.stringify(value), 'timestamp': Math.floor(new Date().getTime() / 1000)};
            localStorage.setItem(key, JSON.stringify(objToSave));
        } else {
            localStorage.setItem(key, value);
        }
    },
    isAlive: function(key, maxLifetime) {
        if ( typeof maxLifetime === 'undefined' ) {
            maxLifetime = 86400;
        }
        const keyTimestamp = this.get(key, 'timestamp');
        return keyTimestamp + maxLifetime > Math.floor(new Date().getTime() / 1000);
    },
    isSet: function(key) {
        return localStorage.getItem(key) !== null;
    },
    delete: function(key) {
        localStorage.removeItem(key);
    },
    getCleanTalkData: function() {
        let data = {};
        for (let i=0; i<localStorage.length; i++) {
            let key = localStorage.key(i);
            if (key.indexOf('ct_') !==-1 || key.indexOf('apbct_') !==-1) {
                data[key.toString()] = apbctLocalStorage.get(key);
            }
        }
        return data;
    },

};
