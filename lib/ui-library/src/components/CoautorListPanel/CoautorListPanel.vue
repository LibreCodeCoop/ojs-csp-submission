<template>
	<div class="pkpListPanel pkpListPanel--submissions" :class="classStatus">
		<div class="pkpListPanel__header -pkpClearfix">
			<div class="pkpListPanel__title">
				{{ i18n.title }}
				<span v-if="isLoading" class="pkpSpinner" aria-hidden="true"></span>
			</div>
			<list-panel-search
				@search-phrase-changed="setSearchPhrase"
				:searchPhrase="searchPhrase"
				:i18n="i18n"
			/>
		</div>
		<div class="pkpListPanel__body -pkpClearfix pkpListPanel__body--submissions">
			<div class="pkpListPanel__content pkpListPanel__content--submissions">
				<ul class="pkpListPanel__items" aria-live="polite">
					<submissions-list-item
						v-for="item in items"
						@filter-list="updateFilter"
						:key="item.id"
						:item="item"
						:i18n="i18n"
						:apiPath="apiPath"
						:fillUser="fillUser"
					/>
				</ul>
			</div>
		</div>
		<div class="pkpListPanel__footer -pkpClearfix">
			<list-panel-load-more
				v-if="canLoadMore"
				@loadMore="loadMore"
				:isLoading="isLoading"
				:i18n="i18n"
			/>
			<list-panel-count
				:count="itemCount"
				:total="this.itemsMax"
				:i18n="i18n"
			/>
		</div>
	</div>
</template>

<script>
import ListPanel from '@/components/ListPanel/ListPanel.vue';
import ListPanelSearch from '@/components/ListPanel/ListPanelSearch.vue';
import ListPanelCount from '@csp/components/CoautorListPanel/ListPanelCount.vue';
import ListPanelLoadMore from '@/components/ListPanel/ListPanelLoadMore.vue';
import SubmissionsListItem from '@csp/components/CoautorListPanel/SubmissionsListItem.vue';

export default {
	extends: ListPanel,
	name: 'CoautorListPanel',
	components: {
		ListPanelSearch,
		ListPanelCount,
		ListPanelLoadMore,
		SubmissionsListItem,
	},
};
</script>