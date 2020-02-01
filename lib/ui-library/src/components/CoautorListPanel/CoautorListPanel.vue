<template>
	<div class="pkpListPanel" :class="classStatus">
		<div class="pkpListPanel__header -pkpClearfix">
			<div class="pkpListPanel__title">{{ i18n.testee }}</div>
		</div>
	</div>
</template>

<script>
export default {
	name: 'CoautorListPanel',
	data: function () {
		return {
			i18n: {
				testee: 'Other pages of this example component',
			},
		};
	},
};
</script>