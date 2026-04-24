<?php
/**
 * PHP version 5.6
 *
 * @package wpml-to-polylang
 */

namespace WP_Syntex\WPML_To_Polylang;

defined( 'ABSPATH' ) || exit;

/**
 * Single unified admin page for the WPML → Polylang migration toolkit.
 *
 * Renders the Full Migration card (one-time) plus individual cards for every
 * additional standalone tool (repeatable). All tools share a single menu entry
 * under Tools and the same JS bundle.
 *
 * @since 0.5
 */
class Page {

	/**
	 * AJAX action name that kicks off the full migration chain.
	 *
	 * @var string
	 */
	protected $action;

	/**
	 * Standalone tool descriptors added via addTool().
	 *
	 * Each entry: [ 'id', 'action', 'title', 'description', 'button' ]
	 *
	 * @var array[]
	 */
	private $tools = [];

	/**
	 * Constructor.
	 *
	 * @param string $action AJAX action name for the first step of the full migration.
	 */
	public function __construct( $action ) {
		$this->action = $action;
	}

	/**
	 * Registers a standalone (repeatable) tool card on the page.
	 *
	 * @param string $id          Unique slug used for HTML IDs (e.g. 'strings').
	 * @param string $action      AJAX action name.
	 * @param string $title       Card heading.
	 * @param string $description Short paragraph describing what the tool does.
	 * @param string $button      Submit button label.
	 * @return void
	 */
	public function addTool( $id, $action, $title, $description, $button ) {
		$this->tools[] = compact( 'id', 'action', 'title', 'description', 'button' );
	}

	/**
	 * Registers WordPress hooks.
	 *
	 * @return void
	 */
	public function addHooks() {
		add_action( 'admin_menu', [ $this, 'addMenus' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'addScripts' ] );
	}

	/**
	 * Adds the single Tools sub-menu entry.
	 *
	 * @return void
	 */
	public function addMenus() {
		load_plugin_textdomain( 'wpml-to-polylang' );
		$title = __( 'WPML to Polylang', 'wpml-to-polylang' );
		add_submenu_page(
			'tools.php',
			$title,
			$title,
			'manage_options',
			'wpml-importer',
			[ $this, 'display' ]
		);
	}

	/**
	 * Enqueues the JS bundle only on this page.
	 *
	 * @return void
	 */
	public function addScripts() {
		$screen = get_current_screen();
		if ( empty( $screen ) || 'tools_page_wpml-importer' !== $screen->base ) {
			return;
		}

		wp_enqueue_script(
			'wpml-importer',
			plugins_url( 'js/index.js', __DIR__ ),
			[ 'jquery', 'wp-ajax-response' ],
			WPML_TO_POLYLANG_VERSION,
			true
		);
	}

