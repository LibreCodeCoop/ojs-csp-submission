<template>
	<div class="pkpListPanel__search">
		<label>
			<span class="-screenReader">{{ i18n.search }}</span>
			<input type="search" class="pkpListPanel__searchInput"
				@keyup="searchPhraseChanged"
				:id="inputId"
				:value="currentSearchPhrase"
				:placeholder="i18n.search"
			>
			<span class="pkpListPanel__searchIcons">
				<icon icon="search" :inline="true" class="pkpListPanel__searchIcons--search" />
			</span>
		</label>
		<button class="pkpListPanel__searchClear"
			v-if="currentSearchPhrase"
			@click.prevent="clearSearchPhrase"
			:aria-controls="inputId"
		>
			<icon icon="times" />
			<span class="-screenReader">{{ i18n.clearSearch }}</span>
		</button>
	</div>
</template>

<script>
import Icon from '@/components/Icon/Icon.vue';
import debounce from 'debounce';

export default {
	name: 'ListPanelSearch',
	components: {
		Icon,
	},
	props: ['searchPhrase', 'i18n', 'currentSearchPhrase', 'minWordsToSearch'],
	computed: {
		inputId: function () {
			return 'list-panel-search-' + this._uid;
		},
	},
	methods: {
		/**
		 * A throttled function to signal to the parent element that the
		 * searchPhrase should be updated. It's throttled to allow it to be
		 * fired by frequent events, like keyup.
		 *
		 * @param string|object data A DOM event (object) or the new search
		 *  phrase (string)
		 */
		searchPhraseChanged: debounce(function (data) {
			var newVal = typeof data === 'string' ? data : data.target.value;
			if (newVal.trim().length >= this.minWordsToSearch) {
				this.$emit('search-phrase-changed', newVal);
			}
			this.$emit('current-search-phrase', newVal);
		}, 250),

		/**
		 * Clear the search phrase
		 */
		clearSearchPhrase: function () {
			if (this.minWordsToSearch == 0) {
				this.$emit('search-phrase-changed', '');
			}
			this.$emit('current-search-phrase', '');
			this.$nextTick(function () {
				this.$el.querySelector('input[type="search"]').focus();
			});
		},
	},
};
</script>