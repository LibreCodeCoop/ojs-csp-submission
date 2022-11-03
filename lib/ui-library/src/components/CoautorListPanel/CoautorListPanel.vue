<template>
	<div class="pkpListPanel--submissions" :class="classes">
		<!-- Header -->
		<pkp-header>
			{{ title }}
			<spinner v-if="isLoading" />
			<template slot="actions">
				<search
					:searchPhrase="searchPhrase"
					:searchLabel="__('common.search')"
					:clearSearchLabel="__('common.clearSearch')"
					@search-phrase-changed="setSearchPhrase"
				/>
			</template>
		</pkp-header>

		<!-- Body of the panel, including items and sidebar -->
		<div class="pkpListPanel__body -pkpClearfix">
			<!-- Content -->
			<div class="pkpListPanel__content" aria-live="polite">
				<!-- Items -->
				
				<template v-if="items.length">
					<coautor-list-item
						v-for="item in items"
						@addFilter="addFilter"
						:key="item.id"
						:item="item"
						:fillUser="fillUser"
					/>
				</template>

				<!-- Loading indicator when loading and no items exist -->
				<div v-else-if="isLoading" class="pkpListPanel__empty">
					<spinner />
					carregando
				</div>
			</div>
		</div>
		<!-- Footer -->
		<div class="pkpListPanel__footer -pkpClearfix">
			<div
				v-if="searchPhrase.trim().length <= minWordsToSearch"
				class="pkpListPanel__loadMore"
				:class="classLoadingMore"
			>
				<a class="pkpListPanel__loadMoreButton">
					Informe um nome
				</a>
			</div>
			<div
				v-if="!itemsMax && searchPhrase.trim().length > minWordsToSearch"
				class="pkpListPanel__loadMore"
				:class="classLoadingMore"
			>
				<a class="pkpListPanel__loadMoreButton" @click="newAuthor">
					Autor não encontrado, cadastrar
				</a>
			</div>
			<list-panel-load-more
				v-if="items.length < itemsMax"
				@loadMore="loadMore"
				:isLoading="isLoading"
			/>
			<ListPanelCount :count="items.length" :total="itemsMax" />
		</div>
	</div>
</template>

<script>
import ListPanel from '@/components/ListPanel/ListPanel.vue';
import ListPanelCount from '@csp/components/CoautorListPanel/ListPanelCount.vue';
import ListPanelLoadMore from '@csp/components/CoautorListPanel/ListPanelLoadMore.vue';
import Search from '@/components/Search/Search.vue';
import CoautorListItem from '@csp/components/CoautorListPanel/CoautorListItem.vue';
import fetch from '@/mixins/fetch';

export default {
	extends: ListPanel,
	components: {
		CoautorListItem,
		ListPanelCount,
		ListPanelLoadMore,
		Search
	},
	mixins: [fetch],
	props: {
		minWordsToSearch: {
			type: Number,
			required: true
		},
		fillUser: {
			type: String,
			required: true
		},
		itemsMax: {
			type: Number,
			default() {
				return 0;
			}
		},
		apiUrl: {
			type: String
		}
	},
	data() {
		return {
			originalApiUrl: '',
			classes: '',
			searchPhrase: ''
		};
	},
	computed: {
		classLoadingMore: function() {
			return {'-isLoadingMore': this.isLoading};
		}
	},
	methods: {
		// setSearchPhrase(searchPhrase) {
		// 	// this.searchPhrase = searchPhrase;
		// 	if (searchPhrase.length <= this.minWordsToSearch) {
		// 		if (!this.originalApiUrl) {
		// 			this.originalApiUrl = this.apiUrl;
		// 		}
		// 		this.$emit('set', this.id, {
		// 			apiUrl: null,
		// 			searchPhrase: searchPhrase,
		// 			items: [],
		// 			itemsMax: 0
		// 		});
		// 	} else if (this.originalApiUrl.length) {
		// 		this.$emit('set', this.id, {
		// 			apiUrl: this.originalApiUrl,
		// 			searchPhrase: searchPhrase
		// 		});
		// 	} else {
		// 		this.$emit('set', this.id, {
		// 			searchPhrase: searchPhrase
		// 		});
		// 	}
		// },
		setItems(items, itemsMax) {
			this.items = items;
			this.itemsMax = itemsMax;
		},
		newAuthor: function(e) {
			e.preventDefault();
			$.ajax({
				url: this.fillUser,
				data: {
					type: 'new',
					submissionId: $('[name="submissionId"]').val(),
					publicationId: $('[name="submissionId"]').val()
				},
				type: 'POST',
				success: function(r) {
					$.pkp.classes.Handler.getHandler($('#editAuthor')).replaceWith(
						r.content
					);
				}
			});
		},
		/**
		 * Load more items in the list
		 */
		loadMore: function() {
			this.$emit('set', this.id, {
				offset: this.items.length
			});
		}
	},
	watch: {
		offset: {
			handler() {
				this.get('append');
			}
		}
	}
};
</script>

<style lang="less">
@import '../../styles/variables';
.pkpListPanel__loadMore {
	position: unset;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	text-align: center;
}

.pkpListPanel__loadMoreButton,
.pkpListPanel__loadMoreNotice {
	position: unset;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	padding: @half @base;
	transition: opacity 0.3s;
	cursor: pointer;

	&:hover,
	&:focus {
		color: @text;
	}
}

.pkpListPanel__loadMoreButton {
	font-weight: @bold;
	text-decoration: none;
	opacity: 1;
	&:before {
		content: '';
		position: absolute;
		top: 0;
		left: 50%;
		width: 1em;
		height: 2px;
		background: @primary;
		opacity: 0;
		transform: translateX(-50%);
		transition: width 0.3s;
	}
	&:hover,
	&:focus {
		&:before {
			opacity: 1;
			width: 10em;
		}
	}
}

.pkpListPanel__loadMore.-isLoadingMore {
	.pkpListPanel__loadMoreButton {
		opacity: 0;
	}
	.pkpListPanel__loadMoreNotice {
		display: block;
		opacity: 1;
	}
}

.-pkpClearfix {
	border-bottom: 0;
	&:before,
	&:after {
		content: ' ';
		display: table;
	}
	&:after {
		clear: both;
	}
}
</style>
