<template>
	<div class="pkpListPanel--submissions" :class="classes">
		<!-- Header -->
		<pkp-header>
			{{ title }}
			<spinner v-if="isLoading" />
			<template slot="actions">
				<search
					:searchPhrase="searchPhrase"
					:searchLabel="i18n.search"
					:clearSearchLabel="i18n.clearSearch"
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
						:i18n="i18n"
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
					{{ i18n.informAName }}
				</a>
			</div>
			<div
				v-if="!itemsMax && searchPhrase.trim().length > minWordsToSearch"
				class="pkpListPanel__loadMore"
				:class="classLoadingMore"
			>
				<a class="pkpListPanel__loadMoreButton" @click="newAuthor">
					{{ i18n.notFoundAndCreate }}
				</a>
			</div>
			<list-panel-load-more
				v-if="lastPage > 1"
				@loadMore="loadMore"
				:isLoading="isLoading"
				:i18n="i18n"
			/>
			<list-panel-count :count="items.length" :total="itemsMax" :i18n="i18n" />
		</div>
	</div>
</template>

<script>
import ListPanel from '@/components/ListPanel/ListPanel.vue';
import ListPanelCount from '@csp/components/CoautorListPanel/ListPanelCount.vue';
import ListPanelLoadMore from '@csp/components/CoautorListPanel/ListPanelLoadMore.vue';
import Search from '@/components/Search/Search.vue';
import CoautorListItem from '@csp/components/CoautorListPanel/CoautorListItem.vue';

export default {
	extends: ListPanel,
	components: {
		CoautorListItem,
		ListPanelCount,
		ListPanelLoadMore,
		Search
	},
	props: {
		minWordsToSearch: {
			type: Number,
			required: true
		},
		fillUser: {
			type: String,
			required: true
		}
	},
	data() {
		return {
			originalApiUrl: ''
		};
	},
	computed: {
		classLoadingMore: function() {
			return {'-isLoadingMore': this.isLoading};
		}
	},
	methods: {
		setSearchPhrase: function(value) {
			if (value.length <= this.minWordsToSearch) {
				if (!this.originalApiUrl) {
					this.originalApiUrl = this.apiUrl;
				}
				this.$emit('set', this.id, {
					apiUrl: null,
					searchPhrase: value,
					items: [],
					itemsMax: 0
				});
			} else if (this.originalApiUrl.length) {
				this.$emit('set', this.id, {
					apiUrl: this.originalApiUrl,
					searchPhrase: value
				});
			} else {
				this.$emit('set', this.id, {
					searchPhrase: value
				});
			}
		},
		newAuthor: function(e) {
			e.preventDefault();
			$.ajax({
				url: this.fillUser,
				data: {
					type: 'new',
					submissionId: $('[name="submissionId"]').val()
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
	position: absolute;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	text-align: center;
}

.pkpListPanel__loadMoreButton,
.pkpListPanel__loadMoreNotice {
	position: absolute;
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
