<?php

namespace WPGraphQL\Acf;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\Acf\LocationRules\LocationRules;
use WPGraphQL\AppContext;
use WPGraphQL\Registry\TypeRegistry;
use WPGraphQL\Utils\Utils;
use WPGraphQL\Acf\Data\Loader\AcfOptionsPageLoader;
use WPGraphQL\Acf\Model\AcfOptionsPage;

class Registry {

	/**
	 * @var array
	 */
	protected $registered_fields = [];

	/**
	 * @todo should be protected with getter/setter?
	 * @var array
	 */
	public $registered_field_groups;

	/**
	 * @var \WPGraphQL\Registry\TypeRegistry The WPGraphQL TypeRegistry
	 */
	protected $type_registry;

	/**
	 * @param \WPGraphQL\Registry\TypeRegistry|null $type_registry
	 *
	 * @throws \Exception
	 */
	public function __construct( TypeRegistry $type_registry = null ) {
		if ( $type_registry instanceof TypeRegistry ) {
			$this->type_registry = $type_registry;
		} else {
			$this->type_registry = \WPGraphQL::get_type_registry();
		}

		/**
		 * @param \WPGraphQL\Acf\Registry $registry The WPGraphQL for ACF Registry
		 */
		do_action( 'wpgraphql/acf/type_registry/init', $this );
	}

	/**
	 * @return \WPGraphQL\Registry\TypeRegistry
	 */
	public function get_type_registry(): TypeRegistry {
		return $this->type_registry;
	}

	/**
	 * @param string $key
	 * @param mixed  $field_group
	 *
	 * @return void
	 */
	public function register_field_group( string $key, $field_group ): void {
		$this->registered_field_groups[ $key ] = $field_group;
	}

	/**
	 * @param string $key
	 *
	 * @return bool
	 */
	public function has_registered_field_group( string $key ): bool {
		return (bool) isset( $this->registered_field_groups[ $key ] );
	}

	/**
	 * Whether the ACF Field Group should show in the GraphQL Schema
	 *
	 * @param array $acf_field_group
	 *
	 * @return bool
	 */
	public function should_field_group_show_in_graphql( array $acf_field_group ): bool {
		return \WPGraphQL\Acf\Utils::should_field_group_show_in_graphql( $acf_field_group );
	}

	/**
	 * Get the ACF Field Groups that should be registered to the Schema
	 *
	 * @return array
	 */
	public function get_acf_field_groups(): array {
		$all_acf_field_groups = acf_get_field_groups();

		$graphql_field_groups = [];
		foreach ( $all_acf_field_groups as $acf_field_group ) {

			// if a field group is explicitly set to NOT show in GraphQL, we'll leave
			// the field group out of the Schema.
			if ( ! $this->should_field_group_show_in_graphql( $acf_field_group ) || ! isset( $acf_field_group['key'] ) ) {
				continue;
			}

			$graphql_field_groups[ $acf_field_group['key'] ] = $acf_field_group;
		}

		return $graphql_field_groups;
	}

