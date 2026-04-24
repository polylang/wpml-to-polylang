<?php
/**
 * PHP version 5.6
 *
 * @package wpml-to-polylang
 */

namespace WP_Syntex\WPML_To_Polylang;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the strings translations.
 *
 * @since 0.5
 */
class Strings extends AbstractSteppable {

	/**
	 * Returns the action name.
	 *
	 * @since 0.5
	 *
	 * @return string
	 */
	public function getName() {
		return 'process_strings_translations';
	}

	/**
	 * Returns the processing message.
	 *
	 * @since 0.5
	 *
	 * @return string
	 */
	protected function getMessage() {
		return esc_html__( 'Processing strings translations', 'wpml-to-polylang' );
	}

	/**
	 * Contexts whose strings are per-post page-builder / block-editor content.
	 * These should be imported into PLL_MO (for completeness) but NOT registered in
	 * Polylang's WPML-compat string registry because they are too numerous and
	 * are already handled through post translations.
	 *
	 * @var string[]
	 */
	const POST_CONTENT_CONTEXT_PREFIXES = [
		'gutenberg-',
		'page-builder-shortcode-strings-',
		'page-builder-',
	];

	/**
	 * Processes the strings translations.
	 *
	 * @since 0.5
	 *
	 * @return void
	 */
	protected function handle() {
		$stringTranslations = $this->getWPMLStringsTranslations();

		if ( empty( $stringTranslations ) ) {
			return;
		}

		/*
		 * Strings to register in Polylang's WPML-compat registry (polylang_wpml_strings).
		 * Keyed by md5('context | name') — same convention used by PLL_WPML_Compat::register_string().
		 * Deduplication is automatic because the same key simply overwrites with identical data.
		 */
		$stringsToRegister = [];

		foreach ( $stringTranslations as $lang => $strings ) {
			$language = PLL()->model->languages->get( $lang );

			if ( empty( $language ) ) {
				// Try a locale-based fallback (e.g. WPML 'pt-br' vs Polylang 'pt_BR').
				$language = $this->getLanguageByLocale( $lang );
			}

			if ( empty( $language ) ) {
				continue;
			}

			$mo = new \PLL_MO();
			$mo->import_from_db( $language ); // Import strings saved in a previous step.

			foreach ( $strings as $msg ) {
				$original    = $msg['original'];
				$translation = $msg['translation'];
				$context     = ! empty( $msg['gettext_context'] ) ? $msg['gettext_context'] : null;
				$wpmlContext = $msg['wpml_context'];
				$wpmlName    = $msg['wpml_name'];

				if ( null !== $context ) {
					/*
					 * Polylang's PLL_MO storage does not preserve gettext context, so we add both
					 * a context-aware entry (for translate_entry() lookups with context) and a
					 * context-free entry (so Polylang's export_to_db can find and persist the value).
					 */
					$contextEntry = new \Translation_Entry(
						[
							'singular'     => $original,
							'context'      => $context,
							'translations' => [ $translation ],
						]
					);
					$mo->add_entry( $contextEntry );
				}

				// Always add a context-free entry so Polylang's translate() can find it.
				$mo->add_entry( $mo->make_entry( $original, $translation ) );

				// Register the string in Polylang's WPML-compat registry (not post-content).
				if ( ! $this->isPostContentContext( $wpmlContext ) ) {
					$key                      = md5( "$wpmlContext | $wpmlName" );
					$stringsToRegister[ $key ] = [
						'context'   => $wpmlContext,
						'name'      => $wpmlName,
						'string'    => $original,
						'multiline' => true,
						'icl'       => true,
					];
				}
			}

			$mo->export_to_db( $language );
		}

		$this->registerInPolylangWpmlStrings( $stringsToRegister );
	}

