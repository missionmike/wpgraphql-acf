<?php

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\Registry\TypeRegistry;

use WPGraphQL\Acf\Admin\PostTypeRegistration;
use WPGraphQL\Acf\Admin\TaxonomyRegistration;
use WPGraphQL\Acf\Admin\OptionsPageRegistration;
use WPGraphQL\Acf\Registry;
use WPGraphQL\Acf\ThirdParty;

class WPGraphQLAcf {

	/**
	 * @var \WPGraphQL\Acf\Admin\Settings
	 */
	protected $admin_settings;

	/**
	 * @var array
	 */
	protected $plugin_load_error_messages = [];

	/**
	 * @return void
	 */
	public function init(): void {

		// If there are any plugin load error messages,
		// prevent the plugin from loading and show the messages
		if ( ! empty( $this->get_plugin_load_error_messages() ) ) {
			add_action( 'admin_init', [ $this, 'show_admin_notice' ] );
			add_action( 'graphql_init', [ $this, 'show_graphql_debug_messages' ] );
			return;
		}

		add_action( 'wpgraphql/acf/init', [ $this, 'init_third_party_support' ] );
		add_action( 'admin_init', [ $this, 'init_admin_settings' ] );
		add_action( 'after_setup_theme', [ $this, 'acf_internal_post_type_support' ] );
		add_action( 'graphql_register_types', [ $this, 'init_registry' ] );

		add_filter( 'graphql_data_loaders', [ $this, 'register_loaders' ], 10, 2 );
		add_filter( 'graphql_resolve_node_type', [ $this, 'resolve_acf_options_page_node' ], 10, 2 );
		/**
		 * This filters any field that returns the `ContentTemplate` type
		 * to pass the source node down to the template for added context
		 */
		add_filter( 'graphql_resolve_field', [ $this, 'page_template_resolver' ], 10, 9 );

		do_action( 'wpgraphql/acf/init' );
	}

	/**
	 * @return void
	 */
	public function init_third_party_support(): void {
		$third_party = new ThirdParty();
		$third_party->init();
	}

	/**
	 * @return void
	 */
	public function init_admin_settings(): void {
		$this->admin_settings = new WPGraphQL\Acf\Admin\Settings();
		$this->admin_settings->init();
	}

	/**
	 * Add functionality to the Custom Post Type and Custom Taxonomy registration screens
	 * and underlying functionality (like exports, php code generation)
	 *
	 * @return void
	 */
	public function acf_internal_post_type_support(): void {
		$taxonomy_registration_screen = new TaxonomyRegistration();
		$taxonomy_registration_screen->init();

		$cpt_registration_screen = new PostTypeRegistration();
		$cpt_registration_screen->init();

		$options_page_registration_screen = new OptionsPageRegistration();
		$options_page_registration_screen->init();
	}

	/**
	 * @param \WPGraphQL\Registry\TypeRegistry $type_registry
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function init_registry( TypeRegistry $type_registry ): void {

		// Register general types that should be available to the Schema regardless
		// of the specific fields and field groups registered by ACF
		$registry = new Registry( $type_registry );
		$registry->register_initial_graphql_types();
		$registry->register_options_pages();

		// Get the field groups that should be mapped to the Schema
		$acf_field_groups = $registry->get_acf_field_groups();

		// If there are no acf field groups to show in GraphQL, do nothing
		if ( empty( $acf_field_groups ) ) {
			return;
		}

		$registry->register_acf_field_groups_to_graphql( $acf_field_groups );
	}

	/**
	 * Empty array if the plugin can load. Array of messages if the plugin cannot load.
	 *
	 * @return array
	 */
	public function get_plugin_load_error_messages(): array {
		if ( ! empty( $this->plugin_load_error_messages ) ) {
			return $this->plugin_load_error_messages;
		}

		// Is ACF active?
		if ( ! class_exists( 'ACF' ) ) {
			$this->plugin_load_error_messages[] = __( 'Advanced Custom Fields must be installed and activated', 'wpgraphql-acf' );
		}

		if ( class_exists( 'WPGraphQL\ACF\ACF' ) ) {
			$this->plugin_load_error_messages[] = __( 'Multiple versions of WPGraphQL for ACF cannot be active at the same time', 'wpgraphql-acf' );
		}

		// Have we met the minimum version requirement?
		if ( ! class_exists( 'WPGraphQL' ) || ! defined( 'WPGRAPHQL_VERSION' ) || true === version_compare( WPGRAPHQL_VERSION, WPGRAPHQL_FOR_ACF_VERSION_WPGRAPHQL_REQUIRED_MIN_VERSION, 'lt' ) ) {
			// translators: %s is the version of the plugin
			$this->plugin_load_error_messages[] = sprintf( __( 'WPGraphQL v%s or higher is required to be installed and active', 'wpgraphql-acf' ), WPGRAPHQL_FOR_ACF_VERSION_WPGRAPHQL_REQUIRED_MIN_VERSION );
		}

		return $this->plugin_load_error_messages;
	}