	/**
	 * Register Initial Types to the Schema
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function register_initial_graphql_types(): void {
		register_graphql_interface_type(
			'AcfFieldGroup',
			[
				'description' => __( 'A Field Group managed by ACF', 'wp-graphql-acf' ),
				'fields'      => [
					'fieldGroupName' => [
						'type'              => 'String',
						'description'       => __( 'The name of the field group', 'wp-graphql-acf' ),
						'deprecationReason' => __( 'Use __typename instead', 'wp-graphql-acf' ),
					],
				],
			]
		);

		register_graphql_interface_type(
			'AcfFieldGroupFields',
			[
				'description' => __( 'Fields associated with an ACF Field Group', 'wp-graphql-acf' ),
				'fields'      => [
					'fieldGroupName' => [
						'type'              => 'String',
						'description'       => __( 'The name of the field group', 'wp-graphql-acf' ),
						'deprecationReason' => __( 'Use __typename instead', 'wp-graphql-acf' ),
					],
				],
			]
		);

		register_graphql_object_type(
			'AcfGoogleMap',
			[
				'description' => __( 'A group of fields representing a Google Map', 'wp-graphql-acf' ),
				'fields'      => [
					'streetAddress' => [
						'type'        => 'String',
						'description' => __( 'The street address associated with the map', 'wp-graphql-acf' ),
						'resolve'     => static function ( $root ) {
							return $root['address'] ?? null;
						},
					],
					'latitude'      => [
						'type'        => 'Float',
						'description' => __( 'The latitude associated with the map', 'wp-graphql-acf' ),
						'resolve'     => static function ( $root ) {
							return $root['lat'] ?? null;
						},
					],
					'longitude'     => [
						'type'        => 'Float',
						'description' => __( 'The longitude associated with the map', 'wp-graphql-acf' ),
						'resolve'     => static function ( $root ) {
							return $root['lng'] ?? null;
						},
					],
					'streetName'    => [
						'type'        => 'String',
						'description' => __( 'The street name associated with the map', 'wp-graphql-acf' ),
						'resolve'     => static function ( $root ) {
							return $root['street_name'] ?? null;
						},
					],
					'streetNumber'  => [
						'type'        => 'String',
						'description' => __( 'The street number associated with the map', 'wp-graphql-acf' ),
						'resolve'     => static function ( $root ) {
							return $root['street_number'] ?? null;
						},
					],
					'city'          => [
						'type'        => 'String',
						'description' => __( 'The city associated with the map', 'wp-graphql-acf' ),
						'resolve'     => static function ( $root ) {
							return $root['city'] ?? null;
						},
					],
					'state'         => [
						'type'        => 'String',
						'description' => __( 'The state associated with the map', 'wp-graphql-acf' ),
						'resolve'     => static function ( $root ) {
							return $root['state'] ?? null;
						},
					],
					'stateShort'    => [
						'type'        => 'String',
						'description' => __( 'The state abbreviation associated with the map', 'wp-graphql-acf' ),
						'resolve'     => static function ( $root ) {
							return $root['state_short'] ?? null;
						},
					],
					'postCode'      => [
						'type'        => 'String',
						'description' => __( 'The post code associated with the map', 'wp-graphql-acf' ),
						'resolve'     => static function ( $root ) {
							return $root['post_code'] ?? null;
						},
					],
					'country'       => [
						'type'        => 'String',
						'description' => __( 'The country associated with the map', 'wp-graphql-acf' ),
						'resolve'     => static function ( $root ) {
							return $root['country'] ?? null;
						},
					],
					'countryShort'  => [
						'type'        => 'String',
						'description' => __( 'The country abbreviation associated with the map', 'wp-graphql-acf' ),
						'resolve'     => static function ( $root ) {
							return $root['country_short'] ?? null;
						},
					],
					'placeId'       => [
						'type'        => 'String',
						'description' => __( 'The country associated with the map', 'wp-graphql-acf' ),
						'resolve'     => static function ( $root ) {
							return $root['place_id'] ?? null;
						},
					],
					'zoom'          => [
						'type'        => 'String',
						'description' => __( 'The zoom defined with the map', 'wp-graphql-acf' ),
						'resolve'     => static function ( $root ) {
							return $root['zoom'] ?? null;
						},
					],
				],
			]
		);

		register_graphql_object_type(
			'AcfLink',
			[
				'description' => __( 'ACF Link field', 'wp-graphql-acf' ),
				'fields'      => [
					'url'    => [
						'type'        => 'String',
						'description' => __( 'The url of the link', 'wp-graphql-acf' ),
					],
					'title'  => [
						'type'        => 'String',
						'description' => __( 'The title of the link', 'wp-graphql-acf' ),
					],
					'target' => [
						'type'        => 'String',
						'description' => __( 'The target of the link (_blank, etc)', 'wp-graphql-acf' ),
					],
				],
			]
		);
	}

	/**
	 * Given an ACF Field Group config, return the interface names that it should apply
	 *
	 * @param array $acf_field_group The ACF Field Group config
	 *
	 * @return array
	 * @throws \GraphQL\Error\Error
	 */
	public function get_field_group_interfaces( array $acf_field_group ): array {
		$fields_interface = $this->get_field_group_graphql_type_name( $acf_field_group ) . '_Fields';
		$interfaces       = isset( $acf_field_group['interfaces'] ) && is_array( $acf_field_group['interfaces'] ) ? $acf_field_group['interfaces'] : [];
		$interfaces[]     = 'AcfFieldGroup';
		$interfaces[]     = $fields_interface;

		// Apply Clone Field interfaces if ACF PRO is active
		if ( defined( 'ACF_PRO' ) ) {
			$raw_fields = [];

			if ( ! empty( $acf_field_group['raw_fields'] ) ) {
				$raw_fields = $acf_field_group['raw_fields'];
			} elseif ( ! empty( $acf_field_group['ID'] ) ) {
				$raw_fields = acf_get_raw_fields( $acf_field_group['ID'] );
			}

			$cloned_groups = [];
			if ( ! empty( $raw_fields ) ) {
				foreach ( $raw_fields as $raw_field ) {
					if ( empty( $raw_field['clone'] ) || ! is_array( $raw_field['clone'] ) ) {
						continue;
					}
					foreach ( $raw_field['clone'] as $cloned_field ) {
						if ( ! acf_get_field_group( $cloned_field ) ) {
							continue;
						}

						if ( ! in_array( $cloned_field, $cloned_groups, true ) ) {
							$cloned_groups[] = $cloned_field;
						}
					}
				}
			}

			if ( ! empty( $cloned_groups ) ) {
				foreach ( $cloned_groups as $cloned_group ) {
					$cloned_group = acf_get_field_group( $cloned_group );
					if ( empty( $cloned_group ) ) {
						continue;
					}
					$interfaces[] = $this->get_field_group_graphql_type_name( $cloned_group ) . '_Fields';
				}
			}
		}

		$interfaces = array_unique( array_values( $interfaces ) );

		return array_unique( $interfaces );
	}