	/**
	 * Returns true when a WPML string context represents per-post page-builder or
	 * block-editor content rather than a reusable theme/plugin string.
	 *
	 * @since 0.7
	 *
	 * @param string $context WPML string context (= domain / group).
	 * @return bool
	 */
	protected function isPostContentContext( $context ) {
		foreach ( self::POST_CONTENT_CONTEXT_PREFIXES as $prefix ) {
			if ( 0 === strpos( $context, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Merges the given strings into the `polylang_wpml_strings` option that Polylang's
	 * WPML-compat layer (PLL_WPML_Compat) reads to populate the Strings Translations UI.
	 *
	 * Existing entries are preserved; only new keys are added.
	 *
	 * @since 0.7
	 *
	 * @param array<string, array{context: string, name: string, string: string, multiline: bool, icl: bool}> $new Strings keyed by md5('context | name').
	 * @return void
	 */
	protected function registerInPolylangWpmlStrings( array $new ) {
		if ( empty( $new ) ) {
			return;
		}

		$existing = get_option( 'polylang_wpml_strings', [] );

		if ( ! is_array( $existing ) ) {
			$existing = [];
		}

		// Existing entries take precedence; new ones fill in the gaps.
		$merged = $new + $existing;

		if ( count( $merged ) !== count( $existing ) ) {
			update_option( 'polylang_wpml_strings', $merged, false );
		}
	}

	/**
	 * Attempts to find a Polylang language object by matching locale variants when the
	 * WPML language code does not directly match a Polylang language slug.
	 *
	 * @since 0.7
	 *
	 * @param string $lang WPML language code (e.g. 'pt-br', 'zh-hans').
	 * @return \PLL_Language|null
	 */
	protected function getLanguageByLocale( $lang ) {
		$normalized = strtolower( str_replace( '-', '_', $lang ) );

		foreach ( PLL()->model->languages->get_list() as $language ) {
			if ( strtolower( str_replace( '-', '_', $language->locale ) ) === $normalized ) {
				return $language;
			}
			if ( strtolower( str_replace( '-', '_', $language->slug ) ) === $normalized ) {
				return $language;
			}
		}

		return null;
	}

	/**
	 * Returns the number of WPML strings translations.
	 *
	 * @since 0.5
	 *
	 * @return int
	 */
	protected function getTotal() {
		global $wpdb;

		if ( ! $this->tableExists( 'icl_strings' ) || ! $this->tableExists( 'icl_string_translations' ) ) {
			return 0;
		}

		return (int) $wpdb->get_var(
			"SELECT COUNT(*)
			FROM {$wpdb->prefix}icl_strings AS s
			INNER JOIN {$wpdb->prefix}icl_string_translations AS st ON st.string_id = s.id
			WHERE st.value IS NOT NULL
			AND st.value != ''
			AND ( s.language IS NULL OR s.language = '' OR s.language != st.language )"
		);
	}

	/**
	 * Gets the WPML Strings translations.
	 *
	 * @since 0.5
	 *
	 * @return array<string, array<array{original: string, translation: string, gettext_context: string|null, wpml_context: string, wpml_name: string}>>
	 */
	protected function getWPMLStringsTranslations() {
		global $wpdb;

		if ( ! $this->tableExists( 'icl_strings' ) || ! $this->tableExists( 'icl_string_translations' ) ) {
			return [];
		}

		$batch_size = $this->getBatchSyze();
		$offset     = ( $this->step * $batch_size ) - $batch_size;

		/**
		 * WPML string translations.
		 *
		 * @var \stdClass[]
		 */
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.value AS string, s.gettext_context, s.context AS wpml_context, s.name AS wpml_name,
				        st.language, st.value AS translation
				FROM {$wpdb->prefix}icl_strings AS s
				INNER JOIN {$wpdb->prefix}icl_string_translations AS st ON st.string_id = s.id
				WHERE st.value IS NOT NULL
				AND st.value != ''
				AND ( s.language IS NULL OR s.language = '' OR s.language != st.language )
				LIMIT %d, %d",
				absint( $offset ),
				absint( $batch_size )
			)
		);

		$stringTranslations = [];

		foreach ( $results as $st ) {
			if ( ! empty( $st->string ) && ! empty( $st->translation ) ) {
				$stringTranslations[ $st->language ][] = [
					'original'        => $st->string,
					'translation'     => $st->translation,
					'gettext_context' => $st->gettext_context,
					'wpml_context'    => (string) $st->wpml_context,
					'wpml_name'       => (string) $st->wpml_name,
				];
			}
		}

		return $stringTranslations;
	}

	/**
	 * Checks whether a given table (without prefix) exists in the database.
	 *
	 * @since 0.7
	 *
	 * @param string $table Table name without the WordPress prefix.
	 * @return bool
	 */
	protected function tableExists( $table ) {
		global $wpdb;
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . $table ) );
	}
}