	/**
	 * Show admin notice to admins if this plugin is active but either ACF and/or WPGraphQL
	 * are not active
	 *
	 * @return void
	 */
	public function show_admin_notice(): void {
		$can_load_messages = $this->get_plugin_load_error_messages();

		/**
		 * For users with lower capabilities, don't show the notice
		 */
		if ( empty( $can_load_messages ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		add_action(
			'admin_notices',
			static function () use ( $can_load_messages ) {
				?>
				<div class="error notice">
					<h3>
						<?php
							// translators: %s is the version of the plugin
							echo esc_html( sprintf( __( 'WPGraphQL for Advanced Custom Fields v%s cannot load', 'wpgraphql-acf' ), WPGRAPHQL_FOR_ACF_VERSION ) );
						?>
					</h3>
					<ol>
						<?php foreach ( $can_load_messages as $message ) : ?>
							<li><?php echo esc_html( $message ); ?></li>
						<?php endforeach; ?>
					</ol>
				</div>
				<?php
			}
		);
	}

	/**
	 * @param mixed $type The GraphQL Type to return based on the resolving node
	 * @param mixed $node The Node being resolved
	 *
	 * @return mixed
	 */
	public function resolve_acf_options_page_node( $type, $node ) {
		if ( $node instanceof \WPGraphQL\Acf\Model\AcfOptionsPage ) {
			return \WPGraphQL\Acf\Utils::get_field_group_name( $node->get_data() );
		}
		return $type;
	}

	/**
	 * @param array                 $loaders
	 * @param \WPGraphQL\AppContext $context
	 *
	 * @return array
	 */
	public function register_loaders( array $loaders, \WPGraphQL\AppContext $context ): array {
		$loaders['acf_options_page'] = new \WPGraphQL\Acf\Data\Loader\AcfOptionsPageLoader( $context );
		return $loaders;
	}


	/**
	 * Output graphql debug messages if the plugin cannot load properly.
	 *
	 * @return void
	 */
	public function show_graphql_debug_messages(): void {
		$messages = $this->get_plugin_load_error_messages();

		if ( empty( $messages ) ) {
			return;
		}

		$prefix = sprintf( 'WPGraphQL for Advanced Custom Fields v%s cannot load', WPGRAPHQL_FOR_ACF_VERSION );
		foreach ( $messages as $message ) {
			graphql_debug( $prefix . ' because ' . $message );
		}
	}

		/**
		 * Add the $source node as the "node" passed to the resolver so ACF Fields assigned to Templates can resolve
		 * using the $source node as the object to get meta from.
		 *
		 * @param mixed           $result         The result of the field resolution
		 * @param mixed           $source         The source passed down the Resolve Tree
		 * @param array           $args           The args for the field
		 * @param \WPGraphQL\AppContext $context The AppContext passed down the ResolveTree
		 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo passed down the ResolveTree
		 * @param string          $type_name      The name of the type the fields belong to
		 * @param string          $field_key      The name of the field
		 * @param \GraphQL\Type\Definition\FieldDefinition $field The Field Definition for the resolving field
		 * @param mixed           $field_resolver The default field resolver
		 *
		 * @return mixed
		 */
	public function page_template_resolver( $result, $source, $args, \WPGraphQL\AppContext $context, ResolveInfo $info, string $type_name, string $field_key, \GraphQL\Type\Definition\FieldDefinition $field, $field_resolver ) {
		if ( strtolower( 'ContentTemplate' ) !== strtolower( $info->returnType ) ) {
			return $result;
		}

		if ( is_array( $result ) && ! isset( $result['node'] ) && ! empty( $source ) ) {
			$result['node'] = $source;
		}

		return $result;
	}

}
