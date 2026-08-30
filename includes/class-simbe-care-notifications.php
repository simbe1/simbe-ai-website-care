<?php
/**
 * Sends a digest email when a scan finds newly critical plugin or theme issues.
 *
 * @package Simbe_AI_Website_Care
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Simbe_Care_Notifications
 */
class Simbe_Care_Notifications {

	/**
	 * Sends an alert digest if notifications are enabled and new critical
	 * issues were detected since the previous scan.
	 *
	 * @param array|null $previous Previous scan results.
	 * @param array      $current  Current scan results.
	 * @param array      $settings Plugin settings.
	 * @return bool Whether an email was sent.
	 */
	public static function maybe_send( $previous, $current, $settings ) {
		$to = self::recipients( isset( $settings['notify_email'] ) ? $settings['notify_email'] : '' );

		if ( empty( $to ) ) {
			return false;
		}

		if ( ! is_array( $current ) ) {
			return false;
		}

		$new_critical = self::new_critical_items( $previous, $current );

		if ( empty( $new_critical ) ) {
			return false;
		}

		$subject = sprintf(
			/* translators: 1: site name, 2: number of new critical items. */
			__( '[%1$s] Simbe Care: %2$d new critical issue(s) found', 'simbe-ai-website-care' ),
			wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ),
			count( $new_critical )
		);

		$body = self::build_body( $new_critical );

		return wp_mail( $to, $subject, $body );
	}

	/**
	 * Compares two scans and returns items that became critical.
	 *
	 * @param array|null $previous Previous scan results.
	 * @param array      $current  Current scan results.
	 * @return array List of critical item summaries.
	 */
	private static function new_critical_items( $previous, $current ) {
		$items = array();

		foreach ( array( 'plugins', 'themes' ) as $type ) {
			$source = isset( $current[ $type ] ) ? $current[ $type ] : array();
			$before = isset( $previous[ $type ] ) ? $previous[ $type ] : array();

			if ( ! is_array( $source ) ) {
				continue;
			}
			if ( ! is_array( $before ) ) {
				$before = array();
			}

			foreach ( $source as $key => $entry ) {
				if ( ! is_array( $entry ) || empty( $entry['risk'] ) || 'critical' !== $entry['risk'] ) {
					continue;
				}
				if ( isset( $before[ $key ] ) && is_array( $before[ $key ] ) && isset( $before[ $key ]['risk'] ) && 'critical' === $before[ $key ]['risk'] ) {
					continue;
				}

				$items[] = array(
					'type'    => ( 'themes' === $type ) ? __( 'Theme', 'simbe-ai-website-care' ) : __( 'Plugin', 'simbe-ai-website-care' ),
					'kind'    => $type,
					'key'     => $key,
					'name'    => isset( $entry['name'] ) ? $entry['name'] : $key,
					'version' => isset( $entry['version'] ) ? $entry['version'] : '',
					'message' => isset( $entry['messages'][0] ) ? $entry['messages'][0] : __( 'Critical issue detected.', 'simbe-ai-website-care' ),
				);
			}
		}

		return $items;
	}

	/**
	 * Builds the plain-text digest body.
	 *
	 * @param array $items Critical item summaries.
	 * @return string
	 */
	private static function build_body( $items ) {
		$lines = array();

		$lines[] = sprintf(
			/* translators: %s: site URL. */
			__( 'A scheduled scan of %s found the following new critical issues:', 'simbe-ai-website-care' ),
			home_url( '/' )
		);
		$lines[] = '';

		$total = count( $items );
		$items = array_slice( $items, 0, 20 );

		foreach ( $items as $item ) {
			$detail = $item['name'];
			if ( $item['version'] ) {
				$detail .= ' v' . $item['version'];
			}
			$lines[] = '- ' . $item['type'] . ': ' . $detail . ' — ' . $item['message'];
		}

		if ( $total > 20 ) {
			$lines[] = '';
			$lines[] = __( '… and more. See the dashboard for the full report.', 'simbe-ai-website-care' );
		}

		$lines[] = '';
		$lines[] = sprintf(
			/* translators: %s: dashboard URL. */
			__( 'Open Simbe Care: %s', 'simbe-ai-website-care' ),
			admin_url( 'admin.php?page=simbe-care' )
		);

		return implode( "\n", $lines );
	}

	/**
	 * Normalizes a comma-separated list of recipient emails.
	 *
	 * @param string $raw Raw emails.
	 * @return array Validated email addresses.
	 */
	private static function recipients( $raw ) {
		$emails = array_map( 'trim', explode( ',', (string) $raw ) );
		$clean  = array();

		foreach ( $emails as $email ) {
			$email = sanitize_email( $email );
			if ( $email && ! in_array( $email, $clean, true ) ) {
				$clean[] = $email;
			}
		}

		return $clean;
	}
}