	/**
	 * @return void
	 * @throws \GraphQL\Error\Error
	 * @throws \Exception
	 */
	public function register_options_pages():void {
		$graphql_options_pages = \WPGraphQL\Acf\Utils::get_acf_options_pages();

		if ( empty( $graphql_options_pages ) ) {
			return;
		}

		register_graphql_interface_type(
			'AcfOptionsPage',
			[
				'interfaces'  => [ 'Node' ],
				'description' => __( 'Options Page registered by ACF', 'wp-graphql-acf' ),
				'fields'      => [
					'id'        => [
						'type' => [ 'non_null' => 'ID' ],
					],
					'pageTitle' => [
						'type' => 'String',
					],
					'menuTitle' => [
						'type' => 'String',
					],
					'parentId'  => [
						'type' => 'String',
					],
				],
			]
		);

		foreach ( $graphql_options_pages as $graphql_options_page ) {
			$graphql_options_page['is_options_page'] = true;
			if ( ! $this->should_field_group_show_in_graphql( $graphql_options_page ) ) {
				continue;
			}

			$type_name = $this->get_field_group_graphql_type_name( $graphql_options_page );

			if ( empty( $type_name ) ) {
				continue;
			}

			register_graphql_object_type(
				$type_name,
				[
					'interfaces' => [ 'AcfOptionsPage' ],
					'model'      => AcfOptionsPage::class,
					'fields'     => [
						'id'        => [
							'type' => [ 'non_null' => 'ID' ],
						],
						'pageTitle' => [
							'type' => 'String',
						],
					],
				]
			);

			$field_name = Utils::format_field_name( $type_name );

			$interface_name = 'WithAcfOptionsPage' . $type_name;

			register_graphql_interface_type(
				$interface_name,
				[
					// translators: %s is the name of the ACF Options Page
					'description' => sprintf( __( 'Access point for the "%s" ACF Options Page', 'wp-graphql-acf' ), $type_name ),
					'fields'      => [
						$field_name => [
							'type'    => $type_name,
							'resolve' => static function ( $source, $args, AppContext $context, ResolveInfo $info ) use ( $graphql_options_page ) {
								$loader = $context->get_loader( 'acf_options_page' );

								if ( ! $loader instanceof AcfOptionsPageLoader ) {
									return 'null';
								}

								return $context->get_loader( 'acf_options_page' )->load_deferred( $graphql_options_page['menu_slug'] );
							},
						],
					],
				]
			);

			register_graphql_interfaces_to_types( [ $interface_name ], [ 'RootQuery' ] );
		}
	}

