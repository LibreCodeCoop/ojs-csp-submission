<template>
	<div class="pkpListPanel pkpListPanel--submissions" :class="classStatus">
		<div class="pkpListPanel__header -pkpClearfix">
			<div class="pkpListPanel__title">
				{{ i18n.title }}
				<span v-if="isLoading" class="pkpSpinner" aria-hidden="true"></span>
			</div>
			<list-panel-search
				@search-phrase-changed="setSearchPhrase"
				@current-search-phrase="setCurrentSearchPhrase"
				:searchPhrase="searchPhrase"
				:i18n="i18n"
				:currentSearchPhrase="currentSearchPhrase"
				:minWordsToSearch="minWordsToSearch"
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
			<div v-if="!itemsMax && currentSearchPhrase.trim().length >= minWordsToSearch" class="pkpListPanel__loadMore" :class="classLoadingMore">
				<a class="pkpListPanel__loadMoreButton" @click="newAuthor">
					{{ i18n.notFoundAndCreate }}
				</a>
			</div>
			<div
				v-if="currentSearchPhrase.trim().length < minWordsToSearch"
				class="pkpListPanel__loadMore" :class="classLoadingMore"
			>
				<a class="pkpListPanel__loadMoreButton">
					{{ i18n.informAName }}
				</a>
			</div>
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
import ListPanelSearch from '@csp/components/CoautorListPanel/ListPanelSearch.vue';
import ListPanelCount from '@/components/ListPanel/ListPanelCount.vue';
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
	data: function () {
		return {
			currentSearchPhrase: '',
		};
	},
	methods: {
		setSearchPhrase: function (val) {
			console.log(val);
			if (this.searchPhrase == val && val.length == this.minWordsToSearch) {
				this.get();
			}
			this.searchPhrase = val;
		},
		setCurrentSearchPhrase: function (val) {
			if (val.trim().length < this.minWordsToSearch) {
				this.items = [];
				this.itemsMax = null;
			}
			this.currentSearchPhrase = val;
		},
		newAuthor: function (e) {
			e.preventDefault();
			$.ajax({
				url: this.fillUser,
				data: {
					type: 'new',
					submissionId: $('[name="submissionId"]').val(),
				},
				type: 'POST',
				success: function (r) {
					$.pkp.classes.Handler.getHandler(
						$('#editAuthor')
					).replaceWith(r.content);
				},
			});
		},
	},
};
</script>
