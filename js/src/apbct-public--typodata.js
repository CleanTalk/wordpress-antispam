class CTTypoData
{
    // ==============================
    // isAutoFill       - only person can use auto fill
    // isUseBuffer      - use buffer for fill current field
    // ==============================
    // lastKeyTimestamp - timestamp of last key press in current field
    // speedDelta       - change for each key press in current field,
    //                    as difference between current and previous key press timestamps,
    //                    robots in general have constant speed of typing.
    //                    If speedDelta is constant for each key press in current field,
    //                    so, speedDelta will be roughly to 0, then it is robot.
    // ==============================
    fieldData = {
        isAutoFill: false,
        isUseBuffer: false,
        speedDelta: 0,
        firstKeyTimestamp: 0,
        lastKeyTimestamp: 0,
        lastDelta: 0,
        countOfKey: 0,
    };

    fields = document.querySelectorAll("textarea[name=comment]");

    data = [];

    gatheringFields() {
        let fieldSet = Array.prototype.slice.call(this.fields);
        fieldSet.forEach((field, i) => {
            this.data.push(Object.assign({}, this.fieldData));
        });
    }

    setListeners() {
        this.fields.forEach((field, i) => {
          field.addEventListener("paste", (event) => {
                this.data[i].isUseBuffer = true;
          });
        });

        this.fields.forEach((field, i) => {
          field.addEventListener("onautocomplete", (event) => {
                this.data[i].isAutoFill = true;
          });
        });

        this.fields.forEach((field, i) => {
          field.addEventListener("input", (event) => {
                this.data[i].countOfKey++;
                let time = + new Date();
                let currentDelta = 0;

                if (this.data[i].countOfKey === 1) {
                    this.data[i].lastKeyTimestamp = time;
                    this.data[i].firstKeyTimestamp = time;
                    return;
                }

                currentDelta = time - this.data[i].lastKeyTimestamp;
                if (this.data[i].countOfKey === 2) {
                    this.data[i].lastKeyTimestamp = time;
                    this.data[i].lastDelta = currentDelta;
                    return;
                }

                if (this.data[i].countOfKey > 2) {
                    this.data[i].speedDelta += Math.abs(this.data[i].lastDelta - currentDelta);
                    this.data[i].lastKeyTimestamp = time;
                    this.data[i].lastDelta = currentDelta;
                }
            });
        });
    }
}
