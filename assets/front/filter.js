/**
 * Dynamic Filter — front-end AJAX engine (vanilla ES6).
 */
( function () {
	'use strict';

	const settings =
		typeof window !== 'undefined' && window.ddfSettings
			? window.ddfSettings
			: { restUrl: '', nonce: '' };

	/**
	 * @param {HTMLElement} root
	 * @returns {boolean}
	 */
	function isMultiple( root ) {
		return root.getAttribute( 'data-ddf-multiple' ) === '1';
	}

	/**
	 * @param {HTMLElement} root
	 * @returns {string[]}
	 */
	function getSelectedTerms( root ) {
		const select = root.querySelector( '.ddf-filter__select' );
		if ( select ) {
			if ( select.multiple ) {
				return Array.from( select.selectedOptions )
					.map( ( opt ) => opt.value )
					.filter( Boolean );
			}
			return select.value ? [ select.value ] : [];
		}

		return Array.from(
			root.querySelectorAll( '.ddf-filter__btn.is-active[data-ddf-term]' )
		)
			.map( ( btn ) => btn.getAttribute( 'data-ddf-term' ) || '' )
			.filter( Boolean );
	}

	/**
	 * @param {HTMLElement} root
	 * @param {string[]} terms
	 * @param {{ push?: boolean }} options
	 */
	async function applyFilter( root, terms, options = {} ) {
		const results = root.querySelector( '[data-ddf-results]' );
		if ( ! results || ! settings.restUrl ) {
			return;
		}

		const list = Array.isArray( terms )
			? terms.filter( Boolean )
			: terms
				? [ terms ]
				: [];

		const instanceId = root.getAttribute( 'data-ddf-instance' ) || '';
		const taxonomy = root.getAttribute( 'data-ddf-taxonomy' ) || 'category';
		const postId = parseInt( root.getAttribute( 'data-ddf-post-id' ) || '0', 10 );

		results.classList.add( 'is-loading' );

		try {
			const response = await fetch( settings.restUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': settings.nonce || '',
				},
				body: JSON.stringify( {
					post_id: postId,
					instance_id: instanceId,
					taxonomy,
					terms: list,
					term: list.length === 1 ? list[ 0 ] : '',
					paged: 1,
				} ),
			} );

			if ( ! response.ok ) {
				throw new Error( 'Filter request failed' );
			}

			const data = await response.json();
			if ( typeof data.html === 'string' ) {
				results.innerHTML = data.html;
			}

			setActiveUi( root, list );

			if ( options.push !== false ) {
				pushUrl( instanceId, taxonomy, list );
			}
		} catch ( err ) {
			if ( typeof console !== 'undefined' && console.error ) {
				console.error( '[dynamic-filter]', err );
			}
		} finally {
			results.classList.remove( 'is-loading' );
		}
	}

	/**
	 * @param {HTMLElement} root
	 * @param {string[]} terms
	 */
	function setActiveUi( root, terms ) {
		const selected = Array.isArray( terms ) ? terms.filter( Boolean ) : [];
		const isAll = selected.length === 0;

		root.querySelectorAll( '.ddf-filter__btn' ).forEach( ( btn ) => {
			const value = btn.getAttribute( 'data-ddf-term' ) || '';
			const isAllBtn = btn.hasAttribute( 'data-ddf-all' ) || value === '';
			const active = isAllBtn
				? isAll
				: selected.includes( value );
			btn.classList.toggle( 'is-active', active );
			btn.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
		} );

		const clearBtn = root.querySelector( '.ddf-filter__clear' );
		if ( clearBtn ) {
			clearBtn.classList.toggle( 'is-active', isAll );
		}

		const select = root.querySelector( '.ddf-filter__select' );
		if ( select ) {
			if ( select.multiple ) {
				Array.from( select.options ).forEach( ( opt ) => {
					opt.selected = selected.includes( opt.value );
				} );
			} else {
				select.value = selected[ 0 ] || '';
			}
		}
	}

	/**
	 * @param {string} instanceId
	 * @param {string} taxonomy
	 * @param {string[]} terms
	 */
	function pushUrl( instanceId, taxonomy, terms ) {
		const url = new URL( window.location.href );
		const list = Array.isArray( terms ) ? terms.filter( Boolean ) : [];
		if ( list.length ) {
			url.searchParams.set( 'ddf_id', instanceId );
			url.searchParams.set( 'ddf_tax', taxonomy );
			url.searchParams.set( 'ddf_term', list.join( ',' ) );
		} else {
			url.searchParams.delete( 'ddf_id' );
			url.searchParams.delete( 'ddf_tax' );
			url.searchParams.delete( 'ddf_term' );
		}
		window.history.pushState( { ddf: true }, '', url.toString() );
	}

	/**
	 * @param {string} instanceId
	 * @returns {{ taxonomy: string, terms: string[] } | null}
	 */
	function readUrlState( instanceId ) {
		const url = new URL( window.location.href );
		const id = url.searchParams.get( 'ddf_id' );
		if ( ! id || id !== instanceId ) {
			return null;
		}
		const raw = url.searchParams.get( 'ddf_term' ) || '';
		const terms = raw
			? raw
					.split( ',' )
					.map( ( t ) => t.trim() )
					.filter( Boolean )
			: [];
		return {
			taxonomy: url.searchParams.get( 'ddf_tax' ) || '',
			terms,
		};
	}

	/**
	 * @param {HTMLElement} root
	 * @param {string} term
	 */
	function onTermClick( root, term ) {
		if ( ! isMultiple( root ) ) {
			applyFilter( root, term ? [ term ] : [] );
			return;
		}

		// "Tous" / clear → empty selection.
		if ( ! term ) {
			applyFilter( root, [] );
			return;
		}

		const current = getSelectedTerms( root );
		const next = current.includes( term )
			? current.filter( ( t ) => t !== term )
			: current.concat( term );
		applyFilter( root, next );
	}

	/**
	 * @param {HTMLElement} root
	 */
	function bindRoot( root ) {
		if ( root.dataset.ddfBound === '1' ) {
			return;
		}
		root.dataset.ddfBound = '1';

		root.addEventListener( 'click', ( event ) => {
			const btn = event.target.closest(
				'.ddf-filter__btn, .ddf-filter__clear'
			);
			if ( ! btn || ! root.contains( btn ) ) {
				return;
			}
			event.preventDefault();
			const term = btn.getAttribute( 'data-ddf-term' ) || '';
			onTermClick( root, term );
		} );

		root.addEventListener( 'change', ( event ) => {
			const select = event.target.closest( '.ddf-filter__select' );
			if ( ! select || ! root.contains( select ) ) {
				return;
			}
			if ( select.multiple ) {
				const values = Array.from( select.selectedOptions )
					.map( ( opt ) => opt.value )
					.filter( Boolean );
				applyFilter( root, values );
			} else {
				applyFilter( root, select.value ? [ select.value ] : [] );
			}
		} );

		const instanceId = root.getAttribute( 'data-ddf-instance' ) || '';
		const fromUrl = readUrlState( instanceId );
		if ( fromUrl ) {
			setActiveUi( root, fromUrl.terms );
		}
	}

	function initAll() {
		document
			.querySelectorAll( '.ddf-query[data-ddf-instance]' )
			.forEach( bindRoot );
	}

	window.addEventListener( 'popstate', () => {
		document
			.querySelectorAll( '.ddf-query[data-ddf-instance]' )
			.forEach( ( root ) => {
				const instanceId = root.getAttribute( 'data-ddf-instance' ) || '';
				const fromUrl = readUrlState( instanceId );
				applyFilter( root, fromUrl ? fromUrl.terms : [], {
					push: false,
				} );
			} );
	} );

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initAll );
	} else {
		initAll();
	}
} )();
