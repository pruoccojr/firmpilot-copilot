<?php
/**
 * Reviews for FirmPilot – front-end renderer (markup inlined; no component templates).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FP_Reviews_Render {

	const TITLE_ELEMENTS = array( 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'p' );

	/**
	 * Theme stylesheet URL (tokens + layout).
	 *
	 * @return string
	 */
	public static function get_style_url() {
		return fp_reviews_asset_url( 'assets/css/styles.css' );
	}

	/**
	 * Placeholder avatar image URL.
	 *
	 * @return string
	 */
	public static function placeholder_image_url() {
		return FP_REVIEWS_PLUGIN_URL . 'assets/images/placeholder-1x1-user.svg';
	}

	public static function default_options() {
		return array(
			'category'                => '',
			'category_slugs'          => array(),
			'column_gap'              => '2rem',
			'columns_desktop'         => 4,
			'columns_mobile'          => 2,
			'columns_tablet'          => 3,
			'display'                 => 'grid',
			'grid_style'              => 'grid',
			'limit'                   => -1,
			'order'                   => 'ASC',
			'orderby'                 => 'sort_order',
			'show_company'            => true,
			'show_job_title'          => true,
			'show_location'           => true,
			'show_user_image'         => true,
			'show_rating'             => true,
			'hide_placeholder_images' => false,
			'title_element'           => 'h3',
			'image_shape'             => 'circle',
			'image_position'          => 'top',
			'image_size_rem'          => 5,
			'carousel_autoplay_ms'    => 0,
			'carousel_pause_on_hover' => true,
			'carousel_show_arrows'    => true,
			'carousel_show_dots'      => true,
		);
	}

	public static function sanitize_options( $atts ) {
		$opts      = array_merge( self::default_options(), $atts );
		$title_tag = isset( $opts['title_element'] ) ? strtolower( $opts['title_element'] ) : 'h3';
		if ( ! in_array( $title_tag, self::TITLE_ELEMENTS, true ) ) {
			$title_tag = 'h3';
		}
		$opts['title_element'] = $title_tag;

		if ( ! empty( $opts['category_slugs'] ) && is_array( $opts['category_slugs'] ) ) {
			$opts['category_slugs'] = $opts['category_slugs'];
		} else {
			$opts['category_slugs'] = self::parse_category_slugs( isset( $opts['category'] ) ? $opts['category'] : '' );
		}

		$raw_limit = isset( $opts['limit'] ) ? $opts['limit'] : -1;
		if ( $raw_limit === '' || $raw_limit === null || $raw_limit === '-1' ) {
			$opts['limit'] = -1;
		} else {
			$lim           = (int) $raw_limit;
			$opts['limit'] = ( $lim < 1 ) ? -1 : $lim;
		}

		$opts['orderby'] = isset( $opts['orderby'] ) ? $opts['orderby'] : 'sort_order';
		$opts['order']   = isset( $opts['order'] ) && strtoupper( $opts['order'] ) === 'DESC' ? 'DESC' : 'ASC';
		$disp            = isset( $opts['display'] ) ? $opts['display'] : 'grid';
		$opts['display'] = in_array( $disp, array( 'grid', 'carousel' ), true ) ? $disp : 'grid';

		$grid_style = isset( $opts['grid_style'] ) ? $opts['grid_style'] : 'grid';
		if ( $grid_style === 'standard' || $grid_style === 'grid' ) {
			$grid_style = 'equal-height';
		}
		$opts['grid_style'] = $grid_style;

		$opts['columns_desktop']         = max( 1, min( 6, (int) ( isset( $opts['columns_desktop'] ) ? $opts['columns_desktop'] : 4 ) ) );
		$opts['columns_tablet']          = max( 1, min( 4, (int) ( isset( $opts['columns_tablet'] ) ? $opts['columns_tablet'] : 3 ) ) );
		$opts['columns_mobile']          = max( 1, min( 2, (int) ( isset( $opts['columns_mobile'] ) ? $opts['columns_mobile'] : 2 ) ) );
		$opts['column_gap']              = isset( $opts['column_gap'] ) && $opts['column_gap'] !== '' ? $opts['column_gap'] : '2rem';
		$opts['show_company']            = isset( $opts['show_company'] ) ? (bool) $opts['show_company'] : true;
		$opts['show_job_title']          = isset( $opts['show_job_title'] ) ? (bool) $opts['show_job_title'] : true;
		$opts['show_location']           = isset( $opts['show_location'] ) ? (bool) $opts['show_location'] : true;
		$opts['show_user_image']         = isset( $opts['show_user_image'] ) ? (bool) $opts['show_user_image'] : true;
		$opts['show_rating']             = isset( $opts['show_rating'] ) ? (bool) $opts['show_rating'] : true;
		$opts['hide_placeholder_images'] = ! empty( $opts['hide_placeholder_images'] );

		$shape               = isset( $opts['image_shape'] ) ? $opts['image_shape'] : 'circle';
		$opts['image_shape'] = in_array( $shape, array( 'circle', 'rounded', 'square' ), true ) ? $shape : 'circle';
		$pos                 = isset( $opts['image_position'] ) ? $opts['image_position'] : 'top';
		$opts['image_position'] = in_array( $pos, array( 'top', 'left' ), true ) ? $pos : 'top';
		$opts['image_size_rem'] = max( 3, min( 10, (float) ( isset( $opts['image_size_rem'] ) ? $opts['image_size_rem'] : 5 ) ) );

		$opts['carousel_autoplay_ms']    = isset( $opts['carousel_autoplay_ms'] ) ? max( 0, (int) $opts['carousel_autoplay_ms'] ) : 0;
		$opts['carousel_pause_on_hover'] = ! isset( $opts['carousel_pause_on_hover'] ) || (bool) $opts['carousel_pause_on_hover'];
		$opts['carousel_show_arrows']    = ! isset( $opts['carousel_show_arrows'] ) || (bool) $opts['carousel_show_arrows'];
		$opts['carousel_show_dots']      = ! isset( $opts['carousel_show_dots'] ) || (bool) $opts['carousel_show_dots'];

		return $opts;
	}

	private static function parse_category_slugs( $category ) {
		if ( $category === '' || ! is_string( $category ) ) {
			return array();
		}
		$slugs = array_filter( array_map( 'trim', explode( ',', $category ) ) );
		return array_map( 'sanitize_title', $slugs );
	}

	private static function normalize_column_gap_for_css( array $opts ) {
		$column_gap = isset( $opts['column_gap'] ) ? $opts['column_gap'] : '2rem';
		$gap        = ( $column_gap !== '' && $column_gap !== null ) ? $column_gap : '2rem';
		if ( is_numeric( $gap ) ) {
			$gap .= 'rem';
		}
		return $gap;
	}

	private static function get_fp_grid_style_attr( array $opts ) {
		$gap = self::normalize_column_gap_for_css( $opts );
		return sprintf(
			'--fp-grid-cols-desktop: %d; --fp-grid-cols-tablet: %d; --fp-grid-cols-mobile: %d; --fp-grid-gap: %s;',
			isset( $opts['columns_desktop'] ) ? (int) $opts['columns_desktop'] : 4,
			isset( $opts['columns_tablet'] ) ? (int) $opts['columns_tablet'] : 3,
			isset( $opts['columns_mobile'] ) ? (int) $opts['columns_mobile'] : 2,
			esc_attr( $gap )
		);
	}

	public static function get_carousel_slides_gap_style( array $opts ) {
		return '--fp-carousel-slide-gap: ' . esc_attr( self::normalize_column_gap_for_css( $opts ) ) . ';';
	}

	private static function html_attrs( array $attrs ) {
		$parts = array();
		foreach ( $attrs as $name => $value ) {
			if ( $value === null || $value === '' ) {
				continue;
			}
			$parts[] = sprintf( '%s="%s"', esc_attr( (string) $name ), esc_attr( (string) $value ) );
		}
		return $parts ? ' ' . implode( ' ', $parts ) : '';
	}

	private static function build_grid_html( $class, $style, $content, array $attrs = array() ) {
		$class_attr = 'fp-grid' . ( $class ? ' ' . esc_attr( $class ) : '' );
		$style_attr = $style ? ' style="' . esc_attr( $style ) . '"' : '';
		return sprintf(
			'<div class="%1$s"%2$s%3$s>%4$s</div>',
			$class_attr,
			$style_attr,
			self::html_attrs( $attrs ),
			$content
		);
	}

	private static function build_carousel_html( array $args ) {
		$slides_content     = isset( $args['slides_content'] ) ? (string) $args['slides_content'] : '';
		$carousel_id        = isset( $args['carousel_id'] ) ? sanitize_html_class( (string) $args['carousel_id'] ) : 'fp-carousel';
		$autoplay_ms        = isset( $args['autoplay_ms'] ) ? max( 0, (int) $args['autoplay_ms'] ) : 0;
		$pause_on_hover     = ! isset( $args['pause_on_hover'] ) || (bool) $args['pause_on_hover'];
		$show_arrows        = ! isset( $args['show_arrows'] ) || (bool) $args['show_arrows'];
		$show_dots          = ! isset( $args['show_dots'] ) || (bool) $args['show_dots'];
		$label              = isset( $args['label'] ) ? (string) $args['label'] : __( 'Carousel', 'firmpilot-reviews' );
		$slides_track_style = isset( $args['slides_track_style'] ) ? trim( (string) $args['slides_track_style'] ) : '';
		$slide_count        = isset( $args['slide_count'] ) ? max( 0, (int) $args['slide_count'] ) : 0;

		$data_attrs = self::html_attrs(
			array(
				'data-fp-carousel'      => '1',
				'data-autoplay-ms'      => (string) $autoplay_ms,
				'data-pause-on-hover'   => $pause_on_hover ? '1' : '0',
			)
		);

		$track_style = $slides_track_style !== '' ? ' style="' . esc_attr( $slides_track_style ) . '"' : '';

		ob_start();
		?>
<div
	class="fp-carousel"
	id="<?php echo esc_attr( $carousel_id ); ?>-root"
	role="region"
	aria-roledescription="carousel"
	aria-label="<?php echo esc_attr( $label ); ?>"
	tabindex="0"
	<?php echo $data_attrs; ?>
>
	<?php if ( $show_arrows ) : ?>
		<button type="button" class="fp-carousel__arrow fp-carousel__arrow--prev" aria-label="<?php esc_attr_e( 'Previous slide', 'firmpilot-reviews' ); ?>" data-fp-carousel-prev></button>
	<?php endif; ?>
	<div class="fp-carousel__viewport">
		<ul class="fp-carousel__slides" id="<?php echo esc_attr( $carousel_id ); ?>" aria-live="polite"<?php echo $track_style; ?>>
			<?php echo $slides_content; ?>
		</ul>
	</div>
	<?php if ( $show_arrows ) : ?>
		<button type="button" class="fp-carousel__arrow fp-carousel__arrow--next" aria-label="<?php esc_attr_e( 'Next slide', 'firmpilot-reviews' ); ?>" data-fp-carousel-next></button>
	<?php endif; ?>
	<?php if ( $show_dots && $slide_count > 1 ) : ?>
		<div class="fp-carousel__dots" data-fp-carousel-dots role="tablist" aria-label="<?php esc_attr_e( 'Slide navigation', 'firmpilot-reviews' ); ?>">
			<?php for ( $dot_i = 0; $dot_i < $slide_count; $dot_i++ ) : ?>
				<?php
				$is_active = ( 0 === $dot_i );
				$dot_label = sprintf(
					/* translators: 1: slide number, 2: total slides */
					__( 'Slide %1$d of %2$d', 'firmpilot-reviews' ),
					$dot_i + 1,
					$slide_count
				);
				?>
				<button
					type="button"
					class="fp-carousel__dot<?php echo $is_active ? ' fp-carousel__dot--active' : ''; ?>"
					role="tab"
					aria-label="<?php echo esc_attr( $dot_label ); ?>"
					aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
					aria-controls="<?php echo esc_attr( $carousel_id . '-slide-' . $dot_i ); ?>"
					tabindex="<?php echo esc_attr( $is_active ? '0' : '-1' ); ?>"
					data-slide-index="<?php echo esc_attr( (string) $dot_i ); ?>"
				></button>
			<?php endfor; ?>
		</div>
	<?php endif; ?>
</div>
		<?php
		return (string) ob_get_clean();
	}

	private static function build_avatar_html( array $args ) {
		$attachment_id   = isset( $args['attachment_id'] ) ? (int) $args['attachment_id'] : 0;
		$alt             = isset( $args['alt'] ) ? (string) $args['alt'] : '';
		$name            = isset( $args['name'] ) ? (string) $args['name'] : '';
		$size            = isset( $args['size'] ) && $args['size'] !== '' ? (string) $args['size'] : 'thumbnail';
		$placeholder_url = isset( $args['placeholder_url'] ) ? esc_url_raw( (string) $args['placeholder_url'] ) : '';
		$placeholder_alt = isset( $args['placeholder_alt'] ) ? (string) $args['placeholder_alt'] : '';
		$shape           = isset( $args['shape'] ) ? (string) $args['shape'] : 'circle';
		$size_rem        = isset( $args['size_rem'] ) ? max( 3, min( 10, (float) $args['size_rem'] ) ) : null;

		$inner = '';
		if ( $attachment_id > 0 ) {
			$inner = wp_get_attachment_image(
				$attachment_id,
				$size,
				false,
				array(
					'alt' => $alt !== '' ? $alt : $name,
				)
			);
		}

		if ( $inner === '' && $placeholder_url !== '' ) {
			$img_alt = $placeholder_alt !== '' ? $placeholder_alt : ( $alt !== '' ? $alt : $name );
			$inner   = '<img src="' . esc_url( $placeholder_url ) . '" alt="' . esc_attr( $img_alt ) . '" loading="lazy" decoding="async" width="512" height="512" />';
		}

		if ( $inner === '' && $name !== '' ) {
			$parts    = preg_split( '/\s+/u', trim( $name ), -1, PREG_SPLIT_NO_EMPTY );
			$initials = '';
			if ( ! empty( $parts ) ) {
				$initials .= mb_strtoupper( mb_substr( $parts[0], 0, 1 ) );
				if ( isset( $parts[1] ) ) {
					$initials .= mb_strtoupper( mb_substr( $parts[1], 0, 1 ) );
				}
			}
			if ( $initials !== '' ) {
				$inner = '<span class="fp-avatar__initials" aria-hidden="true">' . esc_html( $initials ) . '</span>';
			}
		}

		if ( $inner === '' ) {
			return '';
		}

		$shape_class = 'fp-avatar--circle';
		if ( 'rounded' === $shape ) {
			$shape_class = 'fp-avatar--rounded';
		} elseif ( 'square' === $shape ) {
			$shape_class = 'fp-avatar--square';
		}

		$inline_style = '';
		if ( null !== $size_rem ) {
			$inline_style = '--fp-avatar-size: ' . max( 3, min( 10, $size_rem ) ) . 'rem;';
		}

		$uses_initials = ( $attachment_id <= 0 && $placeholder_url === '' && strpos( $inner, 'fp-avatar__initials' ) !== false );
		$label_name    = $alt !== '' ? $alt : $name;
		$aria          = ( $uses_initials && $label_name !== '' ) ? self::html_attrs(
			array(
				'role'       => 'img',
				'aria-label' => $label_name,
			)
		) : '';

		$style_attr = $inline_style !== '' ? ' style="' . esc_attr( $inline_style ) . '"' : '';

		return '<div class="fp-avatar ' . esc_attr( $shape_class ) . '"' . $style_attr . $aria . '>' . $inner . '</div>';
	}

	private static function build_star_rating_html( array $args ) {
		$rating    = isset( $args['rating'] ) ? (int) $args['rating'] : 0;
		$max       = isset( $args['max'] ) ? max( 1, min( 10, (int) $args['max'] ) ) : 5;
		$rating    = max( 0, min( $max, $rating ) );
		$display   = isset( $args['display'] ) ? (string) $args['display'] : 'repeat';
		$label     = isset( $args['label'] ) ? (string) $args['label'] : '';
		$icon_size = isset( $args['icon_size'] ) && $args['icon_size'] !== '' ? (string) $args['icon_size'] : '24px';

		if ( $rating < 1 && 'repeat' === $display ) {
			return '';
		}

		$aria = $label !== '' ? $label : sprintf(
			/* translators: 1: rating value, 2: maximum rating */
			__( '%1$d of %2$d stars', 'firmpilot-reviews' ),
			$rating,
			$max
		);

		$stars = '';
		if ( 'out_of' === $display ) {
			for ( $i = 1; $i <= $max; $i++ ) {
				$class  = $i <= $rating ? 'fp-rating__star--filled' : 'fp-rating__star--empty';
				$stars .= '<span class="' . esc_attr( $class ) . '" aria-hidden="true">' . fp_reviews_kses_inline_svg( fp_reviews_icon_star() ) . '</span>';
			}
		} else {
			for ( $i = 0; $i < $rating; $i++ ) {
				$stars .= '<span class="fp-rating__star--filled" aria-hidden="true">' . fp_reviews_kses_inline_svg( fp_reviews_icon_star() ) . '</span>';
			}
		}

		return sprintf(
			'<div class="fp-rating" role="img" aria-label="%1$s"><span class="fp-rating__stars" style="--fp-rating-icon-size: %2$s;" aria-hidden="true">%3$s</span></div>',
			esc_attr( $aria ),
			esc_attr( $icon_size ),
			$stars
		);
	}

	private static function build_card_article_html( $inner, array $attrs = array() ) {
		return sprintf(
			'<article class="fp-card"%1$s><div class="fp-card__body">%2$s</div></article>',
			self::html_attrs( $attrs ),
			$inner
		);
	}

	private static function build_empty_state_html( array $args ) {
		$title = isset( $args['title'] ) ? (string) $args['title'] : '';
		$icon  = isset( $args['icon'] ) ? (string) $args['icon'] : fp_reviews_empty_state_icon_html();
		$class = isset( $args['class'] ) ? trim( (string) $args['class'] ) : '';
		$id    = isset( $args['id'] ) ? (string) $args['id'] : '';
		$attrs = isset( $args['attrs'] ) && is_array( $args['attrs'] ) ? $args['attrs'] : array();

		if ( $id !== '' ) {
			$attrs['id'] = $id;
		}

		$root_class = 'fp-empty-state' . ( $class !== '' ? ' ' . $class : '' );

		return sprintf(
			'<div class="%1$s"%2$s>%3$s<span class="fp-empty-state__title">%4$s</span></div>',
			esc_attr( $root_class ),
			self::html_attrs( $attrs ),
			$icon,
			esc_html( $title )
		);
	}

	private static function build_reviews_section_html( $section_class, $inner_markup, $schema_markup ) {
		$html  = sprintf(
			'<section class="%1$s" data-firmpilot-plugin="reviews" data-firmpilot-block="reviews" aria-label="%2$s">%3$s</section>',
			esc_attr( $section_class ),
			esc_attr__( 'Reviews', 'firmpilot-reviews' ),
			$inner_markup
		);
		if ( $schema_markup !== '' ) {
			$html .= $schema_markup;
		}
		return $html;
	}

	private static function build_reviews_display_markup( array $opts, array $items, $display, $grid_class, $grid_style_attr, $carousel_id ) {
		$list_attrs = array(
			'role'       => 'list',
			'aria-label' => __( 'Review list', 'firmpilot-reviews' ),
		);

		if ( 'carousel' === $display ) {
			$carousel_per_slide   = max( 1, min( 6, (int) $opts['columns_desktop'] ) );
			$carousel_slide_count = empty( $items ) ? 0 : (int) ceil( count( $items ) / $carousel_per_slide );

			return self::build_carousel_html(
				array(
					'slides_content'     => self::build_carousel_slides_html( $items, $opts, $carousel_id ),
					'slides_track_style' => self::get_carousel_slides_gap_style( $opts ),
					'carousel_id'        => $carousel_id,
					'autoplay_ms'        => (int) $opts['carousel_autoplay_ms'],
					'pause_on_hover'     => ! empty( $opts['carousel_pause_on_hover'] ),
					'show_arrows'        => ! empty( $opts['carousel_show_arrows'] ),
					'show_dots'          => ! empty( $opts['carousel_show_dots'] ) && $carousel_slide_count > 1,
					'slide_count'        => $carousel_slide_count,
					'label'              => __( 'Reviews carousel', 'firmpilot-reviews' ),
				)
			);
		}

		return self::build_grid_html(
			$grid_class,
			$grid_style_attr,
			self::build_review_cards_html( $items, $opts ),
			$list_attrs
		);
	}

	public static function render_reviews( $atts = array() ) {
		$opts           = self::sanitize_options( $atts );
		$category_slugs = $opts['category_slugs'];
		$random         = $opts['orderby'] === 'random';

		$items = FP_Reviews_DB::get_all(
			$category_slugs,
			$random ? 'created_at' : $opts['orderby'],
			$opts['order'],
			$opts['limit'] <= 0 ? -1 : $opts['limit'],
			$random
		);

		if ( empty( $items ) ) {
			return fp_reviews_wrap_html(
				self::build_empty_state_html(
					array(
						'title' => __( 'No reviews found.', 'firmpilot-reviews' ),
						'attrs' => array(
							'role'      => 'status',
							'aria-live' => 'polite',
						),
					)
				)
			);
		}

		$carousel_id     = 'fp-reviews-slides-' . uniqid();
		$grid_class      = 'fp-grid--' . ( $opts['grid_style'] === 'masonry' ? 'masonry' : 'equal-height' );
		$grid_style_attr = self::get_fp_grid_style_attr( $opts );
		$section_class   = 'fp-reviews' . ( 'carousel' === $opts['display'] ? ' fp-reviews--layout-carousel' : '' );

		$inner_markup = self::build_reviews_display_markup(
			$opts,
			$items,
			$opts['display'],
			$grid_class,
			$grid_style_attr,
			$carousel_id
		);

		$html = self::build_reviews_section_html(
			$section_class,
			$inner_markup,
			FP_Reviews_Schema::render_script( $items )
		);

		return fp_reviews_wrap_html( trim( $html ) );
	}

	public static function build_review_cards_html( array $items, array $opts ) {
		$html = '';
		foreach ( $items as $item ) {
			$html .= self::build_review_card_html( $item, $opts );
		}
		return $html;
	}

	public static function build_carousel_slides_html( array $items, array $opts, $carousel_id = '' ) {
		$per_slide   = max( 1, min( 6, (int) $opts['columns_desktop'] ) );
		$chunks      = array_chunk( $items, $per_slide );
		$style       = self::get_fp_grid_style_attr( $opts );
		$slide_total = count( $chunks );
		$list_attrs  = array(
			'role'       => 'list',
			'aria-label' => __( 'Review list', 'firmpilot-reviews' ),
		);

		$slides = '';
		foreach ( $chunks as $slide_index => $chunk ) {
			$slide_id    = $carousel_id !== '' ? $carousel_id . '-slide-' . $slide_index : 'fp-carousel-slide-' . wp_unique_id();
			$slide_label = sprintf(
				/* translators: 1: slide number, 2: total slides */
				__( 'Slide %1$d of %2$d', 'firmpilot-reviews' ),
				$slide_index + 1,
				$slide_total
			);
			$hidden      = ( 0 === $slide_index ) ? '' : ' aria-hidden="true"';

			$slides .= sprintf(
				'<li class="fp-carousel__slide-item" id="%1$s" role="group" aria-roledescription="slide" aria-label="%2$s"%3$s>',
				esc_attr( $slide_id ),
				esc_attr( $slide_label ),
				$hidden
			);
			$slides .= self::build_grid_html( 'fp-grid--equal-height', $style, self::build_review_cards_html( $chunk, $opts ), $list_attrs );
			$slides .= '</li>';
		}
		return $slides;
	}

	public static function build_review_card_html( $row, array $opts ) {
		if ( ! is_object( $row ) ) {
			return '';
		}

		$show_company    = ! empty( $opts['show_company'] );
		$show_job_title  = ! empty( $opts['show_job_title'] );
		$show_location   = ! empty( $opts['show_location'] );
		$show_user_image = ! isset( $opts['show_user_image'] ) || (bool) $opts['show_user_image'];
		$show_rating     = ! isset( $opts['show_rating'] ) || (bool) $opts['show_rating'];
		$hide_ph         = ! empty( $opts['hide_placeholder_images'] );
		$has_image       = isset( $row->image_id ) && (int) $row->image_id > 0;

		$title_tag = isset( $opts['title_element'] ) ? strtolower( (string) $opts['title_element'] ) : 'h3';
		if ( ! in_array( $title_tag, self::TITLE_ELEMENTS, true ) ) {
			$title_tag = 'h3';
		}

		$shape = isset( $opts['image_shape'] ) ? $opts['image_shape'] : 'circle';
		if ( ! in_array( $shape, array( 'circle', 'rounded', 'square' ), true ) ) {
			$shape = 'circle';
		}

		$position = isset( $opts['image_position'] ) && 'left' === $opts['image_position'] ? 'left' : 'top';
		$size_rem = max( 3, min( 10, (float) ( isset( $opts['image_size_rem'] ) ? $opts['image_size_rem'] : 5 ) ) );

		$display_name = isset( $row->display_name ) ? (string) $row->display_name : (string) $row->author_name;
		$review_id    = ! empty( $row->id ) ? 'fp-review-' . (int) $row->id : 'fp-review-' . wp_unique_id();
		$author_id    = $review_id . '-author';
		$rating_id    = $review_id . '-rating';
		$pos_class    = 'left' === $position ? 'fp-review--left' : 'fp-review--top';

		$avatar_html = '';
		if ( $show_user_image && ( $has_image || ! $hide_ph ) ) {
			$avatar_html = self::build_avatar_html(
				array(
					'attachment_id'   => (int) $row->image_id,
					'alt'             => $display_name,
					'name'            => $display_name,
					'placeholder_url' => self::placeholder_image_url(),
					'placeholder_alt' => __( 'No user image', 'firmpilot-reviews' ),
					'shape'           => $shape,
					'size_rem'        => $size_rem,
				)
			);
		}

		$rating_html = '';
		if ( $show_rating && (int) $row->rating > 0 ) {
			$rating_html = self::build_star_rating_html(
				array(
					'rating'    => (int) $row->rating,
					'max'       => 5,
					'display'   => 'out_of',
					'icon_size' => '1.25rem',
					'label'     => sprintf(
						/* translators: 1: rating value, 2: maximum rating */
						__( '%1$d of %2$d stars', 'firmpilot-reviews' ),
						(int) $row->rating,
						5
					),
				)
			);
		}

		$quote = isset( $row->quote ) ? $row->quote : '';

		$card_attrs = array(
			'id'              => $review_id,
			'aria-labelledby' => $author_id,
		);
		if ( ! isset( $opts['list_context'] ) || (bool) $opts['list_context'] ) {
			$card_attrs['role'] = 'listitem';
		}
		if ( $rating_html !== '' ) {
			$card_attrs['aria-describedby'] = $rating_id;
		}

		ob_start();
		?>
		<div class="fp-review <?php echo esc_attr( $pos_class ); ?>">
			<?php if ( $avatar_html !== '' ) : ?>
				<?php echo $avatar_html; ?>
			<?php endif; ?>
			<div class="fp-review__content">
				<?php if ( $rating_html !== '' ) : ?>
					<div class="fp-review__rating" id="<?php echo esc_attr( $rating_id ); ?>"><?php echo $rating_html; ?></div>
				<?php endif; ?>
				<blockquote class="fp-review__quote">
					<div class="fp-review__quote-body">
						<?php
						$GLOBALS['FP_Reviews_skip_content_filter'] = true;
						try {
							echo apply_filters( 'the_content', $quote );
						} finally {
							unset( $GLOBALS['FP_Reviews_skip_content_filter'] );
						}
						?>
					</div>
					<footer class="fp-review__meta">
						<<?php echo esc_attr( $title_tag ); ?> class="fp-title fp-review__author" id="<?php echo esc_attr( $author_id ); ?>">
							<?php echo esc_html( $display_name ); ?>
						</<?php echo esc_attr( $title_tag ); ?>>
						<?php if ( ( $show_job_title && ! empty( $row->job_title ) ) || ( $show_company && ! empty( $row->company ) ) ) : ?>
							<p class="fp-review__position">
								<?php if ( $show_job_title && ! empty( $row->job_title ) ) : ?>
									<span class="fp-review__job-title"><?php echo esc_html( $row->job_title ); ?></span>
								<?php endif; ?>
								<?php if ( $show_company && ! empty( $row->company ) && $show_job_title && ! empty( $row->job_title ) ) : ?>
									<span class="fp-review__at"><?php esc_html_e( 'at', 'firmpilot-reviews' ); ?></span>
								<?php endif; ?>
								<?php if ( $show_company && ! empty( $row->company ) ) : ?>
									<span class="fp-review__company"><?php echo esc_html( $row->company ); ?></span>
								<?php endif; ?>
							</p>
						<?php endif; ?>
						<?php if ( $show_location && ! empty( $row->location ) ) : ?>
							<p class="fp-review__location"><?php echo esc_html( $row->location ); ?></p>
						<?php endif; ?>
					</footer>
				</blockquote>
			</div>
		</div>
		<?php
		return self::build_card_article_html( (string) ob_get_clean(), $card_attrs );
	}

	public static function get_single_review_html( $post_id ) {
		$row = FP_Reviews_DB::get( (int) $post_id );
		if ( ! $row ) {
			return '';
		}
		$opts = self::sanitize_options(
			array(
				'title_element'   => 'h3',
				'image_shape'     => 'circle',
				'image_position'  => 'top',
				'image_size_rem'  => 5,
				'show_user_image' => true,
				'show_rating'     => true,
				'show_company'    => true,
				'show_job_title'  => true,
				'show_location'   => true,
				'list_context'    => false,
			)
		);
		return self::build_review_card_html( $row, $opts );
	}
}
