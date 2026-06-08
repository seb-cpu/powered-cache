<?php
/**
 * Deterministic rules table producing recommended_changes[] with NO key.
 *
 * Emits the SAME shape as the LLM output (minus prose richness) so the feature
 * exists for everyone; the LLM is a pure upgrade layer over this floor. The output
 * runs through the same ValidationGate and the same apply path.
 *
 * @package SwiftPress
 */

namespace SwiftPress\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RulesFallback
 */
class RulesFallback {

	/**
	 * Constructor (placeholder).
	 */
	public function __construct() {
	}

	/**
	 * Return an instance of the current class, create one if it doesn't exist.
	 *
	 * @return RulesFallback
	 */
	public static function factory() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Evaluate metrics + settings into a normalized payload (pre-gate).
	 *
	 * @param array $metrics  Normalized PSI metrics (may be empty if PSI failed).
	 * @param array $settings Current settings.
	 *
	 * @return array{summary:string,overall_assessment:string,findings:array,recommended_changes:array}
	 */
	public function evaluate( array $metrics, array $settings ) {
		$opp      = isset( $metrics['opportunities'] ) && is_array( $metrics['opportunities'] ) ? $metrics['opportunities'] : [];
		$lab      = isset( $metrics['lab'] ) && is_array( $metrics['lab'] ) ? $metrics['lab'] : [];
		$findings = [];
		$changes  = [];

		// TTFB high + page cache off -> enable page cache.
		if ( isset( $lab['TTFB_ms'] ) && $lab['TTFB_ms'] > 600 && empty( $settings['enable_page_cache'] ) ) {
			$findings[] = $this->finding(
				__( 'Slow server response time', 'swiftpress' ),
				sprintf( 'TTFB %dms', (int) $lab['TTFB_ms'] ),
				'high',
				__( 'The server takes a while to respond. Page caching serves a stored copy and skips that work.', 'swiftpress' )
			);
			$changes[] = $this->change( 'enable_page_cache', true, __( 'High TTFB; page caching avoids re-rendering on every request.', 'swiftpress' ), 0.95 );
		}

		// Unminified CSS + minify off.
		if ( ! empty( $opp['unminified_css'] ) && empty( $settings['minify_css'] ) ) {
			$findings[] = $this->finding(
				__( 'Unminified CSS', 'swiftpress' ),
				__( 'render-blocking CSS not minified', 'swiftpress' ),
				'medium',
				__( 'CSS files are larger than they need to be. Minifying shrinks them.', 'swiftpress' )
			);
			$changes[] = $this->change( 'minify_css', true, __( 'CSS is not minified.', 'swiftpress' ), 0.9 );
		}

		// Unminified JS + minify off.
		if ( ! empty( $opp['unminified_js'] ) && empty( $settings['minify_js'] ) ) {
			$findings[] = $this->finding(
				__( 'Unminified JavaScript', 'swiftpress' ),
				__( 'JavaScript not minified', 'swiftpress' ),
				'medium',
				__( 'JavaScript files are larger than necessary. Minifying shrinks them.', 'swiftpress' )
			);
			$changes[] = $this->change( 'minify_js', true, __( 'JavaScript is not minified.', 'swiftpress' ), 0.9 );
		}

		// Render-blocking resources + defer off.
		if ( isset( $opp['render_blocking_count'] ) && $opp['render_blocking_count'] >= 4 && empty( $settings['js_defer'] ) ) {
			$findings[] = $this->finding(
				__( 'Render-blocking scripts', 'swiftpress' ),
				sprintf(
					/* translators: %d: number of render-blocking resources. */
					__( '%d render-blocking resources', 'swiftpress' ),
					(int) $opp['render_blocking_count']
				),
				'high',
				__( 'Several scripts block the page from showing. Deferring them lets content appear sooner.', 'swiftpress' )
			);
			$changes[] = $this->change( 'js_defer', true, __( 'Multiple render-blocking scripts detected.', 'swiftpress' ), 0.8 );
		}

		// Large unused CSS -> remove_unused_css (suggest-only via gate engine gating).
		if ( isset( $opp['unused_css_bytes'] ) && $opp['unused_css_bytes'] > 50000 && empty( $settings['remove_unused_css'] ) ) {
			$findings[] = $this->finding(
				__( 'Large amount of unused CSS', 'swiftpress' ),
				sprintf( 'unused CSS %dKB', (int) round( $opp['unused_css_bytes'] / 1024 ) ),
				'medium',
				__( 'A lot of CSS is downloaded but not used on this page.', 'swiftpress' )
			);
			$changes[] = $this->change( 'remove_unused_css', true, __( 'Significant unused CSS detected.', 'swiftpress' ), 0.7 );
		}

		// Font-display flagged -> font optimization + display swap.
		if ( ! empty( $opp['font_display'] ) && empty( $settings['font_display_swap'] ) ) {
			$findings[] = $this->finding(
				__( 'Fonts block text rendering', 'swiftpress' ),
				__( 'font-display not set to swap', 'swiftpress' ),
				'medium',
				__( 'Text stays invisible while web fonts load. "Swap" shows text immediately.', 'swiftpress' )
			);
			$changes[] = $this->change( 'enable_font_optimization', true, __( 'Fonts block text rendering.', 'swiftpress' ), 0.8 );
			$changes[] = $this->change( 'font_display_swap', true, __( 'Use font-display: swap to show text sooner.', 'swiftpress' ), 0.8 );
		}

		// Next-gen images available + image optimization off.
		if ( isset( $opp['next_gen_images_bytes'] ) && $opp['next_gen_images_bytes'] > 0 && empty( $settings['enable_image_optimization'] ) ) {
			$findings[] = $this->finding(
				__( 'Images can use next-gen formats', 'swiftpress' ),
				sprintf( 'potential image savings %dKB', (int) round( $opp['next_gen_images_bytes'] / 1024 ) ),
				'medium',
				__( 'Images could be served in smaller modern formats like WebP.', 'swiftpress' )
			);
			$changes[] = $this->change( 'enable_image_optimization', true, __( 'Images can be served in next-gen formats.', 'swiftpress' ), 0.8 );
			$changes[] = $this->change( 'image_optimizer_preferred_format', 'webp', __( 'WebP offers broad support and good savings.', 'swiftpress' ), 0.75 );
		}

		// Missing image dimensions -> add them (helps CLS).
		if ( ! empty( $opp['image_dimensions'] ) && empty( $settings['add_missing_image_dimensions'] ) ) {
			$findings[] = $this->finding(
				__( 'Images missing dimensions', 'swiftpress' ),
				__( 'unsized images cause layout shift', 'swiftpress' ),
				'low',
				__( 'Images without width/height can shift the layout as they load.', 'swiftpress' )
			);
			$changes[] = $this->change( 'add_missing_image_dimensions', true, __( 'Unsized images detected.', 'swiftpress' ), 0.85 );
		}

		// Gzip off -> enable.
		if ( empty( $settings['gzip_compression'] ) ) {
			$changes[] = $this->change( 'gzip_compression', true, __( 'Compression reduces transfer size for text assets.', 'swiftpress' ), 0.7 );
		}

		$assessment = $this->assess( $metrics );

		return [
			'summary'             => __( 'AI explanation unavailable — showing standard recommendations based on your performance metrics.', 'swiftpress' ),
			'overall_assessment'  => $assessment,
			'findings'            => $findings,
			'recommended_changes' => $changes,
		];
	}

