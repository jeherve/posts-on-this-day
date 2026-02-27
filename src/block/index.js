/**
 * Posts On This Day — Query Loop block variation.
 *
 * @package
 */

import { registerBlockVariation } from '@wordpress/blocks';
import { InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	ToggleControl,
	SelectControl,
} from '@wordpress/components';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

const VARIATION_NAME = 'jeherve/posts-on-this-day';

registerBlockVariation( 'core/query', {
	name: VARIATION_NAME,
	title: __( 'Posts On This Day', 'posts-on-this-day' ),
	description: __(
		'Display a list of posts from years past.',
		'posts-on-this-day'
	),
	icon: 'calendar-alt',
	attributes: {
		namespace: VARIATION_NAME,
		query: {
			perPage: 10,
			pages: 0,
			offset: 0,
			postType: 'post',
			order: 'desc',
			orderBy: 'date',
			author: '',
			search: '',
			exclude: [],
			sticky: '',
			inherit: false,
			yearsBack: 10,
			exactMatch: false,
			groupByYear: true,
			yearHeadingLevel: 3,
		},
	},
	allowedControls: [ 'order', 'postType' ],
	innerBlocks: [
		[
			'core/post-template',
			{},
			[
				[ 'core/post-featured-image' ],
				[ 'core/post-title' ],
				[ 'core/post-date' ],
			],
		],
		[ 'core/query-no-results' ],
	],
	scope: [ 'inserter' ],
	isActive: [ 'namespace' ],
} );

const HEADING_LEVELS = [
	{ label: 'H2', value: '2' },
	{ label: 'H3', value: '3' },
	{ label: 'H4', value: '4' },
	{ label: 'H5', value: '5' },
	{ label: 'H6', value: '6' },
];

const withPostsOnThisDayControls = ( BlockEdit ) => ( props ) => {
	const { attributes, setAttributes } = props;

	if ( attributes.namespace !== VARIATION_NAME ) {
		return <BlockEdit key="edit" { ...props } />;
	}

	const { query } = attributes;

	const updateQuery = ( newParams ) => {
		setAttributes( {
			query: {
				...query,
				...newParams,
			},
		} );
	};

	return (
		<>
			<BlockEdit key="edit" { ...props } />
			<InspectorControls>
				<PanelBody
					title={ __( 'Posts On This Day', 'posts-on-this-day' ) }
				>
					<RangeControl
						label={ __( 'Years back', 'posts-on-this-day' ) }
						value={ query.yearsBack || 10 }
						onChange={ ( value ) =>
							updateQuery( { yearsBack: value } )
						}
						min={ 1 }
						max={ 20 }
					/>
					<ToggleControl
						label={ __( 'Exact date match', 'posts-on-this-day' ) }
						help={ __(
							'When disabled, shows posts from within a week of this day in years past.',
							'posts-on-this-day'
						) }
						checked={ !! query.exactMatch }
						onChange={ ( value ) =>
							updateQuery( { exactMatch: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Group by year', 'posts-on-this-day' ) }
						checked={ query.groupByYear !== false }
						onChange={ ( value ) =>
							updateQuery( { groupByYear: value } )
						}
					/>
					{ query.groupByYear !== false && (
						<SelectControl
							label={ __(
								'Year heading level',
								'posts-on-this-day'
							) }
							value={ String( query.yearHeadingLevel || 3 ) }
							options={ HEADING_LEVELS }
							onChange={ ( value ) =>
								updateQuery( {
									yearHeadingLevel: parseInt( value, 10 ),
								} )
							}
						/>
					) }
				</PanelBody>
			</InspectorControls>
		</>
	);
};

addFilter(
	'editor.BlockEdit',
	'jeherve/posts-on-this-day/controls',
	withPostsOnThisDayControls
);
