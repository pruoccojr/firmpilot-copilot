/**
 * Reviews for FirmPilot block: edit UI using wp.blockEditor and wp.components.
 */
( function ( wp ) {
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var SelectControl = wp.components.SelectControl;
	var ToggleControl = wp.components.ToggleControl;
	var FormTokenField = wp.components.FormTokenField;
	var RangeControl = wp.components.RangeControl;
	var ToggleGroupControl = wp.components.ToggleGroupControl || wp.components.__experimentalToggleGroupControl;
	var ToggleGroupControlOption = wp.components.ToggleGroupControlOption || wp.components.__experimentalToggleGroupControlOption;
	var UnitControl = wp.components.UnitControl;
	var _ssr = wp.serverSideRender;
	var ServerSideRender = ! _ssr ? _ssr : ( typeof _ssr === 'function' ? _ssr : ( _ssr.ServerSideRender || _ssr.default ) );

	var categoryOptions = ( window.fpReviewsBlock && window.fpReviewsBlock.categories ) || [];

	// Autoplay attribute (number, ms) ↔ UnitControl value string ("500ms").
	function autoplayMsToUnitValue( ms ) {
		if ( ms == null || ms === '' || ms === 0 ) {
			return '';
		}
		return String( ms ) + 'ms';
	}

	function unitValueToAutoplayMs( val ) {
		if ( val === '' || val == null || val === undefined ) {
			return 0;
		}
		var s = String( val ).trim();
		var m = s.match( /^([\-+]?[\d.]+)\s*ms$/i );
		if ( m ) {
			var a = parseFloat( m[ 1 ] );
			return isNaN( a ) ? NaN : Math.max( 0, Math.round( a ) );
		}
		m = s.match( /^([\-+]?[\d.]+)$/ );
		if ( m ) {
			var b = parseFloat( m[ 1 ] );
			return isNaN( b ) ? NaN : Math.max( 0, Math.round( b ) );
		}
		return NaN;
	}

	function slugsToLabels( slugs ) {
		return ( slugs || '' ).split( ',' ).map( function ( s ) {
			return s.trim();
		} ).filter( Boolean ).map( function ( slug ) {
			var o = categoryOptions.find( function ( c ) {
				return c.value === slug;
			} );
			return o ? o.label : slug;
		} );
	}

	function labelsToSlugs( labels ) {
		return ( Array.isArray( labels ) ? labels : [] ).map( function ( label ) {
			var o = categoryOptions.find( function ( c ) {
				return c.label === label;
			} );
			return o ? o.value : label;
		} ).join( ',' );
	}

	function placeholder( msg ) {
		return el( 'div', { className: 'fp-block-placeholder' },
			el( 'strong', null, __( 'Reviews for FirmPilot', 'firmpilot-reviews' ) ),
			el( 'p', { className: 'fp-block-placeholder__desc' }, msg )
		);
	}

	function parseGapRem( val, defaultNum ) {
		if ( val === undefined || val === null || val === '' ) {
			return defaultNum;
		}
		var m = String( val ).trim().match( /^([\d.]+)(rem|em|px)$/i );
		if ( ! m ) {
			return defaultNum;
		}
		var n = parseFloat( m[ 1 ] );
		if ( m[ 2 ].toLowerCase() === 'px' ) {
			n = n / 16;
		}
		return Math.max( 0, Math.min( 5, Math.round( n * 4 ) / 4 ) );
	}

	// Combined Layout value: grid | masonry | carousel
	function getLayoutType( attrs ) {
		if ( attrs.layoutDisplay === 'carousel' ) {
			return 'carousel';
		}
		var gs = attrs.grid_style || 'grid';
		if ( gs === 'masonry' ) {
			return 'masonry';
		}
		return 'grid';
	}

	function setLayoutType( props, v ) {
		if ( v === 'carousel' ) {
			props.setAttributes( {
				layoutDisplay: 'carousel'
			} );
			return;
		}
		props.setAttributes( {
			layoutDisplay: 'grid',
			grid_style: v === 'masonry' ? 'masonry' : 'grid'
		} );
	}

	wp.blocks.registerBlockType( 'firmpilot-reviews/reviews', {
		edit: function ( props ) {
			var attrs = props.attributes;
			var blockProps = useBlockProps( { className: 'wp-block-fp-reviews-reviews' } );
			var layoutType = getLayoutType( attrs );
			var isCarousel = layoutType === 'carousel';
			return el( wp.element.Fragment, null,
				el( InspectorControls, null,
					el( PanelBody, {
						title: __( 'Layout', 'firmpilot-reviews' ),
						initialOpen: true
					},
						el( ToggleGroupControl, {
							label: __( 'Grid Type', 'firmpilot-reviews' ),
							value: layoutType,
							onChange: function ( v ) {
								setLayoutType( props, v || 'grid' );
							},
							isBlock: true,
							__next40pxDefaultSize: true
						},
							el( ToggleGroupControlOption, {
								value: 'grid',
								label: __( 'Grid', 'firmpilot-reviews' )
							} ),
							el( ToggleGroupControlOption, {
								value: 'masonry',
								label: __( 'Masonry', 'firmpilot-reviews' )
							} ),
							el( ToggleGroupControlOption, {
								value: 'carousel',
								label: __( 'Carousel', 'firmpilot-reviews' )
							} )
						),
						el( RangeControl, {
							label: isCarousel ? __( 'Columns on Desktop (per slide)', 'firmpilot-reviews' ) : __( 'Columns on Desktop', 'firmpilot-reviews' ),
							value: attrs.columns_desktop,
							min: 1,
							max: 6,
							onChange: function ( v ) {
								props.setAttributes( { columns_desktop: v } );
							}
						} ),
						el( RangeControl, {
							label: isCarousel ? __( 'Columns on Tablet (per slide)', 'firmpilot-reviews' ) : __( 'Columns on Tablet', 'firmpilot-reviews' ),
							value: attrs.columns_tablet,
							min: 1,
							max: 4,
							onChange: function ( v ) {
								props.setAttributes( { columns_tablet: v } );
							}
						} ),
						el( RangeControl, {
							label: isCarousel ? __( 'Columns on Mobile (per slide)', 'firmpilot-reviews' ) : __( 'Columns on Mobile', 'firmpilot-reviews' ),
							value: attrs.columns_mobile,
							min: 1,
							max: 2,
							onChange: function ( v ) {
								props.setAttributes( { columns_mobile: v } );
							}
						} ),
						el( RangeControl, {
							label: __( 'Column Gap', 'firmpilot-reviews' ),
							value: parseGapRem( attrs.column_gap, 2 ),
							min: 0,
							max: 5,
							step: 0.25,
							onChange: function ( v ) {
								props.setAttributes( { column_gap: v != null ? v + 'rem' : '2rem' } );
							}
						} )
					),
					isCarousel
						? el( PanelBody, {
							title: __( 'Carousel', 'firmpilot-reviews' ),
							initialOpen: false
						},
							UnitControl
								? el( UnitControl, {
									label: __( 'Autoplay interval', 'firmpilot-reviews' ),
									help: __( 'Time between slides. Clear or 0 ms to disable autoplay (minimum 500 ms when active).', 'firmpilot-reviews' ),
									value: autoplayMsToUnitValue( attrs.carouselAutoplayMs ),
									units: [
										{
											value: 'ms',
											label: 'ms',
											default: 0,
											a11yLabel: __( 'Milliseconds (ms)', 'firmpilot-reviews' ),
											step: 1
										}
									],
									__next40pxDefaultSize: true,
									onChange: function ( v ) {
										var n = unitValueToAutoplayMs( v );
										if ( isNaN( n ) ) {
											return;
										}
										props.setAttributes( { carouselAutoplayMs: n } );
									}
								} )
								: el( TextControl, {
									label: __( 'Autoplay interval (ms)', 'firmpilot-reviews' ),
									help: __( 'Time between slides. Leave empty to disable autoplay.', 'firmpilot-reviews' ),
									type: 'number',
									value: attrs.carouselAutoplayMs > 0 ? String( attrs.carouselAutoplayMs ) : '',
									onChange: function ( v ) {
										if ( v === '' || v == null ) {
											props.setAttributes( { carouselAutoplayMs: 0 } );
											return;
										}
										var n = parseInt( String( v ), 10 );
										if ( ! isNaN( n ) ) {
											props.setAttributes( { carouselAutoplayMs: Math.max( 0, n ) } );
										}
									}
								} ),
							el( ToggleControl, {
								label: __( 'Pause Autoplay on Hover', 'firmpilot-reviews' ),
								checked: attrs.carouselPauseOnHover !== false,
								onChange: function ( v ) {
									props.setAttributes( { carouselPauseOnHover: v } );
								}
							} ),
							el( ToggleControl, {
								label: __( 'Arrow Navigation', 'firmpilot-reviews' ),
								checked: attrs.carouselShowArrows !== false,
								onChange: function ( v ) {
									props.setAttributes( { carouselShowArrows: v } );
								}
							} ),
							el( ToggleControl, {
								label: __( 'Dot Navigation', 'firmpilot-reviews' ),
								checked: attrs.carouselShowDots !== false,
								onChange: function ( v ) {
									props.setAttributes( { carouselShowDots: v } );
								}
							} )
						)
						: null,
					el( PanelBody, {
						title: __( 'Image', 'firmpilot-reviews' ),
						initialOpen: false
					},
						el( 'p', { className: 'description', style: { marginTop: 0 } }, __( 'If no featured image is set, a placeholder is used.', 'firmpilot-reviews' ) ),
						el( ToggleGroupControl, {
							label: __( 'Shape', 'firmpilot-reviews' ),
							value: attrs.imageShape || 'circle',
							onChange: function ( v ) {
								props.setAttributes( { imageShape: v || 'circle' } );
							},
							isBlock: true,
							__next40pxDefaultSize: true
						},
							el( ToggleGroupControlOption, {
								value: 'circle',
								label: __( 'Circle', 'firmpilot-reviews' )
							} ),
							el( ToggleGroupControlOption, {
								value: 'rounded',
								label: __( 'Rounded', 'firmpilot-reviews' )
							} ),
							el( ToggleGroupControlOption, {
								value: 'square',
								label: __( 'Squared', 'firmpilot-reviews' )
							} )
						),
						el( ToggleGroupControl, {
							label: __( 'Position', 'firmpilot-reviews' ),
							value: attrs.imagePosition === 'left' ? 'left' : 'top',
							onChange: function ( v ) {
								props.setAttributes( { imagePosition: v === 'left' ? 'left' : 'top' } );
							},
							isBlock: true,
							__next40pxDefaultSize: true
						},
							el( ToggleGroupControlOption, {
								value: 'top',
								label: __( 'Top', 'firmpilot-reviews' )
							} ),
							el( ToggleGroupControlOption, {
								value: 'left',
								label: __( 'Left', 'firmpilot-reviews' )
							} )
						),
						el( RangeControl, {
							label: __( 'Size', 'firmpilot-reviews' ),
							help: __( 'Width of the reviewer image (rem).', 'firmpilot-reviews' ),
							value: attrs.imageSizeRem != null ? attrs.imageSizeRem : 5,
							min: 3,
							max: 10,
							step: 1,
							onChange: function ( v ) {
								props.setAttributes( { imageSizeRem: v } );
							}
						} ),
						el( ToggleControl, {
							label: __( 'Hide placeholder images', 'firmpilot-reviews' ),
							checked: attrs.hidePlaceholderImages === true,
							onChange: function ( v ) {
								props.setAttributes( { hidePlaceholderImages: v } );
							}
						} )
					),
					el( PanelBody, {
						title: __( 'Content', 'firmpilot-reviews' ),
						initialOpen: false
					},
						el( FormTokenField, {
							label: __( 'Categories Shown', 'firmpilot-reviews' ),
							help: __( 'Leave blank to show all categories.', 'firmpilot-reviews' ),
							value: slugsToLabels( attrs.category ),
							suggestions: categoryOptions.map( function ( c ) {
								return c.label;
							} ),
							onChange: function ( tokens ) {
								props.setAttributes( { category: labelsToSlugs( tokens ) } );
							},
							__experimentalExpandOnFocus: true
						} ),
						el( TextControl, {
							label: __( 'Number of Items', 'firmpilot-reviews' ),
							type: 'number',
							value: attrs.limit === -1 ? '' : attrs.limit,
							help: __( 'Leave blank to show all.', 'firmpilot-reviews' ),
							onChange: function ( v ) {
								props.setAttributes( { limit: v === '' ? -1 : parseInt( v, 10 ) } );
							}
						} ),
						el( SelectControl, {
							label: __( 'Sort By', 'firmpilot-reviews' ),
							value: attrs.orderby,
							options: [
								{ label: __( 'Custom Sorted Order', 'firmpilot-reviews' ), value: 'sort_order' },
								{ label: __( 'Date Created', 'firmpilot-reviews' ), value: 'created_at' },
								{ label: __( 'Title', 'firmpilot-reviews' ), value: 'author_name' }
							],
							onChange: function ( v ) {
								props.setAttributes( { orderby: v } );
							}
						} ),
						el( ToggleGroupControl, {
							label: __( 'Sort Direction', 'firmpilot-reviews' ),
							value: attrs.order || 'ASC',
							onChange: function ( v ) {
								props.setAttributes( { order: v || 'ASC' } );
							},
							isBlock: true,
							__next40pxDefaultSize: true
						},
							el( ToggleGroupControlOption, {
								value: 'ASC',
								label: __( 'Ascending', 'firmpilot-reviews' )
							} ),
							el( ToggleGroupControlOption, {
								value: 'DESC',
								label: __( 'Descending', 'firmpilot-reviews' )
							} )
						),
						el( ToggleGroupControl, {
							label: __( 'Title Element', 'firmpilot-reviews' ),
							value: attrs.title_element || 'h3',
							onChange: function ( v ) {
								props.setAttributes( { title_element: v || 'h3' } );
							},
							isBlock: true,
							__next40pxDefaultSize: true
						},
							el( ToggleGroupControlOption, {
								value: 'h2',
								label: 'h2'
							} ),
							el( ToggleGroupControlOption, {
								value: 'h3',
								label: 'h3'
							} ),
							el( ToggleGroupControlOption, {
								value: 'h4',
								label: 'h4'
							} ),
							el( ToggleGroupControlOption, {
								value: 'h5',
								label: 'h5'
							} ),
							el( ToggleGroupControlOption, {
								value: 'h6',
								label: 'h6'
							} ),
							el( ToggleGroupControlOption, {
								value: 'div',
								label: 'div'
							} ),
							el( ToggleGroupControlOption, {
								value: 'p',
								label: 'p'
							} )
						)
					),
					el( PanelBody, {
						title: __( 'Display', 'firmpilot-reviews' ),
						initialOpen: false
					},
						el( ToggleControl, {
							label: __( 'Show User Image', 'firmpilot-reviews' ),
							checked: attrs.showUserImage !== false,
							onChange: function ( v ) {
								props.setAttributes( { showUserImage: v } );
							}
						} ),
						el( ToggleControl, {
							label: __( 'Show Rating', 'firmpilot-reviews' ),
							checked: attrs.showRating !== false,
							onChange: function ( v ) {
								props.setAttributes( { showRating: v } );
							}
						} ),
						el( ToggleControl, {
							label: __( 'Show Company', 'firmpilot-reviews' ),
							checked: attrs.show_company !== false,
							onChange: function ( v ) {
								props.setAttributes( { show_company: v } );
							}
						} ),
						el( ToggleControl, {
							label: __( 'Show Job Title', 'firmpilot-reviews' ),
							checked: attrs.show_job_title !== false,
							onChange: function ( v ) {
								props.setAttributes( { show_job_title: v } );
							}
						} ),
						el( ToggleControl, {
							label: __( 'Show Location', 'firmpilot-reviews' ),
							checked: attrs.show_location !== false,
							onChange: function ( v ) {
								props.setAttributes( { show_location: v } );
							}
						} )
					)
				),
				el( 'div', blockProps,
					el( ServerSideRender, {
						key: 'reviews-' + JSON.stringify( attrs ),
						block: 'firmpilot-reviews/reviews',
						attributes: props.attributes,
						EmptyResponsePlaceholder: function () {
							return placeholder( __( 'No reviews found.', 'firmpilot-reviews' ) );
						},
						ErrorResponsePlaceholder: function () {
							return placeholder( __( 'Preview unavailable.', 'firmpilot-reviews' ) );
						},
						LoadingResponsePlaceholder: function () {
							return el( 'div', { className: 'fp-block-placeholder' },
								el( 'p', { className: 'fp-block-placeholder__desc' }, __( 'Loading…', 'firmpilot-reviews' ) )
							);
						}
					} )
				)
			);
		},
		save: function () {
			return null;
		}
	} );
} )( window.wp );