	/**
	 * Build a finding record.
	 *
	 * @param string $issue       Issue title.
	 * @param string $evidence    Evidence metric.
	 * @param string $severity    Severity.
	 * @param string $explanation Plain explanation.
	 *
	 * @return array
	 */
	private function finding( $issue, $evidence, $severity, $explanation ) {
		return [
			'issue'             => $issue,
			'evidence_metric'   => $evidence,
			'severity'          => $severity,
			'plain_explanation' => $explanation,
		];
	}

	/**
	 * Build a recommended-change record (same shape as LLM output).
	 *
	 * @param string $key        Setting key.
	 * @param mixed  $to         Target value.
	 * @param string $why        Reason.
	 * @param float  $confidence Confidence 0..1.
	 *
	 * @return array
	 */
	private function change( $key, $to, $why, $confidence ) {
		return [
			'setting_key' => $key,
			'to'          => $to,
			'why'         => $why,
			'risk'        => 'low',
			'confidence'  => $confidence,
		];
	}

	/**
	 * Derive a coarse overall assessment from the performance score.
	 *
	 * @param array $metrics Metrics.
	 *
	 * @return string good|needs_work|poor
	 */
	private function assess( array $metrics ) {
		if ( ! isset( $metrics['perf_score'] ) || null === $metrics['perf_score'] ) {
			return 'needs_work';
		}

		$score = (int) $metrics['perf_score'];

		if ( $score >= 90 ) {
			return 'good';
		}
		if ( $score >= 50 ) {
			return 'needs_work';
		}

		return 'poor';
	}
}