	/**
	 * Renders the full page.
	 *
	 * @return void
	 */
	public function display() {
		?>
		<div class="wrap wpml-pll-wrap">
			<?php $this->renderStyles(); ?>

			<h1 class="wpml-pll-heading">
				<?php esc_html_e( 'WPML → Polylang', 'wpml-to-polylang' ); ?>
			</h1>
			<p class="wpml-pll-subtitle">
				<?php esc_html_e( 'Migration toolkit — run the full import once, then use the individual tools below as needed.', 'wpml-to-polylang' ); ?>
			</p>

			<?php $this->renderMainCard(); ?>

			<?php if ( ! empty( $this->tools ) ) : ?>
				<h2 class="wpml-pll-section-title">
					<?php esc_html_e( 'Additional Tools', 'wpml-to-polylang' ); ?>
				</h2>
				<p class="wpml-pll-section-desc">
					<?php esc_html_e( 'These can be run independently at any time after the initial migration.', 'wpml-to-polylang' ); ?>
				</p>
				<div class="wpml-pll-tools-grid">
					<?php foreach ( $this->tools as $tool ) : ?>
						<?php $this->renderToolCard( $tool ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Private rendering helpers
	// -------------------------------------------------------------------------

	/**
	 * Renders the primary Full Migration card.
	 *
	 * @return void
	 */
	private function renderMainCard() {
		$errors = $this->getMainErrors();
		?>
		<div class="wpml-pll-card wpml-pll-card--primary">
			<div class="wpml-pll-card-header">
				<span class="wpml-pll-card-icon dashicons dashicons-migrate"></span>
				<div>
					<h2 class="wpml-pll-card-title">
						<?php esc_html_e( 'Full Migration', 'wpml-to-polylang' ); ?>
					</h2>
					<p class="wpml-pll-card-desc">
						<?php esc_html_e( 'Runs the complete pipeline: languages, posts, terms, menus, strings, WordPress options, and ACF fields.', 'wpml-to-polylang' ); ?>
					</p>
				</div>
				<span class="wpml-pll-badge wpml-pll-badge--once">
					<?php esc_html_e( 'One-time', 'wpml-to-polylang' ); ?>
				</span>
			</div>

			<?php if ( ! empty( $errors ) ) : ?>
				<div class="wpml-pll-notices">
					<?php foreach ( $errors as $error ) : ?>
						<div class="notice notice-error inline"><p><?php echo esc_html( $error ); ?></p></div>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<form class="wpml-pll-form" data-tool="main">
					<input type="hidden" name="action" value="<?php echo esc_attr( $this->action ); ?>">
					<?php wp_nonce_field( 'wpml-importer', '_wpnonce_wpml-importer' ); ?>
					<button type="submit" class="button button-primary button-large">
						<?php esc_html_e( 'Run Full Migration', 'wpml-to-polylang' ); ?>
					</button>
					<div class="wpml-pll-status" id="wpml-status-main"></div>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders a standalone tool card.
	 *
	 * @param array $tool Tool descriptor array.
	 * @return void
	 */
	private function renderToolCard( array $tool ) {
		$errors = $this->getToolErrors( $tool['id'] );
		?>
		<div class="wpml-pll-card">
			<div class="wpml-pll-card-header">
				<div>
					<h3 class="wpml-pll-card-title"><?php echo esc_html( $tool['title'] ); ?></h3>
					<p class="wpml-pll-card-desc"><?php echo esc_html( $tool['description'] ); ?></p>
				</div>
				<span class="wpml-pll-badge wpml-pll-badge--repeatable">
					<?php esc_html_e( 'Repeatable', 'wpml-to-polylang' ); ?>
				</span>
			</div>

			<?php if ( ! empty( $errors ) ) : ?>
				<div class="wpml-pll-notices">
					<?php foreach ( $errors as $error ) : ?>
						<div class="notice notice-warning inline"><p><?php echo esc_html( $error ); ?></p></div>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<form class="wpml-pll-form" data-tool="<?php echo esc_attr( $tool['id'] ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( $tool['action'] ); ?>">
					<?php wp_nonce_field( 'wpml-importer', '_wpnonce_wpml-importer' ); ?>
					<button type="submit" class="button button-secondary">
						<?php echo esc_html( $tool['button'] ); ?>
					</button>
					<div class="wpml-pll-status" id="wpml-status-<?php echo esc_attr( $tool['id'] ); ?>"></div>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Outputs scoped CSS for the page.
	 *
	 * @return void
	 */
	private function renderStyles() {
		?>
		<style>
		.wpml-pll-wrap { max-width: 960px; }

		.wpml-pll-heading {
			font-size: 1.8em;
			font-weight: 700;
			margin-bottom: 4px;
			color: #1d2327;
		}

		.wpml-pll-subtitle {
			color: #646970;
			margin-top: 0;
			margin-bottom: 28px;
			font-size: 14px;
		}

		.wpml-pll-section-title {
			font-size: 1em;
			font-weight: 600;
			text-transform: uppercase;
			letter-spacing: .06em;
			color: #646970;
			margin: 36px 0 4px;
			border: none;
		}

		.wpml-pll-section-desc {
			color: #646970;
			margin-top: 0;
			margin-bottom: 16px;
			font-size: 13px;
		}

		/* Cards */
		.wpml-pll-card {
			background: #fff;
			border: 1px solid #dcdcde;
			border-radius: 6px;
			padding: 20px 24px;
			margin-bottom: 0;
			box-shadow: 0 1px 3px rgba(0,0,0,.04);
		}

		.wpml-pll-card--primary {
			border-left: 4px solid #2271b1;
			margin-bottom: 28px;
		}

		.wpml-pll-tools-grid {
			display: grid;
			grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
			gap: 16px;
		}

		/* Card header */
		.wpml-pll-card-header {
			display: flex;
			align-items: flex-start;
			gap: 14px;
			margin-bottom: 16px;
		}

		.wpml-pll-card-icon {
			font-size: 26px;
			color: #2271b1;
			flex-shrink: 0;
			margin-top: 2px;
		}

		.wpml-pll-card-header > div {
			flex: 1;
		}

		.wpml-pll-card-title {
			margin: 0 0 4px;
			font-size: 1.05em;
			font-weight: 600;
			color: #1d2327;
		}

		.wpml-pll-card--primary .wpml-pll-card-title {
			font-size: 1.2em;
		}

		.wpml-pll-card-desc {
			margin: 0;
			color: #646970;
			font-size: 13px;
			line-height: 1.5;
		}

		/* Badges */
		.wpml-pll-badge {
			display: inline-block;
			flex-shrink: 0;
			font-size: 10px;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: .05em;
			padding: 3px 8px;
			border-radius: 20px;
			margin-top: 2px;
		}

		.wpml-pll-badge--once {
			background: #fef8ee;
			color: #a65a00;
			border: 1px solid #f0c36d;
		}

		.wpml-pll-badge--repeatable {
			background: #edfaef;
			color: #1a7a38;
			border: 1px solid #7dd49e;
		}

		/* Form area */
		.wpml-pll-form {
			display: flex;
			align-items: center;
			gap: 14px;
			flex-wrap: wrap;
		}

		.wpml-pll-notices { margin-bottom: 0; }
		.wpml-pll-notices .notice { margin: 4px 0 0; }

		/* Status text */
		.wpml-pll-status {
			font-size: 13px;
			color: #646970;
			font-style: italic;
		}

		.wpml-pll-status.is-done {
			color: #1a7a38;
			font-style: normal;
			font-weight: 600;
		}

		.wpml-pll-status.is-done::before {
			content: "✓ ";
		}
		</style>
		<?php
	}

	// -------------------------------------------------------------------------
	// Error / pre-flight checks
	// -------------------------------------------------------------------------

	/**
	 * Pre-flight checks for the full migration (one-time).
	 *
	 * @return string[]
	 */
	private function getMainErrors() {
		global $sitepress, $wp_version;

		$checks = [];

		if ( false === get_option( 'icl_sitepress_settings' ) ) {
			$checks[] = __( 'WPML is not installed on this website.', 'wpml-to-polylang' );
			return $checks;
		}

		if ( version_compare( $wp_version, WPML_TO_POLYLANG_MIN_WP_VERSION, '<' ) ) {
			$checks[] = __( 'Your version of WordPress is too old. Please update.', 'wpml-to-polylang' );
		}

		if ( ! empty( $sitepress ) ) {
			$checks[] = __( 'WPML is still activated. Please deactivate it before running the import.', 'wpml-to-polylang' );
		}

		if ( ! defined( 'POLYLANG_VERSION' ) ) {
			$checks[] = __( 'Please install and activate Polylang to run the import.', 'wpml-to-polylang' );
		} else {
			if ( version_compare( POLYLANG_VERSION, WPML_TO_POLYLANG_MIN_PLL_VERSION, '<' ) ) {
				$checks[] = __( 'Your version of Polylang is too old. Please update.', 'wpml-to-polylang' );
			}

			if ( PLL()->model->languages->get_list() ) {
				$checks[] = __( 'Polylang is already configured on this site. The full migration can only be run once (before languages are set up). Use the individual tools below instead.', 'wpml-to-polylang' );
			}
		}

		return $checks;
	}

	/**
	 * Pre-flight checks shared by all standalone (repeatable) tools.
	 *
	 * Tool-specific checks are appended by the switch inside this method.
	 *
	 * @param string $toolId Tool identifier.
	 * @return string[]
	 */
	private function getToolErrors( $toolId ) {
		global $sitepress, $wp_version;

		$checks = [];

		if ( version_compare( $wp_version, WPML_TO_POLYLANG_MIN_WP_VERSION, '<' ) ) {
			$checks[] = __( 'Your version of WordPress is too old. Please update.', 'wpml-to-polylang' );
		}

		if ( ! empty( $sitepress ) ) {
			$checks[] = __( 'WPML is still activated. Please deactivate it before running this tool.', 'wpml-to-polylang' );
		}

		if ( ! defined( 'POLYLANG_VERSION' ) ) {
			$checks[] = __( 'Please install and activate Polylang to run this tool.', 'wpml-to-polylang' );
		} else {
			if ( version_compare( POLYLANG_VERSION, WPML_TO_POLYLANG_MIN_PLL_VERSION, '<' ) ) {
				$checks[] = __( 'Your version of Polylang is too old. Please update.', 'wpml-to-polylang' );
			}

			if ( ! PLL()->model->languages->get_list() ) {
				$checks[] = __( 'No Polylang languages found. Please run the Full Migration first.', 'wpml-to-polylang' );
			}
		}

		// Tool-specific checks.
		if ( 'acf-options' === $toolId && ! defined( 'BEA_ACF_OPTIONS_FOR_POLYLANG_VERSION' ) ) {
			$checks[] = __( '"ACF Options For Polylang" plugin is not active. Please install and activate it first.', 'wpml-to-polylang' );
		}

		return $checks;
	}
}
