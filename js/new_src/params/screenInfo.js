export const PARAM_NAME = 'ct_screen_info';

export function initScreenInfo() {
    // Batch all layout-triggering property reads to avoid forced synchronous layouts
    const docEl = document.documentElement;
    const body = document.body;

    // Read all layout properties in one batch
    const layoutData = {
        scrollWidth: docEl.scrollWidth,
        bodyScrollHeight: body.scrollHeight,
        docScrollHeight: docEl.scrollHeight,
        bodyOffsetHeight: body.offsetHeight,
        docOffsetHeight: docEl.offsetHeight,
        bodyClientHeight: body.clientHeight,
        docClientHeight: docEl.clientHeight,
        docClientWidth: docEl.clientWidth,
    };

    return JSON.stringify({
        fullWidth: layoutData.scrollWidth,
        fullHeight: Math.max(
            layoutData.bodyScrollHeight, layoutData.docScrollHeight,
            layoutData.bodyOffsetHeight, layoutData.docOffsetHeight,
            layoutData.bodyClientHeight, layoutData.docClientHeight,
        ),
        visibleWidth: layoutData.docClientWidth,
        visibleHeight: layoutData.docClientHeight,
    });
}