	/**
	 * @param array $acf_field_group
	 *
	 * @return array
	 * @throws \GraphQL\Error\Error
	 */
	public function get_fields_for_field_group( array $acf_field_group ): array {
		$raw_fields     = ! empty( $acf_field_group['ID'] ) ? acf_get_raw_fields( $acf_field_group['ID'] ) : [];
		$_cloned_fields = [];
		foreach ( $raw_fields as $raw_field ) {
			if ( ! empty( $raw_field['clone'] ) && is_array( $raw_field['clone'] ) ) {
				foreach ( $raw_field['clone'] as $raw_field_clone_key ) {
					$_cloned_fields[] = $raw_field_clone_key;
				}
			}
		}


		// Set the default field for each field group
		$graphql_fields = [
			'fieldGroupName' => [
				'type'              => 'String',
				'description'       => __( 'The name of the field group', 'wp-graphql-acf' ),

				// this field is required to be registered to ensure the field group doesn't have
				// no fields at all, but is marked deprecated as it is not an actual field
				// of the field group as defined by the ACF Field Group
				'deprecationReason' => __( 'Use __typename instead', 'wp-graphql-acf' ),
				'resolve'           => function ( $root, $args, $context, $info ) use ( $acf_field_group ) {
					return $this->get_field_group_graphql_type_name( $acf_field_group );
				},
			],
		];

		$fields = [];

		if ( isset( $acf_field_group['sub_fields'] ) ) {
			$fields = $acf_field_group['sub_fields'];
		} elseif ( isset( $acf_field_group['ID'] ) ) {
			$fields = acf_get_fields( $acf_field_group );
		}

		// Track cloned fields so that their keys can be passed down in the field config for use in resolvers
		$cloned_fields = [];

		foreach ( $fields as $acf_field ) {
			$graphql_field_name = $this->get_graphql_field_name( $acf_field );

			if ( empty( $graphql_field_name ) ) {
				continue;
			}

			if ( defined( 'ACF_PRO' ) && ! empty( $acf_field['_clone'] ) && ! empty( $acf_field['__key'] ) ) {
				$cloned_fields[ $graphql_field_name ] = $acf_field;

				// if the clone field is not in the array of cloned fields
				if ( ! in_array( $acf_field['__key'], $_cloned_fields, true ) ) {
					$cloned_from = $acf_field;
					$acf_field   = acf_get_field( $acf_field['__key'] );
					if ( empty( $acf_field ) ) {
						continue;
					}
					$acf_field['__key'] = $cloned_from['key'];
				}
			}

			$field_config = $this->map_acf_field_to_graphql( $acf_field, $acf_field_group );

			$graphql_fields[ $graphql_field_name ] = $field_config;
		}

		// If there are cloned fields, pass the cloned field key to the field config for use in resolution
		if ( defined( 'ACF_PRO' ) && ! empty( $cloned_fields ) ) {
			foreach ( $cloned_fields as $cloned_field ) {
				$graphql_field_name = $this->get_graphql_field_name( $cloned_field );
				if ( isset( $graphql_fields[ $graphql_field_name ] ) ) {
					$graphql_fields[ $graphql_field_name ]['acf_field']['__key'] = $cloned_field['key'];
				}
			}
		}

		return $graphql_fields;
	}

	/**
	 * @param array $acf_field
	 * @param array $acf_field_group
	 *
	 * @return array|null
	 * @throws \GraphQL\Error\Error
	 * @throws \Exception
	 */
	public function map_acf_field_to_graphql( array $acf_field, array $acf_field_group ): ?array {
		return ( new FieldConfig( $acf_field, $acf_field_group, $this ) )->get_graphql_field_config();
	}


