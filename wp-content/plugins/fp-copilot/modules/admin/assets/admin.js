( function () {
	const rootNode = document.getElementById( 'fp-copilot-admin-root' );

	function showAdminError( message ) {
		if ( ! rootNode ) {
			return;
		}

		rootNode.innerHTML =
			'<div class="notice notice-error"><p><strong>FirmPilot Copilot</strong>: ' +
			message +
			'</p></div>';
	}

	if ( ! window.wp || ! wp.element || ! wp.components || ! wp.i18n || ! wp.apiFetch ) {
		showAdminError(
			'Required WordPress admin scripts failed to load. Please refresh the page or check for plugin conflicts.'
		);
		return;
	}

	const { createElement: el, createRoot, render, useState, useEffect, useCallback } = wp.element;
	const { Button, Panel, PanelBody, PanelRow, ToggleControl, Spinner, Snackbar } = wp.components;
	const { __ } = wp.i18n;
	const apiFetch = wp.apiFetch;
	const icons = wp.icons || {};

	const databaseIcon = icons.database || icons.archive || null;

	const NAV_ITEMS = [
		{ id: 'tools', label: __( 'Tools', 'fp-copilot' ), icon: icons.tool || null },
		{ id: 'utilities', label: __( 'Utilities', 'fp-copilot' ), icon: icons.plugins || null },
		{ id: 'database', label: __( 'Database', 'fp-copilot' ), icon: databaseIcon },
		{ id: 'code-snippets', label: __( 'Code Snippets', 'fp-copilot' ), icon: icons.editorCode || null },
		{ id: 'settings', label: __( 'Settings', 'fp-copilot' ), icon: icons.cog || null },
	];

	if ( window.fpCopilotAdmin ) {
		apiFetch.use( apiFetch.createNonceMiddleware( window.fpCopilotAdmin.nonce ) );
		apiFetch.use( apiFetch.createRootURLMiddleware( window.fpCopilotAdmin.apiRoot ) );
	}

	function Sidebar( { currentView, onNavigate } ) {
		return el(
			'nav',
			{
				className: 'fp-copilot-admin__sidebar',
				'aria-label': __( 'FirmPilot Copilot sections', 'fp-copilot' ),
			},
			el(
				'ul',
				{ className: 'fp-copilot-admin__nav' },
				NAV_ITEMS.map( ( item ) =>
					el(
						'li',
						{ key: item.id },
						el(
							Button,
							{
								className: 'fp-copilot-admin__nav-item',
								icon: item.icon,
								variant: 'tertiary',
								isPressed: currentView === item.id,
								onClick: () => onNavigate( item.id ),
							},
							item.label
						)
					)
				)
			)
		);
	}

	function PlaceholderView( { title } ) {
		return el(
			'div',
			{ className: 'fp-copilot-admin__placeholder' },
			el( 'h2', { className: 'fp-copilot-admin__title' }, title )
		);
	}

	async function copyTextToClipboard( text ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			await navigator.clipboard.writeText( text );
			return;
		}

		const field = document.createElement( 'textarea' );
		field.value = text;
		field.setAttribute( 'readonly', '' );
		field.style.position = 'absolute';
		field.style.left = '-9999px';
		document.body.appendChild( field );
		field.select();
		document.execCommand( 'copy' );
		document.body.removeChild( field );
	}

	function SettingsView() {
		const [ apiKey, setApiKey ] = useState( '' );
		const [ loading, setLoading ] = useState( true );
		const [ notice, setNotice ] = useState( null );

		useEffect( () => {
			let active = true;

			apiFetch( { path: '/fp-copilot/v1/settings' } )
				.then( ( settings ) => {
					if ( active ) {
						setApiKey( settings.apiKey || '' );
					}
				} )
				.catch( ( error ) => {
					if ( active ) {
						setNotice( {
							status: 'error',
							message: error?.message || __( 'Unable to load settings.', 'fp-copilot' ),
						} );
					}
				} )
				.finally( () => {
					if ( active ) {
						setLoading( false );
					}
				} );

			return () => {
				active = false;
			};
		}, [] );

		const handleCopyApiKey = async ( event ) => {
			event.preventDefault();

			if ( ! apiKey ) {
				return;
			}

			event.currentTarget.select();

			try {
				await copyTextToClipboard( apiKey );
				setNotice( {
					status: 'success',
					message: __( 'API key copied to clipboard.', 'fp-copilot' ),
				} );
			} catch ( error ) {
				setNotice( {
					status: 'error',
					message: __( 'Unable to copy the API key.', 'fp-copilot' ),
				} );
			}
		};

		if ( loading ) {
			return el(
				'div',
				{ className: 'fp-copilot-admin__loading' },
				el( Spinner, null )
			);
		}

		return el(
			'div',
			{ className: 'fp-copilot-admin__settings' },
			el( 'h2', { className: 'fp-copilot-admin__title' }, __( 'Settings', 'fp-copilot' ) ),
			el(
				'div',
				{ className: 'fp-copilot-admin__callout' },
				el(
					'h3',
					{ className: 'fp-copilot-admin__callout-title' },
					__( 'Site API key', 'fp-copilot' )
				),
				el(
					'p',
					{ className: 'fp-copilot-admin__callout-description' },
					__(
						'Use this key to connect external FirmPilot software to this WordPress site. Keep it secret and treat it like a password.',
						'fp-copilot'
					)
				),
				el( 'input', {
					type: 'text',
					className: 'fp-copilot-admin__api-key-input',
					value: apiKey,
					readOnly: true,
					onClick: handleCopyApiKey,
					onFocus: ( event ) => event.target.select(),
					'aria-label': __( 'Site API key', 'fp-copilot' ),
					placeholder: __( 'Click to copy', 'fp-copilot' ),
				} ),
				el(
					'p',
					{ className: 'fp-copilot-admin__callout-hint' },
					__( 'Click the field to copy the API key.', 'fp-copilot' )
				)
			),
			notice &&
				el(
					Snackbar,
					{
						status: notice.status,
						onRemove: () => setNotice( null ),
					},
					notice.message
				)
		);
	}

	function UtilitiesView() {
		const [ utilities, setUtilities ] = useState( [] );
		const [ loading, setLoading ] = useState( true );
		const [ savingId, setSavingId ] = useState( null );
		const [ notice, setNotice ] = useState( null );

		const loadUtilities = useCallback( async () => {
			setLoading( true );

			try {
				const items = await apiFetch( { path: '/fp-copilot/v1/utilities' } );
				setUtilities( items );
			} catch ( error ) {
				setNotice( {
					status: 'error',
					message: error?.message || __( 'Unable to load utilities.', 'fp-copilot' ),
				} );
			} finally {
				setLoading( false );
			}
		}, [] );

		useEffect( () => {
			loadUtilities();
		}, [ loadUtilities ] );

		const toggleUtility = async ( id, enabled ) => {
			setSavingId( id );

			try {
				const updated = await apiFetch( {
					path: '/fp-copilot/v1/utilities/' + id,
					method: 'PATCH',
					data: { enabled },
				} );

				setUtilities( ( current ) =>
					current.map( ( utility ) => ( utility.id === id ? updated : utility ) )
				);

				setNotice( {
					status: 'success',
					message: __( 'Utility updated.', 'fp-copilot' ),
				} );
			} catch ( error ) {
				setNotice( {
					status: 'error',
					message: error?.message || __( 'Unable to update utility.', 'fp-copilot' ),
				} );
			} finally {
				setSavingId( null );
			}
		};

		if ( loading ) {
			return el(
				'div',
				{ className: 'fp-copilot-admin__loading' },
				el( Spinner, null )
			);
		}

		return el(
			'div',
			{ className: 'fp-copilot-admin__utilities' },
			el( 'h2', { className: 'fp-copilot-admin__title' }, __( 'Utilities', 'fp-copilot' ) ),
			el(
				'p',
				{ className: 'fp-copilot-admin__description' },
				__( 'Enable or disable individual FirmPilot Copilot utilities.', 'fp-copilot' )
			),
			el(
				Panel,
				null,
				utilities.map( ( utility ) =>
					el(
						PanelBody,
						{ key: utility.id, title: utility.name, initialOpen: true },
						el(
							PanelRow,
							null,
							el( ToggleControl, {
								label: utility.name,
								help: utility.description,
								checked: utility.enabled,
								disabled: savingId === utility.id,
								onChange: ( enabled ) => toggleUtility( utility.id, enabled ),
							} )
						)
					)
				)
			),
			notice &&
				el(
					Snackbar,
					{
						status: notice.status,
						onRemove: () => setNotice( null ),
					},
					notice.message
				)
		);
	}

	function App() {
		const [ currentView, setCurrentView ] = useState( 'tools' );

		let viewContent;

		switch ( currentView ) {
			case 'utilities':
				viewContent = el( UtilitiesView, null );
				break;
			case 'database':
				viewContent = el( PlaceholderView, { title: __( 'Database', 'fp-copilot' ) } );
				break;
			case 'code-snippets':
				viewContent = el( PlaceholderView, { title: __( 'Code Snippets', 'fp-copilot' ) } );
				break;
			case 'settings':
				viewContent = el( SettingsView, null );
				break;
			default:
				viewContent = el( PlaceholderView, { title: __( 'Tools', 'fp-copilot' ) } );
				break;
		}

		return el(
			'div',
			{ className: 'fp-copilot-admin' },
			el( Sidebar, { currentView, onNavigate: setCurrentView } ),
			el( 'main', { className: 'fp-copilot-admin__content' }, viewContent )
		);
	}

	if ( ! rootNode ) {
		return;
	}

	try {
		const app = el( App, null );

		if ( createRoot ) {
			createRoot( rootNode ).render( app );
		} else if ( render ) {
			render( app, rootNode );
		} else {
			showAdminError( 'Unable to initialize the admin interface.' );
		}
	} catch ( error ) {
		showAdminError( error && error.message ? error.message : 'Unable to initialize the admin interface.' );
	}
}() );
