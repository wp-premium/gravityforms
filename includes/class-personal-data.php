<?php

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

/**
 * Handles Integration with the WordPress personal data export and erase tools.
 *
 * @since 2.4
 *
 * Class GF_Personal_Data
 */
class GF_Personal_Data {

	/**
	 * The cached form array.
	 *
	 * @since 2.4
	 *
	 * @var array $form
	 */
	private static $_form;

	/**
	 * The cached array of forms.
	 *
	 * @since 2.4
	 *
	 * @var array $forms
	 */
	private static $_forms;

	/**
	 * Renders the form settings.
	 *
	 * @since 2.4
	 *
	 * @param $form_id
	 */
	public static function form_settings( $form_id ) {

		$form = self::get_form( $form_id );

		$form_personal_data_settings = rgar( $form, 'personalData' );

		$action_url = admin_url( sprintf( 'admin.php?page=gf_edit_forms&view=settings&subview=personal-data&id=%d', $form_id ) );

		$enabled = (bool) rgars( $form_personal_data_settings, 'exportingAndErasing/enabled' );

		$prevent_ip = (bool) rgar( $form_personal_data_settings, 'preventIP' );

		$retention_policy = rgars( $form_personal_data_settings, 'retention/policy' );

		if ( empty( $retention_policy ) ) {
			$retention_policy = 'retain';
		}

		$retention_days = rgars( $form_personal_data_settings, 'retention/retain_entries_days' );

		if ( empty( $retention_days ) ) {
			$retention_days = 1;
		}

		?>
		<h3><span><i class="fa fa-lock"></i> <?php esc_html_e( 'Personal Data', 'gravityforms' ); ?>
		</h3>
		<div class="gform_panel gform_panel_form_settings" id="gf_personal_data_settings">
			<form action="<?php esc_url( $action_url ); ?>" method="POST">
				<?php wp_nonce_field( 'gravityforms_personal_data' ); ?>
				<table class="gforms_form_settings" cellspacing="0" cellpadding="0">
					<tr>
						<td colspan="2">
							<h4 class="gf_settings_subgroup_title"><?php esc_html_e( 'General Settings', 'gravityforms' ); ?></h4>
						</td>
					</tr>
					<tr>
						<th>
							<?php esc_html_e( 'IP Addresses', 'gravityforms' ); ?>
							<?php gform_tooltip( 'personal_data_prevent_ip' ); ?>
						</th>
						<td>
							<label for="gf_personal_data_prevent_ip">
								<input
									id="gf_personal_data_prevent_ip"
									type="checkbox"
									name="prevent_ip"
									<?php checked( true, $prevent_ip ); ?>
								/>
								<?php esc_html_e( 'Prevent the storage of IP addresses during form submission.', 'gravityforms' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th>
							<?php esc_html_e( 'Retention Policy', 'gravityforms' ); ?>
							<?php gform_tooltip( 'personal_data_retention_policy' ); ?>
						</th>
						<td>
							<label for="gf_personal_data_retention_do_not_delete">
								<input
									id="gf_personal_data_retention_do_not_delete"
									type="radio"
									name="retention"
									value="retain"
									<?php checked( 'retain', $retention_policy ); ?>
								/>
								<?php esc_html_e( 'Retain entries indefinitely', 'gravityforms' ); ?>
							</label>
							<br/>
							<label for="gf_personal_data_retention_trash">
								<input
									id="gf_personal_data_retention_trash"
									type="radio"
									name="retention"
									value="trash"
									<?php checked( 'trash', $retention_policy ); ?>
								/>
								<?php esc_html_e( 'Trash entries automatically', 'gravityforms' ); ?>
							</label>
							<br/>
							<label for="gf_personal_data_retention_delete">
								<input
									id="gf_personal_data_retention_delete"
									type="radio"
									name="retention"
									value="delete"
									<?php checked( 'delete', $retention_policy ); ?>
								/>
								<?php esc_html_e( 'Delete entries permanently automatically', 'gravityforms' ); ?>
							</label>
							<div id="gf_personal_data_retain_entries_days_container" style="<?php echo( $retention_policy !== 'retain' ? '' : 'display:none' ) ?>">
								<label for="gf_personal_data_retain_entries_days_container">
									<?php esc_html_e( 'Number of days to retain entries before trashing/deleting:', 'gravityforms' ); ?>
									<input
										id="gf_personal_data_retain_entries_days"
										type="text"
										class="small-text"
										name="retain_entries_days"
										value="<?php echo absint( $retention_days ); ?>"
									/>
								</label>
							</div>
						</td>
					</tr>
					<tr>
						<td colspan="2">
							<h4 class="gf_settings_subgroup_title"><?php esc_html_e( 'Exporting and Erasing Data', 'gravityforms' ); ?></h4>
						</td>
					</tr>
					<?php
					$identification_field_choices = array();

					$email_fields = GFAPI::get_fields_by_type( $form, 'email' );

					foreach ( $email_fields as $email_field ) {
						$identification_field_choices[ (string) $email_field->id ] = $email_field->label;
					}

					/**
					* Allows the list of personal data identification field choices to be modified. Fields values
					* will be treated as user IDs.
					*
					* For example, add the created_by field by returning:
					* $identification_field_choices['created_by'] = 'Created By';
					*
					* @since 2.4
					*
					* @param array $identification_field_choices An associative array with the field id as the key and the value as the label.
					* @param array $form                         The current form.
					*/
					$identification_field_choices = gf_apply_filters( array( 'gform_personal_data_identification_fields', $form['id'] ), $identification_field_choices, $form );

					$no_id = empty( $identification_field_choices );

					$lookup_field = rgars( $form, 'personalData/exportingAndErasing/identificationField' );

					if ( $no_id ) {
						if ( $lookup_field == 'created_by' ) {

							$no_id = false;

							$identification_field_choices = array(
								'created_by' => __( 'Created By', 'gravityforms' ),
							);

						} elseif ( $selected_field = GFFormsModel::get_field( $form, $lookup_field ) ) {

							$no_id = false;

							$selected_field->set_context_property( 'use_admin_label', true );

							$choice_label = GFFormsModel::get_label( $selected_field );

							$identification_field_choices = array(
								$lookup_field => $choice_label,
							);

						} else {
							$enabled = false;
						}
					}
					?>
					<tr>
						<th>
							<?php esc_html_e( 'Enable', 'gravityforms' ); ?>
							<?php gform_tooltip( 'personal_data_enable' ); ?>
						</th>
						<td>
							<label for="gf_personal_data_enable">
								<input
									id="gf_personal_data_enable"
									type="checkbox"
									name="exporting_and_erasing_enabled"
									<?php checked( true, $enabled ); ?>
									<?php disabled( true, $no_id ); ?>
								/>
								<?php esc_html_e( 'Enable integration with the WordPress tools for exporting and erasing personal data.', 'gravityforms' ); ?>
							</label>
						</td>
					</tr>
					<?php

					if ( ! $no_id ) {
						?>
						<tr class="gf_personal_data_settings" style="<?php echo( $enabled ? '' : 'display:none;' ); ?>">
							<th>
								<?php esc_html_e( 'Identification Field', 'gravityforms' ); ?>
								<?php gform_tooltip( 'personal_data_identification' ); ?>
							</th>
							<td>
								<select name="identification_field">
									<?php
									echo sprintf( '<option value="">%s</option>', esc_html__( 'Select a Field', 'gravityforms' ) );
									foreach ( $identification_field_choices as $id => $label ) {
										$selected = selected( $id, $lookup_field, false );
										echo sprintf( '<option %s value="%s">%s</option>', $selected, $id, $label );
									}
									?>
								</select>
							</td>
						</tr>

						<tr class="gf_personal_data_settings" style="<?php echo( $enabled ? '' : 'display:none;' ); ?>">
						<th>
							<?php esc_html_e( 'Personal Data', 'gravityforms' ); ?>
							<?php gform_tooltip( 'personal_data_field_settings' ); ?>

						</th>
						<td>
							<table id="gf_personal_data_field_settings">
								<thead>
								<tr>
									<th class="gf_personal_data_field_label_title"><?php esc_html_e( 'Fields', 'gravityforms' ); ?></th>
									<th class="gf_personal_data_cb_title"><?php esc_html_e( 'Export', 'gravityforms' ); ?></th>
									<th class="gf_personal_data_cb_title"><?php esc_html_e( 'Erase', 'gravityforms' ); ?></th>
								</tr>
								</thead>
								<tbody>
								<tr>
									<td>
										<?php esc_html_e( 'Select/Deselect All', 'gravityforms' ); ?>
									</td>
									<td class="gf_personal_data_cb_cell">
										<input
											id="gf_personal_data_export_all"
											type="checkbox"
										/>
									</td>
									<td class="gf_personal_data_cb_cell">
										<input
											id="gf_personal_data_erase_all"
											type="checkbox"
										/>
									</td>
								</tr>
								<?php

								$columns = self::get_columns();

								$all_column_settings = rgars( $form_personal_data_settings, 'exportingAndErasing/columns' );

								foreach ( $columns as $key => $label ) {
									$column_settings = rgar( $all_column_settings, $key );
									?>
									<tr>
										<td>
											<?php echo esc_html( $label ); ?>
										</td>
										<td class="gf_personal_data_cb_cell">
											<input
												class="gf_personal_data_cb_export"
												type="checkbox"
												name="export_fields[<?php echo esc_attr( $key ); ?>]"
												<?php checked( true, (bool) rgar( $column_settings, 'export' ) ); ?>
											/>
										</td>
										<td class="gf_personal_data_cb_cell">
											<input
												class="gf_personal_data_cb_erase"
												type="checkbox"
												name="erase_fields[<?php echo esc_attr( $key ); ?>]"
												<?php checked( true, (bool) rgar( $column_settings, 'erase' ) ); ?>
											/>
										</td>
									</tr>
									<?php
								}

								/* @var GF_Field[] $fields */
								$fields = $form['fields'];

								foreach ( $fields as $field ) {

									if ( $field->displayOnly ) {
										// Skip display-only fields such as HTML, Section and Page fields.
										continue;
									}

									$field->set_context_property( 'use_admin_label', true );

									?>
									<tr>
										<td>
											<?php echo esc_html( GFFormsModel::get_label( $field ) ); ?>
										</td>
										<td class="gf_personal_data_cb_cell">
											<input
												class="gf_personal_data_cb_export"
												type="checkbox"
												name="export_fields[<?php echo absint( $field->id ); ?>]"
												<?php checked( true, (bool) $field->personalDataExport ); ?>
											/>
										</td>
										<td class="gf_personal_data_cb_cell">
											<input
												class="gf_personal_data_cb_erase"
												type="checkbox"
												name="erase_fields[<?php echo absint( $field->id ); ?>]"
												<?php checked( true, (bool) $field->personalDataErase ); ?>
											/>
										</td>
									</tr>
									<?php
								}

								$custom_items = self::get_custom_items( $form );

								if ( ! empty( $custom_items ) ) {

									?>
									<tr>
										<th class="gf_personal_data_field_label_title" colspan="3">
											<?php esc_html_e( 'Other Data', 'gravityforms' ) ?>
										</th>
									</tr>
									<?php

									$custom_items_settings = rgars( $form_personal_data_settings, 'exportingAndErasing/custom' );

									foreach ( $custom_items as $key => $custom_item_details ) {
										$custom_settings = rgar( $custom_items_settings, $key );
										$label           = rgar( $custom_item_details, 'label' );
										?>
										<tr>
											<td>
												<?php echo esc_html( $label ); ?>
											</td>
											<td class="gf_personal_data_cb_cell">
												<?php
												if ( isset( $custom_item_details['exporter_callback'] ) && is_callable( $custom_item_details['exporter_callback'] ) ) {
													?>
													<input
														class="gf_personal_data_cb_export"
														type="checkbox"
														name="export_fields[<?php echo esc_attr( $key ); ?>]"
														<?php checked( true, (bool) rgar( $custom_settings, 'export' ) ); ?>
													/>
													<?php
												}
												?>
											</td>
											<td class="gf_personal_data_cb_cell">
												<?php
												if ( isset( $custom_item_details['eraser_callback'] ) && is_callable( $custom_item_details['eraser_callback'] ) ) {
													?>
													<input
														class="gf_personal_data_cb_erase"
														type="checkbox"
														name="erase_fields[<?php echo esc_attr( $key ); ?>]"
														<?php checked( true, (bool) rgar( $custom_settings, 'erase' ) ); ?>
													/>
													<?php
												}
												?>
											</td>
										</tr>
										<?php
									}
								}

								?>
								</tbody>
							</table>
						</td>
					</tr>

						<?php
					} else {
						?>
						<tr>
							<th></th>
							<td>
								<div class="alert_red" style="padding:15px;">
									<?php esc_html_e( 'You must add an email address field to the form in order to enable this setting.', 'gravityforms' ); ?>
								</div>
							</td>
						</tr>
						<?php
					}
					?>

				</table>
				<input
					class="button-primary"
					type="submit"
					name="save_personal_data_settings"
					value="<?php esc_attr_e( 'Save', 'gravityforms' ); ?>"
				/>
			</form>
		</div>
		<script>
			jQuery(document).ready(function ($) {
				$('#gf_personal_data_enable').change(function () {
					if ($(this).is(":checked")) {
						$('.gf_personal_data_settings').show();
					} else {
						$('.gf_personal_data_settings').hide();
					}
				});
				$('#gf_personal_data_export_all').change(function () {
					if ($(this).is(":checked")) {
						$('.gf_personal_data_cb_export').prop('checked', true);
					} else {
						$('.gf_personal_data_cb_export').prop('checked', false);
					}
				});
				$('#gf_personal_data_erase_all').change(function () {
					if ($(this).is(":checked")) {
						$('.gf_personal_data_cb_erase').prop('checked', true);
					} else {
						$('.gf_personal_data_cb_erase').prop('checked', false);
					}
				});
				$("input[name='lookup']").change(function () {
					if ($(this).val() == 'identifying_email_field') {
						$('#gf_personal_data_email_field_select').fadeIn();
					} else {
						$('#gf_personal_data_email_field_select').hide();
					}
				});
				$("input[name='retention']").change(function () {
					if ($(this).val() == 'retain') {
						$('#gf_personal_data_retain_entries_days_container').hide();
					} else {
						alert( <?php echo json_encode( __( 'Warning: this will affect all entries that are older than the number of days specified.', 'gravityforms' ) ) ?> );
						$('#gf_personal_data_retain_entries_days_container').fadeIn();
					}
				});

			});
		</script>
		<?php
	}

	/**
	 * Saves the form settings.
	 *
	 * @since 2.4
	 *
	 * @param $form_id
	 */
	public static function process_form_settings( $form_id ) {
		check_admin_referer( 'gravityforms_personal_data' );

		$form = self::get_form( $form_id );

		$posted_export_fields = rgpost( 'export_fields' );

		$posted_erase_fields = rgpost( 'erase_fields' );

		$columns = self::get_columns();

		if ( ! isset( $form['personalData'] ) ) {
			$form['personalData'] = array();
		}

		$form_personal_data_settings = $form['personalData'];

		$form_personal_data_settings['preventIP'] = (bool) rgpost( 'prevent_ip' );

		$retention_policy = rgpost( 'retention' );

		// Whitelist the policy
		$retention_policy = in_array( $retention_policy, array(
			'retain',
			'trash',
			'delete',
		) ) ? $retention_policy : 'retain';

		$retain_entries_days = absint( rgpost( 'retain_entries_days' ) );

		if ( empty( $retain_entries_days ) ) {
			// Minimum to ensure the cron task doesn't delete the entry before all processing is complete.
			$retain_entries_days = 1;
		}


		$form_personal_data_settings['retention'] = array(
			'policy'              => $retention_policy,
			'retain_entries_days' => $retain_entries_days,
		);

		if ( ! isset( $form_personal_data_settings['exportingAndErasing'] ) ) {
			$form_personal_data_settings['exportingAndErasing'] = array();
		}

		$exporting_and_erasing = $form_personal_data_settings['exportingAndErasing'];

		if ( ! isset( $exporting_and_erasing['columns'] ) ) {
			$exporting_and_erasing['columns'] = array();
		}

		$exporting_and_erasing['enabled'] = (bool) rgpost( 'exporting_and_erasing_enabled' );

		if ( ! $exporting_and_erasing['enabled'] ) {
			$exporting_and_erasing['identificationField'] = '';
		} else {
			$exporting_and_erasing['identificationField'] = sanitize_text_field( rgpost( 'identification_field' ) );
		}

		$all_column_settings = $exporting_and_erasing['columns'];

		foreach ( $columns as $key => $label ) {
			$column_settings = array(
				'export' => (bool) rgar( $posted_export_fields, $key ),
				'erase'  => (bool) rgar( $posted_erase_fields, $key ),
			);

			$all_column_settings[ $key ] = $column_settings;
		}

		$exporting_and_erasing['columns'] = $all_column_settings;


		/* @var GF_Field[] $fields */
		$fields = $form['fields'];

		foreach ( $fields as &$field ) {
			$field->personalDataExport = (bool) rgar( $posted_export_fields, $field->id );
			$field->personalDataErase  = (bool) rgar( $posted_erase_fields, $field->id );
		}

		$custom_items = self::get_custom_items( $form );

		if ( ! empty( $custom_items ) ) {
			$custom_items_settings = rgar( $exporting_and_erasing, 'custom' ) ? $exporting_and_erasing['custom'] : array();

			foreach ( array_keys( $custom_items ) as $custom_item_key ) {
				$custom_item_settings = array(
					'export' => (bool) rgar( $posted_export_fields, $custom_item_key ),
					'erase'  => (bool) rgar( $posted_erase_fields, $custom_item_key ),
				);

				$custom_items_settings[ $custom_item_key ] = $custom_item_settings;
			}

			$exporting_and_erasing['custom'] = $custom_items_settings;
		}

		$form_personal_data_settings['exportingAndErasing'] = $exporting_and_erasing;

		$form['personalData'] = $form_personal_data_settings;

		GFAPI::update_form( $form );
		self::$_form = $form;
		?>
		<div class="updated below-h2" id="after_update_dialog">
			<p>
				<strong><?php _e( 'Personal data settings updated successfully.', 'gravityforms' ); ?></strong>
			</p>
		</div>
		<?php
	}

	/**
	 * Returns the form array for use in the form settings.
	 *
	 * @since 2.4
	 *
	 * @param int $form_id
	 *
	 * @return array|mixed
	 */
	public static function get_form( $form_id ) {
		if ( empty( self::$_form ) ) {
			self::$_form = GFAPI::get_form( $form_id );
		}

		return self::$_form;
	}

	/**
	 * Returns an assoiative array of the database columns that may contain personal data.
	 *
	 * @since 2.4
	 *
	 * @return array
	 */
	public static function get_columns() {
		$columns = array(
			'ip'         => esc_html__( 'IP Address', 'gravityforms' ),
			'source_url' => esc_html__( 'Embed URL', 'gravityforms' ),
			'user_agent' => esc_html__( 'Browser details', 'gravityforms' ),
		);

		return $columns;
	}

	/**
	 * Returns an array with the custom personal data items configurations.
	 *
	 * @since 2.4
	 *
	 * @param array $form
	 *
	 * @return array
	 */
	public static function get_custom_items( $form ) {

		$custom_items = array();

		/**
		 * Allows custom exporter and erasers to be registered.
		 *
		 * Example:
		 *
		 * add_filter( 'gform_personal_data', 'filter_gform_personal_data', 10, 2 );
		 * function filter_gform_personal_data( $items, $form ) {
		 *       $items['test'] = array(
		 *          'label'             => 'A custom item',
		 *          'exporter_callback' => 'gf_custom_data_exporter',
		 *          'eraser_callback'   => 'gf_custom_data_eraser',
		 *      );
		 *
		 *      return $items;
		 * }
		 *
		 * function gf_custom_data_exporter( $form, $entry ) {
		 *       $data = array(
		 *        'name'  => 'My Custom Value',
		 *          'value' => 'ABC123',
		 *      );
		 *      return $data;
		 * }
		 *
		 * function gf_custom_data_eraser( $form, $entry ) {
		 *      // Delete or anonymize some data
		 * }
		 *
		 * @since 2.4
		 *
		 * @param array $custom_items
		 * @param array $form
		 */
		$custom_items = apply_filters( 'gform_personal_data', $custom_items, $form );

		return $custom_items;
	}

	/**
	 * Returns an associative array of all the form metas with the form ID as the key.
	 *
	 * @since 2.4
	 *
	 * @return array|null
	 */
	public static function get_forms() {

		if ( is_null( self::$_forms ) ) {
			$form_ids = GFFormsModel::get_form_ids();

			if ( empty( $form_ids ) ) {
				return array(
					'data' => array(),
					'done' => true,
				);
			}

			$forms_by_id = GFFormsModel::get_form_meta_by_id( $form_ids );

			self::$_forms = array();
			foreach ( $forms_by_id as $form ) {
				self::$_forms[ $form['id'] ] = $form;
			}
		}

		return self::$_forms;
	}

	/**
	 * Returns all the entries across all forms for the specified email address.
	 *
	 * @since 2.4
	 *
	 * @param string    $email_address
	 * @param int $page
	 * @param int $limit
	 *
	 * @return array
	 */
	public static function get_entries( $email_address, $page = 1, $limit = 50 ) {

		$user = get_user_by( 'email', $email_address );

		$forms = self::get_forms();

		$form_ids = array();

		$query = new GF_Query();

		$conditions = array();

		foreach ( $forms as $form ) {

			if ( ! rgars( $form, 'personalData/exportingAndErasing/enabled' ) ) {
				continue;
			}

			$form_ids[] = $form['id'];

			$identification_field = rgars( $form, 'personalData/exportingAndErasing/identificationField' );

			$field = GFAPI::get_field( $form, $identification_field );

			if ( $field && $field->get_input_type() == 'email' ) {

				$conditions[] = new GF_Query_Condition(
					new GF_Query_Column( $identification_field, $form['id'] ),
					GF_Query_Condition::EQ,
					new GF_Query_Literal( $email_address )
				);

			} else {

				if ( ! $field && $identification_field != 'created_by' ) {
					continue;
				}

				if ( ! $user ) {
					continue;
				}

				$conditions[] = new GF_Query_Condition(
					new GF_Query_Column( $identification_field, $form['id'] ),
					GF_Query_Condition::EQ,
					new GF_Query_Literal( $user->ID )
				);
			}
		}

		if ( empty( $conditions ) ) {
			return array();
		}

		$all_conditions = call_user_func_array( array( 'GF_Query_Condition', '_or' ), $conditions );

		$entries = $query->from( $form_ids )->where( $all_conditions )->limit( $limit )->page( $page )->get();

		return $entries;
	}

	/**
	 * Exports personal data specified in the form settings.
	 *
	 * @since 2.4
	 *
	 * @param string    $email_address
	 * @param int $page
	 *
	 * @return array
	 */
	public static function data_exporter( $email_address, $page = 1 ) {

		$export_items = array(
			'done' => true,
		);

		$export_data = array();

		if ( $page == 1 ) {
			$export_data = self::get_draft_submissions_export_items( $email_address );
		}

		$export_items['data'] = $export_data;

		$limit = 50;

		$columns = self::get_columns();

		$forms = self::get_forms();

		$entries = self::get_entries( $email_address, $page, $limit );

		if ( empty( $entries ) ) {
			return $export_items;
		}

		foreach ( $entries as $entry ) {

			$data = array();

			$form_id = $entry['form_id'];

			$form = $forms[ $form_id ];

			$item_id = "gf-entry-{$entry['id']}";

			$group_id = 'gravityforms-entries';

			$group_label = __( 'Forms', 'gravityforms' );

			$columns_settings = rgars( $forms, $form_id . '/personalData/exportingAndErasing/columns' );

			if ( is_array( $columns_settings ) ) {
				foreach ( $columns_settings as $column_key => $column_settings ) {
					if ( rgar( $column_settings, 'export' ) ) {
						$data[] = array(
							'name'  => $columns[ $column_key ],
							'value' => $entry[ $column_key ],
						);
					}
				}
			}

			foreach ( $form['fields'] as $field ) {
				/* @var GF_Field $field */
				if ( $field->personalDataExport ) {
					$value  = GFFormsModel::get_lead_field_value( $entry, $field );
					$data[] = array(
						'name'  => $field->get_field_label( false, $value ),
						'value' => $field->get_value_entry_detail( $value, rgar( $entry, 'currency' ), true, 'text' ),
					);
				}
			}

			$custom_items = self::get_custom_items( $form );

			if ( ! empty( $custom_items ) ) {
				$all_custom_settings = rgars( $forms, $form_id . '/personalData/exportingAndErasing/custom' );
				foreach ( $custom_items as $custom_item_key => $custom_item_details ) {
					$custom_settings = rgar( $all_custom_settings, $custom_item_key );
					if ( rgars( $custom_settings, 'export' ) && isset( $custom_item_details['exporter_callback'] ) && is_callable( $custom_item_details['exporter_callback'] ) ) {
						$data[] = call_user_func( $custom_item_details['exporter_callback'], $form, $entry );
					}
				}
			}

			if ( ! empty( $data ) ) {
				$export_data[] = array(
					'group_id'    => $group_id,
					'group_label' => $group_label,
					'item_id'     => $item_id,
					'data'        => $data,
				);
			}
		}

		$done = count( $entries ) < $limit;

		$export_items = array(
			'data' => $export_data,
			'done' => $done,
		);

		return $export_items;
	}

	/**
	 * Returns the export items for draft submissions.
	 *
	 * @since 2.4
	 *
	 * @param $email_address
	 *
	 * @return array
	 */
	public static function get_draft_submissions_export_items( $email_address ) {
		$export_items = array();

		$forms = self::get_forms();

		$columns = self::get_columns();

		$draft_submissions = self::get_draft_submissions( $email_address );

		foreach ( $draft_submissions as $i => $draft_submission ) {
			$data = array();

			$form_id = $draft_submission['form_id'];

			$form = $forms[ $form_id ];

			$submission_json = $draft_submission['submission'];

			$submission = json_decode( $submission_json, true );

			$entry = $submission['partial_entry'];

			$item_id = "gf-draft-submission-{$i}";

			$group_id = 'gravityforms-draft-submissions';

			$group_label = __( 'Draft Forms (Save and Continue Later)', 'gravityforms' );

			$columns_settings = rgars( $forms, $form_id . '/personalData/exportingAndErasing/columns' );

			if ( is_array( $columns_settings ) ) {
				foreach ( $columns_settings as $column_key => $column_settings ) {
					if ( rgar( $column_settings, 'export' ) && isset( $draft_submission[ $column_key ] ) ) {
						$data[] = array(
							'name'  => $columns[ $column_key ],
							'value' => $draft_submission[ $column_key ],
						);
					}
				}
			}

			foreach ( $form['fields'] as $field ) {
				/* @var GF_Field $field */
				if ( $field->personalDataExport ) {
					$value  = GFFormsModel::get_lead_field_value( $entry, $field );
					$data[] = array(
						'name'  => $field->get_field_label( false, $value ),
						'value' => $field->get_value_entry_detail( $value, rgar( $entry, 'currency' ), true, 'text' ),
					);
				}
			}

			if ( ! empty( $data ) ) {
				$export_items[] = array(
					'group_id'    => $group_id,
					'group_label' => $group_label,
					'item_id'     => $item_id,
					'data'        => $data,
				);
			}
		}

		return $export_items;
	}

	/**
	 * Erases personal data specified in the form settings.
	 *
	 * @since 2.4
	 *
	 * @param string $email_address
	 * @param int    $page
	 *
	 * @return array
	 */
	public static function data_eraser( $email_address, $page = 1 ) {

		$limit = 50;

		$items_removed = $page == 1 ? self::erase_draft_submissions_data( $email_address ) : false;

		$forms = self::get_forms();

		$entries = self::get_entries( $email_address, $page, $limit );

		foreach ( $entries as $entry ) {

			$form_id = $entry['form_id'];

			$form = $forms[ $form_id ];

			$columns_settings = rgars( $forms, $form_id . '/personalData/exportingAndErasing/columns' );

			if ( is_array( $columns_settings ) ) {
				foreach ( $columns_settings as $column_key => $column_settings ) {
					if ( rgar( $column_settings, 'erase' ) ) {
						GFAPI::update_entry_property( $entry['id'], $column_key, '' );
						$items_removed = true;
					}
				}
			}

			$has_product_field = false;

			foreach ( $form['fields'] as $field ) {
				/* @var GF_Field $field */

				if ( $field->personalDataErase ) {

					$input_type = $field->get_input_type();

					if ( $input_type == 'fileupload' ) {
						GFFormsModel::delete_files( $entry['id'] );
						GFAPI::update_entry_field( $entry['id'], $field->id, '' );
						continue;
					}

					if ( $field->type == 'product' ) {
						$has_product_field = true;
					}

					$value = GFFormsModel::get_lead_field_value( $entry, $field );

					if ( is_array( $value ) ) {
						self::erase_field_values( $value, $entry['id'], $field->id );
						$items_removed = true;
					} else {
						switch ( $input_type ) {
							case 'email':
								$anonymous = 'deleted@site.invalid';
								break;
							case 'website':
								$anonymous = 'https://site.invalid';
								break;
							case 'date':
								$anonymous = '0000-00-00';
								break;
							case 'text':
							case 'textarea':
								/* translators: deleted text */
								$anonymous = __( '[deleted]' );
								break;
							default:
								$anonymous = '';
						}
						GFAPI::update_entry_field( $entry['id'], $field->id, $anonymous );
						$items_removed = true;
					}
				}
			}

			if ( $has_product_field ) {
				GFFormsModel::refresh_product_cache( $form, $entry );
			}

			$custom_items = self::get_custom_items( $form );

			if ( ! empty( $custom_items ) ) {
				$all_custom_settings = rgars( $forms, $form_id . '/personalData/exportingAndErasing/custom' );
				foreach ( $custom_items as $custom_item_key => $custom_item_details ) {
					$custom_settings = rgar( $all_custom_settings, $custom_item_key );
					if ( rgars( $custom_settings, 'erase' ) && isset( $custom_item_details['eraser_callback'] ) && is_callable( $custom_item_details['eraser_callback'] ) ) {
						call_user_func( $custom_item_details['eraser_callback'], $form, $entry );
						$items_removed = true;
					}
				}
			}
		}

		$done = count( $entries ) < $limit;

		return array(
			'items_removed'  => $items_removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => $done,
		);
	}

	public static function erase_field_values( $value, $entry_id, $input_id, $item_index = '' ) {
		if ( is_array( $value ) ) {
			$i = 0;
			foreach ( $value as $key => $val ) {
				if ( is_array( $val ) ) {
					foreach ( $val as $k => $v ) {
						$new_index = $item_index . '_' . $i;
						self::erase_field_values( $v, $entry_id, $k, $new_index );
					}
					$i++;
				} else {
					GFAPI::update_entry_field( $entry_id, $key, '', $item_index );
				}
			}
		} else {
			GFAPI::update_entry_field( $entry_id, $input_id, '', $item_index );
		}

	}

	/**
	 * Returns the draft submissions (save and continue) for the given email address.
	 *
	 * @since 2.4
	 *
	 * @param $email_address
	 *
	 * @return array
	 */
	public static function get_draft_submissions( $email_address ) {

		$draft_submissions = GFFormsModel::get_draft_submissions();

		if ( empty( $draft_submissions ) ) {
			return array();
		}

		$user = get_user_by( 'email', $email_address );

		$return = array();

		$forms = self::get_forms();

		foreach ( $draft_submissions as $i => $draft_submission ) {

			$form_id = $draft_submission['form_id'];

			$form = $forms[ $form_id ];

			if ( ! rgars( $form, 'personalData/exportingAndErasing/enabled' ) ) {
				continue;
			}

			$submission_json = $draft_submission['submission'];

			$submission = json_decode( $submission_json, true );

			$entry = $submission['partial_entry'];

			$identification_field = rgars( $form, 'personalData/exportingAndErasing/identificationField' );

			$field = GFAPI::get_field( $form, $identification_field );

			if ( ( $field && $field->get_input_type() == 'email' && $entry[ (string) $identification_field ] === $email_address )
			     || ( $user && $user->ID == rgar( $entry, $identification_field ) )
			) {
				$return[] = $draft_submission;
			}
		}

		return $return;
	}

	/**
	 * Erases the data in the draft submissions.
	 *
	 * @since 2.4
	 *
	 * @param $email_address
	 *
	 * @return bool
	 */
	public static function erase_draft_submissions_data( $email_address ) {
		$items_removed = false;

		$forms = self::get_forms();

		$draft_entries = self::get_draft_submissions( $email_address );

		foreach ( $draft_entries as $draft_entry ) {

			$entry_dirty = false;

			$form_id = $draft_entry['form_id'];

			$resume_token = $draft_entry['uuid'];

			$date_created = $draft_entry['date_created'];

			$form = $forms[ $form_id ];

			$columns_settings = rgars( $forms, $form_id . '/personalData/exportingAndErasing/columns' );

			$submission_json = $draft_entry['submission'];

			$submission = json_decode( $submission_json, true );

			$entry = $submission['partial_entry'];

			$submitted_values = $submission['submitted_values'];

			if ( is_array( $columns_settings ) ) {
				foreach ( $columns_settings as $column_key => $column_settings ) {
					if ( rgar( $column_settings, 'erase' ) ) {
						if ( isset( $draft_entry[ $column_key ] ) ) {
							$draft_entry[ $column_key ] = '';
						}

						if ( isset( $entry[ $column_key ] ) ) {
							$entry[ $column_key ] = '';
						}

						$entry_dirty = true;
					}
				}
			}

			foreach ( $form['fields'] as $field ) {
				/* @var GF_Field $field */

				if ( $field->personalDataErase ) {

					$input_type = $field->get_input_type();

					$value = GFFormsModel::get_lead_field_value( $entry, $field );

					if ( is_array( $value ) ) {
						foreach ( $value as $k => $v ) {
							$entry[ $k ] = '';
							$submitted_values[ $field->id ][ $k ] = '';
						}
						$entry_dirty = true;
					} else {
						switch ( $input_type ) {
							case 'email':
								$anonymous = 'deleted@site.invalid';
								break;
							case 'website':
								$anonymous = 'https://site.invalid';
								break;
							case 'date':
								$anonymous = '0000-00-00';
								break;
							case 'text':
							case 'textarea':
								/* translators: deleted text */
								$anonymous = __( '[deleted]', 'gravityforms' );
								break;
							default:
								$anonymous = '';
						}
						$submitted_values[ (string) $field->id ] = $anonymous;
						$entry[ (string) $field->id ] = $anonymous;
						$entry_dirty = true;
					}
				}
			}

			$custom_items = self::get_custom_items( $form );

			if ( ! empty( $custom_items ) ) {
				$all_custom_settings = rgars( $forms, $form_id . '/personalData/exportingAndErasing/custom' );
				foreach ( $custom_items as $custom_item_key => $custom_item_details ) {
					$custom_settings = rgar( $all_custom_settings, $custom_item_key );
					if ( rgars( $custom_settings, 'erase' ) && isset( $custom_item_details['eraser_callback'] ) && is_callable( $custom_item_details['eraser_callback'] ) ) {
						call_user_func( $custom_item_details['eraser_callback'], $form, $entry );
						$items_removed = true;
					}
				}
			}

			if ( $entry_dirty ) {
				$submission['submitted_values'] = $submitted_values;
				$submission['partial_entry'] = $entry;
				$submission_json                = json_encode( $submission );
				GFFormsModel::update_draft_submission( $resume_token, $form, $date_created, $draft_entry['ip'], $draft_entry['source_url'], $submission_json );
				$items_removed = true;
			}
		}


		return $items_removed;
	}

	/**
	 * Deletes and trashes entries according to the retention policy in each of the form settings.
	 *
	 * @since 2.4
	 */
	public static function cron_task() {

		self::log_debug( __METHOD__ . '(): starting personal data cron task' );

		$forms = self::get_forms();

		$trash_form_ids   = array();
		$trash_conditions = array();

		$delete_form_ids   = array();
		$delete_conditions = array();

		foreach ( $forms as $form ) {

			$retention_policy = rgars( $form, 'personalData/retention/policy', 'retain' );

			if ( $retention_policy == 'retain' ) {
				continue;
			}

			$form_conditions = array();

			$retention_days = rgars( $form, 'personalData/retention/retain_entries_days' );

			$delete_timestamp = time() - ( DAY_IN_SECONDS * $retention_days );

			$delete_date = date( 'Y-m-d H:i:s', $delete_timestamp );

			$form_conditions[] = new GF_Query_Condition(
				new GF_Query_Column( 'date_created' ),
				GF_Query_Condition::LT,
				new GF_Query_Literal( $delete_date )
			);

			$form_conditions[] = new GF_Query_Condition(
				new GF_Query_Column( 'form_id' ),
				GF_Query_Condition::EQ,
				new GF_Query_Literal( $form['id'] )
			);

			if ( ! empty( $form_conditions ) ) {
				if ( $retention_policy == 'trash' ) {
					$trash_form_ids[] = $form['id'];
					$trash_conditions[] = call_user_func_array( array(
						'GF_Query_Condition',
						'_and',
					), $form_conditions );
				} elseif ( $retention_policy == 'delete' ) {
					$delete_form_ids[] = $form['id'];
					$delete_conditions[] = call_user_func_array( array(
						'GF_Query_Condition',
						'_and',
					), $form_conditions );
				}
			}
		}

		if ( ! empty( $trash_conditions ) ) {

			$query = new GF_Query();

			$all_trash_conditions = array();

			$all_trash_conditions[] = call_user_func_array( array( 'GF_Query_Condition', '_or' ), $trash_conditions );

			$all_trash_conditions[] = new GF_Query_Condition(
				new GF_Query_Column( 'status' ),
				GF_Query_Condition::NEQ,
				new GF_Query_Literal( 'trash' )
			);

			$all_trash_conditions = call_user_func_array( array( 'GF_Query_Condition', '_and' ), $all_trash_conditions );

			$entry_ids = $query->from( $trash_form_ids )->where( $all_trash_conditions )->get_ids();

			self::log_debug( __METHOD__ . '(): trashing entries: ' . join( ', ', $entry_ids ) );

			foreach ( $entry_ids as $entry_id ) {
				GFAPI::update_entry_property( $entry_id, 'status', 'trash' );
			}
		}

		if ( ! empty( $delete_conditions ) ) {

			$query = new GF_Query();

			$all_delete_conditions = call_user_func_array( array( 'GF_Query_Condition', '_or' ), $delete_conditions );

			$entry_ids = $query->from( $delete_form_ids )->where( $all_delete_conditions )->get_ids();

			self::log_debug( __METHOD__ . '(): deleting entries: ' . join( ', ', $entry_ids ) );

			/**
			 * Allows the array of entry IDs to be modified before automatically deleting according to the
			 * personal data retention policy.
			 *
			 * @since 2.4
			 *
			 * @param int[] $entry_ids The array of entry IDs to delete.
			 */
			$entry_ids = apply_filters( 'gform_entry_ids_automatic_deletion', $entry_ids );

			foreach ( $entry_ids as $entry_id ) {
				GFAPI::delete_entry( $entry_id );
			}
		}

		self::log_debug( __METHOD__ . '(): done' );

	}

	/**
	 * Writes a message to the debug log
	 *
	 * @since 2.4
	 *
	 * @param $message
	 */
	public static function log_debug( $message ) {
		GFCommon::log_debug( $message );
	}

	/**
	 * Flushes the forms
	 *
	 * @since 2.4
	 */
	public static function flush_current_forms() {
		self::$_forms = null;
	}
}
