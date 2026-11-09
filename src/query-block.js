/**
 * Editor UI for wp-dynamic-filter/query container block.
 */
import { registerBlockType, createBlock } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	InnerBlocks,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	ToggleControl,
	TextControl,
	Spinner,
} from '@wordpress/components';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import metadata from '../blocks/query/block.json';

const ALLOWED = [ 'core/query' ];
const BLOCK_NAME = 'wp-dynamic-filter/query';
const I18N = 'wp-dynamic-filter';

/** Seed only for brand-new empty containers — fully replaceable. */
const TEMPLATE = [
	[
		'core/query',
		{
			query: {
				perPage: 6,
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
			},
		},
		[
			[
				'core/post-template',
				{},
				[
					[ 'core/post-title', { isLink: true } ],
					[ 'core/post-date' ],
					[ 'core/post-excerpt' ],
				],
			],
		],
	],
];

function createInstanceId() {
	if (
		typeof crypto !== 'undefined' &&
		typeof crypto.randomUUID === 'function'
	) {
		return crypto.randomUUID();
	}
	return `ddf-${ Date.now().toString( 36 ) }-${ Math.random()
		.toString( 36 )
		.slice( 2, 10 ) }`;
}

function Edit( { attributes, setAttributes, clientId } ) {
	const {
		instanceId,
		taxonomy,
		display,
		showAll,
		allLabel,
		multiple,
		showCounts,
		emptyMessage,
	} = attributes;

	const allButtonLabel =
		allLabel && allLabel.trim() ? allLabel : __( 'All', I18N );

	const [ terms, setTerms ] = useState( [] );
	const [ loadingTerms, setLoadingTerms ] = useState( false );

	useEffect( () => {
		if ( ! instanceId ) {
			setAttributes( { instanceId: createInstanceId() } );
		}
	}, [ instanceId, setAttributes ] );

	const hasInnerQuery = useSelect(
		( select ) => select( 'core/block-editor' ).getBlockCount( clientId ) > 0,
		[ clientId ]
	);

	const taxonomies = useSelect( ( select ) => {
		const list = select( 'core' ).getTaxonomies( { per_page: -1 } );
		return list || [];
	}, [] );

	const taxonomyOptions = useMemo( () => {
		const opts = [];
		taxonomies
			.filter( ( tax ) => tax.visibility?.public )
			.forEach( ( tax ) => {
				opts.push( {
					label: tax.name,
					value: tax.slug,
				} );
			} );
		if ( ! opts.length ) {
			opts.push(
				{ label: __( 'Categories', I18N ), value: 'category' },
				{ label: __( 'Tags', I18N ), value: 'post_tag' }
			);
		}
		return opts;
	}, [ taxonomies ] );

	const restBase = useMemo( () => {
		const tax = taxonomies.find( ( item ) => item.slug === taxonomy );
		if ( tax?.rest_base ) {
			return tax.rest_base;
		}
		if ( taxonomy === 'category' ) {
			return 'categories';
		}
		if ( taxonomy === 'post_tag' ) {
			return 'tags';
		}
		return taxonomy;
	}, [ taxonomies, taxonomy ] );

	useEffect( () => {
		if ( ! taxonomy || ! restBase ) {
			return undefined;
		}
		let cancelled = false;
		setLoadingTerms( true );
		apiFetch( {
			path: `/wp/v2/${ restBase }?per_page=20&hide_empty=true`,
		} )
			.then( ( res ) => {
				if ( ! cancelled ) {
					setTerms( Array.isArray( res ) ? res : [] );
				}
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setTerms( [] );
				}
			} )
			.finally( () => {
				if ( ! cancelled ) {
					setLoadingTerms( false );
				}
			} );
		return () => {
			cancelled = true;
		};
	}, [ taxonomy, restBase ] );

	const blockProps = useBlockProps( {
		className: 'wp-block-wp-dynamic-filter-query',
	} );

	const innerBlocksProps = useInnerBlocksProps(
		{
			className: 'ddf-query__inner',
		},
		{
			allowedBlocks: ALLOWED,
			template: hasInnerQuery ? undefined : TEMPLATE,
			templateLock: false,
			renderAppender: hasInnerQuery
				? undefined
				: InnerBlocks.ButtonBlockAppender,
		}
	);

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Filter', I18N ) } initialOpen={ true }>
					<p className="ddf-inspector-hint">
						{ __(
							'Place your Query Loop inside (or transform an existing loop). You can remove the default loop and drag yours in.',
							I18N
						) }
					</p>
					<SelectControl
						label={ __( 'Taxonomy', I18N ) }
						value={ taxonomy }
						options={ taxonomyOptions }
						onChange={ ( value ) =>
							setAttributes( { taxonomy: value } )
						}
					/>
					<SelectControl
						label={ __( 'Display', I18N ) }
						value={ display }
						options={ [
							{
								label: __( 'Buttons', I18N ),
								value: 'buttons',
							},
							{
								label: __( 'Dropdown', I18N ),
								value: 'select',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { display: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Multiple selection', I18N ) }
						help={ __(
							'Allow combining several terms (OR logic).',
							I18N
						) }
						checked={ !! multiple }
						onChange={ ( value ) =>
							setAttributes( { multiple: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Show “All” button', I18N ) }
						checked={ !! showAll }
						onChange={ ( value ) =>
							setAttributes( { showAll: value } )
						}
					/>
					{ showAll && (
						<TextControl
							label={ __( '“All” button label', I18N ) }
							value={ allLabel }
							placeholder={ __( 'All', I18N ) }
							onChange={ ( value ) =>
								setAttributes( { allLabel: value } )
							}
						/>
					) }
					<ToggleControl
						label={ __( 'Post counts', I18N ) }
						checked={ !! showCounts }
						onChange={ ( value ) =>
							setAttributes( { showCounts: value } )
						}
					/>
					<TextControl
						label={ __( 'Empty results message', I18N ) }
						value={ emptyMessage }
						onChange={ ( value ) =>
							setAttributes( { emptyMessage: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div className="ddf-filter ddf-filter--editor-preview">
					{ loadingTerms ? (
						<Spinner />
					) : display === 'select' ? (
						<select disabled multiple={ !! multiple }>
							{ showAll && ! multiple && (
								<option>{ allButtonLabel }</option>
							) }
							{ terms.map( ( term ) => (
								<option key={ term.id }>
									{ term.name }
									{ showCounts ? ` (${ term.count })` : '' }
								</option>
							) ) }
						</select>
					) : (
						<ul className="ddf-filter__list">
							{ showAll && (
								<li>
									<span className="ddf-filter__btn is-active">
										{ allButtonLabel }
									</span>
								</li>
							) }
							{ terms.map( ( term ) => (
								<li key={ term.id }>
									<span className="ddf-filter__btn">
										{ term.name }
										{ showCounts ? ` (${ term.count })` : '' }
									</span>
								</li>
							) ) }
						</ul>
					) }
				</div>
				{ ! hasInnerQuery && (
					<p className="ddf-query__placeholder">
						{ __(
							'Add or move your Query Loop here.',
							I18N
						) }
					</p>
				) }
				<div { ...innerBlocksProps } />
			</div>
		</>
	);
}

export function registerQueryFilterBlock() {
	registerBlockType( metadata.name, {
		...metadata,
		edit: Edit,
		save: () => <InnerBlocks.Content />,
		transforms: {
			from: [
				{
					type: 'block',
					blocks: [ 'core/query' ],
					transform: ( attributes, innerBlocks ) =>
						createBlock(
							BLOCK_NAME,
							{ instanceId: createInstanceId() },
							[ createBlock( 'core/query', attributes, innerBlocks ) ]
						),
				},
			],
		},
	} );
}
