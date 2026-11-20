/**
 * Posts On This Day: Query Loop block variation.
 */

import { registerBlockVariation } from '@wordpress/blocks';
import { InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	SelectControl,
	ToggleControl,
} from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { store as coreStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';

const VARIATION_NAME = 'jeherve/posts-on-this-day';

// Matches Block::MAX_YEARS_BACK on the server.
const MAX_YEARS_BACK = 100;

// Used when switching from "every year" to a set number of years.
const DEFAULT_YEARS_BACK = 10;

registerBlockVariation( 'core/query', {
	name: VARIATION_NAME,
	title: __( 'Posts On This Day', 'posts-on-this-day' ),
	description: __(
		'Show posts published on this day in past years.',
		'posts-on-this-day'
	),
	keywords: [
		__( 'on this day', 'posts-on-this-day' ),
		__( 'memories', 'posts-on-this-day' ),
		__( 'anniversary', 'posts-on-this-day' ),
		__( 'years ago', 'posts-on-this-day' ),
	],
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
			// 0 means every year the site has posts for.
			yearsBack: 0,
			exactMatch: false,
			groupByYear: false,
			yearHeadingLevel: 3,
		},
	},
	allowedControls: [ 'postType', 'order', 'taxQuery', 'author', 'postCount' ],
	innerBlocks: [
		[
			'core/post-template',
			{
				layout: {
					type: 'grid',
					columnCount: null,
					minimumColumnWidth: '12rem',
				},
			},
			[
				[
					'core/post-featured-image',
					{ isLink: true, aspectRatio: '4/3' },
				],
				[ 'core/post-title', { isLink: true } ],
				[
					'core/post-date',
					{
						// Without this binding, a new Date block is a custom date
						// and shows today's date in the editor.
						metadata: {
							bindings: {
								datetime: {
									source: 'core/post-data',
									args: { field: 'date' },
								},
							},
						},
					},
				],
			],
		],
		[
			'core/query-no-results',
			{},
			[
				[
					'core/paragraph',
					{
						content: __(
							'Nothing was published around this day in past years. Check back tomorrow!',
							'posts-on-this-day'
						),
					},
				],
			],
		],
	],
	scope: [ 'inserter' ],
	isActive: [ 'namespace' ],
} );

const HEADING_LEVELS = [ 2, 3, 4, 5, 6 ].map( ( level ) => ( {
	/* translators: %d: heading level, from 2 to 6. */
	label: sprintf( __( 'Heading %d', 'posts-on-this-day' ), level ),
	value: String( level ),
} ) );

