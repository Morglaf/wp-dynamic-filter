/**
 * Editor UI for dynamic-filter/query container block.
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
		allLabel && allLabel.trim()
			? allLabel
			: __( 'Tous', 'dynamic-filter' );

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
				{ label: __( 'Catégories', 'dynamic-filter' ), value: 'category' },
				{ label: __( 'Étiquettes', 'dynamic-filter' ), value: 'post_tag' }
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
		className: 'wp-block-dynamic-filter-query',
	} );

	const innerBlocksProps = useInnerBlocksProps(
		{
			className: 'ddf-query__inner',
		},
		{
			allowedBlocks: ALLOWED,
			// Only seed a default query when the container is empty.
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
				<PanelBody
					title={ __( 'Filtre', 'dynamic-filter' ) }
					initialOpen={ true }
				>
					<p className="ddf-inspector-hint">
						{ __(
							'Place ta Query Loop à l’intérieur (ou transforme une boucle existante). Tu peux supprimer la boucle par défaut et y glisser la tienne.',
							'dynamic-filter'
						) }
					</p>
					<SelectControl
						label={ __( 'Taxonomie', 'dynamic-filter' ) }
						value={ taxonomy }
						options={ taxonomyOptions }
						onChange={ ( value ) =>
							setAttributes( { taxonomy: value } )
						}
					/>
					<SelectControl
						label={ __( 'Affichage', 'dynamic-filter' ) }
						value={ display }
						options={ [
							{
								label: __( 'Boutons', 'dynamic-filter' ),
								value: 'buttons',
							},
							{
								label: __( 'Menu déroulant', 'dynamic-filter' ),
								value: 'select',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { display: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Sélection multiple', 'dynamic-filter' ) }
						help={ __(
							'Permet de cumuler plusieurs termes (OU logique).',
							'dynamic-filter'
						) }
						checked={ !! multiple }
						onChange={ ( value ) =>
							setAttributes( { multiple: value } )
						}
					/>
					<ToggleControl
						label={ __( 'Afficher le bouton « tous »', 'dynamic-filter' ) }
						checked={ !! showAll }
						onChange={ ( value ) =>
							setAttributes( { showAll: value } )
						}
					/>
					{ showAll && (
						<TextControl
							label={ __( 'Libellé du bouton « tous »', 'dynamic-filter' ) }
							value={ allLabel }
							placeholder={ __( 'Tous', 'dynamic-filter' ) }
							onChange={ ( value ) =>
								setAttributes( { allLabel: value } )
							}
						/>
					) }
					<ToggleControl
						label={ __( 'Compteur d’articles', 'dynamic-filter' ) }
						checked={ !! showCounts }
						onChange={ ( value ) =>
							setAttributes( { showCounts: value } )
						}
					/>
					<TextControl
						label={ __( 'Message si vide', 'dynamic-filter' ) }
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
							'Ajoute ou déplace ici ta boucle de requête (Query Loop).',
							'dynamic-filter'
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
							'dynamic-filter/query',
							{ instanceId: createInstanceId() },
							[ createBlock( 'core/query', attributes, innerBlocks ) ]
						),
				},
			],
		},
	} );
}
