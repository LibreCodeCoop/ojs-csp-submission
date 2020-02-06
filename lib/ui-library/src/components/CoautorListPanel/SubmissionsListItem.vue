<template>
	<li class="pkpListPanelItem pkpListPanelItem--submission pkpListPanelItem--hasSummary" :class="{'-hasFocus': isFocused}">
		<div class="pkpListPanelItem__summary -pkpClearfix">
			<a
				@click="addCoautor" class="pkpListPanelItem--submission__link"
				@focus="focusItem"
				@blur="blurItem"
				>
				<div class="pkpListPanelItem--submission__item">
					<div class="pkpListPanelItem--submission__id">
						<span class="-screenReader">{{ i18n.id }}</span>
						{{ item.id }}
					</div>
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
				<span v-if="isExpanded" class="-screenReader">{{ __('viewLess', {name: item.authorString}) }}</span>
				<span v-else class="-screenReader">{{ __('viewMore', {name: item.authorString}) }}</span>
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
	</li>
</template>

<script>
import ListPanelItem from '@/components/ListPanel/ListPanelItem.vue';
import List from '@/components/List/List.vue';
import ListItem from '@/components/List/ListItem.vue';
import PkpButton from '@/components/Button/Button.vue';
import Icon from '@/components/Icon/Icon.vue';

export default {
	extends: ListPanelItem,
	name: 'SubmissionsListItem',
	components: {
		List,
		ListItem,
		PkpButton,
		Icon,
	},
	props: ['item', 'i18n', 'apiPath', 'fillUser'],
	data: function () {
		return {
			isExpanded: false,
		};
	},
	methods: {
		expandData: function (e) {
			e.preventDefault();
			this.isExpanded = !this.isExpanded;
		},
		addCoautor: function (e) {
			e.preventDefault();
			$.ajax({
				url: this.fillUser,
				data: {
					userId: this.item.id,
					type: this.item.type,
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


<style lang="less">
@import '../../../../../../../../lib/ui-library/src/styles/_import';

.pkpListPanelItem--submission {
	position: relative;
	transition: all 0.3s;

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

	&:hover,
	&.-hasFocus {

		&:before {
			height: 100%;
			opacity: 1;
		}
	}
}

.pkpListPanelItem--submission__link {
	display: block;
	color: @text;
	text-decoration: none;

	&:hover,
	&:focus {
		color: @text;
	}
}

.pkpListPanelItem--submission__item {
	position: relative;
	float: left;
	width: 75%;
	padding-left: 48px;
}

.pkpListPanelItem--submission__id {
	position: absolute;
	top: 0;
	left: 0;
	font-size: @font-tiny;
	line-height: (@font-sml * 1.5); // Match ,pkpListPanelItem--submission__author
	color: @text;
}

.pkpListPanelItem--submission__author {
	font-weight: @bold;
}

// Details panel
.pkpListPanelItem__details--submission {
	padding: 1em (@base * 3) 1em 62px;
}
</style>
