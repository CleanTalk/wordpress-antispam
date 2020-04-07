"use strict";
wp.domReady(function () {
	if (wp.blocks) {
		wp.blocks.getBlockTypes().forEach(function (block) {
			if (apbctDisableComments.disabled_blocks.includes(block.name)) {
				wp.blocks.unregisterBlockType(block.name);
			}
		});
	}
});
