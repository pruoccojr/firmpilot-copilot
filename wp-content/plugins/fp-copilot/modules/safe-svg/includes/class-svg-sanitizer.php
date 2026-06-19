<?php
/**
 * Whitelist-based SVG sanitizer.
 *
 * @package FPCopilot
 */

defined( 'ABSPATH' ) || exit;

/**
 * Sanitizes SVG markup by removing dangerous elements, attributes, and URLs.
 */
final class FP_Copilot_Svg_Sanitizer {

	/**
	 * Allowed SVG elements.
	 *
	 * @var array<string, true>
	 */
	private array $allowed_tags = array(
		'svg'            => true,
		'g'              => true,
		'path'           => true,
		'rect'           => true,
		'circle'         => true,
		'ellipse'        => true,
		'line'           => true,
		'polyline'       => true,
		'polygon'        => true,
		'defs'           => true,
		'lineargradient' => true,
		'radialgradient' => true,
		'stop'           => true,
		'clippath'       => true,
		'mask'           => true,
		'pattern'        => true,
		'symbol'         => true,
		'use'            => true,
		'title'          => true,
		'desc'           => true,
		'text'           => true,
		'tspan'          => true,
		'textpath'       => true,
		'image'          => true,
		'filter'         => true,
		'fegaussianblur' => true,
		'feoffset'       => true,
		'fecolormatrix'  => true,
		'feblend'        => true,
		'fecomposite'    => true,
		'femerge'        => true,
		'femergenode'    => true,
		'view'           => true,
	);

	/**
	 * Globally allowed attributes.
	 *
	 * @var array<string, true>
	 */
	private array $allowed_attrs = array(
		'id'                 => true,
		'class'              => true,
		'style'              => true,
		'transform'          => true,
		'fill'               => true,
		'fill-opacity'       => true,
		'fill-rule'          => true,
		'stroke'             => true,
		'stroke-width'       => true,
		'stroke-linecap'     => true,
		'stroke-linejoin'    => true,
		'stroke-miterlimit'  => true,
		'stroke-opacity'     => true,
		'stroke-dasharray'   => true,
		'stroke-dashoffset'  => true,
		'opacity'            => true,
		'clip-path'          => true,
		'clip-rule'          => true,
		'mask'               => true,
		'viewbox'            => true,
		'width'              => true,
		'height'             => true,
		'x'                  => true,
		'y'                  => true,
		'x1'                 => true,
		'y1'                 => true,
		'x2'                 => true,
		'y2'                 => true,
		'cx'                 => true,
		'cy'                 => true,
		'r'                  => true,
		'rx'                 => true,
		'ry'                 => true,
		'd'                  => true,
		'points'             => true,
		'offset'             => true,
		'stop-color'         => true,
		'stop-opacity'       => true,
		'gradientunits'      => true,
		'gradienttransform'  => true,
		'spreadmethod'       => true,
		'patternunits'       => true,
		'patterntransform'   => true,
		'patterncontentunits'=> true,
		'preserveaspectratio'=> true,
		'xmlns'              => true,
		'xmlns:xlink'        => true,
		'xml:space'          => true,
		'version'            => true,
		'role'               => true,
		'aria-hidden'        => true,
		'aria-label'         => true,
		'focusable'          => true,
		'font-family'        => true,
		'font-size'          => true,
		'font-weight'        => true,
		'text-anchor'        => true,
		'dominant-baseline'  => true,
		'alignment-baseline' => true,
		'letter-spacing'     => true,
		'word-spacing'       => true,
		'display'            => true,
		'visibility'         => true,
		'color'              => true,
		'filter'             => true,
		'stddeviation'       => true,
		'mode'               => true,
		'in'                 => true,
		'in2'                => true,
		'result'             => true,
		'operator'           => true,
		'values'             => true,
		'type'               => true,
	);

	/**
	 * Attributes that may contain references and need URL validation.
	 *
	 * @var array<string, true>
	 */
	private array $reference_attrs = array(
		'href'       => true,
		'xlink:href' => true,
	);

	/**
	 * Sanitize SVG content.
	 *
	 * @param string $content Raw SVG file contents.
	 * @return string|WP_Error Sanitized SVG XML or error.
	 */
	public function sanitize( string $content ) {
		$content = $this->strip_dangerous_markup( $content );

		if ( '' === trim( $content ) ) {
			return new WP_Error( 'fp_copilot_svg_empty', __( 'The SVG file is empty.', 'fp-copilot' ) );
		}

		if ( preg_match( '/<script|foreignobject|handler\s|javascript:|data:text\/html|&#x/i', $content ) ) {
			return new WP_Error( 'fp_copilot_svg_blocked', __( 'The SVG file contains blocked content.', 'fp-copilot' ) );
		}

		$previous = libxml_use_internal_errors( true );

		$document = new DOMDocument();
		$loaded   = $document->loadXML(
			$content,
			LIBXML_NONET | LIBXML_COMPACT
		);

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded ) {
			return new WP_Error( 'fp_copilot_svg_invalid', __( 'The SVG file is not valid XML.', 'fp-copilot' ) );
		}

		$root = $document->documentElement;