function PostsOnThisDayControls( { query, setQuery } ) {
	const postType = query.postType || 'post';

	// The oldest published post sets how far back "every year" reaches.
	const oldestYear = useSelect(
		( select ) => {
			const posts = select( coreStore ).getEntityRecords(
				'postType',
				postType,
				{
					per_page: 1,
					order: 'asc',
					orderby: 'date',
					_fields: 'id,date',
				}
			);
			return posts?.[ 0 ]?.date
				? parseInt( posts[ 0 ].date.slice( 0, 4 ), 10 )
				: null;
		},
		[ postType ]
	);

	const yearsOfHistory = oldestYear
		? Math.max( 1, new Date().getFullYear() - oldestYear )
		: null;
	const allYears = ! ( query.yearsBack > 0 );
	const sortedByDate = ! query.orderBy || query.orderBy === 'date';

	let allYearsHelp = __(
		'Looks back as far as your oldest post.',
		'posts-on-this-day'
	);
	if ( oldestYear ) {
		allYearsHelp = sprintf(
			/* translators: %d: year of the oldest published post. */
			__(
				'Looks back to %d, the year of your oldest post.',
				'posts-on-this-day'
			),
			oldestYear
		);
	}

	let groupHelp = __(
		'Adds a heading with the year before each group of posts on your site. The editor preview does not show the headings.',
		'posts-on-this-day'
	);
	if ( ! sortedByDate ) {
		groupHelp = __(
			'Available when posts are ordered by date.',
			'posts-on-this-day'
		);
	}

	return (
		<InspectorControls>
			<PanelBody title={ __( 'Posts On This Day', 'posts-on-this-day' ) }>
				<ToggleControl
					label={ __(
						'Include every past year',
						'posts-on-this-day'
					) }
					help={ allYears ? allYearsHelp : null }
					checked={ allYears }
					onChange={ ( value ) =>
						setQuery( {
							yearsBack: value
								? 0
								: Math.min(
										DEFAULT_YEARS_BACK,
										yearsOfHistory ?? DEFAULT_YEARS_BACK
								  ),
						} )
					}
				/>
				{ ! allYears && (
					<RangeControl
						__next40pxDefaultSize
						label={ __(
							'Number of years to look back',
							'posts-on-this-day'
						) }
						value={ query.yearsBack }
						onChange={ ( value ) =>
							setQuery( {
								yearsBack: Math.max( 1, value || 1 ),
							} )
						}
						min={ 1 }
						max={ Math.min(
							MAX_YEARS_BACK,
							Math.max(
								yearsOfHistory ?? 20,
								query.yearsBack ?? 1
							)
						) }
					/>
				) }
				<ToggleControl
					label={ __( 'Only this exact day', 'posts-on-this-day' ) }
					help={
						query.exactMatch
							? __(
									'Shows posts published on this exact day in past years.',
									'posts-on-this-day'
							  )
							: __(
									'Shows posts published in the 7 days up to this day in past years.',
									'posts-on-this-day'
							  )
					}
					checked={ !! query.exactMatch }
					onChange={ ( value ) => setQuery( { exactMatch: value } ) }
				/>
				<ToggleControl
					label={ __( 'Group posts by year', 'posts-on-this-day' ) }
					help={ groupHelp }
					checked={ sortedByDate && !! query.groupByYear }
					disabled={ ! sortedByDate }
					onChange={ ( value ) => setQuery( { groupByYear: value } ) }
				/>
				{ sortedByDate && !! query.groupByYear && (
					<SelectControl
						__next40pxDefaultSize
						label={ __(
							'Year heading level',
							'posts-on-this-day'
						) }
						value={ String( query.yearHeadingLevel || 3 ) }
						options={ HEADING_LEVELS }
						onChange={ ( value ) =>
							setQuery( {
								yearHeadingLevel: parseInt( value, 10 ),
							} )
						}
					/>
				) }
			</PanelBody>
		</InspectorControls>
	);
}

const withPostsOnThisDayControls = createHigherOrderComponent(
	( BlockEdit ) => ( props ) => {
		const { name, attributes, setAttributes } = props;
		const isOurs =
			name === 'core/query' && attributes.namespace === VARIATION_NAME;
		const query = attributes.query || {};

		// Core's "Reset all" in the query settings sets `inherit` back to true.
		// This block always runs its own query (the server enforces it too).
		const inherits = isOurs && !! query.inherit;
		useEffect( () => {
			if ( inherits ) {
				setAttributes( {
					query: { ...attributes.query, inherit: false },
				} );
			}
			// eslint-disable-next-line react-hooks/exhaustive-deps -- Only runs when `inherit` flips on.
		}, [ inherits ] );

		if ( ! isOurs ) {
			return <BlockEdit { ...props } />;
		}

		const setQuery = ( newParams ) =>
			setAttributes( { query: { ...query, ...newParams } } );

		return (
			<>
				<BlockEdit { ...props } />
				<PostsOnThisDayControls query={ query } setQuery={ setQuery } />
			</>
		);
	},
	'withPostsOnThisDayControls'
);

addFilter(
	'editor.BlockEdit',
	'jeherve/posts-on-this-day/controls',
	withPostsOnThisDayControls
);
