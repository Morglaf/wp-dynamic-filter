<?php
/**
 * Convert .po files to WordPress Jed JSON for script translations.
 *
 * @package DynamicFilter
 */

$domain = 'wp-dynamic-filter';
$handle = 'wp-dynamic-filter-editor';
$dir    = dirname( __DIR__ ) . '/languages';

/**
 * Parse a simple .po file into msgid => msgstr map.
 *
 * @param string $po_file Path.
 * @return array<string,string>
 */
function ddf_parse_po( $po_file ) {
	$lines   = file( $po_file, FILE_IGNORE_NEW_LINES );
	$entries = array();
	$msgid   = null;
	$msgstr  = null;
	$in      = null;

	foreach ( $lines as $line ) {
		if ( preg_match( '/^msgid "(.*)"\s*$/', $line, $m ) ) {
			if ( null !== $msgid && null !== $msgstr && '' !== $msgid ) {
				$entries[ stripcslashes( $msgid ) ] = stripcslashes( $msgstr );
			}
			$msgid  = $m[1];
			$msgstr = null;
			$in     = 'id';
			continue;
		}
		if ( preg_match( '/^msgstr "(.*)"\s*$/', $line, $m ) ) {
			$msgstr = $m[1];
			$in     = 'str';
			continue;
		}
		if ( preg_match( '/^"(.*)"\s*$/', $line, $m ) ) {
			if ( 'id' === $in ) {
				$msgid .= $m[1];
			}
			if ( 'str' === $in ) {
				$msgstr .= $m[1];
			}
		}
	}
	if ( null !== $msgid && null !== $msgstr && '' !== $msgid ) {
		$entries[ stripcslashes( $msgid ) ] = stripcslashes( $msgstr );
	}
	return $entries;
}

foreach ( array( 'fr_FR', 'es_ES', 'de_DE' ) as $locale ) {
	$po = "$dir/$domain-$locale.po";
	if ( ! file_exists( $po ) ) {
		fwrite( STDERR, "Missing $po\n" );
		continue;
	}
	$map = ddf_parse_po( $po );
	$jed = array(
		'domain'      => $domain,
		'locale_data' => array(
			$domain => array_merge(
				array(
					'' => array(
						'domain'       => $domain,
						'lang'         => $locale,
						'plural-forms' => 'nplurals=2; plural=(n != 1);',
					),
				),
				array_map(
					static function ( $v ) {
						return array( $v );
					},
					$map
				)
			),
		),
	);
	$out = "$dir/$domain-$locale-$handle.json";
	file_put_contents( $out, wp_json_encode_fallback( $jed ) );
	echo "Wrote $out\n";
}

/**
 * json_encode helper without depending on WP.
 *
 * @param mixed $data Data.
 * @return string
 */
function wp_json_encode_fallback( $data ) {
	return json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
}