	/**
	 * Given a field group config, return the name of the field group to be used in the GraphQL
	 * Schema
	 *
	 * @param array $field_group The field group config array
	 *
	 * @return string
	 * @throws \GraphQL\Error\Error
	 */
	public function get_field_group_name( array $field_group ): string {
		return \WPGraphQL\Acf\Utils::get_field_group_name( $field_group );
	}

	/**
	 * @param array $acf_field The ACF Field config
	 *
	 * @return string
	 * @throws \GraphQL\Error\Error
	 */
	public function get_graphql_field_name( array $acf_field ): string {
		return Utils::format_field_name( $this->get_field_group_name( $acf_field ), true );
	}

	/**
	 * @param array $field_group
	 *
	 * @return string|null
	 * @throws \GraphQL\Error\Error
	 */
	public function get_field_group_graphql_type_name( array $field_group ): ?string {
		$name = $this->get_field_group_name( $field_group );

		if ( empty( $name ) ) {
			graphql_debug(
			// translators: %s is the name of the ACF Field or Field Group
				sprintf( __( 'The graphql field name "%s" is not a valid name and cannot be added to the GraphQL Schema', 'wp-graphql-acf' ), $name ),
				[
					'field_group' => $field_group,
				]
			);
			return null;
		}

		$replaced = preg_replace( '/[\W_]+/u', ' ', $name );

		if ( empty( $replaced ) ) {
			// translators: %s is the name of the ACF Field or Field Group
			graphql_debug( sprintf( __( 'The graphql field name %s is not a valid name and cannot be added to the GraphQL Schema', 'wp-graphql-acf' ), $name ) );
			return null;
		}

		return Utils::format_type_name( $replaced );
	}

	/**
	 * Gets the location rules
	 *
	 * @param array $acf_field_groups
	 *
	 * @return array
	 */
	public function get_location_rules( array $acf_field_groups = [] ): array {
		$field_groups = $acf_field_groups;
		$rules        = [];

		// Each field group that doesn't have GraphQL Types explicitly set should get the location
		// rules interpreted.
		foreach ( $field_groups as $field_group ) {
			if ( ! isset( $field_group['graphql_types'] ) || ! is_array( $field_group['graphql_types'] ) ) {
				$rules[] = $field_group;
			}
		}

		if ( empty( $rules ) ) {
			return [];
		}

		// If there are field groups with no graphql_types field set, inherit the rules from
		// ACF Location Rules
		$rules = new LocationRules();
		$rules->determine_location_rules();

		return $rules->get_rules();
	}

	/**
	 * Get the GraphQL Types a Field Group should be registered to show on
	 *
	 * @param array $field_group The ACF Field Group config to determine the Types for
	 * @param array $acf_field_groups
	 *
	 * @return array
	 */
	public function get_graphql_locations_for_field_group( array $field_group, array $acf_field_groups ): array {
		if ( ! $this->should_field_group_show_in_graphql( $field_group ) ) {
			return [];
		}

		$graphql_types = $field_group['graphql_types'] ?? [];

		$field_group_name = $field_group['graphql_field_name'] ?? $field_group['title'];
		$field_group_name = Utils::format_field_name( $field_group_name, true );

		$manually_set_graphql_types = isset( $field_group['map_graphql_types_from_location_rules'] ) && (bool) $field_group['map_graphql_types_from_location_rules'];

		if ( false === $manually_set_graphql_types || empty( $graphql_types ) ) {
			if ( empty( $field_group['graphql_types'] ) ) {
				$location_rules = $this->get_location_rules( $acf_field_groups );
				if ( isset( $location_rules[ $field_group_name ] ) ) {
					$graphql_types = $location_rules[ $field_group_name ];
				}
			}
		}

		return ! empty( $graphql_types ) && is_array( $graphql_types ) ? array_unique( array_filter( $graphql_types ) ) : [];
	}

