<template>
	<div class="pkpListPanelItem--coautor">
		<list>
			<div class="pkpListPanelItem -pkpClearfix" :class="classes">
				<a
					@click="addCoautor"
					@focus="focusItem"
					@blur="blurItem"
					class="pkpListPanelItem--coautor__link"
				>
					<div class="pkpListPanelItem--coautor__item">
						<div class="pkpListPanelItem--submission__author">
							{{ item.fullName }}
						</div>
						<div class="pkpListPanelItem--submission__title">
							{{ item.email }}
						</div>
					</div>
				</a>
				<button
					v-if="item.instituicao"
					@click="expandData"
					class="pkpListPanelItem__expander"
				>
					<icon v-if="isExpanded" icon="angle-up" />
					<icon v-else icon="angle-down" />
					<span v-if="isExpanded" class="-screenReader">
						{{ __('viewLess', {name: item.authorString}) }}
					</span>
					<span v-else class="-screenReader">
						{{ __('viewMore', {name: item.authorString}) }}
					</span>
				</button>
			</div>
			<div
				v-if="isExpanded && item.instituicao"
				class="pkpListPanelItem__details pkpListPanelItem__details--submission"
			>
				<list>
					<list-item>
						<span>{{ item.instituicao }}</span>
					</list-item>
				</list>
			</div>
		</list>
	</div>
</template>

<script>
import ListPanelItem from '@/components/ListPanel/ListPanelItem.vue';
import List from '@/components/List/List.vue';
import ListItem from '@/components/List/ListItem.vue';
import Icon from '@/components/Icon/Icon.vue';

export default {
	extends: ListPanelItem,
	name: 'CoautorListItem',
	components: {
		List,
		ListItem,
		Icon
	},
	props: ['fillUser'],
	data: function() {
		return {
			isExpanded: false,
			isFocused: false
		};
	},
	methods: {
		/**
		 * Update the isFocused property
		 */
		focusItem: function() {
			this.isFocused = true;
		},
		/**
		 * Update the isFocused property
		 */
		blurItem: function() {
			this.isFocused = false;
		},
		expandData: function(e) {
			e.preventDefault();
			this.isExpanded = !this.isExpanded;
		},
		addCoautor: function(e) {
			e.preventDefault();
			$.ajax({
				url: this.fillUser,
				data: {
					userId: this.item.id,
					type: this.item.type,
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
		}
	}
};
</script>

<style lang="less">
@import '../../styles/variables';

.pkpListPanelItem--coautor {
	position: relative;
	transition: all 0.3s;

	&:before {
		content: '';
		display: block;
		position: absolute;
		top: 50%;
		left: 0;
		width: 0.1rem;
		height: 25%;
		background: @primary;
		opacity: 0;
		transition: height 0.3s;
		transform: translateY(-50%);
	}

	&:hover {
		&:before {
			height: 100%;
			opacity: 1;
		}
	}
}

.pkpListPanelItem--coautor__link {
	display: block;
	color: @text;
	text-decoration: none;
	cursor: pointer;

	&:hover,
	&:focus {
		color: @text;
	}

	&:before {
		content: '';
		display: block;
		position: absolute;
		top: 50%;
		right: 100%;
		width: 0.1rem;
		height: 25%;
		background: @primary;
		opacity: 0;
		transition: height 0.3s;
		transform: translateY(-50%);
	}

	&:focus {
		outline: 0;

		&:before {
			height: 100%;
			opacity: 1;
		}
	}
}

.pkpListPanelItem--coautor__item {
	float: left;
	position: relative;
	float: left;
	width: 75%;
}
</style>
