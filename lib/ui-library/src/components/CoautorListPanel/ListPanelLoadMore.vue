<template>
	<div class="pkpListPanel__loadMore" :class="classLoadingMore">
		<a href="#" class="pkpListPanel__loadMoreButton" @click="loadMore">
			<icon icon="chevron-down" :inline="true" />
			{{ __('grid.action.moreItems') }}
		</a>
		<span class="pkpListPanel__loadMoreNotice">
			<span class="pkpSpinner" aria-hidden="true"></span>
			{{ __('common.loading') }}
		</span>
	</div>
</template>

<script>
import Icon from '@/components/Icon/Icon.vue';
export default {
	name: 'ListPanelLoadMore',
	components: {
		Icon
	},
	props: {
		isLoading: {
			type: String
		}
	},
	computed: {
		classLoadingMore: function() {
			return {
				'-isLoadingMore': this.isLoading
			};
		}
	},
	methods: {
		/**
		 * Emit an event to load more items into this list panel
		 */
		loadMore: function(e) {
			if (e instanceof Event) {
				e.preventDefault();
			}
			this.$emit('loadMore');
		}
	}
};
</script>

<style lang="less">
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
	position: absolute;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	padding: 8px 16px;
	transition: opacity 0.3s;
}
.pkpListPanel__loadMoreButton {
	font-weight: 700;
	text-decoration: none;
	opacity: 1;
	&:before {
		content: '';
		position: absolute;
		top: 0;
		left: 50%;
		width: 1em;
		height: 2px;
		background: #007ab2;
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
.pkpListPanel__loadMoreNotice {
	display: none;
	opacity: 0;
	.pkpSpinner {
		margin-right: 0.25em;
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
</style>