	/**
	 * Given an array of Acf Field Groups, add them to the Schema
	 *
	 * @param array $acf_field_groups ACF Field Groups to register to the WPGraphQL Schema
	 * @return void
	 * @throws \Exception
	 */
	public function register_acf_field_groups_to_graphql( array $acf_field_groups = [] ): void {
		if ( empty( $acf_field_groups ) ) {
			return;
		}

		// Iterate over the field groups and add them to the Schema
		foreach ( $acf_field_groups as $acf_field_group ) {
			$type_name  = $this->get_field_group_graphql_type_name( $acf_field_group );
			$locations  = $this->get_graphql_locations_for_field_group( $acf_field_group, $acf_field_groups );
			$fields     = $this->get_fields_for_field_group( $acf_field_group );
			$interfaces = $this->get_field_group_interfaces( $acf_field_group );

			// If there's no fields or type name, we can't register the type to the Schema
			if ( empty( $fields ) || empty( $type_name ) ) {
				continue;
			}

			// If there are locations assigned to the field group,
			// register the Interface that signifies the Type supports the ACF Field Group
			// then register the interface to the type
			if ( ! empty( $locations ) ) {
				$with_field_group_interface_name = 'WithAcf' . $type_name;

				$field_name = Utils::format_field_name( $type_name, true );

				if ( ! $this->has_registered_field_group( $with_field_group_interface_name ) ) {
					register_graphql_interface_type(
						$with_field_group_interface_name,
						[
							'eagerlyLoadType' => true,
							// translators: %1$s is the name of the ACF Field Group and %2$s is the name of the ACF Field
							'description'     => sprintf( __( 'Provides access to fields of the "%1$s" ACF Field Group via the "%2$s" field', 'wp-graphql-acf' ), $type_name, $field_name ),
							'fields'          => [
								$field_name => [
									'type'        => $type_name,
									// translators: %s is the name of the ACF Field Group
									'description' => sprintf( __( 'Fields of the %s ACF Field Group', 'wp-graphql-acf' ), $type_name ),
									'resolve'     => static function ( $node ) use ( $acf_field_group ) {

										// Pass the $root node and the $acf_field_group down
										// to the resolving field
										return [
											'node' => $node,
											'acf_field_group' => $acf_field_group,
										];
									},
								],
							],
						]
					);

					$this->register_field_group( $with_field_group_interface_name, $with_field_group_interface_name );
				}

				// If the field group has locations defined (Types to be added to)
				// Add the
				register_graphql_interfaces_to_types( [ $with_field_group_interface_name ], $locations );
			}

			if ( ! $this->has_registered_field_group( $type_name . '_Fields' ) ) {
				$interfaces[] = 'AcfFieldGroupFields';

				$key = array_search( strtolower( $type_name . '_Fields' ), array_map( 'strtolower', $interfaces ), true );

				// Unset itself from the interfaces to implement
				if ( false !== $key ) {
					unset( $interfaces[ $key ] );
				}

				// Add an Interface to the Schema representing the Fields of the ACF Field Group
				register_graphql_interface_type(
					$type_name . '_Fields',
					[
						'kind'            => 'interface',
						'eagerlyLoadType' => true,
						'name'            => $type_name . '_Fields',
						// translators: %s us the name of the ACF Field Group
						'description'     => sprintf( __( 'Interface representing fields of the ACF "%s" Field Group', 'wp-graphql-acf' ), $type_name ),
						'interfaces'      => $interfaces,
						'fields'          => $fields,
						'locations'       => $locations,
						'acf_field_group' => $acf_field_group,
					]
				);

				$this->register_field_group( $type_name . '_Fields', $acf_field_group );
			}

			if ( ! $this->has_registered_field_group( $type_name ) ) {

				// Add an object type to the Schema representing the Field Group, implementing interfaces
				// of any cloned field groups
				register_graphql_object_type(
					$type_name,
					[
						'kind'            => 'object',
						'eagerlyLoadType' => empty( $locations ),
						'name'            => $type_name,
						// translators: %s us the name of the ACF Field Group
						'description'     => sprintf( __( 'The "%s" Field Group. Added to the Schema by "WPGraphQL for ACF".', 'wp-graphql-acf' ), $type_name ),
						'interfaces'      => [ $type_name . '_Fields' ],
						'fields'          => $fields,
						'locations'       => $locations,
						'acf_field_group' => $acf_field_group,
					]
				);

				$this->register_field_group( $type_name, $acf_field_group );
			}
		}
	}

}
