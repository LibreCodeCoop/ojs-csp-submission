<template>
	<div class="pkpListPanel__count">
		{{ countString }}
	</div>
</template>

<script>
export default {
	name: 'ListPanelCount',
	props: ['count', 'total'],
	computed: {
		countString: function() {
			if (this.total) {
				return this.__('itemsOfTotal', {
					count: this.count,
					total: this.total
				});
			} else {
				return this.count + ' autores';
			}
		}
	}
};
</script>

<style lang="less">
.pkpListPanel__count {
	float: right;
}
</style>