		if ( ! $root || 'svg' !== strtolower( $root->tagName ) ) {
			return new WP_Error( 'fp_copilot_svg_root', __( 'The file must contain a root <svg> element.', 'fp-copilot' ) );
		}

		$this->sanitize_node( $document, $root );

		$sanitized = $document->saveXML( $root );

		if ( false === $sanitized ) {
			return new WP_Error( 'fp_copilot_svg_save', __( 'Unable to sanitize the SVG file.', 'fp-copilot' ) );
		}

		return $this->ensure_xml_prefix( $sanitized );
	}

	/**
	 * Remove DOCTYPE, entities, comments, and processing instructions before parsing.
	 */
	private function strip_dangerous_markup( string $content ): string {
		$content = preg_replace( '/<\?xml-stylesheet[^>]*\?>/i', '', $content ) ?? $content;
		$content = preg_replace( '/<!DOCTYPE[^>]*>/i', '', $content ) ?? $content;
		$content = preg_replace( '/<!ENTITY[^>]*>/i', '', $content ) ?? $content;
		$content = preg_replace( '/<\?.*?\?>/s', '', $content ) ?? $content;
		$content = preg_replace( '/<!--.*?-->/s', '', $content ) ?? $content;

		return trim( $content );
	}

	/**
	 * Recursively sanitize a DOM node.
	 */
	private function sanitize_node( DOMDocument $document, DOMNode $node ): void {
		if ( XML_ELEMENT_NODE !== $node->nodeType ) {
			return;
		}

		/** @var DOMElement $element */
		$element  = $node;
		$tag_name = strtolower( $element->tagName );

		if ( ! isset( $this->allowed_tags[ $tag_name ] ) ) {
			$element->parentNode?->removeChild( $element );

			return;
		}

		if ( $element->hasAttributes() ) {
			$to_remove = array();

			foreach ( iterator_to_array( $element->attributes ) as $attribute ) {
				$name  = strtolower( $attribute->nodeName );
				$value = $attribute->nodeValue ?? '';

				if ( $this->is_event_handler( $name ) || $this->is_dangerous_attribute( $name, $value ) ) {
					$to_remove[] = $attribute->nodeName;
					continue;
				}

				if ( ! isset( $this->allowed_attrs[ $name ] ) && ! isset( $this->reference_attrs[ $name ] ) ) {
					$to_remove[] = $attribute->nodeName;
					continue;
				}

				if ( isset( $this->reference_attrs[ $name ] ) && ! $this->is_safe_reference( $value ) ) {
					$to_remove[] = $attribute->nodeName;
					continue;
				}

				if ( 'style' === $name && ! $this->is_safe_style( $value ) ) {
					$to_remove[] = $attribute->nodeName;
				}
			}

			foreach ( $to_remove as $attribute_name ) {
				$element->removeAttribute( $attribute_name );
			}
		}

		for ( $child = $element->firstChild; null !== $child; ) {
			$next = $child->nextSibling;

			if ( XML_ELEMENT_NODE === $child->nodeType ) {
				$this->sanitize_node( $document, $child );
			} elseif ( in_array( $child->nodeType, array( XML_PI_NODE, XML_COMMENT_NODE ), true ) ) {
				$element->removeChild( $child );
			}

			$child = $next;
		}
	}

	/**
	 * Whether an attribute name is an inline event handler.
	 */
	private function is_event_handler( string $name ): bool {
		return str_starts_with( $name, 'on' );
	}

	/**
	 * Whether an attribute or value is inherently dangerous.
	 */
	private function is_dangerous_attribute( string $name, string $value ): bool {
		$lower_value = strtolower( trim( $value ) );

		if ( '' === $lower_value ) {
			return false;
		}

		if ( preg_match( '/^(javascript|vbscript|data:text\/html)/i', $lower_value ) ) {
			return true;
		}

		if ( preg_match( '/url\s*\(\s*(["\']?)(javascript|data:text\/html)/i', $lower_value ) ) {
			return true;
		}

		if ( str_contains( $lower_value, 'base64' ) && str_contains( $lower_value, 'data:' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Allow only same-document fragment references.
	 */
	private function is_safe_reference( string $value ): bool {
		$value = trim( $value );

		if ( '' === $value ) {
			return true;
		}

		if ( str_starts_with( $value, '#' ) ) {
			return (bool) preg_match( '/^#[A-Za-z0-9_\-:.]+$/', $value );
		}

		return false;
	}

	/**
	 * Validate inline style values.
	 */
	private function is_safe_style( string $value ): bool {
		$lower = strtolower( $value );

		$blocked = array(
			'expression',
			'javascript:',
			'vbscript:',
			'behavior:',
			'-moz-binding',
			'@import',
			'@charset',
			'url(',
		);

		foreach ( $blocked as $pattern ) {
			if ( str_contains( $lower, $pattern ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Ensure sanitized output is XML with the SVG namespace.
	 */
	private function ensure_xml_prefix( string $svg ): string {
		if ( ! str_starts_with( trim( $svg ), '<?xml' ) ) {
			$svg = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $svg;
		}

		return $svg;
	}
}
