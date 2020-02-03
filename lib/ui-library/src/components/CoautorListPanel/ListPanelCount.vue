<template>
	<div class="pkpListPanel__count">
		<span v-if="total">{{ countString }}</span>
		<span v-if="!total">Cadastrar autor "{{ createAuthor }}"</span>
	</div>
</template>

<script>
export default {
	name: 'ListPanelCount',
	props: ['count', 'total', 'i18n'],
	computed: {
		createAuthor: function () {
			return document.getElementsByClassName('pkpListPanel__searchInput')[0].value;
		},
		countString: function () {
			if (this.total) {
				return this.__('itemsOfTotal', { count: this.count, total: this.total });
			} else {
				this.teste = 1;
				return this.__('notFoundAndCreate');
			}
		},
	},
};
</script>

<style lang="less">
.pkpListPanel__count {
	float: right;
}
</style>
